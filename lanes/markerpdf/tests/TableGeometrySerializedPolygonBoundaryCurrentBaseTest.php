<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableFormatter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_serialized_polygon_boundary_page(array $lines): array
{
    return [
        'page' => 0,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'rotation' => 0,
        'blocks' => [[
            'lines' => array_map(
                static fn (array $line): array => [
                    'bbox' => $line['bbox'],
                    'spans' => [[
                        'text' => $line['text'],
                        'bbox' => $line['bbox'],
                        'font' => [
                            'name' => $line['font'] ?? 'Times-Roman',
                            'flags' => 0,
                            'weight' => $line['weight'] ?? 400,
                            'size' => $line['size'] ?? 12,
                        ],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
}

function markerpdf_serialized_polygon_boundary_recognized_table(): array
{
    return [
        'rows' => [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 200.0, 30.0]],
            ['row_id' => 1, 'bbox' => [0.0, 38.0, 200.0, 70.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 96.0, 72.0]],
            ['col_id' => 1, 'bbox' => [98.0, 0.0, 200.0, 72.0]],
        ],
    ];
}

function markerpdf_serialized_polygon_boundary_detector_cells(): array
{
    return [
        ['bbox' => [0.0, 0.0, 90.0, 24.0], 'text' => null],
        ['bbox' => [100.0, 0.0, 190.0, 24.0], 'text' => null],
        ['bbox' => [0.0, 40.0, 90.0, 64.0], 'text' => null],
        ['bbox' => [100.0, 40.0, 190.0, 64.0], 'text' => null],
    ];
}

function markerpdf_serialized_polygon_boundary_ocr_lines(): array
{
    return [
        'text_lines' => [
            [
                'text' => 'Feature',
                'bbox' => [104.0, 5.0, 188.0, 20.0],
                'polygon' => [
                    ['x' => '2.0', 'y' => '4.0'],
                    ['x' => '88.0', 'y' => '4.0'],
                    ['x' => '88.0', 'y' => '20.0'],
                    ['x' => '2.0', 'y' => '20.0'],
                ],
            ],
            [
                'text' => 'Status',
                'bbox' => [4.0, 5.0, 88.0, 20.0],
                'polygon' => ['102.0', '4.0', '188.0', '4.0', '188.0', '20.0', '102.0', '20.0'],
            ],
            [
                'text' => 'Images',
                'bbox' => [104.0, 45.0, 188.0, 60.0],
                'polygon' => [
                    ['x' => 2.0, 'y' => 44.0],
                    ['x' => 88.0, 'y' => 44.0],
                    ['x' => 88.0, 'y' => 60.0],
                    ['x' => 2.0, 'y' => 60.0],
                ],
            ],
            [
                'text' => 'Ready',
                'bbox' => [4.0, 45.0, 88.0, 60.0],
                'polygon' => [102.0, 44.0, 188.0, 44.0, 188.0, 60.0, 102.0, 60.0],
            ],
        ],
    ];
}

return [
    'normalizes serialized polygon point dictionaries and flat coordinate arrays before OCR assignment' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $recognized = $recognizer->recognizeTables(
            [markerpdf_serialized_polygon_boundary_detector_cells()],
            [true],
            [markerpdf_serialized_polygon_boundary_recognized_table()],
            [markerpdf_serialized_polygon_boundary_ocr_lines()]
        );
        $formatted = $recognizer->formatRecognizedTables($recognized, [['width' => 200, 'height' => 72]]);

        $t->same(['Feature', 'Status', 'Images', 'Ready'], array_column($recognized[0]['cells'], 'text'));
        $t->same([], $recognized[0]['ocr_grid_border_conflicts'] ?? []);
        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0] ?? '');
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0] ?? '');
    },
    'normalizes serialized table layout polygons before crop planning' => static function (TestRunner $t): void {
        $planned = (new TableFormatter())->getTableBoxes(
            [[
                'pnum' => 0,
                'bbox' => [0.0, 0.0, 612.0, 792.0],
                'layout' => [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        [
                            'label' => 'Table',
                            'polygon' => [
                                ['x' => '72.0', 'y' => '150.0'],
                                ['x' => '312.0', 'y' => '150.0'],
                                ['x' => '312.0', 'y' => '230.0'],
                                ['x' => '72.0', 'y' => '230.0'],
                            ],
                        ],
                        [
                            'label' => 'Table',
                            'polygon' => ['350.0', '150.0', '470.0', '150.0', '470.0', '230.0', '350.0', '230.0'],
                        ],
                    ],
                ],
                'blocks' => [],
            ]],
            [['blocks' => []]],
            [['width' => 612, 'height' => 792]]
        );

        $t->same([2], $planned['table_counts']);
        $t->same([[72.0, 150.0, 312.0, 230.0], [350.0, 150.0, 470.0, 230.0]], $planned['table_bboxes']);
        $t->same([240.0, 120.0], array_map(static fn (array $image): float => $image['crop_width'], $planned['table_images']));
        $t->same([80.0, 80.0], array_map(static fn (array $image): float => $image['crop_height'], $planned['table_images']));
    },
    'surfaces serialized polygon table geometry through supplied WordPress conversion' => static function (TestRunner $t): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-serialized-polygon-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table serialized polygon boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_serialized_polygon_boundary_page([
                        ['text' => 'Serialized polygon table boundary', 'bbox' => [72.0, 48.0, 500.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale serialized polygon table text should be replaced.', 'bbox' => [72.0, 176.0, 500.0, 196.0]],
                        ['text' => 'After serialized polygon review.', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 500.0, 68.0]],
                            [
                                'label' => 'Table',
                                'polygon' => [
                                    ['x' => '72.0', 'y' => '150.0'],
                                    ['x' => '430.0', 'y' => '150.0'],
                                    ['x' => '430.0', 'y' => '230.0'],
                                    ['x' => '72.0', 'y' => '230.0'],
                                ],
                            ],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_serialized_polygon_boundary_recognized_table()],
                    'table_detector_cells' => [markerpdf_serialized_polygon_boundary_detector_cells()],
                    'table_ocr_text_lines' => [markerpdf_serialized_polygon_boundary_ocr_lines()],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                    'ocr_all_pages' => true,
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');

            $t->contains('# Serialized Polygon Table Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After serialized polygon review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale serialized polygon table text should be replaced.'));
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([true], $result['metadata']['table_needs_ocr']);
            $t->same([4], $result['metadata']['table_cell_counts']);
            $t->same([[72.0, 150.0, 430.0, 230.0]], $result['metadata']['table_plan']['table_bboxes'] ?? null);
            $t->true(!isset($result['metadata']['table_ocr_grid_border_conflicts']));
            $t->same(false, $result['metadata']['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
