<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\OcrDetection;
use PortLibs\MarkerPDF\OcrHeuristics;

$page = static function (string $text, array $bbox): array {
    return [
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'prelim_text' => $text,
        'blocks' => [
            [
                'lines' => [
                    ['text' => $text, 'bbox' => $bbox],
                ],
            ],
        ],
    ];
};

$prediction = static function (array $imageBbox, array ...$lineBboxes): array {
    return [
        'image_bbox' => $imageBbox,
        'bboxes' => array_map(
            static fn (array $bbox): array => ['bbox' => $bbox],
            $lineBboxes
        ),
    ];
};

return [
    'uses upstream detector batch size defaults overrides and multiplier truncation' => static function (TestRunner $t): void {
        $t->same(4, (new OcrDetection())->batchSize());
        $t->same(6, (new OcrDetection(new MarkerSettings(['DETECTOR_BATCH_SIZE' => '6'])))->batchSize());
        $t->same(9, (new OcrDetection(new MarkerSettings(['DETECTOR_BATCH_SIZE' => 6])))->batchSize(1.5));
        $t->same(3, (new OcrDetection())->batchSize(0.75));
        $t->same(4, (new OcrDetection(new MarkerSettings(['TORCH_DEVICE' => 'cuda'])))->batchSize());
    },
    'attaches supplied Surya predictions to pages with upstream zip semantics' => static function (TestRunner $t) use ($page, $prediction): void {
        $detector = new OcrDetection();
        $pages = [
            $page('Clean WordPress import text.', [60.0, 100.0, 280.0, 120.0]),
            $page('Second page text.', [60.0, 180.0, 260.0, 205.0]),
        ];
        $predictions = [
            $prediction([0.0, 0.0, 1200.0, 1600.0], [120.0, 200.0, 560.0, 240.0]),
            $prediction([0.0, 0.0, 1200.0, 1600.0], [120.0, 360.0, 520.0, 410.0]),
            $prediction([0.0, 0.0, 1200.0, 1600.0], [900.0, 900.0, 1000.0, 940.0]),
        ];

        $result = $detector->runWithSuppliedPredictions(['image-1', 'image-2'], $pages, $predictions, 2.0);

        $t->same([
            'image_count' => 2,
            'page_count' => 2,
            'prediction_count' => 3,
            'assigned_pages' => 2,
            'batch_size' => 8,
        ], $result['plan']);
        $t->same($predictions[0], $result['pages'][0]['text_lines']);
        $t->same($predictions[1], $result['pages'][1]['text_lines']);
    },
    'leaves unpaired pages unchanged when upstream predictions are shorter than pages' => static function (TestRunner $t) use ($page, $prediction): void {
        $detector = new OcrDetection();
        $pages = [
            $page('First page text.', [60.0, 100.0, 280.0, 120.0]),
            $page('Second page text.', [60.0, 180.0, 260.0, 205.0]),
        ];

        $result = $detector->runWithSuppliedPredictions(
            ['image-1', 'image-2'],
            $pages,
            [$prediction([0.0, 0.0, 1200.0, 1600.0], [120.0, 200.0, 560.0, 240.0])]
        );

        $t->same(1, $result['plan']['assigned_pages']);
        $t->true(isset($result['pages'][0]['text_lines']));
        $t->true(!isset($result['pages'][1]['text_lines']));
    },
    'drives a WordPress OCR detection preflight with supplied line boxes' => static function (TestRunner $t) use ($page, $prediction): void {
        $detector = new OcrDetection();
        $heuristics = new OcrHeuristics();
        $result = $detector->runWithSuppliedPredictions(
            ['rendered-page'],
            [$page('Clean imported WordPress paragraph.', [60.0, 100.0, 280.0, 120.0])],
            [$prediction([0.0, 0.0, 1200.0, 1600.0], [120.0, 200.0, 560.0, 240.0])]
        );

        [$covered, $detectedLines] = $heuristics->detectedLineCoverage($result['pages'][0]);

        $t->same(1, $detectedLines);
        $t->true($covered);
        $t->true(!$heuristics->shouldOcrPage($result['pages'][0], noText: false));
    },
];
