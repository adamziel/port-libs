<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-metadata-task-arg-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerPDF metadata task-arg folder.');
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
    'preserves scalar and list metadata task-arg value types into worker preflight review' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();

        try {
            foreach ([
                'dict-meta.pdf',
                'empty-dict-meta.pdf',
                'empty-list-meta.pdf',
                'list-meta.pdf',
                'scalar-meta.pdf',
                'zero-meta.pdf',
            ] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }

            $metadataFile = $output . DIRECTORY_SEPARATOR . 'metadata-task-args.json';
            file_put_contents($metadataFile, json_encode([
                'dict-meta.pdf' => ['title' => 'Dict Metadata', 'languages' => ['English']],
                'empty-dict-meta.pdf' => new stdClass(),
                'empty-list-meta.pdf' => [],
                'list-meta.pdf' => ['English'],
                'scalar-meta.pdf' => 'English',
                'zero-meta.pdf' => 0,
            ], JSON_THROW_ON_ERROR));

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 8,
                metadataFile: $metadataFile,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $preflight = $plan['worker_pool']['process_single_pdf_preflight'];

            $t->same(true, $preflight['review_reached']);
            $t->same(null, $preflight['blocked_by']);
            $t->same(6, $preflight['task_args_count']);
            $metadataTypes = $preflight['metadata_value_type_by_filename'];
            ksort($metadataTypes);
            $t->same([
                'dict-meta.pdf' => 'dict',
                'empty-dict-meta.pdf' => 'dict',
                'empty-list-meta.pdf' => 'list',
                'list-meta.pdf' => 'list',
                'scalar-meta.pdf' => 'str',
                'zero-meta.pdf' => 'int',
            ], $metadataTypes);
            $truthyNonMapping = $preflight['truthy_non_mapping_metadata_filenames'];
            sort($truthyNonMapping, SORT_STRING);
            $falsyNonMapping = $preflight['falsy_non_mapping_metadata_filenames'];
            sort($falsyNonMapping, SORT_STRING);
            $t->same(['list-meta.pdf', 'scalar-meta.pdf'], $truthyNonMapping);
            $t->same(['empty-list-meta.pdf', 'zero-meta.pdf'], $falsyNonMapping);
            $t->same(null, $preflight['metadata_non_mapping_boundary_by_filename']['empty-dict-meta.pdf']);
            $t->same(
                'falsy-non-dict-metadata-skips-language-lookup',
                $preflight['metadata_non_mapping_boundary_by_filename']['empty-list-meta.pdf']
            );
            $t->same(
                'convert-single-pdf-metadata-get-failed',
                $preflight['metadata_non_mapping_boundary_by_filename']['scalar-meta.pdf']
            );
            $t->same(
                'falsy-non-dict-metadata-skips-language-lookup',
                $preflight['metadata_non_mapping_boundary_by_filename']['zero-meta.pdf']
            );

            $rowsByName = [];
            foreach ($preflight['preflight_reviews'] as $row) {
                $rowsByName[$row['filename']] = $row;
            }

            $t->same(true, $rowsByName['dict-meta.pdf']['metadata_is_mapping']);
            $t->same(false, $rowsByName['dict-meta.pdf']['metadata_is_list']);
            $t->same(true, $rowsByName['dict-meta.pdf']['conversion_call']['receives_metadata']);
            $t->same('dict', $rowsByName['dict-meta.pdf']['conversion_call']['metadata_argument_value_type']);

            $t->same(true, $rowsByName['empty-dict-meta.pdf']['metadata_is_mapping']);
            $t->same(false, $rowsByName['empty-dict-meta.pdf']['metadata_is_list']);
            $t->same(false, $rowsByName['empty-dict-meta.pdf']['metadata_python_truthy']);
            $t->same(null, $rowsByName['empty-dict-meta.pdf']['metadata_non_mapping_boundary']);
            $t->same('dict', $rowsByName['empty-dict-meta.pdf']['conversion_call']['metadata_argument_value_type']);

            $t->same(false, $rowsByName['empty-list-meta.pdf']['metadata_is_mapping']);
            $t->same(true, $rowsByName['empty-list-meta.pdf']['metadata_is_list']);
            $t->same(false, $rowsByName['empty-list-meta.pdf']['metadata_python_truthy']);
            $t->same(
                'falsy-non-dict-metadata-skips-language-lookup',
                $rowsByName['empty-list-meta.pdf']['metadata_non_mapping_boundary']
            );
            $t->same('list', $rowsByName['empty-list-meta.pdf']['conversion_call']['metadata_argument_value_type']);

            $t->same(false, $rowsByName['list-meta.pdf']['metadata_is_mapping']);
            $t->same(true, $rowsByName['list-meta.pdf']['metadata_is_list']);
            $t->same(true, $rowsByName['list-meta.pdf']['metadata_python_truthy']);
            $t->same('convert-single-pdf-metadata-get-failed', $rowsByName['list-meta.pdf']['metadata_non_mapping_boundary']);
            $t->same('list', $rowsByName['list-meta.pdf']['conversion_call']['metadata_argument_value_type']);

            $t->same(false, $rowsByName['scalar-meta.pdf']['metadata_is_mapping']);
            $t->same(false, $rowsByName['scalar-meta.pdf']['metadata_is_list']);
            $t->same(true, $rowsByName['scalar-meta.pdf']['metadata_python_truthy']);
            $t->same('str', $rowsByName['scalar-meta.pdf']['conversion_call']['metadata_argument_value_type']);
            $t->same(
                'convert-single-pdf-metadata-get-failed',
                $rowsByName['scalar-meta.pdf']['conversion_call']['metadata_argument_non_mapping_boundary']
            );

            $t->same(false, $rowsByName['zero-meta.pdf']['metadata_python_truthy']);
            $t->same('int', $rowsByName['zero-meta.pdf']['metadata_value_type']);
            $t->same(
                'falsy-non-dict-metadata-skips-language-lookup',
                $rowsByName['zero-meta.pdf']['conversion_call']['metadata_argument_non_mapping_boundary']
            );

            $directScalar = (new BatchConverter())->processFilePreflightPlan(
                $input . DIRECTORY_SEPARATOR . 'scalar-meta.pdf',
                $output,
                'English',
                null
            );
            $t->same('ready-for-conversion', $directScalar['status']);
            $t->same('str', $directScalar['metadata_value_type']);
            $t->same(true, $directScalar['conversion_call']['receives_metadata']);
            $t->same('convert-single-pdf-metadata-get-failed', $directScalar['metadata_non_mapping_boundary']);

            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
