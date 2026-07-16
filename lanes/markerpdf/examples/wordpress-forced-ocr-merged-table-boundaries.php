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

$path = sys_get_temp_dir() . '/markerpdf-wordpress-forced-ocr-merged-table-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% forced OCR merged table WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'OCR merged table packet', 'bbox' => [72.0, 48.0, 340.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Legacy pdftext table should be ignored after OCR.', 'bbox' => [72.0, 176.0, 430.0, 196.0]],
                ['text' => 'Post table review note.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 340.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 250.0, 230.0]],
                    ['label' => 'Table', 'bbox' => [248.0, 150.0, 430.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 360.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 40.0, 360.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 170.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [190.0, 0.0, 360.0, 80.0]],
                ],
            ]],
            'table_detector_cells' => [[
                ['bbox' => [12.0, 8.0, 160.0, 28.0], 'text' => null],
                ['bbox' => [198.0, 8.0, 344.0, 28.0], 'text' => null],
                ['bbox' => [12.0, 44.0, 160.0, 66.0], 'text' => null],
                ['bbox' => [198.0, 44.0, 344.0, 66.0], 'text' => null],
            ]],
            'table_ocr_text_lines' => [[
                ['text' => 'Segment'],
                ['text' => 'State'],
                ['text' => 'Merged OCR'],
                ['text' => 'Imported'],
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
$htmlTable = '<figure class="wp-block-table"><table><tbody>'
    . '<tr><td>Segment</td><td>State</td></tr>'
    . '<tr><td>Merged OCR</td><td>Imported</td></tr>'
    . '</tbody></table></figure>';

echo json_encode([
    'scenario' => 'wordpress-forced-ocr-merged-table-boundaries',
    'native_boundary' => 'forced OCR/table-detection pages use merged table layout boxes without requiring stale pdftext table lines',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>OCR Merged Table Packet</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => $htmlTable],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>Post table review note.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'],
    'table_counts' => $result['metadata']['table_plan']['table_counts'],
    'table_bboxes' => $result['metadata']['table_plan']['table_bboxes'],
    'table_needs_ocr' => $result['metadata']['table_needs_ocr'] ?? [],
    'table_detect_boxes' => $result['metadata']['table_detect_boxes'] ?? false,
    'table_cell_counts' => $result['metadata']['table_cell_counts'] ?? [],
    'excluded_stale_pdftext_table_lines' => !str_contains($markdown, 'Legacy pdftext table should be ignored after OCR.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $markdown,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
