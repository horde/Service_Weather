<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

use Horde\Service\Weather\ValueObject\ForecastDetail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ForecastDetail::class)]
class ForecastDetailTest extends TestCase
{
    public function testAllCasesHaveStringValues(): void
    {
        $this->assertSame('daily', ForecastDetail::DAILY->value);
        $this->assertSame('detailed', ForecastDetail::DETAILED->value);
    }
}
