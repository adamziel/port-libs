<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validEAcute = (string) hex2bin('c3a9');
$validATilde = (string) hex2bin('c3a3');
$font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];

$page = [
    'page' => 63,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'blocks' => [[
        'bbox' => [72.0, 96.0, 430.0, 112.0],
        'lines' => [[
            'bbox' => [72.0, 96.0, 430.0, 112.0],
            'spans' => [[
                'text' => "Plugin caf{$validEAcute} import keeps S{$validATilde}o Paulo text.\n",
                'bbox' => [72.0, 96.0, 430.0, 112.0],
                'font' => $font,
                'char_start_idx' => 0,
                'char_end_idx' => 1,
                'chars' => [
                    ['char' => 'P', 'bbox' => [72.0, 96.0, 78.0, 112.0], 'font' => $font, 'char_idx' => 0],
                    ['char' => 'l', 'bbox' => [78.0, 96.0, 84.0, 112.0], 'font' => $font, 'char_idx' => 1],
                ],
            ]],
        ]],
    ]],
];

$extractor = new PdfTextDocumentExtractor();
$invalidSpanTextRejected = false;
$invalidCharTextRejected = false;

$invalidSpanText = $page;
$invalidSpanText['blocks'][0]['lines'][0]['spans'][0]['text'] = 'Invalid ' . chr(0xC3) . chr(0x28) . " span\n";
try {
    $extractor->getTextBlocks([$invalidSpanText], maxPages: 1);
} catch (InvalidArgumentException) {
    $invalidSpanTextRejected = true;
}

$invalidCharText = $page;
$invalidCharText['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char'] = chr(0xC3) . chr(0x28);
try {
    $extractor->getTextBlocks([$invalidCharText], maxPages: 1, keepChars: true);
} catch (InvalidArgumentException) {
    $invalidCharTextRejected = true;
}

$document = $extractor->getTextBlocks([$page], maxPages: 1, keepChars: true);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
$visibleText = $blocks[0]['text'] ?? '';
$char = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0]['chars'][0]['char'] ?? null;

if (!$invalidSpanTextRejected || !$invalidCharTextRejected || $char !== 'P') {
    throw new RuntimeException('Expected invalid pdftext UTF-8 strings to be rejected before WordPress rendering.');
}
if (!str_contains($visibleText, "Plugin caf{$validEAcute} import keeps S{$validATilde}o Paulo text.")) {
    throw new RuntimeException('Expected valid UTF-8 pdftext dictionary text to remain importable.');
}

echo '<!-- markerpdf-pdftext-dictionary-utf8-text-boundary-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-utf8-text-boundary-currentbase',
    'source_truth' => 'pdftext.dictionary_output returns Python Unicode strings after postprocessing before markerPDF converts page dictionaries',
    'support_component' => 'pdf-text-dictionary-core-utf8-boundary',
    'page_range' => $document['page_range'],
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'invalid_span_text_rejected' => $invalidSpanTextRejected,
    'invalid_char_text_rejected' => $invalidCharTextRejected,
    'valid_utf8_text_imported' => str_contains($visibleText, "Plugin caf{$validEAcute} import"),
    'kept_char_utf8_preserved' => $char === 'P',
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) $block['pnum'] . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
