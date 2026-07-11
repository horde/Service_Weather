<?php

declare(strict_types=1);

namespace Horde\Service\Weather;

use Horde\Service\Weather\Provider\Metar;
use Horde\Service\Weather\Provider\NationalWeatherService;
use Horde\Service\Weather\Provider\OpenMeteo;
use Horde\Service\Weather\Provider\OpenWeatherMap;
use Horde\Service\Weather\Provider\WeatherApi;
use Horde\Service\Weather\ValueObject\WeatherConfig;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Weather service facade.
 *
 * Convenience factories that wire a PSR-18 client and PSR-17
 * request factory into a specific provider. Callers supply their
 * own HTTP client (`horde/http` ships PSR-18 `Client\Curl` and
 * `Client\Mock`; Guzzle, Symfony HttpClient and every other modern
 * PHP HTTP library also implement PSR-18).
 *
 * The optional response and stream factories are only used when
 * `$config->cache` is set, because the caching decorator needs to
 * rebuild PSR-7 responses from cached tuples. Providers ignore both
 * when caching is off.
 *
 * Consumers who want to construct a provider directly (bypassing the
 * facade) may. The facade adds no semantics beyond factory sugar and
 * an `instanceof`-friendly `getProvider()` accessor.
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
    private readonly WeatherProvider $provider;

    /**
     * Create weather service with specified provider.
     */
    public function __construct(WeatherProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Create weather service with Open-Meteo provider (no API key required).
     */
    public static function openMeteo(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        ?WeatherConfig $config = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ): self {
        return new self(new OpenMeteo(
            $httpClient,
            $requestFactory,
            $config ?? WeatherConfig::default(),
            $responseFactory,
            $streamFactory,
        ));
    }

    /**
     * Create weather service with OpenWeatherMap provider.
     *
     * Requires an API key from https://openweathermap.org/api
     * Free tier: 1000 calls/day
     */
    public static function openWeatherMap(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        string $apiKey,
        ?WeatherConfig $config = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ): self {
        $config = ($config ?? WeatherConfig::default())->withApiKey($apiKey);

        return new self(new OpenWeatherMap(
            $httpClient,
            $requestFactory,
            $config,
            $responseFactory,
            $streamFactory,
        ));
    }

    /**
     * Create weather service with WeatherAPI.com provider.
     *
     * Requires an API key from https://www.weatherapi.com/
     * Free tier: 1 million calls/month
     */
    public static function weatherApi(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        string $apiKey,
        ?WeatherConfig $config = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ): self {
        $config = ($config ?? WeatherConfig::default())->withApiKey($apiKey);

        return new self(new WeatherApi(
            $httpClient,
            $requestFactory,
            $config,
            $responseFactory,
            $streamFactory,
        ));
    }

    /**
     * Create weather service with US National Weather Service provider.
     *
     * No API key required. US locations only. Custom User-Agent
     * identifying the caller is required by NWS terms; set it via
     * `WeatherConfig::withUserAgent()`.
     */
    public static function nationalWeatherService(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        ?WeatherConfig $config = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ): self {
        return new self(new NationalWeatherService(
            $httpClient,
            $requestFactory,
            $config ?? WeatherConfig::default(),
            $responseFactory,
            $streamFactory,
        ));
    }

    /**
     * Create weather service with the aviationweather.gov METAR/TAF provider.
     *
     * No API key required. Global aviation stations (ICAO codes).
     */
    public static function metar(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        ?WeatherConfig $config = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ): self {
        return new self(new Metar(
            $httpClient,
            $requestFactory,
            $config ?? WeatherConfig::default(),
            $responseFactory,
            $streamFactory,
        ));
    }

    /**
     * Get current weather conditions.
     *
     * @throws WeatherException
     */
    public function getCurrentWeather(ValueObject\Location|string $location): Domain\CurrentWeather
    {
        return $this->provider->getCurrentWeather($location);
    }

    /**
     * Get weather forecast.
     *
     * @throws WeatherException
     */
    public function getForecast(ValueObject\Location|string $location, int $days = 5): Domain\Forecast
    {
        return $this->provider->getForecast($location, $days);
    }

    /**
     * Get the underlying provider instance.
     */
    public function getProvider(): WeatherProvider
    {
        return $this->provider;
    }
}
