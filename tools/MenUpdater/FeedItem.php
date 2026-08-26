<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\MenUpdater;

final readonly class FeedItem
{
    public function __construct(
        public string $title,
        public string $url,
        public \DateTimeImmutable $publishedAt,
    ) {}
}
