<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$pdftextPage = static function (int $page, string $text, float $top = 72.0): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'rotation' => 0,
        'blocks' => [
            [
                'lines' => [
                    [
                        'bbox' => [72.0, $top, 460.0, $top + 12.0],
                        'spans' => [
                            [
                                'text' => $text,
                                'bbox' => [72.0, $top, 460.0, $top + 12.0],
                                'font' => ['name' => 'Times-Roman', 'flags' => null, 'weight' => 400, 'size' => 11.0],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
};

$pdftextLinesPage = static function (int $page, array $lines): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'rotation' => 0,
        'blocks' => [[
            'lines' => array_map(
                static fn (array $line): array => [
                    'bbox' => $line['bbox'],
                    'spans' => [[
                        'text' => $line['text'],
                        'bbox' => $line['bbox'],
                        'font' => ['name' => 'Times-Roman', 'flags' => null, 'weight' => 400, 'size' => 11.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

return [
    'slices supplied pdftext dictionary pages like upstream get_text_blocks' => static function (TestRunner $t) use ($pdftextPage): void {
        $toc = [
            ['title' => 'Front matter', 'level' => 1, 'page_index' => 0],
            ['title' => 'Import range', 'level' => 1, 'page_index' => 8],
        ];

        $result = (new PdfTextDocumentExtractor())->getTextBlocks(
            [
                $pdftextPage(7, 'Skipped cover page'),
                $pdftextPage(8, 'Selected WordPress import page'),
                $pdftextPage(9, 'Second selected page'),
            ],
            maxPages: 5,
            startPage: 1,
            toc: $toc
        );

        $t->same([1, 2], $result['page_range']);
        $t->same(2, $result['metadata']['pages']);
        $t->same(1, $result['metadata']['start_page']);
        $t->same(2, $result['metadata']['max_pages']);
        $t->same($toc, $result['metadata']['pdf_toc']);
        $t->same(8, $result['pages'][0]['pnum']);
        $t->same(9, $result['pages'][1]['pnum']);
        $t->same('0_0', $result['pages'][0]['blocks'][0]['lines'][0]['spans'][0]['span_id']);
        $t->same('1_0', $result['pages'][1]['blocks'][0]['lines'][0]['spans'][0]['span_id']);
    },
    'treats zero max_pages like upstream falsey max page input' => static function (TestRunner $t) use ($pdftextPage): void {
        $result = (new PdfTextDocumentExtractor())->getTextBlocks(
            [$pdftextPage(0, 'First'), $pdftextPage(1, 'Second')],
            maxPages: 0
        );

        $t->same([0, 1], $result['page_range']);
        $t->same(2, $result['metadata']['pages']);
    },
    'records pdftext dictionary output options for the selected page range' => static function (TestRunner $t) use ($pdftextPage): void {
        $result = (new PdfTextDocumentExtractor())->getTextBlocks(
            [
                $pdftextPage(3, 'Skipped front matter'),
                $pdftextPage(4, 'Dictionary core boundary'),
                $pdftextPage(5, 'Deferred appendix'),
            ],
            maxPages: 1,
            startPage: 1,
            flattenPdf: true,
            workers: 2
        );

        $t->same([1], $result['page_range']);
        $t->same([
            'page_range' => [1],
            'keep_chars' => false,
            'flatten_pdf' => true,
            'workers' => 2,
        ], $result['metadata']['pdftext_options']);
        $t->same(4, $result['pages'][0]['pnum']);
    },
    'preserves pdftext page source dimensions through document extraction' => static function (TestRunner $t) use ($pdftextPage): void {
        $page = $pdftextPage(12, 'Dictionary source geometry boundary');
        $page['bbox'] = [0.0, 0.0, 400.0, 600.0];
        $page['width'] = 400.0;
        $page['height'] = 600.0;
        $page['rotation'] = 270;

        $result = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);

        $t->same([0.0, 0.0, 600.0, 400.0], $result['pages'][0]['bbox']);
        $t->same([
            'page' => 12,
            'bbox' => [0.0, 0.0, 400.0, 600.0],
            'rotation' => 270,
            'width' => 400.0,
            'height' => 600.0,
        ], $result['pages'][0]['pdftext_source']);
        $t->same('Dictionary source geometry boundary', $result['pages'][0]['blocks'][0]['lines'][0]['spans'][0]['text']);
    },
    'sanitizes pdftext keep chars false dictionary payloads before WordPress import' => static function (TestRunner $t) use ($pdftextPage): void {
        $page = $pdftextPage(2, 'Dictionary payload boundary');
        $page['blocks'][0]['pdfium_block_type'] = 'text';
        $page['blocks'][0]['lines'][0]['baseline'] = [72.0, 100.0, 440.0, 100.0];
        $page['blocks'][0]['lines'][0]['spans'][0]['char_start_idx'] = 18;
        $page['blocks'][0]['lines'][0]['spans'][0]['char_end_idx'] = 45;
        $page['blocks'][0]['lines'][0]['spans'][0]['chars'] = [
            ['c' => 'D', 'bbox' => [72.0, 96.0, 78.0, 110.0]],
        ];

        $result = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
        $span = $result['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
        $charBlock = $result['pages'][0]['char_blocks'][0];
        $charSpan = $charBlock['lines'][0]['spans'][0];

        $t->same('Dictionary payload boundary', $span['text']);
        $t->same(18, $span['char_start_idx']);
        $t->same(45, $span['char_end_idx']);
        $t->true(!array_key_exists('chars', $span), 'Document get_text_blocks path should reflect pdftext keep_chars=false.');
        $t->true(!array_key_exists('chars', $charSpan), 'Stored char_blocks should not retain raw char payloads for keep_chars=false.');
        $t->true(!array_key_exists('pdfium_block_type', $charBlock), 'pdftext dictionary_output strips non-core block keys.');
        $t->true(!array_key_exists('baseline', $charBlock['lines'][0]), 'pdftext dictionary_output strips non-core line keys.');
    },
    'strips non-core pdftext span payload keys from document char blocks' => static function (TestRunner $t) use ($pdftextPage): void {
        $page = $pdftextPage(3, "Span payload boundary\r\n");
        $page['blocks'][0]['lines'][0]['spans'][0]['rotation'] = 0;
        $page['blocks'][0]['lines'][0]['spans'][0]['char_start_idx'] = 4;
        $page['blocks'][0]['lines'][0]['spans'][0]['char_end_idx'] = 25;
        $page['blocks'][0]['lines'][0]['spans'][0]['raw_image_bytes'] = "\xFF\xD8not visible text";
        $page['blocks'][0]['lines'][0]['spans'][0]['debug_payload'] = [
            'private_stream' => 'decoy payload should not reach char_blocks',
        ];
        $page['blocks'][0]['lines'][0]['spans'][0]['chars'] = [
            ['char' => 'S', 'bbox' => [72.0, 96.0, 78.0, 110.0]],
        ];

        $result = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
        $span = $result['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
        $charSpan = $result['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0];
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same('Span payload boundary', $span['text']);
        $t->same('Span payload boundary' . "\n", $charSpan['text']);
        $t->same(0, $span['rotation']);
        $t->same(4, $span['char_start_idx']);
        $t->same(25, $span['char_end_idx']);
        $t->same(['text', 'bbox', 'font', 'rotation', 'char_start_idx', 'char_end_idx'], array_keys($charSpan));
        $t->true(!array_key_exists('chars', $charSpan), 'keep_chars=false must remove raw character payloads from stored char_blocks.');
        $t->true(!array_key_exists('raw_image_bytes', $charSpan), 'pdftext dictionary_output does not return arbitrary span payload bytes.');
        $t->true(!array_key_exists('debug_payload', $charSpan), 'pdftext dictionary_output does not return arbitrary span debug payloads.');
        $t->true(!str_contains($encoded, 'not visible text'));
        $t->true(!str_contains($encoded, 'private_stream'));
    },
    'normalizes pdftext dictionary_output span text before WordPress import' => static function (TestRunner $t) use ($pdftextPage): void {
        $page = $pdftextPage(2, 'placeholder');
        $page['blocks'][0]['lines'][0]['spans'][0]['text'] = "Of\u{FB01}ce docu\x02ment\u{00A0}keeps\u{FEFF}clean\x00 text\r\n";

        $result = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
        $span = $result['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
        $charSpan = $result['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0];

        $t->same('Office document keeps clean text', $span['text']);
        $t->same("Office docu-\nment keeps clean text\n", $charSpan['text']);
        $t->true(!str_contains($span['text'], "\x02"), 'Visible WordPress text must not retain pdftext hyphen sentinels.');
        $t->true(!str_contains($span['text'], "\x00"), 'Visible WordPress text must not retain unsafe control bytes.');
        $t->true(!str_contains($span['text'], "\u{FB01}"), 'Visible WordPress text must expand pdftext ligatures.');
    },
    'unnormalizes pdftext dictionary child bboxes before WordPress import' => static function (TestRunner $t): void {
        $page = [
            'page' => 5,
            'bbox' => [0.0, 0.0, 612.0, 792.0],
            'width' => 612.0,
            'height' => 792.0,
            'rotation' => 0,
            'blocks' => [[
                'bbox' => [0.10, 0.20, 0.70, 0.24],
                'lines' => [[
                    'bbox' => [0.10, 0.20, 0.70, 0.24],
                    'spans' => [[
                        'text' => 'Normalized bbox dictionary import',
                        'bbox' => [0.12, 0.205, 0.32, 0.225],
                        'font' => ['name' => 'Helvetica', 'flags' => null, 'weight' => 400, 'size' => 11.0],
                        'chars' => [['char' => 'N', 'bbox' => [0.12, 0.205, 0.14, 0.225]]],
                    ]],
                ]],
            ]],
        ];

        $result = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
        $span = $result['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
        $charBlock = $result['pages'][0]['char_blocks'][0];
        $charSpan = $charBlock['lines'][0]['spans'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));

        $t->same([61.2, 158.4, 428.4, 190.1], $charBlock['bbox']);
        $t->same([61.2, 158.4, 428.4, 190.1], $charBlock['lines'][0]['bbox']);
        $t->same([73.4, 162.4, 195.8, 178.2], $span['bbox']);
        $t->same([73.4, 162.4, 195.8, 178.2], $charSpan['bbox']);
        $t->true(!array_key_exists('chars', $charSpan), 'keep_chars=false still removes raw normalized char payloads.');
        $t->same('Normalized bbox dictionary import', $blocks[0]['text']);
        $t->same([0.0, 0.0, 612.0, 792.0], $result['pages'][0]['bbox']);
        $t->same([
            'page' => 5,
            'bbox' => [0.0, 0.0, 612.0, 792.0],
            'rotation' => 0,
            'width' => 612.0,
            'height' => 792.0,
        ], $result['pages'][0]['pdftext_source']);
    },
    'scales off page normalized pdftext dictionary bboxes before WordPress import' => static function (TestRunner $t): void {
        $font = ['name' => 'Helvetica', 'flags' => null, 'weight' => 400, 'size' => 11.0];
        $page = [
            'page' => 6,
            'bbox' => [0.0, 0.0, 612.0, 792.0],
            'width' => 612.0,
            'height' => 792.0,
            'rotation' => 0,
            'blocks' => [[
                'bbox' => [-0.06, 0.10, 1.32, 0.14],
                'lines' => [[
                    'bbox' => [-0.06, 0.10, 1.32, 0.14],
                    'spans' => [[
                        'text' => 'Off-page glyph boxes remain reviewable',
                        'bbox' => [-0.04, 0.105, 1.30, 0.135],
                        'font' => $font,
                        'chars' => [[
                            'char' => 'x',
                            'bbox' => [1.28, 0.106, 1.30, 0.135],
                            'rotation' => 0,
                            'font' => $font,
                            'char_idx' => 37,
                        ]],
                    ]],
                ]],
            ]],
        ];

        $result = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1, keepChars: true);
        $span = $result['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
        $charBlock = $result['pages'][0]['char_blocks'][0];
        $charSpan = $charBlock['lines'][0]['spans'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));

        $t->same([-36.7, 79.2, 807.8, 110.9], $charBlock['bbox']);
        $t->same([-36.7, 79.2, 807.8, 110.9], $charBlock['lines'][0]['bbox']);
        $t->same([-24.5, 83.2, 795.6, 106.9], $span['bbox']);
        $t->same([-24.5, 83.2, 795.6, 106.9], $charSpan['bbox']);
        $t->same([783.4, 84.0, 795.6, 106.9], $span['chars'][0]['bbox']);
        $t->same('Off-page glyph boxes remain reviewable', $blocks[0]['text']);
        $t->same(true, $result['metadata']['pdftext_options']['keep_chars']);
    },
    'optionally sorts supplied pdftext dictionary blocks like dictionary_output sort' => static function (TestRunner $t): void {
        $block = static function (string $text, array $bbox): array {
            return [
                'bbox' => $bbox,
                'lines' => [[
                    'bbox' => $bbox,
                    'spans' => [[
                        'text' => $text,
                        'bbox' => $bbox,
                        'font' => ['name' => 'Times-Roman', 'flags' => null, 'weight' => 400, 'size' => 11.0],
                    ]],
                ]],
            ];
        };
        $page = [
            'page' => 14,
            'bbox' => [0.0, 0.0, 612.0, 792.0],
            'rotation' => 0,
            'blocks' => [
                $block('Bottom dictionary paragraph', [72.0, 144.0, 320.0, 158.0]),
                $block('Right top column', [330.0, 100.3, 540.0, 114.0]),
                $block('Left top column', [72.0, 100.0, 270.0, 114.0]),
            ],
        ];

        $extractor = new PdfTextDocumentExtractor();
        $unsorted = $extractor->getTextBlocks([$page], maxPages: 1);
        $sorted = $extractor->getTextBlocks([$page], maxPages: 1, sort: true);
        $processor = new MarkdownPostProcessor();

        $textFor = static fn (array $document): array => array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $document['pages'][0]['blocks']
        );
        $mergedSorted = $processor->mergeBlocks($processor->mergeSpans($sorted['pages']));

        $t->same(['Bottom dictionary paragraph', 'Right top column', 'Left top column'], $textFor($unsorted));
        $t->same(['Left top column', 'Right top column', 'Bottom dictionary paragraph'], $textFor($sorted));
        $t->same(['Left top column', 'Right top column', 'Bottom dictionary paragraph'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $sorted['pages'][0]['char_blocks']
        ));
        $t->same('Left top column Right top column Bottom dictionary paragraph', $mergedSorted[0]['text']);
        $t->same(true, $sorted['metadata']['pdftext_options']['sort']);
        $t->true(!array_key_exists('sort', $unsorted['metadata']['pdftext_options']), 'Default markerPDF get_text_blocks path should keep pdftext sort=false omitted.');
    },
    'keeps selected blank pdftext dictionary pages without WordPress paragraph leakage' => static function (TestRunner $t) use ($pdftextPage): void {
        $blankPage = [
            'page' => 41,
            'bbox' => [0.0, 0.0, 612.0, 792.0],
            'rotation' => 0,
            'width' => 612,
            'height' => 792,
            'total_chars' => 0,
            'blocks' => [],
        ];

        $result = (new PdfTextDocumentExtractor())->getTextBlocks(
            [
                $pdftextPage(40, 'Skipped front page'),
                $blankPage,
                $pdftextPage(42, 'Skipped appendix'),
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $mergedPages = $processor->mergeSpans($result['pages']);
        $blocks = $processor->mergeBlocks($mergedPages);
        $paginated = $processor->mergeBlocks($mergedPages, paginateOutput: true);

        $t->same([1], $result['page_range']);
        $t->same(1, $result['metadata']['pages']);
        $t->same(41, $result['pages'][0]['pnum']);
        $t->same([0.0, 0.0, 612.0, 792.0], $result['pages'][0]['bbox']);
        $t->same([], $result['pages'][0]['blocks']);
        $t->same([], $result['pages'][0]['char_blocks']);
        $t->same([], $blocks);
        $t->same(1, count($paginated));
        $t->same(true, $paginated[0]['page_start']);
        $t->same(41, $paginated[0]['pnum']);
        $t->true(!str_contains(json_encode($result, JSON_UNESCAPED_SLASHES) ?: '', 'Skipped front page'));
        $t->true(!str_contains(json_encode($result, JSON_UNESCAPED_SLASHES) ?: '', 'Skipped appendix'));
    },
    'rejects out of range page slices before WordPress import' => static function (TestRunner $t) use ($pdftextPage): void {
        $extractor = new PdfTextDocumentExtractor();

        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$pdftextPage(0, 'Only page')], startPage: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$pdftextPage(0, 'Only page')], maxPages: -1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$pdftextPage(0, 'Only page')], workers: 0));
    },
    'feeds selected pdftext pages into Gutenberg-ready paragraph text' => static function (TestRunner $t) use ($pdftextPage): void {
        $result = (new PdfTextDocumentExtractor())->getTextBlocks(
            [
                $pdftextPage(0, 'Editorial cover page'),
                $pdftextPage(1, "Shared-hosting docu-\nment conversion"),
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same(1, count($blocks));
        $t->same('Shared-hosting document conversion', $blocks[0]['text']);
        $t->same(1, $blocks[0]['pnum']);
    },
    'applies supplied layout order to selected pdftext dictionary pages before WordPress merge' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(3, [
                    ['text' => 'Skipped cover page', 'bbox' => [72.0, 80.0, 260.0, 94.0]],
                ]),
                $pdftextLinesPage(4, [
                    ['text' => 'Second selected column', 'bbox' => [330.0, 112.0, 540.0, 126.0]],
                    ['text' => 'First selected column', 'bbox' => [72.0, 112.0, 270.0, 126.0]],
                ]),
            ],
            [
                [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                    ],
                ],
            ],
            orderImages: ['selected-rendered-page'],
            maxPages: 1,
            startPage: 1,
            toc: [['title' => 'Selected import', 'level' => 1, 'page_index' => 4]]
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(4, $result['pages'][0]['pnum']);
        $t->same('0_0', $result['pages'][0]['blocks'][1]['lines'][0]['spans'][0]['span_id']);
        $t->same(['First selected column', 'Second selected column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First selected column Second selected column', $blocks[0]['text']);
        $t->same(['pdftext-dictionary', 'layout-order'], $result['metadata']['supplied_boundaries']);
        $t->same([
            'image_count' => 1,
            'page_count' => 1,
            'layout_bbox_counts' => [0],
            'requested_bboxes' => [[]],
            'order_result_count' => 1,
            'assigned_pages' => 1,
            'batch_size' => 6,
            'order_max_bboxes' => 255,
        ], $result['metadata']['order_plan']);
    },
    'trims whole document order artifacts to the selected pdftext page range' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(10, [
                    ['text' => 'Skipped cover page', 'bbox' => [72.0, 80.0, 260.0, 94.0]],
                ]),
                $pdftextLinesPage(11, [
                    ['text' => 'Second selected dictionary column', 'bbox' => [330.0, 112.0, 540.0, 126.0]],
                    ['text' => 'First selected dictionary column', 'bbox' => [72.0, 112.0, 270.0, 126.0]],
                ]),
                $pdftextLinesPage(12, [
                    ['text' => 'Skipped appendix page', 'bbox' => [72.0, 80.0, 260.0, 94.0]],
                ]),
            ],
            [
                [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                    ],
                ],
                [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                    ],
                ],
                [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                    ],
                ],
            ],
            orderImages: ['cover-render', 'selected-render', 'appendix-render'],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(11, $result['pages'][0]['pnum']);
        $t->same(['First selected dictionary column', 'Second selected dictionary column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First selected dictionary column Second selected dictionary column', $blocks[0]['text']);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'matches sparse keyed order artifacts to selected pdftext page numbers before WordPress merge' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(20, [
                    ['text' => 'Keyed cover page skipped', 'bbox' => [72.0, 80.0, 260.0, 94.0]],
                ]),
                $pdftextLinesPage(21, [
                    ['text' => 'Second keyed selected column', 'bbox' => [330.0, 112.0, 540.0, 126.0]],
                    ['text' => 'First keyed selected column', 'bbox' => [72.0, 112.0, 270.0, 126.0]],
                ]),
                $pdftextLinesPage(22, [
                    ['text' => 'Keyed appendix page skipped', 'bbox' => [72.0, 80.0, 260.0, 94.0]],
                ]),
            ],
            [
                [
                    'page' => 20,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                    ],
                ],
                [
                    'page' => 21,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 20, 'image' => 'keyed-cover-render'],
                ['page' => 21, 'image' => 'keyed-selected-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(21, $result['pages'][0]['pnum']);
        $t->same(['First keyed selected column', 'Second keyed selected column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First keyed selected column Second keyed selected column', $blocks[0]['text']);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'matches shallow wrapped keyed order artifacts to selected pdftext page numbers' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(110, [
                    ['text' => 'Wrapped keyed cover page skipped', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
                ]),
                $pdftextLinesPage(111, [
                    ['text' => 'Second wrapped selected column', 'bbox' => [330.0, 112.0, 540.0, 126.0]],
                    ['text' => 'First wrapped selected column', 'bbox' => [72.0, 112.0, 270.0, 126.0]],
                ]),
                $pdftextLinesPage(112, [
                    ['text' => 'Wrapped keyed appendix page skipped', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['page' => 110],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                    ],
                ],
                [
                    'page_metadata' => ['page' => 111],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['metadata' => ['page' => 110], 'image' => 'wrapped-cover-order-render'],
                ['page_metadata' => ['page' => 111], 'image' => 'wrapped-selected-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(111, $result['pages'][0]['pnum']);
        $t->same(['First wrapped selected column', 'Second wrapped selected column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First wrapped selected column Second wrapped selected column', $blocks[0]['text']);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'matches nested adapter page markers to selected pdftext page numbers' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(150, [
                    ['text' => 'Nested adapter cover page skipped', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
                ]),
                $pdftextLinesPage(151, [
                    ['text' => 'Second nested selected column', 'bbox' => [330.0, 112.0, 540.0, 126.0]],
                    ['text' => 'First nested selected column', 'bbox' => [72.0, 112.0, 270.0, 126.0]],
                ]),
                $pdftextLinesPage(152, [
                    ['text' => 'Nested adapter appendix page skipped', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
                ]),
            ],
            [
                [
                    'page_data' => ['metadata' => ['page' => 150]],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                    ],
                ],
                [
                    'page_result' => ['page_info' => ['page' => 151]],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['page_data' => ['metadata' => ['page' => 150]], 'image' => 'nested-cover-order-render'],
                ['page_result' => ['page_info' => ['page' => 151]], 'image' => 'nested-selected-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(151, $result['pages'][0]['pnum']);
        $t->same(['First nested selected column', 'Second nested selected column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First nested selected column Second nested selected column', $blocks[0]['text']);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'normalizes whitespace numeric page markers before selected pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(200, [
                    ['text' => 'Whitespace keyed cover page skipped', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
                ]),
                $pdftextLinesPage(201, [
                    ['text' => 'Second whitespace selected column', 'bbox' => [330.0, 112.0, 540.0, 126.0]],
                    ['text' => 'First whitespace selected column', 'bbox' => [72.0, 112.0, 270.0, 126.0]],
                ]),
                $pdftextLinesPage(202, [
                    ['text' => 'Whitespace keyed appendix page skipped', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
                ]),
            ],
            [
                [
                    'page' => " 200\t",
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                    ],
                ],
                [
                    'page' => "\n201 ",
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['page' => " 200\t", 'image' => 'whitespace-cover-order-render'],
                ['page' => "\n201 ", 'image' => 'whitespace-selected-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(201, $result['pages'][0]['pnum']);
        $t->same(['First whitespace selected column', 'Second whitespace selected column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First whitespace selected column Second whitespace selected column', $blocks[0]['text']);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'normalizes decimal string page markers before selected pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(230, [
                    ['text' => 'Decimal marker cover page skipped', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
                ]),
                $pdftextLinesPage(231, [
                    ['text' => 'Second decimal-marker selected column', 'bbox' => [330.0, 112.0, 540.0, 126.0]],
                    ['text' => 'First decimal-marker selected column', 'bbox' => [72.0, 112.0, 270.0, 126.0]],
                ]),
                $pdftextLinesPage(232, [
                    ['text' => 'Decimal marker appendix page skipped', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
                ]),
            ],
            [
                [
                    'page' => '230.0',
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                    ],
                ],
                [
                    'page' => '231.000',
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['page' => '230.0', 'image' => 'decimal-cover-order-render'],
                ['page' => '231.000', 'image' => 'decimal-selected-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(231, $result['pages'][0]['pnum']);
        $t->same(['First decimal-marker selected column', 'Second decimal-marker selected column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First decimal-marker selected column Second decimal-marker selected column', $blocks[0]['text']);
        $t->same(231, $result['pages'][0]['order']['page']);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'matches singleton array page markers before selected pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(700, [
                    ['text' => 'Array marker cover page skipped', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
                ]),
                $pdftextLinesPage(701, [
                    ['text' => 'Second array-marker selected column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First array-marker selected column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
                $pdftextLinesPage(702, [
                    ['text' => 'Array marker appendix page skipped', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
                ]),
            ],
            [
                [
                    'page' => [700],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                ],
                [
                    'page' => [701],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['page' => [700], 'image' => 'array-cover-order-render'],
                ['page' => [701], 'image' => 'array-selected-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(701, $result['pages'][0]['pnum']);
        $t->same(['First array-marker selected column', 'Second array-marker selected column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First array-marker selected column Second array-marker selected column', $blocks[0]['text']);
        $t->same(701, $result['pages'][0]['order']['page']);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'rejects ambiguous array page markers before selected pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(720, [
                    ['text' => 'Ambiguous array cover page skipped', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
                ]),
                $pdftextLinesPage(721, [
                    ['text' => 'Second ambiguous array column remains source ordered', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First ambiguous array column has no trusted order', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => [720, 721],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['page' => [720, 721], 'image' => 'ambiguous-array-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(721, $result['pages'][0]['pnum']);
        $t->same(['Second ambiguous array column remains source ordered', 'First ambiguous array column has no trusted order'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second ambiguous array column remains source ordered First ambiguous array column has no trusted order', $blocks[0]['text']);
        $t->same(null, $result['pages'][0]['order'] ?? null);
        $t->same(0, $result['metadata']['order_plan']['image_count']);
        $t->same(0, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
    },
    'matches singleton wrapper-list page markers before selected pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(800, [
                    ['text' => 'Wrapper-list cover page skipped', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
                ]),
                $pdftextLinesPage(801, [
                    ['text' => 'Second wrapper-list selected column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First wrapper-list selected column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
                $pdftextLinesPage(802, [
                    ['text' => 'Wrapper-list appendix page skipped', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
                ]),
            ],
            [
                [
                    'metadata' => [['page' => 800]],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                ],
                [
                    'page_metadata' => [['page' => 801]],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['metadata' => [['page' => 800]], 'image' => 'wrapper-list-cover-order-render'],
                ['page_metadata' => [['page' => 801]], 'image' => 'wrapper-list-selected-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(801, $result['pages'][0]['pnum']);
        $t->same(['First wrapper-list selected column', 'Second wrapper-list selected column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First wrapper-list selected column Second wrapper-list selected column', $blocks[0]['text']);
        $t->same(801, $result['pages'][0]['order']['page']);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'rejects ambiguous wrapper-list page markers before selected pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(820, [
                    ['text' => 'Ambiguous wrapper-list cover page skipped', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
                ]),
                $pdftextLinesPage(821, [
                    ['text' => 'Second ambiguous wrapper-list column remains source ordered', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First ambiguous wrapper-list column has no trusted order', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'metadata' => [['page' => 820], ['page' => 821]],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['metadata' => [['page' => 820], ['page' => 821]], 'image' => 'ambiguous-wrapper-list-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(821, $result['pages'][0]['pnum']);
        $t->same(['Second ambiguous wrapper-list column remains source ordered', 'First ambiguous wrapper-list column has no trusted order'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second ambiguous wrapper-list column remains source ordered First ambiguous wrapper-list column has no trusted order', $blocks[0]['text']);
        $t->same(null, $result['pages'][0]['order'] ?? null);
        $t->same(0, $result['metadata']['order_plan']['image_count']);
        $t->same(0, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
    },
    'prefers exact page markers over weaker page_number collisions before layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(300, [
                    ['text' => 'Marker-precedence cover page skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(301, [
                    ['text' => 'Second exact-marker column should move after', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First exact-marker column should move before', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page_number' => 302,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                ],
                [
                    'page' => 301,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['page_number' => 302, 'image' => 'one-based-collision-order-render'],
                ['page' => 301, 'image' => 'exact-page-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(301, $result['pages'][0]['pnum']);
        $t->same(['First exact-marker column should move before', 'Second exact-marker column should move after'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First exact-marker column should move before Second exact-marker column should move after', $blocks[0]['text']);
        $t->same(301, $result['pages'][0]['order']['page']);
        $t->true(!array_key_exists('page_number', $result['pages'][0]['order']));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'does not positionally assign selected-count keyed order artifacts for skipped pdftext pages' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(40, [
                    ['text' => 'Single keyed cover artifact skipped', 'bbox' => [72.0, 80.0, 260.0, 94.0]],
                ]),
                $pdftextLinesPage(41, [
                    ['text' => 'Second mismatch selected column', 'bbox' => [330.0, 112.0, 540.0, 126.0]],
                    ['text' => 'First mismatch selected column', 'bbox' => [72.0, 112.0, 270.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 40,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 40, 'image' => 'only-cover-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(41, $result['pages'][0]['pnum']);
        $t->same(['Second mismatch selected column', 'First mismatch selected column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second mismatch selected column First mismatch selected column', $blocks[0]['text']);
        $t->same(0, $result['metadata']['order_plan']['image_count']);
        $t->same(0, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
    },
    'does not confuse page identity markers with selected source indexes' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(70, [
                    ['text' => 'One-based cover artifact should not attach', 'bbox' => [72.0, 80.0, 300.0, 94.0]],
                ]),
                $pdftextLinesPage(71, [
                    ['text' => 'Second collision column remains source ordered', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First collision column has no selected order artifact', 'bbox' => [72.0, 112.0, 270.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 1,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 1, 'image' => 'one-based-cover-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(71, $result['pages'][0]['pnum']);
        $t->same(['Second collision column remains source ordered', 'First collision column has no selected order artifact'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second collision column remains source ordered First collision column has no selected order artifact', $blocks[0]['text']);
        $t->same(0, $result['metadata']['order_plan']['image_count']);
        $t->same(0, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
    },
    'rejects conflicting source and page identity markers before layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(90, [
                    ['text' => 'Conflicting identity cover artifact should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(91, [
                    ['text' => 'Second conflicting column remains source ordered', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First conflicting column has no trustworthy order artifact', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'source_page_index' => 1,
                    'page' => 90,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 286.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 560.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['source_page_index' => 1, 'page' => 90, 'image' => 'conflicting-cover-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(91, $result['pages'][0]['pnum']);
        $t->same(['Second conflicting column remains source ordered', 'First conflicting column has no trustworthy order artifact'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second conflicting column remains source ordered First conflicting column has no trustworthy order artifact', $blocks[0]['text']);
        $t->same(0, $result['metadata']['order_plan']['image_count']);
        $t->same(0, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
    },
    'preserves partial sparse keyed order alignment without counting missing selected pages' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(100, [
                    ['text' => 'Partial keyed cover page should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(101, [
                    ['text' => 'Second partial page keeps source order', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First partial page has no supplied order', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
                $pdftextLinesPage(102, [
                    ['text' => 'Second matched sparse page column', 'bbox' => [330.0, 140.0, 560.0, 154.0]],
                    ['text' => 'First matched sparse page column', 'bbox' => [72.0, 140.0, 280.0, 154.0]],
                ]),
                $pdftextLinesPage(103, [
                    ['text' => 'Partial keyed appendix should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
            ],
            [
                [
                    'page' => 102,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 132.0, 290.0, 168.0]],
                        ['position' => 2, 'bbox' => [318.0, 132.0, 570.0, 168.0]],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 102, 'image' => 'matched-second-selected-order-render'],
            ],
            maxPages: 2,
            startPage: 1
        );

        $pageOneBlocks = array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        );
        $pageTwoBlocks = array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][1]['blocks']
        );

        $t->same([1, 2], $result['page_range']);
        $t->same(101, $result['pages'][0]['pnum']);
        $t->same(102, $result['pages'][1]['pnum']);
        $t->same(['Second partial page keeps source order', 'First partial page has no supplied order'], $pageOneBlocks);
        $t->same(['First matched sparse page column', 'Second matched sparse page column'], $pageTwoBlocks);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'matches sparse post-trim order indexes across selected pdftext pages' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(210, [
                    ['text' => 'Selected-index cover page skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(211, [
                    ['text' => 'Second selected-index page keeps source order', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First selected-index page has no supplied order', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
                $pdftextLinesPage(212, [
                    ['text' => 'Second relative-index matched page column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First relative-index matched page column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
                $pdftextLinesPage(213, [
                    ['text' => 'Selected-index appendix page skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
            ],
            [
                [
                    'selected_page_index' => 1,
                    'selected_page_number' => 2,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['selected_page_index' => 1, 'selected_page_number' => 2, 'image' => 'matched-second-selected-relative-order-render'],
            ],
            maxPages: 2,
            startPage: 1
        );

        $pageOneBlocks = array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        );
        $pageTwoBlocks = array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][1]['blocks']
        );

        $t->same([1, 2], $result['page_range']);
        $t->same(211, $result['pages'][0]['pnum']);
        $t->same(212, $result['pages'][1]['pnum']);
        $t->same(['Second selected-index page keeps source order', 'First selected-index page has no supplied order'], $pageOneBlocks);
        $t->same(['First relative-index matched page column', 'Second relative-index matched page column'], $pageTwoBlocks);
        $t->same(null, $result['pages'][0]['order'] ?? null);
        $t->same(1, $result['pages'][1]['order']['selected_page_index']);
        $t->same(2, $result['pages'][1]['order']['selected_page_number']);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'does not replay one keyed order artifact across duplicate selected pdftext page markers' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(500, [
                    ['text' => 'Duplicate-marker cover page skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(501, [
                    ['text' => 'Second duplicate-marker first page column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First duplicate-marker first page column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
                $pdftextLinesPage(501, [
                    ['text' => 'Second duplicate-marker second page keeps source order', 'bbox' => [330.0, 140.0, 560.0, 154.0]],
                    ['text' => 'First duplicate-marker second page must not reuse order', 'bbox' => [72.0, 140.0, 280.0, 154.0]],
                ]),
            ],
            [
                [
                    'page' => 501,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 160.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 160.0]],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 501, 'image' => 'single-duplicate-marker-order-render'],
            ],
            maxPages: 2,
            startPage: 1
        );

        $pageOneBlocks = array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        );
        $pageTwoBlocks = array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][1]['blocks']
        );

        $t->same([1, 2], $result['page_range']);
        $t->same(501, $result['pages'][0]['pnum']);
        $t->same(501, $result['pages'][1]['pnum']);
        $t->same(['First duplicate-marker first page column', 'Second duplicate-marker first page column'], $pageOneBlocks);
        $t->same(['Second duplicate-marker second page keeps source order', 'First duplicate-marker second page must not reuse order'], $pageTwoBlocks);
        $t->same(501, $result['pages'][0]['order']['page']);
        $t->same(null, $result['pages'][1]['order'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'prefers adapter metadata over stale nested pdftext payload markers before layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(610, [
                    ['text' => 'Payload-marker cover page skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(611, [
                    ['text' => 'Second metadata-trusted column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First metadata-trusted column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['page' => 611],
                    'pdftext' => $pdftextLinesPage(610, [
                        ['text' => 'Stale nested pdftext payload must not block selected order', 'bbox' => [72.0, 160.0, 520.0, 174.0]],
                    ]),
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                [
                    'metadata' => ['page' => 611],
                    'pdftext' => $pdftextLinesPage(610, [
                        ['text' => 'Stale nested render payload must not block selected image', 'bbox' => [72.0, 180.0, 520.0, 194.0]],
                    ]),
                    'image' => 'metadata-trusted-selected-order-render',
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(611, $result['pages'][0]['pnum']);
        $t->same(['First metadata-trusted column', 'Second metadata-trusted column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First metadata-trusted column Second metadata-trusted column', $blocks[0]['text']);
        $t->same(611, $result['pages'][0]['order']['page']);
        $t->true(!array_key_exists('pdftext', $result['pages'][0]['order']));
        $t->true(!str_contains($encoded, 'Stale nested pdftext payload must not block selected order'));
        $t->true(!str_contains($encoded, 'Stale nested render payload must not block selected image'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'keeps matched layout order artifacts from leaking nested pdftext dictionary payloads' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(400, [
                    ['text' => 'Adapter payload cover page skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(401, [
                    ['text' => 'Second sanitized order column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First sanitized order column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'metadata' => [
                        'page' => 401,
                        'raw_private_payload' => 'hidden order adapter payload should not cross page order metadata',
                    ],
                    'pdftext' => $pdftextLinesPage(401, [
                        ['text' => 'Nested pdftext page copy must stay out of order metadata', 'bbox' => [72.0, 160.0, 500.0, 174.0]],
                    ]),
                    'blocks' => [[
                        'lines' => [[
                            'spans' => [[
                                'text' => 'Raw order-result text block should not be copied',
                                'bbox' => [72.0, 180.0, 500.0, 194.0],
                            ]],
                        ]],
                    ]],
                    'raw_pdf_bytes' => 'hidden order-result raw PDF bytes',
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                [
                    'metadata' => ['page' => 401],
                    'raw_render_payload' => 'hidden rendered page payload should not affect selected order',
                    'image' => 'matched-order-render',
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(401, $result['pages'][0]['pnum']);
        $t->same(['First sanitized order column', 'Second sanitized order column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First sanitized order column Second sanitized order column', $blocks[0]['text']);
        $t->same(401, $result['pages'][0]['order']['page']);
        $t->same([0.0, 0.0, 612.0, 792.0], $result['pages'][0]['order']['image_bbox']);
        $t->same(2, count($result['pages'][0]['order']['bboxes']));
        $t->true(!array_key_exists('metadata', $result['pages'][0]['order']));
        $t->true(!array_key_exists('pdftext', $result['pages'][0]['order']));
        $t->true(!array_key_exists('blocks', $result['pages'][0]['order']));
        $t->true(!str_contains($encoded, 'hidden order adapter payload'));
        $t->true(!str_contains($encoded, 'Nested pdftext page copy must stay out'));
        $t->true(!str_contains($encoded, 'Raw order-result text block should not be copied'));
        $t->true(!str_contains($encoded, 'hidden order-result raw PDF bytes'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
];
