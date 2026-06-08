<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\LayoutAnnotator;
use PortLibs\MarkerPDF\LayoutOrderer;
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

$jsonDecodedList = static function (array $value): array {
    $decoded = json_decode(
        json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        false,
        512,
        JSON_THROW_ON_ERROR
    );
    if (!is_array($decoded)) {
        throw new RuntimeException('Expected JSON-decoded artifact fixture to remain a list.');
    }

    return $decoded;
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
    'uses point-pair order bboxes before selected pdftext layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(3800, [
                    ['text' => 'Point-pair bbox cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(3801, [
                    ['text' => 'Second point-pair column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First point-pair column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 3801,
                    'image_bbox' => ['top_left' => ['x' => 0.0, 'y' => 0.0], 'bottom_right' => ['x' => 612.0, 'y' => 792.0]],
                    'bboxes' => [
                        [
                            'position' => 1,
                            'top_left' => ['x' => 60.0, 'y' => 96.0],
                            'bottom_right' => ['x' => 290.0, 'y' => 144.0],
                            'raw_payload' => 'point-pair left order payload must stay review-only',
                        ],
                        [
                            'position' => 2,
                            'tl' => [318.0, 96.0],
                            'br' => [570.0, 144.0],
                            'raw_payload' => 'point-pair right order payload must stay review-only',
                        ],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 3801, 'image' => 'point-pair-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(3801, $result['pages'][0]['pnum']);
        $t->same(['First point-pair column', 'Second point-pair column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First point-pair column Second point-pair column', $blocks[0]['text']);
        $t->same([0.0, 0.0, 612.0, 792.0], $order['image_bbox'] ?? null);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!str_contains($encoded, 'point-pair left order payload'));
        $t->true(!str_contains($encoded, 'point-pair right order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses point-pair layout and order bboxes for WordPress supplied imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-point-pair-bbox-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% point-pair bbox pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(3900, [
                        ['text' => 'Point-pair converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(3901, [
                        ['text' => 'Second converter point-pair column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter point-pair column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['page' => 3901, 'image' => 'point-pair-layout-render'],
                    ],
                    'layout_results' => [[
                        'page' => 3901,
                        'image_bbox' => ['tl' => [0.0, 0.0], 'br' => [612.0, 792.0]],
                        'bboxes' => [
                            [
                                'label' => 'Title',
                                'top_left' => ['x' => 60.0, 'y' => 92.0],
                                'bottom_right' => ['x' => 290.0, 'y' => 150.0],
                                'raw_payload' => 'point-pair title layout payload must stay hidden',
                            ],
                            [
                                'label' => 'Text',
                                'tl' => [318.0, 92.0],
                                'br' => [570.0, 150.0],
                                'raw_payload' => 'point-pair body layout payload must stay hidden',
                            ],
                        ],
                    ]],
                    'order_images' => [
                        ['page' => 3901, 'image' => 'point-pair-order-render'],
                    ],
                    'order_results' => [[
                        'page' => 3901,
                        'image_bbox' => ['tl' => [0.0, 0.0], 'br' => [612.0, 792.0]],
                        'bboxes' => [
                            [
                                'position' => 1,
                                'top_left' => ['x' => 60.0, 'y' => 96.0],
                                'bottom_right' => ['x' => 290.0, 'y' => 144.0],
                                'raw_payload' => 'point-pair left converter order payload must stay hidden',
                            ],
                            [
                                'position' => 2,
                                'tl' => [318.0, 96.0],
                                'br' => [570.0, 144.0],
                                'raw_payload' => 'point-pair right converter order payload must stay hidden',
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
        $t->contains('# First Converter Point-Pair Column.', $text);
        $t->contains('Second converter point-pair column.', $text);
        $t->true(strpos($text, '# First Converter Point-Pair Column.') < strpos($text, 'Second converter point-pair column.'));
        $t->true(!str_contains($text, 'Point-pair converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'point-pair title layout payload'));
        $t->true(!str_contains($encoded, 'point-pair body layout payload'));
        $t->true(!str_contains($encoded, 'point-pair left converter order payload'));
        $t->true(!str_contains($encoded, 'point-pair right converter order payload'));
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
    'uses plural page-number markers before selected pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(5400, [
                    ['text' => 'Plural page-number cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(5401, [
                    ['text' => 'Second plural page-number column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First plural page-number column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
                $pdftextLinesPage(5402, [
                    ['text' => 'Plural page-number appendix should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['page_numbers' => [5401]],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                    'raw_payload' => 'plural page-number cover order payload must stay hidden',
                ],
                [
                    'metadata' => ['page_numbers' => [5402]],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'plural page-number selected order payload must stay hidden',
                ],
                [
                    'metadata' => ['page_numbers' => [5401, 5402]],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                    'raw_payload' => 'ambiguous plural page-number order payload must stay hidden',
                ],
            ],
            orderImages: [
                ['metadata' => ['page_numbers' => [5401]], 'image' => 'plural-page-number-cover-order-render'],
                ['metadata' => ['page_numbers' => [5402]], 'image' => 'plural-page-number-selected-order-render'],
                ['metadata' => ['page_numbers' => [5401, 5402]], 'image' => 'plural-page-number-ambiguous-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(5401, $result['pages'][0]['pnum']);
        $t->same(['First plural page-number column', 'Second plural page-number column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First plural page-number column Second plural page-number column', $blocks[0]['text']);
        $t->same(5402, $order['page_numbers'] ?? null);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('metadata', $order));
        $t->true(!str_contains($encoded, 'Plural page-number cover should stay skipped'));
        $t->true(!str_contains($encoded, 'Plural page-number appendix should stay skipped'));
        $t->true(!str_contains($encoded, 'plural page-number cover order payload'));
        $t->true(!str_contains($encoded, 'plural page-number selected order payload'));
        $t->true(!str_contains($encoded, 'ambiguous plural page-number order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses plural selected-page-number markers for sparse WordPress layout order artifacts' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-plural-page-number-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% plural page-number pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(5500, [
                        ['text' => 'Plural selected-number converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(5501, [
                        ['text' => 'Second plural selected-number first page stays source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First plural selected-number first page has no supplied order.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                    $pdftextLinesPage(5502, [
                        ['text' => 'Second plural selected-number second page body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First plural selected-number second page title.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                    $pdftextLinesPage(5503, [
                        ['text' => 'Plural selected-number converter appendix should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 2,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['metadata' => ['selected_page_numbers' => [2]], 'image' => 'plural-selected-number-layout-render'],
                    ],
                    'layout_results' => [[
                        'metadata' => ['selected_page_numbers' => [2]],
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'plural selected-number title layout payload must stay hidden'],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                        'raw_payload' => 'plural selected-number layout payload must stay hidden',
                    ]],
                    'order_images' => [
                        ['metadata' => ['selected_page_numbers' => [2]], 'image' => 'plural-selected-number-order-render'],
                    ],
                    'order_results' => [[
                        'metadata' => ['selected_page_numbers' => [2]],
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'plural selected-number order row payload must stay hidden'],
                        ],
                        'raw_payload' => 'plural selected-number order payload must stay hidden',
                    ]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([1, 2], $result['metadata']['page_range'] ?? null);
        $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['layout_result_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['order_result_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->same(2, $result['metadata']['layout_plan']['page_count'] ?? null);
        $t->same(2, $result['metadata']['order_plan']['page_count'] ?? null);
        $t->contains('Second plural selected-number first page stays source ordered.', $text);
        $t->contains('First plural selected-number first page has no supplied order.', $text);
        $t->contains('# First Plural Selected-Number Second Page Title.', $text);
        $t->contains('Second plural selected-number second page body.', $text);
        $t->true(strpos($text, 'Second plural selected-number first page stays source ordered.') < strpos($text, 'First plural selected-number first page has no supplied order.'));
        $t->true(strpos($text, '# First Plural Selected-Number Second Page Title.') < strpos($text, 'Second plural selected-number second page body.'));
        $t->true(!str_contains($text, 'Plural selected-number converter cover should stay skipped.'));
        $t->true(!str_contains($text, 'Plural selected-number converter appendix should stay skipped.'));
        $t->true(!str_contains($encoded, 'plural selected-number title layout payload'));
        $t->true(!str_contains($encoded, 'plural selected-number layout payload'));
        $t->true(!str_contains($encoded, 'plural selected-number order row payload'));
        $t->true(!str_contains($encoded, 'plural selected-number order payload'));
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
                    ['text' => 'Right finite order row has the only trusted bbox', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'Left nonfinite order row shares the first upstream group', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
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
        $t->same([
            'Left nonfinite order row shares the first upstream group',
            'Right finite order row has the only trusted bbox',
        ], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Left nonfinite order row shares the first upstream group Right finite order row has the only trusted bbox', $blocks[0]['text']);
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
                        ['text' => 'Right converter finite geometry column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'Left converter nonfinite geometry row shares upstream group.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
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
        $t->contains('Right converter finite geometry column.', $text);
        $t->contains('Left converter nonfinite geometry row shares upstream group.', $text);
        $t->true(strpos($text, 'Left converter nonfinite geometry row shares upstream group.') < strpos($text, 'Right converter finite geometry column.'));
        $t->true(!str_contains($text, '# Left Converter Nonfinite Geometry Row Shares Upstream Group.'));
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
    'filters stale row-level layout and order page markers before WordPress pdftext assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-row-page-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% row-level page marker layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(4200, [
                        ['text' => 'Row marker converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(4201, [
                        ['text' => 'Second converter row marker column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter row marker column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['metadata' => ['document_page' => 4201], 'image' => 'row-page-layout-render'],
                    ],
                    'layout_results' => [[
                        'metadata' => ['document_page' => 4201],
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            [
                                'label' => 'Title',
                                'document_page' => 4200,
                                'bbox' => [60.0, 92.0, 290.0, 150.0],
                                'raw_payload' => 'stale row-level layout title payload must stay hidden',
                            ],
                            [
                                'label' => 'Text',
                                'document_page' => 4201,
                                'bbox' => [60.0, 92.0, 290.0, 150.0],
                            ],
                            [
                                'label' => 'Text',
                                'document_page' => 4201,
                                'bbox' => [318.0, 92.0, 570.0, 150.0],
                            ],
                        ],
                    ]],
                    'order_images' => [
                        ['metadata' => ['document_page' => 4201], 'image' => 'row-page-order-render'],
                    ],
                    'order_results' => [[
                        'metadata' => ['document_page' => 4201],
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            [
                                'position' => 1,
                                'document_page' => 4200,
                                'bbox' => [318.0, 96.0, 570.0, 144.0],
                                'raw_payload' => 'stale row-level order bbox payload must stay hidden',
                            ],
                            [
                                'position' => 2,
                                'document_page' => 4201,
                                'bbox' => [60.0, 96.0, 290.0, 144.0],
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
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('First converter row marker column.', $text);
        $t->contains('Second converter row marker column.', $text);
        $t->true(strpos($text, 'First converter row marker column.') < strpos($text, 'Second converter row marker column.'));
        $t->true(!str_contains($text, '# First Converter Row Marker Column.'));
        $t->true(!str_contains($text, 'Row marker converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'stale row-level layout title payload'));
        $t->true(!str_contains($encoded, 'stale row-level order bbox payload'));
    },
    'keeps zero-overlap blocks in the first upstream order group before selected pdftext merge' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(4100, [
                    ['text' => 'Partial order cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(4101, [
                    ['text' => 'Right partial order column has the only supplied bbox', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'Left partial order column has zero overlap but same upstream group', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 4101,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 4101, 'image' => 'partial-order-single-bbox-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(4101, $result['pages'][0]['pnum']);
        $t->same([
            'Left partial order column has zero overlap but same upstream group',
            'Right partial order column has the only supplied bbox',
        ], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Left partial order column has zero overlap but same upstream group Right partial order column has the only supplied bbox', $blocks[0]['text']);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'normalizes JSON decoded order artifacts before selected pdftext layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage, $jsonDecodedList): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            $jsonDecodedList([
                $pdftextLinesPage(4300, [
                    ['text' => 'JSON decoded cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(4301, [
                    ['text' => 'Second JSON decoded order column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First JSON decoded order column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ]),
            $jsonDecodedList([
                [
                    'metadata' => ['document_page' => 4300],
                    'order_result' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ],
                    ],
                    'raw_payload' => 'json decoded cover order payload must stay hidden',
                ],
                [
                    'metadata' => ['document_page' => 4301],
                    'order_result' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'json decoded left row payload must stay hidden'],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                    ],
                    'raw_payload' => 'json decoded selected order payload must stay hidden',
                ],
            ]),
            orderImages: $jsonDecodedList([
                ['metadata' => ['document_page' => 4300], 'image' => 'json-decoded-cover-order-render'],
                ['metadata' => ['document_page' => 4301], 'image' => 'json-decoded-selected-order-render'],
            ]),
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(4301, $result['pages'][0]['pnum']);
        $t->same(['First JSON decoded order column', 'Second JSON decoded order column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First JSON decoded order column Second JSON decoded order column', $blocks[0]['text']);
        $t->same(4301, $order['document_page'] ?? null);
        $t->true(!array_key_exists('order_result', $order));
        $t->true(!str_contains($encoded, 'JSON decoded cover should stay skipped'));
        $t->true(!str_contains($encoded, 'json decoded cover order payload'));
        $t->true(!str_contains($encoded, 'json decoded selected order payload'));
        $t->true(!str_contains($encoded, 'json decoded left row payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'normalizes JSON decoded layout and order artifacts before WordPress pdftext import' => static function (TestRunner $t) use ($pdftextLinesPage, $jsonDecodedList): void {
        $path = sys_get_temp_dir() . '/markerpdf-json-decoded-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% json decoded pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                $jsonDecodedList([
                    $pdftextLinesPage(4400, [
                        ['text' => 'JSON decoded converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(4401, [
                        ['text' => 'Second converter JSON decoded column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter JSON decoded heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ]),
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => $jsonDecodedList([
                        ['metadata' => ['document_page' => 4400], 'image' => 'json-decoded-cover-layout-render'],
                        ['metadata' => ['document_page' => 4401], 'image' => 'json-decoded-selected-layout-render'],
                    ]),
                    'layout_results' => $jsonDecodedList([
                        [
                            'metadata' => ['document_page' => 4400],
                            'layout_result' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ],
                            ],
                            'raw_payload' => 'json decoded cover layout payload must stay hidden',
                        ],
                        [
                            'metadata' => ['document_page' => 4401],
                            'layout_result' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'json decoded title layout payload must stay hidden'],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                            ],
                            'raw_payload' => 'json decoded selected layout payload must stay hidden',
                        ],
                    ]),
                    'order_images' => $jsonDecodedList([
                        ['metadata' => ['document_page' => 4400], 'image' => 'json-decoded-cover-order-render'],
                        ['metadata' => ['document_page' => 4401], 'image' => 'json-decoded-selected-order-render'],
                    ]),
                    'order_results' => $jsonDecodedList([
                        [
                            'metadata' => ['document_page' => 4400],
                            'order_result' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ],
                            ],
                            'raw_payload' => 'json decoded cover order payload must stay hidden',
                        ],
                        [
                            'metadata' => ['document_page' => 4401],
                            'order_result' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'json decoded right row payload must stay hidden'],
                                ],
                            ],
                            'raw_payload' => 'json decoded selected order payload must stay hidden',
                        ],
                    ]),
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
        $t->contains('# First Converter Json Decoded Heading.', $text);
        $t->contains('Second converter JSON decoded column.', $text);
        $t->true(strpos($text, '# First Converter Json Decoded Heading.') < strpos($text, 'Second converter JSON decoded column.'));
        $t->true(!str_contains($text, 'JSON decoded converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'json decoded cover layout payload'));
        $t->true(!str_contains($encoded, 'json decoded selected layout payload'));
        $t->true(!str_contains($encoded, 'json decoded title layout payload'));
        $t->true(!str_contains($encoded, 'json decoded cover order payload'));
        $t->true(!str_contains($encoded, 'json decoded selected order payload'));
        $t->true(!str_contains($encoded, 'json decoded right row payload'));
    },
    'unwraps adapter page-result order payload envelopes before selected pdftext layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(4500, [
                    ['text' => 'Page-result payload cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(4501, [
                    ['text' => 'Second page-result payload column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First page-result payload column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page_result' => [
                        'document_page' => 4500,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ],
                        'raw_payload' => 'page-result cover order payload must stay hidden',
                    ],
                ],
                [
                    'page_result' => [
                        'document_page' => 4501,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                        'raw_payload' => 'page-result selected order payload must stay hidden',
                    ],
                ],
            ],
            orderImages: [
                ['page_result' => ['document_page' => 4500], 'image' => 'page-result-cover-order-render'],
                ['page_result' => ['document_page' => 4501], 'image' => 'page-result-selected-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(4501, $result['pages'][0]['pnum']);
        $t->same(['First page-result payload column', 'Second page-result payload column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First page-result payload column Second page-result payload column', $blocks[0]['text']);
        $t->same(4501, $order['document_page'] ?? null);
        $t->same([0.0, 0.0, 612.0, 792.0], $order['image_bbox'] ?? null);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('page_result', $order));
        $t->true(!str_contains($encoded, 'page-result cover order payload'));
        $t->true(!str_contains($encoded, 'page-result selected order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'unwraps adapter page-result layout and order payload envelopes for WordPress imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-page-result-payload-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% page-result payload pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(4510, [
                        ['text' => 'Page-result payload converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(4511, [
                        ['text' => 'Second converter page-result body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter page-result heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['page_data' => ['document_page' => 4510], 'image' => 'page-result-cover-layout-render'],
                        ['page_data' => ['document_page' => 4511], 'image' => 'page-result-selected-layout-render'],
                    ],
                    'layout_results' => [
                        [
                            'page_data' => [
                                'document_page' => 4510,
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ],
                                'raw_payload' => 'page-data cover layout payload must stay hidden',
                            ],
                        ],
                        [
                            'page_data' => [
                                'document_page' => 4511,
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'page-data title layout row payload must stay hidden'],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'page-data selected layout payload must stay hidden',
                            ],
                        ],
                    ],
                    'order_images' => [
                        ['page_result' => ['document_page' => 4510], 'image' => 'page-result-cover-order-render'],
                        ['page_result' => ['document_page' => 4511], 'image' => 'page-result-selected-order-render'],
                    ],
                    'order_results' => [
                        [
                            'page_result' => [
                                'document_page' => 4510,
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ],
                                'raw_payload' => 'page-result cover order payload must stay hidden',
                            ],
                        ],
                        [
                            'page_result' => [
                                'document_page' => 4511,
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'page-result right order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'page-result selected order payload must stay hidden',
                            ],
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
        $t->contains('# First Converter Page-Result Heading.', $text);
        $t->contains('Second converter page-result body.', $text);
        $t->true(strpos($text, '# First Converter Page-Result Heading.') < strpos($text, 'Second converter page-result body.'));
        $t->true(!str_contains($text, 'Page-result payload converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'page-data cover layout payload'));
        $t->true(!str_contains($encoded, 'page-data selected layout payload'));
        $t->true(!str_contains($encoded, 'page-data title layout row payload'));
        $t->true(!str_contains($encoded, 'page-result cover order payload'));
        $t->true(!str_contains($encoded, 'page-result selected order payload'));
        $t->true(!str_contains($encoded, 'page-result right order row payload'));
    },
    'rejects normalized order rows when supplied image bbox has no area before pdftext layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(4600, [
                    ['text' => 'Zero image-bbox order cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(4601, [
                    ['text' => 'Second zero image-bbox order column cannot trust normalized row', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First zero image-bbox order column uses absolute fallback', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 4601,
                    'image_bbox' => [0.0, 0.0, 0.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [0.519, 0.121, 0.932, 0.182], 'raw_payload' => 'normalized order row needs positive image extent'],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 4601, 'image' => 'zero-image-bbox-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(4601, $result['pages'][0]['pnum']);
        $t->same([
            'First zero image-bbox order column uses absolute fallback',
            'Second zero image-bbox order column cannot trust normalized row',
        ], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First zero image-bbox order column uses absolute fallback Second zero image-bbox order column cannot trust normalized row', $blocks[0]['text']);
        $t->true(!array_key_exists('image_bbox', $order));
        $t->same([
            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!str_contains($encoded, 'normalized order row needs positive image extent'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'rejects normalized layout and order rows when image bboxes have no area before WordPress imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $pdftextPages = [
            $pdftextLinesPage(4700, [
                ['text' => 'Zero image-bbox converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $pdftextLinesPage(4701, [
                ['text' => 'Second converter zero image-bbox body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First converter zero image-bbox heading should stay text.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ];
        $selectedPage = $pdftextPages[1];
        $layoutResult = [
            'page' => 4701,
            'image_bbox' => [0.0, 0.0, 612.0, 0.0],
            'bboxes' => [
                ['label' => 'Title', 'bbox' => [0.098, 0.116, 0.474, 0.189], 'raw_payload' => 'normalized layout row needs positive image extent'],
                ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
            ],
        ];
        $orderResult = [
            'page' => 4701,
            'image_bbox' => [0.0, 0.0, 0.0, 792.0],
            'bboxes' => [
                ['position' => 1, 'bbox' => [0.519, 0.121, 0.932, 0.182], 'raw_payload' => 'normalized converter order row needs positive image extent'],
                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ],
        ];
        $layoutPreview = (new LayoutAnnotator())->runWithSuppliedLayouts(
            [['page' => 4701, 'image' => 'zero-image-bbox-layout-render']],
            [$selectedPage],
            [$layoutResult],
            1.0,
            [1]
        );
        $orderPreview = (new LayoutOrderer())->runWithSuppliedOrder(
            [['page' => 4701, 'image' => 'zero-image-bbox-order-render']],
            [$selectedPage],
            [$orderResult],
            1.0,
            [1]
        );
        $layout = $layoutPreview['pages'][0]['layout'] ?? [];
        $order = $orderPreview['pages'][0]['order'] ?? [];

        $path = sys_get_temp_dir() . '/markerpdf-zero-image-bbox-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% zero image bbox pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                $pdftextPages,
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['page' => 4701, 'image' => 'zero-image-bbox-layout-render'],
                    ],
                    'layout_results' => [$layoutResult],
                    'order_images' => [
                        ['page' => 4701, 'image' => 'zero-image-bbox-order-render'],
                    ],
                    'order_results' => [$orderResult],
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
        $t->same(1, $layoutPreview['plan']['assigned_pages']);
        $t->same(1, $orderPreview['plan']['assigned_pages']);
        $t->true(!array_key_exists('image_bbox', $layout));
        $t->true(!array_key_exists('image_bbox', $order));
        $t->same([
            ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ], $layout['bboxes'] ?? []);
        $t->same([
            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->contains('First converter zero image-bbox heading should stay text.', $text);
        $t->contains('Second converter zero image-bbox body.', $text);
        $t->true(strpos($text, 'First converter zero image-bbox heading should stay text.') < strpos($text, 'Second converter zero image-bbox body.'));
        $t->true(!str_contains($text, '# First Converter Zero Image-Bbox Heading Should Stay Text.'));
        $t->true(!str_contains($text, 'Zero image-bbox converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'normalized layout row needs positive image extent'));
        $t->true(!str_contains($encoded, 'normalized converter order row needs positive image extent'));
    },
    'unwraps dictionary-output artifact envelopes before selected pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(4800, [
                    ['text' => 'Dictionary-output artifact cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(4801, [
                    ['text' => 'Second dictionary-output artifact column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First dictionary-output artifact column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
                $pdftextLinesPage(4802, [
                    ['text' => 'Dictionary-output artifact appendix should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['source' => 'cached order dictionary_output envelope'],
                    'dictionary_output' => [
                        [
                            'page' => 4800,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'dictionary-output cover order payload must stay hidden',
                        ],
                        [
                            'page' => 4801,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'dictionary-output selected order payload must stay hidden',
                        ],
                    ],
                    'raw_payload' => 'dictionary-output order envelope payload must stay hidden',
                ],
            ],
            orderImages: [
                [
                    'metadata' => ['source' => 'cached order image dictionary_output envelope'],
                    'dictionary_output' => [
                        ['page' => 4800, 'image' => 'dictionary-output-cover-order-render'],
                        ['page' => 4801, 'image' => 'dictionary-output-selected-order-render'],
                    ],
                    'raw_payload' => 'dictionary-output order image envelope payload must stay hidden',
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(4801, $result['pages'][0]['pnum']);
        $t->same(['First dictionary-output artifact column', 'Second dictionary-output artifact column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First dictionary-output artifact column Second dictionary-output artifact column', $blocks[0]['text']);
        $t->same(4801, $order['page'] ?? null);
        $t->true(!array_key_exists('dictionary_output', $order));
        $t->true(!str_contains($encoded, 'Dictionary-output artifact cover should stay skipped'));
        $t->true(!str_contains($encoded, 'Dictionary-output artifact appendix should stay skipped'));
        $t->true(!str_contains($encoded, 'dictionary-output cover order payload'));
        $t->true(!str_contains($encoded, 'dictionary-output selected order payload'));
        $t->true(!str_contains($encoded, 'dictionary-output order envelope payload'));
        $t->true(!str_contains($encoded, 'dictionary-output order image envelope payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'unwraps direct dictionary-output payload envelopes before selected pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(4900, [
                    ['text' => 'Direct dictionary-output payload cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(4901, [
                    ['text' => 'Second direct dictionary-output payload column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First direct dictionary-output payload column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 4901,
                    'dictionary_output' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'direct dictionary-output left row payload must stay hidden'],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                        'raw_payload' => 'direct dictionary-output order payload must stay hidden',
                    ],
                    'raw_payload' => 'direct dictionary-output envelope payload must stay hidden',
                ],
            ],
            orderImages: [
                ['page' => 4901, 'image' => 'direct-dictionary-output-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(4901, $result['pages'][0]['pnum']);
        $t->same(['First direct dictionary-output payload column', 'Second direct dictionary-output payload column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First direct dictionary-output payload column Second direct dictionary-output payload column', $blocks[0]['text']);
        $t->same(4901, $order['page'] ?? null);
        $t->same([0.0, 0.0, 612.0, 792.0], $order['image_bbox'] ?? null);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('dictionary_output', $order));
        $t->true(!str_contains($encoded, 'Direct dictionary-output payload cover skipped'));
        $t->true(!str_contains($encoded, 'direct dictionary-output order payload'));
        $t->true(!str_contains($encoded, 'direct dictionary-output left row payload'));
        $t->true(!str_contains($encoded, 'direct dictionary-output envelope payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'unwraps direct pages and dictionary-output payload envelopes for WordPress imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-direct-envelope-payload-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% direct envelope payload pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(4910, [
                        ['text' => 'Direct envelope converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(4911, [
                        ['text' => 'Second converter direct envelope body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter direct envelope heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['page' => 4911, 'image' => 'direct-envelope-layout-render'],
                    ],
                    'layout_results' => [[
                        'page' => 4911,
                        'pages' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'direct pages title layout row payload must stay hidden'],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'direct pages layout payload must stay hidden',
                        ],
                        'raw_payload' => 'direct pages layout envelope payload must stay hidden',
                    ]],
                    'order_images' => [
                        ['page' => 4911, 'image' => 'direct-envelope-order-render'],
                    ],
                    'order_results' => [[
                        'page' => 4911,
                        'dictionary_output' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'direct dictionary-output order row payload must stay hidden'],
                            ],
                            'raw_payload' => 'direct dictionary-output order payload must stay hidden',
                        ],
                        'raw_payload' => 'direct dictionary-output order envelope payload must stay hidden',
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
        $t->contains('# First Converter Direct Envelope Heading.', $text);
        $t->contains('Second converter direct envelope body.', $text);
        $t->true(strpos($text, '# First Converter Direct Envelope Heading.') < strpos($text, 'Second converter direct envelope body.'));
        $t->true(!str_contains($text, 'Direct envelope converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'direct pages title layout row payload'));
        $t->true(!str_contains($encoded, 'direct pages layout payload'));
        $t->true(!str_contains($encoded, 'direct pages layout envelope payload'));
        $t->true(!str_contains($encoded, 'direct dictionary-output order row payload'));
        $t->true(!str_contains($encoded, 'direct dictionary-output order payload'));
        $t->true(!str_contains($encoded, 'direct dictionary-output order envelope payload'));
    },
    'unwraps typed direct-envelope order payloads before selected pdftext layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(5000, [
                    ['text' => 'Typed direct-envelope cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(5001, [
                    ['text' => 'Second typed direct-envelope column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First typed direct-envelope column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['document_page' => 5000],
                    'order_result' => [
                        'dictionary_output' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'typed direct-envelope cover order payload must stay hidden',
                        ],
                    ],
                ],
                [
                    'metadata' => ['document_page' => 5001],
                    'order_result' => [
                        'dictionary_output' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'typed direct-envelope selected order row payload must stay hidden'],
                            ],
                            'raw_payload' => 'typed direct-envelope selected order payload must stay hidden',
                        ],
                    ],
                ],
            ],
            orderImages: [
                ['metadata' => ['document_page' => 5000], 'image' => 'typed-direct-envelope-cover-order-render'],
                ['metadata' => ['document_page' => 5001], 'image' => 'typed-direct-envelope-selected-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(5001, $result['pages'][0]['pnum']);
        $t->same(['First typed direct-envelope column', 'Second typed direct-envelope column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First typed direct-envelope column Second typed direct-envelope column', $blocks[0]['text']);
        $t->same(5001, $order['document_page'] ?? null);
        $t->same([0.0, 0.0, 612.0, 792.0], $order['image_bbox'] ?? null);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('order_result', $order));
        $t->true(!array_key_exists('dictionary_output', $order));
        $t->true(!str_contains($encoded, 'typed direct-envelope cover order payload'));
        $t->true(!str_contains($encoded, 'typed direct-envelope selected order payload'));
        $t->true(!str_contains($encoded, 'typed direct-envelope selected order row payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'unwraps typed direct-envelope layout and order payloads for WordPress imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-typed-direct-envelope-payload-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% typed direct envelope payload pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(5010, [
                        ['text' => 'Typed direct-envelope converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(5011, [
                        ['text' => 'Second converter typed direct-envelope body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter typed direct-envelope heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['metadata' => ['document_page' => 5011], 'image' => 'typed-direct-envelope-layout-render'],
                    ],
                    'layout_results' => [[
                        'metadata' => ['document_page' => 5011],
                        'layout_result' => [
                            'pages' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'typed direct-envelope title layout row payload must stay hidden'],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'typed direct-envelope layout payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'typed direct-envelope layout envelope payload must stay hidden',
                    ]],
                    'order_images' => [
                        ['metadata' => ['document_page' => 5011], 'image' => 'typed-direct-envelope-order-render'],
                    ],
                    'order_results' => [[
                        'metadata' => ['document_page' => 5011],
                        'order_result' => [
                            'dictionary_output' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'typed direct-envelope order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'typed direct-envelope order payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'typed direct-envelope order envelope payload must stay hidden',
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
        $t->contains('# First Converter Typed Direct-Envelope Heading.', $text);
        $t->contains('Second converter typed direct-envelope body.', $text);
        $t->true(strpos($text, '# First Converter Typed Direct-Envelope Heading.') < strpos($text, 'Second converter typed direct-envelope body.'));
        $t->true(!str_contains($text, 'Typed direct-envelope converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'typed direct-envelope title layout row payload'));
        $t->true(!str_contains($encoded, 'typed direct-envelope layout payload'));
        $t->true(!str_contains($encoded, 'typed direct-envelope layout envelope payload'));
        $t->true(!str_contains($encoded, 'typed direct-envelope order row payload'));
        $t->true(!str_contains($encoded, 'typed direct-envelope order payload'));
        $t->true(!str_contains($encoded, 'typed direct-envelope order envelope payload'));
    },
    'unwraps explicit pdftext artifact envelopes before selected layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(5200, [
                    ['text' => 'Explicit pdftext artifact cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(5201, [
                    ['text' => 'Second explicit pdftext artifact column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First explicit pdftext artifact column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
                $pdftextLinesPage(5202, [
                    ['text' => 'Explicit pdftext artifact appendix should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['source' => 'cached order pdftext envelope'],
                    'pdftext' => [
                        'pages' => [
                            [
                                'page' => 5200,
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ],
                                'raw_payload' => 'explicit pdftext cover order payload must stay hidden',
                            ],
                            [
                                'page' => 5201,
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ],
                                'raw_payload' => 'explicit pdftext selected order payload must stay hidden',
                            ],
                            [
                                'page' => 5202,
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ],
                                'raw_payload' => 'explicit pdftext appendix order payload must stay hidden',
                            ],
                        ],
                    ],
                    'raw_payload' => 'explicit pdftext order envelope payload must stay hidden',
                ],
            ],
            orderImages: [
                [
                    'pdftext' => [
                        'pages' => [
                            ['page' => 5200, 'image' => 'explicit-pdftext-cover-order-render'],
                            ['page' => 5201, 'image' => 'explicit-pdftext-selected-order-render'],
                            ['page' => 5202, 'image' => 'explicit-pdftext-appendix-order-render'],
                        ],
                    ],
                    'raw_payload' => 'explicit pdftext order image envelope payload must stay hidden',
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(5201, $result['pages'][0]['pnum']);
        $t->same(['First explicit pdftext artifact column', 'Second explicit pdftext artifact column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First explicit pdftext artifact column Second explicit pdftext artifact column', $blocks[0]['text']);
        $t->same(5201, $order['page'] ?? null);
        $t->true(!array_key_exists('pdftext', $order));
        $t->true(!str_contains($encoded, 'Explicit pdftext artifact cover should stay skipped'));
        $t->true(!str_contains($encoded, 'Explicit pdftext artifact appendix should stay skipped'));
        $t->true(!str_contains($encoded, 'explicit pdftext cover order payload'));
        $t->true(!str_contains($encoded, 'explicit pdftext selected order payload'));
        $t->true(!str_contains($encoded, 'explicit pdftext appendix order payload'));
        $t->true(!str_contains($encoded, 'explicit pdftext order envelope payload'));
        $t->true(!str_contains($encoded, 'explicit pdftext order image envelope payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'unwraps explicit pdftext layout and order artifact envelopes for WordPress imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-explicit-pdftext-artifact-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% explicit pdftext artifact envelope layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(5210, [
                        ['text' => 'Explicit pdftext converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(5211, [
                        ['text' => 'Second converter explicit pdftext body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter explicit pdftext heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                    $pdftextLinesPage(5212, [
                        ['text' => 'Explicit pdftext converter appendix should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [[
                        'pdftext' => [
                            'pages' => [
                                ['page' => 5210, 'image' => 'explicit-pdftext-cover-layout-render'],
                                ['page' => 5211, 'image' => 'explicit-pdftext-selected-layout-render'],
                                ['page' => 5212, 'image' => 'explicit-pdftext-appendix-layout-render'],
                            ],
                        ],
                        'raw_payload' => 'explicit pdftext layout image envelope payload must stay hidden',
                    ]],
                    'layout_results' => [[
                        'metadata' => ['source' => 'cached layout pdftext envelope'],
                        'pdftext' => [
                            'pages' => [
                                [
                                    'page' => 5210,
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                    ],
                                    'raw_payload' => 'explicit pdftext cover layout payload must stay hidden',
                                ],
                                [
                                    'page' => 5211,
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                    ],
                                    'raw_payload' => 'explicit pdftext selected layout payload must stay hidden',
                                ],
                                [
                                    'page' => 5212,
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                    ],
                                    'raw_payload' => 'explicit pdftext appendix layout payload must stay hidden',
                                ],
                            ],
                        ],
                        'raw_payload' => 'explicit pdftext layout envelope payload must stay hidden',
                    ]],
                    'order_images' => [[
                        'pdftext' => [
                            'pages' => [
                                ['page' => 5210, 'image' => 'explicit-pdftext-cover-order-render'],
                                ['page' => 5211, 'image' => 'explicit-pdftext-selected-order-render'],
                                ['page' => 5212, 'image' => 'explicit-pdftext-appendix-order-render'],
                            ],
                        ],
                        'raw_payload' => 'explicit pdftext order image envelope payload must stay hidden',
                    ]],
                    'order_results' => [[
                        'metadata' => ['source' => 'cached order pdftext envelope'],
                        'pdftext' => [
                            'pages' => [
                                [
                                    'page' => 5210,
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ],
                                    'raw_payload' => 'explicit pdftext cover order payload must stay hidden',
                                ],
                                [
                                    'page' => 5211,
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ],
                                    'raw_payload' => 'explicit pdftext selected order payload must stay hidden',
                                ],
                                [
                                    'page' => 5212,
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ],
                                    'raw_payload' => 'explicit pdftext appendix order payload must stay hidden',
                                ],
                            ],
                        ],
                        'raw_payload' => 'explicit pdftext order envelope payload must stay hidden',
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
        $t->contains('# First Converter Explicit Pdftext Heading.', $text);
        $t->contains('Second converter explicit pdftext body.', $text);
        $t->true(strpos($text, '# First Converter Explicit Pdftext Heading.') < strpos($text, 'Second converter explicit pdftext body.'));
        $t->true(!str_contains($text, 'Explicit pdftext converter cover should stay skipped.'));
        $t->true(!str_contains($text, 'Explicit pdftext converter appendix should stay skipped.'));
        $t->true(!str_contains($encoded, 'explicit pdftext cover layout payload'));
        $t->true(!str_contains($encoded, 'explicit pdftext selected layout payload'));
        $t->true(!str_contains($encoded, 'explicit pdftext appendix layout payload'));
        $t->true(!str_contains($encoded, 'explicit pdftext layout envelope payload'));
        $t->true(!str_contains($encoded, 'explicit pdftext layout image envelope payload'));
        $t->true(!str_contains($encoded, 'explicit pdftext cover order payload'));
        $t->true(!str_contains($encoded, 'explicit pdftext selected order payload'));
        $t->true(!str_contains($encoded, 'explicit pdftext appendix order payload'));
        $t->true(!str_contains($encoded, 'explicit pdftext order envelope payload'));
        $t->true(!str_contains($encoded, 'explicit pdftext order image envelope payload'));
    },
    'unwraps direct pdftext order payload envelopes after selected page assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(5220, [
                    ['text' => 'Direct pdftext order cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(5221, [
                    ['text' => 'Second direct pdftext order column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First direct pdftext order column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
                $pdftextLinesPage(5222, [
                    ['text' => 'Direct pdftext order appendix should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['document_page' => 5221],
                    'pdftext' => [
                        'page' => 6221,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                        'raw_payload' => 'direct pdftext selected order payload must stay hidden',
                    ],
                    'raw_payload' => 'direct pdftext order envelope payload must stay hidden',
                ],
            ],
            orderImages: [
                ['metadata' => ['document_page' => 5221], 'image' => 'direct-pdftext-selected-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(5221, $result['pages'][0]['pnum']);
        $t->same(['First direct pdftext order column', 'Second direct pdftext order column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First direct pdftext order column Second direct pdftext order column', $blocks[0]['text']);
        $t->same([0.0, 0.0, 612.0, 792.0], $order['image_bbox'] ?? null);
        $t->same(2, count($order['bboxes'] ?? []));
        $t->same(5221, $order['document_page'] ?? null);
        $t->true(!array_key_exists('pdftext', $order));
        $t->true(!str_contains($encoded, 'Direct pdftext order cover should stay skipped'));
        $t->true(!str_contains($encoded, 'Direct pdftext order appendix should stay skipped'));
        $t->true(!str_contains($encoded, 'direct pdftext selected order payload'));
        $t->true(!str_contains($encoded, 'direct pdftext order envelope payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'unwraps direct pdftext layout and order payload envelopes for WordPress imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-direct-pdftext-payload-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% direct pdftext payload layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(5230, [
                        ['text' => 'Direct pdftext payload cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(5231, [
                        ['text' => 'Second converter direct pdftext payload body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter direct pdftext payload heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                    $pdftextLinesPage(5232, [
                        ['text' => 'Direct pdftext payload appendix should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['metadata' => ['document_page' => 5231], 'image' => 'direct-pdftext-selected-layout-render'],
                    ],
                    'layout_results' => [[
                        'metadata' => ['document_page' => 5231],
                        'pdftext' => [
                            'page' => 6231,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'direct pdftext selected layout payload must stay hidden',
                        ],
                        'raw_payload' => 'direct pdftext layout envelope payload must stay hidden',
                    ]],
                    'order_images' => [
                        ['metadata' => ['document_page' => 5231], 'image' => 'direct-pdftext-selected-order-render'],
                    ],
                    'order_results' => [[
                        'metadata' => ['document_page' => 5231],
                        'pdftext' => [
                            'page' => 6231,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'direct pdftext selected order payload must stay hidden',
                        ],
                        'raw_payload' => 'direct pdftext order envelope payload must stay hidden',
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
        $t->contains('# First Converter Direct Pdftext Payload Heading.', $text);
        $t->contains('Second converter direct pdftext payload body.', $text);
        $t->true(strpos($text, '# First Converter Direct Pdftext Payload Heading.') < strpos($text, 'Second converter direct pdftext payload body.'));
        $t->true(!str_contains($text, 'Direct pdftext payload cover should stay skipped.'));
        $t->true(!str_contains($text, 'Direct pdftext payload appendix should stay skipped.'));
        $t->true(!str_contains($encoded, 'direct pdftext selected layout payload'));
        $t->true(!str_contains($encoded, 'direct pdftext layout envelope payload'));
        $t->true(!str_contains($encoded, 'direct pdftext selected order payload'));
        $t->true(!str_contains($encoded, 'direct pdftext order envelope payload'));
    },
    'unwraps keyed direct payload envelopes before selected pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(5300, [
                    ['text' => 'Keyed direct payload cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(5301, [
                    ['text' => 'Second keyed direct payload column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First keyed direct payload column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 5301,
                    'dictionary_output' => [
                        '5301' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'keyed direct left row payload must stay hidden'],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'keyed direct order payload must stay hidden',
                        ],
                    ],
                    'raw_payload' => 'keyed direct envelope payload must stay hidden',
                ],
            ],
            orderImages: [
                ['page' => 5301, 'image' => 'keyed-direct-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(5301, $result['pages'][0]['pnum']);
        $t->same(['First keyed direct payload column', 'Second keyed direct payload column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First keyed direct payload column Second keyed direct payload column', $blocks[0]['text']);
        $t->same(5301, $order['page'] ?? null);
        $t->same([0.0, 0.0, 612.0, 792.0], $order['image_bbox'] ?? null);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('dictionary_output', $order));
        $t->true(!str_contains($encoded, 'Keyed direct payload cover skipped'));
        $t->true(!str_contains($encoded, 'keyed direct order payload'));
        $t->true(!str_contains($encoded, 'keyed direct left row payload'));
        $t->true(!str_contains($encoded, 'keyed direct envelope payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'unwraps keyed direct layout and order payload envelopes for WordPress imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-keyed-direct-payload-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% keyed direct envelope payload pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(5310, [
                        ['text' => 'Keyed direct payload converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(5311, [
                        ['text' => 'Second converter keyed direct payload body.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                        ['text' => 'First converter keyed direct payload heading.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['page' => 5311, 'image' => 'keyed-direct-layout-render'],
                    ],
                    'layout_results' => [[
                        'page' => 5311,
                        'pages' => [
                            '5311' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                    ['label' => 'Title', 'bbox' => [318.0, 92.0, 570.0, 150.0], 'raw_payload' => 'keyed direct title layout row payload must stay hidden'],
                                ],
                                'raw_payload' => 'keyed direct layout payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'keyed direct layout envelope payload must stay hidden',
                    ]],
                    'order_images' => [
                        ['page' => 5311, 'image' => 'keyed-direct-order-render'],
                    ],
                    'order_results' => [[
                        'page' => 5311,
                        'pdftext' => [
                            '5311' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'keyed direct order row payload must stay hidden'],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ],
                                'raw_payload' => 'keyed direct order payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'keyed direct order envelope payload must stay hidden',
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
        $t->contains('# First Converter Keyed Direct Payload Heading.', $text);
        $t->contains('Second converter keyed direct payload body.', $text);
        $t->true(strpos($text, '# First Converter Keyed Direct Payload Heading.') < strpos($text, 'Second converter keyed direct payload body.'));
        $t->true(!str_contains($text, 'Keyed direct payload converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'keyed direct title layout row payload'));
        $t->true(!str_contains($encoded, 'keyed direct layout payload'));
        $t->true(!str_contains($encoded, 'keyed direct layout envelope payload'));
        $t->true(!str_contains($encoded, 'keyed direct order row payload'));
        $t->true(!str_contains($encoded, 'keyed direct order payload'));
        $t->true(!str_contains($encoded, 'keyed direct order envelope payload'));
    },
    'uses source-page keyed dictionary-output maps before selected pdftext order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(5400, [
                    ['text' => 'Source-key map cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(5401, [
                    ['text' => 'Second source-key map column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First source-key map column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'dictionary_output' => [
                        '5401' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'source-key selected left row payload must stay hidden'],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'source-key selected order payload must stay hidden',
                        ],
                        '5400' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'source-key stale order payload must stay hidden',
                        ],
                    ],
                    'raw_payload' => 'source-key dictionary-output wrapper payload must stay hidden',
                ],
            ],
            orderImages: [
                [
                    'dictionary_output' => [
                        '5401' => ['image' => 'source-key-selected-order-render'],
                        '5400' => ['image' => 'source-key-stale-order-render'],
                    ],
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(5401, $result['pages'][0]['pnum']);
        $t->same(['First source-key map column', 'Second source-key map column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First source-key map column Second source-key map column', $blocks[0]['text']);
        $t->same([0.0, 0.0, 612.0, 792.0], $order['image_bbox'] ?? null);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('dictionary_output', $order));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'Source-key map cover should stay skipped'));
        $t->true(!str_contains($encoded, 'source-key selected order payload'));
        $t->true(!str_contains($encoded, 'source-key selected left row payload'));
        $t->true(!str_contains($encoded, 'source-key stale order payload'));
        $t->true(!str_contains($encoded, 'source-key dictionary-output wrapper payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses nested pdftext pages map keys before selected pdftext order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(5700, [
                    ['text' => 'Nested pdftext pages cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(5701, [
                    ['text' => 'Second nested pdftext pages column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First nested pdftext pages column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'pdftext' => [
                        'pages' => [
                            '5701' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'nested selected pdftext pages row payload must stay hidden'],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ],
                                'raw_payload' => 'nested selected pdftext pages order payload must stay hidden',
                            ],
                            '5700' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ],
                                'raw_payload' => 'nested stale pdftext pages order payload must stay hidden',
                            ],
                        ],
                    ],
                    'raw_payload' => 'nested pdftext pages order wrapper payload must stay hidden',
                ],
            ],
            orderImages: [
                [
                    'pdftext' => [
                        'pages' => [
                            '5701' => ['image' => 'nested-pdftext-pages-selected-order-render'],
                            '5700' => ['image' => 'nested-pdftext-pages-stale-order-render'],
                        ],
                    ],
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(5701, $result['pages'][0]['pnum']);
        $t->same(['First nested pdftext pages column', 'Second nested pdftext pages column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First nested pdftext pages column Second nested pdftext pages column', $blocks[0]['text']);
        $t->same([0.0, 0.0, 612.0, 792.0], $order['image_bbox'] ?? null);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('pdftext', $order));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'Nested pdftext pages cover should stay skipped'));
        $t->true(!str_contains($encoded, 'nested selected pdftext pages row payload'));
        $t->true(!str_contains($encoded, 'nested selected pdftext pages order payload'));
        $t->true(!str_contains($encoded, 'nested stale pdftext pages order payload'));
        $t->true(!str_contains($encoded, 'nested pdftext pages order wrapper payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses nested pdftext pages map keys for WordPress layout and order imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-nested-pdftext-pages-map-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% nested pdftext pages map layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(5710, [
                        ['text' => 'Nested pdftext pages converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(5711, [
                        ['text' => 'Second converter nested pdftext pages body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter nested pdftext pages heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [[
                        'pdftext' => [
                            'pages' => [
                                '5711' => ['image' => 'nested-pdftext-pages-selected-layout-render'],
                                '5710' => ['image' => 'nested-pdftext-pages-stale-layout-render'],
                            ],
                        ],
                        'raw_payload' => 'nested pdftext pages image wrapper payload must stay hidden',
                    ]],
                    'layout_results' => [[
                        'pdftext' => [
                            'pages' => [
                                '5711' => [
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'nested selected layout title payload must stay hidden'],
                                        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                    ],
                                    'raw_payload' => 'nested selected layout payload must stay hidden',
                                ],
                                '5710' => [
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                        ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                    ],
                                    'raw_payload' => 'nested stale layout payload must stay hidden',
                                ],
                            ],
                        ],
                        'raw_payload' => 'nested pdftext pages layout wrapper payload must stay hidden',
                    ]],
                    'order_images' => [[
                        'pdftext' => [
                            'pages' => [
                                '5711' => ['image' => 'nested-pdftext-pages-selected-order-render'],
                                '5710' => ['image' => 'nested-pdftext-pages-stale-order-render'],
                            ],
                        ],
                        'raw_payload' => 'nested pdftext pages order image wrapper payload must stay hidden',
                    ]],
                    'order_results' => [[
                        'pdftext' => [
                            'pages' => [
                                '5711' => [
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'nested selected order row payload must stay hidden'],
                                    ],
                                    'raw_payload' => 'nested selected order payload must stay hidden',
                                ],
                                '5710' => [
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ],
                                    'raw_payload' => 'nested stale order payload must stay hidden',
                                ],
                            ],
                        ],
                        'raw_payload' => 'nested pdftext pages order wrapper payload must stay hidden',
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
        $t->contains('# First Converter Nested Pdftext Pages Heading.', $text);
        $t->contains('Second converter nested pdftext pages body.', $text);
        $t->true(strpos($text, '# First Converter Nested Pdftext Pages Heading.') < strpos($text, 'Second converter nested pdftext pages body.'));
        $t->true(!str_contains($text, 'Nested pdftext pages converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'nested pdftext pages image wrapper payload'));
        $t->true(!str_contains($encoded, 'nested selected layout title payload'));
        $t->true(!str_contains($encoded, 'nested selected layout payload'));
        $t->true(!str_contains($encoded, 'nested stale layout payload'));
        $t->true(!str_contains($encoded, 'nested pdftext pages layout wrapper payload'));
        $t->true(!str_contains($encoded, 'nested pdftext pages order image wrapper payload'));
        $t->true(!str_contains($encoded, 'nested selected order row payload'));
        $t->true(!str_contains($encoded, 'nested selected order payload'));
        $t->true(!str_contains($encoded, 'nested stale order payload'));
        $t->true(!str_contains($encoded, 'nested pdftext pages order wrapper payload'));
    },
    'rejects singleton source-key direct payload when key misses selected pdftext page' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(5500, [
                    ['text' => 'Singleton stale key cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(5501, [
                    ['text' => 'Second singleton key column remains source ordered', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First singleton key column has no trusted order', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'dictionary_output' => [
                        '5500' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'singleton stale key row payload must stay hidden'],
                            ],
                            'raw_payload' => 'singleton stale key order payload must stay hidden',
                        ],
                    ],
                    'raw_payload' => 'singleton stale key wrapper payload must stay hidden',
                ],
            ],
            orderImages: [
                [
                    'dictionary_output' => [
                        '5500' => ['image' => 'singleton-stale-key-order-render'],
                    ],
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(5501, $result['pages'][0]['pnum']);
        $t->same(['Second singleton key column remains source ordered', 'First singleton key column has no trusted order'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second singleton key column remains source ordered First singleton key column has no trusted order', $blocks[0]['text']);
        $t->same(null, $result['pages'][0]['order'] ?? null);
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'Singleton stale key cover should stay skipped'));
        $t->true(!str_contains($encoded, 'singleton stale key order payload'));
        $t->true(!str_contains($encoded, 'singleton stale key row payload'));
        $t->true(!str_contains($encoded, 'singleton stale key wrapper payload'));
        $t->same(0, $result['metadata']['order_plan']['image_count']);
        $t->same(0, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
    },
    'rejects singleton keyed layout and order payload mismatches for WordPress imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-singleton-key-mismatch-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% singleton keyed mismatch pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(5510, [
                        ['text' => 'Singleton keyed converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(5511, [
                        ['text' => 'Second converter singleton key column stays source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter singleton key has no supplied layout.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        [
                            'pages' => [
                                '5510' => ['image' => 'singleton-key-stale-layout-render'],
                            ],
                        ],
                    ],
                    'layout_results' => [[
                        'pages' => [
                            '5510' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'singleton stale layout title payload must stay hidden'],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'singleton stale layout payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'singleton stale layout wrapper payload must stay hidden',
                    ]],
                    'order_images' => [
                        [
                            'dictionary_output' => [
                                '5510' => ['image' => 'singleton-key-stale-order-render'],
                            ],
                        ],
                    ],
                    'order_results' => [[
                        'dictionary_output' => [
                            '5510' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'singleton stale order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'singleton stale order payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'singleton stale order wrapper payload must stay hidden',
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
        $t->same([], $result['metadata']['supplied_boundaries'] ?? null);
        $t->true(!array_key_exists('layout_plan', $result['metadata']));
        $t->true(!array_key_exists('order_plan', $result['metadata']));
        $t->contains('Second converter singleton key column stays source ordered.', $text);
        $t->contains('First converter singleton key has no supplied layout.', $text);
        $t->true(strpos($text, 'Second converter singleton key column stays source ordered.') < strpos($text, 'First converter singleton key has no supplied layout.'));
        $t->true(!str_contains($text, '# First Converter Singleton Key Has No Supplied Layout.'));
        $t->true(!str_contains($text, 'Singleton keyed converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'singleton stale layout title payload'));
        $t->true(!str_contains($encoded, 'singleton stale layout payload'));
        $t->true(!str_contains($encoded, 'singleton stale layout wrapper payload'));
        $t->true(!str_contains($encoded, 'singleton stale order row payload'));
        $t->true(!str_contains($encoded, 'singleton stale order payload'));
        $t->true(!str_contains($encoded, 'singleton stale order wrapper payload'));
    },
    'rejects stale direct payload keys under trusted outer metadata before pdftext order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(5800, [
                    ['text' => 'Inner-key conflict cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(5801, [
                    ['text' => 'Second inner-key conflict column stays source ordered', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First inner-key conflict column has no trusted order', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 5801,
                    'dictionary_output' => [
                        '5800' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'inner-key stale order row payload must stay hidden'],
                            ],
                            'raw_payload' => 'inner-key stale order payload must stay hidden',
                        ],
                    ],
                    'raw_payload' => 'inner-key outer selected order wrapper payload must stay hidden',
                ],
            ],
            orderImages: [
                [
                    'page' => 5801,
                    'dictionary_output' => [
                        '5800' => ['image' => 'inner-key-stale-order-render'],
                    ],
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(5801, $result['pages'][0]['pnum']);
        $t->same(['Second inner-key conflict column stays source ordered', 'First inner-key conflict column has no trusted order'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second inner-key conflict column stays source ordered First inner-key conflict column has no trusted order', $blocks[0]['text']);
        $t->same(null, $result['pages'][0]['order'] ?? null);
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'Inner-key conflict cover should stay skipped'));
        $t->true(!str_contains($encoded, 'inner-key stale order payload'));
        $t->true(!str_contains($encoded, 'inner-key stale order row payload'));
        $t->true(!str_contains($encoded, 'inner-key outer selected order wrapper payload'));
        $t->same(0, $result['metadata']['order_plan']['image_count']);
        $t->same(0, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
    },
    'rejects stale direct payload keys under trusted outer metadata for WordPress imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-inner-key-conflict-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% inner keyed conflict pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(5810, [
                        ['text' => 'Inner-key conflict converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(5811, [
                        ['text' => 'Second converter inner-key conflict column stays source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter inner-key conflict has no supplied layout.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        [
                            'page' => 5811,
                            'pages' => [
                                '5810' => ['image' => 'inner-key-stale-layout-render'],
                            ],
                        ],
                    ],
                    'layout_results' => [[
                        'page' => 5811,
                        'pages' => [
                            '5810' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'inner-key stale layout title payload must stay hidden'],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'inner-key stale layout payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'inner-key outer selected layout wrapper payload must stay hidden',
                    ]],
                    'order_images' => [
                        [
                            'page' => 5811,
                            'dictionary_output' => [
                                '5810' => ['image' => 'inner-key-stale-order-render'],
                            ],
                        ],
                    ],
                    'order_results' => [[
                        'page' => 5811,
                        'dictionary_output' => [
                            '5810' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'inner-key stale order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'inner-key stale order payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'inner-key outer selected order wrapper payload must stay hidden',
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
        $t->same([], $result['metadata']['supplied_boundaries'] ?? null);
        $t->true(!array_key_exists('layout_plan', $result['metadata']));
        $t->true(!array_key_exists('order_plan', $result['metadata']));
        $t->contains('Second converter inner-key conflict column stays source ordered.', $text);
        $t->contains('First converter inner-key conflict has no supplied layout.', $text);
        $t->true(strpos($text, 'Second converter inner-key conflict column stays source ordered.') < strpos($text, 'First converter inner-key conflict has no supplied layout.'));
        $t->true(!str_contains($text, '# First Converter Inner-Key Conflict Has No Supplied Layout.'));
        $t->true(!str_contains($text, 'Inner-key conflict converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'inner-key stale layout title payload'));
        $t->true(!str_contains($encoded, 'inner-key stale layout payload'));
        $t->true(!str_contains($encoded, 'inner-key outer selected layout wrapper payload'));
        $t->true(!str_contains($encoded, 'inner-key stale order row payload'));
        $t->true(!str_contains($encoded, 'inner-key stale order payload'));
        $t->true(!str_contains($encoded, 'inner-key outer selected order wrapper payload'));
    },
    'uses document and pdftext page-number aliases before selected pdftext order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(5600, [
                    ['text' => 'Page-number alias cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(5601, [
                    ['text' => 'Second page-number alias column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First page-number alias column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
                $pdftextLinesPage(5602, [
                    ['text' => 'Page-number alias appendix should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['document_page_number' => 5601],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'stale document page-number row payload must stay hidden'],
                    ],
                    'raw_payload' => 'stale document page-number order payload must stay hidden',
                ],
                [
                    'metadata' => ['pdftext_page_number' => 5602],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'selected pdftext page-number order payload must stay hidden',
                ],
            ],
            orderImages: [
                ['metadata' => ['document_page_number' => 5601], 'image' => 'stale-page-number-order-render'],
                ['metadata' => ['pdftext_page_number' => 5602], 'image' => 'selected-page-number-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(5601, $result['pages'][0]['pnum']);
        $t->same(['First page-number alias column', 'Second page-number alias column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First page-number alias column Second page-number alias column', $blocks[0]['text']);
        $t->same(5602, $order['pdftext_page_number'] ?? null);
        $t->true(!array_key_exists('document_page_number', $order), 'Stale document_page_number marker must not be preserved on the selected order result.');
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!str_contains($encoded, 'Page-number alias cover should stay skipped'));
        $t->true(!str_contains($encoded, 'Page-number alias appendix should stay skipped'));
        $t->true(!str_contains($encoded, 'stale document page-number order payload'));
        $t->true(!str_contains($encoded, 'stale document page-number row payload'));
        $t->true(!str_contains($encoded, 'selected pdftext page-number order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses source document and pdftext page-number aliases for WordPress supplied imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-page-number-alias-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% page-number alias pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(5610, [
                        ['text' => 'Page-number alias converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(5611, [
                        ['text' => 'Second converter page-number alias body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter page-number alias heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                    $pdftextLinesPage(5612, [
                        ['text' => 'Page-number alias converter appendix should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['source_page_number' => 5611, 'image' => 'source-page-number-stale-layout-render'],
                        ['metadata' => ['document_page_number' => 5612], 'image' => 'document-page-number-selected-layout-render'],
                    ],
                    'layout_results' => [
                        [
                            'source_page_number' => 5611,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'stale source page-number layout row payload must stay hidden'],
                            ],
                            'raw_payload' => 'stale source page-number layout payload must stay hidden',
                        ],
                        [
                            'metadata' => ['document_page_number' => 5612],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'selected document page-number layout payload must stay hidden',
                        ],
                    ],
                    'order_images' => [
                        ['source_page_number' => 5611, 'image' => 'source-page-number-stale-order-render'],
                        ['metadata' => ['pdftext_page_number' => 5612], 'image' => 'pdftext-page-number-selected-order-render'],
                    ],
                    'order_results' => [
                        [
                            'source_page_number' => 5611,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'stale source page-number order row payload must stay hidden'],
                            ],
                            'raw_payload' => 'stale source page-number order payload must stay hidden',
                        ],
                        [
                            'metadata' => ['pdftext_page_number' => 5612],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'selected pdftext page-number order payload must stay hidden',
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
        $t->contains('# First Converter Page-Number Alias Heading.', $text);
        $t->contains('Second converter page-number alias body.', $text);
        $t->true(strpos($text, '# First Converter Page-Number Alias Heading.') < strpos($text, 'Second converter page-number alias body.'));
        $t->true(!str_contains($text, 'Page-number alias converter cover should stay skipped.'));
        $t->true(!str_contains($text, 'Page-number alias converter appendix should stay skipped.'));
        $t->true(!str_contains($encoded, 'stale source page-number layout row payload'));
        $t->true(!str_contains($encoded, 'stale source page-number layout payload'));
        $t->true(!str_contains($encoded, 'selected document page-number layout payload'));
        $t->true(!str_contains($encoded, 'stale source page-number order row payload'));
        $t->true(!str_contains($encoded, 'stale source page-number order payload'));
        $t->true(!str_contains($encoded, 'selected pdftext page-number order payload'));
    },
];
