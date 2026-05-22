<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\FontStyleCleaner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$blocks = [
    [
        'type' => 'Text',
        'lines' => [
            [
                'spans' => [
                    ['text' => 'Keep ', 'font' => 'Helvetica', 'font_weight' => 400],
                    ['text' => 'media library', 'font' => 'Helvetica-Bold', 'font_weight' => 400],
                    ['text' => ' captions ', 'font' => 'Helvetica', 'font_weight' => 400],
                    ['text' => 'reviewable', 'font' => 'Helvetica-Italic', 'font_weight' => 400],
                    ['text' => ' during import.', 'font' => 'Helvetica', 'font_weight' => 400],
                ],
            ],
        ],
    ],
];

$cleaner = new FontStyleCleaner();
$styledBlocks = $cleaner->markBoldItalicSpans($blocks);
$markdown = $cleaner->mergeStyledLine($styledBlocks[0]['lines'][0]['spans']);

$html = htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$html = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html) ?? $html;
$html = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $html) ?? $html;

echo "<!-- wp:paragraph -->\n";
echo '<p>' . $html . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
