<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];
$page = [
    'page' => 77,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'blocks' => [[
        'bbox' => [72.0, 96.0, 330.0, 110.0],
        'lines' => [[
            'bbox' => [72.0, 96.0, 330.0, 110.0],
            'spans' => [
                [
                    'text' => 'Skewed ',
                    'bbox' => [72.0, 96.0, 120.0, 110.0],
                    'font' => $font,
                    'rotation' => 12.5,
                    'raw_rotation_payload' => 'hidden span rotation payload',
                ],
                [
                    'text' => 'glyph angle',
                    'bbox' => [120.0, 96.0, 198.0, 110.0],
                    'font' => $font,
                    'rotation' => 270.0,
                    'url' => 'https://example.com/skewed-glyph',
                ],
                [
                    'text' => ' review',
                    'bbox' => [198.0, 96.0, 258.0, 110.0],
                    'font' => $font,
                    'rotation' => 0.0,
                ],
            ],
        ]],
    ]],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$spans = $document['pages'][0]['blocks'][0]['lines'][0]['spans'] ?? [];
$charSpans = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$fractionalSpanRotationPreserved = ($spans[0]['rotation'] ?? null) === 12.5
    && ($charSpans[0]['rotation'] ?? null) === 12.5;
$integralFloatRotationNormalized = ($spans[1]['rotation'] ?? null) === 270
    && ($charSpans[1]['rotation'] ?? null) === 270.0;
$linkStillPromoted = ($spans[1]['url'] ?? null) === 'https://example.com/skewed-glyph';
$payloadExcluded = !str_contains($encoded, 'hidden span rotation payload');

if (!$fractionalSpanRotationPreserved || !$integralFloatRotationNormalized || !$linkStillPromoted || !$payloadExcluded) {
    throw new RuntimeException('Expected pdftext span rotation metadata to survive without truncating fractional angles.');
}

echo '<!-- markerpdf-pdftext-dictionary-span-rotation-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-span-rotation-currentbase',
    'source_truth' => 'pdftext inference stores first-character rotation on each span and dictionary_output leaves span rotation metadata intact before Marker page conversion',
    'support_component' => 'pdf-text-dictionary-core',
    'page_range' => $document['page_range'],
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'span_rotations' => array_map(static fn (array $span): mixed => $span['rotation'] ?? null, $spans),
    'char_block_span_rotations' => array_map(static fn (array $span): mixed => $span['rotation'] ?? null, $charSpans),
    'fractional_span_rotation_preserved' => $fractionalSpanRotationPreserved,
    'integral_float_span_rotation_normalized' => $integralFloatRotationNormalized,
    'safe_link_still_promoted' => $linkStillPromoted,
    'span_rotation_payload_excluded' => $payloadExcluded,
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
