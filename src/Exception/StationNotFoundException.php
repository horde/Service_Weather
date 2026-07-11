<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Exception;

use Horde\Service\Weather\WeatherException;

/**
 * Thrown when a StationLookup implementation cannot resolve
 * the requested station identifier.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
class StationNotFoundException extends WeatherException {}
