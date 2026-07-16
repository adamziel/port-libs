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
            ['bbox' => [0.0, 0.0, 122.0, 48.0], 'text' => 'Alpha', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [126.0, 0.0, 248.0, 48.0], 'text' => 'Beta', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [0.0, 52.0, 122.0, 100.0], 'text' => 'Gamma', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [126.0, 52.0, 248.0, 100.0], 'text' => 'Delta', 'row_ids' => [1], 'col_ids' => [1]],
        ],
        [
            ['bbox' => [0.0, 0.0, 122.0, 48.0], 'text' => 'One', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [126.0, 0.0, 248.0, 48.0], 'text' => 'Two', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [0.0, 52.0, 122.0, 100.0], 'text' => 'Three', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [126.0, 52.0, 248.0, 100.0], 'text' => 'Four', 'row_ids' => [1], 'col_ids' => [1]],
        ],
    ],
    'rows_cols' => [
        [
            'bbox' => [72.0, 560.0, 320.0, 660.0],
            'image_bbox' => [0.0, 0.0, 248.0, 100.0],
            'rows' => [
                ['bbox' => [0.0, 0.0, 248.0, 48.0]],
                ['bbox' => [0.0, 52.0, 248.0, 100.0]],
            ],
            'cols' => [
                ['bbox' => [0.0, 0.0, 122.0, 100.0]],
                ['bbox' => [126.0, 0.0, 248.0, 100.0]],
            ],
        ],
        [
            'bbox' => [72.0, 420.0, 320.0, 520.0],
            'image_bbox' => [0.0, 0.0, 248.0, 100.0],
            'rows' => [
                ['bbox' => [0.0, 0.0, 248.0, 48.0]],
                ['bbox' => [0.0, 52.0, 248.0, 100.0]],
            ],
            'cols' => [
                ['bbox' => [0.0, 0.0, 122.0, 100.0]],
                ['bbox' => [126.0, 0.0, 248.0, 100.0]],
            ],
        ],
    ],
    'bboxes' => [
        ['bbox' => [72.0, 560.0, 320.0, 660.0]],
        ['bbox' => [72.0, 420.0, 320.0, 520.0]],
        ['bbox' => [72.0, 260.0, 320.0, 360.0]],
    ],
    'image_bboxes' => [
        ['bbox' => [0.0, 0.0, 248.0, 100.0]],
        ['bbox' => [0.0, 0.0, 248.0, 100.0]],
        ['bbox' => [0.0, 0.0, 248.0, 100.0]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-page-result-surplus-bbox-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table page result surplus bbox WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Before surplus bbox table results.', 'bbox' => [72.0, 700.0, 230.0, 720.0]],
                ['text' => 'First stale table line should be replaced.', 'bbox' => [82.0, 586.0, 280.0, 606.0]],
                ['text' => 'Second stale table line should be replaced.', 'bbox' => [82.0, 446.0, 280.0, 466.0]],
                ['text' => 'After surplus bbox table results.', 'bbox' => [72.0, 380.0, 260.0, 400.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Text', 'bbox' => [72.0, 700.0, 230.0, 720.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 560.0, 320.0, 660.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 420.0, 320.0, 520.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 380.0, 260.0, 400.0]],
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

$pageResultReview = $result['metadata']['table_page_result_boundary_reviews'][0] ?? [];

echo json_encode([
    'scenario' => 'wordpress-table-page-result-surplus-bbox-boundary-currentbase',
    'native_boundary' => 'upstream tabled ExtractPageResult cells determine table count; surplus bbox sidecars are reviewed, not converted into ghost tables',
    'inserted_tables' => $result['metadata']['inserted_tables'] ?? null,
    'table_count' => $pageResultReview['table_count'] ?? null,
    'table_bbox_count' => $pageResultReview['table_bbox_count'] ?? null,
    'surplus_table_bbox_count' => $pageResultReview['surplus_table_bbox_count'] ?? null,
    'surplus_image_bbox_count' => $pageResultReview['surplus_image_bbox_count'] ?? null,
    'ghost_table_records_suppressed' => $pageResultReview['ghost_table_records_suppressed'] ?? null,
    'authoritative_table_count_source' => $pageResultReview['authoritative_table_count_source'] ?? null,
    'stale_pdftext_table_lines_removed' => !str_contains($result['text'], 'First stale table line should be replaced.')
        && !str_contains($result['text'], 'Second stale table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
