<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Horde\Service\Weather\ValueObject\Coordinate;

/**
 * Weather station / observation point metadata.
 *
 * Represents a physical or virtual point from which observations are
 * reported. Used by StationLookup implementations (NWS, METAR)
 * to describe stations independently of the observations they produce.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final readonly class Station
{
    /**
     * @param string $identifier Provider-specific station id. For aviation
     *                           stations this is typically an ICAO code
     *                           (e.g. "KJFK", "EGLL").
     * @param string $name Human-readable station name.
     * @param Coordinate $coordinate Station location.
     * @param ?string $timezone IANA timezone identifier if known.
     * @param ?float $elevation Station elevation in meters if known.
     * @param ?DateTimeImmutable $sunrise Local sunrise time if provided by
     *                                    the source. Not universally available.
     * @param ?DateTimeImmutable $sunset Local sunset time if provided by
     *                                   the source. Not universally available.
     */
    public function __construct(
        public string $identifier,
        public string $name,
        public Coordinate $coordinate,
        public ?string $timezone = null,
        public ?float $elevation = null,
        public ?DateTimeImmutable $sunrise = null,
        public ?DateTimeImmutable $sunset = null
    ) {}

    /**
     * Get the provider-specific station identifier.
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Get the human-readable station name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the station's geographic coordinate.
     */
    public function getCoordinate(): Coordinate
    {
        return $this->coordinate;
    }

    /**
     * Get the station's IANA timezone identifier, if known.
     */
    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    /**
     * Get the station's elevation in meters, if known.
     */
    public function getElevation(): ?float
    {
        return $this->elevation;
    }

    /**
     * Get local sunrise time at the station, if provided by the source.
     */
    public function getSunrise(): ?DateTimeImmutable
    {
        return $this->sunrise;
    }

    /**
     * Get local sunset time at the station, if provided by the source.
     */
    public function getSunset(): ?DateTimeImmutable
    {
        return $this->sunset;
    }

    /**
     * Get the station's UTC offset in seconds at the given instant.
     *
     * Derived from the IANA timezone identifier. Returns null when the
     * source did not provide a timezone. The `$at` argument matters for
     * timezones that observe DST. Pass the observation time to get the
     * offset in effect at that moment, or omit for the offset in effect
     * right now.
     */
    public function getUtcOffset(?DateTimeInterface $at = null): ?int
    {
        if ($this->timezone === null) {
            return null;
        }

        $at ??= new DateTimeImmutable();

        return (new DateTimeZone($this->timezone))->getOffset($at);
    }
}
