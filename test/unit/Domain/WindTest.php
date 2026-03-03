<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Domain;

use Horde\Service\Weather\Domain\Wind;
use Horde\Service\Weather\ValueObject\Speed;
use Horde\Service\Weather\ValueObject\WindDirection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Wind::class)]
class WindTest extends TestCase
{
    public function testConstructWithSpeedAndDirection(): void
    {
        $speed = Speed::fromMetersPerSecond(10.0);
        $wind = new Wind($speed, WindDirection::N);

        $this->assertSame($speed, $wind->speed);
        $this->assertSame(WindDirection::N, $wind->direction);
        $this->assertNull($wind->gusts);
        $this->assertNull($wind->degrees);
    }

    public function testConstructWithAllParameters(): void
    {
        $speed = Speed::fromMetersPerSecond(15.0);
        $gusts = Speed::fromMetersPerSecond(20.0);
        $wind = new Wind($speed, WindDirection::NE, $gusts, 45.0);

        $this->assertSame($speed, $wind->speed);
        $this->assertSame(WindDirection::NE, $wind->direction);
        $this->assertSame($gusts, $wind->gusts);
        $this->assertSame(45.0, $wind->degrees);
    }

    public function testHasGustsTrue(): void
    {
        $speed = Speed::fromMetersPerSecond(10.0);
        $gusts = Speed::fromMetersPerSecond(15.0);
        $wind = new Wind($speed, WindDirection::S, $gusts);

        $this->assertTrue($wind->hasGusts());
    }

    public function testHasGustsFalse(): void
    {
        $speed = Speed::fromMetersPerSecond(10.0);
        $wind = new Wind($speed, WindDirection::W);

        $this->assertFalse($wind->hasGusts());
    }

    public function testGetDegrees(): void
    {
        $speed = Speed::fromMetersPerSecond(12.0);
        $wind = new Wind($speed, WindDirection::E, degrees: 90.0);

        $this->assertSame(90.0, $wind->getDegrees());
    }

    public function testGetDegreesNull(): void
    {
        $speed = Speed::fromMetersPerSecond(8.0);
        $wind = new Wind($speed, WindDirection::SW);

        // getDegrees() returns direction->toDegrees() if degrees is null
        $this->assertSame(225.0, $wind->getDegrees());
    }
}
