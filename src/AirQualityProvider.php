<?php

declare(strict_types=1);

namespace Horde\Service\Weather;

use Horde\Service\Weather\Domain\AirQuality;
use Horde\Service\Weather\ValueObject\Location;

/**
 * Optional capability: air-quality observations at a location.
 *
 * Air quality is measured separately from surface weather. Providers that
 * expose it (OpenMeteo, OpenWeatherMap, WeatherAPI) implement this
 * alongside WeatherProvider. Consumers `instanceof`-check:
 *
 *     if ($provider instanceof AirQualityProvider) {
 *         $aq = $provider->getAirQuality($location);
 *     }
 *
 * The AirQuality domain object carries PM2.5, PM10, ozone, NO2, SO2, CO,
 * plus locale-specific numeric AQI (US, European, UK). Not every provider
 * fills every field; nulls are expected on unsupported dimensions.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
interface AirQualityProvider
{
    /**
     * Get current air-quality observation.
     *
     * @throws WeatherException
     */
    public function getAirQuality(Location $location): AirQuality;
}
