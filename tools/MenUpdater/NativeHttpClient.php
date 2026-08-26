<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\MenUpdater;

final class NativeHttpClient implements HttpClient
{
    public function get(string $url): string
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new \InvalidArgumentException(sprintf('Only HTTPS sources are allowed: %s.', $url));
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Accept: application/rss+xml, application/xml, text/html;q=0.9',
                    'User-Agent: mleczakm/aeon-school-holidays MEN updater',
                ]),
                'timeout' => 30,
                'follow_location' => 1,
                'max_redirects' => 5,
            ],
        ]);
        $contents = @file_get_contents($url, false, $context);

        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to fetch %s.', $url));
        }

        return $contents;
    }
}
