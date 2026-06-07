<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_rows_cols_boundary_page(array $lines): array
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

function markerpdf_rows_cols_boundary_table(): array
{
    return [
        'bbox' => [72.0, 150.0, 312.0, 230.0],
        'coordinate_space' => 'page_image',
        'rows_cols' => [
            'rows' => [
                ['row_id' => 10, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
                ['row_id' => 11, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
                ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 270.0]],
            ],
            'cols' => [
                ['col_id' => 20, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
                ['col_id' => 21, 'bbox' => [192.0, 150.0, 312.0, 230.0]],
                ['col_id' => 99, 'bbox' => [342.0, 150.0, 362.0, 230.0]],
            ],
        ],
        'cells' => [
            ['bbox' => [82.0, 155.0, 302.0, 170.0], 'text' => 'Header'],
            ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images'],
            ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready'],
        ],
    ];
}

return [
    'canonicalizes single table rows_cols wrapper before geometry assignment' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_rows_cols_boundary_table()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedByText = [];
        foreach ($assigned as $cell) {
            $assignedByText[$cell['text']] = $cell;
        }
        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $localized['rows'] ?? [],
            $localized['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );
        $renderByText = [];
        foreach (($gridReview['render_cells'] ?? []) as $renderCell) {
            $renderByText[$renderCell['text']] = $renderCell;
        }

        $t->same('translated_to_table_crop', $formatted['coordinate_space_reviews'][0]['status'] ?? null);
        $t->same('page_image', $formatted['coordinate_space_reviews'][0]['source_coordinate_spaces']['rows'] ?? null);
        $t->same('page_image', $formatted['coordinate_space_reviews'][0]['source_coordinate_spaces']['cols'] ?? null);
        $t->same('rows_cols.rows', $localized['rows_source_alias'] ?? null);
        $t->same('rows_cols.cols', $localized['cols_source_alias'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same([10], $assignedByText['Header']['row_ids'] ?? null);
        $t->same([20, 21], $assignedByText['Header']['col_ids'] ?? null);
        $t->same([11], $assignedByText['Images']['row_ids'] ?? null);
        $t->same([20], $assignedByText['Images']['col_ids'] ?? null);
        $t->same([11], $assignedByText['Ready']['row_ids'] ?? null);
        $t->same([21], $assignedByText['Ready']['col_ids'] ?? null);
        $t->same("| Header |       |\n|--------|-------|\n| Images | Ready |", $formatted['markdown_tables'][0] ?? '');
        $t->same([10, 11], $gridReview['geometry_boundary_review']['active_row_ids'] ?? null);
        $t->same([20, 21], $gridReview['geometry_boundary_review']['active_col_ids'] ?? null);
        $t->same(2, $gridReview['geometry_boundary_review']['excluded_band_count'] ?? null);
        $t->same(2, $renderByText['Header']['colspan'] ?? null);
        $t->same(['h-r10-c20'], $renderByText['Ready']['headers'] ?? null);
    },
    'surfaces single table rows_cols wrapper through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-rows-cols-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% rows-cols table geometry boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_rows_cols_boundary_page([
                        ['text' => 'Rows cols table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale rows-cols table line should be replaced.', 'bbox' => [72.0, 176.0, 380.0, 196.0]],
                        ['text' => 'After rows cols table.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_rows_cols_boundary_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $assignedByText = [];
            foreach (($result['metadata']['table_assigned_cells'][0] ?? []) as $cell) {
                $assignedByText[$cell['text']] = $cell;
            }
            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $renderByText = [];
            foreach (($gridReview['render_cells'] ?? []) as $renderCell) {
                $renderByText[$renderCell['text']] = $renderCell;
            }

            $t->contains('# Rows Cols Table Boundary', $result['text']);
            $t->contains('| Header |       |', $result['text']);
            $t->contains('| Images | Ready |', $result['text']);
            $t->contains('After rows cols table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale rows-cols table line should be replaced.'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries'] ?? null);
            $t->same('translated_to_table_crop', $result['metadata']['table_coordinate_space_reviews'][0]['status'] ?? null);
            $t->same([10], $assignedByText['Header']['row_ids'] ?? null);
            $t->same([20, 21], $assignedByText['Header']['col_ids'] ?? null);
            $t->same([20, 21], $gridReview['geometry_boundary_review']['active_col_ids'] ?? null);
            $t->same(2, $renderByText['Header']['colspan'] ?? null);
            $t->same(['h-r10-c20'], $renderByText['Ready']['headers'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
