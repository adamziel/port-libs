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

$path = sys_get_temp_dir() . '/markerpdf-decimal-key-layout-order-smoke-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% decimal-key pdftext layout order smoke\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            'dictionary_output' => [
                '+9711.0' => $page(9711, [
                    ['text' => 'Second decimal-key WordPress body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                    ['text' => 'First decimal-key WordPress heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                ]),
                '+9710.0' => $page(9710, [
                    ['text' => 'Decimal-key WordPress cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
            ],
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [[
                'dictionary_output' => [
                    '+9711.0' => ['image' => 'decimal-key-layout-render'],
                    '+9710.0' => ['image' => 'decimal-key-stale-layout-render'],
                ],
            ]],
            'layout_results' => [[
                'dictionary_output' => [
                    '+9711.0' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'decimal-key title layout payload should stay hidden'],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                    ],
                    '+9710.0' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                        'raw_payload' => 'decimal-key stale layout payload should stay hidden',
                    ],
                ],
            ]],
            'order_images' => [[
                'dictionary_output' => [
                    '+9711.0' => ['image' => 'decimal-key-order-render'],
                    '+9710.0' => ['image' => 'decimal-key-stale-order-render'],
                ],
            ]],
            'order_results' => [[
                'dictionary_output' => [
                    '+9711.0' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'decimal-key order payload should stay hidden'],
                        ],
                    ],
                    '+9710.0' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ],
                        'raw_payload' => 'decimal-key stale order payload should stay hidden',
                    ],
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
$headingPosition = strpos($text, '# First Decimal-Key Wordpress Heading.');
$bodyPosition = strpos($text, 'Second decimal-key WordPress body.');
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-decimal-key-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary_output pages before layout/order assignment; native decimal-string source-page object keys must align before zip-style supplied layout/order processing',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'decimal_keyed_dictionary_output_ordered' => ($result['metadata']['page_range'] ?? null) === [1],
    'layout_decimal_key_map_selected' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1,
    'order_decimal_key_map_selected' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'heading_before_body' => $headingPosition !== false && $bodyPosition !== false && $headingPosition < $bodyPosition,
    'cover_excluded' => !str_contains($text, 'Decimal-key WordPress cover should stay skipped.'),
    'payload_excluded' => !str_contains($encoded, 'decimal-key title layout payload')
        && !str_contains($encoded, 'decimal-key stale layout payload')
        && !str_contains($encoded, 'decimal-key order payload')
        && !str_contains($encoded, 'decimal-key stale order payload')
        && !str_contains($encoded, '__markerpdf_envelope_page_key_marker'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['decimal_keyed_dictionary_output_ordered']
    || !$flags['layout_decimal_key_map_selected']
    || !$flags['order_decimal_key_map_selected']
    || !$flags['heading_before_body']
    || !$flags['cover_excluded']
    || !$flags['payload_excluded']
) {
    throw new RuntimeException('Expected decimal-string source keys to preserve selected WordPress layout/order import: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $paragraph) {
    $paragraph = trim($paragraph);
    if ($paragraph === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-decimal-key-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
