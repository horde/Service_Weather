<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

/**
 * Hints for LocationSearch implementations.
 *
 * Providers use this to disambiguate the query string when their search
 * endpoint offers dedicated modes (e.g. WeatherAPI.com's autocomplete
 * accepts city names, ZIP codes or IP addresses). When ANY is passed the
 * provider MUST attempt best-effort interpretation.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
enum SearchType: string
{
    case ANY = 'any';                 // Provider best-effort.
    case CITY = 'city';               // City / place name.
    case COORDINATES = 'coordinates'; // "lat,lon" string.
    case ZIP = 'zip';                 // Postal / ZIP code.
    case ICAO = 'icao';               // ICAO airport code (4 letters).
    case IP_ADDRESS = 'ip';           // IPv4 or IPv6 address (geo-IP).
}
