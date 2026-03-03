<?php

declare(strict_types=1);

namespace Horde\Service\Weather;

use Horde\Http\Client;
use Horde\Service\Weather\Provider\NationalWeatherService;
use Horde\Service\Weather\Provider\OpenMeteo;
use Horde\Service\Weather\Provider\OpenWeatherMap;
use Horde\Service\Weather\Provider\WeatherApi;
use Horde\Service\Weather\ValueObject\WeatherConfig;

/**
 * Weather service facade.
 *
 * Simplified interface for weather operations using modern PSR-4 providers.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
class Weather
{
    private readonly WeatherProviderInterface $provider;

    /**
     * Create weather service with specified provider.
     *
     * @param WeatherProviderInterface $provider Weather provider instance
     */
    public function __construct(WeatherProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Create weather service with Open-Meteo provider (no API key required).
     *
     * @param Client|null $httpClient HTTP client (optional, will create default)
     * @param WeatherConfig|null $config Configuration (optional, will use defaults)
     * @return self
     */
    public static function openMeteo(?Client $httpClient = null, ?WeatherConfig $config = null): self
    {
        $httpClient ??= new Client();
        $config ??= WeatherConfig::default();

        return new self(new OpenMeteo($httpClient, $config));
    }

    /**
     * Create weather service with OpenWeatherMap provider.
     *
     * Requires API key from https://openweathermap.org/api
     * Free tier: 1000 calls/day
     *
     * @param string $apiKey OpenWeatherMap API key
     * @param Client|null $httpClient HTTP client (optional)
     * @param WeatherConfig|null $config Configuration (optional)
     * @return self
     */
    public static function openWeatherMap(
        string $apiKey,
        ?Client $httpClient = null,
        ?WeatherConfig $config = null
    ): self {
        $httpClient ??= new Client();
        $config = ($config ?? WeatherConfig::default())->withApiKey($apiKey);

        return new self(new OpenWeatherMap($httpClient, $config));
    }

    /**
     * Create weather service with WeatherAPI.com provider.
     *
     * Requires API key from https://www.weatherapi.com/
     * Free tier: 1 million calls/month
     *
     * @param string $apiKey WeatherAPI.com API key
     * @param Client|null $httpClient HTTP client (optional)
     * @param WeatherConfig|null $config Configuration (optional)
     * @return self
     */
    public static function weatherApi(
        string $apiKey,
        ?Client $httpClient = null,
        ?WeatherConfig $config = null
    ): self {
        $httpClient ??= new Client();
        $config = ($config ?? WeatherConfig::default())->withApiKey($apiKey);

        return new self(new WeatherApi($httpClient, $config));
    }

    /**
     * Create weather service with US National Weather Service provider.
     *
     * No API key required. US locations only.
     *
     * @param Client|null $httpClient HTTP client (optional)
     * @param WeatherConfig|null $config Configuration (optional)
     * @return self
     */
    public static function nationalWeatherService(
        ?Client $httpClient = null,
        ?WeatherConfig $config = null
    ): self {
        $httpClient ??= new Client();
        $config ??= WeatherConfig::default();

        return new self(new NationalWeatherService($httpClient, $config));
    }

    /**
     * Get current weather conditions.
     *
     * @param ValueObject\Location|string $location Location object or coordinate string "lat,lon"
     * @return Domain\CurrentWeather
     * @throws WeatherException
     */
    public function getCurrentWeather(ValueObject\Location|string $location): Domain\CurrentWeather
    {
        return $this->provider->getCurrentWeather($location);
    }

    /**
     * Get weather forecast.
     *
     * @param ValueObject\Location|string $location Location object or coordinate string "lat,lon"
     * @param int $days Number of forecast days (default 5)
     * @return Domain\Forecast
     * @throws WeatherException
     */
    public function getForecast(ValueObject\Location|string $location, int $days = 5): Domain\Forecast
    {
        return $this->provider->getForecast($location, $days);
    }

    /**
     * Get the underlying provider instance.
     *
     * @return WeatherProviderInterface
     */
    public function getProvider(): WeatherProviderInterface
    {
        return $this->provider;
    }
}
