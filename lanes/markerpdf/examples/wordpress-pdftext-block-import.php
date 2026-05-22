<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextBlockConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdftextPage = [
    'page' => 12,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'rotation' => 0,
    'blocks' => [
        [
            'lines' => [
                [
                    'bbox' => [72.0, 96.0, 430.0, 110.0],
                    'spans' => [
                        ['text' => "Shared host docu-\nment ", 'bbox' => [72.0, 96.0, 190.0, 110.0], 'font' => ['name' => 'Helvetica', 'flags' => (1 << 5), 'weight' => 400, 'size' => 11.0]],
                        ['text' => "handoff\r\n", 'bbox' => [190.0, 96.0, 260.0, 110.0], 'font' => ['name' => 'Helvetica-Bold', 'flags' => (1 << 5) | (1 << 18), 'weight' => 700, 'size' => 11.0]],
                    ],
                ],
                [
                    'bbox' => [72.0, 122.0, 470.0, 136.0],
                    'spans' => [
                        ['text' => 'The converted Marker page shape can feed Gutenberg paragraphs without Python model loading.', 'bbox' => [72.0, 122.0, 470.0, 136.0], 'font' => ['name' => 'Helvetica', 'flags' => null, 'weight' => 400, 'size' => 10.0]],
                    ],
                ],
            ],
        ],
    ],
];

$converter = new PdfTextBlockConverter();
$processor = new MarkdownPostProcessor();
$page = $converter->pdftextFormatToPage($pdftextPage, 0);
$blocks = $processor->mergeBlocks($processor->mergeSpans([$page]));

foreach ($blocks as $block) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
