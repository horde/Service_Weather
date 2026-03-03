<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

use Horde\Service\Weather\ValueObject\Speed;
use Horde\Service\Weather\ValueObject\Units;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Speed::class)]
class SpeedTest extends TestCase
{
    public function testFromMetersPerSecond(): void
    {
        $speed = Speed::fromMetersPerSecond(10.0);
        $this->assertSame(10.0, $speed->toMetersPerSecond());
    }

    public function testFromKilometersPerHour(): void
    {
        $speed = Speed::fromKilometersPerHour(36.0);
        $this->assertEqualsWithDelta(10.0, $speed->toMetersPerSecond(), 0.01);
    }

    public function testFromMilesPerHour(): void
    {
        $speed = Speed::fromMilesPerHour(22.369);
        $this->assertEqualsWithDelta(10.0, $speed->toMetersPerSecond(), 0.01);
    }

    public function testFromKnots(): void
    {
        $speed = Speed::fromKnots(19.438);
        $this->assertEqualsWithDelta(10.0, $speed->toMetersPerSecond(), 0.01);
    }

    public function testToMetersPerSecond(): void
    {
        $speed = Speed::fromMetersPerSecond(5.5);
        $this->assertSame(5.5, $speed->toMetersPerSecond());
    }

    public function testToKilometersPerHour(): void
    {
        $speed = Speed::fromMetersPerSecond(10.0);
        $this->assertEqualsWithDelta(36.0, $speed->toKilometersPerHour(), 0.01);
    }

    public function testToMilesPerHour(): void
    {
        $speed = Speed::fromMetersPerSecond(10.0);
        $this->assertEqualsWithDelta(22.4, $speed->toMilesPerHour(), 0.1);
    }

    public function testToKnots(): void
    {
        $speed = Speed::fromMetersPerSecond(10.0);
        $this->assertEqualsWithDelta(19.4, $speed->toKnots(), 0.1);
    }

    public function testFormatMetric(): void
    {
        $speed = Speed::fromMetersPerSecond(10.0);
        $formatted = $speed->format(Units::METRIC);
        $this->assertStringContainsString('36', $formatted);
        $this->assertStringContainsString('km/h', $formatted);
    }

    public function testFormatImperial(): void
    {
        $speed = Speed::fromMetersPerSecond(10.0);
        $formatted = $speed->format(Units::IMPERIAL);
        $this->assertStringContainsString('22', $formatted);
        $this->assertStringContainsString('mph', $formatted);
    }

    public function testFormatStandard(): void
    {
        $speed = Speed::fromMetersPerSecond(10.0);
        $this->assertSame('10 m/s', $speed->format(Units::STANDARD));
    }

    public function testZeroSpeed(): void
    {
        $speed = Speed::fromMetersPerSecond(0.0);
        $this->assertSame(0.0, $speed->toMetersPerSecond());
        $this->assertSame(0.0, $speed->toKilometersPerHour());
        $this->assertSame(0.0, $speed->toMilesPerHour());
    }
}
