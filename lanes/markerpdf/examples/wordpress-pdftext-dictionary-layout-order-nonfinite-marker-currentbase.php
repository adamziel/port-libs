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

$selectedPage = $page(0, [
    ['text' => 'Second nonfinite marker artifact column remains source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First nonfinite marker artifact column has no trusted order.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);

$layoutResult = [
    'page' => INF,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
    'raw_payload' => 'nonfinite layout marker payload must stay hidden',
];
$orderResult = [
    'page' => INF,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
    'raw_payload' => 'nonfinite order marker payload must stay hidden',
];

$path = sys_get_temp_dir() . '/markerpdf-nonfinite-layout-order-marker-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% nonfinite marker pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$selectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 0,
            'lowres_images' => [
                ['page' => INF, 'image' => 'nonfinite-layout-render'],
            ],
            'layout_results' => [$layoutResult],
            'order_images' => [
                ['page' => INF, 'image' => 'nonfinite-order-render'],
            ],
            'order_results' => [$orderResult],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

foreach (preg_split('/\R{2,}/', trim($result['text'])) ?: [] as $paragraph) {
    $paragraph = trim($paragraph);
    if ($paragraph === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$boundaries = $result['metadata']['supplied_boundaries'] ?? [];

echo '<!-- markerpdf-pdftext-dictionary-layout-order-nonfinite-marker-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-nonfinite-marker-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before layout/order assignment; supplied page identity markers must be finite integers before native zip-style alignment',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'malformed_layout_marker_rejected' => !in_array('layout', $boundaries, true),
    'malformed_order_marker_rejected' => !in_array('order', $boundaries, true),
    'nonfinite_marker_not_cast_to_zero' => ($result['metadata']['layout_plan']['assigned_pages'] ?? 0) === 0 && ($result['metadata']['order_plan']['assigned_pages'] ?? 0) === 0,
    'source_order_preserved' => strpos($text, 'Second nonfinite marker artifact column remains source ordered.') < strpos($text, 'First nonfinite marker artifact column has no trusted order.'),
    'payload_excluded' => !str_contains($encoded, 'nonfinite layout marker payload') && !str_contains($encoded, 'nonfinite order marker payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
