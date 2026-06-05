<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$font = [
    'name' => 'Helvetica',
    'flags' => 0,
    'weight' => 400,
    'size' => 11.0,
    'embedded_font_program' => 'hidden span font payload',
];

$page = [
    'page' => 23,
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'width' => 600.0,
    'height' => 800.0,
    'rotation' => 0,
    'blocks' => [[
        'bbox' => [0.12, 0.12, 0.54, 0.15],
        'lines' => [[
            'bbox' => [0.12, 0.12, 0.54, 0.15],
            'spans' => [[
                'text' => "Character dictionary import\n",
                'bbox' => [0.12, 0.12, 0.54, 0.15],
                'font' => $font,
                'rotation' => 0,
                'char_start_idx' => 0,
                'char_end_idx' => 27,
                'chars' => [
                    [
                        'char' => 'C',
                        'c' => 'legacy alias should not cross the boundary',
                        'bbox' => [0.12, 0.12, 0.13, 0.15],
                        'rotation' => 0,
                        'font' => $font + ['raw_font_stream' => 'hidden character font payload'],
                        'char_idx' => 0,
                    ],
                    [
                        'char' => 'h',
                        'bbox' => [0.13, 0.12, 0.14, 0.15],
                        'rotation' => 0,
                        'font' => $font + ['debug_payload' => ['stream' => 'hidden']],
                        'char_idx' => 1,
                    ],
                ],
            ]],
        ]],
    ]],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1, keepChars: true);
$span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0] ?? [];
$charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

if (array_key_exists('c', $span['chars'][0] ?? [])
    || str_contains($encoded, 'legacy alias should not cross the boundary')
    || str_contains($encoded, 'embedded_font_program')
    || str_contains($encoded, 'raw_font_stream')
    || str_contains($encoded, 'debug_payload')
) {
    throw new RuntimeException('Expected pdftext keep_chars character dictionaries to keep only upstream-shaped keys.');
}

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));

echo '<!-- markerpdf-pdftext-dictionary-char-core-boundary-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-char-core-boundary-currentbase',
    'source_truth' => 'pdftext.dictionary_output keep_chars emits per-character dictionaries with char, bbox, rotation, font, and char_idx; markerPDF stores those blocks as review metadata before WordPress import',
    'support_component' => 'pdf-text-dictionary-core',
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'char_keys' => array_keys($span['chars'][0] ?? []),
    'char_font_keys' => array_keys($span['chars'][0]['font'] ?? []),
    'char_blocks_span_font_keys' => array_keys($charSpan['font'] ?? []),
    'legacy_c_alias_excluded' => !array_key_exists('c', $span['chars'][0] ?? []),
    'font_payload_excluded' => !str_contains($encoded, 'embedded_font_program')
        && !str_contains($encoded, 'raw_font_stream')
        && !str_contains($encoded, 'debug_payload'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
