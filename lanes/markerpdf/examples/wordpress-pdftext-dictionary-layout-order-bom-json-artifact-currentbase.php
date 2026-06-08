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

$bomJsonEnvelope = static fn (array $value): string => "\xEF\xBB\xBF" . json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$path = sys_get_temp_dir() . '/markerpdf-bom-artifact-layout-order-smoke-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% BOM artifact layout-order smoke\n%%EOF");

try {
    $converted = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage(41, [
                ['text' => 'BOM artifact cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $pdftextPage(42, [
                ['text' => 'Second BOM artifact body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First BOM artifact heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [[
                'dictionary_output' => $bomJsonEnvelope([
                    '41' => ['image' => 'bom-artifact-cover-render'],
                    '42' => ['image' => 'bom-artifact-selected-render'],
                ]),
            ]],
            'layout_results' => [[
                'dictionary_output' => $bomJsonEnvelope([
                    '41' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                        'raw_payload' => 'stale BOM layout payload must stay hidden',
                    ],
                    '42' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                    ],
                ]),
            ]],
            'order_images' => [[
                'dictionary_output' => $bomJsonEnvelope([
                    '41' => ['image' => 'bom-artifact-stale-order-render'],
                    '42' => ['image' => 'bom-artifact-selected-order-render'],
                ]),
            ]],
            'order_results' => [[
                'dictionary_output' => $bomJsonEnvelope([
                    '41' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ],
                        'raw_payload' => 'stale BOM order payload must stay hidden',
                    ],
                    '42' => [
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
$heading = '# First Bom Artifact Heading.';
$body = 'Second BOM artifact body.';
$bomArtifactsSelected = ($converted['metadata']['layout_plan']['assigned_pages'] ?? null) === 1
    && ($converted['metadata']['order_plan']['assigned_pages'] ?? null) === 1
    && str_contains($text, $heading)
    && str_contains($text, $body)
    && strpos($text, $heading) < strpos($text, $body);
$stalePayloadExcluded = !str_contains($text, 'BOM artifact cover should stay skipped.')
    && !str_contains($encoded, 'stale BOM layout payload')
    && !str_contains($encoded, 'stale BOM order payload');

if (!$bomArtifactsSelected || !$stalePayloadExcluded) {
    throw new RuntimeException('Expected BOM-prefixed JSON artifact layout/order envelopes to select the current pdftext page only.');
}

echo json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-bom-json-artifact-currentbase',
    'native_boundary' => 'UTF-8 BOM-prefixed raw JSON strings under supplied-artifact envelopes are decoded before selected-page layout/order assignment',
    'supplied_boundaries' => $converted['metadata']['supplied_boundaries'] ?? [],
    'page_range' => $converted['metadata']['page_range'] ?? [],
    'bom_json_artifact_envelopes_selected' => $bomArtifactsSelected,
    'stale_bom_json_artifact_payload_excluded' => $stalePayloadExcluded,
    'layout_assigned_pages' => $converted['metadata']['layout_plan']['assigned_pages'] ?? null,
    'order_assigned_pages' => $converted['metadata']['order_plan']['assigned_pages'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'wordpress_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>First Bom Artifact Heading.</h1>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>Second BOM artifact body.</p>'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
