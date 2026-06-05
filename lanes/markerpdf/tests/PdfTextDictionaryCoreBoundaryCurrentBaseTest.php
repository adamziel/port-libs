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

$pdftextMinimalCharsPage = static function (): array {
    $font = ['name' => 'Courier', 'flags' => 1 << 0, 'weight' => 400, 'size' => 10.0];

    return [
        'page' => 21,
        'bbox' => [0.0, 0.0, 640.0, 480.0],
        'width' => 640.0,
        'height' => 480.0,
        'rotation' => 0,
        'blocks' => [[
            'bbox' => [0.20, 0.30, 0.46, 0.34],
            'lines' => [[
                'bbox' => [0.20, 0.30, 0.46, 0.34],
                'spans' => [[
                    'text' => "Minimal chars\n",
                    'bbox' => [0.20, 0.30, 0.46, 0.34],
                    'font' => $font + ['raw_font_program' => 'hidden parent font payload'],
                    'rotation' => 270,
                    'char_start_idx' => 41,
                    'char_end_idx' => 42,
                    'chars' => [
                        [
                            'char' => 'M',
                            'bbox' => [0.20, 0.30, 0.215, 0.34],
                            'raw_pdf_bytes' => 'hidden minimal char payload',
                        ],
                        [
                            'char' => 'i',
                            'bbox' => [0.215, 0.30, 0.23, 0.34],
                            'debug_payload' => ['stream' => 'hidden minimal debug payload'],
                        ],
                    ],
                ]],
            ]],
        ]],
    ];
};

