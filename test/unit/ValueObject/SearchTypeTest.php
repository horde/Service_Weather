<?php

declare(strict_types=1);

namespace Horde\Service\Weather\Test\ValueObject;

use Horde\Service\Weather\ValueObject\SearchType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchType::class)]
class SearchTypeTest extends TestCase
{
    public function testAllCasesHaveStringValues(): void
    {
        $this->assertSame('any', SearchType::ANY->value);
        $this->assertSame('city', SearchType::CITY->value);
        $this->assertSame('coordinates', SearchType::COORDINATES->value);
        $this->assertSame('zip', SearchType::ZIP->value);
        $this->assertSame('icao', SearchType::ICAO->value);
        $this->assertSame('ip', SearchType::IP_ADDRESS->value);
    }

    public function testFromParsesEachValue(): void
    {
        $this->assertSame(SearchType::ANY, SearchType::from('any'));
        $this->assertSame(SearchType::CITY, SearchType::from('city'));
        $this->assertSame(SearchType::IP_ADDRESS, SearchType::from('ip'));
    }
}
