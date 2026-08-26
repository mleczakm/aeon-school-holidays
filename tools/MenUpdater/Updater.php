<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\MenUpdater;

final readonly class Updater
{
    public function __construct(
        private MenPageFetcher $httpClient,
        private CalendarIndexParser $indexParser,
        private HtmlTextExtractor $htmlTextExtractor,
        private CandidateSelector $candidateSelector,
        private WinterScheduleExtractor $winterScheduleExtractor,
    ) {}

    public function run(\DateTimeImmutable $since, WinterScheduleDataset $dataset): UpdateResult
    {
        $itemsByUrl = [];

        foreach ($this->indexParser->items() as $item) {
            if ($item->publishedAt > $since) {
                $this->assertOfficialArticleUrl($item->url);
                $itemsByUrl[$item->url] = $item;
            }
        }

        uasort($itemsByUrl, static fn(FeedItem $left, FeedItem $right): int => $left->publishedAt <=> $right->publishedAt);
        $relevantItems = [];
        $datasetChanged = false;

        foreach ($itemsByUrl as $item) {
            $articleText = $this->htmlTextExtractor->extractMain($this->httpClient->get($item->url));

            if (!$this->candidateSelector->isRelevant($item, $articleText)) {
                continue;
            }

            if ($this->winterScheduleExtractor->supports($item)) {
                $changed = $dataset->apply($this->winterScheduleExtractor->extract($item, $articleText));
                $datasetChanged = $datasetChanged || $changed;
                $status = $changed ? 'updated' : 'unchanged';
            } else {
                $status = 'review';
            }

            $relevantItems[] = [
                'title' => $item->title,
                'url' => $item->url,
                'published_at' => $item->publishedAt->format(DATE_ATOM),
                'status' => $status,
            ];
        }

        if ($datasetChanged) {
            $dataset->save();
        }

        return new UpdateResult($since, count($itemsByUrl), $relevantItems, [], $datasetChanged);
    }

    private function assertOfficialArticleUrl(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        if ($host !== 'www.gov.pl' || !is_string($path) || !str_starts_with($path, '/web/edukacja/')) {
            throw new \UnexpectedValueException(sprintf('The MEN calendar index linked to a non-MEN article: %s.', $url));
        }
    }
}
