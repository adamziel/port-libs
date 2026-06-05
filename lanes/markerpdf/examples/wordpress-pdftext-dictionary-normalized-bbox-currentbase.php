<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'page' => 5,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'blocks' => [[
        'bbox' => [0.10, 0.20, 0.70, 0.24],
        'pdfium_block_type' => 'text',
        'lines' => [[
            'bbox' => [0.10, 0.20, 0.70, 0.24],
            'baseline' => [0.10, 0.23, 0.70, 0.23],
            'spans' => [[
                'text' => 'Normalized bbox dictionary import',
                'bbox' => [0.12, 0.205, 0.32, 0.225],
                'font' => ['name' => 'Helvetica', 'flags' => null, 'weight' => 400, 'size' => 11.0],
                'chars' => [['char' => 'N', 'bbox' => [0.12, 0.205, 0.14, 0.225]]],
            ]],
        ]],
    ]],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
$span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
$charBlock = $document['pages'][0]['char_blocks'][0];
$charSpan = $charBlock['lines'][0]['spans'][0];
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));

$expectedBlockBbox = [61.2, 158.4, 428.4, 190.1];
$expectedSpanBbox = [73.4, 162.4, 195.8, 178.2];
if (($charBlock['bbox'] ?? null) !== $expectedBlockBbox || ($span['bbox'] ?? null) !== $expectedSpanBbox) {
    throw new RuntimeException('Expected normalized pdftext child bboxes to be scaled by page width and height.');
}

echo '<!-- markerpdf-pdftext-dictionary-normalized-bbox-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-normalized-bbox-currentbase',
    'source_truth' => 'pdftext.extraction.dictionary_output scales block, line, and span bboxes with unnormalize_bbox(page_width,page_height) before markerPDF converts page dictionaries',
    'support_component' => 'pdf-text-dictionary-core',
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'page_range' => $document['page_range'],
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'char_block_bbox' => $charBlock['bbox'] ?? null,
    'line_bbox' => $charBlock['lines'][0]['bbox'] ?? null,
    'span_bbox' => $span['bbox'] ?? null,
    'char_span_bbox' => $charSpan['bbox'] ?? null,
    'normalized_block_bbox_scaled' => ($charBlock['bbox'] ?? null) === $expectedBlockBbox,
    'normalized_span_bbox_scaled' => ($span['bbox'] ?? null) === $expectedSpanBbox,
    'raw_chars_excluded' => !array_key_exists('chars', $charSpan),
    'non_core_line_payload_excluded' => !array_key_exists('baseline', $charBlock['lines'][0]),
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) $block['pnum'] . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
