<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$orderedBbox = static fn (float $x1, float $y1, float $x2, float $y2): array => [$x1, $x2, $y1, $y2];

$recognizedTable = [
    'coordinate_space' => 'page_image',
    'bbox_order' => 'x1_x2_y1_y2',
    'bbox' => $orderedBbox(72.0, 150.0, 312.0, 230.0),
    'rows' => [
        ['row_id' => 0, 'bbox_order' => 'x1_x2_y1_y2', 'bbox' => $orderedBbox(72.0, 150.0, 312.0, 182.0)],
        ['row_id' => 1, 'bbox_order' => 'x1_x2_y1_y2', 'bbox' => $orderedBbox(72.0, 190.0, 312.0, 220.0)],
        ['row_id' => 99, 'bbox_order' => 'x1_x2_y1_y2', 'bbox' => $orderedBbox(72.0, 250.0, 312.0, 268.0)],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox_order' => 'x1_x2_y1_y2', 'bbox' => $orderedBbox(72.0, 150.0, 172.0, 230.0)],
        ['col_id' => 1, 'bbox_order' => 'x1_x2_y1_y2', 'bbox' => $orderedBbox(192.0, 150.0, 312.0, 230.0)],
        ['col_id' => 99, 'bbox_order' => 'x1_x2_y1_y2', 'bbox' => $orderedBbox(342.0, 150.0, 362.0, 230.0)],
    ],
    'cells' => [
        ['bbox_order' => 'x1_x2_y1_y2', 'bbox' => $orderedBbox(82.0, 155.0, 162.0, 170.0), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['bbox_order' => 'x1_x2_y1_y2', 'bbox' => $orderedBbox(202.0, 155.0, 302.0, 170.0), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bbox_order' => 'x1_x2_y1_y2', 'bbox' => $orderedBbox(82.0, 195.0, 162.0, 215.0), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox_order' => 'x1_x2_y1_y2', 'bbox' => $orderedBbox(202.0, 195.0, 302.0, 215.0), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox_order' => 'x1_x2_y1_y2', 'bbox' => $orderedBbox(82.0, 250.0, 162.0, 268.0), 'text' => 'Stale ordered row', 'row_ids' => [99], 'col_ids' => [0]],
        ['bbox_order' => 'x1_x2_y1_y2', 'bbox' => $orderedBbox(360.0, 195.0, 382.0, 215.0), 'text' => 'Stale ordered column', 'row_ids' => [1], 'col_ids' => [99]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-coordinate-order-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table coordinate order boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $page([
                ['text' => 'Coordinate order table boundary', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale coordinate-order table line should be replaced.', 'bbox' => [82.0, 176.0, 360.0, 196.0]],
                ['text' => 'After coordinate order table.', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 520.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
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

if (($coordinateReview['status'] ?? null) !== 'translated_to_table_crop') {
    throw new RuntimeException('Expected page-image ordered bboxes to be translated into the supplied table crop.');
}
if (($gridReview['render_cells'][0]['source_coordinate_source'] ?? null) !== 'bbox_array_x1_x2_y1_y2_order') {
    throw new RuntimeException('Expected x1/x2/y1/y2 source coordinate order to remain review-visible.');
}
if (in_array('Stale ordered row', $assignedTexts, true) || in_array('Stale ordered column', $assignedTexts, true)) {
    throw new RuntimeException('Expected ordered off-crop cells to be filtered before WordPress table output.');
}
if (str_contains($result['text'], 'Stale coordinate-order table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-coordinate-order-boundary-currentbase',
    'native_boundary' => 'explicit x1/x2/y1/y2 supplied table bboxes are normalized before table-crop localization and assignment',
    'source_truth' => [
        'upstream' => 'markerPDF crops each table image before tabled assignment; supplied tabled-style sidecars can arrive in page-image coordinates and must be localized before Markdown/WordPress table output',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Coordinate Order Table Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After coordinate order table.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_review_status' => $coordinateReview['status'] ?? null,
    'coordinate_review_table_bbox_source' => $coordinateReview['table_bbox_source'] ?? null,
    'translated_cell_count' => $coordinateReview['translated_cell_count'] ?? null,
    'render_source_coordinate_source' => $gridReview['render_cells'][0]['source_coordinate_source'] ?? null,
    'render_source_bbox' => $gridReview['render_cells'][0]['source_cell_bbox'] ?? null,
    'assigned_crop_active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'assigned_crop_excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'offcrop_cells_filtered_from_assignment' => !in_array('Stale ordered row', $assignedTexts, true)
        && !in_array('Stale ordered column', $assignedTexts, true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale coordinate-order table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
