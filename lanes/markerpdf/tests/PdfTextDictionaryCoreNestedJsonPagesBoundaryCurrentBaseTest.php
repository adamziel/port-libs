<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$nestedJsonPagesPdftextPage = static function (int $page, string $lead, string $url, string $tail): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'refs' => [[
            'page' => $page,
            'idx' => 6,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => "nested pages ref payload {$page} must stay hidden",
        ]],
        'raw_page_payload' => "nested pages page payload {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 144.0, 460.0, 158.0],
            'raw_block_payload' => "nested pages block payload {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 144.0, 460.0, 158.0],
                'raw_line_payload' => "nested pages line payload {$page} must stay hidden",
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 170.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'nested JSON pages link',
                        'bbox' => [170.0, 144.0, 320.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => "nested pages span payload {$page} must stay hidden",
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [320.0, 144.0, 460.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

return [
    'unwraps raw JSON pages members inside explicit dictionary_output envelopes' => static function (
        TestRunner $t
    ) use ($nestedJsonPagesPdftextPage): void {
        $stale = $nestedJsonPagesPdftextPage(2400, 'Stale nested JSON adapter ', 'https://example.com/nested-pages-stale', ' should not import');
        $cover = $nestedJsonPagesPdftextPage(2500, 'Skipped nested JSON cover ', 'https://example.com/nested-pages-cover', ' should not import');
        $selected = $nestedJsonPagesPdftextPage(2501, 'Nested JSON pages dictionary_output ', 'https://example.com/nested-pages-current', ' stays selected');
        $appendix = $nestedJsonPagesPdftextPage(2502, 'Skipped nested JSON appendix ', 'https://example.com/nested-pages-appendix', ' should not import');
        $pagesJson = json_encode([
            2500 => $cover,
            2501 => $selected,
            2502 => $appendix,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([
            'pages' => [2400 => $stale],
            'dictionary_output' => [
                'metadata' => [
                    'source' => 'native adapter cache with double-encoded pages',
                    'raw_private_payload' => 'nested pages envelope metadata payload must stay hidden',
                ],
                'pages' => $pagesJson,
                'raw_pdftext_payload' => 'nested pages dictionary_output payload must stay hidden',
            ],
            'raw_adapter_payload' => 'nested pages stale adapter payload must stay hidden',
        ], maxPages: 1, startPage: 1);
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(3, $document['metadata']['source_pages']);
        $t->same(2501, $page['pnum']);
        $t->same('Nested JSON pages dictionary_output [nested JSON pages link](https://example.com/nested-pages-current) stays selected', $blocks[0]['text']);
        $t->same('https://example.com/nested-pages-current', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-2501-6', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Stale nested JSON adapter'));
        $t->true(!str_contains($encoded, 'Skipped nested JSON cover'));
        $t->true(!str_contains($encoded, 'Skipped nested JSON appendix'));
        $t->true(!str_contains($encoded, 'nested pages envelope metadata payload'));
        $t->true(!str_contains($encoded, 'nested pages dictionary_output payload'));
        $t->true(!str_contains($encoded, 'nested pages stale adapter payload'));
        $t->true(!str_contains($encoded, 'nested pages ref payload 2501'));
        $t->true(!str_contains($encoded, 'nested pages page payload 2501'));
        $t->true(!str_contains($encoded, 'nested pages span payload 2501'));
    },
    'unwraps BOM-prefixed raw JSON pages members inside selected pdftext page-list entries' => static function (
        TestRunner $t
    ) use ($nestedJsonPagesPdftextPage): void {
        $cover = $nestedJsonPagesPdftextPage(3600, 'Skipped list-entry nested JSON cover ', 'https://example.com/list-nested-cover', ' should not import');
        $staleSelected = $nestedJsonPagesPdftextPage(3601, 'Stale list-entry nested JSON wrapper ', 'https://example.com/list-nested-stale', ' should not import');
        $currentSelected = $nestedJsonPagesPdftextPage(4601, 'List-entry nested JSON pdftext ', 'https://example.com/list-nested-current', ' wins before stale wrapper');
        $appendix = $nestedJsonPagesPdftextPage(3602, 'Skipped list-entry nested JSON appendix ', 'https://example.com/list-nested-appendix', ' should not import');
        $staleSelected['pdftext'] = [
            'metadata' => [
                'source' => 'pdftext dictionary_output JSONL row',
                'raw_private_payload' => 'list-entry nested JSON metadata payload must stay hidden',
            ],
            'pages' => "\xEF\xBB\xBF" . json_encode([
                4601 => $currentSelected,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'raw_pdftext_payload' => 'list-entry nested JSON pdftext payload must stay hidden',
        ];
        $staleSelected['raw_wrapper_payload'] = 'list-entry nested JSON stale wrapper payload must stay hidden';

        $document = (new PdfTextDocumentExtractor())->getTextBlocks(
            [$cover, $staleSelected, $appendix],
            maxPages: 1,
            startPage: 1
        );
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(3, $document['metadata']['source_pages']);
        $t->same(4601, $page['pnum']);
        $t->same('List-entry nested JSON pdftext [nested JSON pages link](https://example.com/list-nested-current) wins before stale wrapper', $blocks[0]['text']);
        $t->same('https://example.com/list-nested-current', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-4601-6', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Skipped list-entry nested JSON cover'));
        $t->true(!str_contains($encoded, 'Stale list-entry nested JSON wrapper'));
        $t->true(!str_contains($encoded, 'Skipped list-entry nested JSON appendix'));
        $t->true(!str_contains($encoded, 'list-entry nested JSON metadata payload'));
        $t->true(!str_contains($encoded, 'list-entry nested JSON pdftext payload'));
        $t->true(!str_contains($encoded, 'list-entry nested JSON stale wrapper payload'));
        $t->true(!str_contains($encoded, 'nested pages page payload 4601'));
        $t->true(!str_contains($encoded, 'nested pages block payload 4601'));
        $t->true(!str_contains($encoded, 'nested pages span payload 4601'));
    },
];
