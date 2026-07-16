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

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-normalized-geometry-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% normalized table geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Normalized table geometry review', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale normalized table line should be replaced.', 'bbox' => [72.0, 226.0, 360.0, 246.0]],
                ['text' => 'After normalized geometry review.', 'bbox' => [72.0, 336.0, 480.0, 354.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [50.0, 200.0, 300.0, 300.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 336.0, 480.0, 354.0]],
                ],
            ]],
            'recognized_tables' => [[
                'coordinate_space' => 'normalized_table',
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 1000.0, 300.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 400.0, 1000.0, 700.0]],
                    ['row_id' => 99, 'bbox' => [0.0, 1150.0, 1000.0, 1300.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 400.0, 1000.0]],
                    ['col_id' => 1, 'bbox' => [500.0, 0.0, 900.0, 1000.0]],
                    ['col_id' => 99, 'bbox' => [1100.0, 0.0, 1200.0, 1000.0]],
                ],
                'cells' => [
                    ['bbox' => [40.0, 50.0, 360.0, 240.0], 'text' => 'Feature'],
                    ['bbox' => [520.0, 50.0, 880.0, 240.0], 'text' => 'Status'],
                    ['bbox' => [40.0, 450.0, 360.0, 650.0], 'text' => 'Images'],
                    ['bbox' => [520.0, 450.0, 880.0, 650.0], 'text' => 'Ready'],
                    ['bbox' => [1120.0, 450.0, 1200.0, 650.0], 'text' => 'Stale normalized edge'],
                ],
                'ocr_grid_border_conflicts' => [[
                    'ocr_index' => 0,
                    'text' => 'Wide normalized OCR',
                    'bbox' => [0.0, 0.0, 900.0, 650.0],
                    'candidate_cell_indexes' => [0, 1, 2],
                    'candidate_overlaps' => [1.0, 0.44, 0.36],
                    'candidate_cell_bboxes' => [
                        [40.0, 50.0, 360.0, 240.0],
                        [520.0, 50.0, 880.0, 240.0],
                        [40.0, 450.0, 360.0, 650.0],
                    ],
                    'assigned_cell_index' => 0,
                    'spans_grid_border' => true,
                ]],
            ]],
            'table_text_lines' => [['blocks' => []]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($pdfPath);
}

$metadata = $result['metadata'];
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$boundary = $gridReview['geometry_boundary_review'] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

if (($coordinateReview['status'] ?? null) !== 'normalized_to_table_crop') {
    throw new RuntimeException('Expected normalized table geometry to be unnormalized to crop-local coordinates.');
}
if (in_array('Stale normalized edge', $assignedTexts, true) || str_contains($result['text'], 'Stale normalized edge')) {
    throw new RuntimeException('Expected off-crop normalized table cell text to remain outside WordPress output.');
}
if (str_contains($result['text'], 'Stale normalized table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-normalized-geometry-boundary-currentbase',
    'native_boundary' => 'supplied normalized 1000-unit table recognition geometry is unnormalized to the cropped table image before tabled-style assignment and WordPress table review',
    'source_truth' => [
        'upstream' => 'marker.schema.bbox.unnormalize_box maps 0-1000 bbox coordinates to image width and height; marker tables crop page images before tabled assignment',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Normalized Table Geometry Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After normalized geometry review.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'table_crop_size' => $coordinateReview['table_crop_size'] ?? null,
    'normalization_scale' => $coordinateReview['normalization_scale'] ?? null,
    'normalized_cell_count' => $coordinateReview['normalized_cell_count'] ?? null,
    'normalized_conflict_count' => $coordinateReview['normalized_conflict_count'] ?? null,
    'active_row_band_count' => $boundary['active_row_band_count'] ?? null,
    'active_col_band_count' => $boundary['active_col_band_count'] ?? null,
    'excluded_band_count' => $boundary['excluded_band_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'normalized_geometry_unnormalized' => ($coordinateReview['status'] ?? null) === 'normalized_to_table_crop',
    'offcrop_normalized_cells_filtered_from_assignment' => !in_array('Stale normalized edge', $assignedTexts, true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale normalized table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
