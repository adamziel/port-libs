<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$bomJsonPage = static function (int $page, string $lead, string $url, string $tail): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'refs' => [[
            'page' => $page,
            'idx' => 5,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => "BOM JSON ref payload {$page} must stay hidden",
        ]],
        'raw_page_payload' => "BOM JSON page payload {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 144.0, 430.0, 158.0],
            'raw_block_payload' => "BOM JSON block payload {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 144.0, 430.0, 158.0],
                'raw_line_payload' => "BOM JSON line payload {$page} must stay hidden",
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 160.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'BOM cached link',
                        'bbox' => [160.0, 144.0, 280.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => "BOM JSON span payload {$page} must stay hidden",
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
    'unwraps UTF-8 BOM prefixed dictionary_output JSON cache envelopes before stale adapter pages' => static function (
        TestRunner $t
    ) use ($bomJsonPage): void {
        $stale = $bomJsonPage(3100, 'Stale BOM top-level adapter ', 'https://example.com/bom-stale', ' should not import');
        $cover = $bomJsonPage(3200, 'Skipped BOM JSON cover ', 'https://example.com/bom-cover', ' should not import');
        $selected = $bomJsonPage(3201, 'BOM dictionary_output ', 'https://example.com/bom-current', ' stays selected');
        $appendix = $bomJsonPage(3202, 'Skipped BOM JSON appendix ', 'https://example.com/bom-appendix', ' should not import');
        $json = "\xEF\xBB\xBF" . json_encode([
            'metadata' => [
                'source' => 'pdftext --json cache with UTF-8 BOM',
                'raw_private_payload' => 'BOM JSON metadata payload must stay hidden',
            ],
            'pages' => [
                3200 => $cover,
                3201 => $selected,
                3202 => $appendix,
            ],
            'raw_pdftext_payload' => 'BOM JSON dictionary_output payload must stay hidden',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([
            'pages' => [3100 => $stale],
            'dictionary_output' => $json,
            'raw_adapter_payload' => 'BOM stale adapter payload must stay hidden',
        ], maxPages: 1, startPage: 1);
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(3, $document['metadata']['source_pages']);
        $t->same(3201, $page['pnum']);
        $t->same('BOM dictionary_output [BOM cached link](https://example.com/bom-current) stays selected', $blocks[0]['text']);
        $t->same('https://example.com/bom-current', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-3201-5', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Stale BOM top-level adapter'));
        $t->true(!str_contains($encoded, 'Skipped BOM JSON cover'));
        $t->true(!str_contains($encoded, 'Skipped BOM JSON appendix'));
        $t->true(!str_contains($encoded, 'BOM JSON metadata payload'));
        $t->true(!str_contains($encoded, 'BOM JSON dictionary_output payload'));
        $t->true(!str_contains($encoded, 'BOM stale adapter payload'));
        $t->true(!str_contains($encoded, 'BOM JSON page payload 3201'));
        $t->true(!str_contains($encoded, 'BOM JSON span payload 3201'));
    },
    'unwraps UTF-8 BOM prefixed explicit pdftext JSON page-list caches' => static function (
        TestRunner $t
    ) use ($bomJsonPage): void {
        $selected = $bomJsonPage(3301, 'BOM pdftext cache ', 'https://example.com/bom-pdftext', ' imports without Python');
        $json = "\xEF\xBB\xBF" . json_encode([$selected], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([
            'pdftext' => $json,
            'raw_adapter_payload' => 'BOM pdftext adapter payload must stay hidden',
        ], maxPages: 1);
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $document['page_range']);
        $t->same(1, $document['metadata']['source_pages']);
        $t->same(3301, $page['pnum']);
        $t->same('BOM pdftext cache [BOM cached link](https://example.com/bom-pdftext) imports without Python', $blocks[0]['text']);
        $t->same('https://example.com/bom-pdftext', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-3301-5', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'BOM pdftext adapter payload'));
        $t->true(!str_contains($encoded, 'BOM JSON page payload 3301'));
        $t->true(!str_contains($encoded, 'BOM JSON ref payload 3301'));
        $t->true(!str_contains($encoded, 'BOM JSON span payload 3301'));
    },
];
