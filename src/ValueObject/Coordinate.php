<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

use Horde\Service\Weather\Exception\InvalidLocationException;

/**
 * Geographic coordinates (latitude/longitude).
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final readonly class Coordinate
{
    public function __construct(
        public float $latitude,
        public float $longitude
    ) {
        if ($latitude < -90 || $latitude > 90) {
            throw new InvalidLocationException(
                "Latitude must be between -90 and 90, got: $latitude"
            );
        }

        if ($longitude < -180 || $longitude > 180) {
            throw new InvalidLocationException(
                "Longitude must be between -180 and 180, got: $longitude"
            );
        }
    }

    /**
     * Create from lat/lon pair.
     */
    public static function fromLatLon(float $latitude, float $longitude): self
    {
        return new self($latitude, $longitude);
    }

    /**
     * Get formatted coordinate string.
     */
    public function toString(): string
    {
        return sprintf('%.6f,%.6f', $this->latitude, $this->longitude);
    }

    /**
     * Get URL-safe coordinate string.
     */
    public function toUrlString(): string
    {
        return $this->toString();
    }
}
