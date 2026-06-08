<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];
$page = [
    'page' => 5,
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'rotation' => 0,
    'raw_page_payload' => 'source bbox only page payload must stay hidden',
    'blocks' => [[
        'bbox' => [0.10, 0.20, 0.50, 0.25],
        'raw_block_payload' => 'source bbox only block payload must stay hidden',
        'lines' => [[
            'bbox' => [0.10, 0.20, 0.50, 0.25],
            'baseline' => [0.10, 0.24, 0.50, 0.24],
            'spans' => [[
                'text' => 'Source bbox scaled WordPress import',
                'bbox' => [0.12, 0.205, 0.32, 0.225],
                'font' => $font,
                'char_start_idx' => 3,
                'char_end_idx' => 3,
                'chars' => [[
                    'char' => 'S',
                    'bbox' => [0.12, 0.205, 0.14, 0.225],
                    'font' => $font,
                    'char_idx' => 3,
                    'raw_char_payload' => 'source bbox only char payload must stay hidden',
                ]],
                'raw_span_payload' => 'source bbox only span payload must stay hidden',
            ]],
        ]],
    ]],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1, keepChars: true);
$pageOut = $document['pages'][0];
$block = $pageOut['blocks'][0];
$line = $block['lines'][0];
$span = $line['spans'][0];
$charBlock = $pageOut['char_blocks'][0];
$charSpan = $charBlock['lines'][0]['spans'][0];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$expectedBlockBbox = [60.0, 160.0, 300.0, 200.0];
$expectedSpanBbox = [72.0, 164.0, 192.0, 180.0];
$expectedCharBbox = [72.0, 164.0, 84.0, 180.0];
if (
    ($block['bbox'] ?? null) !== $expectedBlockBbox
    || ($line['bbox'] ?? null) !== $expectedBlockBbox
    || ($span['bbox'] ?? null) !== $expectedSpanBbox
    || (($span['chars'][0]['bbox'] ?? null) !== $expectedCharBbox)
) {
    throw new RuntimeException('Expected normalized pdftext child bboxes to fall back to source page bbox scale when width and height are absent.');
}

if (array_key_exists('width', $pageOut['pdftext_source']) || array_key_exists('height', $pageOut['pdftext_source'])) {
    throw new RuntimeException('Expected source metadata to preserve omitted pdftext width and height fields.');
}

if (str_contains($encoded, 'source bbox only page payload') || str_contains($encoded, 'source bbox only char payload')) {
    throw new RuntimeException('Expected non-core pdftext dictionary payloads to remain excluded from WordPress output.');
}

echo '<!-- markerpdf-pdftext-dictionary-source-bbox-scale-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-source-bbox-scale-currentbase',
    'source_truth' => 'pdftext dictionary_output scales normalized child bboxes by source page geometry before Marker conversion; native adapters can supply page bbox without width/height',
    'support_component' => 'pdf-text-dictionary-core',
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'page_range' => $document['page_range'],
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'source_bbox_scale_used' => true,
    'width_height_absent' => !array_key_exists('width', $pageOut['pdftext_source']) && !array_key_exists('height', $pageOut['pdftext_source']),
    'normalized_block_bbox_scaled' => ($block['bbox'] ?? null) === $expectedBlockBbox,
    'normalized_line_bbox_scaled' => ($line['bbox'] ?? null) === $expectedBlockBbox,
    'normalized_span_bbox_scaled' => ($span['bbox'] ?? null) === $expectedSpanBbox,
    'normalized_char_bbox_scaled' => ($span['chars'][0]['bbox'] ?? null) === $expectedCharBbox,
    'char_block_bbox_scaled' => ($charBlock['bbox'] ?? null) === $expectedBlockBbox,
    'char_span_bbox_scaled' => ($charSpan['bbox'] ?? null) === $expectedSpanBbox,
    'raw_payload_excluded' => !str_contains($encoded, 'source bbox only page payload') && !str_contains($encoded, 'source bbox only char payload'),
    'non_core_line_payload_excluded' => !array_key_exists('baseline', $charBlock['lines'][0]),
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $blockOut) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) $blockOut['pnum'] . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($blockOut['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
