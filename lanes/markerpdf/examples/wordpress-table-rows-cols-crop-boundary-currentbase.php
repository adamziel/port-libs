<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

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

$recognizedTable = [
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'coordinate_space' => 'page_image',
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'rows_cols' => [
        'bbox' => [72.0, 150.0, 312.0, 230.0],
        'bbox_coordinate_space' => 'page_image',
        'coordinate_space' => 'page_image',
        'rows' => [
            ['row_id' => 10, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
            ['row_id' => 11, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
            ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 270.0]],
        ],
        'cols' => [
            ['col_id' => 20, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
            ['col_id' => 21, 'bbox' => [192.0, 150.0, 312.0, 230.0]],
            ['col_id' => 99, 'bbox' => [342.0, 150.0, 362.0, 230.0]],
        ],
    ],
    'cells' => [
        ['text' => 'Feature', 'bbox' => [82.0, 155.0, 162.0, 170.0], 'row_ids' => [10], 'col_ids' => [20]],
        ['text' => 'Status', 'bbox' => [202.0, 155.0, 302.0, 170.0], 'row_ids' => [10], 'col_ids' => [21]],
        ['text' => 'Images', 'bbox' => [82.0, 195.0, 162.0, 215.0], 'row_ids' => [11], 'col_ids' => [20]],
        ['text' => 'Ready', 'bbox' => [202.0, 195.0, 302.0, 215.0], 'row_ids' => [11], 'col_ids' => [21]],
        ['text' => 'Ghost row', 'bbox' => [82.0, 250.0, 162.0, 268.0], 'row_ids' => [99], 'col_ids' => [20]],
        ['text' => 'Ghost col', 'bbox' => [350.0, 195.0, 382.0, 215.0], 'row_ids' => [11], 'col_ids' => [99]],
    ],
];

$recognizer = new TableRecognizer();
$direct = $recognizer->formatRecognizedTables([$recognizedTable], [['image_bbox' => [0.0, 0.0, 612.0, 792.0]]]);
$directReview = $direct['coordinate_space_reviews'][0] ?? [];
$directBandReview = $direct['assigned_band_boundary_reviews'][0] ?? [];
$directAssignedTexts = array_column($direct['assigned_cells'][0] ?? [], 'text');
$directRowsColsCropPreferred = ($directReview['table_bbox_source'] ?? null) === 'rows_cols.bbox'
    && ($directReview['table_bbox'] ?? null) === [72.0, 150.0, 312.0, 230.0]
    && ($directReview['table_crop_size'] ?? null) === ['width' => 240, 'height' => 80]
    && ($directBandReview['active_row_ids'] ?? null) === [10, 11]
    && ($directBandReview['active_col_ids'] ?? null) === [20, 21]
    && $directAssignedTexts === ['Feature', 'Status', 'Images', 'Ready'];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-rows-cols-crop-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% rows_cols crop boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Rows cols crop boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale rows-cols crop table line should be replaced.', 'bbox' => [72.0, 176.0, 380.0, 196.0]],
                ['text' => 'After rows cols crop table.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
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
$wordpressTableRendered = str_contains($result['text'], '| Feature | Status |')
    && str_contains($result['text'], '| Images  | Ready  |');
$stalePdfTextExcluded = !str_contains($result['text'], 'Stale rows-cols crop table line should be replaced.');

if (!$directRowsColsCropPreferred || !$wordpressTableRendered || !$stalePdfTextExcluded) {
    throw new RuntimeException('Expected rows_cols crop metadata to override stale wrapper bbox and keep WordPress table output bounded.');
}

echo json_encode([
    'scenario' => 'wordpress-table-rows-cols-crop-boundary-currentbase',
    'native_boundary' => 'single-table supplied rows_cols TableResult crop metadata is preferred before generic wrapper bbox fallback',
    'source_truth' => [
        'tabled' => 'ExtractPageResult serializes TableResult rows/cols under rows_cols and emits per-table bboxes alongside cells',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'direct_table_bbox_source' => $directReview['table_bbox_source'] ?? null,
    'direct_table_bbox' => $directReview['table_bbox'] ?? null,
    'direct_table_crop_size' => $directReview['table_crop_size'] ?? null,
    'direct_active_row_ids' => $directBandReview['active_row_ids'] ?? [],
    'direct_active_col_ids' => $directBandReview['active_col_ids'] ?? [],
    'wordpress_coordinate_status' => $metadata['table_coordinate_space_reviews'][0]['status'] ?? null,
    'wordpress_table_rendered' => $wordpressTableRendered,
    'stale_pdftext_table_line_excluded' => $stalePdfTextExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
