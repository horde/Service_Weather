<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Exception;

use Horde\Service\Weather\Exception\StationNotFoundException;
use Horde\Service\Weather\WeatherException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(StationNotFoundException::class)]
class StationNotFoundExceptionTest extends TestCase
{
    public function testExtendsWeatherException(): void
    {
        $e = new StationNotFoundException('boom');
        $this->assertInstanceOf(WeatherException::class, $e);
    }

    public function testMessageAndCodePassThrough(): void
    {
        $prev = new RuntimeException('root');
        $e = new StationNotFoundException('KJFK not found', 42, $prev);

        $this->assertSame('KJFK not found', $e->getMessage());
        $this->assertSame(42, $e->getCode());
        $this->assertSame($prev, $e->getPrevious());
    }
}
