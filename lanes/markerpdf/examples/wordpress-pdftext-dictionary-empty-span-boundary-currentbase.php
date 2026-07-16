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
        'bbox' => [72.0, 96.0, 220.0, 110.0],
        'lines' => [[
            'bbox' => [72.0, 96.0, 220.0, 110.0],
            'spans' => [
                [
                    'text' => '',
                    'bbox' => [72.0, 96.0, 72.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'url' => 'https://example.com/empty-link',
                    'raw_empty_payload' => 'empty safe-link span payload must not cross dictionary_output',
                ],
                [
                    'text' => "\x00",
                    'bbox' => [74.0, 96.0, 74.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'url' => 'javascript:empty()',
                    'raw_empty_payload' => 'empty unsafe-link span payload must not cross dictionary_output',
                ],
                [
                    'text' => "Import guide\n",
                    'bbox' => [76.0, 96.0, 156.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'url' => 'https://example.com/import-guide',
                ],
            ],
        ]],
    ]],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$spans = $document['pages'][0]['blocks'][0]['lines'][0]['spans'] ?? [];
$charSpans = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

if (
    count($spans) !== 1
    || count($charSpans) !== 1
    || (($spans[0]['url'] ?? null) !== 'https://example.com/import-guide')
    || str_contains($encoded, 'https://example.com/empty-link')
    || str_contains($encoded, 'javascript:empty')
    || str_contains($encoded, 'empty safe-link span payload')
    || str_contains($encoded, 'empty unsafe-link span payload')
) {
    throw new RuntimeException('Expected empty pdftext spans to be dropped before WordPress link promotion.');
}

echo '<!-- markerpdf:pdftext-dictionary-empty-span-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'pdf-text-dictionary-core',
    'source_truth' => 'marker.providers.pdf.PdfProvider skips empty pdftext spans before creating Marker spans/chars while preserving non-empty safe span links',
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'empty_span_count_after_import' => count($spans) - 1,
    'empty_safe_link_excluded' => !str_contains($encoded, 'https://example.com/empty-link'),
    'empty_unsafe_link_excluded' => !str_contains($encoded, 'javascript:empty'),
    'empty_payload_excluded' => !str_contains($encoded, 'empty safe-link span payload')
        && !str_contains($encoded, 'empty unsafe-link span payload'),
    'visible_safe_link_preserved' => ($spans[0]['url'] ?? null) === 'https://example.com/import-guide',
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) $block['pnum'] . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
