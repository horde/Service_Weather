<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

/**
 * Location identifier for weather queries.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final readonly class Location
{
    private function __construct(
        public Coordinate $coordinate,
        public ?string $name = null,
        public ?string $country = null,
        public ?string $identifier = null
    ) {}

    /**
     * Create from coordinates.
     */
    public static function fromCoordinates(float $latitude, float $longitude): self
    {
        return new self(Coordinate::fromLatLon($latitude, $longitude));
    }

    /**
     * Create from coordinate object.
     */
    public static function fromCoordinate(Coordinate $coordinate): self
    {
        return new self($coordinate);
    }

    /**
     * Create from city name and optional country.
     */
    public static function fromCity(string $city, ?string $country = null): self
    {
        // Note: This creates a location with name only.
        // Providers will need to geocode this to coordinates.
        return new self(
            Coordinate::fromLatLon(0, 0), // Placeholder
            $city,
            $country
        );
    }

    /**
     * Create from location identifier (ICAO code, zip code, etc.).
     */
    public static function fromIdentifier(string $identifier): self
    {
        return new self(
            Coordinate::fromLatLon(0, 0), // Placeholder
            null,
            null,
            $identifier
        );
    }

    /**
     * Create from a fully-resolved geocoded result (typically from a
     * LocationSearch provider). Carries both coordinates and human-
     * readable name/country labels.
     */
    public static function fromGeocoded(
        Coordinate $coordinate,
        ?string $name = null,
        ?string $country = null,
        ?string $identifier = null,
    ): self {
        return new self($coordinate, $name, $country, $identifier);
    }

    /**
     * Get coordinate.
     */
    public function getCoordinate(): Coordinate
    {
        return $this->coordinate;
    }

    /**
     * Get display name for location.
     */
    public function getDisplayName(): string
    {
        if ($this->name) {
            return $this->country
                ? "{$this->name}, {$this->country}"
                : $this->name;
        }

        if ($this->identifier) {
            return $this->identifier;
        }

        return $this->coordinate->toString();
    }

    /**
     * Check if location has specific coordinates.
     */
    public function hasCoordinates(): bool
    {
        return !($this->coordinate->latitude === 0.0 && $this->coordinate->longitude === 0.0);
    }
}
