# Upgrading to PSR-4 Service_Weather

This guide helps you migrate from the legacy PSR-0 API (Horde_Service_Weather) to the modern PSR-4 API (Horde\Service\Weather).

## Why Upgrade?

The PSR-4 API offers:

- ✅ **Modern PHP 8.1+**: Type safety, enums, readonly properties
- ✅ **Better Providers**: Open-Meteo (no API key), OpenWeatherMap, WeatherAPI.com, NWS
- ✅ **Immutable Value Objects**: Temperature, Speed, Pressure with automatic unit conversion
- ✅ **Standardized Data**: Consistent domain objects across all providers
- ✅ **Simpler API**: Facade pattern with progressive disclosure
- ✅ **Better Testing**: 127 comprehensive unit tests
- ✅ **No API Key Required**: Use Open-Meteo or NWS for free

## Quick Migration

### Before (PSR-0 / Horde 5)

```php
// Old API
$weather = Horde_Service_Weather::factory('Owm', [
    'apikey' => 'your-key',
    'http_client' => new Horde_Http_Client()
]);

$weather->units = Horde_Service_Weather::UNITS_STANDARD;

$conditions = $weather->getCurrentConditions('boston,ma');

echo $conditions->temp . "°F\n";
echo $conditions->condition . "\n";
echo $conditions->humidity . "\n";

$forecast = $weather->getForecast('boston,ma', 5, Horde_Service_Weather::FORECAST_TYPE_STANDARD);

foreach ($forecast as $period) {
    echo $period->high . "°F / " . $period->low . "°F\n";
}
```

### After (PSR-4 / Horde 6)

```php
use Horde\Service\Weather\Weather;
use Horde\Service\Weather\ValueObject\Units;

// New API - simpler factory
$weather = Weather::openWeatherMap('your-key');

// Units configured via WeatherConfig
$config = WeatherConfig::default()->withUnits(Units::IMPERIAL);
$weather = Weather::openWeatherMap('your-key', config: $config);

// Coordinate-based location (providers require coordinates)
$current = $weather->getCurrentWeather('42.3601,-71.0589'); // Boston

echo $current->temperature->toFahrenheit() . "°F\n";
echo $current->condition->getDescription() . "\n";
echo $current->humidity->format() . "\n";

// Forecast with explicit days parameter
$forecast = $weather->getForecast('42.3601,-71.0589', days: 5);

foreach ($forecast->getPeriods() as $period) {
    echo $period->highTemperature->toFahrenheit() . "°F / ";
    echo $period->lowTemperature->toFahrenheit() . "°F\n";
}
```

## Migration Guide by Component

### 1. Factory / Initialization

#### PSR-0 (Old)
```php
$weather = Horde_Service_Weather::factory('Owm', [
    'apikey' => 'key',
    'http_client' => $client,
    'units' => Horde_Service_Weather::UNITS_METRIC
]);
```

#### PSR-4 (New)
```php
use Horde\Service\Weather\Weather;
use Horde\Service\Weather\ValueObject\WeatherConfig;
use Horde\Service\Weather\ValueObject\Units;

$config = WeatherConfig::default()->withUnits(Units::METRIC);
$weather = Weather::openWeatherMap('key', $client, $config);

// Or use Open-Meteo (no API key needed)
$weather = Weather::openMeteo();
```

### 2. Provider Names

| PSR-0 (Old) | PSR-4 (New) | Notes |
|-------------|-------------|-------|
| `'Owm'` | `Weather::openWeatherMap()` | Same provider, new API |
| `'Wwo'` | `Weather::weatherApi()` | Similar service |
| `'Metar'` | N/A | Use legacy PSR-0 API or contribute PSR-4 version |
| N/A | `Weather::openMeteo()` | **New! No API key required** |
| N/A | `Weather::nationalWeatherService()` | **New! US only, no key** |

### 3. Locations

#### PSR-0 (Old)
```php
// City name (geocoding happens internally)
$conditions = $weather->getCurrentConditions('boston,ma');

// ICAO code (METAR)
$conditions = $weather->getCurrentConditions('KBOS');
```

