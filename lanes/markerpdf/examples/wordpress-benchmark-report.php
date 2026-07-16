<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkReportBuilder;
use PortLibs\MarkerPDF\BenchmarkReportVerifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixture = require dirname(__DIR__) . '/fixtures/upstream-ci-benchmark-short.php';
$runs = [];

foreach ($fixture['benchmarkPairs'] as $index => $pair) {
    $runs[] = [
        'method' => 'marker',
        'document' => $pair['document'],
        'hypothesis' => $pair['markerExcerpt'],
        'reference' => $pair['referenceExcerpt'],
        'time' => (float) ($index + 1),
        'pages' => $index + 2,
        'chunkLength' => $pair['chunkLength'],
    ];
}

$report = (new BenchmarkReportBuilder())->build($runs);
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
