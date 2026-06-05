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

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-page-image-geometry-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table page-image geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Page image table geometry review', 'bbox' => [72.0, 48.0, 470.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale page-space table line should be replaced.', 'bbox' => [72.0, 176.0, 300.0, 196.0]],
                ['text' => 'After page-image geometry review.', 'bbox' => [72.0, 276.0, 470.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 470.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 470.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [[
                'coordinate_space' => 'page_image',
                'rows' => [
                    ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
                    ['row_id' => 1, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
                    ['row_id' => 2, 'bbox' => [72.0, 250.0, 312.0, 270.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
                    ['col_id' => 1, 'bbox' => [192.0, 150.0, 332.0, 230.0]],
                    ['col_id' => 2, 'bbox' => [342.0, 150.0, 362.0, 230.0]],
                ],
                'cells' => [
                    ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
                    ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
                    ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
                    ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
                    ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale page edge', 'row_ids' => [1], 'col_ids' => [2]],
                ],
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
$assignedByText = [];
foreach (($metadata['table_assigned_cells'][0] ?? []) as $cell) {
    $assignedByText[$cell['text']] = $cell;
}
$renderByText = [];
foreach (($gridReview['render_cells'] ?? []) as $renderCell) {
    $renderByText[$renderCell['text']] = $renderCell;
}

if (($coordinateReview['status'] ?? null) !== 'translated_to_table_crop') {
    throw new RuntimeException('Expected page-image table geometry to be translated to crop-local coordinates.');
}
if (in_array('Stale page edge', $assignedTexts, true) || str_contains($result['text'], 'Stale page edge')) {
    throw new RuntimeException('Expected off-crop page-image table cell text to remain outside WordPress output.');
}
if (str_contains($result['text'], 'Stale page-space table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}
if (($assignedByText['Feature']['source_bbox'] ?? null) !== [82.0, 155.0, 162.0, 170.0]) {
    throw new RuntimeException('Expected assigned WordPress table metadata to preserve the page-image Feature source bbox.');
}
if (($renderByText['Feature']['source_cell_bbox'] ?? null) !== [82.0, 155.0, 162.0, 170.0]) {
    throw new RuntimeException('Expected span-grid review metadata to preserve the Feature source bbox.');
}

echo json_encode([
    'scenario' => 'wordpress-table-page-image-geometry-boundary-currentbase',
    'native_boundary' => 'supplied page-image table recognition geometry is translated to the cropped table image before tabled-style assignment and WordPress table review',
    'source_truth' => [
        'upstream' => 'sddai/markerPDF marker/tables/table.py::get_table_boxes crops highres page images before tabled assignment',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Page Image Table Geometry Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After page-image geometry review.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'table_bbox' => $coordinateReview['table_bbox'] ?? null,
    'translation' => $coordinateReview['translation'] ?? null,
    'table_crop_size' => $coordinateReview['table_crop_size'] ?? null,
    'translated_cell_count' => $coordinateReview['translated_cell_count'] ?? null,
    'active_row_band_count' => $boundary['active_row_band_count'] ?? null,
    'active_col_band_count' => $boundary['active_col_band_count'] ?? null,
    'excluded_band_count' => $boundary['excluded_band_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'feature_crop_bbox' => $assignedByText['Feature']['bbox'] ?? null,
    'feature_source_bbox' => $assignedByText['Feature']['source_bbox'] ?? null,
    'feature_source_coordinate_space' => $assignedByText['Feature']['source_coordinate_space'] ?? null,
    'feature_render_source_bbox' => $renderByText['Feature']['source_cell_bbox'] ?? null,
    'page_image_geometry_translated' => ($coordinateReview['status'] ?? null) === 'translated_to_table_crop',
    'page_image_source_geometry_preserved' => ($assignedByText['Feature']['source_bbox'] ?? null) === [82.0, 155.0, 162.0, 170.0]
        && ($renderByText['Feature']['source_cell_bbox'] ?? null) === [82.0, 155.0, 162.0, 170.0],
    'offcrop_page_image_cells_filtered_from_assignment' => !in_array('Stale page edge', $assignedTexts, true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale page-space table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
