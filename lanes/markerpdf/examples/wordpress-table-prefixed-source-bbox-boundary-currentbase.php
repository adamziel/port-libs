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
    'table_bbox' => [72.0, 150.0, 312.0, 230.0],
    'rows' => [
        ['row_id' => 10, 'source_left' => 72.0, 'source_top' => 150.0, 'source_right' => 312.0, 'source_bottom' => 182.0, 'source_coordinate_space' => 'page_image'],
        ['row_id' => 11, 'original_x' => 72.0, 'original_y' => 190.0, 'original_width' => 240.0, 'original_height' => 30.0, 'original_coordinate_space' => 'page_image'],
        ['row_id' => 99, 'source_x1' => 72.0, 'source_y1' => 250.0, 'source_x2' => 312.0, 'source_y2' => 268.0, 'source_coordinate_space' => 'page_image'],
    ],
    'cols' => [
        ['col_id' => 20, 'source_x1' => 72.0, 'source_y1' => 150.0, 'source_x2' => 172.0, 'source_y2' => 230.0, 'source_coordinate_space' => 'page_image'],
        ['col_id' => 21, 'original_left' => 192.0, 'original_top' => 150.0, 'original_width' => 120.0, 'original_height' => 80.0, 'original_coordinate_space' => 'page_image'],
        ['col_id' => 99, 'source_left' => 342.0, 'source_top' => 150.0, 'source_width' => 20.0, 'source_height' => 80.0, 'source_coordinate_space' => 'page_image'],
    ],
    'cells' => [
        ['source_left' => 82.0, 'source_top' => 155.0, 'source_right' => 162.0, 'source_bottom' => 170.0, 'source_coordinate_space' => 'page_image', 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [20]],
        ['original_x' => 202.0, 'original_y' => 155.0, 'original_w' => 100.0, 'original_h' => 15.0, 'original_coordinate_space' => 'page_image', 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [21]],
        ['source_page_image_left' => 82.0, 'source_page_image_top' => 195.0, 'source_page_image_right' => 162.0, 'source_page_image_bottom' => 215.0, 'source_coordinate_space' => 'page_image', 'text' => 'Images', 'row_ids' => [11], 'col_ids' => [20]],
        ['source_x' => 202.0, 'source_y' => 195.0, 'source_width' => 100.0, 'source_height' => 20.0, 'source_coordinate_space' => 'page_image', 'text' => 'Ready', 'row_ids' => [11], 'col_ids' => [21]],
        ['source_left' => 82.0, 'source_top' => 250.0, 'source_right' => 162.0, 'source_bottom' => 268.0, 'source_coordinate_space' => 'page_image', 'text' => 'Stale prefixed row', 'row_ids' => [99], 'col_ids' => [20]],
        ['original_left' => 360.0, 'original_top' => 195.0, 'original_right' => 382.0, 'original_bottom' => 215.0, 'original_coordinate_space' => 'page_image', 'text' => 'Stale prefixed col', 'row_ids' => [11], 'col_ids' => [99]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-prefixed-source-bbox-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% prefixed source bbox WordPress table fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Prefixed source bbox table boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale prefixed source table line should be replaced.', 'bbox' => [82.0, 176.0, 360.0, 196.0]],
                ['text' => 'After prefixed source table review.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
    throw new RuntimeException('Expected prefixed source table geometry to localize to table-crop coordinates.');
}
if (($gridReview['render_cells'][0]['source_coordinate_source'] ?? null) !== 'source_bbox_left_top_right_bottom_fields') {
    throw new RuntimeException('Expected source_* named fields to preserve source geometry provenance.');
}
if (($gridReview['render_cells'][2]['source_coordinate_source'] ?? null) !== 'source_page_image_bbox_left_top_right_bottom_fields') {
    throw new RuntimeException('Expected source_page_image_* named fields to preserve page-image geometry provenance.');
}
if (in_array('Stale prefixed row', $assignedTexts, true) || in_array('Stale prefixed col', $assignedTexts, true)) {
    throw new RuntimeException('Expected off-crop prefixed source cells to stay out of WordPress table output.');
}
if (str_contains($result['text'], 'Stale prefixed source table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale prefixed source pdftext line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-prefixed-source-bbox-boundary-currentbase',
    'native_boundary' => 'source_*, original_*, and source_page_image_* named geometry fields are localized to table-crop geometry before WordPress table output',
    'source_truth' => [
        'upstream_marker' => 'markerPDF routes rendered page crops through marker.tables.table before tabled assignment and Markdown formatting',
        'upstream_tabled' => 'saved/review table sidecars may preserve original table geometry separately from primary bbox fields',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells only; does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_status' => $coordinateReview['status'] ?? null,
    'active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'first_cell_source' => $gridReview['render_cells'][0]['source_coordinate_source'] ?? null,
    'third_cell_source' => $gridReview['render_cells'][2]['source_coordinate_source'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'prefixed_source_geometry_localized' => ($coordinateReview['status'] ?? null) === 'translated_to_table_crop'
        && ($gridReview['render_cells'][0]['source_coordinate_source'] ?? null) === 'source_bbox_left_top_right_bottom_fields',
    'source_page_image_geometry_preserved' => ($gridReview['render_cells'][2]['source_coordinate_source'] ?? null) === 'source_page_image_bbox_left_top_right_bottom_fields',
    'offcrop_prefixed_cells_filtered_from_assignment' => !in_array('Stale prefixed row', $assignedTexts, true)
        && !in_array('Stale prefixed col', $assignedTexts, true),
    'stale_pdftext_table_line_excluded' => !str_contains($result['text'], 'Stale prefixed source table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
