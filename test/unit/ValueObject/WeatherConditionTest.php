<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

use Horde\Service\Weather\ValueObject\WeatherCondition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WeatherCondition::class)]
class WeatherConditionTest extends TestCase
{
    public function testGetDescriptionClear(): void
    {
        $this->assertSame('Clear', WeatherCondition::CLEAR->getDescription());
    }

    public function testGetDescriptionPartlyCloudy(): void
    {
        $this->assertSame('Partly Cloudy', WeatherCondition::PARTLY_CLOUDY->getDescription());
    }

    public function testGetDescriptionCloudy(): void
    {
        $this->assertSame('Cloudy', WeatherCondition::CLOUDY->getDescription());
    }

    public function testGetDescriptionRain(): void
    {
        $this->assertSame('Rain', WeatherCondition::RAIN->getDescription());
    }

    public function testGetDescriptionSnow(): void
    {
        $this->assertSame('Snow', WeatherCondition::SNOW->getDescription());
    }

    public function testGetDescriptionThunderstorm(): void
    {
        $this->assertSame('Thunderstorm', WeatherCondition::THUNDERSTORM->getDescription());
    }

    public function testGetIconCodeClear(): void
    {
        $this->assertSame('01', WeatherCondition::CLEAR->getIconCode());
    }

    public function testGetIconCodePartlyCloudy(): void
    {
        $this->assertSame('02', WeatherCondition::PARTLY_CLOUDY->getIconCode());
    }

    public function testGetIconCodeRain(): void
    {
        $this->assertSame('10', WeatherCondition::RAIN->getIconCode());
    }

    public function testGetIconCodeSnow(): void
    {
        $this->assertSame('13', WeatherCondition::SNOW->getIconCode());
    }

    public function testAllConditionsHaveDescriptions(): void
    {
        foreach (WeatherCondition::cases() as $condition) {
            $description = $condition->getDescription();
            $this->assertNotEmpty($description);
            $this->assertIsString($description);
        }
    }

    public function testAllConditionsHaveIconCodes(): void
    {
        foreach (WeatherCondition::cases() as $condition) {
            $iconCode = $condition->getIconCode();
            $this->assertNotEmpty($iconCode);
            $this->assertIsString($iconCode);
        }
    }

    public function testEnumValues(): void
    {
        $this->assertSame('clear', WeatherCondition::CLEAR->value);
        $this->assertSame('partly_cloudy', WeatherCondition::PARTLY_CLOUDY->value);
        $this->assertSame('cloudy', WeatherCondition::CLOUDY->value);
        $this->assertSame('rain', WeatherCondition::RAIN->value);
        $this->assertSame('snow', WeatherCondition::SNOW->value);
        $this->assertSame('unknown', WeatherCondition::UNKNOWN->value);
    }

    public function testGetIconReturnsKebabCaseSlug(): void
    {
        $this->assertSame('clear', WeatherCondition::CLEAR->getIcon());
        $this->assertSame('partly-cloudy', WeatherCondition::PARTLY_CLOUDY->getIcon());
        $this->assertSame('freezing-rain', WeatherCondition::FREEZING_RAIN->getIcon());
        $this->assertSame('thunderstorm', WeatherCondition::THUNDERSTORM->getIcon());
        $this->assertSame('unknown', WeatherCondition::UNKNOWN->getIcon());
    }

    public function testAllConditionsHaveIcons(): void
    {
        foreach (WeatherCondition::cases() as $condition) {
            $icon = $condition->getIcon();
            $this->assertNotEmpty($icon);
            $this->assertIsString($icon);
            $this->assertMatchesRegularExpression('/^[a-z-]+$/', $icon);
        }
    }
}
