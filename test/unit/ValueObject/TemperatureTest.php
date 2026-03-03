<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

use Horde\Service\Weather\ValueObject\Temperature;
use Horde\Service\Weather\ValueObject\Units;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Temperature::class)]
class TemperatureTest extends TestCase
{
    public function testFromCelsius(): void
    {
        $temp = Temperature::fromCelsius(20.0);
        $this->assertSame(20.0, $temp->toCelsius());
    }

    public function testFromFahrenheit(): void
    {
        $temp = Temperature::fromFahrenheit(68.0);
        $this->assertEqualsWithDelta(20.0, $temp->toCelsius(), 0.1);
    }

    public function testFromKelvin(): void
    {
        $temp = Temperature::fromKelvin(293.15);
        $this->assertEqualsWithDelta(20.0, $temp->toCelsius(), 0.1);
    }

    public function testToCelsius(): void
    {
        $temp = Temperature::fromCelsius(25.5);
        $this->assertSame(25.5, $temp->toCelsius());
    }

    public function testToFahrenheit(): void
    {
        $temp = Temperature::fromCelsius(20.0);
        $this->assertEqualsWithDelta(68.0, $temp->toFahrenheit(), 0.1);
    }

    public function testToKelvin(): void
    {
        $temp = Temperature::fromCelsius(20.0);
        $this->assertEqualsWithDelta(293.2, $temp->toKelvin(), 0.1);
    }

    public function testFormatCelsius(): void
    {
        $temp = Temperature::fromCelsius(20.5);
        $this->assertSame('20.5°C', $temp->format(Units::METRIC));
        $this->assertSame('293.7K', $temp->format(Units::STANDARD));
    }

    public function testFormatFahrenheit(): void
    {
        $temp = Temperature::fromCelsius(20.0);
        $formatted = $temp->format(Units::IMPERIAL);
        $this->assertStringContainsString('68', $formatted);
        $this->assertStringContainsString('°F', $formatted);
    }

    public function testFreezingPoint(): void
    {
        $temp = Temperature::fromCelsius(0.0);
        $this->assertSame(0.0, $temp->toCelsius());
        $this->assertEqualsWithDelta(32.0, $temp->toFahrenheit(), 0.1);
        $this->assertEqualsWithDelta(273.2, $temp->toKelvin(), 0.1);
    }

    public function testBoilingPoint(): void
    {
        $temp = Temperature::fromCelsius(100.0);
        $this->assertSame(100.0, $temp->toCelsius());
        $this->assertEqualsWithDelta(212.0, $temp->toFahrenheit(), 0.1);
        $this->assertEqualsWithDelta(373.2, $temp->toKelvin(), 0.1);
    }

    public function testNegativeTemperature(): void
    {
        $temp = Temperature::fromCelsius(-40.0);
        $this->assertSame(-40.0, $temp->toCelsius());
        $this->assertEqualsWithDelta(-40.0, $temp->toFahrenheit(), 0.1);
    }

    public function testAbsoluteZero(): void
    {
        $temp = Temperature::fromKelvin(0.0);
        $this->assertEqualsWithDelta(-273.2, $temp->toCelsius(), 0.1);
    }
}
