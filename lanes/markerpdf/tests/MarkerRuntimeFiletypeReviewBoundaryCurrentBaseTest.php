<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\FiletypeDetector;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-filetype-review-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create markerPDF runtime filetype review fixture.');
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
    'records upstream find_filetype stdout review for unknown and nonstandard uploads' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            $empty = $input . DIRECTORY_SEPARATOR . 'empty-upload.pdf';
            $png = $input . DIRECTORY_SEPARATOR . 'image-upload.pdf';
            $pdf = $input . DIRECTORY_SEPARATOR . 'ready.pdf';
            file_put_contents($empty, '');
            file_put_contents($png, "\x89PNG\r\n\x1a\nnot a marker-supported pdf");
            file_put_contents($pdf, "%PDF-1.4\n% ready\n%%EOF");

            $detector = new FiletypeDetector();
            $unknownReview = $detector->findFiletypeReview($empty);
            $nonstandardReview = $detector->findFiletypeReview($png);
            $pdfReview = $detector->findFiletypeReview($pdf);

            $t->same('other', $unknownReview['filetype']);
            $t->same(false, $unknownReview['filetype_guess_available']);
            $t->same('unknown-kind-return-other', $unknownReview['return_boundary']);
            $t->same('Could not determine filetype for ' . $empty, $unknownReview['stdout_message_line']);
            $t->same(true, $unknownReview['prints_stdout_message']);

            $t->same('other', $nonstandardReview['filetype']);
            $t->same(true, $nonstandardReview['filetype_guess_available']);
            $t->same('image/png', $nonstandardReview['mimetype']);
            $t->same('nonstandard-filetype-return-other', $nonstandardReview['return_boundary']);
            $t->same('Found nonstandard filetype image/png', $nonstandardReview['stdout_message_line']);
            $t->same(true, $nonstandardReview['prints_stdout_message']);

            $t->same('pdf', $pdfReview['filetype']);
            $t->same('pdf-return-pdf', $pdfReview['return_boundary']);
            $t->same(null, $pdfReview['stdout_message_line']);
            $t->same(false, $pdfReview['prints_stdout_message']);

            $batch = new BatchConverter();
            $unknownPreflight = $batch->processFilePreflightPlan($empty, $output, null, 20);
            $nonstandardPreflight = $batch->processFilePreflightPlan($png, $output, null, 20);
            $runtime = $batch->runtimeMainPreflightPlan($input, $output, minLength: -1, workers: 4);
            $workerReview = $runtime['worker_pool']['process_single_pdf_preflight'];

            $t->same('skipped-unsupported-filetype', $unknownPreflight['status']);
            $t->same('unknown-kind-return-other', $unknownPreflight['filetype_review']['return_boundary']);
            $t->same('Could not determine filetype for ' . $empty, $unknownPreflight['filetype_review']['stdout_message_line']);
            $t->same(false, $unknownPreflight['should_invoke_converter']);

            $t->same('skipped-unsupported-filetype', $nonstandardPreflight['status']);
            $t->same('nonstandard-filetype-return-other', $nonstandardPreflight['filetype_review']['return_boundary']);
            $t->same('Found nonstandard filetype image/png', $nonstandardPreflight['filetype_review']['stdout_message_line']);
            $t->same(false, $nonstandardPreflight['text_length_checked']);

            $t->same(true, $workerReview['review_reached']);
            $stdoutFilenames = $workerReview['filetype_stdout_filenames'];
            sort($stdoutFilenames, SORT_STRING);
            $t->same(['empty-upload.pdf', 'image-upload.pdf'], $stdoutFilenames);
            $t->same(
                'Could not determine filetype for ' . $empty,
                $workerReview['filetype_stdout_message_by_filename']['empty-upload.pdf']
            );
            $t->same(
                'Found nonstandard filetype image/png',
                $workerReview['filetype_stdout_message_by_filename']['image-upload.pdf']
            );
            $t->same('unknown-kind-return-other', $workerReview['filetype_review_by_filename']['empty-upload.pdf']['return_boundary']);
            $t->same('nonstandard-filetype-return-other', $workerReview['filetype_review_by_filename']['image-upload.pdf']['return_boundary']);
            $t->same(null, $workerReview['filetype_stdout_message_by_filename']['ready.pdf']);
            $t->same('ready-for-conversion', $workerReview['status_by_filename']['ready.pdf']);
            $t->same(false, $runtime['executes_python_or_models']);
            $t->same(false, $runtime['executes_multiprocessing']);
            $t->same(false, $runtime['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
