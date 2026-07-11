<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

/**
 * Standardized weather conditions.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
enum WeatherCondition: string
{
    case CLEAR = 'clear';
    case PARTLY_CLOUDY = 'partly_cloudy';
    case CLOUDY = 'cloudy';
    case OVERCAST = 'overcast';
    case FOG = 'fog';
    case DRIZZLE = 'drizzle';
    case RAIN = 'rain';
    case FREEZING_RAIN = 'freezing_rain';
    case SNOW = 'snow';
    case SLEET = 'sleet';
    case THUNDERSTORM = 'thunderstorm';
    case HAIL = 'hail';
    case TORNADO = 'tornado';
    case HURRICANE = 'hurricane';
    case UNKNOWN = 'unknown';

    /**
     * Get a human-readable description.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::CLEAR => 'Clear',
            self::PARTLY_CLOUDY => 'Partly Cloudy',
            self::CLOUDY => 'Cloudy',
            self::OVERCAST => 'Overcast',
            self::FOG => 'Fog',
            self::DRIZZLE => 'Drizzle',
            self::RAIN => 'Rain',
            self::FREEZING_RAIN => 'Freezing Rain',
            self::SNOW => 'Snow',
            self::SLEET => 'Sleet',
            self::THUNDERSTORM => 'Thunderstorm',
            self::HAIL => 'Hail',
            self::TORNADO => 'Tornado',
            self::HURRICANE => 'Hurricane',
            self::UNKNOWN => 'Unknown',
        };
    }

    /**
     * Get a simple icon code for UI display.
     */
    public function getIconCode(): string
    {
        return match ($this) {
            self::CLEAR => '01',
            self::PARTLY_CLOUDY => '02',
            self::CLOUDY => '03',
            self::OVERCAST => '04',
            self::FOG => '50',
            self::DRIZZLE => '09',
            self::RAIN => '10',
            self::FREEZING_RAIN => '13',
            self::SNOW => '13',
            self::SLEET => '13',
            self::THUNDERSTORM => '11',
            self::HAIL => '11',
            self::TORNADO => '11',
            self::HURRICANE => '11',
            self::UNKNOWN => '00',
        };
    }

    /**
     * Get a stable semantic icon slug for UI display.
     *
     * Unlike getIconCode() (which returns OpenWeatherMap-style numeric
     * codes), this returns a kebab-case slug describing the condition.
     * Consumers concatenate it against their theme's icon path, e.g.
     * `weather/32x32/{slug}.png`. New blocks and themes are expected
     * to ship an icon set keyed by these slugs.
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::CLEAR => 'clear',
            self::PARTLY_CLOUDY => 'partly-cloudy',
            self::CLOUDY => 'cloudy',
            self::OVERCAST => 'overcast',
            self::FOG => 'fog',
            self::DRIZZLE => 'drizzle',
            self::RAIN => 'rain',
            self::FREEZING_RAIN => 'freezing-rain',
            self::SNOW => 'snow',
            self::SLEET => 'sleet',
            self::THUNDERSTORM => 'thunderstorm',
            self::HAIL => 'hail',
            self::TORNADO => 'tornado',
            self::HURRICANE => 'hurricane',
            self::UNKNOWN => 'unknown',
        };
    }
}
