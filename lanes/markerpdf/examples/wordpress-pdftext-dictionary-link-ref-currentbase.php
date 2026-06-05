<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'page' => 12,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'refs' => [
        [
            'url' => '#page-3-xy',
            'page' => 3,
            'dest_pos' => [72.0, 96.0],
        ],
    ],
    'blocks' => [[
        'bbox' => [72.0, 96.0, 370.0, 110.0],
        'lines' => [[
            'bbox' => [72.0, 96.0, 370.0, 110.0],
            'spans' => [
                [
                    'text' => 'Read ',
                    'bbox' => [72.0, 96.0, 104.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                ],
                [
                    'text' => 'plugin docs',
                    'bbox' => [104.0, 96.0, 172.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'url' => 'https://example.com/import)docs',
                    'chars' => [['char' => 'p', 'bbox' => [104.0, 96.0, 110.0, 110.0]]],
                ],
                [
                    'text' => ' but review script action',
                    'bbox' => [172.0, 96.0, 370.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'url' => 'javascript:alert(1)',
                    'chars' => [['char' => 'b', 'bbox' => [172.0, 96.0, 178.0, 110.0]]],
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
$text = $blocks[0]['text'] ?? '';

if (!str_contains($text, '[plugin docs](https://example.com/import\\)docs)')
    || str_contains($text, 'javascript:')
    || ($document['pages'][0]['pdftext_source']['refs'][0]['url'] ?? null) !== '#page-3-xy'
    || array_key_exists('chars', $charSpans[1] ?? [])
) {
    throw new RuntimeException('Expected pdftext dictionary link/ref metadata to preserve safe links, omit unsafe clickable links, and remove raw char payloads.');
}

echo '<!-- markerpdf-pdftext-dictionary-link-ref-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-link-ref-currentbase',
    'source_truth' => 'pdftext.dictionary_output keeps span url metadata and page refs after link merging; current marker PdfProvider maps url to text spans and stores page refs for review',
    'support_component' => 'pdf-text-dictionary-core',
    'safe_pdftext_url_promoted' => ($spans[1]['url'] ?? null) === 'https://example.com/import)docs',
    'unsafe_pdftext_url_review_only' => ($spans[2]['pdftext_url'] ?? null) === 'javascript:alert(1)' && !array_key_exists('url', $spans[2] ?? []),
    'pdftext_refs_preserved' => ($document['pages'][0]['pdftext_source']['refs'][0]['url'] ?? null) === '#page-3-xy',
    'raw_chars_excluded' => !array_key_exists('chars', $charSpans[1] ?? []),
    'visible_wordpress_text' => $text,
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
