<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Domain;

use DateTimeImmutable;
use Horde\Service\Weather\ValueObject\AirQualityCategory;
use Horde\Service\Weather\ValueObject\Location;

/**
 * Air quality measurement at a location.
 *
 * Concentrations are in µg/m³ (micrograms per cubic meter). The unit
 * every modern air-quality API uses for particulates and gases at
 * ground level. AQI values are locale-specific integer indices; consumers
 * pick the one relevant to their region.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final readonly class AirQuality
{
    /**
     * @param Location $location Where the measurement applies.
     * @param DateTimeImmutable $observationTime When the sample was taken.
     * @param ?float $pm25 PM2.5 concentration (µg/m³).
     * @param ?float $pm10 PM10 concentration (µg/m³).
     * @param ?float $ozone Ozone O3 concentration (µg/m³).
     * @param ?float $no2 Nitrogen dioxide NO2 (µg/m³).
     * @param ?float $so2 Sulfur dioxide SO2 (µg/m³).
     * @param ?float $co Carbon monoxide CO (µg/m³).
     * @param ?int $usAqi US EPA AQI (0-500 scale).
     * @param ?int $europeanAqi European EAQI (1-5 scale).
     * @param ?int $ukDaqi UK DAQI (1-10 scale).
     * @param ?AirQualityCategory $category Coarse band. Defaults to
     *        derived-from-usAqi when that is available and $category
     *        is not passed explicitly.
     */
    public function __construct(
        public Location $location,
        public DateTimeImmutable $observationTime,
        public ?float $pm25 = null,
        public ?float $pm10 = null,
        public ?float $ozone = null,
        public ?float $no2 = null,
        public ?float $so2 = null,
        public ?float $co = null,
        public ?int $usAqi = null,
        public ?int $europeanAqi = null,
        public ?int $ukDaqi = null,
        public ?AirQualityCategory $category = null
    ) {}

    public function getLocation(): Location
    {
        return $this->location;
    }

    public function getObservationTime(): DateTimeImmutable
    {
        return $this->observationTime;
    }

    public function getPm25(): ?float
    {
        return $this->pm25;
    }

    public function getPm10(): ?float
    {
        return $this->pm10;
    }

    public function getOzone(): ?float
    {
        return $this->ozone;
    }

    public function getNo2(): ?float
    {
        return $this->no2;
    }

    public function getSo2(): ?float
    {
        return $this->so2;
    }

    public function getCo(): ?float
    {
        return $this->co;
    }

    public function getUsAqi(): ?int
    {
        return $this->usAqi;
    }

    public function getEuropeanAqi(): ?int
    {
        return $this->europeanAqi;
    }

    public function getUkDaqi(): ?int
    {
        return $this->ukDaqi;
    }

    /**
     * Get the coarse category band.
     *
     * Returns the constructor-supplied value when present, otherwise
     * derives from `usAqi` when available. Returns null when neither
     * is set.
     */
    public function getCategory(): ?AirQualityCategory
    {
        if ($this->category !== null) {
            return $this->category;
        }

        if ($this->usAqi !== null) {
            return AirQualityCategory::fromUsAqi($this->usAqi);
        }

        return null;
    }
}
