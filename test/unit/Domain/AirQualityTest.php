<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Domain;

use DateTimeImmutable;
use Horde\Service\Weather\Domain\AirQuality;
use Horde\Service\Weather\ValueObject\AirQualityCategory;
use Horde\Service\Weather\ValueObject\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AirQuality::class)]
class AirQualityTest extends TestCase
{
    public function testMinimalConstruction(): void
    {
        $t = new DateTimeImmutable('2026-07-09 12:00:00 UTC');
        $aq = new AirQuality(
            location: Location::fromCoordinates(52.5, 13.4),
            observationTime: $t,
        );

        $this->assertSame($t, $aq->observationTime);
        $this->assertNull($aq->pm25);
        $this->assertNull($aq->pm10);
        $this->assertNull($aq->ozone);
        $this->assertNull($aq->no2);
        $this->assertNull($aq->so2);
        $this->assertNull($aq->co);
        $this->assertNull($aq->usAqi);
        $this->assertNull($aq->europeanAqi);
        $this->assertNull($aq->ukDaqi);
        $this->assertNull($aq->category);
        $this->assertNull($aq->getCategory());
    }

    public function testGetCategoryReturnsExplicitValueWhenSet(): void
    {
        $aq = new AirQuality(
            location: Location::fromCoordinates(0, 0),
            observationTime: new DateTimeImmutable(),
            usAqi: 500,
            category: AirQualityCategory::GOOD, // deliberately inconsistent with usAqi
        );

        // Explicit constructor value wins over derivation.
        $this->assertSame(AirQualityCategory::GOOD, $aq->getCategory());
    }

    public function testGetCategoryDerivesFromUsAqiWhenNotSet(): void
    {
        $aq = new AirQuality(
            location: Location::fromCoordinates(0, 0),
            observationTime: new DateTimeImmutable(),
            usAqi: 155,
        );

        $this->assertSame(AirQualityCategory::UNHEALTHY, $aq->getCategory());
    }

    public function testGetCategoryNullWhenNeitherCategoryNorUsAqiSet(): void
    {
        $aq = new AirQuality(
            location: Location::fromCoordinates(0, 0),
            observationTime: new DateTimeImmutable(),
            pm25: 20.0,
            europeanAqi: 3,
        );

        $this->assertNull($aq->getCategory());
    }

    public function testGettersMirrorPromotedProperties(): void
    {
        $t = new DateTimeImmutable('2026-07-09 12:00:00 UTC');
        $aq = new AirQuality(
            location: Location::fromCoordinates(52.5, 13.4),
            observationTime: $t,
            pm25: 12.5,
            pm10: 30.0,
            ozone: 60.5,
            no2: 8.0,
            so2: 2.5,
            co: 200.0,
            usAqi: 55,
            europeanAqi: 3,
            ukDaqi: 4,
        );

        $this->assertSame(12.5, $aq->getPm25());
        $this->assertSame(30.0, $aq->getPm10());
        $this->assertSame(60.5, $aq->getOzone());
        $this->assertSame(8.0, $aq->getNo2());
        $this->assertSame(2.5, $aq->getSo2());
        $this->assertSame(200.0, $aq->getCo());
        $this->assertSame(55, $aq->getUsAqi());
        $this->assertSame(3, $aq->getEuropeanAqi());
        $this->assertSame(4, $aq->getUkDaqi());
    }
}
