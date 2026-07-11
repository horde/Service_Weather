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
use Horde\Service\Weather\Exception\InvalidLocationException;
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
use Horde\Service\Weather\ForecastCapabilities;
use Horde\Service\Weather\HourlyForecast;
use Horde\Service\Weather\WeatherProvider;
use DateTimeZone;

/**
 * Open-Meteo weather provider.
 *
 * Free, open-source weather API with no API key required.
 * Useful for tests and CI without secrets, and for production
 * deployments that prefer an unauthenticated global source.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
class OpenMeteo implements WeatherProvider, ForecastCapabilities, HourlyForecast, AirQualityProvider, AstronomyProvider
{
    private const API_BASE = 'https://api.open-meteo.com/v1';
    private const AIR_QUALITY_BASE = 'https://air-quality-api.open-meteo.com/v1';

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
     * Open-Meteo's `/forecast` endpoint accepts 1..16 days.
     */
    public function getSupportedForecastLengths(): array
    {
        return range(1, 16);
    }

    /**
     * {@inheritdoc}
     *
     * Open-Meteo returns `daily.sunrise` and `daily.sunset` when
     * queried with `daily=sunrise,sunset`. No moon data. Moon fields
     * are left null and moonPhase is UNKNOWN.
     */
    public function getAstronomy(Location $location, ?DateTimeImmutable $date = null): Astronomy
    {
        if (!$location->hasCoordinates()) {
            throw new InvalidLocationException(
                'Open-Meteo requires coordinate-based locations'
            );
        }
        $coord = $location->getCoordinate();
        $date ??= new DateTimeImmutable('today');
        $dateStr = $date->format('Y-m-d');

        $url = $this->buildUrl('/forecast', [
            'latitude' => $coord->latitude,
            'longitude' => $coord->longitude,
            'daily' => 'sunrise,sunset',
            'start_date' => $dateStr,
            'end_date' => $dateStr,
            'timezone' => 'auto',
        ]);

        $data = $this->makeRequest($url);
        $daily = $data['daily'] ?? null;
        if (!is_array($daily) || !isset($daily['time'][0])) {
            throw new ApiException('Invalid daily astronomy response from Open-Meteo');
        }

        $tz = isset($data['timezone']) ? new DateTimeZone($data['timezone']) : $date->getTimezone();

        return new Astronomy(
            location: $location,
            date: $date,
            sunrise: isset($daily['sunrise'][0]) ? new DateTimeImmutable($daily['sunrise'][0], $tz) : null,
            sunset: isset($daily['sunset'][0]) ? new DateTimeImmutable($daily['sunset'][0], $tz) : null,
        );
    }

    /**
     * {@inheritdoc}
     *
     * Uses Open-Meteo's dedicated air-quality-api endpoint. No API key.
     */
    public function getAirQuality(Location $location): AirQuality
    {
        if (!$location->hasCoordinates()) {
            throw new InvalidLocationException(
                'Open-Meteo requires coordinate-based locations'
            );
        }
        $coord = $location->getCoordinate();

        $url = self::AIR_QUALITY_BASE . '/air-quality?' . http_build_query([
            'latitude' => $coord->latitude,
            'longitude' => $coord->longitude,
            'current' => 'pm2_5,pm10,ozone,nitrogen_dioxide,sulphur_dioxide,'
                         . 'carbon_monoxide,us_aqi,european_aqi',
        ]);

        $data = $this->makeRequest($url);
        $current = $data['current'] ?? null;
        if (!is_array($current)) {
            throw new ApiException('Invalid air-quality response from Open-Meteo');
        }

        $time = isset($current['time']) ? new DateTimeImmutable($current['time']) : new DateTimeImmutable();

        return new AirQuality(
            location: $location,
            observationTime: $time,
            pm25: isset($current['pm2_5']) ? (float) $current['pm2_5'] : null,
            pm10: isset($current['pm10']) ? (float) $current['pm10'] : null,
            ozone: isset($current['ozone']) ? (float) $current['ozone'] : null,
            no2: isset($current['nitrogen_dioxide']) ? (float) $current['nitrogen_dioxide'] : null,
            so2: isset($current['sulphur_dioxide']) ? (float) $current['sulphur_dioxide'] : null,
            co: isset($current['carbon_monoxide']) ? (float) $current['carbon_monoxide'] : null,
            usAqi: isset($current['us_aqi']) ? (int) $current['us_aqi'] : null,
            europeanAqi: isset($current['european_aqi']) ? (int) $current['european_aqi'] : null,
            ukDaqi: isset($current['uk_aqi']) ? (int) $current['uk_aqi'] : null,
        );
    }

    /**
     * {@inheritdoc}
     *
     * Open-Meteo returns hourly data on the same `/forecast` endpoint via
     * the `hourly=` parameter, up to 384 hours (16 days).
     */
    public function getHourlyForecast(Location $location, int $hours = 48): Forecast
    {
        if (!$location->hasCoordinates()) {
            throw new InvalidLocationException(
                'Open-Meteo requires coordinate-based locations'
            );
        }
        $coordinate = $location->getCoordinate();
        $hours = max(1, min($hours, 384));

        $url = $this->buildUrl('/forecast', [
            'latitude' => $coordinate->latitude,
            'longitude' => $coordinate->longitude,
            'hourly' => 'temperature_2m,relative_humidity_2m,apparent_temperature,'
                        . 'precipitation,precipitation_probability,weather_code,'
                        . 'cloud_cover,wind_speed_10m,wind_direction_10m,uv_index,'
                        . 'snowfall',
            'forecast_hours' => $hours,
            'temperature_unit' => $this->getTemperatureUnit(),
            'wind_speed_unit' => $this->getWindSpeedUnit(),
        ]);

        $data = $this->makeRequest($url);

        if (!isset($data['hourly'])) {
            throw new ApiException('Invalid response from Open-Meteo API');
        }

        return $this->parseHourlyForecast($data, $location);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentWeather(Location|string $location): CurrentWeather
    {
        $coordinate = $this->normalizeLocation($location);

        $url = $this->buildUrl('/forecast', [
            'latitude' => $coordinate->latitude,
            'longitude' => $coordinate->longitude,
            'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,'
                        . 'precipitation,weather_code,cloud_cover,pressure_msl,'
                        . 'wind_speed_10m,wind_direction_10m',
            'temperature_unit' => $this->getTemperatureUnit(),
            'wind_speed_unit' => $this->getWindSpeedUnit(),
        ]);

        $data = $this->makeRequest($url);

        if (!isset($data['current'])) {
            throw new ApiException('Invalid response from Open-Meteo API');
        }

        return $this->parseCurrentWeather($data, $location);
    }

    /**
     * {@inheritdoc}
     */
    public function getForecast(Location|string $location, int $days = 5): Forecast
    {
        $coordinate = $this->normalizeLocation($location);

        $url = $this->buildUrl('/forecast', [
            'latitude' => $coordinate->latitude,
            'longitude' => $coordinate->longitude,
            'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,'
                      . 'precipitation_probability_max,precipitation_sum,'
                      . 'wind_speed_10m_max,wind_direction_10m_dominant',
            'forecast_days' => min($days, 16), // API maximum
            'temperature_unit' => $this->getTemperatureUnit(),
            'wind_speed_unit' => $this->getWindSpeedUnit(),
        ]);

        $data = $this->makeRequest($url);

        if (!isset($data['daily'])) {
            throw new ApiException('Invalid response from Open-Meteo API');
        }

        return $this->parseForecast($data, $location);
    }

    /**
     * Normalize location to coordinates.
     */
    private function normalizeLocation(Location|string $location): Coordinate
    {
        if (is_string($location)) {
            // Parse "lat,lon" string
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
                'Open-Meteo requires coordinate-based locations'
            );
        }

        return $location->getCoordinate();
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
            throw new ApiException('Invalid JSON response from Open-Meteo');
        }

        if (isset($data['error'])) {
            throw new ApiException('Open-Meteo API error: ' . ($data['reason'] ?? 'Unknown'));
        }

        $status = $response->getStatusCode();
        if ($status >= 400) {
            throw new ApiException('Open-Meteo HTTP ' . $status);
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
            ? Location::fromCoordinates($data['latitude'], $data['longitude'])
            : $originalLocation;

        return new CurrentWeather(
            location: $location,
            temperature: Temperature::fromCelsius($current['temperature_2m']),
            condition: $this->mapWeatherCode($current['weather_code']),
            observationTime: new DateTimeImmutable($current['time']),
            feelsLike: isset($current['apparent_temperature'])
                ? Temperature::fromCelsius($current['apparent_temperature'])
                : null,
            humidity: isset($current['relative_humidity_2m'])
                ? Humidity::fromPercentage((int) $current['relative_humidity_2m'])
                : null,
            pressure: isset($current['pressure_msl'])
                ? Pressure::fromMillibars($current['pressure_msl'])
                : null,
            wind: $this->parseWind($current),
            cloudCover: isset($current['cloud_cover']) ? (int) $current['cloud_cover'] : null
        );
    }

    /**
     * Parse forecast from API response.
     */
    private function parseForecast(array $data, Location|string $originalLocation): Forecast
    {
        $daily = $data['daily'];

        $location = is_string($originalLocation)
            ? Location::fromCoordinates($data['latitude'], $data['longitude'])
            : $originalLocation;

        $periods = [];
        $count = count($daily['time']);

        for ($i = 0; $i < $count; $i++) {
            $date = new DateTimeImmutable($daily['time'][$i]);
            $tempMax = Temperature::fromCelsius($daily['temperature_2m_max'][$i]);
            $tempMin = Temperature::fromCelsius($daily['temperature_2m_min'][$i]);
            $tempAvg = Temperature::fromCelsius(
                ($daily['temperature_2m_max'][$i] + $daily['temperature_2m_min'][$i]) / 2
            );

            $wind = null;
            if (isset($daily['wind_speed_10m_max'][$i])) {
                $windSpeed = Speed::fromMetersPerSecond($daily['wind_speed_10m_max'][$i]);
                $windDir = isset($daily['wind_direction_10m_dominant'][$i])
                    ? WindDirection::fromDegrees($daily['wind_direction_10m_dominant'][$i])
                    : WindDirection::VARIABLE;
                $wind = new Wind($windSpeed, $windDir);
            }

            $periods[] = new ForecastPeriod(
                date: $date,
                temperature: $tempAvg,
                condition: $this->mapWeatherCode($daily['weather_code'][$i]),
                highTemperature: $tempMax,
                lowTemperature: $tempMin,
                wind: $wind,
                precipitationProbability: isset($daily['precipitation_probability_max'][$i])
                    ? $daily['precipitation_probability_max'][$i] / 100
                    : null,
                precipitationAmount: $daily['precipitation_sum'][$i] ?? null
            );
        }

        return new Forecast($location, $periods);
    }

    /**
     * Parse hourly forecast from API response.
     */
    private function parseHourlyForecast(array $data, Location|string $originalLocation): Forecast
    {
        $hourly = $data['hourly'];

        $location = is_string($originalLocation)
            ? Location::fromCoordinates($data['latitude'], $data['longitude'])
            : $originalLocation;

        $periods = [];
        $count = count($hourly['time']);

        for ($i = 0; $i < $count; $i++) {
            $temp = Temperature::fromCelsius($hourly['temperature_2m'][$i]);

            $wind = null;
            if (isset($hourly['wind_speed_10m'][$i])) {
                $windSpeed = Speed::fromMetersPerSecond($hourly['wind_speed_10m'][$i]);
                $windDir = isset($hourly['wind_direction_10m'][$i])
                    ? WindDirection::fromDegrees($hourly['wind_direction_10m'][$i])
                    : WindDirection::VARIABLE;
                $wind = new Wind(
                    $windSpeed,
                    $windDir,
                    degrees: $hourly['wind_direction_10m'][$i] ?? null,
                );
            }

            $periods[] = new ForecastPeriod(
                date: new DateTimeImmutable($hourly['time'][$i]),
                temperature: $temp,
                condition: $this->mapWeatherCode($hourly['weather_code'][$i]),
                humidity: isset($hourly['relative_humidity_2m'][$i])
                    ? Humidity::fromPercentage((int) $hourly['relative_humidity_2m'][$i])
                    : null,
                wind: $wind,
                precipitationProbability: isset($hourly['precipitation_probability'][$i])
                    ? $hourly['precipitation_probability'][$i] / 100
                    : null,
                precipitationAmount: $hourly['precipitation'][$i] ?? null,
                cloudCover: isset($hourly['cloud_cover'][$i])
                    ? (int) $hourly['cloud_cover'][$i]
                    : null,
                uvIndex: $hourly['uv_index'][$i] ?? null,
                snowfallAmount: $hourly['snowfall'][$i] ?? null,
            );
        }

        return new Forecast($location, $periods, ForecastDetail::DETAILED);
    }

    /**
     * Parse wind data from current conditions.
     */
    private function parseWind(array $current): ?Wind
    {
        if (!isset($current['wind_speed_10m'])) {
            return null;
        }

        $speed = Speed::fromMetersPerSecond($current['wind_speed_10m']);
        $direction = isset($current['wind_direction_10m'])
            ? WindDirection::fromDegrees($current['wind_direction_10m'])
            : WindDirection::VARIABLE;

        return new Wind($speed, $direction, degrees: $current['wind_direction_10m'] ?? null);
    }

    /**
     * Map Open-Meteo weather code to WeatherCondition enum.
     */
    private function mapWeatherCode(int $code): WeatherCondition
    {
        return match (true) {
            $code === 0 => WeatherCondition::CLEAR,
            $code >= 1 && $code <= 2 => WeatherCondition::PARTLY_CLOUDY,
            $code === 3 => WeatherCondition::CLOUDY,
            $code >= 45 && $code <= 48 => WeatherCondition::FOG,
            $code >= 51 && $code <= 57 => WeatherCondition::DRIZZLE,
            $code >= 61 && $code <= 65 => WeatherCondition::RAIN,
            $code >= 66 && $code <= 67 => WeatherCondition::FREEZING_RAIN,
            $code >= 71 && $code <= 77 => WeatherCondition::SNOW,
            $code >= 80 && $code <= 82 => WeatherCondition::RAIN,
            $code >= 85 && $code <= 86 => WeatherCondition::SNOW,
            $code >= 95 && $code <= 99 => WeatherCondition::THUNDERSTORM,
            default => WeatherCondition::UNKNOWN,
        };
    }

    /**
     * Get temperature unit for API.
     */
    private function getTemperatureUnit(): string
    {
        return match ($this->config->units) {
            \Horde\Service\Weather\ValueObject\Units::IMPERIAL => 'fahrenheit',
            default => 'celsius',
        };
    }

    /**
     * Get wind speed unit for API.
     */
    private function getWindSpeedUnit(): string
    {
        return match ($this->config->units) {
            \Horde\Service\Weather\ValueObject\Units::IMPERIAL => 'mph',
            default => 'kmh',
        };
    }
}
