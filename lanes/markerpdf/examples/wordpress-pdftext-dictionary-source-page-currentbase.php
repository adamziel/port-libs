<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'page' => 18,
    'bbox' => [0.0, 0.0, 400.0, 600.0],
    'width' => 400.0,
    'height' => 600.0,
    'rotation' => 90,
    'blocks' => [[
        'lines' => [[
            'bbox' => [48.0, 92.0, 340.0, 108.0],
            'spans' => [[
                'text' => 'Source page geometry remains review metadata.',
                'bbox' => [48.0, 92.0, 340.0, 108.0],
                'font' => ['name' => 'Helvetica', 'flags' => null, 'weight' => 400, 'size' => 11.0],
            ]],
        ]],
    ]],
];

$fractionalPage = $page;
$fractionalPage['page'] = 18.5;
$fractionalPageRejected = false;
try {
    (new PdfTextDocumentExtractor())->getTextBlocks([$fractionalPage], maxPages: 1);
} catch (InvalidArgumentException) {
    $fractionalPageRejected = true;
}

$document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1);
$source = $document['pages'][0]['pdftext_source'] ?? null;
$renderedBbox = $document['pages'][0]['bbox'] ?? null;

if ($source !== [
    'page' => 18,
    'bbox' => [0.0, 0.0, 400.0, 600.0],
    'rotation' => 90,
    'width' => 400.0,
    'height' => 600.0,
]) {
    throw new RuntimeException('Expected pdftext source page dimensions to remain available for WordPress review.');
}

if ($renderedBbox !== [0.0, 0.0, 600.0, 400.0]) {
    throw new RuntimeException('Expected rotated Marker page bbox to remain derived from source bbox.');
}

if (!$fractionalPageRejected) {
    throw new RuntimeException('Expected fractional pdftext source page numbers to be rejected before WordPress metadata.');
}

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));

echo '<!-- markerpdf-pdftext-dictionary-source-page-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'pdf-text-dictionary-core-page-source-boundary',
    'source_truth' => 'pdftext.extraction.dictionary_output preserves page-level bbox, width, height, rotation, and page before markerPDF converts the page dictionary',
    'executes_python_pdftext' => false,
    'executes_models_or_external_pdf_tools' => false,
    'pdftext_source' => $source,
    'rendered_marker_bbox' => $renderedBbox,
    'source_dimensions_preserved' => $source['width'] === 400.0 && $source['height'] === 600.0,
    'rotation_bbox_separated' => $renderedBbox === [0.0, 0.0, 600.0, 400.0],
    'fractional_page_rejected' => $fractionalPageRejected,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) $block['pnum'] . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
