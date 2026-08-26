<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\MenUpdater;

final readonly class UpdateResult
{
    /**
     * @param list<array{title: string, url: string, published_at: string, status: string}> $relevantItems
     * @param list<string> $warnings
     */
    public function __construct(
        public \DateTimeImmutable $since,
        public int $scannedItems,
        public array $relevantItems,
        public array $warnings,
        public bool $datasetChanged,
    ) {}

    public function requiresReview(): bool
    {
        foreach ($this->relevantItems as $item) {
            if ($item['status'] === 'review') {
                return true;
            }
        }

        return $this->warnings !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'since' => $this->since->format(DATE_ATOM),
            'scanned_items' => $this->scannedItems,
            'relevant_items' => $this->relevantItems,
            'warnings' => $this->warnings,
            'dataset_changed' => $this->datasetChanged,
            'requires_review' => $this->requiresReview(),
        ];
    }
}
