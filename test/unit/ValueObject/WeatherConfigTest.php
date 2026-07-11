<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

use Horde\Service\Weather\Test\Support\NullCache;
use Horde\Service\Weather\ValueObject\Units;
use Horde\Service\Weather\ValueObject\WeatherConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WeatherConfig::class)]
class WeatherConfigTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        $config = WeatherConfig::default();
        $this->assertNull($config->apiKey);
        $this->assertSame(Units::METRIC, $config->units);
        $this->assertSame('en', $config->language);
        $this->assertSame(10, $config->timeout);
        $this->assertSame(1800, $config->cacheLifetime);
        $this->assertNull($config->cache);
    }

    public function testConstructWithAllParameters(): void
    {
        $config = new WeatherConfig(
            apiKey: 'test-key',
            units: Units::IMPERIAL,
            language: 'de',
            timeout: 20,
            cacheLifetime: 600
        );

        $this->assertSame('test-key', $config->apiKey);
        $this->assertSame(Units::IMPERIAL, $config->units);
        $this->assertSame('de', $config->language);
        $this->assertSame(20, $config->timeout);
        $this->assertSame(600, $config->cacheLifetime);
    }

    public function testWithApiKey(): void
    {
        $config = WeatherConfig::default();
        $newConfig = $config->withApiKey('my-api-key');

        $this->assertNull($config->apiKey);
        $this->assertSame('my-api-key', $newConfig->apiKey);
        $this->assertNotSame($config, $newConfig);
    }

    public function testWithUnits(): void
    {
        $config = WeatherConfig::default();
        $newConfig = $config->withUnits(Units::IMPERIAL);

        $this->assertSame(Units::METRIC, $config->units);
        $this->assertSame(Units::IMPERIAL, $newConfig->units);
    }

    public function testWithLanguage(): void
    {
        $config = WeatherConfig::default();
        $newConfig = $config->withLanguage('fr');

        $this->assertSame('en', $config->language);
        $this->assertSame('fr', $newConfig->language);
    }

    public function testWithTimeout(): void
    {
        $config = WeatherConfig::default();
        $newConfig = $config->withTimeout(30);

        $this->assertSame(10, $config->timeout);
        $this->assertSame(30, $newConfig->timeout);
    }

    public function testWithCacheLifetime(): void
    {
        $config = WeatherConfig::default();
        $newConfig = $config->withCacheLifetime(900);

        $this->assertSame(1800, $config->cacheLifetime);
        $this->assertSame(900, $newConfig->cacheLifetime);
    }

    public function testFluentInterface(): void
    {
        $config = WeatherConfig::default()
            ->withApiKey('test-key')
            ->withUnits(Units::IMPERIAL)
            ->withLanguage('es')
            ->withTimeout(15)
            ->withCacheLifetime(900);

        $this->assertSame('test-key', $config->apiKey);
        $this->assertSame(Units::IMPERIAL, $config->units);
        $this->assertSame('es', $config->language);
        $this->assertSame(15, $config->timeout);
        $this->assertSame(900, $config->cacheLifetime);
    }

    public function testImmutability(): void
    {
        $config1 = WeatherConfig::default();
        $config2 = $config1->withApiKey('key1');
        $config3 = $config1->withApiKey('key2');

        $this->assertNull($config1->apiKey);
        $this->assertSame('key1', $config2->apiKey);
        $this->assertSame('key2', $config3->apiKey);
    }

    public function testWithCacheAttachesInstance(): void
    {
        $cache = new NullCache();
        $config = WeatherConfig::default()->withCache($cache);

        $this->assertSame($cache, $config->cache);
    }

    public function testWithCacheAcceptsNullToDetach(): void
    {
        $cache = new NullCache();
        $withCache = WeatherConfig::default()->withCache($cache);
        $detached = $withCache->withCache(null);

        $this->assertSame($cache, $withCache->cache);
        $this->assertNull($detached->cache);
    }

    /**
     * The critical passthrough test: any withX() call must preserve every
     * OTHER slot, including the (post-Wave-1-refactor) `cache` field. Uses
     * a distinct pre-populated config so a dropped slot surfaces as a
     * default value on the returned instance.
     */
    public function testEveryWitherPreservesAllOtherSlots(): void
    {
        $cache = new NullCache();
        $base = new WeatherConfig(
            apiKey: 'K',
            units: Units::IMPERIAL,
            language: 'de',
            timeout: 42,
            cacheLifetime: 999,
            cache: $cache,
            userAgent: 'my-app/1.0',
        );

        $variants = [
            'withApiKey' => $base->withApiKey('K2'),
            'withUnits' => $base->withUnits(Units::STANDARD),
            'withLanguage' => $base->withLanguage('es'),
            'withTimeout' => $base->withTimeout(99),
            'withCacheLifetime' => $base->withCacheLifetime(60),
            'withCache' => $base->withCache(new NullCache()),
            'withUserAgent' => $base->withUserAgent('other-app/2.0'),
        ];

        foreach ($variants as $method => $config) {
            // Only the wither's own slot is expected to differ from $base;
            // every other slot must match. Check them in a way that surfaces
            // WHICH slot was dropped when a passthrough breaks.
            if ($method !== 'withApiKey') {
                $this->assertSame('K', $config->apiKey, "$method dropped apiKey");
            }
            if ($method !== 'withUnits') {
                $this->assertSame(Units::IMPERIAL, $config->units, "$method dropped units");
            }
            if ($method !== 'withLanguage') {
                $this->assertSame('de', $config->language, "$method dropped language");
            }
            if ($method !== 'withTimeout') {
                $this->assertSame(42, $config->timeout, "$method dropped timeout");
            }
            if ($method !== 'withCacheLifetime') {
                $this->assertSame(999, $config->cacheLifetime, "$method dropped cacheLifetime");
            }
            if ($method !== 'withCache') {
                $this->assertSame($cache, $config->cache, "$method dropped cache");
            }
            if ($method !== 'withUserAgent') {
                $this->assertSame('my-app/1.0', $config->userAgent, "$method dropped userAgent");
            }
        }
    }

    public function testUserAgentDefaultsToNull(): void
    {
        $config = WeatherConfig::default();
        $this->assertNull($config->userAgent);
    }

    public function testWithUserAgentSetsAndDetaches(): void
    {
        $withUa = WeatherConfig::default()->withUserAgent('my-app/1.0');
        $this->assertSame('my-app/1.0', $withUa->userAgent);

        $detached = $withUa->withUserAgent(null);
        $this->assertNull($detached->userAgent);
    }
}
