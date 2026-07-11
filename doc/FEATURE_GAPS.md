# Feature Gaps

This document catalogs what modern Weather APIs usually provide but our client doesn't cover yet.

This is not a road map. Items here are known-not-done. Some may
land in `3.x` minor releases, some in later versions or never.

For migration path guidance, including the shape changes between the
legacy `Horde_Service_Weather*` API and the modern `Horde\Service\Weather\`
API, see [UPGRADING.md](UPGRADING.md).

## Regressions from the legacy library

Features the legacy library had that the modern library does not.

### Alerts

Legacy: `Horde_Service_Weather_Base::getAlerts($location)` returned
an iterable of alert dicts. The legacy base weather block rendered
these.

Modern: the `AlertProvider` capability interface exists. The
`WeatherAlert` domain object is typed and complete. **No provider
implements `AlertProvider` at `3.0.0`.**

Plan: implement on NWS, WeatherAPI and OpenWeatherMap One Call in a
`3.x` minor release.

### Provider self-description

Legacy: every provider exposed `$weather->title` (attribution
string), `$weather->link` (provider homepage URL), `$weather->logo`
(theme icon path) and `$weather->iconMap` (provider-specific icon
code to filename map). The legacy weather block reads all four for
its footer attribution.

Modern: no equivalent. `WeatherCondition::getIcon()` returns a
generic slug that maps to a consumer-chosen icon set. Attribution is
not modeled anywhere.

Legal note: OpenMeteo's ODbL license and WeatherAPI's free tier both
require visible attribution. Applications that render weather to end
users are technically non-compliant until they wire attribution
themselves. NWS and `aviationweather.gov` request identification via
User-Agent (already handled) but do not require display attribution.

Plan: a `ProviderMetadata` capability interface with `getName()`,
`getAttribution()`, `getHomepageUrl()`, `getTermsUrl()`,
`getRateLimitInfo()`. Ships in a `3.x` minor.

### Meteorological helpers

Legacy statics on `Horde_Service_Weather`:

- `calculateWindChill($tempF, $mph)` (NWS 2001 formula)
- `calculateHumidity($tempC, $dewpointC)` (Magnus-Tetens)
- `calculateDewPoint($tempC, $humidity)` (inverse Magnus-Tetens)

Used internally by the legacy METAR parser and by callers rendering
derived fields (e.g. computing humidity when only temperature and
dewpoint were reported).

Modern: no equivalent. `Temperature`, `Speed`, `Pressure` and
`Humidity` value objects have unit-conversion getters but no
derivation helpers between measurements.

Not universal across providers. Each meteorological agency picks a
different flavor of the underlying model. NWS wind chill (2001) is
valid only for `T <= 10 degrees C` and `V >= 4.8 km/h`. Environment
Canada uses the same physics with different unit expression.
Australian BOM uses "apparent temperature" (Steadman) which includes
humidity and is a different model entirely.

Modern providers that ship `feels_like` / `apparent_temperature`
natively (OpenMeteo, OpenWeatherMap, WeatherAPI) already populate
`CurrentWeather::$feelsLike` from an authoritative source. The
helpers are only needed for providers that do not (currently
METAR).

Plan: provide formula-named helpers and leave it to the caller to
use them as appropriate.

### Radar and tile-server URLs

Legacy: `getRadarImageUrl($location)` and
`getTileServerUrl($location, $type)`. The legacy weather block
consumes these when its `showMap` param is set.

Modern: no equivalent.

The gap will not close cleanly. No single URL scheme fits all
providers. NWS embeds a `radar_url` in point-data responses.
OpenWeatherMap has a subscription-tier tile server. WeatherAPI and
OpenMeteo do not offer static radar.

Plan: a `RadarProvider` capability interface may ship later if a
real consumer needs one. Any implementation will be service-specific.

### Station-browsing UI

Legacy: `Horde_Service_Weather_Metar::getLocations()` returned every
ICAO station in the library's own SQL database, keyed for a country
/ station-list picker UI (used by the legacy METAR block).

Modern: no equivalent. `aviationweather.gov` has no
"list-every-station" endpoint. `StationLookup::findStationsNear()`
covers "stations near a point" but not "browse all."

This is an application-level concern. A modern caller that wants a
station picker ships its own curated ICAO list (OurAirports data is
one option) or relies on "stations near" search.

### METAR autocomplete

Legacy: `Horde_Service_Weather_Metar::autocompleteLocation($q)` ran
a `LIKE` query against the same SQL station database as the
station-browsing UI.

Modern: `LocationSearch::searchLocations()` covers autocomplete for
providers that offer server-side geocoding (currently only
WeatherAPI). METAR's autocomplete has no server-side equivalent, for
the same reason as above.

## Modern 2026 features not covered

Features a caller in 2026 could reasonably expect from a weather
library that neither the legacy nor the modern Horde API exposes.
Ordered by likely audience demand.

### Weather alerts

Same underlying gap as under regressions above. Not just a legacy
regression but also a modern expectation. Every consumer weather app
in 2026 renders active watches and warnings when they exist for the
queried location.

### Air quality forecast

Modern shipped `AirQualityProvider::getAirQuality()` for current
conditions only. OpenMeteo's air-quality-api and WeatherAPI's
`/forecast.json?aqi=yes` both offer forecast air quality, hourly, up
to five days for OpenMeteo.

Plan: extend `AirQualityProvider` with a forecast method. Ships in a
`3.x` minor.

### Multi-day astronomy

Current `Astronomy` covers sunrise, sunset, moonrise, moonset, moon
phase and moon illumination for a single date. Multi-day queries
(sunset over a week, moon phase progression over a month) require
one call per date today.

Missing beyond that:

- Civil, nautical and astronomical twilight times
- Solar noon
- Solar / lunar altitude at a specific moment

Plan: rarely-asked-for outside astronomy-focused apps. Extend
`Astronomy` with optional fields on demand.

### Marine forecast

Waves, swell, tides, sea-surface temperature. OpenMeteo has a
dedicated marine API. WeatherAPI has `/marine.json` (paid). NWS has
zone-based marine forecasts.

Plan: `MarineProvider` capability interface, shipped when a real
consumer (sailing app, coastal-planning tool) needs it. OpenMeteo is
the only free source.

### Historical observations

"What was the weather in Berlin on 2018-06-14?" OpenMeteo's
`archive-api.open-meteo.com` covers 1940 to present. WeatherAPI's
`/history.json` covers 2010 to present on paid plans.
OpenWeatherMap has a paid history API.

Plan: `HistoricalWeatherProvider` capability interface. Non-trivial
(date-range semantics, potentially large payloads). Ship in a later
minor if a consumer needs it.

### Solar radiation

GHI (global horizontal irradiance), DNI (direct normal), DHI
(diffuse horizontal). Relevant for solar-panel forecasting and
agriculture. OpenMeteo publishes this on its forecast endpoint.
WeatherAPI does not.

Plan: niche audience. Ship on demand.

### Winds aloft / pressure-level data

Wind speed and direction at multiple altitudes (surface, 850 mb,
700 mb, ...). OpenMeteo publishes 19 pressure levels. Aviation
weather uses FD tables. NWS provides winds-aloft via text bulletin.

Plan: aviation-specific. Ships if an aviation consumer materializes,
likely as an `UpperAirProvider` capability or as an extension to the
METAR provider.

### Fire-weather warnings

NWS publishes fire-weather forecasts and red-flag warnings via its
alerts endpoint, categorized as `Fire Weather` in the CAP severity
scheme. Covered incidentally by the alerts item above for NWS. No
separate capability needed.

### Precipitation nowcast

"Will it rain in the next 90 minutes?" OpenWeatherMap One Call has
`minutely.precipitation`. OpenMeteo publishes 15-minute intervals.

Plan: `PrecipitationNowcast` capability and OpenWeatherMap One Call implementation when there is interest.

### Pollen / allergen forecast

Ambee, Google Pollen API, OpenMeteo (Europe only). Not core weather
but bundled by many consumer apps.

Plan: `PollenProvider` capability if a real audience shows up. No in-tree consumer today.

### UV-index forecast on daily

Current UV is on `CurrentWeather::$uvIndex`. `ForecastPeriod::$uvIndex`
also exists. Provider wiring is inconsistent: OpenMeteo hourly
populates it, WeatherAPI hourly populates it, WeatherAPI daily does
not (available in the response but the parser drops it).

Plan: fix the WeatherAPI daily parser as a small follow-up.

### Bulk multi-location query

"Give me current weather for these 50 cities." OpenMeteo accepts
comma-separated latitude / longitude. WeatherAPI has bulk endpoints
on enterprise plans. Every modern provider is one-location-per-call
today.

Plan: not planned. Consumers loop; the PSR-16 cache absorbs
same-location deduplication.


## Modern-API coverage gaps

Small things that are neither legacy regressions nor missing 2026
capabilities but where the current implementation leaves something
on the table.

### `CurrentWeather::$pressureTrend` populated by no provider

The field exists but no provider populates it at `3.0.0`. Only METAR
has 3-hour pressure tendency in its raw report, and the modern METAR
provider reads the pre-parsed JSON which does not surface that
field. Fix requires parsing the raw METAR text for the `PRESTND`
group. Deferred until a consumer needs it.

### No capability-set self-description

Callers `instanceof`-check each capability interface. What does not
exist: a self-descriptive method that returns "this provider
supports X, Y, Z" as data. Would let a factory or UI render "features
your configured provider supports." Small addition; would roll into
`ProviderMetadata` (see the "Provider self-description" regression
above) if that ships.

## Summary tables

### Legacy features in the modern library

| Legacy feature | Modern status | Ship? |
|---|---|---|
| Current conditions | Yes | done |
| Daily forecast | Yes | done |
| Hourly forecast | Yes | done |
| Weather alerts | Interface only | Yes, `3.x` minor |
| Provider self-description | No | Yes, `3.x` minor |
| Meteorological helpers | No | Yes, `3.x` minor |
| Radar / tile URLs | No | On demand |
| Location autocomplete | Partial | Expand as providers ship it |
| Browse-all-stations | No | No, application concern |

### 2026 features not exposed

| Feature | Ship? |
|---|---|
| Weather alerts | Yes, `3.x` minor |
| Air-quality forecast | Yes, `3.x` minor |
| Multi-day astronomy | On demand |
| Marine forecast | On demand |
| Historical observations | On demand |
| Solar radiation | On demand |
| Winds aloft | On demand |
| Fire-weather warnings | Covered via alerts |
| Precipitation nowcast | Ships with OWM One Call |
| Pollen forecast | On demand |
| Bulk multi-location query | No, cache absorbs it |
| Provider-native ML | Out of scope |
| Ensemble forecasts | Out of scope |
| Climate normals | Out of scope |
