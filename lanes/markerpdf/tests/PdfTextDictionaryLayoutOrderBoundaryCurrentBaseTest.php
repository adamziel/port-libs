<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\LayoutAnnotator;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$pdftextLinesPage = static function (int $page, array $lines): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
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
    'rescales normalized supplied order boxes before pdftext dictionary layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(1200, [
                    ['text' => 'Normalized order cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(1201, [
                    ['text' => 'Second normalized order column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First normalized order column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 1201,
                    'image_bbox' => [0.0, 0.0, 1224.0, 1584.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [0.098, 0.121, 0.474, 0.182]],
                        ['position' => 2, 'bbox' => [0.519, 0.121, 0.932, 0.182]],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 1201, 'image' => 'normalized-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(1201, $result['pages'][0]['pnum']);
        $t->same(['First normalized order column', 'Second normalized order column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First normalized order column Second normalized order column', $blocks[0]['text']);
        $t->same([0.0, 0.0, 1224.0, 1584.0], $result['pages'][0]['order']['image_bbox']);
        $t->same([
            ['position' => 1, 'bbox' => [0.098, 0.121, 0.474, 0.182]],
            ['position' => 2, 'bbox' => [0.519, 0.121, 0.932, 0.182]],
        ], $result['pages'][0]['order']['bboxes']);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'rescales normalized supplied layout boxes before WordPress pdftext dictionary import' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-normalized-layout-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% normalized layout boundary current-base fixture\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(1210, [
                        ['text' => 'Normalized layout cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(1211, [
                        ['text' => 'Normalized layout title', 'bbox' => [72.0, 48.0, 360.0, 68.0]],
                        ['text' => 'Normalized layout body remains paragraph.', 'bbox' => [72.0, 112.0, 480.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['page' => 1211, 'image' => 'normalized-layout-render'],
                    ],
                    'layout_results' => [[
                        'page' => 1211,
                        'image_bbox' => [0.0, 0.0, 1224.0, 1584.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [0.098, 0.055, 0.588, 0.11]],
                            ['label' => 'Text', 'bbox' => [0.098, 0.125, 0.785, 0.19]],
                        ],
                        'raw_payload' => 'normalized layout payload must stay out of WordPress metadata',
                    ]],
                    'order_images' => [
                        ['page' => 1211, 'image' => 'normalized-layout-order-render'],
                    ],
                    'order_results' => [[
                        'page' => 1211,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 42.0, 370.0, 76.0]],
                            ['position' => 2, 'bbox' => [60.0, 100.0, 490.0, 140.0]],
                        ],
                    ]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([1], $result['metadata']['page_range'] ?? null);
        $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['layout_result_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('# Normalized Layout Title', $text);
        $t->contains('Normalized layout body remains paragraph.', $text);
        $t->true(strpos($text, '# Normalized Layout Title') < strpos($text, 'Normalized layout body remains paragraph.'));
        $t->true(!str_contains($text, 'Normalized layout cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'normalized layout payload must stay out'));
    },
    'keeps nested pdftext page payload markers out of trusted document-page order metadata' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(1300, [
                    ['text' => 'Payload source cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(1301, [
                    ['text' => 'Second document-page trusted column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First document-page trusted column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['document_page' => 1301],
                    'pdftext' => $pdftextLinesPage(1300, [
                        ['text' => 'Stale nested pdftext page marker must stay out of order metadata', 'bbox' => [72.0, 160.0, 520.0, 174.0]],
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
                    'metadata' => ['document_page' => 1301],
                    'pdftext' => $pdftextLinesPage(1300, [
                        ['text' => 'Stale nested pdftext render marker must stay out of order metadata', 'bbox' => [72.0, 180.0, 520.0, 194.0]],
                    ]),
                    'image' => 'document-page-trusted-order-render',
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(1301, $result['pages'][0]['pnum']);
        $t->same(['First document-page trusted column', 'Second document-page trusted column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First document-page trusted column Second document-page trusted column', $blocks[0]['text']);
        $t->same(1301, $order['document_page'] ?? null);
        $t->true(!array_key_exists('page', $order), 'Stale nested pdftext.page must not be preserved beside trusted document_page.');
        $t->true(!array_key_exists('pdftext', $order));
        $t->true(!str_contains($encoded, 'Stale nested pdftext page marker'));
        $t->true(!str_contains($encoded, 'Stale nested pdftext render marker'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses bbox-list order rows as ordered geometry before pdftext dictionary layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(1400, [
                    ['text' => 'Bbox-list cover page skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(1401, [
                    ['text' => 'Second bbox-list order column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First bbox-list order column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 1401,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        [318.0, 96.0, 570.0, 144.0],
                        [60.0, 96.0, 290.0, 144.0],
                    ],
                    'raw_payload' => 'bbox-list order payload must not cross page order metadata',
                ],
            ],
            orderImages: [
                ['page' => 1401, 'image' => 'bbox-list-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(1401, $result['pages'][0]['pnum']);
        $t->same(['Second bbox-list order column', 'First bbox-list order column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second bbox-list order column First bbox-list order column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
        ], $result['pages'][0]['order']['bboxes']);
        $t->true(!str_contains($encoded, 'bbox-list order payload must not cross'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'infers missing order positions from bbox dictionary row order before pdftext layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(1500, [
                    ['text' => 'Positionless dictionary row cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(1501, [
                    ['text' => 'Second positionless row supplied first', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First positionless row supplied second', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 1501,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'positionless right column payload must stay review-only'],
                        ['bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'positionless left column payload must stay review-only'],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 1501, 'image' => 'positionless-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(1501, $result['pages'][0]['pnum']);
        $t->same(['Second positionless row supplied first', 'First positionless row supplied second'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second positionless row supplied first First positionless row supplied second', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
        ], $result['pages'][0]['order']['bboxes']);
        $t->true(!str_contains($encoded, 'positionless right column payload'));
        $t->true(!str_contains($encoded, 'positionless left column payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses polygon-only order rows as bbox geometry before pdftext layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(2600, [
                    ['text' => 'Polygon order cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(2601, [
                    ['text' => 'Second polygon order column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First polygon order column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 2601,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        [
                            'position' => 1,
                            'polygon' => [[60.0, 96.0], [290.0, 96.0], [290.0, 144.0], [60.0, 144.0]],
                            'raw_payload' => 'polygon left order payload must stay review-only',
                        ],
                        [
                            'position' => 2,
                            'polygon' => [[318.0, 96.0], [570.0, 96.0], [570.0, 144.0], [318.0, 144.0]],
                            'raw_payload' => 'polygon right order payload must stay review-only',
                        ],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 2601, 'image' => 'polygon-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(2601, $result['pages'][0]['pnum']);
        $t->same(['First polygon order column', 'Second polygon order column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First polygon order column Second polygon order column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $result['pages'][0]['order']['bboxes']);
        $t->true(!str_contains($encoded, 'polygon left order payload'));
        $t->true(!str_contains($encoded, 'polygon right order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses object-point polygon order rows before pdftext layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(3600, [
                    ['text' => 'Object polygon cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(3601, [
                    ['text' => 'Second object polygon order column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First object polygon order column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 3601,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        [
                            'position' => 1,
                            'polygon' => [
                                ['x' => 60.0, 'y' => 96.0, 'confidence' => 0.99],
                                ['x' => 290.0, 'y' => 96.0, 'confidence' => 0.99],
                                ['x' => 290.0, 'y' => 144.0, 'confidence' => 0.98],
                                ['x' => 60.0, 'y' => 144.0, 'confidence' => 0.98],
                            ],
                            'raw_payload' => 'object polygon left order payload must stay review-only',
                        ],
                        [
                            'position' => 2,
                            'polygon' => [
                                ['x' => 318.0, 'y' => 96.0, 'score' => 0.97],
                                ['x' => 570.0, 'y' => 96.0, 'score' => 0.97],
                                ['x' => 570.0, 'y' => 144.0, 'score' => 0.96],
                                ['x' => 318.0, 'y' => 144.0, 'score' => 0.96],
                            ],
                            'raw_payload' => 'object polygon right order payload must stay review-only',
                        ],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 3601, 'image' => 'object-polygon-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(3601, $result['pages'][0]['pnum']);
        $t->same(['First object polygon order column', 'Second object polygon order column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First object polygon order column Second object polygon order column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $result['pages'][0]['order']['bboxes']);
        $t->true(!str_contains($encoded, 'object polygon left order payload'));
        $t->true(!str_contains($encoded, 'object polygon right order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses object-point polygon layout and order rows for WordPress supplied imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-object-point-polygon-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% object-point polygon pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(3700, [
                        ['text' => 'Object polygon converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(3701, [
                        ['text' => 'Object polygon converter title', 'bbox' => [72.0, 48.0, 360.0, 68.0]],
                        ['text' => 'Object polygon converter body.', 'bbox' => [72.0, 112.0, 480.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['page' => 3701, 'image' => 'object-polygon-layout-render'],
                    ],
                    'layout_results' => [[
                        'page' => 3701,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            [
                                'label' => 'Title',
                                'polygon' => [
                                    ['x' => 60.0, 'y' => 42.0, 'confidence' => 0.99],
                                    ['x' => 370.0, 'y' => 42.0, 'confidence' => 0.99],
                                    ['x' => 370.0, 'y' => 76.0, 'confidence' => 0.98],
                                    ['x' => 60.0, 'y' => 76.0, 'confidence' => 0.98],
                                ],
                                'raw_payload' => 'object polygon title layout payload must stay hidden',
                            ],
                            [
                                'label' => 'Text',
                                'polygon' => [
                                    ['x' => 60.0, 'y' => 100.0, 'score' => 0.97],
                                    ['x' => 490.0, 'y' => 100.0, 'score' => 0.97],
                                    ['x' => 490.0, 'y' => 140.0, 'score' => 0.96],
                                    ['x' => 60.0, 'y' => 140.0, 'score' => 0.96],
                                ],
                                'raw_payload' => 'object polygon body layout payload must stay hidden',
                            ],
                        ],
                    ]],
                    'order_images' => [
                        ['page' => 3701, 'image' => 'object-polygon-order-render'],
                    ],
                    'order_results' => [[
                        'page' => 3701,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            [
                                'position' => 1,
                                'polygon' => [
                                    ['x' => 60.0, 'y' => 42.0, 'confidence' => 0.99],
                                    ['x' => 370.0, 'y' => 42.0, 'confidence' => 0.99],
                                    ['x' => 370.0, 'y' => 76.0, 'confidence' => 0.98],
                                    ['x' => 60.0, 'y' => 76.0, 'confidence' => 0.98],
                                ],
                                'raw_payload' => 'object polygon title order payload must stay hidden',
                            ],
                            [
                                'position' => 2,
                                'polygon' => [
                                    ['x' => 60.0, 'y' => 100.0, 'score' => 0.97],
                                    ['x' => 490.0, 'y' => 100.0, 'score' => 0.97],
                                    ['x' => 490.0, 'y' => 140.0, 'score' => 0.96],
                                    ['x' => 60.0, 'y' => 140.0, 'score' => 0.96],
                                ],
                                'raw_payload' => 'object polygon body order payload must stay hidden',
                            ],
                        ],
                    ]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([1], $result['metadata']['page_range'] ?? null);
        $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['layout_result_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['order_result_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('# Object Polygon Converter Title', $text);
        $t->contains('Object polygon converter body.', $text);
        $t->true(strpos($text, '# Object Polygon Converter Title') < strpos($text, 'Object polygon converter body.'));
        $t->true(!str_contains($text, 'Object polygon converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'object polygon title layout payload'));
        $t->true(!str_contains($encoded, 'object polygon body layout payload'));
        $t->true(!str_contains($encoded, 'object polygon title order payload'));
        $t->true(!str_contains($encoded, 'object polygon body order payload'));
    },
    'uses named object order bboxes before selected pdftext layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(3200, [
                    ['text' => 'Named object bbox cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(3201, [
                    ['text' => 'Second named object bbox column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First named object bbox column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 3201,
                    'image_bbox' => ['left' => 0.0, 'top' => 0.0, 'right' => 612.0, 'bottom' => 792.0],
                    'bboxes' => [
                        [
                            'position' => 1,
                            'left' => 60.0,
                            'top' => 96.0,
                            'right' => 290.0,
                            'bottom' => 144.0,
                            'raw_payload' => 'named object left order payload must stay review-only',
                        ],
                        [
                            'position' => 2,
                            'bbox' => ['x' => 318.0, 'y' => 96.0, 'width' => 252.0, 'height' => 48.0],
                            'raw_payload' => 'named object right order payload must stay review-only',
                        ],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 3201, 'image' => 'named-object-bbox-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(3201, $result['pages'][0]['pnum']);
        $t->same(['First named object bbox column', 'Second named object bbox column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First named object bbox column Second named object bbox column', $blocks[0]['text']);
        $t->same([0.0, 0.0, 612.0, 792.0], $order['image_bbox'] ?? null);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!str_contains($encoded, 'named object left order payload'));
        $t->true(!str_contains($encoded, 'named object right order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses named object layout and order bboxes for WordPress supplied imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-named-object-bbox-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% named object bbox pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(3300, [
                        ['text' => 'Named object converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(3301, [
                        ['text' => 'Second converter named object column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter named object column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['page' => 3301, 'image' => 'named-object-layout-render'],
                    ],
                    'layout_results' => [[
                        'page' => 3301,
                        'image_bbox' => ['x0' => 0.0, 'y0' => 0.0, 'x1' => 612.0, 'y1' => 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'left' => 60.0, 'top' => 92.0, 'right' => 290.0, 'bottom' => 150.0],
                            ['label' => 'Text', 'bbox' => ['x' => 318.0, 'y' => 92.0, 'width' => 252.0, 'height' => 58.0]],
                        ],
                        'raw_payload' => 'named object layout payload must stay hidden',
                    ]],
                    'order_images' => [
                        ['page' => 3301, 'image' => 'named-object-order-render'],
                    ],
                    'order_results' => [[
                        'page' => 3301,
                        'image_bbox' => ['x0' => 0.0, 'y0' => 0.0, 'x1' => 612.0, 'y1' => 792.0],
                        'bboxes' => [
                            [
                                'position' => 1,
                                'left' => 60.0,
                                'top' => 96.0,
                                'right' => 290.0,
                                'bottom' => 144.0,
                                'raw_payload' => 'named object left converter order payload must stay hidden',
                            ],
                            [
                                'position' => 2,
                                'bbox' => ['xmin' => 318.0, 'ymin' => 96.0, 'xmax' => 570.0, 'ymax' => 144.0],
                                'raw_payload' => 'named object right converter order payload must stay hidden',
                            ],
                        ],
                    ]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([1], $result['metadata']['page_range'] ?? null);
        $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['layout_result_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['order_result_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->same([
            [60.0, 92.0, 290.0, 150.0],
            [318.0, 92.0, 570.0, 150.0],
        ], $result['metadata']['order_plan']['requested_bboxes'][0] ?? null);
        $t->contains('# First Converter Named Object Column.', $text);
        $t->contains('Second converter named object column.', $text);
        $t->true(strpos($text, '# First Converter Named Object Column.') < strpos($text, 'Second converter named object column.'));
        $t->true(!str_contains($text, 'Named object converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'named object layout payload'));
        $t->true(!str_contains($encoded, 'named object left converter order payload'));
        $t->true(!str_contains($encoded, 'named object right converter order payload'));
    },
    'prefers trusted metadata over copied source-page payload markers before pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(1600, [
                    ['text' => 'Source payload cover page should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(1601, [
                    ['text' => 'Second source payload column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First source payload column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['document_page' => 1601],
                    'source' => $pdftextLinesPage(1600, [
                        ['text' => 'Stale copied source payload must not block selected order', 'bbox' => [72.0, 160.0, 520.0, 174.0]],
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
                    'metadata' => ['document_page' => 1601],
                    'source' => $pdftextLinesPage(1600, [
                        ['text' => 'Stale copied render payload must not block selected image', 'bbox' => [72.0, 180.0, 520.0, 194.0]],
                    ]),
                    'image' => 'source-payload-selected-order-render',
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(1601, $result['pages'][0]['pnum']);
        $t->same(['First source payload column', 'Second source payload column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First source payload column Second source payload column', $blocks[0]['text']);
        $t->same(1601, $order['document_page'] ?? null);
        $t->true(!array_key_exists('page', $order), 'Stale copied source.page must not be preserved beside trusted document_page.');
        $t->true(!array_key_exists('source', $order));
        $t->true(!str_contains($encoded, 'Stale copied source payload'));
        $t->true(!str_contains($encoded, 'Stale copied render payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'keeps copied source pdftext payloads fallback-only for WordPress layout and order artifacts' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-source-payload-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% source-payload pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(1700, [
                        ['text' => 'Source payload converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(1701, [
                        ['text' => 'Second converter source payload column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter source payload column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        [
                            'metadata' => ['document_page' => 1701],
                            'source' => $pdftextLinesPage(1700, [
                                ['text' => 'Stale source image payload must not block layout image', 'bbox' => [72.0, 180.0, 520.0, 194.0]],
                            ]),
                            'image' => 'source-payload-layout-render',
                        ],
                    ],
                    'layout_results' => [[
                        'metadata' => ['document_page' => 1701],
                        'source' => $pdftextLinesPage(1700, [
                            ['text' => 'Stale source layout payload must not become layout metadata', 'bbox' => [72.0, 160.0, 520.0, 174.0]],
                        ]),
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                    ]],
                    'order_images' => [
                        [
                            'metadata' => ['document_page' => 1701],
                            'source' => $pdftextLinesPage(1700, [
                                ['text' => 'Stale source order image payload must not block order image', 'bbox' => [72.0, 200.0, 520.0, 214.0]],
                            ]),
                            'image' => 'source-payload-order-render',
                        ],
                    ],
                    'order_results' => [[
                        'metadata' => ['document_page' => 1701],
                        'source' => $pdftextLinesPage(1700, [
                            ['text' => 'Stale source order payload must not become order metadata', 'bbox' => [72.0, 220.0, 520.0, 234.0]],
                        ]),
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                    ]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([1], $result['metadata']['page_range'] ?? null);
        $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['layout_result_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['order_result_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('First converter source payload column.', $text);
        $t->contains('Second converter source payload column.', $text);
        $t->true(strpos($text, 'First converter source payload column.') < strpos($text, 'Second converter source payload column.'));
        $t->true(!str_contains($text, 'Source payload converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'Stale source image payload'));
        $t->true(!str_contains($encoded, 'Stale source layout payload'));
        $t->true(!str_contains($encoded, 'Stale source order image payload'));
        $t->true(!str_contains($encoded, 'Stale source order payload'));
    },
    'unwraps typed order-result payload wrappers before selected pdftext layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(1800, [
                    ['text' => 'Typed order-result cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(1801, [
                    ['text' => 'Second typed order wrapper column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First typed order wrapper column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
                $pdftextLinesPage(1802, [
                    ['text' => 'Typed order-result appendix should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
            ],
            [
                [
                    'order_result' => [
                        'document_page' => 1800,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ],
                    ],
                    'raw_payload' => 'typed cover order wrapper payload must stay hidden',
                ],
                [
                    'order_result' => [
                        'document_page' => 1801,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                    ],
                    'raw_payload' => 'typed selected order wrapper payload must stay hidden',
                ],
            ],
            orderImages: [
                ['order_result' => ['document_page' => 1800], 'image' => 'typed-cover-order-render'],
                ['order_result' => ['document_page' => 1801], 'image' => 'typed-selected-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(1801, $result['pages'][0]['pnum']);
        $t->same(['First typed order wrapper column', 'Second typed order wrapper column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First typed order wrapper column Second typed order wrapper column', $blocks[0]['text']);
        $t->same(1801, $order['document_page'] ?? null);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('order_result', $order));
        $t->true(!str_contains($encoded, 'typed cover order wrapper payload'));
        $t->true(!str_contains($encoded, 'typed selected order wrapper payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'unwraps typed layout and order result wrappers for WordPress supplied document imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-typed-wrapper-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% typed wrapper pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(1900, [
                        ['text' => 'Typed wrapper converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(1901, [
                        ['text' => 'Second converter typed wrapper column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter typed wrapper column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                    $pdftextLinesPage(1902, [
                        ['text' => 'Typed wrapper converter appendix should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['layout_result' => ['document_page' => 1900], 'image' => 'typed-cover-layout-render'],
                        ['layout_result' => ['document_page' => 1901], 'image' => 'typed-selected-layout-render'],
                    ],
                    'layout_results' => [
                        [
                            'layout_result' => [
                                'document_page' => 1900,
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                            ],
                            'raw_payload' => 'typed cover layout payload must stay hidden',
                        ],
                        [
                            'layout_result' => [
                                'document_page' => 1901,
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                            ],
                            'raw_payload' => 'typed selected layout payload must stay hidden',
                        ],
                    ],
                    'order_images' => [
                        ['order_result' => ['document_page' => 1900], 'image' => 'typed-cover-order-render'],
                        ['order_result' => ['document_page' => 1901], 'image' => 'typed-selected-order-render'],
                    ],
                    'order_results' => [
                        [
                            'order_result' => [
                                'document_page' => 1900,
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ],
                            ],
                            'raw_payload' => 'typed cover order payload must stay hidden',
                        ],
                        [
                            'order_result' => [
                                'document_page' => 1901,
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ],
                            ],
                            'raw_payload' => 'typed selected order payload must stay hidden',
                        ],
                    ],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([1], $result['metadata']['page_range'] ?? null);
        $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['layout_result_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['order_result_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('First converter typed wrapper column.', $text);
        $t->contains('Second converter typed wrapper column.', $text);
        $t->true(strpos($text, 'First converter typed wrapper column.') < strpos($text, 'Second converter typed wrapper column.'));
        $t->true(!str_contains($text, 'Typed wrapper converter cover should stay skipped.'));
        $t->true(!str_contains($text, 'Typed wrapper converter appendix should stay skipped.'));
        $t->true(!str_contains($encoded, 'typed cover layout payload'));
        $t->true(!str_contains($encoded, 'typed selected layout payload'));
        $t->true(!str_contains($encoded, 'typed cover order payload'));
        $t->true(!str_contains($encoded, 'typed selected order payload'));
    },
    'prefers trusted adapter metadata over stale typed order-result page markers before layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(2000, [
                    ['text' => 'Trusted metadata stale payload cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(2001, [
                    ['text' => 'Second stale typed payload column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First trusted metadata column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['document_page' => 2001],
                    'order_result' => [
                        'page' => 2000,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                    ],
                    'raw_payload' => 'stale typed order payload must stay hidden',
                ],
            ],
            orderImages: [
                [
                    'metadata' => ['document_page' => 2001],
                    'order_result' => ['page' => 2000],
                    'image' => 'trusted-metadata-stale-order-render',
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(2001, $result['pages'][0]['pnum']);
        $t->same(['First trusted metadata column', 'Second stale typed payload column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First trusted metadata column Second stale typed payload column', $blocks[0]['text']);
        $t->same(2001, $order['document_page'] ?? null);
        $t->true(!array_key_exists('page', $order), 'Stale order_result.page must not be preserved beside trusted metadata.document_page.');
        $t->true(!array_key_exists('order_result', $order));
        $t->true(!str_contains($encoded, 'stale typed order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'keeps stale typed layout and order payload page markers fallback-only for WordPress imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-trusted-metadata-stale-typed-payload-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% trusted metadata stale typed payload layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(2100, [
                        ['text' => 'Trusted metadata converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(2101, [
                        ['text' => 'Second converter stale typed payload column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter trusted metadata column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        [
                            'metadata' => ['document_page' => 2101],
                            'layout_result' => ['page' => 2100],
                            'image' => 'trusted-metadata-layout-render',
                        ],
                    ],
                    'layout_results' => [[
                        'metadata' => ['document_page' => 2101],
                        'layout_result' => [
                            'page' => 2100,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                        ],
                        'raw_payload' => 'stale typed layout payload must stay hidden',
                    ]],
                    'order_images' => [
                        [
                            'metadata' => ['document_page' => 2101],
                            'order_result' => ['page' => 2100],
                            'image' => 'trusted-metadata-order-render',
                        ],
                    ],
                    'order_results' => [[
                        'metadata' => ['document_page' => 2101],
                        'order_result' => [
                            'page' => 2100,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                        ],
                        'raw_payload' => 'stale typed order payload must stay hidden',
                    ]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([1], $result['metadata']['page_range'] ?? null);
        $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['layout_result_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['order_result_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('First converter trusted metadata column.', $text);
        $t->contains('Second converter stale typed payload column.', $text);
        $t->true(strpos($text, 'First converter trusted metadata column.') < strpos($text, 'Second converter stale typed payload column.'));
        $t->true(!str_contains($text, 'Trusted metadata converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'stale typed layout payload'));
        $t->true(!str_contains($encoded, 'stale typed order payload'));
    },
    'uses singleton page-range metadata before selected pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(2200, [
                    ['text' => 'Page-range metadata cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(2201, [
                    ['text' => 'Second page-range metadata column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First page-range metadata column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['page_range' => [0]],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                    'raw_payload' => 'cover page-range order payload must stay hidden',
                ],
                [
                    'metadata' => ['page_range' => [1]],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'selected page-range order payload must stay hidden',
                ],
                [
                    'metadata' => ['page_range' => [0, 1]],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                    'raw_payload' => 'ambiguous page-range order payload must stay hidden',
                ],
            ],
            orderImages: [
                ['metadata' => ['page_range' => [0]], 'image' => 'page-range-cover-order-render'],
                ['metadata' => ['page_range' => [1]], 'image' => 'page-range-selected-order-render'],
                ['metadata' => ['page_range' => [0, 1]], 'image' => 'page-range-ambiguous-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(2201, $result['pages'][0]['pnum']);
        $t->same(['First page-range metadata column', 'Second page-range metadata column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First page-range metadata column Second page-range metadata column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('metadata', $order));
        $t->true(!array_key_exists('page_range', $order));
        $t->true(!str_contains($encoded, 'cover page-range order payload'));
        $t->true(!str_contains($encoded, 'selected page-range order payload'));
        $t->true(!str_contains($encoded, 'ambiguous page-range order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'keeps page-range metadata trusted over stale typed payload markers for WordPress imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-page-range-typed-payload-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% page-range metadata typed payload layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(2300, [
                        ['text' => 'Page-range converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(2301, [
                        ['text' => 'Second converter page-range column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter page-range column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        [
                            'metadata' => ['page_range' => [1]],
                            'layout_result' => ['page' => 2300],
                            'image' => 'page-range-layout-render',
                        ],
                    ],
                    'layout_results' => [[
                        'metadata' => ['page_range' => [1]],
                        'layout_result' => [
                            'page' => 2300,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                        ],
                        'raw_payload' => 'stale typed page-range layout payload must stay hidden',
                    ]],
                    'order_images' => [
                        [
                            'metadata' => ['page_range' => [1]],
                            'order_result' => ['page' => 2300],
                            'image' => 'page-range-order-render',
                        ],
                    ],
                    'order_results' => [[
                        'metadata' => ['page_range' => [1]],
                        'order_result' => [
                            'page' => 2300,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                        ],
                        'raw_payload' => 'stale typed page-range order payload must stay hidden',
                    ]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];
        $page = $result['pages'][0] ?? [];
        $layout = is_array($page) && is_array($page['layout'] ?? null) ? $page['layout'] : [];
        $order = is_array($page) && is_array($page['order'] ?? null) ? $page['order'] : [];

        $t->same([1], $result['metadata']['page_range'] ?? null);
        $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['layout_result_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['order_result_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->true(!array_key_exists('page', $layout), 'Stale typed layout_result.page must not be preserved beside trusted metadata.page_range.');
        $t->true(!array_key_exists('page', $order), 'Stale typed order_result.page must not be preserved beside trusted metadata.page_range.');
        $t->true(!array_key_exists('page_range', $layout));
        $t->true(!array_key_exists('page_range', $order));
        $t->contains('First converter page-range column.', $text);
        $t->contains('Second converter page-range column.', $text);
        $t->true(strpos($text, 'First converter page-range column.') < strpos($text, 'Second converter page-range column.'));
        $t->true(!str_contains($text, 'Page-range converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'stale typed page-range layout payload'));
        $t->true(!str_contains($encoded, 'stale typed page-range order payload'));
    },
    'uses upstream page_num adapter metadata as one-based page identity before WordPress layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $document = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(2700, [
                    ['text' => 'Page num direct cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(2701, [
                    ['text' => 'Second direct page num column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First direct page num column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
                $pdftextLinesPage(2702, [
                    ['text' => 'Page num direct appendix skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
            ],
            [
                [
                    'page_num' => 2701,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                ],
                [
                    'metadata' => ['page_num' => 2702],
                    'order_result' => [
                        'page' => 2700,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                    ],
                ],
            ],
            orderImages: [
                ['page_num' => 2701, 'image' => 'page-num-direct-cover-order-render'],
                ['metadata' => ['page_num' => 2702], 'image' => 'page-num-direct-selected-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );
        $directBlocks = array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $document['pages'][0]['blocks']
        );
        $t->same(['First direct page num column', 'Second direct page num column'], $directBlocks);
        $t->same(2702, $document['pages'][0]['order']['page_num'] ?? null);
        $t->true(!array_key_exists('page', $document['pages'][0]['order'] ?? []), 'Stale typed order_result.page must not be preserved beside trusted metadata.page_num.');

        $layoutDocument = (new PdfTextDocumentExtractor())->getTextBlocks([
            $pdftextLinesPage(2701, [
                ['text' => 'Layout page num text.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ], maxPages: 1);
        $layoutPreview = (new LayoutAnnotator())->runWithSuppliedLayouts(
            [],
            $layoutDocument['pages'],
            [[
                'metadata' => ['page_num' => 2702],
                'layout_result' => [
                    'page' => 2700,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                    ],
                ],
            ]]
        );
        $t->same(2702, $layoutPreview['pages'][0]['layout']['page_num'] ?? null);
        $t->true(!array_key_exists('page', $layoutPreview['pages'][0]['layout'] ?? []), 'Stale typed layout_result.page must not be preserved beside trusted metadata.page_num.');

        $path = sys_get_temp_dir() . '/markerpdf-page-num-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% page_num pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(2700, [
                        ['text' => 'Page num converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(2701, [
                        ['text' => 'Second converter page num column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter page num column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                    $pdftextLinesPage(2702, [
                        ['text' => 'Page num converter appendix should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['page_num' => 2701, 'image' => 'page-num-cover-layout-render'],
                        ['metadata' => ['page_num' => 2702], 'image' => 'page-num-selected-layout-render'],
                    ],
                    'layout_results' => [
                        [
                            'page_num' => 2701,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'cover page_num layout payload must stay hidden',
                        ],
                        [
                            'metadata' => ['page_num' => 2702],
                            'layout_result' => [
                                'page' => 2700,
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                            ],
                            'raw_payload' => 'selected page_num layout payload must stay hidden',
                        ],
                    ],
                    'order_images' => [
                        ['page_num' => 2701, 'image' => 'page-num-cover-order-render'],
                        ['metadata' => ['page_num' => 2702], 'image' => 'page-num-selected-order-render'],
                    ],
                    'order_results' => [
                        [
                            'page_num' => 2701,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'cover page_num order payload must stay hidden',
                        ],
                        [
                            'metadata' => ['page_num' => 2702],
                            'order_result' => [
                                'page' => 2700,
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ],
                            ],
                            'raw_payload' => 'selected page_num order payload must stay hidden',
                        ],
                    ],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([1], $result['metadata']['page_range'] ?? null);
        $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['layout_result_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['order_result_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('First converter page num column.', $text);
        $t->contains('Second converter page num column.', $text);
        $t->true(strpos($text, 'First converter page num column.') < strpos($text, 'Second converter page num column.'));
        $t->true(!str_contains($text, 'Page num converter cover should stay skipped.'));
        $t->true(!str_contains($text, 'Page num converter appendix should stay skipped.'));
        $t->true(!str_contains($encoded, 'cover page_num layout payload'));
        $t->true(!str_contains($encoded, 'selected page_num layout payload'));
        $t->true(!str_contains($encoded, 'cover page_num order payload'));
        $t->true(!str_contains($encoded, 'selected page_num order payload'));
    },
    'uses pdftext source page metadata before selected pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(2800, [
                    ['text' => 'Pdftext source cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(2801, [
                    ['text' => 'Second pdftext source column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First pdftext source column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
                $pdftextLinesPage(2802, [
                    ['text' => 'Pdftext source appendix skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
            ],
            [
                [
                    'pdftext_source' => ['page' => 2800],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                    'raw_payload' => 'cover pdftext_source order payload must stay hidden',
                ],
                [
                    'pdftext_source' => ['page' => 2801],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'selected pdftext_source order payload must stay hidden',
                ],
            ],
            orderImages: [
                ['pdftext_source' => ['page' => 2800], 'image' => 'pdftext-source-cover-order-render'],
                ['pdftext_source' => ['page' => 2801], 'image' => 'pdftext-source-selected-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(2801, $result['pages'][0]['pnum']);
        $t->same(['First pdftext source column', 'Second pdftext source column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First pdftext source column Second pdftext source column', $blocks[0]['text']);
        $t->same(2801, $order['page'] ?? null);
        $t->true(!array_key_exists('pdftext_source', $order), 'pdftext_source wrapper payload must not be copied into order review metadata.');
        $t->true(!str_contains($encoded, 'cover pdftext_source order payload'));
        $t->true(!str_contains($encoded, 'selected pdftext_source order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses pdftext source metadata for WordPress supplied layout and order artifacts' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-pdftext-source-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% pdftext_source pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(2900, [
                        ['text' => 'Pdftext source converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(2901, [
                        ['text' => 'Second converter pdftext source column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter pdftext source column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                    $pdftextLinesPage(2902, [
                        ['text' => 'Pdftext source converter appendix should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['pdftext_source' => ['page' => 2900], 'image' => 'pdftext-source-cover-layout-render'],
                        ['pdftext_source' => ['page' => 2901], 'image' => 'pdftext-source-selected-layout-render'],
                    ],
                    'layout_results' => [
                        [
                            'pdftext_source' => ['page' => 2900],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'cover pdftext_source layout payload must stay hidden',
                        ],
                        [
                            'pdftext_source' => ['page' => 2901],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'selected pdftext_source layout payload must stay hidden',
                        ],
                    ],
                    'order_images' => [
                        ['pdftext_source' => ['page' => 2900], 'image' => 'pdftext-source-cover-order-render'],
                        ['pdftext_source' => ['page' => 2901], 'image' => 'pdftext-source-selected-order-render'],
                    ],
                    'order_results' => [
                        [
                            'pdftext_source' => ['page' => 2900],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'cover pdftext_source order payload must stay hidden',
                        ],
                        [
                            'pdftext_source' => ['page' => 2901],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'selected pdftext_source order payload must stay hidden',
                        ],
                    ],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([1], $result['metadata']['page_range'] ?? null);
        $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['layout_result_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['order_result_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('First converter pdftext source column.', $text);
        $t->contains('Second converter pdftext source column.', $text);
        $t->true(strpos($text, 'First converter pdftext source column.') < strpos($text, 'Second converter pdftext source column.'));
        $t->true(!str_contains($text, 'Pdftext source converter cover should stay skipped.'));
        $t->true(!str_contains($text, 'Pdftext source converter appendix should stay skipped.'));
        $t->true(!str_contains($encoded, 'cover pdftext_source layout payload'));
        $t->true(!str_contains($encoded, 'selected pdftext_source layout payload'));
        $t->true(!str_contains($encoded, 'cover pdftext_source order payload'));
        $t->true(!str_contains($encoded, 'selected pdftext_source order payload'));
    },
    'uses page_idx adapter aliases before selected pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(3000, [
                    ['text' => 'Page idx alias cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(3001, [
                    ['text' => 'Second page idx alias column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First page idx alias column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page_idx' => 0,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'stale page_idx order payload must stay hidden',
                ],
            ],
            orderImages: [
                ['page_idx' => 0, 'image' => 'page-idx-cover-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(3001, $result['pages'][0]['pnum']);
        $t->same(['Second page idx alias column', 'First page idx alias column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second page idx alias column First page idx alias column', $blocks[0]['text']);
        $t->same(null, $result['pages'][0]['order'] ?? null);
        $t->true(!str_contains($encoded, 'stale page_idx order payload'));
        $t->same(0, $result['metadata']['order_plan']['image_count']);
        $t->same(0, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);

        $selected = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(3010, [
                    ['text' => 'Selected page_idx cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(3011, [
                    ['text' => 'Second selected page_idx column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First selected page_idx column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page_idx' => 0,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                ],
                [
                    'page_idx' => 1,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['page_idx' => 0, 'image' => 'selected-page-idx-cover-order-render'],
                ['page_idx' => 1, 'image' => 'selected-page-idx-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $t->same(['First selected page_idx column', 'Second selected page_idx column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $selected['pages'][0]['blocks']
        ));
        $t->same(1, $selected['pages'][0]['order']['page_idx'] ?? null);
        $t->same(1, $selected['metadata']['order_plan']['image_count']);
        $t->same(1, $selected['metadata']['order_plan']['order_result_count']);
        $t->same(1, $selected['metadata']['order_plan']['assigned_pages']);
    },
    'uses selected page_idx aliases for WordPress supplied layout and order artifacts' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-page-idx-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% page_idx pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(3100, [
                        ['text' => 'Page idx converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(3101, [
                        ['text' => 'Second converter page idx column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter page idx column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                    $pdftextLinesPage(3102, [
                        ['text' => 'Page idx converter appendix should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['page_idx' => 0, 'image' => 'page-idx-cover-layout-render'],
                        ['page_idx' => 1, 'image' => 'page-idx-selected-layout-render'],
                    ],
                    'layout_results' => [
                        [
                            'page_idx' => 0,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'cover page_idx layout payload must stay hidden',
                        ],
                        [
                            'page_idx' => 1,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'selected page_idx layout payload must stay hidden',
                        ],
                    ],
                    'order_images' => [
                        ['page_idx' => 0, 'image' => 'page-idx-cover-order-render'],
                        ['page_idx' => 1, 'image' => 'page-idx-selected-order-render'],
                    ],
                    'order_results' => [
                        [
                            'page_idx' => 0,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'cover page_idx order payload must stay hidden',
                        ],
                        [
                            'page_idx' => 1,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'selected page_idx order payload must stay hidden',
                        ],
                    ],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([1], $result['metadata']['page_range'] ?? null);
        $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['layout_result_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['order_result_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('First converter page idx column.', $text);
        $t->contains('Second converter page idx column.', $text);
        $t->true(strpos($text, 'First converter page idx column.') < strpos($text, 'Second converter page idx column.'));
        $t->true(!str_contains($text, 'Page idx converter cover should stay skipped.'));
        $t->true(!str_contains($text, 'Page idx converter appendix should stay skipped.'));
        $t->true(!str_contains($encoded, 'cover page_idx layout payload'));
        $t->true(!str_contains($encoded, 'selected page_idx layout payload'));
        $t->true(!str_contains($encoded, 'cover page_idx order payload'));
        $t->true(!str_contains($encoded, 'selected page_idx order payload'));
    },
    'rejects non-finite supplied order bboxes before selected pdftext layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(3401, [
                    ['text' => 'Second finite order row remains first', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First nonfinite order row remains fallback', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 3401,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, INF, 144.0], 'raw_payload' => 'nonfinite order bbox payload must stay hidden'],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 3401, 'image' => 'nonfinite-bbox-order-render'],
            ],
            maxPages: 1,
            startPage: 0
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([0], $result['page_range']);
        $t->same(3401, $result['pages'][0]['pnum']);
        $t->same(['Second finite order row remains first', 'First nonfinite order row remains fallback'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second finite order row remains first First nonfinite order row remains fallback', $blocks[0]['text']);
        $t->same([
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!str_contains($encoded, 'INF'));
        $t->true(!str_contains($encoded, 'nonfinite order bbox payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'rejects non-finite supplied layout and order geometry before WordPress imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $nonfiniteLayoutResult = [
            'page' => 3501,
            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
            'bboxes' => [
                ['label' => 'Title', 'bbox' => [60.0, 92.0, INF, 150.0], 'raw_payload' => 'nonfinite layout bbox payload must stay hidden'],
                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
            ],
        ];

        $layoutPreview = (new LayoutAnnotator())->runWithSuppliedLayouts(
            [['page' => 3501, 'image' => 'nonfinite-layout-render']],
            [['pnum' => 3501, 'blocks' => []]],
            [$nonfiniteLayoutResult]
        );
        $layoutPreviewEncoded = json_encode($layoutPreview, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $t->same(1, $layoutPreview['plan']['assigned_pages']);
        $t->same([
            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ], $layoutPreview['pages'][0]['layout']['bboxes'] ?? []);
        $t->true(!str_contains($layoutPreviewEncoded, 'INF'));
        $t->true(!str_contains($layoutPreviewEncoded, 'nonfinite layout bbox payload'));

        $path = sys_get_temp_dir() . '/markerpdf-nonfinite-bbox-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% nonfinite bbox pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(3501, [
                        ['text' => 'Second converter finite geometry column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter nonfinite geometry fallback.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 0,
                    'lowres_images' => [
                        ['page' => 3501, 'image' => 'nonfinite-layout-render'],
                    ],
                    'layout_results' => [[
                        'page' => 3501,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [60.0, 92.0, INF, 150.0], 'raw_payload' => 'nonfinite layout bbox payload must stay hidden'],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                    ]],
                    'order_images' => [
                        ['page' => 3501, 'image' => 'nonfinite-order-render'],
                    ],
                    'order_results' => [[
                        'page' => 3501,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, INF, 144.0], 'raw_payload' => 'nonfinite converter order bbox payload must stay hidden'],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                    ]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([0], $result['metadata']['page_range'] ?? null);
        $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['layout_result_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['order_result_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('Second converter finite geometry column.', $text);
        $t->contains('First converter nonfinite geometry fallback.', $text);
        $t->true(strpos($text, 'Second converter finite geometry column.') < strpos($text, 'First converter nonfinite geometry fallback.'));
        $t->true(!str_contains($text, '# First Converter Nonfinite Geometry Fallback.'));
        $t->true(!str_contains($encoded, 'INF'));
        $t->true(!str_contains($encoded, 'nonfinite layout bbox payload'));
        $t->true(!str_contains($encoded, 'nonfinite converter order bbox payload'));
    },
    'rejects mixed wrapper-list payload dictionaries before selected pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(2400, [
                    ['text' => 'Mixed wrapper-list cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(2401, [
                    ['text' => 'Second mixed wrapper-list column remains source ordered', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First mixed wrapper-list column has no trusted order', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'metadata' => [
                        ['page' => 2401],
                        ['raw_payload' => 'mixed wrapper-list payload dictionary must not select order'],
                    ],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                [
                    'metadata' => [
                        ['page' => 2401],
                        ['raw_payload' => 'mixed wrapper-list image payload dictionary must not select order image'],
                    ],
                    'image' => 'mixed-wrapper-list-order-render',
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(2401, $result['pages'][0]['pnum']);
        $t->same(['Second mixed wrapper-list column remains source ordered', 'First mixed wrapper-list column has no trusted order'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second mixed wrapper-list column remains source ordered First mixed wrapper-list column has no trusted order', $blocks[0]['text']);
        $t->same(null, $result['pages'][0]['order'] ?? null);
        $t->true(!str_contains($encoded, 'mixed wrapper-list payload dictionary'));
        $t->same(0, $result['metadata']['order_plan']['image_count']);
        $t->same(0, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
    },
    'rejects multi-dictionary typed order payload wrappers before selected pdftext layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(2500, [
                    ['text' => 'Typed payload-list cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(2501, [
                    ['text' => 'Second typed payload-list column remains source ordered', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First typed payload-list column has no trusted order', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['document_page' => 2501],
                    'order_result' => [
                        [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                        ],
                        ['raw_payload' => 'multi-dictionary typed order payload must not be selected'],
                    ],
                ],
            ],
            orderImages: [
                [
                    'metadata' => ['document_page' => 2501],
                    'image' => 'typed-payload-list-order-render',
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(2501, $result['pages'][0]['pnum']);
        $t->same(['Second typed payload-list column remains source ordered', 'First typed payload-list column has no trusted order'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second typed payload-list column remains source ordered First typed payload-list column has no trusted order', $blocks[0]['text']);
        $t->same(null, $result['pages'][0]['order'] ?? null);
        $t->true(!str_contains($encoded, 'multi-dictionary typed order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
    },
    'rejects duplicate matching supplied order artifacts before selected pdftext layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(3600, [
                    ['text' => 'Duplicate supplied artifact cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(3601, [
                    ['text' => 'Second duplicate supplied artifact column remains source ordered', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First duplicate supplied artifact has no trusted order', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['document_page' => 3601],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'first duplicate supplied order payload must stay hidden',
                ],
                [
                    'metadata' => ['document_page' => 3601],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                    'raw_payload' => 'second duplicate supplied order payload must stay hidden',
                ],
            ],
            orderImages: [
                ['metadata' => ['document_page' => 3601], 'image' => 'first-duplicate-supplied-order-render'],
                ['metadata' => ['document_page' => 3601], 'image' => 'second-duplicate-supplied-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(3601, $result['pages'][0]['pnum']);
        $t->same(['Second duplicate supplied artifact column remains source ordered', 'First duplicate supplied artifact has no trusted order'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second duplicate supplied artifact column remains source ordered First duplicate supplied artifact has no trusted order', $blocks[0]['text']);
        $t->same(null, $result['pages'][0]['order'] ?? null);
        $t->true(!str_contains($encoded, 'first duplicate supplied order payload'));
        $t->true(!str_contains($encoded, 'second duplicate supplied order payload'));
        $t->same(0, $result['metadata']['order_plan']['image_count']);
        $t->same(0, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
    },
    'rejects duplicate matching supplied layout and order artifacts before WordPress imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-duplicate-supplied-artifacts-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% duplicate supplied artifacts pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(3700, [
                        ['text' => 'Duplicate supplied artifact converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(3701, [
                        ['text' => 'Second converter duplicate artifact column stays source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter duplicate artifact has no trusted order.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['metadata' => ['document_page' => 3701], 'image' => 'first-duplicate-layout-render'],
                        ['metadata' => ['document_page' => 3701], 'image' => 'second-duplicate-layout-render'],
                    ],
                    'layout_results' => [
                        [
                            'metadata' => ['document_page' => 3701],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'first duplicate layout payload must stay hidden',
                        ],
                        [
                            'metadata' => ['document_page' => 3701],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ['label' => 'Title', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'second duplicate layout payload must stay hidden',
                        ],
                    ],
                    'order_images' => [
                        ['metadata' => ['document_page' => 3701], 'image' => 'first-duplicate-order-render'],
                        ['metadata' => ['document_page' => 3701], 'image' => 'second-duplicate-order-render'],
                    ],
                    'order_results' => [
                        [
                            'metadata' => ['document_page' => 3701],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'first duplicate converter order payload must stay hidden',
                        ],
                        [
                            'metadata' => ['document_page' => 3701],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'second duplicate converter order payload must stay hidden',
                        ],
                    ],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([1], $result['metadata']['page_range'] ?? null);
        $t->same([], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(null, $result['metadata']['layout_plan'] ?? null);
        $t->same(null, $result['metadata']['order_plan'] ?? null);
        $t->contains('Second converter duplicate artifact column stays source ordered.', $text);
        $t->contains('First converter duplicate artifact has no trusted order.', $text);
        $t->true(strpos($text, 'Second converter duplicate artifact column stays source ordered.') < strpos($text, 'First converter duplicate artifact has no trusted order.'));
        $t->true(!str_contains($text, '# First Converter Duplicate Artifact Has No Trusted Order.'));
        $t->true(!str_contains($text, 'Duplicate supplied artifact converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'first duplicate layout payload'));
        $t->true(!str_contains($encoded, 'second duplicate layout payload'));
        $t->true(!str_contains($encoded, 'first duplicate converter order payload'));
        $t->true(!str_contains($encoded, 'second duplicate converter order payload'));
    },
    'rejects non-finite supplied page markers before first-page pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(0, [
                    ['text' => 'Second nonfinite marker column remains source ordered', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First nonfinite marker column has no trusted order', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => INF,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'nonfinite marker order payload must stay hidden',
                ],
            ],
            orderImages: [
                ['page' => INF, 'image' => 'nonfinite-marker-order-render'],
            ],
            maxPages: 1,
            startPage: 0
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $result['page_range']);
        $t->same(0, $result['pages'][0]['pnum']);
        $t->same(['Second nonfinite marker column remains source ordered', 'First nonfinite marker column has no trusted order'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second nonfinite marker column remains source ordered First nonfinite marker column has no trusted order', $blocks[0]['text']);
        $t->same(null, $result['pages'][0]['order'] ?? null);
        $t->true(!str_contains($encoded, 'nonfinite marker order payload'));
        $t->same(0, $result['metadata']['order_plan']['image_count']);
        $t->same(0, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
    },
];
