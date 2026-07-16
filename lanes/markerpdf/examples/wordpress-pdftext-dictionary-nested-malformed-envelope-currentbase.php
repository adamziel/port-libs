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
            'bbox' => [72.0, 144.0, 460.0, 158.0],
            'lines' => [[
                'bbox' => [72.0, 144.0, 460.0, 158.0],
                'spans' => [[
                    'text' => $text,
                    'bbox' => [72.0, 144.0, 460.0, 158.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                ]],
            ]],
        ]],
    ];
};

$extractor = new PdfTextDocumentExtractor();
$dictionaryOutputRejected = false;
$pdftextRejected = false;

try {
    $extractor->getTextBlocks([
        'pages' => [
            7100 => $page(7100, 'Top-level stale adapter page must not import'),
        ],
        'dictionary_output' => [
            'pages' => [
                7200 => $page(7200, 'Outer sibling page must not import after malformed nested dictionary_output'),
            ],
            'dictionary_output' => 'not a pdftext page dictionary or page list',
        ],
    ], maxPages: 1);
} catch (InvalidArgumentException) {
    $dictionaryOutputRejected = true;
}

try {
    $extractor->getTextBlocks([
        'pages' => [
            8100 => $page(8100, 'Top-level stale pdftext adapter page must not import'),
        ],
        'pdftext' => [
            'pages' => [
                8200 => $page(8200, 'Outer sibling page must not import after malformed nested pdftext'),
            ],
            'pdftext' => '{"pages":[{"page":8300',
        ],
    ], maxPages: 1);
} catch (InvalidArgumentException) {
    $pdftextRejected = true;
}

$summary = [
    'support_component' => 'pdf-text-dictionary-core',
    'nested_dictionary_output_rejected' => $dictionaryOutputRejected,
    'nested_pdftext_json_rejected' => $pdftextRejected,
    'stale_sibling_pages_imported' => false,
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdftext-dictionary-nested-malformed-envelope '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

if (!$dictionaryOutputRejected || !$pdftextRejected) {
    exit(1);
}
