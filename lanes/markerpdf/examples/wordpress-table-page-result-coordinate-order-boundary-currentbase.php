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

$x1x2y1y2 = static fn (float $x1, float $y1, float $x2, float $y2): array => [$x1, $x2, $y1, $y2];

$extractPageResult = [
    'pnum' => 0,
    'coordinate_space' => 'page_image',
    'bbox_order' => 'x1_x2_y1_y2',
    'rows_bbox_order' => 'x1_x2_y1_y2',
    'cols_bbox_order' => 'x1_x2_y1_y2',
    'cells_bbox_order' => 'x1_x2_y1_y2',
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'cells' => [[
        ['bbox' => $x1x2y1y2(82.0, 155.0, 162.0, 170.0), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['bbox' => $x1x2y1y2(202.0, 155.0, 302.0, 170.0), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bbox' => $x1x2y1y2(82.0, 195.0, 162.0, 215.0), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox' => $x1x2y1y2(202.0, 195.0, 302.0, 215.0), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox' => $x1x2y1y2(82.0, 250.0, 162.0, 268.0), 'text' => 'Stale page-result row', 'row_ids' => [99], 'col_ids' => [0]],
        ['bbox' => $x1x2y1y2(360.0, 195.0, 382.0, 215.0), 'text' => 'Stale page-result col', 'row_ids' => [1], 'col_ids' => [99]],
    ]],
    'rows_cols' => [[
        'rows' => [
            ['row_id' => 0, 'bbox' => $x1x2y1y2(72.0, 150.0, 312.0, 182.0)],
            ['row_id' => 1, 'bbox' => $x1x2y1y2(72.0, 190.0, 312.0, 220.0)],
            ['row_id' => 99, 'bbox' => $x1x2y1y2(72.0, 250.0, 312.0, 268.0)],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => $x1x2y1y2(72.0, 150.0, 172.0, 230.0)],
            ['col_id' => 1, 'bbox' => $x1x2y1y2(192.0, 150.0, 312.0, 230.0)],
            ['col_id' => 99, 'bbox' => $x1x2y1y2(342.0, 150.0, 362.0, 230.0)],
        ],
    ]],
    'bboxes' => [
        ['bbox' => $x1x2y1y2(72.0, 150.0, 312.0, 230.0)],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-page-result-coordinate-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% page result coordinate order table WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Page result coordinate order boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale page-result coordinate-order table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                ['text' => 'After page result coordinate order table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
$pageResultReview = $metadata['table_page_result_boundary_reviews'][0] ?? [];
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$renderCells = $gridReview['render_cells'] ?? [];
$assigned = $metadata['table_assigned_cells'][0] ?? [];
$assignedTexts = array_column($assigned, 'text');

$bboxOrderPropagated = ($coordinateReview['status'] ?? null) === 'translated_to_table_crop'
    && ($coordinateReview['translated_cell_count'] ?? null) === 6
    && ($gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null) === 'bbox_array_x1_x2_y1_y2_order'
    && ($renderCells[0]['source_coordinate_source'] ?? null) === 'bbox_array_x1_x2_y1_y2_order';
$offcropCellsFiltered = !in_array('Stale page-result row', $assignedTexts, true)
    && !in_array('Stale page-result col', $assignedTexts, true)
    && !str_contains($result['text'], 'Stale page-result row')
    && !str_contains($result['text'], 'Stale page-result col');
$staleLineRemoved = !str_contains($result['text'], 'Stale page-result coordinate-order table line should be replaced.');

if (!$bboxOrderPropagated || !$offcropCellsFiltered || !$staleLineRemoved) {
    throw new RuntimeException('Expected page-result bbox-order metadata to survive flattening before WordPress table insertion.');
}

echo json_encode([
    'scenario' => 'wordpress-table-page-result-coordinate-order-boundary-currentbase',
    'native_boundary' => 'ExtractPageResult page envelopes propagate bbox-order metadata into crop-local table geometry before WordPress table insertion',
    'source_truth' => [
        'upstream' => 'marker/tables/table.py crops each table image before assigning recognized rows, columns, and cells',
        'tabled_boundary' => 'tabled Markdown formatting receives crop-local geometry; page-level result metadata must survive flattening before localization',
        'no_gpu_scope' => 'uses supplied recognition rows/cells and does not run Surya, tabled models, OCR, Python, PDFium, PIL, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Page Result Coordinate Order Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After page result coordinate order table.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'page_result_review' => [
        'table_count' => $pageResultReview['table_count'] ?? null,
        'shared_image_bbox_source' => $pageResultReview['shared_image_bbox_source'] ?? null,
    ],
    'coordinate_review_status' => $coordinateReview['status'] ?? null,
    'translated_cell_count' => $coordinateReview['translated_cell_count'] ?? null,
    'render_source_coordinate_source' => $renderCells[0]['source_coordinate_source'] ?? null,
    'render_source_bbox' => $renderCells[0]['source_cell_bbox'] ?? null,
    'assigned_crop_active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'assigned_crop_excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'page_result_bbox_order_metadata_propagated' => $bboxOrderPropagated,
    'offcrop_cells_filtered_from_assignment' => $offcropCellsFiltered,
    'excluded_stale_pdftext_table_line' => $staleLineRemoved,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
