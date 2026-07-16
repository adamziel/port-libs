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
        'raw_singleton_payload' => 'direct singleton page payload must not cross WordPress import',
        'refs' => [[
            'page' => 7,
            'idx' => 2,
            'coord' => [144.0, 216.0],
            'raw_private_payload' => 'direct singleton ref payload must not cross WordPress import',
        ]],
        'blocks' => [[
            'bbox' => [72.0, 96.0, 520.0, 112.0],
            'raw_block_payload' => 'direct singleton block payload must not cross WordPress import',
            'lines' => [[
                'bbox' => [72.0, 96.0, 520.0, 112.0],
                'raw_line_payload' => 'direct singleton line payload must not cross WordPress import',
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 96.0, 210.0, 112.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'singleton link',
                        'bbox' => [210.0, 96.0, 314.0, 112.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => 'direct singleton span payload must not cross WordPress import',
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [314.0, 96.0, 520.0, 112.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$stalePage = $page(990, 'Stale adapter page ', 'https://example.com/stale-singleton', ' must not render.');
$selectedPage = $page(991, 'Direct dictionary_output ', 'https://example.com/direct-singleton', ' imports as one page.');

$document = (new PdfTextDocumentExtractor())->getTextBlocks(
    [
        'pages' => [
            990 => $stalePage,
        ],
        'dictionary_output' => $selectedPage,
        'raw_adapter_payload' => 'stale singleton adapter payload must not cross WordPress import',
    ],
    maxPages: 1,
    toc: [['title' => 'Direct singleton dictionary output', 'level' => 1, 'page_index' => 991]]
);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$selected = $document['pages'][0] ?? [];
$visibleText = $blocks[0]['text'] ?? '';
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$singletonEnvelopeWrapped = ($document['page_range'] ?? null) === [0]
    && ($document['metadata']['source_pages'] ?? null) === 1
    && ($selected['pnum'] ?? null) === 991;
$safeLinkPromoted = str_contains($visibleText, '[singleton link](https://example.com/direct-singleton)')
    && (($selected['blocks'][0]['lines'][0]['spans'][1]['url'] ?? null) === 'https://example.com/direct-singleton');
$refAnchorSynthesized = ($selected['pdftext_source']['refs'][0]['url'] ?? null) === '#page-7-2';
$stalePagesExcluded = !str_contains($visibleText, 'Stale adapter page')
    && !str_contains($encoded, 'Stale adapter page');
$payloadExcluded = !str_contains($encoded, 'direct singleton page payload')
    && !str_contains($encoded, 'direct singleton ref payload')
    && !str_contains($encoded, 'direct singleton block payload')
    && !str_contains($encoded, 'direct singleton line payload')
    && !str_contains($encoded, 'direct singleton span payload')
    && !str_contains($encoded, 'stale singleton adapter payload');

if (!$singletonEnvelopeWrapped || !$safeLinkPromoted || !$refAnchorSynthesized || !$stalePagesExcluded || !$payloadExcluded) {
    throw new RuntimeException('Expected direct dictionary_output singleton envelope to wrap as one current pdftext page before WordPress import.');
}

echo '<!-- markerpdf-pdftext-dictionary-singleton-envelope-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-singleton-envelope-currentbase',
    'source_truth' => 'markerPDF enumerates the page dictionaries returned by pdftext.dictionary_output; native singleton cache envelopes must treat a direct page dictionary as one selected page, not as scalar page fields',
    'support_component' => 'pdf-text-dictionary-core-boundary',
    'page_range' => $document['page_range'],
    'source_pages' => $document['metadata']['source_pages'] ?? null,
    'selected_pdftext_page' => $selected['pnum'] ?? null,
    'singleton_envelope_wrapped' => $singletonEnvelopeWrapped,
    'safe_span_link_promoted' => $safeLinkPromoted,
    'pdftext_ref_anchor_synthesized' => $refAnchorSynthesized,
    'stale_pages_excluded' => $stalePagesExcluded,
    'payload_excluded' => $payloadExcluded,
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_ocr' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
