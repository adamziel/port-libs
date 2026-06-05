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

$points = static fn (float $x1, float $y1, float $x2, float $y2): array => [
    ['x' => (string) $x1, 'y' => (string) $y1],
    ['x' => (string) $x2, 'y' => (string) $y1],
    ['x' => (string) $x2, 'y' => (string) $y2],
    ['x' => (string) $x1, 'y' => (string) $y2],
];
$flat = static fn (float $x1, float $y1, float $x2, float $y2): array => [
    $x1,
    $y1,
    $x2,
    $y1,
    $x2,
    $y2,
    $x1,
    $y2,
];

$recognizedTable = [
    'coordinate_space' => 'page_image',
    'vertices' => $points(72.0, 150.0, 312.0, 230.0),
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'rows' => [
        ['row_id' => 0, 'points' => $points(72.0, 150.0, 312.0, 182.0)],
        ['row_id' => 1, 'vertices' => $points(72.0, 190.0, 312.0, 220.0)],
        ['row_id' => 99, 'quad' => $flat(72.0, 250.0, 312.0, 268.0)],
    ],
    'cols' => [
        ['col_id' => 0, 'quadrilateral' => $points(72.0, 150.0, 172.0, 230.0)],
        ['col_id' => 1, 'quad' => $flat(192.0, 150.0, 312.0, 230.0)],
        ['col_id' => 99, 'points' => $points(340.0, 150.0, 360.0, 230.0)],
    ],
    'cells' => [
        ['points' => $points(82.0, 155.0, 162.0, 170.0), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['vertices' => $points(202.0, 155.0, 302.0, 170.0), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['quad' => $flat(82.0, 195.0, 162.0, 215.0), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['quadrilateral' => $points(202.0, 195.0, 302.0, 215.0), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['points' => $points(82.0, 250.0, 162.0, 268.0), 'text' => 'Stale alias row', 'row_ids' => [99], 'col_ids' => [0]],
        ['vertices' => $points(342.0, 195.0, 358.0, 215.0), 'text' => 'Stale alias col', 'row_ids' => [1], 'col_ids' => [99]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-polygon-alias-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% polygon alias table geometry WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $page([
                ['text' => 'Polygon alias table boundary', 'bbox' => [72.0, 48.0, 500.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale polygon alias table text should be replaced.', 'bbox' => [72.0, 176.0, 500.0, 196.0]],
                ['text' => 'After polygon alias table.', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 500.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
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
    if (is_file($pdfPath)) {
        unlink($pdfPath);
    }
}

$metadata = $result['metadata'];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
$renderCells = $metadata['table_spanning_grid_review'][0]['render_cells'] ?? [];
$sourceCoordinateSources = array_values(array_unique(array_filter(array_map(
    static fn (array $cell): ?string => isset($cell['source_coordinate_source']) && is_scalar($cell['source_coordinate_source'])
        ? (string) $cell['source_coordinate_source']
        : null,
    $renderCells
))));
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];

if ($assignedTexts !== ['Feature', 'Status', 'Images', 'Ready']) {
    throw new RuntimeException('Expected polygon alias table cells to be assigned after crop localization.');
}
if (($coordinateReview['status'] ?? null) !== 'translated_to_table_crop') {
    throw new RuntimeException('Expected page-image polygon alias geometry to translate to table-crop space.');
}
if (!in_array('polygon_points', $sourceCoordinateSources, true)
    || !in_array('polygon_vertices', $sourceCoordinateSources, true)
    || !in_array('polygon_quad', $sourceCoordinateSources, true)
    || !in_array('polygon_quadrilateral', $sourceCoordinateSources, true)
) {
    throw new RuntimeException('Expected polygon alias source labels to reach WordPress table grid metadata.');
}

echo json_encode([
    'scenario' => 'wordpress-table-polygon-alias-boundary-currentbase',
    'native_boundary' => 'supplied table rows, columns, cells, and OCR conflict bboxes accept four-corner polygon aliases before crop-local assignment',
    'source_truth' => [
        'upstream' => 'marker.tables.table crops rendered page images before tabled.assignment.assign_rows_columns; tabled consumes Bbox/SpanTableCell geometry after crop-localization',
        'dependency' => 'locked tabled-pdf 0.1.4 schema uses Bbox and SpanTableCell row_ids/col_ids; this PHP boundary normalizes serialized point-list aliases into the same bbox contract',
        'no_gpu_scope' => 'uses supplied recognition JSON and does not run Surya, OCR, tabled models, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Polygon Alias Table Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><th scope="col">Feature</th><th scope="col">Status</th></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After polygon alias table.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_status' => $coordinateReview['status'] ?? null,
    'table_bbox' => $coordinateReview['table_bbox'] ?? null,
    'table_bbox_source' => $coordinateReview['table_bbox_source'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'source_coordinate_sources' => $sourceCoordinateSources,
    'active_cell_count' => $metadata['table_assigned_crop_boundary_reviews'][0]['active_cell_count'] ?? null,
    'excluded_cell_count' => $metadata['table_assigned_crop_boundary_reviews'][0]['excluded_cell_count'] ?? null,
    'inserted_tables' => $metadata['inserted_tables'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
