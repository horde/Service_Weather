<?php

declare(strict_types=1);

namespace Horde\Service\Weather;

use DateTimeImmutable;
use Horde\Service\Weather\Domain\Astronomy;
use Horde\Service\Weather\ValueObject\Location;

/**
 * Optional capability: astronomical data (sun/moon rise, set, phase).
 *
 * Providers that surface astronomy (WeatherAPI dedicated endpoint,
 * OpenWeatherMap One Call, Open-Meteo sun-only) implement this alongside
 * WeatherProvider.
 *
 *     if ($provider instanceof AstronomyProvider) {
 *         $astronomy = $provider->getAstronomy($location, $tomorrow);
 *     }
 *
 * Providers that only offer sun times return an Astronomy with moonrise,
 * moonset, moon phase and moon illumination all null / UNKNOWN. That is
 * a normal outcome, not an error.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
interface AstronomyProvider
{
    /**
     * Get astronomical data for a location on a given date.
     *
     * @param ?DateTimeImmutable $date Target date. Defaults to "today"
     *        in the caller's timezone.
     * @throws WeatherException
     */
    public function getAstronomy(Location $location, ?DateTimeImmutable $date = null): Astronomy;
}
