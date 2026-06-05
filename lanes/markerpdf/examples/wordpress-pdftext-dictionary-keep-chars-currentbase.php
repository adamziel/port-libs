<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];
$page = [
    'page' => 19,
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'width' => 600.0,
    'height' => 800.0,
    'rotation' => 0,
    'blocks' => [[
        'bbox' => [0.10, 0.10, 0.30, 0.13],
        'lines' => [[
            'bbox' => [0.10, 0.10, 0.30, 0.13],
            'spans' => [[
                'text' => "Kept\u{FB01} chars\n",
                'bbox' => [0.10, 0.10, 0.30, 0.13],
                'font' => $font,
                'rotation' => 0,
                'char_start_idx' => 7,
                'char_end_idx' => 8,
                'chars' => [
                    [
                        'char' => 'K',
                        'bbox' => [0.10, 0.10, 0.11, 0.13],
                        'rotation' => 0,
                        'font' => $font,
                        'char_idx' => 7,
                        'raw_pdf_bytes' => 'hidden payload',
                    ],
                    [
                        'char' => 'e',
                        'bbox' => [0.11, 0.10, 0.12, 0.13],
                        'rotation' => 0,
                        'font' => $font,
                        'char_idx' => 8,
                    ],
                ],
                'raw_image_bytes' => 'not visible',
            ]],
        ]],
    ]],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1, keepChars: true);
$span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0] ?? [];
$charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0] ?? [];
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

if (($span['chars'][0]['bbox'] ?? null) !== [60.0, 80.0, 66.0, 104.0]
    || str_contains($encoded, 'raw_pdf_bytes')
    || str_contains($encoded, 'raw_image_bytes')
) {
    throw new RuntimeException('Expected keep_chars=true pdftext character dictionaries to be sanitized before WordPress import.');
}

echo '<!-- markerpdf-pdftext-dictionary-keep-chars-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-keep-chars-currentbase',
    'source_truth' => 'pdftext.extraction.dictionary_output keeps per-character dictionaries only when keep_chars=true and converts character bboxes before markerPDF page conversion',
    'support_component' => 'pdf-text-dictionary-core',
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'span_text' => $span['text'] ?? '',
    'char_span_text' => $charSpan['text'] ?? '',
    'retained_char_count' => count($span['chars'] ?? []),
    'first_char_bbox' => $span['chars'][0]['bbox'] ?? null,
    'raw_payload_excluded' => !str_contains($encoded, 'raw_pdf_bytes') && !str_contains($encoded, 'raw_image_bytes'),
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
