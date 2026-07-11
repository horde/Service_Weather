# horde/service_weather

Weather-service abstraction for PHP 8.1+ with a modern PSR-4 API and
a set of ready-made providers.

`3.0.0` ships both the modern API under `Horde\Service\Weather\` and
the legacy PSR-0 API under `Horde_Service_Weather*`. New code should
target the modern API.
Legacy classes are only provided to technically not break old users.
A lot of functionality in the legacy code base targets dead API versions or providers.
Every user is encouraged to migrate to the new API immediately.

## Providers

| Provider | API key | Coverage | Notes |
|---|---|---|---|
| Open-Meteo | none | Global | Free, no registration. Recommended for testing and demo use |
| OpenWeatherMap | required | Global | Commercial. Free tier 1000 calls/day on `/data/2.5` endpoints |
| WeatherAPI.com | required | Global | Commercial. Free tier 1M calls/month includes air quality, astronomy, location search |
| US NWS (`api.weather.gov`) | none | US only | Custom `User-Agent` required per NWS terms |
| METAR (`aviationweather.gov`) | none | Global aviation stations | ICAO-keyed. Custom `User-Agent` requested |

## Installation

```bash
composer require horde/service_weather
```

## Quick start

Every provider takes a PSR-18 HTTP client plus a PSR-17 request
factory. `horde/http` ships both (`Horde\Http\Client\Curl` and
`Horde\Http\Client\Mock` implement `Psr\Http\Client\ClientInterface`;
`Horde\Http\RequestFactory` implements
`Psr\Http\Message\RequestFactoryInterface`). Guzzle, Symfony
HttpClient, and every other modern PHP HTTP library also implement
PSR-18 / PSR-17 and drop in directly.

```php
use Horde\Http\Client\Curl;
use Horde\Http\Options;
use Horde\Http\RequestFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde\Service\Weather\Weather;
use Horde\Service\Weather\ValueObject\Location;

$responseFactory = new ResponseFactory();
$streamFactory = new StreamFactory();
$http = new Curl($responseFactory, $streamFactory, new Options());

$weather = Weather::openMeteo($http, new RequestFactory());
$current = $weather->getCurrentWeather(Location::fromCoordinates(52.52, 13.41));
echo $current->temperature->toCelsius();
echo $current->condition->getDescription();
```

Using Guzzle instead is a one-line swap:

```php
$http = new \GuzzleHttp\Client();
$requestFactory = new \Http\Factory\Guzzle\RequestFactory();
$weather = Weather::openMeteo($http, $requestFactory);
```

## API key providers

```php
use Horde\Http\RequestFactory;
use Horde\Service\Weather\Weather;
use Horde\Service\Weather\ValueObject\Location;

$rf = new RequestFactory();

$weather = Weather::openWeatherMap($http, $rf, 'your-owm-key');
// or
$weather = Weather::weatherApi($http, $rf, 'your-weatherapi-key');

$forecast = $weather->getForecast(
    Location::fromCoordinates(40.7128, -74.0060),
    days: 5,
);
foreach ($forecast as $period) {
    printf(
        "%s: %d°C / %d°C - %s\n",
        $period->date->format('Y-m-d'),
        $period->highTemperature?->toCelsius() ?? 0,
        $period->lowTemperature?->toCelsius() ?? 0,
        $period->condition->getDescription(),
    );
}
```

## Capability interfaces

Providers implement a small core interface plus optional capability
interfaces. Callers `instanceof`-check for optional features:

```php
use Horde\Service\Weather\AirQualityProvider;
use Horde\Service\Weather\AstronomyProvider;
use Horde\Service\Weather\HourlyForecast;
use Horde\Service\Weather\StationLookup;

$provider = $weather->getProvider();

