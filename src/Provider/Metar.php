<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Provider;

use DateTimeImmutable;
use Horde\Service\Weather\Domain\CurrentWeather;
use Horde\Service\Weather\Domain\Forecast;
use Horde\Service\Weather\Domain\ForecastPeriod;
use Horde\Service\Weather\Domain\Station;
use Horde\Service\Weather\Domain\Wind;
use Horde\Service\Weather\Exception\ApiException;
use Horde\Service\Weather\Exception\InvalidLocationException;
use Horde\Service\Weather\Exception\StationNotFoundException;
use Horde\Service\Weather\ForecastCapabilities;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Horde\Service\Weather\CachingHttpClient;
use Horde\Service\Weather\StationLookup;
use Horde\Service\Weather\ValueObject\Coordinate;
use Horde\Service\Weather\ValueObject\ForecastDetail;
use Horde\Service\Weather\ValueObject\Location;
use Horde\Service\Weather\ValueObject\Pressure;
use Horde\Service\Weather\ValueObject\Speed;
use Horde\Service\Weather\ValueObject\Temperature;
use Horde\Service\Weather\ValueObject\WeatherCondition;
use Horde\Service\Weather\ValueObject\WeatherConfig;
use Horde\Service\Weather\ValueObject\WindDirection;
use Horde\Service\Weather\WeatherProvider;

