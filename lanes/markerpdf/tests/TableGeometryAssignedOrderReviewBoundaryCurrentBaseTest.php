<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_assigned_order_review_boundary_table(): array
{
    return [
        'rows' => [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 260.0, 30.0]],
            ['row_id' => 1, 'bbox' => [0.0, 40.0, 260.0, 70.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 120.0, 80.0]],
            ['col_id' => 1, 'bbox' => [140.0, 0.0, 260.0, 80.0]],
        ],
        'cells' => [
            ['bbox' => [12.0, 5.0, 96.0, 24.0], 'text' => 'Header B', 'row_ids' => [0], 'col_ids' => [0, 1], 'order' => 1],
            ['bbox' => [152.0, 5.0, 248.0, 24.0], 'text' => 'Header A', 'row_ids' => [0], 'col_ids' => [0, 1], 'order' => 0],
            ['bbox' => [12.0, 45.0, 96.0, 64.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0], 'order' => 2],
            ['bbox' => [152.0, 45.0, 248.0, 64.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1], 'order' => 3],
        ],
    ];
}

function markerpdf_assigned_order_review_boundary_page(array $lines): array
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

return [
    'exposes supplied source order on spanning-grid grouped review cells' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $table = markerpdf_assigned_order_review_boundary_table();
        $formatted = $recognizer->formatRecognizedTables([$table], [['width' => 260, 'height' => 80]]);
        $review = $recognizer->spanningGridReview(
            $formatted['assigned_cells'][0] ?? [],
            $table['rows'],
            $table['cols'],
            ['width' => 260, 'height' => 80]
        );

        $headerCell = $review['render_cells'][0] ?? [];
        $anchorGridCell = $review['grid_cells'][0] ?? [];

        $t->same('Header A Header B', $headerCell['text'] ?? null);
        $t->same(2, $headerCell['source_cell_count'] ?? null);
        $t->same(['Header A', 'Header B'], $headerCell['text_parts'] ?? null);
        $t->same([0, 1], $headerCell['source_orders'] ?? null);
        $t->same(0, $headerCell['anchor_cell_order'] ?? null);
        $t->same(1, $headerCell['continuation_cells'][0]['order'] ?? null);
        $t->same([0, 1], $anchorGridCell['source_orders'] ?? null);
        $t->same(0, $anchorGridCell['anchor_cell_order'] ?? null);
        $t->same("| Header A Header B |       |\n|-------------------|-------|\n| Images            | Ready |", $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces assigned source order review metadata through WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-assigned-order-review-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table assigned order review boundary fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_assigned_order_review_boundary_page([
                        ['text' => 'Assigned order review table', 'bbox' => [72.0, 48.0, 430.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale assigned-order table text should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                        ['text' => 'After assigned order review metadata.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 430.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 332.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_assigned_order_review_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $headerCell = $gridReview['render_cells'][0] ?? [];
            $anchorGridCell = $gridReview['grid_cells'][0] ?? [];

            $t->contains('# Assigned Order Review Table', $result['text']);
            $t->contains('| Header A Header B |', $result['text']);
            $t->contains('| Images            | Ready |', $result['text']);
            $t->contains('After assigned order review metadata.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale assigned-order table text should be replaced.'));
            $t->same([0, 1], $headerCell['source_orders'] ?? null);
            $t->same(0, $headerCell['anchor_cell_order'] ?? null);
            $t->same(1, $headerCell['continuation_cells'][0]['order'] ?? null);
            $t->same([0, 1], $anchorGridCell['source_orders'] ?? null);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries'] ?? null);
        } finally {
            unlink($path);
        }
    },
];
