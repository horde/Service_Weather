<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Provider;

use Horde\Service\Weather\AirQualityProvider;
use Horde\Service\Weather\Exception\InvalidApiKeyException;
use Horde\Service\Weather\ForecastCapabilities;
use Horde\Service\Weather\HourlyForecast;
use Horde\Service\Weather\Provider\OpenWeatherMap;
use Horde\Http\RequestFactory;
use Horde\Service\Weather\Test\Support\MockHttpClient;
use Horde\Service\Weather\ValueObject\ForecastDetail;
use Horde\Service\Weather\ValueObject\Location;
use Horde\Service\Weather\ValueObject\WeatherCondition;
use Horde\Service\Weather\ValueObject\WeatherConfig;
use Horde\Service\Weather\WeatherProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpenWeatherMap::class)]
class OpenWeatherMapTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures/owm';

    private const BERLIN_LAT = 52.52;
    private const BERLIN_LON = 13.41;

    private function config(): WeatherConfig
    {
        return WeatherConfig::default()->withApiKey('test-key');
    }

    public function testImplementsExpectedInterfaces(): void
    {
        $owm = new OpenWeatherMap(new MockHttpClient(), new RequestFactory(), $this->config());

        $this->assertInstanceOf(WeatherProvider::class, $owm);
        $this->assertInstanceOf(ForecastCapabilities::class, $owm);
        $this->assertInstanceOf(HourlyForecast::class, $owm);
        $this->assertInstanceOf(AirQualityProvider::class, $owm);
    }

    public function testConstructorRequiresApiKey(): void
    {
        $this->expectException(InvalidApiKeyException::class);
        new OpenWeatherMap(new MockHttpClient(), new RequestFactory(), WeatherConfig::default());
    }

    public function testGetSupportedForecastLengths(): void
    {
        $owm = new OpenWeatherMap(new MockHttpClient(), new RequestFactory(), $this->config());

        $this->assertSame([1, 2, 3, 4, 5], $owm->getSupportedForecastLengths());
    }

    public function testGetCurrentWeatherParsesBerlinFixture(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/current-berlin.json');

        $owm = new OpenWeatherMap($http, new RequestFactory(), $this->config());
        $current = $owm->getCurrentWeather(Location::fromCoordinates(self::BERLIN_LAT, self::BERLIN_LON));

        $this->assertSame(21.5, $current->temperature->toCelsius());
        // OWM code 803 ("broken clouds") → CLOUDY.
        $this->assertSame(WeatherCondition::CLOUDY, $current->condition);
        $this->assertNotNull($current->humidity);
        $this->assertSame(62, $current->humidity->getPercentage());
        $this->assertNotNull($current->pressure);
        $this->assertEqualsWithDelta(1015.0, $current->pressure->getMillibars(), 0.1);
        $this->assertSame(75, $current->cloudCover);
        $this->assertNotNull($current->wind);
    }

    public function testGetForecastGroupsByDay(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/forecast-berlin.json');

        $owm = new OpenWeatherMap($http, new RequestFactory(), $this->config());
        $forecast = $owm->getForecast(Location::fromCoordinates(self::BERLIN_LAT, self::BERLIN_LON), 3);

        $this->assertSame(ForecastDetail::DAILY, $forecast->detail);
        // Fixture spans 2026-07-09, 2026-07-10, 2026-07-11 (3 days).
        $this->assertSame(3, $forecast->getPeriodsCount());

        $first = $forecast->getPeriod(0);
        $this->assertNotNull($first);
        $this->assertNotNull($first->highTemperature);
        $this->assertNotNull($first->lowTemperature);
    }

    public function testGetHourlyForecastReturnsRawBuckets(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/forecast-berlin.json');

        $owm = new OpenWeatherMap($http, new RequestFactory(), $this->config());
        // Fixture has 5 buckets total.
        $forecast = $owm->getHourlyForecast(Location::fromCoordinates(self::BERLIN_LAT, self::BERLIN_LON), 15);

        $this->assertSame(ForecastDetail::DETAILED, $forecast->detail);
        // Each item in list[] becomes a period. No daily grouping.
        $this->assertSame(5, $forecast->getPeriodsCount());
    }

    public function testGetHourlyForecastConvertsHoursToBuckets(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/forecast-berlin.json');

        $owm = new OpenWeatherMap($http, new RequestFactory(), $this->config());
        $owm->getHourlyForecast(Location::fromCoordinates(self::BERLIN_LAT, self::BERLIN_LON), 24);

        // 24 hours / 3h per bucket = 8 buckets requested.
        $url = $http->getRequestedUrls()[0];
        $this->assertStringContainsString('cnt=8', $url);
    }

    public function testGetHourlyForecastCapsAtFortyBuckets(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/forecast-berlin.json');

        $owm = new OpenWeatherMap($http, new RequestFactory(), $this->config());
        $owm->getHourlyForecast(Location::fromCoordinates(self::BERLIN_LAT, self::BERLIN_LON), 9999);

        $url = $http->getRequestedUrls()[0];
        $this->assertStringContainsString('cnt=40', $url);
    }

    public function testGetAirQualityParsesFixture(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/air-pollution-berlin.json');

        $owm = new OpenWeatherMap($http, new RequestFactory(), $this->config());
        $aq = $owm->getAirQuality(Location::fromCoordinates(self::BERLIN_LAT, self::BERLIN_LON));

        $this->assertSame(8.2, $aq->pm25);
        $this->assertSame(12.4, $aq->pm10);
        $this->assertSame(80.1, $aq->ozone);
        // OWM's `main.aqi` is on their 1-5 scale (European-shaped). Mapped to europeanAqi.
        $this->assertSame(2, $aq->europeanAqi);
        // OWM does not publish US or UK AQIs.
        $this->assertNull($aq->usAqi);
        $this->assertNull($aq->ukDaqi);
    }
}
