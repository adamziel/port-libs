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
        ['page' => 14, 'idx' => 0, 'coord' => [72.0, 96.0]],
        [
            'page' => 14,
            'idx' => 9,
            'coord' => [72.0, 96.0],
            'url' => 'javascript:duplicateRef()',
            'raw_private_payload' => 'duplicate internal ref payload',
        ],
        ['page' => 14, 'idx' => 1, 'coord' => [144.0, 216.0]],
        [
            'url' => 'https://example.com/import-review',
            'bbox' => [72.0, 120.0, 180.0, 136.0],
            'raw_private_payload' => 'external ref payload',
        ],
    ],
    'blocks' => [[
        'bbox' => [72.0, 96.0, 430.0, 110.0],
        'lines' => [[
            'bbox' => [72.0, 96.0, 430.0, 110.0],
            'spans' => [[
                'text' => "Reference duplicate boundary\n",
                'bbox' => [72.0, 96.0, 300.0, 110.0],
                'font' => $font,
            ]],
        ]],
    ]],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
$refs = $document['pages'][0]['pdftext_source']['refs'] ?? [];
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$urls = array_column($refs, 'url');

echo '<!-- markerpdf:pdftext-dictionary-ref-dedupe ' . htmlspecialchars(json_encode([
    'support_component' => 'pdf-text-dictionary-core',
    'ref_count' => count($refs),
    'duplicate_coordinate_refs_deduplicated' => count($refs) === 3
        && in_array('#page-14-0', $urls, true)
        && in_array('#page-14-1', $urls, true),
    'external_ref_preserved' => in_array('https://example.com/import-review', $urls, true),
    'stale_unsafe_duplicate_excluded' => !str_contains($encoded, 'javascript:duplicateRef')
        && !str_contains($encoded, 'duplicate internal ref payload'),
    'visible_text' => $blocks[0]['text'] ?? '',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) $block['pnum'] . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
