<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

/**
 * Weather service configuration.
 *
 * Immutable configuration for weather providers.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final readonly class WeatherConfig
{
    public function __construct(
        public ?string $apiKey = null,
        public Units $units = Units::METRIC,
        public string $language = 'en',
        public int $timeout = 10,
        public int $cacheLifetime = 1800
    ) {
    }

    /**
     * Create default configuration.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Set API key.
     */
    public function withApiKey(string $apiKey): self
    {
        return new self($apiKey, $this->units, $this->language, $this->timeout, $this->cacheLifetime);
    }

    /**
     * Set measurement units.
     */
    public function withUnits(Units $units): self
    {
        return new self($this->apiKey, $units, $this->language, $this->timeout, $this->cacheLifetime);
    }

    /**
     * Set language code.
     */
    public function withLanguage(string $language): self
    {
        return new self($this->apiKey, $this->units, $language, $this->timeout, $this->cacheLifetime);
    }

    /**
     * Set HTTP timeout in seconds.
     */
    public function withTimeout(int $timeout): self
    {
        return new self($this->apiKey, $this->units, $this->language, $timeout, $this->cacheLifetime);
    }

    /**
     * Set cache lifetime in seconds.
     */
    public function withCacheLifetime(int $lifetime): self
    {
        return new self($this->apiKey, $this->units, $this->language, $this->timeout, $lifetime);
    }
}
