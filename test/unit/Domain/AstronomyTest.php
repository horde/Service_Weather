<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Domain;

use DateTimeImmutable;
use Horde\Service\Weather\Domain\Astronomy;
use Horde\Service\Weather\ValueObject\Location;
use Horde\Service\Weather\ValueObject\MoonPhase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Astronomy::class)]
class AstronomyTest extends TestCase
{
    public function testMinimalConstruction(): void
    {
        $date = new DateTimeImmutable('2026-07-09');
        $a = new Astronomy(
            location: Location::fromCoordinates(52.5, 13.4),
            date: $date,
        );

        $this->assertSame($date, $a->getDate());
        $this->assertNull($a->getSunrise());
        $this->assertNull($a->getSunset());
        $this->assertNull($a->getMoonrise());
        $this->assertNull($a->getMoonset());
        $this->assertSame(MoonPhase::UNKNOWN, $a->getMoonPhase());
        $this->assertNull($a->getMoonIllumination());
    }

    public function testGetDaylightSeconds(): void
    {
        $a = new Astronomy(
            location: Location::fromCoordinates(52.5, 13.4),
            date: new DateTimeImmutable('2026-07-09'),
            sunrise: new DateTimeImmutable('2026-07-09 05:00:00 UTC'),
            sunset: new DateTimeImmutable('2026-07-09 21:30:00 UTC'),
        );

        $this->assertSame(59400, $a->getDaylightSeconds());
    }

    public function testGetDaylightSecondsNullWhenSunriseMissing(): void
    {
        $a = new Astronomy(
            location: Location::fromCoordinates(0, 0),
            date: new DateTimeImmutable('2026-07-09'),
            sunset: new DateTimeImmutable('2026-07-09 21:30:00 UTC'),
        );

        $this->assertNull($a->getDaylightSeconds());
    }

    public function testGetDaylightSecondsNullWhenSunsetMissing(): void
    {
        $a = new Astronomy(
            location: Location::fromCoordinates(0, 0),
            date: new DateTimeImmutable('2026-07-09'),
            sunrise: new DateTimeImmutable('2026-07-09 05:00:00 UTC'),
        );

        $this->assertNull($a->getDaylightSeconds());
    }

    public function testFullConstruction(): void
    {
        $a = new Astronomy(
            location: Location::fromCoordinates(52.5, 13.4),
            date: new DateTimeImmutable('2026-07-09'),
            sunrise: new DateTimeImmutable('2026-07-09 05:00:00 UTC'),
            sunset: new DateTimeImmutable('2026-07-09 21:30:00 UTC'),
            moonrise: new DateTimeImmutable('2026-07-09 18:15:00 UTC'),
            moonset: new DateTimeImmutable('2026-07-10 04:22:00 UTC'),
            moonPhase: MoonPhase::WAXING_GIBBOUS,
            moonIllumination: 72,
        );

        $this->assertSame(MoonPhase::WAXING_GIBBOUS, $a->getMoonPhase());
        $this->assertSame(72, $a->getMoonIllumination());
    }
}
