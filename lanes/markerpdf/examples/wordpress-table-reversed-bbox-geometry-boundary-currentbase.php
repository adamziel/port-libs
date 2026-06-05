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

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-reversed-bbox-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% reversed bbox table geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Reversed Bbox table boundary review', 'bbox' => [72.0, 48.0, 500.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale reversed-bbox table text should be replaced.', 'bbox' => [72.0, 176.0, 340.0, 196.0]],
                ['text' => 'After reversed-bbox geometry review.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 500.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [240.0, 30.0, -4.0, -2.0]],
                    ['row_id' => 1, 'bbox' => [240.0, 70.0, 0.0, 40.0]],
                    ['row_id' => 2, 'bbox' => [240.0, 118.0, 0.0, 92.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [100.0, 80.0, 0.0, 0.0]],
                    ['col_id' => 1, 'bbox' => [260.0, 80.0, 120.0, 0.0]],
                    ['col_id' => 2, 'bbox' => [290.0, 80.0, 270.0, 0.0]],
                ],
                'cells' => [
                    ['bbox' => [90.0, 25.0, 10.0, 5.0], 'text' => 'Block'],
                    ['bbox' => [230.0, 25.0, 130.0, 5.0], 'text' => 'Status'],
                    ['bbox' => [90.0, 65.0, 10.0, 45.0], 'text' => 'Intro'],
                    ['bbox' => [230.0, 65.0, 130.0, 45.0], 'text' => 'Published'],
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
$markdown = $result['text'];

$rowEndpointNormalized = ($boundary['row_bands'][0]['endpoint_order_normalized'] ?? false) === true;
$colEndpointNormalized = ($boundary['col_bands'][1]['endpoint_order_normalized'] ?? false) === true;
$staleTextExcluded = !str_contains($markdown, 'Stale reversed-bbox table text should be replaced.');

if (($boundary['active_row_band_count'] ?? null) !== 2
    || ($boundary['active_col_band_count'] ?? null) !== 2
    || !$rowEndpointNormalized
    || !$colEndpointNormalized
    || !$staleTextExcluded
) {
    throw new RuntimeException('Expected reversed endpoint table geometry to be normalized before WordPress table review.');
}

echo json_encode([
    'scenario' => 'wordpress-table-reversed-bbox-geometry-boundary-currentbase',
    'native_boundary' => 'supplied table row, column, and cell bbox endpoints are canonicalized before WordPress crop clipping and grid review metadata',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Reversed Bbox Table Boundary Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Block</td><td>Status</td></tr><tr><td>Intro</td><td>Published</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After reversed-bbox geometry review.</p>'],
    ],
    'table_crop_boundary' => $boundary['image_size'] ?? null,
    'active_band_counts' => [
        'rows' => $boundary['active_row_band_count'] ?? null,
        'cols' => $boundary['active_col_band_count'] ?? null,
    ],
    'clipped_band_count' => $boundary['clipped_band_count'] ?? null,
    'excluded_band_count' => $boundary['excluded_band_count'] ?? null,
    'row_endpoint_order_normalized' => $rowEndpointNormalized,
    'col_endpoint_order_normalized' => $colEndpointNormalized,
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
    'excluded_stale_pdftext_table_line' => $staleTextExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $markdown,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
