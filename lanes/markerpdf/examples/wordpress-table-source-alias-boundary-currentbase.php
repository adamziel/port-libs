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

$recognizedTable = [
    'table_bbox' => [72.0, 150.0, 312.0, 230.0],
    'rows' => [
        ['row_id' => 0, 'source_rect' => [72.0, 150.0, 312.0, 182.0], 'source_coordinate_space' => 'page_image'],
        ['row_id' => 1, 'source_bounds' => [72.0, 190.0, 312.0, 220.0], 'source_coordinate_space' => 'page_image'],
        ['row_id' => 99, 'original_rect' => [72.0, 250.0, 312.0, 270.0], 'source_coordinate_space' => 'page_image'],
    ],
    'cols' => [
        ['col_id' => 0, 'source_box' => [72.0, 150.0, 172.0, 230.0], 'source_coordinate_space' => 'page_image'],
        ['col_id' => 1, 'source_bounding_box' => [192.0, 150.0, 312.0, 230.0], 'source_coordinate_space' => 'page_image'],
        ['col_id' => 99, 'original_box' => [342.0, 150.0, 362.0, 230.0], 'source_coordinate_space' => 'page_image'],
    ],
    'cells' => [
        ['original_bbox' => [82.0, 155.0, 162.0, 170.0], 'source_coordinate_space' => 'page_image', 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['source_rectangle' => [202.0, 155.0, 302.0, 170.0], 'source_coordinate_space' => 'page_image', 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['original_bounds' => [82.0, 195.0, 162.0, 215.0], 'source_coordinate_space' => 'page_image', 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['original_bounding_box' => [202.0, 195.0, 302.0, 215.0], 'source_coordinate_space' => 'page_image', 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['source_rect' => [82.0, 250.0, 162.0, 268.0], 'source_coordinate_space' => 'page_image', 'text' => 'Stale source row', 'row_ids' => [99], 'col_ids' => [0]],
        ['original_box' => [360.0, 195.0, 382.0, 215.0], 'source_coordinate_space' => 'page_image', 'text' => 'Stale source col', 'row_ids' => [1], 'col_ids' => [99]],
    ],
    'ocr_grid_border_conflicts' => [[
        'ocr_index' => 0,
        'text' => 'Wide source alias OCR',
        'source_bounding_box' => [82.0, 155.0, 302.0, 215.0],
        'source_coordinate_space' => 'page_image',
        'candidate_cell_indexes' => [0, 1, 2],
        'candidate_cell_bboxes' => [
            ['original_bbox' => [82.0, 155.0, 162.0, 170.0]],
            ['source_rect' => [202.0, 155.0, 302.0, 170.0]],
            ['source_bounds' => [82.0, 195.0, 162.0, 215.0]],
        ],
        'assigned_cell_index' => 0,
        'spans_grid_border' => true,
    ]],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-source-alias-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table source alias boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Source alias table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale source-alias table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                ['text' => 'After source alias table review.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
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
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

if (($gridReview['render_cells'][0]['source_coordinate_source'] ?? null) !== 'original_bbox') {
    throw new RuntimeException('Expected source-alias-only table cells to preserve alias geometry provenance.');
}
if (in_array('Stale source row', $assignedTexts, true) || in_array('Stale source col', $assignedTexts, true)) {
    throw new RuntimeException('Expected source-alias off-crop table cells to be filtered before WordPress output.');
}
if (str_contains($result['text'], 'Stale source-alias table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale source-alias pdftext line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-source-alias-boundary-currentbase',
    'native_boundary' => 'source/original rectangle aliases in supplied table rows, columns, cells, and conflict candidate bboxes are localized to table-crop geometry before WordPress table output',
    'source_truth' => [
        'upstream' => 'marker tables use supplied table crops and assignment boundaries; saved review adapters may replay original geometry under source/original rectangle aliases',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells; does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_status' => $coordinateReview['status'] ?? null,
    'cell_source_coordinate_source' => $gridReview['render_cells'][0]['source_coordinate_source'] ?? null,
    'cell_source_bbox' => $gridReview['render_cells'][0]['source_cell_bbox'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'source_alias_geometry_preserved' => ($gridReview['render_cells'][0]['source_coordinate_source'] ?? null) === 'original_bbox',
    'offcrop_source_alias_cells_filtered' => !in_array('Stale source row', $assignedTexts, true)
        && !in_array('Stale source col', $assignedTexts, true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale source-alias table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
