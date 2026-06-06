<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

function markerpdf_page_result_shared_image_bbox_page(array $lines): array
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

function markerpdf_page_result_shared_image_bbox_norm(array $bbox): array
{
    return [
        round(((float) $bbox[0]) / 612.0 * 1000.0, 6),
        round(((float) $bbox[1]) / 792.0 * 1000.0, 6),
        round(((float) $bbox[2]) / 612.0 * 1000.0, 6),
        round(((float) $bbox[3]) / 792.0 * 1000.0, 6),
    ];
}

function markerpdf_page_result_shared_image_bbox_round(array $bbox): array
{
    return array_map(
        static fn (mixed $value): float => round((float) $value, 3),
        $bbox
    );
}

function markerpdf_page_result_shared_image_bbox_extract_page_result(): array
{
    return [
        'pnum' => 0,
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'rows_coordinate_space' => 'normalized_page_image',
        'cols_coordinate_space' => 'normalized_page_image',
        'cells_coordinate_space' => 'normalized_page_image',
        'cells' => [[
            ['bbox' => markerpdf_page_result_shared_image_bbox_norm([82.0, 155.0, 162.0, 170.0]), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => markerpdf_page_result_shared_image_bbox_norm([202.0, 155.0, 302.0, 170.0]), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => markerpdf_page_result_shared_image_bbox_norm([82.0, 195.0, 162.0, 215.0]), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => markerpdf_page_result_shared_image_bbox_norm([202.0, 195.0, 302.0, 215.0]), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => markerpdf_page_result_shared_image_bbox_norm([82.0, 250.0, 162.0, 268.0]), 'text' => 'Stale normalized row', 'row_ids' => [99], 'col_ids' => [0]],
            ['bbox' => markerpdf_page_result_shared_image_bbox_norm([360.0, 195.0, 382.0, 215.0]), 'text' => 'Stale normalized col', 'row_ids' => [1], 'col_ids' => [99]],
        ]],
        'rows_cols' => [[
            'rows' => [
                ['row_id' => 0, 'bbox' => markerpdf_page_result_shared_image_bbox_norm([72.0, 150.0, 312.0, 182.0])],
                ['row_id' => 1, 'bbox' => markerpdf_page_result_shared_image_bbox_norm([72.0, 190.0, 312.0, 220.0])],
                ['row_id' => 99, 'bbox' => markerpdf_page_result_shared_image_bbox_norm([72.0, 250.0, 312.0, 270.0])],
            ],
            'cols' => [
                ['col_id' => 0, 'bbox' => markerpdf_page_result_shared_image_bbox_norm([72.0, 150.0, 172.0, 230.0])],
                ['col_id' => 1, 'bbox' => markerpdf_page_result_shared_image_bbox_norm([192.0, 150.0, 312.0, 230.0])],
                ['col_id' => 99, 'bbox' => markerpdf_page_result_shared_image_bbox_norm([342.0, 150.0, 362.0, 230.0])],
            ],
        ]],
        'bboxes' => [
            ['bbox' => [72.0, 150.0, 312.0, 230.0]],
        ],
    ];
}

return [
    'uses shared page result image bbox to localize normalized page image table geometry' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-page-result-shared-image-bbox-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% shared image bbox page result table boundary fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_page_result_shared_image_bbox_page([
                        ['text' => 'Shared page image boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale normalized table text should be replaced.', 'bbox' => [82.0, 176.0, 280.0, 196.0]],
                        ['text' => 'After shared image bbox page result.', 'bbox' => [72.0, 260.0, 480.0, 278.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 260.0, 480.0, 278.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_page_result_shared_image_bbox_extract_page_result()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $metadata = $result['metadata'];
            $pageResultReview = $metadata['table_page_result_boundary_reviews'][0] ?? [];
            $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
            $cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
            $assignedByText = [];
            foreach (($metadata['table_assigned_cells'][0] ?? []) as $cell) {
                $assignedByText[$cell['text'] ?? ''] = $cell;
            }

            $t->contains('# Shared Page Image Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After shared image bbox page result.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale normalized table text should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale normalized row'));
            $t->true(!str_contains($result['text'], 'Stale normalized col'));
            $t->same(1, $metadata['inserted_tables'] ?? null);
            $t->same('table_page_result_boundary', $pageResultReview['review_target'] ?? null);
            $t->same(1, $pageResultReview['table_count'] ?? null);
            $t->same(0, $pageResultReview['image_bbox_count'] ?? null);
            $t->same('image_bbox', $pageResultReview['shared_image_bbox_source'] ?? null);
            $t->same('translated_and_normalized_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same(['width' => 612, 'height' => 792], $coordinateReview['page_image_normalization_size'] ?? null);
            $t->same('normalized_page_image', $coordinateReview['source_coordinate_spaces']['rows'] ?? null);
            $t->same('normalized_page_image', $coordinateReview['source_coordinate_spaces']['cells'] ?? null);
            $t->same(3, $coordinateReview['normalized_row_band_count'] ?? null);
            $t->same(6, $coordinateReview['normalized_cell_count'] ?? null);
            $t->same(6, $cropReview['cell_count'] ?? null);
            $t->same(4, $cropReview['active_cell_count'] ?? null);
            $t->same(2, $cropReview['excluded_cell_count'] ?? null);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], array_column($metadata['table_assigned_cells'][0] ?? [], 'text'));
            $t->same([10.0, 5.0, 90.0, 20.0], markerpdf_page_result_shared_image_bbox_round($assignedByText['Feature']['bbox'] ?? []));
            $t->same([82.0, 155.0, 162.0, 170.0], markerpdf_page_result_shared_image_bbox_round($assignedByText['Feature']['source_page_image_bbox'] ?? []));
            $t->same('normalized_page_image', $assignedByText['Feature']['source_coordinate_space'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
