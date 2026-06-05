<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableFormatter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_crop_polygon_stale_bbox_points(float $x1, float $y1, float $x2, float $y2): array
{
    return [
        ['x' => (string) $x1, 'y' => (string) $y1],
        ['x' => (string) $x2, 'y' => (string) $y1],
        ['x' => (string) $x2, 'y' => (string) $y2],
        ['x' => (string) $x1, 'y' => (string) $y2],
    ];
}

function markerpdf_crop_polygon_stale_bbox_page(array $lines): array
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

function markerpdf_crop_polygon_stale_bbox_layout_page(): array
{
    return [
        'pnum' => 0,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'layout' => [
            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
            'bboxes' => [
                [
                    'label' => 'Table',
                    'bbox' => [400.0, 300.0, 520.0, 340.0],
                    'polygon' => markerpdf_crop_polygon_stale_bbox_points(72.0, 150.0, 312.0, 230.0),
                ],
            ],
        ],
        'blocks' => [],
    ];
}

function markerpdf_crop_polygon_stale_bbox_recognized_table(): array
{
    return [
        'coordinate_space' => 'page_image',
        'bbox' => [400.0, 300.0, 520.0, 340.0],
        'polygon' => markerpdf_crop_polygon_stale_bbox_points(72.0, 150.0, 312.0, 230.0),
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'rows' => [
            ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
            ['row_id' => 1, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
            ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 270.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
            ['col_id' => 1, 'bbox' => [192.0, 150.0, 312.0, 230.0]],
            ['col_id' => 99, 'bbox' => [342.0, 150.0, 362.0, 230.0]],
        ],
        'cells' => [
            ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale polygon row', 'row_ids' => [99], 'col_ids' => [0]],
            ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale polygon column', 'row_ids' => [1], 'col_ids' => [99]],
        ],
    ];
}

return [
    'uses crop polygon instead of stale layout bbox before table crop planning' => static function (TestRunner $t): void {
        $planned = (new TableFormatter())->getTableBoxes(
            [markerpdf_crop_polygon_stale_bbox_layout_page()],
            [['blocks' => []]],
            [['width' => 612, 'height' => 792]]
        );

        $t->same([1], $planned['table_counts']);
        $t->same([[72.0, 150.0, 312.0, 230.0]], $planned['table_bboxes']);
        $t->same([72.0, 150.0, 312.0, 230.0], $planned['table_images'][0]['source_bbox']);
        $t->same([72.0, 150.0, 312.0, 230.0], $planned['table_images'][0]['highres_bbox']);
        $t->same(240.0, $planned['table_images'][0]['crop_width']);
        $t->same(80.0, $planned['table_images'][0]['crop_height']);
    },
    'uses saved table crop polygon instead of stale sidecar bbox before localization' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_crop_polygon_stale_bbox_recognized_table()],
            [['width' => 240, 'height' => 80]]
        );

        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $localized = $formatted['recognized_tables'][0] ?? [];
        $assignedTexts = array_column($formatted['assigned_cells'][0] ?? [], 'text');

        $t->same('translated_to_table_crop', $review['status'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $review['table_bbox'] ?? null);
        $t->same('polygon', $review['table_bbox_source'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $localized['cells'][0]['source_bbox'] ?? null);
        $t->same('bbox_array', $localized['cells'][0]['source_coordinate_source'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale polygon row', $assignedTexts, true));
        $t->true(!in_array('Stale polygon column', $assignedTexts, true));
        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0] ?? '');
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces crop polygon stale-bbox boundary through supplied WordPress conversion' => static function (TestRunner $t): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-crop-polygon-stale-bbox-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table crop polygon stale bbox boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_crop_polygon_stale_bbox_page([
                        ['text' => 'Table crop polygon stale bbox boundary', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale crop-bbox table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                        ['text' => 'After crop polygon stale bbox table.', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 520.0, 68.0]],
                            [
                                'label' => 'Table',
                                'bbox' => [400.0, 300.0, 520.0, 340.0],
                                'polygon' => markerpdf_crop_polygon_stale_bbox_points(72.0, 150.0, 312.0, 230.0),
                            ],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_crop_polygon_stale_bbox_recognized_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $coordinateReview = $result['metadata']['table_coordinate_space_reviews'][0] ?? [];
            $assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');

            $t->contains('# Table Crop Polygon Stale Bbox Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After crop polygon stale bbox table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale crop-bbox table line should be replaced.'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries'] ?? null);
            $t->same([[72.0, 150.0, 312.0, 230.0]], $result['metadata']['table_plan']['table_bboxes'] ?? null);
            $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same([72.0, 150.0, 312.0, 230.0], $coordinateReview['table_bbox'] ?? null);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->true(!in_array('Stale polygon row', $assignedTexts, true));
            $t->true(!in_array('Stale polygon column', $assignedTexts, true));
            $t->same(false, $result['metadata']['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
