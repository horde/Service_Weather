<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

use Horde\Service\Weather\ValueObject\WindDirection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WindDirection::class)]
class WindDirectionTest extends TestCase
{
    public function testFromDegreesNorth(): void
    {
        $this->assertSame(WindDirection::N, WindDirection::fromDegrees(0));
        $this->assertSame(WindDirection::N, WindDirection::fromDegrees(360));
        $this->assertSame(WindDirection::N, WindDirection::fromDegrees(10));
    }

    public function testFromDegreesNorthEast(): void
    {
        $this->assertSame(WindDirection::NE, WindDirection::fromDegrees(45));
        $this->assertSame(WindDirection::NE, WindDirection::fromDegrees(50));
    }

    public function testFromDegreesEast(): void
    {
        $this->assertSame(WindDirection::E, WindDirection::fromDegrees(90));
        $this->assertSame(WindDirection::E, WindDirection::fromDegrees(85));
        $this->assertSame(WindDirection::E, WindDirection::fromDegrees(100));
    }

    public function testFromDegreesSouthEast(): void
    {
        $this->assertSame(WindDirection::SE, WindDirection::fromDegrees(135));
        $this->assertSame(WindDirection::SE, WindDirection::fromDegrees(130));
    }

    public function testFromDegreesSouth(): void
    {
        $this->assertSame(WindDirection::S, WindDirection::fromDegrees(180));
        $this->assertSame(WindDirection::S, WindDirection::fromDegrees(175));
    }

    public function testFromDegreesSouthWest(): void
    {
        $this->assertSame(WindDirection::SW, WindDirection::fromDegrees(225));
        $this->assertSame(WindDirection::SW, WindDirection::fromDegrees(220));
    }

    public function testFromDegreesWest(): void
    {
        $this->assertSame(WindDirection::W, WindDirection::fromDegrees(270));
        $this->assertSame(WindDirection::W, WindDirection::fromDegrees(265));
    }

    public function testFromDegreesNorthWest(): void
    {
        $this->assertSame(WindDirection::NW, WindDirection::fromDegrees(315));
        $this->assertSame(WindDirection::NW, WindDirection::fromDegrees(310));
    }

    public function testToDegreesNorth(): void
    {
        $this->assertSame(0.0, WindDirection::N->toDegrees());
    }

    public function testToDegreesNorthEast(): void
    {
        $this->assertSame(45.0, WindDirection::NE->toDegrees());
    }

    public function testToDegreesEast(): void
    {
        $this->assertSame(90.0, WindDirection::E->toDegrees());
    }

    public function testToDegreesSouthEast(): void
    {
        $this->assertSame(135.0, WindDirection::SE->toDegrees());
    }

    public function testToDegreesSouth(): void
    {
        $this->assertSame(180.0, WindDirection::S->toDegrees());
    }

    public function testToDegreesSouthWest(): void
    {
        $this->assertSame(225.0, WindDirection::SW->toDegrees());
    }

    public function testToDegreesWest(): void
    {
        $this->assertSame(270.0, WindDirection::W->toDegrees());
    }

    public function testToDegreesNorthWest(): void
    {
        $this->assertSame(315.0, WindDirection::NW->toDegrees());
    }

    public function testToDegreesVariable(): void
    {
        $this->assertSame(0.0, WindDirection::VARIABLE->toDegrees());
    }

    public function testEnumValues(): void
    {
        $this->assertSame('N', WindDirection::N->value);
        $this->assertSame('NE', WindDirection::NE->value);
        $this->assertSame('E', WindDirection::E->value);
        $this->assertSame('SE', WindDirection::SE->value);
        $this->assertSame('S', WindDirection::S->value);
        $this->assertSame('SW', WindDirection::SW->value);
        $this->assertSame('W', WindDirection::W->value);
        $this->assertSame('NW', WindDirection::NW->value);
        $this->assertSame('VAR', WindDirection::VARIABLE->value);
    }
}
