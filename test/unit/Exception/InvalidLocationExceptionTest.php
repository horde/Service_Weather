<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Exception;

use Horde\Service\Weather\Exception\InvalidLocationException;
use Horde\Service\Weather\WeatherException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(InvalidLocationException::class)]
class InvalidLocationExceptionTest extends TestCase
{
    public function testExtendsWeatherException(): void
    {
        $exception = new InvalidLocationException('Test message');
        $this->assertInstanceOf(WeatherException::class, $exception);
    }

    public function testExtendsRuntimeException(): void
    {
        $exception = new InvalidLocationException('Test message');
        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function testMessage(): void
    {
        $exception = new InvalidLocationException('Invalid coordinates');
        $this->assertSame('Invalid coordinates', $exception->getMessage());
    }
}
