<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'page' => 33,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'blocks' => [[
        'bbox' => [72.0, 96.0, 420.0, 110.0],
        'lines' => [[
            'bbox' => [72.0, 96.0, 420.0, 110.0],
            'spans' => [[
                'text' => 'Quote loosebox option remains review metadata.',
                'bbox' => [72.0, 96.0, 420.0, 110.0],
                'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
            ]],
        ]],
    ]],
];

$extractor = new PdfTextDocumentExtractor();
$defaultDocument = $extractor->getTextBlocks([$page], maxPages: 1);
$strictDocument = $extractor->getTextBlocks([$page], maxPages: 1, quoteLoosebox: false);
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($strictDocument['pages']));

$defaultRecorded = ($defaultDocument['metadata']['pdftext_options']['quote_loosebox'] ?? null) === true;
$strictRecorded = ($strictDocument['metadata']['pdftext_options']['quote_loosebox'] ?? null) === false;
$visibleText = $blocks[0]['text'] ?? '';

if (!$defaultRecorded || !$strictRecorded || $visibleText !== 'Quote loosebox option remains review metadata.') {
    throw new RuntimeException('Expected pdftext quote_loosebox options to remain reviewable without changing supplied page text.');
}

echo '<!-- markerpdf-pdftext-dictionary-quote-loosebox-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-quote-loosebox-currentbase',
    'source_truth' => 'pdftext.dictionary_output defaults quote_loosebox=True and forwards explicit False into page extraction before markerPDF page conversion',
    'support_component' => 'pdf-text-dictionary-core',
    'default_quote_loosebox_recorded' => $defaultRecorded,
    'strict_quote_loosebox_recorded' => $strictRecorded,
    'default_pdftext_options' => $defaultDocument['metadata']['pdftext_options'],
    'strict_pdftext_options' => $strictDocument['metadata']['pdftext_options'],
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
