<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\MenUpdater;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/** Fetches MEN pages over any PSR-18 HTTP client, enforcing the shared HTTPS-only and header policy. */
final readonly class MenPageFetcher
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
    ) {}

    public function get(string $url): string
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new \InvalidArgumentException(sprintf('Only HTTPS sources are allowed: %s.', $url));
        }

        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Accept', 'application/rss+xml, application/xml, text/html;q=0.9')
            ->withHeader('User-Agent', 'mleczakm/aeon-school-holidays MEN updater');

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $error) {
            throw new \RuntimeException(sprintf('Unable to fetch %s.', $url), previous: $error);
        }

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException(sprintf('Unable to fetch %s: HTTP %d.', $url, $response->getStatusCode()));
        }

        return (string) $response->getBody();
    }
}
