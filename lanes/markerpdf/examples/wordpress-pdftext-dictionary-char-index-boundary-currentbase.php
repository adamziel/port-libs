<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];
$page = [
    'page' => 33,
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'width' => 600.0,
    'height' => 800.0,
    'rotation' => 0,
    'blocks' => [[
        'bbox' => [0.12, 0.18, 0.40, 0.21],
        'lines' => [[
            'bbox' => [0.12, 0.18, 0.40, 0.21],
            'spans' => [[
                'text' => "Indexed dictionary text\n",
                'bbox' => [0.12, 0.18, 0.40, 0.21],
                'font' => $font,
                'rotation' => 0,
                'char_start_idx' => 3.0,
                'char_end_idx' => 25.0,
                'chars' => [
                    ['char' => 'I', 'bbox' => [0.12, 0.18, 0.13, 0.21], 'char_idx' => 3.0],
                    ['char' => 'n', 'bbox' => [0.13, 0.18, 0.14, 0.21], 'char_idx' => 4.0],
                ],
            ]],
        ]],
    ]],
];

$extractor = new PdfTextDocumentExtractor();
$document = $extractor->getTextBlocks([$page], maxPages: 1, keepChars: true);
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0] ?? [];
$charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0] ?? [];

$fractionalStart = $page;
$fractionalStart['blocks'][0]['lines'][0]['spans'][0]['char_start_idx'] = 3.5;
$fractionalStartRejected = false;
try {
    $extractor->getTextBlocks([$fractionalStart], maxPages: 1, keepChars: true);
} catch (InvalidArgumentException) {
    $fractionalStartRejected = true;
}

$fractionalEnd = $page;
$fractionalEnd['blocks'][0]['lines'][0]['spans'][0]['char_end_idx'] = 25.25;
$fractionalEndRejected = false;
try {
    $extractor->getTextBlocks([$fractionalEnd], maxPages: 1, keepChars: true);
} catch (InvalidArgumentException) {
    $fractionalEndRejected = true;
}

$fractionalChar = $page;
$fractionalChar['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char_idx'] = 3.25;
$fractionalCharRejected = false;
try {
    $extractor->getTextBlocks([$fractionalChar], maxPages: 1, keepChars: true);
} catch (InvalidArgumentException) {
    $fractionalCharRejected = true;
}

if (!$fractionalStartRejected || !$fractionalEndRejected || !$fractionalCharRejected) {
    throw new RuntimeException('Expected fractional pdftext dictionary character indexes to be rejected.');
}

echo '<!-- markerpdf-pdftext-dictionary-char-index-boundary-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-char-index-boundary-currentbase',
    'source_truth' => 'pdftext.schema.Span char_start_idx/char_end_idx and Char char_idx are integer dictionary fields before markerPDF page conversion',
    'support_component' => 'pdf-text-dictionary-core',
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'span_char_start_idx' => $span['char_start_idx'] ?? null,
    'span_char_end_idx' => $span['char_end_idx'] ?? null,
    'char_indexes' => array_map(static fn (array $char): mixed => $char['char_idx'] ?? null, $span['chars'] ?? []),
    'char_blocks_indexes' => array_map(static fn (array $char): mixed => $char['char_idx'] ?? null, $charSpan['chars'] ?? []),
    'integral_float_indexes_normalized' => ($span['char_start_idx'] ?? null) === 3
        && ($span['char_end_idx'] ?? null) === 25
        && (($span['chars'][0]['char_idx'] ?? null) === 3)
        && (($charSpan['chars'][1]['char_idx'] ?? null) === 4),
    'fractional_span_start_rejected' => $fractionalStartRejected,
    'fractional_span_end_rejected' => $fractionalEndRejected,
    'fractional_char_index_rejected' => $fractionalCharRejected,
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
