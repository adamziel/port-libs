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

$recognizedTable = [
    'coordinate_space' => 'page_image',
    'bbox' => ['x' => 72.0, 'y' => 150.0, 'width' => 240.0, 'height' => 80.0],
    'rows' => [
        ['row_id' => 10, 'bbox' => ['x' => 72.0, 'y' => 150.0, 'width' => 240.0, 'height' => 32.0]],
        ['row_id' => 20, 'x' => 72.0, 'y' => 190.0, 'width' => 240.0, 'height' => 30.0],
        ['row_id' => 99, 'bbox' => ['x' => 72.0, 'y' => 250.0, 'width' => 240.0, 'height' => 20.0]],
    ],
    'cols' => [
        ['col_id' => 30, 'bbox' => ['left' => 72.0, 'top' => 150.0, 'width' => 100.0, 'height' => 80.0]],
        ['col_id' => 40, 'left' => 192.0, 'top' => 150.0, 'width' => 120.0, 'height' => 80.0],
        ['col_id' => 99, 'bbox' => ['left' => 340.0, 'top' => 150.0, 'width' => 20.0, 'height' => 80.0]],
    ],
    'cells' => [
        ['bbox' => ['x' => 82.0, 'y' => 155.0, 'width' => 80.0, 'height' => 15.0], 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [30]],
        ['bbox' => ['left' => 202.0, 'top' => 155.0, 'width' => 100.0, 'height' => 15.0], 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [40]],
        ['bbox' => ['x0' => 82.0, 'y0' => 195.0, 'width' => 80.0, 'height' => 20.0], 'text' => 'Images', 'row_ids' => [20], 'col_ids' => [30]],
        ['bbox' => ['x' => 202.0, 'y' => 195.0, 'width' => 100.0, 'height' => 20.0], 'text' => 'Ready', 'row_ids' => [20], 'col_ids' => [40]],
        ['bbox' => ['x' => 82.0, 'y' => 250.0, 'width' => 80.0, 'height' => 18.0], 'text' => 'Stale row', 'row_ids' => [99], 'col_ids' => [30]],
        ['bbox' => ['left' => 360.0, 'top' => 195.0, 'width' => 20.0, 'height' => 20.0], 'text' => 'Stale column', 'row_ids' => [20], 'col_ids' => [99]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-source-review-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table source review boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Table source review boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale source-shape table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                ['text' => 'After table source review.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
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

$metadata = $result['metadata'];
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
$rowBand = $gridReview['geometry_boundary_review']['row_bands'][0] ?? [];
$colBand = $gridReview['geometry_boundary_review']['col_bands'][0] ?? [];
$renderCell = $gridReview['render_cells'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

if (($rowBand['source_coordinate_source'] ?? null) !== 'bbox_xy_width_height_fields') {
    throw new RuntimeException('Expected localized row-band review to preserve source bbox field shape.');
}
if (($renderCell['source_coordinate_source'] ?? null) !== 'bbox_xy_width_height_fields') {
    throw new RuntimeException('Expected localized render-cell review to preserve source bbox field shape.');
}
if (str_contains($result['text'], 'Stale source-shape table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-source-review-boundary-currentbase',
    'native_boundary' => 'saved table geometry source field-shape metadata survives page-image localization into WordPress table review',
    'source_truth' => [
        'upstream' => 'marker.tables.table crops rendered page images before tabled.assignment.assign_rows_columns; saved tabled rows, columns, and SpanTableCell records retain their source bbox representation for review overlays',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_status' => $metadata['table_coordinate_space_reviews'][0]['status'] ?? null,
    'row_source_coordinate_source' => $rowBand['source_coordinate_source'] ?? null,
    'col_source_coordinate_source' => $colBand['source_coordinate_source'] ?? null,
    'cell_source_coordinate_source' => $renderCell['source_coordinate_source'] ?? null,
    'cell_source_bbox' => $renderCell['source_cell_bbox'] ?? null,
    'crop_cell_source_coordinate_source' => $cropReview['cells'][0]['source_coordinate_source'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'source_shape_preserved_for_row_bands' => ($rowBand['source_coordinate_source'] ?? null) === 'bbox_xy_width_height_fields',
    'source_shape_preserved_for_cells' => ($renderCell['source_coordinate_source'] ?? null) === 'bbox_xy_width_height_fields',
    'offcrop_source_shape_cells_filtered' => !in_array('Stale row', $assignedTexts, true)
        && !in_array('Stale column', $assignedTexts, true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale source-shape table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
