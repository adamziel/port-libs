<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$normalizedPageBbox = static fn (array $bbox): array => [
    round(((float) $bbox[0] / 612.0) * 1000.0, 6),
    round(((float) $bbox[1] / 792.0) * 1000.0, 6),
    round(((float) $bbox[2] / 612.0) * 1000.0, 6),
    round(((float) $bbox[3] / 792.0) * 1000.0, 6),
];

$normalizedTableBbox = static fn (array $bbox): array => [
    round(((float) $bbox[0] / 240.0) * 1000.0, 6),
    round(((float) $bbox[1] / 80.0) * 1000.0, 6),
    round(((float) $bbox[2] / 240.0) * 1000.0, 6),
    round(((float) $bbox[3] / 80.0) * 1000.0, 6),
];

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
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'rows' => [
        ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0], 'coordinate_space' => 'page_image'],
        ['row_id' => 1, 'bbox' => $normalizedPageBbox([72.0, 190.0, 312.0, 220.0]), 'coordinate_space' => 'normalized_page_image'],
        ['row_id' => 99, 'bbox' => $normalizedTableBbox([0.0, 100.0, 240.0, 118.0]), 'coordinate_space' => 'normalized_table'],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => $normalizedTableBbox([0.0, 0.0, 100.0, 80.0]), 'coordinate_space' => 'normalized_table'],
        ['col_id' => 1, 'bbox' => [192.0, 150.0, 312.0, 230.0], 'coordinate_space' => 'page_image'],
        ['col_id' => 99, 'bbox' => $normalizedPageBbox([340.0, 150.0, 360.0, 230.0]), 'coordinate_space' => 'normalized_page_image'],
    ],
    'cells' => [
        ['bbox' => $normalizedTableBbox([10.0, 5.0, 90.0, 20.0]), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0], 'coordinate_space' => 'normalized_table'],
        ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1], 'coordinate_space' => 'page_image'],
        ['bbox' => $normalizedPageBbox([82.0, 195.0, 162.0, 215.0]), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0], 'coordinate_space' => 'normalized_page_image'],
        ['bbox' => [130.0, 45.0, 230.0, 65.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale page row', 'row_ids' => [99], 'col_ids' => [0], 'coordinate_space' => 'page_image'],
        ['bbox' => $normalizedTableBbox([288.0, 45.0, 310.0, 65.0]), 'text' => 'Stale normalized col', 'row_ids' => [1], 'col_ids' => [99], 'coordinate_space' => 'normalized_table'],
    ],
    'ocr_grid_border_conflicts' => [
        [
            'ocr_index' => 0,
            'text' => 'Wide normalized page OCR',
            'bbox' => $normalizedPageBbox([82.0, 155.0, 302.0, 215.0]),
            'candidate_cell_indexes' => [0, 1, 2],
            'candidate_cell_bboxes' => [
                $normalizedPageBbox([82.0, 155.0, 162.0, 170.0]),
                $normalizedPageBbox([202.0, 155.0, 302.0, 170.0]),
                $normalizedPageBbox([82.0, 195.0, 162.0, 215.0]),
            ],
            'assigned_cell_index' => 0,
            'spans_grid_border' => true,
            'coordinate_space' => 'normalized_page_image',
        ],
        [
            'ocr_index' => 1,
            'text' => 'Wide normalized crop OCR',
            'bbox' => $normalizedTableBbox([10.0, 5.0, 230.0, 20.0]),
            'candidate_cell_indexes' => [0, 1],
            'candidate_cell_bboxes' => [
                $normalizedTableBbox([10.0, 5.0, 90.0, 20.0]),
                $normalizedTableBbox([130.0, 5.0, 230.0, 20.0]),
            ],
            'assigned_cell_index' => 1,
            'spans_grid_border' => true,
            'coordinate_space' => 'normalized_table',
        ],
        [
            'ocr_index' => 2,
            'text' => 'Wide page OCR',
            'bbox' => [82.0, 195.0, 302.0, 215.0],
            'candidate_cell_indexes' => [2, 3],
            'candidate_cell_bboxes' => [
                [82.0, 195.0, 162.0, 215.0],
                [202.0, 195.0, 302.0, 215.0],
            ],
            'assigned_cell_index' => 2,
            'spans_grid_border' => true,
            'coordinate_space' => 'page_image',
        ],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-mixed-coordinate-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% mixed coordinate table geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Mixed coordinate table geometry review', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale mixed-coordinate table line should be replaced.', 'bbox' => [82.0, 176.0, 300.0, 196.0]],
                ['text' => 'After mixed coordinate table.', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
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
    if (is_file($pdfPath)) {
        unlink($pdfPath);
    }
}

$metadata = $result['metadata'];
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$recordSpaces = $coordinateReview['source_record_coordinate_spaces']['conflicts'] ?? [];
$mixedCountsPreserved = (($coordinateReview['translated_conflict_count'] ?? null) === 2)
    && (($coordinateReview['normalized_conflict_count'] ?? null) === 2)
    && ($recordSpaces === ['normalized_page_image' => 1, 'normalized_table' => 1, 'page_image' => 1]);
$staleCellsExcluded = !in_array('Stale page row', $assignedTexts, true)
    && !in_array('Stale normalized col', $assignedTexts, true);
$stalePdftextExcluded = !str_contains($result['text'], 'Stale mixed-coordinate table line should be replaced.');

if (!$mixedCountsPreserved) {
    throw new RuntimeException('Expected mixed OCR conflict coordinate-space counters to accumulate across record spaces.');
}
if (!$staleCellsExcluded || !$stalePdftextExcluded) {
    throw new RuntimeException('Expected mixed-coordinate table geometry to exclude stale table cells and pdftext lines.');
}

echo json_encode([
    'scenario' => 'wordpress-table-mixed-coordinate-boundary-currentbase',
    'native_boundary' => 'mixed per-record table OCR conflict coordinate spaces accumulate review counts while localizing to table-crop geometry',
    'source_truth' => [
        'upstream' => 'marker.tables.table crops rendered page images before tabled.assignment.assign_rows_columns; tabled consumes crop-local rows, columns, SpanTableCell bboxes, and OCR conflict bboxes',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells and supplied OCR conflict rows; does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Mixed Coordinate Table Geometry Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After mixed coordinate table.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_status' => $coordinateReview['status'] ?? null,
    'table_crop_size' => $coordinateReview['table_crop_size'] ?? null,
    'translation' => $coordinateReview['translation'] ?? null,
    'source_conflict_coordinate_spaces' => $recordSpaces,
    'translated_conflict_count' => $coordinateReview['translated_conflict_count'] ?? null,
    'normalized_conflict_count' => $coordinateReview['normalized_conflict_count'] ?? null,
    'assigned_crop_active_cell_count' => $metadata['table_assigned_crop_boundary_reviews'][0]['active_cell_count'] ?? null,
    'assigned_crop_excluded_cell_count' => $metadata['table_assigned_crop_boundary_reviews'][0]['excluded_cell_count'] ?? null,
    'excluded_band_count' => $gridReview['geometry_boundary_review']['excluded_band_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'mixed_conflict_counts_preserved' => $mixedCountsPreserved,
    'offcrop_mixed_coordinate_cells_filtered' => $staleCellsExcluded,
    'excluded_stale_pdftext_table_line' => $stalePdftextExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
