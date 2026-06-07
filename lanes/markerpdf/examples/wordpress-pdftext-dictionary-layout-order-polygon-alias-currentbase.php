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

$points = static fn (float $x1, float $y1, float $x2, float $y2): array => [
    ['x' => (string) $x1, 'y' => (string) $y1],
    ['x' => (string) $x2, 'y' => (string) $y1],
    ['x' => (string) $x2, 'y' => (string) $y2],
    ['x' => (string) $x1, 'y' => (string) $y2],
];
$flatQuad = static fn (float $x1, float $y1, float $x2, float $y2): array => [
    $x1,
    $y1,
    $x2,
    $y1,
    $x2,
    $y2,
    $x1,
    $y2,
];

$path = sys_get_temp_dir() . '/markerpdf-polygon-alias-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% polygon alias pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(6310, [
                ['text' => 'Polygon alias cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(6311, [
                ['text' => 'Polygon alias WordPress body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'Polygon alias WordPress title', 'bbox' => [72.0, 48.0, 360.0, 68.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['page' => 6311, 'image' => 'polygon-alias-layout-render'],
            ],
            'layout_results' => [[
                'page' => 6311,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'vertices' => $points(60.0, 42.0, 370.0, 76.0), 'raw_payload' => 'vertices alias layout payload should remain review-only'],
                    ['label' => 'Text', 'quadrilateral_points' => $points(318.0, 96.0, 570.0, 144.0), 'raw_payload' => 'quadrilateral alias layout payload should remain review-only'],
                ],
            ]],
            'order_images' => [
                ['page' => 6311, 'image' => 'polygon-alias-order-render'],
            ],
            'order_results' => [[
                'page' => 6311,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 1, 'points' => $points(60.0, 42.0, 370.0, 76.0), 'raw_payload' => 'points alias order payload should remain review-only'],
                    ['position' => 2, 'quad' => $flatQuad(318.0, 96.0, 570.0, 144.0), 'raw_payload' => 'flat quad alias order payload should remain review-only'],
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
$titlePos = strpos($text, '# Polygon Alias Wordpress Title');
$bodyPos = strpos($text, 'Polygon alias WordPress body.');

if ($titlePos === false
    || $bodyPos === false
    || $titlePos > $bodyPos
    || str_contains($text, 'Polygon alias cover page should not import.')
    || str_contains($encoded, 'vertices alias layout payload should remain review-only')
    || str_contains($encoded, 'quadrilateral alias layout payload should remain review-only')
    || str_contains($encoded, 'points alias order payload should remain review-only')
    || str_contains($encoded, 'flat quad alias order payload should remain review-only')
) {
    throw new RuntimeException('Expected polygon alias layout/order geometry to drive WordPress heading and reading order.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-polygon-alias-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-polygon-alias-currentbase',
    'source_truth' => 'markerPDF applies supplied layout and Surya ordering geometry after selected pdftext dictionary page extraction; native supplied adapters may serialize the same four-corner geometry as points, vertices, quad, quadrilateral, or quadrilateral_points aliases before WordPress import',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_polygon_aliases_assigned' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1,
    'order_polygon_aliases_assigned' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'ordered_text' => [
        'title_before_body' => $titlePos !== false && $bodyPos !== false && $titlePos < $bodyPos,
        'cover_excluded' => !str_contains($text, 'Polygon alias cover page should not import.'),
    ],
    'raw_payload_excluded' => !str_contains($encoded, 'vertices alias layout payload should remain review-only')
        && !str_contains($encoded, 'quadrilateral alias layout payload should remain review-only')
        && !str_contains($encoded, 'points alias order payload should remain review-only')
        && !str_contains($encoded, 'flat quad alias order payload should remain review-only'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
