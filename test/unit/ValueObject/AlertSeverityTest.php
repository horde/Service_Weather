<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

use Horde\Service\Weather\ValueObject\AlertSeverity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlertSeverity::class)]
class AlertSeverityTest extends TestCase
{
    public function testAllCasesHaveStringValues(): void
    {
        $this->assertSame('minor', AlertSeverity::MINOR->value);
        $this->assertSame('moderate', AlertSeverity::MODERATE->value);
        $this->assertSame('severe', AlertSeverity::SEVERE->value);
        $this->assertSame('extreme', AlertSeverity::EXTREME->value);
        $this->assertSame('unknown', AlertSeverity::UNKNOWN->value);
    }

    public function testFromStringMatchesEachCase(): void
    {
        $this->assertSame(AlertSeverity::MINOR, AlertSeverity::fromString('Minor'));
        $this->assertSame(AlertSeverity::MODERATE, AlertSeverity::fromString('Moderate'));
        $this->assertSame(AlertSeverity::SEVERE, AlertSeverity::fromString('Severe'));
        $this->assertSame(AlertSeverity::EXTREME, AlertSeverity::fromString('Extreme'));
    }

    public function testFromStringIsCaseInsensitiveAndTrims(): void
    {
        $this->assertSame(AlertSeverity::SEVERE, AlertSeverity::fromString('SEVERE'));
        $this->assertSame(AlertSeverity::SEVERE, AlertSeverity::fromString('  severe  '));
    }

    public function testFromStringFallsBackToUnknownOnUnrecognized(): void
    {
        $this->assertSame(AlertSeverity::UNKNOWN, AlertSeverity::fromString(''));
        $this->assertSame(AlertSeverity::UNKNOWN, AlertSeverity::fromString('urgent'));
        $this->assertSame(AlertSeverity::UNKNOWN, AlertSeverity::fromString('nonsense'));
    }
}
