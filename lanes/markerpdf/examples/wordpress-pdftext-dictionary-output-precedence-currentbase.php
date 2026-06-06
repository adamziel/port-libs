<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (int $page, string $text): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'blocks' => [[
            'bbox' => [72.0, 96.0, 480.0, 110.0],
            'lines' => [[
                'bbox' => [72.0, 96.0, 480.0, 110.0],
                'spans' => [
                    [
                        'text' => $text,
                        'bbox' => [72.0, 96.0, 260.0, 110.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'source link',
                        'bbox' => [260.0, 96.0, 330.0, 110.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => 'https://example.com/pdftext-source',
                    ],
                    [
                        'text' => ' is imported.',
                        'bbox' => [330.0, 96.0, 420.0, 110.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
        'refs' => [[
            'page' => 9,
            'idx' => 2,
            'coord' => [144.0, 216.0],
        ]],
    ];
};

$document = (new PdfTextDocumentExtractor())->getTextBlocks(
    [
        'pages' => [
            $page(80, 'Stale adapter pages should not render.'),
        ],
        'dictionary_output' => [
            'metadata' => [
                'source' => 'pdftext.dictionary_output',
                'raw_private_payload' => 'dictionary_output metadata payload must stay private',
            ],
            'pages' => [
                $page(90, 'Explicit dictionary_output '),
            ],
            'raw_pdftext_payload' => 'dictionary_output payload must stay private',
        ],
        'raw_adapter_payload' => 'stale adapter payload must stay private',
    ],
    maxPages: 1
);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$visibleText = $blocks[0]['text'] ?? '';
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$explicitSelected = ($document['pages'][0]['pnum'] ?? null) === 90;
$stalePagesExcluded = !str_contains($visibleText, 'Stale adapter pages should not render.')
    && !str_contains($encoded, 'Stale adapter pages should not render.');
$payloadExcluded = !str_contains($encoded, 'dictionary_output metadata payload must stay private')
    && !str_contains($encoded, 'dictionary_output payload must stay private')
    && !str_contains($encoded, 'stale adapter payload must stay private');
$safeLinkPromoted = str_contains($visibleText, '[source link](https://example.com/pdftext-source)');
$anchorSynthesized = ($document['pages'][0]['pdftext_source']['refs'][0]['url'] ?? null) === '#page-9-2';

if (!$explicitSelected || !$stalePagesExcluded || !$payloadExcluded || !$safeLinkPromoted || !$anchorSynthesized) {
    throw new RuntimeException('Expected explicit pdftext dictionary_output pages to win over stale adapter pages.');
}

echo '<!-- markerpdf-pdftext-dictionary-output-precedence-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-output-precedence-currentbase',
    'source_truth' => 'markerPDF consumes pdftext.extraction.dictionary_output pages before converting them into Marker page blocks; adapter envelope pages are not the authoritative pdftext page list when explicit dictionary_output is present',
    'support_component' => 'pdf-text-dictionary-core',
    'explicit_dictionary_output_selected' => $explicitSelected,
    'selected_pdftext_page' => $document['pages'][0]['pnum'] ?? null,
    'page_range' => $document['page_range'],
    'source_pages' => $document['metadata']['source_pages'] ?? null,
    'stale_adapter_pages_excluded' => $stalePagesExcluded,
    'adapter_payload_excluded' => $payloadExcluded,
    'safe_span_link_promoted' => $safeLinkPromoted,
    'pdftext_ref_anchor_synthesized' => $anchorSynthesized,
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
