<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_rows_cols_tnum_boundary_xxyy(float $x1, float $y1, float $x2, float $y2): array
{
    return [$x1, $x2, $y1, $y2];
}

function markerpdf_rows_cols_tnum_boundary_page(array $lines): array
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

function markerpdf_rows_cols_tnum_boundary_table(): array
{
    return [
        'pnum' => 0,
        'tnum' => 1,
        'bbox' => [72.0, 150.0, 312.0, 230.0],
        'coordinate_space' => 'page_image',
        'rows_cols' => [
            [
                'pnum' => 0,
                'tnum' => 0,
                'bbox' => [380.0, 420.0, 600.0, 500.0],
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'rows' => [
                    ['row_id' => 900, 'bbox' => markerpdf_rows_cols_tnum_boundary_xxyy(380.0, 420.0, 600.0, 452.0)],
                    ['row_id' => 901, 'bbox' => markerpdf_rows_cols_tnum_boundary_xxyy(380.0, 460.0, 600.0, 490.0)],
                ],
                'cols' => [
                    ['col_id' => 910, 'bbox' => markerpdf_rows_cols_tnum_boundary_xxyy(380.0, 420.0, 480.0, 500.0)],
                    ['col_id' => 911, 'bbox' => markerpdf_rows_cols_tnum_boundary_xxyy(500.0, 420.0, 600.0, 500.0)],
                ],
            ],
            [
                'pnum' => 0,
                'tnum' => 1,
                'bbox' => [72.0, 150.0, 312.0, 230.0],
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'rows' => [
                    ['row_id' => 30, 'bbox' => markerpdf_rows_cols_tnum_boundary_xxyy(72.0, 150.0, 312.0, 182.0)],
                    ['row_id' => 31, 'bbox' => markerpdf_rows_cols_tnum_boundary_xxyy(72.0, 190.0, 312.0, 220.0)],
                    ['row_id' => 99, 'bbox' => markerpdf_rows_cols_tnum_boundary_xxyy(72.0, 250.0, 312.0, 268.0)],
                ],
                'cols' => [
                    ['col_id' => 40, 'bbox' => markerpdf_rows_cols_tnum_boundary_xxyy(72.0, 150.0, 172.0, 230.0)],
                    ['col_id' => 41, 'bbox' => markerpdf_rows_cols_tnum_boundary_xxyy(192.0, 150.0, 312.0, 230.0)],
                    ['col_id' => 99, 'bbox' => markerpdf_rows_cols_tnum_boundary_xxyy(342.0, 150.0, 362.0, 230.0)],
                ],
            ],
        ],
        'cells' => [
            ['bbox' => [82.0, 155.0, 302.0, 170.0], 'text' => 'Header'],
            ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images'],
            ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready'],
        ],
    ];
}

function markerpdf_rows_cols_tnum_boundary_convert(): array
{
    $path = sys_get_temp_dir() . '/markerpdf-table-rows-cols-tnum-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
    file_put_contents($path, "%PDF-1.4\n% rows-cols tnum table geometry boundary current-base fixture\n%%EOF");

    try {
        return (new SuppliedDocumentConverter())->convert(
            $path,
            [
                markerpdf_rows_cols_tnum_boundary_page([
                    ['text' => 'Rows cols tnum table boundary', 'bbox' => [72.0, 48.0, 540.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                    ['text' => 'Stale rows-cols tnum table line should be replaced.', 'bbox' => [72.0, 176.0, 410.0, 196.0]],
                    ['text' => 'After rows cols tnum table.', 'bbox' => [72.0, 276.0, 540.0, 294.0]],
                ]),
            ],
            [
                'metadata' => ['languages' => ['English']],
                'layout_results' => [[
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Title', 'bbox' => [72.0, 48.0, 540.0, 68.0]],
                        ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                        ['label' => 'Text', 'bbox' => [72.0, 276.0, 540.0, 294.0]],
                    ],
                ]],
                'recognized_tables' => [markerpdf_rows_cols_tnum_boundary_table()],
                'table_text_lines' => [['blocks' => []]],
                'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
            ],
            new MarkerSettings(['EXTRACT_IMAGES' => false])
        );
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
}

return [
    'selects rows_cols table result by tnum before table crop assignment' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_rows_cols_tnum_boundary_table()],
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
        $t->same('rows_cols.1.rows', $localized['rows_source_alias'] ?? null);
        $t->same('rows_cols.1.cols', $localized['cols_source_alias'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same([30], $assignedByText['Header']['row_ids'] ?? null);
        $t->same([40, 41], $assignedByText['Header']['col_ids'] ?? null);
        $t->same([31], $assignedByText['Images']['row_ids'] ?? null);
        $t->same([40], $assignedByText['Images']['col_ids'] ?? null);
        $t->same([31], $assignedByText['Ready']['row_ids'] ?? null);
        $t->same([41], $assignedByText['Ready']['col_ids'] ?? null);
        $t->same([30, 31], $gridReview['geometry_boundary_review']['active_row_ids'] ?? null);
        $t->same([40, 41], $gridReview['geometry_boundary_review']['active_col_ids'] ?? null);
        $t->same(2, $gridReview['geometry_boundary_review']['excluded_band_count'] ?? null);
        $t->same(2, $renderByText['Header']['colspan'] ?? null);
        $t->same(['h-r30-c40'], $renderByText['Ready']['headers'] ?? null);
        $t->same("| Header |       |\n|--------|-------|\n| Images | Ready |", $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces tnum selected rows_cols through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $result = markerpdf_rows_cols_tnum_boundary_convert();
        $metadata = $result['metadata'];
        $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
        $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
        $assignedByText = [];
        foreach (($metadata['table_assigned_cells'][0] ?? []) as $cell) {
            $assignedByText[$cell['text']] = $cell;
        }
        $renderByText = [];
        foreach (($gridReview['render_cells'] ?? []) as $renderCell) {
            $renderByText[$renderCell['text']] = $renderCell;
        }

        $t->contains('# Rows Cols Tnum Table Boundary', $result['text']);
        $t->contains('| Header |       |', $result['text']);
        $t->contains('| Images | Ready |', $result['text']);
        $t->contains('After rows cols tnum table.', $result['text']);
        $t->true(!str_contains($result['text'], 'Stale rows-cols tnum table line should be replaced.'));
        $t->true(!str_contains($result['text'], '900'));
        $t->true(!str_contains($result['text'], '910'));
        $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
        $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
        $t->same([30], $assignedByText['Header']['row_ids'] ?? null);
        $t->same([40, 41], $assignedByText['Header']['col_ids'] ?? null);
        $t->same([40, 41], $gridReview['geometry_boundary_review']['active_col_ids'] ?? null);
        $t->same(2, $renderByText['Header']['colspan'] ?? null);
        $t->same(['h-r30-c40'], $renderByText['Ready']['headers'] ?? null);
    },
];
