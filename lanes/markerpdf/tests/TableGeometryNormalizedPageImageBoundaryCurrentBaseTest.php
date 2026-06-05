<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

$normalizedPageImageBbox = static function (array $bbox): array {
    return [
        round(((float) $bbox[0] / 612.0) * 1000.0, 6),
        round(((float) $bbox[1] / 792.0) * 1000.0, 6),
        round(((float) $bbox[2] / 612.0) * 1000.0, 6),
        round(((float) $bbox[3] / 792.0) * 1000.0, 6),
    ];
};

$roundBbox = static fn (array $bbox): array => array_map(
    static fn (mixed $value): float => round((float) $value, 1),
    $bbox
);

$roundScale = static fn (array $scale): array => [
    'x' => round((float) ($scale['x'] ?? 0.0), 3),
    'y' => round((float) ($scale['y'] ?? 0.0), 3),
];

$normalizedPageImagePdftextPage = static function (array $lines): array {
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
};

$normalizedPageImageRecognizedTable = static function () use ($normalizedPageImageBbox): array {
    return [
        'coordinate_space' => 'normalized_page_image',
        'bbox' => [72.0, 150.0, 312.0, 230.0],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'rows' => [
            ['row_id' => 0, 'bbox' => $normalizedPageImageBbox([72.0, 150.0, 312.0, 182.0])],
            ['row_id' => 1, 'bbox' => $normalizedPageImageBbox([72.0, 190.0, 312.0, 220.0])],
            ['row_id' => 99, 'bbox' => $normalizedPageImageBbox([72.0, 250.0, 312.0, 270.0])],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => $normalizedPageImageBbox([72.0, 150.0, 172.0, 230.0])],
            ['col_id' => 1, 'bbox' => $normalizedPageImageBbox([192.0, 150.0, 312.0, 230.0])],
            ['col_id' => 99, 'bbox' => $normalizedPageImageBbox([342.0, 150.0, 362.0, 230.0])],
        ],
        'cells' => [
            ['bbox' => $normalizedPageImageBbox([82.0, 155.0, 162.0, 170.0]), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => $normalizedPageImageBbox([202.0, 155.0, 302.0, 170.0]), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => $normalizedPageImageBbox([82.0, 195.0, 162.0, 215.0]), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => $normalizedPageImageBbox([202.0, 195.0, 302.0, 215.0]), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => $normalizedPageImageBbox([82.0, 250.0, 162.0, 268.0]), 'text' => 'Stale normalized row', 'row_ids' => [99], 'col_ids' => [0]],
            ['bbox' => $normalizedPageImageBbox([360.0, 195.0, 382.0, 215.0]), 'text' => 'Stale normalized col', 'row_ids' => [1], 'col_ids' => [99]],
        ],
        'ocr_grid_border_conflicts' => [[
            'ocr_index' => 0,
            'text' => 'Wide page-normalized OCR',
            'bbox' => $normalizedPageImageBbox([82.0, 155.0, 302.0, 215.0]),
            'candidate_cell_indexes' => [0, 1, 2],
            'candidate_overlaps' => [1.0, 0.44, 0.36],
            'candidate_cell_bboxes' => [
                $normalizedPageImageBbox([82.0, 155.0, 162.0, 170.0]),
                $normalizedPageImageBbox([202.0, 155.0, 302.0, 170.0]),
                $normalizedPageImageBbox([82.0, 195.0, 162.0, 215.0]),
            ],
            'assigned_cell_index' => 0,
            'spans_grid_border' => true,
        ]],
    ];
};

