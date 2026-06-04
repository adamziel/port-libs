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

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-numeric-string-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% numeric-string table geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Numeric string table boundary review', 'bbox' => [72.0, 48.0, 500.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale numeric-string table text should be replaced.', 'bbox' => [72.0, 176.0, 312.0, 196.0]],
                ['text' => 'After numeric-string geometry review.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
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
                    ['row_id' => '10', 'bbox' => ['-4.0', '-2.0', '245.0', '30.0']],
                    ['row_id' => '11', 'bbox' => ['0.0', '40.0', '240.0', '70.0']],
                    ['row_id' => '12', 'bbox' => ['0.0', '92.0', '240.0', '118.0']],
                ],
                'cols' => [
                    ['col_id' => '20', 'bbox' => ['0.0', '0.0', '100.0', '90.0']],
                    ['col_id' => '21', 'bbox' => ['120.0', '0.0', '260.0', '90.0']],
                    ['col_id' => '22', 'bbox' => ['270.0', '0.0', '290.0', '80.0']],
                ],
                'cells' => [
                    ['bbox' => ['10.0', '5.0', '90.0', '25.0'], 'text' => 'Block'],
                    ['bbox' => ['130.0', '5.0', '230.0', '25.0'], 'text' => 'Status'],
                    ['bbox' => ['10.0', '45.0', '90.0', '65.0'], 'text' => 'Intro'],
                    ['bbox' => ['130.0', '45.0', '230.0', '65.0'], 'text' => 'Published'],
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
    'scenario' => 'wordpress-table-geometry-numeric-string-boundary-currentbase',
    'native_boundary' => 'supplied tabled/Pydantic-style numeric string geometry is coerced before row-column assignment and WordPress crop-grid review metadata',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Numeric String Table Boundary Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Block</td><td>Status</td></tr><tr><td>Intro</td><td>Published</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After numeric-string geometry review.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'table_crop_boundary' => $boundary['image_size'] ?? null,
    'clipped_band_count' => $boundary['clipped_band_count'] ?? null,
    'excluded_band_count' => $boundary['excluded_band_count'] ?? null,
    'grid_positions' => array_map(
        static fn (array $cell): string => $cell['row_id'] . ':' . $cell['col_id'],
        $gridReview['grid_cells'] ?? []
    ),
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
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale numeric-string table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
