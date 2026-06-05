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
            'raw_private_payload' => 'hidden ref payload should not cross dictionary_output',
            'debug_payload' => ['stream' => 'hidden ref debug data'],
        ],
        [
            'url' => '#page-8-4',
            'page' => 8,
            'idx' => 4,
            'ref' => 'page-8-4',
            'coord' => [12.5, 64.0],
            'raw_pdf_bytes' => 'hidden destination bytes',
        ],
        [
            'raw_payload' => 'payload-only ref row should be dropped',
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
$refs = $document['pages'][0]['pdftext_source']['refs'] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$text = $blocks[0]['text'] ?? '';

if (!str_contains($text, '[plugin docs](https://example.com/import\\)docs)')
    || str_contains($text, 'javascript:')
    || ($refs[0]['url'] ?? null) !== '#page-3-xy'
    || ($refs[1]['ref'] ?? null) !== 'page-8-4'
    || count($refs) !== 2
    || array_key_exists('chars', $charSpans[1] ?? [])
    || str_contains($encoded, 'hidden ref payload')
    || str_contains($encoded, 'hidden destination bytes')
    || str_contains($encoded, 'payload-only ref row should be dropped')
) {
    throw new RuntimeException('Expected pdftext dictionary link/ref metadata to preserve safe links, omit unsafe clickable links, and remove raw ref/char payloads.');
}

echo '<!-- markerpdf-pdftext-dictionary-link-ref-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-link-ref-currentbase',
    'source_truth' => 'pdftext.dictionary_output keeps span url metadata and page refs after link merging; current marker PdfProvider maps url to text spans and stores page refs for review',
    'support_component' => 'pdf-text-dictionary-core',
    'safe_pdftext_url_promoted' => ($spans[1]['url'] ?? null) === 'https://example.com/import)docs',
    'unsafe_pdftext_url_review_only' => ($spans[2]['pdftext_url'] ?? null) === 'javascript:alert(1)' && !array_key_exists('url', $spans[2] ?? []),
    'pdftext_refs_preserved' => ($refs[0]['url'] ?? null) === '#page-3-xy',
    'pdftext_reference_shape_preserved' => ($refs[1]['ref'] ?? null) === 'page-8-4' && ($refs[1]['coord'] ?? null) === [12.5, 64.0],
    'pdftext_ref_payload_excluded' => !str_contains($encoded, 'hidden ref payload')
        && !str_contains($encoded, 'hidden destination bytes')
        && !str_contains($encoded, 'payload-only ref row should be dropped'),
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
