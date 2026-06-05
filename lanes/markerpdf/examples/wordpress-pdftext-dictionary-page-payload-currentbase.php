<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'page' => 22,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'raw_page_bytes' => 'raw page bytes must not cross dictionary_output',
    'metadata' => [
        'document_page' => 999,
        'raw_nested_payload' => 'nested page metadata must not cross dictionary_output',
    ],
    'images' => [
        ['payload' => 'page image payload must not cross dictionary_output'],
    ],
    'refs' => [
        [
            'url' => '#page-22-0',
            'page' => 22,
            'idx' => 0,
        ],
    ],
    'blocks' => [[
        'bbox' => [72.0, 96.0, 420.0, 110.0],
        'lines' => [[
            'bbox' => [72.0, 96.0, 420.0, 110.0],
            'spans' => [[
                'text' => 'Page-level dictionary payload stays out of WordPress.',
                'bbox' => [72.0, 96.0, 420.0, 110.0],
                'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
            ]],
        ]],
    ]],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$source = $document['pages'][0]['pdftext_source'] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$visibleText = $blocks[0]['text'] ?? '';

if (array_keys($source) !== ['page', 'bbox', 'rotation', 'width', 'height', 'refs']
    || str_contains($encoded, 'raw page bytes must not cross dictionary_output')
    || str_contains($encoded, 'nested page metadata must not cross dictionary_output')
    || str_contains($encoded, 'page image payload must not cross dictionary_output')
    || !str_contains($visibleText, 'Page-level dictionary payload stays out of WordPress.')
) {
    throw new RuntimeException('Expected pdftext page-level private payloads to be excluded before WordPress rendering.');
}

echo '<!-- markerpdf-pdftext-dictionary-page-payload-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-page-payload-currentbase',
    'source_truth' => 'markerPDF converts pages returned by pdftext.dictionary_output; native supplied dictionaries trust only page, bbox, width, height, rotation, refs, and blocks before WordPress metadata',
    'support_component' => 'pdf-text-dictionary-core',
    'trusted_pdftext_source_keys' => array_keys($source),
    'page_source_preserved' => ($source['page'] ?? null) === 22
        && ($source['bbox'] ?? null) === [0.0, 0.0, 612.0, 792.0]
        && ($source['width'] ?? null) === 612.0
        && ($source['height'] ?? null) === 792.0,
    'page_ref_preserved' => ($source['refs'][0]['url'] ?? null) === '#page-22-0',
    'page_payload_excluded' => !str_contains($encoded, 'raw page bytes must not cross dictionary_output')
        && !str_contains($encoded, 'nested page metadata must not cross dictionary_output')
        && !str_contains($encoded, 'page image payload must not cross dictionary_output'),
    'visible_wordpress_text' => $visibleText,
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
