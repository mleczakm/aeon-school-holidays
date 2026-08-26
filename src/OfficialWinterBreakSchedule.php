<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays;

use Aeon\Calendar\Exception\HolidayYearException;

final class OfficialWinterBreakSchedule implements WinterBreakSchedule
{
    /** @var null|array<int, list<array{start: string, end: string, voivodeships: list<string>}>> */
    private ?array $schedules = null;

    private readonly string $datasetPath;

    public function __construct(?string $datasetPath = null)
    {
        $this->datasetPath = $datasetPath ?? dirname(__DIR__) . '/resources/polish-winter-breaks.json';
    }

    public function for(int $schoolYearStart, Voivodeship $voivodeship): SchoolHolidayPeriod
    {
        $schedules = $this->loadSchedules();
        $schedule = $schedules[$schoolYearStart] ?? null;

        if ($schedule === null) {
            throw new HolidayYearException(sprintf(
                'Polish winter break data is unavailable for school year %d/%d; supported school years are %s.',
                $schoolYearStart,
                $schoolYearStart + 1,
                implode(', ', array_map(
                    static fn(int $year): string => sprintf('%d/%d', $year, $year + 1),
                    $this->supportedSchoolYears(),
                )),
            ));
        }

        foreach ($schedule as $period) {
            if (in_array($voivodeship->value, $period['voivodeships'], true)) {
                return SchoolHolidayPeriod::fromStrings($period['start'], $period['end']);
            }
        }

        // @codeCoverageIgnoreStart
        // Unreachable: loadSchedules() already rejects any school year that doesn't assign
        // every voivodeship exactly once, so this loop always finds a match.
        throw new \LogicException(sprintf(
            'Winter break data for %s is incomplete in school year %d/%d.',
            $voivodeship->value,
            $schoolYearStart,
            $schoolYearStart + 1,
        ));
        // @codeCoverageIgnoreEnd
    }

    /** @return list<int> */
    public function supportedSchoolYears(): array
    {
        return array_keys($this->loadSchedules());
    }

    /** @return array<int, list<array{start: string, end: string, voivodeships: list<string>}>> */
    private function loadSchedules(): array
    {
        if ($this->schedules !== null) {
            return $this->schedules;
        }

        $contents = @file_get_contents($this->datasetPath);

        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read Polish winter break dataset: %s.', $this->datasetPath));
        }

        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($decoded) || ($decoded['schema_version'] ?? null) !== 1 || !is_array($decoded['schedules'] ?? null)) {
            throw new \UnexpectedValueException('The Polish winter break dataset has an unsupported structure.');
        }

        $schedules = [];

        foreach ($decoded['schedules'] as $schoolYear => $entry) {
            if (!ctype_digit((string) $schoolYear) || !is_array($entry) || !is_array($entry['periods'] ?? null)) {
                throw new \UnexpectedValueException('The Polish winter break dataset contains an invalid school-year entry.');
            }

            $year = (int) $schoolYear;
            $periods = [];
            $assignedVoivodeships = [];

            foreach ($entry['periods'] as $period) {
                if (!is_array($period) || !is_string($period['start'] ?? null) || !is_string($period['end'] ?? null) || !is_array($period['voivodeships'] ?? null)) {
                    throw new \UnexpectedValueException(sprintf('School year %d/%d contains an invalid winter period.', $year, $year + 1));
                }

                $range = SchoolHolidayPeriod::fromStrings($period['start'], $period['end']);

                if (count(iterator_to_array($range->days())) !== 14 || $range->start->year()->number() !== $year + 1 || $range->end->year()->number() !== $year + 1) {
                    throw new \UnexpectedValueException(sprintf('School year %d/%d contains a winter period outside the expected fourteen-day range.', $year, $year + 1));
                }

                $voivodeships = [];

                foreach ($period['voivodeships'] as $isoCode) {
                    if (!is_string($isoCode)) {
                        throw new \UnexpectedValueException(sprintf('School year %d/%d contains an invalid voivodeship code.', $year, $year + 1));
                    }

                    $voivodeship = Voivodeship::fromIsoCode($isoCode);

                    if (isset($assignedVoivodeships[$voivodeship->value])) {
                        throw new \UnexpectedValueException(sprintf('%s occurs more than once in school year %d/%d.', $voivodeship->value, $year, $year + 1));
                    }

                    $assignedVoivodeships[$voivodeship->value] = true;
                    $voivodeships[] = $voivodeship->value;
                }

                $periods[] = [
                    'start' => $period['start'],
                    'end' => $period['end'],
                    'voivodeships' => $voivodeships,
                ];
            }

            if (count($assignedVoivodeships) !== count(Voivodeship::cases())) {
                throw new \UnexpectedValueException(sprintf('School year %d/%d does not assign every Polish voivodeship exactly once.', $year, $year + 1));
            }

            $schedules[$year] = $periods;
        }

        ksort($schedules);

        return $this->schedules = $schedules;
    }
}
