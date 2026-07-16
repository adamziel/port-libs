<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$pdftextCoordinateOrderPage = static function (int $page, array $lines): array {
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

$xxyyBbox = static fn (float $x1, float $y1, float $x2, float $y2): array => [$x1, $x2, $y1, $y2];

return [
    'normalizes supplied order coordinate-order rows before pdftext dictionary assignment' => static function (TestRunner $t) use ($pdftextCoordinateOrderPage, $xxyyBbox): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextCoordinateOrderPage(3000, [
                    ['text' => 'Coordinate order cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextCoordinateOrderPage(3001, [
                    ['text' => 'Second coordinate-order column.', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First coordinate-order column.', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 3001,
                    'image_bbox' => $xxyyBbox(0.0, 0.0, 612.0, 792.0),
                    'image_bbox_order' => 'x1_x2_y1_y2',
                    'bbox_order' => 'x1_x2_y1_y2',
                    'bboxes' => [
                        ['position' => 1, 'bbox' => $xxyyBbox(60.0, 96.0, 290.0, 144.0)],
                        ['position' => 2, 'bbox' => $xxyyBbox(318.0, 96.0, 570.0, 144.0)],
                        ['position' => 0, 'bbox' => ['bad', 500.0, 40.0, 520.0], 'raw_payload' => 'stale malformed coordinate-order bbox payload'],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 3001, 'image' => 'coordinate-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(3001, $result['pages'][0]['pnum']);
        $t->same(['First coordinate-order column.', 'Second coordinate-order column.'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same([0.0, 0.0, 612.0, 792.0], $order['image_bbox'] ?? null);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? null);
        $t->true(!str_contains($encoded, 'stale malformed coordinate-order bbox payload'));
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'normalizes supplied layout coordinate-order rows before WordPress pdftext import' => static function (TestRunner $t) use ($pdftextCoordinateOrderPage, $xxyyBbox): void {
        $path = sys_get_temp_dir() . '/markerpdf-layout-coordinate-order-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% coordinate order layout boundary current-base fixture\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextCoordinateOrderPage(3010, [
                        ['text' => 'Coordinate order cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextCoordinateOrderPage(3011, [
                        ['text' => 'Coordinate Order Title', 'bbox' => [72.0, 48.0, 360.0, 68.0]],
                        ['text' => 'Coordinate order body remains a paragraph.', 'bbox' => [72.0, 112.0, 480.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['page' => 3011, 'image' => 'coordinate-order-layout-render'],
                    ],
                    'layout_results' => [[
                        'page' => 3011,
                        'image_bbox' => $xxyyBbox(0.0, 0.0, 612.0, 792.0),
                        'image_bbox_order' => 'x1_x2_y1_y2',
                        'bbox_order' => 'x1_x2_y1_y2',
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => $xxyyBbox(60.0, 42.0, 370.0, 76.0)],
                            ['label' => 'Text', 'bbox' => $xxyyBbox(60.0, 100.0, 490.0, 140.0)],
                        ],
                        'raw_payload' => 'coordinate order layout payload must stay out of WordPress metadata',
                    ]],
                    'order_images' => [
                        ['page' => 3011, 'image' => 'coordinate-order-layout-order-render'],
                    ],
                    'order_results' => [[
                        'page' => 3011,
                        'image_bbox' => $xxyyBbox(0.0, 0.0, 612.0, 792.0),
                        'image_bbox_order' => 'x1_x2_y1_y2',
                        'bbox_order' => 'x1_x2_y1_y2',
                        'bboxes' => [
                            ['position' => 1, 'bbox' => $xxyyBbox(60.0, 42.0, 370.0, 76.0)],
                            ['position' => 2, 'bbox' => $xxyyBbox(60.0, 100.0, 490.0, 140.0)],
                        ],
                    ]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([1], $result['metadata']['page_range'] ?? null);
        $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('# Coordinate Order Title', $text);
        $t->contains('Coordinate order body remains a paragraph.', $text);
        $t->true(strpos($text, '# Coordinate Order Title') < strpos($text, 'Coordinate order body remains a paragraph.'));
        $t->true(!str_contains($text, 'Coordinate order cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'coordinate order layout payload must stay out'));
    },
];
