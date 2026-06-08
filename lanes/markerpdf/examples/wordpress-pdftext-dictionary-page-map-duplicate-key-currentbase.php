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
            'bbox' => [72.0, 96.0, 520.0, 112.0],
            'lines' => [[
                'bbox' => [72.0, 96.0, 520.0, 112.0],
                'spans' => [[
                    'text' => $text,
                    'bbox' => [72.0, 96.0, 520.0, 112.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                ]],
            ]],
        ]],
    ];
};

$extractor = new PdfTextDocumentExtractor();
$duplicateRejected = false;
try {
    $extractor->getTextBlocks([
        'pages' => [
            $page(40, 'Stale duplicate-key adapter page must not render.'),
        ],
        'dictionary_output' => [
            '01' => $page(401, 'Duplicate alias page one should fail closed.'),
            '+1.0' => $page(501, 'Duplicate alias page two should fail closed.'),
        ],
        'raw_adapter_payload' => 'duplicate-key adapter payload must not cross',
    ], maxPages: 1);
} catch (InvalidArgumentException) {
    $duplicateRejected = true;
}

$document = $extractor->getTextBlocks([
    'dictionary_output' => [
        '+2.0' => $page(402, 'Skipped unique keyed cover should not render.'),
        '3' => $page(403, 'Unique keyed pdftext page imports safely.'),
    ],
], maxPages: 1, startPage: 1);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-page-map-duplicate-key-currentbase',
    'source_truth' => 'markerPDF consumes the ordered page list returned by pdftext.dictionary_output; native cache object maps with duplicate normalized source-page keys are ambiguous and fail closed before WordPress import',
    'support_component' => 'pdf-text-dictionary-core',
    'duplicate_normalized_keys_rejected' => $duplicateRejected,
    'unique_keyed_map_imported' => ($document['pages'][0]['pnum'] ?? null) === 403,
    'selected_page_range' => $document['page_range'],
    'source_pages' => $document['metadata']['source_pages'] ?? null,
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'stale_duplicate_adapter_excluded' => !str_contains($encoded, 'Stale duplicate-key adapter page')
        && !str_contains($encoded, 'duplicate-key adapter payload')
        && !str_contains($encoded, 'Duplicate alias page'),
    'unique_cover_excluded' => !str_contains($encoded, 'Skipped unique keyed cover'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (!$flags['duplicate_normalized_keys_rejected']
    || !$flags['unique_keyed_map_imported']
    || !$flags['stale_duplicate_adapter_excluded']
    || !$flags['unique_cover_excluded']
) {
    throw new RuntimeException('Expected duplicate normalized pdftext page-map keys to fail closed while unique maps import.');
}

echo '<!-- markerpdf-pdftext-dictionary-page-map-duplicate-key-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
