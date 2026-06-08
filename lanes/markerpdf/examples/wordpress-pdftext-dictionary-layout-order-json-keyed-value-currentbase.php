<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdftextPage = static function (int $page, array $lines): array {
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

$jsonArtifact = static fn (array $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$path = sys_get_temp_dir() . '/markerpdf-json-keyed-value-smoke-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% JSON keyed-value layout/order smoke\n%%EOF");

try {
    $converted = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage(42, [
                ['text' => 'JSON keyed-value smoke cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $pdftextPage(43, [
                ['text' => 'Second JSON keyed-value smoke body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First JSON keyed-value smoke heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                '43' => $jsonArtifact(['image' => 'json-keyed-value-smoke-selected-layout-render']),
                '42' => $jsonArtifact(['image' => 'json-keyed-value-smoke-stale-layout-render']),
            ],
            'layout_results' => [
                '43' => $jsonArtifact([
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                ]),
                '42' => $jsonArtifact([
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                    'raw_payload' => 'stale JSON keyed-value smoke layout payload must stay hidden',
                ]),
            ],
            'order_images' => [
                '43' => $jsonArtifact(['image' => 'json-keyed-value-smoke-selected-order-render']),
                '42' => $jsonArtifact(['image' => 'json-keyed-value-smoke-stale-order-render']),
            ],
            'order_results' => [
                '43' => $jsonArtifact([
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ]),
                '42' => $jsonArtifact([
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                    'raw_payload' => 'stale JSON keyed-value smoke order payload must stay hidden',
                ]),
            ],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$encoded = json_encode($converted, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$text = $converted['text'];
$heading = '# First Json Keyed-Value Smoke Heading.';
$body = 'Second JSON keyed-value smoke body.';
$jsonKeyedValuesSelected = ($converted['metadata']['layout_plan']['assigned_pages'] ?? null) === 1
    && ($converted['metadata']['order_plan']['assigned_pages'] ?? null) === 1
    && str_contains($text, $heading)
    && str_contains($text, $body)
    && strpos($text, $heading) < strpos($text, $body);
$stalePayloadExcluded = !str_contains($text, 'JSON keyed-value smoke cover should stay skipped.')
    && !str_contains($encoded, 'stale JSON keyed-value smoke layout payload')
    && !str_contains($encoded, 'stale JSON keyed-value smoke order payload')
    && !str_contains($encoded, '__markerpdf_envelope_page_key_marker');

if (!$jsonKeyedValuesSelected || !$stalePayloadExcluded) {
    throw new RuntimeException('Expected JSON-valued source-keyed layout/order maps to select the current pdftext page only.');
}

echo json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-json-keyed-value-currentbase',
    'native_boundary' => 'raw JSON strings stored as values in source-page keyed layout/order maps are decoded before selected-page assignment',
    'upstream_boundary' => 'marker.pdf.extract_text dictionary pages are sliced before marker.layout.layout and marker.layout.order zip model outputs with selected pages',
    'supplied_boundaries' => $converted['metadata']['supplied_boundaries'] ?? [],
    'page_range' => $converted['metadata']['page_range'] ?? [],
    'json_keyed_values_selected' => $jsonKeyedValuesSelected,
    'stale_json_keyed_value_payload_excluded' => $stalePayloadExcluded,
    'layout_assigned_pages' => $converted['metadata']['layout_plan']['assigned_pages'] ?? null,
    'order_assigned_pages' => $converted['metadata']['order_plan']['assigned_pages'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'wordpress_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>First Json Keyed-Value Smoke Heading.</h1>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>Second JSON keyed-value smoke body.</p>'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
