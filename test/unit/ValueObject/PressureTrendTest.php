<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

use Horde\Service\Weather\ValueObject\PressureTrend;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PressureTrend::class)]
class PressureTrendTest extends TestCase
{
    public function testAllCasesHaveStringValues(): void
    {
        $this->assertSame('rising', PressureTrend::RISING->value);
        $this->assertSame('falling', PressureTrend::FALLING->value);
        $this->assertSame('steady', PressureTrend::STEADY->value);
    }
}
