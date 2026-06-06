<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (int $page, array $lines): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'blocks' => [[
            'lines' => array_map(
                static fn (array $line): array => [
                    'bbox' => $line['bbox'],
                    'spans' => [[
                        'text' => $line['text'],
                        'bbox' => $line['bbox'],
                        'font' => ['name' => 'Times-Roman', 'flags' => null, 'weight' => 400, 'size' => 11.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$path = sys_get_temp_dir() . '/markerpdf-row-page-layout-order-example-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% row-level page marker layout order example\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(6100, [
                ['text' => 'Row marker cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(6101, [
                ['text' => 'Second row-marker import column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First row-marker import column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['metadata' => ['document_page' => 6101], 'image' => 'row-page-layout-render'],
            ],
            'layout_results' => [[
                'metadata' => ['document_page' => 6101],
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    [
                        'label' => 'Title',
                        'document_page' => 6100,
                        'bbox' => [60.0, 92.0, 290.0, 150.0],
                        'raw_payload' => 'stale layout row payload',
                    ],
                    ['label' => 'Text', 'document_page' => 6101, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                    ['label' => 'Text', 'document_page' => 6101, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                ],
            ]],
            'order_images' => [
                ['metadata' => ['document_page' => 6101], 'image' => 'row-page-order-render'],
            ],
            'order_results' => [[
                'metadata' => ['document_page' => 6101],
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    [
                        'position' => 1,
                        'document_page' => 6100,
                        'bbox' => [318.0, 96.0, 570.0, 144.0],
                        'raw_payload' => 'stale order row payload',
                    ],
                    ['position' => 2, 'document_page' => 6101, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                ],
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-row-page-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before zipping supplied layout/order model rows; native adapters must drop mixed row-level page-marker payloads that do not belong to the selected page',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $result['metadata']['page_range'] ?? null,
    'layout_order_assigned' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1
        && ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'first_before_second' => strpos($text, 'First row-marker import column.') < strpos($text, 'Second row-marker import column.'),
    'stale_layout_title_excluded' => !str_contains($text, '# First Row-Marker Import Column.'),
    'cover_excluded' => !str_contains($text, 'Row marker cover should stay skipped.'),
    'stale_payload_excluded' => !str_contains($encoded, 'stale layout row payload')
        && !str_contains($encoded, 'stale order row payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['layout_order_assigned']
    || !$flags['first_before_second']
    || !$flags['stale_layout_title_excluded']
    || !$flags['cover_excluded']
    || !$flags['stale_payload_excluded']
) {
    throw new RuntimeException('Expected row-level page markers to filter stale layout/order rows before WordPress import: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":6101}} -->' . "\n";
echo '<p>' . htmlspecialchars(str_replace("\n", ' ', trim($text)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";
echo '<!-- markerpdf-pdftext-dictionary-layout-order-row-page-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
