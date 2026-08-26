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

    public function testItRejectsAnUnreadableDatasetFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read Polish winter break dataset');

        $path = sys_get_temp_dir() . '/missing-' . bin2hex(random_bytes(8)) . '.json';
        (new OfficialWinterBreakSchedule($path))->supportedSchoolYears();
    }

    /** @param array<string, mixed> $decoded */
    #[DataProvider('malformedDatasetProvider')]
    public function testItRejectsAMalformedDataset(array $decoded, string $expectedMessage): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage($expectedMessage);

        (new OfficialWinterBreakSchedule($this->temporaryDataset($decoded)))->supportedSchoolYears();
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function malformedDatasetProvider(): iterable
    {
        yield 'missing schema_version' => [
            ['schedules' => self::baseSchedule()['schedules']],
            'unsupported structure',
        ];

        yield 'schedules is not an array' => [
            ['schema_version' => 1, 'schedules' => 'not-an-array'],
            'unsupported structure',
        ];

        yield 'school-year entry is not an array' => [
            ['schema_version' => 1, 'schedules' => ['2020' => 'not-an-array']],
            'invalid school-year entry',
        ];

        yield 'school-year key is not numeric' => [
            ['schema_version' => 1, 'schedules' => ['not-a-year' => ['periods' => []]]],
            'invalid school-year entry',
        ];

        yield 'period missing start' => [
            self::withMutatedPeriod(static function (array $period): array {
                unset($period['start']);

                return $period;
            }),
            'invalid winter period',
        ];

        yield 'period is not exactly fourteen days' => [
            self::withMutatedPeriod(static fn(array $period): array => [...$period, 'end' => '2021-01-16']),
            'outside the expected fourteen-day range',
        ];

        yield 'voivodeship code is not a string' => [
            self::withMutatedPeriod(static fn(array $period): array => [...$period, 'voivodeships' => [2, ...array_slice($period['voivodeships'], 1)]]),
            'invalid voivodeship code',
        ];

        yield 'voivodeship code repeats within a school year' => [
            self::withMutatedPeriod(static fn(array $period): array => [...$period, 'voivodeships' => ['PL-02', 'PL-02', ...array_slice($period['voivodeships'], 2)]]),
            'occurs more than once',
        ];

        yield 'a voivodeship is never assigned' => [
            self::withMutatedPeriod(static fn(array $period): array => [...$period, 'voivodeships' => array_slice($period['voivodeships'], 1)]),
            'does not assign every Polish voivodeship exactly once',
        ];
    }

    /** @return array{schema_version: int, schedules: array{'2020': array{periods: list<array{start: string, end: string, voivodeships: list<string>}>}}} */
    private static function baseSchedule(): array
    {
        return [
            'schema_version' => 1,
            'schedules' => [
                '2020' => [
                    'periods' => [
                        [
                            'start' => '2021-01-04',
                            'end' => '2021-01-17',
                            'voivodeships' => array_map(static fn(Voivodeship $voivodeship): string => $voivodeship->value, Voivodeship::cases()),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param callable(array{start: string, end: string, voivodeships: list<string>}): array<string, mixed> $mutate
     * @return array<string, mixed>
     */
    private static function withMutatedPeriod(callable $mutate): array
    {
        $decoded = self::baseSchedule();
        $period = $decoded['schedules']['2020']['periods'][0];
        $decoded['schedules']['2020']['periods'][0] = $mutate($period);

        return $decoded;
    }

    /** @param array<string, mixed> $decoded */
    private function temporaryDataset(array $decoded): string
    {
        $path = tempnam(sys_get_temp_dir(), 'winter-breaks-');
        self::assertIsString($path);
        self::assertNotFalse(file_put_contents($path, json_encode($decoded, JSON_THROW_ON_ERROR)));

        return $path;
    }
}
