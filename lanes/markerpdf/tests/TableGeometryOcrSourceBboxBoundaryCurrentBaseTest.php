<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_ocr_source_bbox_boundary_page(array $lines): array
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

function markerpdf_ocr_source_bbox_boundary_rows(): array
{
    return [
        ['row_id' => 0, 'bbox' => [0.0, 0.0, 200.0, 30.0]],
        ['row_id' => 1, 'bbox' => [0.0, 38.0, 200.0, 70.0]],
    ];
}

function markerpdf_ocr_source_bbox_boundary_cols(): array
{
    return [
        ['col_id' => 0, 'bbox' => [0.0, 0.0, 96.0, 72.0]],
        ['col_id' => 1, 'bbox' => [98.0, 0.0, 200.0, 72.0]],
    ];
}

function markerpdf_ocr_source_bbox_boundary_cells(): array
{
    return [
        ['bbox' => [0.0, 0.0, 90.0, 24.0], 'text' => null],
        ['bbox' => [100.0, 0.0, 190.0, 24.0], 'text' => null],
        ['bbox' => [0.0, 40.0, 90.0, 64.0], 'text' => null],
        ['bbox' => [100.0, 40.0, 190.0, 64.0], 'text' => null],
    ];
}

function markerpdf_ocr_source_bbox_boundary_text_lines(): array
{
    return [
        'lines' => [
            [
                'text' => 'Feature',
                'source_bbox' => [0.0, 0.0, 190.0, 24.0],
                'source_coordinate_space' => 'table_crop',
            ],
            [
                'text' => 'Status',
                'source_bbox' => [0.0, 0.0, 190.0, 24.0],
                'source_coordinate_space' => 'table_crop',
            ],
            [
                'text' => 'Images',
                'source_bbox' => [0.0, 40.0, 190.0, 64.0],
                'source_coordinate_space' => 'table_crop',
            ],
            [
                'text' => 'Ready',
                'source_bbox' => [0.0, 40.0, 190.0, 64.0],
                'source_coordinate_space' => 'table_crop',
            ],
        ],
    ];
}

return [
    'uses source-bbox OCR TextLine geometry for grid-border review before table formatting' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $recognized = $recognizer->recognizeTables(
            [markerpdf_ocr_source_bbox_boundary_cells()],
            [true],
            [[
                'rows' => markerpdf_ocr_source_bbox_boundary_rows(),
                'cols' => markerpdf_ocr_source_bbox_boundary_cols(),
            ]],
            [0 => markerpdf_ocr_source_bbox_boundary_text_lines()]
        );
        $formatted = $recognizer->formatRecognizedTables($recognized, [['width' => 200, 'height' => 80]]);

        $conflicts = $recognized[0]['ocr_grid_border_conflicts'] ?? [];
        $firstConflict = $conflicts[0] ?? [];

        $t->same('source_order_grid_border', $recognized[0]['ocr_text_assignment'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], array_column($formatted['assigned_cells'][0] ?? [], 'text'));
        $t->same(4, count($conflicts));
        $t->same([0, 1], $firstConflict['candidate_cell_indexes'] ?? null);
        $t->same(0, $firstConflict['assigned_cell_index'] ?? null);
        $t->same([0.0, 0.0, 190.0, 24.0], $firstConflict['bbox'] ?? null);
        $t->same([0.0, 0.0, 190.0, 24.0], $firstConflict['source_bbox'] ?? null);
        $t->same('table_crop', $firstConflict['source_coordinate_space'] ?? null);
        $t->same('source_bbox', $firstConflict['source_coordinate_source'] ?? null);
        $t->same(false, $firstConflict['source_endpoint_order_normalized'] ?? null);
        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0] ?? '');
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces source-bbox OCR TextLine grid-border metadata through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-ocr-source-bbox-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table OCR source bbox boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_ocr_source_bbox_boundary_page([
                        ['text' => 'OCR source bbox boundary review', 'bbox' => [72.0, 48.0, 470.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale source-bbox table text should be replaced.', 'bbox' => [72.0, 176.0, 470.0, 196.0]],
                        ['text' => 'Reviewer note after source bbox table.', 'bbox' => [72.0, 276.0, 510.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 470.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 510.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [[
                        'rows' => markerpdf_ocr_source_bbox_boundary_rows(),
                        'cols' => markerpdf_ocr_source_bbox_boundary_cols(),
                    ]],
                    'table_detector_cells' => [markerpdf_ocr_source_bbox_boundary_cells()],
                    'table_ocr_text_lines' => [markerpdf_ocr_source_bbox_boundary_text_lines()],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                    'ocr_all_pages' => true,
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $conflicts = $result['metadata']['table_ocr_grid_border_conflicts'][0] ?? [];
            $firstConflict = $conflicts[0] ?? [];

            $t->contains('# Ocr Source Bbox Boundary Review', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('Reviewer note after source bbox table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale source-bbox table text should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries'] ?? null);
            $t->same([true], $result['metadata']['table_needs_ocr'] ?? null);
            $t->same(true, $result['metadata']['table_detect_boxes'] ?? null);
            $t->same([4], $result['metadata']['table_cell_counts'] ?? null);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text'));
            $t->same(4, count($conflicts));
            $t->same('source_order_grid_border', $firstConflict['assignment_mode'] ?? null);
            $t->same([0, 1], $firstConflict['candidate_cell_indexes'] ?? null);
            $t->same([2, 3], $conflicts[2]['candidate_cell_indexes'] ?? null);
            $t->same([0.0, 0.0, 190.0, 24.0], $firstConflict['source_bbox'] ?? null);
            $t->same('table_crop', $firstConflict['source_coordinate_space'] ?? null);
            $t->same('source_bbox', $firstConflict['source_coordinate_source'] ?? null);
            $t->same(false, $firstConflict['source_endpoint_order_normalized'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
