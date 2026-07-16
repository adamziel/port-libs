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

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-named-bbox-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% named bbox table geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Named Bbox table boundary review', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale named-bbox table text should be replaced.', 'bbox' => [72.0, 176.0, 312.0, 196.0]],
                ['text' => 'After named-bbox geometry review.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => ['y2' => 30.0, 'x2' => 245.0, 'y1' => -4.0, 'x1' => -6.0]],
                    ['row_id' => 1, 'y_start' => 40.0, 'x_start' => 0.0, 'y_end' => 70.0, 'x_end' => 240.0],
                    ['row_id' => 2, 'left' => 0.0, 'top' => 95.0, 'right' => 240.0, 'bottom' => 115.0],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => ['bottom' => 90.0, 'right' => 100.0, 'top' => 0.0, 'left' => 0.0]],
                    ['col_id' => 1, 'bbox' => ['y_end' => 90.0, 'x_end' => 260.0, 'y_start' => 0.0, 'x_start' => 120.0]],
                    ['col_id' => 2, 'bbox' => ['x1' => 270.0, 'y1' => 0.0, 'x2' => 290.0, 'y2' => 80.0]],
                ],
                'cells' => [
                    ['left' => 10.0, 'top' => 5.0, 'right' => 90.0, 'bottom' => 25.0, 'text' => 'Block'],
                    ['bbox' => ['y2' => 25.0, 'x2' => 230.0, 'y1' => 5.0, 'x1' => 130.0], 'text' => 'Status'],
                    ['bbox' => ['y_end' => 65.0, 'x_end' => 90.0, 'y_start' => 45.0, 'x_start' => 10.0], 'text' => 'Intro'],
                    ['bbox' => ['bottom' => 65.0, 'right' => 230.0, 'top' => 45.0, 'left' => 130.0], 'text' => 'Published'],
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

echo json_encode([
    'scenario' => 'wordpress-table-named-bbox-geometry-boundary-currentbase',
    'native_boundary' => 'supplied Surya/tabled-style named Bbox fields are normalized before WordPress table crop clipping and grid review metadata',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Named Bbox Table Boundary Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Block</td><td>Status</td></tr><tr><td>Intro</td><td>Published</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After named-bbox geometry review.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'table_crop_boundary' => $boundary['image_size'] ?? null,
    'clipped_band_count' => $boundary['clipped_band_count'] ?? null,
    'excluded_band_count' => $boundary['excluded_band_count'] ?? null,
    'row_coordinate_sources' => array_map(static fn (array $band): ?string => $band['coordinate_source'] ?? null, $boundary['row_bands'] ?? []),
    'col_coordinate_sources' => array_map(static fn (array $band): ?string => $band['coordinate_source'] ?? null, $boundary['col_bands'] ?? []),
    'header_grid_bboxes' => [
        $gridReview['grid_cells'][0]['grid_bbox'] ?? null,
        $gridReview['grid_cells'][1]['grid_bbox'] ?? null,
    ],
    'body_grid_bboxes' => [
        $gridReview['grid_cells'][2]['grid_bbox'] ?? null,
        $gridReview['grid_cells'][3]['grid_bbox'] ?? null,
    ],
    'excluded_outside_bands' => [
        $boundary['row_bands'][2]['status'] ?? null,
        $boundary['col_bands'][2]['status'] ?? null,
    ],
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale named-bbox table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
