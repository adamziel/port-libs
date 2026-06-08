<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\OutputWriter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-worker-availability-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerPDF worker availability folder.');
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
    'records selected input availability catch stages before process_single_pdf converter launch' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        $output = $root . DIRECTORY_SEPARATOR . 'output';
        mkdir($output);

        try {
            $batch = new BatchConverter();
            $missing = $root . DIRECTORY_SEPARATOR . 'selected-then-missing.pdf';
            $directory = $root . DIRECTORY_SEPARATOR . 'selected-then-directory.pdf';
            mkdir($directory);
            $broken = $root . DIRECTORY_SEPARATOR . 'selected-then-broken.pdf';
            if (!@symlink($root . DIRECTORY_SEPARATOR . 'missing-symlink-target.pdf', $broken)) {
                throw new RuntimeException('Unable to create broken symlink worker availability fixture.');
            }

            $missingNoMin = $batch->processFilePreflightPlan(
                $missing,
                $output,
                ['title' => 'WordPress upload disappeared after task construction'],
                null
            );
            $missingWithMin = $batch->processFilePreflightPlan(
                $missing,
                $output,
                ['title' => 'WordPress upload disappeared after task construction'],
                80
            );
            $directoryNoMin = $batch->processFilePreflightPlan(
                $directory,
                $output,
                ['title' => 'WordPress upload became a folder'],
                null
            );
            $brokenWithMin = $batch->processFilePreflightPlan(
                $broken,
                $output,
                ['title' => 'WordPress upload symlink broke after task construction'],
                80
            );

            (new OutputWriter())->saveMarkdown(
                $output,
                'selected-then-missing.pdf',
                '<!-- wp:paragraph --><p>Already imported.</p><!-- /wp:paragraph -->',
                [],
                ['title' => 'Already Imported']
            );
            $missingExisting = $batch->processFilePreflightPlan(
                $missing,
                $output,
                ['title' => 'Already imported before worker path access'],
                80
            );

            $noMinBoundary = $missingNoMin['worker_file_availability_runtime_boundary'];
            $t->same('convert.py process_single_pdf worker file availability boundary', $noMinBoundary['source']);
            $t->same('selected-input-missing-before-worker-preflight', $noMinBoundary['availability_boundary']);
            $t->same(false, $noMinBoundary['explicit_worker_isfile_gate']);
            $t->same(false, $noMinBoundary['min_length_gate_active']);
            $t->same(false, $noMinBoundary['handled_before_converter']);
            $t->same('convert_single_pdf', $noMinBoundary['handling_stage']);
            $t->same(true, $noMinBoundary['unavailable_input_reaches_converter']);
            $t->same('conversion-exception-print-return-none', $noMinBoundary['upstream_return_boundary_if_unavailable']);
            $t->same(true, $missingNoMin['should_invoke_converter']);
            $t->same('ready-for-conversion', $missingNoMin['status']);

            $minBoundary = $missingWithMin['worker_file_availability_runtime_boundary'];
            $t->same(true, $minBoundary['min_length_gate_active']);
            $t->same(true, $minBoundary['handled_before_converter']);
            $t->same('find_filetype', $minBoundary['handling_stage']);
            $t->same(false, $minBoundary['unavailable_input_reaches_converter']);
            $t->same('unsupported-filetype-return-zero', $minBoundary['upstream_return_boundary_if_unavailable']);
            $t->same('skipped-unsupported-filetype', $missingWithMin['status']);
            $t->same(0, $missingWithMin['upstream_return_value']);

            $directoryBoundary = $directoryNoMin['worker_file_availability_runtime_boundary'];
            $t->same('selected-input-not-file-before-worker-preflight', $directoryBoundary['availability_boundary']);
            $t->same('directory', $directoryBoundary['path_type']);
            $t->same('convert_single_pdf', $directoryBoundary['handling_stage']);
            $t->same(true, $directoryBoundary['unavailable_input_reaches_converter']);

            $brokenBoundary = $brokenWithMin['worker_file_availability_runtime_boundary'];
            $t->same('selected-input-broken-symlink-before-worker-preflight', $brokenBoundary['availability_boundary']);
            $t->same('broken-symlink', $brokenBoundary['path_type']);
            $t->same('find_filetype', $brokenBoundary['handling_stage']);
            $t->same(false, $brokenBoundary['unavailable_input_reaches_converter']);
            $t->same('unsupported-filetype-return-zero', $brokenBoundary['upstream_return_boundary_if_unavailable']);

            $existingBoundary = $missingExisting['worker_file_availability_runtime_boundary'];
            $t->same('markdown_exists', $existingBoundary['handling_stage']);
            $t->same(true, $existingBoundary['handled_before_converter']);
            $t->same(false, $existingBoundary['unavailable_input_reaches_converter']);
            $t->same('markdown_exists-return-none', $existingBoundary['upstream_return_boundary_if_unavailable']);
            $t->same('skipped-existing', $missingExisting['status']);
            $t->same(false, $missingExisting['executes_python_or_models']);
            $t->same(false, $missingExisting['executes_external_pdf_tools']);
        } finally {
            $removeTree($root);
        }
    },
];
