<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

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

$wrappedBox = static fn (float $x1, float $y1, float $x2, float $y2): array => [
    'bbox' => [$x1, $y1, $x2, $y2],
    'source' => 'tabled.Bbox.model_dump',
];

$recognizedTable = [
    'coordinate_space' => 'page_image',
    'bbox' => $wrappedBox(72.0, 150.0, 312.0, 230.0),
    'image_bbox' => $wrappedBox(0.0, 0.0, 612.0, 792.0),
    'rows' => [
        ['row_id' => 0, 'bbox' => $wrappedBox(72.0, 150.0, 312.0, 182.0)],
        ['row_id' => 1, 'bbox' => $wrappedBox(72.0, 190.0, 312.0, 220.0)],
        ['row_id' => 99, 'bbox' => $wrappedBox(72.0, 250.0, 312.0, 270.0)],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => $wrappedBox(72.0, 150.0, 172.0, 230.0)],
        ['col_id' => 1, 'bbox' => $wrappedBox(192.0, 150.0, 312.0, 230.0)],
        ['col_id' => 99, 'bbox' => $wrappedBox(342.0, 150.0, 362.0, 230.0)],
    ],
    'cells' => [
        ['bbox' => $wrappedBox(82.0, 155.0, 162.0, 170.0), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['bbox' => $wrappedBox(202.0, 155.0, 302.0, 170.0), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bbox' => $wrappedBox(82.0, 195.0, 162.0, 215.0), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox' => $wrappedBox(202.0, 195.0, 302.0, 215.0), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox' => $wrappedBox(82.0, 250.0, 162.0, 268.0), 'text' => 'Stale wrapped row', 'row_ids' => [99], 'col_ids' => [0]],
        ['bbox' => $wrappedBox(360.0, 195.0, 382.0, 215.0), 'text' => 'Stale wrapped col', 'row_ids' => [1], 'col_ids' => [99]],
    ],
    'ocr_grid_border_conflicts' => [[
        'ocr_index' => 0,
        'text' => 'Wide wrapped bbox OCR',
        'bbox' => $wrappedBox(82.0, 155.0, 302.0, 215.0),
        'candidate_cell_indexes' => [0, 1, 2],
        'candidate_cell_bboxes' => [
            $wrappedBox(82.0, 155.0, 162.0, 170.0),
            $wrappedBox(202.0, 155.0, 302.0, 170.0),
            $wrappedBox(82.0, 195.0, 162.0, 215.0),
        ],
        'assigned_cell_index' => 0,
        'spans_grid_border' => true,
    ]],
];

$direct = (new TableRecognizer())->formatRecognizedTables([$recognizedTable], [[]]);

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-wrapped-bbox-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table wrapped bbox boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Wrapped bbox table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale wrapped-bbox table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                ['text' => 'After wrapped bbox table review.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
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
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$directReview = $direct['coordinate_space_reviews'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

if (($directReview['table_bbox_source'] ?? null) !== 'bbox.bbox_array') {
    throw new RuntimeException('Expected wrapped table bbox to provide the direct table-crop boundary.');
}
if (($gridReview['render_cells'][0]['source_coordinate_source'] ?? null) !== 'bbox.bbox_array') {
    throw new RuntimeException('Expected wrapped cell bboxes to preserve bbox-wrapper provenance.');
}
if (in_array('Stale wrapped row', $assignedTexts, true) || in_array('Stale wrapped col', $assignedTexts, true)) {
    throw new RuntimeException('Expected wrapped off-crop table cells to be filtered before WordPress output.');
}
if (str_contains($result['text'], 'Stale wrapped-bbox table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale wrapped-bbox pdftext line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-wrapped-bbox-boundary-currentbase',
    'native_boundary' => 'Bbox-shaped supplied table rows, columns, cells, conflicts, image boxes, and table crop boxes are unwrapped before table-crop localization and WordPress table output',
    'source_truth' => [
        'upstream' => 'sddai/markerPDF delegates supplied table boundaries to tabled; tabled 0.1.4 models table/cell geometry as Bbox and ExtractPageResult bboxes/image_bboxes as Bbox lists',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells; does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'direct_table_bbox_source' => $directReview['table_bbox_source'] ?? null,
    'coordinate_status' => $coordinateReview['status'] ?? null,
    'cell_source_coordinate_source' => $gridReview['render_cells'][0]['source_coordinate_source'] ?? null,
    'cell_source_bbox' => $gridReview['render_cells'][0]['source_cell_bbox'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'wrapped_bbox_geometry_unwrapped' => ($directReview['table_bbox_source'] ?? null) === 'bbox.bbox_array'
        && ($gridReview['render_cells'][0]['source_coordinate_source'] ?? null) === 'bbox.bbox_array',
    'offcrop_wrapped_bbox_cells_filtered' => !in_array('Stale wrapped row', $assignedTexts, true)
        && !in_array('Stale wrapped col', $assignedTexts, true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale wrapped-bbox table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
