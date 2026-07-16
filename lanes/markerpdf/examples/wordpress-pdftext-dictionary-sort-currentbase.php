<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$block = static function (string $text, array $bbox): array {
    return [
        'bbox' => $bbox,
        'lines' => [[
            'bbox' => $bbox,
            'spans' => [[
                'text' => $text,
                'bbox' => $bbox,
                'font' => ['name' => 'Helvetica', 'flags' => null, 'weight' => 400, 'size' => 11.0],
            ]],
        ]],
    ];
};

$page = [
    'page' => 22,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'rotation' => 0,
    'blocks' => [
        $block('Later audit note stays below columns.', [72.0, 148.0, 390.0, 164.0]),
        $block('Right column import detail.', [330.0, 104.2, 540.0, 118.0]),
        $block('Left column import summary.', [72.0, 104.0, 270.0, 118.0]),
    ],
];

$extractor = new PdfTextDocumentExtractor();
$unsorted = $extractor->getTextBlocks([$page], maxPages: 1);
$document = $extractor->getTextBlocks([$page], maxPages: 1, sort: true);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));

$textFor = static fn (array $doc): array => array_map(
    static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
    $doc['pages'][0]['blocks']
);

$unsortedText = $textFor($unsorted);
$sortedText = $textFor($document);
$expectedSorted = [
    'Left column import summary.',
    'Right column import detail.',
    'Later audit note stays below columns.',
];

if ($sortedText !== $expectedSorted) {
    throw new RuntimeException('Expected pdftext dictionary sort=true to order blocks by row then column.');
}
if ($unsortedText === $sortedText) {
    throw new RuntimeException('Expected default pdftext dictionary path to preserve supplied unsorted block order.');
}
if (($document['metadata']['pdftext_options']['sort'] ?? null) !== true) {
    throw new RuntimeException('Expected pdftext sort option to be recorded for WordPress review metadata.');
}

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) $block['pnum'] . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-sort-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-sort-currentbase',
    'source_truth' => 'pdftext.extraction.dictionary_output(sort=true) applies postprocessing.sort_blocks before markerPDF converts supplied dictionary pages',
    'support_component' => 'pdf-text-dictionary-core',
    'page_range' => $document['page_range'],
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'unsorted_text' => $unsortedText,
    'sorted_text' => $sortedText,
    'sorted_like_pdftext_sort_blocks' => $sortedText === $expectedSorted,
    'default_unsorted_path_preserved' => $unsortedText !== $sortedText,
    'merged_wordpress_text' => $blocks[0]['text'] ?? '',
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
