<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\OutputWriter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-markdown-exists-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf markdown_exists test folder.');
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
    'treats markdown path directories as existing like upstream os.path.exists before worker gates' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePdf): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            $pdf = $input . DIRECTORY_SEPARATOR . 'directory-markdown.pdf';
            $writePdf($pdf, 'Directory markdown collision should be skipped.');

            $writer = new OutputWriter();
            $markdownPath = $writer->getMarkdownFilepath($output, 'directory-markdown.pdf');
            mkdir(dirname($markdownPath), 0777, true);
            mkdir($markdownPath, 0777, true);

            $lengthCalls = 0;
            $converterCalls = 0;
            $batch = new BatchConverter();
            $preflight = $batch->processFilePreflightPlan(
                $pdf,
                $output,
                ['title' => 'Directory Markdown Collision'],
                120,
                static function () use (&$lengthCalls): int {
                    $lengthCalls++;

                    return 500;
                }
            );
            $result = $batch->processFile(
                $pdf,
                $output,
                ['title' => 'Directory Markdown Collision'],
                120,
                static function () use (&$converterCalls): string {
                    $converterCalls++;

                    return '<!-- wp:paragraph --><p>Should not convert.</p><!-- /wp:paragraph -->';
                },
                static function () use (&$lengthCalls): int {
                    $lengthCalls++;

                    return 500;
                }
            );

            $t->same('markerpdf.convert_process_single_pdf_preflight.v1', $preflight['schema']);
            $t->same('skipped-existing', $preflight['status']);
            $t->same('markdown_exists', $preflight['skip_reason']);
            $t->same(true, $preflight['existing_markdown']);
            $t->same($markdownPath, $preflight['markdown_exists_path']);
            $t->same('os.path.exists', $preflight['markdown_exists_function']);
            $t->same(true, $preflight['markdown_exists_path_exists']);
            $t->same('directory', $preflight['markdown_exists_path_type']);
            $t->same(true, $preflight['markdown_exists_directory_counts_as_existing']);
            $t->same(false, $preflight['filetype_checked']);
            $t->same(false, $preflight['text_length_checked']);
            $t->same(false, $preflight['should_invoke_converter']);
            $t->same(null, $preflight['upstream_return_value']);
            $t->same('python-none', $preflight['upstream_return_type']);
            $t->same('markdown_exists-return-none', $preflight['upstream_return_boundary']);
            $t->same(0, $lengthCalls);
            $t->same(0, $converterCalls);
            $t->same('skipped-existing', $result['status']);
            $t->same('markdown_exists-return-none', $result['upstream_return_boundary']);
            $t->same(false, $result['executes_python_or_models']);
            $t->same(false, $result['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
    'records directory markdown path collisions in convert.py main worker review and result drain' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePdf): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            foreach (['directory-markdown.pdf', 'ready.pdf'] as $filename) {
                $writePdf($input . DIRECTORY_SEPARATOR . $filename, 'Runtime queue ' . $filename);
            }

            $writer = new OutputWriter();
            $markdownPath = $writer->getMarkdownFilepath($output, 'directory-markdown.pdf');
            mkdir(dirname($markdownPath), 0777, true);
            mkdir($markdownPath, 0777, true);

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 4,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $review = $plan['worker_pool']['process_single_pdf_preflight'];
            $t->same(true, $review['review_reached']);
            $t->same(['directory-markdown.pdf'], $review['existing_markdown_filenames']);
            $t->same('skipped-existing', $review['status_by_filename']['directory-markdown.pdf']);
            $t->same('markdown_exists-return-none', $review['upstream_return_boundary_by_filename']['directory-markdown.pdf']);
            $t->same(false, $review['filetype_checked_by_filename']['directory-markdown.pdf']);
            $t->same(false, $review['text_length_checked_by_filename']['directory-markdown.pdf']);
            $t->same($markdownPath, $review['markdown_exists_path_by_filename']['directory-markdown.pdf']);
            $t->same('directory', $review['markdown_exists_path_type_by_filename']['directory-markdown.pdf']);
            $t->same(true, $review['markdown_exists_directory_filenames'] === ['directory-markdown.pdf']);

            $rows = [];
            foreach ($review['preflight_reviews'] as $row) {
                $rows[$row['filename']] = $row;
            }
            $t->same('directory', $rows['directory-markdown.pdf']['markdown_exists_path_type']);
            $t->same(true, $rows['directory-markdown.pdf']['markdown_exists_directory_counts_as_existing']);
            $t->same(false, $rows['directory-markdown.pdf']['should_invoke_converter']);
            $t->same(true, $rows['ready.pdf']['should_invoke_converter']);

            $drain = $plan['worker_pool']['pool_result_drain'];
            $t->same('NoneType', $drain['return_type_by_filename']['directory-markdown.pdf']);
            $t->same(null, $drain['return_value_by_filename']['directory-markdown.pdf']);
            $t->same('markdown_exists-return-none', $drain['return_boundary_by_filename']['directory-markdown.pdf']);
            $t->same('skipped-existing', $drain['status_by_filename']['directory-markdown.pdf']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
