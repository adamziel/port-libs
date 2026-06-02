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
];
