<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\MenUpdater;

final class HtmlTextExtractor
{
    public function extractMain(string $html): string
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            if (!$document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                throw new \UnexpectedValueException('The MEN article is not valid HTML.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $main = $document->getElementsByTagName('main')->item(0);

        if (!$main instanceof \DOMElement) {
            throw new \UnexpectedValueException('The MEN article does not contain a main element.');
        }

        $text = preg_replace('/\s+/u', ' ', html_entity_decode($main->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (!is_string($text) || trim($text) === '') {
            throw new \UnexpectedValueException('The MEN article main element is empty.');
        }

        return trim($text);
    }
}
