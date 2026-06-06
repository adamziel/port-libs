<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

function markerpdf_page_result_boundary_page(array $lines): array
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

function markerpdf_page_result_boundary_extract_page_result(): array
{
    return [
        'pnum' => 0,
        'cells' => [
            [
                ['bbox' => [10.0, 5.0, 90.0, 20.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
                ['bbox' => [130.0, 5.0, 230.0, 20.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
                ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
                ['bbox' => [130.0, 45.0, 230.0, 65.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
                ['bbox' => [260.0, 45.0, 285.0, 65.0], 'text' => 'Offcrop page result', 'row_ids' => [1], 'col_ids' => [99]],
            ],
            [
                ['bbox' => [10.0, 5.0, 95.0, 20.0], 'text' => 'Attachment', 'row_ids' => [0], 'col_ids' => [0]],
                ['bbox' => [120.0, 5.0, 210.0, 20.0], 'text' => 'State', 'row_ids' => [0], 'col_ids' => [1]],
                ['bbox' => [10.0, 45.0, 95.0, 65.0], 'text' => 'PDF', 'row_ids' => [1], 'col_ids' => [0]],
                ['bbox' => [120.0, 45.0, 210.0, 65.0], 'text' => 'Queued', 'row_ids' => [1], 'col_ids' => [1]],
            ],
        ],
        'rows_cols' => [
            [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 32.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
                    ['col_id' => 99, 'bbox' => [255.0, 0.0, 285.0, 80.0]],
                ],
            ],
            [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 220.0, 32.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 40.0, 220.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 105.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [115.0, 0.0, 220.0, 80.0]],
                ],
            ],
        ],
        'bboxes' => [
            ['bbox' => [72.0, 150.0, 312.0, 230.0]],
            ['bbox' => [72.0, 270.0, 292.0, 350.0]],
        ],
        'image_bboxes' => [
            ['bbox' => [0.0, 0.0, 612.0, 792.0]],
            ['bbox' => [0.0, 0.0, 612.0, 792.0]],
        ],
    ];
}

return [
    'flattens upstream page result table boundaries before supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-page-result-table-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% page result table geometry boundary fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_page_result_boundary_page([
                        ['text' => 'Page result table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'First stale table line should be replaced.', 'bbox' => [82.0, 176.0, 280.0, 196.0]],
                        ['text' => 'Second stale table line should be replaced.', 'bbox' => [82.0, 296.0, 270.0, 316.0]],
                        ['text' => 'After upstream grouped page result.', 'bbox' => [72.0, 390.0, 480.0, 408.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 270.0, 292.0, 350.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 390.0, 480.0, 408.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_page_result_boundary_extract_page_result()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $textsByTable = array_map(
                static fn (array $cells): array => array_column($cells, 'text'),
                $result['metadata']['table_assigned_cells'] ?? []
            );
            $pageResultReview = $result['metadata']['table_page_result_boundary_reviews'][0] ?? [];
            $cropReviews = $result['metadata']['table_assigned_crop_boundary_reviews'] ?? [];
            $firstTablePosition = strpos($result['text'], '| Feature | Status |');
            $secondTablePosition = strpos($result['text'], '| Attachment | State  |');

            $t->contains('# Page Result Table Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('| Attachment | State  |', $result['text']);
            $t->contains('| PDF        | Queued |', $result['text']);
            $t->contains('After upstream grouped page result.', $result['text']);
            $t->true(!str_contains($result['text'], 'First stale table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Second stale table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Offcrop page result'));
            $t->true($firstTablePosition !== false && $secondTablePosition !== false && $firstTablePosition < $secondTablePosition);
            $t->same(2, $result['metadata']['inserted_tables'] ?? null);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries'] ?? null);
            $t->same([
                [72.0, 150.0, 312.0, 230.0],
                [72.0, 270.0, 292.0, 350.0],
            ], $result['metadata']['table_plan']['table_bboxes'] ?? null);
            $t->same('table_page_result_boundary', $pageResultReview['review_target'] ?? null);
            $t->same('tabled.schema.ExtractPageResult', $pageResultReview['upstream_boundary'] ?? null);
            $t->same(0, $pageResultReview['page_result_index'] ?? null);
            $t->same(2, $pageResultReview['table_count'] ?? null);
            $t->same([0, 1], $pageResultReview['flattened_table_indexes'] ?? null);
            $t->same(2, $pageResultReview['cells_table_count'] ?? null);
            $t->same(2, $pageResultReview['rows_cols_table_count'] ?? null);
            $t->same(2, $pageResultReview['table_bbox_count'] ?? null);
            $t->same(2, $pageResultReview['image_bbox_count'] ?? null);
            $t->same(0, $pageResultReview['pnum'] ?? null);
            $t->same([
                ['Feature', 'Status', 'Images', 'Ready'],
                ['Attachment', 'State', 'PDF', 'Queued'],
            ], $textsByTable);
            $t->same(5, $cropReviews[0]['cell_count'] ?? null);
            $t->same(4, $cropReviews[0]['active_cell_count'] ?? null);
            $t->same(1, $cropReviews[0]['excluded_cell_count'] ?? null);
            $t->same(4, $cropReviews[1]['active_cell_count'] ?? null);
            $t->same(2, count($result['metadata']['table_spanning_grid_review'] ?? []));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
