<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

use Psr\SimpleCache\CacheInterface;

/**
 * Weather service configuration.
 *
 * Immutable configuration for weather providers.
 *
 * All wither methods use named arguments internally so future slot
 * additions are one-line changes and cannot silently drop unrelated
 * fields.
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
        public int $cacheLifetime = 1800,
        public ?CacheInterface $cache = null,
        public ?string $userAgent = null
    ) {}

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
        return new self(
            apiKey: $apiKey,
            units: $this->units,
            language: $this->language,
            timeout: $this->timeout,
            cacheLifetime: $this->cacheLifetime,
            cache: $this->cache,
            userAgent: $this->userAgent,
        );
    }

    /**
     * Set measurement units.
     */
    public function withUnits(Units $units): self
    {
        return new self(
            apiKey: $this->apiKey,
            units: $units,
            language: $this->language,
            timeout: $this->timeout,
            cacheLifetime: $this->cacheLifetime,
            cache: $this->cache,
            userAgent: $this->userAgent,
        );
    }

    /**
     * Set language code.
     */
    public function withLanguage(string $language): self
    {
        return new self(
            apiKey: $this->apiKey,
            units: $this->units,
            language: $language,
            timeout: $this->timeout,
            cacheLifetime: $this->cacheLifetime,
            cache: $this->cache,
            userAgent: $this->userAgent,
        );
    }

    /**
     * Set HTTP timeout in seconds.
     */
    public function withTimeout(int $timeout): self
    {
        return new self(
            apiKey: $this->apiKey,
            units: $this->units,
            language: $this->language,
            timeout: $timeout,
            cacheLifetime: $this->cacheLifetime,
            cache: $this->cache,
            userAgent: $this->userAgent,
        );
    }

    /**
     * Set cache lifetime in seconds.
     */
    public function withCacheLifetime(int $lifetime): self
    {
        return new self(
            apiKey: $this->apiKey,
            units: $this->units,
            language: $this->language,
            timeout: $this->timeout,
            cacheLifetime: $lifetime,
            cache: $this->cache,
            userAgent: $this->userAgent,
        );
    }

    /**
     * Attach (or detach with null) a PSR-16 cache implementation.
     *
     * Providers wire their HTTP responses through this cache when set,
     * keyed by endpoint + params, with cacheLifetime as the TTL.
     */
    public function withCache(?CacheInterface $cache): self
    {
        return new self(
            apiKey: $this->apiKey,
            units: $this->units,
            language: $this->language,
            timeout: $this->timeout,
            cacheLifetime: $this->cacheLifetime,
            cache: $cache,
            userAgent: $this->userAgent,
        );
    }

    /**
     * Override the User-Agent sent on outgoing HTTP requests.
     *
     * Some providers (NWS, aviationweather.gov) require or strongly
     * request a User-Agent that identifies the calling application.
     * Providers fall back to a per-provider default when this is null.
     */
    public function withUserAgent(?string $userAgent): self
    {
        return new self(
            apiKey: $this->apiKey,
            units: $this->units,
            language: $this->language,
            timeout: $this->timeout,
            cacheLifetime: $this->cacheLifetime,
            cache: $this->cache,
            userAgent: $userAgent,
        );
    }
}
