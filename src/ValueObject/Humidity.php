<?php

declare(strict_types=1);

namespace Horde\Service\Weather\ValueObject;

use Horde\Service\Weather\Exception\InvalidLocationException;

/**
 * Relative humidity percentage.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Service_Weather
 */
final readonly class Humidity
{
    public function __construct(
        public int $percentage
    ) {
        if ($percentage < 0 || $percentage > 100) {
            throw new InvalidLocationException(
                "Humidity must be between 0 and 100, got: $percentage"
            );
        }
    }

    /**
     * Create from percentage (0-100).
     */
    public static function fromPercentage(int $percentage): self
    {
        return new self($percentage);
    }

    /**
     * Get humidity percentage.
     */
    public function getPercentage(): int
    {
        return $this->percentage;
    }

    /**
     * Get formatted humidity string.
     */
    public function format(): string
    {
        return $this->percentage . '%';
    }
}
