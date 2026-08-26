<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\MenUpdater;

final class CandidateSelector
{
    private const array KEYWORDS = [
        'ferie',
        'ferii',
        'wakacje',
        'przerwa świąteczna',
        'dni wolne od zajęć',
        'dzień wolny od zajęć',
        'kalendarz roku szkolnego',
        'zawieszenie zajęć',
        'odwołanie zajęć',
        'wolne od szkoły',
    ];

    public function isRelevant(FeedItem $item, string $articleText): bool
    {
        $haystack = mb_strtolower($item->title . ' ' . $articleText, 'UTF-8');

        foreach (self::KEYWORDS as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
