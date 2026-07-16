<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$point = static function (float $x, float $y, string $style = 'left-top'): array {
    return match ($style) {
        'right-bottom' => ['bottom' => (string) $y, 'right' => (string) $x],
        'x0-y0' => ['y0' => (string) $y, 'x0' => (string) $x],
        'x1-y1' => ['y1' => (string) $y, 'x1' => (string) $x],
        'xmin-ymin' => ['ymin' => (string) $y, 'xmin' => (string) $x],
        'xmax-ymax' => ['ymax' => (string) $y, 'xmax' => (string) $x],
        default => ['top' => (string) $y, 'left' => (string) $x],
    };
};
$box = static fn (float $x1, float $y1, float $x2, float $y2): array => [
    'top_left' => $point($x1, $y1),
    'bottom_right' => $point($x2, $y2, 'right-bottom'),
];
$startEndBox = static fn (float $x1, float $y1, float $x2, float $y2): array => [
    'start' => $point($x1, $y1, 'x0-y0'),
    'end' => $point($x2, $y2, 'x1-y1'),
];
$minMaxBox = static fn (float $x1, float $y1, float $x2, float $y2): array => [
    'p1' => $point($x1, $y1, 'xmin-ymin'),
    'p2' => $point($x2, $y2, 'xmax-ymax'),
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
    'image_bbox' => $box(0.0, 0.0, 612.0, 792.0),
    'bbox' => $box(72.0, 150.0, 312.0, 230.0),
    'rows' => [
        ['row_id' => 10, 'bbox' => $box(72.0, 150.0, 312.0, 182.0)],
        ['row_id' => 20, 'bbox' => $box(72.0, 190.0, 312.0, 220.0)],
        ['row_id' => 99, 'bbox' => $box(72.0, 250.0, 312.0, 268.0)],
    ],
    'cols' => [
        ['col_id' => 30, 'bbox' => $minMaxBox(72.0, 150.0, 172.0, 230.0)],
        ['col_id' => 40, 'bbox' => $startEndBox(192.0, 150.0, 312.0, 230.0)],
        ['col_id' => 99, 'bbox' => $box(340.0, 150.0, 360.0, 230.0)],
    ],
    'cells' => [
        ['bbox' => ['top_left' => $point(162.0, 170.0, 'right-bottom'), 'bottom_right' => $point(82.0, 155.0)], 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [30]],
        ['bbox' => $startEndBox(202.0, 155.0, 302.0, 170.0), 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [40]],
        ['bbox' => $minMaxBox(82.0, 195.0, 162.0, 215.0), 'text' => 'Images', 'row_ids' => [20], 'col_ids' => [30]],
        ['bbox' => $box(202.0, 195.0, 302.0, 215.0), 'text' => 'Ready', 'row_ids' => [20], 'col_ids' => [40]],
        ['bbox' => $box(82.0, 250.0, 162.0, 268.0), 'text' => 'Stale below crop', 'row_ids' => [99], 'col_ids' => [30]],
        ['bbox' => $box(360.0, 195.0, 382.0, 215.0), 'text' => 'Stale right edge', 'row_ids' => [20], 'col_ids' => [99]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-point-object-alias-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table point object alias boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $page([
                ['text' => 'Point object alias table geometry', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale point-object table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                ['text' => 'After point object alias table.', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => $box(0.0, 0.0, 612.0, 792.0),
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 520.0, 68.0]],
                    ['label' => 'Table', 'bbox' => $box(72.0, 150.0, 312.0, 230.0)],
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
    unlink($pdfPath);
}

$metadata = $result['metadata'];
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

echo json_encode([
    'scenario' => 'wordpress-table-point-object-alias-boundary-currentbase',
    'native_boundary' => 'named point-coordinate objects such as top/left, bottom/right, x0/y0, and xmin/ymin are interpreted by key before table replacement',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Point Object Alias Table Geometry</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After point object alias table.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'table_plan_bboxes' => $metadata['table_plan']['table_bboxes'] ?? [],
    'coordinate_review_status' => $coordinateReview['status'] ?? null,
    'coordinate_review_table_bbox' => $coordinateReview['table_bbox'] ?? null,
    'first_cell_source_bbox' => $gridReview['render_cells'][0]['source_cell_bbox'] ?? null,
    'first_cell_source_coordinate_source' => $gridReview['render_cells'][0]['source_coordinate_source'] ?? null,
    'first_cell_endpoint_order_normalized' => $gridReview['render_cells'][0]['source_endpoint_order_normalized'] ?? null,
    'active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'offcrop_cells_filtered_from_assignment' => !in_array('Stale right edge', $assignedTexts, true)
        && !in_array('Stale below crop', $assignedTexts, true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale point-object table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
