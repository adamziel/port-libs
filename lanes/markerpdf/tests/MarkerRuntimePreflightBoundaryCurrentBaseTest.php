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
                'print_conversion_summary',
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
            $t->same(true, $plan['console_summary']['summary_reached']);
            $t->same(
                'Converting 2 pdfs in chunk 2/2 with 2 processes, and storing in ' . $output,
                $plan['console_summary']['message_line']
            );
            $t->same('after_model_handoff_before_task_args', $plan['console_summary']['emission_order']);
            $t->same(true, $plan['review_only']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
    'records convert.py model handoff branch before summary task args and pool launch' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        $blockedRoot = $makeTempDir();
        try {
            foreach (['alpha.pdf', 'beta.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% " . $filename . "\n%%EOF");
            }

            $batch = new BatchConverter();
            $cuda = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 4,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );
            $mps = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 4,
                torchDevice: 'cpu',
                torchDeviceModel: 'mps'
            );
            $blockedOutput = $blockedRoot . DIRECTORY_SEPARATOR . 'marker-output';
            file_put_contents($blockedOutput, 'not a directory');
            $blocked = $batch->runtimeMainPreflightPlan(
                $input,
                $blockedOutput,
                workers: 4,
                torchDevice: 'mps',
                torchDeviceModel: 'mps'
            );

            $handoff = $cuda['model_handoff'];
            $t->same('convert.py settings.TORCH_DEVICE model handoff', $handoff['source']);
            $t->same(true, $handoff['model_handoff_reached']);
            $t->same('after_spawn_start_method_before_conversion_summary', $handoff['order']);
            $t->same('cuda', $handoff['torch_device']);
            $t->same('cpu', $handoff['torch_device_model']);
            $t->same(false, $handoff['uses_mps_no_shared_memory_branch']);
            $t->same(true, $handoff['main_load_all_models']);
            $t->same(true, $handoff['share_memory_before_pool']);
            $t->same(true, $handoff['model_share_memory_loop_reached']);
            $t->same('model_lst', $handoff['worker_init_argument']);
            $t->same(false, $handoff['worker_loads_models_when_init_arg_null']);
            $t->same(null, $handoff['warning']);
            $t->same(true, $handoff['upstream_model_execution_required']);
            $t->same(false, $handoff['native_plan_loads_models']);
            $t->same(false, $handoff['executes_python_or_models']);
            $t->same('after_model_handoff_before_task_args', $cuda['console_summary']['emission_order']);
            $t->same(2, $cuda['worker_pool']['total_processes']);

            $mpsHandoff = $mps['model_handoff'];
            $t->same(true, $mpsHandoff['model_handoff_reached']);
            $t->same(true, $mpsHandoff['uses_mps_no_shared_memory_branch']);
            $t->same(false, $mpsHandoff['main_load_all_models']);
            $t->same(false, $mpsHandoff['share_memory_before_pool']);
            $t->same(false, $mpsHandoff['model_share_memory_loop_reached']);
            $t->same(null, $mpsHandoff['worker_init_argument']);
            $t->same(true, $mpsHandoff['worker_loads_models_when_init_arg_null']);
            $t->contains('Cannot use MPS with torch multiprocessing share_memory', (string) $mpsHandoff['warning']);
            $t->same(true, $mps['console_summary']['summary_reached']);
            $t->same(false, $mps['executes_python_or_models']);

            $blockedHandoff = $blocked['model_handoff'];
            $t->same(false, $blockedHandoff['model_handoff_reached']);
            $t->same('output-folder-create-failed', $blockedHandoff['blocked_by']);
            $t->same(false, $blockedHandoff['upstream_model_execution_required']);
            $t->same(false, $blockedHandoff['main_load_all_models']);
            $t->same(false, $blockedHandoff['executes_python_or_models']);
            $t->same(false, $blocked['console_summary']['summary_reached']);
        } finally {
            $removeTree($input);
            $removeTree($output);
            $removeTree($blockedRoot);
        }
    },
    'records repeated spawn start method failure after metadata and before model handoff' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            foreach (['first.pdf', 'second.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% " . $filename . "\n%%EOF");
            }
            $metadataFile = $output . DIRECTORY_SEPARATOR . 'metadata.json';
            file_put_contents($metadataFile, json_encode([
                'first.pdf' => ['title' => 'First Import'],
            ], JSON_THROW_ON_ERROR));

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 5,
                metadataFile: $metadataFile,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu',
                spawnStartMethodAlreadySet: true
            );

            $t->same(['first.pdf', 'second.pdf'], $plan['chunking']['selected_filenames']);
            $t->same(true, $plan['metadata']['metadata_load_reached']);
            $t->same(true, $plan['metadata']['metadata_load_success']);
            $t->same(['first.pdf'], $plan['metadata']['metadata_filenames']);
            $t->same(['first.pdf'], $plan['metadata']['selected_metadata_filenames']);
            $t->same(['second.pdf'], $plan['metadata']['missing_metadata_filenames']);

            $spawn = $plan['spawn_start_method'];
            $t->same('convert.py torch.multiprocessing set_start_method boundary', $spawn['source']);
            $t->same('after_metadata_before_model_handoff', $spawn['order']);
            $t->same('spawn', $spawn['requested_start_method']);
            $t->same(true, $spawn['start_method_reached']);
            $t->same(false, $spawn['start_method_success']);
            $t->same(true, $spawn['start_method_already_set']);
            $t->same(2, $spawn['total_processes_computed_before_spawn']);
            $t->same('spawn-start-method-failed', $spawn['error_boundary']);
            $t->same('RuntimeError', $spawn['error_class']);
            $t->contains('Set start method to spawn twice', (string) $spawn['error_message']);
            $t->same(true, $spawn['blocks_model_handoff']);
            $t->same(true, $spawn['blocks_conversion_summary']);
            $t->same(true, $spawn['blocks_task_args']);
            $t->same(true, $spawn['blocks_pool_launch']);
            $t->same(false, $spawn['executes_multiprocessing']);

            $t->same(false, $plan['model_handoff']['model_handoff_reached']);
            $t->same('spawn-start-method-failed', $plan['model_handoff']['blocked_by']);
            $t->same(false, $plan['model_handoff']['main_load_all_models']);
            $t->same(false, $plan['model_handoff']['upstream_model_execution_required']);
            $t->same(false, $plan['model_handoff']['executes_python_or_models']);
            $t->same(0, $plan['worker_pool']['task_args_count']);
            $t->same([], $plan['worker_pool']['task_args']);
            $t->same(0, $plan['worker_pool']['total_processes']);
            $t->same(false, $plan['worker_pool']['pool_launchable']);
            $t->same('spawn-start-method-failed', $plan['worker_pool']['pool_error_boundary']);
            $t->same(false, $plan['console_summary']['summary_reached']);
            $t->same('spawn-start-method-failed', $plan['console_summary']['blocked_by']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
    'keeps convert.py input and chunk boundary errors ahead of metadata file loading' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            file_put_contents($input . DIRECTORY_SEPARATOR . 'queued.pdf', "%PDF-1.4\n% queued pdf\n%%EOF");
            $missingInput = $input . DIRECTORY_SEPARATOR . 'missing-input';
            $missingMetadata = $input . DIRECTORY_SEPARATOR . 'missing-metadata.json';
            $batch = new BatchConverter();

            $captureMessage = static function (callable $callback): string {
                try {
                    $callback();
                } catch (InvalidArgumentException $exception) {
                    return $exception->getMessage();
                }

                return '';
            };

            $missingInputMessage = $captureMessage(
                static fn (): array => $batch->runtimeMainPreflightPlan($missingInput, $output, metadataFile: $missingMetadata)
            );
            $invalidChunkMessage = $captureMessage(
                static fn (): array => $batch->runtimeMainPreflightPlan($input, $output, numChunks: 0, metadataFile: $missingMetadata)
            );
            $missingMetadataMessage = $captureMessage(
                static fn (): array => $batch->runtimeMainPreflightPlan($input, $output, metadataFile: $missingMetadata)
            );

            $t->contains('Batch input folder does not exist', $missingInputMessage);
            $t->same(false, str_contains($missingInputMessage, 'metadata file'));
            $t->contains('Batch chunk count must be at least one', $invalidChunkMessage);
            $t->same(false, str_contains($invalidChunkMessage, 'metadata file'));
            $t->contains('Batch metadata file is not readable', $missingMetadataMessage);
            $t->same(false, $batch->runtimeMainPreflightPlan($input, $output)['executes_python_or_models']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
    'matches convert.py integer truthiness for max and min_length gates' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            foreach (['alpha.pdf', 'beta.pdf', 'gamma.pdf', 'omega.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% " . $filename . "\n%%EOF");
            }
            file_put_contents($input . DIRECTORY_SEPARATOR . 'archive.pdf', "PK\x03\x04not really a pdf");

            $batch = new BatchConverter();
            $zeroMax = $batch->runtimeMainPreflightPlan($input, $output, maxFiles: 0);
            $negativeMax = $batch->runtimeMainPreflightPlan($input, $output, maxFiles: -1);
            $zeroMinLength = $batch->processFilePreflightPlan(
                $input . DIRECTORY_SEPARATOR . 'archive.pdf',
                $output,
                null,
                0,
                static fn (): int => 0
            );
            $negativeMinLengthSpoof = $batch->processFilePreflightPlan(
                $input . DIRECTORY_SEPARATOR . 'archive.pdf',
                $output,
                null,
                -1,
                static fn (): int => 0
            );
            $negativeMinLengthPdf = $batch->processFilePreflightPlan(
                $input . DIRECTORY_SEPARATOR . 'alpha.pdf',
                $output,
                ['title' => 'Alpha'],
                -1,
                static fn (): int => 0
            );

            $t->same(false, $zeroMax['chunking']['max_files_limit_active']);
            $t->same(5, $zeroMax['chunking']['selected_count']);
            $t->same(['alpha.pdf', 'archive.pdf', 'beta.pdf', 'gamma.pdf', 'omega.pdf'], $zeroMax['chunking']['selected_filenames']);
            $t->same(true, $negativeMax['chunking']['max_files_limit_active']);
            $t->same(4, $negativeMax['chunking']['selected_count']);
            $t->same(['alpha.pdf', 'archive.pdf', 'beta.pdf', 'gamma.pdf'], $negativeMax['chunking']['selected_filenames']);

            $t->same(false, $zeroMinLength['min_length_gate_active']);
            $t->same(false, $zeroMinLength['filetype_checked']);
            $t->same('ready-for-conversion', $zeroMinLength['status']);
            $t->same(true, $negativeMinLengthSpoof['min_length_gate_active']);
            $t->same(true, $negativeMinLengthSpoof['filetype_checked']);
            $t->same('other', $negativeMinLengthSpoof['filetype']);
            $t->same(false, $negativeMinLengthSpoof['text_length_checked']);
            $t->same('skipped-unsupported-filetype', $negativeMinLengthSpoof['status']);
            $t->same(true, $negativeMinLengthPdf['min_length_gate_active']);
            $t->same(true, $negativeMinLengthPdf['filetype_checked']);
            $t->same(true, $negativeMinLengthPdf['text_length_checked']);
            $t->same(0, $negativeMinLengthPdf['text_length']);
            $t->same('ready-for-conversion', $negativeMinLengthPdf['status']);
            $t->same(false, $negativeMinLengthPdf['executes_python_or_models']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
    'matches convert.py negative chunk index slicing before metadata and worker launch' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            foreach (['alpha.pdf', 'archive.pdf', 'beta.pdf', 'gamma.pdf', 'omega.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% " . $filename . "\n%%EOF");
            }
            $metadataFile = $output . DIRECTORY_SEPARATOR . 'metadata.json';
            file_put_contents($metadataFile, json_encode([
                'alpha.pdf' => ['title' => 'Alpha Import'],
                'beta.pdf' => ['title' => 'Beta Import'],
                'omega.pdf' => ['title' => 'Omega Import'],
            ], JSON_THROW_ON_ERROR));

            $batch = new BatchConverter();
            $negativeHead = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                chunkIndex: -2,
                numChunks: 2,
                workers: 4,
                metadataFile: $metadataFile
            );
            $negativeEmpty = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                chunkIndex: -1,
                numChunks: 2,
                workers: 4,
                metadataFile: $metadataFile
            );

            $t->same(['alpha.pdf', 'archive.pdf'], $negativeHead['chunking']['selected_filenames']);
            $t->same(true, $negativeHead['chunking']['negative_chunk_index_active']);
            $t->same(3, $negativeHead['chunking']['chunk_size']);
            $t->same(-6, $negativeHead['chunking']['start_index']);
            $t->same(-3, $negativeHead['chunking']['end_index']);
            $t->same(0, $negativeHead['chunking']['python_slice_start_index']);
            $t->same(2, $negativeHead['chunking']['python_slice_end_index']);
            $t->same(2, $negativeHead['worker_pool']['task_args_count']);
            $t->same(2, $negativeHead['worker_pool']['total_processes']);
            $t->same(true, $negativeHead['worker_pool']['pool_launchable']);
            $t->same(['alpha.pdf'], $negativeHead['metadata']['selected_metadata_filenames']);
            $t->same(['archive.pdf'], $negativeHead['metadata']['missing_metadata_filenames']);
            $t->same(false, $negativeHead['executes_python_or_models']);

            $t->same([], $negativeEmpty['chunking']['selected_filenames']);
            $t->same(-3, $negativeEmpty['chunking']['start_index']);
            $t->same(0, $negativeEmpty['chunking']['end_index']);
            $t->same(2, $negativeEmpty['chunking']['python_slice_start_index']);
            $t->same(0, $negativeEmpty['chunking']['python_slice_end_index']);
            $t->same(0, $negativeEmpty['worker_pool']['task_args_count']);
            $t->same(0, $negativeEmpty['worker_pool']['total_processes']);
            $t->same(false, $negativeEmpty['worker_pool']['pool_launchable']);
            $t->same('empty-task-queue', $negativeEmpty['worker_pool']['pool_error_boundary']);
            $t->same(false, $negativeEmpty['executes_multiprocessing']);
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
    'records output-folder file conflicts before metadata and worker launch' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $root = $makeTempDir();
        try {
            foreach (['queued.pdf', 'sidecar.txt'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% " . $filename . "\n%%EOF");
            }
            $blockedOutput = $root . DIRECTORY_SEPARATOR . 'marker-output';
            $missingMetadata = $root . DIRECTORY_SEPARATOR . 'missing-metadata.json';
            file_put_contents($blockedOutput, 'not a directory');

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $blockedOutput,
                workers: 4,
                metadataFile: $missingMetadata
            );

            $t->same('os.makedirs(out_folder, exist_ok=True)', $plan['paths']['output_folder_creation_call']);
            $t->same('after_list_input_files_before_chunk_files', $plan['paths']['output_folder_creation_order']);
            $t->same(true, $plan['paths']['output_path_exists']);
            $t->same('file', $plan['paths']['output_path_type']);
            $t->same(false, $plan['paths']['output_folder_exists']);
            $t->same(true, $plan['paths']['output_folder_creation_required']);
            $t->same(true, $plan['paths']['output_folder_creation_blocked']);
            $t->same('FileExistsError', $plan['paths']['output_folder_creation_error_class']);
            $t->contains('File exists', (string) $plan['paths']['output_folder_creation_error_message']);
            $t->same('queued.pdf', $plan['input_listing']['file_basenames'][0]);
            $t->same('sidecar.txt', $plan['input_listing']['file_basenames'][1]);
            $t->same('output-folder-create-failed', $plan['chunking']['chunk_error_boundary']);
            $t->same(false, $plan['chunking']['chunking_reached']);
            $t->same(0, $plan['chunking']['selected_count']);
            $t->same([], $plan['chunking']['selected_filenames']);
            $t->same(false, $plan['metadata']['metadata_load_reached']);
            $t->same($missingMetadata, $plan['metadata']['metadata_file']);
            $t->same([], $plan['metadata']['metadata_filenames']);
            $t->same(0, $plan['worker_pool']['task_args_count']);
            $t->same(0, $plan['worker_pool']['total_processes']);
            $t->same(false, $plan['worker_pool']['pool_launchable']);
            $t->same('output-folder-create-failed', $plan['worker_pool']['pool_error_boundary']);
            $t->same(false, $plan['console_summary']['summary_reached']);
            $t->same(null, $plan['console_summary']['message_line']);
            $t->same('output-folder-create-failed', $plan['console_summary']['blocked_by']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
        } finally {
            $removeTree($input);
            $removeTree($root);
        }
    },
    'records malformed metadata json before model handoff and worker launch' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            foreach (['alpha.pdf', 'beta.pdf', 'notes.txt'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% " . $filename . "\n%%EOF");
            }
            $metadataFile = $output . DIRECTORY_SEPARATOR . 'metadata.json';
            file_put_contents($metadataFile, '{"alpha.pdf": {"title": "Alpha Import",}');

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 5,
                metadataFile: $metadataFile
            );

            $t->same(true, $plan['chunking']['chunking_reached']);
            $t->same(null, $plan['chunking']['chunk_error_boundary']);
            $t->same(3, $plan['chunking']['selected_count']);
            $t->same(['alpha.pdf', 'beta.pdf', 'notes.txt'], $plan['chunking']['selected_filenames']);
            $t->same(['notes.txt'], $plan['input_listing']['selected_non_pdf_filenames']);
            $t->same('metadata_file json.load keyed by basename', $plan['metadata']['source']);
            $t->same($metadataFile, $plan['metadata']['metadata_file']);
            $t->same(true, $plan['metadata']['metadata_load_reached']);
            $t->same(false, $plan['metadata']['metadata_load_success']);
            $t->same('metadata-file-json-load-failed', $plan['metadata']['metadata_error_boundary']);
            $t->same('JSONDecodeError', $plan['metadata']['metadata_error_class']);
            $t->contains('valid JSON', $plan['metadata']['metadata_error_message']);
            $t->same([], $plan['metadata']['metadata_filenames']);
            $t->same([], $plan['metadata']['selected_metadata_filenames']);
            $t->same([], $plan['metadata']['missing_metadata_filenames']);
            $t->same(5, $plan['worker_pool']['requested_workers']);
            $t->same(0, $plan['worker_pool']['task_args_count']);
            $t->same([], $plan['worker_pool']['task_args']);
            $t->same(0, $plan['worker_pool']['total_processes']);
            $t->same(false, $plan['worker_pool']['pool_launchable']);
            $t->same('metadata-file-json-load-failed', $plan['worker_pool']['pool_error_boundary']);
            $t->same(false, $plan['console_summary']['summary_reached']);
            $t->same(null, $plan['console_summary']['message_line']);
            $t->same('metadata-file-json-load-failed', $plan['console_summary']['blocked_by']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
    'records metadata json shape errors at task-args boundary after summary' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            foreach (['alpha.pdf', 'beta.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% " . $filename . "\n%%EOF");
            }
            $metadataFile = $output . DIRECTORY_SEPARATOR . 'metadata-list.json';
            file_put_contents($metadataFile, json_encode([
                ['title' => 'List-shaped metadata cannot answer get()'],
            ], JSON_THROW_ON_ERROR));

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 5,
                metadataFile: $metadataFile,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $t->same(['alpha.pdf', 'beta.pdf'], $plan['chunking']['selected_filenames']);
            $t->same(true, $plan['metadata']['metadata_load_reached']);
            $t->same(true, $plan['metadata']['metadata_load_success']);
            $t->same(null, $plan['metadata']['metadata_error_boundary']);
            $t->same('list', $plan['metadata']['metadata_json_type']);
            $t->same(false, $plan['metadata']['metadata_get_available']);
            $t->same('metadata-get-failed', $plan['metadata']['metadata_shape_error_boundary']);
            $t->same('AttributeError', $plan['metadata']['metadata_shape_error_class']);
            $t->contains("object has no attribute 'get'", (string) $plan['metadata']['metadata_shape_error_message']);
            $t->same([], $plan['metadata']['metadata_filenames']);
            $t->same([], $plan['metadata']['selected_metadata_filenames']);
            $t->same(['alpha.pdf', 'beta.pdf'], $plan['metadata']['missing_metadata_filenames']);

            $t->same(true, $plan['spawn_start_method']['start_method_success']);
            $t->same(true, $plan['model_handoff']['model_handoff_reached']);
            $t->same(true, $plan['model_handoff']['main_load_all_models']);
            $t->same(false, $plan['model_handoff']['executes_python_or_models']);
            $t->same(true, $plan['console_summary']['summary_reached']);
            $t->same(2, $plan['console_summary']['selected_pdf_count']);
            $t->same(
                'Converting 2 pdfs in chunk 1/1 with 2 processes, and storing in ' . $output,
                $plan['console_summary']['message_line']
            );
            $t->same(true, $plan['console_summary']['emitted_before_task_args']);
            $t->same(0, $plan['worker_pool']['task_args_count']);
            $t->same([], $plan['worker_pool']['task_args']);
            $t->same(0, $plan['worker_pool']['total_processes']);
            $t->same(false, $plan['worker_pool']['pool_launchable']);
            $t->same('metadata-get-failed', $plan['worker_pool']['pool_error_boundary']);
            $t->same('metadata-get-failed', $plan['worker_pool']['task_args_error_boundary']);
            $t->same('AttributeError', $plan['worker_pool']['task_args_error_class']);
            $t->same('metadata-get-failed', $plan['conversion_boundary']['metadata_lookup_error_boundary']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
    'records convert.py os.listdir file-only boundary without extension filtering' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            foreach (['alpha.pdf', 'metadata.json', 'omega.PDF', 'wp-upload.txt'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% " . $filename . "\n%%EOF");
            }
            mkdir($input . DIRECTORY_SEPARATOR . 'nested.pdf');
            mkdir($input . DIRECTORY_SEPARATOR . 'preview-assets');

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 8
            );

            $listing = $plan['input_listing'];
            $t->same('os.listdir + os.path.isfile', $listing['source']);
            $t->same(['alpha.pdf', 'metadata.json', 'nested.pdf', 'omega.PDF', 'preview-assets', 'wp-upload.txt'], $listing['entry_basenames']);
            $t->same(6, $listing['entry_count']);
            $t->same(['alpha.pdf', 'metadata.json', 'omega.PDF', 'wp-upload.txt'], $listing['file_basenames']);
            $t->same(4, $listing['file_count']);
            $t->same(['nested.pdf', 'preview-assets'], $listing['skipped_non_file_basenames']);
            $t->same(2, $listing['skipped_non_file_count']);
            $t->same('os.path.isfile', $listing['file_filter']);
            $t->same(false, $listing['extension_filter_active']);
            $t->same(true, $listing['non_pdf_files_are_task_candidates']);
            $t->same(['metadata.json', 'wp-upload.txt'], $listing['non_pdf_file_basenames']);
            $t->same(['metadata.json', 'wp-upload.txt'], $listing['selected_non_pdf_filenames']);
            $t->same(['alpha.pdf', 'metadata.json', 'omega.PDF', 'wp-upload.txt'], $plan['chunking']['selected_filenames']);
            $t->same(4, $plan['worker_pool']['task_args_count']);
            $t->same(4, $plan['worker_pool']['total_processes']);
            $t->same(
                'Converting 4 pdfs in chunk 1/1 with 4 processes, and storing in ' . $output,
                $plan['console_summary']['message_line']
            );
            $t->same(false, $plan['executes_python_or_models']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
    'records process_single_pdf return-value boundary before converter launch' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            foreach (['existing.pdf', 'short.pdf', 'ready.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% " . $filename . "\n%%EOF");
            }
            file_put_contents($input . DIRECTORY_SEPARATOR . 'archive.pdf', "PK\x03\x04not really a pdf");

            (new OutputWriter())->saveMarkdown(
                $output,
                'existing.pdf',
                '<!-- wp:paragraph --><p>Already imported.</p><!-- /wp:paragraph -->',
                [],
                ['title' => 'Already Imported']
            );

            $batch = new BatchConverter();
            $textLength = static fn (string $filepath): int => basename($filepath) === 'short.pdf' ? 12 : 180;

            $existing = $batch->processFilePreflightPlan($input . DIRECTORY_SEPARATOR . 'existing.pdf', $output, null, 80, $textLength);
            $archive = $batch->processFilePreflightPlan($input . DIRECTORY_SEPARATOR . 'archive.pdf', $output, null, 80, $textLength);
            $short = $batch->processFilePreflightPlan($input . DIRECTORY_SEPARATOR . 'short.pdf', $output, null, 80, $textLength);
            $ready = $batch->processFilePreflightPlan($input . DIRECTORY_SEPARATOR . 'ready.pdf', $output, ['title' => 'Ready Import'], 80, $textLength);

            $t->same('skipped-existing', $existing['status']);
            $t->same(null, $existing['upstream_return_value']);
            $t->same('python-none', $existing['upstream_return_type']);
            $t->same('markdown_exists-return-none', $existing['upstream_return_boundary']);

            $t->same('skipped-unsupported-filetype', $archive['status']);
            $t->same(0, $archive['upstream_return_value']);
            $t->same('int', $archive['upstream_return_type']);
            $t->same('unsupported-filetype-return-zero', $archive['upstream_return_boundary']);

            $t->same('skipped-short-text', $short['status']);
            $t->same(null, $short['upstream_return_value']);
            $t->same('python-none', $short['upstream_return_type']);
            $t->same('short-text-return-none', $short['upstream_return_boundary']);

            $t->same('ready-for-conversion', $ready['status']);
            $t->same(null, $ready['upstream_return_value']);
            $t->same('python-none', $ready['upstream_return_type']);
            $t->same('conversion-or-empty-output-return-none', $ready['upstream_return_boundary']);
            $t->same(false, $ready['executes_python_or_models']);
            $t->same(false, $ready['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
    'records convert.py conversion summary stdout before task args and pool launch' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            foreach (['alpha.pdf', 'beta.pdf', 'gamma.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% " . $filename . "\n%%EOF");
            }

            $batch = new BatchConverter();
            $plan = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                chunkIndex: 1,
                numChunks: 2,
                workers: 5
            );
            $emptyPlan = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                chunkIndex: 4,
                numChunks: 2,
                workers: 5
            );
            $invalidWorkers = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 0
            );

            $summary = $plan['console_summary'];
            $t->same('convert.py stdout conversion summary', $summary['source']);
            $t->same(true, $summary['summary_reached']);
            $t->same(1, $summary['selected_pdf_count']);
            $t->same(2, $summary['chunk_display_index']);
            $t->same(2, $summary['num_chunks']);
            $t->same(1, $summary['total_processes']);
            $t->same($output, $summary['out_folder']);
            $t->same(
                'Converting 1 pdfs in chunk 2/2 with 1 processes, and storing in ' . $output,
                $summary['message_line']
            );
            $t->same('after_model_handoff_before_task_args', $summary['emission_order']);
            $t->same(true, $summary['emitted_before_task_args']);
            $t->same(true, $summary['emitted_before_pool_launch']);
            $t->same(null, $summary['blocked_by']);

            $t->same(true, $emptyPlan['console_summary']['summary_reached']);
            $t->same(0, $emptyPlan['console_summary']['selected_pdf_count']);
            $t->same(0, $emptyPlan['console_summary']['total_processes']);
            $t->same(
                'Converting 0 pdfs in chunk 5/2 with 0 processes, and storing in ' . $output,
                $emptyPlan['console_summary']['message_line']
            );
            $t->same('empty-task-queue', $emptyPlan['worker_pool']['pool_error_boundary']);
            $t->same(true, $emptyPlan['console_summary']['emitted_before_pool_launch']);

            $t->same(0, $invalidWorkers['console_summary']['total_processes']);
            $t->same(
                'Converting 3 pdfs in chunk 1/1 with 0 processes, and storing in ' . $output,
                $invalidWorkers['console_summary']['message_line']
            );
            $t->same('invalid-worker-count', $invalidWorkers['worker_pool']['pool_error_boundary']);
            $t->same(false, $invalidWorkers['executes_multiprocessing']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
