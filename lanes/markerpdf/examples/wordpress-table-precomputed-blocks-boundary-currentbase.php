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

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-precomputed-blocks-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table precomputed blocks WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Precomputed table block boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale precomputed table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                ['text' => 'After precomputed block review.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 358.0, 32.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 170.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [180.0, 0.0, 358.0, 80.0]],
                ],
            ]],
            'table_text_lines' => [[
                'table_blocks_coordinate_space' => 'table_crop',
                'table_blocks' => [
                    ['bbox' => [-6.0, 10.0, 47.0, 24.0], 'text' => 'Margin'],
                    ['bbox' => [180.0, 10.0, 224.0, 24.0], 'text' => 'Value'],
                ],
            ]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    if (is_file($pdfPath)) {
        unlink($pdfPath);
    }
}

$review = $result['metadata']['table_text_cell_boundary_reviews'][0] ?? [];

echo json_encode([
    'scenario' => 'wordpress-table-precomputed-blocks-boundary-currentbase',
    'native_boundary' => 'precomputed surya.input.pdflines.get_table_blocks cells are accepted as table-crop-local before WordPress table rendering',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Precomputed Table Block Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Margin</td><td>Value</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After precomputed block review.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'table_needs_ocr' => $result['metadata']['table_needs_ocr'] ?? [],
    'table_cell_counts' => $result['metadata']['table_cell_counts'] ?? [],
    'table_text_cell_source' => $review['source'] ?? null,
    'table_crop_size' => $review['table_crop_size'] ?? null,
    'clipped_cell_count' => $review['clipped_cell_count'] ?? null,
    'first_cell_original_bbox' => $review['cells'][0]['original_bbox'] ?? null,
    'first_cell_bounded_bbox' => $review['cells'][0]['bounded_bbox'] ?? null,
    'precomputed_blocks_used_without_detector_ocr' => ($result['metadata']['table_needs_ocr'] ?? []) === [false],
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale precomputed table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
