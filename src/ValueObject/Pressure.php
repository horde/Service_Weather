<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

/**
 * Atmospheric pressure with unit conversion.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final readonly class Pressure
{
    /**
     * @param float $millibars Pressure in millibars (hPa)
     */
    public function __construct(
        private float $millibars
    ) {}

    /**
     * Create from millibars (same as hectopascals).
     */
    public static function fromMillibars(float $mb): self
    {
        return new self($mb);
    }

    /**
     * Create from hectopascals (same as millibars).
     */
    public static function fromHectopascals(float $hpa): self
    {
        return new self($hpa);
    }

    /**
     * Create from inches of mercury.
     */
    public static function fromInchesOfMercury(float $inHg): self
    {
        return new self($inHg * 33.8639);
    }

    /**
     * Get pressure in millibars.
     */
    public function getMillibars(): float
    {
        return round($this->millibars, 1);
    }

    /**
     * Get pressure in hectopascals (same as millibars).
     */
    public function getHectopascals(): float
    {
        return round($this->millibars, 1);
    }

    /**
     * Get pressure in inches of mercury.
     */
    public function getInchesOfMercury(): float
    {
        return round($this->millibars / 33.8639, 2);
    }

    /**
     * Get formatted pressure string.
     */
    public function format(Units $units): string
    {
        return match ($units) {
            Units::METRIC, Units::STANDARD => $this->getMillibars() . ' hPa',
            Units::IMPERIAL => $this->getInchesOfMercury() . ' inHg',
        };
    }
}