return [
    'scales normalized page image table geometry before translating to the table crop' => static function (
        TestRunner $t
    ) use ($normalizedPageImageRecognizedTable, $roundBbox, $roundScale): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [$normalizedPageImageRecognizedTable()],
            [[
                'width' => 240,
                'height' => 80,
                'table_bbox' => [72.0, 150.0, 312.0, 230.0],
            ]]
        );

        $localized = $formatted['recognized_tables'][0];
        $assigned = $formatted['assigned_cells'][0];
        $assignedByText = [];
        foreach ($assigned as $cell) {
            $assignedByText[$cell['text']] = $cell;
        }
        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $cropReview = $formatted['assigned_crop_boundary_reviews'][0] ?? [];
        $conflict = $localized['ocr_grid_border_conflicts'][0] ?? [];
        $grid = $recognizer->spanningGridReview($assigned, $localized['rows'], $localized['cols'], ['width' => 240, 'height' => 80]);
        $boundary = $grid['geometry_boundary_review'] ?? [];

        $t->same('table_recognition_coordinate_space_boundary', $review['review_target'] ?? null);
        $t->same('translated_and_normalized_to_table_crop', $review['status'] ?? null);
        $t->same(['width' => 240, 'height' => 80], $review['table_crop_size'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same(['x' => 0.24, 'y' => 0.08], $review['normalization_scale'] ?? null);
        $t->same(['x' => 0.612, 'y' => 0.792], $roundScale($review['page_image_normalization_scale'] ?? []));
        $t->same(3, $review['normalized_row_band_count'] ?? null);
        $t->same(3, $review['normalized_col_band_count'] ?? null);
        $t->same(6, $review['normalized_cell_count'] ?? null);
        $t->same(1, $review['normalized_conflict_count'] ?? null);
        $t->same(3, $review['translated_row_band_count'] ?? null);
        $t->same(3, $review['translated_col_band_count'] ?? null);
        $t->same(6, $review['translated_cell_count'] ?? null);
        $t->same(1, $review['translated_conflict_count'] ?? null);
        $t->same('normalized_page_image', $review['source_coordinate_spaces']['cells'] ?? null);
        $t->same('table_crop', $localized['coordinate_space'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $roundBbox($localized['rows'][0]['bbox']));
        $t->same([120.0, 0.0, 240.0, 80.0], $roundBbox($localized['cols'][1]['bbox']));
        $t->same([10.0, 5.0, 90.0, 20.0], $roundBbox($localized['cells'][0]['bbox']));
        $t->same([82.0, 155.0, 162.0, 170.0], $roundBbox($localized['cells'][0]['source_page_image_bbox'] ?? []));
        $t->same([130.0, 45.0, 230.0, 65.0], $roundBbox($localized['cells'][3]['bbox']));
        $t->same([288.0, 45.0, 310.0, 65.0], $roundBbox($localized['cells'][5]['bbox']));
        $t->same([10.0, 5.0, 230.0, 65.0], $roundBbox($conflict['bbox'] ?? []));
        $t->same([82.0, 155.0, 302.0, 215.0], $roundBbox($conflict['source_page_image_bbox'] ?? []));
        $t->same([10.0, 5.0, 90.0, 20.0], $roundBbox($conflict['candidate_cell_bboxes'][0] ?? []));
        $t->same(['Feature', 'Status', 'Images', 'Ready'], array_column($assigned, 'text'));
        $t->same([1], $assignedByText['Ready']['row_ids']);
        $t->same([1], $assignedByText['Ready']['col_ids']);
        $t->true(!isset($assignedByText['Stale normalized row']));
        $t->true(!isset($assignedByText['Stale normalized col']));
        $t->same(6, $cropReview['cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0]);
        $t->same(2, $boundary['active_row_band_count'] ?? null);
        $t->same(2, $boundary['active_col_band_count'] ?? null);
        $t->same(2, $boundary['excluded_band_count'] ?? null);
    },
    'surfaces normalized page image table geometry through WordPress conversion metadata' => static function (
        TestRunner $t
    ) use ($normalizedPageImagePdftextPage, $normalizedPageImageRecognizedTable, $roundScale): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-normalized-page-image-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table normalized page-image geometry boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $normalizedPageImagePdftextPage([
                        ['text' => 'Normalized page image table geometry review', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale page-normalized table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                        ['text' => 'After normalized page image geometry review.', 'bbox' => [72.0, 336.0, 520.0, 354.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 520.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 336.0, 520.0, 354.0]],
                        ],
                    ]],
                    'recognized_tables' => [$normalizedPageImageRecognizedTable()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $coordinateReview = $result['metadata']['table_coordinate_space_reviews'][0] ?? [];
            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $boundary = $gridReview['geometry_boundary_review'] ?? [];
            $assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');

            $t->contains('# Normalized Page Image Table Geometry Review', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After normalized page image geometry review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale page-normalized table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale normalized row'));
            $t->true(!str_contains($result['text'], 'Stale normalized col'));
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same('table_recognition_coordinate_space_boundary', $coordinateReview['review_target'] ?? null);
            $t->same('translated_and_normalized_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same(['width' => 240, 'height' => 80], $coordinateReview['table_crop_size'] ?? null);
            $t->same(['x' => 0.612, 'y' => 0.792], $roundScale($coordinateReview['page_image_normalization_scale'] ?? []));
            $t->same(6, $coordinateReview['normalized_cell_count'] ?? null);
            $t->same(1, $coordinateReview['normalized_conflict_count'] ?? null);
            $t->same('table_grid_geometry_boundary', $boundary['review_target'] ?? null);
            $t->same(2, $boundary['active_row_band_count'] ?? null);
            $t->same(2, $boundary['active_col_band_count'] ?? null);
            $t->same(2, $boundary['excluded_band_count'] ?? null);
            $t->same([[72.0, 150.0, 312.0, 230.0]], $result['metadata']['table_plan']['table_bboxes'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
