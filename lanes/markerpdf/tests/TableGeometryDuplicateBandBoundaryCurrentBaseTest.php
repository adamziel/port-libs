<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_duplicate_band_boundary_page(array $lines): array
{
    return [
        'page' => 0,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'rotation' => 0,
        'blocks' => array_map(
            static fn (array $line): array => [
                'lines' => [[
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
                ]],
            ],
            $lines
        ),
    ];
}

function markerpdf_duplicate_band_boundary_table(): array
{
    return [
        'rows' => [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 30.0]],
            ['row_id' => 0, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
            ['row_id' => 1, 'bbox' => [0.0, 80.0, 240.0, 110.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 120.0]],
            ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 120.0]],
            ['col_id' => 1, 'bbox' => [40.0, 0.0, 110.0, 120.0]],
        ],
        'cells' => [
            ['bbox' => [10.0, 5.0, 90.0, 24.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [130.0, 5.0, 230.0, 24.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [10.0, 85.0, 90.0, 104.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [130.0, 85.0, 230.0, 104.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => [10.0, 45.0, 90.0, 64.0], 'text' => 'Duplicate row decoy', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [50.0, 85.0, 105.0, 104.0], 'text' => 'Duplicate column decoy', 'row_ids' => [1], 'col_ids' => [1]],
        ],
    ];
}

return [
    'excludes duplicate active row and column bands from supplied table geometry' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_duplicate_band_boundary_table()],
            [['width' => 240, 'height' => 120]]
        );

        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedTexts = array_column($assigned, 'text');
        $bandReview = $formatted['assigned_band_boundary_reviews'][0] ?? [];
        $reviewByText = [];
        foreach (($bandReview['cells'] ?? []) as $row) {
            $reviewByText[(string) ($row['text'] ?? '')] = $row;
        }
        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $formatted['recognized_tables'][0]['rows'] ?? [],
            $formatted['recognized_tables'][0]['cols'] ?? [],
            ['width' => 240, 'height' => 120]
        );
        $geometryReview = $gridReview['geometry_boundary_review'] ?? [];

        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Duplicate row decoy', $assignedTexts, true));
        $t->true(!in_array('Duplicate column decoy', $assignedTexts, true));
        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0] ?? '');
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0] ?? '');
        $t->true(!str_contains($formatted['markdown_tables'][0] ?? '', 'Duplicate row decoy'));
        $t->true(!str_contains($formatted['markdown_tables'][0] ?? '', 'Duplicate column decoy'));

        $t->same('table_assigned_band_geometry_boundary', $bandReview['review_target'] ?? null);
        $t->same([0, 1], $bandReview['active_row_ids'] ?? null);
        $t->same([0, 1], $bandReview['active_col_ids'] ?? null);
        $t->same(6, $bandReview['cell_count'] ?? null);
        $t->same(4, $bandReview['active_cell_count'] ?? null);
        $t->same(2, $bandReview['excluded_cell_count'] ?? null);
        $t->same('excluded_duplicate_row_band', $reviewByText['Duplicate row decoy']['status'] ?? null);
        $t->same('excluded_duplicate_column_band', $reviewByText['Duplicate column decoy']['status'] ?? null);

        $t->same('table_grid_geometry_boundary', $geometryReview['review_target'] ?? null);
        $t->same(2, $geometryReview['duplicate_band_count'] ?? null);
        $t->same(2, $geometryReview['excluded_band_count'] ?? null);
        $t->same('excluded_duplicate_row_id', $geometryReview['row_bands'][1]['status'] ?? null);
        $t->same(0, $geometryReview['row_bands'][1]['duplicate_of_id'] ?? null);
        $t->same('excluded_duplicate_column_id', $geometryReview['col_bands'][2]['status'] ?? null);
        $t->same(1, $geometryReview['col_bands'][2]['duplicate_of_id'] ?? null);
        $t->same([0, 1], $gridReview['rows'] ?? null);
        $t->same([0, 1], $gridReview['cols'] ?? null);
        $t->same('Feature', $gridReview['grid_cells'][0]['text'] ?? null);
        $t->same('Status', $gridReview['grid_cells'][1]['text'] ?? null);
        $t->same('Images', $gridReview['grid_cells'][2]['text'] ?? null);
        $t->same('Ready', $gridReview['grid_cells'][3]['text'] ?? null);
    },
    'surfaces duplicate band exclusion through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-duplicate-band-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% duplicate table band geometry boundary fixture\n%%EOF");
        try {
            $document = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_duplicate_band_boundary_page([
                        ['text' => 'Duplicate band table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale duplicate band table line should be replaced.', 'bbox' => [92.0, 196.0, 292.0, 216.0]],
                        ['text' => 'After duplicate band table.', 'bbox' => [72.0, 300.0, 480.0, 318.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 270.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 300.0, 480.0, 318.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_duplicate_band_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $assignedTexts = array_column($document['metadata']['table_assigned_cells'][0] ?? [], 'text');
            $bandReview = $document['metadata']['table_assigned_band_boundary_reviews'][0] ?? [];
            $gridReview = $document['metadata']['table_spanning_grid_review'][0] ?? [];
            $geometryReview = $gridReview['geometry_boundary_review'] ?? [];

            $t->contains('# Duplicate Band Table Boundary', $document['text']);
            $t->contains('| Feature | Status |', $document['text']);
            $t->contains('| Images  | Ready  |', $document['text']);
            $t->contains('After duplicate band table.', $document['text']);
            $t->true(!str_contains($document['text'], 'Stale duplicate band table line should be replaced.'));
            $t->true(!str_contains($document['text'], 'Duplicate row decoy'));
            $t->true(!str_contains($document['text'], 'Duplicate column decoy'));
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $document['metadata']['supplied_boundaries'] ?? null);
            $t->same(2, $bandReview['excluded_cell_count'] ?? null);
            $t->same(2, $geometryReview['duplicate_band_count'] ?? null);
            $t->same('excluded_duplicate_row_id', $geometryReview['row_bands'][1]['status'] ?? null);
            $t->same('excluded_duplicate_column_id', $geometryReview['col_bands'][2]['status'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
