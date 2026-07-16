<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\ImageExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'pnum' => 0,
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'layout' => [
        'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
        'bboxes' => [
            ['label' => 'Picture', 'bbox' => [120.0, 200.0, 560.0, 440.0]],
        ],
    ],
    'blocks' => [
        [
            'type' => 'Figure',
            'bbox' => [60.0, 100.0, 280.0, 220.0],
            'lines' => [
                [
                    'bbox' => [62.0, 104.0, 270.0, 132.0],
                    'spans' => [
                        ['text' => 'Raster image placeholder from PDF export', 'span_id' => 'image_text_0'],
                    ],
                ],
            ],
        ],
    ],
];

$page = (new ImageExtractor())->insertImagePlaceholders($page);

foreach ($page['blocks'] as $block) {
    foreach ($block['lines'] as $line) {
        foreach ($line['spans'] as $span) {
            if (($span['image'] ?? false) !== true) {
                continue;
            }

            preg_match('/!\[([^]]+)]\(([^)]+)\)/', (string) $span['text'], $matches);
            $alt = htmlspecialchars($matches[1] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $src = htmlspecialchars($matches[2] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            echo "<!-- wp:image -->\n";
            echo '<figure class="wp-block-image"><img src="' . $src . '" alt="' . $alt . '"/></figure>' . "\n";
            echo "<!-- /wp:image -->\n\n";
        }
    }
}
