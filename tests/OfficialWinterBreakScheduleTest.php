<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\Tests;

use Aeon\Calendar\Exception\HolidayYearException;
use Mleczakm\AeonSchoolHolidays\OfficialWinterBreakSchedule;
use Mleczakm\AeonSchoolHolidays\SchoolHolidayPeriod;
use Mleczakm\AeonSchoolHolidays\Voivodeship;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OfficialWinterBreakSchedule::class)]
#[UsesClass(Voivodeship::class)]
#[UsesClass(SchoolHolidayPeriod::class)]
final class OfficialWinterBreakScheduleTest extends TestCase
{
    public function testEveryRegionHasExactlyFourteenDaysInEverySupportedSchoolYear(): void
    {
        $schedule = new OfficialWinterBreakSchedule();

        foreach ($schedule->supportedSchoolYears() as $schoolYear) {
            foreach (Voivodeship::cases() as $voivodeship) {
                self::assertCount(
                    14,
                    iterator_to_array($schedule->for($schoolYear, $voivodeship)->days()),
                    sprintf('%s in %d/%d', $voivodeship->value, $schoolYear, $schoolYear + 1),
                );
            }
        }
    }

    #[DataProvider('masovianScheduleProvider')]
    public function testItReturnsPublishedMasovianDates(int $schoolYear, string $expectedStart, string $expectedEnd): void
    {
        $range = (new OfficialWinterBreakSchedule())->for($schoolYear, Voivodeship::Masovian);

        self::assertSame($expectedStart, $range->start->toString());
        self::assertSame($expectedEnd, $range->end->toString());
    }

    /** @return iterable<string, array{int, string, string}> */
    public static function masovianScheduleProvider(): iterable
    {
        yield 'pandemic-wide 2020/21 schedule' => [2020, '2021-01-04', '2021-01-17'];
        yield '2021/22' => [2021, '2022-01-31', '2022-02-13'];
        yield '2022/23' => [2022, '2023-02-13', '2023-02-26'];
        yield '2023/24' => [2023, '2024-01-15', '2024-01-28'];
        yield '2024/25' => [2024, '2025-02-03', '2025-02-16'];
        yield '2025/26' => [2025, '2026-01-19', '2026-02-01'];
        yield '2026/27' => [2026, '2027-02-01', '2027-02-14'];
        yield '2027/28' => [2027, '2028-01-31', '2028-02-13'];
    }

    public function testItReportsTheSupportedRangeForAnUnknownSchoolYear(): void
    {
        $this->expectException(HolidayYearException::class);
        $this->expectExceptionMessage('2028/2029');
        $this->expectExceptionMessage('2020/2021, 2021/2022');

        (new OfficialWinterBreakSchedule())->for(2028, Voivodeship::Masovian);
    }
}
