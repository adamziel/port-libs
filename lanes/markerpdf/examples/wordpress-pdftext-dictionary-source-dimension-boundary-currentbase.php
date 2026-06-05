<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'page' => 16,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'blocks' => [[
        'bbox' => [72.0, 96.0, 420.0, 112.0],
        'lines' => [[
            'bbox' => [72.0, 96.0, 420.0, 112.0],
            'spans' => [[
                'text' => "Positive source dimensions reach WordPress review.\n",
                'bbox' => [72.0, 96.0, 420.0, 112.0],
                'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
            ]],
        ]],
    ]],
];

$extractor = new PdfTextDocumentExtractor();
$zeroWidthRejected = false;
$negativeHeightRejected = false;
$zeroBboxWidthRejected = false;
$zeroBboxHeightRejected = false;

$zeroWidth = $page;
$zeroWidth['width'] = 0.0;
try {
    $extractor->getTextBlocks([$zeroWidth], maxPages: 1);
} catch (InvalidArgumentException) {
    $zeroWidthRejected = true;
}

$negativeHeight = $page;
$negativeHeight['height'] = -792.0;
try {
    $extractor->getTextBlocks([$negativeHeight], maxPages: 1);
} catch (InvalidArgumentException) {
    $negativeHeightRejected = true;
}

$zeroBboxWidth = $page;
$zeroBboxWidth['bbox'] = [72.0, 96.0, 72.0, 792.0];
try {
    $extractor->getTextBlocks([$zeroBboxWidth], maxPages: 1);
} catch (InvalidArgumentException) {
    $zeroBboxWidthRejected = true;
}

$zeroBboxHeight = $page;
$zeroBboxHeight['bbox'] = [0.0, 96.0, 612.0, 96.0];
try {
    $extractor->getTextBlocks([$zeroBboxHeight], maxPages: 1);
} catch (InvalidArgumentException) {
    $zeroBboxHeightRejected = true;
}

$document = $extractor->getTextBlocks([$page], maxPages: 1);
$source = $document['pages'][0]['pdftext_source'] ?? [];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
$visibleText = $blocks[0]['text'] ?? '';

if (!$zeroWidthRejected || !$negativeHeightRejected || !$zeroBboxWidthRejected || !$zeroBboxHeightRejected) {
    throw new RuntimeException('Expected nonpositive pdftext source dimensions and degenerate page bboxes to be rejected before WordPress metadata.');
}
if (($source['width'] ?? null) !== 612.0 || ($source['height'] ?? null) !== 792.0) {
    throw new RuntimeException('Expected positive pdftext source dimensions to remain available for WordPress review.');
}
if ($visibleText !== 'Positive source dimensions reach WordPress review.') {
    throw new RuntimeException('Expected valid pdftext dictionary text to remain importable.');
}

echo '<!-- markerpdf-pdftext-dictionary-source-dimension-boundary-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-source-dimension-boundary-currentbase',
    'source_truth' => 'pdftext.get_pages derives page width and height from absolute PDF page bbox extents before markerPDF converts dictionary_output pages',
    'support_component' => 'pdf-text-dictionary-core-page-source-boundary',
    'pdftext_source' => $source,
    'positive_dimensions_preserved' => $source['width'] === 612.0 && $source['height'] === 792.0,
    'zero_width_rejected' => $zeroWidthRejected,
    'negative_height_rejected' => $negativeHeightRejected,
    'zero_bbox_width_rejected' => $zeroBboxWidthRejected,
    'zero_bbox_height_rejected' => $zeroBboxHeightRejected,
    'visible_text_imported' => $visibleText === 'Positive source dimensions reach WordPress review.',
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) $block['pnum'] . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
