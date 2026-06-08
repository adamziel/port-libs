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

$points = static function (float $x1, float $y1, float $x2, float $y2): array {
    return [
        ['x' => (string) $x1, 'y' => (string) $y1],
        ['x' => (string) $x2, 'y' => (string) $y1],
        ['x' => (string) $x2, 'y' => (string) $y2],
        ['x' => (string) $x1, 'y' => (string) $y2],
    ];
};

$recognizedTable = [
    'coordinate_space' => 'page_image',
    'geometry' => [72.0, 150.0, 312.0, 230.0],
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'rows' => [
        ['row_id' => 0, 'geometry' => ['left' => 72.0, 'top' => 150.0, 'right' => 312.0, 'bottom' => 182.0]],
        ['row_id' => 1, 'coordinates' => [72.0, 190.0, 312.0, 220.0]],
        ['row_id' => 99, 'geometry' => [72.0, 250.0, 312.0, 268.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'coordinates' => ['x' => 72.0, 'y' => 150.0, 'width' => 100.0, 'height' => 80.0]],
        ['col_id' => 1, 'geometry' => ['center' => ['x' => 252.0, 'y' => 190.0], 'size' => ['width' => 120.0, 'height' => 80.0]]],
        ['col_id' => 99, 'coordinates' => [342.0, 150.0, 362.0, 230.0]],
    ],
    'cells' => [
        ['geometry' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['coordinates' => ['left' => 202.0, 'top' => 155.0, 'right' => 302.0, 'bottom' => 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['geometry' => $points(82.0, 195.0, 162.0, 215.0), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['coordinates' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['geometry' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale wrapper row', 'row_ids' => [99], 'col_ids' => [0]],
        ['coordinates' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale wrapper col', 'row_ids' => [1], 'col_ids' => [99]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-generic-wrapper-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% generic wrapper table geometry WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Generic wrapper table geometry boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale generic-wrapper table line should be replaced.', 'bbox' => [82.0, 176.0, 360.0, 196.0]],
                ['text' => 'After generic wrapper table review.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 560.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [$recognizedTable],
            'table_text_lines' => [['blocks' => []]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    if (is_file($pdfPath)) {
        unlink($pdfPath);
    }
}

$metadata = $result['metadata'];
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

if (($coordinateReview['status'] ?? null) !== 'translated_to_table_crop') {
    throw new RuntimeException('Expected generic wrapper geometry to localize to table-crop coordinates.');
}
if (($gridReview['render_cells'][2]['source_coordinate_source'] ?? null) !== 'geometry.bbox_polygon_points') {
    throw new RuntimeException('Expected geometry wrapper point lists to preserve source provenance.');
}
if (in_array('Stale wrapper row', $assignedTexts, true) || in_array('Stale wrapper col', $assignedTexts, true)) {
    throw new RuntimeException('Expected off-crop generic wrapper cells to stay out of WordPress table output.');
}
if (str_contains($result['text'], 'Stale generic-wrapper table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale generic wrapper pdftext line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-generic-wrapper-boundary-currentbase',
    'native_boundary' => 'geometry and coordinates wrapper fields are normalized as supplied table geometry before WordPress table output',
    'source_truth' => [
        'upstream_marker' => 'markerPDF routes rendered page crops through marker.tables.table before tabled assignment and Markdown formatting',
        'upstream_tabled' => 'saved table sidecars can carry Bbox-like records through generic geometry containers rather than primary bbox keys',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells only; does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_status' => $coordinateReview['status'] ?? null,
    'table_bbox_source' => $coordinateReview['table_bbox_source'] ?? null,
    'active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'third_cell_source' => $gridReview['render_cells'][2]['source_coordinate_source'] ?? null,
    'third_cell_source_bbox' => $gridReview['render_cells'][2]['source_cell_bbox'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'generic_wrapper_geometry_localized' => ($coordinateReview['status'] ?? null) === 'translated_to_table_crop',
    'geometry_point_wrapper_provenance_preserved' => ($gridReview['render_cells'][2]['source_coordinate_source'] ?? null) === 'geometry.bbox_polygon_points',
    'offcrop_wrapper_cells_filtered_from_assignment' => !in_array('Stale wrapper row', $assignedTexts, true)
        && !in_array('Stale wrapper col', $assignedTexts, true),
    'stale_pdftext_table_line_excluded' => !str_contains($result['text'], 'Stale generic-wrapper table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
