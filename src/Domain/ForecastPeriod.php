<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Domain;

use DateTimeImmutable;
use Horde\Service\Weather\ValueObject\Humidity;
use Horde\Service\Weather\ValueObject\Pressure;
use Horde\Service\Weather\ValueObject\Temperature;
use Horde\Service\Weather\ValueObject\WeatherCondition;

/**
 * Single forecast period (e.g., a specific day or time slot).
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final readonly class ForecastPeriod
{
    public function __construct(
        public DateTimeImmutable $date,
        public Temperature $temperature,
        public WeatherCondition $condition,
        public ?Temperature $highTemperature = null,
        public ?Temperature $lowTemperature = null,
        public ?Humidity $humidity = null,
        public ?Pressure $pressure = null,
        public ?Wind $wind = null,
        public ?float $precipitationProbability = null,
        public ?float $precipitationAmount = null,
        public ?int $cloudCover = null
    ) {
    }

    /**
     * Get date/time for this period.
     */
    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * Get temperature (average or main temperature).
     */
    public function getTemperature(): Temperature
    {
        return $this->temperature;
    }

    /**
     * Get high temperature for the period.
     */
    public function getHighTemperature(): ?Temperature
    {
        return $this->highTemperature;
    }

    /**
     * Get low temperature for the period.
     */
    public function getLowTemperature(): ?Temperature
    {
        return $this->lowTemperature;
    }

    /**
     * Get weather condition.
     */
    public function getCondition(): WeatherCondition
    {
        return $this->condition;
    }

    /**
     * Get precipitation probability (0.0-1.0).
     */
    public function getPrecipitationProbability(): ?float
    {
        return $this->precipitationProbability;
    }

    /**
     * Get precipitation amount in mm.
     */
    public function getPrecipitationAmount(): ?float
    {
        return $this->precipitationAmount;
    }

    /**
     * Get humidity.
     */
    public function getHumidity(): ?Humidity
    {
        return $this->humidity;
    }

    /**
     * Get pressure.
     */
    public function getPressure(): ?Pressure
    {
        return $this->pressure;
    }

    /**
     * Get wind.
     */
    public function getWind(): ?Wind
    {
        return $this->wind;
    }

    /**
     * Get cloud cover percentage (0-100).
     */
    public function getCloudCover(): ?int
    {
        return $this->cloudCover;
    }
}
