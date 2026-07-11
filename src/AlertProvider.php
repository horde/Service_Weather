<?php

declare(strict_types=1);

namespace Horde\Service\Weather;

use Horde\Service\Weather\Domain\WeatherAlert;
use Horde\Service\Weather\Exception\AlertsUnavailableException;
use Horde\Service\Weather\ValueObject\Location;

/**
 * Optional capability: exposing weather alerts / warnings / advisories.
 *
 * Providers that surface alerts (NWS, WeatherAPI, OpenWeatherMap One Call)
 * implement this in addition to WeatherProvider. Callers should
 * `instanceof`-check before calling getAlerts():
 *
 *     if ($provider instanceof AlertProvider) {
 *         $alerts = $provider->getAlerts($location);
 *     }
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
interface AlertProvider
{
    /**
     * Get active weather alerts affecting the given location.
     *
     * Returns an empty array when no alerts are active.
     *
     * @return array<WeatherAlert>
     * @throws AlertsUnavailableException When the provider cannot serve
     *         alerts for this location (e.g. NWS asked about a non-US point).
     * @throws WeatherException On generic provider / transport failures.
     */
    public function getAlerts(Location $location): array;
}
