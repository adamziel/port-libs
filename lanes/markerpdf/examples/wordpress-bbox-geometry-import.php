<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BboxGeometry;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$geometry = new BboxGeometry();
$spans = [
    ['text' => 'Intro', 'bbox' => [72.0, 96.0, 118.0, 112.0]],
    ['text' => 'duction', 'bbox' => [121.0, 97.0, 188.0, 113.0]],
];

$text = $spans[0]['text'];
$bbox = $spans[0]['bbox'];
if ($geometry->shouldMergeBlocks($bbox, $spans[1]['bbox'])) {
    $text .= $spans[1]['text'];
    $bbox = $geometry->mergeBoxes($bbox, $spans[1]['bbox']);
}

echo "<!-- wp:paragraph -->\n";
echo '<p data-marker-bbox="' . htmlspecialchars(implode(',', $bbox), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

$imageRegion = [70.0, 90.0, 190.0, 120.0];
if ($geometry->boxesIntersect($bbox, $imageRegion)) {
    echo "<!-- wp:image -->\n";
    echo '<figure class="wp-block-image"><img src="0_image_0.png" alt="0_image_0.png"/></figure>' . "\n";
    echo "<!-- /wp:image -->\n";
}
