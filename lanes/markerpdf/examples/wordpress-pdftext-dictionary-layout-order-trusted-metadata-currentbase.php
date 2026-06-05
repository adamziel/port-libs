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

$coverPage = $page(2200, [
    ['text' => 'Trusted metadata cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);
$selectedPage = $page(2201, [
    ['text' => 'Second trusted metadata payload column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First trusted metadata payload column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);

$path = sys_get_temp_dir() . '/markerpdf-trusted-metadata-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% trusted metadata stale typed payload layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                [
                    'metadata' => ['document_page' => 2201],
                    'layout_result' => ['page' => 2200],
                    'image' => 'trusted-metadata-layout-render',
                ],
            ],
            'layout_results' => [[
                'metadata' => ['document_page' => 2201],
                'layout_result' => [
                    'page' => 2200,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                ],
                'raw_payload' => 'hidden stale layout-result page marker payload',
            ]],
            'order_images' => [
                [
                    'metadata' => ['document_page' => 2201],
                    'order_result' => ['page' => 2200],
                    'image' => 'trusted-metadata-order-render',
                ],
            ],
            'order_results' => [[
                'metadata' => ['document_page' => 2201],
                'order_result' => [
                    'page' => 2200,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
                'raw_payload' => 'hidden stale order-result page marker payload',
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$firstBeforeSecond = strpos($text, 'First trusted metadata payload column.') < strpos($text, 'Second trusted metadata payload column.');
$payloadExcluded = !str_contains($encoded, 'hidden stale layout-result page marker payload')
    && !str_contains($encoded, 'hidden stale order-result page marker payload');

if (
    ($result['metadata']['layout_plan']['assigned_pages'] ?? null) !== 1
    || ($result['metadata']['order_plan']['assigned_pages'] ?? null) !== 1
    || !$firstBeforeSecond
    || !$payloadExcluded
    || str_contains($text, 'Trusted metadata cover page should not import.')
) {
    throw new RuntimeException('Expected trusted adapter metadata to select the current pdftext page before stale typed payload page markers.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-trusted-metadata-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-trusted-metadata-currentbase',
    'source_truth' => 'markerPDF zips selected pdftext dictionary pages with layout/order model results; native adapter metadata identifies the selected page while typed result payload page markers are fallback-only',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_artifacts_trimmed' => ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_artifacts_trimmed' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'trusted_metadata_wins' => $firstBeforeSecond,
    'stale_typed_payload_markers_fallback_only' => $payloadExcluded,
    'cover_excluded' => !str_contains($text, 'Trusted metadata cover page should not import.'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
