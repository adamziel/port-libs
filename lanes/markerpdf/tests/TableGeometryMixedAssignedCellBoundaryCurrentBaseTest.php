<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_mixed_assigned_cell_boundary_table(): array
{
    return [
        'rows' => [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 32.0]],
            ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 72.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
            ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
        ],
        'cells' => [
            ['bbox' => [10.0, 5.0, 90.0, 22.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0], 'order' => 0],
            ['bbox' => [130.0, 5.0, 230.0, 22.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1], 'order' => 1],
            ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0], 'order' => 2],
            ['bbox' => [130.0, 45.0, 230.0, 65.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1], 'order' => 3],
            ['bbox' => [132.0, 46.0, 232.0, 66.0], 'text' => 'Stale unassigned sidecar', 'row_ids' => [null], 'col_ids' => [1], 'order' => 4],
        ],
    ];
}

function markerpdf_mixed_assigned_cell_boundary_page(array $lines): array
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
    'filters mixed saved table assignment cells without rerunning detector assignment' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_mixed_assigned_cell_boundary_table()],
            [['width' => 240, 'height' => 80]]
        );

        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedTexts = array_column($assigned, 'text');
        $sourceReview = $formatted['assigned_source_boundary_reviews'][0] ?? [];
        $rejected = $sourceReview['rejected_cells'][0] ?? [];
        $cropReview = $formatted['assigned_crop_boundary_reviews'][0] ?? [];
        $bandReview = $formatted['assigned_band_boundary_reviews'][0] ?? [];

        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale unassigned sidecar', $assignedTexts, true));
        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0] ?? '');
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0] ?? '');
        $t->true(!str_contains($formatted['markdown_tables'][0] ?? '', 'Stale unassigned sidecar'));
        $t->same('table_assigned_cell_source_boundary', $sourceReview['review_target'] ?? null);
        $t->same('tabled.schema.ExtractPageResult.cells_after_assign_rows_columns', $sourceReview['upstream_boundary'] ?? null);
        $t->same(5, $sourceReview['cell_count'] ?? null);
        $t->same(4, $sourceReview['assigned_cell_count'] ?? null);
        $t->same(1, $sourceReview['rejected_cell_count'] ?? null);
        $t->same('Stale unassigned sidecar', $rejected['text'] ?? null);
        $t->same('rejected_missing_row_assignment_anchor', $rejected['status'] ?? null);
        $t->same([null], $rejected['row_ids'] ?? null);
        $t->same([1], $rejected['col_ids'] ?? null);
        $t->same(4, $cropReview['cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(4, $bandReview['active_cell_count'] ?? null);
    },
    'surfaces mixed assigned table cell boundary through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-mixed-assigned-cell-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% mixed assigned table cell boundary fixture\n%%EOF");
        try {
            $document = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_mixed_assigned_cell_boundary_page([
                        ['text' => 'Mixed assigned table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale mixed assigned table line should be replaced.', 'bbox' => [72.0, 176.0, 300.0, 196.0]],
                        ['text' => 'After mixed assigned table.', 'bbox' => [72.0, 260.0, 430.0, 278.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 260.0, 430.0, 278.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_mixed_assigned_cell_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $sourceReview = $document['metadata']['table_assigned_source_boundary_reviews'][0] ?? [];
            $assignedTexts = array_column($document['metadata']['table_assigned_cells'][0] ?? [], 'text');

            $t->contains('# Mixed Assigned Table Boundary', $document['text']);
            $t->contains('| Feature | Status |', $document['text']);
            $t->contains('| Images  | Ready  |', $document['text']);
            $t->contains('After mixed assigned table.', $document['text']);
            $t->true(!str_contains($document['text'], 'Stale mixed assigned table line should be replaced.'));
            $t->true(!str_contains($document['text'], 'Stale unassigned sidecar'));
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same('table_assigned_cell_source_boundary', $sourceReview['review_target'] ?? null);
            $t->same(1, $sourceReview['rejected_cell_count'] ?? null);
            $t->same('rejected_missing_row_assignment_anchor', $sourceReview['rejected_cells'][0]['status'] ?? null);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $document['metadata']['supplied_boundaries'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
