<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (int $page, string $lead, string $url, string $tail): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'refs' => [[
            'page' => $page,
            'idx' => 2,
            'coord' => [72.0, 96.0],
            'raw_private_payload' => 'hidden raw JSON ref payload',
        ]],
        'raw_page_payload' => 'hidden raw JSON page payload',
        'blocks' => [[
            'bbox' => [72.0, 96.0, 430.0, 110.0],
            'raw_block_payload' => 'hidden raw JSON block payload',
            'lines' => [[
                'bbox' => [72.0, 96.0, 430.0, 110.0],
                'raw_line_payload' => 'hidden raw JSON line payload',
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 96.0, 170.0, 110.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'cached JSON link',
                        'bbox' => [170.0, 96.0, 270.0, 110.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => 'hidden raw JSON span payload',
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [270.0, 96.0, 430.0, 110.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$cover = $page(700, 'Skipped raw JSON cover ', 'https://example.com/raw-json-cover', ' should not import');
$selected = $page(701, 'Raw JSON dictionary_output ', 'https://example.com/raw-json-current', ' imports without Python');
$appendix = $page(702, 'Skipped raw JSON appendix ', 'https://example.com/raw-json-appendix', ' should not import');
$stale = $page(800, 'Stale adapter page ', 'https://example.com/raw-json-stale', ' should not import');

$json = json_encode([
    'metadata' => [
        'source' => 'pdftext --json cache',
        'raw_private_payload' => 'hidden raw JSON metadata payload',
    ],
    'pages' => [
        700 => $cover,
        701 => $selected,
        702 => $appendix,
    ],
    'raw_pdftext_payload' => 'hidden raw JSON dictionary payload',
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$document = (new PdfTextDocumentExtractor())->getTextBlocks([
    'pages' => [800 => $stale],
    'dictionary_output' => $json,
    'raw_adapter_payload' => 'hidden stale raw JSON adapter payload',
], maxPages: 1, startPage: 1);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$pageOut = $document['pages'][0] ?? [];
$span = $pageOut['blocks'][0]['lines'][0]['spans'][1] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-json-string-envelope-currentbase',
    'source_truth' => 'pdftext --json emits a page-list JSON structure with bbox, rotation, page, blocks, lines, spans, font, and link metadata; markerPDF consumes dictionary_output pages before WordPress conversion',
    'support_component' => 'pdf-text-dictionary-core-json-envelope',
    'page_range' => $document['page_range'],
    'source_pages' => $document['metadata']['source_pages'] ?? null,
    'selected_page' => $pageOut['pnum'] ?? null,
    'safe_span_link_promoted' => ($span['url'] ?? null) === 'https://example.com/raw-json-current',
    'reference_anchor_synthesized' => ($pageOut['pdftext_source']['refs'][0]['url'] ?? null) === '#page-701-2',
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'raw_json_cache_unwrapped' => ($pageOut['pnum'] ?? null) === 701,
    'stale_adapter_excluded' => !str_contains($encoded, 'Stale adapter page'),
    'cover_appendix_excluded' => !str_contains($encoded, 'Skipped raw JSON cover') && !str_contains($encoded, 'Skipped raw JSON appendix'),
    'raw_payload_excluded' => !str_contains($encoded, 'hidden raw JSON')
        && !str_contains($encoded, 'hidden stale raw JSON'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['raw_json_cache_unwrapped']
    || !$flags['safe_span_link_promoted']
    || !$flags['reference_anchor_synthesized']
    || !$flags['stale_adapter_excluded']
    || !$flags['cover_appendix_excluded']
    || !$flags['raw_payload_excluded']
) {
    throw new RuntimeException('Expected raw JSON pdftext dictionary_output cache strings to unwrap before WordPress import: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-pdftext-dictionary-json-string-envelope-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars((string) ($block['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
