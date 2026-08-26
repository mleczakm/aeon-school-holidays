<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\MenUpdater;

use Mleczakm\AeonSchoolHolidays\Voivodeship;

final class WinterScheduleExtractor
{
    private const array MONTHS = [
        'stycznia' => 1,
        'lutego' => 2,
        'marca' => 3,
    ];

    public function supports(FeedItem $item): bool
    {
        return str_contains(mb_strtolower($item->title, 'UTF-8'), 'terminy ferii zimowych w roku szkolnym');
    }

    public function extract(FeedItem $item, string $articleText): WinterSchedule
    {
        if (preg_match('/roku szkolnym\s+(?<start>\d{4})\s*\/\s*(?<end>\d{4})/ui', $item->title, $schoolYear) !== 1) {
            throw new \UnexpectedValueException(sprintf('Unable to read the school year from MEN announcement "%s".', $item->title));
        }

        $schoolYearStart = (int) $schoolYear['start'];
        $schoolYearEnd = (int) $schoolYear['end'];

        if ($schoolYearEnd !== $schoolYearStart + 1) {
            throw new \UnexpectedValueException(sprintf('MEN announcement "%s" contains an invalid school year.', $item->title));
        }

        $pattern = '/(?<start_day>\d{1,2})\s+(?<start_month>stycznia|lutego|marca)\s*[-–—]\s*(?<end_day>\d{1,2})\s+(?<end_month>stycznia|lutego|marca)\s+(?<year>\d{4})\s*:?[\s-]*(?<regions>.*?)(?=\d{1,2}\s+(?:stycznia|lutego|marca)\s*[-–—]|Podstawa prawna|$)/ui';
        $matchCount = preg_match_all($pattern, $articleText, $matches, PREG_SET_ORDER);

        if (!is_int($matchCount) || $matchCount < 1) {
            throw new \UnexpectedValueException(sprintf('No winter-break periods were found in MEN announcement "%s".', $item->title));
        }

        $periods = [];

        foreach ($matches as $match) {
            $year = (int) $match['year'];

            if ($year !== $schoolYearEnd) {
                continue;
            }

            $start = $this->date($year, $match['start_month'], (int) $match['start_day']);
            $end = $this->date($year, $match['end_month'], (int) $match['end_day']);
            $voivodeships = $this->extractVoivodeships($match['regions']);

            if ($voivodeships === []) {
                continue;
            }

            $key = $start . '/' . $end . '/' . implode(',', $voivodeships);
            $periods[$key] = [
                'start' => $start,
                'end' => $end,
                'voivodeships' => $voivodeships,
            ];
        }

        $normalizedPeriods = array_values($periods);
        usort($normalizedPeriods, static fn(array $left, array $right): int => $left['start'] <=> $right['start']);
        $this->validate($schoolYearStart, $normalizedPeriods);

        return new WinterSchedule(
            $schoolYearStart,
            $item->url,
            $item->publishedAt,
            $normalizedPeriods,
        );
    }

    private function date(int $year, string $polishMonth, int $day): string
    {
        $month = self::MONTHS[mb_strtolower($polishMonth, 'UTF-8')] ?? null;

        if ($month === null || !checkdate($month, $day, $year)) {
            throw new \UnexpectedValueException(sprintf('Invalid Polish date: %d %s %d.', $day, $polishMonth, $year));
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /** @return list<string> */
    private function extractVoivodeships(string $text): array
    {
        $voivodeships = [];

        foreach (Voivodeship::cases() as $voivodeship) {
            $pattern = '/(?<![\p{L}-])' . preg_quote($voivodeship->polishName(), '/') . '(?![\p{L}-])/ui';

            if (preg_match($pattern, $text) === 1) {
                $voivodeships[] = $voivodeship->value;
            }
        }

        sort($voivodeships);

        return $voivodeships;
    }

    /** @param list<array{start: string, end: string, voivodeships: list<string>}> $periods */
    private function validate(int $schoolYearStart, array $periods): void
    {
        $assigned = [];

        foreach ($periods as $period) {
            $start = new \DateTimeImmutable($period['start'], new \DateTimeZone('UTC'));
            $end = new \DateTimeImmutable($period['end'], new \DateTimeZone('UTC'));

            if ($start->diff($end)->days !== 13 || (int) $start->format('Y') !== $schoolYearStart + 1 || (int) $end->format('Y') !== $schoolYearStart + 1) {
                throw new \UnexpectedValueException(sprintf('A parsed winter break for %d/%d is not exactly fourteen days in the expected calendar year.', $schoolYearStart, $schoolYearStart + 1));
            }

            foreach ($period['voivodeships'] as $isoCode) {
                if (isset($assigned[$isoCode])) {
                    throw new \UnexpectedValueException(sprintf('%s occurs in more than one parsed winter period for %d/%d.', $isoCode, $schoolYearStart, $schoolYearStart + 1));
                }

                $assigned[$isoCode] = true;
            }
        }

        $expected = array_map(static fn(Voivodeship $voivodeship): string => $voivodeship->value, Voivodeship::cases());
        sort($expected);
        $actual = array_keys($assigned);
        sort($actual);

        if ($actual !== $expected) {
            throw new \UnexpectedValueException(sprintf('Parsed winter schedule for %d/%d does not assign every voivodeship exactly once.', $schoolYearStart, $schoolYearStart + 1));
        }
    }
}
