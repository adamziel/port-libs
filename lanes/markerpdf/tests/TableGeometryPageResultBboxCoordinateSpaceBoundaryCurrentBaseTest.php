<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_page_result_bbox_coordinate_space_page(array $lines): array
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

function markerpdf_page_result_bbox_coordinate_space_norm_page(array $bbox): array
{
    return [
        round(((float) $bbox[0] / 612.0) * 1000.0, 6),
        round(((float) $bbox[1] / 792.0) * 1000.0, 6),
        round(((float) $bbox[2] / 612.0) * 1000.0, 6),
        round(((float) $bbox[3] / 792.0) * 1000.0, 6),
    ];
}

function markerpdf_page_result_bbox_coordinate_space_round(array $bbox): array
{
    return array_map(
        static fn (mixed $value): float => round((float) $value, 1),
        $bbox
    );
}

function markerpdf_page_result_bbox_coordinate_space_extract_page_result(): array
{
    return [
        'pnum' => 0,
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes_coordinate_space' => 'normalized_page_image',
        'rows_coordinate_space' => 'normalized_page_image',
        'cols_coordinate_space' => 'normalized_page_image',
        'cells_coordinate_space' => 'normalized_page_image',
        'cells' => [[
            ['bbox' => markerpdf_page_result_bbox_coordinate_space_norm_page([82.0, 155.0, 162.0, 170.0]), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => markerpdf_page_result_bbox_coordinate_space_norm_page([202.0, 155.0, 302.0, 170.0]), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => markerpdf_page_result_bbox_coordinate_space_norm_page([82.0, 195.0, 162.0, 215.0]), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => markerpdf_page_result_bbox_coordinate_space_norm_page([202.0, 195.0, 302.0, 215.0]), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => markerpdf_page_result_bbox_coordinate_space_norm_page([82.0, 250.0, 162.0, 268.0]), 'text' => 'Stale page-result row', 'row_ids' => [99], 'col_ids' => [0]],
            ['bbox' => markerpdf_page_result_bbox_coordinate_space_norm_page([360.0, 195.0, 382.0, 215.0]), 'text' => 'Stale page-result col', 'row_ids' => [1], 'col_ids' => [99]],
        ]],
        'rows_cols' => [[
            'rows' => [
                ['row_id' => 0, 'bbox' => markerpdf_page_result_bbox_coordinate_space_norm_page([72.0, 150.0, 312.0, 182.0])],
                ['row_id' => 1, 'bbox' => markerpdf_page_result_bbox_coordinate_space_norm_page([72.0, 190.0, 312.0, 220.0])],
                ['row_id' => 99, 'bbox' => markerpdf_page_result_bbox_coordinate_space_norm_page([72.0, 250.0, 312.0, 270.0])],
            ],
            'cols' => [
                ['col_id' => 0, 'bbox' => markerpdf_page_result_bbox_coordinate_space_norm_page([72.0, 150.0, 172.0, 230.0])],
                ['col_id' => 1, 'bbox' => markerpdf_page_result_bbox_coordinate_space_norm_page([192.0, 150.0, 312.0, 230.0])],
                ['col_id' => 99, 'bbox' => markerpdf_page_result_bbox_coordinate_space_norm_page([342.0, 150.0, 362.0, 230.0])],
            ],
        ]],
        'bboxes' => [
            ['bbox' => markerpdf_page_result_bbox_coordinate_space_norm_page([72.0, 150.0, 312.0, 230.0])],
        ],
    ];
}

function markerpdf_page_result_bbox_coordinate_space_table(): array
{
    $pageResult = markerpdf_page_result_bbox_coordinate_space_extract_page_result();
    $rowsCols = $pageResult['rows_cols'][0];

    return [
        'bbox' => $pageResult['bboxes'][0]['bbox'],
        'image_bbox' => $pageResult['image_bbox'],
        'bboxes_coordinate_space' => $pageResult['bboxes_coordinate_space'],
        'rows_coordinate_space' => $pageResult['rows_coordinate_space'],
        'cols_coordinate_space' => $pageResult['cols_coordinate_space'],
        'cells_coordinate_space' => $pageResult['cells_coordinate_space'],
        'rows' => $rowsCols['rows'],
        'cols' => $rowsCols['cols'],
        'cells' => $pageResult['cells'][0],
    ];
}

