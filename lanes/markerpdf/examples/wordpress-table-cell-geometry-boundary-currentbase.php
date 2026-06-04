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

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-cell-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table cell geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Table cell boundary review', 'bbox' => [72.0, 48.0, 430.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale crop-edge table line should be replaced.', 'bbox' => [72.0, 176.0, 300.0, 196.0]],
                ['text' => 'After cell geometry review.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
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
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 32.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
                ],
                'cells' => [
                    ['bbox' => [-6.0, 4.0, 100.0, 20.0], 'text' => 'Header'],
                    ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => 'Images'],
                    ['bbox' => [130.0, 45.0, 250.0, 65.0], 'text' => 'Ready'],
                ],
            ]],
            'table_text_lines' => [['blocks' => []]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($pdfPath);
}

$gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
$boundary = $gridReview['cell_geometry_boundary_review'] ?? [];
if (($boundary['review_target'] ?? null) !== 'table_cell_geometry_boundary') {
    throw new RuntimeException('Missing table cell geometry boundary review.');
}
if (($boundary['clipped_cell_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected two clipped table cells in the WordPress boundary fixture.');
}
if (str_contains($result['text'], 'Stale crop-edge table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-cell-geometry-boundary-currentbase',
    'native_boundary' => 'supplied table cell bboxes are clipped to the cropped table image for WordPress review metadata',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Table Cell Boundary Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Header</td><td></td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After cell geometry review.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'table_crop_size' => $boundary['image_size'] ?? null,
    'cell_count' => $boundary['cell_count'] ?? null,
    'clipped_cell_count' => $boundary['clipped_cell_count'] ?? null,
    'excluded_cell_count' => $boundary['excluded_cell_count'] ?? null,
    'render_cell_bounded_bboxes' => [
        'Header' => $gridReview['render_cells'][0]['bounded_cell_bbox'] ?? null,
        'Images' => $gridReview['render_cells'][1]['bounded_cell_bbox'] ?? null,
        'Ready' => $gridReview['render_cells'][2]['bounded_cell_bbox'] ?? null,
    ],
    'cell_review_statuses' => array_map(
        static fn (array $cell): ?string => isset($cell['status']) ? (string) $cell['status'] : null,
        is_array($boundary['cells'] ?? null) ? $boundary['cells'] : []
    ),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale crop-edge table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
