<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\Tests;

use Mleczakm\AeonSchoolHolidays\Voivodeship;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('polishNameProvider')]
    public function testItReturnsTheCorrectPolishName(Voivodeship $voivodeship, string $expectedName): void
    {
        self::assertSame($expectedName, $voivodeship->polishName());
    }

    /** @return iterable<string, array{Voivodeship, string}> */
    public static function polishNameProvider(): iterable
    {
        yield 'PL-02' => [Voivodeship::LowerSilesian, 'dolnośląskie'];
        yield 'PL-04' => [Voivodeship::KuyavianPomeranian, 'kujawsko-pomorskie'];
        yield 'PL-06' => [Voivodeship::Lublin, 'lubelskie'];
        yield 'PL-08' => [Voivodeship::Lubusz, 'lubuskie'];
        yield 'PL-10' => [Voivodeship::Lodz, 'łódzkie'];
        yield 'PL-12' => [Voivodeship::LesserPoland, 'małopolskie'];
        yield 'PL-14' => [Voivodeship::Masovian, 'mazowieckie'];
        yield 'PL-16' => [Voivodeship::Opole, 'opolskie'];
        yield 'PL-18' => [Voivodeship::Subcarpathian, 'podkarpackie'];
        yield 'PL-20' => [Voivodeship::Podlaskie, 'podlaskie'];
        yield 'PL-22' => [Voivodeship::Pomeranian, 'pomorskie'];
        yield 'PL-24' => [Voivodeship::Silesian, 'śląskie'];
        yield 'PL-26' => [Voivodeship::HolyCross, 'świętokrzyskie'];
        yield 'PL-28' => [Voivodeship::WarmianMasurian, 'warmińsko-mazurskie'];
        yield 'PL-30' => [Voivodeship::GreaterPoland, 'wielkopolskie'];
        yield 'PL-32' => [Voivodeship::WestPomeranian, 'zachodniopomorskie'];
    }

    public function testItRejectsAnUnknownIsoCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PL-99');

        Voivodeship::fromIsoCode('PL-99');
    }
}
