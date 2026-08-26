<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\MenUpdater;

final readonly class WinterSchedule
{
    /**
     * @param list<array{start: string, end: string, voivodeships: list<string>}> $periods
     */
    public function __construct(
        public int $schoolYearStart,
        public string $sourceUrl,
        public \DateTimeImmutable $publishedAt,
        public array $periods,
    ) {}
}