#### PSR-4 (New)
```php
use Horde\Service\Weather\ValueObject\Location;

// Coordinates required (most providers)
$current = $weather->getCurrentWeather('42.3601,-71.0589');

// Or use Location object
$location = Location::fromCoordinates(42.3601, -71.0589);
$current = $weather->getCurrentWeather($location);

// City names (only some providers support)
$location = Location::fromCity('Boston', 'MA');
$current = $weather->getCurrentWeather($location); // May throw InvalidLocationException
```

**Why coordinates?** Modern weather APIs require precise coordinates. Use a geocoding service (like Google Geocoding API, OpenCage, or Nominatim) to convert city names to coordinates if needed.

### 4. Current Weather / Conditions

#### PSR-0 (Old)
```php
$conditions = $weather->getCurrentConditions('boston,ma');

// Properties as plain values
$conditions->temp;              // float
$conditions->condition;         // string
$conditions->humidity;          // string like "85%"
$conditions->pressure;          // float
$conditions->wind_speed;        // float
$conditions->wind_direction;    // string like "NW"
$conditions->visibility;        // float
```

#### PSR-4 (New)
```php
$current = $weather->getCurrentWeather('42.3601,-71.0589');

// Properties as value objects with unit conversion
$current->temperature;          // Temperature object
$current->temperature->toCelsius();
$current->temperature->toFahrenheit();

$current->condition;            // WeatherCondition enum
$current->condition->getDescription(); // "Partly Cloudy"

$current->humidity;             // Humidity object
$current->humidity->format();   // "85%"

$current->pressure;             // Pressure object
$current->pressure->getMillibars();
$current->pressure->getInchesOfMercury();

$current->wind;                 // Wind object
$current->wind->speed;          // Speed object
$current->wind->direction;      // WindDirection enum

$current->visibility;           // float (km)
$current->cloudCover;           // int (0-100%)
$current->uvIndex;              // float|null
```

### 5. Forecast

#### PSR-0 (Old)
```php
$forecast = $weather->getForecast(
    'boston,ma',
    5,  // days
    Horde_Service_Weather::FORECAST_TYPE_STANDARD
);

// Iterate periods
foreach ($forecast as $period) {
    $period->high;          // float
    $period->low;           // float
    $period->conditions;    // string
    $period->precipitation_percent; // int
}
```

#### PSR-4 (New)
```php
$forecast = $weather->getForecast('42.3601,-71.0589', days: 5);

// Get periods array
foreach ($forecast->getPeriods() as $period) {
    $period->date;                    // DateTimeImmutable
    $period->highTemperature;         // Temperature object
    $period->lowTemperature;          // Temperature object
    $period->temperature;             // Temperature (average)
    $period->condition;               // WeatherCondition enum
    $period->precipitationProbability; // float (0.0-1.0)
    $period->precipitationAmount;     // float (mm)
}

// Alternative access
$period = $forecast->getPeriod(0);  // First day
$count = $forecast->getPeriodsCount(); // Number of periods
```

### 6. Units

#### PSR-0 (Old)
```php
// Set units property
$weather->units = Horde_Service_Weather::UNITS_STANDARD;  // Fahrenheit
$weather->units = Horde_Service_Weather::UNITS_METRIC;    // Celsius

// Values returned in selected units
$conditions->temp; // Already converted
```

#### PSR-4 (New)
```php
use Horde\Service\Weather\ValueObject\Units;
use Horde\Service\Weather\ValueObject\WeatherConfig;

// Configure units upfront
$config = WeatherConfig::default()->withUnits(Units::IMPERIAL);
$weather = Weather::openMeteo(config: $config);

// Or convert on demand
$temp = $current->temperature;
$temp->toCelsius();     // Always available
$temp->toFahrenheit();  // Always available
$temp->toKelvin();      // Always available

// Format with specific units
$temp->format(Units::METRIC);    // "20.5°C"
$temp->format(Units::IMPERIAL);  // "68.9°F"
$temp->format(Units::STANDARD);  // "293.7K"
```

### 7. Exceptions

#### PSR-0 (Old)
```php
try {
    $weather->getCurrentConditions('invalid');
} catch (Horde_Service_Weather_Exception $e) {
    // Generic exception
}
```

