<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-metadata-process-task-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerPDF metadata process task folder.');
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

$metadataAwareConverter = static function (string $filepath, mixed $metadata): array {
    if ($metadata) {
        $type = is_array($metadata) && array_is_list($metadata)
            ? 'list'
            : match (get_debug_type($metadata)) {
                'string' => 'str',
                'int' => 'int',
                'float' => 'float',
                'bool' => 'bool',
                default => get_debug_type($metadata),
            };

        if (!is_array($metadata) || array_is_list($metadata)) {
            throw new BadMethodCallException("'{$type}' object has no attribute 'get'");
        }
    }

    return [
        'text' => 'Converted ' . basename($filepath),
        'images' => [],
        'metadata' => [
            'metadata_type' => is_array($metadata) && array_is_list($metadata)
                ? 'list'
                : get_debug_type($metadata),
        ],
    ];
};

return [
    'keeps process_single_pdf task metadata values mixed until converter-side metadata lookup' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $metadataAwareConverter): void {
        $input = $makeTempDir();
        $output = $makeTempDir();

        try {
            foreach (['truthy-string.pdf', 'truthy-list.pdf', 'falsy-zero.pdf', 'dict-meta.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }

            $batch = new BatchConverter();
            $truthyString = $batch->processTask(
                [
                    'filepath' => $input . DIRECTORY_SEPARATOR . 'truthy-string.pdf',
                    'out_folder' => $output,
                    'metadata' => 'English',
                    'min_length' => null,
                ],
                $metadataAwareConverter
            );
            $truthyList = $batch->processTask(
                [
                    'filepath' => $input . DIRECTORY_SEPARATOR . 'truthy-list.pdf',
                    'out_folder' => $output,
                    'metadata' => ['English'],
                    'min_length' => null,
                ],
                $metadataAwareConverter
            );
            $falsyZero = $batch->processTask(
                [
                    'filepath' => $input . DIRECTORY_SEPARATOR . 'falsy-zero.pdf',
                    'out_folder' => $output,
                    'metadata' => 0,
                    'min_length' => null,
                ],
                $metadataAwareConverter
            );
            $dictMeta = $batch->processTask(
                [
                    'filepath' => $input . DIRECTORY_SEPARATOR . 'dict-meta.pdf',
                    'out_folder' => $output,
                    'metadata' => ['languages' => ['English'], 'title' => 'Dictionary Metadata'],
                    'min_length' => null,
                ],
                $metadataAwareConverter
            );

            $t->same('error', $truthyString['status']);
            $t->same('str', $truthyString['preflight']['metadata_value_type']);
            $t->same(true, $truthyString['preflight']['metadata_python_truthy']);
            $t->same('convert-single-pdf-metadata-get-failed', $truthyString['preflight']['metadata_non_mapping_boundary']);
            $t->same('conversion-exception-print-return-none', $truthyString['conversion_result']['upstream_return_boundary']);
            $t->contains("'str' object has no attribute 'get'", $truthyString['error_output']['message_line']);
            $t->same(false, $truthyString['writes_markdown']);

            $t->same('error', $truthyList['status']);
            $t->same('list', $truthyList['preflight']['metadata_value_type']);
            $t->same(true, $truthyList['preflight']['metadata_python_truthy']);
            $t->same('convert-single-pdf-metadata-get-failed', $truthyList['preflight']['metadata_non_mapping_boundary']);
            $t->contains("'list' object has no attribute 'get'", $truthyList['error_output']['message_line']);

            $t->same('converted', $falsyZero['status']);
            $t->same('int', $falsyZero['preflight']['metadata_value_type']);
            $t->same(false, $falsyZero['preflight']['metadata_python_truthy']);
            $t->same('falsy-non-dict-metadata-skips-language-lookup', $falsyZero['preflight']['metadata_non_mapping_boundary']);
            $t->same('saved-markdown-return-none', $falsyZero['conversion_result']['upstream_return_boundary']);
            $t->same(false, $falsyZero['executes_python_or_models']);
            $t->same(false, $falsyZero['executes_external_pdf_tools']);

            $t->same('converted', $dictMeta['status']);
            $t->same('dict', $dictMeta['preflight']['metadata_value_type']);
            $t->same(true, $dictMeta['preflight']['metadata_is_mapping']);
            $t->same(null, $dictMeta['preflight']['metadata_non_mapping_boundary']);
            $t->same('saved-markdown-return-none', $dictMeta['conversion_result']['upstream_return_boundary']);
            $t->same(false, $dictMeta['executes_python_or_models']);
            $t->same(false, $dictMeta['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
