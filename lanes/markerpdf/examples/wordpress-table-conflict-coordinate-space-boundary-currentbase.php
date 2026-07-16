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

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-conflict-coordinate-space-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table conflict coordinate-space boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Table conflict coordinate-space review', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale page-image conflict table text should be replaced.', 'bbox' => [72.0, 176.0, 380.0, 196.0]],
                ['text' => 'After table conflict review.', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
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
            'recognized_tables' => [[
                'table_bbox' => [72.0, 150.0, 312.0, 230.0],
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 80.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [110.0, 0.0, 240.0, 80.0]],
                ],
                'cells' => [
                    ['bbox' => [10.0, 5.0, 90.0, 24.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
                    ['bbox' => [120.0, 5.0, 230.0, 24.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
                    ['bbox' => [10.0, 45.0, 90.0, 70.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
                    ['bbox' => [120.0, 45.0, 230.0, 70.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
                ],
                'ocr_grid_border_conflicts_coordinate_space' => 'page_image',
                'ocr_grid_border_conflicts' => [[
                    'ocr_index' => 0,
                    'text' => 'Page-image conflict',
                    'bbox' => [72.0, 150.0, 312.0, 215.0],
                    'candidate_cell_indexes' => [0, 1],
                    'candidate_overlaps' => [1.0, 0.42],
                    'candidate_cell_bboxes' => [
                        [82.0, 155.0, 162.0, 174.0],
                        [192.0, 155.0, 302.0, 174.0],
                    ],
                    'assigned_cell_index' => 0,
                    'spans_grid_border' => true,
                ]],
            ]],
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
$conflict = $metadata['table_ocr_grid_border_conflicts'][0][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

if (($coordinateReview['status'] ?? null) !== 'translated_to_table_crop') {
    throw new RuntimeException('Expected field-specific OCR conflict coordinate-space metadata to trigger page-image translation.');
}
if (($coordinateReview['source_coordinate_spaces']['conflicts'] ?? null) !== 'page_image') {
    throw new RuntimeException('Expected OCR conflict source coordinate space to remain visible in review metadata.');
}
if (($coordinateReview['translated_conflict_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected exactly one OCR conflict row to be localized to table-crop coordinates.');
}
if (($conflict['source_coordinate_space'] ?? null) !== 'page_image' || ($conflict['coordinate_space'] ?? null) !== 'table_crop') {
    throw new RuntimeException('Expected WordPress OCR conflict metadata to carry source and target coordinate spaces.');
}
if (($conflict['bbox'] ?? null) !== [0.0, 0.0, 240.0, 65.0]) {
    throw new RuntimeException('Expected page-image OCR conflict bbox to be translated to table-crop coordinates.');
}
if (str_contains($result['text'], 'Stale page-image conflict table text should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-conflict-coordinate-space-boundary-currentbase',
    'native_boundary' => 'supplied OCR grid-border conflict rows may declare page-image coordinates separately from crop-local rows, columns, and cells',
    'source_truth' => [
        'upstream' => 'marker table recognition reviews OCR grid-border conflicts beside tabled crop-local row/column/cell geometry; supplied serialized bundles can preserve field-level coordinate-space metadata',
        'no_gpu_scope' => 'uses supplied rows/cells/conflict rows and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Table Conflict Coordinate-Space Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After table conflict review.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_review_status' => $coordinateReview['status'] ?? null,
    'source_coordinate_spaces' => $coordinateReview['source_coordinate_spaces'] ?? null,
    'translated_conflict_count' => $coordinateReview['translated_conflict_count'] ?? null,
    'translated_cell_count' => $coordinateReview['translated_cell_count'] ?? null,
    'localized_conflict_bbox' => $conflict['bbox'] ?? null,
    'source_conflict_bbox' => $conflict['source_bbox'] ?? null,
    'localized_candidate_bboxes' => $conflict['candidate_cell_bboxes'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'field_specific_conflict_geometry_translated' => ($coordinateReview['status'] ?? null) === 'translated_to_table_crop'
        && ($coordinateReview['translated_conflict_count'] ?? null) === 1
        && ($conflict['bbox'] ?? null) === [0.0, 0.0, 240.0, 65.0],
    'crop_local_table_cells_preserved' => ($coordinateReview['translated_cell_count'] ?? null) === 0,
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale page-image conflict table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
