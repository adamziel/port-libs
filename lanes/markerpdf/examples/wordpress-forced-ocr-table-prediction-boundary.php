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
            $line('Scanned import matrix', [72.0, 48.0, 340.0, 68.0], 'Times-Bold', 700, 18),
            $line('Stale pdftext table line should not survive.', [72.0, 178.0, 430.0, 196.0]),
            $line('Review after table.', [72.0, 276.0, 430.0, 294.0]),
        ],
    ]],
]];

$layout = [
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Title', 'bbox' => [72.0, 48.0, 340.0, 68.0]],
        ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 230.0]],
        ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
    ],
];

$recognizedTable = [
    'rows' => [
        ['row_id' => 0, 'bbox' => [0.0, 0.0, 360.0, 30.0]],
        ['row_id' => 1, 'bbox' => [0.0, 40.0, 360.0, 70.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => [0.0, 0.0, 170.0, 80.0]],
        ['col_id' => 1, 'bbox' => [190.0, 0.0, 360.0, 80.0]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-forced-ocr-table-prediction-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% forced OCR table prediction boundary fixture\n%%EOF");

try {
    $converted = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        $pdftextPages,
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [$layout],
            'recognized_tables' => [$recognizedTable],
            'table_detector_cells' => [[
                ['bbox' => [12.0, 8.0, 160.0, 28.0], 'text' => null],
                ['bbox' => [198.0, 8.0, 344.0, 28.0], 'text' => null],
                ['bbox' => [12.0, 44.0, 160.0, 66.0], 'text' => null],
                ['bbox' => [198.0, 44.0, 344.0, 66.0], 'text' => null],
            ]],
            'table_ocr_text_lines' => [[
                'text_lines' => [
                    ['text' => 'Metric'],
                    ['text' => 'State'],
                    ['text' => 'Prediction OCR'],
                    ['text' => 'Recovered'],
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
$htmlTable = '<figure class="wp-block-table"><table><tbody>'
    . '<tr><td>Metric</td><td>State</td></tr>'
    . '<tr><td>Prediction OCR</td><td>Recovered</td></tr>'
    . '</tbody></table></figure>';

echo json_encode([
    'scenario' => 'wordpress-forced-ocr-table-prediction-boundary',
    'native_boundary' => 'forced OCR table recognition unwraps upstream OCR prediction text_lines before Markdown table rendering',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Scanned Import Matrix</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => $htmlTable],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>Review after table.</p>'],
    ],
    'supplied_boundaries' => $converted['metadata']['supplied_boundaries'] ?? [],
    'table_needs_ocr' => $converted['metadata']['table_needs_ocr'] ?? [],
    'table_detect_boxes' => $converted['metadata']['table_detect_boxes'] ?? false,
    'table_cell_counts' => $converted['metadata']['table_cell_counts'] ?? [],
    'prediction_object_unwrapped' => ($converted['metadata']['table_assigned_cells'][0][2]['text'] ?? null) === 'Prediction OCR',
    'excluded_stale_pdftext_table_line' => !str_contains($markdown, 'Stale pdftext table line should not survive.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $markdown,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
