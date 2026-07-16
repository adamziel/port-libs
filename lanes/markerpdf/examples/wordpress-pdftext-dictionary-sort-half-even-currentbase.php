<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];
$span = static fn (string $text, array $bbox): array => [
    'text' => $text,
    'bbox' => $bbox,
    'font' => $font,
    'raw_span_payload' => "hidden {$text} payload",
];

$page = [
    'page' => 17,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'raw_page_payload' => 'sort half-even WordPress page payload must stay hidden',
    'blocks' => [
        [
            'bbox' => [300.0, 0.0, 430.0, 14.0],
            'raw_block_payload' => 'right-column WordPress block payload must stay hidden',
            'lines' => [[
                'bbox' => [300.0, 0.0, 430.0, 14.0],
                'spans' => [$span("Right column first in source\n", [300.0, 0.0, 430.0, 14.0])],
            ]],
        ],
        [
            'bbox' => [72.0, 0.625, 220.0, 14.625],
            'raw_block_payload' => 'left half-even tie WordPress block payload must stay hidden',
            'lines' => [[
                'bbox' => [72.0, 0.625, 220.0, 14.625],
                'spans' => [$span("Left half-even tie\n", [72.0, 0.625, 220.0, 14.625])],
            ]],
        ],
        [
            'bbox' => [72.0, 18.0, 260.0, 32.0],
            'raw_block_payload' => 'second row WordPress block payload must stay hidden',
            'lines' => [[
                'bbox' => [72.0, 18.0, 260.0, 32.0],
                'spans' => [$span("Second row after tie group\n", [72.0, 18.0, 260.0, 32.0])],
            ]],
        ],
    ],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks([$page], maxPages: 1, sort: true);
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$pageOut = $document['pages'][0] ?? [];
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$visibleText = $blocks[0]['text'] ?? '';

$firstBlockText = $pageOut['blocks'][0]['lines'][0]['spans'][0]['text'] ?? null;
$secondBlockText = $pageOut['blocks'][1]['lines'][0]['spans'][0]['text'] ?? null;
$charFirstText = $pageOut['char_blocks'][0]['lines'][0]['spans'][0]['text'] ?? null;
$charFirstVisibleText = is_string($charFirstText) ? rtrim($charFirstText, "\r\n") : null;
$expectedText = 'Left half-even tie Right column first in source Second row after tie group';

if (($document['metadata']['pdftext_options']['sort'] ?? null) !== true
    || $firstBlockText !== 'Left half-even tie'
    || $secondBlockText !== 'Right column first in source'
    || $charFirstVisibleText !== 'Left half-even tie'
    || $visibleText !== $expectedText
    || str_contains($encoded, 'WordPress page payload')
    || str_contains($encoded, 'WordPress block payload')
    || str_contains($encoded, 'hidden Left half-even tie')
) {
    throw new RuntimeException('Expected pdftext sort=True half-even tie grouping to match upstream before WordPress rendering.');
}

echo '<!-- markerpdf-pdftext-dictionary-sort-half-even-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-sort-half-even-currentbase',
    'source_truth' => 'pdftext.postprocessing.sort_blocks uses Python round(y / tolerance) grouping before horizontal sorting, so exact half-tolerance rows follow half-even tie behavior before markerPDF page conversion',
    'support_component' => 'pdf-text-dictionary-core',
    'sort_option_recorded' => ($document['metadata']['pdftext_options']['sort'] ?? null) === true,
    'first_block_text' => $firstBlockText,
    'second_block_text' => $secondBlockText,
    'char_blocks_sorted' => $charFirstVisibleText === 'Left half-even tie',
    'visible_wordpress_text' => $visibleText,
    'raw_payload_excluded' => !str_contains($encoded, 'WordPress page payload')
        && !str_contains($encoded, 'WordPress block payload')
        && !str_contains($encoded, 'hidden Left half-even tie'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
