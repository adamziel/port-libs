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

$coverPage = $page(3300, [
    ['text' => 'Named bbox cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);
$selectedPage = $page(3301, [
    ['text' => 'Second named bbox WordPress column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First named bbox WordPress column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);

$path = sys_get_temp_dir() . '/markerpdf-named-bbox-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% named bbox pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['page' => 3301, 'image' => 'named-bbox-layout-render'],
            ],
            'layout_results' => [[
                'page' => 3301,
                'image_bbox' => ['left' => 0.0, 'top' => 0.0, 'right' => 612.0, 'bottom' => 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'left' => 60.0, 'top' => 92.0, 'right' => 290.0, 'bottom' => 150.0],
                    ['label' => 'Text', 'bbox' => ['x' => 318.0, 'y' => 92.0, 'width' => 252.0, 'height' => 58.0]],
                ],
                'raw_payload' => 'named layout payload should remain review-only',
            ]],
            'order_images' => [
                ['page' => 3301, 'image' => 'named-bbox-order-render'],
            ],
            'order_results' => [[
                'page' => 3301,
                'image_bbox' => ['left' => 0.0, 'top' => 0.0, 'right' => 612.0, 'bottom' => 792.0],
                'bboxes' => [
                    [
                        'position' => 1,
                        'left' => 60.0,
                        'top' => 96.0,
                        'right' => 290.0,
                        'bottom' => 144.0,
                        'raw_payload' => 'named left order payload should remain review-only',
                    ],
                    [
                        'position' => 2,
                        'bbox' => ['x' => 318.0, 'y' => 96.0, 'width' => 252.0, 'height' => 48.0],
                        'raw_payload' => 'named right order payload should remain review-only',
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
$firstOffset = strpos($text, '# First Named Bbox Wordpress Column.');
$secondOffset = strpos($text, 'Second named bbox WordPress column.');
$requestedBboxes = $result['metadata']['order_plan']['requested_bboxes'][0] ?? null;

if (
    $firstOffset === false
    || $secondOffset === false
    || $firstOffset > $secondOffset
    || str_contains($text, 'Named bbox cover page should not import.')
    || str_contains($encoded, 'named layout payload should remain review-only')
    || str_contains($encoded, 'named left order payload should remain review-only')
    || str_contains($encoded, 'named right order payload should remain review-only')
    || $requestedBboxes !== [[60.0, 92.0, 290.0, 150.0], [318.0, 92.0, 570.0, 150.0]]
) {
    throw new RuntimeException('Expected named object layout/order bboxes to drive selected-page WordPress import ordering.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-named-bbox-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-named-bbox-currentbase',
    'source_truth' => 'markerPDF applies supplied layout and order geometry after selected pdftext dictionary pages; native adapters may serialize bboxes as named coordinate dictionaries before WordPress import',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_order_artifacts_trimmed' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1
        && ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'named_layout_bboxes_requested_for_ordering' => $requestedBboxes,
    'ordered_text' => [
        'first_before_second' => $firstOffset < $secondOffset,
        'cover_excluded' => !str_contains($text, 'Named bbox cover page should not import.'),
        'raw_payload_excluded' => !str_contains($encoded, 'named layout payload should remain review-only')
            && !str_contains($encoded, 'named left order payload should remain review-only')
            && !str_contains($encoded, 'named right order payload should remain review-only'),
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
