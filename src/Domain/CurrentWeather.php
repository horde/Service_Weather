<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Domain;

use DateTimeImmutable;
use Horde\Service\Weather\ValueObject\Humidity;
use Horde\Service\Weather\ValueObject\Location;
use Horde\Service\Weather\ValueObject\Pressure;
use Horde\Service\Weather\ValueObject\Temperature;
use Horde\Service\Weather\ValueObject\WeatherCondition;

/**
 * Current weather conditions.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final readonly class CurrentWeather
{
    public function __construct(
        public Location $location,
        public Temperature $temperature,
        public WeatherCondition $condition,
        public DateTimeImmutable $observationTime,
        public ?Temperature $feelsLike = null,
        public ?Humidity $humidity = null,
        public ?Pressure $pressure = null,
        public ?Wind $wind = null,
        public ?float $visibility = null,
        public ?int $cloudCover = null,
        public ?float $uvIndex = null,
        public ?string $providerData = null
    ) {
    }

    /**
     * Get location.
     */
    public function getLocation(): Location
    {
        return $this->location;
    }

    /**
     * Get temperature.
     */
    public function getTemperature(): Temperature
    {
        return $this->temperature;
    }

    /**
     * Get "feels like" temperature.
     */
    public function getFeelsLike(): ?Temperature
    {
        return $this->feelsLike;
    }

    /**
     * Get weather condition.
     */
    public function getCondition(): WeatherCondition
    {
        return $this->condition;
    }

    /**
     * Get observation time.
     */
    public function getObservationTime(): DateTimeImmutable
    {
        return $this->observationTime;
    }

    /**
     * Get humidity.
     */
    public function getHumidity(): ?Humidity
    {
        return $this->humidity;
    }

    /**
     * Get atmospheric pressure.
     */
    public function getPressure(): ?Pressure
    {
        return $this->pressure;
    }

    /**
     * Get wind data.
     */
    public function getWind(): ?Wind
    {
        return $this->wind;
    }

    /**
     * Get visibility in kilometers.
     */
    public function getVisibility(): ?float
    {
        return $this->visibility;
    }

    /**
     * Get cloud cover percentage (0-100).
     */
    public function getCloudCover(): ?int
    {
        return $this->cloudCover;
    }

    /**
     * Get UV index.
     */
    public function getUvIndex(): ?float
    {
        return $this->uvIndex;
    }

    /**
     * Get raw provider data (for debugging).
     */
    public function getProviderData(): ?string
    {
        return $this->providerData;
    }
}
