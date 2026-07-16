<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'page' => 12,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'raw_json_adapter_payload' => 'json adapter payload must not cross dictionary_output',
    'refs' => [[
        'url' => '#page-3-xy',
        'page' => 3,
        'dest_pos' => [72.0, 96.0],
        'raw_private_payload' => 'json ref payload must not cross dictionary_output',
    ]],
    'blocks' => [[
        'bbox' => [72.0, 96.0, 370.0, 110.0],
        'lines' => [[
            'bbox' => [72.0, 96.0, 370.0, 110.0],
            'spans' => [
                [
                    'text' => 'Read ',
                    'bbox' => [72.0, 96.0, 104.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                ],
                [
                    'text' => 'plugin docs',
                    'bbox' => [104.0, 96.0, 172.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'url' => 'https://example.com/import)docs',
                    'chars' => [['char' => 'p', 'bbox' => [104.0, 96.0, 110.0, 110.0]]],
                ],
                [
                    'text' => ' but review script action',
                    'bbox' => [172.0, 96.0, 370.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'url' => 'javascript:alert(1)',
                    'raw_action_payload' => 'javascript payload must not render',
                ],
            ],
        ]],
    ]],
];

$jsonDecodedPage = json_decode(json_encode($page, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: 'null');
$document = (new PdfTextDocumentExtractor())->getTextBlocks([$jsonDecodedPage], maxPages: 1);
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$spanRows = $document['pages'][0]['blocks'][0]['lines'][0]['spans'] ?? [];
$refs = $document['pages'][0]['pdftext_source']['refs'] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$safeLinkPromoted = ($spanRows[1]['url'] ?? null) === 'https://example.com/import)docs';
$unsafeLinkReviewOnly = ($spanRows[2]['pdftext_url_is_safe'] ?? null) === false
    && !array_key_exists('url', $spanRows[2] ?? []);
$payloadExcluded = !str_contains($encoded, 'json adapter payload must not cross dictionary_output')
    && !str_contains($encoded, 'json ref payload must not cross dictionary_output')
    && !str_contains($encoded, 'javascript payload must not render');
$visibleText = $blocks[0]['text'] ?? '';
$visibleTextSafe = $visibleText === 'Read [plugin docs](https://example.com/import\\)docs) but review script action'
    && !str_contains($visibleText, 'javascript:');

if (!$safeLinkPromoted || !$unsafeLinkReviewOnly || !$payloadExcluded || !$visibleTextSafe) {
    throw new RuntimeException('Expected JSON-decoded pdftext dictionaries to normalize before WordPress rendering.');
}

echo '<!-- markerpdf-pdftext-dictionary-json-object-boundary-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-json-object-boundary-currentbase',
    'source_truth' => 'pdftext.dictionary_output exposes structured page/block/line/span dictionaries; PHP default json_decode returns stdClass dictionaries that are normalized before native markerPDF import',
    'support_component' => 'pdf-text-dictionary-core',
    'page_range' => $document['page_range'],
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'source_page' => $document['pages'][0]['pdftext_source']['page'] ?? null,
    'safe_link_promoted' => $safeLinkPromoted,
    'unsafe_link_review_only' => $unsafeLinkReviewOnly,
    'refs' => $refs,
    'json_adapter_payload_excluded' => $payloadExcluded,
    'visible_wordpress_text' => $visibleText,
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
