<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];
$page = [
    'page' => 12,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'refs' => [[
        'page' => 9,
        'idx' => 2,
        'coord' => [144.0, 216.0],
    ]],
    'blocks' => [[
        'bbox' => [72.0, 96.0, 390.0, 110.0],
        'lines' => [[
            'bbox' => [72.0, 96.0, 390.0, 110.0],
            'spans' => [[
                'text' => 'Review linked section before import',
                'bbox' => [72.0, 96.0, 390.0, 110.0],
                'font' => $font,
            ]],
        ]],
    ]],
];

$extractor = new PdfTextDocumentExtractor();
$document = $extractor->getTextBlocks([$page], maxPages: 1);
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$refs = $document['pages'][0]['pdftext_source']['refs'] ?? [];

$rejectsFractional = static function (array $ref) use ($extractor, $page): bool {
    $candidate = $page;
    $candidate['refs'] = [$ref];

    try {
        $extractor->getTextBlocks([$candidate], maxPages: 1);
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
};

$fractionalPageRejected = $rejectsFractional([
    'page' => 9.5,
    'idx' => 2,
    'coord' => [144.0, 216.0],
]);
$fractionalDestinationPageRejected = $rejectsFractional([
    'url' => '#appendix',
    'dest_page' => 4.25,
    'dest_pos' => [72.0, 144.0],
]);
$fractionalIndexRejected = $rejectsFractional([
    'page' => 9,
    'idx' => 2.5,
    'coord' => [144.0, 216.0],
]);

if (($refs[0]['ref'] ?? null) !== 'page-9-2'
    || ($refs[0]['url'] ?? null) !== '#page-9-2'
    || !$fractionalPageRejected
    || !$fractionalDestinationPageRejected
    || !$fractionalIndexRejected
) {
    throw new RuntimeException('Expected valid pdftext refs to synthesize anchors and fractional ref integer metadata to fail closed.');
}

echo '<!-- markerpdf-pdftext-dictionary-ref-integer-boundary-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-ref-integer-boundary-currentbase',
    'support_component' => 'pdf-text-dictionary-core',
    'source_boundary' => 'pdftext PageReference page/idx integers before markerPDF page metadata conversion',
    'valid_reference_anchor_synthesized' => ($refs[0]['ref'] ?? null) === 'page-9-2' && ($refs[0]['url'] ?? null) === '#page-9-2',
    'fractional_page_rejected' => $fractionalPageRejected,
    'fractional_dest_page_rejected' => $fractionalDestinationPageRejected,
    'fractional_idx_rejected' => $fractionalIndexRejected,
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
