<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$topLevelJsonPageListPage = static function (int $page, string $lead, string $url, string $tail): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'refs' => [[
            'page' => $page,
            'idx' => 9,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => "top-level JSON page-list ref payload {$page} must stay hidden",
        ]],
        'raw_page_payload' => "top-level JSON page-list page payload {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 144.0, 500.0, 158.0],
            'raw_block_payload' => "top-level JSON page-list block payload {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 144.0, 500.0, 158.0],
                'raw_line_payload' => "top-level JSON page-list line payload {$page} must stay hidden",
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 190.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'top-level JSON link',
                        'bbox' => [190.0, 144.0, 335.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => "top-level JSON page-list span payload {$page} must stay hidden",
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [335.0, 144.0, 500.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

return [
    'unwraps top-level BOM JSON pages strings before selected pdftext dictionary import' => static function (
        TestRunner $t
    ) use ($topLevelJsonPageListPage): void {
        $cover = $topLevelJsonPageListPage(10100, 'Skipped top-level pages cover ', 'https://example.com/top-pages-cover', ' should not import');
        $selected = $topLevelJsonPageListPage(10101, 'Top-level pages JSON ', 'https://example.com/top-pages-current', ' stays selected');
        $appendix = $topLevelJsonPageListPage(10102, 'Skipped top-level pages appendix ', 'https://example.com/top-pages-appendix', ' should not import');
        $json = "\xEF\xBB\xBF" . json_encode([
            10100 => $cover,
            10101 => $selected,
            10102 => $appendix,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([
            'pages' => $json,
            'metadata' => [
                'source' => 'legacy adapter top-level pages JSON cache',
                'raw_private_payload' => 'top-level pages envelope metadata payload must stay hidden',
            ],
            'raw_adapter_payload' => 'top-level pages adapter payload must stay hidden',
        ], maxPages: 1, startPage: 1);

        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(3, $document['metadata']['source_pages']);
        $t->same(10101, $page['pnum']);
        $t->same('Top-level pages JSON [top-level JSON link](https://example.com/top-pages-current) stays selected', $blocks[0]['text']);
        $t->same('https://example.com/top-pages-current', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-10101-9', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Skipped top-level pages cover'));
        $t->true(!str_contains($encoded, 'Skipped top-level pages appendix'));
        $t->true(!str_contains($encoded, 'top-level pages envelope metadata payload'));
        $t->true(!str_contains($encoded, 'top-level pages adapter payload'));
        $t->true(!str_contains($encoded, 'top-level JSON page-list ref payload 10101'));
        $t->true(!str_contains($encoded, 'top-level JSON page-list page payload 10101'));
        $t->true(!str_contains($encoded, 'top-level JSON page-list block payload 10101'));
        $t->true(!str_contains($encoded, 'top-level JSON page-list span payload 10101'));
    },
    'unwraps top-level raw JSON page map aliases before selected pdftext dictionary import' => static function (
        TestRunner $t
    ) use ($topLevelJsonPageListPage): void {
        foreach ([
            'page_map' => [11100, 11101, 'page_map', 'https://example.com/top-page-map-current'],
            'pageMap' => [12100, 12101, 'pageMap', 'https://example.com/top-pageMap-current'],
        ] as $pageListKey => [$coverPage, $selectedPage, $label, $url]) {
            $cover = $topLevelJsonPageListPage($coverPage, "Skipped top-level {$label} cover ", "https://example.com/{$label}-cover", ' should not import');
            $selected = $topLevelJsonPageListPage($selectedPage, "Top-level {$label} JSON ", $url, ' stays selected');
            $appendix = $topLevelJsonPageListPage($selectedPage + 1, "Skipped top-level {$label} appendix ", "https://example.com/{$label}-appendix", ' should not import');
            $json = json_encode([
                (string) $coverPage => $cover,
                (string) $selectedPage => $selected,
                (string) ($selectedPage + 1) => $appendix,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $document = (new PdfTextDocumentExtractor())->getTextBlocks([
                $pageListKey => $json,
                'metadata' => [
                    'source' => "legacy adapter top-level {$label} JSON cache",
                    'raw_private_payload' => "top-level {$label} metadata payload must stay hidden",
                ],
                'raw_adapter_payload' => "top-level {$label} adapter payload must stay hidden",
            ], maxPages: 1, startPage: 1);

            $page = $document['pages'][0];
            $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
            $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

            $t->same([1], $document['page_range']);
            $t->same(3, $document['metadata']['source_pages']);
            $t->same($selectedPage, $page['pnum']);
            $t->same("Top-level {$label} JSON [top-level JSON link]({$url}) stays selected", $blocks[0]['text']);
            $t->same($url, $page['blocks'][0]['lines'][0]['spans'][1]['url']);
            $t->same("#page-{$selectedPage}-9", $page['pdftext_source']['refs'][0]['url'] ?? null);
            $t->true(!str_contains($encoded, "Skipped top-level {$label} cover"));
            $t->true(!str_contains($encoded, "Skipped top-level {$label} appendix"));
            $t->true(!str_contains($encoded, "top-level {$label} metadata payload"));
            $t->true(!str_contains($encoded, "top-level {$label} adapter payload"));
            $t->true(!str_contains($encoded, "top-level JSON page-list page payload {$selectedPage}"));
            $t->true(!str_contains($encoded, "top-level JSON page-list line payload {$selectedPage}"));
            $t->true(!str_contains($encoded, "top-level JSON page-list span payload {$selectedPage}"));
        }
    },
];
