<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays;

use Aeon\Calendar\Gregorian\Day;
use Aeon\Calendar\Gregorian\TimePeriod;
use Aeon\Calendar\Holidays;
use Aeon\Calendar\Holidays\Holiday;
use Aeon\Calendar\Holidays\HolidayLocaleName;
use Aeon\Calendar\Holidays\HolidayName;

final class PolishSchoolHolidays implements Holidays
{
    /** @var array<int, array<string, Holiday>> */
    private array $calendars = [];

    public function __construct(
        private readonly Voivodeship $voivodeship,
        private readonly WinterBreakSchedule $winterBreaks = new OfficialWinterBreakSchedule(),
    ) {}

    public function isHoliday(Day $day): bool
    {
        return isset($this->calendarFor($this->schoolYearStartFor($day))[$day->toString()]);
    }

    /** @return list<Holiday> */
    public function holidaysAt(Day $day): array
    {
        $holiday = $this->calendarFor($this->schoolYearStartFor($day))[$day->toString()] ?? null;

        return $holiday === null ? [] : [$holiday];
    }

    /** @return list<Holiday> */
    public function in(TimePeriod $period): array
    {
        $start = $period->start()->day();
        $end = $period->end()->day();

        if ($start->isAfter($end)) {
            return [];
        }

        $holidays = [];
        $firstSchoolYear = $this->schoolYearStartFor($start);
        $lastSchoolYear = $this->schoolYearStartFor($end);

        for ($schoolYear = $firstSchoolYear; $schoolYear <= $lastSchoolYear; ++$schoolYear) {
            foreach ($this->calendarFor($schoolYear) as $holiday) {
                if ($holiday->day()->isAfterOrEqualTo($start) && $holiday->day()->isBeforeOrEqualTo($end)) {
                    $holidays[$holiday->day()->toString()] = $holiday;
                }
            }
        }

        ksort($holidays);

        return array_values($holidays);
    }

    public function voivodeship(): Voivodeship
    {
        return $this->voivodeship;
    }

    /** @return list<int> */
    public function supportedSchoolYears(): array
    {
        return $this->winterBreaks->supportedSchoolYears();
    }

    /** @return array<string, Holiday> */
    private function calendarFor(int $schoolYearStart): array
    {
        if (isset($this->calendars[$schoolYearStart])) {
            return $this->calendars[$schoolYearStart];
        }

        $calendar = [];

        foreach ($this->breaksFor($schoolYearStart) as [$range, $polishName, $englishName]) {
            foreach ($range->days() as $day) {
                $calendar[$day->toString()] = new Holiday(
                    $day,
                    new HolidayName(
                        new HolidayLocaleName('pl', $polishName),
                        new HolidayLocaleName('en', $englishName),
                    ),
                );
            }
        }

        ksort($calendar);

        return $this->calendars[$schoolYearStart] = $calendar;
    }

    /** @return list<array{SchoolHolidayPeriod, string, string}> */
    private function breaksFor(int $schoolYearStart): array
    {
        $schoolYearEnd = $schoolYearStart + 1;
        $christmasStart = Day::create($schoolYearStart, 12, 22)->weekDay()->number() === 1
            ? Day::create($schoolYearStart, 12, 22)
            : Day::create($schoolYearStart, 12, 23);
        $easterSunday = $this->easterSunday($schoolYearEnd);
        $lastDayOfClasses = Day::fromDateTime(
            (new \DateTimeImmutable(sprintf('%d-06-20', $schoolYearEnd), new \DateTimeZone('UTC')))->modify('next friday'),
        );

        return [
            [
                new SchoolHolidayPeriod($christmasStart, Day::create($schoolYearStart, 12, 31)),
                'Zimowa przerwa świąteczna',
                'Christmas school break',
            ],
            [
                $this->winterBreaks->for($schoolYearStart, $this->voivodeship),
                'Ferie zimowe',
                'Winter school holidays',
            ],
            [
                new SchoolHolidayPeriod($easterSunday->subDays(3), $easterSunday->addDays(2)),
                'Wiosenna przerwa świąteczna',
                'Easter school break',
            ],
            [
                new SchoolHolidayPeriod($lastDayOfClasses->next(), Day::create($schoolYearEnd, 8, 31)),
                'Ferie letnie',
                'Summer school holidays',
            ],
        ];
    }

    private function schoolYearStartFor(Day $day): int
    {
        $year = $day->year()->number();

        return $day->month()->number() >= 9 ? $year : $year - 1;
    }

    /** Meeus/Jones/Butcher Gregorian computus. */
    private function easterSunday(int $year): Day
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Day::create($year, $month, $day);
    }
}