/**
 * aviationweather.gov METAR/TAF provider.
 *
 * The international standard aviation-weather source, run by the US
 * National Weather Service Aviation Weather Center. Provides METAR
 * (current) and TAF (forecast) observations for airports worldwide,
 * keyed by ICAO code.
 *
 * - No API key
 * - Rate-limited to 100 req/min per user; custom User-Agent requested
 * - Global coverage of ICAO-registered stations
 * - JSON responses for `format=json`
 * - Invalid ICAO codes return an empty body (not a JSON error)
 *
 * Implements WeatherProvider (getCurrentWeather/getForecast accept
 * Location and resolve to a station), StationLookup (getStation by
 * ICAO, findStationsNear by coordinate via bbox) and ForecastCapabilities
 * (TAF horizon is ~30 hours from the issue time, effectively 1-2 days).
 *
 * Also exposes provider-specific ICAO-shaped convenience methods:
 * `getCurrentWeatherByIcao()` and `getForecastByIcao()`.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
class Metar implements WeatherProvider, StationLookup, ForecastCapabilities
{
    private const API_BASE = 'https://aviationweather.gov/api/data';
    private const DEFAULT_UA = 'horde-service-weather/3.0 (+https://www.horde.org/)';

    /**
     * Half-side of the default bounding box for findStationsNear(),
     * in degrees latitude/longitude. ~1° ≈ 110km at the equator.
     */
    private const NEAR_BBOX_DEGREES = 1.0;

    private readonly ClientInterface $httpClient;
    private readonly RequestFactoryInterface $requestFactory;

    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        private readonly WeatherConfig $config = new WeatherConfig(),
        ?ResponseFactoryInterface $responseFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->requestFactory = $requestFactory;
        if ($config->cache !== null && $responseFactory !== null && $streamFactory !== null) {
            $this->httpClient = new CachingHttpClient(
                $httpClient,
                $responseFactory,
                $streamFactory,
                $config->cache,
                $config->cacheLifetime,
            );
        } else {
            $this->httpClient = $httpClient;
        }
    }

    /**
     * {@inheritdoc}
     *
     * TAF forecasts cover ~24-30 hours from issue. In days this rounds to 1-2.
     */
    public function getSupportedForecastLengths(): array
    {
        return [1, 2];
    }

    /**
     * {@inheritdoc}
     *
     * Location must either carry an ICAO identifier or coordinates
     * (which resolve via findStationsNear).
     */
    public function getCurrentWeather(Location|string $location): CurrentWeather
    {
        $icao = $this->resolveIcao($location);

        return $this->getCurrentWeatherByIcao($icao);
    }

    /**
     * {@inheritdoc}
     */
    public function getForecast(Location|string $location, int $days = 5): Forecast
    {
        $icao = $this->resolveIcao($location);

        return $this->getForecastByIcao($icao, $days);
    }

    /**
     * Get current METAR observation for a specific ICAO code.
     *
     * @throws StationNotFoundException When the ICAO code returns no data.
     * @throws ApiException On generic provider / transport failures.
     */
    public function getCurrentWeatherByIcao(string $icao): CurrentWeather
    {
        $icao = strtoupper(trim($icao));
        $url = $this->buildUrl('/metar', ['ids' => $icao, 'format' => 'json']);

        $entries = $this->makeRequestList($url);
        if ($entries === []) {
            throw new StationNotFoundException("METAR not found for ICAO: $icao");
        }

        return $this->parseCurrentWeather($entries[0]);
    }

    /**
     * Get TAF forecast for a specific ICAO code.
     *
     * @param string $icao ICAO airport code.
     * @param int $days Approximate horizon in days; TAF natively covers ~1-2.
     * @throws StationNotFoundException When the ICAO code returns no data.
     * @throws ApiException On generic provider / transport failures.
     */
    public function getForecastByIcao(string $icao, int $days = 2): Forecast
    {
        $icao = strtoupper(trim($icao));
        $url = $this->buildUrl('/taf', ['ids' => $icao, 'format' => 'json']);

        $entries = $this->makeRequestList($url);
        if ($entries === []) {
            throw new StationNotFoundException("TAF not found for ICAO: $icao");
        }

        return $this->parseForecast($entries[0], $days);
    }

    /**
     * {@inheritdoc}
     */
    public function getStation(string $identifier): Station
    {
        $icao = strtoupper(trim($identifier));
        $url = $this->buildUrl('/stationinfo', ['ids' => $icao, 'format' => 'json']);

        $entries = $this->makeRequestList($url);
        if ($entries === []) {
            throw new StationNotFoundException("Station not found: $icao");
        }

        return $this->parseStation($entries[0]);
    }

    /**
     * {@inheritdoc}
     *
     * Uses aviationweather.gov's `bbox=lat0,lon0,lat1,lon1` query. Filters
     * out non-ICAO entries (buoys, WMO sites without ICAO codes) and sorts
     * client-side by squared Euclidean distance from the query coordinate.
     */
    public function findStationsNear(Coordinate $coordinate, int $limit = 5): array
    {
        $d = self::NEAR_BBOX_DEGREES;
        $bbox = sprintf(
            '%s,%s,%s,%s',
            $coordinate->latitude - $d,
            $coordinate->longitude - $d,
            $coordinate->latitude + $d,
            $coordinate->longitude + $d,
        );
        $url = $this->buildUrl('/stationinfo', ['bbox' => $bbox, 'format' => 'json']);

        $entries = $this->makeRequestList($url);

        // Keep only entries with an actual ICAO code. Bbox returns buoys
        // and other siteless observation points too.
        $withIcao = array_filter(
            $entries,
            fn($e) => is_array($e) && !empty($e['icaoId']),
        );

        // Sort by squared distance from the query coord. Close enough for
        // limit-N triage without a real haversine call.
        usort(
            $withIcao,
            function ($a, $b) use ($coordinate) {
                $da = (($a['lat'] ?? 0) - $coordinate->latitude) ** 2
                    + (($a['lon'] ?? 0) - $coordinate->longitude) ** 2;
                $db = (($b['lat'] ?? 0) - $coordinate->latitude) ** 2
                    + (($b['lon'] ?? 0) - $coordinate->longitude) ** 2;
                return $da <=> $db;
            },
        );

        $limit = max(1, $limit);
        $stations = [];
        foreach (array_slice($withIcao, 0, $limit) as $entry) {
            $stations[] = $this->parseStation($entry);
        }

        return $stations;
    }

    /**
     * Resolve a Location argument (or "lat,lon" string) to an ICAO code.
     *
     * Priority:
     *  1. Location::$identifier. If non-empty, use it directly.
     *  2. Location with coordinates. FindStationsNear() first ICAO station.
     *  3. "lat,lon" string. Parse then step 2.
     */
    private function resolveIcao(Location|string $location): string
    {
        if (is_string($location)) {
            $parts = explode(',', $location);
            if (count($parts) !== 2) {
                throw new InvalidLocationException(
                    'Location string must be in format "latitude,longitude"'
                );
            }
            $coord = Coordinate::fromLatLon((float) trim($parts[0]), (float) trim($parts[1]));

            return $this->firstNearbyIcao($coord);
        }

        if ($location->identifier !== null && $location->identifier !== '') {
            return strtoupper($location->identifier);
        }

        if (!$location->hasCoordinates()) {
            throw new InvalidLocationException(
                'Metar requires either an ICAO identifier or coordinates on Location'
            );
        }

        return $this->firstNearbyIcao($location->getCoordinate());
    }

    private function firstNearbyIcao(Coordinate $coord): string
    {
        $stations = $this->findStationsNear($coord, 1);
        if ($stations === []) {
            throw new StationNotFoundException(
                sprintf('No aviation station near %s,%s', $coord->latitude, $coord->longitude)
            );
        }

        return $stations[0]->identifier;
    }

    /**
     * Parse a METAR entry into a CurrentWeather.
     */
    private function parseCurrentWeather(array $entry): CurrentWeather
    {
        $station = $this->parseStation($entry);
        $location = Location::fromCoordinate($station->coordinate);

        $obsTime = isset($entry['reportTime'])
            ? new DateTimeImmutable($entry['reportTime'])
            : (isset($entry['obsTime']) ? new DateTimeImmutable('@' . $entry['obsTime']) : new DateTimeImmutable());

        $wind = null;
        if (isset($entry['wspd'])) {
            $windSpeed = Speed::fromKnots((float) $entry['wspd']);
            $windDir = isset($entry['wdir']) && is_numeric($entry['wdir'])
                ? WindDirection::fromDegrees((float) $entry['wdir'])
                : WindDirection::VARIABLE;
            $gusts = isset($entry['wgst']) && is_numeric($entry['wgst'])
                ? Speed::fromKnots((float) $entry['wgst'])
                : null;
            $wind = new Wind(
                $windSpeed,
                $windDir,
                $gusts,
                degrees: is_numeric($entry['wdir'] ?? null) ? (float) $entry['wdir'] : null,
            );
        }

        return new CurrentWeather(
            location: $location,
            temperature: Temperature::fromCelsius((float) ($entry['temp'] ?? 0)),
            condition: $this->mapCoverToCondition($entry['cover'] ?? '', $entry['wxString'] ?? null),
            observationTime: $obsTime,
            pressure: isset($entry['altim']) && is_numeric($entry['altim'])
                ? Pressure::fromMillibars((float) $entry['altim'])
                : null,
            wind: $wind,
            visibility: $this->parseVisibilityMiles($entry['visib'] ?? null),
            cloudCover: $this->parseCloudCover($entry['clouds'] ?? []),
            providerData: $entry['rawOb'] ?? null,
            dewpoint: isset($entry['dewp']) && is_numeric($entry['dewp'])
                ? Temperature::fromCelsius((float) $entry['dewp'])
                : null,
            station: $station,
        );
    }

    /**
     * Parse a TAF entry into a Forecast.
     *
     * $days is honored by truncating to periods that start within the
     * requested window from `validTimeFrom`.
     */
    private function parseForecast(array $entry, int $days): Forecast
    {
        $station = $this->parseStation($entry);
        $location = Location::fromCoordinate($station->coordinate);

        $validFrom = $entry['validTimeFrom'] ?? null;
        $cutoff = is_int($validFrom) ? $validFrom + $days * 86400 : PHP_INT_MAX;

        $periods = [];
        foreach ($entry['fcsts'] ?? [] as $fcst) {
            $from = $fcst['timeFrom'] ?? null;
            if (!is_int($from) || $from > $cutoff) {
                continue;
            }

            $wind = null;
            if (isset($fcst['wspd']) && is_numeric($fcst['wspd'])) {
                $windSpeed = Speed::fromKnots((float) $fcst['wspd']);
                $windDir = isset($fcst['wdir']) && is_numeric($fcst['wdir'])
                    ? WindDirection::fromDegrees((float) $fcst['wdir'])
                    : WindDirection::VARIABLE;
                $gusts = isset($fcst['wgst']) && is_numeric($fcst['wgst'])
                    ? Speed::fromKnots((float) $fcst['wgst'])
                    : null;
                $wind = new Wind($windSpeed, $windDir, $gusts);
            }

            $topCover = $this->pickTopCloudCover($fcst['clouds'] ?? []);
            $periods[] = new ForecastPeriod(
                date: new DateTimeImmutable('@' . $from),
                // TAF fcsts have no temp for aviation-standard periods.
                // seed with a nominal 0°C so the required Temperature slot
                // is populated; callers rely on daily forecasts elsewhere
                // if they want real temperature data. wxString gives the
                // condition; clouds give cover.
                temperature: Temperature::fromCelsius(0.0),
                condition: $this->mapCoverToCondition($topCover, $fcst['wxString'] ?? null),
                wind: $wind,
                cloudCover: $this->parseCloudCover($fcst['clouds'] ?? []),
            );
        }

        // TAF is inherently detailed / change-groups, not daily-summarized.
        return new Forecast($location, $periods, ForecastDetail::DETAILED);
    }

    /**
     * Parse a stationinfo entry into a Station.
     *
     * Handles both /stationinfo results (with `site`) and /metar or /taf
     * results (with `name`). Both include `icaoId`, `lat`, `lon`, `elev`.
     */
    private function parseStation(array $entry): Station
    {
        $icao = (string) ($entry['icaoId'] ?? $entry['id'] ?? '');
        $name = (string) ($entry['site'] ?? $entry['name'] ?? $icao);
        $lat = (float) ($entry['lat'] ?? 0.0);
        $lon = (float) ($entry['lon'] ?? 0.0);
        $elev = isset($entry['elev']) && is_numeric($entry['elev'])
            ? (float) $entry['elev']
            : null;

        return new Station(
            identifier: $icao,
            name: $name,
            coordinate: Coordinate::fromLatLon($lat, $lon),
            elevation: $elev,
        );
    }

    /**
     * @return array<int, mixed>
     */
    private function makeRequestList(string $url): array
    {
        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('User-Agent', $this->config->userAgent ?? self::DEFAULT_UA);

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new ApiException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }

        $body = (string) $response->getBody();

        // aviationweather.gov returns 0 bytes (not `[]`) for unknown ICAOs.
        if ($body === '' || trim($body) === '') {
            return [];
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new ApiException('Invalid JSON response from aviationweather.gov');
        }

        $status = $response->getStatusCode();
        if ($status >= 400) {
            throw new ApiException('aviationweather.gov HTTP ' . $status);
        }

        return $data;
    }

    private function buildUrl(string $endpoint, array $params): string
    {
        return self::API_BASE . $endpoint . '?' . http_build_query($params);
    }

    /**
     * Parse METAR visibility strings ("10SM", "6+", "1/4SM", "9999") to km.
     *
     * Returns null when parsing fails or input is missing. Values with "SM"
     * suffix are converted from statute miles; bare numbers ≥100 are treated
     * as meters (aviation ICAO code convention); the "6+" style means "at
     * least 6 miles" and is mapped to 6.
     */
    private function parseVisibilityMiles(mixed $value): ?float
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $v = trim($value);
        // "6+" → 6 statute miles → km.
        if (str_ends_with($v, '+')) {
            $miles = (float) rtrim($v, '+');

            return $miles * 1.609344;
        }
        if (stripos($v, 'SM') !== false) {
            $miles = (float) str_ireplace('SM', '', $v);

            return $miles * 1.609344;
        }
        if (is_numeric($v)) {
            $num = (float) $v;

            // Bare 9999 / 8000 / etc: ICAO meters.
            return $num >= 100 ? $num / 1000 : $num;
        }

        return null;
    }

    /**
     * Convert a cloud-layers array to a percentage cover using METAR
     * amount codes (CLR=0, FEW=25, SCT=50, BKN=75, OVC=100). Takes the
     * densest layer.
     */
    private function parseCloudCover(array $clouds): int
    {
        $max = 0;
        foreach ($clouds as $layer) {
            $amount = strtoupper((string) ($layer['cover'] ?? ''));
            $pct = match ($amount) {
                'CLR', 'SKC', 'NCD', 'NSC' => 0,
                'FEW' => 25,
                'SCT' => 50,
                'BKN' => 75,
                'OVC', 'VV' => 100,
                default => 0,
            };
            $max = max($max, $pct);
        }

        return $max;
    }

    /**
     * Pick the top-level cloud cover code from a cloud-layers array.
     * Used to seed the WeatherCondition mapping when no explicit cover
     * field is present (as in TAF `fcsts[]`).
     */
    private function pickTopCloudCover(array $clouds): string
    {
        $best = '';
        $bestPct = -1;
        foreach ($clouds as $layer) {
            $amount = strtoupper((string) ($layer['cover'] ?? ''));
            $pct = match ($amount) {
                'CLR', 'SKC', 'NCD', 'NSC' => 0,
                'FEW' => 25,
                'SCT' => 50,
                'BKN' => 75,
                'OVC', 'VV' => 100,
                default => -1,
            };
            if ($pct > $bestPct) {
                $bestPct = $pct;
                $best = $amount;
            }
        }

        return $best;
    }

    /**
     * Combine METAR cloud-cover code and optional wxString into a
     * WeatherCondition band.
     *
     * wxString takes priority when it names a precip / storm / fog phenomenon.
     */
    private function mapCoverToCondition(string $cover, ?string $wxString): WeatherCondition
    {
        $wx = strtoupper((string) $wxString);
        if ($wx !== '') {
            // Precip / storm phenomena parse from wxString tokens.
            return match (true) {
                str_contains($wx, 'TS') => WeatherCondition::THUNDERSTORM,
                str_contains($wx, 'GR') || str_contains($wx, 'GS') => WeatherCondition::HAIL,
                str_contains($wx, 'FZRA') => WeatherCondition::FREEZING_RAIN,
                str_contains($wx, 'PL'), str_contains($wx, 'IC') => WeatherCondition::SLEET,
                str_contains($wx, 'SN') => WeatherCondition::SNOW,
                str_contains($wx, 'DZ') => WeatherCondition::DRIZZLE,
                str_contains($wx, 'RA') => WeatherCondition::RAIN,
                str_contains($wx, 'FG'), str_contains($wx, 'BR') => WeatherCondition::FOG,
                default => $this->coverToCondition($cover),
            };
        }

        return $this->coverToCondition($cover);
    }

    private function coverToCondition(string $cover): WeatherCondition
    {
        return match (strtoupper($cover)) {
            'CLR', 'SKC', 'NCD', 'NSC' => WeatherCondition::CLEAR,
            'FEW' => WeatherCondition::PARTLY_CLOUDY,
            'SCT' => WeatherCondition::PARTLY_CLOUDY,
            'BKN' => WeatherCondition::CLOUDY,
            'OVC' => WeatherCondition::OVERCAST,
            default => WeatherCondition::UNKNOWN,
        };
    }
}