if ($provider instanceof HourlyForecast) {
    $hourly = $provider->getHourlyForecast($location, hours: 24);
}
if ($provider instanceof AirQualityProvider) {
    $aq = $provider->getAirQuality($location);
    echo $aq->getCategory()?->value;  // "moderate", "good", ...
}
if ($provider instanceof AstronomyProvider) {
    $astro = $provider->getAstronomy($location);
    echo $astro->sunset?->format('H:i');
}
if ($provider instanceof StationLookup) {
    $station = $provider->getStation('KJFK');
}
```

Full interface list:

- `WeatherProvider` (core)
- `ForecastCapabilities` – describe supported forecast lengths
- `HourlyForecast` – intra-day granularity
- `LocationSearch` – free-form location resolution (WeatherAPI only at 3.0)
- `StationLookup` – observation stations (NWS, METAR)
- `AirQualityProvider` – PM/gas concentrations and locale AQIs
- `AstronomyProvider` – sun/moon rise, set, phase
- `AlertProvider` – weather alerts. Version `3.0.0` only provides an interface and provider implementations may follow in a `3.x` minor (see [doc/FEATURE_GAPS.md](doc/FEATURE_GAPS.md))

## Configuration

`WeatherConfig` collects optional knobs: API key, units, language,
timeout, cache lifetime, PSR-16 cache instance, custom User-Agent.
It's immutable. Use the `with*()` methods to derive new configs.

```php
use Horde\Http\RequestFactory;
use Horde\Service\Weather\ValueObject\Units;
use Horde\Service\Weather\ValueObject\WeatherConfig;

$config = WeatherConfig::default()
    ->withUnits(Units::IMPERIAL)
    ->withLanguage('de')
    ->withUserAgent('my-app/1.0 (+https://example.com)')
    ->withCache($psr16Cache)
    ->withCacheLifetime(900);

$weather = Weather::nationalWeatherService($http, new RequestFactory(), $config);
```

## Caching

When a PSR-16 cache is set on `WeatherConfig`, provider HTTP calls
are transparently deduplicated. The cache key is derived from the
request URI. TTL is `cacheLifetime`. To enable caching, pass a
`ResponseFactory` and `StreamFactory` alongside the config so the
decorator can rebuild PSR-7 responses from the cached tuples.

```php
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde\Service\Weather\Weather;
use Horde\Service\Weather\ValueObject\WeatherConfig;

$config = WeatherConfig::default()->withCache($psr16Cache);
$weather = Weather::openMeteo(
    $http,
    $rf,
    $config,
    new ResponseFactory(),
    new StreamFactory(),
);

$weather->getCurrentWeather($location);  // hits HTTP
$weather->getCurrentWeather($location);  // hits cache
```

Non-2xx responses and exceptions are never cached.

## Legacy `Horde_Service_Weather*` API

Legacy code that still uses the PSR-0 classes continues to work at
`3.x`:

```php
$weather = Horde_Service_Weather::factory('Owm', [
    'apikey' => 'your-key',
    'http_client' => new Horde_Http_Client(),
]);
$conditions = $weather->getCurrentConditions('boston,ma');
```

Every legacy class is `@deprecated`-tagged in its docblock. See
[doc/UPGRADING.md](doc/UPGRADING.md) for migration notes covering
each in-tree consumer (timeobjects driver, base weather blocks) and
the constant / method-name mapping between old and new APIs.

## Feature gaps

Not every legacy feature is ported yet and some 2026-standard
weather-API features are not (yet) exposed. Notable items include
weather alerts (interface only at `3.0.0`), provider attribution
metadata, meteorological helpers (wind-chill, dewpoint, humidity
derivations) and air-quality forecasts.

See [doc/FEATURE_GAPS.md](doc/FEATURE_GAPS.md) for the full
inventory, categorized as regressions vs features we never had but modern integrators expect.

## Requirements

- PHP 8.1 or later (readonly properties, enums, named arguments)
- `psr/http-client: ^1` (PSR-18)
- `psr/http-factory: ^1` (PSR-17)
- `psr/http-message: ^1 || ^2` (PSR-7)
- `psr/simple-cache: ^3`
- A caller-supplied PSR-18 client and PSR-17 request factory.
  `horde/http` (suggested, not required) ships both.

## License

BSD-2-Clause. See [LICENSE](LICENSE).
