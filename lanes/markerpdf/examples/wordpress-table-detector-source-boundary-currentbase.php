<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdftextPage = static function (array $lines): array {
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
};

$detectorCells = [
    ['bbox' => ['left' => 340.0, 'top' => 155.0, 'right' => 380.0, 'bottom' => 170.0], 'text' => null],
    ['bbox' => ['xmin' => 162.0, 'ymin' => 170.0, 'xmax' => 82.0, 'ymax' => 155.0], 'text' => null],
    ['bbox' => ['left' => 202.0, 'top' => 155.0, 'width' => 100.0, 'height' => 15.0], 'text' => null],
    ['x' => 82.0, 'y' => 195.0, 'width' => 80.0, 'height' => 20.0, 'text' => null],
    ['bbox' => ['x0' => 202.0, 'y0' => 195.0, 'x1' => 302.0, 'y1' => 215.0], 'text' => null],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-detector-source-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table detector source boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
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
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 32.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
                ],
            ]],
            'table_detect_boxes' => true,
            'table_detector_cells' => [$detectorCells],
            'table_ocr_text_lines' => [['Feature', 'Status', 'Images', 'Ready']],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    if (is_file($pdfPath)) {
        unlink($pdfPath);
    }
}

$metadata = $result['metadata'];
$detectorReview = $metadata['table_detector_cell_boundary_reviews'][0] ?? [];
$firstActive = $detectorReview['cells'][1] ?? [];
$excluded = $detectorReview['cells'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
$sourceShapePreserved = ($firstActive['source_coordinate_source'] ?? null) === 'bbox_xmin_ymin_xmax_ymax_fields'
    && (($firstActive['source_endpoint_order_normalized'] ?? null) === true)
    && (($firstActive['source_coordinate_space'] ?? null) === 'page_image');
$sourceBboxLocalized = ($firstActive['source_bbox'] ?? null) === [82.0, 155.0, 162.0, 170.0]
    && (($firstActive['bounded_bbox'] ?? null) === [10.0, 5.0, 90.0, 20.0]);
$sourceOrderPreserved = $assignedTexts === ['Feature', 'Status', 'Images', 'Ready'];
$offcropExcluded = ($detectorReview['excluded_cell_count'] ?? null) === 1
    && (($excluded['status'] ?? null) === 'excluded_outside_table_image')
    && (($excluded['source_coordinate_source'] ?? null) === 'bbox_left_top_right_bottom_fields');
$staleTextExcluded = !str_contains($result['text'], 'Stale detector-source table line should be replaced.');

if (!$sourceShapePreserved || !$sourceBboxLocalized || !$sourceOrderPreserved || !$offcropExcluded || !$staleTextExcluded) {
    throw new RuntimeException('Expected detector source geometry to survive table-crop localization before OCR assignment.');
}

echo json_encode([
    'scenario' => 'wordpress-table-detector-source-boundary-currentbase',
    'native_boundary' => 'forced-OCR detector cells localized from page-image space retain source bbox field-shape and endpoint provenance in WordPress review metadata',
    'source_truth' => [
        'upstream' => 'marker.tables.table crops rendered page images before tabled recognition and consumes tabled cell bbox rows for assignment/review handoff',
        'no_gpu_scope' => 'uses supplied detector cells, supplied table rows/columns, and supplied OCR text; no Surya, OCR models, Python, or external PDF tools are executed',
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'table_needs_ocr' => $metadata['table_needs_ocr'] ?? [],
    'table_cell_counts_after_detector_crop' => $metadata['table_cell_counts'] ?? [],
    'detector_boundary_target' => $detectorReview['review_target'] ?? null,
    'detector_boundary_active_cell_count' => $detectorReview['active_cell_count'] ?? null,
    'detector_boundary_excluded_cell_count' => $detectorReview['excluded_cell_count'] ?? null,
    'first_active_source_bbox' => $firstActive['source_bbox'] ?? null,
    'first_active_table_crop_bbox' => $firstActive['bounded_bbox'] ?? null,
    'first_active_source_coordinate_space' => $firstActive['source_coordinate_space'] ?? null,
    'first_active_source_coordinate_source' => $firstActive['source_coordinate_source'] ?? null,
    'first_active_endpoint_order_normalized' => $firstActive['source_endpoint_order_normalized'] ?? null,
    'offcrop_source_coordinate_source' => $excluded['source_coordinate_source'] ?? null,
    'source_shape_preserved_after_crop_localization' => $sourceShapePreserved,
    'source_bbox_localized_to_table_crop' => $sourceBboxLocalized,
    'ocr_source_order_preserved_after_crop_filter' => $sourceOrderPreserved,
    'offcrop_detector_cell_excluded_before_ocr' => $offcropExcluded,
    'excluded_stale_pdftext_table_line' => $staleTextExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
