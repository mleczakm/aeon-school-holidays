<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\MenUpdater;

final class RssFeedParser
{
    /** @return list<FeedItem> */
    public function parse(string $xml): array
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            if (!$document->loadXML($xml, LIBXML_NONET)) {
                throw new \UnexpectedValueException('The MEN feed is not valid XML.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $items = [];

        foreach ($document->getElementsByTagName('item') as $item) {
            $title = $this->requiredText($item, 'title');
            $url = $this->requiredText($item, 'link');
            $publishedAt = $this->requiredText($item, 'pubDate');

            try {
                $date = new \DateTimeImmutable($publishedAt);
            } catch (\Exception $error) {
                throw new \UnexpectedValueException(sprintf('Invalid RSS publication date: %s.', $publishedAt), previous: $error);
            }

            $items[] = new FeedItem($title, $url, $date);
        }

        if ($items === []) {
            throw new \UnexpectedValueException('The MEN feed contains no items.');
        }

        return $items;
    }

    private function requiredText(\DOMElement $item, string $element): string
    {
        $value = trim($item->getElementsByTagName($element)->item(0)?->textContent ?? '');

        if ($value === '') {
            throw new \UnexpectedValueException(sprintf('An RSS item is missing %s.', $element));
        }

        return $value;
    }
}
