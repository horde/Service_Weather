<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

use Horde\Service\Weather\ValueObject\Units;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Units::class)]
class UnitsTest extends TestCase
{
    public function testAllCasesHaveStringValues(): void
    {
        $this->assertSame('standard', Units::STANDARD->value);
        $this->assertSame('metric', Units::METRIC->value);
        $this->assertSame('imperial', Units::IMPERIAL->value);
    }

    public function testMetricLabels(): void
    {
        $labels = Units::METRIC->getLabels();
        $this->assertSame('°C', $labels['temp']);
        $this->assertSame('km/h', $labels['wind']);
        $this->assertSame('mb', $labels['pressure']);
        $this->assertSame('km', $labels['visibility']);
        $this->assertSame('mm', $labels['precipitation']);
    }

    public function testImperialLabels(): void
    {
        $labels = Units::IMPERIAL->getLabels();
        $this->assertSame('°F', $labels['temp']);
        $this->assertSame('mph', $labels['wind']);
        $this->assertSame('inHg', $labels['pressure']);
        $this->assertSame('mi', $labels['visibility']);
        $this->assertSame('in', $labels['precipitation']);
    }

    public function testStandardLabels(): void
    {
        $labels = Units::STANDARD->getLabels();
        $this->assertSame('K', $labels['temp']);
        $this->assertSame('m/s', $labels['wind']);
        $this->assertSame('hPa', $labels['pressure']);
        $this->assertSame('km', $labels['visibility']);
        $this->assertSame('mm', $labels['precipitation']);
    }

    public function testEveryCaseReturnsCompleteLabelSet(): void
    {
        $keys = ['temp', 'wind', 'pressure', 'visibility', 'precipitation'];
        foreach (Units::cases() as $unit) {
            $labels = $unit->getLabels();
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $labels, "Units::{$unit->name} missing '$key' label");
                $this->assertIsString($labels[$key]);
                $this->assertNotEmpty($labels[$key]);
            }
        }
    }
}
