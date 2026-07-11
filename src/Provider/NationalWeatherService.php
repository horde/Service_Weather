<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Provider;

use DateTimeImmutable;
use Horde\Service\Weather\CachingHttpClient;
use Horde\Service\Weather\Domain\CurrentWeather;
use Horde\Service\Weather\Domain\Forecast;
use Horde\Service\Weather\Domain\ForecastPeriod;
use Horde\Service\Weather\Domain\Station;
use Horde\Service\Weather\Domain\Wind;
use Horde\Service\Weather\Exception\ApiException;
use Horde\Service\Weather\Exception\InvalidLocationException;
use Horde\Service\Weather\Exception\StationNotFoundException;
use Horde\Service\Weather\ForecastCapabilities;
use Horde\Service\Weather\HourlyForecast;
use Horde\Service\Weather\StationLookup;
use Horde\Service\Weather\ValueObject\Coordinate;
use Horde\Service\Weather\ValueObject\ForecastDetail;
use Horde\Service\Weather\ValueObject\Humidity;
use Horde\Service\Weather\ValueObject\Location;
use Horde\Service\Weather\ValueObject\Pressure;
use Horde\Service\Weather\ValueObject\Speed;
use Horde\Service\Weather\ValueObject\Temperature;
use Horde\Service\Weather\ValueObject\WeatherCondition;
use Horde\Service\Weather\ValueObject\WeatherConfig;
use Horde\Service\Weather\ValueObject\WindDirection;
use Horde\Service\Weather\WeatherProvider;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * US National Weather Service (NWS) API provider.
 *
 * NOAA-run public API. US locations only. No API key required.
 * Custom User-Agent identifying the caller is required by NWS terms.
 *
 * Implements StationLookup: NWS observations are inherently
 * station-based (each `/points/{lat,lon}` resolves to the closest
 * observation stations, and each station has an ICAO-shaped id).
 *
 * API: https://www.weather.gov/documentation/services-web-api
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
class NationalWeatherService implements WeatherProvider, StationLookup, ForecastCapabilities, HourlyForecast
{
    private const API_BASE = 'https://api.weather.gov';

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
     * NWS grid forecast covers roughly 7 days.
     */
    public function getSupportedForecastLengths(): array
    {
        return [1, 2, 3, 4, 5, 6, 7];
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentWeather(Location|string $location): CurrentWeather
    {
        $coordinate = $this->normalizeLocation($location);

        $stations = $this->findStationsNear($coordinate, 1);
        if ($stations === []) {
            throw new ApiException('No weather stations found for this location');
        }

        return $this->fetchLatestObservation($stations[0], $location);
    }

    /**
     * {@inheritdoc}
     */
    public function getForecast(Location|string $location, int $days = 5): Forecast
    {
        $coordinate = $this->normalizeLocation($location);

        // Grid-based forecast. Comes off /points/, not off a station.
        $pointData = $this->fetchPointData($coordinate);
        $forecastUrl = $pointData['properties']['forecast'];
        $forecastData = $this->makeRequest($forecastUrl);

        return $this->parseForecast($forecastData, $location);
    }

    /**
     * {@inheritdoc}
     *
     * NWS's /gridpoints/{office}/{gx},{gy}/forecast/hourly endpoint provides
     * per-hour forecasts up to ~156 hours (~6.5 days). The URL is discovered
     * via /points/{lat,lon} on the `forecastHourly` property.
     */
    public function getHourlyForecast(Location $location, int $hours = 48): Forecast
    {
        if (!$location->hasCoordinates()) {
            throw new InvalidLocationException(
                'NWS requires coordinate-based locations'
            );
        }
        $coordinate = $location->getCoordinate();
        $hours = max(1, min($hours, 156));

        $pointData = $this->fetchPointData($coordinate);
        $hourlyUrl = $pointData['properties']['forecastHourly'] ?? null;
        if (!is_string($hourlyUrl) || $hourlyUrl === '') {
            throw new ApiException('NWS point response did not include forecastHourly URL');
        }

        $data = $this->makeRequest($hourlyUrl);

        return $this->parseHourlyForecast($data, $location, $hours);
    }

    /**
     * {@inheritdoc}
     */
    public function getStation(string $identifier): Station
    {
        $url = $this->buildUrl('/stations/' . rawurlencode($identifier));

        try {
            $data = $this->makeRequest($url);
        } catch (ApiException $e) {
            throw new StationNotFoundException(
                'NWS station not found: ' . $identifier,
                0,
                $e
            );
        }

        return $this->parseStation($data);
    }

    /**
     * {@inheritdoc}
     */
    public function findStationsNear(Coordinate $coordinate, int $limit = 5): array
    {
        $pointData = $this->fetchPointData($coordinate);

        $stationsUrl = $pointData['properties']['observationStations'] ?? null;
        if (!is_string($stationsUrl) || $stationsUrl === '') {
            return [];
        }

        $stationsData = $this->makeRequest($stationsUrl);
        $features = $stationsData['features'] ?? [];
        if ($features === []) {
            return [];
        }

        $stations = [];
        foreach (array_slice($features, 0, max(1, $limit)) as $feature) {
            $stations[] = $this->parseStation($feature);
        }

        return $stations;
    }

    /**
     * Fetch NWS point metadata (grid + station list URLs).
     *
     * @return array<string, mixed>
     */
    private function fetchPointData(Coordinate $coordinate): array
    {
        $pointUrl = $this->buildUrl('/points/' . $coordinate->latitude . ',' . $coordinate->longitude);

        return $this->makeRequest($pointUrl);
    }

    /**
     * Fetch and parse the latest observation for a station.
     */
    private function fetchLatestObservation(Station $station, Location|string $originalLocation): CurrentWeather
    {
        $observationUrl = $this->buildUrl('/stations/' . rawurlencode($station->identifier) . '/observations/latest');
        $obsData = $this->makeRequest($observationUrl);

        return $this->parseCurrentWeather($obsData, $originalLocation);
    }

    /**
     * Parse a NWS station feature or full-station document into a Station.
     */
    private function parseStation(array $data): Station
    {
        // /stations/{id} returns a Feature at the top; /observationStations
        // returns a FeatureCollection whose features[] are also Features.
        // Both have the same shape at this level.
        $props = $data['properties'] ?? [];
        $geometry = $data['geometry'] ?? [];

        $identifier = $props['stationIdentifier'] ?? ($props['identifier'] ?? '');
        if ($identifier === '' && isset($data['id'])) {
            $identifier = basename((string) $data['id']);
        }

        $name = $props['name'] ?? $identifier;

        $lat = 0.0;
        $lon = 0.0;
        if (isset($geometry['coordinates']) && is_array($geometry['coordinates'])) {
            // GeoJSON convention: [lon, lat].
            $lon = (float) ($geometry['coordinates'][0] ?? 0.0);
            $lat = (float) ($geometry['coordinates'][1] ?? 0.0);
        }

        $elevation = null;
        if (isset($props['elevation']['value'])) {
            $elevation = (float) $props['elevation']['value'];
        }

        $timezone = $props['timeZone'] ?? null;

        return new Station(
            identifier: (string) $identifier,
            name: (string) $name,
            coordinate: Coordinate::fromLatLon($lat, $lon),
            timezone: $timezone,
            elevation: $elevation,
        );
    }

    /**
     * Normalize location to coordinates.
     */
    private function normalizeLocation(Location|string $location): Coordinate
    {
        if (is_string($location)) {
            $parts = explode(',', $location);
            if (count($parts) !== 2) {
                throw new InvalidLocationException(
                    'Location string must be in format "latitude,longitude"'
                );
            }
            return Coordinate::fromLatLon((float) trim($parts[0]), (float) trim($parts[1]));
        }

        if (!$location->hasCoordinates()) {
            throw new InvalidLocationException(
                'NWS requires coordinate-based locations'
            );
        }

        return $location->getCoordinate();
    }

    /**
     * Build API URL.
     */
    private function buildUrl(string $path): string
    {
        return self::API_BASE . $path;
    }

    /**
     * Make HTTP request to API.
     */
    private function makeRequest(string $url): array
    {
        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader(
                'User-Agent',
                $this->config->userAgent ?? 'Horde_Service_Weather (https://www.horde.org/)',
            );

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new ApiException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }

        $body = (string) $response->getBody();
        $status = $response->getStatusCode();

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new ApiException('Invalid JSON response from NWS API');
        }

