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
                        'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$jsonEnvelope = static fn (array $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$path = sys_get_temp_dir() . '/markerpdf-json-artifact-layout-order-smoke-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% JSON artifact layout-order smoke\n%%EOF");

try {
    $converted = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage(31, [
                ['text' => 'JSON artifact cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $pdftextPage(32, [
                ['text' => 'Second JSON artifact body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First JSON artifact heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [[
                'dictionary_output' => $jsonEnvelope([
                    '31' => ['image' => 'json-artifact-cover-render'],
                    '32' => ['image' => 'json-artifact-selected-render'],
                ]),
            ]],
            'layout_results' => [[
                'dictionary_output' => $jsonEnvelope([
                    '31' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                        'raw_payload' => 'stale JSON layout payload must stay hidden',
                    ],
                    '32' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                    ],
                ]),
            ]],
            'order_images' => [[
                'dictionary_output' => $jsonEnvelope([
                    '31' => ['image' => 'json-artifact-stale-order-render'],
                    '32' => ['image' => 'json-artifact-selected-order-render'],
                ]),
            ]],
            'order_results' => [[
                'dictionary_output' => $jsonEnvelope([
                    '31' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ],
                        'raw_payload' => 'stale JSON order payload must stay hidden',
                    ],
                    '32' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                    ],
                ]),
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$encoded = json_encode($converted, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$text = $converted['text'];
$heading = '# First Json Artifact Heading.';
$body = 'Second JSON artifact body.';
$jsonArtifactsSelected = ($converted['metadata']['layout_plan']['assigned_pages'] ?? null) === 1
    && ($converted['metadata']['order_plan']['assigned_pages'] ?? null) === 1
    && str_contains($text, $heading)
    && str_contains($text, $body)
    && strpos($text, $heading) < strpos($text, $body);
$stalePayloadExcluded = !str_contains($text, 'JSON artifact cover should stay skipped.')
    && !str_contains($encoded, 'stale JSON layout payload')
    && !str_contains($encoded, 'stale JSON order payload');

if (!$jsonArtifactsSelected || !$stalePayloadExcluded) {
    throw new RuntimeException('Expected JSON artifact layout/order envelopes to select the current pdftext page only.');
}

echo json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-json-artifact-currentbase',
    'native_boundary' => 'raw JSON strings under dictionary_output supplied-artifact envelopes are decoded before selected-page layout/order assignment',
    'supplied_boundaries' => $converted['metadata']['supplied_boundaries'] ?? [],
    'page_range' => $converted['metadata']['page_range'] ?? [],
    'json_artifact_envelopes_selected' => $jsonArtifactsSelected,
    'stale_json_artifact_payload_excluded' => $stalePayloadExcluded,
    'layout_assigned_pages' => $converted['metadata']['layout_plan']['assigned_pages'] ?? null,
    'order_assigned_pages' => $converted['metadata']['order_plan']['assigned_pages'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'wordpress_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>First Json Artifact Heading.</h1>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>Second JSON artifact body.</p>'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
