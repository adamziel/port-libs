<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkScorer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixture = require dirname(__DIR__) . '/fixtures/upstream-multicolcnn-surrogate.php';
$score = (new BenchmarkScorer())->scoreText(
    $fixture['markerExcerpt'],
    $fixture['referenceExcerpt'],
    $fixture['chunkLength'],
);

echo json_encode([
    'document' => $fixture['document'],
    'referenceKind' => $fixture['referenceKind'],
    'score' => $score,
    'threshold' => $fixture['scoreThreshold'],
    'passes' => $score >= $fixture['scoreThreshold'],
], JSON_PRETTY_PRINT) . "\n";
