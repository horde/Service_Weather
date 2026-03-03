<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

use Horde\Service\Weather\Exception\InvalidLocationException;
use Horde\Service\Weather\ValueObject\Humidity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Humidity::class)]
class HumidityTest extends TestCase
{
    public function testFromPercentage(): void
    {
        $humidity = Humidity::fromPercentage(75);
        $this->assertSame(75, $humidity->percentage);
    }

    public function testFormat(): void
    {
        $humidity = Humidity::fromPercentage(65);
        $this->assertSame('65%', $humidity->format());
    }

    public function testZeroPercentage(): void
    {
        $humidity = Humidity::fromPercentage(0);
        $this->assertSame(0, $humidity->percentage);
        $this->assertSame('0%', $humidity->format());
    }

    public function testHundredPercentage(): void
    {
        $humidity = Humidity::fromPercentage(100);
        $this->assertSame(100, $humidity->percentage);
        $this->assertSame('100%', $humidity->format());
    }

    public function testInvalidNegativePercentage(): void
    {
        $this->expectException(InvalidLocationException::class);
        $this->expectExceptionMessage('Humidity must be between 0 and 100');
        Humidity::fromPercentage(-1);
    }

    public function testInvalidTooHighPercentage(): void
    {
        $this->expectException(InvalidLocationException::class);
        $this->expectExceptionMessage('Humidity must be between 0 and 100');
        Humidity::fromPercentage(101);
    }
}
