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
        ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0], 'coordinate_space' => 'page_image'],
        ['row_id' => 1, 'bbox' => [72.0, 190.0, 312.0, 220.0], 'coordinate_space' => 'page_image'],
        ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 270.0], 'coordinate_space' => 'page_image'],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => [72.0, 150.0, 172.0, 230.0], 'coordinate_space' => 'page_image'],
        ['col_id' => 1, 'bbox' => [192.0, 150.0, 312.0, 230.0], 'coordinate_space' => 'page_image'],
        ['col_id' => 99, 'bbox' => [342.0, 150.0, 362.0, 230.0], 'coordinate_space' => 'page_image'],
    ],
    'cells' => [
        ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0], 'coordinate_space' => 'page_image'],
        ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1], 'coordinate_space' => 'page_image'],
        ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0], 'coordinate_space' => 'page_image'],
        ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1], 'coordinate_space' => 'page_image'],
        ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale row', 'row_ids' => [99], 'col_ids' => [0], 'coordinate_space' => 'page_image'],
        ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale column', 'row_ids' => [1], 'col_ids' => [99], 'coordinate_space' => 'page_image'],
    ],
    'ocr_grid_border_conflicts' => [[
        'ocr_index' => 0,
        'text' => 'Wide page-image OCR',
        'bbox' => [72.0, 150.0, 312.0, 215.0],
        'candidate_cell_indexes' => [0, 1],
        'candidate_cell_bboxes' => [
            [82.0, 155.0, 162.0, 170.0],
            [202.0, 155.0, 302.0, 170.0],
        ],
        'assigned_cell_index' => 0,
        'spans_grid_border' => true,
        'coordinate_space' => 'page_image',
    ]],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-record-coordinate-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% record coordinate table geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Record coordinate table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale record-coordinate table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                ['text' => 'After record coordinate geometry review.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
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
    unlink($pdfPath);
}

$metadata = $result['metadata'];
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];

if (($coordinateReview['status'] ?? null) !== 'translated_to_table_crop') {
    throw new RuntimeException('Expected per-record page-image table geometry to be translated into crop-local coordinates.');
}
if (in_array('Stale row', $assignedTexts, true) || in_array('Stale column', $assignedTexts, true)) {
    throw new RuntimeException('Expected stale off-crop page-image cells to be filtered after translation.');
}
if (str_contains($result['text'], 'Stale record-coordinate table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-record-coordinate-boundary-currentbase',
    'native_boundary' => 'per-record table recognition coordinate_space metadata is localized before tabled-style assignment and WordPress table review',
    'source_truth' => [
        'upstream' => 'marker.tables.table crops rendered page images before tabled.assignment.assign_rows_columns; serialized handoffs may carry table rows, columns, cells, and conflict rows with per-record page-image geometry',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Record Coordinate Table Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After record coordinate geometry review.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'translation' => $coordinateReview['translation'] ?? null,
    'record_coordinate_spaces' => $coordinateReview['source_record_coordinate_spaces'] ?? [],
    'translated_cell_count' => $coordinateReview['translated_cell_count'] ?? null,
    'translated_conflict_count' => $coordinateReview['translated_conflict_count'] ?? null,
    'assigned_crop_active_cell_count' => $metadata['table_assigned_crop_boundary_reviews'][0]['active_cell_count'] ?? null,
    'assigned_crop_excluded_cell_count' => $metadata['table_assigned_crop_boundary_reviews'][0]['excluded_cell_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'source_cell_bbox_preserved' => $gridReview['render_cells'][0]['source_cell_bbox'] ?? null,
    'source_coordinate_space_preserved' => $gridReview['render_cells'][0]['source_coordinate_space'] ?? null,
    'record_geometry_translated' => ($coordinateReview['status'] ?? null) === 'translated_to_table_crop',
    'offcrop_page_image_cells_filtered_from_assignment' => !in_array('Stale row', $assignedTexts, true)
        && !in_array('Stale column', $assignedTexts, true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale record-coordinate table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
