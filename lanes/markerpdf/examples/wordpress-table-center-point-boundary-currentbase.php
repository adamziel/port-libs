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

$recognizedTable = [
    'coordinate_space' => 'page_image',
    'image_bbox' => ['center' => [306.0, 396.0], 'size' => [612.0, 792.0]],
    'rows' => [
        ['row_id' => 10, 'bbox' => ['center' => [192.0, 166.0], 'extent' => [240.0, 32.0]]],
        ['row_id' => 20, 'center' => ['x' => 192.0, 'y' => 205.0], 'width' => 240.0, 'height' => 30.0],
        ['row_id' => 99, 'bbox' => ['center' => [192.0, 260.0], 'size' => [240.0, 20.0]]],
    ],
    'cols' => [
        ['col_id' => 30, 'bbox' => ['center' => [122.0, 190.0], 'extent' => ['w' => 100.0, 'h' => 80.0]]],
        ['col_id' => 40, 'center' => [252.0, 190.0], 'size' => ['width' => 120.0, 'height' => 80.0]],
        ['col_id' => 99, 'bbox' => ['center' => [350.0, 190.0], 'extent' => [20.0, 80.0]]],
    ],
    'cells' => [
        ['bbox' => ['center' => [122.0, 162.5], 'extent' => [80.0, 15.0]], 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [30]],
        ['center' => ['x' => 252.0, 'y' => 162.5], 'width' => 100.0, 'height' => 15.0, 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [40]],
        ['bbox' => ['center' => [122.0, 205.0], 'size' => [80.0, 20.0]], 'text' => 'Images', 'row_ids' => [20], 'col_ids' => [30]],
        ['center' => [252.0, 205.0], 'size' => ['width' => 100.0, 'height' => 20.0], 'text' => 'Ready', 'row_ids' => [20], 'col_ids' => [40]],
        ['bbox' => ['center' => [122.0, 259.0], 'extent' => [80.0, 18.0]], 'text' => 'Stale center point row', 'row_ids' => [99], 'col_ids' => [30]],
        ['bbox' => ['center' => [370.0, 205.0], 'extent' => [22.0, 20.0]], 'text' => 'Stale center point column', 'row_ids' => [20], 'col_ids' => [99]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-center-point-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% center point table geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Center point table geometry', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale center-point table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                ['text' => 'After center point table.', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 520.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [$recognizedTable],
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

$gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
$boundary = $gridReview['geometry_boundary_review'] ?? [];
$assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');

if (!in_array('Feature', $assignedTexts, true) || in_array('Stale center point row', $assignedTexts, true)) {
    throw new RuntimeException('Expected center-point geometry to localize active cells and exclude stale rows before WordPress output.');
}

echo json_encode([
    'scenario' => 'wordpress-table-center-point-boundary-currentbase',
    'native_boundary' => 'supplied tabled Bbox center/extent helper geometry is converted to endpoint bboxes before table-crop localization and WordPress table review',
    'source_truth' => [
        'upstream_tabled' => 'tabled-pdf SpanTableCell inherits Surya Bbox and assignment uses center, width, and height helpers before Markdown formatting',
        'scope' => 'native supplied-boundary conversion only; no Python, Surya, tabled model, OCR, or external PDF tool execution',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Center Point Table Geometry</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After center point table.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'coordinate_status' => $result['metadata']['table_coordinate_space_reviews'][0]['status'] ?? null,
    'row_source_shapes' => array_column($boundary['row_bands'] ?? [], 'source_coordinate_source'),
    'render_cell_source_shapes' => array_column($gridReview['render_cells'] ?? [], 'source_coordinate_source'),
    'active_cell_count' => $result['metadata']['table_assigned_crop_boundary_reviews'][0]['active_cell_count'] ?? null,
    'excluded_cell_count' => $result['metadata']['table_assigned_crop_boundary_reviews'][0]['excluded_cell_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale center-point table line should be replaced.'),
    'excluded_stale_supplied_cells' => !str_contains($result['text'], 'Stale center point row')
        && !str_contains($result['text'], 'Stale center point column'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
