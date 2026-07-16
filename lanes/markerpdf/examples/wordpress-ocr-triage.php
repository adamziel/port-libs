<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OcrHeuristics;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pages = [
    [
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'prelim_text' => '@@@ ### !!!',
        'text_lines' => [
            'image_bbox' => [0.0, 0.0, 600.0, 800.0],
            'bboxes' => [
                ['bbox' => [72.0, 96.0, 540.0, 120.0]],
                ['bbox' => [72.0, 132.0, 540.0, 156.0]],
            ],
        ],
        'blocks' => [
            [
                'lines' => [
                    ['text' => '@@@ ### !!!', 'bbox' => [72.0, 96.0, 540.0, 120.0]],
                ],
            ],
        ],
    ],
    [
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'prelim_text' => 'Clean imported text for a normal WordPress paragraph.',
        'text_lines' => [
            'image_bbox' => [0.0, 0.0, 600.0, 800.0],
            'bboxes' => [
                ['bbox' => [72.0, 96.0, 540.0, 120.0]],
            ],
        ],
        'blocks' => [
            [
                'lines' => [
                    ['text' => 'Clean imported text for a normal WordPress paragraph.', 'bbox' => [72.0, 96.0, 540.0, 120.0]],
                ],
            ],
        ],
    ],
];

$heuristics = new OcrHeuristics();
$noText = $heuristics->noTextFound($pages);
$triage = [];

foreach ($pages as $index => $page) {
    [$covered, $detectedLines] = $heuristics->detectedLineCoverage($page);
    $triage[] = [
        'page' => $index + 1,
        'detectedLines' => $detectedLines,
        'detectedLinesCovered' => $covered,
        'badOcr' => $heuristics->detectBadOcr((string) ($page['prelim_text'] ?? '')),
        'sendToOcr' => $heuristics->shouldOcrPage($page, $noText),
    ];
}

echo json_encode([
    'scenario' => 'wordpress-pdf-ocr-triage',
    'pages' => $triage,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
