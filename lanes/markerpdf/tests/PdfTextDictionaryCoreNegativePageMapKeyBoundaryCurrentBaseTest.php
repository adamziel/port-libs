<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$negativePageMapKeyPage = static function (int $page, string $text): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'refs' => [[
            'page' => $page,
            'idx' => 3,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => "negative page-map ref payload {$page} must stay hidden",
        ]],
        'raw_page_payload' => "negative page-map page payload {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 144.0, 440.0, 158.0],
            'raw_block_payload' => "negative page-map block payload {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 144.0, 440.0, 158.0],
                'raw_line_payload' => "negative page-map line payload {$page} must stay hidden",
                'spans' => [[
                    'text' => $text,
                    'bbox' => [72.0, 144.0, 440.0, 158.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'raw_span_payload' => "negative page-map span payload {$page} must stay hidden",
                ]],
            ]],
        ]],
    ];
};

return [
    'rejects negative dictionary_output page-map keys before stale adapter pages' => static function (
        TestRunner $t
    ) use ($negativePageMapKeyPage): void {
        $extractor = new PdfTextDocumentExtractor();

        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [
                $negativePageMapKeyPage(10, 'Stale adapter page must not import.'),
            ],
            'dictionary_output' => [
                '-1.0' => $negativePageMapKeyPage(101, 'Negative keyed dictionary_output page must not import.'),
                '2' => $negativePageMapKeyPage(102, 'Positive sibling dictionary_output page must not import after negative key.'),
            ],
            'raw_adapter_payload' => 'negative dictionary_output adapter payload must stay hidden',
        ], maxPages: 1));
    },
    'rejects negative raw JSON pdftext page-map keys before stale adapter pages' => static function (
        TestRunner $t
    ) use ($negativePageMapKeyPage): void {
        $extractor = new PdfTextDocumentExtractor();
        $json = json_encode([
            'metadata' => [
                'source' => 'pdftext --json cache with invalid page-map key',
                'raw_private_payload' => 'negative JSON page-map metadata payload must stay hidden',
            ],
            'pages' => [
                '-01' => $negativePageMapKeyPage(201, 'Negative keyed JSON pdftext page must not import.'),
                '3' => $negativePageMapKeyPage(202, 'Positive JSON sibling page must not import after negative key.'),
            ],
            'raw_pdftext_payload' => 'negative JSON page-map payload must stay hidden',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [
                $negativePageMapKeyPage(20, 'Stale JSON adapter page must not import.'),
            ],
            'pdftext' => $json,
            'raw_adapter_payload' => 'negative JSON adapter payload must stay hidden',
        ], maxPages: 1));
    },
    'keeps zero source-page page-map keys importable at the core boundary' => static function (
        TestRunner $t
    ) use ($negativePageMapKeyPage): void {
        $document = (new PdfTextDocumentExtractor())->getTextBlocks([
            'dictionary_output' => [
                '0' => $negativePageMapKeyPage(0, 'Zero keyed pdftext page imports safely.'),
                '+2.0' => $negativePageMapKeyPage(2, 'Positive keyed appendix stays out of selected slice.'),
            ],
            'raw_adapter_payload' => 'valid zero-key adapter payload must stay hidden',
        ], maxPages: 1);

        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $document['page_range']);
        $t->same(2, $document['metadata']['source_pages']);
        $t->same(0, $page['pnum']);
        $t->same('Zero keyed pdftext page imports safely.', $blocks[0]['text']);
        $t->same('#page-0-3', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Positive keyed appendix'));
        $t->true(!str_contains($encoded, 'valid zero-key adapter payload'));
        $t->true(!str_contains($encoded, 'negative page-map page payload 0'));
        $t->true(!str_contains($encoded, 'negative page-map ref payload 0'));
        $t->true(!str_contains($encoded, 'negative page-map span payload 0'));
    },
];
