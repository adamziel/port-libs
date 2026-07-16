<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_prefixed_source_bbox_boundary_page(array $lines): array
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

function markerpdf_prefixed_source_bbox_boundary_table(): array
{
    return [
        'table_bbox' => [72.0, 150.0, 312.0, 230.0],
        'rows' => [
            ['row_id' => 10, 'source_left' => 72.0, 'source_top' => 150.0, 'source_right' => 312.0, 'source_bottom' => 182.0, 'source_coordinate_space' => 'page_image'],
            ['row_id' => 11, 'original_x' => 72.0, 'original_y' => 190.0, 'original_width' => 240.0, 'original_height' => 30.0, 'original_coordinate_space' => 'page_image'],
            ['row_id' => 99, 'source_x1' => 72.0, 'source_y1' => 250.0, 'source_x2' => 312.0, 'source_y2' => 268.0, 'source_coordinate_space' => 'page_image'],
        ],
        'cols' => [
            ['col_id' => 20, 'source_x1' => 72.0, 'source_y1' => 150.0, 'source_x2' => 172.0, 'source_y2' => 230.0, 'source_coordinate_space' => 'page_image'],
            ['col_id' => 21, 'original_left' => 192.0, 'original_top' => 150.0, 'original_width' => 120.0, 'original_height' => 80.0, 'original_coordinate_space' => 'page_image'],
            ['col_id' => 99, 'source_left' => 342.0, 'source_top' => 150.0, 'source_width' => 20.0, 'source_height' => 80.0, 'source_coordinate_space' => 'page_image'],
        ],
        'cells' => [
            ['source_left' => 82.0, 'source_top' => 155.0, 'source_right' => 162.0, 'source_bottom' => 170.0, 'source_coordinate_space' => 'page_image', 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [20]],
            ['original_x' => 202.0, 'original_y' => 155.0, 'original_w' => 100.0, 'original_h' => 15.0, 'original_coordinate_space' => 'page_image', 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [21]],
            ['source_page_image_left' => 82.0, 'source_page_image_top' => 195.0, 'source_page_image_right' => 162.0, 'source_page_image_bottom' => 215.0, 'source_coordinate_space' => 'page_image', 'text' => 'Images', 'row_ids' => [11], 'col_ids' => [20]],
            ['source_x' => 202.0, 'source_y' => 195.0, 'source_width' => 100.0, 'source_height' => 20.0, 'source_coordinate_space' => 'page_image', 'text' => 'Ready', 'row_ids' => [11], 'col_ids' => [21]],
            ['source_left' => 82.0, 'source_top' => 250.0, 'source_right' => 162.0, 'source_bottom' => 268.0, 'source_coordinate_space' => 'page_image', 'text' => 'Stale prefixed row', 'row_ids' => [99], 'col_ids' => [20]],
            ['original_left' => 360.0, 'original_top' => 195.0, 'original_right' => 382.0, 'original_bottom' => 215.0, 'original_coordinate_space' => 'page_image', 'text' => 'Stale prefixed col', 'row_ids' => [11], 'col_ids' => [99]],
        ],
        'ocr_grid_border_conflicts' => [[
            'ocr_index' => 0,
            'text' => 'Prefixed source conflict',
            'source_left' => 82.0,
            'source_top' => 155.0,
            'source_right' => 302.0,
            'source_bottom' => 215.0,
            'source_coordinate_space' => 'page_image',
            'candidate_cell_indexes' => [0, 1, 2],
            'candidate_cell_bboxes' => [
                ['source_left' => 82.0, 'source_top' => 155.0, 'source_right' => 162.0, 'source_bottom' => 170.0, 'source_coordinate_space' => 'page_image'],
                ['original_x' => 202.0, 'original_y' => 155.0, 'original_w' => 100.0, 'original_h' => 15.0, 'original_coordinate_space' => 'page_image'],
                ['source_page_image_left' => 82.0, 'source_page_image_top' => 195.0, 'source_page_image_right' => 162.0, 'source_page_image_bottom' => 215.0, 'source_coordinate_space' => 'page_image'],
            ],
            'assigned_cell_index' => 0,
            'spans_grid_border' => true,
        ]],
    ];
}

