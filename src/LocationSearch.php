<?php

declare(strict_types=1);

namespace Horde\Service\Weather;

use Horde\Service\Weather\ValueObject\Location;
use Horde\Service\Weather\ValueObject\SearchType;

/**
 * Optional capability: resolving a free-form string into candidate
 * Location objects with real coordinates.
 *
 * Providers that expose server-side geocoding / autocomplete (WeatherAPI,
 * OpenWeatherMap, Open-Meteo) implement this in addition to
 * WeatherProvider. Applications that need generic geocoding
 * against a provider that does not implement this should inject their
 * own geocoder service.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
interface LocationSearch
{
    /**
     * Search for locations matching a free-form query.
     *
     * @param string $query User-supplied search term.
     * @param ?SearchType $type Hint for interpretation. Null / SearchType::ANY
     *                          means the provider should best-effort match
     *                          all supported forms.
     * @return array<Location> Zero or more candidate locations, ordered by
     *                         provider-defined relevance.
     * @throws WeatherException
     */
    public function searchLocations(string $query, ?SearchType $type = null): array;
}
