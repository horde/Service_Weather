<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Provider;

use Horde\Service\Weather\AirQualityProvider;
use Horde\Service\Weather\Domain\Astronomy;
use Horde\Service\Weather\Exception\StationNotFoundException;
use Horde\Service\Weather\ForecastCapabilities;
use Horde\Service\Weather\Provider\Metar;
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

#[CoversClass(Metar::class)]
class MetarTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures/metar';

    public function testImplementsExpectedInterfaces(): void
    {
        $http = new MockHttpClient();
        $metar = new Metar($http, new RequestFactory());

        $this->assertInstanceOf(WeatherProvider::class, $metar);
        $this->assertInstanceOf(StationLookup::class, $metar);
        $this->assertInstanceOf(ForecastCapabilities::class, $metar);
        // Metar deliberately does NOT implement AirQualityProvider or
        // location search. Negative check catches accidental additions.
        $this->assertNotInstanceOf(AirQualityProvider::class, $metar);
    }

    public function testGetSupportedForecastLengths(): void
    {
        $http = new MockHttpClient();
        $metar = new Metar($http, new RequestFactory());

        $this->assertSame([1, 2], $metar->getSupportedForecastLengths());
    }

    public function testGetCurrentWeatherByIcaoParsesKjfkFixture(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/current-kjfk.json');

        $metar = new Metar($http, new RequestFactory());
        $current = $metar->getCurrentWeatherByIcao('KJFK');

        $this->assertSame(24.4, $current->temperature->toCelsius());
        $this->assertNotNull($current->dewpoint);
        $this->assertSame(20.6, $current->dewpoint->toCelsius());
        $this->assertNotNull($current->wind);
        // 7 knots ≈ 12.964 km/h.
        $this->assertEqualsWithDelta(12.96, $current->wind->getSpeed()->toKilometersPerHour(), 0.05);
        $this->assertNotNull($current->pressure);
        $this->assertEqualsWithDelta(1014.3, $current->pressure->getMillibars(), 0.01);
        // Visibility "10+" → 10 miles → 16.09 km.
        $this->assertEqualsWithDelta(16.09, $current->visibility, 0.01);
        // clouds: FEW/FEW/SCT. SCT is the densest → 50%.
        $this->assertSame(50, $current->cloudCover);
        $this->assertNotNull($current->station);
        $this->assertSame('KJFK', $current->station->identifier);
        $this->assertStringContainsString('METAR KJFK', $current->providerData ?? '');
    }

    public function testGetCurrentWeatherByIcaoTrimsAndUppercases(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/current-kjfk.json');

        $metar = new Metar($http, new RequestFactory());
        $current = $metar->getCurrentWeatherByIcao(' kjfk ');

        $this->assertSame('KJFK', $current->station?->identifier);
        $urls = $http->getRequestedUrls();
        $this->assertStringContainsString('ids=KJFK', $urls[0]);
    }

    public function testCurrentWeatherEgllHasNcdClouds(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/current-egll.json');

        $metar = new Metar($http, new RequestFactory());
        $current = $metar->getCurrentWeatherByIcao('EGLL');

        // "NCD" (nil cloud detected) → cover 0, condition CLEAR.
        $this->assertSame(0, $current->cloudCover);
        $this->assertSame(WeatherCondition::CLEAR, $current->condition);
    }

    public function testGetCurrentWeatherByIcaoRaisesForEmptyResponse(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/current-invalid.json');

        $metar = new Metar($http, new RequestFactory());

        $this->expectException(StationNotFoundException::class);
        $metar->getCurrentWeatherByIcao('ZZZZ');
    }

    public function testGetForecastByIcaoParsesTaf(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/taf-kjfk.json');

        $metar = new Metar($http, new RequestFactory());
        $forecast = $metar->getForecastByIcao('KJFK', days: 2);

        $this->assertSame(ForecastDetail::DETAILED, $forecast->detail);
        $this->assertGreaterThan(0, $forecast->getPeriodsCount());
        // Every period should have a date; wind may be set for most.
        foreach ($forecast as $period) {
            $this->assertNotNull($period->date);
        }
    }

    public function testGetStationParsesStationinfo(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/station-kjfk.json');

        $metar = new Metar($http, new RequestFactory());
        $station = $metar->getStation('KJFK');

        $this->assertSame('KJFK', $station->identifier);
        $this->assertStringContainsString('Kennedy', $station->name);
        $this->assertEqualsWithDelta(40.639, $station->coordinate->latitude, 0.001);
        $this->assertEqualsWithDelta(-73.764, $station->coordinate->longitude, 0.001);
        $this->assertSame(3.0, $station->elevation);
    }

    public function testGetStationRaisesForEmptyResponse(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/current-invalid.json');

        $metar = new Metar($http, new RequestFactory());

        $this->expectException(StationNotFoundException::class);
        $metar->getStation('ZZZZ');
    }

    public function testFindStationsNearFiltersNonIcaoAndReturnsClosest(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/stations-near-nyc.json');

        $metar = new Metar($http, new RequestFactory());
        $stations = $metar->findStationsNear(Coordinate::fromLatLon(40.7128, -74.0060), 3);

        // At least one. The NYC-area bbox fixture contains real ICAO stations
        // like KJFK/KLGA/KEWR mixed with buoys. The filter must drop buoys.
        $this->assertNotEmpty($stations);
        foreach ($stations as $s) {
            $this->assertNotSame('', $s->identifier);
            // Every returned station must have an ICAO-shaped id (4 letters,
            // starts with K for US). Buoys have numeric IDs like "44022".
            $this->assertMatchesRegularExpression('/^[A-Z]{4}$/', $s->identifier);
        }
    }

    public function testGetCurrentWeatherResolvesLocationByIdentifier(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/current-kjfk.json');

        $metar = new Metar($http, new RequestFactory());
        $location = Location::fromIdentifier('kjfk');
        $current = $metar->getCurrentWeather($location);

        $this->assertSame('KJFK', $current->station?->identifier);
    }

    public function testGetCurrentWeatherResolvesCoordinateViaFindStationsNear(): void
    {
        $http = (new MockHttpClient())
            // First: bbox lookup finds NYC-area stations.
            ->queueFixture(self::FIXTURES . '/stations-near-nyc.json')
            // Then: METAR fetch for the winning station. The bbox fixture's
            // sorted-first station happens to be KLGA (LaGuardia), so we
            // register a KLGA fixture using the KJFK payload (parser is
            // provider-shape-only; we're testing resolution flow, not
            // content).
            ->queueFixture(self::FIXTURES . '/current-kjfk.json');

        $metar = new Metar($http, new RequestFactory());
        $location = Location::fromCoordinates(40.7128, -74.0060);
        $current = $metar->getCurrentWeather($location);

        // The resolver picked *some* nearby ICAO and fetched a METAR for it.
        $this->assertNotNull($current->station);
        $urls = $http->getRequestedUrls();
        $this->assertGreaterThanOrEqual(2, count($urls));
        $this->assertStringContainsString('bbox=', $urls[0]);
        $this->assertStringContainsString('/metar?ids=', $urls[1]);
    }

    public function testDefaultUserAgentIsSent(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/current-kjfk.json');

        $metar = new Metar($http, new RequestFactory());
        $metar->getCurrentWeatherByIcao('KJFK');

        $ua = $http->getLastHeaders()['User-Agent'] ?? '';
        $this->assertStringContainsString('horde-service-weather', $ua);
    }

    public function testCustomUserAgentFromConfig(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/current-kjfk.json');

        $config = WeatherConfig::default()->withUserAgent('my-cool-app/2.5 (+https://example.com/)');
        $metar = new Metar($http, new RequestFactory(), $config);
        $metar->getCurrentWeatherByIcao('KJFK');

        $ua = $http->getLastHeaders()['User-Agent'] ?? '';
        $this->assertSame('my-cool-app/2.5 (+https://example.com/)', $ua);
    }
}
