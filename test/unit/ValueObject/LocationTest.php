<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

use Horde\Service\Weather\ValueObject\Coordinate;
use Horde\Service\Weather\ValueObject\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Location::class)]
class LocationTest extends TestCase
{
    public function testFromCoordinates(): void
    {
        $location = Location::fromCoordinates(52.52, 13.405);
        $this->assertSame(52.52, $location->coordinate->latitude);
        $this->assertSame(13.405, $location->coordinate->longitude);
        $this->assertTrue($location->hasCoordinates());
    }

    public function testFromCoordinate(): void
    {
        $coord = Coordinate::fromLatLon(40.7128, -74.0060);
        $location = Location::fromCoordinate($coord);
        $this->assertSame($coord, $location->coordinate);
        $this->assertTrue($location->hasCoordinates());
    }

    public function testFromCity(): void
    {
        $location = Location::fromCity('Berlin', 'Germany');
        $this->assertSame('Berlin', $location->name);
        $this->assertSame('Germany', $location->country);
        $this->assertFalse($location->hasCoordinates());
    }

    public function testFromCityWithoutCountry(): void
    {
        $location = Location::fromCity('London');
        $this->assertSame('London', $location->name);
        $this->assertNull($location->country);
    }

    public function testFromIdentifier(): void
    {
        $location = Location::fromIdentifier('EDDB');
        $this->assertSame('EDDB', $location->identifier);
        $this->assertFalse($location->hasCoordinates());
    }

    public function testGetCoordinate(): void
    {
        $location = Location::fromCoordinates(51.5074, -0.1278);
        $coord = $location->getCoordinate();
        $this->assertSame(51.5074, $coord->latitude);
        $this->assertSame(-0.1278, $coord->longitude);
    }

    public function testGetDisplayNameWithCityAndCountry(): void
    {
        $location = Location::fromCity('Paris', 'France');
        $this->assertSame('Paris, France', $location->getDisplayName());
    }

    public function testGetDisplayNameWithCityOnly(): void
    {
        $location = Location::fromCity('Tokyo');
        $this->assertSame('Tokyo', $location->getDisplayName());
    }

    public function testGetDisplayNameWithIdentifier(): void
    {
        $location = Location::fromIdentifier('90210');
        $this->assertSame('90210', $location->getDisplayName());
    }

    public function testGetDisplayNameWithCoordinates(): void
    {
        $location = Location::fromCoordinates(35.6762, 139.6503);
        $this->assertSame('35.676200,139.650300', $location->getDisplayName());
    }

    public function testHasCoordinatesTrue(): void
    {
        $location = Location::fromCoordinates(48.8566, 2.3522);
        $this->assertTrue($location->hasCoordinates());
    }

    public function testHasCoordinatesFalseForCity(): void
    {
        $location = Location::fromCity('New York');
        $this->assertFalse($location->hasCoordinates());
    }

    public function testHasCoordinatesFalseForIdentifier(): void
    {
        $location = Location::fromIdentifier('KJFK');
        $this->assertFalse($location->hasCoordinates());
    }
}
