<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Provider;

use DateTimeImmutable;
use Horde\Service\Weather\AirQualityProvider;
use Horde\Service\Weather\AstronomyProvider;
use Horde\Service\Weather\ForecastCapabilities;
use Horde\Service\Weather\HourlyForecast;
use Horde\Service\Weather\Provider\OpenMeteo;
use Horde\Http\RequestFactory;
use Horde\Service\Weather\Test\Support\MockHttpClient;
use Horde\Service\Weather\ValueObject\AirQualityCategory;
use Horde\Service\Weather\ValueObject\ForecastDetail;
use Horde\Service\Weather\ValueObject\Location;
use Horde\Service\Weather\ValueObject\MoonPhase;
use Horde\Service\Weather\ValueObject\WeatherCondition;
use Horde\Service\Weather\WeatherProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpenMeteo::class)]
class OpenMeteoTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures/open-meteo';

    private const BERLIN_LAT = 52.52;
    private const BERLIN_LON = 13.41;

    public function testImplementsExpectedInterfaces(): void
    {
        $om = new OpenMeteo(new MockHttpClient(), new RequestFactory());

        $this->assertInstanceOf(WeatherProvider::class, $om);
        $this->assertInstanceOf(ForecastCapabilities::class, $om);
        $this->assertInstanceOf(HourlyForecast::class, $om);
        $this->assertInstanceOf(AirQualityProvider::class, $om);
        $this->assertInstanceOf(AstronomyProvider::class, $om);
    }

    public function testGetSupportedForecastLengths(): void
    {
        $om = new OpenMeteo(new MockHttpClient(), new RequestFactory());

        $this->assertSame(range(1, 16), $om->getSupportedForecastLengths());
    }

    public function testGetCurrentWeatherParsesBerlinFixture(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/current-berlin.json');

        $om = new OpenMeteo($http, new RequestFactory());
        $current = $om->getCurrentWeather(Location::fromCoordinates(self::BERLIN_LAT, self::BERLIN_LON));

        // Live-fetched values from Berlin at capture time.
        $this->assertSame(23.9, $current->temperature->toCelsius());
        // Open-Meteo weather_code 1 (mainly clear / few clouds) → PARTLY_CLOUDY.
        $this->assertSame(WeatherCondition::PARTLY_CLOUDY, $current->condition);
        $this->assertNotNull($current->humidity);
        $this->assertSame(45, $current->humidity->getPercentage());
        $this->assertSame(52, $current->cloudCover);
        $this->assertNotNull($current->wind);
        // 4.22 m/s wind, direction 306° → NW cardinal.
        $this->assertEqualsWithDelta(4.22, $current->wind->getSpeed()->toMetersPerSecond(), 0.05);
    }

    public function testGetForecastParsesBerlinDailyFixture(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/forecast-berlin.json');

        $om = new OpenMeteo($http, new RequestFactory());
        $forecast = $om->getForecast(Location::fromCoordinates(self::BERLIN_LAT, self::BERLIN_LON), 5);

        // Default detail is DAILY.
        $this->assertSame(ForecastDetail::DAILY, $forecast->detail);
        $this->assertGreaterThanOrEqual(5, $forecast->getPeriodsCount());

        $first = $forecast->getPeriod(0);
        $this->assertNotNull($first);
        $this->assertNotNull($first->highTemperature);
        $this->assertNotNull($first->lowTemperature);
        // High should always exceed low.
        $this->assertGreaterThan(
            $first->lowTemperature->toCelsius(),
            $first->highTemperature->toCelsius(),
        );
    }

    public function testGetHourlyForecastPopulatesUvAndSnowfall(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/hourly-berlin.json');

        $om = new OpenMeteo($http, new RequestFactory());
        $forecast = $om->getHourlyForecast(Location::fromCoordinates(self::BERLIN_LAT, self::BERLIN_LON), 24);

        $this->assertSame(ForecastDetail::DETAILED, $forecast->detail);
        $this->assertSame(24, $forecast->getPeriodsCount());

        // uvIndex and snowfallAmount are pulled through from the response.
        // Even if a specific hour has zero snowfall, the field should be populated
        // (0.0 is a valid non-null value).
        $anyHasUv = false;
        $anyHasSnowFieldPopulated = false;
        foreach ($forecast as $period) {
            if ($period->uvIndex !== null) {
                $anyHasUv = true;
            }
            if ($period->snowfallAmount !== null) {
                $anyHasSnowFieldPopulated = true;
            }
        }
        $this->assertTrue($anyHasUv, 'expected at least one hour to expose uvIndex');
        $this->assertTrue($anyHasSnowFieldPopulated, 'expected snowfallAmount to be populated (may be 0.0)');
    }

    public function testGetHourlyForecastCapsHoursTo384(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/hourly-berlin.json');

        $om = new OpenMeteo($http, new RequestFactory());
        // Ask for absurd number of hours. Provider should silently cap.
        $om->getHourlyForecast(Location::fromCoordinates(self::BERLIN_LAT, self::BERLIN_LON), 9999);

        // The forecast_hours param should be clamped to 384.
        $url = $http->getRequestedUrls()[0];
        $this->assertStringContainsString('forecast_hours=384', $url);
    }

    public function testGetAirQualityParsesBerlinFixture(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/air-quality-berlin.json');

        $om = new OpenMeteo($http, new RequestFactory());
        $aq = $om->getAirQuality(Location::fromCoordinates(self::BERLIN_LAT, self::BERLIN_LON));

        $this->assertSame(3.4, $aq->pm25);
        $this->assertSame(5.3, $aq->pm10);
        $this->assertSame(76.0, $aq->ozone);
        $this->assertSame(28, $aq->usAqi);
        $this->assertSame(30, $aq->europeanAqi);
        // Open-Meteo does not publish UK DAQI.
        $this->assertNull($aq->ukDaqi);
        // usAqi=28 → GOOD band.
        $this->assertSame(AirQualityCategory::GOOD, $aq->getCategory());
    }

    public function testGetAstronomySunOnlyMoonFieldsNull(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/astronomy-berlin.json');

        $om = new OpenMeteo($http, new RequestFactory());
        $astro = $om->getAstronomy(
            Location::fromCoordinates(self::BERLIN_LAT, self::BERLIN_LON),
            new DateTimeImmutable('2026-07-09'),
        );

        $this->assertNotNull($astro->sunrise);
        $this->assertNotNull($astro->sunset);
        // Open-Meteo does not offer moon data.
        $this->assertNull($astro->moonrise);
        $this->assertNull($astro->moonset);
        $this->assertSame(MoonPhase::UNKNOWN, $astro->moonPhase);
        $this->assertNull($astro->moonIllumination);
    }
}
