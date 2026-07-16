<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$normPage = static fn (array $bbox): array => [
    round(((float) $bbox[0] / 612.0) * 1000.0, 6),
    round(((float) $bbox[1] / 792.0) * 1000.0, 6),
    round(((float) $bbox[2] / 612.0) * 1000.0, 6),
    round(((float) $bbox[3] / 792.0) * 1000.0, 6),
];

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
    'table_bbox' => $normPage([72.0, 150.0, 312.0, 230.0]),
    'table_bbox_coordinate_space' => 'normalized_page_image',
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'rows_coordinate_space' => 'normalized_page_image',
    'cols_coordinate_space' => 'normalized_page_image',
    'cells_coordinate_space' => 'normalized_page_image',
    'ocr_grid_border_conflicts_coordinate_space' => 'normalized_page_image',
    'rows' => [
        ['row_id' => 0, 'bbox' => $normPage([72.0, 150.0, 312.0, 182.0])],
        ['row_id' => 1, 'bbox' => $normPage([72.0, 190.0, 312.0, 220.0])],
        ['row_id' => 99, 'bbox' => $normPage([72.0, 250.0, 312.0, 270.0])],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => $normPage([72.0, 150.0, 172.0, 230.0])],
        ['col_id' => 1, 'bbox' => $normPage([192.0, 150.0, 312.0, 230.0])],
        ['col_id' => 99, 'bbox' => $normPage([342.0, 150.0, 362.0, 230.0])],
    ],
    'cells' => [
        ['bbox' => $normPage([82.0, 155.0, 162.0, 170.0]), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['bbox' => $normPage([202.0, 155.0, 302.0, 170.0]), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bbox' => $normPage([82.0, 195.0, 162.0, 215.0]), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox' => $normPage([202.0, 195.0, 302.0, 215.0]), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox' => $normPage([82.0, 250.0, 162.0, 268.0]), 'text' => 'Stale normalized row', 'row_ids' => [99], 'col_ids' => [0]],
        ['bbox' => $normPage([360.0, 195.0, 382.0, 215.0]), 'text' => 'Stale normalized col', 'row_ids' => [1], 'col_ids' => [99]],
    ],
    'ocr_grid_border_conflicts' => [[
        'ocr_index' => 0,
        'text' => 'Wide normalized-crop OCR',
        'bbox' => $normPage([82.0, 155.0, 302.0, 215.0]),
        'candidate_cell_indexes' => [0, 1, 2],
        'candidate_cell_bboxes' => [
            $normPage([82.0, 155.0, 162.0, 170.0]),
            $normPage([202.0, 155.0, 302.0, 170.0]),
            $normPage([82.0, 195.0, 162.0, 215.0]),
        ],
        'assigned_cell_index' => 0,
        'spans_grid_border' => true,
    ]],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-normalized-crop-bbox-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% normalized crop bbox table geometry WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Normalized crop bbox table geometry review', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale normalized-crop table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                ['text' => 'After normalized crop bbox table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$boundary = $gridReview['geometry_boundary_review'] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
$localized = ($coordinateReview['status'] ?? null) === 'translated_and_normalized_to_table_crop'
    && ($coordinateReview['table_bbox_source_coordinate_space'] ?? null) === 'normalized_page_image'
    && ($coordinateReview['table_bbox_page_image_normalization_size'] ?? null) === ['width' => 612, 'height' => 792];
$staleExcluded = !in_array('Stale normalized row', $assignedTexts, true)
    && !in_array('Stale normalized col', $assignedTexts, true)
    && !str_contains($result['text'], 'Stale normalized-crop table line should be replaced.');

if (!$localized || !$staleExcluded || !str_contains($result['text'], '| Feature | Status |')) {
    throw new RuntimeException('Expected normalized table crop bbox to localize before WordPress table output.');
}

echo json_encode([
    'scenario' => 'wordpress-table-normalized-crop-bbox-boundary-currentbase',
    'native_boundary' => 'explicit normalized page-image table_bbox is unnormalized against image_bbox before rows, columns, cells, and OCR conflict geometry are translated to table crop coordinates',
    'source_truth' => [
        'upstream' => 'marker/tables/table.py crops each high-resolution page table bbox before tabled assignment; tabled ExtractPageResult carries bboxes and image_bboxes as page-image crop records',
        'no_gpu_scope' => 'uses supplied table recognition geometry and does not run Surya, tabled model inference, OCR, Python, PDFium, PIL, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Normalized Crop Bbox Table Geometry Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After normalized crop bbox table.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'source_table_bbox' => $coordinateReview['source_table_bbox'] ?? null,
    'table_bbox' => $coordinateReview['table_bbox'] ?? null,
    'table_bbox_source_coordinate_space' => $coordinateReview['table_bbox_source_coordinate_space'] ?? null,
    'table_bbox_page_image_normalization_size' => $coordinateReview['table_bbox_page_image_normalization_size'] ?? null,
    'normalized_cell_count' => $coordinateReview['normalized_cell_count'] ?? null,
    'normalized_conflict_count' => $coordinateReview['normalized_conflict_count'] ?? null,
    'active_row_band_count' => $boundary['active_row_band_count'] ?? null,
    'active_col_band_count' => $boundary['active_col_band_count'] ?? null,
    'excluded_band_count' => $boundary['excluded_band_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'normalized_crop_bbox_localized' => $localized,
    'stale_normalized_cells_filtered' => $staleExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
