<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays;

interface WinterBreakSchedule
{
    public function for(int $schoolYearStart, Voivodeship $voivodeship): SchoolHolidayPeriod;

    /** @return list<int> */
    public function supportedSchoolYears(): array;
}
