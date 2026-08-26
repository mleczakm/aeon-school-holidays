<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays;

enum Voivodeship: string
{
    case LowerSilesian = 'PL-02';
    case KuyavianPomeranian = 'PL-04';
    case Lublin = 'PL-06';
    case Lubusz = 'PL-08';
    case Lodz = 'PL-10';
    case LesserPoland = 'PL-12';
    case Masovian = 'PL-14';
    case Opole = 'PL-16';
    case Subcarpathian = 'PL-18';
    case Podlaskie = 'PL-20';
    case Pomeranian = 'PL-22';
    case Silesian = 'PL-24';
    case HolyCross = 'PL-26';
    case WarmianMasurian = 'PL-28';
    case GreaterPoland = 'PL-30';
    case WestPomeranian = 'PL-32';

    public static function fromIsoCode(string $isoCode): self
    {
        $normalizedCode = strtoupper(trim($isoCode));

        try {
            return self::from($normalizedCode);
        } catch (\ValueError $error) {
            throw new \InvalidArgumentException(sprintf('Unknown Polish voivodeship ISO 3166-2 code: "%s".', $isoCode), previous: $error);
        }
    }

    public function polishName(): string
    {
        return match ($this) {
            self::LowerSilesian => 'dolnośląskie',
            self::KuyavianPomeranian => 'kujawsko-pomorskie',
            self::Lublin => 'lubelskie',
            self::Lubusz => 'lubuskie',
            self::Lodz => 'łódzkie',
            self::LesserPoland => 'małopolskie',
            self::Masovian => 'mazowieckie',
            self::Opole => 'opolskie',
            self::Subcarpathian => 'podkarpackie',
            self::Podlaskie => 'podlaskie',
            self::Pomeranian => 'pomorskie',
            self::Silesian => 'śląskie',
            self::HolyCross => 'świętokrzyskie',
            self::WarmianMasurian => 'warmińsko-mazurskie',
            self::GreaterPoland => 'wielkopolskie',
            self::WestPomeranian => 'zachodniopomorskie',
        };
    }
}
