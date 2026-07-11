<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Domain;

use DateTimeImmutable;
use Horde\Service\Weather\Domain\Station;
use Horde\Service\Weather\ValueObject\Coordinate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Station::class)]
class StationTest extends TestCase
{
    public function testMinimalConstruction(): void
    {
        $station = new Station(
            identifier: 'KJFK',
            name: 'John F. Kennedy International',
            coordinate: Coordinate::fromLatLon(40.6398, -73.7789),
        );

        $this->assertSame('KJFK', $station->identifier);
        $this->assertSame('KJFK', $station->getIdentifier());
        $this->assertSame('John F. Kennedy International', $station->name);
        $this->assertSame('John F. Kennedy International', $station->getName());
        $this->assertEqualsWithDelta(40.6398, $station->coordinate->latitude, 0.0001);
        $this->assertEqualsWithDelta(-73.7789, $station->coordinate->longitude, 0.0001);
        $this->assertSame($station->coordinate, $station->getCoordinate());
        $this->assertNull($station->timezone);
        $this->assertNull($station->getTimezone());
        $this->assertNull($station->elevation);
        $this->assertNull($station->getElevation());
        $this->assertNull($station->sunrise);
        $this->assertNull($station->getSunrise());
        $this->assertNull($station->sunset);
        $this->assertNull($station->getSunset());
    }

    public function testConstructionWithAllFields(): void
    {
        $sunrise = new DateTimeImmutable('2026-07-09 05:32:00');
        $sunset = new DateTimeImmutable('2026-07-09 20:28:00');

        $station = new Station(
            identifier: 'EGLL',
            name: 'London Heathrow',
            coordinate: Coordinate::fromLatLon(51.4700, -0.4543),
            timezone: 'Europe/London',
            elevation: 25.0,
            sunrise: $sunrise,
            sunset: $sunset,
        );

        $this->assertSame('Europe/London', $station->timezone);
        $this->assertSame('Europe/London', $station->getTimezone());
        $this->assertSame(25.0, $station->elevation);
        $this->assertSame(25.0, $station->getElevation());
        $this->assertSame($sunrise, $station->sunrise);
        $this->assertSame($sunset, $station->sunset);
    }

    public function testGetUtcOffsetWithoutTimezoneReturnsNull(): void
    {
        $station = new Station(
            identifier: 'X',
            name: 'X',
            coordinate: Coordinate::fromLatLon(0.0, 0.0),
        );

        $this->assertNull($station->getUtcOffset());
    }

    public function testGetUtcOffsetAppliesTimezoneAtGivenInstant(): void
    {
        $station = new Station(
            identifier: 'EGLL',
            name: 'London Heathrow',
            coordinate: Coordinate::fromLatLon(51.4700, -0.4543),
            timezone: 'Europe/London',
        );

        // Mid-summer: London is in BST (UTC+1).
        $summer = new DateTimeImmutable('2026-07-09 12:00:00 UTC');
        $this->assertSame(3600, $station->getUtcOffset($summer));

        // Mid-winter: London is in GMT (UTC+0).
        $winter = new DateTimeImmutable('2026-01-09 12:00:00 UTC');
        $this->assertSame(0, $station->getUtcOffset($winter));
    }

    public function testGetUtcOffsetDefaultsToNowWhenNoInstantGiven(): void
    {
        $station = new Station(
            identifier: 'KJFK',
            name: 'X',
            coordinate: Coordinate::fromLatLon(40.0, -74.0),
            timezone: 'America/New_York',
        );

        // Without asserting a specific offset (depends on today's DST state),
        // just confirm it returns an integer in the expected range.
        $offset = $station->getUtcOffset();
        $this->assertIsInt($offset);
        $this->assertGreaterThanOrEqual(-5 * 3600, $offset);
        $this->assertLessThanOrEqual(-4 * 3600, $offset);
    }
}
