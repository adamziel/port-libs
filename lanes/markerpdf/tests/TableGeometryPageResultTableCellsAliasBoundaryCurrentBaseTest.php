<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

function markerpdf_page_result_table_cells_alias_page(array $lines): array
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

function markerpdf_page_result_table_cells_alias_extract_page_result(): array
{
    return [
        'pnum' => 0,
        'bboxes_coordinate_space' => 'page_image',
        'table_cells_coordinate_space' => 'page_image',
        'table_cells' => [[
            ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [20]],
            ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [21]],
            ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [11], 'col_ids' => [20]],
            ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [11], 'col_ids' => [21]],
            ['bbox' => [92.0, 160.0, 152.0, 176.0], 'text' => 'Inactive alias row', 'row_ids' => [99], 'col_ids' => [20]],
            ['bbox' => [104.0, 198.0, 154.0, 214.0], 'text' => 'Inactive alias column', 'row_ids' => [11], 'col_ids' => [99]],
        ]],
        'rows_cols' => [[
            'rows_coordinate_space' => 'page_image',
            'cols_coordinate_space' => 'page_image',
            'rows' => [
                ['row_id' => 10, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
                ['row_id' => 11, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
                ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 270.0]],
            ],
            'cols' => [
                ['col_id' => 20, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
                ['col_id' => 21, 'bbox' => [192.0, 150.0, 312.0, 230.0]],
                ['col_id' => 99, 'bbox' => [342.0, 150.0, 362.0, 230.0]],
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

function markerpdf_page_result_table_cells_alias_convert(): array
{
    $path = sys_get_temp_dir() . '/markerpdf-page-result-table-cells-alias-' . bin2hex(random_bytes(4)) . '.pdf';
    file_put_contents($path, "%PDF-1.4\n% page result table_cells alias table boundary fixture\n%%EOF");

    try {
        return (new SuppliedDocumentConverter())->convert(
            $path,
            [
                markerpdf_page_result_table_cells_alias_page([
                    ['text' => 'Page result table cells alias boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                    ['text' => 'Stale table_cells alias line should be replaced.', 'bbox' => [82.0, 176.0, 370.0, 196.0]],
                    ['text' => 'After table cells alias table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
                'recognized_tables' => [markerpdf_page_result_table_cells_alias_extract_page_result()],
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
    'flattens ExtractPageResult table_cells alias before WordPress table assignment' => static function (
        TestRunner $t
    ): void {
        $result = markerpdf_page_result_table_cells_alias_convert();
        $metadata = $result['metadata'];
        $pageResultReview = $metadata['table_page_result_boundary_reviews'][0] ?? [];
        $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
        $bandReview = $metadata['table_assigned_band_boundary_reviews'][0] ?? [];
        $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
        $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

        $t->contains('# Page Result Table Cells Alias Boundary', $result['text']);
        $t->contains('| Feature | Status |', $result['text']);
        $t->contains('| Images  | Ready  |', $result['text']);
        $t->contains('After table cells alias table.', $result['text']);
        $t->true(!str_contains($result['text'], 'Stale table_cells alias line should be replaced.'));
        $t->true(!str_contains($result['text'], 'Inactive alias row'));
        $t->true(!str_contains($result['text'], 'Inactive alias column'));
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->same('table_page_result_boundary', $pageResultReview['review_target'] ?? null);
        $t->same('tabled.schema.ExtractPageResult', $pageResultReview['upstream_boundary'] ?? null);
        $t->same('table_cells', $pageResultReview['cells_source'] ?? null);
        $t->same('table_cells', $pageResultReview['cells_source_alias'] ?? null);
        $t->same(1, $pageResultReview['table_count'] ?? null);
        $t->same([0], $pageResultReview['flattened_table_indexes'] ?? null);
        $t->same(1, $pageResultReview['cells_table_count'] ?? null);
        $t->same(1, $pageResultReview['rows_cols_table_count'] ?? null);
        $t->same(1, $pageResultReview['table_bbox_count'] ?? null);
        $t->same(0, $pageResultReview['pnum'] ?? null);
        $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
        $t->same('page_image', $coordinateReview['source_coordinate_spaces']['cells'] ?? null);
        $t->same('page_image', $coordinateReview['source_coordinate_spaces']['rows'] ?? null);
        $t->same('page_image', $coordinateReview['source_coordinate_spaces']['cols'] ?? null);
        $t->same(6, $coordinateReview['translated_cell_count'] ?? null);
        $t->same([10, 11], $bandReview['active_row_ids'] ?? null);
        $t->same([20, 21], $bandReview['active_col_ids'] ?? null);
        $t->same(6, $bandReview['cell_count'] ?? null);
        $t->same(4, $bandReview['active_cell_count'] ?? null);
        $t->same(2, $bandReview['excluded_cell_count'] ?? null);
        $t->same([10, 11], $gridReview['geometry_boundary_review']['active_row_ids'] ?? null);
        $t->same([20, 21], $gridReview['geometry_boundary_review']['active_col_ids'] ?? null);
        $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
        $t->same(1, $metadata['inserted_tables'] ?? null);
    },
];
