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

$path = sys_get_temp_dir() . '/markerpdf-polygon-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% polygon pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(2600, [
                ['text' => 'Polygon order cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(2601, [
                ['text' => 'Second polygon WordPress column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First polygon WordPress column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'order_images' => [
                ['page' => 2601, 'image' => 'polygon-order-render'],
            ],
            'order_results' => [[
                'page' => 2601,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    [
                        'position' => 1,
                        'polygon' => [[60.0, 96.0], [290.0, 96.0], [290.0, 150.0], [60.0, 150.0]],
                        'raw_payload' => 'polygon left order payload should remain review-only',
                    ],
                    [
                        'position' => 2,
                        'polygon' => [[318.0, 96.0], [570.0, 96.0], [570.0, 150.0], [318.0, 150.0]],
                        'raw_payload' => 'polygon right order payload should remain review-only',
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
$firstColumnPos = strpos($text, 'First polygon WordPress column.');
$secondColumnPos = strpos($text, 'Second polygon WordPress column.');

if (
    $firstColumnPos === false
    || $secondColumnPos === false
    || $firstColumnPos > $secondColumnPos
    || str_contains($text, 'Polygon order cover page should not import.')
    || str_contains($encoded, 'polygon left order payload should remain review-only')
    || str_contains($encoded, 'polygon right order payload should remain review-only')
) {
    throw new RuntimeException('Expected polygon-only order rows to drive WordPress paragraph reading order.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-polygon-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-polygon-currentbase',
    'source_truth' => 'markerPDF applies Surya ordering geometry after selected pdftext dictionary page extraction; native supplied order rows may carry four-corner polygons that are reduced to bbox geometry before block ordering',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'polygon_order_rows_used' => $firstColumnPos !== false && $secondColumnPos !== false && $firstColumnPos < $secondColumnPos,
    'polygon_order_artifact_assigned' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'cover_excluded' => !str_contains($text, 'Polygon order cover page should not import.'),
    'raw_payload_excluded' => !str_contains($encoded, 'polygon left order payload should remain review-only')
        && !str_contains($encoded, 'polygon right order payload should remain review-only'),
    'order_plan' => $result['metadata']['order_plan'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
