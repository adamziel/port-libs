<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$font = ['name' => 'Courier', 'flags' => 1 << 0, 'weight' => 400, 'size' => 10.0];
$page = [
    'page' => 21,
    'bbox' => [0.0, 0.0, 640.0, 480.0],
    'width' => 640.0,
    'height' => 480.0,
    'rotation' => 0,
    'blocks' => [[
        'bbox' => [0.20, 0.30, 0.46, 0.34],
        'lines' => [[
            'bbox' => [0.20, 0.30, 0.46, 0.34],
            'spans' => [[
                'text' => "Minimal chars\n",
                'bbox' => [0.20, 0.30, 0.46, 0.34],
                'font' => $font + ['raw_font_program' => 'hidden parent font payload'],
                'rotation' => 270,
                'char_start_idx' => 41,
                'char_end_idx' => 42,
                'chars' => [
                    ['char' => 'M', 'bbox' => [0.20, 0.30, 0.215, 0.34], 'raw_pdf_bytes' => 'hidden minimal char payload'],
                    ['char' => 'i', 'bbox' => [0.215, 0.30, 0.23, 0.34], 'debug_payload' => ['stream' => 'hidden minimal debug payload']],
                ],
            ]],
        ]],
    ]],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1, keepChars: true);
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
$charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

echo '<!-- markerpdf:pdftext-dictionary-minimal-keep-chars ' . htmlspecialchars(json_encode([
    'support_component' => 'pdf-text-dictionary-core',
    'source_boundary' => 'pdftext-0.3.18-keep-chars-minimal-char-dictionaries',
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'keep_chars' => $document['metadata']['pdftext_options']['keep_chars'] ?? false,
    'minimal_char_dictionary_accepted' => array_keys($charSpan['chars'][0]) === ['char', 'bbox', 'rotation', 'font', 'char_idx'],
    'char_rotation_inferred_from_span' => ($charSpan['chars'][0]['rotation'] ?? null) === 270,
    'char_font_inferred_from_span' => ($charSpan['chars'][0]['font'] ?? null) === $font,
    'char_index_inferred_from_span_start' => ($charSpan['chars'][1]['char_idx'] ?? null) === 42,
    'normalized_char_bbox_scaled' => ($charSpan['chars'][0]['bbox'] ?? null) === [128.0, 144.0, 137.6, 163.2],
    'private_payload_excluded' => !str_contains($encoded, 'hidden parent font payload')
        && !str_contains($encoded, 'hidden minimal char payload')
        && !str_contains($encoded, 'hidden minimal debug payload'),
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) $block['pnum'] . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