function markerpdf_prefixed_source_bbox_boundary_convert(): array
{
    $path = sys_get_temp_dir() . '/markerpdf-table-prefixed-source-bbox-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
    file_put_contents($path, "%PDF-1.4\n% table prefixed source bbox boundary current-base fixture\n%%EOF");

    try {
        return (new SuppliedDocumentConverter())->convert(
            $path,
            [
                markerpdf_prefixed_source_bbox_boundary_page([
                    ['text' => 'Prefixed source bbox table boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                    ['text' => 'Stale prefixed source table line should be replaced.', 'bbox' => [82.0, 176.0, 360.0, 196.0]],
                    ['text' => 'After prefixed source table review.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
                'recognized_tables' => [markerpdf_prefixed_source_bbox_boundary_table()],
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
    'localizes prefixed source and original named table geometry fields' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_prefixed_source_bbox_boundary_table()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedTexts = array_column($assigned, 'text');
        $conflict = $localized['ocr_grid_border_conflicts'][0] ?? [];
        $cropReview = $formatted['assigned_crop_boundary_reviews'][0] ?? [];
        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $localized['rows'] ?? [],
            $localized['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );

        $t->same('translated_to_table_crop', $review['status'] ?? null);
        $t->same(['page_image' => 3], $review['source_record_coordinate_spaces']['rows'] ?? null);
        $t->same(['page_image' => 6], $review['source_record_coordinate_spaces']['cells'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([0.0, 40.0, 240.0, 70.0], $localized['rows'][1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 100.0, 80.0], $localized['cols'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same('source_bbox_left_top_right_bottom_fields', $localized['rows'][0]['source_coordinate_source'] ?? null);
        $t->same('original_bbox_xy_width_height_fields', $localized['rows'][1]['source_coordinate_source'] ?? null);
        $t->same('source_bbox_xyxy_named_fields', $localized['cols'][0]['source_coordinate_source'] ?? null);
        $t->same('original_bbox_left_top_width_height_fields', $localized['cols'][1]['source_coordinate_source'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same([130.0, 5.0, 230.0, 20.0], $localized['cells'][1]['bbox'] ?? null);
        $t->same([10.0, 45.0, 90.0, 65.0], $localized['cells'][2]['bbox'] ?? null);
        $t->same('source_bbox_left_top_right_bottom_fields', $localized['cells'][0]['source_coordinate_source'] ?? null);
        $t->same('original_bbox_xy_w_h_fields', $localized['cells'][1]['source_coordinate_source'] ?? null);
        $t->same('source_page_image_bbox_left_top_right_bottom_fields', $localized['cells'][2]['source_coordinate_source'] ?? null);
        $t->same([10.0, 5.0, 230.0, 65.0], $conflict['bbox'] ?? null);
        $t->same([[10.0, 5.0, 90.0, 20.0], [130.0, 5.0, 230.0, 20.0], [10.0, 45.0, 90.0, 65.0]], $conflict['candidate_cell_bboxes'] ?? null);
        $t->same('source_bbox_left_top_right_bottom_fields', $conflict['source_coordinate_source'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale prefixed row', $assignedTexts, true));
        $t->true(!in_array('Stale prefixed col', $assignedTexts, true));
        $t->same(6, $cropReview['cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same('source_bbox_left_top_right_bottom_fields', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
        $t->same('original_bbox_xy_width_height_fields', $gridReview['geometry_boundary_review']['row_bands'][1]['source_coordinate_source'] ?? null);
        $t->same('source_bbox_left_top_right_bottom_fields', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $gridReview['render_cells'][0]['source_cell_bbox'] ?? null);
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces prefixed source named geometry through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $result = markerpdf_prefixed_source_bbox_boundary_convert();
        $metadata = $result['metadata'];
        $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
        $cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
        $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
        $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

        $t->contains('# Prefixed Source Bbox Table Boundary', $result['text']);
        $t->contains('| Feature | Status |', $result['text']);
        $t->contains('| Images  | Ready  |', $result['text']);
        $t->contains('After prefixed source table review.', $result['text']);
        $t->true(!str_contains($result['text'], 'Stale prefixed source table line should be replaced.'));
        $t->true(!str_contains($result['text'], 'Stale prefixed row'));
        $t->true(!str_contains($result['text'], 'Stale prefixed col'));
        $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
        $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
        $t->same(6, $coordinateReview['translated_cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same('source_bbox_left_top_right_bottom_fields', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
        $t->same('source_page_image_bbox_left_top_right_bottom_fields', $gridReview['render_cells'][2]['source_coordinate_source'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->same(false, $metadata['context']['filetype'] !== 'pdf');
    },
];
