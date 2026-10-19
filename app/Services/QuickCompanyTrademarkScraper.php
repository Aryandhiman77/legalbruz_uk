<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class QuickCompanyTrademarkScraper
{
    private string $baseUrl = 'https://www.quickcompany.in';

    private array $headers = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.9',
    ];

    public function scrapeTrademark(string $keyword, int $limit = 20, int $delayMs = 1000): array
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return [];
        }

        $sourceUrl = $this->baseUrl.'/trademarks?q='.rawurlencode($keyword);
        $searchResults = array_slice($this->extractSearchResults($this->fetchHtml($sourceUrl), $keyword, $sourceUrl), 0, $limit);
        $results = [];

        foreach ($searchResults as $item) {
            try {
                $detailData = $this->extractTrademarkDetail($this->fetchHtml($item['detail_url']), $item['detail_url'], $item);
                $result = array_merge(['search_keyword' => $keyword], $detailData, ['source_url' => $sourceUrl]);
                $result['validation_errors'] = $this->validateResult($result);
                $results[] = $result;

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            } catch (\Throwable $exception) {
                $results[] = [
                    'search_keyword' => $keyword,
                    'application_id' => $item['application_id'] ?? '',
                    'trademark_name' => $item['trademark_name'] ?? '',
                    'status' => '',
                    'class' => '',
                    'type' => '',
                    'proprietor' => '',
                    'detail_url' => $item['detail_url'] ?? '',
                    'source_url' => $sourceUrl,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function fetchHtml(string $url): string
    {
        $response = Http::timeout(30)->withHeaders($this->headers)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Request failed with HTTP status: '.$response->status());
        }

        return $response->body();
    }

    private function extractSearchResults(string $html, string $keyword, string $sourceUrl): array
    {
        $crawler = new Crawler($html);
        $results = [];
        $seenIds = [];

        $crawler->filter('a[href*="/trademarks/"]')->each(function (Crawler $node) use (&$results, &$seenIds, $keyword, $sourceUrl): void {
            $href = (string) ($node->attr('href') ?? '');

            if (! preg_match('~/trademarks/(\d+)-([^/?#]+)~i', $href, $matches)) {
                return;
            }

            $applicationId = $matches[1];

            if (isset($seenIds[$applicationId])) {
                return;
            }

            $seenIds[$applicationId] = true;
            $detailUrl = $this->absoluteUrl($href);
            $linkText = $this->cleanText($node->text(''));

            $results[] = [
                'search_keyword' => $keyword,
                'application_id' => $applicationId,
                'trademark_name' => $linkText ?: $this->getNameFromDetailUrl($detailUrl),
                'detail_url' => $detailUrl,
                'source_url' => $sourceUrl,
            ];
        });

        return $results;
    }

    private function extractTrademarkDetail(string $html, string $detailUrl, array $fallback = []): array
    {
        $crawler = new Crawler($html);
        $pageText = $this->cleanText($crawler->filter('body')->count() ? $crawler->filter('body')->text(' ') : $html);
        $applicationId = $this->getValueAfterLabel($pageText, 'Application ID');

        if ($applicationId === '' || ! preg_match('/^\d+$/', $applicationId)) {
            $applicationId = ($fallback['application_id'] ?? '') ?: $this->applicationIdFromUrl($detailUrl);
        }

        return [
            'application_id' => $this->cleanText($applicationId),
            'application_date' => $this->getValueAfterLabel($pageText, 'Date of Application'),
            'trademark_name' => $this->extractTrademarkName($crawler, $detailUrl, (string) ($fallback['trademark_name'] ?? '')),
            'proprietor' => $this->getValueAfterLabel($pageText, 'Proprietor'),
            'status' => $this->extractStatus($pageText),
            'sub_status' => $this->getValueAfterLabel($pageText, 'Sub Status'),
            'class' => $this->getValueAfterLabel($pageText, 'Classes'),
            'type' => $this->getValueAfterLabel($pageText, 'Type'),
            'attorney' => $this->getValueAfterLabel($pageText, 'Attorney') ?: $this->getValueAfterLabel($pageText, 'Agent'),
            'state' => $this->getValueAfterLabel($pageText, 'State'),
            'country' => $this->getValueAfterLabel($pageText, 'Country'),
            'filing_mode' => $this->getValueAfterLabel($pageText, 'Filing Mode'),
            'branch_office' => $this->getValueAfterLabel($pageText, 'Branch Office'),
            'ip_office' => $this->getValueAfterLabel($pageText, 'IP Office'),
            'used_since' => $this->getValueAfterLabel($pageText, 'Used Since'),
            'valid_upto' => $this->getValueAfterLabel($pageText, 'Valid / Upto') ?: $this->getValueAfterLabel($pageText, 'Valid Upto'),
            'description' => $this->getValueAfterLabel($pageText, 'Description'),
            'image_url' => $this->extractImageUrl($crawler),
            'detail_url' => $detailUrl,
        ];
    }

    private function getValueAfterLabel(string $text, string $label): string
    {
        $labels = [
            'Application ID', 'Status', 'Sub Status', 'Date of Application', 'Classes', 'Proprietor', 'Attorney',
            'Agent', 'Type', 'State', 'Country', 'Filing Mode', 'Branch Office', 'IP Office', 'Used Since',
            'Valid / Upto', 'Valid Upto', 'Description', 'PR Details', 'Uploaded Documents',
            'Correspondence and Notices', 'Frequently Asked Questions',
        ];
        $nextLabels = collect($labels)
            ->reject(fn (string $item): bool => mb_strtolower($item) === mb_strtolower($label))
            ->map(fn (string $item): string => preg_quote($item, '/'))
            ->implode('|');

        preg_match('/'.preg_quote($label, '/').'\s+(.+?)\s*(?='.$nextLabels.'|$)/iu', $text, $matches);

        $value = $this->cleanText($matches[1] ?? '');
        $value = preg_replace('/^Input\s*/i', '', $value) ?? '';
        $value = preg_replace('/^\[?Button:\s*/i', '', $value) ?? '';
        $value = preg_replace('/\]?$/i', '', $value) ?? '';
        $value = $this->cleanText($value);

        foreach ($labels as $knownLabel) {
            if (mb_strtolower($knownLabel) !== mb_strtolower($label) && preg_match('/^'.preg_quote($knownLabel, '/').'\b/iu', $value)) {
                return '';
            }
        }

        return $value;
    }

    private function extractStatus(string $pageText): string
    {
        if (preg_match('/trademark application status is\s+(.+?)\./i', $pageText, $matches)) {
            return $this->cleanText($matches[1] ?? '');
        }

        return $this->getValueAfterLabel($pageText, 'Status');
    }

    private function extractTrademarkName(Crawler $crawler, string $detailUrl, string $fallbackName = ''): string
    {
        $slugName = $this->getNameFromDetailUrl($detailUrl);
        $badHeadingWords = [
            'Trademark Information', 'Uploaded Documents', 'Correspondence and Notices', 'Frequently Asked Questions',
            'Get Free WhatsApp Updates', 'Hearings Update', 'Examination Report', 'Recieve Updates on WhatsApp',
            'Receive Updates on WhatsApp',
        ];
        $candidates = [];

        $crawler->filter('h1, h2, h3')->each(function (Crawler $node) use (&$candidates, $badHeadingWords): void {
            $text = $this->cleanText($node->text(''));
            $text = preg_replace('/Trademark Information|Image\s*\(DEVICE\)|Device mark.*$|Word mark.*$/iu', '', $text) ?? '';
            $text = $this->cleanText($text);

            if ($text === '') {
                return;
            }

            foreach ($badHeadingWords as $badHeadingWord) {
                if (Str::contains(mb_strtolower($text), mb_strtolower($badHeadingWord))) {
                    return;
                }
            }

            $candidates[] = $text;
        });

        if ($slugName !== '') {
            $slugLower = mb_strtolower($slugName);
            foreach ($candidates as $candidate) {
                $candidateLower = mb_strtolower($candidate);
                if (Str::contains($candidateLower, $slugLower) || Str::contains($slugLower, $candidateLower)) {
                    return $candidate;
                }
            }
        }

        return $candidates[0] ?? ($fallbackName ?: $slugName);
    }

    private function extractImageUrl(Crawler $crawler): string
    {
        $imageUrl = '';

        $crawler->filter('img')->each(function (Crawler $node) use (&$imageUrl): ?bool {
            $src = (string) ($node->attr('src') ?: $node->attr('data-src') ?: '');
            $alt = (string) ($node->attr('alt') ?: '');

            if (Str::contains(mb_strtolower($src.' '.$alt), ['trademark', 'device', 'logo'])) {
                $imageUrl = $this->absoluteUrl($src);
                return false;
            }

            return null;
        });

        return $imageUrl;
    }

    private function validateResult(array $result): array
    {
        $errors = [];

        foreach (['application_id', 'trademark_name', 'status', 'class', 'type', 'proprietor'] as $field) {
            if (empty($result[$field])) {
                $errors[] = 'Missing '.$field;
            }
        }

        $idFromUrl = $this->applicationIdFromUrl((string) ($result['detail_url'] ?? ''));

        if ($idFromUrl !== '' && ! empty($result['application_id']) && $idFromUrl !== (string) $result['application_id']) {
            $errors[] = "Application ID mismatch. URL={$idFromUrl}, Data={$result['application_id']}";
        }

        return $errors;
    }

    private function absoluteUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return Str::startsWith($url, '/') ? $this->baseUrl.$url : $this->baseUrl.'/'.ltrim($url, '/');
    }

    private function getNameFromDetailUrl(string $url): string
    {
        if (! preg_match('~/trademarks/\d+-([^/?#]+)~i', $url, $matches)) {
            return '';
        }

        return collect(explode(' ', str_replace('-', ' ', $matches[1])))
            ->filter()
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)).mb_strtolower(mb_substr($word, 1)))
            ->implode(' ');
    }

    private function applicationIdFromUrl(string $url): string
    {
        preg_match('~/trademarks/(\d+)-~i', $url, $matches);

        return $matches[1] ?? '';
    }

    private function cleanText(?string $text = ''): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $text));
    }
}
