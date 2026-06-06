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

$point = static fn (float $x, float $y): array => ['x' => (string) $x, 'y' => (string) $y];
$pointPairBox = static function (
    float $x1,
    float $y1,
    float $x2,
    float $y2,
    string $style = 'start-end'
) use ($point): array {
    return match ($style) {
        'from-to' => ['from' => $point($x1, $y1), 'to' => $point($x2, $y2)],
        'p1-p2' => ['p1' => [$x1, $y1], 'p2' => [$x2, $y2]],
        'point1-point2' => ['point1' => $point($x1, $y1), 'point2' => $point($x2, $y2)],
        'start-point-end-point' => ['start_point' => [$x1, $y1], 'end_point' => [$x2, $y2]],
        'reversed' => ['start' => $point($x2, $y2), 'end' => $point($x1, $y1)],
        default => ['start' => $point($x1, $y1), 'end' => $point($x2, $y2)],
    };
};

$recognizedTable = [
    'coordinate_space' => 'page_image',
    'image_bbox' => $pointPairBox(0.0, 0.0, 612.0, 792.0, 'start-point-end-point'),
    'bbox' => $pointPairBox(72.0, 150.0, 312.0, 230.0),
    'rows' => [
        ['row_id' => 10, 'bbox' => $pointPairBox(72.0, 150.0, 312.0, 182.0)],
        ['row_id' => 20] + $pointPairBox(72.0, 190.0, 312.0, 220.0, 'from-to'),
        ['row_id' => 99, 'bbox' => $pointPairBox(72.0, 250.0, 312.0, 268.0, 'p1-p2')],
    ],
    'cols' => [
        ['col_id' => 30, 'bbox' => $pointPairBox(72.0, 150.0, 172.0, 230.0, 'p1-p2')],
        ['col_id' => 40, 'bbox' => $pointPairBox(192.0, 150.0, 312.0, 230.0, 'point1-point2')],
        ['col_id' => 99] + $pointPairBox(340.0, 150.0, 360.0, 230.0),
    ],
    'cells' => [
        ['bbox' => $pointPairBox(82.0, 155.0, 162.0, 170.0, 'reversed'), 'text' => 'Feature', 'row_ids' => [10], 'col_ids' => [30]],
        ['bbox' => $pointPairBox(202.0, 155.0, 302.0, 170.0, 'from-to'), 'text' => 'Status', 'row_ids' => [10], 'col_ids' => [40]],
        ['p1' => [82.0, 195.0], 'p2' => [162.0, 215.0], 'text' => 'Images', 'row_ids' => [20], 'col_ids' => [30]],
        ['bbox' => $pointPairBox(202.0, 195.0, 302.0, 215.0, 'point1-point2'), 'text' => 'Ready', 'row_ids' => [20], 'col_ids' => [40]],
        ['bbox' => $pointPairBox(82.0, 250.0, 162.0, 268.0), 'text' => 'Stale point row', 'row_ids' => [99], 'col_ids' => [30]],
        ['bbox' => $pointPairBox(360.0, 195.0, 382.0, 215.0), 'text' => 'Stale point column', 'row_ids' => [20], 'col_ids' => [99]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-point-pair-alias-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% point pair alias table WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Point pair alias boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale point-pair table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                ['text' => 'After point pair alias table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
$cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$renderCells = $gridReview['render_cells'] ?? [];
$assigned = $metadata['table_assigned_cells'][0] ?? [];
$assignedTexts = array_column($assigned, 'text');

$pointPairAliasesNormalized = ($coordinateReview['status'] ?? null) === 'translated_to_table_crop'
    && ($gridReview['geometry_boundary_review']['row_bands'][0]['source_coordinate_source'] ?? null) === 'bbox_start_end_points'
    && ($gridReview['geometry_boundary_review']['row_bands'][1]['source_coordinate_source'] ?? null) === 'bbox_from_to_points'
    && ($gridReview['geometry_boundary_review']['col_bands'][0]['source_coordinate_source'] ?? null) === 'bbox_p1_p2_points'
    && ($gridReview['geometry_boundary_review']['col_bands'][1]['source_coordinate_source'] ?? null) === 'bbox_point1_point2_points';
$offcropCellsFiltered = !in_array('Stale point row', $assignedTexts, true)
    && !in_array('Stale point column', $assignedTexts, true)
    && !str_contains($result['text'], 'Stale point row')
    && !str_contains($result['text'], 'Stale point column');
$staleLineRemoved = !str_contains($result['text'], 'Stale point-pair table line should be replaced.');
$endpointOrderReviewed = ($renderCells[0]['source_endpoint_order_normalized'] ?? null) === true;

if (!$pointPairAliasesNormalized || !$offcropCellsFiltered || !$staleLineRemoved || !$endpointOrderReviewed) {
    throw new RuntimeException('Expected neutral point-pair aliases to survive table localization before WordPress table insertion.');
}

echo json_encode([
    'scenario' => 'wordpress-table-point-pair-alias-boundary-currentbase',
    'native_boundary' => 'supplied table rows, columns, and cells using neutral point-pair aliases are normalized before crop-local WordPress table insertion',
    'source_truth' => [
        'upstream' => 'marker/tables/table.py crops each table image before assigning recognized rows, columns, and cells',
        'tabled_boundary' => 'tabled Markdown formatting receives crop-local rows, columns, and cells after geometry localization',
        'no_gpu_scope' => 'uses supplied recognition rows/cells and does not run Surya, tabled models, OCR, Python, PDFium, PIL, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Point Pair Alias Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After point pair alias table.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_review_status' => $coordinateReview['status'] ?? null,
    'row_source_coordinate_sources' => array_column($gridReview['geometry_boundary_review']['row_bands'] ?? [], 'source_coordinate_source'),
    'col_source_coordinate_sources' => array_column($gridReview['geometry_boundary_review']['col_bands'] ?? [], 'source_coordinate_source'),
    'render_source_coordinate_source' => $renderCells[0]['source_coordinate_source'] ?? null,
    'render_source_bbox' => $renderCells[0]['source_cell_bbox'] ?? null,
    'source_endpoint_order_normalized' => $renderCells[0]['source_endpoint_order_normalized'] ?? null,
    'assigned_crop_active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'assigned_crop_excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'point_pair_aliases_normalized' => $pointPairAliasesNormalized,
    'offcrop_cells_filtered_from_assignment' => $offcropCellsFiltered,
    'excluded_stale_pdftext_table_line' => $staleLineRemoved,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
