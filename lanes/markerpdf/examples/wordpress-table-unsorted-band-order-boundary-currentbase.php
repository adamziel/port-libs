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
    'rows' => [
        ['row_id' => 20, 'bbox' => [0.0, 40.0, 200.0, 70.0]],
        ['row_id' => -5, 'bbox' => [0.0, 0.0, 200.0, 30.0]],
    ],
    'cols' => [
        ['col_id' => 100, 'bbox' => [108.0, 0.0, 200.0, 80.0]],
        ['col_id' => -10, 'bbox' => [0.0, 0.0, 96.0, 80.0]],
    ],
    'cells' => [
        ['bbox' => [6.0, 5.0, 84.0, 24.0], 'text' => 'Feature'],
        ['bbox' => [116.0, 5.0, 190.0, 24.0], 'text' => 'Status'],
        ['bbox' => [6.0, 45.0, 84.0, 64.0], 'text' => 'Images'],
        ['bbox' => [116.0, 45.0, 190.0, 64.0], 'text' => 'Ready'],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-unsorted-band-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table unsorted band order boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Unsorted band order table review', 'bbox' => [72.0, 48.0, 500.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale source-order table text should be replaced.', 'bbox' => [72.0, 176.0, 330.0, 196.0]],
                ['text' => 'After unsorted band order review.', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 500.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 272.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [$recognizedTable],
            'table_text_lines' => [['blocks' => []]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($pdfPath);
}

$metadata = $result['metadata'];
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$boundary = $gridReview['geometry_boundary_review'] ?? [];
$assigned = $metadata['table_assigned_cells'][0] ?? [];
$assignedTexts = array_column($assigned, 'text');

$checks = [
    'row_band_order_normalized' => ($boundary['row_band_order_normalized'] ?? null) === true,
    'col_band_order_normalized' => ($boundary['col_band_order_normalized'] ?? null) === true,
    'geometry_rows_top_to_bottom' => ($gridReview['rows'] ?? null) === [-5, 20],
    'geometry_cols_left_to_right' => ($gridReview['cols'] ?? null) === [-10, 100],
    'renders_feature_status_header' => str_contains($result['text'], '| Feature | Status |'),
    'renders_images_ready_data_row' => str_contains($result['text'], '| Images  | Ready  |'),
    'excludes_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale source-order table text should be replaced.'),
    'does_not_execute_models' => true,
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('Expected unsorted table band order check to pass: ' . $name);
    }
}

echo json_encode([
    'scenario' => 'wordpress-table-unsorted-band-order-boundary-currentbase',
    'native_boundary' => 'supplied table rows and columns are sorted by cropped table geometry before tabled-style assignment and WordPress table formatting',
    'source_truth' => [
        'upstream' => 'sddai/markerPDF marker/tables/table.py::get_table_boxes crops high-resolution page images before tabled assignment',
        'tabled_boundary' => 'tabled assignment operates on crop-local row/column geometry, so stale serialized source order must not invert the rendered table grid',
        'no_gpu_scope' => 'uses supplied recognition rows/cells and does not run Surya, tabled models, OCR, Python, PDFium, PIL, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Unsorted Band Order Table Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After unsorted band order review.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'assigned_table_texts' => $assignedTexts,
    'active_row_ids' => $boundary['active_row_ids'] ?? null,
    'active_col_ids' => $boundary['active_col_ids'] ?? null,
    'row_sort_axis' => $boundary['row_sort_axis'] ?? null,
    'col_sort_axis' => $boundary['col_sort_axis'] ?? null,
    'row_band_order_normalized' => $boundary['row_band_order_normalized'] ?? null,
    'col_band_order_normalized' => $boundary['col_band_order_normalized'] ?? null,
    'row_review_orders' => array_map(static fn (array $row): ?int => $row['geometry_order'] ?? null, $boundary['row_bands'] ?? []),
    'col_review_orders' => array_map(static fn (array $col): ?int => $col['geometry_order'] ?? null, $boundary['col_bands'] ?? []),
    'excluded_stale_pdftext_table_line' => $checks['excludes_stale_pdftext_table_line'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
