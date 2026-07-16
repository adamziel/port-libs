<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

function markerpdf_page_result_table_bboxes_alias_page(array $lines): array
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

function markerpdf_page_result_table_bboxes_alias_extract_page_result(): array
{
    return [
        'pnum' => 0,
        'coordinate_space' => 'page_image',
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'table_bboxes' => [[
            'bbox' => [72.0, 150.0, 312.0, 230.0],
            'coordinate_space' => 'page_image',
        ]],
        'cells' => [[
            ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale table-bboxes row', 'row_ids' => [99], 'col_ids' => [0]],
            ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale table-bboxes col', 'row_ids' => [1], 'col_ids' => [99]],
        ]],
        'rows_cols' => [[
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
        ]],
    ];
}

function markerpdf_page_result_table_bboxes_alias_convert(): array
{
    $path = sys_get_temp_dir() . '/markerpdf-page-result-table-bboxes-alias-' . bin2hex(random_bytes(4)) . '.pdf';
    file_put_contents($path, "%PDF-1.4\n% page result table_bboxes alias boundary fixture\n%%EOF");

    try {
        return (new SuppliedDocumentConverter())->convert(
            $path,
            [
                markerpdf_page_result_table_bboxes_alias_page([
                    ['text' => 'Page result table bboxes alias boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                    ['text' => 'Stale table_bboxes alias line should be replaced.', 'bbox' => [300.0, 420.0, 530.0, 438.0]],
                    ['text' => 'After page result table_bboxes alias.', 'bbox' => [72.0, 520.0, 560.0, 538.0]],
                ]),
            ],
            [
                'metadata' => ['languages' => ['English']],
                'layout_results' => [[
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Title', 'bbox' => [72.0, 48.0, 560.0, 68.0]],
                        ['label' => 'Table', 'bbox' => [300.0, 400.0, 540.0, 480.0]],
                        ['label' => 'Text', 'bbox' => [72.0, 520.0, 560.0, 538.0]],
                    ],
                ]],
                'recognized_tables' => [markerpdf_page_result_table_bboxes_alias_extract_page_result()],
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
    'propagates ExtractPageResult table_bboxes aliases before table crop localization' => static function (
        TestRunner $t
    ): void {
        $result = markerpdf_page_result_table_bboxes_alias_convert();
        $metadata = $result['metadata'];
        $pageResultReview = $metadata['table_page_result_boundary_reviews'][0] ?? [];
        $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
        $cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
        $assignedByText = [];
        foreach (($metadata['table_assigned_cells'][0] ?? []) as $cell) {
            $assignedByText[$cell['text'] ?? ''] = $cell;
        }

        $t->contains('# Page Result Table Bboxes Alias Boundary', $result['text']);
        $t->contains('| Feature | Status |', $result['text']);
        $t->contains('| Images  | Ready  |', $result['text']);
        $t->contains('After page result table_bboxes alias.', $result['text']);
        $t->true(!str_contains($result['text'], 'Stale table_bboxes alias line should be replaced.'));
        $t->true(!str_contains($result['text'], 'Stale table-bboxes row'));
        $t->true(!str_contains($result['text'], 'Stale table-bboxes col'));
        $t->same('table_page_result_boundary', $pageResultReview['review_target'] ?? null);
        $t->same('tabled.schema.ExtractPageResult', $pageResultReview['upstream_boundary'] ?? null);
        $t->same(1, $pageResultReview['table_count'] ?? null);
        $t->same(1, $pageResultReview['table_bbox_count'] ?? null);
        $t->same('table_bboxes', $pageResultReview['table_bbox_source'] ?? null);
        $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
        $t->same('ExtractPageResult.table_bboxes', $coordinateReview['table_bbox_source'] ?? null);
        $t->same('page_image', $coordinateReview['table_bbox_source_coordinate_space'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $coordinateReview['table_bbox'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $coordinateReview['translation'] ?? null);
        $t->same(3, $coordinateReview['translated_row_band_count'] ?? null);
        $t->same(3, $coordinateReview['translated_col_band_count'] ?? null);
        $t->same(6, $coordinateReview['translated_cell_count'] ?? null);
        $t->same(6, $cropReview['cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], array_keys($assignedByText));
        $t->same([10.0, 5.0, 90.0, 20.0], $assignedByText['Feature']['bbox'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $assignedByText['Feature']['source_bbox'] ?? null);
        $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
        $t->same(false, $metadata['context']['filetype'] !== 'pdf');
    },
];
