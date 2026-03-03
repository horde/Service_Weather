<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Exception;

use Horde\Service\Weather\Exception\ApiException;
use Horde\Service\Weather\WeatherException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ApiException::class)]
class ApiExceptionTest extends TestCase
{
    public function testExtendsWeatherException(): void
    {
        $exception = new ApiException('Test message');
        $this->assertInstanceOf(WeatherException::class, $exception);
    }

    public function testExtendsRuntimeException(): void
    {
        $exception = new ApiException('Test message');
        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function testMessage(): void
    {
        $exception = new ApiException('API request failed');
        $this->assertSame('API request failed', $exception->getMessage());
    }

    public function testWithPrevious(): void
    {
        $previous = new RuntimeException('Previous error');
        $exception = new ApiException('API error', 0, $previous);

        $this->assertSame('API error', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
