<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

/**
 * Three-hour barometric pressure trend.
 *
 * Native to METAR reports (via the `3hpresstend` remark); other providers
 * typically do not surface it. Consumers that render weather dashboards
 * (base's Weather block, aviation displays) show this alongside the
 * absolute pressure reading.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
enum PressureTrend: string
{
    case RISING = 'rising';
    case FALLING = 'falling';
    case STEADY = 'steady';
}
