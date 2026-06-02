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

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-text-cell-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table text cell boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Table text cell boundary review', 'bbox' => [72.0, 48.0, 440.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Legacy crop-edge table text should be replaced.', 'bbox' => [72.0, 178.0, 430.0, 196.0]],
                ['text' => 'After crop cell review.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 440.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 358.0, 32.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 170.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [180.0, 0.0, 358.0, 80.0]],
                ],
            ]],
            'table_text_lines' => [[
                'width' => 612,
                'height' => 792,
                'rotation' => 0,
                'blocks' => [[
                    'lines' => [
                        $pdfTextLine([
                            $pdfTextChars('Margin', 66.0, 160.0),
                            $pdfTextChars('Value', 260.0, 160.0),
                        ]),
                    ],
                ]],
            ]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($pdfPath);
}

$review = $result['metadata']['table_text_cell_boundary_reviews'][0] ?? [];

echo json_encode([
    'scenario' => 'wordpress-table-text-cell-boundary-currentbase',
    'native_boundary' => 'pdftext-derived table cells keep upstream crop-local bboxes while WordPress receives clipped cell-boundary review metadata',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Table Text Cell Boundary Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Margin</td><td>Value</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After crop cell review.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'table_crop_size' => $review['table_crop_size'] ?? null,
    'clipped_cell_count' => $review['clipped_cell_count'] ?? null,
    'first_cell_original_bbox' => $review['cells'][0]['original_bbox'] ?? null,
    'first_cell_bounded_bbox' => $review['cells'][0]['bounded_bbox'] ?? null,
    'upstream_cell_bbox_retained' => $review['cells'][0]['upstream_cell_bbox_retained'] ?? null,
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Legacy crop-edge table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
