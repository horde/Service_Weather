<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Support;

use Horde\Http\Client\Mock;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

/**
 * Thin fluent wrapper over horde/http's PSR-18 Mock client. Adds
 * fixture-file loading and a `getLastHeaders()` helper on top of
 * upstream's request-recording surface (getRequests, getRequestCount,
 * getRequestedUrls, getRequest, clearRequests).
 *
 * Queue order matters. Register responses in the order the provider
 * will make the calls. When only one response remains, horde/http's
 * Mock returns it repeatedly for every subsequent request.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final class MockHttpClient extends Mock
{
    public function __construct(
        ?ResponseFactoryInterface $responseFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        parent::__construct(
            $responseFactory ?? new ResponseFactory(),
            $streamFactory ?? new StreamFactory(),
        );
    }

    /**
     * Queue a fixture read from disk.
     *
     * Empty files (aviationweather.gov's 0-byte response for invalid
     * ICAOs) are represented by an empty body.
     */
    public function queueFixture(string $path, int $status = 200, array $headers = []): self
    {
        $body = @file_get_contents($path);
        if ($body === false) {
            throw new RuntimeException("Fixture file not readable: $path");
        }
        $this->addResponse($body, $status, '', $headers);

        return $this;
    }

    /**
     * Queue a literal response body. Fluent variant of addResponse().
     */
    public function queue(string $body, int $status = 200, array $headers = []): self
    {
        $this->addResponse($body, $status, '', $headers);

        return $this;
    }

    /**
     * Return the headers of the most recent request as a flat
     * `[name => value]` map. Multi-value headers are joined with ", "
     * per HTTP convention. Returns an empty array when no requests
     * have been made yet.
     *
     * @return array<string, string>
     */
    public function getLastHeaders(): array
    {
        $requests = $this->getRequests();
        if ($requests === []) {
            return [];
        }
        $last = $requests[count($requests) - 1];
        $out = [];
        foreach ($last->getHeaders() as $name => $values) {
            $out[$name] = implode(', ', $values);
        }

        return $out;
    }
}
