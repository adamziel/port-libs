<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_band_id_boundary_table_fixture(): array
{
    return [
        'rows' => [
            ['row_id' => '0 0 R', 'bbox' => [0.0, 0.0, 240.0, 30.0]],
            ['row_id' => 0, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
            ['row_id' => 1, 'bbox' => [0.0, 80.0, 240.0, 110.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 120.0]],
            ['col_id' => '1 0 R', 'bbox' => [40.0, 0.0, 110.0, 120.0]],
            ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 120.0]],
        ],
        'cells' => [
            ['bbox' => [10.0, 45.0, 90.0, 64.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [130.0, 45.0, 230.0, 64.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [10.0, 85.0, 90.0, 104.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [130.0, 85.0, 230.0, 104.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ],
        'table_bbox' => [0.0, 0.0, 240.0, 120.0],
    ];
}

function markerpdf_band_id_boundary_page(array $lines): array
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
    'rejects malformed row and column band ids before duplicate band shadowing' => static function (
        TestRunner $t
    ): void {
        $fixture = markerpdf_band_id_boundary_table_fixture();
        $recognizer = new TableRecognizer();

        $formatted = $recognizer->formatRecognizedTables(
            [$fixture],
            [['width' => 240, 'height' => 120]]
        );
        $gridReview = $recognizer->spanningGridReview(
            $fixture['cells'],
            $fixture['rows'],
            $fixture['cols'],
            ['width' => 240, 'height' => 120]
        );

        $assignedTexts = array_column($formatted['assigned_cells'][0] ?? [], 'text');

        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0] ?? '');
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0] ?? '');

        $bandReview = $formatted['assigned_band_boundary_reviews'][0] ?? [];
        $t->same([0, 1], $bandReview['active_row_ids'] ?? null);
        $t->same([0, 1], $bandReview['active_col_ids'] ?? null);
        $t->same(4, $bandReview['active_cell_count'] ?? null);
        $t->same(0, $bandReview['duplicate_band_excluded_cell_count'] ?? null);
        $t->same(0, $bandReview['excluded_cell_count'] ?? null);

        $geometryReview = $gridReview['geometry_boundary_review'] ?? [];
        $t->same('excluded_invalid_row_id', $geometryReview['row_bands'][0]['status'] ?? null);
        $t->same('0 0 R', $geometryReview['row_bands'][0]['raw_id'] ?? null);
        $t->same(true, $geometryReview['row_bands'][0]['invalid_id'] ?? null);
        $t->same('excluded_invalid_column_id', $geometryReview['col_bands'][1]['status'] ?? null);
        $t->same('1 0 R', $geometryReview['col_bands'][1]['raw_id'] ?? null);
        $t->same(true, $geometryReview['col_bands'][1]['invalid_id'] ?? null);
        $t->same([0, 1], $geometryReview['active_row_ids'] ?? null);
        $t->same([0, 1], $geometryReview['active_col_ids'] ?? null);
    },

    'surfaces malformed band id rejection through supplied WordPress conversion metadata' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-band-id-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% band id table geometry boundary fixture\n%%EOF");
        try {
            $document = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_band_id_boundary_page([
                        ['text' => 'Band id table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale malformed band table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                        ['text' => 'After malformed band table.', 'bbox' => [72.0, 284.0, 430.0, 302.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 270.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 284.0, 430.0, 302.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_band_id_boundary_table_fixture()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $assignedTexts = array_column($document['metadata']['table_assigned_cells'][0] ?? [], 'text');
            $bandReview = $document['metadata']['table_assigned_band_boundary_reviews'][0] ?? [];
            $spanningReview = $document['metadata']['table_spanning_grid_review'][0] ?? [];
            $geometryReview = $spanningReview['geometry_boundary_review'] ?? [];

            $t->contains('# Band Id Table Boundary', $document['text']);
            $t->contains('| Feature | Status |', $document['text']);
            $t->contains('| Images  | Ready  |', $document['text']);
            $t->contains('After malformed band table.', $document['text']);
            $t->true(!str_contains($document['text'], 'Stale malformed band table line should be replaced.'));
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same(4, $bandReview['active_cell_count'] ?? null);
            $t->same('excluded_invalid_row_id', $geometryReview['row_bands'][0]['status'] ?? null);
            $t->same('excluded_invalid_column_id', $geometryReview['col_bands'][1]['status'] ?? null);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $document['metadata']['supplied_boundaries'] ?? null);
            $t->same(false, $document['metadata']['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
