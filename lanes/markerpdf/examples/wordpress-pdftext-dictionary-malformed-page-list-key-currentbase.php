<?php

declare(strict_types=1);

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
            'bbox' => [72.0, 144.0, 440.0, 158.0],
            'lines' => [[
                'bbox' => [72.0, 144.0, 440.0, 158.0],
                'spans' => [[
                    'text' => $text,
                    'bbox' => [72.0, 144.0, 440.0, 158.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'raw_span_payload' => 'malformed page-list WordPress span payload must stay hidden',
                ]],
            ]],
        ]],
    ];
};

$extractor = new PdfTextDocumentExtractor();

$dictionaryOutputRejected = false;
try {
    $extractor->getTextBlocks([
        'pages' => [
            1200 => $page(1200, 'Stale malformed dictionary_output adapter page must not render.'),
        ],
        'dictionary_output' => [
            'metadata' => [
                'source' => 'corrupt native dictionary_output cache',
                'raw_private_payload' => 'malformed dictionary_output metadata payload must stay hidden',
            ],
            'pages' => 'not an ordered pdftext page list',
            2200 => $page(2200, 'Sibling dictionary_output row must fail closed.'),
            'raw_pdftext_payload' => 'malformed dictionary_output payload must stay hidden',
        ],
        'raw_adapter_payload' => 'malformed dictionary_output outer payload must stay hidden',
    ], maxPages: 1);
} catch (InvalidArgumentException) {
    $dictionaryOutputRejected = true;
}

$json = json_encode([
    'metadata' => [
        'source' => 'corrupt pdftext --json page_map cache',
        'raw_private_payload' => 'malformed pdftext page_map metadata payload must stay hidden',
    ],
    'page_map' => 'not a pdftext page map',
    3300 => $page(3300, 'Sibling pdftext page_map row must fail closed.'),
    'raw_pdftext_payload' => 'malformed pdftext page_map payload must stay hidden',
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$pdftextRejected = false;
try {
    $extractor->getTextBlocks([
        'pages' => [
            1300 => $page(1300, 'Stale malformed pdftext adapter page must not render.'),
        ],
        'pdftext' => $json,
        'raw_adapter_payload' => 'malformed pdftext outer payload must stay hidden',
    ], maxPages: 1);
} catch (InvalidArgumentException) {
    $pdftextRejected = true;
}

$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-malformed-page-list-key-currentbase',
    'source_truth' => 'pdftext.dictionary_output emits an ordered list of page dictionaries; known native cache page-list keys must be valid page dictionaries or fail closed before WordPress paragraph conversion',
    'support_component' => 'pdf-text-dictionary-core-boundary',
    'malformed_dictionary_output_pages_key_rejected' => $dictionaryOutputRejected,
    'malformed_pdftext_page_map_key_rejected' => $pdftextRejected,
    'sibling_numeric_rows_not_imported' => $dictionaryOutputRejected && $pdftextRejected,
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['malformed_dictionary_output_pages_key_rejected']
    || !$flags['malformed_pdftext_page_map_key_rejected']
    || !$flags['sibling_numeric_rows_not_imported']
) {
    throw new RuntimeException('Expected malformed pdftext page-list cache keys to fail closed before WordPress import.');
}

echo '<!-- markerpdf-pdftext-dictionary-malformed-page-list-key-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
