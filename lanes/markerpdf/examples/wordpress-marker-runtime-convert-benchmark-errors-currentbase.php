<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
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

$writePdf = static function (string $path, string $text): void {
    $content = 'BT /F1 12 Tf 72 720 Td (' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text) . ') Tj ET';
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
    file_put_contents($path, $pdf);
};

$base = sys_get_temp_dir() . '/markerpdf-runtime-errors-' . bin2hex(random_bytes(4));
$input = $base . '/input';
$output = $base . '/output';
mkdir($input, 0777, true);
mkdir($output, 0777, true);

try {
    $writePdf($input . '/annual-report.pdf', 'Annual report import');
    $writePdf($input . '/model-error.pdf', 'Model error import');

    $batch = new BatchConverter();
    $summary = $batch->processFolder(
        $input,
        $output,
        static function (string $filepath): array {
            if (basename($filepath) === 'model-error.pdf') {
                throw new RuntimeException('surya model boundary unavailable');
            }

            return [
                'text' => "<!-- wp:paragraph -->\n<p>Imported " . htmlspecialchars(basename($filepath), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->",
                'images' => [],
                'metadata' => ['scenario' => 'wordpress-marker-runtime-convert-benchmark-errors-currentbase'],
            ];
        }
    );

    $failed = array_values(array_filter(
        $summary['results'],
        static fn (array $result): bool => ($result['status'] ?? '') === 'error'
    ))[0] ?? null;
    if (!is_array($failed) || ($failed['writes_markdown'] ?? true) !== false) {
        throw new RuntimeException('Expected failed conversion to remain review-only.');
    }

    $memoryFailure = (new BenchmarkRunner())->memorySnapshotFailureReport(
        'marker_memory_0.pickle',
        'CUDA snapshot unavailable'
    );

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-convert-benchmark-errors-currentbase',
        'purpose' => 'Preserve upstream convert.py per-file error output and benchmarks/overall.py memory snapshot failure metadata for WordPress PDF import review without executing Python, Torch, CUDA, models, or external PDF tools.',
        'converted' => $summary['converted'],
        'errors' => $summary['errors'],
        'failed_document' => $failed['filename'],
        'failed_status' => $failed['status'],
        'failed_error_line' => $failed['error_output']['message_line'],
        'failed_traceback_available' => $failed['error_output']['traceback_available'],
        'failed_review_only' => $failed['error_output']['review_only'],
        'failed_writes_markdown' => $failed['writes_markdown'],
        'converted_markdown' => basename((string) $summary['results'][0]['markdown']),
        'benchmark_memory_snapshot_failure' => $memoryFailure,
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($base);
}
