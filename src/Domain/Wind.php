<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Domain;

use Horde\Service\Weather\ValueObject\Speed;
use Horde\Service\Weather\ValueObject\WindDirection;

/**
 * Wind data (speed, direction, gusts).
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final readonly class Wind
{
    public function __construct(
        public Speed $speed,
        public WindDirection $direction,
        public ?Speed $gusts = null,
        public ?float $degrees = null
    ) {}

    /**
     * Get wind speed.
     */
    public function getSpeed(): Speed
    {
        return $this->speed;
    }

    /**
     * Get wind direction.
     */
    public function getDirection(): WindDirection
    {
        return $this->direction;
    }

    /**
     * Get wind direction in degrees (0-360). Falls back to the
     * cardinal direction's canonical angle when a precise heading
     * was not provided at construction, so callers always get a
     * usable numeric value.
     */
    public function getDegrees(): float
    {
        return $this->degrees ?? $this->direction->toDegrees();
    }

    /**
     * Get wind gusts.
     */
    public function getGusts(): ?Speed
    {
        return $this->gusts;
    }

    /**
     * Check if wind has gust data.
     */
    public function hasGusts(): bool
    {
        return $this->gusts !== null;
    }
}
