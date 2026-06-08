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
            'idx' => 4,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => 'hidden raw JSON list-entry ref payload',
        ]],
        'raw_page_payload' => 'hidden raw JSON list-entry page payload',
        'blocks' => [[
            'bbox' => [72.0, 144.0, 430.0, 158.0],
            'raw_block_payload' => 'hidden raw JSON list-entry block payload',
            'lines' => [[
                'bbox' => [72.0, 144.0, 430.0, 158.0],
                'raw_line_payload' => 'hidden raw JSON list-entry line payload',
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 160.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'JSONL cached link',
                        'bbox' => [160.0, 144.0, 280.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => 'hidden raw JSON list-entry span payload',
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

$cover = $page(1700, 'Skipped JSONL cover ', 'https://example.com/jsonl-cover', ' should not import');
$staleSelected = $page(1701, 'Stale JSONL wrapper ', 'https://example.com/jsonl-stale', ' should not import');
$currentSelected = $page(2701, 'JSONL one-page envelope ', 'https://example.com/jsonl-current', ' imports without Python');
$appendix = $page(1702, 'Skipped JSONL appendix ', 'https://example.com/jsonl-appendix', ' should not import');

$staleSelected['dictionary_output'] = [
    'metadata' => [
        'source' => 'pdftext.dictionary_output jsonl page cache',
        'raw_private_payload' => 'hidden raw JSON list-entry envelope metadata',
    ],
    'pages' => [
        2701 => $currentSelected,
    ],
    'raw_pdftext_payload' => 'hidden raw JSON list-entry envelope payload',
];
$staleSelected['raw_wrapper_payload'] = 'hidden raw JSON list-entry stale wrapper payload';

$document = (new PdfTextDocumentExtractor())->getTextBlocks(
    [
        json_encode($cover, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        json_encode($staleSelected, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        json_encode($appendix, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ],
    maxPages: 1,
    startPage: 1
);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$pageOut = $document['pages'][0] ?? [];
$span = $pageOut['blocks'][0]['lines'][0]['spans'][1] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-json-list-entry-currentbase',
    'source_truth' => 'markerPDF consumes the ordered page dictionaries returned by pdftext.dictionary_output; JSONL-style import caches can preserve each selected page or one-page envelope as a raw JSON list entry before WordPress conversion',
    'support_component' => 'pdf-text-dictionary-core-json-list-entry',
    'page_range' => $document['page_range'],
    'source_pages' => $document['metadata']['source_pages'] ?? null,
    'selected_page' => $pageOut['pnum'] ?? null,
    'json_list_entry_unwrapped' => ($pageOut['pnum'] ?? null) === 2701,
    'safe_span_link_promoted' => ($span['url'] ?? null) === 'https://example.com/jsonl-current',
    'reference_anchor_synthesized' => ($pageOut['pdftext_source']['refs'][0]['url'] ?? null) === '#page-2701-4',
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'stale_wrapper_excluded' => !str_contains($encoded, 'Stale JSONL wrapper'),
    'cover_appendix_excluded' => !str_contains($encoded, 'Skipped JSONL cover') && !str_contains($encoded, 'Skipped JSONL appendix'),
    'raw_payload_excluded' => !str_contains($encoded, 'hidden raw JSON list-entry'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['json_list_entry_unwrapped']
    || !$flags['safe_span_link_promoted']
    || !$flags['reference_anchor_synthesized']
    || !$flags['stale_wrapper_excluded']
    || !$flags['cover_appendix_excluded']
    || !$flags['raw_payload_excluded']
) {
    throw new RuntimeException('Expected raw JSON pdftext page-list entries to unwrap before WordPress import: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-pdftext-dictionary-json-list-entry-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars((string) ($block['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
