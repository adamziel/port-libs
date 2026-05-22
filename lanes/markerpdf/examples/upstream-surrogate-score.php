<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkScorer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixtures = [
    require dirname(__DIR__) . '/fixtures/upstream-multicolcnn-surrogate.php',
    require dirname(__DIR__) . '/fixtures/upstream-switch-transformers-surrogate.php',
    require dirname(__DIR__) . '/fixtures/upstream-thinkpython-surrogate.php',
    require dirname(__DIR__) . '/fixtures/upstream-thinkos-surrogate.php',
];

$scorer = new BenchmarkScorer();
$reports = [];
foreach ($fixtures as $fixture) {
    $score = $scorer->scoreText(
        $fixture['markerExcerpt'],
        $fixture['referenceExcerpt'],
        $fixture['chunkLength'],
    );

    $reports[] = [
        'document' => $fixture['document'],
        'referenceKind' => $fixture['referenceKind'],
        'score' => $score,
        'threshold' => $fixture['scoreThreshold'],
        'passes' => $score >= $fixture['scoreThreshold'],
    ];
}

echo json_encode($reports, JSON_PRETTY_PRINT) . "\n";
