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
        ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 30.0]],
        ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 80.0]],
    ],
    'cells' => [
        ['bbox' => [8.0, 5.0, 90.0, 24.0], 'text' => 'Header B', 'row_ids' => [0], 'col_ids' => [0], 'order' => 1],
        ['bbox' => [132.0, 5.0, 230.0, 24.0], 'text' => 'Header A', 'row_ids' => [0], 'col_ids' => [0], 'order' => 0],
        ['bbox' => [8.0, 45.0, 90.0, 64.0], 'text' => 'Second', 'row_ids' => [1], 'col_ids' => [0], 'order' => 1],
        ['bbox' => [132.0, 45.0, 230.0, 64.0], 'text' => 'First', 'row_ids' => [1], 'col_ids' => [0], 'order' => 0],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-assigned-order-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table assigned order boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Assigned order table review', 'bbox' => [72.0, 48.0, 430.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale geometry-order table text should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                ['text' => 'After assigned order review.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 430.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [$table],
            'table_text_lines' => [['blocks' => []]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($pdfPath);
}

$gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
$assigned = $result['metadata']['table_assigned_cells'][0] ?? [];
$renderCells = $gridReview['render_cells'] ?? [];

if (!str_contains($result['text'], '| Header A Header B |') || !str_contains($result['text'], '| First Second      |')) {
    throw new RuntimeException('Expected WordPress Markdown table to use supplied same-anchor cell order.');
}
if (str_contains($result['text'], 'Stale geometry-order table text should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}
if (array_column($assigned, 'text') !== ['Header A', 'Header B', 'First', 'Second']) {
    throw new RuntimeException('Expected assigned cell metadata to preserve supplied tabled display order.');
}
if (($renderCells[0]['source_orders'] ?? null) !== [0, 1] || ($renderCells[0]['continuation_cells'][0]['order'] ?? null) !== 1) {
    throw new RuntimeException('Expected spanning-grid review metadata to expose supplied source orders.');
}

echo json_encode([
    'scenario' => 'wordpress-table-assigned-order-boundary-currentbase',
    'native_boundary' => 'tabled SpanTableCell.order sorts same-anchor continuation cells before WordPress Markdown and review metadata',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Assigned Order Table Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Header A Header B</td></tr><tr><td>First Second</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After assigned order review.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'assigned_text_order' => array_column($assigned, 'text'),
    'assigned_order_values' => array_column($assigned, 'order'),
    'render_source_orders' => array_map(
        static fn (array $cell): array => isset($cell['source_orders']) && is_array($cell['source_orders']) ? $cell['source_orders'] : [],
        $renderCells
    ),
    'render_anchor_cell_orders' => array_column($renderCells, 'anchor_cell_order'),
    'continuation_order_values' => array_map(
        static fn (array $cell): array => array_column($cell['continuation_cells'] ?? [], 'order'),
        $renderCells
    ),
    'render_text_parts' => array_map(
        static fn (array $cell): array => isset($cell['text_parts']) && is_array($cell['text_parts']) ? $cell['text_parts'] : [],
        $renderCells
    ),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale geometry-order table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
