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

$pdftextCharsWithoutSpanRangePage = static function (): array {
    $font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];

    return [
        'page' => 37,
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'width' => 600.0,
        'height' => 800.0,
        'rotation' => 0,
        'blocks' => [[
            'bbox' => [0.12, 0.22, 0.48, 0.25],
            'lines' => [[
                'bbox' => [0.12, 0.22, 0.48, 0.25],
                'spans' => [[
                    'text' => "Inferred span range\n",
                    'bbox' => [0.12, 0.22, 0.48, 0.25],
                    'font' => $font,
                    'rotation' => 0,
                    'chars' => [
                        [
                            'char' => 'I',
                            'bbox' => [0.12, 0.22, 0.13, 0.25],
                            'font' => $font,
                            'rotation' => 0,
                            'char_idx' => 30,
                            'raw_payload' => 'missing span range payload must stay hidden',
                        ],
                        [
                            'char' => 'n',
                            'bbox' => [0.13, 0.22, 0.14, 0.25],
                            'font' => $font,
                            'rotation' => 0,
                            'char_idx' => 31,
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

$pdftextRotatedNormalizedPage = static function (): array {
    $font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];

    return [
        'page' => 44,
        'bbox' => [600.0, 800.0, 0.0, 0.0],
        'width' => 800.0,
        'height' => 600.0,
        'rotation' => 90,
        'raw_rotated_payload' => 'rotated normalized page payload must not cross dictionary_output',
        'blocks' => [[
            'bbox' => [0.10, 0.20, 0.30, 0.23],
            'lines' => [[
                'bbox' => [0.10, 0.20, 0.30, 0.23],
                'spans' => [[
                    'text' => "Rotated normalized bbox\n",
                    'bbox' => [0.10, 0.20, 0.30, 0.23],
                    'font' => $font,
                    'raw_span_payload' => 'rotated normalized span payload must not cross dictionary_output',
                ]],
                'raw_line_payload' => 'rotated normalized line payload must not cross dictionary_output',
            ]],
            'raw_block_payload' => 'rotated normalized block payload must not cross dictionary_output',
        ]],
    ];
};

$pdftextMojibakePage = static function (): array {
    $font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];
    $mojibakeEAcute = (string) hex2bin('c383c2a9');
    $mojibakeRightQuote = (string) hex2bin('c3a2e282ace284a2');
    $validATilde = (string) hex2bin('c3a3');
    $validEAcute = (string) hex2bin('c3a9');

    return [
        'page' => 52,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'blocks' => [[
            'bbox' => [72.0, 144.0, 430.0, 176.0],
            'lines' => [[
                'bbox' => [72.0, 144.0, 430.0, 176.0],
                'spans' => [
                    [
                        'text' => "Plugin caf{$mojibakeEAcute} d{$mojibakeRightQuote}import\n",
                        'bbox' => [72.0, 144.0, 260.0, 158.0],
                        'font' => $font,
                        'raw_encoding_payload' => 'hidden mojibake payload must not cross dictionary_output',
                    ],
                    [
                        'text' => " keeps S{$validATilde}o Paulo r{$validEAcute}sum{$validEAcute} intact\n",
                        'bbox' => [260.0, 144.0, 430.0, 158.0],
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
    'accepts json decoded pdftext dictionaries at the core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = $pdftextLinkedPage();
        $page['raw_json_adapter_payload'] = 'json adapter payload must not cross dictionary_output';
        $page['refs'][0]['raw_private_payload'] = 'json ref payload must not cross dictionary_output';
        $jsonDecodedPage = json_decode(json_encode($page, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: 'null');

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$jsonDecodedPage], maxPages: 1);
        $spans = $document['pages'][0]['blocks'][0]['lines'][0]['spans'];
        $refs = $document['pages'][0]['pdftext_source']['refs'];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same(12, $document['pages'][0]['pnum']);
        $t->same('https://example.com/import)docs', $spans[1]['url']);
        $t->same(false, $spans[2]['pdftext_url_is_safe']);
        $t->same([[
            'url' => '#page-3-xy',
            'page' => 3,
            'dest_pos' => [72.0, 96.0],
        ]], $refs);
        $t->same('Read [plugin docs](https://example.com/import\\)docs) but review script action', $blocks[0]['text']);
        $t->true(!str_contains($encoded, 'json adapter payload must not cross dictionary_output'));
        $t->true(!str_contains($encoded, 'json ref payload must not cross dictionary_output'));
        $t->true(!str_contains($blocks[0]['text'], 'javascript:'), 'Unsafe URI text from JSON-decoded pdftext spans stays review-only.');
    },
    'wraps direct supplied pdftext page dictionaries at the core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = $pdftextLinkedPage();
        $page['page'] = 92;
        $page['raw_direct_page_payload'] = 'direct page adapter payload must not cross dictionary_output';
        $page['blocks'][0]['raw_direct_block_payload'] = 'direct page block payload must not cross dictionary_output';
        $page['blocks'][0]['lines'][0]['raw_direct_line_payload'] = 'direct page line payload must not cross dictionary_output';
        $page['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Direct page ';
        $page['blocks'][0]['lines'][0]['spans'][1]['text'] = 'dictionary link';
        $page['blocks'][0]['lines'][0]['spans'][1]['raw_direct_span_payload'] = 'direct page span payload must not cross dictionary_output';
        $page['blocks'][0]['lines'][0]['spans'][2]['text'] = ' stays single page';
        unset($page['blocks'][0]['lines'][0]['spans'][2]['url']);

        $document = (new PdfTextDocumentExtractor())->getTextBlocks(
            $page,
            maxPages: 1,
            toc: [['title' => 'Direct page dictionary', 'level' => 1, 'page_index' => 92]]
        );
        $pageOut = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $document['page_range']);
        $t->same(1, $document['metadata']['source_pages']);
        $t->same(1, $document['metadata']['pages']);
        $t->same(0, $document['metadata']['start_page']);
        $t->same(1, $document['metadata']['max_pages']);
        $t->same(92, $pageOut['pnum']);
        $t->same('Direct page [dictionary link](https://example.com/import\\)docs) stays single page', $blocks[0]['text']);
        $t->same('https://example.com/import)docs', $pageOut['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('https://example.com/import)docs', $pageOut['char_blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same([
            [
                'url' => '#page-3-xy',
                'page' => 3,
                'dest_pos' => [72.0, 96.0],
            ],
        ], $pageOut['pdftext_source']['refs']);
        $t->true(!str_contains($encoded, 'direct page adapter payload'));
        $t->true(!str_contains($encoded, 'direct page block payload'));
        $t->true(!str_contains($encoded, 'direct page line payload'));
        $t->true(!str_contains($encoded, 'direct page span payload'));
    },
    'unwraps cached pdftext dictionary page envelopes at the core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $cover = $pdftextLinkedPage();
        $cover['page'] = 20;
        $cover['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Skipped envelope cover';

        $selected = $pdftextLinkedPage();
        $selected['page'] = 21;
        $selected['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Envelope selected ';
        $selected['blocks'][0]['lines'][0]['spans'][1]['text'] = 'page link';
        $selected['blocks'][0]['lines'][0]['spans'][2]['text'] = ' keeps adapter private data out';

        $appendix = $pdftextLinkedPage();
        $appendix['page'] = 22;
        $appendix['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Skipped envelope appendix';

        $document = (new PdfTextDocumentExtractor())->getTextBlocks(
            [
                'metadata' => [
                    'source' => 'cached pdftext dictionary_output',
                    'raw_private_payload' => 'envelope metadata payload must not cross dictionary_output',
                ],
                'pages' => [
                    20 => $cover,
                    21 => $selected,
                    22 => $appendix,
                ],
                'raw_adapter_payload' => 'top-level envelope payload must not cross dictionary_output',
            ],
            maxPages: 1,
            startPage: 1,
            toc: [['title' => 'Envelope selected page', 'level' => 1, 'page_index' => 21]]
        );

        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(1, $document['metadata']['pages']);
        $t->same(1, $document['metadata']['start_page']);
        $t->same(1, $document['metadata']['max_pages']);
        $t->same(21, $page['pnum']);
        $t->same('Envelope selected [page link](https://example.com/import\\)docs) keeps adapter private data out', $blocks[0]['text']);
        $t->same('https://example.com/import)docs', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same([
            [
                'url' => '#page-3-xy',
                'page' => 3,
                'dest_pos' => [72.0, 96.0],
            ],
        ], $page['pdftext_source']['refs']);
        $t->true(!str_contains($encoded, 'Skipped envelope cover'));
        $t->true(!str_contains($encoded, 'Skipped envelope appendix'));
        $t->true(!str_contains($encoded, 'envelope metadata payload must not cross'));
        $t->true(!str_contains($encoded, 'top-level envelope payload must not cross'));
    },
    'unwraps json object pages inside cached pdftext dictionary envelopes' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $cover = $pdftextLinkedPage();
        $cover['page'] = 40;
        $cover['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Skipped object-envelope cover';

        $selected = $pdftextLinkedPage();
        $selected['page'] = 41;
        $selected['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Object envelope selected ';
        $selected['blocks'][0]['lines'][0]['spans'][1]['text'] = 'page link';
        $selected['blocks'][0]['lines'][0]['spans'][2]['text'] = ' keeps object pages safe';
        unset($selected['blocks'][0]['lines'][0]['spans'][2]['url']);

        $appendix = $pdftextLinkedPage();
        $appendix['page'] = 42;
        $appendix['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Skipped object-envelope appendix';

        $envelope = (array) json_decode(json_encode([
            'metadata' => [
                'source' => 'json cached pdftext dictionary_output',
                'raw_private_payload' => 'object envelope metadata payload must not cross dictionary_output',
            ],
            'pages' => [
                40 => $cover,
                41 => $selected,
                42 => $appendix,
            ],
            'raw_adapter_payload' => 'object envelope top-level payload must not cross dictionary_output',
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}');

        $document = (new PdfTextDocumentExtractor())->getTextBlocks(
            $envelope,
            maxPages: 1,
            startPage: 1,
            toc: [['title' => 'Object envelope selected page', 'level' => 1, 'page_index' => 41]]
        );

        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->true($envelope['pages'] instanceof stdClass);
        $t->same([1], $document['page_range']);
        $t->same(41, $page['pnum']);
        $t->same('Object envelope selected [page link](https://example.com/import\\)docs) keeps object pages safe', $blocks[0]['text']);
        $t->same('https://example.com/import)docs', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same([[
            'url' => '#page-3-xy',
            'page' => 3,
            'dest_pos' => [72.0, 96.0],
        ]], $page['pdftext_source']['refs']);
        $t->true(!str_contains($encoded, 'Skipped object-envelope cover'));
        $t->true(!str_contains($encoded, 'Skipped object-envelope appendix'));
        $t->true(!str_contains($encoded, 'object envelope metadata payload must not cross'));
        $t->true(!str_contains($encoded, 'object envelope top-level payload must not cross'));
    },
    'unwraps named dictionary_output page envelopes at the core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $cover = $pdftextLinkedPage();
        $cover['page'] = 80;
        $cover['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Skipped dictionary-output cover';

        $selected = $pdftextLinkedPage();
        $selected['page'] = 81;
        $selected['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Named output selected ';
        $selected['blocks'][0]['lines'][0]['spans'][1]['text'] = 'dictionary link';
        $selected['blocks'][0]['lines'][0]['spans'][2]['text'] = ' keeps adapter payload hidden';
        unset($selected['blocks'][0]['lines'][0]['spans'][2]['url']);

        $appendix = $pdftextLinkedPage();
        $appendix['page'] = 82;
        $appendix['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Skipped dictionary-output appendix';

        $envelope = (array) json_decode(json_encode([
            'metadata' => [
                'source' => 'native adapter cache',
                'raw_private_payload' => 'dictionary_output envelope metadata payload must not cross',
            ],
            'dictionary_output' => [
                'metadata' => [
                    'source' => 'pdftext.dictionary_output',
                    'raw_private_payload' => 'nested dictionary_output metadata payload must not cross',
                ],
                'pages' => [
                    80 => $cover,
                    81 => $selected,
                    82 => $appendix,
                ],
                'raw_pdftext_payload' => 'dictionary_output adapter payload must not cross',
            ],
            'raw_adapter_payload' => 'top-level dictionary_output payload must not cross',
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}');

        $document = (new PdfTextDocumentExtractor())->getTextBlocks(
            $envelope,
            maxPages: 1,
            startPage: 1,
            toc: [['title' => 'Named dictionary output', 'level' => 1, 'page_index' => 81]]
        );

        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->true($envelope['dictionary_output'] instanceof stdClass);
        $t->same([1], $document['page_range']);
        $t->same(3, $document['metadata']['source_pages']);
        $t->same(81, $page['pnum']);
        $t->same('Named output selected [dictionary link](https://example.com/import\\)docs) keeps adapter payload hidden', $blocks[0]['text']);
        $t->same('https://example.com/import)docs', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same([[
            'url' => '#page-3-xy',
            'page' => 3,
            'dest_pos' => [72.0, 96.0],
        ]], $page['pdftext_source']['refs']);
        $t->true(!str_contains($encoded, 'Skipped dictionary-output cover'));
        $t->true(!str_contains($encoded, 'Skipped dictionary-output appendix'));
        $t->true(!str_contains($encoded, 'dictionary_output envelope metadata payload'));
        $t->true(!str_contains($encoded, 'nested dictionary_output metadata payload'));
        $t->true(!str_contains($encoded, 'dictionary_output adapter payload'));
        $t->true(!str_contains($encoded, 'top-level dictionary_output payload'));
    },
    'prefers explicit dictionary_output over stale adapter pages at the core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $staleCover = $pdftextLinkedPage();
        $staleCover['page'] = 400;
        $staleCover['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Stale top-level cover page should not import';

        $staleSelected = $pdftextLinkedPage();
        $staleSelected['page'] = 401;
        $staleSelected['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Stale top-level adapter page should not import';

        $staleAppendix = $pdftextLinkedPage();
        $staleAppendix['page'] = 402;
        $staleAppendix['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Stale top-level appendix page should not import';

        $currentCover = $pdftextLinkedPage();
        $currentCover['page'] = 500;
        $currentCover['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Skipped explicit dictionary_output cover';

        $currentSelected = $pdftextLinkedPage();
        $currentSelected['page'] = 501;
        $currentSelected['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Explicit dictionary_output ';
        $currentSelected['blocks'][0]['lines'][0]['spans'][1]['text'] = 'page link';
        $currentSelected['blocks'][0]['lines'][0]['spans'][2]['text'] = ' wins over stale adapter pages';
        unset($currentSelected['blocks'][0]['lines'][0]['spans'][2]['url']);

        $currentAppendix = $pdftextLinkedPage();
        $currentAppendix['page'] = 502;
        $currentAppendix['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Skipped explicit dictionary_output appendix';

        $document = (new PdfTextDocumentExtractor())->getTextBlocks(
            [
                'pages' => [
                    400 => $staleCover,
                    401 => $staleSelected,
                    402 => $staleAppendix,
                ],
                'dictionary_output' => [
                    'metadata' => [
                        'source' => 'pdftext.dictionary_output',
                        'raw_private_payload' => 'nested dictionary_output collision metadata must not cross',
                    ],
                    'pages' => [
                        500 => $currentCover,
                        501 => $currentSelected,
                        502 => $currentAppendix,
                    ],
                    'raw_pdftext_payload' => 'explicit dictionary_output collision payload must not cross',
                ],
                'raw_adapter_payload' => 'top-level stale adapter pages payload must not cross',
            ],
            maxPages: 1,
            startPage: 1,
            toc: [['title' => 'Explicit dictionary output wins', 'level' => 1, 'page_index' => 501]]
        );

        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(3, $document['metadata']['source_pages']);
        $t->same(501, $page['pnum']);
        $t->same('Explicit dictionary_output [page link](https://example.com/import\\)docs) wins over stale adapter pages', $blocks[0]['text']);
        $t->same('https://example.com/import)docs', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same([
            [
                'url' => '#page-3-xy',
                'page' => 3,
                'dest_pos' => [72.0, 96.0],
            ],
        ], $page['pdftext_source']['refs']);
        $t->true(!str_contains($encoded, 'Stale top-level'));
        $t->true(!str_contains($encoded, 'Skipped explicit dictionary_output cover'));
        $t->true(!str_contains($encoded, 'Skipped explicit dictionary_output appendix'));
        $t->true(!str_contains($encoded, 'nested dictionary_output collision metadata'));
        $t->true(!str_contains($encoded, 'explicit dictionary_output collision payload'));
        $t->true(!str_contains($encoded, 'top-level stale adapter pages payload'));
    },
    'prefers json decoded dictionary_output over stale adapter pages at the core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $staleSelected = $pdftextLinkedPage();
        $staleSelected['page'] = 610;
        $staleSelected['blocks'][0]['lines'][0]['spans'][0]['text'] = 'JSON stale top-level page should not import';

        $currentSelected = $pdftextLinkedPage();
        $currentSelected['page'] = 710;
        $currentSelected['blocks'][0]['lines'][0]['spans'][0]['text'] = 'JSON explicit dictionary_output ';
        $currentSelected['blocks'][0]['lines'][0]['spans'][1]['text'] = 'page link';
        $currentSelected['blocks'][0]['lines'][0]['spans'][2]['text'] = ' remains authoritative';
        unset($currentSelected['blocks'][0]['lines'][0]['spans'][2]['url']);

        $envelope = (array) json_decode(json_encode([
            'pages' => [
                610 => $staleSelected,
            ],
            'dictionary_output' => [
                'pages' => [
                    710 => $currentSelected,
                ],
                'raw_pdftext_payload' => 'json explicit dictionary_output payload must not cross',
            ],
            'raw_adapter_payload' => 'json stale adapter pages payload must not cross',
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}');

        $document = (new PdfTextDocumentExtractor())->getTextBlocks($envelope, maxPages: 1);
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->true($envelope['pages'] instanceof stdClass);
        $t->true($envelope['dictionary_output'] instanceof stdClass);
        $t->same([0], $document['page_range']);
        $t->same(710, $page['pnum']);
        $t->same('JSON explicit dictionary_output [page link](https://example.com/import\\)docs) remains authoritative', $blocks[0]['text']);
        $t->same('https://example.com/import)docs', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->true(!str_contains($encoded, 'JSON stale top-level page should not import'));
        $t->true(!str_contains($encoded, 'json explicit dictionary_output payload'));
        $t->true(!str_contains($encoded, 'json stale adapter pages payload'));
    },
    'prefers explicit pdftext envelope over stale adapter pages at the core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $staleSelected = $pdftextLinkedPage();
        $staleSelected['page'] = 810;
        $staleSelected['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Stale adapter pdftext page should not import';

        $currentSelected = $pdftextLinkedPage();
        $currentSelected['page'] = 910;
        $currentSelected['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Explicit pdftext envelope ';
        $currentSelected['blocks'][0]['lines'][0]['spans'][1]['text'] = 'page link';
        $currentSelected['blocks'][0]['lines'][0]['spans'][2]['text'] = ' wins before stale pages';
        unset($currentSelected['blocks'][0]['lines'][0]['spans'][2]['url']);

        $document = (new PdfTextDocumentExtractor())->getTextBlocks(
            [
                'pages' => [
                    810 => $staleSelected,
                ],
                'pdftext' => [
                    'metadata' => [
                        'source' => 'pdftext.dictionary_output',
                        'raw_private_payload' => 'pdftext envelope metadata payload must not cross',
                    ],
                    'pages' => [
                        910 => $currentSelected,
                    ],
                    'raw_pdftext_payload' => 'explicit pdftext envelope payload must not cross',
                ],
                'raw_adapter_payload' => 'stale adapter pdftext pages payload must not cross',
            ],
            maxPages: 1
        );

        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $document['page_range']);
        $t->same(1, $document['metadata']['source_pages']);
        $t->same(910, $page['pnum']);
        $t->same('Explicit pdftext envelope [page link](https://example.com/import\\)docs) wins before stale pages', $blocks[0]['text']);
        $t->same('https://example.com/import)docs', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->true(!str_contains($encoded, 'Stale adapter pdftext page should not import'));
        $t->true(!str_contains($encoded, 'pdftext envelope metadata payload'));
        $t->true(!str_contains($encoded, 'explicit pdftext envelope payload'));
        $t->true(!str_contains($encoded, 'stale adapter pdftext pages payload'));
    },
    'prefers explicit dictionary_output over stale direct page-shaped wrappers at the core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $staleDirect = $pdftextLinkedPage();
        $staleDirect['page'] = 930;
        $staleDirect['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Stale direct wrapper should not import';
        $staleDirect['raw_direct_wrapper_payload'] = 'stale direct wrapper payload must not cross dictionary_output';

        $current = $pdftextLinkedPage();
        $current['page'] = 931;
        $current['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Direct wrapper dictionary_output ';
        $current['blocks'][0]['lines'][0]['spans'][1]['text'] = 'page link';
        $current['blocks'][0]['lines'][0]['spans'][2]['text'] = ' wins over stale blocks';
        unset($current['blocks'][0]['lines'][0]['spans'][2]['url']);

        $staleDirect['dictionary_output'] = [
            'metadata' => [
                'source' => 'pdftext.dictionary_output',
                'raw_private_payload' => 'direct wrapper dictionary_output metadata must not cross',
            ],
            'pages' => [
                931 => $current,
            ],
            'raw_pdftext_payload' => 'direct wrapper dictionary_output payload must not cross',
        ];

        $document = (new PdfTextDocumentExtractor())->getTextBlocks($staleDirect, maxPages: 1);
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $document['page_range']);
        $t->same(1, $document['metadata']['source_pages']);
        $t->same(931, $page['pnum']);
        $t->same('Direct wrapper dictionary_output [page link](https://example.com/import\\)docs) wins over stale blocks', $blocks[0]['text']);
        $t->same('https://example.com/import)docs', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same([[
            'url' => '#page-3-xy',
            'page' => 3,
            'dest_pos' => [72.0, 96.0],
        ]], $page['pdftext_source']['refs']);
        $t->true(!str_contains($encoded, 'Stale direct wrapper should not import'));
        $t->true(!str_contains($encoded, 'stale direct wrapper payload'));
        $t->true(!str_contains($encoded, 'direct wrapper dictionary_output metadata'));
        $t->true(!str_contains($encoded, 'direct wrapper dictionary_output payload'));
    },
    'rejects malformed explicit dictionary_output wrappers before stale adapter pages at the core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $stalePage = $pdftextLinkedPage();
        $stalePage['page'] = 935;
        $stalePage['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Stale malformed dictionary_output adapter page must not import';

        $extractor = new PdfTextDocumentExtractor();
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [935 => $stalePage],
            'dictionary_output' => 'not a pdftext dictionary_output page list',
            'raw_adapter_payload' => 'malformed explicit dictionary_output scalar wrapper payload must not cross',
        ], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [935 => $stalePage],
            'dictionary_output' => null,
            'raw_adapter_payload' => 'malformed explicit dictionary_output null wrapper payload must not cross',
        ], maxPages: 1));
    },
    'rejects malformed explicit pdftext wrappers before stale adapter pages at the core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $stalePage = $pdftextLinkedPage();
        $stalePage['page'] = 937;
        $stalePage['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Stale malformed pdftext adapter page must not import';

        $extractor = new PdfTextDocumentExtractor();
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [937 => $stalePage],
            'pdftext' => 'not a pdftext dictionary_output page list',
            'raw_adapter_payload' => 'malformed explicit pdftext scalar wrapper payload must not cross',
        ], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [937 => $stalePage],
            'pdftext' => null,
            'raw_adapter_payload' => 'malformed explicit pdftext null wrapper payload must not cross',
        ], maxPages: 1));
    },
    'keeps direct pdftext pages authoritative when a non-page dictionary_output field is attached' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = $pdftextLinkedPage();
        $page['page'] = 936;
        $page['dictionary_output'] = 'adapter sidecar marker must stay fallback-only on direct pages';
        $page['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Direct page ';
        $page['blocks'][0]['lines'][0]['spans'][1]['text'] = 'dictionary link';
        $page['blocks'][0]['lines'][0]['spans'][2]['text'] = ' ignores malformed sidecar';
        unset($page['blocks'][0]['lines'][0]['spans'][2]['url']);

        $document = (new PdfTextDocumentExtractor())->getTextBlocks($page, maxPages: 1);
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $document['page_range']);
        $t->same(1, $document['metadata']['source_pages']);
        $t->same(936, $document['pages'][0]['pnum']);
        $t->same('Direct page [dictionary link](https://example.com/import\\)docs) ignores malformed sidecar', $blocks[0]['text']);
        $t->true(!str_contains($encoded, 'adapter sidecar marker must stay fallback-only'));
    },
    'keeps direct pdftext pages authoritative when a non-page pdftext field is attached' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = $pdftextLinkedPage();
        $page['page'] = 938;
        $page['pdftext'] = 'adapter pdftext sidecar marker must stay fallback-only on direct pages';
        $page['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Direct pdftext page ';
        $page['blocks'][0]['lines'][0]['spans'][1]['text'] = 'dictionary link';
        $page['blocks'][0]['lines'][0]['spans'][2]['text'] = ' ignores malformed cache sidecar';
        unset($page['blocks'][0]['lines'][0]['spans'][2]['url']);

        $document = (new PdfTextDocumentExtractor())->getTextBlocks($page, maxPages: 1);
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $document['page_range']);
        $t->same(1, $document['metadata']['source_pages']);
        $t->same(938, $document['pages'][0]['pnum']);
        $t->same('Direct pdftext page [dictionary link](https://example.com/import\\)docs) ignores malformed cache sidecar', $blocks[0]['text']);
        $t->true(!str_contains($encoded, 'adapter pdftext sidecar marker must stay fallback-only'));
    },
    'prefers explicit pdftext envelopes inside list-entry page-shaped wrappers at the core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = static function (int $pageNumber, string $lead, string $linkUrl, string $tail) use ($pdftextLinkedPage): array {
            $page = $pdftextLinkedPage();
            $page['page'] = $pageNumber;
            $page['blocks'][0]['lines'][0]['spans'][0]['text'] = $lead;
            $page['blocks'][0]['lines'][0]['spans'][1]['text'] = 'list-wrapper link';
            $page['blocks'][0]['lines'][0]['spans'][1]['url'] = $linkUrl;
            $page['blocks'][0]['lines'][0]['spans'][2]['text'] = $tail;
            unset($page['blocks'][0]['lines'][0]['spans'][2]['url']);

            return $page;
        };

        $cover = $page(940, 'Skipped page-shaped wrapper cover ', 'https://example.com/list-wrapper-cover', ' should not import');
        $staleSelected = $page(941, 'Stale page-shaped wrapper ', 'https://example.com/list-wrapper-stale', ' should not import');
        $currentSelected = $page(1941, 'Selected page-shaped wrapper pdftext ', 'https://example.com/list-wrapper-current', ' stays visible');
        $appendix = $page(942, 'Skipped page-shaped wrapper appendix ', 'https://example.com/list-wrapper-appendix', ' should not import');

        $staleSelected['raw_page_wrapper_payload'] = 'selected stale page-shaped wrapper payload must not cross';
        $staleSelected['pdftext'] = [
            'metadata' => [
                'source' => 'pdftext.dictionary_output',
                'raw_private_payload' => 'selected page-shaped wrapper pdftext metadata must not cross',
            ],
            'pages' => [
                1941 => $currentSelected,
            ],
            'raw_pdftext_payload' => 'selected page-shaped wrapper pdftext payload must not cross',
        ];

        $document = (new PdfTextDocumentExtractor())->getTextBlocks(
            [$cover, $staleSelected, $appendix],
            maxPages: 1,
            startPage: 1
        );
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(3, $document['metadata']['source_pages']);
        $t->same(1941, $page['pnum']);
        $t->same('Selected page-shaped wrapper pdftext [list-wrapper link](https://example.com/list-wrapper-current) stays visible', $blocks[0]['text']);
        $t->same('https://example.com/list-wrapper-current', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-3-xy', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Skipped page-shaped wrapper cover'));
        $t->true(!str_contains($encoded, 'Stale page-shaped wrapper'));
        $t->true(!str_contains($encoded, 'Skipped page-shaped wrapper appendix'));
        $t->true(!str_contains($encoded, 'selected stale page-shaped wrapper payload'));
        $t->true(!str_contains($encoded, 'selected page-shaped wrapper pdftext metadata'));
        $t->true(!str_contains($encoded, 'selected page-shaped wrapper pdftext payload'));
    },
    'unwraps json decoded pdftext page envelopes at the core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $selected = $pdftextLinkedPage();
        $selected['page'] = 920;
        $selected['blocks'][0]['lines'][0]['spans'][0]['text'] = 'JSON pdftext envelope ';
        $selected['blocks'][0]['lines'][0]['spans'][1]['text'] = 'page link';
        $selected['blocks'][0]['lines'][0]['spans'][2]['text'] = ' remains authoritative';
        unset($selected['blocks'][0]['lines'][0]['spans'][2]['url']);

        $envelope = (array) json_decode(json_encode([
            'metadata' => [
                'source' => 'native pdftext cache',
                'raw_private_payload' => 'json pdftext envelope metadata must not cross',
            ],
            'pdftext' => [
                'metadata' => [
                    'source' => 'pdftext.dictionary_output',
                    'raw_private_payload' => 'json nested pdftext metadata must not cross',
                ],
                'pages' => [
                    920 => $selected,
                ],
                'raw_pdftext_payload' => 'json pdftext envelope payload must not cross',
            ],
            'raw_adapter_payload' => 'json pdftext adapter payload must not cross',
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}');

        $document = (new PdfTextDocumentExtractor())->getTextBlocks($envelope, maxPages: 1);
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->true($envelope['pdftext'] instanceof stdClass);
        $t->true($envelope['pdftext']->pages instanceof stdClass);
        $t->same([0], $document['page_range']);
        $t->same(920, $page['pnum']);
        $t->same('JSON pdftext envelope [page link](https://example.com/import\\)docs) remains authoritative', $blocks[0]['text']);
        $t->same('https://example.com/import)docs', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->true(!str_contains($encoded, 'json pdftext envelope metadata'));
        $t->true(!str_contains($encoded, 'json nested pdftext metadata'));
        $t->true(!str_contains($encoded, 'json pdftext envelope payload'));
        $t->true(!str_contains($encoded, 'json pdftext adapter payload'));
    },
    'wraps singleton pdftext page dictionaries inside named core envelopes' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = static function (int $pageNumber, string $lead, string $linkUrl, string $tail) use ($pdftextLinkedPage): array {
            $page = $pdftextLinkedPage();
            $page['page'] = $pageNumber;
            $page['raw_singleton_payload'] = "singleton {$pageNumber} payload must not cross dictionary_output";
            $page['blocks'][0]['lines'][0]['spans'][0]['text'] = $lead;
            $page['blocks'][0]['lines'][0]['spans'][1]['text'] = 'singleton link';
            $page['blocks'][0]['lines'][0]['spans'][1]['url'] = $linkUrl;
            $page['blocks'][0]['lines'][0]['spans'][2]['text'] = $tail;
            unset($page['blocks'][0]['lines'][0]['spans'][2]['url']);

            return $page;
        };

        $stale = $page(990, 'Stale singleton adapter ', 'https://example.com/stale-singleton', ' should not import');
        $dictionaryOutputPage = $page(991, 'Direct dictionary_output ', 'https://example.com/direct-dictionary-output', ' stays one page');
        $pdftextPage = $page(992, 'Direct pdftext envelope ', 'https://example.com/direct-pdftext', ' wins before stale pages');
        $pagesPage = $page(993, 'Direct pages envelope ', 'https://example.com/direct-pages', ' stays one page');
        $extractor = new PdfTextDocumentExtractor();
        $processor = new MarkdownPostProcessor();

        $dictionaryOutputDocument = $extractor->getTextBlocks(
            [
                'pages' => [990 => $stale],
                'dictionary_output' => $dictionaryOutputPage,
                'raw_adapter_payload' => 'stale singleton adapter payload must not cross',
            ],
            maxPages: 1
        );
        $dictionaryOutputBlocks = $processor->mergeBlocks($processor->mergeSpans($dictionaryOutputDocument['pages']));
        $dictionaryOutputEncoded = json_encode($dictionaryOutputDocument, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $dictionaryOutputDocument['page_range']);
        $t->same(1, $dictionaryOutputDocument['metadata']['source_pages']);
        $t->same(991, $dictionaryOutputDocument['pages'][0]['pnum']);
        $t->same('Direct dictionary_output [singleton link](https://example.com/direct-dictionary-output) stays one page', $dictionaryOutputBlocks[0]['text']);
        $t->same('https://example.com/direct-dictionary-output', $dictionaryOutputDocument['pages'][0]['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-3-xy', $dictionaryOutputDocument['pages'][0]['pdftext_source']['refs'][0]['url']);
        $t->true(!str_contains($dictionaryOutputEncoded, 'Stale singleton adapter'));
        $t->true(!str_contains($dictionaryOutputEncoded, 'singleton 991 payload must not cross'));
        $t->true(!str_contains($dictionaryOutputEncoded, 'stale singleton adapter payload must not cross'));

        $pdftextDocument = $extractor->getTextBlocks(
            [
                'pages' => [990 => $stale],
                'pdftext' => $pdftextPage,
                'raw_adapter_payload' => 'stale pdftext singleton payload must not cross',
            ],
            maxPages: 1
        );
        $pdftextBlocks = $processor->mergeBlocks($processor->mergeSpans($pdftextDocument['pages']));
        $pdftextEncoded = json_encode($pdftextDocument, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $pdftextDocument['page_range']);
        $t->same(1, $pdftextDocument['metadata']['source_pages']);
        $t->same(992, $pdftextDocument['pages'][0]['pnum']);
        $t->same('Direct pdftext envelope [singleton link](https://example.com/direct-pdftext) wins before stale pages', $pdftextBlocks[0]['text']);
        $t->same('https://example.com/direct-pdftext', $pdftextDocument['pages'][0]['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->true(!str_contains($pdftextEncoded, 'Stale singleton adapter'));
        $t->true(!str_contains($pdftextEncoded, 'singleton 992 payload must not cross'));
        $t->true(!str_contains($pdftextEncoded, 'stale pdftext singleton payload must not cross'));

        $pagesDocument = $extractor->getTextBlocks(
            [
                'pages' => $pagesPage,
                'metadata' => ['raw_private_payload' => 'direct pages singleton metadata must not cross'],
            ],
            maxPages: 1
        );
        $pagesBlocks = $processor->mergeBlocks($processor->mergeSpans($pagesDocument['pages']));
        $pagesEncoded = json_encode($pagesDocument, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $pagesDocument['page_range']);
        $t->same(1, $pagesDocument['metadata']['source_pages']);
        $t->same(993, $pagesDocument['pages'][0]['pnum']);
        $t->same('Direct pages envelope [singleton link](https://example.com/direct-pages) stays one page', $pagesBlocks[0]['text']);
        $t->same('https://example.com/direct-pages', $pagesDocument['pages'][0]['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->true(!str_contains($pagesEncoded, 'singleton 993 payload must not cross'));
        $t->true(!str_contains($pagesEncoded, 'direct pages singleton metadata must not cross'));
    },
    'unwraps nested singleton pages dictionaries inside named core envelopes' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = static function (int $pageNumber, string $lead, string $linkUrl, string $tail) use ($pdftextLinkedPage): array {
            $page = $pdftextLinkedPage();
            $page['page'] = $pageNumber;
            $page['raw_nested_singleton_payload'] = "nested singleton {$pageNumber} payload must not cross dictionary_output";
            $page['blocks'][0]['lines'][0]['spans'][0]['text'] = $lead;
            $page['blocks'][0]['lines'][0]['spans'][1]['text'] = 'nested singleton link';
            $page['blocks'][0]['lines'][0]['spans'][1]['url'] = $linkUrl;
            $page['blocks'][0]['lines'][0]['spans'][2]['text'] = $tail;
            unset($page['blocks'][0]['lines'][0]['spans'][2]['url']);

            return $page;
        };

        $stale = $page(1010, 'Stale nested adapter ', 'https://example.com/stale-nested-singleton', ' should not import');
        $dictionaryOutputPage = $page(1011, 'Nested dictionary_output ', 'https://example.com/nested-dictionary-output', ' unwraps one page');
        $pdftextPage = $page(1012, 'Nested pdftext ', 'https://example.com/nested-pdftext', ' unwraps one page');
        $pagesPage = $page(1013, 'Nested pages ', 'https://example.com/nested-pages', ' unwraps one page');
        $extractor = new PdfTextDocumentExtractor();
        $processor = new MarkdownPostProcessor();

        $dictionaryOutputDocument = $extractor->getTextBlocks(
            [
                'pages' => [1010 => $stale],
                'dictionary_output' => [
                    'metadata' => ['raw_private_payload' => 'nested dictionary_output metadata must not cross'],
                    'pages' => $dictionaryOutputPage,
                    'raw_pdftext_payload' => 'nested dictionary_output wrapper payload must not cross',
                ],
                'raw_adapter_payload' => 'stale nested adapter payload must not cross',
            ],
            maxPages: 1
        );
        $dictionaryOutputBlocks = $processor->mergeBlocks($processor->mergeSpans($dictionaryOutputDocument['pages']));
        $dictionaryOutputEncoded = json_encode($dictionaryOutputDocument, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $dictionaryOutputDocument['page_range']);
        $t->same(1, $dictionaryOutputDocument['metadata']['source_pages']);
        $t->same(1011, $dictionaryOutputDocument['pages'][0]['pnum']);
        $t->same('Nested dictionary_output [nested singleton link](https://example.com/nested-dictionary-output) unwraps one page', $dictionaryOutputBlocks[0]['text']);
        $t->same('https://example.com/nested-dictionary-output', $dictionaryOutputDocument['pages'][0]['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-3-xy', $dictionaryOutputDocument['pages'][0]['pdftext_source']['refs'][0]['url']);
        $t->true(!str_contains($dictionaryOutputEncoded, 'Stale nested adapter'));
        $t->true(!str_contains($dictionaryOutputEncoded, 'nested singleton 1011 payload must not cross'));
        $t->true(!str_contains($dictionaryOutputEncoded, 'nested dictionary_output metadata must not cross'));
        $t->true(!str_contains($dictionaryOutputEncoded, 'nested dictionary_output wrapper payload must not cross'));
        $t->true(!str_contains($dictionaryOutputEncoded, 'stale nested adapter payload must not cross'));

        $pdftextDocument = $extractor->getTextBlocks(
            [
                'pages' => [1010 => $stale],
                'pdftext' => [
                    'metadata' => ['raw_private_payload' => 'nested pdftext metadata must not cross'],
                    'pages' => $pdftextPage,
                    'raw_pdftext_payload' => 'nested pdftext wrapper payload must not cross',
                ],
                'raw_adapter_payload' => 'stale nested pdftext adapter payload must not cross',
            ],
            maxPages: 1
        );
        $pdftextBlocks = $processor->mergeBlocks($processor->mergeSpans($pdftextDocument['pages']));
        $pdftextEncoded = json_encode($pdftextDocument, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $pdftextDocument['page_range']);
        $t->same(1, $pdftextDocument['metadata']['source_pages']);
        $t->same(1012, $pdftextDocument['pages'][0]['pnum']);
        $t->same('Nested pdftext [nested singleton link](https://example.com/nested-pdftext) unwraps one page', $pdftextBlocks[0]['text']);
        $t->same('https://example.com/nested-pdftext', $pdftextDocument['pages'][0]['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->true(!str_contains($pdftextEncoded, 'Stale nested adapter'));
        $t->true(!str_contains($pdftextEncoded, 'nested singleton 1012 payload must not cross'));
        $t->true(!str_contains($pdftextEncoded, 'nested pdftext metadata must not cross'));
        $t->true(!str_contains($pdftextEncoded, 'nested pdftext wrapper payload must not cross'));
        $t->true(!str_contains($pdftextEncoded, 'stale nested pdftext adapter payload must not cross'));

        $pagesDocument = $extractor->getTextBlocks(
            [
                'pages' => [
                    'metadata' => ['raw_private_payload' => 'nested pages metadata must not cross'],
                    'pages' => $pagesPage,
                    'raw_pages_payload' => 'nested pages wrapper payload must not cross',
                ],
                'raw_adapter_payload' => 'outer nested pages adapter payload must not cross',
            ],
            maxPages: 1
        );
        $pagesBlocks = $processor->mergeBlocks($processor->mergeSpans($pagesDocument['pages']));
        $pagesEncoded = json_encode($pagesDocument, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $pagesDocument['page_range']);
        $t->same(1, $pagesDocument['metadata']['source_pages']);
        $t->same(1013, $pagesDocument['pages'][0]['pnum']);
        $t->same('Nested pages [nested singleton link](https://example.com/nested-pages) unwraps one page', $pagesBlocks[0]['text']);
        $t->same('https://example.com/nested-pages', $pagesDocument['pages'][0]['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->true(!str_contains($pagesEncoded, 'nested singleton 1013 payload must not cross'));
        $t->true(!str_contains($pagesEncoded, 'nested pages metadata must not cross'));
        $t->true(!str_contains($pagesEncoded, 'nested pages wrapper payload must not cross'));
        $t->true(!str_contains($pagesEncoded, 'outer nested pages adapter payload must not cross'));
    },
    'unwraps list entry pdftext page envelopes at the core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = static function (int $pageNumber, string $lead, string $linkUrl, string $tail) use ($pdftextLinkedPage): array {
            $page = $pdftextLinkedPage();
            $page['page'] = $pageNumber;
            $page['raw_list_entry_payload'] = "list entry page {$pageNumber} payload must not cross dictionary_output";
            $page['blocks'][0]['lines'][0]['spans'][0]['text'] = $lead;
            $page['blocks'][0]['lines'][0]['spans'][1]['text'] = 'list-entry link';
            $page['blocks'][0]['lines'][0]['spans'][1]['url'] = $linkUrl;
            $page['blocks'][0]['lines'][0]['spans'][2]['text'] = $tail;
            unset($page['blocks'][0]['lines'][0]['spans'][2]['url']);

            return $page;
        };

        $cover = $page(1000, 'Skipped list-entry cover ', 'https://example.com/list-entry-cover', ' should not import');
        $selected = $page(1001, 'Selected list-entry ', 'https://example.com/list-entry-selected', ' stays visible');
        $appendix = $page(1002, 'Skipped list-entry appendix ', 'https://example.com/list-entry-appendix', ' should not import');

        $document = (new PdfTextDocumentExtractor())->getTextBlocks(
            [
                [
                    'dictionary_output' => [
                        'metadata' => [
                            'source' => 'cached first page dictionary_output',
                            'raw_private_payload' => 'list entry dictionary_output metadata must not cross',
                        ],
                        'pages' => [$cover],
                        'raw_pdftext_payload' => 'list entry dictionary_output payload must not cross',
                    ],
                    'raw_adapter_payload' => 'cover list entry adapter payload must not cross',
                ],
                [
                    'pdftext' => $selected,
                    'raw_adapter_payload' => 'selected list entry adapter payload must not cross',
                ],
                [
                    'pages' => [
                        '1002' => $appendix,
                    ],
                    'raw_adapter_payload' => 'appendix list entry adapter payload must not cross',
                ],
            ],
            maxPages: 1,
            startPage: 1,
            toc: [['title' => 'List entry envelope selected page', 'level' => 1, 'page_index' => 1001]]
        );

        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(3, $document['metadata']['source_pages']);
        $t->same(1001, $page['pnum']);
        $t->same('Selected list-entry [list-entry link](https://example.com/list-entry-selected) stays visible', $blocks[0]['text']);
        $t->same('https://example.com/list-entry-selected', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-3-xy', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Skipped list-entry cover'));
        $t->true(!str_contains($encoded, 'Skipped list-entry appendix'));
        $t->true(!str_contains($encoded, 'list entry page 1001 payload'));
        $t->true(!str_contains($encoded, 'list entry dictionary_output metadata'));
        $t->true(!str_contains($encoded, 'list entry dictionary_output payload'));
        $t->true(!str_contains($encoded, 'cover list entry adapter payload'));
        $t->true(!str_contains($encoded, 'selected list entry adapter payload'));
        $t->true(!str_contains($encoded, 'appendix list entry adapter payload'));
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
    'rejects fractional pdftext page numbers before WordPress page metadata' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $fractionalPage = $pdftextLinkedPage();
        $fractionalPage['page'] = 12.75;

        $integralFloatPage = $pdftextLinkedPage();
        $integralFloatPage['page'] = 12.0;

        $extractor = new PdfTextDocumentExtractor();
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$fractionalPage], maxPages: 1));

        $document = $extractor->getTextBlocks([$integralFloatPage], maxPages: 1);
        $t->same(12, $document['pages'][0]['pnum']);
        $t->same(12, $document['pages'][0]['pdftext_source']['page']);
    },
    'rejects negative pdftext page reference and character indexes before WordPress metadata' => static function (TestRunner $t) use ($pdftextLinkedPage, $pdftextCharsPage): void {
        $negativePage = $pdftextLinkedPage();
        $negativePage['page'] = -1;

        $negativeReferencePage = $pdftextLinkedPage();
        $negativeReferencePage['refs'] = [[
            'page' => -2,
            'idx' => 0,
            'coord' => [144.0, 216.0],
        ]];

        $negativeReferenceIndex = $pdftextLinkedPage();
        $negativeReferenceIndex['refs'] = [[
            'page' => 2,
            'idx' => -1,
            'coord' => [144.0, 216.0],
        ]];

        $negativeDestinationPage = $pdftextLinkedPage();
        $negativeDestinationPage['refs'] = [[
            'url' => '#negative-destination',
            'dest_page' => -3,
            'dest_pos' => [72.0, 144.0],
        ]];

        $negativeSpanRange = $pdftextCharsPage();
        $negativeSpanRange['blocks'][0]['lines'][0]['spans'][0]['char_start_idx'] = -1;
        $negativeSpanRange['blocks'][0]['lines'][0]['spans'][0]['char_end_idx'] = 8;

        $negativeCharIndex = $pdftextCharsPage();
        $negativeCharIndex['blocks'][0]['lines'][0]['spans'][0]['char_start_idx'] = 0;
        $negativeCharIndex['blocks'][0]['lines'][0]['spans'][0]['char_end_idx'] = 8;
        $negativeCharIndex['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char_idx'] = -1;

        $extractor = new PdfTextDocumentExtractor();
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$negativePage], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$negativeReferencePage], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$negativeReferenceIndex], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$negativeDestinationPage], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$negativeSpanRange], maxPages: 1, keepChars: true));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$negativeCharIndex], maxPages: 1, keepChars: true));

        $zeroIndexed = $pdftextCharsPage();
        $zeroIndexed['page'] = 0;
        $zeroIndexed['refs'] = [[
            'page' => 0,
            'idx' => 0,
            'coord' => [0.0, 0.0],
            'dest_page' => 0,
            'dest_pos' => [0.0, 0.0],
        ]];
        $zeroIndexed['blocks'][0]['lines'][0]['spans'][0]['char_start_idx'] = 0.0;
        $zeroIndexed['blocks'][0]['lines'][0]['spans'][0]['char_end_idx'] = 1.0;
        $zeroIndexed['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char_idx'] = 0.0;
        $zeroIndexed['blocks'][0]['lines'][0]['spans'][0]['chars'][1]['char_idx'] = 1.0;

        $document = $extractor->getTextBlocks([$zeroIndexed], maxPages: 1, keepChars: true);
        $page = $document['pages'][0];
        $span = $page['blocks'][0]['lines'][0]['spans'][0];
        $refs = $page['pdftext_source']['refs'];

        $t->same(0, $page['pnum']);
        $t->same(0, $page['pdftext_source']['page']);
        $t->same(0, $span['char_start_idx']);
        $t->same(1, $span['char_end_idx']);
        $t->same(0, $span['chars'][0]['char_idx']);
        $t->same(1, $span['chars'][1]['char_idx']);
        $t->same(0, $refs[0]['page']);
        $t->same(0, $refs[0]['idx']);
        $t->same('page-0-0', $refs[0]['ref']);
        $t->same('#page-0-0', $refs[0]['url']);
        $t->same(0, $refs[0]['dest_page']);
    },
    'drops non core pdftext page payload keys before WordPress metadata' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = $pdftextLinkedPage();
        $page['raw_page_bytes'] = 'raw page bytes must not cross dictionary_output';
        $page['metadata'] = [
            'document_page' => 999,
            'raw_nested_payload' => 'nested page metadata must not cross dictionary_output',
        ];
        $page['images'] = [
            ['payload' => 'page image payload must not cross dictionary_output'],
        ];

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
        $source = $document['pages'][0]['pdftext_source'];
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same(['page', 'bbox', 'rotation', 'width', 'height', 'refs'], array_keys($source));
        $t->same(12, $source['page']);
        $t->same([0.0, 0.0, 612.0, 792.0], $source['bbox']);
        $t->same('Read ', $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0]['text']);
        $t->true(!array_key_exists('metadata', $source), 'Supplied adapter metadata is not trusted pdftext page-source metadata.');
        $t->true(!str_contains($encoded, 'raw page bytes must not cross dictionary_output'));
        $t->true(!str_contains($encoded, 'nested page metadata must not cross dictionary_output'));
        $t->true(!str_contains($encoded, 'page image payload must not cross dictionary_output'));
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
    'drops empty pdftext spans before link promotion at the dictionary core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = $pdftextLinkedPage();
        $page['blocks'][0]['lines'][0]['spans'] = [
            [
                'text' => '',
                'bbox' => [72.0, 96.0, 72.0, 110.0],
                'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                'url' => 'https://example.com/empty-link',
                'raw_empty_payload' => 'empty safe-link span payload must not cross dictionary_output',
            ],
            [
                'text' => "\x00",
                'bbox' => [74.0, 96.0, 74.0, 110.0],
                'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                'url' => 'javascript:empty()',
                'raw_empty_payload' => 'empty unsafe-link span payload must not cross dictionary_output',
            ],
            [
                'text' => "Visible import\n",
                'bbox' => [76.0, 96.0, 176.0, 110.0],
                'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                'url' => 'https://example.com/visible',
            ],
        ];

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
        $spans = $document['pages'][0]['blocks'][0]['lines'][0]['spans'];
        $charSpans = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same(1, count($spans));
        $t->same(1, count($charSpans));
        $t->same('Visible import', $spans[0]['text']);
        $t->same("Visible import\n", $charSpans[0]['text']);
        $t->same('https://example.com/visible', $spans[0]['url']);
        $t->same('[Visible import](https://example.com/visible)', $blocks[0]['text']);
        $t->true(!str_contains($encoded, 'https://example.com/empty-link'));
        $t->true(!str_contains($encoded, 'javascript:empty'));
        $t->true(!str_contains($encoded, 'empty safe-link span payload'));
        $t->true(!str_contains($encoded, 'empty unsafe-link span payload'));
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
    'infers pdftext span character ranges from kept character indexes' => static function (TestRunner $t) use ($pdftextCharsWithoutSpanRangePage): void {
        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$pdftextCharsWithoutSpanRangePage()], maxPages: 1, keepChars: true);
        $span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
        $charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same(30, $span['char_start_idx'] ?? null);
        $t->same(31, $span['char_end_idx'] ?? null);
        $t->same(30, $charSpan['char_start_idx'] ?? null);
        $t->same(31, $charSpan['char_end_idx'] ?? null);
        $t->same(30, $span['chars'][0]['char_idx']);
        $t->same(31, $charSpan['chars'][1]['char_idx']);
        $t->same('Inferred span range', $blocks[0]['text']);
        $t->true(!str_contains($encoded, 'missing span range payload must stay hidden'));

        $invertedCharacterOrder = $pdftextCharsWithoutSpanRangePage();
        $invertedCharacterOrder['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char_idx'] = 32;
        $invertedCharacterOrder['blocks'][0]['lines'][0]['spans'][0]['chars'][1]['char_idx'] = 31;
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$invertedCharacterOrder], maxPages: 1, keepChars: true));
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
    'accepts single-codepoint pdftext characters and rejects multi-codepoint rows before WordPress rendering' => static function (TestRunner $t) use ($pdftextCharsPage): void {
        $singleCodepoint = $pdftextCharsPage();
        $singleCodepoint['blocks'][0]['lines'][0]['spans'][0]['text'] = "Emoji character rows\n";
        $singleCodepoint['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char'] = "\u{1F600}";
        $singleCodepoint['blocks'][0]['lines'][0]['spans'][0]['chars'][1]['char'] = "\u{00E9}";

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$singleCodepoint], maxPages: 1, keepChars: true);
        $span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
        $charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0];

        $t->same("\u{1F600}", $span['chars'][0]['char']);
        $t->same("\u{00E9}", $span['chars'][1]['char']);
        $t->same("\u{1F600}", $charSpan['chars'][0]['char']);
        $t->same("\u{00E9}", $charSpan['chars'][1]['char']);

        $emptyChar = $pdftextCharsPage();
        $emptyChar['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char'] = '';
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$emptyChar], maxPages: 1, keepChars: true));

        $twoCharacters = $pdftextCharsPage();
        $twoCharacters['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char'] = 'Ke';
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$twoCharacters], maxPages: 1, keepChars: true));

        $combiningSequence = $pdftextCharsPage();
        $combiningSequence['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char'] = "e\u{0301}";
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$combiningSequence], maxPages: 1, keepChars: true));
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
    'rejects impossible pdftext character index ranges before WordPress rendering' => static function (TestRunner $t) use ($pdftextCharsPage): void {
        $invertedRange = $pdftextCharsPage();
        $invertedRange['blocks'][0]['lines'][0]['spans'][0]['char_start_idx'] = 9;
        $invertedRange['blocks'][0]['lines'][0]['spans'][0]['char_end_idx'] = 8;
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$invertedRange], maxPages: 1));

        $charBeforeRange = $pdftextCharsPage();
        $charBeforeRange['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char_idx'] = 6;
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$charBeforeRange], maxPages: 1, keepChars: true));

        $charAfterRange = $pdftextCharsPage();
        $charAfterRange['blocks'][0]['lines'][0]['spans'][0]['chars'][1]['char_idx'] = 9;
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$charAfterRange], maxPages: 1, keepChars: true));

        $singleCharRange = $pdftextCharsPage();
        $singleCharRange['blocks'][0]['lines'][0]['spans'][0]['text'] = "Single\n";
        $singleCharRange['blocks'][0]['lines'][0]['spans'][0]['char_start_idx'] = 11.0;
        $singleCharRange['blocks'][0]['lines'][0]['spans'][0]['char_end_idx'] = 11.0;
        $singleCharRange['blocks'][0]['lines'][0]['spans'][0]['chars'] = [[
            'char' => 'S',
            'bbox' => [0.10, 0.10, 0.11, 0.13],
        ]];

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$singleCharRange], maxPages: 1, keepChars: true);
        $span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
        $charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0];

        $t->same(11, $span['char_start_idx']);
        $t->same(11, $span['char_end_idx']);
        $t->same(11, $span['chars'][0]['char_idx']);
        $t->same(11, $charSpan['chars'][0]['char_idx']);
    },
    'rejects missing pdftext font flags at the dictionary core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage, $pdftextCharsPage): void {
        $missingSpanFlags = $pdftextLinkedPage();
        unset($missingSpanFlags['blocks'][0]['lines'][0]['spans'][0]['font']['flags']);
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$missingSpanFlags], maxPages: 1));

        $missingCharFlags = $pdftextCharsPage();
        unset($missingCharFlags['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['font']['flags']);
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$missingCharFlags], maxPages: 1, keepChars: true));
    },
    'rejects fractional pdftext font flags before WordPress style metadata' => static function (TestRunner $t) use ($pdftextLinkedPage, $pdftextCharsPage): void {
        $fractionalSpanFlags = $pdftextLinkedPage();
        $fractionalSpanFlags['blocks'][0]['lines'][0]['spans'][0]['font']['flags'] = 1.5;
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$fractionalSpanFlags], maxPages: 1));

        $fractionalCharFlags = $pdftextCharsPage();
        $fractionalCharFlags['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['font']['flags'] = 33.25;
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$fractionalCharFlags], maxPages: 1, keepChars: true));

        $integralFloatFlags = $pdftextCharsPage();
        $integralFloatFlags['blocks'][0]['lines'][0]['spans'][0]['font']['flags'] = 33.0;
        $integralFloatFlags['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['font']['flags'] = 33.0;
        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$integralFloatFlags], maxPages: 1, keepChars: true);
        $span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
        $charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0];

        $t->same('Helvetica_fixed_pitch_non_symbolic', $span['font']);
        $t->same(33, $charSpan['font']['flags']);
        $t->same(33, $span['chars'][0]['font']['flags']);
    },
    'rejects negative pdftext font flags before WordPress style metadata' => static function (TestRunner $t) use ($pdftextLinkedPage, $pdftextCharsPage): void {
        $negativeSpanFlags = $pdftextLinkedPage();
        $negativeSpanFlags['blocks'][0]['lines'][0]['spans'][0]['font']['flags'] = -1;

        $negativeCharFlags = $pdftextCharsPage();
        $negativeCharFlags['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['font']['flags'] = -33;

        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$negativeSpanFlags], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$negativeCharFlags], maxPages: 1, keepChars: true));

        $zeroFlags = $pdftextCharsPage();
        $zeroFlags['blocks'][0]['lines'][0]['spans'][0]['font']['flags'] = 0.0;
        $zeroFlags['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['font']['flags'] = 0.0;

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$zeroFlags], maxPages: 1, keepChars: true);
        $span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
        $charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0];

        $t->same('Helvetica_', $span['font']);
        $t->same(0, $charSpan['font']['flags']);
        $t->same(0, $span['chars'][0]['font']['flags']);
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
    'rejects non finite pdftext numeric dictionaries before WordPress rendering' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $infinitePageBbox = $pdftextLinkedPage();
        $infinitePageBbox['bbox'][2] = INF;

        $nanPageWidth = $pdftextLinkedPage();
        $nanPageWidth['width'] = NAN;

        $nanSpanBbox = $pdftextLinkedPage();
        $nanSpanBbox['blocks'][0]['lines'][0]['spans'][0]['bbox'][2] = NAN;

        $infiniteFontSize = $pdftextLinkedPage();
        $infiniteFontSize['blocks'][0]['lines'][0]['spans'][0]['font']['size'] = INF;

        $nanReferenceCoordinate = $pdftextLinkedPage();
        $nanReferenceCoordinate['refs'][0]['dest_pos'][1] = NAN;

        $extractor = new PdfTextDocumentExtractor();
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$infinitePageBbox], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$nanPageWidth], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$nanSpanBbox], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$infiniteFontSize], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$nanReferenceCoordinate], maxPages: 1));
    },
    'rejects nonpositive pdftext source dimensions before WordPress metadata' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $zeroWidth = $pdftextLinkedPage();
        $zeroWidth['width'] = 0.0;

        $negativeWidth = $pdftextLinkedPage();
        $negativeWidth['width'] = -612.0;

        $zeroHeight = $pdftextLinkedPage();
        $zeroHeight['height'] = 0;

        $negativeHeight = $pdftextLinkedPage();
        $negativeHeight['height'] = -792;

        $validTinyPage = $pdftextLinkedPage();
        $validTinyPage['width'] = 1.0;
        $validTinyPage['height'] = 1.0;

        $extractor = new PdfTextDocumentExtractor();
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$zeroWidth], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$negativeWidth], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$zeroHeight], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$negativeHeight], maxPages: 1));

        $document = $extractor->getTextBlocks([$validTinyPage], maxPages: 1);
        $t->same(1.0, $document['pages'][0]['pdftext_source']['width']);
        $t->same(1.0, $document['pages'][0]['pdftext_source']['height']);
    },
    'rejects degenerate pdftext source page bboxes before WordPress geometry' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $zeroBboxWidth = $pdftextLinkedPage();
        $zeroBboxWidth['bbox'] = [72.0, 96.0, 72.0, 792.0];

        $zeroBboxHeight = $pdftextLinkedPage();
        $zeroBboxHeight['bbox'] = [0.0, 96.0, 612.0, 96.0];

        $reversedValidBbox = $pdftextLinkedPage();
        $reversedValidBbox['bbox'] = [612.0, 792.0, 0.0, 0.0];

        $extractor = new PdfTextDocumentExtractor();
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$zeroBboxWidth], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$zeroBboxHeight], maxPages: 1));

        $document = $extractor->getTextBlocks([$reversedValidBbox], maxPages: 1);
        $t->same([0.0, 0.0, 612.0, 792.0], $document['pages'][0]['bbox']);
        $t->same([612.0, 792.0, 0.0, 0.0], $document['pages'][0]['pdftext_source']['bbox']);
    },
    'preserves fractional pdftext span rotation metadata before WordPress rendering' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = $pdftextLinkedPage();
        $page['page'] = 77;
        $page['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Skewed ';
        $page['blocks'][0]['lines'][0]['spans'][0]['rotation'] = 12.5;
        $page['blocks'][0]['lines'][0]['spans'][1]['text'] = 'glyph angle';
        $page['blocks'][0]['lines'][0]['spans'][1]['url'] = 'https://example.com/skewed';
        $page['blocks'][0]['lines'][0]['spans'][1]['rotation'] = 270.0;
        $page['blocks'][0]['lines'][0]['spans'][2]['text'] = ' review';
        unset($page['blocks'][0]['lines'][0]['spans'][2]['url']);
        $page['blocks'][0]['lines'][0]['spans'][2]['rotation'] = 0.0;

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
        $spans = $document['pages'][0]['blocks'][0]['lines'][0]['spans'];
        $charSpans = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));

        $t->same(77, $document['pages'][0]['pnum']);
        $t->same(12.5, $spans[0]['rotation']);
        $t->same(270, $spans[1]['rotation']);
        $t->same(0, $spans[2]['rotation']);
        $t->same(12.5, $charSpans[0]['rotation']);
        $t->same(270.0, $charSpans[1]['rotation']);
        $t->same('Skewed [glyph angle](https://example.com/skewed) review', $blocks[0]['text']);
        $t->same('https://example.com/skewed', $spans[1]['url']);
    },
    'preserves pdftext character angles while rejecting malformed page rotations' => static function (TestRunner $t) use ($pdftextCharsPage, $pdftextLinkedPage): void {
        $characterAnglePage = $pdftextCharsPage();
        $characterAnglePage['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['rotation'] = 12.5;
        $characterAnglePage['blocks'][0]['lines'][0]['spans'][0]['chars'][1]['rotation'] = 270.0;

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$characterAnglePage], maxPages: 1, keepChars: true);
        $span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
        $charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0];

        $t->same(12.5, $span['chars'][0]['rotation']);
        $t->same(270, $span['chars'][1]['rotation']);
        $t->same(12.5, $charSpan['chars'][0]['rotation']);
        $t->same(270, $charSpan['chars'][1]['rotation']);

        $fractionalPageRotation = $pdftextLinkedPage();
        $fractionalPageRotation['rotation'] = 90.5;
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$fractionalPageRotation], maxPages: 1));

        $unsupportedPageRotation = $pdftextLinkedPage();
        $unsupportedPageRotation['rotation'] = 45;
        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$unsupportedPageRotation], maxPages: 1));

        $integralFloatPageRotation = $pdftextLinkedPage();
        $integralFloatPageRotation['bbox'] = [0.0, 0.0, 200.0, 300.0];
        $integralFloatPageRotation['rotation'] = 90.0;
        $accepted = (new PdfTextDocumentExtractor())->getTextBlocks([$integralFloatPageRotation], maxPages: 1);
        $t->same(90, $accepted['pages'][0]['rotation']);
        $t->same([0.0, 0.0, 300.0, 200.0], $accepted['pages'][0]['bbox']);
    },
    'scales rotated normalized pdftext bboxes from source page bbox dimensions' => static function (TestRunner $t) use ($pdftextRotatedNormalizedPage): void {
        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$pdftextRotatedNormalizedPage()], maxPages: 1);
        $page = $document['pages'][0];
        $block = $page['blocks'][0];
        $span = $block['lines'][0]['spans'][0];
        $charSpan = $page['char_blocks'][0]['lines'][0]['spans'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same(44, $page['pnum']);
        $t->same(90, $page['rotation']);
        $t->same([0.0, 0.0, 800.0, 600.0], $page['bbox']);
        $t->same([
            'page' => 44,
            'bbox' => [600.0, 800.0, 0.0, 0.0],
            'rotation' => 90,
            'width' => 800.0,
            'height' => 600.0,
        ], $page['pdftext_source']);
        $t->same([60.0, 160.0, 180.0, 184.0], $block['bbox']);
        $t->same([60.0, 160.0, 180.0, 184.0], $span['bbox']);
        $t->same([60.0, 160.0, 180.0, 184.0], $charSpan['bbox']);
        $t->same('Rotated normalized bbox', $span['text']);
        $t->same("Rotated normalized bbox\n", $charSpan['text']);
        $t->same('Rotated normalized bbox', $blocks[0]['text']);
        $t->true(!str_contains($encoded, 'rotated normalized page payload'));
        $t->true(!str_contains($encoded, 'rotated normalized block payload'));
        $t->true(!str_contains($encoded, 'rotated normalized line payload'));
        $t->true(!str_contains($encoded, 'rotated normalized span payload'));
    },
    'repairs mojibake in visible marker spans while preserving pdftext dictionary text' => static function (TestRunner $t) use ($pdftextMojibakePage): void {
        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$pdftextMojibakePage()], maxPages: 1);
        $spans = $document['pages'][0]['blocks'][0]['lines'][0]['spans'];
        $charSpans = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $mojibakeEAcute = (string) hex2bin('c383c2a9');
        $mojibakeRightQuote = (string) hex2bin('c3a2e282ace284a2');
        $repairedEAcute = (string) hex2bin('c3a9');
        $repairedRightQuote = (string) hex2bin('e28099');
        $validATilde = (string) hex2bin('c3a3');

        $t->same("Plugin caf{$repairedEAcute} d{$repairedRightQuote}import", $spans[0]['text']);
        $t->same(" keeps S{$validATilde}o Paulo r{$repairedEAcute}sum{$repairedEAcute} intact", $spans[1]['text']);
        $t->same("Plugin caf{$mojibakeEAcute} d{$mojibakeRightQuote}import\n", $charSpans[0]['text']);
        $t->same("Plugin caf{$repairedEAcute} d{$repairedRightQuote}import keeps S{$validATilde}o Paulo r{$repairedEAcute}sum{$repairedEAcute} intact", $blocks[0]['text']);
        $t->true(!str_contains($blocks[0]['text'], $mojibakeEAcute));
        $t->true(!str_contains($blocks[0]['text'], $mojibakeRightQuote));
        $t->true(str_contains($charSpans[0]['text'], $mojibakeEAcute), 'char_blocks preserve the pdftext dictionary source text for review.');
        $t->true(!str_contains($encoded, 'hidden mojibake payload must not cross dictionary_output'));
    },
    'rejects invalid utf8 pdftext string metadata before WordPress rendering' => static function (TestRunner $t) use ($pdftextLinkedPage, $pdftextCharsPage, $pdftextMojibakePage): void {
        $invalidBytes = chr(0xC3) . chr(0x28);

        $invalidSpanText = $pdftextLinkedPage();
        $invalidSpanText['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Invalid ' . $invalidBytes . " span\n";

        $invalidCharText = $pdftextCharsPage();
        $invalidCharText['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char'] = $invalidBytes;

        $invalidSpanFontName = $pdftextLinkedPage();
        $invalidSpanFontName['blocks'][0]['lines'][0]['spans'][0]['font']['name'] = 'Bad' . $invalidBytes;

        $invalidCharFontName = $pdftextCharsPage();
        $invalidCharFontName['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['font']['name'] = 'Bad' . $invalidBytes;

        $invalidSpanUrl = $pdftextLinkedPage();
        $invalidSpanUrl['blocks'][0]['lines'][0]['spans'][1]['url'] = 'https://example.com/' . $invalidBytes;

        $invalidRefUrl = $pdftextLinkedPage();
        $invalidRefUrl['refs'][0]['url'] = '#page-' . $invalidBytes;

        $invalidRefAnchor = $pdftextLinkedPage();
        $invalidRefAnchor['refs'] = [[
            'page' => 9,
            'idx' => 2,
            'ref' => 'page-' . $invalidBytes,
        ]];

        $extractor = new PdfTextDocumentExtractor();
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$invalidSpanText], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$invalidCharText], maxPages: 1, keepChars: true));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$invalidSpanFontName], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$invalidCharFontName], maxPages: 1, keepChars: true));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$invalidSpanUrl], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$invalidRefUrl], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$invalidRefAnchor], maxPages: 1));

        $document = $extractor->getTextBlocks([$pdftextMojibakePage()], maxPages: 1);
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $t->contains('Plugin café d’import', $blocks[0]['text']);
        $t->contains('São Paulo résumé', $blocks[0]['text']);
    },
    'records pdftext quote loosebox option at the dictionary core boundary' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $defaultDocument = (new PdfTextDocumentExtractor())->getTextBlocks([$pdftextLinkedPage()], maxPages: 1);
        $strictDocument = (new PdfTextDocumentExtractor())->getTextBlocks([$pdftextLinkedPage()], maxPages: 1, quoteLoosebox: false);

        $t->same(true, $defaultDocument['metadata']['pdftext_options']['quote_loosebox']);
        $t->same(false, $strictDocument['metadata']['pdftext_options']['quote_loosebox']);
        $t->same([0], $strictDocument['metadata']['pdftext_options']['page_range']);
        $t->same('Read ', $strictDocument['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0]['text']);
    },
    'accepts zero pdftext workers as sequential dictionary extraction metadata' => static function (TestRunner $t) use ($pdftextLinkedPage): void {
        $page = $pdftextLinkedPage();
        $page['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Zero-worker ';
        $page['blocks'][0]['lines'][0]['spans'][1]['text'] = 'pdftext dictionary';
        $page['blocks'][0]['lines'][0]['spans'][2]['text'] = ' stays sequential';

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1, workers: 0);
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $spans = $document['pages'][0]['blocks'][0]['lines'][0]['spans'];

        $t->same(0, $document['metadata']['pdftext_options']['workers']);
        $t->same([0], $document['metadata']['pdftext_options']['page_range']);
        $t->same(12, $document['pages'][0]['pnum']);
        $t->same('0_0', $spans[0]['span_id']);
        $t->same('Zero-worker [pdftext dictionary](https://example.com/import\\)docs) stays sequential', $blocks[0]['text']);
        $t->same('https://example.com/import)docs', $spans[1]['url']);
        $t->same('javascript:alert(1)', $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][2]['url']);

        $t->throws(InvalidArgumentException::class, static fn () => (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1, workers: -1));
    },
];
