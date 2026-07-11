<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Provider;

use DateTimeImmutable;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Horde\Service\Weather\CachingHttpClient;
use Horde\Service\Weather\AirQualityProvider;
use Horde\Service\Weather\AstronomyProvider;
use Horde\Service\Weather\Domain\AirQuality;
use Horde\Service\Weather\Domain\Astronomy;
use Horde\Service\Weather\Domain\CurrentWeather;
use Horde\Service\Weather\Domain\Forecast;
use Horde\Service\Weather\Domain\ForecastPeriod;
use Horde\Service\Weather\Domain\Wind;
use Horde\Service\Weather\Exception\ApiException;
use Horde\Service\Weather\Exception\InvalidApiKeyException;
use Horde\Service\Weather\Exception\InvalidLocationException;
use Horde\Service\Weather\ValueObject\AirQualityCategory;
use Horde\Service\Weather\ValueObject\Coordinate;
use Horde\Service\Weather\ValueObject\ForecastDetail;
use Horde\Service\Weather\ValueObject\Humidity;
use Horde\Service\Weather\ValueObject\Location;
use Horde\Service\Weather\ValueObject\MoonPhase;
use Horde\Service\Weather\ValueObject\Pressure;
use Horde\Service\Weather\ValueObject\SearchType;
use Horde\Service\Weather\ValueObject\Speed;
use Horde\Service\Weather\ValueObject\Temperature;
use Horde\Service\Weather\ValueObject\WeatherCondition;
use Horde\Service\Weather\ValueObject\WeatherConfig;
use Horde\Service\Weather\ValueObject\WindDirection;
use Horde\Service\Weather\ForecastCapabilities;
use Horde\Service\Weather\HourlyForecast;
use Horde\Service\Weather\LocationSearch;
use Horde\Service\Weather\WeatherProvider;
use DateTimeZone;
use Exception;

