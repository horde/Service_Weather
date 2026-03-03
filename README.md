# Horde Service_Weather

Modern weather service library for PHP 8.1+ with support for multiple weather providers.

## Features

- ✅ **Multiple Providers**: Open-Meteo, OpenWeatherMap, WeatherAPI.com, US National Weather Service
- ✅ **PSR-4 Architecture**: Modern, autoloadable namespace structure
- ✅ **Type Safety**: Full PHP 8.1+ type declarations and readonly properties
- ✅ **Immutable Value Objects**: Temperature, Speed, Pressure with automatic unit conversion
- ✅ **No API Key Required**: Use Open-Meteo or NWS without registration
- ✅ **Standardized Data**: All providers return consistent domain objects
- ✅ **HTTP via horde/http**: PSR-7/18 compatible HTTP client
- ✅ **Backward Compatible**: Legacy PSR-0 providers still work (METAR, WWO, Wunderground)

## Installation

```bash
composer require horde/service-weather
```

## Quick Start

### Open-Meteo (No API Key Required)

```php
use Horde\Service\Weather\Weather;

// Create service using Open-Meteo (free, unlimited, no API key)
$weather = Weather::openMeteo();

// Get current weather for Boston
$current = $weather->getCurrentWeather('42.3601,-71.0589');

echo "Temperature: " . $current->temperature->toCelsius() . "°C\n";
echo "Condition: " . $current->condition->getDescription() . "\n";
echo "Humidity: " . $current->humidity->format() . "\n";

// Get 5-day forecast
$forecast = $weather->getForecast('42.3601,-71.0589', days: 5);

foreach ($forecast->getPeriods() as $period) {
    echo $period->date->format('Y-m-d') . ": ";
    echo $period->condition->getDescription() . " ";
    echo "High: " . $period->highTemperature->toFahrenheit() . "°F ";
    echo "Low: " . $period->lowTemperature->toFahrenheit() . "°F\n";
}
```

### OpenWeatherMap

```php
// Requires API key from https://openweathermap.org/api
// Free tier: 1000 calls/day
$weather = Weather::openWeatherMap('your-api-key-here');

$current = $weather->getCurrentWeather('40.7128,-74.0060'); // New York
```

### WeatherAPI.com

```php
// Requires API key from https://www.weatherapi.com/
// Free tier: 1 million calls/month
$weather = Weather::weatherApi('your-api-key-here');

$forecast = $weather->getForecast('51.5074,-0.1278', days: 7); // London
```

### US National Weather Service

```php
// No API key required, US locations only
$weather = Weather::nationalWeatherService();

$current = $weather->getCurrentWeather('47.6062,-122.3321'); // Seattle
```

## Using Location Objects

```php
use Horde\Service\Weather\ValueObject\Location;

// From coordinates
$location = Location::fromCoordinates(48.8566, 2.3522);

// From coordinate object
$coordinate = Coordinate::fromLatLon(51.5074, -0.1278);
$location = Location::fromCoordinate($coordinate);

// From city name (some providers support this)
$location = Location::fromCity('Paris', 'France');

// Use with weather service
$current = $weather->getCurrentWeather($location);
```

## Unit Conversions

All value objects support automatic unit conversion:

### Temperature

```php
$temp = Temperature::fromCelsius(20);
echo $temp->toCelsius();     // 20.0
echo $temp->toFahrenheit();  // 68.0
echo $temp->toKelvin();      // 293.2
echo $temp->format(Units::IMPERIAL); // "68.0°F"
```

### Wind Speed

```php
$speed = Speed::fromMetersPerSecond(10);
echo $speed->toMetersPerSecond();      // 10.0
echo $speed->toKilometersPerHour();    // 36.0
echo $speed->toMilesPerHour();         // 22.4
echo $speed->toKnots();                // 19.4
```

### Pressure

```php
$pressure = Pressure::fromMillibars(1013.25);
echo $pressure->getMillibars();        // 1013.3
echo $pressure->getHectopascals();     // 1013.3 (same as millibars)
echo $pressure->getInchesOfMercury();  // 29.92
```

## Configuration

Customize provider behavior with `WeatherConfig`:

```php
use Horde\Service\Weather\ValueObject\WeatherConfig;
use Horde\Service\Weather\ValueObject\Units;

$config = WeatherConfig::default()
    ->withUnits(Units::IMPERIAL)      // Use Fahrenheit, mph, inHg
    ->withLanguage('es')               // Spanish translations
    ->withTimeout(15)                  // 15 second HTTP timeout
    ->withCacheLifetime(600);          // Cache for 10 minutes

$weather = Weather::openMeteo(config: $config);
```

## Provider Comparison

| Provider | Free Tier | API Key | Coverage | Current | Forecast |
|----------|-----------|---------|----------|---------|----------|
| **Open-Meteo** | Unlimited | No | Global | ✅ | ✅ 16-day |
| **OpenWeatherMap** | 1K/day | Yes | Global | ✅ | ✅ 5-day |
| **WeatherAPI.com** | 1M/month | Yes | Global | ✅ | ✅ 14-day |
| **NWS** | Unlimited | No | US only | ✅ | ✅ 7-day |

## Domain Objects

