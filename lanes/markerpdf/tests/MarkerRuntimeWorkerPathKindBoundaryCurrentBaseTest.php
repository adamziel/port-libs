<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-worker-path-kind-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerPDF worker path-kind folder.');
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

return [
    'classifies queued symlink directory and broken targets at process_single_pdf worker preflight' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        $output = $root . DIRECTORY_SEPARATOR . 'output';
        mkdir($output);

        try {
            $target = $root . DIRECTORY_SEPARATOR . 'queued-target.pdf';
            $directoryLink = $root . DIRECTORY_SEPARATOR . 'linked-then-directory.pdf';
            $brokenLink = $root . DIRECTORY_SEPARATOR . 'linked-then-missing.pdf';

            file_put_contents($target, "%PDF-1.4\n% queued before path kind changed\n%%EOF");
            if (!@symlink($target, $directoryLink)) {
                throw new RuntimeException('Unable to create queued symlink fixture.');
            }
            unlink($target);
            mkdir($target);

            if (!@symlink($root . DIRECTORY_SEPARATOR . 'missing-target.pdf', $brokenLink)) {
                throw new RuntimeException('Unable to create broken queued symlink fixture.');
            }

            $batch = new BatchConverter();
            $directory = $batch->processFilePreflightPlan(
                $directoryLink,
                $output,
                ['title' => 'Retargeted WordPress upload'],
                80
            );
            $directoryNoMinLength = $batch->processFilePreflightPlan(
                $directoryLink,
                $output,
                ['title' => 'Retargeted WordPress upload'],
                null
            );
            $broken = $batch->processFilePreflightPlan(
                $brokenLink,
                $output,
                ['title' => 'Broken WordPress upload'],
                80
            );

            $t->same('markerpdf.convert_process_single_pdf_preflight.v1', $directory['schema']);
            $t->same('linked-then-directory.pdf', $directory['filename']);
            $t->same('directory', $directory['filepath_path_type_at_worker_preflight']);
            $t->same(true, $directory['filepath_exists_at_worker_preflight']);
            $t->same(false, $directory['filepath_is_file_at_worker_preflight']);
            $t->same(true, $directory['filepath_is_readable_at_worker_preflight']);
            $t->same('selected-input-not-file-before-worker-preflight', $directory['worker_file_availability_boundary']);
            $t->same(true, $directory['selected_input_not_file_at_worker_preflight']);
            $t->same(true, $directory['selected_input_directory_at_worker_preflight']);
            $t->same(false, $directory['selected_input_missing_at_worker_preflight']);
            $t->same(false, $directory['selected_input_broken_symlink_at_worker_preflight']);
            $t->same(false, $directory['selected_input_unreadable_at_worker_preflight']);
            $t->same(true, $directory['min_length_gate_active']);
            $t->same(true, $directory['filetype_checked']);
            $t->same(false, $directory['text_length_checked']);
            $t->same('other', $directory['filetype']);
            $t->same('skipped-unsupported-filetype', $directory['status']);
            $t->same(0, $directory['upstream_return_value']);
            $t->same('unsupported-filetype-return-zero', $directory['upstream_return_boundary']);
            $t->same(false, $directory['should_invoke_converter']);
            $t->same(false, $directory['executes_python_or_models']);
            $t->same(false, $directory['executes_external_pdf_tools']);

            $directoryFiletype = $directory['filetype_review'];
            $t->same(false, $directoryFiletype['path_is_file']);
            $t->same(true, $directoryFiletype['path_is_readable']);
            $t->same(false, $directoryFiletype['bytes_available']);
            $t->same('unknown-kind-return-other', $directoryFiletype['return_boundary']);
            $t->contains('Could not determine filetype for ' . $directoryLink, $directoryFiletype['stdout_message_line']);

            $t->same('ready-for-conversion', $directoryNoMinLength['status']);
            $t->same(false, $directoryNoMinLength['filetype_checked']);
            $t->same(false, $directoryNoMinLength['text_length_checked']);
            $t->same(true, $directoryNoMinLength['selected_input_directory_at_worker_preflight']);
            $t->same(true, $directoryNoMinLength['should_invoke_converter']);
            $t->same('conversion-or-empty-output-return-none', $directoryNoMinLength['upstream_return_boundary']);

            $t->same('broken-symlink', $broken['filepath_path_type_at_worker_preflight']);
            $t->same(false, $broken['filepath_exists_at_worker_preflight']);
            $t->same(false, $broken['filepath_is_file_at_worker_preflight']);
            $t->same(false, $broken['filepath_is_readable_at_worker_preflight']);
            $t->same('selected-input-broken-symlink-before-worker-preflight', $broken['worker_file_availability_boundary']);
            $t->same(true, $broken['selected_input_broken_symlink_at_worker_preflight']);
            $t->same(false, $broken['selected_input_directory_at_worker_preflight']);
            $t->same(false, $broken['selected_input_not_file_at_worker_preflight']);
            $t->same('skipped-unsupported-filetype', $broken['status']);
            $t->same(0, $broken['upstream_return_value']);
            $t->same(false, $broken['should_invoke_converter']);
        } finally {
            $removeTree($root);
        }
    },
];
