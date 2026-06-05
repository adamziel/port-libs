<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];
$page = [
    'page' => 31,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'blocks' => [[
        'bbox' => [72.0, 104.0, 190.0, 118.0],
        'lines' => [[
            'bbox' => [72.0, 104.0, 190.0, 118.0],
            'spans' => [
                [
                    'text' => 'H',
                    'bbox' => [72.0, 104.0, 80.0, 118.0],
                    'font' => $font,
                ],
                [
                    'text' => " 2 \n",
                    'bbox' => [80.0, 100.0, 88.0, 110.0],
                    'font' => $font,
                    'superscript' => true,
                    'raw_script_payload' => 'script payload should not cross dictionary_output',
                ],
                [
                    'text' => 'O',
                    'bbox' => [88.0, 104.0, 98.0, 118.0],
                    'font' => $font,
                ],
                [
                    'text' => " i \n",
                    'bbox' => [110.0, 112.0, 118.0, 122.0],
                    'font' => $font,
                    'subscript' => true,
                ],
                [
                    'text' => ' marker import',
                    'bbox' => [118.0, 104.0, 190.0, 118.0],
                    'font' => $font,
                ],
            ],
        ]],
    ]],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
$spans = $document['pages'][0]['blocks'][0]['lines'][0]['spans'] ?? [];
$charSpans = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$text = $blocks[0]['text'] ?? '';

if (($spans[1]['has_superscript'] ?? null) !== true
    || ($spans[3]['has_subscript'] ?? null) !== true
    || ($charSpans[1]['superscript'] ?? null) !== true
    || ($charSpans[3]['subscript'] ?? null) !== true
    || ($spans[1]['text'] ?? null) !== '2'
    || ($spans[3]['text'] ?? null) !== 'i'
    || $text !== 'H2Oi marker import'
    || str_contains($encoded, 'script payload should not cross dictionary_output')
) {
    throw new RuntimeException('Expected pdftext dictionary superscript/subscript metadata to survive as review metadata without raw payload leakage.');
}

echo '<!-- markerpdf-pdftext-dictionary-script-style-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-script-style-currentbase',
    'source_truth' => 'Current Marker PdfProvider maps pdftext span superscript/subscript flags to script review metadata and trims script span text before WordPress-visible text composition.',
    'support_component' => 'pdf-text-dictionary-core',
    'visible_wordpress_text' => $text,
    'superscript_preserved' => ($spans[1]['has_superscript'] ?? null) === true,
    'subscript_preserved' => ($spans[3]['has_subscript'] ?? null) === true,
    'char_blocks_script_flags_preserved' => ($charSpans[1]['superscript'] ?? null) === true
        && ($charSpans[3]['subscript'] ?? null) === true,
    'script_text_trimmed' => ($spans[1]['text'] ?? null) === '2' && ($spans[3]['text'] ?? null) === 'i',
    'script_payload_excluded' => !str_contains($encoded, 'script payload should not cross dictionary_output'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
