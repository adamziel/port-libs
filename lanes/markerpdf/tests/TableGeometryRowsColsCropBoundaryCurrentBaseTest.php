<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/TableRecognizer.php';

use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_rows_cols_crop_boundary_table(): array
{
    return [
        'bbox' => [0, 0, 612, 792],
        'coordinate_space' => 'page_image',
        'image_bbox' => [0, 0, 612, 792],
        'rows_cols' => [
            'bbox' => [72, 150, 312, 230],
            'bbox_coordinate_space' => 'page_image',
            'coordinate_space' => 'page_image',
            'rows' => [
                [
                    'row_id' => 10,
                    'bbox' => [72, 150, 312, 182],
                    'bbox_coordinate_space' => 'page_image',
                ],
                [
                    'row_id' => 11,
                    'bbox' => [72, 190, 312, 220],
                    'bbox_coordinate_space' => 'page_image',
                ],
                [
                    'row_id' => 99,
                    'bbox' => [72, 250, 312, 270],
                    'bbox_coordinate_space' => 'page_image',
                ],
            ],
            'cols' => [
                [
                    'col_id' => 20,
                    'bbox' => [72, 150, 172, 230],
                    'bbox_coordinate_space' => 'page_image',
                ],
                [
                    'col_id' => 21,
                    'bbox' => [192, 150, 312, 230],
                    'bbox_coordinate_space' => 'page_image',
                ],
                [
                    'col_id' => 99,
                    'bbox' => [342, 150, 362, 230],
                    'bbox_coordinate_space' => 'page_image',
                ],
            ],
        ],
        'cells' => [
            [
                'text' => 'Feature',
                'bbox' => [82, 155, 162, 170],
                'row_ids' => [10],
                'col_ids' => [20],
            ],
            [
                'text' => 'Status',
                'bbox' => [202, 155, 302, 170],
                'row_ids' => [10],
                'col_ids' => [21],
            ],
            [
                'text' => 'Images',
                'bbox' => [82, 195, 162, 215],
                'row_ids' => [11],
                'col_ids' => [20],
            ],
            [
                'text' => 'Ready',
                'bbox' => [202, 195, 302, 215],
                'row_ids' => [11],
                'col_ids' => [21],
            ],
            [
                'text' => 'Ghost row',
                'bbox' => [82, 250, 162, 268],
                'row_ids' => [99],
                'col_ids' => [20],
            ],
            [
                'text' => 'Ghost col',
                'bbox' => [350, 195, 382, 215],
                'row_ids' => [11],
                'col_ids' => [99],
            ],
        ],
    ];
}

return [
    'upstream rows_cols bbox beats stale wrapper bbox before crop boundary filtering' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_rows_cols_crop_boundary_table()],
            [['image_bbox' => [0, 0, 612, 792]]]
        );

        $t->same(1, count($formatted['recognized_tables']));
        $table = $formatted['recognized_tables'][0];
        $coordinateReview = $formatted['coordinate_space_reviews'][0] ?? [];
        $bandReview = $formatted['assigned_band_boundary_reviews'][0] ?? [];

        $t->same([72.0, 150.0, 312.0, 230.0], $coordinateReview['table_bbox'] ?? null);
        $t->same('rows_cols.bbox', $coordinateReview['table_bbox_source'] ?? null);
        $t->same('page_image', $coordinateReview['table_bbox_source_coordinate_space'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $coordinateReview['translation'] ?? null);
        $t->same(['width' => 240, 'height' => 80], $coordinateReview['table_crop_size'] ?? null);

        $t->same('rows_cols.rows', $table['rows_source_alias'] ?? null);
        $t->same('rows_cols.cols', $table['cols_source_alias'] ?? null);
        $t->same([0.0, 0.0, 240.0, 32.0], $table['rows'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $table['cols'][1]['bbox'] ?? null);

        $cellTexts = array_map(
            static fn (array $cell): string => (string) ($cell['text'] ?? ''),
            $formatted['assigned_cells'][0] ?? []
        );
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $cellTexts);
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');

        $t->same([10, 11], $bandReview['active_row_ids'] ?? null);
        $t->same([20, 21], $bandReview['active_col_ids'] ?? null);
    },
];