$pdftextScriptPage = static function (): array {
    $font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];

    return [
        'page' => 31,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'blocks' => [[
            'bbox' => [72.0, 104.0, 190.0, 118.0],
            'lines' => [[
                'bbox' => [72.0, 104.0, 190.0, 118.0],
                'spans' => [
                    [
                        'text' => 'H',
                        'bbox' => [72.0, 104.0, 80.0, 118.0],
                        'font' => $font,
                    ],
                    [
                        'text' => " 2 \n",
                        'bbox' => [80.0, 100.0, 88.0, 110.0],
                        'font' => $font,
                        'superscript' => true,
                        'chars' => [['char' => '2', 'bbox' => [80.0, 100.0, 88.0, 110.0]]],
                    ],
                    [
                        'text' => 'O',
                        'bbox' => [88.0, 104.0, 98.0, 118.0],
                        'font' => $font,
                    ],
                    [
                        'text' => " i \n",
                        'bbox' => [110.0, 112.0, 118.0, 122.0],
                        'font' => $font,
                        'subscript' => true,
                        'raw_script_payload' => 'script payload should not cross dictionary_output',
                    ],
                    [
                        'text' => ' marker',
                        'bbox' => [118.0, 104.0, 190.0, 118.0],
                        'font' => $font,
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
    'synthesizes pdftext reference anchors from upstream Reference dictionaries' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = $pdftextLinkedPage();
        $page['refs'] = [
            [
                'page' => 9,
                'idx' => 2,
                'coord' => [144.0, 216.0],
                'raw_private_payload' => 'hidden upstream Reference payload',
            ],
            [
                'page' => 9,
                'idx' => 3,
                'coord' => [0.0, 0.0],
                'url' => 'javascript:alert(1)',
            ],
        ];

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
        $refs = $document['pages'][0]['pdftext_source']['refs'];
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same(9, $refs[0]['page']);
        $t->same(2, $refs[0]['idx']);
        $t->same([144.0, 216.0], $refs[0]['coord']);
        $t->same('page-9-2', $refs[0]['ref']);
        $t->same('#page-9-2', $refs[0]['url']);
        $t->true(!str_contains($encoded, 'hidden upstream Reference payload'));
        $t->true(!array_key_exists('url', $refs[1]), 'Supplied unsafe pdftext ref URLs remain review-only and are not replaced by synthesized anchors.');
        $t->same('page-9-3', $refs[1]['ref']);
    },
    'rejects fractional pdftext reference integer metadata before anchor synthesis' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $fractionalPage = $pdftextLinkedPage();
        $fractionalPage['refs'] = [[
            'page' => 9.5,
            'idx' => 2,
            'coord' => [144.0, 216.0],
        ]];

        $fractionalDestinationPage = $pdftextLinkedPage();
        $fractionalDestinationPage['refs'] = [[
            'url' => '#appendix',
            'dest_page' => 4.25,
            'dest_pos' => [72.0, 144.0],
        ]];

        $fractionalIndex = $pdftextLinkedPage();
        $fractionalIndex['refs'] = [[
            'page' => 9,
            'idx' => 2.5,
            'coord' => [144.0, 216.0],
        ]];

        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$fractionalPage], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$fractionalDestinationPage], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$fractionalIndex], maxPages: 1));
    },
    'honors pdftext disable_links at the dictionary core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = $pdftextLinkedPage();
        $page['refs'][0]['raw_private_payload'] = 'hidden disabled-link ref payload';

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1, disableLinks: true);
        $page = $document['pages'][0];
        $spans = $page['blocks'][0]['lines'][0]['spans'];
        $charSpans = $page['char_blocks'][0]['lines'][0]['spans'];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same(true, $document['metadata']['pdftext_options']['disable_links']);
        $t->true(!array_key_exists('refs', $page['pdftext_source']), 'disable_links skips pdftext page reference annotation.');
        $t->true(!array_key_exists('url', $spans[1]), 'Safe span links are not promoted when pdftext link annotation is disabled.');
        $t->true(!array_key_exists('pdftext_url', $spans[1]), 'Disabled pdftext links should not remain as review URLs.');
        $t->true(!array_key_exists('pdftext_url_is_safe', $spans[1]), 'Disabled pdftext links should not retain safety flags.');
        $t->true(!array_key_exists('url', $charSpans[1]), 'Stored char_blocks mirror dictionary_output with no span links.');
        $t->true(!array_key_exists('url', $charSpans[2]), 'Unsafe span link payloads are stripped with disable_links.');
        $t->same('Read plugin docs but review script action', $blocks[0]['text']);
        $t->true(!str_contains($blocks[0]['text'], 'https://example.com/import'), 'WordPress Markdown should not render disabled pdftext links.');
        $t->true(!str_contains($encoded, 'hidden disabled-link ref payload'));
        $t->true(!str_contains($encoded, 'javascript:alert'));
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
    'accepts locked pdftext minimal keep chars dictionaries at the core boundary' => static function (TestRunner $t) use ($pdftextMinimalCharsPage): void {
        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$pdftextMinimalCharsPage()], maxPages: 1, keepChars: true);
        $span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
        $charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same('Minimal chars', $span['text']);
        $t->same("Minimal chars\n", $charSpan['text']);
        $t->same([128.0, 144.0, 294.4, 163.2], $span['bbox']);
        $t->same([128.0, 144.0, 137.6, 163.2], $span['chars'][0]['bbox']);
        $t->same([137.6, 144.0, 147.2, 163.2], $charSpan['chars'][1]['bbox']);
        $t->same(270, $span['chars'][0]['rotation']);
        $t->same(['name' => 'Courier', 'flags' => 1 << 0, 'weight' => 400, 'size' => 10.0], $charSpan['chars'][0]['font']);
        $t->same(41, $span['chars'][0]['char_idx']);
        $t->same(42, $charSpan['chars'][1]['char_idx']);
        $t->same('Minimal chars', $blocks[0]['text']);
        $t->true(!str_contains($encoded, 'hidden parent font payload'));
        $t->true(!str_contains($encoded, 'hidden minimal char payload'));
        $t->true(!str_contains($encoded, 'hidden minimal debug payload'));
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
    'requires pdftext span chars when keep chars is requested' => static function (TestRunner $t) use ($pdftextCharsPage): void {
        $missingChars = $pdftextCharsPage();
        unset($missingChars['blocks'][0]['lines'][0]['spans'][0]['chars']);
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$missingChars], maxPages: 1, keepChars: true));
    },
    'rejects malformed pdftext keep chars rows before WordPress rendering' => static function (TestRunner $t) use ($pdftextCharsPage): void {
        $missingChar = $pdftextCharsPage();
        unset($missingChar['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char']);
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$missingChar], maxPages: 1, keepChars: true));

        $badBbox = $pdftextCharsPage();
        $badBbox['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['bbox'] = [0.10, 0.10, 'right', 0.13];
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$badBbox], maxPages: 1, keepChars: true));

        $badFont = $pdftextCharsPage();
        $badFont['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['font']['weight'] = 'bold';
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$badFont], maxPages: 1, keepChars: true));

        $badRotation = $pdftextCharsPage();
        $badRotation['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['rotation'] = '0';
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$badRotation], maxPages: 1, keepChars: true));

        $badIndex = $pdftextCharsPage();
        $badIndex['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char_idx'] = '7';
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$badIndex], maxPages: 1, keepChars: true));
    },
    'rejects fractional pdftext character indexes before WordPress rendering' => static function (TestRunner $t) use ($pdftextCharsPage): void {
        $fractionalStart = $pdftextCharsPage();
        $fractionalStart['blocks'][0]['lines'][0]['spans'][0]['char_start_idx'] = 7.5;
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$fractionalStart], maxPages: 1, keepChars: true));

        $fractionalEnd = $pdftextCharsPage();
        $fractionalEnd['blocks'][0]['lines'][0]['spans'][0]['char_end_idx'] = 8.25;
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$fractionalEnd], maxPages: 1));

        $fractionalChar = $pdftextCharsPage();
        $fractionalChar['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char_idx'] = 7.25;
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$fractionalChar], maxPages: 1, keepChars: true));

        $integralFloats = $pdftextCharsPage();
        $integralFloats['blocks'][0]['lines'][0]['spans'][0]['char_start_idx'] = 7.0;
        $integralFloats['blocks'][0]['lines'][0]['spans'][0]['char_end_idx'] = 8.0;
        $integralFloats['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char_idx'] = 7.0;
        $integralFloats['blocks'][0]['lines'][0]['spans'][0]['chars'][1]['char_idx'] = 8.0;

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$integralFloats], maxPages: 1, keepChars: true);
        $span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
        $charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0];

        $t->same(7, $span['char_start_idx']);
        $t->same(8, $span['char_end_idx']);
        $t->same(7, $span['chars'][0]['char_idx']);
        $t->same(8, $charSpan['chars'][1]['char_idx']);
    },
    'rejects missing pdftext font flags at the dictionary core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage, $pdftextCharsPage): void {
        $missingSpanFlags = $pdftextLinkedPage();
        unset($missingSpanFlags['blocks'][0]['lines'][0]['spans'][0]['font']['flags']);
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$missingSpanFlags], maxPages: 1));

        $missingCharFlags = $pdftextCharsPage();
        unset($missingCharFlags['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['font']['flags']);
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$missingCharFlags], maxPages: 1, keepChars: true));
    },
    'preserves pdftext superscript and subscript flags at the core boundary' => static function (TestRunner $t) use ($pdftextScriptPage): void {
        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$pdftextScriptPage()], maxPages: 1);
        $spans = $document['pages'][0]['blocks'][0]['lines'][0]['spans'];
        $charSpans = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same(true, $spans[1]['has_superscript']);
        $t->same(true, $spans[3]['has_subscript']);
        $t->true(!array_key_exists('has_superscript', $spans[0]), 'Plain spans should not gain script metadata.');
        $t->true(!array_key_exists('has_subscript', $spans[4]), 'Plain spans should not gain script metadata.');
        $t->same(true, $charSpans[1]['superscript']);
        $t->same(true, $charSpans[3]['subscript']);
        $t->same('2', $spans[1]['text']);
        $t->same('i', $spans[3]['text']);
        $t->same('H2Oi marker', $blocks[0]['text']);
        $t->true(!str_contains($encoded, 'script payload should not cross dictionary_output'));
    },
    'rejects non boolean pdftext script flags before WordPress rendering' => static function (TestRunner $t) use ($pdftextScriptPage): void {
        $page = $pdftextScriptPage();
        $page['blocks'][0]['lines'][0]['spans'][1]['superscript'] = 'yes';

        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1));
    },
];
