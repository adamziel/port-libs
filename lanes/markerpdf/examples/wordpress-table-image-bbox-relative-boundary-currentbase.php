<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-image-bbox-relative-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% image-bbox-relative table geometry WordPress fixture\n%%EOF");

$recognizedTable = [
    'coordinate_space' => 'image_bbox_relative',
    'image_bbox' => [100.0, 200.0, 712.0, 992.0],
    'bbox' => [72.0, 150.0, 312.0, 230.0],
    'rows' => [
        ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
        ['row_id' => 1, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
        ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 270.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
        ['col_id' => 1, 'bbox' => [192.0, 150.0, 312.0, 230.0]],
        ['col_id' => 99, 'bbox' => [340.0, 150.0, 362.0, 230.0]],
    ],
    'cells' => [
        ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale relative row', 'row_ids' => [99], 'col_ids' => [0]],
        ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale relative column', 'row_ids' => [1], 'col_ids' => [99]],
    ],
    'ocr_grid_border_conflicts' => [[
        'ocr_index' => 0,
        'text' => 'Wide relative OCR',
        'bbox' => [82.0, 155.0, 302.0, 215.0],
        'candidate_cell_indexes' => [0, 1, 2],
        'candidate_overlaps' => [1.0, 0.44, 0.36],
        'candidate_cell_bboxes' => [
            [82.0, 155.0, 162.0, 170.0],
            [202.0, 155.0, 302.0, 170.0],
            [82.0, 195.0, 162.0, 215.0],
        ],
        'assigned_cell_index' => 0,
        'spans_grid_border' => true,
    ]],
];

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [[
            'page' => 0,
            'bbox' => [0.0, 0.0, 612.0, 792.0],
            'rotation' => 0,
            'blocks' => [[
                'lines' => [
                    [
                        'bbox' => [72.0, 48.0, 520.0, 68.0],
                        'spans' => [[
                            'text' => 'Image Bbox Relative Table Geometry Review',
                            'bbox' => [72.0, 48.0, 520.0, 68.0],
                            'font' => ['name' => 'Helvetica-Bold', 'flags' => 0, 'weight' => 700, 'size' => 18],
                        ]],
                    ],
                    [
                        'bbox' => [82.0, 176.0, 300.0, 196.0],
                        'spans' => [[
                            'text' => 'Stale image-bbox-relative table line should be replaced.',
                            'bbox' => [82.0, 176.0, 300.0, 196.0],
                            'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12],
                        ]],
                    ],
                    [
                        'bbox' => [72.0, 276.0, 520.0, 294.0],
                        'spans' => [[
                            'text' => 'After image bbox relative table.',
                            'bbox' => [72.0, 276.0, 520.0, 294.0],
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
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 520.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
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
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$boundary = $gridReview['geometry_boundary_review'] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
$relativeLocalized = ($coordinateReview['status'] ?? null) === 'translated_to_table_crop'
    && (($coordinateReview['source_coordinate_spaces']['cells'] ?? null) === 'image_bbox_relative');
$staleCellsExcluded = !in_array('Stale relative row', $assignedTexts, true)
    && !in_array('Stale relative column', $assignedTexts, true)
    && !str_contains($result['text'], 'Stale relative row')
    && !str_contains($result['text'], 'Stale relative column');
$stalePdftextExcluded = !str_contains($result['text'], 'Stale image-bbox-relative table line should be replaced.');

if (!$relativeLocalized || !$staleCellsExcluded || !$stalePdftextExcluded) {
    throw new RuntimeException('Expected image-bbox-relative table geometry to localize into the supplied table crop before WordPress output.');
}

echo json_encode([
    'scenario' => 'wordpress-table-image-bbox-relative-boundary-currentbase',
    'native_boundary' => 'saved tabled result geometry declared relative to image_bbox is translated into the cropped table image before WordPress table review',
    'source_truth' => [
        'upstream_tabled' => 'tabled-pdf result JSON documents image_bbox as the containing image bbox and bbox as the table bbox relative to that image before cell/row/column formatting',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Image Bbox Relative Table Geometry Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After image bbox relative table.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'table_crop_size' => $coordinateReview['table_crop_size'] ?? null,
    'image_bbox' => $recognizedTable['image_bbox'],
    'table_bbox' => $coordinateReview['table_bbox'] ?? null,
    'translation' => $coordinateReview['translation'] ?? null,
    'source_coordinate_spaces' => $coordinateReview['source_coordinate_spaces'] ?? null,
    'translated_cell_count' => $coordinateReview['translated_cell_count'] ?? null,
    'translated_conflict_count' => $coordinateReview['translated_conflict_count'] ?? null,
    'active_row_band_count' => $boundary['active_row_band_count'] ?? null,
    'active_col_band_count' => $boundary['active_col_band_count'] ?? null,
    'excluded_band_count' => $boundary['excluded_band_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'image_bbox_relative_translated_to_crop' => $relativeLocalized,
    'stale_relative_cells_filtered' => $staleCellsExcluded,
    'excluded_stale_pdftext_table_line' => $stalePdftextExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
