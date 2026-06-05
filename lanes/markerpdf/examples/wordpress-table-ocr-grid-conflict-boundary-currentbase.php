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

$rows = [
    ['row_id' => 0, 'bbox' => [-8.0, -6.0, 210.0, 26.0]],
    ['row_id' => 1, 'bbox' => [0.0, 36.0, 210.0, 70.0]],
    ['row_id' => 2, 'bbox' => [0.0, 96.0, 210.0, 124.0]],
];
$cols = [
    ['col_id' => 0, 'bbox' => [-6.0, 0.0, 90.0, 96.0]],
    ['col_id' => 1, 'bbox' => [96.0, 0.0, 206.0, 96.0]],
    ['col_id' => 2, 'bbox' => [230.0, 0.0, 260.0, 80.0]],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-ocr-conflict-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% OCR grid conflict geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Table OCR conflict boundary review', 'bbox' => [72.0, 48.0, 500.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale OCR conflict table text should be replaced.', 'bbox' => [72.0, 176.0, 330.0, 196.0]],
                ['text' => 'After conflict geometry review.', 'bbox' => [72.0, 276.0, 450.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 500.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 272.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 450.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => $rows,
                'cols' => $cols,
            ]],
            'table_detect_boxes' => true,
            'table_detector_cells' => [[
                ['bbox' => [2.0, 4.0, 198.0, 20.0], 'text' => ''],
                ['bbox' => [6.0, 42.0, 84.0, 62.0], 'text' => ''],
                ['bbox' => [102.0, 42.0, 196.0, 62.0], 'text' => ''],
            ]],
            'table_ocr_text_lines' => [[
                'lines' => [
                    ['text' => 'Header', 'bbox' => [0.0, 4.0, 198.0, 20.0]],
                    ['text' => 'Images', 'bbox' => [0.0, 42.0, 198.0, 62.0]],
                    ['text' => 'Ready', 'bbox' => [0.0, 42.0, 198.0, 62.0]],
                ],
            ]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($pdfPath);
}

$conflicts = $result['metadata']['table_ocr_grid_border_conflicts'][0] ?? [];
$firstConflict = $conflicts[0] ?? [];
$boundary = is_array($firstConflict) ? ($firstConflict['geometry_boundary_review'] ?? []) : [];
if (($boundary['review_target'] ?? null) !== 'table_grid_geometry_boundary') {
    throw new RuntimeException('Missing OCR conflict table grid boundary review.');
}
if (str_contains($result['text'], 'Stale OCR conflict table text should be replaced.')) {
    throw new RuntimeException('Expected supplied OCR table Markdown to replace stale pdftext table line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-ocr-grid-conflict-boundary-currentbase',
    'native_boundary' => 'OCR grid-border conflict rows carry clipped row and column crop-boundary review metadata for WordPress table overlays',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Table OCR Conflict Boundary Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Header</td><td></td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After conflict geometry review.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'table_crop_boundary' => $boundary['image_size'] ?? null,
    'conflict_count' => count($conflicts),
    'first_conflict_axis' => $firstConflict['grid_border_axis'] ?? null,
    'clipped_band_count' => $boundary['clipped_band_count'] ?? null,
    'excluded_band_count' => $boundary['excluded_band_count'] ?? null,
    'assigned_conflict_grid_bbox' => $firstConflict['assigned_grid_cell']['grid_bbox'] ?? null,
    'assigned_conflict_render_text' => $firstConflict['assigned_grid_render_cell']['render_cell']['text'] ?? null,
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale OCR conflict table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
