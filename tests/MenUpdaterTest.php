<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\Tests;

use Mleczakm\AeonSchoolHolidays\MenUpdater\CandidateSelector;
use Mleczakm\AeonSchoolHolidays\MenUpdater\FeedItem;
use Mleczakm\AeonSchoolHolidays\MenUpdater\HtmlTextExtractor;
use Mleczakm\AeonSchoolHolidays\MenUpdater\HttpClient;
use Mleczakm\AeonSchoolHolidays\MenUpdater\RssFeedParser;
use Mleczakm\AeonSchoolHolidays\MenUpdater\Updater;
use Mleczakm\AeonSchoolHolidays\MenUpdater\WinterScheduleDataset;
use Mleczakm\AeonSchoolHolidays\MenUpdater\WinterScheduleExtractor;
use Mleczakm\AeonSchoolHolidays\OfficialWinterBreakSchedule;
use Mleczakm\AeonSchoolHolidays\Voivodeship;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RssFeedParser::class)]
#[CoversClass(HtmlTextExtractor::class)]
#[CoversClass(CandidateSelector::class)]
#[CoversClass(WinterScheduleExtractor::class)]
#[CoversClass(WinterScheduleDataset::class)]
#[CoversClass(Updater::class)]
final class MenUpdaterTest extends TestCase
{
    private const string FEED_URL = 'https://feeds.example.test/men.xml';
    private const string SCHEDULE_URL = 'https://www.gov.pl/web/edukacja/terminy-ferii-zimowych-w-roku-szkolnym-20282029';
    private const string REVIEW_URL = 'https://www.gov.pl/web/edukacja/zmiana-organizacji-zajec-szkolnych';

    public function testItUpdatesRecognizedSchedulesAndQueuesOtherRelevantMessagesForReview(): void
    {
        $datasetPath = $this->temporaryDataset();
        $httpClient = new InMemoryHttpClient([
            self::FEED_URL => $this->fixture('feed.xml'),
            self::SCHEDULE_URL => $this->fixture('winter-schedule.html'),
            self::REVIEW_URL => $this->fixture('review-candidate.html'),
        ]);
        $result = $this->updater($httpClient)->run(
            [self::FEED_URL],
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
        $httpClient = new InMemoryHttpClient([
            self::FEED_URL => $this->fixture('feed.xml'),
            self::SCHEDULE_URL => $this->fixture('winter-schedule.html'),
            self::REVIEW_URL => $this->fixture('review-candidate.html'),
        ]);
        $updater = $this->updater($httpClient);
        $since = new \DateTimeImmutable('2027-06-01T00:00:00Z');

        $updater->run([self::FEED_URL], $since, new WinterScheduleDataset($datasetPath));
        $second = $updater->run([self::FEED_URL], $since, new WinterScheduleDataset($datasetPath));

        self::assertFalse($second->datasetChanged);
        self::assertSame(['unchanged', 'review'], array_column($second->relevantItems, 'status'));
    }

    public function testItRejectsNonMenLinksBeforeFetchingThem(): void
    {
        $feed = str_replace(self::SCHEDULE_URL, 'https://malicious.example.test/article', $this->fixture('feed.xml'));
        $datasetPath = $this->temporaryDataset();

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('non-MEN article');

        $this->updater(new InMemoryHttpClient([self::FEED_URL => $feed]))->run(
            [self::FEED_URL],
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

    private function updater(HttpClient $httpClient): Updater
    {
        return new Updater(
            $httpClient,
            new RssFeedParser(),
            new HtmlTextExtractor(),
            new CandidateSelector(),
            new WinterScheduleExtractor(),
        );
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
final readonly class InMemoryHttpClient implements HttpClient
{
    /** @param array<string, string> $responses */
    public function __construct(private array $responses) {}

    public function get(string $url): string
    {
        return $this->responses[$url] ?? throw new \RuntimeException(sprintf('Unexpected test URL: %s.', $url));
    }
}
