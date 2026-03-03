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
use Horde\Service\Weather\Exception\InvalidApiKeyException;
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
 * OpenWeatherMap weather provider.
 *
 * Industry-standard weather API with free tier (1000 calls/day).
 * Requires API key from https://openweathermap.org/api
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
class OpenWeatherMap implements WeatherProviderInterface
{
    private const API_BASE = 'https://api.openweathermap.org/data/2.5';

    public function __construct(
        private readonly Client $httpClient,
        private readonly WeatherConfig $config
    ) {
        if (!$this->config->apiKey) {
            throw new InvalidApiKeyException('OpenWeatherMap requires an API key');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentWeather(Location|string $location): CurrentWeather
    {
        $coordinate = $this->normalizeLocation($location);

        $url = $this->buildUrl('/weather', [
            'lat' => $coordinate->latitude,
            'lon' => $coordinate->longitude,
            'appid' => $this->config->apiKey,
            'units' => $this->getUnitsParam(),
            'lang' => $this->config->language,
        ]);

        $data = $this->makeRequest($url);

        return $this->parseCurrentWeather($data, $location);
    }

    /**
     * {@inheritdoc}
     */
    public function getForecast(Location|string $location, int $days = 5): Forecast
    {
        $coordinate = $this->normalizeLocation($location);

        $url = $this->buildUrl('/forecast', [
            'lat' => $coordinate->latitude,
            'lon' => $coordinate->longitude,
            'appid' => $this->config->apiKey,
            'units' => $this->getUnitsParam(),
            'lang' => $this->config->language,
            'cnt' => min($days * 8, 40), // API returns 3-hour intervals, max 5 days (40 intervals)
        ]);

        $data = $this->makeRequest($url);

        return $this->parseForecast($data, $location);
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
            return Coordinate::fromLatLon((float)trim($parts[0]), (float)trim($parts[1]));
        }

        if (!$location->hasCoordinates()) {
            throw new InvalidLocationException(
                'OpenWeatherMap requires coordinate-based locations'
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
                throw new ApiException('Invalid JSON response from OpenWeatherMap');
            }

            // OpenWeatherMap returns errors with 'cod' field
            if (isset($data['cod']) && $data['cod'] !== 200 && $data['cod'] !== '200') {
                $message = $data['message'] ?? 'Unknown error';

                if ($data['cod'] == 401) {
                    throw new InvalidApiKeyException('Invalid OpenWeatherMap API key: ' . $message);
                }

                throw new ApiException('OpenWeatherMap API error: ' . $message);
            }

            return $data;
        } catch (ApiException | InvalidApiKeyException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ApiException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Parse current weather from API response.
     */
    private function parseCurrentWeather(array $data, Location|string $originalLocation): CurrentWeather
    {
        $location = is_string($originalLocation)
            ? Location::fromCoordinates($data['coord']['lat'], $data['coord']['lon'])
            : $originalLocation;

        $temp = Temperature::fromCelsius($data['main']['temp']);

        return new CurrentWeather(
            location: $location,
            temperature: $temp,
            condition: $this->mapWeatherCode($data['weather'][0]['id']),
            observationTime: new DateTimeImmutable('@' . $data['dt']),
            feelsLike: isset($data['main']['feels_like'])
                ? Temperature::fromCelsius($data['main']['feels_like'])
                : null,
            humidity: isset($data['main']['humidity'])
                ? Humidity::fromPercentage((int)$data['main']['humidity'])
                : null,
            pressure: isset($data['main']['pressure'])
                ? Pressure::fromMillibars($data['main']['pressure'])
                : null,
            wind: $this->parseWind($data),
            visibility: isset($data['visibility']) ? $data['visibility'] / 1000 : null, // Convert m to km
            cloudCover: $data['clouds']['all'] ?? null
        );
    }

    /**
     * Parse forecast from API response.
     */
    private function parseForecast(array $data, Location|string $originalLocation): Forecast
    {
        $location = is_string($originalLocation)
            ? Location::fromCoordinates($data['city']['coord']['lat'], $data['city']['coord']['lon'])
            : $originalLocation;

        // Group 3-hour forecasts by day
        $dailyData = [];
        foreach ($data['list'] as $item) {
            $date = (new DateTimeImmutable('@' . $item['dt']))->format('Y-m-d');
            if (!isset($dailyData[$date])) {
                $dailyData[$date] = [];
            }
            $dailyData[$date][] = $item;
        }

        $periods = [];
        foreach ($dailyData as $date => $items) {
            $temps = array_map(fn($i) => $i['main']['temp'], $items);
            $tempMax = max($temps);
            $tempMin = min($temps);
            $tempAvg = array_sum($temps) / count($temps);

            // Use the midday forecast for conditions
            $middayItem = $items[count($items) >> 1] ?? $items[0];

            $wind = null;
            if (isset($middayItem['wind']['speed'])) {
                $windSpeed = Speed::fromMetersPerSecond($middayItem['wind']['speed']);
                $windDir = isset($middayItem['wind']['deg'])
                    ? WindDirection::fromDegrees($middayItem['wind']['deg'])
                    : WindDirection::VARIABLE;
                $wind = new Wind($windSpeed, $windDir, degrees: $middayItem['wind']['deg'] ?? null);
            }

            $periods[] = new ForecastPeriod(
                date: new DateTimeImmutable($date),
                temperature: Temperature::fromCelsius($tempAvg),
                condition: $this->mapWeatherCode($middayItem['weather'][0]['id']),
                highTemperature: Temperature::fromCelsius($tempMax),
                lowTemperature: Temperature::fromCelsius($tempMin),
                wind: $wind,
                precipitationProbability: isset($middayItem['pop']) ? $middayItem['pop'] : null,
                precipitationAmount: ($middayItem['rain']['3h'] ?? 0) + ($middayItem['snow']['3h'] ?? 0),
                cloudCover: $middayItem['clouds']['all'] ?? null
            );
        }

        return new Forecast($location, $periods);
    }

    /**
     * Parse wind data.
     */
    private function parseWind(array $data): ?Wind
    {
        if (!isset($data['wind']['speed'])) {
            return null;
        }

        $speed = Speed::fromMetersPerSecond($data['wind']['speed']);
        $direction = isset($data['wind']['deg'])
            ? WindDirection::fromDegrees($data['wind']['deg'])
            : WindDirection::VARIABLE;

        $gusts = isset($data['wind']['gust'])
            ? Speed::fromMetersPerSecond($data['wind']['gust'])
            : null;

        return new Wind($speed, $direction, $gusts, $data['wind']['deg'] ?? null);
    }

    /**
     * Map OpenWeatherMap weather condition code to WeatherCondition enum.
     *
     * OWM uses codes like 800 (clear), 801-804 (clouds), 2xx (thunderstorm), etc.
     */
    private function mapWeatherCode(int $code): WeatherCondition
    {
        return match (true) {
            $code === 800 => WeatherCondition::CLEAR,
            $code === 801 => WeatherCondition::PARTLY_CLOUDY,
            $code === 802 => WeatherCondition::PARTLY_CLOUDY,
            $code === 803 => WeatherCondition::CLOUDY,
            $code === 804 => WeatherCondition::OVERCAST,
            $code >= 200 && $code < 300 => WeatherCondition::THUNDERSTORM,
            $code >= 300 && $code < 400 => WeatherCondition::DRIZZLE,
            $code >= 500 && $code < 600 => WeatherCondition::RAIN,
            $code >= 600 && $code < 700 => WeatherCondition::SNOW,
            $code >= 700 && $code < 800 => WeatherCondition::FOG,
            default => WeatherCondition::UNKNOWN,
        };
    }

    /**
     * Get units parameter for API.
     */
    private function getUnitsParam(): string
    {
        return match ($this->config->units) {
            \Horde\Service\Weather\ValueObject\Units::IMPERIAL => 'imperial',
            \Horde\Service\Weather\ValueObject\Units::METRIC => 'metric',
            default => 'standard',
        };
    }
}
