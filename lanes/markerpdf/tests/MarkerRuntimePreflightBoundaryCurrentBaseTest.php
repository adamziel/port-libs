<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OutputWriter;
use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\SingleDocumentConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-preflight-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf runtime preflight folder.');
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
    'records convert_single runtime preflight before model loading without executing models' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $output = $makeTempDir();
        try {
            (new OutputWriter())->saveMarkdown(
                $output,
                'annual.report.pdf',
                '<!-- wp:paragraph --><p>Existing import.</p><!-- /wp:paragraph -->',
                [],
                ['title' => 'Existing Import']
            );

            $filename = '/wp/uploads/2026/annual.report.pdf';
            $plan = (new SingleDocumentConverter())->runtimePreflightPlan(
                $filename,
                $output,
                maxPages: 3,
                startPage: 1,
                languages: 'English, Spanish,de',
                batchMultiplier: 4
            );

            $t->same('markerpdf.convert_single_runtime_preflight.v1', $plan['schema']);
            $t->contains('convert_single.py::main', $plan['source']);
            $t->contains('load_all_models', $plan['source']);
            $t->same('annual.report.pdf', $plan['filename']);
            $t->same($filename, $plan['filepath']);
            $t->same($output, $plan['output_folder']);
            $t->same($output . DIRECTORY_SEPARATOR . 'annual.report', $plan['subfolder']);
            $t->same($output . DIRECTORY_SEPARATOR . 'annual.report' . DIRECTORY_SEPARATOR . 'annual.report.md', $plan['markdown_path']);
            $t->same(['PYTORCH_ENABLE_MPS_FALLBACK' => '1'], $plan['environment']);
            $t->same([
                'configure_logging',
                'parse_args',
                'parse_langs',
                'load_all_models',
                'convert_single_pdf',
                'save_markdown',
                'print_saved_folder',
                'print_total_time',
            ], $plan['preflight_order']);

            $t->same(3, $plan['options']['max_pages']);
            $t->same(1, $plan['options']['start_page']);
            $t->same(['English', ' Spanish', 'de'], $plan['options']['langs']);
            $t->same(4, $plan['options']['batch_multiplier']);
            $t->same('load_all_models', $plan['model_boundary']['load_function']);
            $t->same(true, $plan['model_boundary']['loads_all_models_before_conversion']);
            $t->same(true, $plan['model_boundary']['passes_model_list_to_convert_single_pdf']);
            $t->same(false, $plan['model_boundary']['native_plan_loads_models']);
            $t->same(true, $plan['model_boundary']['upstream_model_execution_required']);
            $t->same('convert_single_pdf', $plan['conversion_call']['function']);
            $t->same($filename, $plan['conversion_call']['receives_filename']);
            $t->same([
                'max_pages' => 3,
                'langs' => ['English', ' Spanish', 'de'],
                'batch_multiplier' => 4,
                'start_page' => 1,
            ], $plan['conversion_call']['receives_options']);
            $t->same(null, $plan['conversion_call']['metadata_argument_source']);
            $t->contains('settings.OCR_ALL_PAGES', $plan['conversion_call']['ocr_all_pages_argument_source']);

            $outputPolicy = $plan['output_policy'];
            $t->same('save_markdown', $outputPolicy['function']);
            $t->same(true, $outputPolicy['uses_basename_after_conversion']);
            $t->same(true, $outputPolicy['existing_markdown']);
            $t->same(false, $outputPolicy['skips_existing_markdown']);
            $t->same(false, $outputPolicy['min_length_preflight']);
            $t->same(false, $outputPolicy['filetype_preflight_before_model_load']);
            $t->same(true, $outputPolicy['saves_empty_output']);
            $t->same('Saved markdown to the {subfolder_path} folder', $outputPolicy['saved_folder_message']);
            $t->same('Total time: ', $outputPolicy['elapsed_message_prefix']);
            $t->same(true, $plan['review_only']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_streamlit']);
            $t->same(false, $plan['executes_fastapi']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($output);
        }
    },
    'keeps convert_single defaults distinct from batch process preflight gates' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $output = $makeTempDir();
        try {
            $plan = (new SingleDocumentConverter())->runtimePreflightPlan(
                '/wp/uploads/editor-checklist.pdf',
                $output
            );

            $t->same(['max_pages' => null, 'start_page' => null, 'langs' => null, 'batch_multiplier' => 2], $plan['options']);
            $t->same(false, $plan['output_policy']['existing_markdown']);
            $t->same(false, $plan['output_policy']['skips_existing_markdown']);
            $t->same(false, $plan['output_policy']['min_length_preflight']);
            $t->same(false, $plan['output_policy']['filetype_preflight_before_model_load']);
            $t->same(true, $plan['output_policy']['saves_empty_output']);
            $t->same(false, $plan['executes_python_or_models']);

            $single = new SingleDocumentConverter();
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $single->runtimePreflightPlan('', $output)
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $single->runtimePreflightPlan('/wp/uploads/report.pdf', '')
            );
        } finally {
            $removeTree($output);
        }
    },
    'records convert.py main runtime admission before task pool launch' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            foreach (['alpha.pdf', 'beta.pdf', 'gamma.pdf', 'omega.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% " . $filename . "\n%%EOF");
            }
            $metadataFile = $output . DIRECTORY_SEPARATOR . 'metadata.json';
            file_put_contents($metadataFile, json_encode([
                'gamma.pdf' => ['title' => 'Gamma Import', 'languages' => ['English']],
                'unselected.pdf' => ['title' => 'Unselected Import'],
            ], JSON_THROW_ON_ERROR));

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                chunkIndex: 1,
                numChunks: 2,
                maxFiles: 2,
                metadataByFilename: ['ignored.pdf' => ['title' => 'Ignored when metadata_file is present']],
                minLength: 80,
                workers: 5,
                metadataFile: $metadataFile
            );

            $t->same('markerpdf.convert_main_runtime_preflight.v1', $plan['schema']);
            $t->contains('convert.py::main', $plan['source']);
            $t->contains('os.makedirs', $plan['source']);
            $t->same([
                'configure_logging',
                'parse_args',
                'abspath_input_output',
                'list_input_files',
                'makedirs_output_exist_ok',
                'chunk_files',
                'load_metadata_file',
                'set_spawn_start_method',
                'prepare_model_handoff',
                'build_task_args',
                'pool_imap_process_single_pdf',
            ], $plan['preflight_order']);
            $t->same(['PYTORCH_ENABLE_MPS_FALLBACK' => '1', 'IN_STREAMLIT' => 'true', 'PDFTEXT_CPU_WORKERS' => '1'], $plan['environment']);
            $t->same($input, $plan['paths']['absolute_input_folder']);
            $t->same($output, $plan['paths']['absolute_output_folder']);
            $t->same(true, $plan['paths']['output_folder_exists']);
            $t->same(true, $plan['paths']['upstream_creates_output_folder']);
            $t->same(false, $plan['paths']['native_plan_creates_output_folder']);
            $t->same(false, $plan['paths']['output_folder_creation_required']);
            $t->same(4, $plan['chunking']['input_file_count']);
            $t->same(2, $plan['chunking']['chunk_size']);
            $t->same(2, $plan['chunking']['start_index']);
            $t->same(4, $plan['chunking']['end_index']);
            $t->same(2, $plan['chunking']['selected_count']);
            $t->same(['gamma.pdf', 'omega.pdf'], $plan['chunking']['selected_filenames']);
            $t->same('metadata_file json.load keyed by basename', $plan['metadata']['source']);
            $t->same($metadataFile, $plan['metadata']['metadata_file']);
            $t->same(['gamma.pdf', 'unselected.pdf'], $plan['metadata']['metadata_filenames']);
            $t->same(['gamma.pdf'], $plan['metadata']['selected_metadata_filenames']);
            $t->same(['omega.pdf'], $plan['metadata']['missing_metadata_filenames']);
            $t->same(5, $plan['worker_pool']['requested_workers']);
            $t->same(2, $plan['worker_pool']['total_processes']);
            $t->same(true, $plan['worker_pool']['pool_launchable']);
            $t->same(null, $plan['worker_pool']['pool_error_boundary']);
            $t->same('spawn', $plan['worker_pool']['start_method']);
            $t->same('process_single_pdf', $plan['worker_pool']['process_function']);
            $t->same(2, $plan['worker_pool']['task_args_count']);
            $t->same('gamma.pdf', basename($plan['worker_pool']['task_args'][0]['filepath']));
            $t->same($output, $plan['worker_pool']['task_args'][0]['out_folder']);
            $t->same(['title' => 'Gamma Import', 'languages' => ['English']], $plan['worker_pool']['task_args'][0]['metadata']);
            $t->same(null, $plan['worker_pool']['task_args'][1]['metadata']);
            $t->same(80, $plan['worker_pool']['task_args'][1]['min_length']);
            $t->same('tqdm(pool.imap(process_single_pdf, task_args), total=len(task_args), desc="Processing PDFs", unit="pdf")', $plan['worker_pool']['progress_iterator']);
            $t->same('process_single_pdf', $plan['conversion_boundary']['per_file_preflight_function']);
            $t->same('convert_single_pdf', $plan['conversion_boundary']['converter_function']);
            $t->same('metadata.get(os.path.basename(f))', $plan['conversion_boundary']['metadata_lookup']);
            $t->same('print_empty_file_without_save_markdown', $plan['conversion_boundary']['empty_output_policy']);
            $t->same(true, $plan['review_only']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
    'flags empty convert.py chunks and invalid workers before pool launch' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        try {
            foreach (['one.pdf', 'two.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% " . $filename . "\n%%EOF");
            }
            $missingOutput = $input . DIRECTORY_SEPARATOR . 'missing-output';
            $batch = new BatchConverter();
            $emptyChunk = $batch->runtimeMainPreflightPlan(
                $input,
                $missingOutput,
                chunkIndex: 3,
                numChunks: 2,
                workers: 4
            );
            $invalidWorkers = $batch->runtimeMainPreflightPlan(
                $input,
                $missingOutput,
                workers: 0
            );

            $t->same(false, $emptyChunk['paths']['output_folder_exists']);
            $t->same(true, $emptyChunk['paths']['output_folder_creation_required']);
            $t->same(false, is_dir($missingOutput));
            $t->same(2, $emptyChunk['chunking']['input_file_count']);
            $t->same(1, $emptyChunk['chunking']['chunk_size']);
            $t->same(3, $emptyChunk['chunking']['start_index']);
            $t->same(0, $emptyChunk['chunking']['selected_count']);
            $t->same([], $emptyChunk['chunking']['selected_filenames']);
            $t->same(0, $emptyChunk['worker_pool']['total_processes']);
            $t->same(false, $emptyChunk['worker_pool']['pool_launchable']);
            $t->same('empty-task-queue', $emptyChunk['worker_pool']['pool_error_boundary']);
            $t->same(0, $emptyChunk['worker_pool']['task_args_count']);
            $t->same([], $emptyChunk['metadata']['selected_metadata_filenames']);
            $t->same(false, $emptyChunk['executes_multiprocessing']);
            $t->same(0, $invalidWorkers['worker_pool']['requested_workers']);
            $t->same(0, $invalidWorkers['worker_pool']['total_processes']);
            $t->same(false, $invalidWorkers['worker_pool']['pool_launchable']);
            $t->same('invalid-worker-count', $invalidWorkers['worker_pool']['pool_error_boundary']);
            $t->same(2, $invalidWorkers['worker_pool']['task_args_count']);
            $t->same(false, $invalidWorkers['executes_python_or_models']);
            $t->same(false, $invalidWorkers['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
        }
    },
];
