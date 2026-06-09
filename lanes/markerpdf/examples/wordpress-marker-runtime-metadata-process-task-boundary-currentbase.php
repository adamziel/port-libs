<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-runtime-metadata-process-task-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-metadata-process-task-output-' . $runId;
if (!mkdir($input, 0777, true) && !is_dir($input)) {
    throw new RuntimeException('Unable to create markerPDF process-task metadata input folder.');
}
if (!mkdir($output, 0777, true) && !is_dir($output)) {
    throw new RuntimeException('Unable to create markerPDF process-task metadata output folder.');
}

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

$pythonMetadataType = static function (mixed $metadata): string {
    if (is_array($metadata) && array_is_list($metadata)) {
        return 'list';
    }

    return match (get_debug_type($metadata)) {
        'string' => 'str',
        'integer', 'int' => 'int',
        'double', 'float' => 'float',
        'boolean', 'bool' => 'bool',
        default => get_debug_type($metadata),
    };
};

$metadataAwareConverter = static function (string $filepath, mixed $metadata) use ($pythonMetadataType): array {
    if ($metadata) {
        $type = $pythonMetadataType($metadata);
        if (!is_array($metadata) || array_is_list($metadata)) {
            throw new BadMethodCallException("'{$type}' object has no attribute 'get'");
        }
    }

    return [
        'text' => '<!-- wp:paragraph --><p>Converted ' . basename($filepath) . '</p><!-- /wp:paragraph -->',
        'images' => [],
        'metadata' => [
            'metadata_type' => $pythonMetadataType($metadata),
        ],
    ];
};

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

    $truthyStringMatchesUpstream = $truthyString['status'] === 'error'
        && $truthyString['preflight']['metadata_value_type'] === 'str'
        && $truthyString['preflight']['metadata_non_mapping_boundary'] === 'convert-single-pdf-metadata-get-failed'
        && str_contains($truthyString['error_output']['message_line'], "'str' object has no attribute 'get'")
        && $truthyString['writes_markdown'] === false;
    $truthyListMatchesUpstream = $truthyList['status'] === 'error'
        && $truthyList['preflight']['metadata_value_type'] === 'list'
        && $truthyList['preflight']['metadata_non_mapping_boundary'] === 'convert-single-pdf-metadata-get-failed'
        && str_contains($truthyList['error_output']['message_line'], "'list' object has no attribute 'get'");
    $falsyZeroSkipsLookup = $falsyZero['status'] === 'converted'
        && $falsyZero['preflight']['metadata_value_type'] === 'int'
        && $falsyZero['preflight']['metadata_python_truthy'] === false
        && $falsyZero['preflight']['metadata_non_mapping_boundary'] === 'falsy-non-dict-metadata-skips-language-lookup'
        && is_file($falsyZero['markdown']);
    $dictMetaConverts = $dictMeta['status'] === 'converted'
        && $dictMeta['preflight']['metadata_value_type'] === 'dict'
        && $dictMeta['preflight']['metadata_non_mapping_boundary'] === null
        && is_file($dictMeta['markdown']);
    $noModelExecution = $truthyString['executes_python_or_models'] === false
        && $truthyList['executes_python_or_models'] === false
        && $falsyZero['executes_python_or_models'] === false
        && $dictMeta['executes_python_or_models'] === false
        && $truthyString['executes_external_pdf_tools'] === false
        && $truthyList['executes_external_pdf_tools'] === false
        && $falsyZero['executes_external_pdf_tools'] === false
        && $dictMeta['executes_external_pdf_tools'] === false;

    if (!$truthyStringMatchesUpstream || !$truthyListMatchesUpstream || !$falsyZeroSkipsLookup || !$dictMetaConverts || !$noModelExecution) {
        throw new RuntimeException('MarkerPDF runtime metadata process-task boundary smoke failed.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-metadata-process-task-boundary-currentbase',
        'purpose' => 'Review process_single_pdf metadata values at the executable task boundary for WordPress batch imports without launching Python, model workers, multiprocessing, or external PDF tools.',
        'source_truth' => 'sddai/markerPDF convert.py passes metadata.get(os.path.basename(f)) values into process_single_pdf; marker.convert.convert_single_pdf calls metadata.get("languages", ...) only when metadata is truthy.',
        'truthy_string_status' => $truthyString['status'],
        'truthy_string_error_boundary' => $truthyString['conversion_result']['upstream_return_boundary'],
        'truthy_string_error' => $truthyString['error_output']['message_line'],
        'truthy_list_status' => $truthyList['status'],
        'truthy_list_error_boundary' => $truthyList['conversion_result']['upstream_return_boundary'],
        'falsy_zero_status' => $falsyZero['status'],
        'falsy_zero_boundary' => $falsyZero['preflight']['metadata_non_mapping_boundary'],
        'dict_status' => $dictMeta['status'],
        'dict_boundary' => $dictMeta['preflight']['metadata_non_mapping_boundary'],
        'converted_markdown_files' => [
            basename($falsyZero['markdown']),
            basename($dictMeta['markdown']),
        ],
        'executes_python_or_models' => false,
        'executes_multiprocessing' => false,
        'executes_external_pdf_tools' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($input);
    $removeTree($output);
}