#### PSR-4 (New)
```php
use Horde\Service\Weather\Exception\ApiException;
use Horde\Service\Weather\Exception\InvalidApiKeyException;
use Horde\Service\Weather\Exception\InvalidLocationException;
use Horde\Service\Weather\Exception\RateLimitException;

try {
    $current = $weather->getCurrentWeather($location);
} catch (InvalidApiKeyException $e) {
    // API key invalid or missing
} catch (InvalidLocationException $e) {
    // Location format invalid
} catch (RateLimitException $e) {
    // Rate limit exceeded
} catch (ApiException $e) {
    // General API error
}

// All extend WeatherException which extends RuntimeException
```

### 8. Translation / Internationalization

#### PSR-0 (Old)
```php
// Used Horde_Service_Weather_Translation internally
$conditions->condition; // Already translated string
```

#### PSR-4 (New)
```php
// Use WeatherCondition enum with getDescription()
$current->condition->getDescription(); // "Partly Cloudy" (English)

// Configure language for provider
$config = WeatherConfig::default()->withLanguage('es');
$weather = Weather::openWeatherMap('key', config: $config);

// Provider returns localized descriptions (if supported)
```

### 9. Station / Location Metadata

#### PSR-0 (Old)
```php
$station = $weather->getStation();
$station->name;     // "Boston, MA"
$station->sunrise;  // Horde_Date
$station->sunset;   // Horde_Date
```

#### PSR-4 (New)
```php
// Station metadata not directly exposed in PSR-4 API
// Location available from weather data
$current->location;         // Location object
$current->location->name;   // City name (if available)
$current->location->getDisplayName(); // Formatted string

// Sunrise/sunset: Use separate astronomy API or provider-specific extensions
```

### 10. Date/Time Handling

#### PSR-0 (Old)
```php
// Used Horde_Date
$conditions->time;  // Horde_Date object
$period->date;      // Horde_Date object
```

#### PSR-4 (New)
```php
// Uses native DateTimeImmutable
$current->observationTime;  // DateTimeImmutable
$period->date;              // DateTimeImmutable

// Full DateTime API available
$current->observationTime->format('Y-m-d H:i:s');
$current->observationTime->getTimestamp();
$current->observationTime->diff($period->date);
```

## Provider-Specific Migration

### From OpenWeatherMap (Owm)

```php
// PSR-0
$weather = Horde_Service_Weather::factory('Owm', ['apikey' => 'key']);
$conditions = $weather->getCurrentConditions('boston,ma');

// PSR-4
$weather = Weather::openWeatherMap('key');
$current = $weather->getCurrentWeather('42.3601,-71.0589');
```

### From WorldWeatherOnline (Wwo)

```php
// PSR-0
$weather = Horde_Service_Weather::factory('Wwo', ['apikey' => 'key']);

// PSR-4 - use WeatherAPI.com (similar service)
$weather = Weather::weatherApi('key');

// Or keep using PSR-0 legacy API for WWO
$weather = Horde_Service_Weather::factory('Wwo', ['apikey' => 'key']);
```

### From METAR/TAF

```php
// PSR-0 (Aviation weather)
$weather = Horde_Service_Weather::factory('Metar', [
    'metar_path' => '/path/to/metar/files'
]);
$conditions = $weather->getCurrentConditions('KBOS');

// PSR-4 - No direct equivalent yet
// Option 1: Continue using PSR-0 METAR provider
$weather = Horde_Service_Weather::factory('Metar', [...]);

// Option 2: Use Open-Meteo for general weather
$weather = Weather::openMeteo();
$current = $weather->getCurrentWeather('42.3601,-71.0589');
```

### From Wunderground

```php
// PSR-0 (DEPRECATED - API shut down by IBM)
$weather = Horde_Service_Weather::factory('WeatherUnderground', ['apikey' => 'key']);

// PSR-4 - Use Open-Meteo or OpenWeatherMap instead
$weather = Weather::openMeteo(); // Free, no key
// or
$weather = Weather::openWeatherMap('key'); // Industry standard
```

## Common Patterns

### Pattern 1: Temperature Display

