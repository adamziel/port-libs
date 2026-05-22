<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\EquationReplacer;
use PortLibs\MarkerPDF\MarkdownPostProcessor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'pnum' => 0,
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'layout' => [
        'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
        'bboxes' => [
            ['label' => 'Formula', 'bbox' => [120.0, 220.0, 560.0, 260.0]],
        ],
    ],
    'blocks' => [
        [
            'type' => 'Text',
            'bbox' => [60.0, 88.0, 290.0, 150.0],
            'lines' => [
                ['prelim_text' => 'Imported physics note.', 'bbox' => [60.0, 90.0, 240.0, 104.0]],
                ['prelim_text' => 'E = m c^2', 'bbox' => [62.0, 112.0, 276.0, 130.0]],
                ['prelim_text' => 'Review math before publishing.', 'bbox' => [60.0, 136.0, 290.0, 150.0]],
            ],
        ],
    ],
];

$replaced = (new EquationReplacer())->replaceEquations([$page], ['$$E=mc^2$$']);
$processor = new MarkdownPostProcessor();
$textBlocks = $processor->mergeBlocks($replaced['pages']);
$fullText = $processor->getFullText($textBlocks);

foreach (preg_split('/\n{2,}/', trim($fullText)) ?: [] as $paragraph) {
    $paragraph = trim($paragraph);
    if ($paragraph === '') {
        continue;
    }

    if (str_starts_with($paragraph, '$$') && str_ends_with($paragraph, '$$')) {
        echo "<!-- wp:html -->\n";
        echo '<div class="wp-block-markerpdf-equation">' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</div>\n";
        echo "<!-- /wp:html -->\n\n";
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo json_encode([
    'block_stats' => [
        'equations' => $replaced['metadata'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
