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
    'uses saved tabled result bbox as page image crop boundary for assigned geometry' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [[
                'coordinate_space' => 'page_image',
                'bbox' => [72.0, 150.0, 312.0, 230.0],
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'rows' => [
                    ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
                    ['row_id' => 1, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
                    ['col_id' => 1, 'bbox' => [192.0, 150.0, 312.0, 230.0]],
                ],
                'cells' => [
                    ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
                    ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
                    ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
                    ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
                ],
            ]],
            [['width' => 240, 'height' => 80]]
        );

        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $localized = $formatted['recognized_tables'][0] ?? [];
        $assignedByText = [];
        foreach (($formatted['assigned_cells'][0] ?? []) as $cell) {
            $assignedByText[$cell['text']] = $cell;
        }

        $t->same('table_recognition_coordinate_space_boundary', $review['review_target'] ?? null);
        $t->same('translated_to_table_crop', $review['status'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $review['table_bbox'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same([130.0, 45.0, 230.0, 65.0], $localized['cells'][3]['bbox'] ?? null);
        $t->same([0], $assignedByText['Feature']['row_ids'] ?? null);
        $t->same([0], $assignedByText['Feature']['col_ids'] ?? null);
        $t->same([1], $assignedByText['Ready']['row_ids'] ?? null);
        $t->same([1], $assignedByText['Ready']['col_ids'] ?? null);
        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0] ?? '');
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0] ?? '');
    },
    'preserves page image source bboxes in assigned table geometry review metadata' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [[
                'coordinate_space' => 'page_image',
                'bbox' => [72.0, 150.0, 312.0, 230.0],
                'rows' => [
                    ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
                    ['row_id' => 1, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
                    ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 270.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
                    ['col_id' => 1, 'bbox' => [192.0, 150.0, 312.0, 230.0]],
                    ['col_id' => 99, 'bbox' => [342.0, 150.0, 362.0, 230.0]],
                ],
                'cells' => [
                    ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
                    ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
                    ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
                    ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
                    ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale page edge', 'row_ids' => [1], 'col_ids' => [99]],
                    ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale page row', 'row_ids' => [99], 'col_ids' => [0]],
                ],
            ]],
            [['width' => 240, 'height' => 80]]
        );

        $coordinateReview = $formatted['coordinate_space_reviews'][0] ?? [];
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
        $gridByPosition = [];
        foreach (($gridReview['grid_cells'] ?? []) as $gridCell) {
            $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
        }
        $cellBoundary = $gridReview['cell_geometry_boundary_review'] ?? [];

        $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $localized['cells'][0]['bbox'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $localized['cells'][0]['source_bbox'] ?? null);
        $t->same('page_image', $localized['cells'][0]['source_coordinate_space'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $assignedByText['Feature']['bbox'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $assignedByText['Feature']['source_bbox'] ?? null);
        $t->same('page_image', $assignedByText['Feature']['source_coordinate_space'] ?? null);
        $t->same([202.0, 195.0, 302.0, 215.0], $assignedByText['Ready']['source_bbox'] ?? null);
        $t->true(!isset($assignedByText['Stale page edge']));
        $t->true(!isset($assignedByText['Stale page row']));
        $t->same([82.0, 155.0, 162.0, 170.0], $cellBoundary['cells'][0]['source_bbox'] ?? null);
        $t->same('page_image', $cellBoundary['cells'][0]['source_coordinate_space'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $renderByText['Feature']['source_cell_bbox'] ?? null);
        $t->same([[82.0, 155.0, 162.0, 170.0]], $renderByText['Feature']['source_cell_bboxes'] ?? null);
        $t->same('page_image', $renderByText['Feature']['source_coordinate_space'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $gridByPosition['0:0']['source_cell_bbox'] ?? null);
        $t->same([202.0, 195.0, 302.0, 215.0], $gridByPosition['1:1']['source_cell_bbox'] ?? null);
        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0] ?? '');
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0] ?? '');
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
    'supplied WordPress conversion surfaces page image source table geometry metadata' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-source-geometry-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table source geometry boundary fixture\n%%EOF");
        try {
            $document = (new SuppliedDocumentConverter())->convert(
                $path,
                [[
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
                                'bbox' => [82.0, 176.0, 300.0, 196.0],
                                'spans' => [[
                                    'text' => 'Stale source geometry table line should be replaced.',
                                    'bbox' => [82.0, 176.0, 300.0, 196.0],
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
                ]],
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
                    'recognized_tables' => [[
                        'coordinate_space' => 'page_image',
                        'bbox' => [72.0, 150.0, 312.0, 230.0],
                        'rows' => [
                            ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
                            ['row_id' => 1, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
                        ],
                        'cols' => [
                            ['col_id' => 0, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
                            ['col_id' => 1, 'bbox' => [192.0, 150.0, 312.0, 230.0]],
                        ],
                        'cells' => [
                            ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
                            ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
                            ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
                            ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
                        ],
                    ]],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $assignedByText = [];
            foreach (($document['metadata']['table_assigned_cells'][0] ?? []) as $cell) {
                $assignedByText[$cell['text']] = $cell;
            }
            $gridReview = $document['metadata']['table_spanning_grid_review'][0] ?? [];
            $renderByText = [];
            foreach (($gridReview['render_cells'] ?? []) as $renderCell) {
                $renderByText[$renderCell['text']] = $renderCell;
            }

            $t->contains('# Assigned Table Boundary', $document['text']);
            $t->contains('| Feature | Status |', $document['text']);
            $t->contains('| Images  | Ready  |', $document['text']);
            $t->true(!str_contains($document['text'], 'Stale source geometry table line should be replaced.'));
            $t->same([82.0, 155.0, 162.0, 170.0], $assignedByText['Feature']['source_bbox'] ?? null);
            $t->same([202.0, 195.0, 302.0, 215.0], $assignedByText['Ready']['source_bbox'] ?? null);
            $t->same('page_image', $assignedByText['Feature']['source_coordinate_space'] ?? null);
            $t->same([82.0, 155.0, 162.0, 170.0], $gridReview['cell_geometry_boundary_review']['cells'][0]['source_bbox'] ?? null);
            $t->same([82.0, 155.0, 162.0, 170.0], $renderByText['Feature']['source_cell_bbox'] ?? null);
            $t->same('page_image', $renderByText['Feature']['source_coordinate_space'] ?? null);
            $t->same('translated_to_table_crop', $document['metadata']['table_coordinate_space_reviews'][0]['status'] ?? null);
            $t->true(in_array('table-recognition', $document['metadata']['supplied_boundaries'] ?? [], true));
            $t->true(in_array('table-formatting', $document['metadata']['supplied_boundaries'] ?? [], true));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
