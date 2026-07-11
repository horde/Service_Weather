<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Domain;

use DateTimeImmutable;
use Horde\Service\Weather\ValueObject\AlertSeverity;

/**
 * Weather alert / warning / advisory.
 *
 * Returned by AlertProvider implementations. Field shape aligns
 * with the CAP (Common Alerting Protocol) subset that NWS, WeatherAPI,
 * and OpenWeatherMap all agree on.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final readonly class WeatherAlert
{
    /**
     * @param string $identifier Provider-assigned alert id (used for dedup).
     * @param string $headline Short human-readable summary.
     * @param string $description Full narrative text.
     * @param AlertSeverity $severity CAP severity classification.
     * @param DateTimeImmutable $effective When the alert becomes active.
     * @param DateTimeImmutable $expires When the alert ceases to be active.
     * @param ?string $areaDescription Free-text list of affected areas.
     * @param ?string $senderName Issuing office / authority.
     */
    public function __construct(
        public string $identifier,
        public string $headline,
        public string $description,
        public AlertSeverity $severity,
        public DateTimeImmutable $effective,
        public DateTimeImmutable $expires,
        public ?string $areaDescription = null,
        public ?string $senderName = null
    ) {}

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getHeadline(): string
    {
        return $this->headline;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getSeverity(): AlertSeverity
    {
        return $this->severity;
    }

    public function getEffective(): DateTimeImmutable
    {
        return $this->effective;
    }

    public function getExpires(): DateTimeImmutable
    {
        return $this->expires;
    }

    public function getAreaDescription(): ?string
    {
        return $this->areaDescription;
    }

    public function getSenderName(): ?string
    {
        return $this->senderName;
    }

    /**
     * True when now() is within [effective, expires].
     */
    public function isActive(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();

        return $now >= $this->effective && $now <= $this->expires;
    }
}
