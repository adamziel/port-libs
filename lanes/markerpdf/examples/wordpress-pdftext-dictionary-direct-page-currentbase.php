<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'page' => 92,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'refs' => [[
        'url' => '#page-3-xy',
        'page' => 3,
        'dest_pos' => [72.0, 96.0],
        'raw_private_payload' => 'direct page ref payload must not cross dictionary_output',
    ]],
    'raw_direct_page_payload' => 'direct page adapter payload must not cross dictionary_output',
    'blocks' => [[
        'bbox' => [72.0, 96.0, 430.0, 112.0],
        'raw_direct_block_payload' => 'direct page block payload must not cross dictionary_output',
        'lines' => [[
            'bbox' => [72.0, 96.0, 430.0, 112.0],
            'raw_direct_line_payload' => 'direct page line payload must not cross dictionary_output',
            'spans' => [
                [
                    'text' => 'Direct page ',
                    'bbox' => [72.0, 96.0, 160.0, 112.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                ],
                [
                    'text' => 'dictionary link',
                    'bbox' => [160.0, 96.0, 270.0, 112.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'url' => 'https://example.com/direct-page',
                    'raw_direct_span_payload' => 'direct page span payload must not cross dictionary_output',
                    'chars' => [['char' => 'd', 'bbox' => [160.0, 96.0, 166.0, 112.0]]],
                ],
                [
                    'text' => ' stays single page',
                    'bbox' => [270.0, 96.0, 430.0, 112.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                ],
            ],
        ]],
    ]],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks(
    $page,
    maxPages: 1,
    toc: [['title' => 'Direct page dictionary', 'level' => 1, 'page_index' => 92]]
);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$selectedPage = $document['pages'][0] ?? [];
$span = $selectedPage['blocks'][0]['lines'][0]['spans'][1] ?? [];
$charSpan = $selectedPage['char_blocks'][0]['lines'][0]['spans'][1] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$text = $blocks[0]['text'] ?? '';

if (
    ($document['page_range'] ?? null) !== [0]
    || ($document['metadata']['source_pages'] ?? null) !== 1
    || ($selectedPage['pnum'] ?? null) !== 92
    || ($span['url'] ?? null) !== 'https://example.com/direct-page'
    || array_key_exists('chars', $charSpan)
    || !str_contains($text, '[dictionary link](https://example.com/direct-page)')
    || str_contains($encoded, 'direct page adapter payload')
    || str_contains($encoded, 'direct page ref payload')
    || str_contains($encoded, 'direct page block payload')
    || str_contains($encoded, 'direct page line payload')
    || str_contains($encoded, 'direct page span payload')
) {
    throw new RuntimeException('Expected direct supplied pdftext page dictionaries to wrap as one selected page before WordPress import.');
}

echo '<!-- markerpdf:pdftext-dictionary-direct-page ' . htmlspecialchars(json_encode([
    'support_component' => 'pdf-text-dictionary-core',
    'source_boundary' => 'direct-supplied-pdftext-page-dictionary',
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'direct_page_wrapped' => ($document['page_range'] ?? null) === [0],
    'source_pages' => $document['metadata']['source_pages'] ?? null,
    'selected_pdftext_page' => $selectedPage['pnum'] ?? null,
    'pdftext_options' => $document['metadata']['pdftext_options'] ?? [],
    'safe_span_link_promoted' => ($span['url'] ?? null) === 'https://example.com/direct-page',
    'raw_chars_excluded' => !array_key_exists('chars', $charSpan),
    'direct_page_payload_excluded' => !str_contains($encoded, 'direct page adapter payload')
        && !str_contains($encoded, 'direct page ref payload')
        && !str_contains($encoded, 'direct page block payload')
        && !str_contains($encoded, 'direct page line payload')
        && !str_contains($encoded, 'direct page span payload'),
    'visible_wordpress_text' => $text,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
