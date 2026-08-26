<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\MenUpdater;

final class WinterScheduleDataset
{
    /** @var array<string, mixed> */
    private array $data;

    public function __construct(private readonly string $path)
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read winter schedule dataset: %s.', $path));
        }

        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($decoded) || ($decoded['schema_version'] ?? null) !== 1 || !is_array($decoded['schedules'] ?? null)) {
            throw new \UnexpectedValueException('The winter schedule dataset has an unsupported structure.');
        }

        $this->data = $decoded;
    }

    public function apply(WinterSchedule $schedule): bool
    {
        /** @var array<string, mixed> $schedules */
        $schedules = $this->data['schedules'];
        $key = (string) $schedule->schoolYearStart;
        $replacement = [
            'source_url' => $schedule->sourceUrl,
            'published_at' => $schedule->publishedAt->format('Y-m-d'),
            'periods' => $schedule->periods,
        ];

        $existing = is_array($schedules[$key] ?? null) ? $schedules[$key]['periods'] ?? null : null;

        if (is_array($existing) && $this->normalize($existing) === $this->normalize($schedule->periods)) {
            return false;
        }

        $schedules[$key] = $replacement;
        uksort($schedules, static fn(string $left, string $right): int => (int) $left <=> (int) $right);
        $this->data['schedules'] = $schedules;

        return true;
    }

    public function save(): void
    {
        $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";

        if (file_put_contents($this->path, $json) === false) {
            throw new \RuntimeException(sprintf('Unable to write winter schedule dataset: %s.', $this->path));
        }
    }

    /**
     * Sorts each period's voivodeship list so equivalent schedules compare equal
     * regardless of the order MEN's announcement prose (and thus extraction) lists them in.
     */
    private function normalize(mixed $periods): mixed
    {
        if (!is_array($periods)) {
            return $periods;
        }

        return array_map(static function (mixed $period): mixed {
            if (!is_array($period) || !is_array($period['voivodeships'] ?? null)) {
                return $period;
            }

            $voivodeships = $period['voivodeships'];
            sort($voivodeships);
            $period['voivodeships'] = $voivodeships;

            return $period;
        }, $periods);
    }
}
