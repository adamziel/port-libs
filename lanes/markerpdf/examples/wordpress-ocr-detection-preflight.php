<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OcrDetection;
use PortLibs\MarkerPDF\OcrHeuristics;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'prelim_text' => 'Clean imported WordPress paragraph.',
    'blocks' => [
        [
            'lines' => [
                [
                    'text' => 'Clean imported WordPress paragraph.',
                    'bbox' => [60.0, 100.0, 280.0, 120.0],
                ],
            ],
        ],
    ],
];

$suryaPrediction = [
    'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
    'bboxes' => [
        ['bbox' => [120.0, 200.0, 560.0, 240.0]],
    ],
];

$detector = new OcrDetection();
$detection = $detector->runWithSuppliedPredictions(
    ['rendered-page-placeholder'],
    [$page],
    [$suryaPrediction]
);

$heuristics = new OcrHeuristics();
[$covered, $detectedLines] = $heuristics->detectedLineCoverage($detection['pages'][0]);

echo json_encode([
    'scenario' => 'wordpress-pdf-ocr-detection-preflight',
    'detectorPlan' => $detection['plan'],
    'detectedLines' => $detectedLines,
    'detectedLinesCovered' => $covered,
    'sendToOcr' => $heuristics->shouldOcrPage($detection['pages'][0], noText: false),
    'reviewBlock' => [
        'blockName' => 'core/paragraph',
        'attrs' => [
            'markerPDFTextLineBoxes' => $detection['pages'][0]['text_lines']['bboxes'],
        ],
        'innerHTML' => '<p>Clean imported WordPress paragraph.</p>',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
