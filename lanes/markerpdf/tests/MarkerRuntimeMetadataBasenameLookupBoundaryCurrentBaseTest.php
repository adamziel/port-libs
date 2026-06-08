<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-metadata-basename-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerPDF metadata basename folder.');
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
        if (is_link($child) || !is_dir($child)) {
            unlink($child);
        } else {
            $removeTree($child);
        }
    }

    rmdir($path);
};

$taskMetadataByFilename = static function (array $taskArgs): array {
    $metadataByFilename = [];
    foreach ($taskArgs as $taskArg) {
        $metadataByFilename[basename((string) $taskArg['filepath'])] = $taskArg['metadata'];
    }
    ksort($metadataByFilename, SORT_STRING);

    return $metadataByFilename;
};

return [
    'keeps path-shaped metadata file keys out of task args because convert.py looks up basenames' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $taskMetadataByFilename): void {
        $input = $makeTempDir();
        $output = $makeTempDir();

        try {
            foreach (['absolute-only.pdf', 'editorial.pdf', 'notes.txt', 'windows.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }

            $absoluteEditorialKey = $input . DIRECTORY_SEPARATOR . 'editorial.pdf';
            $relativeAbsoluteOnlyKey = 'wp-content/uploads/absolute-only.pdf';
            $windowsKey = 'C:\\wp\\uploads\\windows.pdf';
            $metadataFile = $output . DIRECTORY_SEPARATOR . 'metadata-basename.json';
            file_put_contents($metadataFile, json_encode([
                $absoluteEditorialKey => ['title' => 'Absolute Path Decoy'],
                'editorial.pdf' => ['title' => 'Basename Metadata'],
                $relativeAbsoluteOnlyKey => ['title' => 'Relative Path Decoy'],
                $windowsKey => ['title' => 'Windows Path Decoy'],
                'notes.txt' => ['title' => 'Sidecar Basename Metadata'],
            ], JSON_THROW_ON_ERROR));

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 6,
                metadataFile: $metadataFile,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $review = $plan['metadata']['metadata_basename_lookup_review'];
            $expectedPathLikeKeys = [$absoluteEditorialKey, $relativeAbsoluteOnlyKey, $windowsKey];
            sort($expectedPathLikeKeys, SORT_STRING);
            $expectedPathLikeBasenames = [
                $absoluteEditorialKey => 'editorial.pdf',
                $relativeAbsoluteOnlyKey => 'absolute-only.pdf',
                $windowsKey => 'windows.pdf',
            ];
            ksort($expectedPathLikeBasenames, SORT_STRING);

            $t->same('convert.py task_args metadata.get(os.path.basename(f))', $review['source']);
            $t->same(true, $review['review_reached']);
            $t->same('os.path.basename(f)', $review['lookup_key_source']);
            $t->same('metadata.get(os.path.basename(f))', $review['metadata_lookup']);
            $t->same(true, $review['basename_only_lookup_preserved']);
            $t->same(true, $review['path_like_metadata_keys_found']);
            $t->same(3, $review['path_like_metadata_key_count']);
            $t->same($expectedPathLikeKeys, $review['path_like_metadata_keys']);
            $t->same($expectedPathLikeBasenames, $review['path_like_metadata_key_basenames']);
            $t->same($expectedPathLikeKeys, $review['path_like_metadata_keys_with_selected_basenames']);
            $t->same(true, $review['path_like_metadata_values_excluded_from_task_args']);
            $t->same(['editorial.pdf'], $review['exact_basename_keys_with_path_like_decoys']);
            $t->same(true, $review['exact_basename_values_preferred_over_path_like_keys']);
            $missingDueToPathLikeKeys = $review['missing_metadata_filenames_due_to_path_like_keys'];
            sort($missingDueToPathLikeKeys, SORT_STRING);
            $t->same(['absolute-only.pdf', 'windows.pdf'], $missingDueToPathLikeKeys);
            $t->same(false, $review['task_args_receive_path_like_values']);
            $t->same(false, $review['blocks_task_args']);
            $t->same(false, $review['executes_python_or_models']);

            $selectedMetadata = $plan['metadata']['selected_metadata_filenames'];
            sort($selectedMetadata, SORT_STRING);
            $missingMetadata = $plan['metadata']['missing_metadata_filenames'];
            sort($missingMetadata, SORT_STRING);
            $t->same(['editorial.pdf', 'notes.txt'], $selectedMetadata);
            $t->same(['absolute-only.pdf', 'windows.pdf'], $missingMetadata);

            $taskMetadata = $taskMetadataByFilename($plan['worker_pool']['task_args']);
            $t->same(null, $taskMetadata['absolute-only.pdf']);
            $t->same('Basename Metadata', $taskMetadata['editorial.pdf']['title'] ?? null);
            $t->same('Sidecar Basename Metadata', $taskMetadata['notes.txt']['title'] ?? null);
            $t->same(null, $taskMetadata['windows.pdf']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },

    'applies the same basename-only metadata review to direct runtime metadata arguments' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $taskMetadataByFilename): void {
        $input = $makeTempDir();
        $output = $makeTempDir();

        try {
            foreach (['argument-exact.pdf', 'argument-path-only.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }

            $pathOnlyKey = $input . DIRECTORY_SEPARATOR . 'argument-path-only.pdf';
            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                metadataByFilename: [
                    'argument-exact.pdf' => ['title' => 'Direct Basename Metadata'],
                    $pathOnlyKey => ['title' => 'Direct Path Metadata Decoy'],
                ],
                workers: 2,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $review = $plan['metadata']['metadata_basename_lookup_review'];
            $t->same('metadataByFilename argument', $plan['metadata']['source']);
            $t->same(true, $review['review_reached']);
            $t->same([$pathOnlyKey], $review['path_like_metadata_keys']);
            $t->same([$pathOnlyKey => 'argument-path-only.pdf'], $review['path_like_metadata_key_basenames']);
            $t->same([$pathOnlyKey], $review['path_like_metadata_keys_with_selected_basenames']);
            $t->same(['argument-path-only.pdf'], $review['missing_metadata_filenames_due_to_path_like_keys']);
            $t->same(['argument-exact.pdf'], $review['selected_metadata_filenames']);
            $t->same(['argument-path-only.pdf'], $review['missing_metadata_filenames']);
            $t->same(false, $review['task_args_receive_path_like_values']);

            $taskMetadata = $taskMetadataByFilename($plan['worker_pool']['task_args']);
            $t->same('Direct Basename Metadata', $taskMetadata['argument-exact.pdf']['title'] ?? null);
            $t->same(null, $taskMetadata['argument-path-only.pdf']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
