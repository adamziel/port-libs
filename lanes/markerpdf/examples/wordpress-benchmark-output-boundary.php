<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkReportVerifier;
use PortLibs\MarkerPDF\BenchmarkRunner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child)) {
            $removeTree($child);
        } else {
            unlink($child);
        }
    }

    rmdir($path);
};

$base = sys_get_temp_dir() . '/markerpdf-wordpress-benchmark-output-' . bin2hex(random_bytes(4));
$pdfFolder = $base . '/pdfs';
$referenceFolder = $base . '/references';
$markdownFolder = $base . '/markdown';
$reportOutput = $base . '/overall.json';

mkdir($pdfFolder, 0777, true);
mkdir($referenceFolder, 0777, true);
mkdir($markdownFolder, 0777, true);

try {
    $fixture = require dirname(__DIR__) . '/fixtures/upstream-ci-benchmark-short.php';
    $pairsByDocument = [];
    foreach ($fixture['benchmarkPairs'] as $pair) {
        $pairsByDocument[$pair['document']] = $pair;
        file_put_contents($pdfFolder . '/' . $pair['document'], "%PDF-1.4\n% benchmark output boundary\n%%EOF");
        file_put_contents(
            $referenceFolder . '/' . preg_replace('/\.[^.]*$/', '.md', $pair['document']),
            $pair['referenceExcerpt']
        );
    }

    $result = (new BenchmarkRunner())->run(
        $pdfFolder,
        $referenceFolder,
        [
            'marker' => static fn (string $pdfPath, string $document): string => $pairsByDocument[$document]['markerExcerpt'],
            'nougat' => static fn (string $pdfPath, string $document, string $reference): string => $reference,
        ],
        static fn (string $pdfPath): int => str_contains($pdfPath, 'switch_trans') ? 4 : 3,
        $markdownFolder,
        array_map(static fn (array $pair): int => $pair['chunkLength'], $pairsByDocument),
        $reportOutput
    );

    (new BenchmarkReportVerifier())->verifyMarkerScores($result['report']);
    $decodedReport = json_decode((string) file_get_contents($reportOutput), true, flags: JSON_THROW_ON_ERROR);

    echo json_encode([
        'scenario' => 'wordpress-pdf-benchmark-output-boundary',
        'purpose' => 'Persist upstream overall.py-style benchmark report JSON and score-table rows before WordPress import review.',
        'benchmark_files' => $result['benchmark_files'],
        'report_output_basename' => basename((string) $result['report_output']),
        'report_methods' => array_keys($decodedReport),
        'summary_headers' => $result['output_tables']['summary_headers'],
        'score_headers' => $result['output_tables']['score_headers'],
        'written_markdown' => array_map('basename', $result['written_markdown']),
        'passes_upstream_ci_marker_thresholds' => true,
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($base);
}
