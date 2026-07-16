<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OcrRecognition;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'prelim_text' => '@@@ ### !!!',
    'text_lines' => [
        'image_bbox' => [0.0, 0.0, 600.0, 800.0],
        'bboxes' => [
            ['polygon' => [[60, 96], [270, 96], [270, 116], [60, 116]]],
            ['bbox' => [300.0, 200.0, 300.0, 230.0]],
        ],
    ],
    'blocks' => [
        [
            'lines' => [
                ['text' => '@@@ ### !!!', 'bbox' => [60.0, 96.0, 270.0, 116.0]],
            ],
        ],
    ],
];

$recognition = new OcrRecognition();
$plan = $recognition->suryaRecognitionPlan([$page], [0]);
$recognizedPages = $recognition->buildSuryaRecognitionPages(
    [$page],
    [0],
    [[
        ['text' => 'Recovered scanned PDF text for WordPress import.', 'bbox' => [120.0, 192.0, 540.0, 232.0]],
    ]],
    [['width' => 1200, 'height' => 1600]]
);
$result = $recognition->runWithSuppliedPages([$page], $recognizedPages);

$text = htmlspecialchars(
    (string) $result['pages'][0]['blocks'][0]['lines'][0]['spans'][0]['text'],
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo json_encode([
    'scenario' => 'wordpress-surya-ocr-recognition-import',
    'recognitionPlan' => [
        'batch_size' => $plan['batch_size'],
        'box_scale' => $plan['box_scale'],
        'polygon_count' => count($plan['polygons'][0]),
    ],
    'stats' => $result['stats'],
    'block' => "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->",
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