return [
    'uses page result bboxes coordinate space for flattened table crop localization' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_page_result_bbox_coordinate_space_table()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedTexts = array_column($assigned, 'text');
        $cropReview = $formatted['assigned_crop_boundary_reviews'][0] ?? [];
        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $localized['rows'] ?? [],
            $localized['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );

        $t->same('table_recognition_coordinate_space_boundary', $review['review_target'] ?? null);
        $t->same('translated_and_normalized_to_table_crop', $review['status'] ?? null);
        $t->same('bbox_array', $review['table_bbox_source'] ?? null);
        $t->same('normalized_page_image', $review['table_bbox_source_coordinate_space'] ?? null);
        $t->same(markerpdf_page_result_bbox_coordinate_space_table()['bbox'], $review['source_table_bbox'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], markerpdf_page_result_bbox_coordinate_space_round($review['table_bbox'] ?? []));
        $t->same(['width' => 612, 'height' => 792], $review['table_bbox_page_image_normalization_size'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], [
            'x' => round((float) ($review['translation']['x'] ?? 0.0), 1),
            'y' => round((float) ($review['translation']['y'] ?? 0.0), 1),
        ]);
        $t->same('normalized_page_image', $review['source_coordinate_spaces']['rows'] ?? null);
        $t->same('normalized_page_image', $review['source_coordinate_spaces']['cells'] ?? null);
        $t->same(3, $review['normalized_row_band_count'] ?? null);
        $t->same(3, $review['normalized_col_band_count'] ?? null);
        $t->same(6, $review['normalized_cell_count'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], markerpdf_page_result_bbox_coordinate_space_round($localized['rows'][0]['bbox'] ?? []));
        $t->same([120.0, 0.0, 240.0, 80.0], markerpdf_page_result_bbox_coordinate_space_round($localized['cols'][1]['bbox'] ?? []));
        $t->same([10.0, 5.0, 90.0, 20.0], markerpdf_page_result_bbox_coordinate_space_round($localized['cells'][0]['bbox'] ?? []));
        $t->same([82.0, 155.0, 162.0, 170.0], markerpdf_page_result_bbox_coordinate_space_round($localized['cells'][0]['source_page_image_bbox'] ?? []));
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->same(6, $cropReview['cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same(2, $gridReview['geometry_boundary_review']['active_row_band_count'] ?? null);
        $t->same(2, $gridReview['geometry_boundary_review']['active_col_band_count'] ?? null);
        $t->same(2, $gridReview['geometry_boundary_review']['excluded_band_count'] ?? null);
        $t->same('normalized_page_image', $gridReview['render_cells'][0]['source_coordinate_space'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], markerpdf_page_result_bbox_coordinate_space_round($gridReview['render_cells'][0]['source_page_image_bbox'] ?? []));
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
    },
    'propagates page result bboxes coordinate space before table crop localization' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-page-result-bbox-coordinate-space-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% page result bboxes coordinate-space table boundary fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_page_result_bbox_coordinate_space_page([
                        ['text' => 'Page result bbox coordinate space boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale page-result bbox-coordinate table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                        ['text' => 'After page result bbox coordinate space table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
                    'recognized_tables' => [markerpdf_page_result_bbox_coordinate_space_extract_page_result()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $metadata = $result['metadata'];
            $pageResultReview = $metadata['table_page_result_boundary_reviews'][0] ?? [];
            $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
            $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
            $cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
            $assignedByText = [];
            foreach (($metadata['table_assigned_cells'][0] ?? []) as $cell) {
                $assignedByText[$cell['text'] ?? ''] = $cell;
            }
            $assignedTexts = array_keys($assignedByText);

            $t->contains('# Page Result Bbox Coordinate Space Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After page result bbox coordinate space table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale page-result bbox-coordinate table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale page-result row'));
            $t->true(!str_contains($result['text'], 'Stale page-result col'));
            $t->same('table_page_result_boundary', $pageResultReview['review_target'] ?? null);
            $t->same(1, $pageResultReview['table_count'] ?? null);
            $t->same('translated_and_normalized_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same('table_bbox', $coordinateReview['table_bbox_source'] ?? null);
            $t->same([72.0, 150.0, 312.0, 230.0], markerpdf_page_result_bbox_coordinate_space_round($coordinateReview['table_bbox'] ?? []));
            $t->same(['width' => 612, 'height' => 792], $coordinateReview['page_image_normalization_size'] ?? null);
            $t->same('normalized_page_image', $coordinateReview['source_coordinate_spaces']['rows'] ?? null);
            $t->same('normalized_page_image', $coordinateReview['source_coordinate_spaces']['cells'] ?? null);
            $t->same(3, $coordinateReview['normalized_row_band_count'] ?? null);
            $t->same(3, $coordinateReview['normalized_col_band_count'] ?? null);
            $t->same(6, $coordinateReview['normalized_cell_count'] ?? null);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same([10.0, 5.0, 90.0, 20.0], markerpdf_page_result_bbox_coordinate_space_round($assignedByText['Feature']['bbox'] ?? []));
            $t->same([82.0, 155.0, 162.0, 170.0], markerpdf_page_result_bbox_coordinate_space_round($assignedByText['Feature']['source_page_image_bbox'] ?? []));
            $t->same('normalized_page_image', $assignedByText['Feature']['source_coordinate_space'] ?? null);
            $t->same(6, $cropReview['cell_count'] ?? null);
            $t->same(4, $cropReview['active_cell_count'] ?? null);
            $t->same(2, $cropReview['excluded_cell_count'] ?? null);
            $t->same(2, $gridReview['geometry_boundary_review']['active_row_band_count'] ?? null);
            $t->same(2, $gridReview['geometry_boundary_review']['active_col_band_count'] ?? null);
            $t->same(2, $gridReview['geometry_boundary_review']['excluded_band_count'] ?? null);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
            $t->same(false, $metadata['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
