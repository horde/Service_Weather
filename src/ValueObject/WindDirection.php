<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

/**
 * Cardinal wind directions.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
enum WindDirection: string
{
    case N = 'N';    // North (337.5° - 22.5°)
    case NE = 'NE';  // Northeast (22.5° - 67.5°)
    case E = 'E';    // East (67.5° - 112.5°)
    case SE = 'SE';  // Southeast (112.5° - 157.5°)
    case S = 'S';    // South (157.5° - 202.5°)
    case SW = 'SW';  // Southwest (202.5° - 247.5°)
    case W = 'W';    // West (247.5° - 292.5°)
    case NW = 'NW';  // Northwest (292.5° - 337.5°)
    case VARIABLE = 'VAR'; // Variable direction

    /**
     * Create from degrees (0-360).
     */
    public static function fromDegrees(float $degrees): self
    {
        $degrees = fmod($degrees, 360);
        if ($degrees < 0) {
            $degrees += 360;
        }

        return match (true) {
            $degrees >= 337.5 || $degrees < 22.5 => self::N,
            $degrees >= 22.5 && $degrees < 67.5 => self::NE,
            $degrees >= 67.5 && $degrees < 112.5 => self::E,
            $degrees >= 112.5 && $degrees < 157.5 => self::SE,
            $degrees >= 157.5 && $degrees < 202.5 => self::S,
            $degrees >= 202.5 && $degrees < 247.5 => self::SW,
            $degrees >= 247.5 && $degrees < 292.5 => self::W,
            $degrees >= 292.5 && $degrees < 337.5 => self::NW,
            default => self::VARIABLE,
        };
    }

    /**
     * Get approximate degrees for this direction.
     */
    public function toDegrees(): float
    {
        return match ($this) {
            self::N => 0.0,
            self::NE => 45.0,
            self::E => 90.0,
            self::SE => 135.0,
            self::S => 180.0,
            self::SW => 225.0,
            self::W => 270.0,
            self::NW => 315.0,
            self::VARIABLE => 0.0,
        };
    }
}
