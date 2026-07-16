<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$nestedMalformedEnvelopePage = static function (int $page, string $text): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'blocks' => [[
            'bbox' => [72.0, 144.0, 460.0, 158.0],
            'lines' => [[
                'bbox' => [72.0, 144.0, 460.0, 158.0],
                'spans' => [[
                    'text' => $text,
                    'bbox' => [72.0, 144.0, 460.0, 158.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                ]],
            ]],
        ]],
    ];
};

return [
    'rejects malformed nested dictionary_output caches before sibling adapter pages' => static function (
        TestRunner $t
    ) use ($nestedMalformedEnvelopePage): void {
        $extractor = new PdfTextDocumentExtractor();

        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [
                7100 => $nestedMalformedEnvelopePage(7100, 'Top-level stale adapter page must not import'),
            ],
            'dictionary_output' => [
                'metadata' => [
                    'source' => 'outer native adapter cache',
                    'raw_private_payload' => 'outer malformed metadata payload must stay hidden',
                ],
                'pages' => [
                    7200 => $nestedMalformedEnvelopePage(7200, 'Outer sibling page must not import after malformed nested dictionary_output'),
                ],
                'dictionary_output' => 'not a pdftext page dictionary or page list',
                'raw_pdftext_payload' => 'outer malformed dictionary_output payload must stay hidden',
            ],
            'raw_adapter_payload' => 'top-level malformed adapter payload must stay hidden',
        ], maxPages: 1));
    },
    'rejects malformed nested pdftext JSON caches before sibling adapter pages' => static function (
        TestRunner $t
    ) use ($nestedMalformedEnvelopePage): void {
        $extractor = new PdfTextDocumentExtractor();

        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [
                8100 => $nestedMalformedEnvelopePage(8100, 'Top-level stale pdftext adapter page must not import'),
            ],
            'pdftext' => [
                'metadata' => [
                    'source' => 'outer native pdftext cache',
                    'raw_private_payload' => 'outer malformed pdftext metadata payload must stay hidden',
                ],
                'pages' => [
                    8200 => $nestedMalformedEnvelopePage(8200, 'Outer sibling page must not import after malformed nested pdftext'),
                ],
                'pdftext' => '{"pages":[{"page":8300',
                'raw_pdftext_payload' => 'outer malformed pdftext payload must stay hidden',
            ],
            'raw_adapter_payload' => 'top-level malformed pdftext adapter payload must stay hidden',
        ], maxPages: 1));
    },
];
