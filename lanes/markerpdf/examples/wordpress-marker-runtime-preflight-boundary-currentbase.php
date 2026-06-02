<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\OutputWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-runtime-preflight-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-preflight-output-' . $runId;
@mkdir($input, 0777, true);
@mkdir($output, 0777, true);

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
    $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    $content = 'BT /F1 12 Tf 72 720 Td (' . $escaped . ') Tj ET';
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
    file_put_contents($path, $pdf);
};

try {
    $writePdf($input . DIRECTORY_SEPARATOR . 'already-imported.pdf', 'Already imported WordPress PDF');
    $writePdf($input . DIRECTORY_SEPARATOR . 'short-text.pdf', 'Short text PDF');
    $writePdf($input . DIRECTORY_SEPARATOR . 'ready-for-marker.pdf', 'Ready for Marker conversion PDF');
    file_put_contents($input . DIRECTORY_SEPARATOR . 'extension-spoof.pdf', "PK\x03\x04not really a pdf");

    (new OutputWriter())->saveMarkdown(
        $output,
        'already-imported.pdf',
        '<!-- wp:paragraph --><p>Previously imported.</p><!-- /wp:paragraph -->',
        [],
        ['title' => 'Previously Imported']
    );

    $batch = new BatchConverter();
    $textLength = static fn (string $filepath): int => basename($filepath) === 'short-text.pdf' ? 12 : 180;
    $plans = [];
    foreach (['already-imported.pdf', 'extension-spoof.pdf', 'short-text.pdf', 'ready-for-marker.pdf'] as $filename) {
        $plans[$filename] = $batch->processFilePreflightPlan(
            $input . DIRECTORY_SEPARATOR . $filename,
            $output,
            ['title' => ucfirst(str_replace(['-', '.pdf'], [' ', ''], $filename)), 'languages' => ['English']],
            80,
            $textLength
        );
    }

    if ($plans['already-imported.pdf']['status'] !== 'skipped-existing') {
        throw new RuntimeException('Expected existing WordPress import output to skip before filetype checks.');
    }
    if ($plans['extension-spoof.pdf']['status'] !== 'skipped-unsupported-filetype') {
        throw new RuntimeException('Expected extension-spoofed upload to skip before embedded text checks.');
    }
    if ($plans['short-text.pdf']['status'] !== 'skipped-short-text') {
        throw new RuntimeException('Expected short embedded text PDF to skip before converter invocation.');
    }
    if ($plans['ready-for-marker.pdf']['status'] !== 'ready-for-conversion' || $plans['ready-for-marker.pdf']['should_invoke_converter'] !== true) {
        throw new RuntimeException('Expected ready PDF to reach the convert_single_pdf handoff boundary.');
    }
    if ($plans['ready-for-marker.pdf']['executes_python_or_models'] !== false || $plans['ready-for-marker.pdf']['executes_external_pdf_tools'] !== false) {
        throw new RuntimeException('Preflight smoke must not execute Python models or external PDF tools.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-preflight-boundary-currentbase',
        'purpose' => 'Review convert.py process_single_pdf preflight decisions for a WordPress PDF import queue before launching Marker model workers.',
        'source' => $plans['ready-for-marker.pdf']['source'],
        'min_length' => 80,
        'preflight_order' => $plans['ready-for-marker.pdf']['preflight_order'],
        'status_by_filename' => array_map(static fn (array $plan): string => (string) $plan['status'], $plans),
        'skip_reasons' => array_map(static fn (array $plan): ?string => $plan['skip_reason'], $plans),
        'ready_text_length' => $plans['ready-for-marker.pdf']['text_length'],
        'ready_should_invoke_converter' => $plans['ready-for-marker.pdf']['should_invoke_converter'],
        'existing_filetype_checked' => $plans['already-imported.pdf']['filetype_checked'],
        'spoof_text_length_checked' => $plans['extension-spoof.pdf']['text_length_checked'],
        'executes_python_or_models' => $plans['ready-for-marker.pdf']['executes_python_or_models'],
        'executes_multiprocessing' => $plans['ready-for-marker.pdf']['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plans['ready-for-marker.pdf']['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($input);
    $removeTree($output);
}
