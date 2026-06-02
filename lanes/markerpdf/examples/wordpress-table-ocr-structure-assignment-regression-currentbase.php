<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$line = static fn (string $text, array $bbox, string $font = 'Times-Roman', int $weight = 400, int $size = 12): array => [
    'bbox' => $bbox,
    'spans' => [[
        'text' => $text,
        'bbox' => $bbox,
        'font' => [
            'name' => $font,
            'flags' => 0,
            'weight' => $weight,
            'size' => $size,
        ],
    ]],
];

$pdftextPages = [[
    'page' => 0,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'rotation' => 0,
    'blocks' => [[
        'lines' => [
            $line('OCR structure assignment matrix', [72.0, 48.0, 440.0, 68.0], 'Times-Bold', 700, 18),
            $line('Stale OCR structure table text should be replaced.', [72.0, 178.0, 460.0, 196.0]),
            $line('After OCR structure table.', [72.0, 276.0, 430.0, 294.0]),
        ],
    ]],
]];

$layout = [
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Title', 'bbox' => [72.0, 48.0, 440.0, 68.0]],
        ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 230.0]],
        ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
    ],
];

$recognizedTable = [
    'rows' => [
        ['row_id' => 0, 'bbox' => [0.0, 0.0, 200.0, 30.0]],
        ['row_id' => 1, 'bbox' => [0.0, 38.0, 200.0, 70.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => [0.0, 0.0, 96.0, 72.0]],
        ['col_id' => 1, 'bbox' => [98.0, 0.0, 200.0, 72.0]],
    ],
    'cells' => [
        ['bbox' => [100.0, 0.0, 190.0, 24.0], 'text' => null],
        ['bbox' => [0.0, 0.0, 90.0, 24.0], 'text' => null],
        ['bbox' => [100.0, 40.0, 190.0, 64.0], 'text' => null],
        ['bbox' => [0.0, 40.0, 90.0, 64.0], 'text' => null],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-ocr-structure-assignment-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table OCR structure assignment regression fixture\n%%EOF");

try {
    $converted = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        $pdftextPages,
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [$layout],
            'recognized_tables' => [$recognizedTable],
            'table_detector_cells' => [[
                ['bbox' => [0.0, 0.0, 90.0, 24.0], 'text' => null],
                ['bbox' => [100.0, 0.0, 190.0, 24.0], 'text' => null],
                ['bbox' => [0.0, 40.0, 90.0, 64.0], 'text' => null],
                ['bbox' => [100.0, 40.0, 190.0, 64.0], 'text' => null],
            ]],
            'table_ocr_text_lines' => [[
                'text_lines' => [
                    ['text' => 'Feature', 'bbox' => [2.0, 4.0, 88.0, 20.0]],
                    ['text' => 'Status', 'bbox' => [102.0, 4.0, 188.0, 20.0]],
                    ['text' => 'Imported', 'bbox' => [2.0, 44.0, 88.0, 60.0]],
                    ['text' => 'Ready', 'bbox' => [102.0, 44.0, 188.0, 60.0]],
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

$markdown = $converted['text'];
$assignedCells = $converted['metadata']['table_assigned_cells'][0] ?? [];
$assignedTextOrder = array_column($assignedCells, 'text');
$htmlTable = '<figure class="wp-block-table"><table><tbody>'
    . '<tr><td>Feature</td><td>Status</td></tr>'
    . '<tr><td>Imported</td><td>Ready</td></tr>'
    . '</tbody></table></figure>';

echo json_encode([
    'scenario' => 'wordpress-table-ocr-structure-assignment-regression-currentbase',
    'native_boundary' => 'forced OCR detector-cell text is preserved by geometry when supplied table-recognition structure cells are blank and reordered',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>OCR Structure Assignment Matrix</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => $htmlTable],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After OCR structure table.</p>'],
    ],
    'supplied_boundaries' => $converted['metadata']['supplied_boundaries'] ?? [],
    'table_needs_ocr' => $converted['metadata']['table_needs_ocr'] ?? [],
    'table_detect_boxes' => $converted['metadata']['table_detect_boxes'] ?? false,
    'table_cell_counts' => $converted['metadata']['table_cell_counts'] ?? [],
    'structure_cell_text_order' => $assignedTextOrder,
    'structure_cells_reordered' => $assignedTextOrder === ['Status', 'Feature', 'Ready', 'Imported'],
    'rendered_table_order_preserved' => str_contains($markdown, '| Feature  | Status |')
        && str_contains($markdown, '| Imported | Ready  |'),
    'excluded_stale_pdftext_table_line' => !str_contains($markdown, 'Stale OCR structure table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $markdown,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
