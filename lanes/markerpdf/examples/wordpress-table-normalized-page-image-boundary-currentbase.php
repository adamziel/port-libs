<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$normalizedPageBbox = static fn (array $bbox): array => [
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
    'coordinate_space' => 'normalized_page_image',
    'bbox' => [72.0, 150.0, 312.0, 230.0],
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'rows' => [
        ['row_id' => 0, 'bbox' => $normalizedPageBbox([72.0, 150.0, 312.0, 182.0])],
        ['row_id' => 1, 'bbox' => $normalizedPageBbox([72.0, 190.0, 312.0, 220.0])],
        ['row_id' => 99, 'bbox' => $normalizedPageBbox([72.0, 250.0, 312.0, 270.0])],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => $normalizedPageBbox([72.0, 150.0, 172.0, 230.0])],
        ['col_id' => 1, 'bbox' => $normalizedPageBbox([192.0, 150.0, 312.0, 230.0])],
        ['col_id' => 99, 'bbox' => $normalizedPageBbox([342.0, 150.0, 362.0, 230.0])],
    ],
    'cells' => [
        ['bbox' => $normalizedPageBbox([82.0, 155.0, 162.0, 170.0]), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['bbox' => $normalizedPageBbox([202.0, 155.0, 302.0, 170.0]), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bbox' => $normalizedPageBbox([82.0, 195.0, 162.0, 215.0]), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox' => $normalizedPageBbox([202.0, 195.0, 302.0, 215.0]), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox' => $normalizedPageBbox([82.0, 250.0, 162.0, 268.0]), 'text' => 'Stale normalized row', 'row_ids' => [99], 'col_ids' => [0]],
        ['bbox' => $normalizedPageBbox([360.0, 195.0, 382.0, 215.0]), 'text' => 'Stale normalized col', 'row_ids' => [1], 'col_ids' => [99]],
    ],
    'ocr_grid_border_conflicts' => [[
        'ocr_index' => 0,
        'text' => 'Wide page-normalized OCR',
        'bbox' => $normalizedPageBbox([82.0, 155.0, 302.0, 215.0]),
        'candidate_cell_indexes' => [0, 1, 2],
        'candidate_overlaps' => [1.0, 0.44, 0.36],
        'candidate_cell_bboxes' => [
            $normalizedPageBbox([82.0, 155.0, 162.0, 170.0]),
            $normalizedPageBbox([202.0, 155.0, 302.0, 170.0]),
            $normalizedPageBbox([82.0, 195.0, 162.0, 215.0]),
        ],
        'assigned_cell_index' => 0,
        'spans_grid_border' => true,
    ]],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-normalized-page-image-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% normalized page-image table geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Normalized page image table geometry review', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale page-normalized table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                ['text' => 'After normalized page image geometry review.', 'bbox' => [72.0, 336.0, 520.0, 354.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 520.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 336.0, 520.0, 354.0]],
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
$pageNormalizedLocalized = ($coordinateReview['status'] ?? null) === 'translated_and_normalized_to_table_crop'
    && (($coordinateReview['page_image_normalization_scale']['x'] ?? null) === 0.612)
    && (($coordinateReview['page_image_normalization_scale']['y'] ?? null) === 0.792);
$staleCellsExcluded = !in_array('Stale normalized row', $assignedTexts, true)
    && !in_array('Stale normalized col', $assignedTexts, true)
    && !str_contains($result['text'], 'Stale normalized row')
    && !str_contains($result['text'], 'Stale normalized col');
$stalePdftextExcluded = !str_contains($result['text'], 'Stale page-normalized table line should be replaced.');

if (!$pageNormalizedLocalized || !$staleCellsExcluded || !$stalePdftextExcluded) {
    throw new RuntimeException('Expected normalized page-image table geometry to localize into the supplied table crop before WordPress output.');
}

echo json_encode([
    'scenario' => 'wordpress-table-normalized-page-image-boundary-currentbase',
    'native_boundary' => 'supplied 1000-unit page-image table recognition geometry is scaled by full page image_bbox and translated into the cropped table image before WordPress table review',
    'source_truth' => [
        'upstream' => 'tabled recognition consumes rows/cells in the cropped table image; marker bbox helpers unnormalize 0-1000 coordinates against the declared image extent',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Normalized Page Image Table Geometry Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After normalized page image geometry review.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'table_crop_size' => $coordinateReview['table_crop_size'] ?? null,
    'table_bbox' => $coordinateReview['table_bbox'] ?? null,
    'translation' => $coordinateReview['translation'] ?? null,
    'normalization_scale' => $coordinateReview['normalization_scale'] ?? null,
    'page_image_normalization_scale' => $coordinateReview['page_image_normalization_scale'] ?? null,
    'translated_cell_count' => $coordinateReview['translated_cell_count'] ?? null,
    'normalized_cell_count' => $coordinateReview['normalized_cell_count'] ?? null,
    'normalized_conflict_count' => $coordinateReview['normalized_conflict_count'] ?? null,
    'active_row_band_count' => $boundary['active_row_band_count'] ?? null,
    'active_col_band_count' => $boundary['active_col_band_count'] ?? null,
    'excluded_band_count' => $boundary['excluded_band_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'normalized_page_image_scaled_to_page' => $pageNormalizedLocalized,
    'normalized_page_image_translated_to_crop' => $pageNormalizedLocalized,
    'stale_page_normalized_cells_filtered' => $staleCellsExcluded,
    'excluded_stale_pdftext_table_line' => $stalePdftextExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
