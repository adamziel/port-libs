<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_scalar_span_boundary_table_fixture(): array
{
    return [
        'rows' => [
            ['row_id' => 10, 'bbox' => [0.0, 0.0, 300.0, 26.0]],
            ['row_id' => 20, 'bbox' => [0.0, 34.0, 300.0, 60.0]],
            ['row_id' => 30, 'bbox' => [0.0, 70.0, 300.0, 96.0]],
            ['row_id' => 99, 'bbox' => [0.0, 122.0, 300.0, 146.0]],
        ],
        'cols' => [
            ['col_id' => 5, 'bbox' => [0.0, 0.0, 90.0, 110.0]],
            ['col_id' => 7, 'bbox' => [105.0, 0.0, 190.0, 110.0]],
            ['col_id' => 9, 'bbox' => [210.0, 0.0, 300.0, 110.0]],
            ['col_id' => 99, 'bbox' => [330.0, 0.0, 370.0, 110.0]],
        ],
        'cells' => [
            ['bbox' => [5.0, 5.0, 86.0, 22.0], 'text' => 'Inventory summary', 'row_id' => 10, 'col_id' => 5, 'rowspan' => 1, 'colspan' => 3, 'order' => 0],
            ['bbox' => [5.0, 40.0, 86.0, 56.0], 'text' => 'Media group', 'row_id' => 20, 'col_id' => 5, 'rowspan' => 2, 'colspan' => 1, 'order' => 1],
            ['bbox' => [112.0, 40.0, 180.0, 56.0], 'text' => 'Images', 'row_id' => 20, 'col_id' => 7, 'order' => 2],
            ['bbox' => [218.0, 40.0, 292.0, 56.0], 'text' => 'Ready', 'row_id' => 20, 'col_id' => 9, 'order' => 3],
            ['bbox' => [112.0, 76.0, 180.0, 92.0], 'text' => 'Attachments', 'row_id' => 30, 'col_id' => 7, 'order' => 4],
            ['bbox' => [218.0, 76.0, 292.0, 92.0], 'text' => 'Queued', 'row_id' => 30, 'col_id' => 9, 'order' => 5],
            ['bbox' => [334.0, 40.0, 360.0, 56.0], 'text' => 'Offcrop scalar col', 'row_id' => 20, 'col_id' => 99, 'order' => 6],
            ['bbox' => [5.0, 126.0, 86.0, 140.0], 'text' => 'Offcrop scalar row', 'row_id' => 99, 'col_id' => 5, 'order' => 7],
        ],
    ];
}

function markerpdf_scalar_span_boundary_page(array $lines): array
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
    'expands upstream scalar row column spans before active band boundary filtering' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_scalar_span_boundary_table_fixture()],
            [['width' => 300, 'height' => 110]]
        );

        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedByText = [];
        foreach ($assigned as $cell) {
            $assignedByText[$cell['text']] = $cell;
        }
        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $formatted['recognized_tables'][0]['rows'],
            $formatted['recognized_tables'][0]['cols'],
            ['width' => 300, 'height' => 110]
        );
        $renderByText = [];
        foreach (($gridReview['render_cells'] ?? []) as $renderCell) {
            $renderByText[$renderCell['text']] = $renderCell;
        }
        $gridByPosition = [];
        foreach (($gridReview['grid_cells'] ?? []) as $gridCell) {
            $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
        }
        $bandReview = $formatted['assigned_band_boundary_reviews'][0] ?? [];
        $cropReview = $formatted['assigned_crop_boundary_reviews'][0] ?? [];

        $t->same(['Inventory summary', 'Media group', 'Images', 'Ready', 'Attachments', 'Queued'], array_column($assigned, 'text'));
        $t->same([10], $assignedByText['Inventory summary']['row_ids'] ?? null);
        $t->same([5, 7, 9], $assignedByText['Inventory summary']['col_ids'] ?? null);
        $t->same([20, 30], $assignedByText['Media group']['row_ids'] ?? null);
        $t->same([5], $assignedByText['Media group']['col_ids'] ?? null);
        $t->same([0, 1, 2], $assignedByText['Inventory summary']['col_geometry_orders'] ?? null);
        $t->same([1, 2], $assignedByText['Media group']['row_geometry_orders'] ?? null);
        $t->same('table_assigned_band_geometry_boundary', $bandReview['review_target'] ?? null);
        $t->same([10, 20, 30], $bandReview['active_row_ids'] ?? null);
        $t->same([5, 7, 9], $bandReview['active_col_ids'] ?? null);
        $t->same('table_assigned_cell_crop_boundary', $cropReview['review_target'] ?? null);
        $t->same(8, $cropReview['cell_count'] ?? null);
        $t->same(6, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same(6, $bandReview['cell_count'] ?? null);
        $t->same(6, $bandReview['active_cell_count'] ?? null);
        $t->same(0, $bandReview['excluded_cell_count'] ?? null);
        $t->same(3, $renderByText['Inventory summary']['colspan'] ?? null);
        $t->same(2, $renderByText['Media group']['rowspan'] ?? null);
        $t->same('covered', $gridByPosition['10:7']['state'] ?? null);
        $t->same(['row_id' => 10, 'col_id' => 5, 'render_cell_index' => 0], $gridByPosition['10:7']['covered_by'] ?? null);
        $t->same('covered', $gridByPosition['30:5']['state'] ?? null);
        $t->same(['row_id' => 20, 'col_id' => 5, 'render_cell_index' => 1], $gridByPosition['30:5']['covered_by'] ?? null);
        $t->contains('Inventory summary', $formatted['markdown_tables'][0] ?? '');
        $t->contains('Media group', $formatted['markdown_tables'][0] ?? '');
        $t->true(!str_contains($formatted['markdown_tables'][0] ?? '', 'Offcrop scalar'));
    },
    'surfaces scalar span grid review through supplied WordPress conversion' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-scalar-span-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% scalar span table geometry boundary fixture\n%%EOF");
        try {
            $document = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_scalar_span_boundary_page([
                        ['text' => 'Scalar span table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale scalar span table line should be replaced.', 'bbox' => [72.0, 176.0, 430.0, 196.0]],
                        ['text' => 'After scalar span table.', 'bbox' => [72.0, 300.0, 430.0, 318.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 372.0, 260.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 300.0, 430.0, 318.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_scalar_span_boundary_table_fixture()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $assigned = $document['metadata']['table_assigned_cells'][0] ?? [];
            $assignedByText = [];
            foreach ($assigned as $cell) {
                $assignedByText[$cell['text']] = $cell;
            }
            $gridReview = $document['metadata']['table_spanning_grid_review'][0] ?? [];
            $renderByText = [];
            foreach (($gridReview['render_cells'] ?? []) as $renderCell) {
                $renderByText[$renderCell['text']] = $renderCell;
            }

            $t->contains('# Scalar Span Table Boundary', $document['text']);
            $t->contains('Inventory summary', $document['text']);
            $t->contains('Media group', $document['text']);
            $t->contains('After scalar span table.', $document['text']);
            $t->true(!str_contains($document['text'], 'Stale scalar span table line should be replaced.'));
            $t->true(!str_contains($document['text'], 'Offcrop scalar'));
            $t->same([5, 7, 9], $assignedByText['Inventory summary']['col_ids'] ?? null);
            $t->same([20, 30], $assignedByText['Media group']['row_ids'] ?? null);
            $t->same(3, $renderByText['Inventory summary']['colspan'] ?? null);
            $t->same(2, $renderByText['Media group']['rowspan'] ?? null);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $document['metadata']['supplied_boundaries'] ?? null);
            $t->same(false, $document['metadata']['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
