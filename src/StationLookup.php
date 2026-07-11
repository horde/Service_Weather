<?php

declare(strict_types=1);

namespace Horde\Service\Weather;

use Horde\Service\Weather\Domain\Station;
use Horde\Service\Weather\Exception\StationNotFoundException;
use Horde\Service\Weather\ValueObject\Coordinate;

/**
 * Optional capability: looking up weather / observation stations by id
 * or proximity.
 *
 * Providers that model observations against physical stations (NWS,
 * METAR) implement this in addition to WeatherProvider.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
interface StationLookup
{
    /**
     * Get metadata for a specific station by identifier.
     *
     * For aviation stations the identifier is typically an ICAO code
     * (e.g. "KJFK", "EGLL"). NWS station ids are also ICAO-shaped.
     *
     * @throws StationNotFoundException When the identifier does not
     *         resolve to any known station.
     * @throws WeatherException On generic provider / transport failures.
     */
    public function getStation(string $identifier): Station;

    /**
     * Find stations near a coordinate, ordered by proximity.
     *
     * Returns an empty array when no stations are within the provider's
     * search radius.
     *
     * @param int $limit Maximum stations to return.
     * @return array<Station>
     * @throws WeatherException
     */
    public function findStationsNear(Coordinate $coordinate, int $limit = 5): array;
}
