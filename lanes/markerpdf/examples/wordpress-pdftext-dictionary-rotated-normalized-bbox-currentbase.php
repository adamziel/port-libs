<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];
$page = [
    'page' => 44,
    'bbox' => [600.0, 800.0, 0.0, 0.0],
    'width' => 800.0,
    'height' => 600.0,
    'rotation' => 90,
    'raw_rotated_payload' => 'rotated normalized page payload must not cross dictionary_output',
    'blocks' => [[
        'bbox' => [0.10, 0.20, 0.30, 0.23],
        'lines' => [[
            'bbox' => [0.10, 0.20, 0.30, 0.23],
            'spans' => [[
                'text' => "Rotated normalized bbox\n",
                'bbox' => [0.10, 0.20, 0.30, 0.23],
                'font' => $font,
                'raw_span_payload' => 'rotated normalized span payload must not cross dictionary_output',
            ]],
            'raw_line_payload' => 'rotated normalized line payload must not cross dictionary_output',
        ]],
        'raw_block_payload' => 'rotated normalized block payload must not cross dictionary_output',
    ]],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
$markerPage = $document['pages'][0] ?? [];
$span = $markerPage['blocks'][0]['lines'][0]['spans'][0] ?? [];
$charSpan = $markerPage['char_blocks'][0]['lines'][0]['spans'][0] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$visibleText = $blocks[0]['text'] ?? '';

$expectedBbox = [60.0, 160.0, 180.0, 184.0];
$rotatedBboxScaledFromSourcePage = ($span['bbox'] ?? null) === $expectedBbox
    && ($charSpan['bbox'] ?? null) === $expectedBbox;
$payloadExcluded = !str_contains($encoded, 'rotated normalized page payload')
    && !str_contains($encoded, 'rotated normalized block payload')
    && !str_contains($encoded, 'rotated normalized line payload')
    && !str_contains($encoded, 'rotated normalized span payload');

if (
    !$rotatedBboxScaledFromSourcePage
    || ($markerPage['bbox'] ?? null) !== [0.0, 0.0, 800.0, 600.0]
    || $visibleText !== 'Rotated normalized bbox'
    || !$payloadExcluded
) {
    throw new RuntimeException('Expected rotated normalized pdftext bboxes to scale from source page bbox dimensions before WordPress rendering.');
}

echo '<!-- markerpdf-pdftext-dictionary-rotated-normalized-bbox-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-rotated-normalized-bbox-currentbase',
    'source_truth' => 'pdftext.dictionary_output processes child bboxes before swapping page width/height for 90/270 degree pages; native supplied dictionaries scale normalized children from the source page bbox extent',
    'support_component' => 'pdf-text-dictionary-core',
    'source_pdftext_bbox' => $markerPage['pdftext_source']['bbox'] ?? null,
    'source_pdftext_width' => $markerPage['pdftext_source']['width'] ?? null,
    'source_pdftext_height' => $markerPage['pdftext_source']['height'] ?? null,
    'rendered_marker_bbox' => $markerPage['bbox'] ?? null,
    'span_bbox' => $span['bbox'] ?? null,
    'char_blocks_span_bbox' => $charSpan['bbox'] ?? null,
    'rotated_bbox_scaled_from_source_page' => $rotatedBboxScaledFromSourcePage,
    'payload_excluded' => $payloadExcluded,
    'visible_wordpress_text' => $visibleText,
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
