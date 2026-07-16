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
    'bboxes_coordinate_space' => 'page_image',
    'cells_coordinate_space' => 'page_image',
    'cells' => [[
        ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [20]],
        ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [21]],
        ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [11], 'col_ids' => [20]],
        ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [11], 'col_ids' => [21]],
        ['bbox' => [90.0, 160.0, 150.0, 176.0], 'text' => 'Stale singular row', 'row_ids' => [99], 'col_ids' => [20]],
        ['bbox' => [104.0, 198.0, 154.0, 214.0], 'text' => 'Stale singular column', 'row_ids' => [11], 'col_ids' => [99]],
    ]],
    'rows_cols' => [[
        'row_bbox_coordinate_space' => 'page_image',
        'row_bbox_order' => 'x1_x2_y1_y2',
        'column_bbox_coordinate_space' => 'page_image',
        'column_bbox_order' => 'x1_x2_y1_y2',
        'row_bbox' => [
            ['row_id' => 10, 'bbox' => $x1x2y1y2(72.0, 150.0, 312.0, 182.0)],
            ['row_id' => 11, 'bbox' => $x1x2y1y2(72.0, 190.0, 312.0, 220.0)],
            ['row_id' => 99, 'bbox' => $x1x2y1y2(72.0, 250.0, 312.0, 270.0)],
        ],
        'column_bbox' => [
            ['col_id' => 20, 'bbox' => $x1x2y1y2(72.0, 150.0, 172.0, 230.0)],
            ['col_id' => 21, 'bbox' => $x1x2y1y2(192.0, 150.0, 312.0, 230.0)],
            ['col_id' => 99, 'bbox' => $x1x2y1y2(342.0, 150.0, 362.0, 230.0)],
        ],
    ]],
    'bboxes' => [
        ['bbox' => [72.0, 150.0, 312.0, 230.0]],
    ],
    'image_bboxes' => [
        ['bbox' => [0.0, 0.0, 612.0, 792.0]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-singular-rows-cols-alias-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% singular rows-cols alias table WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Singular rows cols alias boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale singular rows-cols table line should be replaced.', 'bbox' => [82.0, 176.0, 380.0, 196.0]],
                ['text' => 'After singular rows cols table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
$pageResultReview = $metadata['table_page_result_boundary_reviews'][0] ?? [];
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$bandReview = $metadata['table_assigned_band_boundary_reviews'][0] ?? [];
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];

$markdownTablePreserved = str_contains($result['text'], '| Feature | Status |')
    && str_contains($result['text'], '| Images  | Ready  |');
$staleBoundaryExcluded = !str_contains($result['text'], 'Stale singular rows-cols table line should be replaced.')
    && !str_contains($result['text'], 'Stale singular row')
    && !str_contains($result['text'], 'Stale singular column');
$singularAliasesFlattened = ($pageResultReview['rows_cols_row_aliases'] ?? null) === ['row_bbox']
    && ($pageResultReview['rows_cols_col_aliases'] ?? null) === ['column_bbox'];
$activeBandsPreserved = ($bandReview['active_row_ids'] ?? null) === [10, 11]
    && ($bandReview['active_col_ids'] ?? null) === [20, 21]
    && ($gridReview['geometry_boundary_review']['active_row_ids'] ?? null) === [10, 11]
    && ($gridReview['geometry_boundary_review']['active_col_ids'] ?? null) === [20, 21];

if (!$markdownTablePreserved || !$staleBoundaryExcluded || !$singularAliasesFlattened || !$activeBandsPreserved) {
    throw new RuntimeException('Expected singular row_bbox/column_bbox rows_cols aliases to survive WordPress table conversion.');
}

echo json_encode([
    'scenario' => 'wordpress-table-singular-rows-cols-alias-boundary-currentbase',
    'native_boundary' => 'ExtractPageResult rows_cols sidecars with singular row_bbox and column_bbox containers are flattened before crop-local assignment and WordPress table insertion',
    'source_truth' => [
        'upstream' => 'tabled ExtractPageResult carries per-table cells and rows_cols geometry; supplied sidecars may serialize the row/column bands using singular bbox container names alongside singular metadata keys',
        'no_gpu_scope' => 'uses supplied table recognition cells and geometry; does not run Surya, tabled models, OCR, Python, PDFium, PIL, or external PDF tools',
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'page_result_aliases' => [
        'rows' => $pageResultReview['rows_cols_row_aliases'] ?? [],
        'cols' => $pageResultReview['rows_cols_col_aliases'] ?? [],
    ],
    'coordinate_review_status' => $coordinateReview['status'] ?? null,
    'source_coordinate_spaces' => $coordinateReview['source_coordinate_spaces'] ?? [],
    'assigned_table_texts' => $assignedTexts,
    'active_row_ids' => $bandReview['active_row_ids'] ?? [],
    'active_col_ids' => $bandReview['active_col_ids'] ?? [],
    'singular_aliases_flattened' => $singularAliasesFlattened,
    'markdown_table_preserved' => $markdownTablePreserved,
    'stale_pdftext_and_offband_cells_excluded' => $staleBoundaryExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
