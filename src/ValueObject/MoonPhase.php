<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

/**
 * Discrete moon phase classification.
 *
 * The eight primary phase names used across weather APIs. Providers that
 * return numeric phase values (0.0 = new, 0.25 = first quarter, 0.5 = full,
 * 0.75 = last quarter) map into these bands via `fromFraction()`.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
enum MoonPhase: string
{
    case NEW = 'new';
    case WAXING_CRESCENT = 'waxing_crescent';
    case FIRST_QUARTER = 'first_quarter';
    case WAXING_GIBBOUS = 'waxing_gibbous';
    case FULL = 'full';
    case WANING_GIBBOUS = 'waning_gibbous';
    case LAST_QUARTER = 'last_quarter';
    case WANING_CRESCENT = 'waning_crescent';
    case UNKNOWN = 'unknown';

    /**
     * Convert a numeric lunar phase (0.0-1.0) to a discrete band.
     *
     * 0.0 = new, 0.25 = first quarter, 0.5 = full, 0.75 = last quarter.
     * Bands are centered on those cardinal points, ±1/16.
     */
    public static function fromFraction(float $fraction): self
    {
        $fraction = fmod($fraction, 1.0);
        if ($fraction < 0) {
            $fraction += 1.0;
        }

        return match (true) {
            $fraction < 0.0625 || $fraction >= 0.9375 => self::NEW,
            $fraction < 0.1875 => self::WAXING_CRESCENT,
            $fraction < 0.3125 => self::FIRST_QUARTER,
            $fraction < 0.4375 => self::WAXING_GIBBOUS,
            $fraction < 0.5625 => self::FULL,
            $fraction < 0.6875 => self::WANING_GIBBOUS,
            $fraction < 0.8125 => self::LAST_QUARTER,
            default => self::WANING_CRESCENT,
        };
    }
}