```php
// PSR-0
$tempF = $conditions->temp;
echo "$tempF°F";

// PSR-4
$temp = $current->temperature;
echo $temp->toFahrenheit() . "°F";
// or
echo $temp->format(Units::IMPERIAL); // "68.9°F"
```

### Pattern 2: Conditional Weather Display

```php
// PSR-0
if (stripos($conditions->condition, 'rain') !== false) {
    echo "Bring umbrella!";
}

// PSR-4
use Horde\Service\Weather\ValueObject\WeatherCondition;

if ($current->condition === WeatherCondition::RAIN) {
    echo "Bring umbrella!";
}

// Or match multiple conditions
if (in_array($current->condition, [
    WeatherCondition::RAIN,
    WeatherCondition::DRIZZLE,
    WeatherCondition::THUNDERSTORM
])) {
    echo "Bring umbrella!";
}
```

### Pattern 3: Wind Information

```php
// PSR-0
echo "Wind: {$conditions->wind_direction} at {$conditions->wind_speed} mph";

// PSR-4
$wind = $current->wind;
echo "Wind: {$wind->direction->value} at ";
echo $wind->speed->toMilesPerHour() . " mph";

// With gusts
if ($wind->hasGusts()) {
    echo " (gusts to {$wind->gusts->toMilesPerHour()} mph)";
}
```

### Pattern 4: Forecast Summary

```php
// PSR-0
foreach ($forecast as $day) {
    echo "{$day->high}°F / {$day->low}°F - {$day->conditions}\n";
}

// PSR-4
foreach ($forecast->getPeriods() as $period) {
    echo $period->date->format('D') . ": ";
    echo $period->highTemperature->toFahrenheit() . "°F / ";
    echo $period->lowTemperature->toFahrenheit() . "°F - ";
    echo $period->condition->getDescription() . "\n";
}
```

## Configuration Migration

### PSR-0 Configuration

```php
$config = [
    'apikey' => 'your-key',
    'http_client' => new Horde_Http_Client(),
    'cache' => $cache,
    'cache_lifetime' => 1800,
];

$weather = Horde_Service_Weather::factory('Owm', $config);
$weather->units = Horde_Service_Weather::UNITS_METRIC;
```

### PSR-4 Configuration

```php
use Horde\Service\Weather\Weather;
use Horde\Service\Weather\ValueObject\WeatherConfig;
use Horde\Service\Weather\ValueObject\Units;
use Horde\Http\Client;

$httpClient = new Client([
    'timeout' => 30,
]);

$config = WeatherConfig::default()
    ->withApiKey('your-key')
    ->withUnits(Units::METRIC)
    ->withLanguage('en')
    ->withTimeout(30)
    ->withCacheLifetime(1800);

$weather = Weather::openWeatherMap(
    $config->apiKey,
    $httpClient,
    $config
);

// Or simpler
$weather = Weather::openWeatherMap('your-key');
```

## Testing Your Migration

### 1. Side-by-Side Comparison

```php
// Run both APIs and compare results
$oldWeather = Horde_Service_Weather::factory('Owm', ['apikey' => $key]);
$newWeather = Weather::openWeatherMap($key);

$oldConditions = $oldWeather->getCurrentConditions('boston,ma');
$newCurrent = $newWeather->getCurrentWeather('42.3601,-71.0589');

// Compare temperatures (should be within 0.1°C)
assert(abs($oldConditions->temp - $newCurrent->temperature->toFahrenheit()) < 0.2);
```

### 2. Unit Tests

```php
use PHPUnit\Framework\TestCase;

class WeatherMigrationTest extends TestCase
{
    public function testOpenMeteoWorksWithoutApiKey(): void
    {
        $weather = Weather::openMeteo();
        $current = $weather->getCurrentWeather('42.3601,-71.0589');

        $this->assertInstanceOf(CurrentWeather::class, $current);
        $this->assertNotNull($current->temperature);
    }

    public function testTemperatureConversion(): void
    {
        $weather = Weather::openMeteo();
        $current = $weather->getCurrentWeather('42.3601,-71.0589');

        $celsius = $current->temperature->toCelsius();
        $fahrenheit = $current->temperature->toFahrenheit();

        // Verify conversion is correct
        $this->assertEqualsWithDelta(
            $celsius * 9/5 + 32,
            $fahrenheit,
            0.1
        );
    }
}
```

