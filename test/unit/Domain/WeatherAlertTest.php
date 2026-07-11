<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\Domain;

use DateTimeImmutable;
use Horde\Service\Weather\Domain\WeatherAlert;
use Horde\Service\Weather\ValueObject\AlertSeverity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WeatherAlert::class)]
class WeatherAlertTest extends TestCase
{
    public function testMinimalConstruction(): void
    {
        $effective = new DateTimeImmutable('2026-07-09 10:00:00 UTC');
        $expires = new DateTimeImmutable('2026-07-09 20:00:00 UTC');

        $alert = new WeatherAlert(
            identifier: 'urn:oid:2.49.0.1.840.0.abc',
            headline: 'Severe Thunderstorm Watch',
            description: 'Severe thunderstorms possible through evening.',
            severity: AlertSeverity::SEVERE,
            effective: $effective,
            expires: $expires,
        );

        $this->assertSame('urn:oid:2.49.0.1.840.0.abc', $alert->identifier);
        $this->assertSame('urn:oid:2.49.0.1.840.0.abc', $alert->getIdentifier());
        $this->assertSame('Severe Thunderstorm Watch', $alert->headline);
        $this->assertSame('Severe Thunderstorm Watch', $alert->getHeadline());
        $this->assertSame('Severe thunderstorms possible through evening.', $alert->getDescription());
        $this->assertSame(AlertSeverity::SEVERE, $alert->getSeverity());
        $this->assertSame($effective, $alert->getEffective());
        $this->assertSame($expires, $alert->getExpires());
        $this->assertNull($alert->getAreaDescription());
        $this->assertNull($alert->getSenderName());
    }

    public function testConstructionWithAllFields(): void
    {
        $alert = new WeatherAlert(
            identifier: 'id1',
            headline: 'H',
            description: 'D',
            severity: AlertSeverity::EXTREME,
            effective: new DateTimeImmutable('2026-07-09 10:00:00 UTC'),
            expires: new DateTimeImmutable('2026-07-09 20:00:00 UTC'),
            areaDescription: 'Bronx, NY; New York, NY',
            senderName: 'NWS New York NY',
        );

        $this->assertSame('Bronx, NY; New York, NY', $alert->getAreaDescription());
        $this->assertSame('NWS New York NY', $alert->getSenderName());
    }

    public function testIsActiveInsideWindow(): void
    {
        $alert = new WeatherAlert(
            identifier: 'a',
            headline: 'h',
            description: 'd',
            severity: AlertSeverity::MODERATE,
            effective: new DateTimeImmutable('2026-07-09 10:00:00 UTC'),
            expires: new DateTimeImmutable('2026-07-09 20:00:00 UTC'),
        );

        $inside = new DateTimeImmutable('2026-07-09 15:00:00 UTC');
        $this->assertTrue($alert->isActive($inside));
    }

    public function testIsActiveBeforeWindow(): void
    {
        $alert = new WeatherAlert(
            identifier: 'a',
            headline: 'h',
            description: 'd',
            severity: AlertSeverity::MODERATE,
            effective: new DateTimeImmutable('2026-07-09 10:00:00 UTC'),
            expires: new DateTimeImmutable('2026-07-09 20:00:00 UTC'),
        );

        $before = new DateTimeImmutable('2026-07-09 09:59:00 UTC');
        $this->assertFalse($alert->isActive($before));
    }

    public function testIsActiveAfterWindow(): void
    {
        $alert = new WeatherAlert(
            identifier: 'a',
            headline: 'h',
            description: 'd',
            severity: AlertSeverity::MODERATE,
            effective: new DateTimeImmutable('2026-07-09 10:00:00 UTC'),
            expires: new DateTimeImmutable('2026-07-09 20:00:00 UTC'),
        );

        $after = new DateTimeImmutable('2026-07-09 20:00:01 UTC');
        $this->assertFalse($alert->isActive($after));
    }

    public function testIsActiveOnBoundaryIsInclusive(): void
    {
        $effective = new DateTimeImmutable('2026-07-09 10:00:00 UTC');
        $expires = new DateTimeImmutable('2026-07-09 20:00:00 UTC');

        $alert = new WeatherAlert(
            identifier: 'a',
            headline: 'h',
            description: 'd',
            severity: AlertSeverity::MINOR,
            effective: $effective,
            expires: $expires,
        );

        $this->assertTrue($alert->isActive($effective));
        $this->assertTrue($alert->isActive($expires));
    }
}
