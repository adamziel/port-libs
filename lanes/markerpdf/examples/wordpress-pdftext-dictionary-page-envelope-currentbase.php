<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (int $page, string $prefix): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'refs' => [[
            'page' => 4,
            'idx' => 2,
            'coord' => [96.0, 144.0],
        ]],
        'blocks' => [[
            'bbox' => [72.0, 96.0, 430.0, 112.0],
            'lines' => [[
                'bbox' => [72.0, 96.0, 430.0, 112.0],
                'spans' => [
                    [
                        'text' => $prefix . ' ',
                        'bbox' => [72.0, 96.0, 142.0, 112.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'cached dictionary page',
                        'bbox' => [142.0, 96.0, 284.0, 112.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => 'https://example.com/import-guide',
                    ],
                    [
                        'text' => ' import',
                        'bbox' => [284.0, 96.0, 430.0, 112.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$document = (new PdfTextDocumentExtractor())->getTextBlocks(
    [
        'metadata' => [
            'source' => 'cached pdftext.dictionary_output',
            'raw_private_payload' => 'envelope metadata payload must not cross dictionary_output',
        ],
        'pages' => [
            30 => $page(30, 'Skipped cover'),
            31 => $page(31, 'WordPress'),
            32 => $page(32, 'Skipped appendix'),
        ],
        'raw_adapter_payload' => 'top-level envelope payload must not cross dictionary_output',
    ],
    maxPages: 1,
    startPage: 1,
    toc: [['title' => 'Cached page envelope', 'level' => 1, 'page_index' => 31]]
);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$page = $document['pages'][0];

echo '<!-- markerpdf:pdftext-dictionary-page-envelope ' . htmlspecialchars(json_encode([
    'support_component' => 'pdf-text-dictionary-core',
    'source_boundary' => 'pdftext.dictionary_output-pages-list',
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'selected_page_range' => $document['page_range'],
    'selected_pdftext_page' => $page['pnum'],
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'page_ref_synthesized' => ($page['pdftext_source']['refs'][0]['url'] ?? null) === '#page-4-2',
    'safe_span_link_promoted' => ($page['blocks'][0]['lines'][0]['spans'][1]['url'] ?? null) === 'https://example.com/import-guide',
    'envelope_payload_excluded' => !str_contains($encoded, 'envelope metadata payload must not cross dictionary_output')
        && !str_contains($encoded, 'top-level envelope payload must not cross dictionary_output'),
    'skipped_pages_excluded' => !str_contains($encoded, 'Skipped cover')
        && !str_contains($encoded, 'Skipped appendix'),
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) $block['pnum'] . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
