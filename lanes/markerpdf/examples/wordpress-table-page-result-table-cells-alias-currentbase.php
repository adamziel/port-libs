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
    'bboxes_coordinate_space' => 'page_image',
    'table_cells_coordinate_space' => 'page_image',
    'table_cells' => [[
        ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [20]],
        ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [21]],
        ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [11], 'col_ids' => [20]],
        ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [11], 'col_ids' => [21]],
        ['bbox' => [92.0, 160.0, 152.0, 176.0], 'text' => 'Inactive alias row', 'row_ids' => [99], 'col_ids' => [20]],
        ['bbox' => [104.0, 198.0, 154.0, 214.0], 'text' => 'Inactive alias column', 'row_ids' => [11], 'col_ids' => [99]],
    ]],
    'rows_cols' => [[
        'rows_coordinate_space' => 'page_image',
        'cols_coordinate_space' => 'page_image',
        'rows' => [
            ['row_id' => 10, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
            ['row_id' => 11, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
            ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 270.0]],
        ],
        'cols' => [
            ['col_id' => 20, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
            ['col_id' => 21, 'bbox' => [192.0, 150.0, 312.0, 230.0]],
            ['col_id' => 99, 'bbox' => [342.0, 150.0, 362.0, 230.0]],
        ],
    ]],
    'bboxes' => [
        ['bbox' => [72.0, 150.0, 312.0, 230.0]],
    ],
    'image_bboxes' => [
        ['bbox' => [0.0, 0.0, 612.0, 792.0]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-cells-alias-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table page result table_cells alias WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Page result table cells alias boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale table_cells alias line should be replaced.', 'bbox' => [82.0, 176.0, 370.0, 196.0]],
                ['text' => 'After table cells alias table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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

$assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');
$pageResultReview = $result['metadata']['table_page_result_boundary_reviews'][0] ?? [];
$bandReview = $result['metadata']['table_assigned_band_boundary_reviews'][0] ?? [];

echo json_encode([
    'scenario' => 'wordpress-table-page-result-table-cells-alias-currentbase',
    'native_boundary' => 'upstream-shaped ExtractPageResult envelopes with native table_cells sidecar aliases flatten into crop-local table recognition records before WordPress block conversion',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Page Result Table Cells Alias Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After table cells alias table.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'page_result_review' => $pageResultReview,
    'assigned_table_texts' => $assignedTexts,
    'inserted_tables' => $result['metadata']['inserted_tables'] ?? null,
    'table_cells_alias_flattened' => ($pageResultReview['cells_source'] ?? null) === 'table_cells',
    'stale_pdftext_table_line_excluded' => !str_contains($result['text'], 'Stale table_cells alias line should be replaced.'),
    'inactive_alias_cells_filtered' => !in_array('Inactive alias row', $assignedTexts, true)
        && !in_array('Inactive alias column', $assignedTexts, true)
        && ($bandReview['excluded_cell_count'] ?? null) === 2,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
