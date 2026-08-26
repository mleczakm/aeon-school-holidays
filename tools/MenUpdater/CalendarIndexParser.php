<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\MenUpdater;

/** Parses the MEN school-year calendar index, which lists the full announcement history (unlike the rolling RSS feeds). */
final readonly class CalendarIndexParser
{
    private const string BASE_URL = 'https://www.gov.pl';
    private const string INDEX_PATH = '/web/edukacja/kalendarz-roku-szkolnego';
    private const int PAGE_SIZE = 10;
    private const int MAX_PAGES = 20;

    public function __construct(private HttpClient $httpClient) {}

    /** @return list<FeedItem> */
    public function items(): array
    {
        $items = [];
        $page = 1;
        $totalPages = 1;

        do {
            $document = $this->fetchPage($page);
            array_push($items, ...$this->parseItems($document));
            $totalPages = $this->totalPages($document, $totalPages);
            ++$page;
        } while ($page <= $totalPages && $page <= self::MAX_PAGES);

        if ($page <= $totalPages) {
            throw new \UnexpectedValueException(sprintf('The MEN calendar index reports %d pages, exceeding the safety cap of %d.', $totalPages, self::MAX_PAGES));
        }

        return $items;
    }

    private function fetchPage(int $page): \Dom\HTMLDocument
    {
        $url = self::BASE_URL . self::INDEX_PATH;

        if ($page > 1) {
            $url .= sprintf('?page=%d&size=%d', $page, self::PAGE_SIZE);
        }

        return \Dom\HTMLDocument::createFromString($this->httpClient->get($url), LIBXML_NOERROR);
    }

    /** @return list<FeedItem> */
    private function parseItems(\Dom\HTMLDocument $document): array
    {
        $rows = $document->querySelectorAll('main .art-prev li');

        if ($rows->length === 0) {
            throw new \UnexpectedValueException('The MEN calendar index contains no entries.');
        }

        $items = [];

        foreach ($rows as $row) {
            $dateElement = $row->querySelector('.date');
            $link = $row->querySelector('.title a');

            if (!$dateElement instanceof \Dom\Element || !$link instanceof \Dom\Element) {
                throw new \UnexpectedValueException('The MEN calendar index contains an entry without a date or a link.');
            }

            $href = $link->getAttribute('href');

            if ($href === null) {
                throw new \UnexpectedValueException('The MEN calendar index contains a link without an href.');
            }

            $items[] = new FeedItem(
                trim($link->textContent),
                self::BASE_URL . $href,
                $this->parseDate(trim($dateElement->textContent)),
            );
        }

        return $items;
    }

    private function totalPages(\Dom\HTMLDocument $document, int $fallback): int
    {
        $link = $document->querySelector('#js-pagination-pages-count');
        $text = $link instanceof \Dom\Element ? trim($link->textContent) : '';

        return ctype_digit($text) ? (int) $text : $fallback;
    }

    private function parseDate(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!d.m.Y', $value, new \DateTimeZone('UTC'));

        if ($date === false) {
            throw new \UnexpectedValueException(sprintf('Unable to parse a MEN calendar index date: %s.', $value));
        }

        return $date;
    }
}
