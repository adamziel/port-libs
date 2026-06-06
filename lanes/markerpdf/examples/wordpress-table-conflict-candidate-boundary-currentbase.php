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

$normalizedPageBbox = static function (array $bbox): array {
    return [
        round(((float) $bbox[0] / 612.0) * 1000.0, 6),
        round(((float) $bbox[1] / 792.0) * 1000.0, 6),
        round(((float) $bbox[2] / 612.0) * 1000.0, 6),
        round(((float) $bbox[3] / 792.0) * 1000.0, 6),
    ];
};

$normalizedTableBbox = static function (array $bbox): array {
    return [
        round(((float) $bbox[0] / 240.0) * 1000.0, 6),
        round(((float) $bbox[1] / 80.0) * 1000.0, 6),
        round(((float) $bbox[2] / 240.0) * 1000.0, 6),
        round(((float) $bbox[3] / 80.0) * 1000.0, 6),
    ];
};

$recognizedTable = [
    'table_bbox' => [72.0, 150.0, 312.0, 230.0],
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'rows' => [
        ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 30.0]],
        ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
        ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
    ],
    'cells' => [
        ['bbox' => [10.0, 5.0, 90.0, 24.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['bbox' => [130.0, 5.0, 230.0, 24.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox' => [130.0, 45.0, 230.0, 65.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
    ],
    'ocr_grid_border_conflicts' => [[
        'ocr_index' => 0,
        'text' => 'Mixed candidate OCR',
        'bbox' => [0.0, 0.0, 240.0, 65.0],
        'coordinate_space' => 'table_crop',
        'candidate_cell_indexes' => [0, 1, 2, 3],
        'candidate_cell_bboxes' => [
            ['bbox' => [82.0, 155.0, 162.0, 174.0], 'coordinate_space' => 'page_image'],
            ['bbox' => [130.0, 5.0, 230.0, 24.0], 'coordinate_space' => 'table_crop'],
            ['bbox' => $normalizedPageBbox([82.0, 195.0, 162.0, 215.0]), 'coordinate_space' => 'normalized_page_image'],
            ['bbox' => $normalizedTableBbox([130.0, 45.0, 230.0, 65.0]), 'coordinate_space' => 'normalized_table'],
        ],
        'assigned_cell_index' => 0,
        'spans_grid_border' => true,
    ]],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-table-conflict-candidate-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table conflict candidate geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Candidate table geometry review', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale candidate table line should be replaced.', 'bbox' => [72.0, 176.0, 380.0, 196.0]],
                ['text' => 'After candidate geometry review.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
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
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$conflictReview = $metadata['table_ocr_grid_border_conflicts'][0][0] ?? [];
$candidateGridCells = $conflictReview['candidate_grid_cells'] ?? [];

if (($coordinateReview['status'] ?? null) !== 'translated_and_normalized_to_table_crop') {
    throw new RuntimeException('Expected mixed candidate OCR conflict bboxes to be localized to table-crop coordinates.');
}
if (($conflictReview['source_candidate_coordinate_spaces'] ?? []) !== ['page_image', 'table_crop', 'normalized_page_image', 'normalized_table']) {
    throw new RuntimeException('Expected per-candidate source coordinate spaces to be preserved in conflict review metadata.');
}
if (str_contains($result['text'], 'Stale candidate table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-conflict-candidate-boundary-currentbase',
    'native_boundary' => 'ocr_grid_border_conflicts candidate_cell_bboxes carry per-candidate coordinate spaces and are localized before WordPress grid-border review',
    'source_truth' => [
        'upstream' => 'marker.tables.table crops rendered page images before tabled.assignment.assign_rows_columns; tabled review records keep detector/OCR conflict candidates as table-crop grid-cell evidence',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells/conflicts; does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_status' => $coordinateReview['status'] ?? null,
    'translated_conflict_count' => $coordinateReview['translated_conflict_count'] ?? null,
    'normalized_conflict_count' => $coordinateReview['normalized_conflict_count'] ?? null,
    'source_record_coordinate_spaces' => $coordinateReview['source_record_coordinate_spaces']['conflicts'] ?? [],
    'source_candidate_coordinate_spaces' => $conflictReview['source_candidate_coordinate_spaces'] ?? [],
    'source_candidate_coordinate_sources' => $conflictReview['source_candidate_coordinate_sources'] ?? [],
    'candidate_cell_bboxes' => $conflictReview['candidate_cell_bboxes'] ?? [],
    'candidate_grid_cell_bboxes' => array_map(
        static fn (array $cell): array => $cell['bbox'] ?? [],
        $candidateGridCells
    ),
    'candidate_grid_axis' => $conflictReview['grid_border_axis'] ?? null,
    'candidate_row_ids' => $conflictReview['candidate_row_ids'] ?? [],
    'candidate_col_ids' => $conflictReview['candidate_col_ids'] ?? [],
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale candidate table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
