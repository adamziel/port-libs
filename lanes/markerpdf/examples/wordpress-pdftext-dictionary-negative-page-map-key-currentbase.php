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
        'refs' => [[
            'page' => $page,
            'idx' => 3,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => "WordPress negative page-map ref payload {$page} must stay hidden",
        ]],
        'raw_page_payload' => "WordPress negative page-map page payload {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 144.0, 440.0, 158.0],
            'raw_block_payload' => "WordPress negative page-map block payload {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 144.0, 440.0, 158.0],
                'raw_line_payload' => "WordPress negative page-map line payload {$page} must stay hidden",
                'spans' => [[
                    'text' => $text,
                    'bbox' => [72.0, 144.0, 440.0, 158.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'raw_span_payload' => "WordPress negative page-map span payload {$page} must stay hidden",
                ]],
            ]],
        ]],
    ];
};

$extractor = new PdfTextDocumentExtractor();
$negativeDictionaryRejected = false;
$negativeJsonRejected = false;

try {
    $extractor->getTextBlocks([
        'pages' => [
            $page(10, 'Stale adapter page must not import into WordPress.'),
        ],
        'dictionary_output' => [
            '-1.0' => $page(101, 'Negative keyed dictionary_output page must not import into WordPress.'),
            '2' => $page(102, 'Positive sibling dictionary_output page must not import after invalid key.'),
        ],
        'raw_adapter_payload' => 'negative dictionary_output WordPress adapter payload must stay hidden',
    ], maxPages: 1);
} catch (InvalidArgumentException) {
    $negativeDictionaryRejected = true;
}

$json = json_encode([
    'metadata' => [
        'source' => 'pdftext --json cache with invalid page-map key',
        'raw_private_payload' => 'negative JSON page-map metadata payload must stay hidden',
    ],
    'pages' => [
        '-01' => $page(201, 'Negative keyed JSON pdftext page must not import into WordPress.'),
        '3' => $page(202, 'Positive JSON sibling page must not import after invalid key.'),
    ],
    'raw_pdftext_payload' => 'negative JSON page-map payload must stay hidden',
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

try {
    $extractor->getTextBlocks([
        'pages' => [
            $page(20, 'Stale JSON adapter page must not import into WordPress.'),
        ],
        'pdftext' => $json,
        'raw_adapter_payload' => 'negative JSON WordPress adapter payload must stay hidden',
    ], maxPages: 1);
} catch (InvalidArgumentException) {
    $negativeJsonRejected = true;
}

$document = $extractor->getTextBlocks([
    'dictionary_output' => [
        '0' => $page(0, 'Zero keyed pdftext page imports safely into WordPress.'),
        '+2.0' => $page(2, 'Positive keyed appendix stays out of the selected WordPress slice.'),
    ],
    'raw_adapter_payload' => 'valid zero-key WordPress adapter payload must stay hidden',
], maxPages: 1);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$zeroKeyImported = ($document['pages'][0]['pnum'] ?? null) === 0
    && ($blocks[0]['text'] ?? '') === 'Zero keyed pdftext page imports safely into WordPress.'
    && (($document['pages'][0]['pdftext_source']['refs'][0]['url'] ?? null) === '#page-0-3');
$payloadExcluded = !str_contains($encoded, 'WordPress negative page-map')
    && !str_contains($encoded, 'valid zero-key WordPress adapter payload');

if (!$negativeDictionaryRejected || !$negativeJsonRejected || !$zeroKeyImported || !$payloadExcluded) {
    throw new RuntimeException('Expected negative pdftext page-map keys to fail closed while zero source-page keys remain importable.');
}

echo '<!-- markerpdf-pdftext-dictionary-negative-page-map-key-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-negative-page-map-key-currentbase',
    'source_truth' => 'markerPDF enumerates the zero-based page list returned by pdftext.dictionary_output; native adapter page maps must reject negative source-page keys before selected WordPress import slicing',
    'support_component' => 'pdf-text-dictionary-core-page-map-boundary',
    'negative_dictionary_output_key_rejected' => $negativeDictionaryRejected,
    'negative_json_pdftext_key_rejected' => $negativeJsonRejected,
    'zero_source_page_key_imported' => $zeroKeyImported,
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'payload_excluded' => $payloadExcluded,
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
