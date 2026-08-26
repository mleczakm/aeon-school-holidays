<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\Tests;

use Aeon\Calendar\Exception\HolidayYearException;
use Aeon\Calendar\Gregorian\DateTime;
use Aeon\Calendar\Gregorian\Day;
use Aeon\Calendar\Gregorian\TimePeriod;
use Mleczakm\AeonSchoolHolidays\PolishSchoolHolidays;
use Mleczakm\AeonSchoolHolidays\Voivodeship;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PolishSchoolHolidays::class)]
final class PolishSchoolHolidaysTest extends TestCase
{
    public function testWinterBreakDependsOnTheVoivodeship(): void
    {
        $masovian = new PolishSchoolHolidays(Voivodeship::Masovian);
        $lowerSilesian = new PolishSchoolHolidays(Voivodeship::LowerSilesian);
        $day = Day::fromString('2026-01-19');

        self::assertTrue($masovian->isHoliday($day));
        self::assertFalse($lowerSilesian->isHoliday($day));
        self::assertTrue($lowerSilesian->isHoliday(Day::fromString('2026-02-02')));
    }

    #[DataProvider('nationalBreakBoundaryProvider')]
    public function testNationalBreakBoundaries(string $date, bool $expected): void
    {
        $holidays = new PolishSchoolHolidays(Voivodeship::Masovian);

        self::assertSame($expected, $holidays->isHoliday(Day::fromString($date)), $date);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function nationalBreakBoundaryProvider(): iterable
    {
        yield 'day before the exceptional Monday Christmas start' => ['2025-12-21', false];
        yield 'Christmas starts on Monday the 22nd' => ['2025-12-22', true];
        yield 'Christmas ends' => ['2025-12-31', true];
        yield 'New Year is a public holiday, not part of the school break' => ['2026-01-01', false];
        yield 'ordinary Christmas break starts on the 23rd' => ['2026-12-23', true];
        yield '2027 Easter break starts on Maundy Thursday' => ['2027-03-25', true];
        yield '2027 Easter break ends on Tuesday' => ['2027-03-30', true];
        yield 'day after 2027 Easter break' => ['2027-03-31', false];
        yield '2027 summer break starts after the last Friday of classes' => ['2027-06-26', true];
        yield '2027 summer break ends' => ['2027-08-31', true];
        yield 'next school year begins' => ['2027-09-01', false];
    }

    public function testHolidayNamesAreAvailableInPolishAndEnglish(): void
    {
        $holidays = new PolishSchoolHolidays(Voivodeship::Masovian);
        $holiday = $holidays->holidaysAt(Day::fromString('2027-02-01'))[0];

        self::assertSame('Ferie zimowe', $holiday->name('pl'));
        self::assertSame('Winter school holidays', $holiday->name('en'));
        self::assertSame(['pl', 'en'], $holiday->locales());
    }

    public function testItReturnsOrderedHolidaysInsideAnInclusivePeriod(): void
    {
        $holidays = new PolishSchoolHolidays(Voivodeship::Masovian);
        $result = $holidays->in(new TimePeriod(
            DateTime::fromString('2025-12-20 12:00:00 UTC'),
            DateTime::fromString('2026-01-02 12:00:00 UTC'),
        ));

        self::assertCount(10, $result);
        self::assertSame('2025-12-22', $result[0]->day()->toString());
        self::assertSame('2025-12-31', $result[9]->day()->toString());
    }

    public function testEverySupportedWinterBreakContainsFourteenAeonHolidays(): void
    {
        foreach (Voivodeship::cases() as $voivodeship) {
            $holidays = new PolishSchoolHolidays($voivodeship);

            foreach ($holidays->supportedSchoolYears() as $schoolYear) {
                $winter = $holidays->in(new TimePeriod(
                    DateTime::fromString(sprintf('%d-01-01 00:00:00 UTC', $schoolYear + 1)),
                    DateTime::fromString(sprintf('%d-03-10 23:59:59 UTC', $schoolYear + 1)),
                ));

                self::assertCount(14, $winter, sprintf('%s in %d/%d', $voivodeship->value, $schoolYear, $schoolYear + 1));
                self::assertSame(
                    ['Ferie zimowe'],
                    array_values(array_unique(array_map(static fn($holiday): string => $holiday->name('pl'), $winter))),
                );
            }
        }
    }

    public function testItRejectsADayFromAnUnsupportedSchoolYear(): void
    {
        $holidays = new PolishSchoolHolidays(Voivodeship::Masovian);

        $this->expectException(HolidayYearException::class);
        $this->expectExceptionMessage('2028/2029');

        $holidays->isHoliday(Day::fromString('2028-09-01'));
    }

    public function testItExposesItsRegionAndSupportedYears(): void
    {
        $holidays = new PolishSchoolHolidays(Voivodeship::Lodz);

        self::assertSame(Voivodeship::Lodz, $holidays->voivodeship());
        self::assertSame(range(2020, 2027), $holidays->supportedSchoolYears());
    }
}
