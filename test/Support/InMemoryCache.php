<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Support;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * Assoc-array-backed PSR-16 cache for tests.
 *
 * Unlike NullCache, this actually stores values across get/set calls
 * within a single instance's lifetime. TTLs are ignored. Tests that
 * need expiry behavior should stub differently.
 *
 * Records hit and miss counts for assertions like "the second identical
 * call didn't hit the network."
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final class InMemoryCache implements CacheInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $store = [];

    public int $hits = 0;
    public int $misses = 0;

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->store)) {
            ++$this->hits;

            return $this->store[$key];
        }
        ++$this->misses;

        return $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->store[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->store = [];
        $this->hits = 0;
        $this->misses = 0;

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->get($key, $default);
        }

        return $out;
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $k => $v) {
            $this->set((string) $k, $v, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }

    /**
     * Test helper. Total request count (hits + misses).
     */
    public function totalGets(): int
    {
        return $this->hits + $this->misses;
    }
}
