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
            'idx' => 8,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => 'hidden page-map ref payload',
        ]],
        'raw_page_payload' => 'hidden page-map page payload',
        'blocks' => [[
            'bbox' => [72.0, 144.0, 480.0, 158.0],
            'raw_block_payload' => 'hidden page-map block payload',
            'lines' => [[
                'bbox' => [72.0, 144.0, 480.0, 158.0],
                'raw_line_payload' => 'hidden page-map line payload',
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 180.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'page-map cache link',
                        'bbox' => [180.0, 144.0, 330.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => 'hidden page-map span payload',
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [330.0, 144.0, 480.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$stale = $page(8100, 'Stale WordPress wrapper ', 'https://example.com/page-map-smoke-stale', ' should not render');
$cover = $page(8200, 'Skipped WordPress page_map cover ', 'https://example.com/page-map-smoke-cover', ' should not render');
$selected = $page(8201, 'WordPress page_map dictionary_output ', 'https://example.com/page-map-smoke-current', ' imports without Python');
$appendix = $page(8202, 'Skipped WordPress page_map appendix ', 'https://example.com/page-map-smoke-appendix', ' should not render');

$document = (new PdfTextDocumentExtractor())->getTextBlocks([
    'pages' => [8100 => $stale],
    'dictionary_output' => [
        'metadata' => [
            'source' => 'native adapter page_map cache',
            'raw_private_payload' => 'hidden page_map smoke metadata payload',
        ],
        'page_map' => [
            8200 => $cover,
            8201 => $selected,
            8202 => $appendix,
        ],
        'raw_pdftext_payload' => 'hidden page_map smoke wrapper payload',
    ],
    'raw_adapter_payload' => 'hidden stale wrapper payload',
], maxPages: 1, startPage: 1);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$pageOut = $document['pages'][0] ?? [];
$span = $pageOut['blocks'][0]['lines'][0]['spans'][1] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-page-map-envelope-currentbase',
    'source_truth' => 'pdftext JSON/dictionary output is an ordered page list; native WordPress cache wrappers may preserve that list as page_map/pageMap keyed maps before import',
    'support_component' => 'pdf-text-dictionary-core-page-map-envelope',
    'page_map_cache_selected' => ($pageOut['pnum'] ?? null) === 8201,
    'selected_page_range' => $document['page_range'],
    'source_pages' => $document['metadata']['source_pages'] ?? null,
    'safe_span_link_promoted' => ($span['url'] ?? null) === 'https://example.com/page-map-smoke-current',
    'reference_anchor_synthesized' => ($pageOut['pdftext_source']['refs'][0]['url'] ?? null) === '#page-8201-8',
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'stale_wrapper_excluded' => !str_contains($encoded, 'Stale WordPress wrapper')
        && !str_contains($encoded, 'hidden stale wrapper payload'),
    'cover_appendix_excluded' => !str_contains($encoded, 'Skipped WordPress page_map cover')
        && !str_contains($encoded, 'Skipped WordPress page_map appendix'),
    'raw_payload_excluded' => !str_contains($encoded, 'hidden page-map')
        && !str_contains($encoded, 'hidden page_map smoke'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['page_map_cache_selected']
    || !$flags['safe_span_link_promoted']
    || !$flags['reference_anchor_synthesized']
    || !$flags['stale_wrapper_excluded']
    || !$flags['cover_appendix_excluded']
    || !$flags['raw_payload_excluded']
) {
    throw new RuntimeException('Expected page_map pdftext dictionary cache to unwrap before WordPress import: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-pdftext-dictionary-page-map-envelope-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars((string) ($block['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
