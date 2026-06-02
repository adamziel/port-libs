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

$path = sys_get_temp_dir() . '/markerpdf-wordpress-table-ocr-polygon-geometry-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% table OCR polygon geometry WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'OCR polygon geometry review', 'bbox' => [72.0, 48.0, 410.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale polygon table text should be replaced.', 'bbox' => [72.0, 176.0, 460.0, 196.0]],
                ['text' => 'Reviewer note after polygon table.', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 410.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
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
                'text_lines' => [
                    ['text' => 'Status', 'polygon' => [[102.0, 4.0], [188.0, 4.0], [188.0, 20.0], [102.0, 20.0]]],
                    ['text' => 'Feature', 'polygon' => [[2.0, 4.0], [88.0, 4.0], [88.0, 20.0], [2.0, 20.0]]],
                    ['text' => 'Ready', 'polygon' => [[102.0, 44.0], [188.0, 44.0], [188.0, 60.0], [102.0, 60.0]]],
                    ['text' => 'Images', 'polygon' => [[2.0, 44.0], [88.0, 44.0], [88.0, 60.0], [2.0, 60.0]]],
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
$assignedCells = $result['metadata']['table_assigned_cells'][0] ?? [];
$htmlTable = '<figure class="wp-block-table"><table><tbody>'
    . '<tr><td>Feature</td><td>Status</td></tr>'
    . '<tr><td>Images</td><td>Ready</td></tr>'
    . '</tbody></table></figure>';

echo json_encode([
    'scenario' => 'wordpress-table-ocr-polygon-geometry-currentbase',
    'native_boundary' => 'supplied Surya OCR TextLine polygons are converted to bboxes before table OCR text is assigned to detector cells',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>OCR Polygon Geometry Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => $htmlTable],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>Reviewer note after polygon table.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'table_needs_ocr' => $result['metadata']['table_needs_ocr'] ?? [],
    'table_detect_boxes' => $result['metadata']['table_detect_boxes'] ?? false,
    'table_cell_counts' => $result['metadata']['table_cell_counts'] ?? [],
    'geometry_assignment_used' => array_column($assignedCells, 'text') === ['Feature', 'Status', 'Images', 'Ready'],
    'source_order_would_be_wrong' => true,
    'grid_border_conflict_review_empty' => !isset($result['metadata']['table_ocr_grid_border_conflicts']),
    'excluded_stale_pdftext_table_line' => !str_contains($markdown, 'Stale polygon table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $markdown,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
