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

$coverPage = $page(10600, [
    ['text' => 'Stale direct-key selected-marker cover should not import.', 'bbox' => [72.0, 80.0, 340.0, 94.0]],
]);
$selectedPage = $page(10601, [
    ['text' => 'Second converter direct-key conflict body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First converter direct-key conflict heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);

$path = sys_get_temp_dir() . '/markerpdf-direct-key-marker-conflict-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% direct source-key marker conflict boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                10600 => [
                    'metadata' => ['selected_page_index' => 0],
                    'image' => 'stale-direct-key-selected-marker-layout-render',
                ],
            ],
            'layout_results' => [
                10600 => [
                    'metadata' => ['selected_page_index' => 0],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'stale direct-key selected-marker layout row payload must stay hidden'],
                        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                    'raw_payload' => 'stale direct-key selected-marker layout payload must stay hidden',
                ],
            ],
            'order_images' => [
                10600 => [
                    'metadata' => ['selected_page_index' => 0],
                    'image' => 'stale-direct-key-selected-marker-order-render',
                ],
            ],
            'order_results' => [
                10600 => [
                    'metadata' => ['selected_page_index' => 0],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'stale direct-key selected-marker order row payload must stay hidden'],
                    ],
                    'raw_payload' => 'stale direct-key selected-marker order payload must stay hidden',
                ],
            ],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$text = $result['text'];
$sourceOrderPreserved = strpos($text, 'Second converter direct-key conflict body.') < strpos($text, 'First converter direct-key conflict heading.');
$suppliedArtifactsExcluded = ($result['metadata']['supplied_boundaries'] ?? []) === []
    && !array_key_exists('layout_plan', $result['metadata'])
    && !array_key_exists('order_plan', $result['metadata']);
$stalePayloadExcluded = !str_contains($text, 'Stale direct-key selected-marker cover should not import.')
    && !str_contains($encoded, 'stale direct-key selected-marker layout payload')
    && !str_contains($encoded, 'stale direct-key selected-marker layout row payload')
    && !str_contains($encoded, 'stale direct-key selected-marker order payload')
    && !str_contains($encoded, 'stale direct-key selected-marker order row payload')
    && !str_contains($encoded, '__markerpdf_envelope_page_key_marker');

if (!$sourceOrderPreserved || !$suppliedArtifactsExcluded || !$stalePayloadExcluded) {
    throw new RuntimeException('Expected stale direct-key selected-marker artifacts to be excluded before WordPress import.');
}

$paragraph = trim(str_replace("\n\n", ' ', $text));
echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":10601}} -->' . "\n";
echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf-pdftext-dictionary-layout-order-direct-key-marker-conflict-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-direct-key-marker-conflict-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before layout/order assignment and applies order results to the selected Page objects in page order; native source-keyed sidecars must keep their key as page identity before assignment',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'selected_page' => 10601,
    'supplied_artifacts_excluded' => $suppliedArtifactsExcluded,
    'stale_payload_excluded' => $stalePayloadExcluded,
    'source_order_preserved_without_matching_order' => $sourceOrderPreserved,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
