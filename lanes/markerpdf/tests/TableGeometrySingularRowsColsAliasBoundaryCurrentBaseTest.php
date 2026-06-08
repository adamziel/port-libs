<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_singular_rows_cols_alias_boundary_page(array $lines): array
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

function markerpdf_singular_rows_cols_alias_boundary_xxyy(float $x1, float $y1, float $x2, float $y2): array
{
    return [$x1, $x2, $y1, $y2];
}

function markerpdf_singular_rows_cols_alias_boundary_flat_table(): array
{
    return [
        'bbox' => [72.0, 150.0, 312.0, 230.0],
        'row_bbox_coordinate_space' => 'page_image',
        'column_bbox_coordinate_space' => 'page_image',
        'cells_coordinate_space' => 'page_image',
        'row_bbox' => [
            ['row_id' => 10, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
            ['row_id' => 11, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
            ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 270.0]],
        ],
        'column_bbox' => [
            ['col_id' => 20, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
            ['col_id' => 21, 'bbox' => [192.0, 150.0, 312.0, 230.0]],
            ['col_id' => 99, 'bbox' => [340.0, 150.0, 360.0, 230.0]],
        ],
        'cells' => [
            ['bbox' => [82.0, 155.0, 302.0, 170.0], 'text' => 'Header'],
            ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images'],
            ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready'],
        ],
    ];
}

function markerpdf_singular_rows_cols_alias_boundary_extract_page_result(): array
{
    return [
        'pnum' => 0,
        'bboxes_coordinate_space' => 'page_image',
        'cells_coordinate_space' => 'page_image',
        'cells' => [[
            ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [20]],
            ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [21]],
            ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [11], 'col_ids' => [20]],
            ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [11], 'col_ids' => [21]],
            ['bbox' => [90.0, 160.0, 150.0, 176.0], 'text' => 'Stale singular row', 'row_ids' => [99], 'col_ids' => [20]],
            ['bbox' => [104.0, 198.0, 154.0, 214.0], 'text' => 'Stale singular column', 'row_ids' => [11], 'col_ids' => [99]],
        ]],
        'rows_cols' => [[
            'row_bbox_coordinate_space' => 'page_image',
            'row_bbox_order' => 'x1_x2_y1_y2',
            'column_bbox_coordinate_space' => 'page_image',
            'column_bbox_order' => 'x1_x2_y1_y2',
            'row_bbox' => [
                ['row_id' => 10, 'bbox' => markerpdf_singular_rows_cols_alias_boundary_xxyy(72.0, 150.0, 312.0, 182.0)],
                ['row_id' => 11, 'bbox' => markerpdf_singular_rows_cols_alias_boundary_xxyy(72.0, 190.0, 312.0, 220.0)],
                ['row_id' => 99, 'bbox' => markerpdf_singular_rows_cols_alias_boundary_xxyy(72.0, 250.0, 312.0, 270.0)],
            ],
            'column_bbox' => [
                ['col_id' => 20, 'bbox' => markerpdf_singular_rows_cols_alias_boundary_xxyy(72.0, 150.0, 172.0, 230.0)],
                ['col_id' => 21, 'bbox' => markerpdf_singular_rows_cols_alias_boundary_xxyy(192.0, 150.0, 312.0, 230.0)],
                ['col_id' => 99, 'bbox' => markerpdf_singular_rows_cols_alias_boundary_xxyy(342.0, 150.0, 362.0, 230.0)],
            ],
        ]],
        'bboxes' => [
            ['bbox' => [72.0, 150.0, 312.0, 230.0]],
        ],
        'image_bboxes' => [
            ['bbox' => [0.0, 0.0, 612.0, 792.0]],
        ],
    ];
}

