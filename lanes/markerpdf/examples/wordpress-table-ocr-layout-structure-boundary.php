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

$pdfTextChars = static function (string $text, float $x, float $y, float $charWidth = 8.0, float $gap = 1.0): array {
    $chars = [];
    $cursor = $x;
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
        $chars[] = [
            'char' => $char,
            'bbox' => [$cursor, $y, $cursor + $charWidth, $y + 14.0],
        ];
        $cursor += $charWidth + $gap;
    }

    return $chars;
};

$pdfTextLine = static function (array $charGroups): array {
    $chars = [];
    foreach ($charGroups as $group) {
        array_push($chars, ...$group);
    }
    $boxes = array_column($chars, 'bbox');

    return [
        'bbox' => [
            min(array_column($boxes, 0)),
            min(array_column($boxes, 1)),
            max(array_column($boxes, 2)),
            max(array_column($boxes, 3)),
        ],
        'spans' => [[
            'chars' => $chars,
        ]],
    ];
};

$pdftextPages = [[
    'page' => 0,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'rotation' => 0,
    'blocks' => [[
        'lines' => [
            $line('Layout table import', [72.0, 48.0, 340.0, 68.0], 'Times-Bold', 700, 18),
            $line('Legacy table text should be replaced.', [72.0, 178.0, 430.0, 196.0]),
            $line('After structured table.', [72.0, 276.0, 430.0, 294.0]),
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
        ['row_id' => 0, 'bbox' => [0.0, 0.0, 358.0, 32.0]],
        ['row_id' => 1, 'bbox' => [0.0, 38.0, 358.0, 72.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => [0.0, 0.0, 170.0, 80.0]],
        ['col_id' => 1, 'bbox' => [180.0, 0.0, 358.0, 80.0]],
    ],
];

$tableTextLines = [[
    'width' => 612,
    'height' => 792,
    'rotation' => 0,
    'blocks' => [[
        'lines' => [
            $pdfTextLine([
                $pdfTextChars('Feature', 84.0, 160.0),
                $pdfTextChars('Status', 260.0, 160.0),
            ]),
            $pdfTextLine([
                $pdfTextChars('Imported', 84.0, 196.0),
                $pdfTextChars('Ready', 260.0, 196.0),
            ]),
            $pdfTextLine([
                $pdfTextChars('Stale', 50.0, 140.0),
            ]),
        ],
    ]],
]];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-ocr-layout-structure-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table OCR layout structure boundary fixture\n%%EOF");

try {
    $converted = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        $pdftextPages,
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [$layout],
            'recognized_tables' => [$recognizedTable],
            'table_text_lines' => $tableTextLines,
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($pdfPath);
}

$markdown = $converted['text'];
$htmlTable = '<figure class="wp-block-table"><table><tbody>'
    . '<tr><td>Feature</td><td>Status</td></tr>'
    . '<tr><td>Imported</td><td>Ready</td></tr>'
    . '</tbody></table></figure>';

echo json_encode([
    'scenario' => 'wordpress-table-ocr-layout-structure-boundary',
    'native_boundary' => 'pdftext table-line structures are filtered by highres table layout bbox, split into table-local cells, and used when recognition output has rows/columns but no cells',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Layout Table Import</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => $htmlTable],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After structured table.</p>'],
    ],
    'supplied_boundaries' => $converted['metadata']['supplied_boundaries'] ?? [],
    'table_needs_ocr' => $converted['metadata']['table_needs_ocr'] ?? [],
    'table_detect_boxes' => $converted['metadata']['table_detect_boxes'] ?? false,
    'table_cell_counts' => $converted['metadata']['table_cell_counts'] ?? [],
    'first_table_cell_bbox' => $converted['metadata']['table_assigned_cells'][0][0]['bbox'] ?? null,
    'excluded_stale_pdftext_table_line' => !str_contains($markdown, 'Legacy table text should be replaced.'),
    'excluded_outside_layout_textline' => !str_contains($markdown, 'Stale'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $markdown,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
