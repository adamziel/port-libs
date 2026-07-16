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

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-polygon-stale-bbox-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% OCR polygon stale bbox boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'OCR stale bbox polygon review', 'bbox' => [72.0, 48.0, 460.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale bbox table text should be replaced.', 'bbox' => [72.0, 176.0, 470.0, 196.0]],
                ['text' => 'Reviewer note after stale bbox table.', 'bbox' => [72.0, 276.0, 510.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 460.0, 68.0]],
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
                'text_lines' => [
                    [
                        'text' => 'Feature',
                        'bbox' => [104.0, 5.0, 188.0, 20.0],
                        'polygon' => [[2.0, 4.0], [88.0, 4.0], [88.0, 20.0], [2.0, 20.0]],
                    ],
                    [
                        'text' => 'Status',
                        'bbox' => [4.0, 5.0, 88.0, 20.0],
                        'polygon' => [[102.0, 4.0], [188.0, 4.0], [188.0, 20.0], [102.0, 20.0]],
                    ],
                    [
                        'text' => 'Images',
                        'bbox' => [104.0, 45.0, 188.0, 60.0],
                        'polygon' => [[2.0, 44.0], [88.0, 44.0], [88.0, 60.0], [2.0, 60.0]],
                    ],
                    [
                        'text' => 'Ready',
                        'bbox' => [4.0, 45.0, 88.0, 60.0],
                        'polygon' => [[102.0, 44.0], [188.0, 44.0], [188.0, 60.0], [102.0, 60.0]],
                    ],
                ],
            ]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
            'ocr_all_pages' => true,
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($pdfPath);
}

$assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');

echo json_encode([
    'scenario' => 'wordpress-table-ocr-polygon-stale-bbox-boundary-currentbase',
    'native_boundary' => 'serialized Surya OCR TextLine polygon geometry wins over stale bbox values before WordPress table rendering',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>OCR Stale Bbox Polygon Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>Reviewer note after stale bbox table.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'assigned_texts' => $assignedTexts,
    'polygon_assignment_preserved' => $assignedTexts === ['Feature', 'Status', 'Images', 'Ready'],
    'stale_bbox_assignment_excluded' => $assignedTexts !== ['Status', 'Feature', 'Ready', 'Images'],
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale bbox table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
