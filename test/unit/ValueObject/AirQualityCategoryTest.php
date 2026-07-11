<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

use Horde\Service\Weather\ValueObject\AirQualityCategory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AirQualityCategory::class)]
class AirQualityCategoryTest extends TestCase
{
    public function testAllCasesHaveStringValues(): void
    {
        $this->assertSame('good', AirQualityCategory::GOOD->value);
        $this->assertSame('moderate', AirQualityCategory::MODERATE->value);
        $this->assertSame('unhealthy_sensitive', AirQualityCategory::UNHEALTHY_FOR_SENSITIVE->value);
        $this->assertSame('unhealthy', AirQualityCategory::UNHEALTHY->value);
        $this->assertSame('very_unhealthy', AirQualityCategory::VERY_UNHEALTHY->value);
        $this->assertSame('hazardous', AirQualityCategory::HAZARDOUS->value);
        $this->assertSame('unknown', AirQualityCategory::UNKNOWN->value);
    }

    public function testFromUsAqiBands(): void
    {
        $this->assertSame(AirQualityCategory::GOOD, AirQualityCategory::fromUsAqi(0));
        $this->assertSame(AirQualityCategory::GOOD, AirQualityCategory::fromUsAqi(50));
        $this->assertSame(AirQualityCategory::MODERATE, AirQualityCategory::fromUsAqi(51));
        $this->assertSame(AirQualityCategory::MODERATE, AirQualityCategory::fromUsAqi(100));
        $this->assertSame(AirQualityCategory::UNHEALTHY_FOR_SENSITIVE, AirQualityCategory::fromUsAqi(101));
        $this->assertSame(AirQualityCategory::UNHEALTHY_FOR_SENSITIVE, AirQualityCategory::fromUsAqi(150));
        $this->assertSame(AirQualityCategory::UNHEALTHY, AirQualityCategory::fromUsAqi(151));
        $this->assertSame(AirQualityCategory::UNHEALTHY, AirQualityCategory::fromUsAqi(200));
        $this->assertSame(AirQualityCategory::VERY_UNHEALTHY, AirQualityCategory::fromUsAqi(201));
        $this->assertSame(AirQualityCategory::VERY_UNHEALTHY, AirQualityCategory::fromUsAqi(300));
        $this->assertSame(AirQualityCategory::HAZARDOUS, AirQualityCategory::fromUsAqi(301));
        $this->assertSame(AirQualityCategory::HAZARDOUS, AirQualityCategory::fromUsAqi(500));
    }
}