### CurrentWeather

```php
$current = $weather->getCurrentWeather($location);

// Always available
$current->location;           // Location object
$current->temperature;        // Temperature object
$current->condition;          // WeatherCondition enum
$current->observationTime;    // DateTimeImmutable

// Often available (provider-dependent)
$current->feelsLike;          // Temperature|null
$current->humidity;           // Humidity|null
$current->pressure;           // Pressure|null
$current->wind;               // Wind|null
$current->visibility;         // float|null (km)
$current->cloudCover;         // int|null (0-100%)
$current->uvIndex;            // float|null
```

### Forecast

```php
$forecast = $weather->getForecast($location, days: 5);

$forecast->getLocation();      // Location object
$forecast->getPeriodsCount();  // int
$forecast->getPeriods();       // array<ForecastPeriod>
$forecast->getPeriod(0);       // ForecastPeriod|null
```

### ForecastPeriod

```php
foreach ($forecast->getPeriods() as $period) {
    $period->date;                    // DateTimeImmutable
    $period->temperature;             // Temperature (average)
    $period->condition;               // WeatherCondition
    $period->highTemperature;         // Temperature|null
    $period->lowTemperature;          // Temperature|null
    $period->humidity;                // Humidity|null
    $period->pressure;                // Pressure|null
    $period->wind;                    // Wind|null
    $period->precipitationProbability; // float|null (0.0-1.0)
    $period->precipitationAmount;     // float|null (mm)
    $period->cloudCover;              // int|null (0-100%)
}
```

## Weather Conditions

Standardized conditions across all providers:

```php
enum WeatherCondition {
    CLEAR, PARTLY_CLOUDY, CLOUDY, OVERCAST,
    FOG, DRIZZLE, RAIN, FREEZING_RAIN,
    SNOW, SLEET, THUNDERSTORM, HAIL,
    TORNADO, HURRICANE, UNKNOWN
}

$condition = WeatherCondition::RAIN;
echo $condition->getDescription();  // "Rain"
echo $condition->getIconCode();     // "10"
```

## Error Handling

```php
use Horde\Service\Weather\Exception\ApiException;
use Horde\Service\Weather\Exception\InvalidApiKeyException;
use Horde\Service\Weather\Exception\InvalidLocationException;

try {
    $current = $weather->getCurrentWeather($location);
} catch (InvalidApiKeyException $e) {
    // API key is invalid or missing
} catch (InvalidLocationException $e) {
    // Location format is invalid
} catch (ApiException $e) {
    // General API error (network, rate limit, etc.)
}
```

## Advanced Usage

### Custom HTTP Client

```php
use Horde\Http\Client;

$httpClient = new Client([
    'timeout' => 30,
    'userAgent' => 'MyApp/1.0',
]);

$weather = Weather::openMeteo($httpClient);
```

### Direct Provider Access

```php
use Horde\Service\Weather\Provider\OpenMeteo;
use Horde\Http\Client;
use Horde\Service\Weather\ValueObject\WeatherConfig;

$provider = new OpenMeteo(
    new Client(),
    WeatherConfig::default()->withUnits(Units::IMPERIAL)
);

$current = $provider->getCurrentWeather('42.3601,-71.0589');
```

## Legacy PSR-0 Support

The legacy PSR-0 API (Horde_Service_Weather) is still available for backward compatibility:

```php
// Old PSR-0 API (still works)
$weather = Horde_Service_Weather::factory('Metar', [
    'metar_path' => '/path/to/metar/files'
]);
```

## Upgrading from PSR-0 (Horde 5)

Migrating from the legacy `Horde_Service_Weather` API? See **[doc/UPGRADING.md](doc/UPGRADING.md)** for a complete migration guide including:

- Side-by-side code comparisons
- Provider migration paths
- Common patterns and gotchas
- Testing strategies
- Troubleshooting tips

**Quick example:**

```php
// Before (PSR-0)
$weather = Horde_Service_Weather::factory('Owm', ['apikey' => 'key']);
$conditions = $weather->getCurrentConditions('boston,ma');
echo $conditions->temp . "°F";

// After (PSR-4)
$weather = Weather::openWeatherMap('key');
$current = $weather->getCurrentWeather('42.3601,-71.0589');
echo $current->temperature->toFahrenheit() . "°F";
```

## Testing

```bash
# Run all tests
vendor/bin/phpunit

# Run only PSR-4 tests
vendor/bin/phpunit test/unit/

# Run only legacy PSR-0 tests
vendor/bin/phpunit test/Horde/
```

## Requirements

- PHP 8.1 or higher
- horde/http
- horde/date
- horde/exception
- horde/translation
- horde/url

## License

BSD License. See LICENSE file for details.

## Links

- **Documentation**: https://www.horde.org/
- **Repository**: https://github.com/horde/Service_Weather
- **Provider APIs**:
  - [Open-Meteo](https://open-meteo.com/)
  - [OpenWeatherMap](https://openweathermap.org/api)
  - [WeatherAPI.com](https://www.weatherapi.com/)
  - [US National Weather Service](https://www.weather.gov/documentation/services-web-api)

## Contributing

Contributions are welcome! Please follow the Horde coding standards and include tests for new features.
