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
                        'font' => ['name' => 'Times-Roman', 'flags' => null, 'weight' => 400, 'size' => 11.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$coverPage = $page(74, [
    ['text' => 'Point-pair cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);
$selectedPage = $page(75, [
    ['text' => 'Second point-pair WordPress column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First point-pair WordPress column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);

$path = sys_get_temp_dir() . '/markerpdf-point-pair-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% point-pair pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['page' => 75, 'image' => 'point-pair-layout-render'],
            ],
            'layout_results' => [[
                'page' => 75,
                'image_bbox' => ['tl' => [0.0, 0.0], 'br' => [612.0, 792.0]],
                'bboxes' => [
                    [
                        'label' => 'Title',
                        'top_left' => ['x' => 60.0, 'y' => 92.0],
                        'bottom_right' => ['x' => 290.0, 'y' => 150.0],
                        'raw_payload' => 'point-pair layout payload should stay hidden',
                    ],
                    [
                        'label' => 'Text',
                        'tl' => [318.0, 92.0],
                        'br' => [570.0, 150.0],
                    ],
                ],
            ]],
            'order_images' => [
                ['page' => 75, 'image' => 'point-pair-order-render'],
            ],
            'order_results' => [[
                'page' => 75,
                'image_bbox' => ['tl' => [0.0, 0.0], 'br' => [612.0, 792.0]],
                'bboxes' => [
                    [
                        'position' => 1,
                        'top_left' => ['x' => 60.0, 'y' => 96.0],
                        'bottom_right' => ['x' => 290.0, 'y' => 144.0],
                        'raw_payload' => 'point-pair left order payload should stay hidden',
                    ],
                    [
                        'position' => 2,
                        'tl' => [318.0, 96.0],
                        'br' => [570.0, 144.0],
                        'raw_payload' => 'point-pair right order payload should stay hidden',
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
$firstOffset = stripos($text, 'First point-pair WordPress column.');
$secondOffset = stripos($text, 'Second point-pair WordPress column.');

if (str_contains($text, 'Point-pair cover page should not import.')) {
    throw new RuntimeException('Expected selected page range to exclude the point-pair cover page.');
}
if ($firstOffset === false || $secondOffset === false) {
    throw new RuntimeException('Expected both selected point-pair columns in WordPress output.');
}
if ($firstOffset > $secondOffset) {
    throw new RuntimeException('Expected point-pair order bboxes to reorder selected pdftext columns.');
}
foreach (['point-pair layout payload', 'point-pair left order payload', 'point-pair right order payload'] as $payload) {
    if (str_contains($encoded, $payload)) {
        throw new RuntimeException('Expected private point-pair artifact payloads to remain review-only.');
    }
}

echo '<!-- wp:heading {"level":1,"metadata":{"markerpdfPage":75}} -->' . "\n";
echo '<h1>First Point-Pair WordPress Column.</h1>' . "\n";
echo "<!-- /wp:heading -->\n\n";
echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":75}} -->' . "\n";
echo '<p>Second point-pair WordPress column.</p>' . "\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf-pdftext-dictionary-layout-order-point-pair-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-point-pair-currentbase',
    'source_truth' => 'markerPDF zips selected pdftext dictionary pages with supplied layout/order predictions; point-pair bbox aliases must normalize before selected-page reading order assignment',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'selected_page' => 75,
    'layout_assigned_pages' => $result['metadata']['layout_plan']['assigned_pages'] ?? null,
    'order_assigned_pages' => $result['metadata']['order_plan']['assigned_pages'] ?? null,
    'requested_order_bboxes' => $result['metadata']['order_plan']['requested_bboxes'][0] ?? [],
    'cover_excluded' => !str_contains($text, 'Point-pair cover page should not import.'),
    'point_pair_order_applied' => $firstOffset !== false && $secondOffset !== false && $firstOffset < $secondOffset,
    'private_payloads_excluded' => !str_contains($encoded, 'point-pair layout payload') && !str_contains($encoded, 'point-pair left order payload') && !str_contains($encoded, 'point-pair right order payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
