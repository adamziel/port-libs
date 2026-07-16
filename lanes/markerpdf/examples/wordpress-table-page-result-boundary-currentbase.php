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
    'cells' => [
        [
            ['bbox' => [10.0, 5.0, 90.0, 20.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [130.0, 5.0, 230.0, 20.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [130.0, 45.0, 230.0, 65.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => [260.0, 45.0, 285.0, 65.0], 'text' => 'Offcrop page result', 'row_ids' => [1], 'col_ids' => [99]],
        ],
        [
            ['bbox' => [10.0, 5.0, 95.0, 20.0], 'text' => 'Attachment', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [120.0, 5.0, 210.0, 20.0], 'text' => 'State', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [10.0, 45.0, 95.0, 65.0], 'text' => 'PDF', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [120.0, 45.0, 210.0, 65.0], 'text' => 'Queued', 'row_ids' => [1], 'col_ids' => [1]],
        ],
    ],
    'rows_cols' => [
        [
            'rows' => [
                ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 32.0]],
                ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
            ],
            'cols' => [
                ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
                ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
                ['col_id' => 99, 'bbox' => [255.0, 0.0, 285.0, 80.0]],
            ],
        ],
        [
            'rows' => [
                ['row_id' => 0, 'bbox' => [0.0, 0.0, 220.0, 32.0]],
                ['row_id' => 1, 'bbox' => [0.0, 40.0, 220.0, 70.0]],
            ],
            'cols' => [
                ['col_id' => 0, 'bbox' => [0.0, 0.0, 105.0, 80.0]],
                ['col_id' => 1, 'bbox' => [115.0, 0.0, 220.0, 80.0]],
            ],
        ],
    ],
    'bboxes' => [
        ['bbox' => [72.0, 150.0, 312.0, 230.0]],
        ['bbox' => [72.0, 270.0, 292.0, 350.0]],
    ],
    'image_bboxes' => [
        ['bbox' => [0.0, 0.0, 612.0, 792.0]],
        ['bbox' => [0.0, 0.0, 612.0, 792.0]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-page-result-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table page result boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Page result table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'First stale table line should be replaced.', 'bbox' => [82.0, 176.0, 280.0, 196.0]],
                ['text' => 'Second stale table line should be replaced.', 'bbox' => [82.0, 296.0, 270.0, 316.0]],
                ['text' => 'After upstream grouped page result.', 'bbox' => [72.0, 390.0, 480.0, 408.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 270.0, 292.0, 350.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 390.0, 480.0, 408.0]],
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

$assignedTexts = array_map(
    static fn (array $cells): array => array_column($cells, 'text'),
    $result['metadata']['table_assigned_cells'] ?? []
);
$pageResultReview = $result['metadata']['table_page_result_boundary_reviews'][0] ?? [];

echo json_encode([
    'scenario' => 'wordpress-table-page-result-boundary-currentbase',
    'native_boundary' => 'upstream tabled ExtractPageResult page envelopes are flattened into per-table recognition records before crop-local table geometry assignment',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Page Result Table Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Attachment</td><td>State</td></tr><tr><td>PDF</td><td>Queued</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After upstream grouped page result.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'page_result_review' => $pageResultReview,
    'table_bboxes' => $result['metadata']['table_plan']['table_bboxes'] ?? [],
    'assigned_table_texts' => $assignedTexts,
    'inserted_tables' => $result['metadata']['inserted_tables'] ?? null,
    'stale_pdftext_table_lines_removed' => !str_contains($result['text'], 'First stale table line should be replaced.')
        && !str_contains($result['text'], 'Second stale table line should be replaced.'),
    'offcrop_cells_filtered_from_assignment' => !in_array('Offcrop page result', $assignedTexts[0] ?? [], true)
        && !str_contains($result['text'], 'Offcrop page result'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
