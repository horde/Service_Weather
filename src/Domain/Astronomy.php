<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Domain;

use DateTimeImmutable;
use Horde\Service\Weather\ValueObject\Location;
use Horde\Service\Weather\ValueObject\MoonPhase;

/**
 * Astronomical data for a location on a given date.
 *
 * All times are localized to the queried location's timezone (per the
 * upstream provider's normalization). Callers that need UTC should
 * consult `DateTimeImmutable::setTimezone()`.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final readonly class Astronomy
{
    /**
     * @param Location $location Where the observation applies.
     * @param DateTimeImmutable $date The date these values are for.
     * @param ?DateTimeImmutable $sunrise Local sunrise. Null in polar-night regions.
     * @param ?DateTimeImmutable $sunset Local sunset. Null in polar-day regions.
     * @param ?DateTimeImmutable $moonrise Local moonrise. Null when the moon does not rise.
     * @param ?DateTimeImmutable $moonset Local moonset. Null when the moon does not set.
     * @param MoonPhase $moonPhase Discrete phase band. Defaults to UNKNOWN.
     * @param ?int $moonIllumination Percentage of moon disc illuminated (0-100).
     */
    public function __construct(
        public Location $location,
        public DateTimeImmutable $date,
        public ?DateTimeImmutable $sunrise = null,
        public ?DateTimeImmutable $sunset = null,
        public ?DateTimeImmutable $moonrise = null,
        public ?DateTimeImmutable $moonset = null,
        public MoonPhase $moonPhase = MoonPhase::UNKNOWN,
        public ?int $moonIllumination = null
    ) {}

    public function getLocation(): Location
    {
        return $this->location;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function getSunrise(): ?DateTimeImmutable
    {
        return $this->sunrise;
    }

    public function getSunset(): ?DateTimeImmutable
    {
        return $this->sunset;
    }

    public function getMoonrise(): ?DateTimeImmutable
    {
        return $this->moonrise;
    }

    public function getMoonset(): ?DateTimeImmutable
    {
        return $this->moonset;
    }

    public function getMoonPhase(): MoonPhase
    {
        return $this->moonPhase;
    }

    public function getMoonIllumination(): ?int
    {
        return $this->moonIllumination;
    }

    /**
     * Duration of daylight in seconds, when both sunrise and sunset are known.
     */
    public function getDaylightSeconds(): ?int
    {
        if ($this->sunrise === null || $this->sunset === null) {
            return null;
        }

        return $this->sunset->getTimestamp() - $this->sunrise->getTimestamp();
    }
}
