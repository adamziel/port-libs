<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\LayoutAnnotator;
use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\MarkerSettings;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'pnum' => 0,
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'layout' => [
        'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
        'bboxes' => [
            ['label' => 'Title', 'bbox' => [120.0, 80.0, 1000.0, 140.0]],
            ['label' => 'Text', 'bbox' => [120.0, 200.0, 1000.0, 260.0]],
            ['label' => 'Picture', 'bbox' => [120.0, 320.0, 1000.0, 480.0]],
        ],
    ],
    'blocks' => [
        ['bbox' => [60.0, 42.0, 270.0, 54.0], 'lines' => [['text' => 'migration', 'bbox' => [60.0, 42.0, 270.0, 54.0]]]],
        ['bbox' => [280.0, 42.0, 500.0, 54.0], 'lines' => [['text' => 'packet', 'bbox' => [280.0, 42.0, 500.0, 54.0]]]],
        ['bbox' => [60.0, 102.0, 420.0, 126.0], 'lines' => [['text' => 'Review imported content before publishing.', 'bbox' => [60.0, 102.0, 420.0, 126.0]]]],
        ['bbox' => [60.0, 164.0, 500.0, 224.0], 'lines' => [['text' => 'Screenshot placeholder text.', 'bbox' => [60.0, 164.0, 500.0, 224.0]]]],
    ],
];

$settings = new MarkerSettings();
$annotated = (new LayoutAnnotator())->annotateBlockTypes([$page]);
$annotated[0]['blocks'] = array_values(array_filter(
    $annotated[0]['blocks'],
    static fn (array $block): bool => !in_array($block['block_type'], $settings->badSpanTypes(), true)
));

$merged = (new MarkdownPostProcessor())->mergeBlocks($annotated);

foreach ($merged as $block) {
    if ($block['block_type'] === 'Title') {
        $text = trim(ltrim($block['text'], '# '));
        echo "<!-- wp:heading -->\n";
        echo '<h1>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h1>\n";
        echo "<!-- /wp:heading -->\n\n";
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars(trim($block['text']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
