<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

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
}
