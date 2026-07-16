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

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-band-order-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table band order boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Band order table review', 'bbox' => [72.0, 48.0, 430.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale numeric-id table text should be replaced.', 'bbox' => [72.0, 176.0, 330.0, 196.0]],
                ['text' => 'After band order review.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 430.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 272.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 20, 'bbox' => [0.0, 0.0, 200.0, 30.0]],
                    ['row_id' => -5, 'bbox' => [0.0, 40.0, 200.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 100, 'bbox' => [0.0, 0.0, 96.0, 80.0]],
                    ['col_id' => -10, 'bbox' => [108.0, 0.0, 200.0, 80.0]],
                ],
                'cells' => [
                    ['bbox' => [6.0, 5.0, 84.0, 24.0], 'text' => 'Feature'],
                    ['bbox' => [116.0, 5.0, 190.0, 24.0], 'text' => 'Status'],
                    ['bbox' => [6.0, 45.0, 84.0, 64.0], 'text' => 'Images'],
                    ['bbox' => [116.0, 45.0, 190.0, 64.0], 'text' => 'Ready'],
                ],
            ]],
            'table_text_lines' => [['blocks' => []]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($pdfPath);
}

$gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
if (($gridReview['rows'] ?? null) !== [20, -5] || ($gridReview['cols'] ?? null) !== [100, -10]) {
    throw new RuntimeException('Expected table grid review to preserve geometric row/column band order.');
}
if (!str_contains($result['text'], '| Feature | Status |') || !str_contains($result['text'], '| Images  | Ready  |')) {
    throw new RuntimeException('Expected WordPress Markdown table to preserve geometric band order.');
}
if (str_contains($result['text'], 'Stale numeric-id table text should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-band-order-boundary-currentbase',
    'native_boundary' => 'tabled assignment row_id and col_id values remain identifiers while Markdown/grid review order follows table-crop geometry',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Band Order Table Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After band order review.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'geometry_ordered_rows' => $gridReview['rows'] ?? null,
    'geometry_ordered_cols' => $gridReview['cols'] ?? null,
    'header_texts' => array_column($gridReview['header_cells'] ?? [], 'text'),
    'data_texts' => array_column($gridReview['data_cells'] ?? [], 'text'),
    'body_headers' => array_map(
        static fn (array $cell): array => isset($cell['headers']) && is_array($cell['headers']) ? $cell['headers'] : [],
        $gridReview['data_cells'] ?? []
    ),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale numeric-id table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
