<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerServerAdapter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-server-upload-filename-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf upload filename test folder.');
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

return [
    'records upstream raw upload filename joins while native wrapper writes basename-safe paths' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $uploadRoot = $makeTempDir();
        try {
            $adapter = new MarkerServerAdapter();
            $upload = [
                'filename' => '../wp escaped upload.pdf',
                'content_type' => 'application/pdf',
                'bytes' => '%PDF upload filename boundary',
            ];
            $plan = $adapter->serverUploadFilenameBoundaryPlan($upload, $uploadRoot);
            $seenPath = null;
            $response = $adapter->convertPdfFromUpload(
                $upload,
                ['paginate' => true, 'extract_images' => false],
                $uploadRoot,
                true,
                static function (string $filepath, array $options) use (&$seenPath): array {
                    $seenPath = $filepath;

                    return [
                        'markdown' => 'Uploaded filename boundary: ' . basename($filepath),
                        'images' => [],
                        'metadata' => ['source' => basename($filepath), 'options' => $options],
                    ];
                }
            );

            $t->same('markerpdf.server_upload_filename_boundary.v1', $plan['schema']);
            $t->contains('marker_server.py::convert_pdf_from_upload', $plan['source']);
            $t->contains('os.path.join', $plan['source']);
            $t->same($uploadRoot, $plan['upload_directory']);
            $t->same('../wp escaped upload.pdf', $plan['raw_filename']);
            $t->same('application/pdf', $plan['content_type']);
            $t->same(true, $plan['content_type_admitted_by_upstream_guard']);
            $t->same('os.path.join(UPLOAD_DIRECTORY, file.filename)', $plan['upstream_upload_path_expression']);
            $t->same($uploadRoot . '/../wp escaped upload.pdf', $plan['upstream_raw_upload_path']);
            $t->same(true, $plan['upstream_uses_raw_upload_filename']);
            $t->same(true, $plan['upstream_raw_filename_has_directory_segments']);
            $t->same(true, $plan['upstream_raw_filename_has_parent_segments']);
            $t->same(false, $plan['upstream_raw_filename_is_posix_absolute']);
            $t->same(false, $plan['upstream_raw_filename_is_windows_absolute']);
            $t->same(true, $plan['upstream_raw_path_may_escape_upload_directory']);
            $t->same('wp escaped upload.pdf', $plan['native_safe_basename']);
            $t->same(true, $plan['native_safe_basename_valid']);
            $t->same($uploadRoot . DIRECTORY_SEPARATOR . 'wp escaped upload.pdf', $plan['native_basename_upload_path']);
            $t->same(true, $plan['native_wrapper_uses_basename_for_written_path']);
            $t->same(true, $plan['native_wrapper_rejects_empty_dot_or_dotdot_basename']);
            $t->same(false, $plan['native_wrapper_rejects_filename_before_write']);
            $t->same(true, $plan['review_preserves_upstream_raw_path_without_using_it_for_native_write']);
            $t->same(true, $plan['raw_upload_bytes_excluded']);
            $t->same(true, $plan['review_only']);
            $t->same(false, $plan['executes_fastapi']);
            $t->same(false, $plan['executes_live_http']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_external_pdf_tools']);

            $t->same(true, $response['success']);
            $t->same($uploadRoot . DIRECTORY_SEPARATOR . 'wp escaped upload.pdf', $seenPath);
            $t->same(false, is_file($uploadRoot . DIRECTORY_SEPARATOR . 'wp escaped upload.pdf'));
            $t->same(false, is_file(dirname($uploadRoot) . DIRECTORY_SEPARATOR . 'wp escaped upload.pdf'));
            $t->contains('wp escaped upload.pdf', $response['markdown']);
            $t->same('wp escaped upload.pdf', $response['metadata']['source']);
            $t->same(['max_pages' => null, 'langs' => null, 'ocr_all_pages' => false], $response['metadata']['options']);
        } finally {
            $removeTree($uploadRoot);
        }
    },
    'records absolute upstream upload filenames as directory escape candidates without live server execution' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $uploadRoot = $makeTempDir();
        try {
            $adapter = new MarkerServerAdapter();
            $absoluteFilename = $uploadRoot . '-outside.pdf';
            $plan = $adapter->serverUploadFilenameBoundaryPlan(
                [
                    'filename' => $absoluteFilename,
                    'content_type' => 'application/pdf',
                    'bytes' => '%PDF absolute upload filename boundary',
                ],
                $uploadRoot
            );

            $t->same($absoluteFilename, $plan['raw_filename']);
            $t->same($absoluteFilename, $plan['upstream_raw_upload_path']);
            $t->same(true, $plan['upstream_raw_filename_has_directory_segments']);
            $t->same(false, $plan['upstream_raw_filename_has_parent_segments']);
            $t->same(true, $plan['upstream_raw_filename_is_posix_absolute']);
            $t->same(false, $plan['upstream_raw_filename_is_windows_absolute']);
            $t->same(true, $plan['upstream_raw_path_may_escape_upload_directory']);
            $t->same(basename($absoluteFilename), $plan['native_safe_basename']);
            $t->same($uploadRoot . DIRECTORY_SEPARATOR . basename($absoluteFilename), $plan['native_basename_upload_path']);
            $t->same(true, $plan['native_wrapper_uses_basename_for_written_path']);
            $t->same(true, $plan['review_preserves_upstream_raw_path_without_using_it_for_native_write']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_external_pdf_tools']);
            $t->same(false, is_file($absoluteFilename));
        } finally {
            $removeTree($uploadRoot);
        }
    },
];
