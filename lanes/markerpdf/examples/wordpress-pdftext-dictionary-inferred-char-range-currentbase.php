<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];
$page = [
    'page' => 37,
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'width' => 600.0,
    'height' => 800.0,
    'rotation' => 0,
    'blocks' => [[
        'bbox' => [0.12, 0.22, 0.48, 0.25],
        'lines' => [[
            'bbox' => [0.12, 0.22, 0.48, 0.25],
            'spans' => [[
                'text' => "Inferred span range\n",
                'bbox' => [0.12, 0.22, 0.48, 0.25],
                'font' => $font,
                'rotation' => 0,
                'chars' => [
                    [
                        'char' => 'I',
                        'bbox' => [0.12, 0.22, 0.13, 0.25],
                        'font' => $font,
                        'rotation' => 0,
                        'char_idx' => 30,
                        'raw_payload' => 'missing span range payload must stay hidden',
                    ],
                    [
                        'char' => 'n',
                        'bbox' => [0.13, 0.22, 0.14, 0.25],
                        'font' => $font,
                        'rotation' => 0,
                        'char_idx' => 31,
                    ],
                ],
            ]],
        ]],
    ]],
];

$extractor = new PdfTextDocumentExtractor();
$document = $extractor->getTextBlocks([$page], maxPages: 1, keepChars: true);
$span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0] ?? [];
$charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0] ?? [];
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$invertedCharacterOrder = $page;
$invertedCharacterOrder['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char_idx'] = 32;
$invertedCharacterOrder['blocks'][0]['lines'][0]['spans'][0]['chars'][1]['char_idx'] = 31;
$invertedCharacterOrderRejected = false;
try {
    $extractor->getTextBlocks([$invertedCharacterOrder], maxPages: 1, keepChars: true);
} catch (InvalidArgumentException) {
    $invertedCharacterOrderRejected = true;
}

$rangeInferred = ($span['char_start_idx'] ?? null) === 30
    && ($span['char_end_idx'] ?? null) === 31
    && ($charSpan['char_start_idx'] ?? null) === 30
    && ($charSpan['char_end_idx'] ?? null) === 31;
$payloadExcluded = !str_contains($encoded, 'missing span range payload must stay hidden');
$visibleText = $blocks[0]['text'] ?? '';

if (!$rangeInferred || !$payloadExcluded || !$invertedCharacterOrderRejected || $visibleText !== 'Inferred span range') {
    throw new RuntimeException('Expected kept-character indexes to infer the parent pdftext span range before WordPress rendering.');
}

echo '<!-- markerpdf-pdftext-dictionary-inferred-char-range-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-inferred-char-range-currentbase',
    'source_truth' => 'pdftext inference derives span char_start_idx and char_end_idx from the first and last kept character indexes before markerPDF page conversion',
    'support_component' => 'pdf-text-dictionary-core',
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'visible_wordpress_text' => $visibleText,
    'span_char_start_idx' => $span['char_start_idx'] ?? null,
    'span_char_end_idx' => $span['char_end_idx'] ?? null,
    'char_indexes' => array_map(static fn (array $char): mixed => $char['char_idx'] ?? null, $span['chars'] ?? []),
    'char_blocks_span_range_inferred' => $rangeInferred,
    'payload_excluded' => $payloadExcluded,
    'inverted_character_order_rejected' => $invertedCharacterOrderRejected,
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
