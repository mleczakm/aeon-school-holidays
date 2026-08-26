<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\Tests;

use Aeon\Calendar\Gregorian\Day;
use Mleczakm\AeonSchoolHolidays\SchoolHolidayPeriod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchoolHolidayPeriod::class)]
final class SchoolHolidayPeriodTest extends TestCase
{
    public function testItIteratesBothBoundaryDays(): void
    {
        $period = SchoolHolidayPeriod::fromStrings('2027-02-01', '2027-02-03');

        self::assertSame(
            ['2027-02-01', '2027-02-02', '2027-02-03'],
            array_map(
                static fn(Day $day): string => $day->toString(),
                iterator_to_array($period->days()),
            ),
        );
    }

    public function testItRejectsAReversedPeriod(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SchoolHolidayPeriod::fromStrings('2027-02-03', '2027-02-01');
    }
}
