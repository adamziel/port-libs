<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_duplicate_span_id_boundary_page(array $lines): array
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

function markerpdf_duplicate_span_id_boundary_table(): array
{
    return [
        'rows' => [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 28.0]],
            ['row_id' => 1, 'bbox' => [0.0, 36.0, 240.0, 64.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 110.0, 80.0]],
            ['col_id' => 1, 'bbox' => [130.0, 0.0, 240.0, 80.0]],
        ],
        'cells' => [
            [
                'bbox' => [8.0, 5.0, 232.0, 22.0],
                'text' => 'Feature Status',
                'row_ids' => [0, 0],
                'col_ids' => [0, 1, 1],
            ],
            [
                'bbox' => [10.0, 42.0, 96.0, 58.0],
                'text' => 'Images',
                'row_ids' => [1, 1],
                'col_ids' => [0, 0],
            ],
            [
                'bbox' => [136.0, 42.0, 224.0, 58.0],
                'text' => 'Ready',
                'row_ids' => [1],
                'col_ids' => [1, 1],
            ],
        ],
    ];
}

return [
    'deduplicates supplied SpanTableCell row and column ids before span review metadata' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_duplicate_span_id_boundary_table()],
            [['width' => 240, 'height' => 80]]
        );

        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedByText = [];
        foreach ($assigned as $cell) {
            $assignedByText[(string) ($cell['text'] ?? '')] = $cell;
        }
        $bandReview = $formatted['assigned_band_boundary_reviews'][0] ?? [];
        $bandRowsByText = [];
        foreach (($bandReview['cells'] ?? []) as $row) {
            if (is_array($row)) {
                $bandRowsByText[(string) ($row['text'] ?? '')] = $row;
            }
        }
        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $formatted['recognized_tables'][0]['rows'] ?? [],
            $formatted['recognized_tables'][0]['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );

        $t->same([0], $assignedByText['Feature Status']['row_ids'] ?? null);
        $t->same([0, 1], $assignedByText['Feature Status']['col_ids'] ?? null);
        $t->same([1], $assignedByText['Images']['row_ids'] ?? null);
        $t->same([0], $assignedByText['Images']['col_ids'] ?? null);
        $t->same([1], $assignedByText['Ready']['row_ids'] ?? null);
        $t->same([1], $assignedByText['Ready']['col_ids'] ?? null);
        $t->same('table_assigned_band_geometry_boundary', $bandReview['review_target'] ?? null);
        $t->same([0], $bandRowsByText['Feature Status']['original_row_ids'] ?? null);
        $t->same([0, 1], $bandRowsByText['Feature Status']['original_col_ids'] ?? null);
        $t->same([1], $bandRowsByText['Images']['original_row_ids'] ?? null);
        $t->same([0], $bandRowsByText['Images']['original_col_ids'] ?? null);
        $t->same([1], $bandRowsByText['Ready']['original_col_ids'] ?? null);
        $t->same(0, $bandReview['trimmed_cell_count'] ?? null);
        $t->same(0, $bandReview['excluded_cell_count'] ?? null);
        $t->same("| Feature Status |       |\n|----------------|-------|\n| Images         | Ready |", $formatted['markdown_tables'][0] ?? '');
        $t->same([0, 1], $gridReview['cols'] ?? null);
        $t->same([0, 1], $gridReview['render_cells'][0]['col_ids'] ?? null);
        $t->same(2, $gridReview['render_cells'][0]['colspan'] ?? null);
        $t->same(1, $gridReview['render_cells'][1]['rowspan'] ?? null);
        $t->same(1, $gridReview['render_cells'][2]['colspan'] ?? null);
        $t->same([
            ['row_id' => 0, 'col_id' => 0],
            ['row_id' => 0, 'col_id' => 1],
        ], $gridReview['render_cells'][0]['grid_cells'] ?? null);
    },
    'surfaces duplicate span id cleanup through supplied WordPress conversion' => static function (TestRunner $t): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-duplicate-span-id-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table duplicate span id boundary current-base fixture\n%%EOF");

        try {
            $document = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_duplicate_span_id_boundary_page([
                        ['text' => 'Duplicate span id table boundary', 'bbox' => [72.0, 48.0, 500.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale duplicate span table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                        ['text' => 'After duplicate span table.', 'bbox' => [72.0, 260.0, 430.0, 278.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 500.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 260.0, 430.0, 278.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_duplicate_span_id_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $assignedCells = $document['metadata']['table_assigned_cells'][0] ?? [];
            $gridReview = $document['metadata']['table_spanning_grid_review'][0] ?? [];
            $bandReview = $document['metadata']['table_assigned_band_boundary_reviews'][0] ?? [];

            $t->contains('# Duplicate Span Id Table Boundary', $document['text']);
            $t->contains('| Feature Status |       |', $document['text']);
            $t->contains('| Images         | Ready |', $document['text']);
            $t->contains('After duplicate span table.', $document['text']);
            $t->true(!str_contains($document['text'], 'Stale duplicate span table line should be replaced.'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $document['metadata']['supplied_boundaries'] ?? null);
            $t->same([0], $assignedCells[0]['row_ids'] ?? null);
            $t->same([0, 1], $assignedCells[0]['col_ids'] ?? null);
            $t->same(2, $gridReview['render_cells'][0]['colspan'] ?? null);
            $t->same(2, count($gridReview['render_cells'][0]['grid_cells'] ?? []));
            $t->same(0, $bandReview['trimmed_cell_count'] ?? null);
            $t->same(0, $bandReview['excluded_cell_count'] ?? null);
            $t->same(1, $document['metadata']['inserted_tables'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
