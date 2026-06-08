<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$nestedExplicitEnvelopePage = static function (int $page, string $lead, string $url, string $tail): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'refs' => [[
            'page' => $page,
            'idx' => 7,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => "nested explicit ref payload {$page} must stay hidden",
        ]],
        'raw_page_payload' => "nested explicit page payload {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 144.0, 460.0, 158.0],
            'raw_block_payload' => "nested explicit block payload {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 144.0, 460.0, 158.0],
                'raw_line_payload' => "nested explicit line payload {$page} must stay hidden",
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 170.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'nested explicit link',
                        'bbox' => [170.0, 144.0, 310.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => "nested explicit span payload {$page} must stay hidden",
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [310.0, 144.0, 460.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

return [
    'prefers nested explicit dictionary_output caches before stale wrapper pages at the core boundary' => static function (
        TestRunner $t
    ) use ($nestedExplicitEnvelopePage): void {
        $staleCover = $nestedExplicitEnvelopePage(7800, 'Stale wrapper cover ', 'https://example.com/stale-wrapper-cover', ' should not import');
        $staleSelected = $nestedExplicitEnvelopePage(7801, 'Stale wrapper selected ', 'https://example.com/stale-wrapper-selected', ' should not import');
        $staleAppendix = $nestedExplicitEnvelopePage(7802, 'Stale wrapper appendix ', 'https://example.com/stale-wrapper-appendix', ' should not import');
        $currentCover = $nestedExplicitEnvelopePage(8800, 'Skipped nested dictionary_output cover ', 'https://example.com/nested-dict-cover', ' should not import');
        $currentSelected = $nestedExplicitEnvelopePage(8801, 'Nested dictionary_output ', 'https://example.com/nested-dict-current', ' stays selected');
        $currentAppendix = $nestedExplicitEnvelopePage(8802, 'Skipped nested dictionary_output appendix ', 'https://example.com/nested-dict-appendix', ' should not import');

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([
            'pages' => [
                7701 => $nestedExplicitEnvelopePage(7701, 'Top-level stale adapter ', 'https://example.com/top-stale', ' should not import'),
            ],
            'dictionary_output' => [
                'metadata' => [
                    'source' => 'outer native adapter cache',
                    'raw_private_payload' => 'outer dictionary_output metadata payload must stay hidden',
                ],
                'pages' => [
                    7800 => $staleCover,
                    7801 => $staleSelected,
                    7802 => $staleAppendix,
                ],
                'dictionary_output' => [
                    'metadata' => [
                        'source' => 'nested pdftext.dictionary_output cache',
                        'raw_private_payload' => 'nested dictionary_output metadata payload must stay hidden',
                    ],
                    'pages' => [
                        8800 => $currentCover,
                        8801 => $currentSelected,
                        8802 => $currentAppendix,
                    ],
                    'raw_pdftext_payload' => 'nested dictionary_output payload must stay hidden',
                ],
                'raw_pdftext_payload' => 'outer dictionary_output payload must stay hidden',
            ],
            'raw_adapter_payload' => 'top-level adapter payload must stay hidden',
        ], maxPages: 1, startPage: 1);
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(3, $document['metadata']['source_pages']);
        $t->same(8801, $page['pnum']);
        $t->same('Nested dictionary_output [nested explicit link](https://example.com/nested-dict-current) stays selected', $blocks[0]['text']);
        $t->same('https://example.com/nested-dict-current', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-8801-7', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Top-level stale adapter'));
        $t->true(!str_contains($encoded, 'Stale wrapper'));
        $t->true(!str_contains($encoded, 'Skipped nested dictionary_output cover'));
        $t->true(!str_contains($encoded, 'Skipped nested dictionary_output appendix'));
        $t->true(!str_contains($encoded, 'outer dictionary_output metadata payload'));
        $t->true(!str_contains($encoded, 'outer dictionary_output payload'));
        $t->true(!str_contains($encoded, 'nested dictionary_output metadata payload'));
        $t->true(!str_contains($encoded, 'nested dictionary_output payload'));
        $t->true(!str_contains($encoded, 'nested explicit page payload 8801'));
        $t->true(!str_contains($encoded, 'nested explicit span payload 8801'));
        $t->true(!str_contains($encoded, 'top-level adapter payload'));
    },
    'prefers nested explicit pdftext JSON caches before stale wrapper pages at the core boundary' => static function (
        TestRunner $t
    ) use ($nestedExplicitEnvelopePage): void {
        $staleCover = $nestedExplicitEnvelopePage(8900, 'Stale pdftext wrapper cover ', 'https://example.com/stale-pdftext-cover', ' should not import');
        $staleSelected = $nestedExplicitEnvelopePage(8901, 'Stale pdftext wrapper selected ', 'https://example.com/stale-pdftext-selected', ' should not import');
        $staleAppendix = $nestedExplicitEnvelopePage(8902, 'Stale pdftext wrapper appendix ', 'https://example.com/stale-pdftext-appendix', ' should not import');
        $currentCover = $nestedExplicitEnvelopePage(9900, 'Skipped nested pdftext cover ', 'https://example.com/nested-pdftext-cover', ' should not import');
        $currentSelected = $nestedExplicitEnvelopePage(9901, 'Nested pdftext JSON ', 'https://example.com/nested-pdftext-current', ' stays selected');
        $currentAppendix = $nestedExplicitEnvelopePage(9902, 'Skipped nested pdftext appendix ', 'https://example.com/nested-pdftext-appendix', ' should not import');

        $nestedJson = json_encode([
            'metadata' => [
                'source' => 'nested pdftext --json cache',
                'raw_private_payload' => 'nested pdftext JSON metadata payload must stay hidden',
            ],
            'pages' => [
                9900 => $currentCover,
                9901 => $currentSelected,
                9902 => $currentAppendix,
            ],
            'raw_pdftext_payload' => 'nested pdftext JSON payload must stay hidden',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([
            'pages' => [
                8701 => $nestedExplicitEnvelopePage(8701, 'Top-level stale pdftext adapter ', 'https://example.com/top-pdftext-stale', ' should not import'),
            ],
            'pdftext' => [
                'metadata' => [
                    'source' => 'outer native pdftext cache',
                    'raw_private_payload' => 'outer pdftext metadata payload must stay hidden',
                ],
                'pages' => [
                    8900 => $staleCover,
                    8901 => $staleSelected,
                    8902 => $staleAppendix,
                ],
                'pdftext' => $nestedJson,
                'raw_pdftext_payload' => 'outer pdftext payload must stay hidden',
            ],
            'raw_adapter_payload' => 'top-level pdftext adapter payload must stay hidden',
        ], maxPages: 1, startPage: 1);
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(3, $document['metadata']['source_pages']);
        $t->same(9901, $page['pnum']);
        $t->same('Nested pdftext JSON [nested explicit link](https://example.com/nested-pdftext-current) stays selected', $blocks[0]['text']);
        $t->same('https://example.com/nested-pdftext-current', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-9901-7', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Top-level stale pdftext adapter'));
        $t->true(!str_contains($encoded, 'Stale pdftext wrapper'));
        $t->true(!str_contains($encoded, 'Skipped nested pdftext cover'));
        $t->true(!str_contains($encoded, 'Skipped nested pdftext appendix'));
        $t->true(!str_contains($encoded, 'outer pdftext metadata payload'));
        $t->true(!str_contains($encoded, 'outer pdftext payload'));
        $t->true(!str_contains($encoded, 'nested pdftext JSON metadata payload'));
        $t->true(!str_contains($encoded, 'nested pdftext JSON payload'));
        $t->true(!str_contains($encoded, 'nested explicit page payload 9901'));
        $t->true(!str_contains($encoded, 'nested explicit span payload 9901'));
        $t->true(!str_contains($encoded, 'top-level pdftext adapter payload'));
    },
];
