<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xxyy = static fn (float $x1, float $y1, float $x2, float $y2): array => [$x1, $x2, $y1, $y2];

$page = static function (array $lines): array {
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

$recognizedTable = [
    'bbox' => [72.0, 150.0, 312.0, 230.0],
    'coordinate_space' => 'page_image',
    'rows_cols' => [[
        'pnum' => 0,
        'tnum' => 0,
        'bbox' => [72.0, 150.0, 312.0, 230.0],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'rows' => [
            ['row_id' => 10, 'bbox' => $xxyy(72.0, 150.0, 312.0, 182.0)],
            ['row_id' => 11, 'bbox' => $xxyy(72.0, 190.0, 312.0, 220.0)],
            ['row_id' => 99, 'bbox' => $xxyy(72.0, 250.0, 312.0, 268.0)],
        ],
        'cols' => [
            ['col_id' => 20, 'bbox' => $xxyy(72.0, 150.0, 172.0, 230.0)],
            ['col_id' => 21, 'bbox' => $xxyy(192.0, 150.0, 312.0, 230.0)],
            ['col_id' => 99, 'bbox' => $xxyy(342.0, 150.0, 362.0, 230.0)],
        ],
    ]],
    'cells' => [
        ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [20]],
        ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [21]],
        ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [11], 'col_ids' => [20]],
        ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [11], 'col_ids' => [21]],
        ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale nested saved row', 'row_ids' => [99], 'col_ids' => [20]],
        ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale nested saved col', 'row_ids' => [11], 'col_ids' => [99]],
    ],
];

$direct = (new TableRecognizer())->formatRecognizedTables([$recognizedTable], [['width' => 240, 'height' => 80]]);

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-rows-cols-saved-result-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% nested saved rows-cols order WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $page([
                ['text' => 'Nested saved rows columns order boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale nested saved table line should be replaced.', 'bbox' => [82.0, 176.0, 360.0, 196.0]],
                ['text' => 'After nested saved table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 560.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [$recognizedTable],
            'table_text_lines' => [['blocks' => []]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    if (is_file($pdfPath)) {
        unlink($pdfPath);
    }
}

$directLocalized = $direct['recognized_tables'][0] ?? [];
$directAssignedTexts = array_column($direct['assigned_cells'][0] ?? [], 'text');
$metadata = $result['metadata'];
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

if (($directLocalized['rows'][0]['source_coordinate_source'] ?? null) !== 'bbox_array_x1_x2_y1_y2_order') {
    throw new RuntimeException('Expected nested saved rows_cols row bands to default to tabled x1,x2,y1,y2 order.');
}
if (($directLocalized['cols'][1]['bbox'] ?? null) !== [120.0, 0.0, 240.0, 80.0]) {
    throw new RuntimeException('Expected nested saved rows_cols column bands to localize with tabled x1,x2,y1,y2 order.');
}
if ($directAssignedTexts !== ['Feature', 'Status', 'Images', 'Ready']) {
    throw new RuntimeException('Expected nested saved rows_cols assignments to retain both table columns.');
}
if (($coordinateReview['status'] ?? null) !== 'translated_to_table_crop') {
    throw new RuntimeException('Expected nested saved rows_cols geometry to translate into table-crop space.');
}
if (in_array('Stale nested saved row', $assignedTexts, true) || in_array('Stale nested saved col', $assignedTexts, true)) {
    throw new RuntimeException('Expected off-crop nested saved rows_cols cells to be filtered before WordPress output.');
}
if (str_contains($result['text'], 'Stale nested saved table line should be replaced.')) {
    throw new RuntimeException('Expected supplied nested saved rows_cols table to replace stale pdftext table line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-rows-cols-saved-result-order-currentbase',
    'native_boundary' => 'nested tabled ExtractPageResult.rows_cols saved TableResult bands default to x1,x2,y1,y2 order before table-crop localization and WordPress table rendering',
    'source_truth' => [
        'upstream_marker' => 'sddai/markerPDF crops rendered page images before delegating table structure to tabled',
        'upstream_tabled' => 'tabled-pdf saved TableResult rows and cols are x1,x2,y1,y2 band rectangles; ExtractPageResult may nest those rows_cols results beside page-level cells',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells and does not run Surya, tabled models, OCR, Python, PDFium, PIL, or external PDF tools',
    ],
    'direct_row_source_coordinate_source' => $directLocalized['rows'][0]['source_coordinate_source'] ?? null,
    'direct_col_source_coordinate_source' => $directLocalized['cols'][1]['source_coordinate_source'] ?? null,
    'direct_second_column_bbox' => $directLocalized['cols'][1]['bbox'] ?? null,
    'coordinate_status' => $coordinateReview['status'] ?? null,
    'coordinate_translated_row_band_count' => $coordinateReview['translated_row_band_count'] ?? null,
    'coordinate_translated_col_band_count' => $coordinateReview['translated_col_band_count'] ?? null,
    'grid_row_source_coordinate_source' => $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null,
    'grid_col_source_coordinate_source' => $gridReview['geometry_boundary_review']['col_bands'][1]['source_coordinate_source'] ?? null,
    'assigned_crop_active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'assigned_crop_excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'nested_saved_rows_cols_order_defaulted' => ($directLocalized['rows'][0]['source_coordinate_source'] ?? null) === 'bbox_array_x1_x2_y1_y2_order'
        && ($directLocalized['cols'][1]['source_coordinate_source'] ?? null) === 'bbox_array_x1_x2_y1_y2_order',
    'right_column_cells_retained' => in_array('Status', $assignedTexts, true) && in_array('Ready', $assignedTexts, true),
    'offcrop_nested_saved_cells_filtered' => !in_array('Stale nested saved row', $assignedTexts, true)
        && !in_array('Stale nested saved col', $assignedTexts, true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale nested saved table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_ocr' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
