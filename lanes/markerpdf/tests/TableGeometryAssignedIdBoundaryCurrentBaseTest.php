<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_assigned_id_boundary_table_fixture(): array
{
    return [
        'rows' => [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 30.0]],
            ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
            ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
        ],
        'cells' => [
            ['bbox' => [10.0, 5.0, 90.0, 20.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [130.0, 5.0, 230.0, 20.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [130.0, 45.0, 230.0, 65.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => [10.0, 24.0, 90.0, 36.0], 'text' => 'Row Token Decoy', 'row_ids' => ['0 0 R'], 'col_ids' => [0]],
            ['bbox' => [130.0, 24.0, 230.0, 36.0], 'text' => 'Column Token Decoy', 'row_ids' => [0], 'col_ids' => ['1 0 R']],
            ['bbox' => [130.0, 50.0, 230.0, 64.0], 'text' => 'Span Tail Decoy', 'row_ids' => [1, '2 0 R'], 'col_ids' => [1]],
        ],
    ];
}

function markerpdf_assigned_id_boundary_page(array $lines): array
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
    'rejects malformed assigned row and column id tokens before table Markdown formatting' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_assigned_id_boundary_table_fixture()],
            [['width' => 240, 'height' => 80]]
        );

        $assignedTexts = array_column($formatted['assigned_cells'][0] ?? [], 'text');
        $sourceReview = $formatted['assigned_source_boundary_reviews'][0] ?? [];
        $cropReview = $formatted['assigned_crop_boundary_reviews'][0] ?? [];
        $rejectedByText = [];
        foreach (($sourceReview['rejected_cells'] ?? []) as $row) {
            $rejectedByText[(string) ($row['text'] ?? '')] = $row;
        }

        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Row Token Decoy', $assignedTexts, true));
        $t->true(!in_array('Column Token Decoy', $assignedTexts, true));
        $t->true(!in_array('Span Tail Decoy', $assignedTexts, true));
        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0] ?? '');
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0] ?? '');
        $t->true(!str_contains($formatted['markdown_tables'][0] ?? '', 'Decoy'));

        $t->same('table_assigned_cell_source_boundary', $sourceReview['review_target'] ?? null);
        $t->same('tabled.schema.ExtractPageResult.cells_after_assign_rows_columns', $sourceReview['upstream_boundary'] ?? null);
        $t->same(7, $sourceReview['cell_count'] ?? null);
        $t->same(4, $sourceReview['assigned_cell_count'] ?? null);
        $t->same(3, $sourceReview['rejected_cell_count'] ?? null);
        $t->same([0, 1, 2, 3], $sourceReview['assigned_cell_indexes'] ?? null);
        $t->same(true, $sourceReview['detector_reassignment_blocked'] ?? null);
        $t->same('rejected_invalid_row_assignment_anchor', $rejectedByText['Row Token Decoy']['status'] ?? null);
        $t->same('rejected_invalid_column_assignment_anchor', $rejectedByText['Column Token Decoy']['status'] ?? null);
        $t->same('rejected_invalid_row_assignment_anchor', $rejectedByText['Span Tail Decoy']['status'] ?? null);
        $t->same(true, $rejectedByText['Row Token Decoy']['invalid_row_assignment_anchor'] ?? null);
        $t->same(true, $rejectedByText['Column Token Decoy']['invalid_col_assignment_anchor'] ?? null);
        $t->same([1, '2 0 R'], $rejectedByText['Span Tail Decoy']['row_ids'] ?? null);
        $t->same(4, $cropReview['cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
    },
    'surfaces malformed assigned id rejection through supplied WordPress conversion metadata' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-assigned-id-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% assigned id table geometry boundary fixture\n%%EOF");
        try {
            $document = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_assigned_id_boundary_page([
                        ['text' => 'Assigned id table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale malformed id table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                        ['text' => 'After assigned id table.', 'bbox' => [72.0, 260.0, 430.0, 278.0]],
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
                    'recognized_tables' => [markerpdf_assigned_id_boundary_table_fixture()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $assignedTexts = array_column($document['metadata']['table_assigned_cells'][0] ?? [], 'text');
            $sourceReview = $document['metadata']['table_assigned_source_boundary_reviews'][0] ?? [];
            $rejectedTexts = array_column($sourceReview['rejected_cells'] ?? [], 'text');

            $t->contains('# Assigned Id Table Boundary', $document['text']);
            $t->contains('| Feature | Status |', $document['text']);
            $t->contains('| Images  | Ready  |', $document['text']);
            $t->contains('After assigned id table.', $document['text']);
            $t->true(!str_contains($document['text'], 'Stale malformed id table line should be replaced.'));
            $t->true(!str_contains($document['text'], 'Row Token Decoy'));
            $t->true(!str_contains($document['text'], 'Column Token Decoy'));
            $t->true(!str_contains($document['text'], 'Span Tail Decoy'));
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same('table_assigned_cell_source_boundary', $sourceReview['review_target'] ?? null);
            $t->same(3, $sourceReview['rejected_cell_count'] ?? null);
            $t->same(['Row Token Decoy', 'Column Token Decoy', 'Span Tail Decoy'], $rejectedTexts);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $document['metadata']['supplied_boundaries'] ?? null);
            $t->same(false, $document['metadata']['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
