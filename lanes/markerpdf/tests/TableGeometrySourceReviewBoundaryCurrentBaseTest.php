<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_source_review_boundary_page(array $lines): array
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

function markerpdf_source_review_boundary_table(): array
{
    return [
        'coordinate_space' => 'page_image',
        'bbox' => ['x' => 72.0, 'y' => 150.0, 'width' => 240.0, 'height' => 80.0],
        'rows' => [
            ['row_id' => 10, 'bbox' => ['x' => 72.0, 'y' => 150.0, 'width' => 240.0, 'height' => 32.0]],
            ['row_id' => 20, 'x' => 72.0, 'y' => 190.0, 'width' => 240.0, 'height' => 30.0],
            ['row_id' => 99, 'bbox' => ['x' => 72.0, 'y' => 250.0, 'width' => 240.0, 'height' => 20.0]],
        ],
        'cols' => [
            ['col_id' => 30, 'bbox' => ['left' => 72.0, 'top' => 150.0, 'width' => 100.0, 'height' => 80.0]],
            ['col_id' => 40, 'left' => 192.0, 'top' => 150.0, 'width' => 120.0, 'height' => 80.0],
            ['col_id' => 99, 'bbox' => ['left' => 340.0, 'top' => 150.0, 'width' => 20.0, 'height' => 80.0]],
        ],
        'cells' => [
            ['bbox' => ['x' => 82.0, 'y' => 155.0, 'width' => 80.0, 'height' => 15.0], 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [30]],
            ['bbox' => ['left' => 202.0, 'top' => 155.0, 'width' => 100.0, 'height' => 15.0], 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [40]],
            ['bbox' => ['x0' => 82.0, 'y0' => 195.0, 'width' => 80.0, 'height' => 20.0], 'text' => 'Images', 'row_ids' => [20], 'col_ids' => [30]],
            ['bbox' => ['x' => 202.0, 'y' => 195.0, 'width' => 100.0, 'height' => 20.0], 'text' => 'Ready', 'row_ids' => [20], 'col_ids' => [40]],
            ['bbox' => ['x' => 82.0, 'y' => 250.0, 'width' => 80.0, 'height' => 18.0], 'text' => 'Stale row', 'row_ids' => [99], 'col_ids' => [30]],
            ['bbox' => ['left' => 360.0, 'top' => 195.0, 'width' => 20.0, 'height' => 20.0], 'text' => 'Stale column', 'row_ids' => [20], 'col_ids' => [99]],
        ],
    ];
}

return [
    'preserves source field-shape metadata after table geometry localization' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_source_review_boundary_table()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $localized['rows'] ?? [],
            $localized['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );

        $assignedTexts = array_column($assigned, 'text');
        $rowBand = $gridReview['geometry_boundary_review']['row_bands'][0] ?? [];
        $colBand = $gridReview['geometry_boundary_review']['col_bands'][0] ?? [];
        $renderCell = $gridReview['render_cells'][0] ?? [];
        $cropCell = $formatted['assigned_crop_boundary_reviews'][0]['cells'][0] ?? [];

        $t->same('translated_to_table_crop', $formatted['coordinate_space_reviews'][0]['status'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same([72.0, 150.0, 312.0, 182.0], $localized['rows'][0]['source_bbox'] ?? null);
        $t->same([72.0, 150.0, 172.0, 230.0], $localized['cols'][0]['source_bbox'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $localized['cells'][0]['source_bbox'] ?? null);
        $t->same('bbox_xy_width_height_fields', $localized['rows'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_left_top_width_height_fields', $localized['cols'][0]['source_coordinate_source'] ?? null);
        $t->same('bbox_xy_width_height_fields', $localized['cells'][0]['source_coordinate_source'] ?? null);
        $t->same(false, $localized['cells'][0]['source_endpoint_order_normalized'] ?? null);

        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale row', $assignedTexts, true));
        $t->true(!in_array('Stale column', $assignedTexts, true));
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');

        $t->same([72.0, 150.0, 312.0, 182.0], $rowBand['source_bbox'] ?? null);
        $t->same('page_image', $rowBand['source_coordinate_space'] ?? null);
        $t->same('bbox_xy_width_height_fields', $rowBand['source_coordinate_source'] ?? null);
        $t->same('bbox_left_top_width_height_fields', $colBand['source_coordinate_source'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $renderCell['source_cell_bbox'] ?? null);
        $t->same('page_image', $renderCell['source_coordinate_space'] ?? null);
        $t->same('bbox_xy_width_height_fields', $renderCell['source_coordinate_source'] ?? null);
        $t->same('bbox_xy_width_height_fields', $cropCell['source_coordinate_source'] ?? null);
        $t->same(false, $cropCell['source_endpoint_order_normalized'] ?? null);
    },
    'surfaces source field-shape metadata through supplied WordPress table review' => static function (TestRunner $t): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-source-review-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table source review boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_source_review_boundary_page([
                        ['text' => 'Table source review boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale source-shape table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                        ['text' => 'After table source review.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_source_review_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $cropReview = $result['metadata']['table_assigned_crop_boundary_reviews'][0] ?? [];

            $t->contains('# Table Source Review Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After table source review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale source-shape table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale row'));
            $t->true(!str_contains($result['text'], 'Stale column'));
            $t->same('translated_to_table_crop', $result['metadata']['table_coordinate_space_reviews'][0]['status'] ?? null);
            $t->same('bbox_xy_width_height_fields', $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null);
            $t->same('bbox_left_top_width_height_fields', $gridReview['geometry_boundary_review']['col_bands'][0]['source_coordinate_source'] ?? null);
            $t->same('bbox_xy_width_height_fields', $gridReview['render_cells'][0]['source_coordinate_source'] ?? null);
            $t->same([82.0, 155.0, 162.0, 170.0], $gridReview['render_cells'][0]['source_cell_bbox'] ?? null);
            $t->same('bbox_xy_width_height_fields', $cropReview['cells'][0]['source_coordinate_source'] ?? null);
            $t->same(4, $cropReview['active_cell_count'] ?? null);
            $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
