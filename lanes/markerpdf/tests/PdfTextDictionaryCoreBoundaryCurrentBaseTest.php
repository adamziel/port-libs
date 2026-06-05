<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$pdftextLinkedPage = static function (): array {
    return [
        'page' => 12,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'refs' => [
            [
                'url' => '#page-3-xy',
                'page' => 3,
                'dest_pos' => [72.0, 96.0],
            ],
        ],
        'blocks' => [[
            'bbox' => [72.0, 96.0, 370.0, 110.0],
            'lines' => [[
                'bbox' => [72.0, 96.0, 370.0, 110.0],
                'spans' => [
                    [
                        'text' => 'Read ',
                        'bbox' => [72.0, 96.0, 104.0, 110.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'plugin docs',
                        'bbox' => [104.0, 96.0, 172.0, 110.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => 'https://example.com/import)docs',
                        'chars' => [['char' => 'p', 'bbox' => [104.0, 96.0, 110.0, 110.0]]],
                    ],
                    [
                        'text' => ' but review script action',
                        'bbox' => [172.0, 96.0, 370.0, 110.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => 'javascript:alert(1)',
                        'chars' => [['char' => 'b', 'bbox' => [172.0, 96.0, 178.0, 110.0]]],
                    ],
                ],
            ]],
        ]],
    ];
};

return [
    'preserves pdftext dictionary links and refs at the core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$pdftextLinkedPage()], maxPages: 1);
        $page = $document['pages'][0];
        $spans = $page['blocks'][0]['lines'][0]['spans'];
        $charSpans = $page['char_blocks'][0]['lines'][0]['spans'];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));

        $t->same([0], $document['page_range']);
        $t->same(12, $page['pnum']);
        $t->same('https://example.com/import)docs', $spans[1]['pdftext_url']);
        $t->same(true, $spans[1]['pdftext_url_is_safe']);
        $t->same('https://example.com/import)docs', $spans[1]['url']);
        $t->same('javascript:alert(1)', $spans[2]['pdftext_url']);
        $t->same(false, $spans[2]['pdftext_url_is_safe']);
        $t->true(!array_key_exists('url', $spans[2]), 'Unsafe pdftext URIs stay review-only and are not rendered as clickable WordPress links.');
        $t->same('https://example.com/import)docs', $charSpans[1]['url']);
        $t->same('javascript:alert(1)', $charSpans[2]['url']);
        $t->true(!array_key_exists('chars', $charSpans[1]), 'keep_chars=false still removes raw link character payloads.');
        $t->same([
            [
                'url' => '#page-3-xy',
                'page' => 3,
                'dest_pos' => [72.0, 96.0],
            ],
        ], $page['pdftext_source']['refs']);
        $t->same('Read [plugin docs](https://example.com/import\\)docs) but review script action', $blocks[0]['text']);
        $t->true(!str_contains($blocks[0]['text'], 'javascript:'), 'Unsafe pdftext URI metadata must not leak into visible Gutenberg Markdown.');
    },
    'rejects non string pdftext span urls before WordPress rendering' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = $pdftextLinkedPage();
        $page['blocks'][0]['lines'][0]['spans'][1]['url'] = ['https://example.com/not-a-string'];

        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1));
    },
];
