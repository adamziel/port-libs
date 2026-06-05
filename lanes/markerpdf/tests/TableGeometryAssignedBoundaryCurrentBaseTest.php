<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SuppliedDocumentConverter.php';
require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_assigned_table_fixture(): array
{
    return [
        'rows' => [
            ['bbox' => [0, 0, 240, 30]],
            ['bbox' => [0, 40, 240, 70]],
        ],
        'cols' => [
            ['bbox' => [0, 0, 100, 80]],
            ['bbox' => [120, 0, 240, 80]],
        ],
        'cells' => [
            [
                'bbox' => [130, 5, 230, 25],
                'text' => 'Feature',
                'row_ids' => [0],
                'col_ids' => [0],
                'order' => 1,
            ],
            [
                'bbox' => [10, 5, 90, 25],
                'text' => 'Status',
                'row_ids' => [0],
                'col_ids' => [1],
                'order' => 0,
            ],
            [
                'bbox' => [130, 45, 230, 65],
                'text' => 'Images',
                'row_ids' => [1],
                'col_ids' => [0],
                'order' => 3,
            ],
            [
                'bbox' => [10, 45, 90, 65],
                'text' => 'Ready',
                'row_ids' => [1],
                'col_ids' => [1],
                'order' => 2,
            ],
        ],
    ];
}

function markerpdf_assigned_table_page(): array
{
    return [
        'page' => 0,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'rotation' => 0,
        'blocks' => [[
            'lines' => [
                [
                    'bbox' => [72.0, 48.0, 430.0, 68.0],
                    'spans' => [[
                        'text' => 'Assigned Table Boundary',
                        'bbox' => [72.0, 48.0, 430.0, 68.0],
                        'font' => ['name' => 'Helvetica-Bold', 'flags' => 0, 'weight' => 700, 'size' => 18],
                    ]],
                ],
                [
                    'bbox' => [72.0, 176.0, 420.0, 196.0],
                    'spans' => [[
                        'text' => 'Stale table text should be replaced.',
                        'bbox' => [72.0, 176.0, 420.0, 196.0],
                        'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12],
                    ]],
                ],
                [
                    'bbox' => [72.0, 260.0, 430.0, 278.0],
                    'spans' => [[
                        'text' => 'After assigned table.',
                        'bbox' => [72.0, 260.0, 430.0, 278.0],
                        'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12],
                    ]],
                ],
            ],
        ]],
    ];
}

return [
    'supplied assigned table cells keep upstream row column ids when bboxes overlap different bands' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_assigned_table_fixture()],
            [[240, 80]]
        );

        $assigned = $formatted['assigned_cells'][0];
        $byText = [];
        foreach ($assigned as $cell) {
            $byText[$cell['text']] = $cell;
        }

        $t->same([0], $byText['Feature']['row_ids']);
        $t->same([0], $byText['Feature']['col_ids']);
        $t->same(1, $byText['Feature']['order']);
        $t->same([0], $byText['Status']['row_ids']);
        $t->same([1], $byText['Status']['col_ids']);
        $t->same(0, $byText['Status']['order']);
        $t->same([1], $byText['Images']['row_ids']);
        $t->same([0], $byText['Images']['col_ids']);
        $t->same([1], $byText['Ready']['row_ids']);
        $t->same([1], $byText['Ready']['col_ids']);

        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0]);
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0]);
        $t->true(! str_contains($formatted['markdown_tables'][0], '| Status  | Feature |'));
    },
    'supplied document conversion preserves assigned table cell geometry boundaries' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-assigned-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% assigned table geometry boundary fixture\n%%EOF");
        try {
            $document = (new SuppliedDocumentConverter())->convert(
                $path,
                [markerpdf_assigned_table_page()],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 430.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 260.0, 430.0, 278.0]],
                        ],
                    ]],
                    'recognized_tables' => [markerpdf_assigned_table_fixture()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $t->contains('# Assigned Table Boundary', $document['text']);
            $t->contains('After assigned table.', $document['text']);
            $t->true(in_array('table-recognition', $document['metadata']['supplied_boundaries'] ?? [], true));
            $t->true(in_array('table-formatting', $document['metadata']['supplied_boundaries'] ?? [], true));

            $assignedByText = [];
            foreach (($document['metadata']['table_assigned_cells'][0] ?? []) as $cell) {
                $assignedByText[$cell['text']] = $cell;
            }
            $t->same([0], $assignedByText['Feature']['col_ids'] ?? null);
            $t->same([1], $assignedByText['Status']['col_ids'] ?? null);

            $gridReview = $document['metadata']['table_spanning_grid_review'][0] ?? [];
            $gridByPosition = [];
            foreach (($gridReview['grid_cells'] ?? []) as $gridCell) {
                $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
            }

            $t->same(['Feature', 'Status'], array_column($gridReview['header_cells'] ?? [], 'text'));
            $t->same(['Images', 'Ready'], array_column($gridReview['data_cells'] ?? [], 'text'));
            $t->same('Feature', $gridByPosition['0:0']['text'] ?? null);
            $t->same('Status', $gridByPosition['0:1']['text'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
