<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

$precomputedBlocksPdftextPage = static function (array $lines): array {
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
    'accepts precomputed get_table_blocks cells as table crop local geometry' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $cells = $recognizer->getCells(
            [[100.0, 200.0, 500.0, 320.0]],
            [['width' => 1000, 'height' => 800]],
            [[
                'table_blocks_coordinate_space' => 'table_crop',
                'table_blocks' => [
                    ['bbox' => [-8.0, 10.0, 45.0, 24.0], 'text' => 'Margin'],
                    ['bbox' => [150.0, 10.0, 194.0, 24.0], 'text' => 'Value'],
                ],
            ]]
        );

        $review = $cells['table_text_cell_boundary_reviews'][0] ?? [];

        $t->same([false], $cells['needs_ocr']);
        $t->same(['Margin', 'Value'], array_column($cells['table_cells'][0], 'text'));
        $t->same([-8.0, 10.0, 45.0, 24.0], $cells['table_cells'][0][0]['bbox']);
        $t->same([150.0, 10.0, 194.0, 24.0], $cells['table_cells'][0][1]['bbox']);
        $t->same('table_text_cell_geometry_boundary', $review['review_target'] ?? null);
        $t->same('precomputed_get_table_blocks', $review['source'] ?? null);
        $t->same('surya.input.pdflines.get_table_blocks', $review['upstream_boundary'] ?? null);
        $t->same(['width' => 400, 'height' => 120], $review['table_crop_size'] ?? null);
        $t->same(2, $review['cell_count'] ?? null);
        $t->same(1, $review['clipped_cell_count'] ?? null);
        $t->same(0, $review['outside_cell_count'] ?? null);
        $t->same('clipped_to_table_image', $review['cells'][0]['status'] ?? null);
        $t->same([0.0, 10.0, 45.0, 24.0], $review['cells'][0]['bounded_bbox'] ?? null);
        $t->same(true, $review['cells'][0]['upstream_cell_bbox_retained'] ?? null);
    },
    'surfaces precomputed crop-local table blocks through supplied WordPress conversion' => static function (
        TestRunner $t
    ) use ($precomputedBlocksPdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-precomputed-blocks-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table precomputed blocks boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $precomputedBlocksPdftextPage([
                        ['text' => 'Precomputed table block boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale precomputed table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                        ['text' => 'After precomputed block review.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [[
                        'rows' => [
                            ['row_id' => 0, 'bbox' => [0.0, 0.0, 358.0, 32.0]],
                        ],
                        'cols' => [
                            ['col_id' => 0, 'bbox' => [0.0, 0.0, 170.0, 80.0]],
                            ['col_id' => 1, 'bbox' => [180.0, 0.0, 358.0, 80.0]],
                        ],
                    ]],
                    'table_text_lines' => [[
                        'table_blocks_coordinate_space' => 'table_crop',
                        'table_blocks' => [
                            ['bbox' => [-6.0, 10.0, 47.0, 24.0], 'text' => 'Margin'],
                            ['bbox' => [180.0, 10.0, 224.0, 24.0], 'text' => 'Value'],
                        ],
                    ]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $review = $result['metadata']['table_text_cell_boundary_reviews'][0] ?? [];

            $t->contains('# Precomputed Table Block Boundary', $result['text']);
            $t->contains('| Margin | Value |', $result['text']);
            $t->contains('After precomputed block review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale precomputed table line should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([false], $result['metadata']['table_needs_ocr']);
            $t->same([2], $result['metadata']['table_cell_counts']);
            $t->same([-6.0, 10.0, 47.0, 24.0], $result['metadata']['table_assigned_cells'][0][0]['bbox']);
            $t->same('precomputed_get_table_blocks', $review['source'] ?? null);
            $t->same(['width' => 358, 'height' => 80], $review['table_crop_size'] ?? null);
            $t->same(1, $review['clipped_cell_count'] ?? null);
            $t->same([0.0, 10.0, 47.0, 24.0], $review['cells'][0]['bounded_bbox'] ?? null);
            $t->same(false, $result['metadata']['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
