<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\OutputWriter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-markdown-symlink-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf markdown symlink test folder.');
    }

    return $path;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_link($path) || !is_dir($path)) {
        unlink($path);
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $removeTree($path . DIRECTORY_SEPARATOR . $entry);
    }

    rmdir($path);
};

$writePdf = static function (string $path, string $text): void {
    $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    $content = 'BT /F1 12 Tf 72 720 Td (' . $escaped . ') Tj ET';
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
    file_put_contents($path, $pdf);
};

return [
    'records generated markdown symlink existence before filetype text and converter gates' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $writePdf): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        $symlinkTargets = $makeTempDir();

        try {
            $writer = new OutputWriter();
            $batch = new BatchConverter();

            $livePdf = $input . DIRECTORY_SEPARATOR . 'live-symlink-output.pdf';
            $brokenPdf = $input . DIRECTORY_SEPARATOR . 'broken-symlink-output.pdf';
            $writePdf($livePdf, 'Live symlink output should skip before any text gate.');
            $writePdf($brokenPdf, 'Broken markdown symlink should not count as existing.');

            $liveMarkdownPath = $writer->getMarkdownFilepath($output, 'live-symlink-output.pdf');
            mkdir(dirname($liveMarkdownPath), 0777, true);
            $liveTarget = $symlinkTargets . DIRECTORY_SEPARATOR . 'already-imported.md';
            file_put_contents($liveTarget, '<!-- wp:paragraph --><p>Already imported.</p><!-- /wp:paragraph -->');
            if (!@symlink($liveTarget, $liveMarkdownPath)) {
                throw new RuntimeException('Unable to create live generated Markdown symlink fixture.');
            }

            $brokenMarkdownPath = $writer->getMarkdownFilepath($output, 'broken-symlink-output.pdf');
            mkdir(dirname($brokenMarkdownPath), 0777, true);
            if (!@symlink($symlinkTargets . DIRECTORY_SEPARATOR . 'missing-import.md', $brokenMarkdownPath)) {
                throw new RuntimeException('Unable to create broken generated Markdown symlink fixture.');
            }

            $lengthCalls = 0;
            $textLength = static function () use (&$lengthCalls): int {
                $lengthCalls++;

                return 500;
            };

            $live = $batch->processFilePreflightPlan(
                $livePdf,
                $output,
                ['title' => 'Live Symlink Output'],
                120,
                $textLength
            );
            $broken = $batch->processFilePreflightPlan(
                $brokenPdf,
                $output,
                ['title' => 'Broken Symlink Output'],
                120,
                $textLength
            );

            $t->same('skipped-existing', $live['status']);
            $t->same('markdown_exists', $live['skip_reason']);
            $t->same(true, $live['existing_markdown']);
            $t->same($liveMarkdownPath, $live['markdown_exists_path']);
            $t->same('os.path.exists', $live['markdown_exists_function']);
            $t->same(true, $live['markdown_exists_path_exists']);
            $t->same('file', $live['markdown_exists_path_type']);
            $t->same(true, $live['markdown_exists_path_is_symlink']);
            $t->same(true, $live['markdown_exists_symlink_target_exists']);
            $t->same('file', $live['markdown_exists_symlink_target_type']);
            $t->same(false, $live['markdown_exists_broken_symlink']);
            $t->same(true, $live['markdown_exists_symlink_counts_as_existing']);
            $t->same(false, $live['markdown_exists_broken_symlink_does_not_count_as_existing']);
            $t->same(false, $live['filetype_checked']);
            $t->same(false, $live['text_length_checked']);
            $t->same(false, $live['should_invoke_converter']);
            $t->same('markdown_exists-return-none', $live['upstream_return_boundary']);

            $t->same('ready-for-conversion', $broken['status']);
            $t->same(false, $broken['existing_markdown']);
            $t->same($brokenMarkdownPath, $broken['markdown_exists_path']);
            $t->same(false, $broken['markdown_exists_path_exists']);
            $t->same('broken-symlink', $broken['markdown_exists_path_type']);
            $t->same(true, $broken['markdown_exists_path_is_symlink']);
            $t->same(false, $broken['markdown_exists_symlink_target_exists']);
            $t->same('missing', $broken['markdown_exists_symlink_target_type']);
            $t->same(true, $broken['markdown_exists_broken_symlink']);
            $t->same(false, $broken['markdown_exists_symlink_counts_as_existing']);
            $t->same(true, $broken['markdown_exists_broken_symlink_does_not_count_as_existing']);
            $t->same(true, $broken['filetype_checked']);
            $t->same(true, $broken['text_length_checked']);
            $t->same(500, $broken['text_length']);
            $t->same(true, $broken['should_invoke_converter']);
            $t->same('conversion-or-empty-output-return-none', $broken['upstream_return_boundary']);
            $t->same(1, $lengthCalls);
            $t->same(false, $live['executes_python_or_models']);
            $t->same(false, $broken['executes_python_or_models']);
            $t->same(false, $broken['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
            $removeTree($symlinkTargets);
        }
    },
    'carries generated markdown symlink boundaries into convert.py main worker review' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $writePdf): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        $symlinkTargets = $makeTempDir();

        try {
            $writer = new OutputWriter();
            foreach (['live-symlink-output.pdf', 'broken-symlink-output.pdf', 'ready.pdf'] as $filename) {
                $writePdf($input . DIRECTORY_SEPARATOR . $filename, 'Runtime markdown symlink queue ' . $filename);
            }

            $liveMarkdownPath = $writer->getMarkdownFilepath($output, 'live-symlink-output.pdf');
            mkdir(dirname($liveMarkdownPath), 0777, true);
            $liveTarget = $symlinkTargets . DIRECTORY_SEPARATOR . 'already-imported.md';
            file_put_contents($liveTarget, '<!-- wp:paragraph --><p>Already imported.</p><!-- /wp:paragraph -->');
            if (!@symlink($liveTarget, $liveMarkdownPath)) {
                throw new RuntimeException('Unable to create live generated Markdown symlink fixture.');
            }

            $brokenMarkdownPath = $writer->getMarkdownFilepath($output, 'broken-symlink-output.pdf');
            mkdir(dirname($brokenMarkdownPath), 0777, true);
            if (!@symlink($symlinkTargets . DIRECTORY_SEPARATOR . 'missing-import.md', $brokenMarkdownPath)) {
                throw new RuntimeException('Unable to create broken generated Markdown symlink fixture.');
            }

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 4,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $review = $plan['worker_pool']['process_single_pdf_preflight'];
            $t->same(true, $review['review_reached']);
            $t->same(['live-symlink-output.pdf'], $review['existing_markdown_filenames']);
            $t->true(in_array('live-symlink-output.pdf', $review['markdown_exists_symlink_filenames'], true));
            $t->true(in_array('broken-symlink-output.pdf', $review['markdown_exists_symlink_filenames'], true));
            $t->same(['live-symlink-output.pdf'], $review['markdown_exists_live_symlink_filenames']);
            $t->same(['broken-symlink-output.pdf'], $review['markdown_exists_broken_symlink_filenames']);
            $t->same($liveMarkdownPath, $review['markdown_exists_path_by_filename']['live-symlink-output.pdf']);
            $t->same($brokenMarkdownPath, $review['markdown_exists_path_by_filename']['broken-symlink-output.pdf']);
            $t->same(true, $review['markdown_exists_path_is_symlink_by_filename']['live-symlink-output.pdf']);
            $t->same(true, $review['markdown_exists_path_is_symlink_by_filename']['broken-symlink-output.pdf']);
            $t->same(true, $review['markdown_exists_symlink_target_exists_by_filename']['live-symlink-output.pdf']);
            $t->same(false, $review['markdown_exists_symlink_target_exists_by_filename']['broken-symlink-output.pdf']);
            $t->same('file', $review['markdown_exists_symlink_target_type_by_filename']['live-symlink-output.pdf']);
            $t->same('missing', $review['markdown_exists_symlink_target_type_by_filename']['broken-symlink-output.pdf']);
            $t->same(true, $review['markdown_exists_symlink_counts_as_existing_by_filename']['live-symlink-output.pdf']);
            $t->same(false, $review['markdown_exists_symlink_counts_as_existing_by_filename']['broken-symlink-output.pdf']);
            $t->same(false, $review['markdown_exists_broken_symlink_does_not_count_as_existing_by_filename']['live-symlink-output.pdf']);
            $t->same(true, $review['markdown_exists_broken_symlink_does_not_count_as_existing_by_filename']['broken-symlink-output.pdf']);
            $t->same('skipped-existing', $review['status_by_filename']['live-symlink-output.pdf']);
            $t->same('ready-for-conversion', $review['status_by_filename']['broken-symlink-output.pdf']);
            $t->same('markdown_exists-return-none', $review['upstream_return_boundary_by_filename']['live-symlink-output.pdf']);
            $t->same('conversion-or-empty-output-return-none', $review['upstream_return_boundary_by_filename']['broken-symlink-output.pdf']);

            $rows = [];
            foreach ($review['preflight_reviews'] as $row) {
                $rows[$row['filename']] = $row;
            }
            $t->same(true, $rows['live-symlink-output.pdf']['markdown_exists_path_is_symlink']);
            $t->same(false, $rows['live-symlink-output.pdf']['should_invoke_converter']);
            $t->same(true, $rows['broken-symlink-output.pdf']['markdown_exists_broken_symlink']);
            $t->same(true, $rows['broken-symlink-output.pdf']['should_invoke_converter']);
            $t->same(true, $rows['ready.pdf']['should_invoke_converter']);

            $drain = $plan['worker_pool']['pool_result_drain'];
            $t->same('markdown_exists-return-none', $drain['return_boundary_by_filename']['live-symlink-output.pdf']);
            $t->same(null, $drain['return_value_by_filename']['live-symlink-output.pdf']);
            $t->same('conversion-or-empty-output-return-none', $drain['return_boundary_by_filename']['broken-symlink-output.pdf']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
            $removeTree($symlinkTargets);
        }
    },
];
