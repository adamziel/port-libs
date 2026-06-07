<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-hardlink-identity-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerPDF hardlink identity folder.');
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

$runtimeDirectoryOrder = static function (string $path, bool $filesOnly = false): array {
    $handle = opendir($path);
    if ($handle === false) {
        throw new RuntimeException('Unable to inspect temporary runtime hardlink directory order.');
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
    'keeps hardlinked pdf entries as separate runtime task args while recording shared file identity' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $runtimeDirectoryOrder): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            $originalPath = $input . DIRECTORY_SEPARATOR . 'wp-import-original.pdf';
            $hardlinkPath = $input . DIRECTORY_SEPARATOR . 'wp-import-hardlink.pdf';
            $controlPath = $input . DIRECTORY_SEPARATOR . 'wp-import-control.pdf';

            file_put_contents($originalPath, "%PDF-1.4\n% WordPress hardlink original\n%%EOF");
            file_put_contents($controlPath, "%PDF-1.4\n% WordPress control import\n%%EOF");
            if (!@link($originalPath, $hardlinkPath)) {
                throw new RuntimeException('Unable to create hardlinked PDF fixture for markerPDF runtime preflight.');
            }

            $fileOrder = $runtimeDirectoryOrder($input, filesOnly: true);
            $duplicateFilenames = array_values(array_filter(
                $fileOrder,
                static fn (string $filename): bool => in_array(
                    $filename,
                    ['wp-import-original.pdf', 'wp-import-hardlink.pdf'],
                    true
                )
            ));

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                metadataByFilename: [
                    'wp-import-control.pdf' => ['title' => 'Control Import'],
                    'wp-import-original.pdf' => ['title' => 'Original WordPress Import'],
                    'wp-import-hardlink.pdf' => ['title' => 'Hardlink WordPress Import'],
                ],
                workers: 4,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $review = $plan['worker_pool']['task_arg_identity_review'];
            $t->same('convert.py os.listdir/os.path.isfile task tuple identity boundary', $review['source']);
            $t->same('after_task_args_before_pool_imap', $review['order']);
            $t->same(true, $review['review_reached']);
            $t->same($fileOrder, $review['task_arg_filenames']);
            $t->same(3, $review['task_args_count']);
            $t->same(false, $review['duplicate_resolved_targets_found']);
            $t->same(0, $review['duplicate_resolved_target_group_count']);
            $t->same(true, $review['duplicate_file_identities_found']);
            $t->same(1, $review['duplicate_file_identity_group_count']);
            $t->same($duplicateFilenames, $review['duplicate_file_identity_filenames']);
            $t->same(true, $review['hardlink_file_identity_found']);
            $t->same(1, $review['hardlink_file_identity_group_count']);
            $t->same($duplicateFilenames, $review['hardlink_file_identity_filenames']);
            $t->same(true, $review['no_dedupe_before_task_args']);
            $t->same(true, $review['metadata_lookup_uses_entry_basename']);
            $t->same(false, $review['target_basename_metadata_fallback']);

            $identityGroup = $review['duplicate_file_identity_groups'][0];
            $t->same(2, $identityGroup['entry_count']);
            $t->same($duplicateFilenames, $identityGroup['filenames']);
            $t->same([$originalPath, $hardlinkPath], array_values(array_intersect([$originalPath, $hardlinkPath], $identityGroup['filepaths'])));
            $t->same(false, $identityGroup['contains_symlink']);
            $t->same([], $identityGroup['symlink_filenames']);
            $t->same(2, $identityGroup['resolved_target_count']);
            $t->same(true, in_array(realpath($originalPath), $identityGroup['resolved_targets'], true));
            $t->same(true, in_array(realpath($hardlinkPath), $identityGroup['resolved_targets'], true));
            $t->same(true, $identityGroup['hardlink_candidate']);
            $t->same(true, $identityGroup['queued_separately']);
            $t->same(false, $identityGroup['deduplicated_by_file_identity']);
            $t->same(false, $identityGroup['deduplicated_by_inode']);

            $hardlinkGroup = $review['hardlink_file_identity_groups'][0];
            $t->same($identityGroup['file_identity_key'], $hardlinkGroup['file_identity_key']);
            $t->same($duplicateFilenames, $hardlinkGroup['filenames']);
            $t->same(true, is_int($hardlinkGroup['device']) || is_string($hardlinkGroup['device']));
            $t->same(true, is_int($hardlinkGroup['inode']) || is_string($hardlinkGroup['inode']));

            $taskArgsByName = [];
            foreach ($plan['worker_pool']['task_args'] as $taskArg) {
                $taskArgsByName[basename((string) $taskArg['filepath'])] = $taskArg;
            }
            $t->same($originalPath, $taskArgsByName['wp-import-original.pdf']['filepath']);
            $t->same($hardlinkPath, $taskArgsByName['wp-import-hardlink.pdf']['filepath']);
            $t->same($controlPath, $taskArgsByName['wp-import-control.pdf']['filepath']);
            $t->same(['title' => 'Original WordPress Import'], $taskArgsByName['wp-import-original.pdf']['metadata']);
            $t->same(['title' => 'Hardlink WordPress Import'], $taskArgsByName['wp-import-hardlink.pdf']['metadata']);
            $t->same(['title' => 'Control Import'], $taskArgsByName['wp-import-control.pdf']['metadata']);
            $t->same(3, $plan['worker_pool']['task_args_count']);
            $t->same(3, $plan['worker_pool']['total_processes']);
            $t->same(true, $plan['worker_pool']['pool_launchable']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