/**
 * WeatherAPI.com weather provider.
 *
 * Commercial weather API. Free tier allows 1 million calls/month.
 * Requires an API key from https://www.weatherapi.com/
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
class WeatherApi implements WeatherProvider, ForecastCapabilities, HourlyForecast, LocationSearch, AirQualityProvider, AstronomyProvider
{
    private const API_BASE = 'https://api.weatherapi.com/v1';

    private readonly ClientInterface $httpClient;
    private readonly RequestFactoryInterface $requestFactory;

    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        private readonly WeatherConfig $config,
        ?ResponseFactoryInterface $responseFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        if (!$this->config->apiKey) {
            throw new InvalidApiKeyException('WeatherAPI.com requires an API key');
        }
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
     * WeatherAPI.com's /forecast.json accepts 1..14 days.
     */
    public function getSupportedForecastLengths(): array
    {
        return range(1, 14);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentWeather(Location|string $location): CurrentWeather
    {
        $query = $this->buildLocationQuery($location);

        $url = $this->buildUrl('/current.json', [
            'key' => $this->config->apiKey,
            'q' => $query,
        ]);

        $data = $this->makeRequest($url);

        return $this->parseCurrentWeather($data, $location);
    }

    /**
     * {@inheritdoc}
     *
     * Uses /astronomy.json which returns sun/moon rise-set plus discrete
     * moon phase name and illumination percentage.
     */
    public function getAstronomy(Location $location, ?DateTimeImmutable $date = null): Astronomy
    {
        if (!$location->hasCoordinates()) {
            throw new InvalidLocationException(
                'AstronomyProvider requires coordinate-based locations'
            );
        }
        $coord = $location->getCoordinate();
        $date ??= new DateTimeImmutable('today');

        $url = $this->buildUrl('/astronomy.json', [
            'key' => $this->config->apiKey,
            'q' => $coord->latitude . ',' . $coord->longitude,
            'dt' => $date->format('Y-m-d'),
        ]);

        $data = $this->makeRequest($url);
        $astro = $data['astronomy']['astro'] ?? null;
        if (!is_array($astro)) {
            throw new ApiException('WeatherAPI response did not include astronomy.astro');
        }

        $dateStr = $date->format('Y-m-d');
        $tzId = $data['location']['tz_id'] ?? null;
        $tz = $tzId ? new DateTimeZone($tzId) : $date->getTimezone();

        return new Astronomy(
            location: $location,
            date: $date,
            sunrise: $this->parseAstroTime($astro['sunrise'] ?? null, $dateStr, $tz),
            sunset: $this->parseAstroTime($astro['sunset'] ?? null, $dateStr, $tz),
            moonrise: $this->parseAstroTime($astro['moonrise'] ?? null, $dateStr, $tz),
            moonset: $this->parseAstroTime($astro['moonset'] ?? null, $dateStr, $tz),
            moonPhase: $this->mapMoonPhase($astro['moon_phase'] ?? ''),
            moonIllumination: isset($astro['moon_illumination'])
                ? (int) $astro['moon_illumination']
                : null,
        );
    }

    /**
     * Parse WeatherAPI's "HH:MM AM" astro time into a full DateTimeImmutable.
     *
     * Returns null when the value is missing or the special "No moonrise"
     * / "No moonset" strings (or unparseable).
     */
    private function parseAstroTime(?string $value, string $dateStr, DateTimeZone $tz): ?DateTimeImmutable
    {
        if ($value === null || $value === '' || stripos($value, 'no ') === 0) {
            return null;
        }
        try {
            return new DateTimeImmutable($dateStr . ' ' . $value, $tz);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Map WeatherAPI's text moon-phase name to the MoonPhase enum.
     */
    private function mapMoonPhase(string $name): MoonPhase
    {
        return match (strtolower(trim($name))) {
            'new moon' => MoonPhase::NEW,
            'waxing crescent' => MoonPhase::WAXING_CRESCENT,
            'first quarter' => MoonPhase::FIRST_QUARTER,
            'waxing gibbous' => MoonPhase::WAXING_GIBBOUS,
            'full moon' => MoonPhase::FULL,
            'waning gibbous' => MoonPhase::WANING_GIBBOUS,
            'last quarter' => MoonPhase::LAST_QUARTER,
            'waning crescent' => MoonPhase::WANING_CRESCENT,
            default => MoonPhase::UNKNOWN,
        };
    }

    /**
     * {@inheritdoc}
     *
     * Uses /current.json?aqi=yes and reads the `current.air_quality` block.
     * WeatherAPI's `us-epa-index` is on a 1-6 scale (not the 0-500 EPA AQI),
     * so we map it to the coarse AirQualityCategory band and leave the
     * usAqi numeric field null. gb-defra-index (1-10) is surfaced as ukDaqi.
     */
    public function getAirQuality(Location $location): AirQuality
    {
        if (!$location->hasCoordinates()) {
            throw new InvalidLocationException(
                'HourlyForecast requires coordinate-based locations'
            );
        }
        $coord = $location->getCoordinate();

        $url = $this->buildUrl('/current.json', [
            'key' => $this->config->apiKey,
            'q' => $coord->latitude . ',' . $coord->longitude,
            'aqi' => 'yes',
        ]);

        $data = $this->makeRequest($url);
        $aq = $data['current']['air_quality'] ?? null;
        if (!is_array($aq)) {
            throw new ApiException('WeatherAPI response did not include air_quality');
        }

        $usIndex = isset($aq['us-epa-index']) ? (int) $aq['us-epa-index'] : null;
        // Map WeatherAPI's 1-6 EPA index to our category enum.
        $category = $usIndex === null ? null : match ($usIndex) {
            1 => AirQualityCategory::GOOD,
            2 => AirQualityCategory::MODERATE,
            3 => AirQualityCategory::UNHEALTHY_FOR_SENSITIVE,
            4 => AirQualityCategory::UNHEALTHY,
            5 => AirQualityCategory::VERY_UNHEALTHY,
            6 => AirQualityCategory::HAZARDOUS,
            default => AirQualityCategory::UNKNOWN,
        };

        return new AirQuality(
            location: $location,
            observationTime: new DateTimeImmutable($data['location']['localtime']),
            pm25: isset($aq['pm2_5']) ? (float) $aq['pm2_5'] : null,
            pm10: isset($aq['pm10']) ? (float) $aq['pm10'] : null,
            ozone: isset($aq['o3']) ? (float) $aq['o3'] : null,
            no2: isset($aq['no2']) ? (float) $aq['no2'] : null,
            so2: isset($aq['so2']) ? (float) $aq['so2'] : null,
            co: isset($aq['co']) ? (float) $aq['co'] : null,
            ukDaqi: isset($aq['gb-defra-index']) ? (int) $aq['gb-defra-index'] : null,
            category: $category,
        );
    }

    /**
     * {@inheritdoc}
     *
     * WeatherAPI's /forecast.json returns per-hour data inside each day's
     * `hour[]` array. This method fetches enough days to cover the requested
     * hours (ceil(hours/24)) and flattens the per-day hour arrays,
     * discarding hours past the requested horizon.
     */
    public function getHourlyForecast(Location $location, int $hours = 48): Forecast
    {
        if (!$location->hasCoordinates()) {
            throw new InvalidLocationException(
                'HourlyForecast requires coordinate-based locations'
            );
        }
        $hours = max(1, min($hours, 14 * 24));
        $days = max(1, (int) ceil($hours / 24));
        $coord = $location->getCoordinate();

        $url = $this->buildUrl('/forecast.json', [
            'key' => $this->config->apiKey,
            'q' => $coord->latitude . ',' . $coord->longitude,
            'days' => $days,
        ]);

        $data = $this->makeRequest($url);

        return $this->parseHourlyForecast($data, $location, $hours);
    }

    /**
     * {@inheritdoc}
     */
    public function getForecast(Location|string $location, int $days = 5): Forecast
    {
        $query = $this->buildLocationQuery($location);

        $url = $this->buildUrl('/forecast.json', [
            'key' => $this->config->apiKey,
            'q' => $query,
            'days' => min($days, 14), // API maximum is 14 days on free tier
        ]);

        $data = $this->makeRequest($url);

        return $this->parseForecast($data, $location);
    }

    /**
     * {@inheritdoc}
     *
     * Dispatches by `$type`:
     *  - SearchType::IP_ADDRESS → /ip.json (WeatherAPI's dedicated geo-IP endpoint;
     *    returns a single result wrapped as a one-element array).
     *  - SearchType::ICAO → /search.json with the WeatherAPI-specific `metar:` prefix.
     *  - all other types (including ANY / null) → /search.json, letting WeatherAPI's
     *    own heuristics disambiguate city / ZIP / lat,lon / postcode / IATA.
     *
     * @return array<Location>
     */
    public function searchLocations(string $query, ?SearchType $type = null): array
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return [];
        }

        if ($type === SearchType::IP_ADDRESS) {
            return $this->searchByIp($trimmed);
        }

        $q = $type === SearchType::ICAO ? 'metar:' . $trimmed : $trimmed;

        $url = $this->buildUrl('/search.json', [
            'key' => $this->config->apiKey,
            'q' => $q,
        ]);

        $data = $this->makeRequest($url);

        // /search.json returns a JSON array at the top level; makeRequest()
        // normalizes this to an assoc-array-shaped structure. When it's a
        // plain list, PHP's json_decode still gives us a list-shaped array.
        if (!array_is_list($data)) {
            return [];
        }

        $locations = [];
        foreach ($data as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $locations[] = $this->parseSearchResult($entry);
        }

        return $locations;
    }

    /**
     * Geo-IP lookup via WeatherAPI's /ip.json.
     *
     * @return array<Location>
     */
    private function searchByIp(string $ip): array
    {
        $url = $this->buildUrl('/ip.json', [
            'key' => $this->config->apiKey,
            'q' => $ip,
        ]);

        try {
            $data = $this->makeRequest($url);
        } catch (InvalidApiKeyException) {
            // A genuinely-bad key should be surfaced. But ip.json for a
            // malformed input returns error code 2006 too, so we can't
            // reliably distinguish. Treat as no-match; callers who care
            // will notice via the empty result.
            return [];
        } catch (ApiException) {
            // Bad IPs and non-routable addresses come back as API errors;
            // treat as "no match" rather than propagating.
            return [];
        }

        if (!isset($data['lat'], $data['lon'])) {
            return [];
        }

        return [$this->parseIpResult($data)];
    }

    /**
     * Parse a /search.json result entry.
     */
    private function parseSearchResult(array $entry): Location
    {
        $lat = (float) ($entry['lat'] ?? 0.0);
        $lon = (float) ($entry['lon'] ?? 0.0);
        $name = (string) ($entry['name'] ?? '');
        $country = isset($entry['country']) ? (string) $entry['country'] : null;

        return Location::fromGeocoded(
            coordinate: Coordinate::fromLatLon($lat, $lon),
            name: $name !== '' ? $name : null,
            country: $country,
        );
    }

    /**
     * Parse a /ip.json result.
     */
    private function parseIpResult(array $data): Location
    {
        $lat = (float) $data['lat'];
        $lon = (float) $data['lon'];
        $name = isset($data['city']) && $data['city'] !== ''
            ? (string) $data['city']
            : null;
        $country = isset($data['country_name']) ? (string) $data['country_name'] : null;

        return Location::fromGeocoded(
            coordinate: Coordinate::fromLatLon($lat, $lon),
            name: $name,
            country: $country,
        );
    }

    /**
     * Build location query parameter.
     */
    private function buildLocationQuery(Location|string $location): string
    {
        if (is_string($location)) {
            // Assume it's "lat,lon" format
            return $location;
        }

        if ($location->hasCoordinates()) {
            $coord = $location->getCoordinate();
            return $coord->latitude . ',' . $coord->longitude;
        }

        // Try to use city name if available
        if ($location->name) {
            return $location->name . ($location->country ? ',' . $location->country : '');
        }

        throw new InvalidLocationException('Location must have coordinates or city name');
    }

    /**
     * Build API URL with parameters.
     */
    private function buildUrl(string $endpoint, array $params): string
    {
        return self::API_BASE . $endpoint . '?' . http_build_query($params);
    }

    /**
     * Make HTTP request to API.
     */
    private function makeRequest(string $url): array
    {
        $request = $this->requestFactory->createRequest('GET', $url);

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new ApiException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new ApiException('Invalid JSON response from WeatherAPI.com');
        }

        if (isset($data['error'])) {
            $code = $data['error']['code'];
            $message = $data['error']['message'];

            if ($code === 1002 || $code === 2006) {
                throw new InvalidApiKeyException('Invalid WeatherAPI.com API key: ' . $message);
            }

            throw new ApiException('WeatherAPI.com error: ' . $message);
        }

        $status = $response->getStatusCode();
        if ($status === 401 || $status === 403) {
            throw new InvalidApiKeyException('WeatherAPI.com HTTP ' . $status);
        }
        if ($status >= 400) {
            throw new ApiException('WeatherAPI.com HTTP ' . $status);
        }

        return $data;
    }

    /**
     * Parse current weather from API response.
     */
    private function parseCurrentWeather(array $data, Location|string $originalLocation): CurrentWeather
    {
        $current = $data['current'];

        $location = is_string($originalLocation)
            ? Location::fromCoordinates($data['location']['lat'], $data['location']['lon'])
            : $originalLocation;

        $temp = $this->parseTemperature($current['temp_c'], $current['temp_f']);
        $feelsLike = $this->parseTemperature($current['feelslike_c'], $current['feelslike_f']);

        return new CurrentWeather(
            location: $location,
            temperature: $temp,
            condition: $this->mapWeatherCondition($current['condition']['code']),
            observationTime: new DateTimeImmutable($data['location']['localtime']),
            feelsLike: $feelsLike,
            humidity: Humidity::fromPercentage((int) $current['humidity']),
            pressure: Pressure::fromMillibars($current['pressure_mb']),
            wind: $this->parseWind($current),
            visibility: $current['vis_km'],
            cloudCover: (int) $current['cloud'],
            uvIndex: $current['uv']
        );
    }

    /**
     * Parse forecast from API response.
     */
    private function parseForecast(array $data, Location|string $originalLocation): Forecast
    {
        $location = is_string($originalLocation)
            ? Location::fromCoordinates($data['location']['lat'], $data['location']['lon'])
            : $originalLocation;

        $periods = [];

        foreach ($data['forecast']['forecastday'] as $day) {
            $dayData = $day['day'];

            $tempAvg = $this->parseTemperature($dayData['avgtemp_c'], $dayData['avgtemp_f']);
            $tempMax = $this->parseTemperature($dayData['maxtemp_c'], $dayData['maxtemp_f']);
            $tempMin = $this->parseTemperature($dayData['mintemp_c'], $dayData['mintemp_f']);

            $wind = null;
            if (isset($dayData['maxwind_kph'])) {
                $windSpeed = Speed::fromKilometersPerHour($dayData['maxwind_kph']);
                $wind = new Wind($windSpeed, WindDirection::VARIABLE);
            }

            $periods[] = new ForecastPeriod(
                date: new DateTimeImmutable($day['date']),
                temperature: $tempAvg,
                condition: $this->mapWeatherCondition($dayData['condition']['code']),
                highTemperature: $tempMax,
                lowTemperature: $tempMin,
                humidity: isset($dayData['avghumidity'])
                    ? Humidity::fromPercentage((int) $dayData['avghumidity'])
                    : null,
                wind: $wind,
                precipitationProbability: isset($dayData['daily_chance_of_rain'])
                    ? $dayData['daily_chance_of_rain'] / 100
                    : null,
                precipitationAmount: $dayData['totalprecip_mm'] ?? null
            );
        }

        return new Forecast($location, $periods);
    }

    /**
     * Parse hourly forecast from /forecast.json. Flattens each forecast day's
     * `hour[]` array into a single time-ordered list, capped at $maxHours.
     */
    private function parseHourlyForecast(array $data, Location $originalLocation, int $maxHours): Forecast
    {
        $periods = [];
        foreach ($data['forecast']['forecastday'] as $day) {
            foreach ($day['hour'] as $hour) {
                if (count($periods) >= $maxHours) {
                    break 2;
                }

                $temp = $this->parseTemperature($hour['temp_c'], $hour['temp_f']);

                $wind = null;
                if (isset($hour['wind_kph'])) {
                    $windSpeed = match ($this->config->units) {
                        \Horde\Service\Weather\ValueObject\Units::IMPERIAL => Speed::fromMilesPerHour($hour['wind_mph']),
                        default => Speed::fromKilometersPerHour($hour['wind_kph']),
                    };
                    $windDir = isset($hour['wind_degree'])
                        ? WindDirection::fromDegrees($hour['wind_degree'])
                        : WindDirection::VARIABLE;
                    $gusts = isset($hour['gust_kph'])
                        ? (match ($this->config->units) {
                            \Horde\Service\Weather\ValueObject\Units::IMPERIAL => Speed::fromMilesPerHour($hour['gust_mph']),
                            default => Speed::fromKilometersPerHour($hour['gust_kph']),
                        })
                        : null;
                    $wind = new Wind(
                        $windSpeed,
                        $windDir,
                        $gusts,
                        degrees: $hour['wind_degree'] ?? null,
                    );
                }

                $periods[] = new ForecastPeriod(
                    date: new DateTimeImmutable($hour['time']),
                    temperature: $temp,
                    condition: $this->mapWeatherCondition($hour['condition']['code']),
                    humidity: isset($hour['humidity'])
                        ? Humidity::fromPercentage((int) $hour['humidity'])
                        : null,
                    pressure: isset($hour['pressure_mb'])
                        ? Pressure::fromMillibars($hour['pressure_mb'])
                        : null,
                    wind: $wind,
                    precipitationProbability: isset($hour['chance_of_rain'])
                        ? $hour['chance_of_rain'] / 100
                        : null,
                    precipitationAmount: $hour['precip_mm'] ?? null,
                    cloudCover: isset($hour['cloud']) ? (int) $hour['cloud'] : null,
                    uvIndex: $hour['uv'] ?? null,
                    snowfallAmount: $hour['snow_cm'] ?? null,
                );
            }
        }

        return new Forecast($originalLocation, $periods, ForecastDetail::DETAILED);
    }

    /**
     * Parse temperature based on configured units.
     */
    private function parseTemperature(float $celsius, float $fahrenheit): Temperature
    {
        return match ($this->config->units) {
            \Horde\Service\Weather\ValueObject\Units::IMPERIAL => Temperature::fromFahrenheit($fahrenheit),
            default => Temperature::fromCelsius($celsius),
        };
    }

    /**
     * Parse wind data.
     */
    private function parseWind(array $current): Wind
    {
        $speed = match ($this->config->units) {
            \Horde\Service\Weather\ValueObject\Units::IMPERIAL => Speed::fromMilesPerHour($current['wind_mph']),
            default => Speed::fromKilometersPerHour($current['wind_kph']),
        };

        $direction = WindDirection::fromDegrees($current['wind_degree']);

        $gusts = match ($this->config->units) {
            \Horde\Service\Weather\ValueObject\Units::IMPERIAL => Speed::fromMilesPerHour($current['gust_mph']),
            default => Speed::fromKilometersPerHour($current['gust_kph']),
        };

        return new Wind($speed, $direction, $gusts, $current['wind_degree']);
    }

    /**
     * Map WeatherAPI.com condition code to WeatherCondition enum.
     *
     * Codes from: https://www.weatherapi.com/docs/weather_conditions.json
     *
     * Explicit code lists (rather than numeric ranges) because WeatherAPI's
     * code assignments are not densely contiguous by category. The prior
     * range-based mapping mis-categorized several codes: thunder-with-
     * precip codes (1273-1282) were shadowed by an overlapping SNOW range,
     * ice-pellet showers (1261/1264) collided with the snow-showers band,
     * "patchy X nearby" codes (1063/1066/1069) were lumped as DRIZZLE, and
     * FREEZING_RAIN codes (1072/1168/1171/1198/1201) hid inside DRIZZLE
     * and RAIN ranges.
     */
    private function mapWeatherCondition(int $code): WeatherCondition
    {
        return match ($code) {
            1000 => WeatherCondition::CLEAR,
            1003 => WeatherCondition::PARTLY_CLOUDY,
            1006 => WeatherCondition::CLOUDY,
            1009 => WeatherCondition::OVERCAST,
            1030, 1135, 1147 => WeatherCondition::FOG,
            1150, 1153 => WeatherCondition::DRIZZLE,
            1072, 1168, 1171, 1198, 1201 => WeatherCondition::FREEZING_RAIN,
            1063, 1180, 1183, 1186, 1189, 1192, 1195, 1240, 1243, 1246 => WeatherCondition::RAIN,
            1066, 1114, 1117, 1210, 1213, 1216, 1219, 1222, 1225, 1255, 1258 => WeatherCondition::SNOW,
            1069, 1204, 1207, 1237, 1249, 1252, 1261, 1264 => WeatherCondition::SLEET,
            1087, 1273, 1276, 1279, 1282 => WeatherCondition::THUNDERSTORM,
            default => WeatherCondition::UNKNOWN,
        };
    }
}
