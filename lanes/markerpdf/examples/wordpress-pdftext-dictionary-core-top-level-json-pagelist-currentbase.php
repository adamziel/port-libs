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
            'idx' => 9,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => "top-level JSON WordPress ref payload {$page} must stay hidden",
        ]],
        'raw_page_payload' => "top-level JSON WordPress page payload {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 144.0, 500.0, 158.0],
            'raw_block_payload' => "top-level JSON WordPress block payload {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 144.0, 500.0, 158.0],
                'raw_line_payload' => "top-level JSON WordPress line payload {$page} must stay hidden",
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 190.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'WordPress import link',
                        'bbox' => [190.0, 144.0, 342.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => "top-level JSON WordPress span payload {$page} must stay hidden",
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [342.0, 144.0, 500.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$cover = $page(13100, 'Skipped top-level JSON cover ', 'https://example.com/top-json-cover', ' should not import');
$selected = $page(13101, 'Top-level raw JSON pdftext ', 'https://example.com/top-json-current', ' imports for WordPress');
$appendix = $page(13102, 'Skipped top-level JSON appendix ', 'https://example.com/top-json-appendix', ' should not import');
$json = "\xEF\xBB\xBF" . json_encode([
    13100 => $cover,
    13101 => $selected,
    13102 => $appendix,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$document = (new PdfTextDocumentExtractor())->getTextBlocks([
    'pages' => $json,
    'metadata' => [
        'source' => 'legacy adapter top-level pages JSON cache',
        'raw_private_payload' => 'top-level JSON WordPress metadata payload must stay hidden',
    ],
    'raw_adapter_payload' => 'top-level JSON WordPress adapter payload must stay hidden',
], maxPages: 1, startPage: 1);

$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
$text = $blocks[0]['text'] ?? '';
$pageOut = $document['pages'][0] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-core-top-level-json-pagelist-currentbase',
    'source_truth' => 'pdftext.dictionary_output emits an ordered page list; native WordPress adapters may preserve that list as a raw JSON string under a top-level pages/page_map/pageMap cache key before markerPDF converts selected pages',
    'support_component' => 'pdf-text-dictionary-core',
    'top_level_json_pages_selected' => ($pageOut['pnum'] ?? null) === 13101,
    'selected_page_range_preserved' => ($document['page_range'] ?? null) === [1],
    'source_page_count_preserved' => ($document['metadata']['source_pages'] ?? null) === 3,
    'safe_pdftext_link_promoted' => ($pageOut['blocks'][0]['lines'][0]['spans'][1]['url'] ?? null) === 'https://example.com/top-json-current',
    'pdftext_ref_preserved' => ($pageOut['pdftext_source']['refs'][0]['url'] ?? null) === '#page-13101-9',
    'stale_pages_excluded' => !str_contains($encoded, 'Skipped top-level JSON cover')
        && !str_contains($encoded, 'Skipped top-level JSON appendix'),
    'wrapper_payload_excluded' => !str_contains($encoded, 'top-level JSON WordPress metadata payload')
        && !str_contains($encoded, 'top-level JSON WordPress adapter payload')
        && !str_contains($encoded, 'top-level JSON WordPress ref payload 13101')
        && !str_contains($encoded, 'top-level JSON WordPress page payload 13101')
        && !str_contains($encoded, 'top-level JSON WordPress block payload 13101')
        && !str_contains($encoded, 'top-level JSON WordPress span payload 13101'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'top_level_json_pages_selected',
    'selected_page_range_preserved',
    'source_page_count_preserved',
    'safe_pdftext_link_promoted',
    'pdftext_ref_preserved',
    'stale_pages_excluded',
    'wrapper_payload_excluded',
] as $requiredFlag) {
    if (!$flags[$requiredFlag]) {
        throw new RuntimeException('Top-level JSON pdftext dictionary smoke failed: ' . $requiredFlag);
    }
}

echo '<!-- markerpdf-pdftext-dictionary-core-top-level-json-pagelist-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo '<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
