<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
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

$points = static fn (float $x1, float $y1, float $x2, float $y2): array => [
    ['x' => (string) $x1, 'y' => (string) $y1],
    ['x' => (string) $x2, 'y' => (string) $y1],
    ['x' => (string) $x2, 'y' => (string) $y2],
    ['x' => (string) $x1, 'y' => (string) $y2],
];

$flatQuad = static fn (float $x1, float $y1, float $x2, float $y2): array => [
    $x1,
    $y1,
    $x2,
    $y1,
    $x2,
    $y2,
    $x1,
    $y2,
];

return [
    'uses polygon alias order rows before selected pdftext layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage, $points, $flatQuad): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(6100, [
                    ['text' => 'Polygon alias order cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(6101, [
                    ['text' => 'Second polygon alias order column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First polygon alias order column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 6101,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        [
                            'position' => 1,
                            'points' => $points(60.0, 96.0, 290.0, 144.0),
                            'raw_payload' => 'points alias order row payload must stay hidden',
                        ],
                        [
                            'position' => 2,
                            'quad' => $flatQuad(318.0, 96.0, 570.0, 144.0),
                            'raw_payload' => 'quad alias order row payload must stay hidden',
                        ],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 6101, 'image' => 'polygon-alias-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(6101, $result['pages'][0]['pnum']);
        $t->same(['First polygon alias order column', 'Second polygon alias order column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First polygon alias order column Second polygon alias order column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $result['pages'][0]['order']['bboxes'] ?? []);
        $t->true(!str_contains($encoded, 'points alias order row payload'));
        $t->true(!str_contains($encoded, 'quad alias order row payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses polygon alias layout and order rows for WordPress supplied imports' => static function (TestRunner $t) use ($pdftextLinesPage, $points, $flatQuad): void {
        $path = sys_get_temp_dir() . '/markerpdf-polygon-alias-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% polygon alias pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(6200, [
                        ['text' => 'Polygon alias converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(6201, [
                        ['text' => 'Polygon alias converter body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'Polygon alias converter title', 'bbox' => [72.0, 48.0, 360.0, 68.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['page' => 6201, 'image' => 'polygon-alias-layout-render'],
                    ],
                    'layout_results' => [[
                        'page' => 6201,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            [
                                'label' => 'Title',
                                'vertices' => $points(60.0, 42.0, 370.0, 76.0),
                                'raw_payload' => 'vertices alias layout payload must stay hidden',
                            ],
                            [
                                'label' => 'Text',
                                'quadrilateral_points' => $points(318.0, 96.0, 570.0, 144.0),
                                'raw_payload' => 'quadrilateral points layout payload must stay hidden',
                            ],
                        ],
                    ]],
                    'order_images' => [
                        ['page' => 6201, 'image' => 'polygon-alias-order-render'],
                    ],
                    'order_results' => [[
                        'page' => 6201,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            [
                                'position' => 1,
                                'points' => $points(60.0, 42.0, 370.0, 76.0),
                                'raw_payload' => 'points alias order payload must stay hidden',
                            ],
                            [
                                'position' => 2,
                                'quad' => $flatQuad(318.0, 96.0, 570.0, 144.0),
                                'raw_payload' => 'flat quad alias order payload must stay hidden',
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
        $t->contains('# Polygon Alias Converter Title', $text);
        $t->contains('Polygon alias converter body.', $text);
        $t->true(strpos($text, '# Polygon Alias Converter Title') < strpos($text, 'Polygon alias converter body.'));
        $t->true(!str_contains($text, 'Polygon alias converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'vertices alias layout payload'));
        $t->true(!str_contains($encoded, 'quadrilateral points layout payload'));
        $t->true(!str_contains($encoded, 'points alias order payload'));
        $t->true(!str_contains($encoded, 'flat quad alias order payload'));
    },
];