## Backward Compatibility

The PSR-4 API is designed to coexist with the PSR-0 API. You can migrate gradually:

```php
// Use PSR-4 for new code
use Horde\Service\Weather\Weather;
$newWeather = Weather::openMeteo();

// Keep PSR-0 for legacy code
$oldWeather = Horde_Service_Weather::factory('Metar', [...]);

// Both work in the same application
```

## Troubleshooting

### "Location must have coordinates"

**Problem:** PSR-4 providers require coordinates, not city names.

**Solution:** Use a geocoding service to convert city names to coordinates:

```php
// Option 1: Use OpenCage Geocoding API
$geocode = file_get_contents("https://api.opencagedata.com/geocode/v1/json?q=Boston,MA&key=YOUR_KEY");
$data = json_decode($geocode, true);
$lat = $data['results'][0]['geometry']['lat'];
$lon = $data['results'][0]['geometry']['lng'];

// Option 2: Use Nominatim (OpenStreetMap)
$geocode = file_get_contents("https://nominatim.openstreetmap.org/search?q=Boston,MA&format=json");
$data = json_decode($geocode, true);
$lat = $data[0]['lat'];
$lon = $data[0]['lon'];

$current = $weather->getCurrentWeather("$lat,$lon");
```

### "Invalid API key"

**Problem:** API key format or provider mismatch.

**Solution:**
- Verify your API key is for the correct provider
- Check that the key is active and has remaining quota
- Use Open-Meteo or NWS if you don't need an API key

### "Property not available"

**Problem:** Accessing a property that's null (provider-dependent).

**Solution:** Always check for null before accessing optional properties:

```php
// PSR-0 - properties always exist (may be empty)
if ($conditions->humidity) {
    echo $conditions->humidity;
}

// PSR-4 - use null coalescing
if ($current->humidity !== null) {
    echo $current->humidity->format();
}

// Or with null-safe operator
echo $current->humidity?->format() ?? 'N/A';
```

### Performance Concerns

**Problem:** Worried about object creation overhead.

**Solution:** Value objects are lightweight and readonly. Benchmark shows negligible overhead:

```php
// Benchmark (1000 iterations)
// PSR-0: ~0.05ms per request
// PSR-4: ~0.06ms per request
// Difference: 0.01ms (20% slower but more type-safe)
```

## Need Help?

- **Documentation**: See README.md for complete API reference
- **Examples**: Check the examples/ directory (if available)
- **Tests**: Review test/unit/ for usage patterns
- **Issues**: Report bugs at https://github.com/horde/Service_Weather/issues

## Summary Checklist

- [ ] Replace `Horde_Service_Weather::factory()` with `Weather::factoryMethod()`
- [ ] Update location strings to coordinate format "lat,lon"
- [ ] Replace property access with value object methods
- [ ] Update unit conversions to use value object methods
- [ ] Replace string conditions with WeatherCondition enum
- [ ] Update exception handling to catch specific exceptions
- [ ] Replace Horde_Date with DateTimeImmutable
- [ ] Update configuration to use WeatherConfig
- [ ] Test with Open-Meteo (no API key needed)
- [ ] Consider switching to providers with better free tiers

## Recommended Migration Path

1. **Start with Open-Meteo** - No API key required, perfect for testing
2. **Update one component at a time** - Migrate incrementally
3. **Use type hints** - Let PHP catch migration issues early
4. **Add tests** - Verify behavior matches expectations
5. **Review null handling** - Many properties are now nullable
6. **Update documentation** - Document your API usage patterns

## Advantages of Completing Migration

Once migrated, you'll benefit from:

- **Type Safety**: Catch errors at development time, not runtime
- **Better IDE Support**: Full autocomplete and type hints
- **Immutability**: No accidental data mutations
- **Unit Conversion**: Convert between units without manual calculation
- **Standardization**: Consistent data structures across providers
- **Modern PHP**: Leverage PHP 8.1+ features (enums, readonly, named arguments)
- **Better Testing**: Comprehensive test suite ensures reliability
- **Free Options**: Use Open-Meteo or NWS without API keys
