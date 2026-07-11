<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Provider;

use Horde\Http\RequestFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde\Service\Weather\Provider\Metar;
use Horde\Service\Weather\Test\Support\MockHttpClient;
use Horde\Service\Weather\Test\Support\InMemoryCache;
use Horde\Service\Weather\ValueObject\WeatherConfig;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end verification that a WeatherConfig with a cache set makes
 * providers automatically deduplicate identical HTTP calls without any
 * code change at the provider layer. Uses the Metar provider as the
 * representative. Every other provider uses the same wire-up.
 * @coversNothing
 */
class CacheIntegrationTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures/metar';

    public function testMetarSecondIdenticalCallSkipsHttp(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/current-kjfk.json');
        $cache = new InMemoryCache();

        $config = WeatherConfig::default()->withCache($cache);
        $metar = new Metar($http, new RequestFactory(), $config, new ResponseFactory(), new StreamFactory());

        $first = $metar->getCurrentWeatherByIcao('KJFK');
        $second = $metar->getCurrentWeatherByIcao('KJFK');

        // Second call must not have hit the network.
        $this->assertSame(1, $http->getRequestCount());
        // But it must have returned the same shape.
        $this->assertSame($first->temperature->toCelsius(), $second->temperature->toCelsius());
        $this->assertSame($first->station?->identifier, $second->station?->identifier);
        // Cache should show one hit (second call) and one miss (first call).
        $this->assertSame(1, $cache->hits);
        $this->assertSame(1, $cache->misses);
    }

    public function testDifferentIcaosMissIndependently(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/current-kjfk.json')
            ->queueFixture(self::FIXTURES . '/current-egll.json');
        $cache = new InMemoryCache();

        $config = WeatherConfig::default()->withCache($cache);
        $metar = new Metar($http, new RequestFactory(), $config, new ResponseFactory(), new StreamFactory());

        $metar->getCurrentWeatherByIcao('KJFK');
        $metar->getCurrentWeatherByIcao('EGLL');
        $metar->getCurrentWeatherByIcao('KJFK');   // cached
        $metar->getCurrentWeatherByIcao('EGLL');   // cached

        $this->assertSame(2, $http->getRequestCount());
        $this->assertSame(2, $cache->hits);
        $this->assertSame(2, $cache->misses);
    }

    public function testConfigWithoutCacheDoesNotDeduplicate(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/current-kjfk.json');

        // No cache. Should hit HTTP twice.
        $metar = new Metar($http, new RequestFactory(), WeatherConfig::default());

        $metar->getCurrentWeatherByIcao('KJFK');
        $metar->getCurrentWeatherByIcao('KJFK');

        $this->assertSame(2, $http->getRequestCount());
    }

    public function testStationLookupIsAlsoCached(): void
    {
        $http = (new MockHttpClient())
            ->queueFixture(self::FIXTURES . '/station-kjfk.json');
        $cache = new InMemoryCache();

        $config = WeatherConfig::default()->withCache($cache);
        $metar = new Metar($http, new RequestFactory(), $config, new ResponseFactory(), new StreamFactory());

        $metar->getStation('KJFK');
        $metar->getStation('KJFK');
        $metar->getStation('KJFK');

        $this->assertSame(1, $http->getRequestCount());
        $this->assertSame(2, $cache->hits);
    }
}
