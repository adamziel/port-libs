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

$xxyy = static fn (float $x1, float $y1, float $x2, float $y2): array => [$x1, $x2, $y1, $y2];

$recognizedTable = [
    'pnum' => 0,
    'tnum' => 0,
    'coordinate_space' => 'page_image',
    'table_bbox' => [72.0, 150.0, 312.0, 230.0],
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
    'cells' => [
        ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [20]],
        ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [21]],
        ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [11], 'col_ids' => [20]],
        ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [11], 'col_ids' => [21]],
        ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale alias row', 'row_ids' => [99], 'col_ids' => [20]],
        ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale alias col', 'row_ids' => [11], 'col_ids' => [99]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-saved-result-table-bbox-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% saved result table_bbox order WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Saved Result Table Bbox Order Boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale saved-result table bbox line should be replaced.', 'bbox' => [82.0, 176.0, 360.0, 196.0]],
                ['text' => 'After saved result table bbox order.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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

$metadata = $result['metadata'];
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

if (($coordinateReview['status'] ?? null) !== 'translated_to_table_crop') {
    throw new RuntimeException('Expected saved table_bbox alias geometry to localize from page-image to table-crop coordinates.');
}
if (($gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null) !== 'bbox_array_x1_x2_y1_y2_order') {
    throw new RuntimeException('Expected saved table_bbox alias row bands to default to tabled x1,x2,y1,y2 order.');
}
if (($gridReview['geometry_boundary_review']['col_bands'][1]['original_bbox'] ?? null) !== [120.0, 0.0, 240.0, 80.0]) {
    throw new RuntimeException('Expected saved table_bbox alias columns to localize to table-crop coordinates.');
}
if (in_array('Stale alias row', $assignedTexts, true) || in_array('Stale alias col', $assignedTexts, true)) {
    throw new RuntimeException('Expected off-crop saved alias cells to stay out of WordPress table output.');
}
if (str_contains($result['text'], 'Stale saved-result table bbox line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-saved-result-table-bbox-order-currentbase',
    'native_boundary' => 'direct saved tabled records whose crop uses table_bbox still default row and column bands to x1,x2,y1,y2 before crop-local assignment',
    'source_truth' => [
        'upstream_marker' => 'sddai/markerPDF marker/tables/table.py crops high-resolution page images to each table bbox before tabled assignment and Markdown formatting',
        'upstream_tabled' => 'tabled-pdf extract.py saves each TableResult with pnum, tnum, bbox/image_bbox, rows, cols, cells, and row/column bands serialized in x1,x2,y1,y2 order',
        'no_gpu_scope' => 'uses supplied saved table recognition rows/cells and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_status' => $coordinateReview['status'] ?? null,
    'table_bbox_source' => $coordinateReview['table_bbox_source'] ?? null,
    'row_band_source' => $gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null,
    'active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'table_bbox_alias_row_col_order_defaulted' => ($coordinateReview['table_bbox_source'] ?? null) === 'table_bbox'
        && ($gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null) === 'bbox_array_x1_x2_y1_y2_order',
    'offcrop_alias_cells_filtered_from_assignment' => !in_array('Stale alias row', $assignedTexts, true)
        && !in_array('Stale alias col', $assignedTexts, true),
    'stale_pdftext_table_line_excluded' => !str_contains($result['text'], 'Stale saved-result table bbox line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
