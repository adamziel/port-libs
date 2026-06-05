<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$points = static fn (float $x1, float $y1, float $x2, float $y2): array => [
    ['x' => (string) $x1, 'y' => (string) $y1],
    ['x' => (string) $x2, 'y' => (string) $y1],
    ['x' => (string) $x2, 'y' => (string) $y2],
    ['x' => (string) $x1, 'y' => (string) $y2],
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
    'bbox' => [400.0, 300.0, 520.0, 340.0],
    'polygon' => $points(72.0, 150.0, 312.0, 230.0),
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'rows' => [
        ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
        ['row_id' => 1, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
        ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 270.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
        ['col_id' => 1, 'bbox' => [192.0, 150.0, 312.0, 230.0]],
        ['col_id' => 99, 'bbox' => [342.0, 150.0, 362.0, 230.0]],
    ],
    'cells' => [
        ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale polygon row', 'row_ids' => [99], 'col_ids' => [0]],
        ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale polygon column', 'row_ids' => [1], 'col_ids' => [99]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-crop-polygon-stale-bbox-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table crop polygon stale bbox WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $page([
                ['text' => 'Table crop polygon stale bbox boundary', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale crop-bbox table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                ['text' => 'After crop polygon stale bbox table.', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 520.0, 68.0]],
                    [
                        'label' => 'Table',
                        'bbox' => [400.0, 300.0, 520.0, 340.0],
                        'polygon' => $points(72.0, 150.0, 312.0, 230.0),
                    ],
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
    if (is_file($pdfPath)) {
        unlink($pdfPath);
    }
}

$metadata = $result['metadata'];
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

if (($metadata['table_plan']['table_bboxes'] ?? null) !== [[72.0, 150.0, 312.0, 230.0]]) {
    throw new RuntimeException('Expected table crop planning to use the supplied polygon instead of the stale bbox.');
}
if (($coordinateReview['table_bbox'] ?? null) !== [72.0, 150.0, 312.0, 230.0]) {
    throw new RuntimeException('Expected table recognition localization to use the supplied polygon instead of the stale bbox.');
}
if ($assignedTexts !== ['Feature', 'Status', 'Images', 'Ready']) {
    throw new RuntimeException('Expected only crop-local table cells to be assigned.');
}
if (($metadata['inserted_tables'] ?? null) !== 1 || str_contains($result['text'], 'Stale crop-bbox table line should be replaced.')) {
    throw new RuntimeException('Expected the stale pdftext table line to be replaced by Markdown table output.');
}

echo json_encode([
    'scenario' => 'wordpress-table-crop-polygon-stale-bbox-boundary-currentbase',
    'native_boundary' => 'supplied layout and table-recognition crop polygons override stale crop bboxes before crop planning, localization, and WordPress table insertion',
    'source_truth' => [
        'upstream' => 'marker.tables.table crops rendered page images from layout table geometry before tabled assignment',
        'no_gpu_scope' => 'uses supplied layout/recognition JSON and does not run Surya, OCR, tabled models, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Table Crop Polygon Stale Bbox Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><th scope="col">Feature</th><th scope="col">Status</th></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After crop polygon stale bbox table.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'table_plan_bboxes' => $metadata['table_plan']['table_bboxes'] ?? [],
    'coordinate_status' => $coordinateReview['status'] ?? null,
    'coordinate_table_bbox' => $coordinateReview['table_bbox'] ?? null,
    'coordinate_table_bbox_source' => $coordinateReview['table_bbox_source'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'inserted_tables' => $metadata['inserted_tables'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
