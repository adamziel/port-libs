<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$roundBbox = static fn (array $bbox): array => array_map(
    static fn (mixed $value): float => round((float) $value, 1),
    $bbox
);

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

$recognizedTable = [
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'coordinate_space' => 'page_image',
    'table_image' => [
        'left' => 72.0,
        'top' => 150.0,
        'width' => 240.0,
        'height' => 80.0,
        'coordinate_space' => 'page_image',
        'crop_width' => 240,
        'crop_height' => 80,
    ],
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
        ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale named row', 'row_ids' => [99], 'col_ids' => [0]],
        ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale named col', 'row_ids' => [1], 'col_ids' => [99]],
    ],
    'ocr_grid_border_conflicts' => [[
        'ocr_index' => 0,
        'text' => 'Wide named crop OCR',
        'bbox' => [82.0, 155.0, 302.0, 215.0],
        'candidate_cell_indexes' => [0, 1, 2],
        'candidate_cell_bboxes' => [
            [82.0, 155.0, 162.0, 170.0],
            [202.0, 155.0, 302.0, 170.0],
            [82.0, 195.0, 162.0, 215.0],
        ],
        'assigned_cell_index' => 0,
        'spans_grid_border' => true,
    ]],
];

$recognizer = new TableRecognizer();
$direct = $recognizer->formatRecognizedTables([$recognizedTable], [[]]);
$directReview = $direct['coordinate_space_reviews'][0] ?? [];
$directAssignedTexts = array_column($direct['assigned_cells'][0] ?? [], 'text');

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-nested-named-crop-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% nested named crop WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Nested Named Crop Boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale named crop table line should be replaced.', 'bbox' => [82.0, 176.0, 340.0, 196.0]],
                ['text' => 'After nested named crop table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

$nestedNamedCropUnwrapped = ($directReview['table_bbox_source'] ?? null) === 'table_image.bbox_left_top_width_height_fields'
    && ($directReview['table_bbox_source_coordinate_space'] ?? null) === 'page_image'
    && $roundBbox($directReview['table_bbox'] ?? []) === [72.0, 150.0, 312.0, 230.0]
    && ($directReview['table_crop_size'] ?? null) === ['width' => 240, 'height' => 80];
$wordpressTableRendered = str_contains($result['text'], '| Feature | Status |')
    && str_contains($result['text'], '| Images  | Ready  |');
$pageSourceCellPreserved = ($gridReview['render_cells'][0]['source_cell_bbox'] ?? null) === [82.0, 155.0, 162.0, 170.0]
    && ($gridReview['render_cells'][0]['source_coordinate_space'] ?? null) === 'page_image';
$staleExcluded = !in_array('Stale named row', $assignedTexts, true)
    && !in_array('Stale named col', $assignedTexts, true)
    && !str_contains($result['text'], 'Stale named crop table line should be replaced.');

if (!$nestedNamedCropUnwrapped || !$wordpressTableRendered || !$pageSourceCellPreserved || !$staleExcluded) {
    throw new RuntimeException('Expected nested named crop geometry to localize before WordPress table output.');
}

echo json_encode([
    'scenario' => 'wordpress-table-nested-named-crop-boundary-currentbase',
    'native_boundary' => 'nested table_image named rectangle fields are unwrapped as the table crop, then rows, columns, cells, and OCR conflict candidates are translated into table-crop geometry before table Markdown output',
    'source_truth' => [
        'marker_pdf' => 'marker/tables/table.py crops page images before tabled recognition and later formats assigned rows, columns, and cells as Markdown',
        'tabled' => 'tabled ExtractPageResult sidecars carry crop-table geometry; saved review sidecars may preserve crop geometry as named rectangle fields on table_image/table_crop wrappers',
        'no_gpu_scope' => 'uses supplied recognition geometry only; does not run Surya, tabled model inference, OCR, Python, PDFium, PIL, or external PDF tools',
    ],
    'direct_table_bbox_source' => $directReview['table_bbox_source'] ?? null,
    'direct_table_bbox_source_coordinate_space' => $directReview['table_bbox_source_coordinate_space'] ?? null,
    'direct_table_bbox' => $roundBbox($directReview['table_bbox'] ?? []),
    'direct_table_crop_size' => $directReview['table_crop_size'] ?? null,
    'direct_assigned_table_texts' => $directAssignedTexts,
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_review_status' => $coordinateReview['status'] ?? null,
    'render_source_coordinate_source' => $gridReview['render_cells'][0]['source_coordinate_source'] ?? null,
    'render_source_coordinate_space' => $gridReview['render_cells'][0]['source_coordinate_space'] ?? null,
    'render_source_cell_bbox' => $gridReview['render_cells'][0]['source_cell_bbox'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'nested_named_crop_unwrapped' => $nestedNamedCropUnwrapped,
    'wordpress_table_rendered' => $wordpressTableRendered,
    'stale_nested_named_cells_filtered' => $staleExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
