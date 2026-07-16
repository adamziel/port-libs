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

$coverPage = $page(1500, [
    ['text' => 'Bbox-list cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);
$selectedPage = $page(1501, [
    ['text' => 'Second bbox-list WordPress column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First bbox-list WordPress column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);

$path = sys_get_temp_dir() . '/markerpdf-bbox-list-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% bbox-list pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'order_images' => [
                ['page' => 1501, 'image' => 'bbox-list-order-render'],
            ],
            'order_results' => [[
                'page' => 1501,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    [318.0, 96.0, 570.0, 150.0],
                    [60.0, 96.0, 290.0, 150.0],
                ],
                'raw_payload' => 'bbox-list order payload should remain review-only',
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
if (str_contains($text, 'Bbox-list cover page should not import.')
    || strpos($text, 'Second bbox-list WordPress column.') > strpos($text, 'First bbox-list WordPress column.')
) {
    throw new RuntimeException('Expected bare bbox-list order rows to drive selected-page WordPress paragraph ordering.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-bbox-list-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-bbox-list-currentbase',
    'source_truth' => 'markerPDF applies supplied layout/order geometry after pdftext dictionary page selection; native bare bbox-list order rows preserve their supplied sequence before zip-style ordering',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'order_artifacts_trimmed' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'bbox_list_positions_inferred' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'ordered_text' => [
        'supplied_sequence_preserved' => strpos($text, 'Second bbox-list WordPress column.') < strpos($text, 'First bbox-list WordPress column.'),
        'cover_excluded' => !str_contains($text, 'Bbox-list cover page should not import.'),
        'raw_payload_excluded' => !str_contains(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '', 'bbox-list order payload should remain review-only'),
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
