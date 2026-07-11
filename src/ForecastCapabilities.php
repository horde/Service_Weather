<?php

declare(strict_types=1);

namespace Horde\Service\Weather;

/**
 * Optional capability: describing a provider's forecast horizon.
 *
 * Providers that publish which day-count values are valid `$days`
 * arguments for `getForecast()` implement this interface. Consumers
 * with a UI (e.g. horde/base's weather block populating a "days"
 * dropdown) `instanceof`-check before consulting.
 *
 *     if ($provider instanceof ForecastCapabilities) {
 *         $lengths = $provider->getSupportedForecastLengths();
 *     }
 *
 * A provider that does not implement this is treated as unspecified.
 * Callers either pass a conservative default (5) or fall back to a
 * documented UI default. Requesting a length outside the supported
 * set is not enforced by this interface; providers may accept
 * out-of-range values and return whatever their upstream returns.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
interface ForecastCapabilities
{
    /**
     * Supported forecast lengths in days, ascending.
     *
     * Every value in the returned array is a valid `$days` argument to
     * `getForecast()`. Empty array means the provider offers no
     * multi-day forecast (only current conditions).
     *
     * @return array<int>
     */
    public function getSupportedForecastLengths(): array;
}
