<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$normPage = static fn (array $bbox): array => [
    round(((float) $bbox[0] / 612.0) * 1000.0, 6),
    round(((float) $bbox[1] / 792.0) * 1000.0, 6),
    round(((float) $bbox[2] / 612.0) * 1000.0, 6),
    round(((float) $bbox[3] / 792.0) * 1000.0, 6),
];

$roundBbox = static fn (array $bbox): array => array_map(
    static fn (mixed $value): float => round((float) $value, 1),
    $bbox
);

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
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes_coordinate_space' => 'normalized_page_image',
    'rows_coordinate_space' => 'normalized_page_image',
    'cols_coordinate_space' => 'normalized_page_image',
    'cells_coordinate_space' => 'normalized_page_image',
    'cells' => [[
        ['bbox' => $normPage([82.0, 155.0, 162.0, 170.0]), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['bbox' => $normPage([202.0, 155.0, 302.0, 170.0]), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bbox' => $normPage([82.0, 195.0, 162.0, 215.0]), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox' => $normPage([202.0, 195.0, 302.0, 215.0]), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox' => $normPage([82.0, 250.0, 162.0, 268.0]), 'text' => 'Stale page-result row', 'row_ids' => [99], 'col_ids' => [0]],
        ['bbox' => $normPage([360.0, 195.0, 382.0, 215.0]), 'text' => 'Stale page-result col', 'row_ids' => [1], 'col_ids' => [99]],
    ]],
    'rows_cols' => [[
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
    ]],
    'bboxes' => [
        ['bbox' => $normPage([72.0, 150.0, 312.0, 230.0])],
    ],
];

$recognizedTable = [
    'bbox' => $extractPageResult['bboxes'][0]['bbox'],
    'image_bbox' => $extractPageResult['image_bbox'],
    'bboxes_coordinate_space' => $extractPageResult['bboxes_coordinate_space'],
    'rows_coordinate_space' => $extractPageResult['rows_coordinate_space'],
    'cols_coordinate_space' => $extractPageResult['cols_coordinate_space'],
    'cells_coordinate_space' => $extractPageResult['cells_coordinate_space'],
    'rows' => $extractPageResult['rows_cols'][0]['rows'],
    'cols' => $extractPageResult['rows_cols'][0]['cols'],
    'cells' => $extractPageResult['cells'][0],
];

$recognizer = new TableRecognizer();
$direct = $recognizer->formatRecognizedTables([$recognizedTable], [['width' => 240, 'height' => 80]]);
$directReview = $direct['coordinate_space_reviews'][0] ?? [];
$directAssignedTexts = array_column($direct['assigned_cells'][0] ?? [], 'text');

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-page-result-bbox-coordinate-space-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% page result bbox coordinate-space WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Page Result Bbox Coordinate Space Boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale page-result bbox-coordinate table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                ['text' => 'After page result bbox coordinate space table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
    if (is_file($pdfPath)) {
        unlink($pdfPath);
    }
}

$metadata = $result['metadata'];
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$pageResultReview = $metadata['table_page_result_boundary_reviews'][0] ?? [];
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

$pageResultBboxLocalized = ($directReview['table_bbox_source_coordinate_space'] ?? null) === 'normalized_page_image'
    && $roundBbox($directReview['table_bbox'] ?? []) === [72.0, 150.0, 312.0, 230.0]
    && ($directReview['table_bbox_page_image_normalization_size'] ?? null) === ['width' => 612, 'height' => 792];
$wordpressTableRendered = str_contains($result['text'], '| Feature | Status |')
    && str_contains($result['text'], '| Images  | Ready  |');
$staleExcluded = !in_array('Stale page-result row', $assignedTexts, true)
    && !in_array('Stale page-result col', $assignedTexts, true)
    && !str_contains($result['text'], 'Stale page-result bbox-coordinate table line should be replaced.');

if (!$pageResultBboxLocalized || !$wordpressTableRendered || !$staleExcluded) {
    throw new RuntimeException('Expected page-result bbox coordinate-space metadata to localize before WordPress table output.');
}

echo json_encode([
    'scenario' => 'wordpress-table-page-result-bbox-coordinate-space-boundary-currentbase',
    'native_boundary' => 'ExtractPageResult bboxes_coordinate_space metadata is preserved for flattened table bbox localization before table-crop assignment and WordPress table replacement',
    'source_truth' => [
        'marker_pdf' => 'marker/tables/table.py crops high-resolution table bboxes before tabled assignment and Markdown formatting',
        'tabled' => 'tabled.schema.ExtractPageResult carries page-level bboxes, image_bbox, cells, rows, and cols for saved table recognition results',
        'no_gpu_scope' => 'uses supplied table recognition geometry and does not run Surya, tabled model inference, OCR, Python, PDFium, PIL, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Page Result Bbox Coordinate Space Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After page result bbox coordinate space table.</p>'],
    ],
    'direct_source_table_bbox' => $directReview['source_table_bbox'] ?? null,
    'direct_table_bbox' => $roundBbox($directReview['table_bbox'] ?? []),
    'direct_table_bbox_source_coordinate_space' => $directReview['table_bbox_source_coordinate_space'] ?? null,
    'direct_table_bbox_page_image_normalization_size' => $directReview['table_bbox_page_image_normalization_size'] ?? null,
    'direct_assigned_table_texts' => $directAssignedTexts,
    'page_result_review' => [
        'review_target' => $pageResultReview['review_target'] ?? null,
        'table_count' => $pageResultReview['table_count'] ?? null,
        'upstream_boundary' => $pageResultReview['upstream_boundary'] ?? null,
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_review_status' => $coordinateReview['status'] ?? null,
    'coordinate_table_bbox_source' => $coordinateReview['table_bbox_source'] ?? null,
    'coordinate_source_space_cells' => $coordinateReview['source_coordinate_spaces']['cells'] ?? null,
    'normalized_cell_count' => $coordinateReview['normalized_cell_count'] ?? null,
    'render_source_coordinate_space' => $gridReview['render_cells'][0]['source_coordinate_space'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'page_result_bboxes_coordinate_space_localized' => $pageResultBboxLocalized,
    'wordpress_table_rendered' => $wordpressTableRendered,
    'stale_page_result_cells_filtered' => $staleExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
