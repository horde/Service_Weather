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
}
