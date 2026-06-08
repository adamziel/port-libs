<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$malformedPageListKeyPage = static function (int $page, string $text): array {
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
            'raw_private_payload' => "malformed page-list ref payload {$page} must stay hidden",
        ]],
        'raw_page_payload' => "malformed page-list page payload {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 144.0, 440.0, 158.0],
            'raw_block_payload' => "malformed page-list block payload {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 144.0, 440.0, 158.0],
                'raw_line_payload' => "malformed page-list line payload {$page} must stay hidden",
                'spans' => [[
                    'text' => $text,
                    'bbox' => [72.0, 144.0, 440.0, 158.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'raw_span_payload' => "malformed page-list span payload {$page} must stay hidden",
                ]],
            ]],
        ]],
    ];
};

return [
    'rejects malformed explicit dictionary_output pages keys before sibling page rows' => static function (
        TestRunner $t
    ) use ($malformedPageListKeyPage): void {
        $extractor = new PdfTextDocumentExtractor();

        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [
                1000 => $malformedPageListKeyPage(1000, 'Stale outer adapter page must not import'),
            ],
            'dictionary_output' => [
                'metadata' => [
                    'source' => 'corrupt native dictionary_output cache',
                    'raw_private_payload' => 'malformed dictionary_output metadata payload must stay hidden',
                ],
                'pages' => 'not an ordered pdftext page list',
                2000 => $malformedPageListKeyPage(2000, 'Sibling dictionary_output row must not import'),
                'raw_pdftext_payload' => 'malformed dictionary_output payload must stay hidden',
            ],
            'raw_adapter_payload' => 'malformed outer adapter payload must stay hidden',
        ], maxPages: 1));
    },
    'rejects malformed explicit pdftext page_map keys before sibling page rows' => static function (
        TestRunner $t
    ) use ($malformedPageListKeyPage): void {
        $extractor = new PdfTextDocumentExtractor();
        $json = json_encode([
            'metadata' => [
                'source' => 'corrupt pdftext --json page map cache',
                'raw_private_payload' => 'malformed pdftext page_map metadata payload must stay hidden',
            ],
            'page_map' => 'not a pdftext page map',
            3000 => $malformedPageListKeyPage(3000, 'Sibling pdftext row must not import'),
            'raw_pdftext_payload' => 'malformed pdftext page_map payload must stay hidden',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [
                1100 => $malformedPageListKeyPage(1100, 'Stale pdftext outer adapter page must not import'),
            ],
            'pdftext' => $json,
            'raw_adapter_payload' => 'malformed pdftext outer adapter payload must stay hidden',
        ], maxPages: 1));
    },
];
