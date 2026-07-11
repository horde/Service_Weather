<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

/**
 * Measurement unit systems for weather data.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
enum Units: string
{
    case STANDARD = 'standard';  // Kelvin, m/s
    case METRIC = 'metric';      // Celsius, m/s
    case IMPERIAL = 'imperial';  // Fahrenheit, mph

    /**
     * Label suffixes for measurement fields under this unit system.
     *
     * Returned keys: `temp`, `wind`, `pressure`, `visibility`, `precipitation`.
     * Consumers concatenate these onto rendered values (e.g. "23°C", "15 mph").
     *
     * @return array{temp: string, wind: string, pressure: string, visibility: string, precipitation: string}
     */
    public function getLabels(): array
    {
        return match ($this) {
            self::METRIC => [
                'temp' => '°C',
                'wind' => 'km/h',
                'pressure' => 'mb',
                'visibility' => 'km',
                'precipitation' => 'mm',
            ],
            self::IMPERIAL => [
                'temp' => '°F',
                'wind' => 'mph',
                'pressure' => 'inHg',
                'visibility' => 'mi',
                'precipitation' => 'in',
            ],
            self::STANDARD => [
                'temp' => 'K',
                'wind' => 'm/s',
                'pressure' => 'hPa',
                'visibility' => 'km',
                'precipitation' => 'mm',
            ],
        };
    }
}
