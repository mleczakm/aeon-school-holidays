<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\Tests;

use Mleczakm\AeonSchoolHolidays\Voivodeship;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Voivodeship::class)]
final class VoivodeshipTest extends TestCase
{
    public function testItContainsEveryPolishVoivodeship(): void
    {
        self::assertCount(16, Voivodeship::cases());
        self::assertSame(
            ['PL-02', 'PL-04', 'PL-06', 'PL-08', 'PL-10', 'PL-12', 'PL-14', 'PL-16', 'PL-18', 'PL-20', 'PL-22', 'PL-24', 'PL-26', 'PL-28', 'PL-30', 'PL-32'],
            array_map(static fn(Voivodeship $voivodeship): string => $voivodeship->value, Voivodeship::cases()),
        );
    }

    public function testItCreatesARegionFromANormalizedIsoCode(): void
    {
        self::assertSame(Voivodeship::Masovian, Voivodeship::fromIsoCode(' pl-14 '));
        self::assertSame('mazowieckie', Voivodeship::Masovian->polishName());
    }

    public function testItRejectsAnUnknownIsoCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PL-99');

        Voivodeship::fromIsoCode('PL-99');
    }
}
