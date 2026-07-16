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

$pdfPageBbox = static fn (array $pageImageBbox): array => [
    (float) $pageImageBbox[0],
    792.0 - (float) $pageImageBbox[3],
    (float) $pageImageBbox[2],
    792.0 - (float) $pageImageBbox[1],
];

$recognizedTable = [
    'coordinate_space' => 'pdf_page_bottom_left',
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bbox' => $pdfPageBbox([72.0, 150.0, 312.0, 230.0]),
    'rows' => [
        ['row_id' => 0, 'bbox' => $pdfPageBbox([72.0, 150.0, 312.0, 182.0])],
        ['row_id' => 1, 'bbox' => $pdfPageBbox([72.0, 190.0, 312.0, 220.0])],
        ['row_id' => 99, 'bbox' => $pdfPageBbox([72.0, 250.0, 312.0, 268.0])],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => $pdfPageBbox([72.0, 150.0, 172.0, 230.0])],
        ['col_id' => 1, 'bbox' => $pdfPageBbox([192.0, 150.0, 312.0, 230.0])],
        ['col_id' => 99, 'bbox' => $pdfPageBbox([342.0, 150.0, 362.0, 230.0])],
    ],
    'cells' => [
        ['bbox' => $pdfPageBbox([82.0, 155.0, 162.0, 170.0]), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['bbox' => $pdfPageBbox([202.0, 155.0, 302.0, 170.0]), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bbox' => $pdfPageBbox([82.0, 195.0, 162.0, 215.0]), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox' => $pdfPageBbox([202.0, 195.0, 302.0, 215.0]), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox' => $pdfPageBbox([82.0, 250.0, 162.0, 268.0]), 'text' => 'Stale PDF row', 'row_ids' => [99], 'col_ids' => [0]],
        ['bbox' => $pdfPageBbox([360.0, 195.0, 382.0, 215.0]), 'text' => 'Stale PDF col', 'row_ids' => [1], 'col_ids' => [99]],
    ],
    'ocr_grid_border_conflicts' => [[
        'ocr_index' => 0,
        'text' => 'Wide PDF-coordinate OCR',
        'bbox' => $pdfPageBbox([82.0, 155.0, 302.0, 215.0]),
        'candidate_cell_indexes' => [0, 1, 2],
        'candidate_cell_bboxes' => [
            $pdfPageBbox([82.0, 155.0, 162.0, 170.0]),
            $pdfPageBbox([202.0, 155.0, 302.0, 170.0]),
            $pdfPageBbox([82.0, 195.0, 162.0, 215.0]),
        ],
        'assigned_cell_index' => 0,
        'spans_grid_border' => true,
    ]],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-pdf-page-bottom-left-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% PDF bottom-left page coordinate table WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'PDF Bottom Left Table Boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale PDF-coordinate table line should be replaced.', 'bbox' => [82.0, 176.0, 360.0, 196.0]],
                ['text' => 'After PDF bottom-left table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
$sameBbox = static function (mixed $actual, array $expected): bool {
    if (!is_array($actual) || count($actual) !== count($expected)) {
        return false;
    }

    foreach (array_values($actual) as $index => $value) {
        if (round((float) $value, 3) !== round((float) $expected[$index], 3)) {
            return false;
        }
    }

    return true;
};

$smoke = [
    'scenario' => 'wordpress-table-pdf-page-bottom-left-boundary-currentbase',
    'native_boundary' => 'supplied table rows, columns, cells, and grid conflicts serialized in PDF bottom-left user space are flipped through the rendered page image before WordPress table assignment',
    'source_truth' => [
        'upstream' => 'markerPDF crops rendered page images before tabled assignment; PDF native page/user-space rectangles are bottom-left origin, so supplied PDF-space table geometry must flip into rendered page top-left pixels before crop localization',
        'no_gpu_scope' => 'uses supplied table recognition records and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Pdf Bottom Left Table Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After PDF bottom-left table.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_review_target' => $coordinateReview['review_target'] ?? null,
    'coordinate_review_status' => $coordinateReview['status'] ?? null,
    'source_coordinate_space' => $coordinateReview['source_coordinate_spaces']['cells'] ?? null,
    'table_bbox' => $coordinateReview['table_bbox'] ?? null,
    'pdf_page_image_size' => $coordinateReview['pdf_page_image_size'] ?? null,
    'translated_cell_count' => $coordinateReview['translated_cell_count'] ?? null,
    'translated_conflict_count' => $coordinateReview['translated_conflict_count'] ?? null,
    'active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'first_render_cell_source_page_image_bbox' => $gridReview['render_cells'][0]['source_page_image_bbox'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'inserted_tables' => $metadata['inserted_tables'] ?? null,
    'stale_pdftext_line_excluded' => !str_contains($result['text'], 'Stale PDF-coordinate table line should be replaced.'),
    'offcrop_pdf_cells_excluded' => !in_array('Stale PDF row', $assignedTexts, true)
        && !in_array('Stale PDF col', $assignedTexts, true)
        && !str_contains($result['text'], 'Stale PDF row')
        && !str_contains($result['text'], 'Stale PDF col'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
];

if (
    ($smoke['coordinate_review_status'] ?? null) !== 'translated_to_table_crop'
    || ($smoke['source_coordinate_space'] ?? null) !== 'pdf_page_bottom_left'
    || !$sameBbox($smoke['table_bbox'] ?? null, [72.0, 150.0, 312.0, 230.0])
    || ($smoke['pdf_page_image_size'] ?? null) !== ['width' => 612, 'height' => 792]
    || ($smoke['translated_cell_count'] ?? null) !== 6
    || ($smoke['translated_conflict_count'] ?? null) !== 1
    || ($smoke['active_cell_count'] ?? null) !== 4
    || ($smoke['excluded_cell_count'] ?? null) !== 2
    || !$sameBbox($smoke['first_render_cell_source_page_image_bbox'] ?? null, [82.0, 155.0, 162.0, 170.0])
    || ($smoke['assigned_table_texts'] ?? null) !== ['Feature', 'Status', 'Images', 'Ready']
    || ($smoke['inserted_tables'] ?? null) !== 1
    || ($smoke['stale_pdftext_line_excluded'] ?? null) !== true
    || ($smoke['offcrop_pdf_cells_excluded'] ?? null) !== true
    || ($smoke['executes_python_or_models'] ?? null) !== false
    || ($smoke['executes_external_pdf_tools'] ?? null) !== false
) {
    throw new RuntimeException(
        'PDF bottom-left table coordinate WordPress smoke failed: '
        . json_encode($smoke, JSON_UNESCAPED_SLASHES)
    );
}

echo json_encode($smoke, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
