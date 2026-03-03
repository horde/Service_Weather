<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

use Horde\Service\Weather\ValueObject\Pressure;
use Horde\Service\Weather\ValueObject\Units;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pressure::class)]
class PressureTest extends TestCase
{
    public function testFromMillibars(): void
    {
        $pressure = Pressure::fromMillibars(1013.25);
        $this->assertSame(1013.3, $pressure->getMillibars());
    }

    public function testFromHectopascals(): void
    {
        $pressure = Pressure::fromHectopascals(1013.25);
        $this->assertSame(1013.3, $pressure->getHectopascals());
    }

    public function testFromInchesOfMercury(): void
    {
        $pressure = Pressure::fromInchesOfMercury(29.92);
        $this->assertEqualsWithDelta(1013.3, $pressure->getMillibars(), 0.5);
    }

    public function testToMillibars(): void
    {
        $pressure = Pressure::fromMillibars(1000.0);
        $this->assertSame(1000.0, $pressure->getMillibars());
    }

    public function testToHectopascals(): void
    {
        $pressure = Pressure::fromMillibars(1013.25);
        $this->assertSame(1013.3, $pressure->getHectopascals());
    }

    public function testToInchesOfMercury(): void
    {
        $pressure = Pressure::fromMillibars(1013.25);
        $this->assertEqualsWithDelta(29.92, $pressure->getInchesOfMercury(), 0.01);
    }

    public function testFormatMetric(): void
    {
        $pressure = Pressure::fromMillibars(1013.25);
        $this->assertSame('1013.3 hPa', $pressure->format(Units::METRIC));
    }

    public function testFormatStandard(): void
    {
        $pressure = Pressure::fromMillibars(1013.25);
        $this->assertSame('1013.3 hPa', $pressure->format(Units::STANDARD));
    }

    public function testFormatImperial(): void
    {
        $pressure = Pressure::fromMillibars(1013.25);
        $formatted = $pressure->format(Units::IMPERIAL);
        $this->assertStringContainsString('29.9', $formatted);
        $this->assertStringContainsString('inHg', $formatted);
    }

    public function testStandardAtmosphericPressure(): void
    {
        $pressure = Pressure::fromMillibars(1013.25);
        $this->assertSame(1013.3, $pressure->getMillibars());
        $this->assertEqualsWithDelta(29.92, $pressure->getInchesOfMercury(), 0.01);
    }
}
