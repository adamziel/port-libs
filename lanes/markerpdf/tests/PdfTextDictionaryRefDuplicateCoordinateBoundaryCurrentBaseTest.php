<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$pdftextRefPage = static function (): array {
    return [
        'page' => 14,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'refs' => [
            [
                'page' => 14,
                'idx' => 0,
                'coord' => [72.0, 96.0],
                'raw_private_payload' => 'first reference payload must stay hidden',
            ],
            [
                'page' => 14,
                'idx' => 9,
                'coord' => [72.0, 96.0],
                'url' => 'javascript:duplicateRef()',
                'ref' => 'stale-duplicate-ref',
                'raw_private_payload' => 'duplicate reference payload must stay hidden',
            ],
            [
                'page' => 14,
                'idx' => 1,
                'coord' => [144.0, 216.0],
                'raw_private_payload' => 'second reference payload must stay hidden',
            ],
            [
                'page' => 14,
                'idx' => 8,
                'coord' => [144.0, 216.0],
                'url' => '#stale-second-duplicate',
                'raw_private_payload' => 'second duplicate reference payload must stay hidden',
            ],
        ],
        'blocks' => [[
            'bbox' => [72.0, 96.0, 430.0, 110.0],
            'lines' => [[
                'bbox' => [72.0, 96.0, 430.0, 110.0],
                'spans' => [[
                    'text' => "Reference duplicate boundary\n",
                    'bbox' => [72.0, 96.0, 300.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                ]],
            ]],
        ]],
    ];
};

return [
    'deduplicates upstream-shaped pdftext page refs by target coordinate before WordPress metadata' => static function (TestRunner $t) use ($pdftextRefPage): void {
        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$pdftextRefPage()], maxPages: 1);
        $refs = $document['pages'][0]['pdftext_source']['refs'];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same('Reference duplicate boundary', $blocks[0]['text']);
        $t->same([
            [
                'page' => 14,
                'idx' => 0,
                'coord' => [72.0, 96.0],
                'ref' => 'page-14-0',
                'url' => '#page-14-0',
            ],
            [
                'page' => 14,
                'idx' => 1,
                'coord' => [144.0, 216.0],
                'ref' => 'page-14-1',
                'url' => '#page-14-1',
            ],
        ], $refs);
        $t->true(!str_contains($encoded, 'duplicate reference payload'));
        $t->true(!str_contains($encoded, 'stale-duplicate-ref'));
        $t->true(!str_contains($encoded, 'javascript:duplicateRef'));
        $t->true(!str_contains($encoded, '#stale-second-duplicate'));
    },
    'keeps external pdftext ref rows while deduplicating only upstream coordinate references' => static function (TestRunner $t) use ($pdftextRefPage): void {
        $page = $pdftextRefPage();
        $page['refs'][] = [
            'url' => 'https://example.com/import-review',
            'bbox' => [72.0, 120.0, 180.0, 136.0],
            'raw_private_payload' => 'external reference payload must stay hidden',
        ];

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
        $refs = $document['pages'][0]['pdftext_source']['refs'];
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same(3, count($refs));
        $t->same('#page-14-0', $refs[0]['url']);
        $t->same('#page-14-1', $refs[1]['url']);
        $t->same([
            'url' => 'https://example.com/import-review',
            'bbox' => [72.0, 120.0, 180.0, 136.0],
        ], $refs[2]);
        $t->true(!str_contains($encoded, 'external reference payload must stay hidden'));
    },
];
