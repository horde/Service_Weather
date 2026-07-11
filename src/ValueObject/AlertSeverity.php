<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

/**
 * Weather alert severity classifications.
 *
 * Levels mirror the CAP (Common Alerting Protocol) severity axis used
 * by the US National Weather Service and adopted by WeatherAPI and
 * OpenWeatherMap One Call for their alert payloads.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
enum AlertSeverity: string
{
    case MINOR = 'minor';        // Minimal to no known threat to life or property.
    case MODERATE = 'moderate';  // Possible threat to life or property.
    case SEVERE = 'severe';      // Significant threat to life or property.
    case EXTREME = 'extreme';    // Extraordinary threat to life or property.
    case UNKNOWN = 'unknown';    // Severity unknown or not provided by source.

    /**
     * Parse a CAP severity string (case-insensitive) into an enum case.
     *
     * Falls back to UNKNOWN for unrecognized values so provider mapping
     * code stays exception-free on non-standard payloads.
     */
    public static function fromString(string $value): self
    {
        return match (strtolower(trim($value))) {
            'minor' => self::MINOR,
            'moderate' => self::MODERATE,
            'severe' => self::SEVERE,
            'extreme' => self::EXTREME,
            default => self::UNKNOWN,
        };
    }
}
