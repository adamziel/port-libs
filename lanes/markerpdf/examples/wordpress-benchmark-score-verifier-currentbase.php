<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkReportVerifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$removeFile = static function (string $path): void {
    if (is_file($path)) {
        unlink($path);
    }
};

$writeJsonFile = static function (string $name, mixed $data): string {
    $path = sys_get_temp_dir() . '/markerpdf-' . $name . '-' . bin2hex(random_bytes(4)) . '.json';
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return $path;
};

$markerScoreFile = $writeJsonFile('benchmark-marker-scores', [
    'marker' => [
        'files' => [
            'multicolcnn.pdf' => ['score' => 0.341, 'source' => 'wordpress-import-preview'],
            'switch_trans.pdf' => ['score' => 0.401, 'source' => 'wordpress-import-preview'],
        ],
    ],
]);
$tableScoreFile = $writeJsonFile('benchmark-table-scores', [
    ['document' => 'switch_trans.pdf', 'score' => 0.73],
    ['document' => 'multicolcnn.pdf', 'score' => 0.71],
    ['document' => 'wordpress-import.pdf', 'score' => 0.70],
]);

try {
    $verifier = new BenchmarkReportVerifier();
    $markerReport = $verifier->verifyScoreFile($markerScoreFile, 'marker');
    $tableRows = $verifier->verifyScoreFile($tableScoreFile, 'table');

    echo json_encode([
        'scenario' => 'wordpress-pdf-benchmark-score-verifier-currentbase',
        'purpose' => 'Verify upstream markerPDF benchmark score JSON files before WordPress PDF imports reach editorial review.',
        'marker_score_file_verified' => true,
        'table_score_file_verified' => true,
        'marker_documents' => array_keys($markerReport['marker']['files']),
        'table_rows' => count($tableRows),
        'marker_thresholds' => $verifier->markerThresholds(),
        'table_average_threshold' => $verifier->tableAverageThreshold(),
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeFile($markerScoreFile);
    $removeFile($tableScoreFile);
}
