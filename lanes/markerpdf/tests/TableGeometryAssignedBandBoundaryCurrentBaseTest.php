<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_assigned_band_boundary_table_fixture(): array
{
    return [
        'rows' => [
            ['row_id' => 20, 'bbox' => [0.0, 0.0, 240.0, 24.0]],
            ['row_id' => -5, 'bbox' => [0.0, 32.0, 240.0, 54.0]],
            ['row_id' => 7, 'bbox' => [0.0, 62.0, 240.0, 78.0]],
            ['row_id' => 99, 'bbox' => [0.0, 98.0, 240.0, 126.0]],
        ],
        'cols' => [
            ['col_id' => 100, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
            ['col_id' => -10, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
            ['col_id' => 99, 'bbox' => [264.0, 0.0, 304.0, 80.0]],
        ],
        'cells' => [
            ['bbox' => [10.0, 5.0, 90.0, 20.0], 'text' => 'Feature', 'row_ids' => [20], 'col_ids' => [100]],
            ['bbox' => [130.0, 5.0, 230.0, 20.0], 'text' => 'Status', 'row_ids' => [20], 'col_ids' => [-10]],
            ['bbox' => [10.0, 36.0, 90.0, 50.0], 'text' => 'Images', 'row_ids' => [-5], 'col_ids' => [100]],
            ['bbox' => [130.0, 36.0, 230.0, 50.0], 'text' => 'Ready', 'row_ids' => [-5], 'col_ids' => [-10]],
            ['bbox' => [205.0, 36.0, 235.0, 50.0], 'text' => 'Ghost column', 'row_ids' => [-5], 'col_ids' => [99]],
            ['bbox' => [10.0, 52.0, 90.0, 68.0], 'text' => 'Ghost row', 'row_ids' => [99], 'col_ids' => [100]],
            ['bbox' => [0.0, 64.0, 238.0, 76.0], 'text' => 'Wide Note', 'row_ids' => [7], 'col_ids' => [100, -10, 99]],
        ],
    ];
}

function markerpdf_assigned_band_boundary_page(array $lines): array
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
    'bounds already assigned table cells to active crop-local row and column bands' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_assigned_band_boundary_table_fixture()],
            [['width' => 240, 'height' => 80]]
        );

        $assigned = $formatted['assigned_cells'][0];
        $assignedTexts = array_column($assigned, 'text');
        $bandReview = $formatted['assigned_band_boundary_reviews'][0] ?? [];
        $reviewByText = [];
        foreach (($bandReview['cells'] ?? []) as $row) {
            $reviewByText[$row['text']] = $row;
        }
        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $formatted['recognized_tables'][0]['rows'],
            $formatted['recognized_tables'][0]['cols'],
            ['width' => 240, 'height' => 80]
        );

        $t->same(['Feature', 'Status', 'Images', 'Ready', 'Wide Note'], $assignedTexts);
        $t->true(!in_array('Ghost column', $assignedTexts, true));
        $t->true(!in_array('Ghost row', $assignedTexts, true));
        $t->same('table_assigned_band_geometry_boundary', $bandReview['review_target'] ?? null);
        $t->same(['width' => 240, 'height' => 80], $bandReview['image_size'] ?? null);
        $t->same([20, -5, 7], $bandReview['active_row_ids'] ?? null);
        $t->same([100, -10], $bandReview['active_col_ids'] ?? null);
        $t->same(7, $bandReview['cell_count'] ?? null);
        $t->same(5, $bandReview['active_cell_count'] ?? null);
        $t->same(1, $bandReview['trimmed_cell_count'] ?? null);
        $t->same(2, $bandReview['excluded_cell_count'] ?? null);
        $t->same('excluded_inactive_column_band', $reviewByText['Ghost column']['status'] ?? null);
        $t->same('excluded_inactive_row_band', $reviewByText['Ghost row']['status'] ?? null);
        $t->same('trimmed_to_active_bands', $reviewByText['Wide Note']['status'] ?? null);
        $t->same([100, -10, 99], $reviewByText['Wide Note']['original_col_ids'] ?? null);
        $t->same([100, -10], $reviewByText['Wide Note']['bounded_col_ids'] ?? null);
        $t->contains('| Feature   | Status |', $formatted['markdown_tables'][0]);
        $t->contains('| Images    | Ready  |', $formatted['markdown_tables'][0]);
        $t->contains('| Wide Note |        |', $formatted['markdown_tables'][0]);
        $t->true(!str_contains($formatted['markdown_tables'][0], 'Ghost column'));
        $t->true(!str_contains($formatted['markdown_tables'][0], 'Ghost row'));
        $t->same([20, -5, 7], $gridReview['rows'] ?? null);
        $t->same([100, -10], $gridReview['cols'] ?? null);
        $t->same([100, -10], $gridReview['render_cells'][4]['col_ids'] ?? null);
        $t->same(2, $gridReview['render_cells'][4]['colspan'] ?? null);
        $t->same([0], $assigned[0]['row_geometry_orders'] ?? null);
        $t->same([0], $assigned[2]['col_geometry_orders'] ?? null);
        $t->same([0, 1], $assigned[4]['col_geometry_orders'] ?? null);
    },
    'surfaces assigned band boundary metadata through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-assigned-band-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% assigned band table geometry boundary fixture\n%%EOF");
        try {
            $document = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_assigned_band_boundary_page([
                        ['text' => 'Assigned band table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale assigned band table line should be replaced.', 'bbox' => [72.0, 176.0, 330.0, 196.0]],
                        ['text' => 'After assigned band table.', 'bbox' => [72.0, 260.0, 430.0, 278.0]],
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
                    'recognized_tables' => [markerpdf_assigned_band_boundary_table_fixture()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $assignedTexts = array_column($document['metadata']['table_assigned_cells'][0] ?? [], 'text');
            $bandReview = $document['metadata']['table_assigned_band_boundary_reviews'][0] ?? [];

            $t->contains('# Assigned Band Table Boundary', $document['text']);
            $t->contains('| Feature   | Status |', $document['text']);
            $t->contains('| Images    | Ready  |', $document['text']);
            $t->contains('| Wide Note |        |', $document['text']);
            $t->contains('After assigned band table.', $document['text']);
            $t->true(!str_contains($document['text'], 'Stale assigned band table line should be replaced.'));
            $t->true(!str_contains($document['text'], 'Ghost column'));
            $t->true(!str_contains($document['text'], 'Ghost row'));
            $t->same(['Feature', 'Status', 'Images', 'Ready', 'Wide Note'], $assignedTexts);
            $t->same('table_assigned_band_geometry_boundary', $bandReview['review_target'] ?? null);
            $t->same(1, $bandReview['trimmed_cell_count'] ?? null);
            $t->same(2, $bandReview['excluded_cell_count'] ?? null);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $document['metadata']['supplied_boundaries'] ?? null);
            $t->same(false, $document['metadata']['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
