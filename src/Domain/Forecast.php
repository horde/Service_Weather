<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Domain;

use Horde\Service\Weather\ValueObject\Location;

/**
 * Multi-period weather forecast.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final readonly class Forecast
{
    /**
     * @param Location $location
     * @param array<ForecastPeriod> $periods
     */
    public function __construct(
        public Location $location,
        public array $periods
    ) {
    }

    /**
     * Get location for this forecast.
     */
    public function getLocation(): Location
    {
        return $this->location;
    }

    /**
     * Get all forecast periods.
     *
     * @return array<ForecastPeriod>
     */
    public function getPeriods(): array
    {
        return $this->periods;
    }

    /**
     * Get number of forecast periods.
     */
    public function getPeriodsCount(): int
    {
        return count($this->periods);
    }

    /**
     * Get forecast period by index.
     */
    public function getPeriod(int $index): ?ForecastPeriod
    {
        return $this->periods[$index] ?? null;
    }
}
