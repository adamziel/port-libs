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

$normalizePageBbox = static function (array $bbox): array {
    return [
        round(((float) $bbox[0]) / 612.0 * 1000.0, 6),
        round(((float) $bbox[1]) / 792.0 * 1000.0, 6),
        round(((float) $bbox[2]) / 612.0 * 1000.0, 6),
        round(((float) $bbox[3]) / 792.0 * 1000.0, 6),
    ];
};

$extractPageResult = [
    'pnum' => 0,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'rows_coordinate_space' => 'normalized_page_image',
    'cols_coordinate_space' => 'normalized_page_image',
    'cells_coordinate_space' => 'normalized_page_image',
    'cells' => [[
        ['bbox' => $normalizePageBbox([82.0, 155.0, 162.0, 170.0]), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['bbox' => $normalizePageBbox([202.0, 155.0, 302.0, 170.0]), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bbox' => $normalizePageBbox([82.0, 195.0, 162.0, 215.0]), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox' => $normalizePageBbox([202.0, 195.0, 302.0, 215.0]), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox' => $normalizePageBbox([82.0, 250.0, 162.0, 268.0]), 'text' => 'Stale normalized row', 'row_ids' => [99], 'col_ids' => [0]],
        ['bbox' => $normalizePageBbox([360.0, 195.0, 382.0, 215.0]), 'text' => 'Stale normalized col', 'row_ids' => [1], 'col_ids' => [99]],
    ]],
    'rows_cols' => [[
        'rows' => [
            ['row_id' => 0, 'bbox' => $normalizePageBbox([72.0, 150.0, 312.0, 182.0])],
            ['row_id' => 1, 'bbox' => $normalizePageBbox([72.0, 190.0, 312.0, 220.0])],
            ['row_id' => 99, 'bbox' => $normalizePageBbox([72.0, 250.0, 312.0, 270.0])],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => $normalizePageBbox([72.0, 150.0, 172.0, 230.0])],
            ['col_id' => 1, 'bbox' => $normalizePageBbox([192.0, 150.0, 312.0, 230.0])],
            ['col_id' => 99, 'bbox' => $normalizePageBbox([342.0, 150.0, 362.0, 230.0])],
        ],
    ]],
    'bboxes' => [
        ['bbox' => [72.0, 150.0, 312.0, 230.0]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-page-result-shared-image-bbox-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% shared image bbox table page result WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Shared page image boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale normalized table text should be replaced.', 'bbox' => [82.0, 176.0, 280.0, 196.0]],
                ['text' => 'After shared image bbox page result.', 'bbox' => [72.0, 260.0, 480.0, 278.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 260.0, 480.0, 278.0]],
                ],
            ]],
            'recognized_tables' => [$extractPageResult],
            'table_text_lines' => [['blocks' => []]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($pdfPath);
}

$metadata = $result['metadata'];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
$pageResultReview = $metadata['table_page_result_boundary_reviews'][0] ?? [];
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];

echo json_encode([
    'scenario' => 'wordpress-table-page-result-shared-image-bbox-currentbase',
    'native_boundary' => 'tabled page-result envelopes with a shared page image bbox preserve normalized page-image table geometry before WordPress table insertion',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Shared Page Image Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After shared image bbox page result.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'page_result_review' => $pageResultReview,
    'coordinate_status' => $coordinateReview['status'] ?? null,
    'shared_image_bbox_source' => $pageResultReview['shared_image_bbox_source'] ?? null,
    'page_image_normalization_size' => $coordinateReview['page_image_normalization_size'] ?? null,
    'normalized_cell_count' => $coordinateReview['normalized_cell_count'] ?? null,
    'active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'stale_pdftext_table_line_removed' => !str_contains($result['text'], 'Stale normalized table text should be replaced.'),
    'stale_normalized_cells_filtered' => !in_array('Stale normalized row', $assignedTexts, true)
        && !in_array('Stale normalized col', $assignedTexts, true)
        && !str_contains($result['text'], 'Stale normalized row')
        && !str_contains($result['text'], 'Stale normalized col'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
