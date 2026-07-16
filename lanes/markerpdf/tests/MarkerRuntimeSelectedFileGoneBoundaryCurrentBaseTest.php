<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-selected-file-gone-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf selected-file-gone folder.');
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
    'records selected file disappearing before worker filetype preflight' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        $output = $root . DIRECTORY_SEPARATOR . 'output';
        mkdir($output);

        try {
            $filepath = $root . DIRECTORY_SEPARATOR . 'queued-then-removed.pdf';

            $plan = (new BatchConverter())->processFilePreflightPlan(
                $filepath,
                $output,
                ['title' => 'Queued before unlink'],
                80
            );

            $t->same('markerpdf.convert_process_single_pdf_preflight.v1', $plan['schema']);
            $t->same('queued-then-removed.pdf', $plan['filename']);
            $t->same('skipped-unsupported-filetype', $plan['status']);
            $t->same('unsupported-filetype', $plan['skip_reason']);
            $t->same(true, $plan['min_length_gate_active']);
            $t->same(true, $plan['filetype_checked']);
            $t->same(false, $plan['text_length_checked']);
            $t->same('other', $plan['filetype']);
            $t->same('selected-input-missing-before-worker-preflight', $plan['worker_file_availability_boundary']);
            $t->same(false, $plan['filepath_exists_at_worker_preflight']);
            $t->same(false, $plan['filepath_is_file_at_worker_preflight']);
            $t->same(false, $plan['filepath_is_readable_at_worker_preflight']);
            $t->same('missing', $plan['filepath_path_type_at_worker_preflight']);
            $t->same(true, $plan['selected_input_missing_at_worker_preflight']);
            $t->same(false, $plan['selected_input_broken_symlink_at_worker_preflight']);
            $t->same(false, $plan['selected_input_not_file_at_worker_preflight']);
            $t->same(false, $plan['selected_input_unreadable_at_worker_preflight']);
            $t->same(0, $plan['upstream_return_value']);
            $t->same('int', $plan['upstream_return_type']);
            $t->same('unsupported-filetype-return-zero', $plan['upstream_return_boundary']);
            $t->same(false, $plan['should_invoke_converter']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_external_pdf_tools']);

            $filetype = $plan['filetype_review'];
            $t->same('other', $filetype['filetype']);
            $t->same(false, $filetype['path_is_file']);
            $t->same(false, $filetype['path_is_readable']);
            $t->same(false, $filetype['bytes_available']);
            $t->same(true, $filetype['prints_stdout_message']);
            $t->contains('Could not determine filetype for ' . $filepath, $filetype['stdout_message_line']);
            $t->same('unknown-kind-return-other', $filetype['return_boundary']);
        } finally {
            $removeTree($root);
        }
    },
    'keeps disappeared file visible while deferring failure when min length is inactive' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        $output = $root . DIRECTORY_SEPARATOR . 'output';
        mkdir($output);

        try {
            $filepath = $root . DIRECTORY_SEPARATOR . 'queued-without-min-length.pdf';

            $plan = (new BatchConverter())->processFilePreflightPlan(
                $filepath,
                $output,
                null,
                null
            );

            $t->same('ready-for-conversion', $plan['status']);
            $t->same(false, $plan['min_length_gate_active']);
            $t->same(false, $plan['filetype_checked']);
            $t->same(false, $plan['text_length_checked']);
            $t->same('selected-input-missing-before-worker-preflight', $plan['worker_file_availability_boundary']);
            $t->same(false, $plan['filepath_exists_at_worker_preflight']);
            $t->same(true, $plan['selected_input_missing_at_worker_preflight']);
            $t->same(true, $plan['should_invoke_converter']);
            $t->same('conversion-or-empty-output-return-none', $plan['upstream_return_boundary']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($root);
        }
    },
];
