<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test;

use Horde\Http\RequestFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde\Service\Weather\CachingHttpClient;
use Horde\Service\Weather\Test\Support\InMemoryCache;
use Horde\Service\Weather\Test\Support\MockHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CachingHttpClient::class)]
class CachingHttpClientTest extends TestCase
{
    public function testKeyForIsStableAndPsr16Safe(): void
    {
        $key = CachingHttpClient::keyFor('https://example.com/foo?a=1&b=2');
        $this->assertSame(CachingHttpClient::keyFor('https://example.com/foo?a=1&b=2'), $key);
        // PSR-16 allows [A-Za-z0-9_.]. Our key is `weather.` + 40-char sha1.
        $this->assertMatchesRegularExpression('/^weather\.[a-f0-9]{40}$/', $key);
        $this->assertLessThanOrEqual(64, strlen($key));
    }

    public function testMissDelegatesAndStores(): void
    {
        $inner = (new MockHttpClient())->queue('BODY', 200);
        $cache = new InMemoryCache();
        $c = $this->makeDecorator($inner, $cache);

        $response = $c->sendRequest((new RequestFactory())->createRequest('GET', 'https://example.com/foo'));

        $this->assertSame('BODY', (string) $response->getBody());
        $this->assertSame(1, $inner->getRequestCount());
        $this->assertTrue($cache->has(CachingHttpClient::keyFor('https://example.com/foo')));
    }

    public function testHitSkipsInnerClient(): void
    {
        $inner = (new MockHttpClient())->queue('BODY', 200);
        $cache = new InMemoryCache();
        $c = $this->makeDecorator($inner, $cache);
        $rf = new RequestFactory();

        $r1 = $c->sendRequest($rf->createRequest('GET', 'https://example.com/foo'));  // miss
        $r2 = $c->sendRequest($rf->createRequest('GET', 'https://example.com/foo'));  // hit

        // Inner client saw only ONE request across two calls.
        $this->assertSame(1, $inner->getRequestCount());
        $this->assertSame(1, $cache->hits);
        // The body from the cached response is fully re-readable.
        $this->assertSame('BODY', (string) $r1->getBody());
        $this->assertSame('BODY', (string) $r2->getBody());
    }

    public function testNon2xxIsNotCached(): void
    {
        $inner = (new MockHttpClient())->queue('{"error":"too many"}', 429);
        $cache = new InMemoryCache();
        $c = $this->makeDecorator($inner, $cache);
        $rf = new RequestFactory();

        $response = $c->sendRequest($rf->createRequest('GET', 'https://example.com/rate'));
        $this->assertSame(429, $response->getStatusCode());
        $this->assertFalse($cache->has(CachingHttpClient::keyFor('https://example.com/rate')));

        // Queue a second 429 for the follow-up call; without cache, it re-hits inner.
        $inner->queue('{"error":"still too many"}', 429);
        $c->sendRequest($rf->createRequest('GET', 'https://example.com/rate'));
        $this->assertSame(2, $inner->getRequestCount());
    }

    public function testNonGetRequestsBypassCache(): void
    {
        $inner = (new MockHttpClient())->queue('OK', 200);
        $cache = new InMemoryCache();
        $c = $this->makeDecorator($inner, $cache);
        $rf = new RequestFactory();

        $c->sendRequest($rf->createRequest('POST', 'https://example.com/thing'));

        $this->assertSame(1, $inner->getRequestCount());
        $this->assertSame(0, $cache->totalGets());  // no lookup for POST
    }

    public function testDifferentUrlsAreCachedIndependently(): void
    {
        $inner = (new MockHttpClient())
            ->queue('AAA', 200)
            ->queue('BBB', 200);
        $cache = new InMemoryCache();
        $c = $this->makeDecorator($inner, $cache);
        $rf = new RequestFactory();

        $c->sendRequest($rf->createRequest('GET', 'https://example.com/a'));
        $c->sendRequest($rf->createRequest('GET', 'https://example.com/b'));
        // Both should now be cache hits.
        $ra = $c->sendRequest($rf->createRequest('GET', 'https://example.com/a'));
        $rb = $c->sendRequest($rf->createRequest('GET', 'https://example.com/b'));

        $this->assertSame(2, $inner->getRequestCount());
        $this->assertSame('AAA', (string) $ra->getBody());
        $this->assertSame('BBB', (string) $rb->getBody());
    }

    private function makeDecorator(MockHttpClient $inner, InMemoryCache $cache): CachingHttpClient
    {
        return new CachingHttpClient(
            $inner,
            new ResponseFactory(),
            new StreamFactory(),
            $cache,
            60,
        );
    }
}
