<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-duplicate-metadata-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf duplicate metadata folder.');
    }

    return $path;
};

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

$runtimeDirectoryOrder = static function (string $path, bool $filesOnly = false): array {
    $handle = opendir($path);
    if ($handle === false) {
        throw new RuntimeException('Unable to inspect duplicate metadata directory order.');
    }

    $entries = [];
    try {
        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if ($filesOnly && !is_file($path . DIRECTORY_SEPARATOR . $entry)) {
                continue;
            }

            $entries[] = $entry;
        }
    } finally {
        closedir($handle);
    }

    return $entries;
};

return [
    'records duplicate metadata_file basename keys as Python json.load last-value wins before task args' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $runtimeDirectoryOrder): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            foreach (['alpha.pdf', 'beta.pdf', 'missing.pdf', 'review.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }

            $metadataFile = $output . DIRECTORY_SEPARATOR . 'duplicate-metadata.json';
            file_put_contents($metadataFile, <<<'JSON'
{
  "alpha.pdf": {"title": "Stale Alpha", "languages": ["Spanish"], "raw_payload": "stale-upload-bytes"},
  "beta.pdf": {"title": "Beta Import", "nested": {"alpha.pdf": "not a top-level duplicate"}},
  "alpha.pdf": {"title": "Current Alpha", "languages": ["English"], "wordpress_post_id": 42},
  "review.pdf": "English",
  "review.pdf": {"title": "Review Current", "languages": ["German"]}
}
JSON);

            $fileOrder = $runtimeDirectoryOrder($input, filesOnly: true);
            $duplicateCounts = ['alpha.pdf' => 2, 'review.pdf' => 2];
            $selectedDuplicateFilenames = array_values(array_filter(
                $fileOrder,
                static fn (string $filename): bool => isset($duplicateCounts[$filename])
            ));
            $selectedMetadataFilenames = array_values(array_filter(
                $fileOrder,
                static fn (string $filename): bool => in_array($filename, ['alpha.pdf', 'beta.pdf', 'review.pdf'], true)
            ));
            $missingMetadataFilenames = array_values(array_filter(
                $fileOrder,
                static fn (string $filename): bool => $filename === 'missing.pdf'
            ));

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 6,
                metadataFile: $metadataFile,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $metadata = $plan['metadata'];
            $t->same(true, $metadata['metadata_load_success']);
            $t->same('object', $metadata['metadata_json_type']);
            $t->same(true, $metadata['metadata_get_available']);
            $t->same([
                'alpha.pdf',
                'beta.pdf',
                'alpha.pdf',
                'review.pdf',
                'review.pdf',
            ], $metadata['metadata_top_level_key_order']);
            $t->same(['alpha.pdf', 'beta.pdf', 'review.pdf'], $metadata['metadata_filenames']);
            $t->same($selectedMetadataFilenames, $metadata['selected_metadata_filenames']);
            $t->same($missingMetadataFilenames, $metadata['missing_metadata_filenames']);
            $t->same([
                'alpha.pdf' => 'dict',
                'beta.pdf' => 'dict',
                'review.pdf' => 'dict',
            ], $metadata['metadata_value_types']);

            $duplicateReview = $metadata['metadata_duplicate_key_review'];
            $t->same('convert.py metadata_file json.load duplicate basename boundary', $duplicateReview['source']);
            $t->same(true, $duplicateReview['review_reached']);
            $t->same('json.load', $duplicateReview['json_loader']);
            $t->same('python-json-load-last-value-wins', $duplicateReview['duplicate_key_policy']);
            $t->same(true, $duplicateReview['duplicate_keys_found']);
            $t->same(2, $duplicateReview['duplicate_key_count']);
            $t->same(['alpha.pdf', 'review.pdf'], $duplicateReview['duplicate_keys']);
            $t->same($duplicateCounts, $duplicateReview['duplicate_key_occurrence_counts']);
            $t->same([
                'alpha.pdf' => 'dict',
                'review.pdf' => 'dict',
            ], $duplicateReview['duplicate_key_last_value_types']);
            $t->same($selectedDuplicateFilenames, $duplicateReview['selected_duplicate_filenames']);
            $t->same(2, $duplicateReview['selected_duplicate_count']);
            $t->same(true, $duplicateReview['task_args_receive_last_values']);
            $t->same(true, $duplicateReview['stale_duplicate_values_excluded_from_task_args']);
            $t->same(false, $duplicateReview['blocks_task_args']);
            $t->same(false, $duplicateReview['blocks_model_handoff']);

            $taskArgsByName = [];
            foreach ($plan['worker_pool']['task_args'] as $taskArg) {
                $taskArgsByName[basename($taskArg['filepath'])] = $taskArg;
            }

            $t->same(['title' => 'Current Alpha', 'languages' => ['English'], 'wordpress_post_id' => 42], $taskArgsByName['alpha.pdf']['metadata']);
            $t->same(['title' => 'Beta Import', 'nested' => ['alpha.pdf' => 'not a top-level duplicate']], $taskArgsByName['beta.pdf']['metadata']);
            $t->same(['title' => 'Review Current', 'languages' => ['German']], $taskArgsByName['review.pdf']['metadata']);
            $t->same(null, $taskArgsByName['missing.pdf']['metadata']);
            $t->same(4, $plan['worker_pool']['task_args_count']);
            $t->same(4, $plan['worker_pool']['total_processes']);
            $t->same(true, $plan['worker_pool']['pool_launchable']);

            $encodedPlan = json_encode($plan, JSON_THROW_ON_ERROR);
            $t->same(false, str_contains($encodedPlan, 'Stale Alpha'));
            $t->same(false, str_contains($encodedPlan, 'stale-upload-bytes'));
            $t->same(false, str_contains($encodedPlan, 'Spanish'));
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
