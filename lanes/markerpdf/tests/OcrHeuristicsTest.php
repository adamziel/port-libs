<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OcrHeuristics;

$coveredPage = static function (string $text = 'Clean WordPress import text.'): array {
    return [
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'prelim_text' => $text,
        'text_lines' => [
            'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
            'bboxes' => [
                ['bbox' => [120.0, 200.0, 560.0, 240.0]],
                ['bbox' => [120.0, 260.0, 560.0, 300.0]],
            ],
        ],
        'blocks' => [
            [
                'lines' => [
                    ['text' => 'Clean WordPress', 'bbox' => [60.0, 100.0, 280.0, 120.0]],
                    ['text' => 'import text.', 'bbox' => [60.0, 130.0, 280.0, 150.0]],
                ],
            ],
        ],
    ];
};

return [
    'computes upstream alphanum ratio after removing only spaces and newlines' => static function (TestRunner $t): void {
        $heuristics = new OcrHeuristics();

        $t->same(0.75, $heuristics->alphanumRatio("A B\nC!"));
        $t->same(1.0, $heuristics->alphanumRatio(" \n"));
    },
    'detects bad OCR using upstream whitespace garble and invalid character thresholds' => static function (TestRunner $t): void {
        $heuristics = new OcrHeuristics();

        $t->true($heuristics->detectBadOcr(''));
        $t->true($heuristics->detectBadOcr('     '));
        $t->true($heuristics->detectBadOcr("\nA\n"));
        $t->true($heuristics->detectBadOcr('@@@ ### !!! ???'));
        $t->true($heuristics->detectBadOcr('Clean text ' . str_repeat("\u{FFFD}", 7)));
        $t->true(!$heuristics->detectBadOcr('Clean WordPress import text has enough recognizable letters.'));
    },
    'measures detected line coverage with upstream bbox rescaling and intersection threshold' => static function (TestRunner $t) use ($coveredPage): void {
        $heuristics = new OcrHeuristics();

        $t->same([true, 2], $heuristics->detectedLineCoverage($coveredPage()));

        $page = $coveredPage();
        $page['text_lines']['bboxes'][] = ['bbox' => [900.0, 900.0, 1100.0, 940.0]];
        $page['text_lines']['bboxes'][] = ['bbox' => [900.0, 960.0, 1100.0, 1000.0]];
        $page['text_lines']['bboxes'][] = ['bbox' => [900.0, 1020.0, 1100.0, 1060.0]];
        $page['text_lines']['bboxes'][] = ['bbox' => [900.0, 1080.0, 1100.0, 1120.0]];

        $t->same([false, 6], $heuristics->detectedLineCoverage($page));
        $t->same([true, 0], $heuristics->detectedLineCoverage(['text_lines' => ['bboxes' => []]]));
    },
    'triages pages for OCR like marker ocr heuristics' => static function (TestRunner $t) use ($coveredPage): void {
        $heuristics = new OcrHeuristics();

        $t->true($heuristics->shouldOcrPage($coveredPage(), noText: true));
        $t->true($heuristics->shouldOcrPage($coveredPage("@@@ ### !!!"), noText: false));
        $t->true($heuristics->shouldOcrPage($coveredPage(), noText: false, ocrAllPages: true));
        $t->true(!$heuristics->shouldOcrPage($coveredPage(), noText: false));
        $t->true(!$heuristics->shouldOcrPage(['text_lines' => ['bboxes' => []]], noText: true, ocrAllPages: true));
    },
    'detects all-empty documents before WordPress import OCR fallback' => static function (TestRunner $t): void {
        $heuristics = new OcrHeuristics();

        $t->true($heuristics->noTextFound([
            ['prelim_text' => '   '],
            ['blocks' => [['lines' => [['spans' => [['text' => '']]]]]]],
        ]));
        $t->true(!$heuristics->noTextFound([
            ['prelim_text' => ''],
            ['blocks' => [['lines' => [['spans' => [['text' => 'Recovered text']]]]]]],
        ]));
    },
];
