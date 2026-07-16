<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\ImageExtractor;
use PortLibs\MarkerPDF\MarkdownImageEmbedder;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'pnum' => 2,
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'layout_boxes' => [
        ['label' => 'Figure', 'bbox' => [80.0, 160.0, 420.0, 360.0]],
    ],
    'blocks' => [
        [
            'type' => 'Figure',
            'bbox' => [78.0, 158.0, 422.0, 362.0],
            'lines' => [
                [
                    'bbox' => [80.0, 160.0, 420.0, 360.0],
                    'spans' => [
                        ['text' => 'migration chart placeholder', 'span_id' => 'figure_text'],
                    ],
                ],
            ],
        ],
    ],
];

$transparentPng = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
    true
);
if ($transparentPng === false) {
    throw new RuntimeException('Unable to decode embedded PNG fixture.');
}

$extractor = new ImageExtractor();
$page = $extractor->insertImagePlaceholders($page, [$transparentPng]);
$images = $extractor->imagesToDict([$page]);
$markdown = $page['blocks'][0]['lines'][0]['spans'][0]['text'];
$html = (new MarkdownImageEmbedder())->markdownInsertImages($markdown, $images);

echo "<!-- wp:html -->\n";
echo trim($html) . "\n";
echo "<!-- /wp:html -->\n";
