<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-runtime-duplicate-metadata-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-duplicate-metadata-output-' . $runId;
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

try {
    foreach (['editorial.pdf', 'translation.pdf', 'untitled.pdf'] as $filename) {
        file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
    }

    $metadataFile = $output . DIRECTORY_SEPARATOR . 'metadata.json';
    file_put_contents($metadataFile, <<<'JSON'
{
  "editorial.pdf": {"title": "Stale editorial title", "raw_upload": "stale WordPress metadata payload"},
  "translation.pdf": {"title": "Translation Import", "languages": ["Spanish"]},
  "editorial.pdf": {"title": "Current Editorial Import", "languages": ["English"], "wordpress_post_id": 731}
}
JSON);

    $plan = (new BatchConverter())->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 5,
        metadataFile: $metadataFile,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );

    $taskArgsByName = [];
    foreach ($plan['worker_pool']['task_args'] as $taskArg) {
        $taskArgsByName[basename($taskArg['filepath'])] = $taskArg;
    }

    $encodedPlan = json_encode($plan, JSON_THROW_ON_ERROR);
    $duplicateReview = $plan['metadata']['metadata_duplicate_key_review'];
    $currentEditorial = $taskArgsByName['editorial.pdf']['metadata'] ?? null;

    if (($currentEditorial['title'] ?? null) !== 'Current Editorial Import') {
        throw new RuntimeException('Expected duplicate metadata basename to keep the Python json.load last value.');
    }
    if (str_contains($encodedPlan, 'Stale editorial title') || str_contains($encodedPlan, 'stale WordPress metadata payload')) {
        throw new RuntimeException('Stale duplicate metadata values must stay out of the runtime preflight output.');
    }
    if ($duplicateReview['duplicate_keys'] !== ['editorial.pdf']) {
        throw new RuntimeException('Expected duplicate metadata key review for editorial.pdf.');
    }
    if ($plan['executes_python_or_models'] !== false || $plan['executes_external_pdf_tools'] !== false) {
        throw new RuntimeException('Duplicate metadata runtime preflight smoke must not execute Python, models, or external PDF tools.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-preflight-duplicate-metadata-currentbase',
        'purpose' => 'Record convert.py metadata_file duplicate-basename json.load boundaries for a WordPress batch import without launching Python, Torch multiprocessing, pdftext, pypdfium, models, or external PDF tools.',
        'schema' => $plan['schema'],
        'metadata_file' => $plan['metadata']['metadata_file'],
        'metadata_key_order' => $plan['metadata']['metadata_top_level_key_order'],
        'duplicate_key_policy' => $duplicateReview['duplicate_key_policy'],
        'duplicate_keys' => $duplicateReview['duplicate_keys'],
        'duplicate_key_occurrence_counts' => $duplicateReview['duplicate_key_occurrence_counts'],
        'selected_duplicate_filenames' => $duplicateReview['selected_duplicate_filenames'],
        'editorial_metadata_title' => $currentEditorial['title'] ?? null,
        'stale_metadata_excluded' => !str_contains($encodedPlan, 'Stale editorial title')
            && !str_contains($encodedPlan, 'stale WordPress metadata payload'),
        'task_args_count' => $plan['worker_pool']['task_args_count'],
        'pool_launchable' => $plan['worker_pool']['pool_launchable'],
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($input);
    $removeTree($output);
}
