<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pages = [
    [
        'pnum' => 0,
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'blocks' => [
            [
                'type' => 'Text',
                'bbox' => [72.0, 96.0, 480.0, 114.0],
                'lines' => [
                    [
                        'bbox' => [72.0, 96.0, 480.0, 114.0],
                        'spans' => [
                            ['text' => 'Imported ', 'font' => 'Helvetica'],
                            ['text' => 'media captions', 'font' => 'Helvetica-Bold', 'bold' => true],
                            ['text' => ' stay ', 'font' => 'Helvetica'],
                            ['text' => 'reviewable', 'font' => 'Helvetica-Italic', 'italic' => true],
                            ['text' => ' during import.', 'font' => 'Helvetica'],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($pages));

foreach ($blocks as $block) {
    if (($block['block_type'] ?? '') !== 'Text') {
        continue;
    }

    $html = htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html) ?? $html;
    $html = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $html) ?? $html;

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . $html . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
