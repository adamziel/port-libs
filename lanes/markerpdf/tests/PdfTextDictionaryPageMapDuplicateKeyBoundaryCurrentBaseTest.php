<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$pdftextPageMapDuplicateKeyPage = static function (int $page, string $text): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'blocks' => [[
            'bbox' => [72.0, 96.0, 430.0, 110.0],
            'lines' => [[
                'bbox' => [72.0, 96.0, 430.0, 110.0],
                'spans' => [[
                    'text' => $text,
                    'bbox' => [72.0, 96.0, 430.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                ]],
            ]],
        ]],
    ];
};

return [
    'rejects duplicate normalized dictionary_output source-page keys before stale adapter pages' => static function (
        TestRunner $t
    ) use ($pdftextPageMapDuplicateKeyPage): void {
        $extractor = new PdfTextDocumentExtractor();

        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [
                $pdftextPageMapDuplicateKeyPage(10, 'Stale adapter page must not import'),
            ],
            'dictionary_output' => [
                '01' => $pdftextPageMapDuplicateKeyPage(101, 'Duplicate alias page one'),
                '+1.0' => $pdftextPageMapDuplicateKeyPage(201, 'Duplicate alias page two'),
            ],
            'raw_adapter_payload' => 'stale duplicate-key adapter payload must not cross',
        ], maxPages: 1));
    },
    'rejects duplicate normalized raw JSON pdftext source-page keys before stale adapter pages' => static function (
        TestRunner $t
    ) use ($pdftextPageMapDuplicateKeyPage): void {
        $extractor = new PdfTextDocumentExtractor();
        $json = json_encode([
            'metadata' => [
                'source' => 'pdftext --json cache',
                'raw_private_payload' => 'duplicate-key JSON metadata must not cross',
            ],
            'pages' => [
                '001' => $pdftextPageMapDuplicateKeyPage(301, 'JSON duplicate alias page one'),
                '1.0' => $pdftextPageMapDuplicateKeyPage(401, 'JSON duplicate alias page two'),
            ],
            'raw_pdftext_payload' => 'duplicate-key JSON payload must not cross',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [
                $pdftextPageMapDuplicateKeyPage(30, 'Stale JSON adapter page must not import'),
            ],
            'pdftext' => $json,
            'raw_adapter_payload' => 'stale duplicate-key JSON adapter payload must not cross',
        ], maxPages: 1));
    },
];
