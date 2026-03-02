<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

/**
 * Temperature value with unit conversion.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final readonly class Temperature
{
    /**
     * @param float $celsius Temperature in Celsius
     */
    public function __construct(
        private float $celsius
    ) {
    }

    /**
     * Create from Celsius.
     */
    public static function fromCelsius(float $celsius): self
    {
        return new self($celsius);
    }

    /**
     * Create from Fahrenheit.
     */
    public static function fromFahrenheit(float $fahrenheit): self
    {
        return new self(($fahrenheit - 32) * 5 / 9);
    }

    /**
     * Create from Kelvin.
     */
    public static function fromKelvin(float $kelvin): self
    {
        return new self($kelvin - 273.15);
    }

    /**
     * Get temperature in Celsius.
     */
    public function toCelsius(): float
    {
        return round($this->celsius, 1);
    }

    /**
     * Get temperature in Fahrenheit.
     */
    public function toFahrenheit(): float
    {
        return round($this->celsius * 9 / 5 + 32, 1);
    }

    /**
     * Get temperature in Kelvin.
     */
    public function toKelvin(): float
    {
        return round($this->celsius + 273.15, 1);
    }

    /**
     * Get formatted temperature string with unit.
     */
    public function format(Units $units): string
    {
        return match ($units) {
            Units::METRIC => $this->toCelsius() . '°C',
            Units::IMPERIAL => $this->toFahrenheit() . '°F',
            Units::STANDARD => $this->toKelvin() . 'K',
        };
    }
}
