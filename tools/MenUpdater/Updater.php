<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\MenUpdater;

final readonly class Updater
{
    public function __construct(
        private HttpClient $httpClient,
        private RssFeedParser $feedParser,
        private HtmlTextExtractor $htmlTextExtractor,
        private CandidateSelector $candidateSelector,
        private WinterScheduleExtractor $winterScheduleExtractor,
    ) {}

    /** @param non-empty-list<string> $feedUrls */
    public function run(array $feedUrls, \DateTimeImmutable $since, WinterScheduleDataset $dataset): UpdateResult
    {
        $itemsByUrl = [];
        $warnings = [];

        foreach ($feedUrls as $feedUrl) {
            $items = $this->feedParser->parse($this->httpClient->get($feedUrl));
            $oldest = null;

            foreach ($items as $item) {
                $oldest = $oldest === null || $item->publishedAt < $oldest ? $item->publishedAt : $oldest;

                if ($item->publishedAt > $since) {
                    $this->assertOfficialArticleUrl($item->url);
                    $itemsByUrl[$item->url] = $item;
                }
            }

            if (count($items) >= 10 && $oldest instanceof \DateTimeImmutable && $oldest > $since) {
                $warnings[] = sprintf('Feed %s may be truncated: its oldest available item (%s) is newer than the previous successful run.', $feedUrl, $oldest->format(DATE_ATOM));
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

        return new UpdateResult($since, count($itemsByUrl), $relevantItems, $warnings, $datasetChanged);
    }

    private function assertOfficialArticleUrl(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        if ($host !== 'www.gov.pl' || !is_string($path) || !str_starts_with($path, '/web/edukacja/')) {
            throw new \UnexpectedValueException(sprintf('The feed linked to a non-MEN article: %s.', $url));
        }
    }
}
