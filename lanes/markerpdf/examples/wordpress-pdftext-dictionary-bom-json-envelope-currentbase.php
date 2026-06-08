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
            'idx' => 5,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => 'BOM cached ref payload must stay hidden',
        ]],
        'raw_page_payload' => 'BOM cached page payload must stay hidden',
        'blocks' => [[
            'bbox' => [72.0, 144.0, 430.0, 158.0],
            'raw_block_payload' => 'BOM cached block payload must stay hidden',
            'lines' => [[
                'bbox' => [72.0, 144.0, 430.0, 158.0],
                'raw_line_payload' => 'BOM cached line payload must stay hidden',
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 160.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'BOM cached link',
                        'bbox' => [160.0, 144.0, 280.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => 'BOM cached span payload must stay hidden',
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [280.0, 144.0, 430.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$stale = $page(3400, 'Stale BOM adapter ', 'https://example.com/bom-stale', ' should not render');
$cover = $page(3500, 'Skipped BOM cover ', 'https://example.com/bom-cover', ' should not render');
$selected = $page(3501, 'BOM dictionary_output ', 'https://example.com/bom-current', ' imports without Python');
$appendix = $page(3502, 'Skipped BOM appendix ', 'https://example.com/bom-appendix', ' should not render');
$json = "\xEF\xBB\xBF" . json_encode([
    'metadata' => [
        'source' => 'pdftext --json cache with UTF-8 BOM',
        'raw_private_payload' => 'BOM JSON metadata payload must stay hidden',
    ],
    'pages' => [
        3500 => $cover,
        3501 => $selected,
        3502 => $appendix,
    ],
    'raw_pdftext_payload' => 'BOM JSON payload must stay hidden',
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$document = (new PdfTextDocumentExtractor())->getTextBlocks([
    'pages' => [3400 => $stale],
    'dictionary_output' => $json,
    'raw_adapter_payload' => 'BOM stale adapter payload must stay hidden',
], maxPages: 1, startPage: 1);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$pageOut = $document['pages'][0] ?? [];
$span = $pageOut['blocks'][0]['lines'][0]['spans'][1] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-bom-json-envelope-currentbase',
    'source_truth' => 'markerPDF consumes page dictionaries returned by pdftext.dictionary_output; native WordPress import caches may preserve the same JSON page-list envelope with a UTF-8 BOM before PHP conversion',
    'support_component' => 'pdf-text-dictionary-core-boundary',
    'page_range' => $document['page_range'],
    'source_pages' => $document['metadata']['source_pages'] ?? null,
    'selected_page' => $pageOut['pnum'] ?? null,
    'bom_json_unwrapped' => ($pageOut['pnum'] ?? null) === 3501,
    'safe_span_link_promoted' => ($span['url'] ?? null) === 'https://example.com/bom-current',
    'reference_anchor_synthesized' => ($pageOut['pdftext_source']['refs'][0]['url'] ?? null) === '#page-3501-5',
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'stale_adapter_excluded' => !str_contains($encoded, 'Stale BOM adapter'),
    'cover_appendix_excluded' => !str_contains($encoded, 'Skipped BOM cover') && !str_contains($encoded, 'Skipped BOM appendix'),
    'raw_payload_excluded' => !str_contains($encoded, 'BOM cached ref payload')
        && !str_contains($encoded, 'BOM cached page payload')
        && !str_contains($encoded, 'BOM cached block payload')
        && !str_contains($encoded, 'BOM cached line payload')
        && !str_contains($encoded, 'BOM cached span payload')
        && !str_contains($encoded, 'BOM JSON metadata payload')
        && !str_contains($encoded, 'BOM JSON payload')
        && !str_contains($encoded, 'BOM stale adapter payload'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['bom_json_unwrapped']
    || !$flags['safe_span_link_promoted']
    || !$flags['reference_anchor_synthesized']
    || !$flags['stale_adapter_excluded']
    || !$flags['cover_appendix_excluded']
    || !$flags['raw_payload_excluded']
) {
    throw new RuntimeException('Expected BOM-prefixed pdftext dictionary JSON cache to unwrap before WordPress import: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-pdftext-dictionary-bom-json-envelope-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars((string) ($block['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
