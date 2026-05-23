<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$span = static fn (string $text, array $bbox, string $font = 'Times-Roman', int $weight = 400, int $size = 11): array => [
    'text' => $text,
    'bbox' => $bbox,
    'font' => [
        'name' => $font,
        'flags' => 0,
        'weight' => $weight,
        'size' => $size,
    ],
];

$line = static fn (string $text, array $bbox, string $font = 'Times-Roman', int $weight = 400, int $size = 11): array => [
    'bbox' => $bbox,
    'spans' => [$span($text, $bbox, $font, $weight, $size)],
];

$pdftextPages = [[
    'page' => 0,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'rotation' => 0,
    'blocks' => [[
        'lines' => [
            $line('Scanned table review', [72.0, 48.0, 300.0, 68.0], 'Times-Bold', 700, 18),
            $line('Feature Status Images Needs OCR review', [72.0, 180.0, 360.0, 214.0]),
        ],
    ]],
]];

$layout = [
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Title', 'bbox' => [72.0, 48.0, 300.0, 68.0]],
        ['label' => 'Table', 'bbox' => [72.0, 180.0, 360.0, 240.0]],
    ],
];

$recognizedTable = [
    'rows' => [
        ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 30.0]],
        ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => [0.0, 0.0, 110.0, 80.0]],
        ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-forced-ocr-table-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% forced OCR table example\n%%EOF");

try {
    $converted = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        $pdftextPages,
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [$layout],
            'order_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 0, 'bbox' => [72.0, 48.0, 300.0, 68.0]],
                    ['position' => 1, 'bbox' => [72.0, 180.0, 360.0, 240.0]],
                ],
            ]],
            'recognized_tables' => [$recognizedTable],
            'table_text_lines' => [['blocks' => [
                ['bbox' => [72.0, 180.0, 360.0, 214.0], 'text' => 'Feature Status Images Needs OCR review'],
            ]]],
            'table_detector_cells' => [[
                ['bbox' => [10.0, 5.0, 100.0, 25.0], 'text' => null],
                ['bbox' => [130.0, 5.0, 230.0, 25.0], 'text' => null],
                ['bbox' => [10.0, 45.0, 100.0, 65.0], 'text' => null],
                ['bbox' => [130.0, 45.0, 230.0, 65.0], 'text' => null],
            ]],
            'table_ocr_text_lines' => [[
                ['text' => 'Feature'],
                ['text' => 'Status'],
                ['text' => 'Images'],
                ['text' => 'Needs OCR review'],
            ]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
            'ocr_all_pages' => true,
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );

    echo json_encode([
        'scenario' => 'wordpress-markerpdf-forced-ocr-table-import',
        'purpose' => 'Force table detector cells through supplied OCR text before rendering a Gutenberg-ready Markdown table.',
        'metadata' => [
            'supplied_boundaries' => $converted['metadata']['supplied_boundaries'] ?? [],
            'table_needs_ocr' => $converted['metadata']['table_needs_ocr'] ?? [],
            'table_cell_counts' => $converted['metadata']['table_cell_counts'] ?? [],
            'inserted_tables' => $converted['metadata']['inserted_tables'] ?? 0,
        ],
        'markdown' => $converted['text'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    unlink($pdfPath);
}
