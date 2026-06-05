<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\TableRecognizer;

return [
    'bounds supplied table geometry to crop image before row column assignment' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $result = [
            'rows' => [
                ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 24.0]],
                ['row_id' => 1, 'bbox' => [0.0, 34.0, 300.0, 66.0]],
                ['row_id' => 99, 'bbox' => [0.0, 122.0, 300.0, 150.0]],
            ],
            'cols' => [
                ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 120.0]],
                ['col_id' => 1, 'bbox' => [110.0, 0.0, 220.0, 120.0]],
                ['col_id' => 99, 'bbox' => [304.0, 0.0, 360.0, 120.0]],
            ],
            'cells' => [
                ['bbox' => [10.0, 5.0, 90.0, 20.0], 'text' => 'Header'],
                ['bbox' => [130.0, 5.0, 315.0, 20.0], 'text' => 'Status'],
                ['bbox' => [10.0, 42.0, 90.0, 60.0], 'text' => 'Images'],
                ['bbox' => [130.0, 42.0, 210.0, 60.0], 'text' => 'Ready'],
                ['bbox' => [306.0, 42.0, 350.0, 60.0], 'text' => 'Stale right edge'],
                ['bbox' => [10.0, 126.0, 90.0, 144.0], 'text' => 'Stale below crop'],
            ],
        ];

        $assigned = $recognizer->assignRowsColumns($result, ['width' => 300, 'height' => 120]);
        $review = $recognizer->spanningGridReview($assigned, $result['rows'], $result['cols'], ['width' => 300, 'height' => 120]);

        $t->same(['Header', 'Status', 'Images', 'Ready'], array_column($assigned, 'text'));
        $t->same([130.0, 5.0, 315.0, 20.0], $assigned[1]['bbox']);
        $t->same([[0], [0], [1], [1]], array_map(static fn (array $cell): array => $cell['row_ids'], $assigned));
        $t->same([[0], [1], [0], [1]], array_map(static fn (array $cell): array => $cell['col_ids'], $assigned));
        $t->same([0, 1], $review['rows']);
        $t->same([0, 1], $review['cols']);
        $t->same(2, $review['geometry_boundary_review']['excluded_band_count']);
        $t->same('excluded_outside_table_image', $review['geometry_boundary_review']['row_bands'][2]['status']);
        $t->same('excluded_outside_table_image', $review['geometry_boundary_review']['col_bands'][2]['status']);
    },
];
