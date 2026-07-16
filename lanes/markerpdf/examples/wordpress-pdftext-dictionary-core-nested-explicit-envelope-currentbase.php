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
            'idx' => 3,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => "nested explicit WordPress ref payload {$page} must stay hidden",
        ]],
        'raw_page_payload' => "nested explicit WordPress page payload {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 144.0, 450.0, 158.0],
            'lines' => [[
                'bbox' => [72.0, 144.0, 450.0, 158.0],
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 170.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'nested cache link',
                        'bbox' => [170.0, 144.0, 302.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => "nested explicit WordPress span payload {$page} must stay hidden",
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [302.0, 144.0, 450.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$staleSelected = $page(1201, 'Stale nested wrapper ', 'https://example.com/stale-nested-wrapper', ' should not import');
$currentSelected = $page(2201, 'Nested explicit pdftext ', 'https://example.com/current-nested-cache', ' imports for WordPress');

$document = (new PdfTextDocumentExtractor())->getTextBlocks([
    'pages' => [
        1101 => $page(1101, 'Top-level stale adapter ', 'https://example.com/top-stale', ' should not import'),
    ],
    'dictionary_output' => [
        'metadata' => [
            'source' => 'outer native adapter cache',
            'raw_private_payload' => 'outer nested explicit metadata must stay hidden',
        ],
        'pages' => [
            1201 => $staleSelected,
        ],
        'dictionary_output' => [
            'metadata' => [
                'source' => 'nested pdftext.dictionary_output cache',
                'raw_private_payload' => 'nested explicit metadata must stay hidden',
            ],
            'pages' => [
                2201 => $currentSelected,
            ],
            'raw_pdftext_payload' => 'nested explicit payload must stay hidden',
        ],
        'raw_pdftext_payload' => 'outer explicit payload must stay hidden',
    ],
    'raw_adapter_payload' => 'top-level nested explicit payload must stay hidden',
], maxPages: 1);

$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
$text = $blocks[0]['text'] ?? '';
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$pageOut = $document['pages'][0] ?? [];

$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-core-nested-explicit-envelope-currentbase',
    'source_truth' => 'pdftext.dictionary_output emits an ordered page list; native adapters may wrap that selected cache inside another explicit dictionary_output envelope, but only the nested page list should reach WordPress paragraphs',
    'support_component' => 'pdf-text-dictionary-core',
    'nested_explicit_cache_selected' => ($pageOut['pnum'] ?? null) === 2201,
    'stale_wrapper_pages_excluded' => !str_contains($encoded, 'Stale nested wrapper') && !str_contains($encoded, 'Top-level stale adapter'),
    'safe_pdftext_link_promoted' => ($pageOut['blocks'][0]['lines'][0]['spans'][1]['url'] ?? null) === 'https://example.com/current-nested-cache',
    'pdftext_ref_preserved' => ($pageOut['pdftext_source']['refs'][0]['url'] ?? null) === '#page-2201-3',
    'wrapper_payload_excluded' => !str_contains($encoded, 'outer nested explicit metadata')
        && !str_contains($encoded, 'outer explicit payload')
        && !str_contains($encoded, 'nested explicit metadata')
        && !str_contains($encoded, 'nested explicit payload')
        && !str_contains($encoded, 'nested explicit WordPress page payload 2201')
        && !str_contains($encoded, 'nested explicit WordPress span payload 2201'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'nested_explicit_cache_selected',
    'stale_wrapper_pages_excluded',
    'safe_pdftext_link_promoted',
    'pdftext_ref_preserved',
    'wrapper_payload_excluded',
] as $requiredFlag) {
    if (!$flags[$requiredFlag]) {
        throw new RuntimeException('Nested explicit pdftext dictionary smoke failed: ' . $requiredFlag);
    }
}

echo '<!-- markerpdf-pdftext-dictionary-core-nested-explicit-envelope-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo '<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
