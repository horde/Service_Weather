<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

use Horde\Service\Weather\ValueObject\MoonPhase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MoonPhase::class)]
class MoonPhaseTest extends TestCase
{
    public function testAllCasesHaveStringValues(): void
    {
        $this->assertSame('new', MoonPhase::NEW->value);
        $this->assertSame('waxing_crescent', MoonPhase::WAXING_CRESCENT->value);
        $this->assertSame('first_quarter', MoonPhase::FIRST_QUARTER->value);
        $this->assertSame('waxing_gibbous', MoonPhase::WAXING_GIBBOUS->value);
        $this->assertSame('full', MoonPhase::FULL->value);
        $this->assertSame('waning_gibbous', MoonPhase::WANING_GIBBOUS->value);
        $this->assertSame('last_quarter', MoonPhase::LAST_QUARTER->value);
        $this->assertSame('waning_crescent', MoonPhase::WANING_CRESCENT->value);
        $this->assertSame('unknown', MoonPhase::UNKNOWN->value);
    }

    public function testFromFractionCardinalPoints(): void
    {
        $this->assertSame(MoonPhase::NEW, MoonPhase::fromFraction(0.0));
        $this->assertSame(MoonPhase::FIRST_QUARTER, MoonPhase::fromFraction(0.25));
        $this->assertSame(MoonPhase::FULL, MoonPhase::fromFraction(0.5));
        $this->assertSame(MoonPhase::LAST_QUARTER, MoonPhase::fromFraction(0.75));
    }

    public function testFromFractionIntermediateBands(): void
    {
        $this->assertSame(MoonPhase::WAXING_CRESCENT, MoonPhase::fromFraction(0.125));
        $this->assertSame(MoonPhase::WAXING_GIBBOUS, MoonPhase::fromFraction(0.375));
        $this->assertSame(MoonPhase::WANING_GIBBOUS, MoonPhase::fromFraction(0.625));
        $this->assertSame(MoonPhase::WANING_CRESCENT, MoonPhase::fromFraction(0.875));
    }

    public function testFromFractionWrapsAround(): void
    {
        // Near-1.0 belongs to the NEW band (which wraps through 0).
        $this->assertSame(MoonPhase::NEW, MoonPhase::fromFraction(0.99));

        // Fractions > 1.0 fmod to their fractional part.
        // 1.5 → 0.5 → FULL.
        $this->assertSame(MoonPhase::FULL, MoonPhase::fromFraction(1.5));

        // Negative values normalize positive.
        // -0.5 → 0.5 (after +1.0) → FULL.
        $this->assertSame(MoonPhase::FULL, MoonPhase::fromFraction(-0.5));
    }
}
