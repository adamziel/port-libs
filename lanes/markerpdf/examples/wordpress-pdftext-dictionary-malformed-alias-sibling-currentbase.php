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
            'idx' => 12,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => 'malformed alias sibling ref payload must stay hidden',
        ]],
        'raw_page_payload' => 'malformed alias sibling page payload must stay hidden',
        'blocks' => [[
            'bbox' => [72.0, 144.0, 480.0, 158.0],
            'lines' => [[
                'bbox' => [72.0, 144.0, 480.0, 158.0],
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 180.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'alias sibling link',
                        'bbox' => [180.0, 144.0, 320.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => 'malformed alias sibling span payload must stay hidden',
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [320.0, 144.0, 480.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$extractor = new PdfTextDocumentExtractor();

$dictionaryOutputRejected = false;
try {
    $extractor->getTextBlocks([
        'pages' => [
            9100 => $page(9100, 'Stale alias sibling adapter ', 'https://example.com/alias-stale', ' should not render'),
        ],
        'dictionary_output' => [
            'metadata' => [
                'source' => 'corrupt native dictionary_output alias cache',
                'raw_private_payload' => 'malformed alias dictionary_output metadata must stay hidden',
            ],
            'pages' => 'not an ordered pdftext page list',
            'page_map' => [
                9201 => $page(9201, 'Valid page_map sibling ', 'https://example.com/alias-current', ' must not mask malformed pages'),
            ],
            'raw_pdftext_payload' => 'malformed alias dictionary_output payload must stay hidden',
        ],
        'raw_adapter_payload' => 'malformed alias stale adapter payload must stay hidden',
    ], maxPages: 1);
} catch (InvalidArgumentException) {
    $dictionaryOutputRejected = true;
}

$json = json_encode([
    'metadata' => [
        'source' => 'corrupt pdftext --json alias cache',
        'raw_private_payload' => 'malformed alias pdftext metadata must stay hidden',
    ],
    'page_map' => 'not a pdftext page map',
    'pageMap' => [
        9301 => $page(9301, 'Valid pageMap sibling ', 'https://example.com/json-alias-current', ' must not mask malformed page_map'),
    ],
    'raw_pdftext_payload' => 'malformed alias pdftext payload must stay hidden',
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$pdftextRejected = false;
try {
    $extractor->getTextBlocks([
        'pages' => [
            9300 => $page(9300, 'Stale raw JSON alias adapter ', 'https://example.com/json-alias-stale', ' should not render'),
        ],
        'pdftext' => $json,
        'raw_adapter_payload' => 'malformed alias pdftext outer payload must stay hidden',
    ], maxPages: 1);
} catch (InvalidArgumentException) {
    $pdftextRejected = true;
}

$validDocument = $extractor->getTextBlocks([
    'dictionary_output' => [
        'metadata' => [
            'source' => 'native dictionary_output alias cache',
            'raw_private_payload' => 'valid alias-only metadata must stay hidden',
        ],
        'pageMap' => [
            9401 => $page(9401, 'Valid alias-only pageMap ', 'https://example.com/alias-only-current', ' imports without Python'),
        ],
        'raw_pdftext_payload' => 'valid alias-only payload must stay hidden',
    ],
], maxPages: 1);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($validDocument['pages']));
$encoded = json_encode($validDocument, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-malformed-alias-sibling-currentbase',
    'source_truth' => 'markerPDF consumes the single ordered page list returned by pdftext.dictionary_output; native alias caches with any malformed known page-list sibling fail closed before WordPress paragraph conversion',
    'support_component' => 'pdf-text-dictionary-core-boundary',
    'malformed_dictionary_output_pages_before_page_map_rejected' => $dictionaryOutputRejected,
    'malformed_pdftext_page_map_before_pageMap_rejected' => $pdftextRejected,
    'valid_alias_without_malformed_sibling_imported' => ($validDocument['pages'][0]['pnum'] ?? null) === 9401
        && ($blocks[0]['text'] ?? '') === 'Valid alias-only pageMap [alias sibling link](https://example.com/alias-only-current) imports without Python',
    'valid_alias_private_payload_excluded' => !str_contains($encoded, 'valid alias-only metadata')
        && !str_contains($encoded, 'valid alias-only payload')
        && !str_contains($encoded, 'malformed alias sibling page payload'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['malformed_dictionary_output_pages_before_page_map_rejected']
    || !$flags['malformed_pdftext_page_map_before_pageMap_rejected']
    || !$flags['valid_alias_without_malformed_sibling_imported']
    || !$flags['valid_alias_private_payload_excluded']
) {
    throw new RuntimeException('Expected malformed pdftext alias siblings to fail closed while valid alias-only caches remain importable.');
}

echo '<!-- markerpdf-pdftext-dictionary-malformed-alias-sibling-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
