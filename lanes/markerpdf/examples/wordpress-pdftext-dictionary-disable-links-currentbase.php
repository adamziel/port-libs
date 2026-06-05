<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];
$page = [
    'page' => 14,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'refs' => [
        [
            'url' => '#page-6-2',
            'page' => 6,
            'idx' => 2,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => 'hidden disabled ref payload',
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
                    'font' => $font,
                ],
                [
                    'text' => 'plugin docs',
                    'bbox' => [104.0, 96.0, 172.0, 110.0],
                    'font' => $font,
                    'url' => 'https://example.com/import-docs',
                    'chars' => [['char' => 'p', 'bbox' => [104.0, 96.0, 110.0, 110.0]]],
                ],
                [
                    'text' => ' while scripts stay inert',
                    'bbox' => [172.0, 96.0, 370.0, 110.0],
                    'font' => $font,
                    'url' => 'javascript:alert(1)',
                ],
            ],
        ]],
    ]],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1, disableLinks: true);
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$spans = $document['pages'][0]['blocks'][0]['lines'][0]['spans'] ?? [];
$charSpans = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'] ?? [];
$source = $document['pages'][0]['pdftext_source'] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$text = $blocks[0]['text'] ?? '';

if (($document['metadata']['pdftext_options']['disable_links'] ?? null) !== true
    || array_key_exists('refs', $source)
    || array_key_exists('url', $spans[1] ?? [])
    || array_key_exists('pdftext_url', $spans[1] ?? [])
    || array_key_exists('pdftext_url_is_safe', $spans[1] ?? [])
    || array_key_exists('url', $charSpans[1] ?? [])
    || $text !== 'Read plugin docs while scripts stay inert'
    || str_contains($text, 'https://example.com')
    || str_contains($encoded, 'javascript:alert')
    || str_contains($encoded, 'hidden disabled ref payload')
) {
    throw new RuntimeException('Expected disable_links to strip supplied pdftext refs and span URLs before WordPress rendering.');
}

echo '<!-- markerpdf-pdftext-dictionary-disable-links-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-disable-links-currentbase',
    'source_truth' => 'pdftext.dictionary_output(..., disable_links=True) skips add_links_and_refs, so supplied span URLs and page refs are absent before markerPDF converts pages to WordPress blocks',
    'support_component' => 'pdf-text-dictionary-core',
    'disable_links_option_recorded' => ($document['metadata']['pdftext_options']['disable_links'] ?? null) === true,
    'page_refs_excluded' => !array_key_exists('refs', $source),
    'safe_span_link_not_promoted' => !array_key_exists('url', $spans[1] ?? []) && !array_key_exists('pdftext_url', $spans[1] ?? []),
    'unsafe_span_link_excluded' => !str_contains($encoded, 'javascript:alert'),
    'char_blocks_linkless' => !array_key_exists('url', $charSpans[1] ?? []),
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
