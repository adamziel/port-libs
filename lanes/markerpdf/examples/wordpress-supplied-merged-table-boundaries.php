<?php

declare(strict_types=1);

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

$path = sys_get_temp_dir() . '/markerpdf-wordpress-merged-table-boundaries-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% merged table boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'Merged structure import', 'bbox' => [72.0, 60.0, 420.0, 78.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Raw merged table formula image seam text', 'bbox' => [72.0, 160.0, 420.0, 178.0]],
                ['text' => 'After merged structure.', 'bbox' => [72.0, 260.0, 420.0, 278.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 60.0, 420.0, 78.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 250.0, 210.0]],
                    ['label' => 'Table', 'bbox' => [258.0, 150.0, 420.0, 210.0]],
                    ['label' => 'Formula', 'bbox' => [252.0, 160.0, 257.0, 182.0]],
                    ['label' => 'Picture', 'bbox' => [252.0, 184.0, 257.0, 205.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 260.0, 420.0, 278.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 30.0, 300.0, 60.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 150.0, 60.0]],
                    ['col_id' => 1, 'bbox' => [150.0, 0.0, 300.0, 60.0]],
                ],
                'cells' => [
                    ['bbox' => [0.0, 0.0, 140.0, 25.0], 'text' => 'Metric'],
                    ['bbox' => [150.0, 0.0, 290.0, 25.0], 'text' => 'Value'],
                    ['bbox' => [0.0, 30.0, 140.0, 55.0], 'text' => 'Seam'],
                    ['bbox' => [150.0, 30.0, 290.0, 55.0], 'text' => 'Protected'],
                ],
            ]],
            'table_text_lines' => [['blocks' => []]],
            'equation_predictions' => ['$$E=mc^2$$'],
            'image_payloads' => [['PNG-SEAM-BYTES']],
        ]
    );
} finally {
    unlink($path);
}

$markdown = $result['text'];
$paragraphs = array_values(array_filter(
    preg_split('/\n{2,}/', trim($markdown)) ?: [],
    static fn (string $block): bool => trim($block) !== ''
));

echo json_encode([
    'scenario' => 'wordpress-supplied-merged-table-boundaries',
    'native_boundary' => 'merged table boxes protect seam Formula/Picture regions before WordPress rendering',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Merged Structure Import</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Metric</td><td>Value</td></tr><tr><td>Seam</td><td>Protected</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After merged structure.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'],
    'block_stats' => $result['metadata']['block_stats'],
    'table_bboxes' => $result['metadata']['table_plan']['table_bboxes'],
    'image_count' => count($result['images']),
    'excluded_duplicate_equation' => !str_contains($markdown, '$$E=mc^2$$'),
    'excluded_duplicate_image' => !str_contains($markdown, '![0_image_0.png](0_image_0.png)'),
    'excluded_raw_table_text' => !str_contains($markdown, 'Raw merged table formula image seam text'),
    'paragraph_count' => count($paragraphs),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $markdown,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
