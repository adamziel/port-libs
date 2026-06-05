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

$pdftextCharsPage = static function (): array {
    $font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];

    return [
        'page' => 19,
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'width' => 600.0,
        'height' => 800.0,
        'rotation' => 0,
        'blocks' => [[
            'bbox' => [0.10, 0.10, 0.30, 0.13],
            'lines' => [[
                'bbox' => [0.10, 0.10, 0.30, 0.13],
                'spans' => [[
                    'text' => "Kept\u{FB01} chars\n",
                    'bbox' => [0.10, 0.10, 0.30, 0.13],
                    'font' => $font,
                    'rotation' => 0,
                    'char_start_idx' => 7,
                    'char_end_idx' => 8,
                    'chars' => [
                        [
                            'char' => 'K',
                            'bbox' => [0.10, 0.10, 0.11, 0.13],
                            'rotation' => 0,
                            'font' => $font,
                            'char_idx' => 7,
                            'raw_pdf_bytes' => 'should not cross dictionary_output',
                        ],
                        [
                            'char' => 'e',
                            'bbox' => [0.11, 0.10, 0.12, 0.13],
                            'rotation' => 0,
                            'font' => $font,
                            'char_idx' => 8,
                            'debug_payload' => ['stream' => 'hidden'],
                        ],
                    ],
                    'raw_image_bytes' => 'not visible',
                ]],
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
    'sanitizes pdftext page refs at the source metadata boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = $pdftextLinkedPage();
        $page['refs'][0]['raw_private_payload'] = 'hidden ref payload should not cross dictionary_output';
        $page['refs'][0]['debug_payload'] = ['stream' => 'hidden ref debug data'];
        $page['refs'][] = [
            'url' => '#page-8-4',
            'page' => 8,
            'idx' => 4,
            'ref' => 'page-8-4',
            'coord' => [12.5, 64.0],
            'raw_pdf_bytes' => 'hidden destination bytes',
        ];
        $page['refs'][] = [
            'raw_payload' => 'payload-only ref row should be dropped',
        ];

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
        $refs = $document['pages'][0]['pdftext_source']['refs'];
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([
            [
                'url' => '#page-3-xy',
                'page' => 3,
                'dest_pos' => [72.0, 96.0],
            ],
            [
                'url' => '#page-8-4',
                'page' => 8,
                'idx' => 4,
                'ref' => 'page-8-4',
                'coord' => [12.5, 64.0],
            ],
        ], $refs);
        $t->true(!str_contains($encoded, 'hidden ref payload should not cross dictionary_output'));
        $t->true(!str_contains($encoded, 'hidden ref debug data'));
        $t->true(!str_contains($encoded, 'hidden destination bytes'));
        $t->true(!str_contains($encoded, 'payload-only ref row should be dropped'));
    },
    'rejects non string pdftext span urls before WordPress rendering' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = $pdftextLinkedPage();
        $page['blocks'][0]['lines'][0]['spans'][1]['url'] = ['https://example.com/not-a-string'];

        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1));
    },
    'preserves sanitized pdftext character dictionaries when keep chars is requested' => static function (TestRunner $t) use ($pdftextCharsPage): void {
        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$pdftextCharsPage()], maxPages: 1, keepChars: true);
        $span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
        $charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0];
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same(true, $document['metadata']['pdftext_options']['keep_chars']);
        $t->same('Keptfi chars', $span['text']);
        $t->same("Keptfi chars\n", $charSpan['text']);
        $t->same([60.0, 80.0, 180.0, 104.0], $span['bbox']);
        $t->same([60.0, 80.0, 66.0, 104.0], $span['chars'][0]['bbox']);
        $t->same([66.0, 80.0, 72.0, 104.0], $charSpan['chars'][1]['bbox']);
        $t->same(['char', 'bbox', 'rotation', 'font', 'char_idx'], array_keys($span['chars'][0]));
        $t->same('K', $charSpan['chars'][0]['char']);
        $t->same(8, $charSpan['chars'][1]['char_idx']);
        $t->true(!str_contains($encoded, 'raw_pdf_bytes'), 'pdftext keep_chars=true should still drop arbitrary character payload keys.');
        $t->true(!str_contains($encoded, 'debug_payload'), 'pdftext keep_chars=true should still drop arbitrary character debug payloads.');
        $t->true(!str_contains($encoded, 'raw_image_bytes'), 'Arbitrary span payload bytes must not cross the dictionary core boundary.');
    },
    'keeps upstream-shaped character font dictionaries at the keep chars boundary' => static function (TestRunner $t) use ($pdftextCharsPage): void {
        $page = $pdftextCharsPage();
        $page['blocks'][0]['lines'][0]['spans'][0]['font']['embedded_font_program'] = 'hidden span font payload';
        $page['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['c'] = 'legacy alias should be dropped';
        $page['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['font']['raw_font_stream'] = 'hidden font payload';
        $page['blocks'][0]['lines'][0]['spans'][0]['chars'][1]['font']['debug_payload'] = ['stream' => 'hidden'];

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1, keepChars: true);
        $span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
        $charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0];
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same(['char', 'bbox', 'rotation', 'font', 'char_idx'], array_keys($span['chars'][0]));
        $t->same(['name', 'flags', 'weight', 'size'], array_keys($charSpan['font']));
        $t->same(['name', 'flags', 'weight', 'size'], array_keys($span['chars'][0]['font']));
        $t->same(['name', 'flags', 'weight', 'size'], array_keys($charSpan['chars'][1]['font']));
        $t->same('K', $span['chars'][0]['char']);
        $t->same('e', $charSpan['chars'][1]['char']);
        $t->true(!array_key_exists('c', $span['chars'][0]), 'pdftext keep_chars emits char, not legacy c aliases.');
        $t->true(!str_contains($encoded, 'legacy alias should be dropped'));
        $t->true(!str_contains($encoded, 'embedded_font_program'));
        $t->true(!str_contains($encoded, 'raw_font_stream'));
        $t->true(!str_contains($encoded, 'debug_payload'));
    },
];
