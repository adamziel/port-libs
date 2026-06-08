<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$jsonListEntryPdftextPage = static function (int $page, string $lead, string $url, string $tail): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'refs' => [[
            'page' => $page,
            'idx' => 4,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => "raw ref payload for {$page} must stay hidden",
        ]],
        'raw_page_payload' => "raw JSON list-entry page payload {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 144.0, 430.0, 158.0],
            'raw_block_payload' => "raw JSON list-entry block payload {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 144.0, 430.0, 158.0],
                'raw_line_payload' => "raw JSON list-entry line payload {$page} must stay hidden",
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 160.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'JSON list-entry link',
                        'bbox' => [160.0, 144.0, 280.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => "raw JSON list-entry span payload {$page} must stay hidden",
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [280.0, 144.0, 430.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

return [
    'unwraps raw JSON pdftext page strings inside selected page-list entries at the core boundary' => static function (
        TestRunner $t
    ) use ($jsonListEntryPdftextPage): void {
        $cover = $jsonListEntryPdftextPage(1200, 'Skipped raw JSON list-entry cover ', 'https://example.com/json-list-cover', ' should not import');
        $selected = $jsonListEntryPdftextPage(1201, 'Raw JSON list-entry page ', 'https://example.com/json-list-current', ' stays selected');
        $appendix = $jsonListEntryPdftextPage(1202, 'Skipped raw JSON list-entry appendix ', 'https://example.com/json-list-appendix', ' should not import');

        $document = (new PdfTextDocumentExtractor())->getTextBlocks(
            [
                json_encode($cover, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                json_encode($selected, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                json_encode($appendix, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ],
            maxPages: 1,
            startPage: 1
        );
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(3, $document['metadata']['source_pages']);
        $t->same(1201, $page['pnum']);
        $t->same('Raw JSON list-entry page [JSON list-entry link](https://example.com/json-list-current) stays selected', $blocks[0]['text']);
        $t->same('https://example.com/json-list-current', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-1201-4', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Skipped raw JSON list-entry cover'));
        $t->true(!str_contains($encoded, 'Skipped raw JSON list-entry appendix'));
        $t->true(!str_contains($encoded, 'raw JSON list-entry page payload 1201'));
        $t->true(!str_contains($encoded, 'raw JSON list-entry block payload 1201'));
        $t->true(!str_contains($encoded, 'raw JSON list-entry line payload 1201'));
        $t->true(!str_contains($encoded, 'raw JSON list-entry span payload 1201'));
        $t->true(!str_contains($encoded, 'raw ref payload for 1201'));
    },
    'unwraps raw JSON one-page pdftext envelopes inside selected page-list entries' => static function (
        TestRunner $t
    ) use ($jsonListEntryPdftextPage): void {
        $cover = $jsonListEntryPdftextPage(1300, 'Skipped raw JSON envelope cover ', 'https://example.com/json-envelope-cover', ' should not import');
        $staleSelected = $jsonListEntryPdftextPage(1301, 'Stale raw JSON envelope wrapper ', 'https://example.com/json-envelope-stale', ' should not import');
        $currentSelected = $jsonListEntryPdftextPage(2301, 'Raw JSON one-page envelope ', 'https://example.com/json-envelope-current', ' wins before stale wrapper text');
        $appendix = $jsonListEntryPdftextPage(1302, 'Skipped raw JSON envelope appendix ', 'https://example.com/json-envelope-appendix', ' should not import');
        $staleSelected['dictionary_output'] = [
            'metadata' => [
                'source' => 'pdftext.dictionary_output jsonl page cache',
                'raw_private_payload' => 'raw JSON list-entry envelope metadata must stay hidden',
            ],
            'pages' => [
                2301 => $currentSelected,
            ],
            'raw_pdftext_payload' => 'raw JSON list-entry envelope payload must stay hidden',
        ];
        $staleSelected['raw_wrapper_payload'] = 'raw JSON stale wrapper payload must stay hidden';

        $document = (new PdfTextDocumentExtractor())->getTextBlocks(
            [
                $cover,
                json_encode($staleSelected, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                $appendix,
            ],
            maxPages: 1,
            startPage: 1
        );
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(3, $document['metadata']['source_pages']);
        $t->same(2301, $page['pnum']);
        $t->same('Raw JSON one-page envelope [JSON list-entry link](https://example.com/json-envelope-current) wins before stale wrapper text', $blocks[0]['text']);
        $t->same('https://example.com/json-envelope-current', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-2301-4', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Stale raw JSON envelope wrapper'));
        $t->true(!str_contains($encoded, 'Skipped raw JSON envelope cover'));
        $t->true(!str_contains($encoded, 'Skipped raw JSON envelope appendix'));
        $t->true(!str_contains($encoded, 'raw JSON list-entry envelope metadata'));
        $t->true(!str_contains($encoded, 'raw JSON list-entry envelope payload'));
        $t->true(!str_contains($encoded, 'raw JSON stale wrapper payload'));
        $t->true(!str_contains($encoded, 'raw JSON list-entry page payload 2301'));
    },
];
