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
 * US National Weather Service (NWS) API provider.
 *
 * Official US government weather data.
 * - Free, unlimited, no API key required
 * - US locations only
 * - High quality official data
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
class NationalWeatherService implements WeatherProviderInterface
{
    private const API_BASE = 'https://api.weather.gov';

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

        // Step 1: Get grid point data for the location
        $pointUrl = $this->buildUrl('/points/' . $coordinate->latitude . ',' . $coordinate->longitude);
        $pointData = $this->makeRequest($pointUrl);

        // Step 2: Get latest observation from the nearest station
        $stationsUrl = $pointData['properties']['observationStations'];
        $stationsData = $this->makeRequest($stationsUrl);

        if (empty($stationsData['features'])) {
            throw new ApiException('No weather stations found for this location');
        }

        $stationId = basename($stationsData['features'][0]['id']);
        $observationUrl = $this->buildUrl('/stations/' . $stationId . '/observations/latest');
        $obsData = $this->makeRequest($observationUrl);

        return $this->parseCurrentWeather($obsData, $location);
    }

    /**
     * {@inheritdoc}
     */
    public function getForecast(Location|string $location, int $days = 5): Forecast
    {
        $coordinate = $this->normalizeLocation($location);

        // Step 1: Get grid point data
        $pointUrl = $this->buildUrl('/points/' . $coordinate->latitude . ',' . $coordinate->longitude);
        $pointData = $this->makeRequest($pointUrl);

        // Step 2: Get forecast
        $forecastUrl = $pointData['properties']['forecast'];
        $forecastData = $this->makeRequest($forecastUrl);

        return $this->parseForecast($forecastData, $location);
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
        try {
            $response = $this->httpClient->get($url, [
                'User-Agent' => 'Horde_Service_Weather (https://www.horde.org/)',
            ]);

            $body = $response->getBody();
            $data = json_decode($body, true);

            if (!is_array($data)) {
                throw new ApiException('Invalid JSON response from NWS API');
            }

            if (isset($data['status']) && $data['status'] >= 400) {
                $message = $data['title'] ?? $data['detail'] ?? 'Unknown error';
                throw new ApiException('NWS API error: ' . $message);
            }

            return $data;
        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ApiException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }
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
                ? Humidity::fromPercentage((int)round($props['relativeHumidity']['value']))
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
                $windDir = WindDirection::from($mainPeriod['windDirection'] ?? 'VAR');
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
     * Parse NWS value structure (value + unitCode).
     */
    private function parseValue(?array $value): ?Temperature
    {
        if (!$value || !isset($value['value']) || $value['value'] === null) {
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
        if (!isset($props['windSpeed']['value']) || $props['windSpeed']['value'] === null) {
            return null;
        }

        // Wind speed in km/h
        $speed = Speed::fromKilometersPerHour($props['windSpeed']['value']);

        $direction = isset($props['windDirection']['value'])
            ? WindDirection::fromDegrees($props['windDirection']['value'])
            : WindDirection::VARIABLE;

        $gusts = isset($props['windGust']['value']) && $props['windGust']['value'] !== null
            ? Speed::fromKilometersPerHour($props['windGust']['value'])
            : null;

        return new Wind($speed, $direction, $gusts, $props['windDirection']['value'] ?? null);
    }

    /**
     * Parse wind speed from string like "15 mph" or "10 to 15 mph".
     */
    private function parseWindSpeed(string $windSpeed): Speed
    {
        // Extract first number
        if (preg_match('/(\d+)/', $windSpeed, $matches)) {
            $speed = (float)$matches[1];

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
