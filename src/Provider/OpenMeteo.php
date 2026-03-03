<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Provider;

use DateTimeImmutable;
use Horde\Http\Client;
use Horde\Service\Weather\Domain\CurrentWeather;
use Horde\Service\Weather\Domain\Forecast;
use Horde\Service\Weather\Domain\ForecastPeriod;
use Horde\Service\Weather\Domain\Wind;
use Horde\Service\Weather\Exception\ApiException;
use Horde\Service\Weather\Exception\InvalidLocationException;
use Horde\Service\Weather\ValueObject\Coordinate;
use Horde\Service\Weather\ValueObject\Humidity;
use Horde\Service\Weather\ValueObject\Location;
use Horde\Service\Weather\ValueObject\Pressure;
use Horde\Service\Weather\ValueObject\Speed;
use Horde\Service\Weather\ValueObject\Temperature;
use Horde\Service\Weather\ValueObject\WeatherCondition;
use Horde\Service\Weather\ValueObject\WeatherConfig;
use Horde\Service\Weather\ValueObject\WindDirection;
use Horde\Service\Weather\WeatherProviderInterface;

/**
 * Open-Meteo weather provider.
 *
 * Free, open-source weather API with no API key required.
 * Perfect for testing and development.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
class OpenMeteo implements WeatherProviderInterface
{
    private const API_BASE = 'https://api.open-meteo.com/v1';

    public function __construct(
        private readonly Client $httpClient,
        private readonly WeatherConfig $config = new WeatherConfig()
    ) {
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
            'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,' .
                        'precipitation,weather_code,cloud_cover,pressure_msl,' .
                        'wind_speed_10m,wind_direction_10m',
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
            'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,' .
                      'precipitation_probability_max,precipitation_sum,' .
                      'wind_speed_10m_max,wind_direction_10m_dominant',
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
            return Coordinate::fromLatLon((float)trim($parts[0]), (float)trim($parts[1]));
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
        try {
            $response = $this->httpClient->get($url);
            $body = $response->getBody();
            $data = json_decode($body, true);

            if (!is_array($data)) {
                throw new ApiException('Invalid JSON response from Open-Meteo');
            }

            if (isset($data['error'])) {
                throw new ApiException('Open-Meteo API error: ' . ($data['reason'] ?? 'Unknown'));
            }

            return $data;
        } catch (\Exception $e) {
            if ($e instanceof ApiException) {
                throw $e;
            }
            throw new ApiException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }
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
                ? Humidity::fromPercentage((int)$current['relative_humidity_2m'])
                : null,
            pressure: isset($current['pressure_msl'])
                ? Pressure::fromMillibars($current['pressure_msl'])
                : null,
            wind: $this->parseWind($current),
            cloudCover: isset($current['cloud_cover']) ? (int)$current['cloud_cover'] : null
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
