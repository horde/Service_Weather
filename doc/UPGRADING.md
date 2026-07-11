# Upgrading Service_Weather

This guide covers what changed at `3.0.0` and how existing callers
move from the legacy PSR-0 API to the modern PSR-4 API.

For a catalog of what the modern API does not yet cover (both
regressions from legacy and features new consumers might expect in
2026), see [FEATURE_GAPS.md](FEATURE_GAPS.md).

## TL;DR

- Legacy `Horde_Service_Weather*` classes still work at `3.x`. They
  are `@deprecated` and will be removed at `4.0.0`. There is no
  fixed date for `4.0.0`. Legacy APIs are largely defunct but the classes keep old consumers technically running.
- New code should target `Horde\Service\Weather\Weather` and the capability-interface family under `Horde\Service\Weather\`.
- WWO (WorldWeatherOnline) is retired. Use OpenWeatherMap or WeatherAPI as a replacement in modern code.
- Aviation weather (METAR/TAF) is rebuilt against
  `aviationweather.gov`'s JSON API. Text parsing was dropped.
- Some legacy features are not yet ported (alerts,
  provider-attribution metadata, meteorological helpers) and some
  are deliberately not in the modern API (radar URLs, mutable-units,
  translated condition names). See
  [FEATURE_GAPS.md](FEATURE_GAPS.md) for the full inventory.

## What's in the modern API

Providers implement a thin core interface plus optional capability
interfaces. Callers `instanceof`-check when they want an optional
feature.

Core interface: `Horde\Service\Weather\WeatherProvider`. Two methods:
`getCurrentWeather(Location|string): CurrentWeather` and
`getForecast(Location|string, int $days = 5): Forecast`.

Capability interfaces (a provider implements some subset):

| Interface | Purpose |
|---|---|
| `ForecastCapabilities` | Publish supported forecast lengths |
| `HourlyForecast` | Intra-day granularity |
| `LocationSearch` | Free-form location resolution / geocoding |
| `StationLookup` | Look up observation stations by id or proximity |
| `AirQualityProvider` | PM/gas concentrations and locale AQIs |
| `AstronomyProvider` | Sun/moon rise, set, phase |
| `AlertProvider` | Weather alerts / warnings; interface only at `3.0.0`, no provider implementations yet (see [FEATURE_GAPS.md](FEATURE_GAPS.md)) |

Provider matrix as shipped at `3.0.0`:

| Provider | Core | Forecast | Hourly | Search | Stations | Air quality | Astronomy |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Open-Meteo | ✓ | ✓ | ✓ |  |  | ✓ | ✓ (sun only) |
| OpenWeatherMap | ✓ | ✓ | ✓ |  |  | ✓ |  |
| WeatherAPI.com | ✓ | ✓ | ✓ | ✓ |  | ✓ | ✓ |
| NWS | ✓ | ✓ | ✓ |  | ✓ |  |  |
| METAR (aviationweather.gov) | ✓ | ✓ |  |  | ✓ |  |  |

## HTTP is caller-supplied via PSR-18

The library is transport-agnostic. Providers take a PSR-18 client
(`Psr\Http\Client\ClientInterface`) plus a PSR-17 request factory
(`Psr\Http\Message\RequestFactoryInterface`) on construction. Any
compliant HTTP library plugs in directly: `horde/http` ships
`Horde\Http\Client\Curl` and `Horde\Http\Client\Mock` (both PSR-18)
along with `Horde\Http\RequestFactory` (PSR-17). Guzzle, Symfony
HttpClient, PHP-HTTP's discovery layer and every other modern PHP
HTTP library also implement these interfaces.

Providers make GET requests only (weather APIs are read-only).
Response parsing consumes `(string)$response->getBody()`.

Tests can inject `Horde\Http\Client\Mock` directly, or use the
`Horde\Service\Weather\Test\Support\MockHttpClient` helper (a thin
extension over `Mock` that records inbound requests for header /
count assertions).

The library does not construct a concrete HTTP client on your behalf.

## Caching

`WeatherConfig` accepts an optional PSR-16 cache. When set, all
provider HTTP calls are transparently deduplicated using the URL as
the key. TTL is `WeatherConfig::$cacheLifetime` (default 1800s).

```php
use Horde\Service\Weather\ValueObject\WeatherConfig;

