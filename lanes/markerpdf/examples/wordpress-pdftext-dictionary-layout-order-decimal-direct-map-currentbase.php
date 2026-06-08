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
                        'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$path = sys_get_temp_dir() . '/markerpdf-decimal-direct-map-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% decimal direct-map pdftext layout order smoke\n%%EOF");

try {
    $converted = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            'dictionary_output' => [
                '+9820.0' => $page(9820, [
                    ['text' => 'Decimal direct-map cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                '+9821.0' => $page(9821, [
                    ['text' => 'Second decimal direct-map column is body text.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                    ['text' => 'First decimal direct-map column becomes the heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                ]),
            ],
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                '+9820.0' => ['image' => 'decimal-direct-map-cover-layout-render'],
                '+9821.0' => ['image' => 'decimal-direct-map-selected-layout-render'],
            ],
            'layout_results' => [
                '+9820.0' => [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                    'raw_payload' => 'stale decimal direct-map layout payload must stay hidden',
                ],
                '+9821.0' => [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                ],
            ],
            'order_images' => [
                '+9820.0' => ['image' => 'decimal-direct-map-cover-order-render'],
                '+9821.0' => ['image' => 'decimal-direct-map-selected-order-render'],
            ],
            'order_results' => [
                '+9820.0' => [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                    'raw_payload' => 'stale decimal direct-map order payload must stay hidden',
                ],
                '+9821.0' => [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
            ],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

if (str_contains($converted['text'], 'Decimal direct-map cover should not import.')
    || strpos($converted['text'], 'First decimal direct-map column becomes the heading.') > strpos($converted['text'], 'Second decimal direct-map column is body text.')
) {
    throw new RuntimeException('Expected decimal source-keyed direct maps to select and order the current pdftext page.');
}

foreach (preg_split('/\R{2,}/', trim($converted['text'])) ?: [] as $paragraph) {
    $paragraph = trim($paragraph);
    if ($paragraph === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

$encoded = json_encode($converted, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$text = $converted['text'];
echo '<!-- markerpdf-pdftext-dictionary-layout-order-decimal-direct-map-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-decimal-direct-map-currentbase',
    'source_truth' => 'Native adapters can pass source-page keyed supplied layout/order maps directly; decimal .0 page keys are the same integer identity already accepted by pdftext dictionary and nested artifact maps.',
    'page_range' => $converted['metadata']['page_range'] ?? [],
    'layout_direct_map_selected' => ($converted['metadata']['layout_plan']['assigned_pages'] ?? null) === 1
        && ($converted['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_direct_map_selected' => ($converted['metadata']['order_plan']['assigned_pages'] ?? null) === 1
        && ($converted['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'ordered_text' => [
        'first_before_second' => strpos($text, 'First decimal direct-map column becomes the heading.') < strpos($text, 'Second decimal direct-map column is body text.'),
        'cover_excluded' => !str_contains($text, 'Decimal direct-map cover should not import.'),
    ],
    'payload_excluded' => !str_contains($encoded, 'stale decimal direct-map layout payload')
        && !str_contains($encoded, 'stale decimal direct-map order payload'),
    'supplied_boundaries' => $converted['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
