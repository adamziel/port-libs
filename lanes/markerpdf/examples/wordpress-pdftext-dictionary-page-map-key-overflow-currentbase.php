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
            'raw_private_payload' => "WordPress overflow page-map ref payload {$page} must stay hidden",
        ]],
        'raw_page_payload' => "WordPress overflow page-map page payload {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 144.0, 500.0, 158.0],
            'raw_block_payload' => "WordPress overflow page-map block payload {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 144.0, 500.0, 158.0],
                'raw_line_payload' => "WordPress overflow page-map line payload {$page} must stay hidden",
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 190.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'overflow key link',
                        'bbox' => [190.0, 144.0, 345.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => "WordPress overflow page-map span payload {$page} must stay hidden",
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [345.0, 144.0, 500.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$extractor = new PdfTextDocumentExtractor();
$overflowKey = (string) PHP_INT_MAX . '0';
$overflowDictionaryRejected = false;
$overflowJsonRejected = false;

try {
    $extractor->getTextBlocks([
        'pages' => [
            10 => $page(10, 'Stale overflow adapter ', 'https://example.com/overflow-stale', ' must not render'),
        ],
        'dictionary_output' => [
            $overflowKey => $page(901, 'Overflow keyed dictionary_output ', 'https://example.com/overflow-current', ' must fail closed'),
            '2' => $page(902, 'Positive sibling dictionary_output ', 'https://example.com/overflow-sibling', ' must not render after overflow'),
        ],
        'raw_adapter_payload' => 'overflow dictionary_output WordPress adapter payload must stay hidden',
    ], maxPages: 1);
} catch (InvalidArgumentException) {
    $overflowDictionaryRejected = true;
}

$json = json_encode([
    'metadata' => [
        'source' => 'pdftext --json cache with overflow page-map key',
        'raw_private_payload' => 'overflow JSON page-map metadata payload must stay hidden',
    ],
    'page_map' => [
        '+' . $overflowKey . '.0' => $page(1201, 'Overflow JSON page_map ', 'https://example.com/overflow-json-current', ' must fail closed'),
        '3' => $page(1202, 'Positive JSON sibling page ', 'https://example.com/overflow-json-sibling', ' must not render after overflow'),
    ],
    'raw_pdftext_payload' => 'overflow JSON page-map payload must stay hidden',
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

try {
    $extractor->getTextBlocks([
        'pages' => [
            20 => $page(20, 'Stale overflow JSON adapter ', 'https://example.com/overflow-json-stale', ' must not render'),
        ],
        'pdftext' => $json,
        'raw_adapter_payload' => 'overflow JSON WordPress adapter payload must stay hidden',
    ], maxPages: 1);
} catch (InvalidArgumentException) {
    $overflowJsonRejected = true;
}

$document = $extractor->getTextBlocks([
    'dictionary_output' => [
        (string) PHP_INT_MAX => $page(0, 'Maximum integer keyed ', 'https://example.com/max-key-current', ' imports safely for WordPress'),
    ],
    'raw_adapter_payload' => 'valid max-key WordPress adapter payload must stay hidden',
], maxPages: 1);

$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
$pageOut = $document['pages'][0] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-page-map-key-overflow-currentbase',
    'source_truth' => 'markerPDF enumerates the zero-based page list returned by pdftext.dictionary_output; native cache page-map keys must be bounded integer source-page identities before WordPress import slicing',
    'support_component' => 'pdf-text-dictionary-core-page-map-boundary',
    'overflow_dictionary_output_key_rejected' => $overflowDictionaryRejected,
    'overflow_json_pdftext_key_rejected' => $overflowJsonRejected,
    'max_integer_source_key_imported' => ($pageOut['pnum'] ?? null) === 0,
    'safe_span_link_promoted' => ($pageOut['blocks'][0]['lines'][0]['spans'][1]['url'] ?? null) === 'https://example.com/max-key-current',
    'reference_anchor_synthesized' => ($pageOut['pdftext_source']['refs'][0]['url'] ?? null) === '#page-0-4',
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'payload_excluded' => !str_contains($encoded, 'WordPress overflow page-map')
        && !str_contains($encoded, 'valid max-key WordPress adapter payload'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'overflow_dictionary_output_key_rejected',
    'overflow_json_pdftext_key_rejected',
    'max_integer_source_key_imported',
    'safe_span_link_promoted',
    'reference_anchor_synthesized',
    'payload_excluded',
] as $requiredFlag) {
    if (!$flags[$requiredFlag]) {
        throw new RuntimeException('Expected overflow pdftext page-map keys to fail closed before WordPress import: ' . $requiredFlag);
    }
}

echo '<!-- markerpdf-pdftext-dictionary-page-map-key-overflow-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars((string) ($block['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
