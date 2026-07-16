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
                        'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};
$xxyy = static fn (float $x1, float $y1, float $x2, float $y2): array => [$x1, $x2, $y1, $y2];

$path = sys_get_temp_dir() . '/markerpdf-layout-coordinate-order-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% coordinate order layout pdftext boundary current-base smoke\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(3010, [
                ['text' => 'Coordinate order cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(3011, [
                ['text' => 'Coordinate Order Title', 'bbox' => [72.0, 48.0, 360.0, 68.0]],
                ['text' => 'Coordinate order body remains a paragraph.', 'bbox' => [72.0, 112.0, 480.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['page' => 3011, 'image' => 'coordinate-order-layout-render'],
            ],
            'layout_results' => [[
                'page' => 3011,
                'image_bbox' => $xxyy(0.0, 0.0, 612.0, 792.0),
                'image_bbox_order' => 'x1_x2_y1_y2',
                'bbox_order' => 'x1_x2_y1_y2',
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => $xxyy(60.0, 42.0, 370.0, 76.0)],
                    ['label' => 'Text', 'bbox' => $xxyy(60.0, 100.0, 490.0, 140.0)],
                ],
                'raw_payload' => 'coordinate order layout payload must stay hidden',
            ]],
            'order_images' => [
                ['page' => 3011, 'image' => 'coordinate-order-layout-order-render'],
            ],
            'order_results' => [[
                'page' => 3011,
                'image_bbox' => $xxyy(0.0, 0.0, 612.0, 792.0),
                'image_bbox_order' => 'x1_x2_y1_y2',
                'bbox_order' => 'x1_x2_y1_y2',
                'bboxes' => [
                    ['position' => 1, 'bbox' => $xxyy(60.0, 42.0, 370.0, 76.0)],
                    ['position' => 2, 'bbox' => $xxyy(60.0, 100.0, 490.0, 140.0)],
                ],
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    if (is_file($path)) {
        unlink($path);
    }
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$headingPromoted = str_contains($text, '# Coordinate Order Title');
$bodyPreserved = str_contains($text, 'Coordinate order body remains a paragraph.');
$coverExcluded = !str_contains($text, 'Coordinate order cover should not import.');
$payloadExcluded = !str_contains($encoded, 'coordinate order layout payload must stay hidden');
$headingBeforeBody = strpos($text, '# Coordinate Order Title') < strpos($text, 'Coordinate order body remains a paragraph.');

if (!$headingPromoted || !$bodyPreserved || !$coverExcluded || !$payloadExcluded || !$headingBeforeBody) {
    throw new RuntimeException('Expected coordinate-order supplied layout/order boxes to drive WordPress heading and paragraph output.');
}

foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $block) {
    $block = trim($block);
    if ($block === '') {
        continue;
    }

    if (str_starts_with($block, '# ')) {
        echo "<!-- wp:heading {\"level\":1} -->\n";
        echo '<h1>' . htmlspecialchars(substr($block, 2), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h1>\n";
        echo "<!-- /wp:heading -->\n\n";
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-coordinate-order-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-coordinate-order-currentbase',
    'source_truth' => 'markerPDF accepts supplied layout/order bboxes for selected pdftext dictionary pages; table geometry already treats x1_x2_y1_y2 coordinate-order metadata as a native boundary contract',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'selected_page' => 3011,
    'layout_coordinate_order_normalized' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1,
    'order_coordinate_order_normalized' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'heading_promoted' => $headingPromoted,
    'body_preserved_as_paragraph' => $bodyPreserved,
    'heading_before_body' => $headingBeforeBody,
    'cover_excluded' => $coverExcluded,
    'payload_excluded' => $payloadExcluded,
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
