<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays;

use Aeon\Calendar\Gregorian\Day;

final readonly class SchoolHolidayPeriod
{
    public function __construct(
        public Day $start,
        public Day $end,
    ) {
        if ($start->isAfter($end)) {
            throw new \InvalidArgumentException('A school holiday period cannot end before it starts.');
        }
    }

    public static function fromStrings(string $start, string $end): self
    {
        return new self(Day::fromString($start), Day::fromString($end));
    }

    /** @return \Generator<int, Day> */
    public function days(): \Generator
    {
        for ($day = $this->start; $day->isBeforeOrEqualTo($this->end); $day = $day->next()) {
            yield $day;
        }
    }
}