$config = WeatherConfig::default()
    ->withCache($psr16Cache)
    ->withCacheLifetime(900);

$weather = Weather::openMeteo($httpClient, $config);
```

No cache is added when `withCache()` is not called. Existing callers
see no behavioral change.

## Minimum PHP

The modern API requires PHP 8.1+ (readonly properties, enums, named
arguments, new in initializers). `composer.json` declares
`php: ^8.1` and this is not negotiable inside the modern namespace.
The legacy PSR-0 classes still tolerate PHP 7.4 in principle but
are frozen at `3.x`.

## Migrating an existing caller

### Simple current-weather lookup

Before:

```php
$weather = Horde_Service_Weather::factory('Owm', [
    'apikey' => 'your-key',
    'http_client' => new Horde_Http_Client(),
]);
$weather->units = Horde_Service_Weather::UNITS_STANDARD;
$conditions = $weather->getCurrentConditions('boston,ma');
echo $conditions->temp;
echo $conditions->condition;
```

After:

```php
use Horde\Service\Weather\Weather;
use Horde\Service\Weather\ValueObject\Location;
use Horde\Service\Weather\ValueObject\Units;
use Horde\Service\Weather\ValueObject\WeatherConfig;

$weather = Weather::openWeatherMap(
    $httpClient,
    'your-key',
    WeatherConfig::default()->withUnits(Units::IMPERIAL),
);
$current = $weather->getCurrentWeather(
    Location::fromCoordinates(42.3601, -71.0589)
);
echo $current->temperature->toFahrenheit();
echo $current->condition->getDescription();
```

### Notable shape changes

- Providers are constructed via facade factories (`Weather::openMeteo()`,
  `Weather::openWeatherMap()`, `Weather::weatherApi()`,
  `Weather::nationalWeatherService()`, `Weather::metar()`) or by
  directly instantiating the provider class.
- `Location` is a value object. Prefer coordinate construction
  (`Location::fromCoordinates(lat, lon)`) when possible. Legacy
  string forms (`"city,country"`) still work via `Location::fromCity()`
  but leave lat/lon at placeholder values until a geocoder fills them.
  Only WeatherAPI silently accepts a name-only location today; every
  other provider throws `InvalidLocationException` on a `fromCity()`
  location that has no coordinates. Callers who want geocoded
  city-name lookups should route through
  `LocationSearch::searchLocations()` on WeatherAPI, or through a
  dedicated geocoder at the application layer.
- `Units` is now an enum (`Units::METRIC`, `Units::IMPERIAL`,
  `Units::STANDARD`). It's set on `WeatherConfig` at construction,
  not mutated on the provider afterwards. Callers who need to switch
  units re-instantiate the provider with a new
  `$config->withUnits(...)`.
- Domain objects (`CurrentWeather`, `Forecast`, `ForecastPeriod`,
  `Station`, `WeatherAlert`, `AirQuality`, `Astronomy`) are
  `final readonly` classes. Fields are typed (`Temperature`,
  `Speed`, `Pressure`, `Humidity` value objects; `WeatherCondition`
  and other enums).
- `Forecast` is `Countable` and iterable via `foreach`.
- Optional fields (dewpoint, pressureTrend, uvIndex, snowfallAmount,
  station reference etc.) surface as `null` when the provider does
  not populate them. Legacy code returned `false` in some slots; the
  new API uses `null` universally.

### Design changes with no legacy equivalent

Small legacy features that were deliberately not carried into the
modern API. These are not gaps. The modern shape is the intended
long-term design.

- **`Forecast::limitLength($days)`**: no equivalent. Callers slice
  `$forecast->periods` themselves with `array_slice()`.
- **`$forecast->fields` bitmask**: no equivalent. Legacy templates
  read the `FORECAST_FIELD_*` bitmask to omit rows for unpopulated
  fields. Modern `ForecastPeriod` fields are nullable-typed; consumers
  null-check per field. Same information, different pattern.
- **Translated condition names**: no equivalent. Legacy ran condition
  names through `Horde_Service_Weather_Translation::t()` (Horde
  gettext). The modern API deliberately excludes translation.
  `WeatherCondition::getDescription()` returns English literals.
  Translate at the presentation layer with your app's i18n system.
- **`Alerts_Base` iterator**: no equivalent. Legacy returned alerts
  as `Horde_Service_Weather_Alerts_Base implements IteratorAggregate`.
  Modern `AlertProvider::getAlerts()` returns `array<WeatherAlert>`.
- **`Exception\InvalidProperty`**: no equivalent. Legacy threw this
  from `Current_Base::__get()` when a property was not populated for
  a given provider. Modern API surfaces unpopulated fields as `null`
  without an exception.
- **Mutable `$driver->units`**: no equivalent. Legacy allowed
  changing the provider's rendering by writing to a public property
  between calls. Modern `WeatherConfig` is immutable; see the
  `Units` bullet above.
- **`CurrentWeather::$feelsLike` semantics are provider-defined.**
  OpenMeteo populates from ECMWF apparent temperature (includes
  humidity and wind). OpenWeatherMap uses its own model. WeatherAPI
  uses its own. NWS reports wind-chill and heat-index separately.
  Trust `$feelsLike` when populated; fall back to derivation helpers
  only when the provider returns null.
- **NWS and METAR share the ICAO station space**. Both providers
  understand ICAO codes and both return `Station` objects. A caller
  who asks for `KJFK` can hit either. The library does not surface
  "these providers cover the same station space"; use whichever
  provider matches your data quality needs (NWS has richer
  observation fields; METAR has richer text-based aviation data).

The core `WeatherProvider` interface currently still accepts
`Location|string` on `getCurrentWeather()` and `getForecast()`.
String support is preserved for backward compatibility with
in-development callers and tightens to `Location` only at `4.0.0`.

### City-name lookups

WeatherAPI is the only provider that accepts free-form city names as
a query string directly. For every other provider, resolve the string
to coordinates first. Options:

- `WeatherApi` implements `LocationSearch::searchLocations($query)`;
  the first result is usable as a `Location`.
- Use a dedicated geocoder (Nominatim, Google Geocoding, OpenCage or
  another) in your application layer, then pass a
  `Location::fromCoordinates(lat, lon)` to the provider.

### METAR / TAF (aviation weather)

The modern Metar provider hits `aviationweather.gov`'s JSON API
directly. Text parsing was removed; the upstream endpoint returns
pre-parsed fields.

```php
use Horde\Service\Weather\Weather;

