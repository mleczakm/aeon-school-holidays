<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Mleczakm\AeonSchoolHolidays\MenUpdater\CalendarIndexParser;
use Mleczakm\AeonSchoolHolidays\MenUpdater\CandidateSelector;
use Mleczakm\AeonSchoolHolidays\MenUpdater\FeedItem;
use Mleczakm\AeonSchoolHolidays\MenUpdater\HtmlTextExtractor;
use Mleczakm\AeonSchoolHolidays\MenUpdater\MenPageFetcher;
use Mleczakm\AeonSchoolHolidays\MenUpdater\Updater;
use Mleczakm\AeonSchoolHolidays\MenUpdater\WinterScheduleDataset;
use Mleczakm\AeonSchoolHolidays\MenUpdater\WinterScheduleExtractor;
use Mleczakm\AeonSchoolHolidays\OfficialWinterBreakSchedule;
use Mleczakm\AeonSchoolHolidays\Voivodeship;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(CalendarIndexParser::class)]
#[CoversClass(HtmlTextExtractor::class)]
#[CoversClass(MenPageFetcher::class)]
#[CoversClass(CandidateSelector::class)]
#[CoversClass(WinterScheduleExtractor::class)]
#[CoversClass(WinterScheduleDataset::class)]
#[CoversClass(Updater::class)]
final class MenUpdaterTest extends TestCase
{
    private const string INDEX_URL = 'https://www.gov.pl/web/edukacja/kalendarz-roku-szkolnego';
    private const string SCHEDULE_URL = 'https://www.gov.pl/web/edukacja/terminy-ferii-zimowych-w-roku-szkolnym-20282029';
    private const string REVIEW_URL = 'https://www.gov.pl/web/edukacja/zmiana-organizacji-zajec-szkolnych';

    public function testItUpdatesRecognizedSchedulesAndQueuesOtherRelevantMessagesForReview(): void
    {
        $datasetPath = $this->temporaryDataset();
        $httpClient = $this->fetcher([
            self::INDEX_URL => $this->fixture('calendar-index.html'),
            self::SCHEDULE_URL => $this->fixture('winter-schedule.html'),
            self::REVIEW_URL => $this->fixture('review-candidate.html'),
        ]);
        $result = $this->updater($httpClient)->run(
            new \DateTimeImmutable('2027-06-01T00:00:00Z'),
            new WinterScheduleDataset($datasetPath),
        );

        self::assertSame(2, $result->scannedItems);
        self::assertTrue($result->datasetChanged);
        self::assertTrue($result->requiresReview());
        self::assertSame(['updated', 'review'], array_column($result->relevantItems, 'status'));

        $schedule = new OfficialWinterBreakSchedule($datasetPath);
        self::assertSame(range(2020, 2028), $schedule->supportedSchoolYears());
        self::assertSame('2029-01-15', $schedule->for(2028, Voivodeship::Masovian)->start->toString());
        self::assertSame('2029-02-25', $schedule->for(2028, Voivodeship::GreaterPoland)->end->toString());
    }

    public function testApplyingTheSameAnnouncementIsIdempotent(): void
    {
        $datasetPath = $this->temporaryDataset();
        $httpClient = $this->fetcher([
            self::INDEX_URL => $this->fixture('calendar-index.html'),
            self::SCHEDULE_URL => $this->fixture('winter-schedule.html'),
            self::REVIEW_URL => $this->fixture('review-candidate.html'),
        ]);
        $updater = $this->updater($httpClient);
        $since = new \DateTimeImmutable('2027-06-01T00:00:00Z');

        $updater->run($since, new WinterScheduleDataset($datasetPath));
        $second = $updater->run($since, new WinterScheduleDataset($datasetPath));

        self::assertFalse($second->datasetChanged);
        self::assertSame(['unchanged', 'review'], array_column($second->relevantItems, 'status'));
    }

    public function testItRejectsNonMenLinksBeforeFetchingThem(): void
    {
        $index = str_replace(
            '/web/edukacja/terminy-ferii-zimowych-w-roku-szkolnym-20282029',
            '//malicious.example.test/article',
            $this->fixture('calendar-index.html'),
        );
        $datasetPath = $this->temporaryDataset();

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('non-MEN article');

        $this->updater($this->fetcher([self::INDEX_URL => $index]))->run(
            new \DateTimeImmutable('2027-06-01T00:00:00Z'),
            new WinterScheduleDataset($datasetPath),
        );
    }

    public function testExtractorReadsTheCurrentMenAnnouncementShape(): void
    {
        $item = new FeedItem(
            'Terminy ferii zimowych w roku szkolnym 2028/2029',
            self::SCHEDULE_URL,
            new \DateTimeImmutable('2027-06-02T10:00:00+02:00'),
        );
        $text = (new HtmlTextExtractor())->extractMain($this->fixture('winter-schedule.html'));
        $schedule = (new WinterScheduleExtractor())->extract($item, $text);

        self::assertSame(2028, $schedule->schoolYearStart);
        self::assertCount(3, $schedule->periods);
        self::assertSame(['PL-02', 'PL-14', 'PL-16', 'PL-20', 'PL-32'], $schedule->periods[0]['voivodeships']);
    }

    private function updater(MenPageFetcher $httpClient): Updater
    {
        return new Updater(
            $httpClient,
            new CalendarIndexParser($httpClient),
            new HtmlTextExtractor(),
            new CandidateSelector(),
            new WinterScheduleExtractor(),
        );
    }

    /** @param array<string, string> $responses */
    private function fetcher(array $responses): MenPageFetcher
    {
        return new MenPageFetcher(new InMemoryPsr18Client($responses), new HttpFactory());
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__ . '/Fixtures/men/' . $name);
        self::assertIsString($contents);

        return $contents;
    }

    private function temporaryDataset(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'men-dataset-');
        self::assertIsString($path);
        self::assertTrue(copy(dirname(__DIR__) . '/resources/polish-winter-breaks.json', $path));

        return $path;
    }
}

/** @internal */
final readonly class InMemoryPsr18Client implements ClientInterface
{
    /** @param array<string, string> $responses */
    public function __construct(private array $responses) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $url = (string) $request->getUri();
        $body = $this->responses[$url] ?? throw new \RuntimeException(sprintf('Unexpected test URL: %s.', $url));

        return new Response(200, [], $body);
    }
}
