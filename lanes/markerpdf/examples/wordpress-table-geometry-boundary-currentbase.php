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

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-geometry-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Table geometry boundary review', 'bbox' => [72.0, 48.0, 440.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale clipped table text should be replaced.', 'bbox' => [72.0, 176.0, 430.0, 196.0]],
                ['text' => 'After clipped geometry review.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 440.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 372.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [-5.0, -4.0, 310.0, 28.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 40.0, 300.0, 68.0]],
                    ['row_id' => 2, 'bbox' => [0.0, 130.0, 300.0, 150.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [-10.0, 0.0, 100.0, 140.0]],
                    ['col_id' => 1, 'bbox' => [110.0, 0.0, 330.0, 140.0]],
                    ['col_id' => 2, 'bbox' => [340.0, 0.0, 360.0, 80.0]],
                ],
                'cells' => [
                    ['bbox' => [5.0, 4.0, 295.0, 20.0], 'text' => 'Header'],
                    ['bbox' => [5.0, 44.0, 90.0, 62.0], 'text' => 'Images'],
                    ['bbox' => [120.0, 44.0, 290.0, 62.0], 'text' => 'Ready'],
                    ['bbox' => [306.0, 44.0, 350.0, 62.0], 'text' => 'Stale right edge'],
                    ['bbox' => [5.0, 124.0, 90.0, 142.0], 'text' => 'Stale below crop'],
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
$boundary = $gridReview['geometry_boundary_review'] ?? [];
$assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');

echo json_encode([
    'scenario' => 'wordpress-table-geometry-boundary-currentbase',
    'native_boundary' => 'supplied table row and column bands plus fully off-crop cells are bounded to the cropped table image before WordPress grid review metadata',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Table Geometry Boundary Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Header</td><td></td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After clipped geometry review.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'table_crop_boundary' => $boundary['image_size'] ?? null,
    'clipped_band_count' => $boundary['clipped_band_count'] ?? null,
    'excluded_band_count' => $boundary['excluded_band_count'] ?? null,
    'header_grid_bbox' => $gridReview['render_cells'][0]['grid_bbox'] ?? null,
    'body_grid_bboxes' => [
        $gridReview['grid_cells'][2]['grid_bbox'] ?? null,
        $gridReview['grid_cells'][3]['grid_bbox'] ?? null,
    ],
    'excluded_outside_bands' => [
        $boundary['row_bands'][2]['status'] ?? null,
        $boundary['col_bands'][2]['status'] ?? null,
    ],
    'assigned_table_texts' => $assignedTexts,
    'offcrop_cells_filtered_from_assignment' => !in_array('Stale right edge', $assignedTexts, true)
        && !in_array('Stale below crop', $assignedTexts, true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale clipped table text should be replaced.'),
    'excluded_offcrop_supplied_cell_text' => !str_contains($result['text'], 'Stale right edge')
        && !str_contains($result['text'], 'Stale below crop'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
