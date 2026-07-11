<?php

declare(strict_types=1);

namespace Horde\Service\Weather;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * PSR-18 client decorator that caches successful GET responses.
 *
 * Wraps any inner PSR-18 client. On each request, computes a cache
 * key from the effective URI, checks the PSR-16 cache and rebuilds
 * a fresh PSR-7 response from the cached tuple on hit. On miss,
 * delegates to the inner client; when the response is a successful
 * 2xx, stores the tuple (status, headers, body-as-string) and
 * returns the original response.
 *
 * Non-2xx responses and thrown exceptions are NEVER cached.
 * Transient upstream problems must not extend into the TTL window.
 *
 * PSR-7 response bodies are one-shot streams, so we cannot cache
 * `ResponseInterface` instances directly. The cached tuple lets us
 * reconstitute an equivalent response on every hit.
 *
 * Cache-key format: `weather.` + sha1(URI). URI alone is the cache
 * discriminator; request headers (User-Agent etc.) are treated as
 * response-invariant, which matches how weather APIs behave.
 *
 * Only GET requests are cached. Non-GET methods delegate through
 * without cache interaction.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final class CachingHttpClient implements ClientInterface
{
    public function __construct(
        private readonly ClientInterface $inner,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly CacheInterface $cache,
        private readonly int $ttl,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() !== 'GET') {
            return $this->inner->sendRequest($request);
        }

        $key = self::keyFor((string) $request->getUri());

        $hit = $this->cache->get($key);
        if (is_array($hit) && isset($hit['status'], $hit['body'])) {
            return $this->reconstitute($hit);
        }

        $response = $this->inner->sendRequest($request);

        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            $this->cache->set($key, $this->freeze($response), $this->ttl);
        }

        return $response;
    }

    /**
     * Compute the PSR-16 cache key for a given URI string.
     *
     * Exposed as a static so integration tests can look up entries
     * without duplicating the derivation logic.
     */
    public static function keyFor(string $uri): string
    {
        return 'weather.' . sha1($uri);
    }

    /**
     * Serialize a response for caching. Reads the body eagerly so the
     * cached tuple is self-contained.
     *
     * @return array{status: int, headers: array<string, array<int, string>>, body: string}
     */
    private function freeze(ResponseInterface $response): array
    {
        return [
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => (string) $response->getBody(),
        ];
    }

    /**
     * Rebuild a fresh PSR-7 response from a frozen tuple. Called on every
     * cache hit so consumers get an unread body they can stream.
     *
     * @param array{status: int, headers: array<string, array<int, string>>, body: string} $frozen
     */
    private function reconstitute(array $frozen): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($frozen['status'])
            ->withBody($this->streamFactory->createStream($frozen['body']));

        foreach ($frozen['headers'] as $name => $values) {
            foreach ($values as $value) {
                $response = $response->withAddedHeader($name, $value);
            }
        }

        return $response;
    }
}
