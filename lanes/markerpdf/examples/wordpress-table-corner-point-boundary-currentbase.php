<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$point = static fn (float $x, float $y): array => ['x' => (string) $x, 'y' => (string) $y];
$box = static fn (float $x1, float $y1, float $x2, float $y2): array => [
    'top_left' => $point($x1, $y1),
    'bottom_right' => $point($x2, $y2),
];
$upperBox = static fn (float $x1, float $y1, float $x2, float $y2): array => [
    'upper_left' => $point($x1, $y1),
    'lower_right' => $point($x2, $y2),
];
$page = static function (array $lines): array {
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
    'image_bbox' => $upperBox(0.0, 0.0, 612.0, 792.0),
    'bbox' => $box(72.0, 150.0, 312.0, 230.0),
    'rows' => [
        ['row_id' => 0, 'bbox' => $box(72.0, 150.0, 312.0, 182.0)],
        ['row_id' => 1, 'bbox' => $box(72.0, 190.0, 312.0, 220.0)],
        ['row_id' => 99, 'bbox' => $box(72.0, 250.0, 312.0, 268.0)],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => $upperBox(72.0, 150.0, 172.0, 230.0)],
        ['col_id' => 1, 'bbox' => ['tl' => [192.0, 150.0], 'br' => [312.0, 230.0]]],
        ['col_id' => 99, 'bbox' => $box(342.0, 150.0, 362.0, 230.0)],
    ],
    'cells' => [
        ['bbox' => ['top_left' => $point(162.0, 170.0), 'bottom_right' => $point(82.0, 155.0)], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['bbox' => $box(202.0, 155.0, 302.0, 170.0), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bbox' => $box(82.0, 195.0, 162.0, 215.0), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox' => $upperBox(202.0, 195.0, 302.0, 215.0), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox' => $box(82.0, 250.0, 162.0, 268.0), 'text' => 'Stale below crop', 'row_ids' => [99], 'col_ids' => [0]],
        ['bbox' => $box(360.0, 195.0, 382.0, 215.0), 'text' => 'Stale right edge', 'row_ids' => [1], 'col_ids' => [99]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-corner-point-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table corner point boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $page([
                ['text' => 'Corner point table boundary', 'bbox' => [72.0, 48.0, 500.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale corner point table line should be replaced.', 'bbox' => [72.0, 176.0, 380.0, 196.0]],
                ['text' => 'After corner point table.', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => $box(0.0, 0.0, 612.0, 792.0),
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 500.0, 68.0]],
                    ['label' => 'Table', 'bbox' => $box(72.0, 150.0, 312.0, 230.0)],
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
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

echo json_encode([
    'scenario' => 'wordpress-table-corner-point-boundary-currentbase',
    'native_boundary' => 'two named corner points are accepted as supplied table crop, row, column, and cell bboxes before WordPress table replacement',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Corner Point Table Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After corner point table.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'table_plan_bboxes' => $metadata['table_plan']['table_bboxes'] ?? [],
    'coordinate_review_status' => $coordinateReview['status'] ?? null,
    'coordinate_review_table_bbox' => $coordinateReview['table_bbox'] ?? null,
    'first_cell_source_bbox' => $gridReview['render_cells'][0]['source_cell_bbox'] ?? null,
    'first_cell_source_coordinate_source' => $gridReview['render_cells'][0]['source_coordinate_source'] ?? null,
    'first_cell_endpoint_order_normalized' => $gridReview['render_cells'][0]['source_endpoint_order_normalized'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'offcrop_cells_filtered_from_assignment' => !in_array('Stale right edge', $assignedTexts, true)
        && !in_array('Stale below crop', $assignedTexts, true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale corner point table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
