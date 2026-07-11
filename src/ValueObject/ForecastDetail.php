<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

/**
 * Granularity of a Forecast object.
 *
 * DAILY: one period per day (base weather blocks, calendar display).
 * DETAILED: multiple periods per day (hourly / 3-hourly, aviation TAF
 * change groups, "detailed forecast" views).
 *
 * Providers declare which shape they returned so consumers can render
 * appropriately.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
enum ForecastDetail: string
{
    case DAILY = 'daily';
    case DETAILED = 'detailed';
}
