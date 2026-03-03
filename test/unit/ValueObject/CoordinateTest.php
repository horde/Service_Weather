<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

use Horde\Service\Weather\Exception\InvalidLocationException;
use Horde\Service\Weather\ValueObject\Coordinate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Coordinate::class)]
class CoordinateTest extends TestCase
{
    public function testFromLatLon(): void
    {
        $coord = Coordinate::fromLatLon(52.52, 13.405);
        $this->assertSame(52.52, $coord->latitude);
        $this->assertSame(13.405, $coord->longitude);
    }

    public function testToString(): void
    {
        $coord = Coordinate::fromLatLon(52.52, 13.405);
        $this->assertSame('52.520000,13.405000', $coord->toString());
    }

    public function testValidLatitudeBoundaries(): void
    {
        $coord1 = Coordinate::fromLatLon(90.0, 0.0);
        $this->assertSame(90.0, $coord1->latitude);

        $coord2 = Coordinate::fromLatLon(-90.0, 0.0);
        $this->assertSame(-90.0, $coord2->latitude);
    }

    public function testValidLongitudeBoundaries(): void
    {
        $coord1 = Coordinate::fromLatLon(0.0, 180.0);
        $this->assertSame(180.0, $coord1->longitude);

        $coord2 = Coordinate::fromLatLon(0.0, -180.0);
        $this->assertSame(-180.0, $coord2->longitude);
    }

    public function testInvalidLatitudeTooHigh(): void
    {
        $this->expectException(InvalidLocationException::class);
        $this->expectExceptionMessage('Latitude must be between -90 and 90');
        Coordinate::fromLatLon(91.0, 0.0);
    }

    public function testInvalidLatitudeTooLow(): void
    {
        $this->expectException(InvalidLocationException::class);
        $this->expectExceptionMessage('Latitude must be between -90 and 90');
        Coordinate::fromLatLon(-91.0, 0.0);
    }

    public function testInvalidLongitudeTooHigh(): void
    {
        $this->expectException(InvalidLocationException::class);
        $this->expectExceptionMessage('Longitude must be between -180 and 180');
        Coordinate::fromLatLon(0.0, 181.0);
    }

    public function testInvalidLongitudeTooLow(): void
    {
        $this->expectException(InvalidLocationException::class);
        $this->expectExceptionMessage('Longitude must be between -180 and 180');
        Coordinate::fromLatLon(0.0, -181.0);
    }
}
