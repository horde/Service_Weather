<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Provider;

use DateTimeImmutable;
use Horde\Service\Weather\AirQualityProvider;
use Horde\Service\Weather\AstronomyProvider;
use Horde\Service\Weather\Exception\ApiException;
use Horde\Service\Weather\Exception\InvalidApiKeyException;
use Horde\Service\Weather\ForecastCapabilities;
use Horde\Service\Weather\HourlyForecast;
use Horde\Service\Weather\LocationSearch;
use Horde\Service\Weather\Provider\WeatherApi;
use Horde\Http\RequestFactory;
use Horde\Service\Weather\Test\Support\MockHttpClient;
use Horde\Service\Weather\ValueObject\AirQualityCategory;
use Horde\Service\Weather\ValueObject\ForecastDetail;
use Horde\Service\Weather\ValueObject\Location;
use Horde\Service\Weather\ValueObject\MoonPhase;
use Horde\Service\Weather\ValueObject\SearchType;
use Horde\Service\Weather\ValueObject\WeatherCondition;
use Horde\Service\Weather\ValueObject\WeatherConfig;
use Horde\Service\Weather\WeatherProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WeatherApi::class)]
class WeatherApiTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures/weatherapi';

    private const BERLIN_LAT = 52.52;
    private const BERLIN_LON = 13.41;
    private const LONDON_LAT = 51.52;
    private const LONDON_LON = -0.11;

    private function config(): WeatherConfig
    {
        return WeatherConfig::default()->withApiKey('test-key');
    }

    public function testImplementsExpectedInterfaces(): void
    {
        $api = new WeatherApi(new MockHttpClient(), new RequestFactory(), $this->config());

        $this->assertInstanceOf(WeatherProvider::class, $api);
        $this->assertInstanceOf(ForecastCapabilities::class, $api);
        $this->assertInstanceOf(HourlyForecast::class, $api);
        $this->assertInstanceOf(LocationSearch::class, $api);
        $this->assertInstanceOf(AirQualityProvider::class, $api);
        $this->assertInstanceOf(AstronomyProvider::class, $api);
    }

    public function testConstructorRequiresApiKey(): void
    {
        $this->expectException(InvalidApiKeyException::class);
        new WeatherApi(new MockHttpClient(), new RequestFactory(), WeatherConfig::default());
    }

    public function testGetSupportedForecastLengths(): void
    {
        $api = new WeatherApi(new MockHttpClient(), new RequestFactory(), $this->config());

        $this->assertSame(range(1, 14), $api->getSupportedForecastLengths());
    }

    public function testGetCurrentWeatherParsesBerlinFixture(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/current-berlin.json');

        $api = new WeatherApi($http, new RequestFactory(), $this->config());
        $current = $api->getCurrentWeather(Location::fromCoordinates(self::BERLIN_LAT, self::BERLIN_LON));

        $this->assertSame(21.5, $current->temperature->toCelsius());
        // WeatherAPI code 1003 → PARTLY_CLOUDY.
        $this->assertSame(WeatherCondition::PARTLY_CLOUDY, $current->condition);
        $this->assertNotNull($current->humidity);
        $this->assertSame(62, $current->humidity->getPercentage());
        $this->assertNotNull($current->wind);
        $this->assertEqualsWithDelta(16.2, $current->wind->getSpeed()->toKilometersPerHour(), 0.1);
        $this->assertSame(50, $current->cloudCover);
        // UV index is available on current-conditions in WeatherAPI.
        $this->assertSame(6.0, $current->uvIndex);
    }

    public function testInvalidApiKeyRaises(): void
    {
        $http = (new MockHttpClient())
            // WeatherAPI returns 200 with a body-level error object for bad keys.
            ->queueFixture(self::FIXTURES . '/error-invalid-key.json');

        $api = new WeatherApi($http, new RequestFactory(), $this->config());

        $this->expectException(InvalidApiKeyException::class);
        $api->getCurrentWeather(Location::fromCoordinates(self::BERLIN_LAT, self::BERLIN_LON));
    }

    public function testGetForecastGroupsDailyPeriods(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/forecast-berlin.json');

        $api = new WeatherApi($http, new RequestFactory(), $this->config());
        $forecast = $api->getForecast(Location::fromCoordinates(self::BERLIN_LAT, self::BERLIN_LON), 2);

        $this->assertSame(ForecastDetail::DAILY, $forecast->detail);
        $this->assertSame(2, $forecast->getPeriodsCount());
    }

    public function testGetHourlyForecastFlattensDayHourArrays(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/forecast-berlin.json');

        $api = new WeatherApi($http, new RequestFactory(), $this->config());
        $forecast = $api->getHourlyForecast(Location::fromCoordinates(self::BERLIN_LAT, self::BERLIN_LON), 5);

        $this->assertSame(ForecastDetail::DETAILED, $forecast->detail);
        // Fixture has 2 hour entries in day 1, 0 in day 2. Max output = 2.
        $this->assertSame(2, $forecast->getPeriodsCount());
    }

    public function testSearchLocationsHitsSearchEndpoint(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/search-berlin.json');

        $api = new WeatherApi($http, new RequestFactory(), $this->config());
        $results = $api->searchLocations('Berlin');

        $this->assertCount(3, $results);
        $this->assertSame('Berlin', $results[0]->name);
        $this->assertSame('Germany', $results[0]->country);
        $this->assertEqualsWithDelta(52.52, $results[0]->coordinate->latitude, 0.001);
    }

    public function testSearchLocationsWithIcaoTypeUsesMetarPrefix(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/search-berlin.json');

        $api = new WeatherApi($http, new RequestFactory(), $this->config());
        $api->searchLocations('KJFK', SearchType::ICAO);

        // ICAO type prefixes the query with `metar:` per WeatherAPI's convention.
        $url = $http->getRequestedUrls()[0];
        $this->assertStringContainsString('metar%3AKJFK', $url); // url-encoded ':'
    }

    public function testSearchLocationsByIpUsesIpEndpoint(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/ip-8888.json');

        $api = new WeatherApi($http, new RequestFactory(), $this->config());
        $results = $api->searchLocations('8.8.8.8', SearchType::IP_ADDRESS);

        $this->assertCount(1, $results);
        $this->assertSame('Ashburn', $results[0]->name);
        $this->assertSame('United States of America', $results[0]->country);
        $urls = $http->getRequestedUrls();
        $this->assertStringContainsString('/ip.json', $urls[0]);
    }

    public function testSearchLocationsByIpReturnsEmptyOnApiError(): void
    {
        // Bad IPs return an error object; interface promises empty array, not exception.
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/error-invalid-key.json');

        $api = new WeatherApi($http, new RequestFactory(), $this->config());
        $results = $api->searchLocations('not-an-ip', SearchType::IP_ADDRESS);

        $this->assertSame([], $results);
    }

    public function testSearchLocationsEmptyQueryShortCircuits(): void
    {
        $http = new MockHttpClient();

        $api = new WeatherApi($http, new RequestFactory(), $this->config());
        $results = $api->searchLocations('   ');

        $this->assertSame([], $results);
        $this->assertSame(0, $http->getRequestCount());
    }

    public function testGetAirQualityParsesInlineBlock(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/current-aqi-london.json');

        $api = new WeatherApi($http, new RequestFactory(), $this->config());
        $aq = $api->getAirQuality(Location::fromCoordinates(self::LONDON_LAT, self::LONDON_LON));

        $this->assertSame(8.2, $aq->pm25);
        $this->assertSame(12.4, $aq->pm10);
        // us-epa-index=2 → MODERATE band via the WeatherAPI-specific mapping.
        $this->assertSame(AirQualityCategory::MODERATE, $aq->category);
        // gb-defra-index=3 → surfaced as ukDaqi.
        $this->assertSame(3, $aq->ukDaqi);
        // WeatherAPI does not publish a numeric US EPA AQI value; we leave that null.
        $this->assertNull($aq->usAqi);
    }

    public function testGetAstronomyParsesLondonFixture(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/astronomy-london.json');

        $api = new WeatherApi($http, new RequestFactory(), $this->config());
        $astro = $api->getAstronomy(
            Location::fromCoordinates(self::LONDON_LAT, self::LONDON_LON),
            new DateTimeImmutable('2026-07-09'),
        );

        $this->assertNotNull($astro->sunrise);
        $this->assertNotNull($astro->sunset);
        $this->assertNotNull($astro->moonrise);
        $this->assertNotNull($astro->moonset);
        $this->assertSame(MoonPhase::WAXING_GIBBOUS, $astro->moonPhase);
        $this->assertSame(72, $astro->moonIllumination);
    }
}
