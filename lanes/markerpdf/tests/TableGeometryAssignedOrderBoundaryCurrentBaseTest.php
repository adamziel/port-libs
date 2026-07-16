<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_assigned_order_boundary_table(): array
{
    return [
        'rows' => [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 30.0]],
            ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 80.0]],
        ],
        'cells' => [
            ['bbox' => [8.0, 5.0, 90.0, 24.0], 'text' => 'Header B', 'row_ids' => [0], 'col_ids' => [0], 'order' => 1],
            ['bbox' => [132.0, 5.0, 230.0, 24.0], 'text' => 'Header A', 'row_ids' => [0], 'col_ids' => [0], 'order' => 0],
            ['bbox' => [8.0, 45.0, 90.0, 64.0], 'text' => 'Second', 'row_ids' => [1], 'col_ids' => [0], 'order' => 1],
            ['bbox' => [132.0, 45.0, 230.0, 64.0], 'text' => 'First', 'row_ids' => [1], 'col_ids' => [0], 'order' => 0],
        ],
    ];
}

function markerpdf_assigned_order_boundary_page(array $lines): array
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
    'preserves supplied tabled cell order when same-anchor cell bboxes disagree' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_assigned_order_boundary_table()],
            [['width' => 240, 'height' => 80]]
        );
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $review = $recognizer->spanningGridReview(
            $assigned,
            markerpdf_assigned_order_boundary_table()['rows'],
            markerpdf_assigned_order_boundary_table()['cols'],
            ['width' => 240, 'height' => 80]
        );

        $t->same(['Header A', 'Header B', 'First', 'Second'], array_column($assigned, 'text'));
        $t->same([0, 1, 0, 1], array_column($assigned, 'order'));
        $t->same("| Header A Header B |\n|-------------------|\n| First Second      |", $formatted['markdown_tables'][0] ?? '');
        $t->same('Header A Header B', $review['render_cells'][0]['text'] ?? null);
        $t->same(['Header A', 'Header B'], $review['render_cells'][0]['text_parts'] ?? null);
        $t->same('First Second', $review['render_cells'][1]['text'] ?? null);
        $t->same(['First', 'Second'], $review['render_cells'][1]['text_parts'] ?? null);
        $t->true(!str_contains($formatted['markdown_tables'][0] ?? '', 'Header B Header A'));
        $t->true(!str_contains($formatted['markdown_tables'][0] ?? '', 'Second First'));
    },
    'surfaces supplied cell order through WordPress table conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-assigned-order-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table assigned cell order boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_assigned_order_boundary_page([
                        ['text' => 'Assigned order table review', 'bbox' => [72.0, 48.0, 430.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale geometry-order table text should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                        ['text' => 'After assigned order review.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
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
                    'recognized_tables' => [markerpdf_assigned_order_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $assigned = $result['metadata']['table_assigned_cells'][0] ?? [];

            $t->contains('# Assigned Order Table Review', $result['text']);
            $t->contains('| Header A Header B |', $result['text']);
            $t->contains('| First Second      |', $result['text']);
            $t->contains('After assigned order review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale geometry-order table text should be replaced.'));
            $t->same(['Header A', 'Header B', 'First', 'Second'], array_column($assigned, 'text'));
            $t->same([0, 1, 0, 1], array_column($assigned, 'order'));
            $t->same(['Header A', 'Header B'], $gridReview['render_cells'][0]['text_parts'] ?? null);
            $t->same(['First', 'Second'], $gridReview['render_cells'][1]['text_parts'] ?? null);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same(false, $result['metadata']['context']['filetype'] !== 'pdf');
        } finally {
            unlink($path);
        }
    },
];
