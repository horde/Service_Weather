<?php

declare(strict_types=1);

namespace Horde\Service\Weather;

use Horde\Service\Weather\Domain\CurrentWeather;
use Horde\Service\Weather\Domain\Forecast;
use Horde\Service\Weather\ValueObject\Location;

/**
 * Weather provider interface.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
interface WeatherProvider
{
    /**
     * Get current weather conditions.
     *
     * @param Location|string $location Location object or coordinate string
     * @throws WeatherException
     */
    public function getCurrentWeather(Location|string $location): CurrentWeather;

    /**
     * Get weather forecast.
     *
     * @param Location|string $location Location object or coordinate string
     * @param int $days Number of forecast days
     * @throws WeatherException
     */
    public function getForecast(Location|string $location, int $days = 5): Forecast;
}
