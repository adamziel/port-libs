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

$table = [
    'rows' => [
        ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 28.0]],
        ['row_id' => 1, 'bbox' => [0.0, 36.0, 240.0, 64.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => [0.0, 0.0, 110.0, 80.0]],
        ['col_id' => 1, 'bbox' => [130.0, 0.0, 240.0, 80.0]],
    ],
    'cells' => [
        ['bbox' => [8.0, 5.0, 232.0, 22.0], 'text' => 'Feature Status', 'row_ids' => [0, 0], 'col_ids' => [0, 1, 1]],
        ['bbox' => [10.0, 42.0, 96.0, 58.0], 'text' => 'Images', 'row_ids' => [1, 1], 'col_ids' => [0, 0]],
        ['bbox' => [136.0, 42.0, 224.0, 58.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1, 1]],
    ],
];

$path = sys_get_temp_dir() . '/markerpdf-wordpress-table-duplicate-span-id-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% duplicate span id table boundary WordPress fixture\n%%EOF");

try {
    $converted = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'Duplicate span id import', 'bbox' => [72.0, 48.0, 430.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale duplicate-id table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                ['text' => 'After duplicate span review.', 'bbox' => [72.0, 260.0, 430.0, 278.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 430.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 260.0, 430.0, 278.0]],
                ],
            ]],
            'recognized_tables' => [$table],
            'table_text_lines' => [['blocks' => []]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    if (is_file($path)) {
        unlink($path);
    }
}

$assignedCells = $converted['metadata']['table_assigned_cells'][0] ?? [];
$gridReview = $converted['metadata']['table_spanning_grid_review'][0] ?? [];
$bandReview = $converted['metadata']['table_assigned_band_boundary_reviews'][0] ?? [];
$markdown = $converted['text'];

$dedupedHeaderSpan = ($assignedCells[0]['row_ids'] ?? null) === [0]
    && ($assignedCells[0]['col_ids'] ?? null) === [0, 1]
    && (($gridReview['render_cells'][0]['colspan'] ?? null) === 2)
    && count($gridReview['render_cells'][0]['grid_cells'] ?? []) === 2;
$dedupedDataCells = ($assignedCells[1]['row_ids'] ?? null) === [1]
    && ($assignedCells[1]['col_ids'] ?? null) === [0]
    && ($assignedCells[2]['row_ids'] ?? null) === [1]
    && ($assignedCells[2]['col_ids'] ?? null) === [1];
$staleTextExcluded = !str_contains($markdown, 'Stale duplicate-id table line should be replaced.');

if (!$dedupedHeaderSpan || !$dedupedDataCells || !$staleTextExcluded) {
    throw new RuntimeException('Duplicate span-id boundary did not produce a clean WordPress table import.');
}

echo json_encode([
    'scenario' => 'wordpress-table-duplicate-span-id-boundary-currentbase',
    'native_boundary' => 'saved tabled SpanTableCell row_ids/col_ids are deduplicated before WordPress table span/grid review',
    'source_truth' => 'tabled.assignment SpanTableCell row/column occupancy is set-like grid geometry; Markdown consumes first anchors while WordPress review keeps explicit rowspan/colspan cells',
    'supplied_boundaries' => $converted['metadata']['supplied_boundaries'] ?? [],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Duplicate Span Id Import</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td colspan="2">Feature Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After duplicate span review.</p>'],
    ],
    'deduped_header_span' => $dedupedHeaderSpan,
    'deduped_data_cells' => $dedupedDataCells,
    'header_row_ids' => $assignedCells[0]['row_ids'] ?? null,
    'header_col_ids' => $assignedCells[0]['col_ids'] ?? null,
    'header_colspan' => $gridReview['render_cells'][0]['colspan'] ?? null,
    'header_grid_cell_count' => count($gridReview['render_cells'][0]['grid_cells'] ?? []),
    'band_trimmed_cell_count' => $bandReview['trimmed_cell_count'] ?? null,
    'band_excluded_cell_count' => $bandReview['excluded_cell_count'] ?? null,
    'excluded_stale_pdftext_table_lines' => $staleTextExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $markdown,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
