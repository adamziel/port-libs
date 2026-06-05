<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_assigned_crop_boundary_table_fixture(): array
{
    return [
        'rows' => [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 30.0]],
            ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
            ['row_id' => 99, 'bbox' => [0.0, 96.0, 240.0, 116.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
            ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
            ['col_id' => 99, 'bbox' => [260.0, 0.0, 300.0, 80.0]],
        ],
        'cells' => [
            ['bbox' => [10.0, 5.0, 90.0, 20.0], 'text' => 'Header', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [130.0, 5.0, 230.0, 20.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [130.0, 45.0, 250.0, 65.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => [250.0, 45.0, 280.0, 65.0], 'text' => 'Offcrop assigned', 'row_ids' => [1], 'col_ids' => [99]],
            ['bbox' => [10.0, 92.0, 90.0, 112.0], 'text' => 'Offcrop row assigned', 'row_ids' => [99], 'col_ids' => [0]],
        ],
    ];
}

function markerpdf_assigned_crop_boundary_page(array $lines): array
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
    'filters already assigned supplied cells with no positive crop area before Markdown formatting' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_assigned_crop_boundary_table_fixture()],
            [['width' => 240, 'height' => 80]]
        );

        $assigned = $formatted['assigned_cells'][0];
        $assignedTexts = array_column($assigned, 'text');
        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $formatted['recognized_tables'][0]['rows'],
            $formatted['recognized_tables'][0]['cols'],
            ['width' => 240, 'height' => 80]
        );
        $geometryBoundary = $gridReview['geometry_boundary_review'] ?? [];
        $cellBoundary = $gridReview['cell_geometry_boundary_review'] ?? [];

        $t->same(['Header', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Offcrop assigned', $assignedTexts, true));
        $t->true(!in_array('Offcrop row assigned', $assignedTexts, true));
        $t->contains('| Header | Status |', $formatted['markdown_tables'][0]);
        $t->contains('| Images | Ready  |', $formatted['markdown_tables'][0]);
        $t->true(!str_contains($formatted['markdown_tables'][0], 'Offcrop assigned'));
        $t->true(!str_contains($formatted['markdown_tables'][0], 'Offcrop row assigned'));
        $t->same([0, 1], $gridReview['rows'] ?? null);
        $t->same([0, 1], $gridReview['cols'] ?? null);
        $t->same(2, $geometryBoundary['excluded_band_count'] ?? null);
        $t->same('excluded_outside_table_image', $geometryBoundary['row_bands'][2]['status'] ?? null);
        $t->same('excluded_outside_table_image', $geometryBoundary['col_bands'][2]['status'] ?? null);
        $t->same(4, $cellBoundary['cell_count'] ?? null);
        $t->same(4, $cellBoundary['active_cell_count'] ?? null);
        $t->same(1, $cellBoundary['clipped_cell_count'] ?? null);
        $t->same(0, $cellBoundary['excluded_cell_count'] ?? null);
    },
    'supplied WordPress conversion excludes off crop already assigned table cells' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-assigned-crop-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% assigned crop table geometry boundary fixture\n%%EOF");
        try {
            $document = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_assigned_crop_boundary_page([
                        ['text' => 'Assigned crop table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale assigned table line should be replaced.', 'bbox' => [72.0, 176.0, 300.0, 196.0]],
                        ['text' => 'After assigned crop table.', 'bbox' => [72.0, 260.0, 430.0, 278.0]],
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
                    'recognized_tables' => [markerpdf_assigned_crop_boundary_table_fixture()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $assignedTexts = array_column($document['metadata']['table_assigned_cells'][0] ?? [], 'text');
            $gridReview = $document['metadata']['table_spanning_grid_review'][0] ?? [];
            $boundary = $gridReview['geometry_boundary_review'] ?? [];
            $cellBoundary = $gridReview['cell_geometry_boundary_review'] ?? [];

            $t->contains('# Assigned Crop Table Boundary', $document['text']);
            $t->contains('| Header | Status |', $document['text']);
            $t->contains('| Images | Ready  |', $document['text']);
            $t->contains('After assigned crop table.', $document['text']);
            $t->true(!str_contains($document['text'], 'Stale assigned table line should be replaced.'));
            $t->true(!str_contains($document['text'], 'Offcrop assigned'));
            $t->true(!str_contains($document['text'], 'Offcrop row assigned'));
            $t->same(['Header', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->true(in_array('table-recognition', $document['metadata']['supplied_boundaries'] ?? [], true));
            $t->true(in_array('table-formatting', $document['metadata']['supplied_boundaries'] ?? [], true));
            $t->same([[72.0, 150.0, 312.0, 230.0]], $document['metadata']['table_plan']['table_bboxes'] ?? null);
            $t->same(2, $boundary['excluded_band_count'] ?? null);
            $t->same(4, $cellBoundary['active_cell_count'] ?? null);
            $t->same(1, $cellBoundary['clipped_cell_count'] ?? null);
            $t->same(0, $cellBoundary['excluded_cell_count'] ?? null);
            $t->same(1, $document['metadata']['inserted_tables'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
