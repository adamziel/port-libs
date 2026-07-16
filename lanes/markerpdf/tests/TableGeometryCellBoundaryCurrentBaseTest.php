<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

$cellBoundaryPdftextPage = static function (array $lines): array {
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

return [
    'adds table cell crop-boundary review to span grid metadata' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $rows = [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 32.0]],
            ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
            ['row_id' => 2, 'bbox' => [0.0, 92.0, 240.0, 112.0]],
        ];
        $cols = [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
            ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
            ['col_id' => 2, 'bbox' => [260.0, 0.0, 280.0, 80.0]],
        ];
        $assigned = [
            ['bbox' => [-8.0, 4.0, 230.0, 20.0], 'text' => 'Header', 'row_ids' => [0], 'col_ids' => [0, 1]],
            ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [130.0, 45.0, 250.0, 65.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => [262.0, 94.0, 278.0, 108.0], 'text' => 'Outside decoy', 'row_ids' => [2], 'col_ids' => [2]],
        ];

        $review = $recognizer->spanningGridReview($assigned, $rows, $cols, ['width' => 240, 'height' => 80]);
        $boundary = $review['cell_geometry_boundary_review'] ?? [];
        $renderByText = [];
        foreach ($review['render_cells'] as $renderCell) {
            $renderByText[$renderCell['text']] = $renderCell;
        }

        $t->same('table_cell_geometry_boundary', $boundary['review_target'] ?? null);
        $t->same(['width' => 240, 'height' => 80], $boundary['image_size'] ?? null);
        $t->same(4, $boundary['cell_count'] ?? null);
        $t->same(3, $boundary['active_cell_count'] ?? null);
        $t->same(1, $boundary['within_cell_count'] ?? null);
        $t->same(2, $boundary['clipped_cell_count'] ?? null);
        $t->same(1, $boundary['excluded_cell_count'] ?? null);
        $t->same('clipped_to_table_image', $boundary['cells'][0]['status'] ?? null);
        $t->same([-8.0, 4.0, 230.0, 20.0], $boundary['cells'][0]['original_bbox'] ?? null);
        $t->same([0.0, 4.0, 230.0, 20.0], $boundary['cells'][0]['bounded_bbox'] ?? null);
        $t->same([0], $boundary['cells'][0]['row_ids'] ?? null);
        $t->same([0, 1], $boundary['cells'][0]['col_ids'] ?? null);
        $t->same('within_table_image', $boundary['cells'][1]['status'] ?? null);
        $t->same('clipped_to_table_image', $boundary['cells'][2]['status'] ?? null);
        $t->same([130.0, 45.0, 240.0, 65.0], $boundary['cells'][2]['bounded_bbox'] ?? null);
        $t->same('excluded_outside_table_image', $boundary['cells'][3]['status'] ?? null);
        $t->same(null, $boundary['cells'][3]['bounded_bbox'] ?? null);
        $t->same(true, $boundary['cells'][3]['upstream_cell_bbox_retained'] ?? null);

        $t->same('clipped_to_table_image', $renderByText['Header']['cell_boundary_status'] ?? null);
        $t->same([0.0, 4.0, 230.0, 20.0], $renderByText['Header']['bounded_cell_bbox'] ?? null);
        $t->same('within_table_image', $renderByText['Images']['cell_boundary_status'] ?? null);
        $t->same('clipped_to_table_image', $renderByText['Ready']['cell_boundary_status'] ?? null);
        $t->same([130.0, 45.0, 240.0, 65.0], $renderByText['Ready']['bounded_cell_bbox'] ?? null);
        $t->same('excluded_outside_table_image', $renderByText['Outside decoy']['cell_boundary_status'] ?? null);
        $t->true(!array_key_exists('bounded_cell_bbox', $renderByText['Outside decoy']));
    },
    'surfaces table cell boundary metadata through supplied WordPress conversion' => static function (TestRunner $t) use ($cellBoundaryPdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-cell-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table cell boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $cellBoundaryPdftextPage([
                        ['text' => 'Table cell boundary review', 'bbox' => [72.0, 48.0, 430.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale crop-edge table line should be replaced.', 'bbox' => [72.0, 176.0, 300.0, 196.0]],
                        ['text' => 'After cell geometry review.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 430.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [[
                        'rows' => [
                            ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 32.0]],
                            ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
                        ],
                        'cols' => [
                            ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
                            ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
                        ],
                        'cells' => [
                            ['bbox' => [-6.0, 4.0, 100.0, 20.0], 'text' => 'Header'],
                            ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => 'Images'],
                            ['bbox' => [130.0, 45.0, 250.0, 65.0], 'text' => 'Ready'],
                        ],
                    ]],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $boundary = $gridReview['cell_geometry_boundary_review'] ?? [];

            $t->contains('# Table Cell Boundary Review', $result['text']);
            $t->contains('| Header |       |', $result['text']);
            $t->contains('| Images | Ready |', $result['text']);
            $t->contains('After cell geometry review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale crop-edge table line should be replaced.'));
            $t->same('table_cell_geometry_boundary', $boundary['review_target'] ?? null);
            $t->same(['width' => 240, 'height' => 80], $boundary['image_size'] ?? null);
            $t->same(3, $boundary['cell_count'] ?? null);
            $t->same(2, $boundary['clipped_cell_count'] ?? null);
            $t->same([0.0, 4.0, 100.0, 20.0], $gridReview['render_cells'][0]['bounded_cell_bbox'] ?? null);
            $t->same([130.0, 45.0, 240.0, 65.0], $gridReview['render_cells'][2]['bounded_cell_bbox'] ?? null);
            $t->same(false, $result['metadata']['context']['filetype'] !== 'pdf');
        } finally {
            unlink($path);
        }
    },
];
