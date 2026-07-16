<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$overflowPageMapKeyPage = static function (int $page, string $lead, string $url, string $tail): array {
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
            'raw_private_payload' => "overflow page-map ref payload {$page} must stay hidden",
        ]],
        'raw_page_payload' => "overflow page-map page payload {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 144.0, 500.0, 158.0],
            'raw_block_payload' => "overflow page-map block payload {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 144.0, 500.0, 158.0],
                'raw_line_payload' => "overflow page-map line payload {$page} must stay hidden",
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 190.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'overflow-safe link',
                        'bbox' => [190.0, 144.0, 345.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => "overflow page-map span payload {$page} must stay hidden",
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [345.0, 144.0, 500.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

return [
    'rejects overflow dictionary_output page-map keys before stale adapter pages' => static function (
        TestRunner $t
    ) use ($overflowPageMapKeyPage): void {
        $overflowKey = (string) PHP_INT_MAX . '0';
        $extractor = new PdfTextDocumentExtractor();

        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [
                10 => $overflowPageMapKeyPage(10, 'Stale overflow adapter ', 'https://example.com/overflow-stale', ' must not import'),
            ],
            'dictionary_output' => [
                $overflowKey => $overflowPageMapKeyPage(901, 'Overflow keyed dictionary_output ', 'https://example.com/overflow-current', ' must fail closed'),
                '2' => $overflowPageMapKeyPage(902, 'Positive sibling dictionary_output ', 'https://example.com/overflow-sibling', ' must not import after overflow'),
            ],
            'raw_adapter_payload' => 'overflow dictionary_output adapter payload must stay hidden',
        ], maxPages: 1));
    },
    'rejects overflow raw JSON pdftext page-map keys before stale adapter pages' => static function (
        TestRunner $t
    ) use ($overflowPageMapKeyPage): void {
        $overflowKey = '+' . (string) PHP_INT_MAX . '0.000';
        $json = json_encode([
            'metadata' => [
                'source' => 'pdftext --json cache with overflow page-map key',
                'raw_private_payload' => 'overflow JSON page-map metadata payload must stay hidden',
            ],
            'page_map' => [
                $overflowKey => $overflowPageMapKeyPage(1201, 'Overflow JSON page_map ', 'https://example.com/overflow-json-current', ' must fail closed'),
                '3' => $overflowPageMapKeyPage(1202, 'Positive JSON sibling page ', 'https://example.com/overflow-json-sibling', ' must not import after overflow'),
            ],
            'raw_pdftext_payload' => 'overflow JSON page-map payload must stay hidden',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $extractor = new PdfTextDocumentExtractor();
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [
                20 => $overflowPageMapKeyPage(20, 'Stale overflow JSON adapter ', 'https://example.com/overflow-json-stale', ' must not import'),
            ],
            'pdftext' => $json,
            'raw_adapter_payload' => 'overflow JSON adapter payload must stay hidden',
        ], maxPages: 1));
    },
    'keeps maximum integer source-page page-map keys importable at the core boundary' => static function (
        TestRunner $t
    ) use ($overflowPageMapKeyPage): void {
        $page = $overflowPageMapKeyPage(0, 'Maximum integer keyed ', 'https://example.com/max-key-current', ' imports safely');

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([
            'dictionary_output' => [
                (string) PHP_INT_MAX => $page,
            ],
            'raw_adapter_payload' => 'valid max-key adapter payload must stay hidden',
        ], maxPages: 1);

        $pageOut = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $document['page_range']);
        $t->same(1, $document['metadata']['source_pages']);
        $t->same(0, $pageOut['pnum']);
        $t->same('Maximum integer keyed [overflow-safe link](https://example.com/max-key-current) imports safely', $blocks[0]['text']);
        $t->same('https://example.com/max-key-current', $pageOut['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-0-4', $pageOut['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'valid max-key adapter payload'));
        $t->true(!str_contains($encoded, 'overflow page-map page payload 0'));
        $t->true(!str_contains($encoded, 'overflow page-map ref payload 0'));
        $t->true(!str_contains($encoded, 'overflow page-map block payload 0'));
        $t->true(!str_contains($encoded, 'overflow page-map span payload 0'));
    },
];
