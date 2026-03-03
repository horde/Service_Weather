<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Domain;

use DateTimeImmutable;
use Horde\Service\Weather\Domain\Forecast;
use Horde\Service\Weather\Domain\ForecastPeriod;
use Horde\Service\Weather\ValueObject\Location;
use Horde\Service\Weather\ValueObject\Temperature;
use Horde\Service\Weather\ValueObject\WeatherCondition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Forecast::class)]
class ForecastTest extends TestCase
{
    public function testConstruct(): void
    {
        $location = Location::fromCoordinates(51.5074, -0.1278);
        $periods = [
            new ForecastPeriod(
                new DateTimeImmutable('2026-03-03'),
                Temperature::fromCelsius(15.0),
                WeatherCondition::CLEAR
            ),
            new ForecastPeriod(
                new DateTimeImmutable('2026-03-04'),
                Temperature::fromCelsius(18.0),
                WeatherCondition::CLOUDY
            ),
        ];

        $forecast = new Forecast($location, $periods);

        $this->assertSame($location, $forecast->location);
        $this->assertSame($periods, $forecast->periods);
    }

    public function testGetLocation(): void
    {
        $location = Location::fromCity('London', 'UK');
        $forecast = new Forecast($location, []);

        $this->assertSame($location, $forecast->getLocation());
    }

    public function testGetPeriods(): void
    {
        $location = Location::fromCoordinates(40.7128, -74.0060);
        $periods = [
            new ForecastPeriod(
                new DateTimeImmutable('2026-03-03'),
                Temperature::fromCelsius(12.0),
                WeatherCondition::RAIN
            ),
        ];

        $forecast = new Forecast($location, $periods);

        $this->assertSame($periods, $forecast->getPeriods());
    }

    public function testGetPeriodsCount(): void
    {
        $location = Location::fromCoordinates(48.8566, 2.3522);
        $periods = [
            new ForecastPeriod(
                new DateTimeImmutable('2026-03-03'),
                Temperature::fromCelsius(14.0),
                WeatherCondition::PARTLY_CLOUDY
            ),
            new ForecastPeriod(
                new DateTimeImmutable('2026-03-04'),
                Temperature::fromCelsius(16.0),
                WeatherCondition::CLEAR
            ),
            new ForecastPeriod(
                new DateTimeImmutable('2026-03-05'),
                Temperature::fromCelsius(15.0),
                WeatherCondition::RAIN
            ),
        ];

        $forecast = new Forecast($location, $periods);

        $this->assertSame(3, $forecast->getPeriodsCount());
    }

    public function testGetPeriodsCountEmpty(): void
    {
        $location = Location::fromCoordinates(35.6762, 139.6503);
        $forecast = new Forecast($location, []);

        $this->assertSame(0, $forecast->getPeriodsCount());
    }

    public function testGetPeriod(): void
    {
        $location = Location::fromCoordinates(52.52, 13.405);
        $period1 = new ForecastPeriod(
            new DateTimeImmutable('2026-03-03'),
            Temperature::fromCelsius(10.0),
            WeatherCondition::SNOW
        );
        $period2 = new ForecastPeriod(
            new DateTimeImmutable('2026-03-04'),
            Temperature::fromCelsius(8.0),
            WeatherCondition::CLOUDY
        );

        $forecast = new Forecast($location, [$period1, $period2]);

        $this->assertSame($period1, $forecast->getPeriod(0));
        $this->assertSame($period2, $forecast->getPeriod(1));
    }

    public function testGetPeriodInvalidIndex(): void
    {
        $location = Location::fromCoordinates(37.7749, -122.4194);
        $forecast = new Forecast($location, []);

        $this->assertNull($forecast->getPeriod(0));
        $this->assertNull($forecast->getPeriod(10));
    }
}
