<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (int $page, array $lines): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
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

$path = sys_get_temp_dir() . '/markerpdf-zero-area-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% zero-area pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(900, [
                ['text' => 'Zero-area order cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(901, [
                ['text' => 'Second zero-area supplied column remains source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First zero-area supplied column has no trusted order.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['page' => 901, 'image' => 'selected-layout-render'],
            ],
            'layout_results' => [[
                'page' => 901,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                ],
            ]],
            'order_images' => [
                ['page' => 901, 'image' => 'zero-area-order-render'],
            ],
            'order_results' => [[
                'page' => 901,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 1, 'bbox' => [60.0, 96.0, 60.0, 144.0], 'raw_payload' => 'zero width order payload'],
                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 96.0], 'raw_payload' => 'zero height order payload'],
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
$firstPosition = strpos($text, 'First zero-area supplied column has no trusted order.');
$secondPosition = strpos($text, 'Second zero-area supplied column remains source ordered.');

if (
    $firstPosition === false
    || $secondPosition === false
    || $secondPosition > $firstPosition
    || str_contains($text, 'Zero-area order cover page should not import.')
    || str_contains($encoded, 'zero width order payload')
    || str_contains($encoded, 'zero height order payload')
) {
    throw new RuntimeException('Expected zero-area order geometry to fail closed before WordPress paragraph import.');
}

foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $paragraph) {
    $paragraph = trim($paragraph);
    if ($paragraph === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-zero-area-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-zero-area-currentbase',
    'source_truth' => 'markerPDF applies supplied layout/order after selected pdftext dictionary pages; native order boxes need positive area and positive overlap before changing selected-page reading order',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'zero_area_order_boxes_excluded' => !str_contains($encoded, 'zero width order payload') && !str_contains($encoded, 'zero height order payload'),
    'source_order_preserved' => $secondPosition !== false && $firstPosition !== false && $secondPosition < $firstPosition,
    'cover_excluded' => !str_contains($text, 'Zero-area order cover page should not import.'),
    'order_plan' => $result['metadata']['order_plan'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
