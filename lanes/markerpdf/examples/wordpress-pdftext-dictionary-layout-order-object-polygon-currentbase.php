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

$polygon = static function (float $left, float $top, float $right, float $bottom): array {
    return [
        ['x' => $left, 'y' => $top, 'confidence' => 0.99],
        ['x' => $right, 'y' => $top, 'confidence' => 0.99],
        ['x' => $right, 'y' => $bottom, 'confidence' => 0.98],
        ['x' => $left, 'y' => $bottom, 'confidence' => 0.98],
    ];
};

$path = sys_get_temp_dir() . '/markerpdf-object-polygon-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% object polygon pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(3700, [
                ['text' => 'Object polygon cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(3701, [
                ['text' => 'Object polygon import title', 'bbox' => [72.0, 48.0, 360.0, 68.0]],
                ['text' => 'Object polygon import body.', 'bbox' => [72.0, 112.0, 480.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['page' => 3701, 'image' => 'object-polygon-layout-render'],
            ],
            'layout_results' => [[
                'page' => 3701,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    [
                        'label' => 'Title',
                        'polygon' => $polygon(60.0, 42.0, 370.0, 76.0),
                        'raw_payload' => 'object polygon title layout payload should remain review-only',
                    ],
                    [
                        'label' => 'Text',
                        'polygon' => $polygon(60.0, 100.0, 490.0, 140.0),
                        'raw_payload' => 'object polygon body layout payload should remain review-only',
                    ],
                ],
            ]],
            'order_images' => [
                ['page' => 3701, 'image' => 'object-polygon-order-render'],
            ],
            'order_results' => [[
                'page' => 3701,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    [
                        'position' => 1,
                        'polygon' => $polygon(60.0, 42.0, 370.0, 76.0),
                        'raw_payload' => 'object polygon title order payload should remain review-only',
                    ],
                    [
                        'position' => 2,
                        'polygon' => $polygon(60.0, 100.0, 490.0, 140.0),
                        'raw_payload' => 'object polygon body order payload should remain review-only',
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
$titlePos = strpos($text, '# Object Polygon Import Title');
$bodyPos = strpos($text, 'Object polygon import body.');

if (
    $titlePos === false
    || $bodyPos === false
    || $titlePos > $bodyPos
    || str_contains($text, 'Object polygon cover page should not import.')
    || str_contains($encoded, 'object polygon title layout payload should remain review-only')
    || str_contains($encoded, 'object polygon body layout payload should remain review-only')
    || str_contains($encoded, 'object polygon title order payload should remain review-only')
    || str_contains($encoded, 'object polygon body order payload should remain review-only')
) {
    throw new RuntimeException('Expected object-point polygons to drive WordPress layout and reading-order review.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-object-polygon-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-object-polygon-currentbase',
    'source_truth' => 'markerPDF applies supplied layout and Surya ordering geometry after selected pdftext dictionary page extraction; native supplied adapters may carry object-shaped polygon points that reduce to bbox geometry before block typing and ordering',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'object_polygon_layout_assigned' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1,
    'object_polygon_order_assigned' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'title_before_body' => $titlePos !== false && $bodyPos !== false && $titlePos < $bodyPos,
    'cover_excluded' => !str_contains($text, 'Object polygon cover page should not import.'),
    'raw_payload_excluded' => !str_contains($encoded, 'object polygon title layout payload should remain review-only')
        && !str_contains($encoded, 'object polygon body layout payload should remain review-only')
        && !str_contains($encoded, 'object polygon title order payload should remain review-only')
        && !str_contains($encoded, 'object polygon body order payload should remain review-only'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
