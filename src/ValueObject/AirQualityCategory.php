<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

/**
 * Air quality category classification.
 *
 * Six-level scale aligned with the US EPA AQI banding, which most modern
 * providers converge on for their category output. Locale-specific
 * numeric AQI values are preserved on the AirQuality domain object;
 * this enum is the coarse "what should the UI say" bucket.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
enum AirQualityCategory: string
{
    case GOOD = 'good';                                 //   0- 50 US AQI
    case MODERATE = 'moderate';                         //  51-100
    case UNHEALTHY_FOR_SENSITIVE = 'unhealthy_sensitive'; // 101-150
    case UNHEALTHY = 'unhealthy';                       // 151-200
    case VERY_UNHEALTHY = 'very_unhealthy';             // 201-300
    case HAZARDOUS = 'hazardous';                       // 301+
    case UNKNOWN = 'unknown';

    /**
     * Categorize a US EPA AQI numeric value into a coarse band.
     */
    public static function fromUsAqi(int $aqi): self
    {
        return match (true) {
            $aqi <= 50 => self::GOOD,
            $aqi <= 100 => self::MODERATE,
            $aqi <= 150 => self::UNHEALTHY_FOR_SENSITIVE,
            $aqi <= 200 => self::UNHEALTHY,
            $aqi <= 300 => self::VERY_UNHEALTHY,
            default => self::HAZARDOUS,
        };
    }
}
