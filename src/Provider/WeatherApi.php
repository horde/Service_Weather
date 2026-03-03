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
 * WeatherAPI.com weather provider.
 *
 * Generous free tier: 1 million calls/month.
 * Requires API key from https://www.weatherapi.com/
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
class WeatherApi implements WeatherProviderInterface
{
    private const API_BASE = 'https://api.weatherapi.com/v1';

    public function __construct(
        private readonly Client $httpClient,
        private readonly WeatherConfig $config
    ) {
        if (!$this->config->apiKey) {
            throw new InvalidApiKeyException('WeatherAPI.com requires an API key');
        }
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
        try {
            $response = $this->httpClient->get($url);
            $body = $response->getBody();
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
            humidity: Humidity::fromPercentage((int)$current['humidity']),
            pressure: Pressure::fromMillibars($current['pressure_mb']),
            wind: $this->parseWind($current),
            visibility: $current['vis_km'],
            cloudCover: (int)$current['cloud'],
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
                    ? Humidity::fromPercentage((int)$dayData['avghumidity'])
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
     */
    private function mapWeatherCondition(int $code): WeatherCondition
    {
        return match (true) {
            $code === 1000 => WeatherCondition::CLEAR,
            $code === 1003 => WeatherCondition::PARTLY_CLOUDY,
            $code === 1006 => WeatherCondition::CLOUDY,
            $code === 1009 => WeatherCondition::OVERCAST,
            $code === 1030 || $code === 1135 || $code === 1147 => WeatherCondition::FOG,
            $code >= 1063 && $code <= 1072 => WeatherCondition::DRIZZLE,
            $code >= 1150 && $code <= 1171 => WeatherCondition::DRIZZLE,
            $code >= 1180 && $code <= 1201 => WeatherCondition::RAIN,
            $code >= 1240 && $code <= 1246 => WeatherCondition::RAIN,
            $code === 1237 || ($code >= 1249 && $code <= 1264) => WeatherCondition::SLEET,
            $code >= 1210 && $code <= 1237 => WeatherCondition::SNOW,
            $code >= 1255 && $code <= 1282 => WeatherCondition::SNOW,
            $code >= 1273 && $code <= 1282 => WeatherCondition::THUNDERSTORM,
            $code === 1087 => WeatherCondition::THUNDERSTORM,
            default => WeatherCondition::UNKNOWN,
        };
    }
}
