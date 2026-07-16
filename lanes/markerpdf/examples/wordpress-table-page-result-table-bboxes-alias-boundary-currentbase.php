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

$extractPageResult = [
    'pnum' => 0,
    'coordinate_space' => 'page_image',
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'table_bboxes' => [[
        'bbox' => [72.0, 150.0, 312.0, 230.0],
        'coordinate_space' => 'page_image',
    ]],
    'cells' => [[
        ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale table-bboxes row', 'row_ids' => [99], 'col_ids' => [0]],
        ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale table-bboxes col', 'row_ids' => [1], 'col_ids' => [99]],
    ]],
    'rows_cols' => [[
        'rows' => [
            ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
            ['row_id' => 1, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
            ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 270.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
            ['col_id' => 1, 'bbox' => [192.0, 150.0, 312.0, 230.0]],
            ['col_id' => 99, 'bbox' => [342.0, 150.0, 362.0, 230.0]],
        ],
    ]],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-page-result-table-bboxes-alias-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% page result table_bboxes alias WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Page result table bboxes alias boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale table_bboxes alias line should be replaced.', 'bbox' => [300.0, 420.0, 530.0, 438.0]],
                ['text' => 'After page result table_bboxes alias.', 'bbox' => [72.0, 520.0, 560.0, 538.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 560.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [300.0, 400.0, 540.0, 480.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 520.0, 560.0, 538.0]],
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
$assigned = $metadata['table_assigned_cells'][0] ?? [];
$assignedTexts = array_column($assigned, 'text');

$tableBboxesPropagated = ($pageResultReview['table_bbox_source'] ?? null) === 'table_bboxes'
    && ($coordinateReview['table_bbox_source'] ?? null) === 'ExtractPageResult.table_bboxes'
    && ($coordinateReview['status'] ?? null) === 'translated_to_table_crop'
    && ($coordinateReview['translated_cell_count'] ?? null) === 6;
$offcropCellsFiltered = !in_array('Stale table-bboxes row', $assignedTexts, true)
    && !in_array('Stale table-bboxes col', $assignedTexts, true)
    && !str_contains($result['text'], 'Stale table-bboxes row')
    && !str_contains($result['text'], 'Stale table-bboxes col');
$staleLineRemoved = !str_contains($result['text'], 'Stale table_bboxes alias line should be replaced.');

if (!$tableBboxesPropagated || !$offcropCellsFiltered || !$staleLineRemoved) {
    throw new RuntimeException('Expected ExtractPageResult table_bboxes to drive table-crop localization before WordPress insertion.');
}

echo json_encode([
    'scenario' => 'wordpress-table-page-result-table-bboxes-alias-boundary-currentbase',
    'native_boundary' => 'ExtractPageResult table_bboxes aliases survive flattening as authoritative recognition crop geometry before WordPress table insertion',
    'source_truth' => [
        'upstream' => 'marker/tables/table.py::get_table_boxes returns table_bboxes from rendered page-image crops before get_cells/recognize_tables/assign_rows_columns',
        'tabled_boundary' => 'recognized rows, columns, and cells must be localized against the crop used for tabled assignment, not a stale layout replacement box',
        'no_gpu_scope' => 'uses supplied recognition rows/cells and does not run Surya, tabled models, OCR, Python, PDFium, PIL, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Page Result Table Bboxes Alias Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After page result table_bboxes alias.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'page_result_review' => [
        'table_count' => $pageResultReview['table_count'] ?? null,
        'table_bbox_count' => $pageResultReview['table_bbox_count'] ?? null,
        'table_bbox_source' => $pageResultReview['table_bbox_source'] ?? null,
    ],
    'coordinate_review_status' => $coordinateReview['status'] ?? null,
    'coordinate_table_bbox_source' => $coordinateReview['table_bbox_source'] ?? null,
    'coordinate_table_bbox' => $coordinateReview['table_bbox'] ?? null,
    'translated_cell_count' => $coordinateReview['translated_cell_count'] ?? null,
    'assigned_crop_active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'assigned_crop_excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'table_bboxes_alias_propagated' => $tableBboxesPropagated,
    'offcrop_cells_filtered_from_assignment' => $offcropCellsFiltered,
    'excluded_stale_pdftext_table_line' => $staleLineRemoved,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
