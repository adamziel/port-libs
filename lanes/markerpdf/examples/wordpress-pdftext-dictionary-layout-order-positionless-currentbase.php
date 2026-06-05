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

$coverPage = $page(1600, [
    ['text' => 'Positionless order cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);
$selectedPage = $page(1601, [
    ['text' => 'Second positionless WordPress column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First positionless WordPress column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);

$path = sys_get_temp_dir() . '/markerpdf-positionless-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% positionless pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'order_images' => [
                ['page' => 1601, 'image' => 'positionless-order-render'],
            ],
            'order_results' => [[
                'page' => 1601,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['bbox' => [318.0, 96.0, 570.0, 150.0], 'raw_payload' => 'positionless right payload should remain review-only'],
                    ['bbox' => [60.0, 96.0, 290.0, 150.0], 'raw_payload' => 'positionless left payload should remain review-only'],
                ],
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$secondColumnPos = strpos($text, 'Second positionless WordPress column.');
$firstColumnPos = strpos($text, 'First positionless WordPress column.');
if (str_contains($text, 'Positionless order cover page should not import.')
    || $secondColumnPos === false
    || $firstColumnPos === false
    || $secondColumnPos > $firstColumnPos
) {
    throw new RuntimeException('Expected positionless order bbox dictionaries to preserve supplied row order for WordPress paragraphs.');
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

$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
echo '<!-- markerpdf-pdftext-dictionary-layout-order-positionless-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-positionless-currentbase',
    'source_truth' => 'markerPDF applies supplied layout/order geometry after selected pdftext dictionary page extraction; native positionless bbox dictionaries infer model order from row sequence before block sorting',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'order_artifacts_trimmed' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'positionless_rows_ordered' => $secondColumnPos !== false && $firstColumnPos !== false && $secondColumnPos < $firstColumnPos,
    'cover_excluded' => !str_contains($text, 'Positionless order cover page should not import.'),
    'raw_payload_excluded' => !str_contains($encoded, 'positionless right payload should remain review-only')
        && !str_contains($encoded, 'positionless left payload should remain review-only'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
