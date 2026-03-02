<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

/**
 * Wind speed value with unit conversion.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final readonly class Speed
{
    /**
     * @param float $metersPerSecond Speed in m/s
     */
    public function __construct(
        private float $metersPerSecond
    ) {
    }

    /**
     * Create from meters per second.
     */
    public static function fromMetersPerSecond(float $mps): self
    {
        return new self($mps);
    }

    /**
     * Create from kilometers per hour.
     */
    public static function fromKilometersPerHour(float $kmh): self
    {
        return new self($kmh / 3.6);
    }

    /**
     * Create from miles per hour.
     */
    public static function fromMilesPerHour(float $mph): self
    {
        return new self($mph * 0.44704);
    }

    /**
     * Create from knots.
     */
    public static function fromKnots(float $knots): self
    {
        return new self($knots * 0.514444);
    }

    /**
     * Get speed in meters per second.
     */
    public function toMetersPerSecond(): float
    {
        return round($this->metersPerSecond, 1);
    }

    /**
     * Get speed in kilometers per hour.
     */
    public function toKilometersPerHour(): float
    {
        return round($this->metersPerSecond * 3.6, 1);
    }

    /**
     * Get speed in miles per hour.
     */
    public function toMilesPerHour(): float
    {
        return round($this->metersPerSecond / 0.44704, 1);
    }

    /**
     * Get speed in knots.
     */
    public function toKnots(): float
    {
        return round($this->metersPerSecond / 0.514444, 1);
    }

    /**
     * Get formatted speed string with unit.
     */
    public function format(Units $units): string
    {
        return match ($units) {
            Units::METRIC => $this->toKilometersPerHour() . ' km/h',
            Units::IMPERIAL => $this->toMilesPerHour() . ' mph',
            Units::STANDARD => $this->toMetersPerSecond() . ' m/s',
        };
    }
}
