<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkReportVerifier;
use PortLibs\MarkerPDF\BenchmarkScorer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixture = require dirname(__DIR__) . '/fixtures/upstream-ci-benchmark-short.php';
$scorer = new BenchmarkScorer();
$report = ['marker' => ['files' => []]];

foreach ($fixture['benchmarkPairs'] as $pair) {
    $report['marker']['files'][$pair['document']] = [
        'score' => $scorer->scoreText($pair['markerExcerpt'], $pair['referenceExcerpt'], $pair['chunkLength']),
        'referenceKind' => $pair['referenceKind'],
        'referencePath' => $pair['referencePath'],
    ];
}

(new BenchmarkReportVerifier())->verifyMarkerScores($report);

echo json_encode([
    'scenario' => 'wordpress-pdf-benchmark-report',
    'archive' => [
        'filename' => $fixture['archive']['filename'],
        'sha256' => $fixture['archive']['sha256'],
    ],
    'purpose' => 'Gate imported PDF-to-block content against upstream Marker CI benchmark references before editorial review.',
    'report' => $report,
    'passes_upstream_ci_marker_thresholds' => true,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
