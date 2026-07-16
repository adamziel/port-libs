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
            'idx' => 11,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => "raw duplicate-key ref payload {$page} must stay hidden",
        ]],
        'raw_page_payload' => "raw duplicate-key page payload {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 144.0, 500.0, 158.0],
            'raw_block_payload' => "raw duplicate-key block payload {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 144.0, 500.0, 158.0],
                'raw_line_payload' => "raw duplicate-key line payload {$page} must stay hidden",
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 190.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'duplicate-key link',
                        'bbox' => [190.0, 144.0, 340.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => "raw duplicate-key span payload {$page} must stay hidden",
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [340.0, 144.0, 500.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$jsonValue = static fn (mixed $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$extractor = new PdfTextDocumentExtractor();

$duplicateRejected = false;
try {
    $json = '{"metadata":{"source":"pdftext --json duplicate page map","raw_private_payload":"duplicate JSON metadata must stay hidden"},'
        . '"pages":{"17":' . $jsonValue($page(1700, 'First duplicate raw JSON ', 'https://example.com/duplicate-first', ' should fail closed')) . ','
        . '"\u0031\u0037":' . $jsonValue($page(1701, 'Second duplicate raw JSON ', 'https://example.com/duplicate-second', ' should fail closed')) . '},'
        . '"raw_pdftext_payload":"duplicate JSON payload must stay hidden"}';
    $extractor->getTextBlocks([
        'pages' => [
            1200 => $page(1200, 'Stale duplicate-key adapter ', 'https://example.com/duplicate-stale', ' should not import'),
        ],
        'pdftext' => $json,
        'raw_adapter_payload' => 'duplicate JSON stale adapter payload must stay hidden',
    ], maxPages: 1);
} catch (InvalidArgumentException) {
    $duplicateRejected = true;
}

$uniqueJson = json_encode([
    'metadata' => [
        'source' => 'pdftext --json unique page map',
        'raw_private_payload' => 'unique JSON metadata payload must stay hidden',
    ],
    'page_map' => [
        '2100' => $page(2100, 'Skipped unique JSON cover ', 'https://example.com/unique-cover', ' should not import'),
        '2101' => $page(2101, 'Unique raw JSON pdftext ', 'https://example.com/unique-current', ' remains importable'),
        '2102' => $page(2102, 'Skipped unique JSON appendix ', 'https://example.com/unique-appendix', ' should not import'),
    ],
    'raw_pdftext_payload' => 'unique JSON payload must stay hidden',
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$document = $extractor->getTextBlocks(['pdftext' => $uniqueJson], maxPages: 1, startPage: 1);
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-json-duplicate-key-currentbase',
    'source_truth' => 'markerPDF consumes ordered page dictionaries returned by pdftext.dictionary_output; raw JSON cache maps with duplicate object keys are ambiguous and fail closed before WordPress import',
    'support_component' => 'pdf-text-dictionary-core',
    'duplicate_json_page_keys_rejected' => $duplicateRejected,
    'unique_json_page_map_imported' => ($document['pages'][0]['pnum'] ?? null) === 2101,
    'selected_page_range' => $document['page_range'],
    'source_pages' => $document['metadata']['source_pages'] ?? null,
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'duplicate_stale_payload_excluded' => !str_contains($encoded, 'Stale duplicate-key adapter')
        && !str_contains($encoded, 'duplicate JSON stale adapter payload')
        && !str_contains($encoded, 'duplicate JSON payload'),
    'unique_cover_excluded' => !str_contains($encoded, 'Skipped unique JSON cover')
        && !str_contains($encoded, 'Skipped unique JSON appendix'),
    'raw_payload_excluded' => !str_contains($encoded, 'raw duplicate-key page payload 2101')
        && !str_contains($encoded, 'raw duplicate-key ref payload 2101')
        && !str_contains($encoded, 'raw duplicate-key block payload 2101')
        && !str_contains($encoded, 'raw duplicate-key span payload 2101'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (!$flags['duplicate_json_page_keys_rejected']
    || !$flags['unique_json_page_map_imported']
    || !$flags['duplicate_stale_payload_excluded']
    || !$flags['unique_cover_excluded']
    || !$flags['raw_payload_excluded']
) {
    throw new RuntimeException('Expected duplicate raw JSON pdftext page-map keys to fail closed while unique raw JSON maps import.');
}

echo '<!-- markerpdf-pdftext-dictionary-json-duplicate-key-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