function markerpdf_singular_rows_cols_alias_boundary_convert(array $recognizedTable): array
{
    $path = sys_get_temp_dir() . '/markerpdf-table-singular-rows-cols-alias-' . bin2hex(random_bytes(4)) . '.pdf';
    file_put_contents($path, "%PDF-1.4\n% singular rows-cols table alias boundary current-base fixture\n%%EOF");

    try {
        return (new SuppliedDocumentConverter())->convert(
            $path,
            [
                markerpdf_singular_rows_cols_alias_boundary_page([
                    ['text' => 'Singular rows cols alias boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                    ['text' => 'Stale singular rows-cols table line should be replaced.', 'bbox' => [82.0, 176.0, 380.0, 196.0]],
                    ['text' => 'After singular rows cols table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
                'recognized_tables' => [$recognizedTable],
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
    'canonicalizes singular row_bbox column_bbox aliases before table crop boundary assignment' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_singular_rows_cols_alias_boundary_flat_table()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedByText = [];
        foreach ($assigned as $cell) {
            $assignedByText[$cell['text']] = $cell;
        }
        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $localized['rows'] ?? [],
            $localized['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );
        $renderByText = [];
        foreach (($gridReview['render_cells'] ?? []) as $renderCell) {
            $renderByText[$renderCell['text']] = $renderCell;
        }

        $t->same('translated_to_table_crop', $formatted['coordinate_space_reviews'][0]['status'] ?? null);
        $t->same('page_image', $formatted['coordinate_space_reviews'][0]['source_coordinate_spaces']['rows'] ?? null);
        $t->same('page_image', $formatted['coordinate_space_reviews'][0]['source_coordinate_spaces']['cols'] ?? null);
        $t->same('row_bbox', $localized['rows_source_alias'] ?? null);
        $t->same('column_bbox', $localized['cols_source_alias'] ?? null);
        $t->same('table_crop', $localized['row_bbox_coordinate_space'] ?? null);
        $t->same('table_crop', $localized['column_bbox_coordinate_space'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $localized['row_bbox'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['column_bbox'][1]['bbox'] ?? null);
        $t->same([10], $assignedByText['Header']['row_ids'] ?? null);
        $t->same([20, 21], $assignedByText['Header']['col_ids'] ?? null);
        $t->same([11], $assignedByText['Images']['row_ids'] ?? null);
        $t->same([20], $assignedByText['Images']['col_ids'] ?? null);
        $t->same([11], $assignedByText['Ready']['row_ids'] ?? null);
        $t->same([21], $assignedByText['Ready']['col_ids'] ?? null);
        $t->same("| Header |       |\n|--------|-------|\n| Images | Ready |", $formatted['markdown_tables'][0] ?? '');
        $t->same([10, 11], $gridReview['geometry_boundary_review']['active_row_ids'] ?? null);
        $t->same([20, 21], $gridReview['geometry_boundary_review']['active_col_ids'] ?? null);
        $t->same(2, $gridReview['geometry_boundary_review']['excluded_band_count'] ?? null);
        $t->same(2, $renderByText['Header']['colspan'] ?? null);
        $t->same(['h-r10-c20'], $renderByText['Ready']['headers'] ?? null);
    },
    'surfaces singular ExtractPageResult rows_cols aliases through supplied WordPress table conversion' => static function (
        TestRunner $t
    ): void {
        $result = markerpdf_singular_rows_cols_alias_boundary_convert(
            markerpdf_singular_rows_cols_alias_boundary_extract_page_result()
        );
        $metadata = $result['metadata'];
        $pageResultReview = $metadata['table_page_result_boundary_reviews'][0] ?? [];
        $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
        $bandReview = $metadata['table_assigned_band_boundary_reviews'][0] ?? [];
        $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
        $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
        $rowBand = $gridReview['geometry_boundary_review']['row_bands'][0] ?? [];
        $colBand = $gridReview['geometry_boundary_review']['col_bands'][1] ?? [];

        $t->contains('# Singular Rows Cols Alias Boundary', $result['text']);
        $t->contains('| Feature | Status |', $result['text']);
        $t->contains('| Images  | Ready  |', $result['text']);
        $t->contains('After singular rows cols table.', $result['text']);
        $t->true(!str_contains($result['text'], 'Stale singular rows-cols table line should be replaced.'));
        $t->true(!str_contains($result['text'], 'Stale singular row'));
        $t->true(!str_contains($result['text'], 'Stale singular column'));
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->same('table_page_result_boundary', $pageResultReview['review_target'] ?? null);
        $t->same(['row_bbox'], $pageResultReview['rows_cols_row_aliases'] ?? null);
        $t->same(['column_bbox'], $pageResultReview['rows_cols_col_aliases'] ?? null);
        $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
        $t->same('page_image', $coordinateReview['source_coordinate_spaces']['rows'] ?? null);
        $t->same('page_image', $coordinateReview['source_coordinate_spaces']['cols'] ?? null);
        $t->same(3, $coordinateReview['translated_row_band_count'] ?? null);
        $t->same(3, $coordinateReview['translated_col_band_count'] ?? null);
        $t->same(6, $coordinateReview['translated_cell_count'] ?? null);
        $t->same([10, 11], $bandReview['active_row_ids'] ?? null);
        $t->same([20, 21], $bandReview['active_col_ids'] ?? null);
        $t->same(4, $bandReview['active_cell_count'] ?? null);
        $t->same(2, $bandReview['excluded_cell_count'] ?? null);
        $t->same([10, 11], $gridReview['geometry_boundary_review']['active_row_ids'] ?? null);
        $t->same([20, 21], $gridReview['geometry_boundary_review']['active_col_ids'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $rowBand['original_bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $colBand['original_bbox'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $rowBand['source_coordinate_source'] ?? null);
        $t->same('bbox_array_x1_x2_y1_y2_order', $colBand['source_coordinate_source'] ?? null);
        $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
    },
];
