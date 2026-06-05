<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\LayoutAnnotator;
use PortLibs\MarkerPDF\MarkdownPostProcessor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'pnum' => 811,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'blocks' => [
        [
            'bbox' => [72.0, 104.0, 280.0, 126.0],
            'lines' => [
                [
                    'text' => 'Selected layout payload boundary paragraph.',
                    'bbox' => [72.0, 104.0, 280.0, 126.0],
                    'spans' => [[
                        'text' => 'Selected layout payload boundary paragraph.',
                        'bbox' => [72.0, 104.0, 280.0, 126.0],
                    ]],
                ],
            ],
        ],
    ],
];

$layout = [
    'metadata' => [
        'page' => 811,
        'raw_private_payload' => 'hidden layout adapter payload',
    ],
    'pdftext' => [
        'page' => 810,
        'blocks' => [[
            'lines' => [[
                'spans' => [[
                    'text' => 'Stale nested pdftext layout payload must stay hidden.',
                    'bbox' => [72.0, 160.0, 500.0, 174.0],
                ]],
            ]],
        ]],
    ],
    'blocks' => [[
        'lines' => [[
            'spans' => [[
                'text' => 'Raw layout block payload must stay hidden.',
                'bbox' => [72.0, 180.0, 500.0, 194.0],
            ]],
        ]],
    ]],
    'segmentation_map' => 'hidden layout segmentation payload',
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Text', 'bbox' => [60.0, 96.0, 300.0, 144.0]],
    ],
];

$annotator = new LayoutAnnotator();
$detected = $annotator->runWithSuppliedLayouts(
    [
        [
            'metadata' => ['page' => 811],
            'pdftext' => ['page' => 810, 'blocks' => []],
            'image' => 'selected-layout-render',
            'raw_render_payload' => 'hidden rendered page payload',
        ],
    ],
    [$page],
    [$layout]
);
$annotated = $annotator->annotateBlockTypes($detected['pages']);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($annotated));
$encoded = json_encode($detected, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$visibleText = $blocks[0]['text'] ?? '';
$layoutMetadata = $detected['pages'][0]['layout'] ?? [];

if (
    ($layoutMetadata['page'] ?? null) !== 811
    || array_key_exists('metadata', $layoutMetadata)
    || array_key_exists('pdftext', $layoutMetadata)
    || array_key_exists('blocks', $layoutMetadata)
    || array_key_exists('segmentation_map', $layoutMetadata)
    || str_contains($encoded, 'hidden layout adapter payload')
    || str_contains($encoded, 'Stale nested pdftext layout payload')
    || str_contains($encoded, 'Raw layout block payload')
    || str_contains($encoded, 'hidden rendered page payload')
    || !str_contains($visibleText, 'Selected layout payload boundary paragraph.')
) {
    throw new RuntimeException('Expected matched layout geometry and page marker to survive while nested adapter payloads stay hidden.');
}

echo '<!-- markerpdf-pdftext-dictionary-layout-sanitized-payload-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-sanitized-payload-currentbase',
    'source_truth' => 'markerPDF attaches Surya layout predictions to selected pdftext pages before annotation; native supplied layout adapters may carry page identity, but nested pdftext/page payload copies are not layout metadata',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'selected_page' => $layoutMetadata['page'] ?? null,
    'layout_page_marker_preserved' => ($layoutMetadata['page'] ?? null) === 811,
    'layout_geometry_preserved' => count($layoutMetadata['bboxes'] ?? []) === 1,
    'layout_payload_excluded' => !str_contains($encoded, 'hidden layout adapter payload')
        && !str_contains($encoded, 'Stale nested pdftext layout payload')
        && !str_contains($encoded, 'Raw layout block payload')
        && !str_contains($encoded, 'hidden rendered page payload'),
    'visible_wordpress_text' => $visibleText,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars((string) ($block['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
