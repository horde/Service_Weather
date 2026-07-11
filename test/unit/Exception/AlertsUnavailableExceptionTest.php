<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Exception;

use Horde\Service\Weather\Exception\AlertsUnavailableException;
use Horde\Service\Weather\WeatherException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlertsUnavailableException::class)]
class AlertsUnavailableExceptionTest extends TestCase
{
    public function testExtendsWeatherException(): void
    {
        $e = new AlertsUnavailableException('boom');
        $this->assertInstanceOf(WeatherException::class, $e);
    }
}
