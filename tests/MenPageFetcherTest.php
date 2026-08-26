<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Mleczakm\AeonSchoolHolidays\MenUpdater\MenPageFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(MenPageFetcher::class)]
final class MenPageFetcherTest extends TestCase
{
    public function testItReturnsTheResponseBody(): void
    {
        $fetcher = new MenPageFetcher(new FakeClient(new Response(200, [], 'hello')), new HttpFactory());

        self::assertSame('hello', $fetcher->get('https://www.gov.pl/web/edukacja/example'));
    }

    public function testItRejectsNonHttpsUrls(): void
    {
        $fetcher = new MenPageFetcher(new FakeClient(new Response(200)), new HttpFactory());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only HTTPS sources are allowed');

        $fetcher->get('http://www.gov.pl/web/edukacja/example');
    }

    public function testItTranslatesClientExceptionsIntoRuntimeExceptions(): void
    {
        $fetcher = new MenPageFetcher(new FailingClient(), new HttpFactory());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to fetch https://www.gov.pl/web/edukacja/example');

        $fetcher->get('https://www.gov.pl/web/edukacja/example');
    }

    public function testItRejectsHttpErrorResponses(): void
    {
        $fetcher = new MenPageFetcher(new FakeClient(new Response(404)), new HttpFactory());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 404');

        $fetcher->get('https://www.gov.pl/web/edukacja/example');
    }
}

/** @internal */
final readonly class FakeClient implements ClientInterface
{
    public function __construct(private ResponseInterface $response) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}

/** @internal */
final class FailingClient implements ClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        throw new class extends \RuntimeException implements ClientExceptionInterface {};
    }
}
