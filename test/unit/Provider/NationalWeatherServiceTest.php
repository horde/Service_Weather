<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Provider;

use Horde\Service\Weather\Exception\StationNotFoundException;
use Horde\Service\Weather\ForecastCapabilities;
use Horde\Service\Weather\HourlyForecast;
use Horde\Service\Weather\Provider\NationalWeatherService;
use Horde\Service\Weather\StationLookup;
use Horde\Http\RequestFactory;
use Horde\Service\Weather\Test\Support\MockHttpClient;
use Horde\Service\Weather\ValueObject\Coordinate;
use Horde\Service\Weather\ValueObject\ForecastDetail;
use Horde\Service\Weather\ValueObject\Location;
use Horde\Service\Weather\ValueObject\WeatherCondition;
use Horde\Service\Weather\ValueObject\WeatherConfig;
use Horde\Service\Weather\WeatherProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NationalWeatherService::class)]
class NationalWeatherServiceTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures/nws';

    // Denver, near DIA. The fixture point.
    private const LAT = 39.8617;
    private const LON = -104.6731;

    public function testImplementsExpectedInterfaces(): void
    {
        $nws = new NationalWeatherService(new MockHttpClient(), new RequestFactory());

        $this->assertInstanceOf(WeatherProvider::class, $nws);
        $this->assertInstanceOf(StationLookup::class, $nws);
        $this->assertInstanceOf(ForecastCapabilities::class, $nws);
        $this->assertInstanceOf(HourlyForecast::class, $nws);
    }

    public function testGetSupportedForecastLengths(): void
    {
        $nws = new NationalWeatherService(new MockHttpClient(), new RequestFactory());

        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $nws->getSupportedForecastLengths());
    }

    public function testGetCurrentWeatherThreeHopFlow(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/points-denver.json')
            ->queueFixture(self::FIXTURES . '/stations-denver.json')
            ->queueFixture(self::FIXTURES . '/observation-denver.json');

        $nws = new NationalWeatherService($http, new RequestFactory());
        $current = $nws->getCurrentWeather(Location::fromCoordinates(self::LAT, self::LON));

        $this->assertSame(18.9, $current->temperature->toCelsius());
        $this->assertSame(WeatherCondition::CLOUDY, $current->condition);
        $this->assertNotNull($current->humidity);
        $this->assertEqualsWithDelta(70, $current->humidity->getPercentage(), 1);
        $this->assertNotNull($current->wind);
        // 9.36 km/h wind (rounded through Speed's internal m/s conversion).
        $this->assertEqualsWithDelta(9.36, $current->wind->getSpeed()->toKilometersPerHour(), 0.1);

        // Three sequential requests: points → stations → observation.
        $urls = $http->getRequestedUrls();
        $this->assertCount(3, $urls);
        $this->assertStringContainsString('/points/', $urls[0]);
        $this->assertStringContainsString('/gridpoints/BOU/74,66/stations', $urls[1]);
        $this->assertStringContainsString('observations/latest', $urls[2]);
    }

    public function testGetForecastDailyGrouping(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/points-denver.json')
            ->queueFixture(self::FIXTURES . '/forecast-denver.json');

        $nws = new NationalWeatherService($http, new RequestFactory());
        $forecast = $nws->getForecast(Location::fromCoordinates(self::LAT, self::LON), 5);

        // NWS returns day/night periods (14 in the fixture); grouped by
        // date, that collapses to 7 daily entries.
        $this->assertSame(ForecastDetail::DAILY, $forecast->detail);
        $this->assertGreaterThan(0, $forecast->getPeriodsCount());
        $this->assertLessThanOrEqual(8, $forecast->getPeriodsCount());
    }

    public function testGetHourlyForecast(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/points-denver.json')
            ->queueFixture(self::FIXTURES . '/forecast-hourly-denver.json');

        $nws = new NationalWeatherService($http, new RequestFactory());
        $forecast = $nws->getHourlyForecast(Location::fromCoordinates(self::LAT, self::LON), 48);

        $this->assertSame(ForecastDetail::DETAILED, $forecast->detail);
        $this->assertLessThanOrEqual(48, $forecast->getPeriodsCount());
        $this->assertGreaterThan(0, $forecast->getPeriodsCount());
    }

    public function testGetHourlyForecastCapsHoursTo156(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/points-denver.json')
            ->queueFixture(self::FIXTURES . '/forecast-hourly-denver.json');

        $nws = new NationalWeatherService($http, new RequestFactory());
        // NWS's hourly maxes at 156 hours; provider clamps.
        $forecast = $nws->getHourlyForecast(Location::fromCoordinates(self::LAT, self::LON), 9999);

        $this->assertLessThanOrEqual(156, $forecast->getPeriodsCount());
    }

    public function testGetStationParsesSingleStationFixture(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/station-single-denver.json');

        $nws = new NationalWeatherService($http, new RequestFactory());
        $station = $nws->getStation('KDEN');

        $this->assertSame('KDEN', $station->identifier);
        $this->assertStringContainsString('Denver', $station->name);
        $this->assertSame('America/Denver', $station->timezone);
        $this->assertNotNull($station->elevation);
    }

    public function testGetStationRaisesForNonexistent(): void
    {
        $http = new MockHttpClient();
        $http->queue('{"status":404,"title":"Not Found"}', 200);

        $nws = new NationalWeatherService($http, new RequestFactory());

        $this->expectException(StationNotFoundException::class);
        $nws->getStation('ZZZZ');
    }

    public function testFindStationsNearReturnsFromObservationStations(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/points-denver.json')
            ->queueFixture(self::FIXTURES . '/stations-denver.json');

        $nws = new NationalWeatherService($http, new RequestFactory());
        $stations = $nws->findStationsNear(Coordinate::fromLatLon(self::LAT, self::LON), 3);

        $this->assertCount(3, $stations);
        // First station in the fixture is KDEN (Denver International).
        $this->assertSame('KDEN', $stations[0]->identifier);
        foreach ($stations as $s) {
            $this->assertNotSame('', $s->identifier);
        }
    }

    public function testCustomUserAgentIsSentFromConfig(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/station-single-denver.json');

        $config = WeatherConfig::default()->withUserAgent('nws-consumer-app/1.0 (+https://example.com)');
        $nws = new NationalWeatherService($http, new RequestFactory(), $config);
        $nws->getStation('KDEN');

        $this->assertSame(
            'nws-consumer-app/1.0 (+https://example.com)',
            $http->getLastHeaders()['User-Agent'] ?? null,
        );
    }

    public function testDefaultUserAgentIsSentWhenConfigOmitsIt(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/station-single-denver.json');

        $nws = new NationalWeatherService($http, new RequestFactory());
        $nws->getStation('KDEN');

        $ua = $http->getLastHeaders()['User-Agent'] ?? '';
        $this->assertStringContainsString('Horde_Service_Weather', $ua);
    }
}
