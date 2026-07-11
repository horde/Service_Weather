<?php

declare(strict_types=1);

namespace Horde\Service\Weather;

use Horde\Service\Weather\Domain\Forecast;
use Horde\Service\Weather\ValueObject\Location;

/**
 * Optional capability: intra-day forecast granularity.
 *
 * Providers that publish hour-by-hour (or sub-hourly aggregated) forecasts
 * implement this alongside WeatherProvider's daily-granularity getForecast().
 * The return type is the same Forecast object; the granularity is signalled
 * via Forecast::$detail === ForecastDetail::DETAILED.
 *
 *     if ($provider instanceof HourlyForecast) {
 *         $forecast = $provider->getHourlyForecast($location, 24);
 *     }
 *
 * Providers may cap `$hours` internally at their own upstream maximum
 * (typically 48-336 depending on the API). Callers should not assume all
 * requested hours will be returned.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
interface HourlyForecast
{
    /**
     * Get hourly (or sub-hourly aggregated) forecast.
     *
     * The returned Forecast has ForecastDetail::DETAILED. Each
     * ForecastPeriod's `date` is the period start; period length is
     * provider-specific (typically 1 hour; OpenWeatherMap yields 3-hour
     * buckets).
     *
     * @param int $hours Requested horizon in hours. Providers cap silently.
     * @throws WeatherException
     */
    public function getHourlyForecast(Location $location, int $hours = 48): Forecast;
}
