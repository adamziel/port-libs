<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$pageMapEnvelopePage = static function (int $page, string $lead, string $url, string $tail): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'refs' => [[
            'page' => $page,
            'idx' => 8,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => "page-map ref payload {$page} must stay hidden",
        ]],
        'raw_page_payload' => "page-map page payload {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 144.0, 480.0, 158.0],
            'raw_block_payload' => "page-map block payload {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 144.0, 480.0, 158.0],
                'raw_line_payload' => "page-map line payload {$page} must stay hidden",
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 180.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'page-map link',
                        'bbox' => [180.0, 144.0, 300.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => "page-map span payload {$page} must stay hidden",
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [300.0, 144.0, 480.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

return [
    'unwraps page_map members inside explicit dictionary_output envelopes before stale adapter pages' => static function (
        TestRunner $t
    ) use ($pageMapEnvelopePage): void {
        $stale = $pageMapEnvelopePage(5400, 'Stale page_map adapter ', 'https://example.com/page-map-stale', ' should not import');
        $cover = $pageMapEnvelopePage(5500, 'Skipped page_map cover ', 'https://example.com/page-map-cover', ' should not import');
        $selected = $pageMapEnvelopePage(5501, 'Explicit page_map dictionary_output ', 'https://example.com/page-map-current', ' stays selected');
        $appendix = $pageMapEnvelopePage(5502, 'Skipped page_map appendix ', 'https://example.com/page-map-appendix', ' should not import');

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([
            'pages' => [5400 => $stale],
            'dictionary_output' => [
                'metadata' => [
                    'source' => 'native adapter page_map cache',
                    'raw_private_payload' => 'page_map dictionary_output metadata payload must stay hidden',
                ],
                'page_map' => [
                    5500 => $cover,
                    5501 => $selected,
                    5502 => $appendix,
                ],
                'raw_pdftext_payload' => 'page_map dictionary_output payload must stay hidden',
            ],
            'raw_adapter_payload' => 'page_map stale adapter payload must stay hidden',
        ], maxPages: 1, startPage: 1);
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(3, $document['metadata']['source_pages']);
        $t->same(5501, $page['pnum']);
        $t->same('Explicit page_map dictionary_output [page-map link](https://example.com/page-map-current) stays selected', $blocks[0]['text']);
        $t->same('https://example.com/page-map-current', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-5501-8', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Stale page_map adapter'));
        $t->true(!str_contains($encoded, 'Skipped page_map cover'));
        $t->true(!str_contains($encoded, 'Skipped page_map appendix'));
        $t->true(!str_contains($encoded, 'page_map dictionary_output metadata payload'));
        $t->true(!str_contains($encoded, 'page_map dictionary_output payload'));
        $t->true(!str_contains($encoded, 'page_map stale adapter payload'));
        $t->true(!str_contains($encoded, 'page-map page payload 5501'));
        $t->true(!str_contains($encoded, 'page-map span payload 5501'));
    },
    'unwraps camelCase pageMap members inside raw pdftext JSON cache envelopes' => static function (
        TestRunner $t
    ) use ($pageMapEnvelopePage): void {
        $stale = $pageMapEnvelopePage(6400, 'Stale pageMap adapter ', 'https://example.com/page-map-json-stale', ' should not import');
        $cover = $pageMapEnvelopePage(6500, 'Skipped pageMap cover ', 'https://example.com/page-map-json-cover', ' should not import');
        $selected = $pageMapEnvelopePage(6501, 'Raw JSON pageMap pdftext ', 'https://example.com/page-map-json-current', ' stays selected');
        $appendix = $pageMapEnvelopePage(6502, 'Skipped pageMap appendix ', 'https://example.com/page-map-json-appendix', ' should not import');
        $json = json_encode([
            'metadata' => [
                'source' => 'pdftext --json pageMap cache',
                'raw_private_payload' => 'pageMap JSON metadata payload must stay hidden',
            ],
            'pageMap' => [
                6500 => $cover,
                6501 => $selected,
                6502 => $appendix,
            ],
            'raw_pdftext_payload' => 'pageMap JSON payload must stay hidden',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([
            'pages' => [6400 => $stale],
            'pdftext' => $json,
            'raw_adapter_payload' => 'pageMap JSON stale adapter payload must stay hidden',
        ], maxPages: 1, startPage: 1);
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(3, $document['metadata']['source_pages']);
        $t->same(6501, $page['pnum']);
        $t->same('Raw JSON pageMap pdftext [page-map link](https://example.com/page-map-json-current) stays selected', $blocks[0]['text']);
        $t->same('https://example.com/page-map-json-current', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-6501-8', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Stale pageMap adapter'));
        $t->true(!str_contains($encoded, 'Skipped pageMap cover'));
        $t->true(!str_contains($encoded, 'Skipped pageMap appendix'));
        $t->true(!str_contains($encoded, 'pageMap JSON metadata payload'));
        $t->true(!str_contains($encoded, 'pageMap JSON payload'));
        $t->true(!str_contains($encoded, 'pageMap JSON stale adapter payload'));
        $t->true(!str_contains($encoded, 'page-map ref payload 6501'));
        $t->true(!str_contains($encoded, 'page-map block payload 6501'));
    },
    'unwraps one-page page_map envelopes inside selected page-list entries' => static function (
        TestRunner $t
    ) use ($pageMapEnvelopePage): void {
        $cover = $pageMapEnvelopePage(7400, 'Skipped selected entry cover ', 'https://example.com/page-map-entry-cover', ' should not import');
        $staleSelected = $pageMapEnvelopePage(7401, 'Stale selected entry wrapper ', 'https://example.com/page-map-entry-stale', ' should not import');
        $currentSelected = $pageMapEnvelopePage(7501, 'Selected entry page_map ', 'https://example.com/page-map-entry-current', ' wins before stale wrapper');
        $appendix = $pageMapEnvelopePage(7402, 'Skipped selected entry appendix ', 'https://example.com/page-map-entry-appendix', ' should not import');
        $staleSelected['pdftext'] = [
            'metadata' => [
                'source' => 'pdftext selected-entry page_map cache',
                'raw_private_payload' => 'selected entry page_map metadata payload must stay hidden',
            ],
            'page_map' => [
                7501 => $currentSelected,
            ],
            'raw_pdftext_payload' => 'selected entry page_map payload must stay hidden',
        ];
        $staleSelected['raw_wrapper_payload'] = 'selected entry stale page_map wrapper payload must stay hidden';

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
        $t->same(7501, $page['pnum']);
        $t->same('Selected entry page_map [page-map link](https://example.com/page-map-entry-current) wins before stale wrapper', $blocks[0]['text']);
        $t->same('https://example.com/page-map-entry-current', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-7501-8', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Skipped selected entry cover'));
        $t->true(!str_contains($encoded, 'Stale selected entry wrapper'));
        $t->true(!str_contains($encoded, 'Skipped selected entry appendix'));
        $t->true(!str_contains($encoded, 'selected entry page_map metadata payload'));
        $t->true(!str_contains($encoded, 'selected entry page_map payload'));
        $t->true(!str_contains($encoded, 'selected entry stale page_map wrapper payload'));
        $t->true(!str_contains($encoded, 'page-map page payload 7501'));
        $t->true(!str_contains($encoded, 'page-map line payload 7501'));
    },
];
