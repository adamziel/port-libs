<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_detector_source_boundary_page(array $lines): array
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

function markerpdf_detector_source_boundary_rows(): array
{
    return [
        ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 32.0]],
        ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
    ];
}

function markerpdf_detector_source_boundary_cols(): array
{
    return [
        ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
        ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
    ];
}

function markerpdf_detector_source_boundary_cells(): array
{
    return [
        ['bbox' => ['left' => 340.0, 'top' => 155.0, 'right' => 380.0, 'bottom' => 170.0], 'text' => null],
        ['bbox' => ['xmin' => 162.0, 'ymin' => 170.0, 'xmax' => 82.0, 'ymax' => 155.0], 'text' => null],
        ['bbox' => ['left' => 202.0, 'top' => 155.0, 'width' => 100.0, 'height' => 15.0], 'text' => null],
        ['x' => 82.0, 'y' => 195.0, 'width' => 80.0, 'height' => 20.0, 'text' => null],
        ['bbox' => ['x0' => 202.0, 'y0' => 195.0, 'x1' => 302.0, 'y1' => 215.0], 'text' => null],
    ];
}

return [
    'preserves detector source field-shape metadata after page-image crop localization' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $cells = $recognizer->getCells(
            [[72.0, 150.0, 312.0, 230.0]],
            [['width' => 240, 'height' => 80]],
            [null],
            [0 => markerpdf_detector_source_boundary_cells()],
            true
        );
        $recognized = $recognizer->recognizeTables(
            $cells['table_cells'],
            $cells['needs_ocr'],
            [[
                'rows' => markerpdf_detector_source_boundary_rows(),
                'cols' => markerpdf_detector_source_boundary_cols(),
            ]],
            [0 => ['Feature', 'Status', 'Images', 'Ready']]
        );
        $formatted = $recognizer->formatRecognizedTables($recognized, [['width' => 240, 'height' => 80]]);
        $review = $cells['table_detector_cell_boundary_reviews'][0] ?? [];
        $excluded = $review['cells'][0] ?? [];
        $firstActive = $review['cells'][1] ?? [];
        $secondActive = $review['cells'][2] ?? [];

        $t->same([true], $cells['needs_ocr']);
        $t->same(4, count($cells['table_cells'][0] ?? []));
        $t->same('table_detector_cell_crop_boundary', $review['review_target'] ?? null);
        $t->same('tabled.inference.recognition.get_cells.table_image_detector_cells', $review['upstream_boundary'] ?? null);
        $t->same(5, $review['cell_count'] ?? null);
        $t->same(4, $review['active_cell_count'] ?? null);
        $t->same(1, $review['excluded_cell_count'] ?? null);

        $t->same('excluded_outside_table_image', $excluded['status'] ?? null);
        $t->same([268.0, 5.0, 308.0, 20.0], $excluded['original_bbox'] ?? null);
        $t->same([340.0, 155.0, 380.0, 170.0], $excluded['source_bbox'] ?? null);
        $t->same('page_image', $excluded['source_coordinate_space'] ?? null);
        $t->same('bbox_left_top_right_bottom_fields', $excluded['source_coordinate_source'] ?? null);
        $t->same(false, $excluded['source_endpoint_order_normalized'] ?? null);

        $t->same('within_table_image', $firstActive['status'] ?? null);
        $t->same([10.0, 5.0, 90.0, 20.0], $firstActive['original_bbox'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], $firstActive['source_bbox'] ?? null);
        $t->same('page_image', $firstActive['source_coordinate_space'] ?? null);
        $t->same('bbox_xmin_ymin_xmax_ymax_fields', $firstActive['source_coordinate_source'] ?? null);
        $t->same(true, $firstActive['source_endpoint_order_normalized'] ?? null);
        $t->same('bbox_xmin_ymin_xmax_ymax_fields', $cells['table_cells'][0][0]['source_coordinate_source'] ?? null);
        $t->same(true, $cells['table_cells'][0][0]['source_endpoint_order_normalized'] ?? null);

        $t->same([130.0, 5.0, 230.0, 20.0], $secondActive['original_bbox'] ?? null);
        $t->same([202.0, 155.0, 302.0, 170.0], $secondActive['source_bbox'] ?? null);
        $t->same('bbox_left_top_width_height_fields', $secondActive['source_coordinate_source'] ?? null);
        $t->same(false, $secondActive['source_endpoint_order_normalized'] ?? null);

        $t->same(['Feature', 'Status', 'Images', 'Ready'], array_column($formatted['assigned_cells'][0] ?? [], 'text'));
        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0] ?? '');
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces detector source metadata through supplied WordPress conversion' => static function (TestRunner $t): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-detector-source-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table detector source boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_detector_source_boundary_page([
                        ['text' => 'Detector source table boundary', 'bbox' => [72.0, 48.0, 500.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale detector-source table line should be replaced.', 'bbox' => [72.0, 176.0, 390.0, 196.0]],
                        ['text' => 'After detector source review.', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 500.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [[
                        'rows' => markerpdf_detector_source_boundary_rows(),
                        'cols' => markerpdf_detector_source_boundary_cols(),
                    ]],
                    'table_detect_boxes' => true,
                    'table_detector_cells' => [markerpdf_detector_source_boundary_cells()],
                    'table_ocr_text_lines' => [['Feature', 'Status', 'Images', 'Ready']],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $detectorReview = $result['metadata']['table_detector_cell_boundary_reviews'][0] ?? [];
            $firstActive = $detectorReview['cells'][1] ?? [];
            $assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');

            $t->contains('# Detector Source Table Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After detector source review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale detector-source table line should be replaced.'));
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same([true], $result['metadata']['table_needs_ocr'] ?? null);
            $t->same([4], $result['metadata']['table_cell_counts'] ?? null);
            $t->same('bbox_xmin_ymin_xmax_ymax_fields', $firstActive['source_coordinate_source'] ?? null);
            $t->same(true, $firstActive['source_endpoint_order_normalized'] ?? null);
            $t->same([82.0, 155.0, 162.0, 170.0], $firstActive['source_bbox'] ?? null);
            $t->same('page_image', $firstActive['source_coordinate_space'] ?? null);
            $t->same([10.0, 5.0, 90.0, 20.0], $firstActive['bounded_bbox'] ?? null);
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