        // NWS embeds problem details in the body under `status`/`title`/`detail`
        // and mirrors the HTTP status code there. Prefer that when present;
        // fall back to the HTTP status for other error shapes.
        if (isset($data['status']) && $data['status'] >= 400) {
            $message = $data['title'] ?? $data['detail'] ?? 'Unknown error';
            throw new ApiException('NWS API error: ' . $message);
        }
        if ($status >= 400) {
            throw new ApiException('NWS HTTP ' . $status);
        }

        return $data;
    }

    /**
     * Parse current weather from observation.
     */
    private function parseCurrentWeather(array $data, Location|string $originalLocation): CurrentWeather
    {
        $props = $data['properties'];

        $location = is_string($originalLocation)
            ? Location::fromCoordinates(
                $data['geometry']['coordinates'][1],
                $data['geometry']['coordinates'][0]
            )
            : $originalLocation;

        // NWS returns values with units in the structure
        $temp = $this->parseValue($props['temperature']);
        $feelsLike = $this->parseValue($props['windChill'] ?? $props['heatIndex'] ?? null);
        $dewpoint = $this->parseValue($props['dewpoint']);

        return new CurrentWeather(
            location: $location,
            temperature: $temp,
            condition: $this->mapWeatherCondition($props['textDescription']),
            observationTime: new DateTimeImmutable($props['timestamp']),
            feelsLike: $feelsLike,
            humidity: isset($props['relativeHumidity']['value'])
                ? Humidity::fromPercentage((int) round($props['relativeHumidity']['value']))
                : null,
            pressure: isset($props['barometricPressure']['value'])
                ? Pressure::fromMillibars($props['barometricPressure']['value'] / 100) // Convert Pa to hPa
                : null,
            wind: $this->parseWind($props),
            visibility: isset($props['visibility']['value'])
                ? $props['visibility']['value'] / 1000 // Convert m to km
                : null,
            cloudCover: isset($props['cloudLayers'][0])
                ? $this->parseCloudCover($props['cloudLayers'])
                : null
        );
    }

    /**
     * Parse forecast periods.
     */
    private function parseForecast(array $data, Location|string $originalLocation): Forecast
    {
        $location = is_string($originalLocation)
            ? Location::fromCoordinates(
                $data['geometry']['coordinates'][0][0][1],
                $data['geometry']['coordinates'][0][0][0]
            )
            : $originalLocation;

        // NWS returns periods (day/night), we need to group by day
        $periods = [];
        $dailyData = [];

        foreach ($data['properties']['periods'] as $period) {
            $date = (new DateTimeImmutable($period['startTime']))->format('Y-m-d');

            if (!isset($dailyData[$date])) {
                $dailyData[$date] = ['high' => null, 'low' => null, 'periods' => []];
            }

            $dailyData[$date]['periods'][] = $period;

            if ($period['isDaytime']) {
                $dailyData[$date]['high'] = $period['temperature'];
            } else {
                $dailyData[$date]['low'] = $period['temperature'];
            }
        }

        foreach ($dailyData as $date => $day) {
            // Use the first period for general conditions
            $mainPeriod = $day['periods'][0];

            $high = $day['high'] ?? $mainPeriod['temperature'];
            $low = $day['low'] ?? $mainPeriod['temperature'];
            $avg = ($high + $low) / 2;

            $tempHigh = Temperature::fromFahrenheit($high);
            $tempLow = Temperature::fromFahrenheit($low);
            $tempAvg = Temperature::fromFahrenheit($avg);

            $wind = null;
            if (isset($mainPeriod['windSpeed']) && $mainPeriod['windSpeed'] !== '') {
                $windSpeed = $this->parseWindSpeed($mainPeriod['windSpeed']);
                $windDir = $this->parseWindDirection($mainPeriod['windDirection'] ?? null);
                $wind = new Wind($windSpeed, $windDir);
            }

            $periods[] = new ForecastPeriod(
                date: new DateTimeImmutable($date),
                temperature: $tempAvg,
                condition: $this->mapWeatherCondition($mainPeriod['shortForecast']),
                highTemperature: $tempHigh,
                lowTemperature: $tempLow,
                wind: $wind,
                precipitationProbability: isset($mainPeriod['probabilityOfPrecipitation']['value'])
                    ? $mainPeriod['probabilityOfPrecipitation']['value'] / 100
                    : null
            );
        }

        return new Forecast($location, $periods);
    }

    /**
     * Parse NWS /forecast/hourly response.
     *
     * Each `properties.periods[]` entry is a 1-hour block. Capped at
     * `$maxHours` to honor the requested horizon.
     */
    private function parseHourlyForecast(array $data, Location $originalLocation, int $maxHours): Forecast
    {
        $periods = [];
        foreach ($data['properties']['periods'] ?? [] as $i => $period) {
            if ($i >= $maxHours) {
                break;
            }

            $tempUnit = $period['temperatureUnit'] ?? 'F';
            $temp = $tempUnit === 'C'
                ? Temperature::fromCelsius((float) $period['temperature'])
                : Temperature::fromFahrenheit((float) $period['temperature']);

            $wind = null;
            if (isset($period['windSpeed']) && $period['windSpeed'] !== '') {
                $windSpeed = $this->parseWindSpeed($period['windSpeed']);
                $windDir = $this->parseWindDirection($period['windDirection'] ?? null);
                $wind = new Wind($windSpeed, $windDir);
            }

            $periods[] = new ForecastPeriod(
                date: new DateTimeImmutable($period['startTime']),
                temperature: $temp,
                condition: $this->mapWeatherCondition($period['shortForecast'] ?? ''),
                humidity: isset($period['relativeHumidity']['value'])
                    ? Humidity::fromPercentage((int) round($period['relativeHumidity']['value']))
                    : null,
                wind: $wind,
                precipitationProbability: isset($period['probabilityOfPrecipitation']['value'])
                    ? $period['probabilityOfPrecipitation']['value'] / 100
                    : null,
            );
        }

        return new Forecast($originalLocation, $periods, ForecastDetail::DETAILED);
    }

    /**
     * Parse NWS value structure (value + unitCode).
     */
    private function parseValue(?array $value): ?Temperature
    {
        if (!$value || !isset($value['value'])) {
            return null;
        }

        // NWS uses unit codes like "wmoUnit:degC"
        $unit = $value['unitCode'] ?? '';

        if (str_contains($unit, 'degC')) {
            return Temperature::fromCelsius($value['value']);
        }

        if (str_contains($unit, 'degF')) {
            return Temperature::fromFahrenheit($value['value']);
        }

        // Default to Celsius
        return Temperature::fromCelsius($value['value']);
    }

    /**
     * Parse wind data.
     */
    private function parseWind(array $props): ?Wind
    {
        if (!isset($props['windSpeed']['value'])) {
            return null;
        }

        // Wind speed in km/h
        $speed = Speed::fromKilometersPerHour($props['windSpeed']['value']);

        $direction = isset($props['windDirection']['value'])
            ? WindDirection::fromDegrees($props['windDirection']['value'])
            : WindDirection::VARIABLE;

        $gusts = isset($props['windGust']['value'])
            ? Speed::fromKilometersPerHour($props['windGust']['value'])
            : null;

        return new Wind($speed, $direction, $gusts, $props['windDirection']['value'] ?? null);
    }

    /**
     * Parse an NWS wind-direction abbreviation into a WindDirection.
     *
     * NWS uses 16-point compass names ("NNE", "ENE", ...) in its forecast
     * periods, while WindDirection is an 8-point enum. Intermediate points
     * fold to the nearest 8-point value; unrecognized inputs (including
     * null and empty) fall through to VARIABLE.
     */
    private function parseWindDirection(?string $abbrev): WindDirection
    {
        $s = strtoupper(trim((string) $abbrev));

        return match ($s) {
            'N' => WindDirection::N,
            'NNE', 'NE', 'ENE' => WindDirection::NE,
            'E' => WindDirection::E,
            'ESE', 'SE', 'SSE' => WindDirection::SE,
            'S' => WindDirection::S,
            'SSW', 'SW', 'WSW' => WindDirection::SW,
            'W' => WindDirection::W,
            'WNW', 'NW', 'NNW' => WindDirection::NW,
            default => WindDirection::VARIABLE,
        };
    }

    /**
     * Parse wind speed from string like "15 mph" or "10 to 15 mph".
     */
    private function parseWindSpeed(string $windSpeed): Speed
    {
        // Extract first number
        if (preg_match('/(\d+)/', $windSpeed, $matches)) {
            $speed = (float) $matches[1];

            if (str_contains($windSpeed, 'mph')) {
                return Speed::fromMilesPerHour($speed);
            }

            if (str_contains($windSpeed, 'km')) {
                return Speed::fromKilometersPerHour($speed);
            }

            // Default to mph (NWS uses imperial)
            return Speed::fromMilesPerHour($speed);
        }

        return Speed::fromMilesPerHour(0);
    }

    /**
     * Parse cloud cover from cloud layers.
     */
    private function parseCloudCover(array $cloudLayers): int
    {
        // Use the highest coverage
        $maxCoverage = 0;

        foreach ($cloudLayers as $layer) {
            $amount = $layer['amount'] ?? '';
            $coverage = match ($amount) {
                'CLR', 'SKC' => 0,
                'FEW' => 25,
                'SCT' => 50,
                'BKN' => 75,
                'OVC' => 100,
                default => 0,
            };

            $maxCoverage = max($maxCoverage, $coverage);
        }

        return $maxCoverage;
    }

    /**
     * Map NWS text description to WeatherCondition.
     */
    private function mapWeatherCondition(string $description): WeatherCondition
    {
        $desc = strtolower($description);

        return match (true) {
            str_contains($desc, 'sunny') || str_contains($desc, 'clear') => WeatherCondition::CLEAR,
            str_contains($desc, 'partly') || str_contains($desc, 'scattered') => WeatherCondition::PARTLY_CLOUDY,
            str_contains($desc, 'mostly cloudy') || str_contains($desc, 'broken') => WeatherCondition::CLOUDY,
            str_contains($desc, 'overcast') || str_contains($desc, 'cloudy') => WeatherCondition::OVERCAST,
            str_contains($desc, 'fog') => WeatherCondition::FOG,
            str_contains($desc, 'drizzle') => WeatherCondition::DRIZZLE,
            str_contains($desc, 'rain') || str_contains($desc, 'showers') => WeatherCondition::RAIN,
            str_contains($desc, 'freezing rain') || str_contains($desc, 'ice') => WeatherCondition::FREEZING_RAIN,
            str_contains($desc, 'snow') || str_contains($desc, 'flurries') => WeatherCondition::SNOW,
            str_contains($desc, 'sleet') || str_contains($desc, 'wintry') => WeatherCondition::SLEET,
            str_contains($desc, 'thunder') || str_contains($desc, 'storm') => WeatherCondition::THUNDERSTORM,
            str_contains($desc, 'hail') => WeatherCondition::HAIL,
            str_contains($desc, 'tornado') => WeatherCondition::TORNADO,
            str_contains($desc, 'hurricane') => WeatherCondition::HURRICANE,
            default => WeatherCondition::UNKNOWN,
        };
    }
}
