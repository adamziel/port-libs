<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$font = ['name' => 'Helvetica', 'flags' => null, 'weight' => 400, 'size' => 11.0];
$page = [
    'page' => 6,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'blocks' => [[
        'bbox' => [-0.06, 0.10, 1.32, 0.14],
        'lines' => [[
            'bbox' => [-0.06, 0.10, 1.32, 0.14],
            'spans' => [[
                'text' => 'Off-page glyph boxes remain reviewable',
                'bbox' => [-0.04, 0.105, 1.30, 0.135],
                'font' => $font,
                'chars' => [[
                    'char' => 'x',
                    'bbox' => [1.28, 0.106, 1.30, 0.135],
                    'rotation' => 0,
                    'font' => $font,
                    'char_idx' => 37,
                ]],
            ]],
        ]],
    ]],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1, keepChars: true);
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0] ?? [];
$charBlock = $document['pages'][0]['char_blocks'][0] ?? [];
$charSpan = $charBlock['lines'][0]['spans'][0] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$expectedBlockBbox = [-36.7, 79.2, 807.8, 110.9];
$expectedSpanBbox = [-24.5, 83.2, 795.6, 106.9];
$expectedCharBbox = [783.4, 84.0, 795.6, 106.9];

if (($charBlock['bbox'] ?? null) !== $expectedBlockBbox
    || ($span['bbox'] ?? null) !== $expectedSpanBbox
    || (($span['chars'][0]['bbox'] ?? null) !== $expectedCharBbox)
    || str_contains($encoded, 'raw_pdf_bytes')
) {
    throw new RuntimeException('Expected off-page normalized pdftext bboxes to scale into page review coordinates.');
}

echo '<!-- markerpdf-pdftext-dictionary-offpage-bbox-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-offpage-bbox-currentbase',
    'source_truth' => 'pdftext.extraction.dictionary_output calls unnormalize_bbox on block, line, span, and kept character bboxes without clamping off-page normalized coordinates',
    'support_component' => 'pdf-text-dictionary-core',
    'page_range' => $document['page_range'],
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'char_block_bbox' => $charBlock['bbox'] ?? null,
    'line_bbox' => $charBlock['lines'][0]['bbox'] ?? null,
    'span_bbox' => $span['bbox'] ?? null,
    'kept_char_bbox' => $span['chars'][0]['bbox'] ?? null,
    'offpage_block_bbox_scaled' => ($charBlock['bbox'] ?? null) === $expectedBlockBbox,
    'offpage_span_bbox_scaled' => ($span['bbox'] ?? null) === $expectedSpanBbox,
    'offpage_char_bbox_scaled' => ($span['chars'][0]['bbox'] ?? null) === $expectedCharBbox,
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) $block['pnum'] . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
