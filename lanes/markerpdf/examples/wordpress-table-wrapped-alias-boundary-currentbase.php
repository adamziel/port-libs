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

$table = [
    'coordinate_space' => 'page_image',
    'bbox' => ['stale' => 'not-a-geometry'],
    'box' => [72.0, 150.0, 312.0, 230.0],
    'image_bbox' => ['box' => [0.0, 0.0, 612.0, 792.0]],
    'rows' => [
        ['row_id' => 0, 'bbox' => ['stale' => 'not-a-geometry'], 'box' => [72.0, 150.0, 312.0, 182.0]],
        ['row_id' => 1, 'rect' => ['left' => 72.0, 'top' => 190.0, 'right' => 312.0, 'bottom' => 220.0]],
        ['row_id' => 99, 'bounds' => ['x' => 72.0, 'y' => 250.0, 'width' => 240.0, 'height' => 20.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bounds' => ['x' => 72.0, 'y' => 150.0, 'width' => 100.0, 'height' => 80.0]],
        ['col_id' => 1, 'bounding_box' => ['center' => ['x' => 252.0, 'y' => 190.0], 'size' => ['width' => 120.0, 'height' => 80.0]]],
        ['col_id' => 99, 'rectangle' => [342.0, 150.0, 362.0, 230.0]],
    ],
    'cells' => [
        ['bbox' => ['stale' => 'not-a-geometry'], 'box' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['rect' => ['left' => 202.0, 'top' => 155.0, 'right' => 302.0, 'bottom' => 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bounds' => ['x' => 82.0, 'y' => 195.0, 'width' => 80.0, 'height' => 20.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bounding_box' => ['center' => ['x' => 252.0, 'y' => 205.0], 'width' => 100.0, 'height' => 20.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['box' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale alias row', 'row_ids' => [99], 'col_ids' => [0]],
        ['rectangle' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale alias col', 'row_ids' => [1], 'col_ids' => [99]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-wrapped-alias-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table wrapped alias boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Wrapped alias table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale wrapped-alias table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                ['text' => 'After wrapped alias table review.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [$table],
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
$staleFiltered = !str_contains($result['text'], 'Stale wrapped-alias table line should be replaced.')
    && !str_contains($result['text'], 'Stale alias row')
    && !str_contains($result['text'], 'Stale alias col');

if (($coordinateReview['table_bbox_source'] ?? null) !== 'table_bbox') {
    throw new RuntimeException('Expected the supplied layout table bbox to remain authoritative in WordPress conversion.');
}
if (($gridReview['render_cells'][3]['source_coordinate_source'] ?? null) !== 'bounding_box.bbox_center_width_height_fields') {
    throw new RuntimeException('Expected wrapped alias cell geometry to reach table grid review metadata.');
}
if ($assignedTexts !== ['Feature', 'Status', 'Images', 'Ready'] || !$staleFiltered) {
    throw new RuntimeException('Expected stale wrapped-alias table text and off-crop supplied cells to be filtered.');
}

echo json_encode([
    'scenario' => 'wordpress-table-wrapped-alias-boundary-currentbase',
    'native_boundary' => 'record-level box, rect, bounds, rectangle, and bounding_box table geometry aliases are normalized before WordPress table grid review',
    'source_geometry_aliases' => ['box', 'rect', 'bounds', 'rectangle', 'bounding_box'],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'recognized_table_status' => $coordinateReview['status'] ?? null,
    'recognized_table_bbox_source' => $coordinateReview['table_bbox_source'] ?? null,
    'render_cell_sources' => array_map(
        static fn (array $cell): ?string => $cell['source_coordinate_source'] ?? null,
        $gridReview['render_cells'] ?? []
    ),
    'assigned_table_texts' => $assignedTexts,
    'stale_pdftext_and_alias_cells_filtered' => $staleFiltered,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
