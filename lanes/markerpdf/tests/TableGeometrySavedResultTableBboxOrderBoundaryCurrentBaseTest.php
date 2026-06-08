<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_saved_result_table_bbox_order_boundary_xxyy(float $x1, float $y1, float $x2, float $y2): array
{
    return [$x1, $x2, $y1, $y2];
}

function markerpdf_saved_result_table_bbox_order_boundary_page(array $lines): array
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

function markerpdf_saved_result_table_bbox_order_boundary_table(): array
{
    return [
        'pnum' => 0,
        'tnum' => 0,
        'coordinate_space' => 'page_image',
        'table_bbox' => [72.0, 150.0, 312.0, 230.0],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'rows' => [
            ['row_id' => 10, 'bbox' => markerpdf_saved_result_table_bbox_order_boundary_xxyy(72.0, 150.0, 312.0, 182.0)],
            ['row_id' => 11, 'bbox' => markerpdf_saved_result_table_bbox_order_boundary_xxyy(72.0, 190.0, 312.0, 220.0)],
            ['row_id' => 99, 'bbox' => markerpdf_saved_result_table_bbox_order_boundary_xxyy(72.0, 250.0, 312.0, 268.0)],
        ],
        'cols' => [
            ['col_id' => 20, 'bbox' => markerpdf_saved_result_table_bbox_order_boundary_xxyy(72.0, 150.0, 172.0, 230.0)],
            ['col_id' => 21, 'bbox' => markerpdf_saved_result_table_bbox_order_boundary_xxyy(192.0, 150.0, 312.0, 230.0)],
            ['col_id' => 99, 'bbox' => markerpdf_saved_result_table_bbox_order_boundary_xxyy(342.0, 150.0, 362.0, 230.0)],
        ],
        'cells' => [
            ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [20]],
            ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [21]],
            ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [11], 'col_ids' => [20]],
            ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [11], 'col_ids' => [21]],
            ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale alias row', 'row_ids' => [99], 'col_ids' => [20]],
            ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale alias col', 'row_ids' => [11], 'col_ids' => [99]],
        ],
    ];
}

function markerpdf_saved_result_table_bbox_order_boundary_convert(): array
{
    $path = sys_get_temp_dir() . '/markerpdf-table-saved-result-table-bbox-order-' . bin2hex(random_bytes(4)) . '.pdf';
    file_put_contents($path, "%PDF-1.4\n% saved result table_bbox order boundary fixture\n%%EOF");

    try {
        return (new SuppliedDocumentConverter())->convert(
            $path,
            [
                markerpdf_saved_result_table_bbox_order_boundary_page([
                    ['text' => 'Saved Result Table Bbox Order Boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                    ['text' => 'Stale saved-result table bbox line should be replaced.', 'bbox' => [82.0, 176.0, 360.0, 196.0]],
                    ['text' => 'After saved result table bbox order.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
                ]),
            ],
            [
                'metadata' => ['languages' => ['English']],
                'layout_results' => [[
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Title', 'bbox' => [72.0, 48.0, 560.0, 68.0]],
                        ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                        ['label' => 'Text', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
                    ],
                ]],
                'recognized_tables' => [markerpdf_saved_result_table_bbox_order_boundary_table()],
                'table_text_lines' => [['blocks' => []]],
                'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
            ],
            new MarkerSettings(['EXTRACT_IMAGES' => false])
        );
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
}

return [
    'defaults direct saved tabled row column bands to x1 x2 y1 y2 when crop uses table bbox alias' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_saved_result_table_bbox_order_boundary_table()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $coordinateReview = $formatted['coordinate_space_reviews'][0] ?? [];
        $cropReview = $formatted['assigned_crop_boundary_reviews'][0] ?? [];
        $assignedTexts = array_column($formatted['assigned_cells'][0] ?? [], 'text');
        $gridReview = $recognizer->spanningGridReview(
            $formatted['assigned_cells'][0] ?? [],
            $localized['rows'] ?? [],
            $localized['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );

        $t->same('table_recognition_coordinate_space_boundary', $coordinateReview['review_target'] ?? null);
        $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
        $t->same('table_bbox', $coordinateReview['table_bbox_source'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $coordinateReview['table_bbox'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $coordinateReview['translation'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([0.0, 40.0, 240.0, 70.0], $localized['rows'][1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 100.0, 80.0], $localized['cols'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $localized['rows'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $localized['cols'][0]['source_coordinate_source'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same([130.0, 45.0, 230.0, 65.0], $localized['cells'][3]['bbox'] ?? null);
        $t->same(6, $cropReview['cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['geometry_boundary_review']['col_bands'][0]['source_coordinate_source'] ?? null);
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces direct saved tabled table bbox alias row column order through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $result = markerpdf_saved_result_table_bbox_order_boundary_convert();
        $metadata = $result['metadata'];
        $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
        $cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
        $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
        $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

        $t->contains('# Saved Result Table Bbox Order Boundary', $result['text']);
        $t->contains('| Feature | Status |', $result['text']);
        $t->contains('| Images  | Ready  |', $result['text']);
        $t->contains('After saved result table bbox order.', $result['text']);
        $t->true(!str_contains($result['text'], 'Stale saved-result table bbox line should be replaced.'));
        $t->true(!str_contains($result['text'], 'Stale alias row'));
        $t->true(!str_contains($result['text'], 'Stale alias col'));
        $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
        $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
        $t->same('table_bbox', $coordinateReview['table_bbox_source'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $coordinateReview['table_bbox'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $gridReview['geometry_boundary_review']['row_bands'][0]['original_bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $gridReview['geometry_boundary_review']['col_bands'][1]['original_bbox'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $gridReview['geometry_boundary_review']['col_bands'][1]['source_coordinate_source'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->same(false, $metadata['context']['filetype'] !== 'pdf');
    },
];
