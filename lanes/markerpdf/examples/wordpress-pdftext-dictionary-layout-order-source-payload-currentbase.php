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

$coverPage = $page(1700, [
    ['text' => 'Source payload converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);
$selectedPage = $page(1701, [
    ['text' => 'Second converter source payload column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First converter source payload column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);

$staleSourcePayload = $page(1700, [
    ['text' => 'Stale source payload must not reach WordPress paragraphs or metadata.', 'bbox' => [72.0, 160.0, 520.0, 174.0]],
]);

$path = sys_get_temp_dir() . '/markerpdf-source-payload-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% source-payload pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['metadata' => ['document_page' => 1701], 'source' => $staleSourcePayload, 'image' => 'source-payload-layout-render'],
            ],
            'layout_results' => [[
                'metadata' => ['document_page' => 1701],
                'source' => $staleSourcePayload,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                ],
            ]],
            'order_images' => [
                ['metadata' => ['document_page' => 1701], 'source' => $staleSourcePayload, 'image' => 'source-payload-order-render'],
            ],
            'order_results' => [[
                'metadata' => ['document_page' => 1701],
                'source' => $staleSourcePayload,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                    ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
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
$firstBeforeSecond = strpos($text, 'First converter source payload column.') < strpos($text, 'Second converter source payload column.');
$payloadExcluded = !str_contains($text, 'Stale source payload')
    && !str_contains($encoded, 'Stale source payload');

if (
    !$firstBeforeSecond
    || !$payloadExcluded
    || ($result['metadata']['supplied_boundaries'] ?? []) !== ['layout', 'order']
    || ($result['metadata']['layout_plan']['assigned_pages'] ?? null) !== 1
    || ($result['metadata']['order_plan']['assigned_pages'] ?? null) !== 1
) {
    throw new RuntimeException('Expected trusted metadata to align layout/order while copied source pdftext payloads remain fallback-only.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-source-payload-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-source-payload-currentbase',
    'source_truth' => 'markerPDF selects pdftext dictionary pages before layout/order assignment; copied source pdftext page payloads are fallback-only when adapter metadata carries trusted selected-page identity',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'selected_page' => 1701,
    'layout_artifact_assigned' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1,
    'order_artifact_assigned' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'visible_columns_in_reading_order' => $firstBeforeSecond,
    'source_payload_fallback_only' => $payloadExcluded,
    'cover_excluded' => !str_contains($text, 'Source payload converter cover should stay skipped.'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