$weather = Weather::metar($httpClient);
$provider = $weather->getProvider();  // returns the Metar instance
$current = $provider->getCurrentWeatherByIcao('KJFK');
$forecast = $provider->getForecastByIcao('KJFK');
$station = $provider->getStation('KJFK');
```

`aviationweather.gov` requests a custom `User-Agent`. Set it once:

```php
$config = WeatherConfig::default()->withUserAgent('my-app/1.0 (+https://example.com)');
$weather = Weather::metar($httpClient, $config);
```

Location-by-coordinate also works. The provider's bbox query finds
nearby ICAO stations, filters out non-aviation observation points
(buoys) and picks the closest:

```php
$current = $weather->getCurrentWeather(
    Location::fromCoordinates(40.7128, -74.0060)
);
```

### WWO retirement

`Horde_Service_Weather_Wwo` (WorldWeatherOnline) is not in the modern
API. Callers that used WWO for global commercial weather should move
to `Weather::openWeatherMap()` or `Weather::weatherApi()`. Both cover
WWO's use case with more generous free tiers.

The legacy `Wwo` and `Wwov2` classes remain in `lib/` at `3.x` for
existing callers, `@deprecated`-tagged. They will be removed at
`4.0.0` along with the rest of `lib/`.

## Consumer-side migration notes

### timeobjects `Weather` driver

`timeobjects/src/Driver/Weather.php` currently consumes
`Horde_Weather` from the injector and treats the returned provider
as mutable (`$driver->units = ...`). The modern replacement:

- Construct one provider per request via the facade (or DI a
  `WeatherProvider` factory).
- Pass a `WeatherConfig` with `Units` set at construction rather than
  mutating afterwards.
- Read from `Forecast` via `foreach` and `->periods[]` instead of
  the legacy iterator + `->detail` pair. `Forecast::$detail` still
  exists but is a `ForecastDetail` enum (`DAILY` or `DETAILED`), not
  an int constant.
- Optional fields (precipitation probability, humidity, wind
  direction) are `null` on absence, not `false`.

### `base/lib/Block/Weather`

The legacy weather block reads `$driver->units`, calls
`getSupportedForecastLengths()` and consumes a `getUnits($x)` label
map. Modern equivalents:

- Units are `WeatherConfig::$units`; the block should build its own
  `WeatherConfig` from user prefs.
- `WeatherProvider` capability check for `ForecastCapabilities` gives
  the length list.
- `Units::getLabels()` returns the temp/wind/pressure/visibility/
  precipitation label suffixes.
- Sunrise/sunset live on `Station` (via `CurrentWeather::$station`)
  when the provider is station-based (NWS, METAR). For cloud
  providers, use `AstronomyProvider::getAstronomy()` when supported.

### `base/lib/Block/Metar`

The legacy Metar block has a station-picker UI backed by
`Horde_Db` and consumes decoded METAR remark structures via
`->getRawData()`. `aviationweather.gov`'s JSON API returns different
fields and has no "browse all stations" endpoint.

A modern replacement block would either:

- Ship its own curated ICAO list (OurAirports data is one option), or
- Accept free-text ICAO codes with client-side autocomplete.

Detailed METAR remarks (sea-level pressure, precipitation totals,
sensor status, pressure tendency) are not surfaced by the modern
Metar provider. Applications that need them should either parse the
`rawOb` field (accessible via `CurrentWeather::$providerData`) or
keep using the legacy `Horde_Service_Weather_Metar` until `4.0.0`.

## Constants and enums

Legacy constants map to modern enums:

| Legacy | Modern |
|---|---|
| `Horde_Service_Weather::UNITS_METRIC` | `Units::METRIC` |
| `Horde_Service_Weather::UNITS_STANDARD` | `Units::STANDARD` |
| `Horde_Service_Weather::UNITS_IMPERIAL` | `Units::IMPERIAL` (new) |
| `Horde_Service_Weather::FORECAST_*DAY` | integer literal to `getForecast(..., $days)` |
| `Horde_Service_Weather::FORECAST_TYPE_STANDARD` | `ForecastDetail::DAILY` |
| `Horde_Service_Weather::FORECAST_TYPE_DETAILED` | `ForecastDetail::DETAILED` |
| `Horde_Service_Weather::SEARCHTYPE_IP` | `SearchType::IP_ADDRESS` |
| `Horde_Service_Weather::SEARCHTYPE_STANDARD` | `SearchType::ANY` or the more specific `CITY`/`ZIP` |

## Bug fixes in the modern API

Bugs that existed in the March 2026 pre-release and were fixed before
`3.0.0`:

- Open-Meteo air-quality query sent `uk_aqi` which is not a valid
  Open-Meteo parameter; every `getAirQuality()` call would fail with
  a parameter-validation error. Fixed by dropping `uk_aqi` from the
  request. `AirQuality::$ukDaqi` remains available for other
  providers.
- NWS forecast parsers threw on 16-point compass directions (`NNE`,
  `ENE` etc.) because `WindDirection::from()` only accepts 8-point
  values. Fixed with a `parseWindDirection()` helper that folds
  16-point strings to the nearest 8-point value.
- `Location::__construct` was private but WeatherAPI's search
  parsers called `new Location(...)` directly, throwing at runtime.
  Fixed by adding a public `Location::fromGeocoded()` factory.
- Providers imported `Horde\Http\Client` (which is not a real class
  in the modern `horde/http` tree). The March code stood up a
  library-scoped `Horde\Service\Weather\HttpClient` interface to
  work around this. `3.0.0` targets standard PSR-18
  (`Psr\Http\Client\ClientInterface`) plus PSR-17 factories directly.

## Timeline

- `3.0.0`: legacy `lib/` and modern `src/` ship side by side. `lib/`
  is `@deprecated`.
- `3.x` minor releases: No bug fixes on `lib/`. Feature growth on
  `src/`.
- `4.0.0`: `lib/` deleted. No fixed date. Cut when the last in-tree
  consumer has flipped or explicit product decision is made to force
  the retirement.
