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
            'idx' => 6,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => 'nested pages ref payload must stay hidden',
        ]],
        'raw_page_payload' => 'nested pages page payload must stay hidden',
        'blocks' => [[
            'bbox' => [72.0, 144.0, 460.0, 158.0],
            'raw_block_payload' => 'nested pages block payload must stay hidden',
            'lines' => [[
                'bbox' => [72.0, 144.0, 460.0, 158.0],
                'raw_line_payload' => 'nested pages line payload must stay hidden',
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 170.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'nested JSON pages link',
                        'bbox' => [170.0, 144.0, 320.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => 'nested pages span payload must stay hidden',
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [320.0, 144.0, 460.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$stale = $page(5400, 'Stale nested JSON adapter ', 'https://example.com/nested-json-stale', ' should not import');
$cover = $page(5500, 'Skipped nested JSON cover ', 'https://example.com/nested-json-cover', ' should not import');
$selected = $page(5501, 'Nested JSON pages dictionary_output ', 'https://example.com/nested-json-current', ' imports without Python');
$appendix = $page(5502, 'Skipped nested JSON appendix ', 'https://example.com/nested-json-appendix', ' should not import');
$pagesJson = json_encode([
    5500 => $cover,
    5501 => $selected,
    5502 => $appendix,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$document = (new PdfTextDocumentExtractor())->getTextBlocks([
    'pages' => [5400 => $stale],
    'dictionary_output' => [
        'metadata' => [
            'source' => 'WordPress adapter cache with double-encoded pdftext pages',
            'raw_private_payload' => 'nested pages envelope metadata payload must stay hidden',
        ],
        'pages' => $pagesJson,
        'raw_pdftext_payload' => 'nested pages dictionary_output payload must stay hidden',
    ],
    'raw_adapter_payload' => 'nested pages stale adapter payload must stay hidden',
], maxPages: 1, startPage: 1);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$pageOut = $document['pages'][0] ?? [];
$span = $pageOut['blocks'][0]['lines'][0]['spans'][1] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-nested-json-pages-currentbase',
    'source_truth' => 'markerPDF consumes ordered page dictionaries returned by pdftext.dictionary_output; native WordPress import caches may double-encode only the nested pages member before PHP conversion',
    'support_component' => 'pdf-text-dictionary-core-boundary',
    'page_range' => $document['page_range'],
    'source_pages' => $document['metadata']['source_pages'] ?? null,
    'selected_page' => $pageOut['pnum'] ?? null,
    'nested_pages_json_unwrapped' => ($pageOut['pnum'] ?? null) === 5501,
    'safe_span_link_promoted' => ($span['url'] ?? null) === 'https://example.com/nested-json-current',
    'reference_anchor_synthesized' => ($pageOut['pdftext_source']['refs'][0]['url'] ?? null) === '#page-5501-6',
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'stale_adapter_excluded' => !str_contains($encoded, 'Stale nested JSON adapter'),
    'cover_appendix_excluded' => !str_contains($encoded, 'Skipped nested JSON cover') && !str_contains($encoded, 'Skipped nested JSON appendix'),
    'raw_payload_excluded' => !str_contains($encoded, 'nested pages ref payload')
        && !str_contains($encoded, 'nested pages page payload')
        && !str_contains($encoded, 'nested pages block payload')
        && !str_contains($encoded, 'nested pages line payload')
        && !str_contains($encoded, 'nested pages span payload')
        && !str_contains($encoded, 'nested pages envelope metadata payload')
        && !str_contains($encoded, 'nested pages dictionary_output payload')
        && !str_contains($encoded, 'nested pages stale adapter payload'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['nested_pages_json_unwrapped']
    || !$flags['safe_span_link_promoted']
    || !$flags['reference_anchor_synthesized']
    || !$flags['stale_adapter_excluded']
    || !$flags['cover_appendix_excluded']
    || !$flags['raw_payload_excluded']
) {
    throw new RuntimeException('Expected nested raw JSON pages member to unwrap before WordPress import: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-pdftext-dictionary-nested-json-pages-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars((string) ($block['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
