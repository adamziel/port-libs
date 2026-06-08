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

$path = sys_get_temp_dir() . '/markerpdf-wordpress-table-ocr-source-bbox-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% table OCR source bbox WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'OCR source bbox boundary review', 'bbox' => [72.0, 48.0, 470.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale source-bbox table text should be replaced.', 'bbox' => [72.0, 176.0, 470.0, 196.0]],
                ['text' => 'Reviewer note after source bbox table.', 'bbox' => [72.0, 276.0, 510.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 470.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 510.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 200.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 38.0, 200.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 96.0, 72.0]],
                    ['col_id' => 1, 'bbox' => [98.0, 0.0, 200.0, 72.0]],
                ],
            ]],
            'table_detector_cells' => [[
                ['bbox' => [0.0, 0.0, 90.0, 24.0], 'text' => null],
                ['bbox' => [100.0, 0.0, 190.0, 24.0], 'text' => null],
                ['bbox' => [0.0, 40.0, 90.0, 64.0], 'text' => null],
                ['bbox' => [100.0, 40.0, 190.0, 64.0], 'text' => null],
            ]],
            'table_ocr_text_lines' => [[
                'lines' => [
                    ['text' => 'Feature', 'source_bbox' => [0.0, 0.0, 190.0, 24.0], 'source_coordinate_space' => 'table_crop'],
                    ['text' => 'Status', 'source_bbox' => [0.0, 0.0, 190.0, 24.0], 'source_coordinate_space' => 'table_crop'],
                    ['text' => 'Images', 'source_bbox' => [0.0, 40.0, 190.0, 64.0], 'source_coordinate_space' => 'table_crop'],
                    ['text' => 'Ready', 'source_bbox' => [0.0, 40.0, 190.0, 64.0], 'source_coordinate_space' => 'table_crop'],
                ],
            ]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
            'ocr_all_pages' => true,
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$markdown = $result['text'];
$conflicts = $result['metadata']['table_ocr_grid_border_conflicts'][0] ?? [];
$assignedCells = $result['metadata']['table_assigned_cells'][0] ?? [];

if (count($conflicts) !== 4) {
    throw new RuntimeException('Expected source-bbox OCR lines to produce grid-border conflict review rows.');
}
if (($conflicts[0]['source_coordinate_source'] ?? null) !== 'source_bbox') {
    throw new RuntimeException('Expected source_bbox provenance to remain visible on the OCR conflict row.');
}
if (array_column($assignedCells, 'text') !== ['Feature', 'Status', 'Images', 'Ready']) {
    throw new RuntimeException('Expected detector-grid text assignment to preserve source order for source-bbox OCR conflicts.');
}
if (str_contains($markdown, 'Stale source-bbox table text should be replaced.')) {
    throw new RuntimeException('Expected supplied source-bbox OCR table to replace stale pdftext table text.');
}

$htmlTable = '<figure class="wp-block-table"><table><tbody>'
    . '<tr><td>Feature</td><td>Status</td></tr>'
    . '<tr><td>Images</td><td>Ready</td></tr>'
    . '</tbody></table></figure>';

echo json_encode([
    'scenario' => 'wordpress-table-ocr-source-bbox-boundary-currentbase',
    'native_boundary' => 'source_bbox-only supplied OCR text lines are treated as table-crop TextLine geometry before grid-border review',
    'upstream_scope' => 'Mirrors the supplied Surya/tabled TextLine handoff boundary without live OCR, CUDA, model loading, or raster table detection.',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>OCR Source Bbox Boundary Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => $htmlTable],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>Reviewer note after source bbox table.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'table_needs_ocr' => $result['metadata']['table_needs_ocr'] ?? [],
    'table_detect_boxes' => $result['metadata']['table_detect_boxes'] ?? false,
    'table_cell_counts' => $result['metadata']['table_cell_counts'] ?? [],
    'grid_border_conflict_count' => count($conflicts),
    'source_order_assignment_used' => ($conflicts[0]['assignment_mode'] ?? null) === 'source_order_grid_border',
    'source_bbox_geometry_used' => ($conflicts[0]['source_coordinate_source'] ?? null) === 'source_bbox',
    'preserved_detector_grid_text' => array_column($assignedCells, 'text') === ['Feature', 'Status', 'Images', 'Ready'],
    'candidate_grid_cells_for_first_ocr_line' => $conflicts[0]['candidate_cell_indexes'] ?? [],
    'first_conflict_source_bbox' => $conflicts[0]['source_bbox'] ?? null,
    'excluded_stale_pdftext_table_line' => !str_contains($markdown, 'Stale source-bbox table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $markdown,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
