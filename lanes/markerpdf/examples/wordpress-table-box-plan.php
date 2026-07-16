<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\TableFormatter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pages = [
    [
        'pnum' => 3,
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'layout' => [
            'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
            'bboxes' => [
                ['label' => 'Table', 'bbox' => [100.0, 100.0, 210.0, 200.0]],
                ['label' => 'Table', 'bbox' => [209.0, 100.0, 320.0, 200.0]],
            ],
        ],
        'blocks' => [],
    ],
    [
        'pnum' => 4,
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'ocr_method' => 'surya',
        'layout' => [
            'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
            'bboxes' => [
                ['label' => 'Table', 'bbox' => [120.0, 300.0, 520.0, 480.0]],
            ],
        ],
        'blocks' => [],
    ],
];

$plan = (new TableFormatter())->getTableBoxes(
    $pages,
    [
        [
            'lines' => [
                ['text' => 'Block Status', 'bbox' => [200.0, 210.0, 630.0, 230.0]],
            ],
        ],
    ],
    [
        ['width' => 2400, 'height' => 3200],
        ['width' => 2400, 'height' => 3200],
    ]
);

foreach ($plan['table_images'] as $index => $crop) {
    $attrs = [
        'markerTableCrop' => [
            'pnum' => $crop['pnum'],
            'source_bbox' => $crop['source_bbox'],
            'highres_bbox' => $crop['highres_bbox'],
            'text_lines' => $plan['text_lines'][$index],
            'needs_cell_redetection' => $plan['text_lines'][$index] === null,
        ],
    ];

    echo '<!-- wp:table ' . json_encode($attrs, JSON_THROW_ON_ERROR) . " -->\n";
    echo '<figure class="wp-block-table"><table><tbody>';
    echo '<tr><td>Table crop queued for recognition</td></tr>';
    echo "</tbody></table></figure>\n";
    echo "<!-- /wp:table -->\n";
}
