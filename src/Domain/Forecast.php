<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Domain;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Horde\Service\Weather\ValueObject\ForecastDetail;
use Horde\Service\Weather\ValueObject\Location;
use Traversable;

/**
 * Multi-period weather forecast.
 *
 * Iterable via `foreach ($forecast as $period)`. Countable via `count()`.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 *
 * @implements IteratorAggregate<int, ForecastPeriod>
 */
final readonly class Forecast implements IteratorAggregate, Countable
{
    /**
     * @param Location $location
     * @param array<ForecastPeriod> $periods
     * @param ForecastDetail $detail Granularity of the periods array.
     */
    public function __construct(
        public Location $location,
        public array $periods,
        public ForecastDetail $detail = ForecastDetail::DAILY
    ) {}

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

    /**
     * Get forecast granularity.
     */
    public function getDetail(): ForecastDetail
    {
        return $this->detail;
    }

    /**
     * @return Traversable<int, ForecastPeriod>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->periods);
    }

    /**
     * Countable. Allows `count($forecast)` in addition to `getPeriodsCount()`.
     */
    public function count(): int
    {
        return count($this->periods);
    }
}
