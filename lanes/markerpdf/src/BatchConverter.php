<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use JsonException;
use stdClass;
use Throwable;

final class BatchConverter
{
    private OutputWriter $writer;
    private FiletypeDetector $filetypeDetector;
    private PdfTextExtractor $textExtractor;

    public function __construct(
        ?OutputWriter $writer = null,
        ?FiletypeDetector $filetypeDetector = null,
        ?PdfTextExtractor $textExtractor = null
    ) {
        $this->writer = $writer ?? new OutputWriter();
        $this->filetypeDetector = $filetypeDetector ?? new FiletypeDetector();
        $this->textExtractor = $textExtractor ?? new PdfTextExtractor();
    }

    /**
     * Native boundary for top-level convert.py task construction.
     *
     * @param array<string, array<string, mixed>> $metadataByFilename
     * @return list<array{filepath: string, out_folder: string, metadata: array<string, mixed>|null, min_length: int|null}>
     */
    public function planTasks(
        string $inputFolder,
        string $outputFolder,
        int $chunkIndex = 0,
        int $numChunks = 1,
        ?int $maxFiles = null,
        array $metadataByFilename = [],
        ?int $minLength = null
    ): array {
        $selectedFiles = $this->chunkFiles($this->inputFiles($inputFolder), $chunkIndex, $numChunks, $maxFiles);

        return $this->tasksForFiles($selectedFiles, $outputFolder, $metadataByFilename, $minLength);
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    public function chunkFiles(array $files, int $chunkIndex = 0, int $numChunks = 1, ?int $maxFiles = null): array
    {
        if ($numChunks < 1) {
            throw new InvalidArgumentException('Batch chunk count must be at least one.');
        }

        $files = array_values($files);
        $chunkSize = (int) ceil(count($files) / $numChunks);
        if ($chunkSize === 0) {
            return [];
        }

        $startIndex = $chunkIndex * $chunkSize;
        $selected = $this->pythonSlice($files, $startIndex, $startIndex + $chunkSize);
        if ($this->pythonTruthyInteger($maxFiles)) {
            $selected = array_slice($selected, 0, (int) $maxFiles);
        }

        return array_values($selected);
    }

    /**
     * @param array{filepath: string, out_folder: string, metadata?: array<string, mixed>|null, min_length?: int|null} $task
     * @return array<string, mixed>
     */
    public function processTask(array $task, callable $converter, ?callable $textLength = null): array
    {
        return $this->processFile(
            $task['filepath'],
            $task['out_folder'],
            $task['metadata'] ?? null,
            $task['min_length'] ?? null,
            $converter,
            $textLength
        );
    }

    /**
     * @param array<string, array<string, mixed>> $metadataByFilename
     * @return array{tasks: list<array<string, mixed>>, results: list<array<string, mixed>>, converted: int, skipped: int, errors: int, progress: array<string, mixed>}
     */
    public function processFolder(
        string $inputFolder,
        string $outputFolder,
        callable $converter,
        int $chunkIndex = 0,
        int $numChunks = 1,
        ?int $maxFiles = null,
        array $metadataByFilename = [],
        ?int $minLength = null,
        ?callable $textLength = null,
        ?callable $progressCallback = null
    ): array {
        $tasks = $this->planTasks(
            $inputFolder,
            $outputFolder,
            $chunkIndex,
            $numChunks,
            $maxFiles,
            $metadataByFilename,
            $minLength
        );

        $results = [];
        $total = count($tasks);
        foreach ($tasks as $index => $task) {
            $result = $this->processTask($task, $converter, $textLength);
            $results[] = $result;
            if ($progressCallback !== null) {
                $progressCallback($this->progressEvent($index + 1, $total, $task, $result, $results));
            }
        }

        return $this->folderSummary($tasks, $results);
    }

    /**
     * Native boundary for convert.py's tqdm progress and markdown_exists resume behavior.
     *
     * Upstream convert.py builds all task tuples, wraps pool.imap(process_single_pdf, task_args)
     * in tqdm(total=len(task_args), desc="Processing PDFs", unit="pdf"), and lets
     * process_single_pdf return early when marker.output::markdown_exists is true.
     * This method records the same queue/progress/resume decisions without launching
     * Python, Torch multiprocessing, pdftext, pypdfium, models, or external PDF tools.
     *
     * @param array<string, array<string, mixed>> $metadataByFilename
     * @return array{tasks: list<array<string, mixed>>, task_args: list<array<string, mixed>>, progress: array<string, mixed>, resume: array<string, mixed>, executes_python_or_models: false, executes_multiprocessing: false, executes_external_pdf_tools: false}
     */
    public function batchProgressResumePlan(
        string $inputFolder,
        string $outputFolder,
        int $chunkIndex = 0,
        int $numChunks = 1,
        ?int $maxFiles = null,
        array $metadataByFilename = [],
        ?int $minLength = null
    ): array {
        $tasks = $this->planTasks(
            $inputFolder,
            $outputFolder,
            $chunkIndex,
            $numChunks,
            $maxFiles,
            $metadataByFilename,
            $minLength
        );

        $taskArgs = [];
        $statusByFilename = [];
        $pendingTaskArgs = [];
        $pendingFilenames = [];
        $skippedExistingFilenames = [];

        foreach ($tasks as $task) {
            $taskArg = $this->taskArg($task);
            $taskArgs[] = $taskArg;

            $filename = basename($taskArg['filepath']);
            if ($this->writer->markdownExists($taskArg['out_folder'], $filename)) {
                $statusByFilename[$filename] = 'skipped-existing';
                $skippedExistingFilenames[] = $filename;
                continue;
            }

            $statusByFilename[$filename] = 'pending';
            $pendingFilenames[] = $filename;
            $pendingTaskArgs[] = $taskArg;
        }

        $total = count($tasks);
        $initialCompleted = count($skippedExistingFilenames);

        return [
            'tasks' => $tasks,
            'task_args' => $taskArgs,
            'progress' => [
                'description' => 'Processing PDFs',
                'unit' => 'pdf',
                'iterator' => $this->progressIterator(),
                'total' => $total,
                'initial_completed' => $initialCompleted,
                'completed' => $initialCompleted,
                'pending' => count($pendingTaskArgs),
                'percent_complete' => $this->percentComplete($initialCompleted, $total),
            ],
            'resume' => [
                'strategy' => 'markdown_exists',
                'skips_existing_markdown' => true,
                'status_by_filename' => $statusByFilename,
                'skipped_existing_filenames' => $skippedExistingFilenames,
                'pending_filenames' => $pendingFilenames,
                'pending_task_args' => $pendingTaskArgs,
            ],
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * Native no-execution boundary for convert.py::main argparse admission.
     *
     * Upstream parses CLI arguments before normalizing paths, listing input
     * files, creating output folders, loading metadata, setting the torch
     * multiprocessing start method, or touching models. This review method
     * mirrors that first boundary without launching Python or reading files.
     *
     * @param list<string|int|float|bool> $argv Arguments after the script name.
     * @return array<string, mixed>
     */
    public function runtimeMainArgumentPreflightPlan(array $argv): array
    {
        $tokens = $this->normalizeRuntimeArgv($argv);
        $options = [
            'chunk_idx' => 0,
            'num_chunks' => 1,
            'max' => null,
            'workers' => 5,
            'metadata_file' => null,
            'min_length' => null,
        ];
        $defaultsApplied = [
            'chunk_idx' => true,
            'num_chunks' => true,
            'max' => true,
            'workers' => true,
            'metadata_file' => true,
            'min_length' => true,
        ];
        $definitions = $this->runtimeMainArgparseOptionDefinitions();
        $positionals = [];

        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '--') {
                $positionals = array_merge($positionals, array_slice($tokens, $index + 1));
                break;
            }

            if (str_starts_with($token, '--')) {
                $valueProvided = false;
                $value = null;
                $optionName = $token;
                if (str_contains($token, '=')) {
                    [$optionName, $value] = explode('=', $token, 2);
                    $valueProvided = true;
                }

                $resolvedOption = $this->runtimeMainArgparseResolveOptionName($optionName, array_keys($definitions));
                if ($resolvedOption['error'] !== null) {
                    return $this->runtimeMainArgparseErrorPlan(
                        $tokens,
                        $resolvedOption['error'],
                        $optionName
                    );
                }
                $optionName = $resolvedOption['option'];

                if (!$valueProvided) {
                    $nextIndex = $index + 1;
                    if ($nextIndex >= $count || $tokens[$nextIndex] === '--' || str_starts_with($tokens[$nextIndex], '--')) {
                        return $this->runtimeMainArgparseErrorPlan(
                            $tokens,
                            'argument ' . $optionName . ': expected one argument',
                            $optionName
                        );
                    }

                    $value = $tokens[$nextIndex];
                    $index = $nextIndex;
                }

                $definition = $definitions[$optionName];
                $key = $definition['key'];
                if ($definition['type'] === 'int') {
                    $integer = $this->runtimeMainArgparseIntegerValue((string) $value);
                    if ($integer === null) {
                        return $this->runtimeMainArgparseErrorPlan(
                            $tokens,
                            "argument {$optionName}: invalid int value: '" . (string) $value . "'",
                            $optionName
                        );
                    }

                    $options[$key] = $integer;
                } else {
                    $options[$key] = (string) $value;
                }
                $defaultsApplied[$key] = false;
                continue;
            }

            if (str_starts_with($token, '-')) {
                return $this->runtimeMainArgparseErrorPlan(
                    $tokens,
                    'unrecognized arguments: ' . $token,
                    $token
                );
            }

            $positionals[] = $token;
        }

        if (count($positionals) < 2) {
            $missing = [];
            if (!array_key_exists(0, $positionals)) {
                $missing[] = 'in_folder';
            }
            if (!array_key_exists(1, $positionals)) {
                $missing[] = 'out_folder';
            }

            return $this->runtimeMainArgparseErrorPlan(
                $tokens,
                'the following arguments are required: ' . implode(', ', $missing),
                null,
                $missing
            );
        }

        if (count($positionals) > 2) {
            $extra = array_slice($positionals, 2);

            return $this->runtimeMainArgparseErrorPlan(
                $tokens,
                'unrecognized arguments: ' . implode(' ', $extra),
                $extra[0] ?? null
            );
        }

        return [
            'schema' => 'markerpdf.convert_main_argparse_preflight.v1',
            'source' => 'sddai/markerPDF convert.py::main argparse.ArgumentParser.parse_args',
            'parser' => $this->runtimeMainArgparseParserPlan(),
            'argv' => $tokens,
            'preflight_order' => [
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
            ],
            'parse_args' => [
                'source' => 'argparse.ArgumentParser.parse_args',
                'order' => 'after_configure_logging_before_abspath_input_output',
                'parse_args_reached' => true,
                'parse_args_success' => true,
                'exit_code' => 0,
                'error_boundary' => null,
                'error_class' => null,
                'error_argument' => null,
                'error_message' => null,
                'missing_required_arguments' => [],
                'filesystem_touched_before_error' => false,
                'blocks_runtime_preflight' => false,
            ],
            'arguments' => [
                'in_folder' => $positionals[0],
                'out_folder' => $positionals[1],
                'positionals' => [
                    'in_folder' => $positionals[0],
                    'out_folder' => $positionals[1],
                ],
                'options' => $options,
                'defaults_applied' => $defaultsApplied,
            ],
            'semantic_boundaries' => [
                'input_folder_exists_checked_by_argparse' => false,
                'output_folder_exists_checked_by_argparse' => false,
                'num_chunks_less_than_one_deferred_to_chunk_files' => $options['num_chunks'] < 1,
                'negative_chunk_idx_allowed_by_argparse' => $options['chunk_idx'] < 0,
                'negative_max_allowed_by_argparse' => $options['max'] !== null && $options['max'] < 0,
                'workers_less_than_one_deferred_to_pool_creation' => $options['workers'] < 1,
                'negative_min_length_allowed_by_argparse' => $options['min_length'] !== null && $options['min_length'] < 0,
                'metadata_file_read_deferred_until_after_chunk_files' => $options['metadata_file'] !== null,
            ],
            'blocked_by' => null,
            'blocked_stages' => [],
            'next_stage' => 'abspath_input_output',
            'review_only' => true,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * Native no-execution boundary for convert.py::main runtime admission.
     *
     * Upstream normalizes input/output folders with os.path.abspath(), creates
     * the output folder with exist_ok=True, slices os.listdir() results into a
     * chunk, resolves optional metadata by basename, prepares the model handoff
     * branch, then builds task tuples before torch multiprocessing. This records the same
     * admission state for WordPress import review without mutating folders or
     * launching Python workers.
     *
     * @param array<string, array<string, mixed>> $metadataByFilename
     * @return array<string, mixed>
     */
    public function runtimeMainPreflightPlan(
        string $inputFolder,
        string $outputFolder,
        int $chunkIndex = 0,
        int $numChunks = 1,
        ?int $maxFiles = null,
        array $metadataByFilename = [],
        ?int $minLength = null,
        int $workers = 5,
        ?string $metadataFile = null,
        ?string $torchDevice = null,
        ?string $torchDeviceModel = null,
        bool $spawnStartMethodAlreadySet = false
    ): array {
        $absoluteInputFolder = $this->absolutePath($inputFolder);
        $absoluteOutputFolder = $this->absolutePath($outputFolder);
        $inputListing = $this->inputDirectoryListing($absoluteInputFolder);
        $inputFiles = $inputListing['file_paths'];
        $outputCreation = $this->outputFolderCreationPlan($absoluteOutputFolder);
        $absoluteMetadataFile = $metadataFile === null || $metadataFile === ''
            ? null
            : $this->absolutePath($metadataFile);

        if ($outputCreation['output_folder_creation_blocked']) {
            return [
                'schema' => 'markerpdf.convert_main_runtime_preflight.v1',
                'source' => 'sddai/markerPDF convert.py::main + os.path.abspath + os.makedirs(exist_ok=True) + task_args + torch.multiprocessing.Pool',
                'environment' => [
                    'PYTORCH_ENABLE_MPS_FALLBACK' => '1',
                    'IN_STREAMLIT' => 'true',
                    'PDFTEXT_CPU_WORKERS' => '1',
                ],
                'preflight_order' => [
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
                ],
                'paths' => [
                    'input_folder' => $inputFolder,
                    'output_folder' => $outputFolder,
                    'absolute_input_folder' => $absoluteInputFolder,
                    'absolute_output_folder' => $absoluteOutputFolder,
                    ...$outputCreation,
                ],
                'input_listing' => [
                    'source' => 'os.listdir + os.path.isfile',
                    'entry_count' => count($inputListing['entry_basenames']),
                    'entry_basenames' => $inputListing['entry_basenames'],
                    'file_count' => count($inputListing['file_basenames']),
                    'file_basenames' => $inputListing['file_basenames'],
                    'skipped_non_file_count' => count($inputListing['skipped_non_file_basenames']),
                    'skipped_non_file_basenames' => $inputListing['skipped_non_file_basenames'],
                    'file_filter' => 'os.path.isfile',
                    'extension_filter_active' => false,
                    'non_pdf_files_are_task_candidates' => $inputListing['non_pdf_file_basenames'] !== [],
                    'non_pdf_file_basenames' => $inputListing['non_pdf_file_basenames'],
                    'selected_non_pdf_filenames' => [],
                ],
                'chunking' => [
                    'chunk_index' => $chunkIndex,
                    'num_chunks' => $numChunks,
                    'chunk_size' => 0,
                    'start_index' => 0,
                    'end_index' => 0,
                    'python_slice_start_index' => 0,
                    'python_slice_end_index' => 0,
                    'negative_chunk_index_active' => $chunkIndex < 0,
                    'max_files' => $maxFiles,
                    'max_files_limit_active' => $this->pythonTruthyInteger($maxFiles),
                    'input_file_count' => count($inputFiles),
                    'selected_count' => 0,
                    'selected_filenames' => [],
                    'chunking_reached' => false,
                    'chunk_error_boundary' => 'output-folder-create-failed',
                ],
                'metadata' => [
                    'source' => $absoluteMetadataFile === null ? 'metadataByFilename argument' : 'metadata_file json.load keyed by basename',
                    'metadata_file' => $absoluteMetadataFile,
                    'metadata_load_reached' => false,
                    'metadata_filenames' => [],
                    'selected_metadata_filenames' => [],
                    'missing_metadata_filenames' => [],
                ],
                'spawn_start_method' => $this->convertMainSpawnStartMethodPlan(
                    'output-folder-create-failed',
                    $spawnStartMethodAlreadySet
                ),
                'model_handoff' => $this->convertMainModelHandoffPlan(
                    $torchDevice,
                    $torchDeviceModel,
                    'output-folder-create-failed'
                ),
                'worker_pool' => [
                    'requested_workers' => $workers,
                    'total_processes' => 0,
                    'pool_launchable' => false,
                    'pool_error_boundary' => 'output-folder-create-failed',
                    'start_method' => 'spawn',
                    'process_function' => 'process_single_pdf',
                    'task_args_count' => 0,
                    'task_args' => [],
                    'progress_iterator' => $this->progressIterator(),
                ],
                'console_summary' => $this->conversionSummaryPlan(
                    0,
                    $chunkIndex,
                    $numChunks,
                    0,
                    $absoluteOutputFolder,
                    'output-folder-create-failed'
                ),
                'conversion_boundary' => [
                    'min_length' => $minLength,
                    'per_file_preflight_function' => 'process_single_pdf',
                    'converter_function' => 'convert_single_pdf',
                    'metadata_lookup' => 'metadata.get(os.path.basename(f))',
                    'empty_output_policy' => 'print_empty_file_without_save_markdown',
                ],
                'review_only' => true,
                'executes_python_or_models' => false,
                'executes_multiprocessing' => false,
                'executes_external_pdf_tools' => false,
            ];
        }

        $selectedFiles = $this->chunkFiles($inputFiles, $chunkIndex, $numChunks, $maxFiles);
        $selectedFilenames = array_map(static fn (string $filepath): string => basename($filepath), $selectedFiles);
        $chunkSize = $numChunks < 1 ? 0 : (int) ceil(count($inputFiles) / $numChunks);
        $startIndex = $chunkIndex * $chunkSize;
        $endIndex = $startIndex + $chunkSize;
        [$pythonSliceStartIndex, $pythonSliceEndIndex] = $this->pythonSliceBounds(
            $startIndex,
            $endIndex,
            count($inputFiles)
        );

        try {
            $runtimeMetadataPlan = $absoluteMetadataFile === null
                ? [
                    'metadata' => $metadataByFilename,
                    'metadata_json_type' => 'object',
                    'metadata_get_available' => true,
                    'metadata_shape_error_boundary' => null,
                    'metadata_shape_error_class' => null,
                    'metadata_shape_error_message' => null,
                    'metadata_value_types' => $this->phpMetadataValueTypes($metadataByFilename),
                ]
                : $this->loadRuntimeMetadataFile($absoluteMetadataFile);
        } catch (InvalidArgumentException $exception) {
            if (!$this->isJsonMetadataLoadFailure($exception)) {
                throw $exception;
            }

            return [
                'schema' => 'markerpdf.convert_main_runtime_preflight.v1',
                'source' => 'sddai/markerPDF convert.py::main + os.path.abspath + os.makedirs(exist_ok=True) + task_args + torch.multiprocessing.Pool',
                'environment' => [
                    'PYTORCH_ENABLE_MPS_FALLBACK' => '1',
                    'IN_STREAMLIT' => 'true',
                    'PDFTEXT_CPU_WORKERS' => '1',
                ],
                'preflight_order' => [
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
                ],
                'paths' => [
                    'input_folder' => $inputFolder,
                    'output_folder' => $outputFolder,
                    'absolute_input_folder' => $absoluteInputFolder,
                    'absolute_output_folder' => $absoluteOutputFolder,
                    ...$outputCreation,
                ],
                'input_listing' => [
                    'source' => 'os.listdir + os.path.isfile',
                    'entry_count' => count($inputListing['entry_basenames']),
                    'entry_basenames' => $inputListing['entry_basenames'],
                    'file_count' => count($inputListing['file_basenames']),
                    'file_basenames' => $inputListing['file_basenames'],
                    'skipped_non_file_count' => count($inputListing['skipped_non_file_basenames']),
                    'skipped_non_file_basenames' => $inputListing['skipped_non_file_basenames'],
                    'file_filter' => 'os.path.isfile',
                    'extension_filter_active' => false,
                    'non_pdf_files_are_task_candidates' => $inputListing['non_pdf_file_basenames'] !== [],
                    'non_pdf_file_basenames' => $inputListing['non_pdf_file_basenames'],
                    'selected_non_pdf_filenames' => $this->nonPdfBasenames($selectedFilenames),
                ],
                'chunking' => [
                    'chunk_index' => $chunkIndex,
                    'num_chunks' => $numChunks,
                    'chunk_size' => $chunkSize,
                    'start_index' => $startIndex,
                    'end_index' => $endIndex,
                    'python_slice_start_index' => $pythonSliceStartIndex,
                    'python_slice_end_index' => $pythonSliceEndIndex,
                    'negative_chunk_index_active' => $chunkIndex < 0,
                    'max_files' => $maxFiles,
                    'max_files_limit_active' => $this->pythonTruthyInteger($maxFiles),
                    'input_file_count' => count($inputFiles),
                    'selected_count' => count($selectedFiles),
                    'selected_filenames' => $selectedFilenames,
                    'chunking_reached' => true,
                    'chunk_error_boundary' => null,
                ],
                'metadata' => [
                    'source' => 'metadata_file json.load keyed by basename',
                    'metadata_file' => $absoluteMetadataFile,
                    'metadata_load_reached' => true,
                    'metadata_load_success' => false,
                    'metadata_error_boundary' => 'metadata-file-json-load-failed',
                    'metadata_error_class' => 'JSONDecodeError',
                    'metadata_error_message' => $exception->getMessage(),
                    'metadata_json_type' => null,
                    'metadata_get_available' => false,
                    'metadata_shape_error_boundary' => null,
                    'metadata_shape_error_class' => null,
                    'metadata_shape_error_message' => null,
                    'metadata_value_types' => [],
                    'metadata_value_review' => $this->runtimeMetadataValueReview([], [], [], 'metadata-file-json-load-failed'),
                    'metadata_filenames' => [],
                    'selected_metadata_filenames' => [],
                    'missing_metadata_filenames' => [],
                ],
                'spawn_start_method' => $this->convertMainSpawnStartMethodPlan(
                    'metadata-file-json-load-failed',
                    $spawnStartMethodAlreadySet
                ),
                'model_handoff' => $this->convertMainModelHandoffPlan(
                    $torchDevice,
                    $torchDeviceModel,
                    'metadata-file-json-load-failed'
                ),
                'worker_pool' => [
                    'requested_workers' => $workers,
                    'total_processes' => 0,
                    'pool_launchable' => false,
                    'pool_error_boundary' => 'metadata-file-json-load-failed',
                    'start_method' => 'spawn',
                    'process_function' => 'process_single_pdf',
                    'task_args_count' => 0,
                    'task_args' => [],
                    'progress_iterator' => $this->progressIterator(),
                ],
                'console_summary' => $this->conversionSummaryPlan(
                    0,
                    $chunkIndex,
                    $numChunks,
                    0,
                    $absoluteOutputFolder,
                    'metadata-file-json-load-failed'
                ),
                'conversion_boundary' => [
                    'min_length' => $minLength,
                    'per_file_preflight_function' => 'process_single_pdf',
                    'converter_function' => 'convert_single_pdf',
                    'metadata_lookup' => 'metadata.get(os.path.basename(f))',
                    'empty_output_policy' => 'print_empty_file_without_save_markdown',
                ],
                'review_only' => true,
                'executes_python_or_models' => false,
                'executes_multiprocessing' => false,
                'executes_external_pdf_tools' => false,
            ];
        }

        $runtimeMetadata = $runtimeMetadataPlan['metadata'];
        $metadataFilenames = array_values(array_filter(array_keys($runtimeMetadata), 'is_string'));
        sort($metadataFilenames, SORT_STRING);
        $selectedMetadataFilenames = array_values(array_filter(
            $selectedFilenames,
            static fn (string $filename): bool => array_key_exists($filename, $runtimeMetadata)
        ));
        $missingMetadataFilenames = array_values(array_filter(
            $selectedFilenames,
            static fn (string $filename): bool => !array_key_exists($filename, $runtimeMetadata)
        ));

        $chunkSize = $numChunks < 1 ? 0 : (int) ceil(count($inputFiles) / $numChunks);
        $startIndex = $chunkIndex * $chunkSize;
        $endIndex = $startIndex + $chunkSize;
        [$pythonSliceStartIndex, $pythonSliceEndIndex] = $this->pythonSliceBounds(
            $startIndex,
            $endIndex,
            count($inputFiles)
        );
        $totalProcesses = min(count($selectedFiles), $workers);
        $spawnStartMethod = $this->convertMainSpawnStartMethodPlan(null, $spawnStartMethodAlreadySet, $totalProcesses);
        if (!$spawnStartMethod['start_method_success']) {
            return [
                'schema' => 'markerpdf.convert_main_runtime_preflight.v1',
                'source' => 'sddai/markerPDF convert.py::main + os.path.abspath + os.makedirs(exist_ok=True) + task_args + torch.multiprocessing.Pool',
                'environment' => [
                    'PYTORCH_ENABLE_MPS_FALLBACK' => '1',
                    'IN_STREAMLIT' => 'true',
                    'PDFTEXT_CPU_WORKERS' => '1',
                ],
                'preflight_order' => [
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
                ],
                'paths' => [
                    'input_folder' => $inputFolder,
                    'output_folder' => $outputFolder,
                    'absolute_input_folder' => $absoluteInputFolder,
                    'absolute_output_folder' => $absoluteOutputFolder,
                    ...$outputCreation,
                ],
                'input_listing' => [
                    'source' => 'os.listdir + os.path.isfile',
                    'entry_count' => count($inputListing['entry_basenames']),
                    'entry_basenames' => $inputListing['entry_basenames'],
                    'file_count' => count($inputListing['file_basenames']),
                    'file_basenames' => $inputListing['file_basenames'],
                    'skipped_non_file_count' => count($inputListing['skipped_non_file_basenames']),
                    'skipped_non_file_basenames' => $inputListing['skipped_non_file_basenames'],
                    'file_filter' => 'os.path.isfile',
                    'extension_filter_active' => false,
                    'non_pdf_files_are_task_candidates' => $inputListing['non_pdf_file_basenames'] !== [],
                    'non_pdf_file_basenames' => $inputListing['non_pdf_file_basenames'],
                    'selected_non_pdf_filenames' => $this->nonPdfBasenames($selectedFilenames),
                ],
                'chunking' => [
                    'chunk_index' => $chunkIndex,
                    'num_chunks' => $numChunks,
                    'chunk_size' => $chunkSize,
                    'start_index' => $startIndex,
                    'end_index' => $endIndex,
                    'python_slice_start_index' => $pythonSliceStartIndex,
                    'python_slice_end_index' => $pythonSliceEndIndex,
                    'negative_chunk_index_active' => $chunkIndex < 0,
                    'max_files' => $maxFiles,
                    'max_files_limit_active' => $this->pythonTruthyInteger($maxFiles),
                    'input_file_count' => count($inputFiles),
                    'selected_count' => count($selectedFiles),
                    'selected_filenames' => $selectedFilenames,
                    'chunking_reached' => true,
                    'chunk_error_boundary' => null,
                ],
                'metadata' => [
                    'source' => $absoluteMetadataFile === null ? 'metadataByFilename argument' : 'metadata_file json.load keyed by basename',
                    'metadata_file' => $absoluteMetadataFile,
                    'metadata_load_reached' => true,
                    'metadata_load_success' => true,
                    'metadata_error_boundary' => null,
                    'metadata_error_class' => null,
                    'metadata_error_message' => null,
                    'metadata_json_type' => $runtimeMetadataPlan['metadata_json_type'],
                    'metadata_get_available' => $runtimeMetadataPlan['metadata_get_available'],
                    'metadata_shape_error_boundary' => $runtimeMetadataPlan['metadata_shape_error_boundary'],
                    'metadata_shape_error_class' => $runtimeMetadataPlan['metadata_shape_error_class'],
                    'metadata_shape_error_message' => $runtimeMetadataPlan['metadata_shape_error_message'],
                    'metadata_value_types' => $runtimeMetadataPlan['metadata_value_types'],
                    'metadata_value_review' => $this->runtimeMetadataValueReview(
                        $selectedFilenames,
                        $runtimeMetadata,
                        $runtimeMetadataPlan['metadata_value_types']
                    ),
                    'metadata_filenames' => $metadataFilenames,
                    'selected_metadata_filenames' => $selectedMetadataFilenames,
                    'missing_metadata_filenames' => $missingMetadataFilenames,
                ],
                'spawn_start_method' => $spawnStartMethod,
                'model_handoff' => $this->convertMainModelHandoffPlan(
                    $torchDevice,
                    $torchDeviceModel,
                    'spawn-start-method-failed'
                ),
                'worker_pool' => [
                    'requested_workers' => $workers,
                    'total_processes' => 0,
                    'pool_launchable' => false,
                    'pool_error_boundary' => 'spawn-start-method-failed',
                    'start_method' => 'spawn',
                    'process_function' => 'process_single_pdf',
                    'task_args_count' => 0,
                    'task_args' => [],
                    'progress_iterator' => $this->progressIterator(),
                ],
                'console_summary' => $this->conversionSummaryPlan(
                    0,
                    $chunkIndex,
                    $numChunks,
                    0,
                    $absoluteOutputFolder,
                    'spawn-start-method-failed'
                ),
                'conversion_boundary' => [
                    'min_length' => $minLength,
                    'per_file_preflight_function' => 'process_single_pdf',
                    'converter_function' => 'convert_single_pdf',
                    'metadata_lookup' => 'metadata.get(os.path.basename(f))',
                    'empty_output_policy' => 'print_empty_file_without_save_markdown',
                ],
                'review_only' => true,
                'executes_python_or_models' => false,
                'executes_multiprocessing' => false,
                'executes_external_pdf_tools' => false,
            ];
        }

        $modelHandoff = $this->convertMainModelHandoffPlan($torchDevice, $torchDeviceModel);
        if (!$runtimeMetadataPlan['metadata_get_available'] && $selectedFiles !== []) {
            return [
                'schema' => 'markerpdf.convert_main_runtime_preflight.v1',
                'source' => 'sddai/markerPDF convert.py::main + os.path.abspath + os.makedirs(exist_ok=True) + task_args + torch.multiprocessing.Pool',
                'environment' => [
                    'PYTORCH_ENABLE_MPS_FALLBACK' => '1',
                    'IN_STREAMLIT' => 'true',
                    'PDFTEXT_CPU_WORKERS' => '1',
                ],
                'preflight_order' => [
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
                ],
                'paths' => [
                    'input_folder' => $inputFolder,
                    'output_folder' => $outputFolder,
                    'absolute_input_folder' => $absoluteInputFolder,
                    'absolute_output_folder' => $absoluteOutputFolder,
                    ...$outputCreation,
                ],
                'input_listing' => [
                    'source' => 'os.listdir + os.path.isfile',
                    'entry_count' => count($inputListing['entry_basenames']),
                    'entry_basenames' => $inputListing['entry_basenames'],
                    'file_count' => count($inputListing['file_basenames']),
                    'file_basenames' => $inputListing['file_basenames'],
                    'skipped_non_file_count' => count($inputListing['skipped_non_file_basenames']),
                    'skipped_non_file_basenames' => $inputListing['skipped_non_file_basenames'],
                    'file_filter' => 'os.path.isfile',
                    'extension_filter_active' => false,
                    'non_pdf_files_are_task_candidates' => $inputListing['non_pdf_file_basenames'] !== [],
                    'non_pdf_file_basenames' => $inputListing['non_pdf_file_basenames'],
                    'selected_non_pdf_filenames' => $this->nonPdfBasenames($selectedFilenames),
                ],
                'chunking' => [
                    'chunk_index' => $chunkIndex,
                    'num_chunks' => $numChunks,
                    'chunk_size' => $chunkSize,
                    'start_index' => $startIndex,
                    'end_index' => $endIndex,
                    'python_slice_start_index' => $pythonSliceStartIndex,
                    'python_slice_end_index' => $pythonSliceEndIndex,
                    'negative_chunk_index_active' => $chunkIndex < 0,
                    'max_files' => $maxFiles,
                    'max_files_limit_active' => $this->pythonTruthyInteger($maxFiles),
                    'input_file_count' => count($inputFiles),
                    'selected_count' => count($selectedFiles),
                    'selected_filenames' => $selectedFilenames,
                    'chunking_reached' => true,
                    'chunk_error_boundary' => null,
                ],
                'metadata' => [
                    'source' => 'metadata_file json.load keyed by basename',
                    'metadata_file' => $absoluteMetadataFile,
                    'metadata_load_reached' => true,
                    'metadata_load_success' => true,
                    'metadata_error_boundary' => null,
                    'metadata_error_class' => null,
                    'metadata_error_message' => null,
                    'metadata_json_type' => $runtimeMetadataPlan['metadata_json_type'],
                    'metadata_get_available' => false,
                    'metadata_shape_error_boundary' => $runtimeMetadataPlan['metadata_shape_error_boundary'],
                    'metadata_shape_error_class' => $runtimeMetadataPlan['metadata_shape_error_class'],
                    'metadata_shape_error_message' => $runtimeMetadataPlan['metadata_shape_error_message'],
                    'metadata_value_types' => $runtimeMetadataPlan['metadata_value_types'],
                    'metadata_value_review' => $this->runtimeMetadataValueReview(
                        $selectedFilenames,
                        $runtimeMetadata,
                        $runtimeMetadataPlan['metadata_value_types']
                    ),
                    'metadata_filenames' => [],
                    'selected_metadata_filenames' => [],
                    'missing_metadata_filenames' => $selectedFilenames,
                ],
                'spawn_start_method' => $spawnStartMethod,
                'model_handoff' => $modelHandoff,
                'worker_pool' => [
                    'requested_workers' => $workers,
                    'total_processes' => 0,
                    'pool_launchable' => false,
                    'pool_error_boundary' => 'metadata-get-failed',
                    'start_method' => 'spawn',
                    'process_function' => 'process_single_pdf',
                    'task_args_count' => 0,
                    'task_args' => [],
                    'task_args_error_boundary' => 'metadata-get-failed',
                    'task_args_error_class' => $runtimeMetadataPlan['metadata_shape_error_class'],
                    'task_args_error_message' => $runtimeMetadataPlan['metadata_shape_error_message'],
                    'progress_iterator' => $this->progressIterator(),
                ],
                'console_summary' => $this->conversionSummaryPlan(
                    count($selectedFiles),
                    $chunkIndex,
                    $numChunks,
                    $totalProcesses,
                    $absoluteOutputFolder
                ),
                'conversion_boundary' => [
                    'min_length' => $minLength,
                    'per_file_preflight_function' => 'process_single_pdf',
                    'converter_function' => 'convert_single_pdf',
                    'metadata_lookup' => 'metadata.get(os.path.basename(f))',
                    'metadata_lookup_error_boundary' => 'metadata-get-failed',
                    'empty_output_policy' => 'print_empty_file_without_save_markdown',
                ],
                'review_only' => true,
                'executes_python_or_models' => false,
                'executes_multiprocessing' => false,
                'executes_external_pdf_tools' => false,
            ];
        }

        $metadataValueReview = $this->runtimeMetadataValueReview(
            $selectedFilenames,
            $runtimeMetadata,
            $runtimeMetadataPlan['metadata_value_types']
        );
        $tasks = $this->runtimeTasksForFiles($selectedFiles, $absoluteOutputFolder, $runtimeMetadata, $minLength);

        $taskArgs = [];
        foreach ($tasks as $task) {
            $taskArgs[] = $this->taskArg($task);
        }

        $poolErrorBoundary = null;
        if ($workers < 1) {
            $poolErrorBoundary = 'invalid-worker-count';
        } elseif (count($taskArgs) === 0) {
            $poolErrorBoundary = 'empty-task-queue';
        }
        $processSinglePdfPreflight = $this->runtimeProcessSinglePdfPreflightReview($taskArgs, $poolErrorBoundary);

        return [
            'schema' => 'markerpdf.convert_main_runtime_preflight.v1',
            'source' => 'sddai/markerPDF convert.py::main + os.path.abspath + os.makedirs(exist_ok=True) + task_args + torch.multiprocessing.Pool',
            'environment' => [
                'PYTORCH_ENABLE_MPS_FALLBACK' => '1',
                'IN_STREAMLIT' => 'true',
                'PDFTEXT_CPU_WORKERS' => '1',
            ],
            'preflight_order' => [
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
            ],
            'paths' => [
                'input_folder' => $inputFolder,
                'output_folder' => $outputFolder,
                'absolute_input_folder' => $absoluteInputFolder,
                'absolute_output_folder' => $absoluteOutputFolder,
                ...$outputCreation,
            ],
            'input_listing' => [
                'source' => 'os.listdir + os.path.isfile',
                'entry_count' => count($inputListing['entry_basenames']),
                'entry_basenames' => $inputListing['entry_basenames'],
                'file_count' => count($inputListing['file_basenames']),
                'file_basenames' => $inputListing['file_basenames'],
                'skipped_non_file_count' => count($inputListing['skipped_non_file_basenames']),
                'skipped_non_file_basenames' => $inputListing['skipped_non_file_basenames'],
                'file_filter' => 'os.path.isfile',
                'extension_filter_active' => false,
                'non_pdf_files_are_task_candidates' => $inputListing['non_pdf_file_basenames'] !== [],
                'non_pdf_file_basenames' => $inputListing['non_pdf_file_basenames'],
                'selected_non_pdf_filenames' => $this->nonPdfBasenames($selectedFilenames),
            ],
            'chunking' => [
                'chunk_index' => $chunkIndex,
                'num_chunks' => $numChunks,
                'chunk_size' => $chunkSize,
                'start_index' => $startIndex,
                'end_index' => $endIndex,
                'python_slice_start_index' => $pythonSliceStartIndex,
                'python_slice_end_index' => $pythonSliceEndIndex,
                'negative_chunk_index_active' => $chunkIndex < 0,
                'max_files' => $maxFiles,
                'max_files_limit_active' => $this->pythonTruthyInteger($maxFiles),
                'input_file_count' => count($inputFiles),
                'selected_count' => count($tasks),
                'selected_filenames' => $selectedFilenames,
                'chunking_reached' => true,
                'chunk_error_boundary' => null,
            ],
            'metadata' => [
                'source' => $absoluteMetadataFile === null ? 'metadataByFilename argument' : 'metadata_file json.load keyed by basename',
                'metadata_file' => $absoluteMetadataFile,
                'metadata_load_reached' => true,
                'metadata_load_success' => true,
                'metadata_error_boundary' => null,
                'metadata_error_class' => null,
                'metadata_error_message' => null,
                'metadata_json_type' => $runtimeMetadataPlan['metadata_json_type'],
                'metadata_get_available' => $runtimeMetadataPlan['metadata_get_available'],
                'metadata_shape_error_boundary' => $runtimeMetadataPlan['metadata_shape_error_boundary'],
                'metadata_shape_error_class' => $runtimeMetadataPlan['metadata_shape_error_class'],
                'metadata_shape_error_message' => $runtimeMetadataPlan['metadata_shape_error_message'],
                'metadata_value_types' => $runtimeMetadataPlan['metadata_value_types'],
                'metadata_value_review' => $metadataValueReview,
                'metadata_filenames' => $metadataFilenames,
                'selected_metadata_filenames' => $selectedMetadataFilenames,
                'missing_metadata_filenames' => $missingMetadataFilenames,
            ],
            'spawn_start_method' => $spawnStartMethod,
            'model_handoff' => $modelHandoff,
            'worker_pool' => [
                'requested_workers' => $workers,
                'total_processes' => $totalProcesses,
                'pool_launchable' => $totalProcesses > 0,
                'pool_error_boundary' => $poolErrorBoundary,
                'start_method' => 'spawn',
                'process_function' => 'process_single_pdf',
                'task_args_count' => count($taskArgs),
                'task_args' => $taskArgs,
                'task_args_metadata_value_types' => $metadataValueReview['selected_metadata_value_types'],
                'truthy_non_mapping_metadata_filenames' => $metadataValueReview['truthy_non_mapping_metadata_filenames'],
                'falsy_non_mapping_metadata_filenames' => $metadataValueReview['falsy_non_mapping_metadata_filenames'],
                'per_file_metadata_error_boundary' => $metadataValueReview['conversion_error_boundary'],
                'pool_creation' => $this->convertMainPoolCreationPlan($totalProcesses),
                'pool_cleanup' => $this->convertMainPoolCleanupPlan($totalProcesses, $modelHandoff),
                'process_single_pdf_preflight' => $processSinglePdfPreflight,
                'progress_iterator' => $this->progressIterator(),
            ],
            'console_summary' => $this->conversionSummaryPlan(
                count($taskArgs),
                $chunkIndex,
                $numChunks,
                $totalProcesses,
                $absoluteOutputFolder
            ),
            'conversion_boundary' => [
                'min_length' => $minLength,
                'per_file_preflight_function' => 'process_single_pdf',
                'converter_function' => 'convert_single_pdf',
                'metadata_lookup' => 'metadata.get(os.path.basename(f))',
                'per_file_metadata_error_boundary' => $metadataValueReview['conversion_error_boundary'],
                'per_file_metadata_error_class' => $metadataValueReview['conversion_error_class'],
                'per_file_metadata_error_message_template' => $metadataValueReview['conversion_error_message_template'],
                'empty_output_policy' => 'print_empty_file_without_save_markdown',
            ],
            'review_only' => true,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * WordPress-safe wrapper for convert.py::main preflight failures.
     *
     * Upstream fails at os.listdir(in_folder) before os.makedirs(out_folder),
     * metadata loading, model handoff, task construction, or pool launch. This
     * exposes that early boundary without changing runtimeMainPreflightPlan()
     * callers that intentionally expect exceptions.
     *
     * @param array<string, array<string, mixed>> $metadataByFilename
     * @return array<string, mixed>
     */
    public function runtimeMainPreflightErrorBoundary(
        string $inputFolder,
        string $outputFolder,
        int $chunkIndex = 0,
        int $numChunks = 1,
        ?int $maxFiles = null,
        array $metadataByFilename = [],
        ?int $minLength = null,
        int $workers = 5,
        ?string $metadataFile = null,
        ?string $torchDevice = null,
        ?string $torchDeviceModel = null,
        bool $spawnStartMethodAlreadySet = false
    ): array {
        $absoluteInputFolder = $this->absolutePath($inputFolder);
        $absoluteOutputFolder = $this->absolutePath($outputFolder);
        $absoluteMetadataFile = $metadataFile === null || $metadataFile === ''
            ? null
            : $this->absolutePath($metadataFile);

        try {
            $plan = $this->runtimeMainPreflightPlan(
                $inputFolder,
                $outputFolder,
                $chunkIndex,
                $numChunks,
                $maxFiles,
                $metadataByFilename,
                $minLength,
                $workers,
                $metadataFile,
                $torchDevice,
                $torchDeviceModel,
                $spawnStartMethodAlreadySet
            );

            return [
                'schema' => 'markerpdf.convert_main_runtime_preflight_error_boundary.v1',
                'source' => 'sddai/markerPDF convert.py::main + os.listdir input-folder error boundary',
                'success' => true,
                'plan' => $plan,
                'error' => null,
                'error_boundary' => null,
                'error_class' => null,
                'upstream_error_message' => null,
                'paths' => [
                    'input_folder' => $inputFolder,
                    'output_folder' => $outputFolder,
                    'absolute_input_folder' => $absoluteInputFolder,
                    'absolute_output_folder' => $absoluteOutputFolder,
                    'input_path_exists' => file_exists($absoluteInputFolder),
                    'input_path_type' => $this->filesystemPathType($absoluteInputFolder),
                    'output_folder_creation_reached' => true,
                    'metadata_file' => $absoluteMetadataFile,
                ],
                'input_listing' => [
                    'source' => 'os.listdir + os.path.isfile',
                    'listing_reached' => true,
                    'listing_success' => true,
                    'error_boundary' => null,
                ],
                'blocked_stages' => [],
                'review_only' => true,
                'executes_python_or_models' => false,
                'executes_multiprocessing' => false,
                'executes_external_pdf_tools' => false,
            ];
        } catch (InvalidArgumentException $exception) {
            $errorBoundary = $this->runtimeMainPreflightExceptionBoundary($exception);

            return [
                'schema' => 'markerpdf.convert_main_runtime_preflight_error_boundary.v1',
                'source' => 'sddai/markerPDF convert.py::main + os.listdir input-folder error boundary',
                'success' => false,
                'plan' => null,
                'error' => $exception->getMessage(),
                'error_boundary' => $errorBoundary,
                'error_class' => $this->runtimeMainPreflightExceptionClass($errorBoundary, $absoluteInputFolder),
                'upstream_error_message' => $this->runtimeMainPreflightUpstreamErrorMessage(
                    $errorBoundary,
                    $absoluteInputFolder,
                    $exception->getMessage()
                ),
                'paths' => [
                    'input_folder' => $inputFolder,
                    'output_folder' => $outputFolder,
                    'absolute_input_folder' => $absoluteInputFolder,
                    'absolute_output_folder' => $absoluteOutputFolder,
                    'input_path_exists' => file_exists($absoluteInputFolder),
                    'input_path_type' => $this->filesystemPathType($absoluteInputFolder),
                    'output_folder_creation_reached' => $errorBoundary !== 'input-folder-list-failed',
                    'output_folder_creation_required' => false,
                    'output_folder_creation_blocked' => false,
                    'metadata_file' => $absoluteMetadataFile,
                ],
                'input_listing' => [
                    'source' => 'os.listdir + os.path.isfile',
                    'listing_reached' => $errorBoundary === 'input-folder-list-failed',
                    'listing_success' => false,
                    'entry_count' => 0,
                    'entry_basenames' => [],
                    'file_count' => 0,
                    'file_basenames' => [],
                    'skipped_non_file_count' => 0,
                    'skipped_non_file_basenames' => [],
                    'error_boundary' => $errorBoundary,
                ],
                'metadata' => [
                    'metadata_file' => $absoluteMetadataFile,
                    'metadata_load_reached' => false,
                    'metadata_filenames' => [],
                    'selected_metadata_filenames' => [],
                    'missing_metadata_filenames' => [],
                ],
                'spawn_start_method' => $this->convertMainSpawnStartMethodPlan($errorBoundary, $spawnStartMethodAlreadySet),
                'model_handoff' => $this->convertMainModelHandoffPlan($torchDevice, $torchDeviceModel, $errorBoundary),
                'worker_pool' => [
                    'requested_workers' => $workers,
                    'total_processes' => 0,
                    'pool_launchable' => false,
                    'pool_error_boundary' => $errorBoundary,
                    'start_method' => 'spawn',
                    'process_function' => 'process_single_pdf',
                    'task_args_count' => 0,
                    'task_args' => [],
                    'progress_iterator' => $this->progressIterator(),
                ],
                'console_summary' => $this->conversionSummaryPlan(0, $chunkIndex, $numChunks, 0, $absoluteOutputFolder, $errorBoundary),
                'blocked_stages' => $this->runtimeMainPreflightBlockedStages($errorBoundary),
                'review_only' => true,
                'executes_python_or_models' => false,
                'executes_multiprocessing' => false,
                'executes_external_pdf_tools' => false,
            ];
        }
    }

    /**
     * Runtime worker-side mirror for convert.py::process_single_pdf.
     *
     * convert.py queues every file candidate selected by os.listdir() before
     * process_single_pdf applies markdown_exists and optional min_length/
     * filetype gates inside pool workers. This keeps that later boundary
     * inspectable without invoking convert_single_pdf or model workers.
     *
     * @param list<array{filepath: string, out_folder: string, metadata: mixed, min_length: int|null}> $taskArgs
     * @return array<string, mixed>
     */
    private function runtimeProcessSinglePdfPreflightReview(array $taskArgs, ?string $poolErrorBoundary = null): array
    {
        $blockedBy = $poolErrorBoundary === null ? null : 'pool-process-count-failed';
        $reached = $blockedBy === null;
        $taskArgFilenames = array_map(static fn (array $task): string => basename((string) $task['filepath']), $taskArgs);
        $selectedNonPdfFilenames = $this->nonPdfBasenames($taskArgFilenames);

        $reviews = [];
        $statusByFilename = [];
        $filetypeByFilename = [];
        $filetypeCheckedByFilename = [];
        $textLengthCheckedByFilename = [];
        $upstreamReturnValueByFilename = [];
        $upstreamReturnBoundaryByFilename = [];
        $existingMarkdownFilenames = [];
        $unsupportedFiletypeFilenames = [];
        $shortTextFilenames = [];
        $readyFilenames = [];
        $filetypeCheckedFilenames = [];
        $textLengthCheckedFilenames = [];

        if ($reached) {
            foreach ($taskArgs as $taskArg) {
                $metadata = is_array($taskArg['metadata']) ? $taskArg['metadata'] : null;
                $preflight = $this->processFilePreflightPlan(
                    (string) $taskArg['filepath'],
                    (string) $taskArg['out_folder'],
                    $metadata,
                    $taskArg['min_length'] ?? null
                );
                $filename = (string) $preflight['filename'];
                $status = (string) $preflight['status'];

                $statusByFilename[$filename] = $status;
                $filetypeByFilename[$filename] = $preflight['filetype'];
                $filetypeCheckedByFilename[$filename] = (bool) $preflight['filetype_checked'];
                $textLengthCheckedByFilename[$filename] = (bool) $preflight['text_length_checked'];
                $upstreamReturnValueByFilename[$filename] = $preflight['upstream_return_value'];
                $upstreamReturnBoundaryByFilename[$filename] = $preflight['upstream_return_boundary'];

                if ((bool) $preflight['existing_markdown']) {
                    $existingMarkdownFilenames[] = $filename;
                }
                if ((bool) $preflight['filetype_checked']) {
                    $filetypeCheckedFilenames[] = $filename;
                }
                if ((bool) $preflight['text_length_checked']) {
                    $textLengthCheckedFilenames[] = $filename;
                }

                if ($status === 'skipped-unsupported-filetype') {
                    $unsupportedFiletypeFilenames[] = $filename;
                } elseif ($status === 'skipped-short-text') {
                    $shortTextFilenames[] = $filename;
                } elseif ($status === 'ready-for-conversion') {
                    $readyFilenames[] = $filename;
                }

                $reviews[] = [
                    'filename' => $filename,
                    'status' => $status,
                    'skip_reason' => $preflight['skip_reason'],
                    'existing_markdown' => $preflight['existing_markdown'],
                    'min_length_gate_active' => $preflight['min_length_gate_active'],
                    'filetype_checked' => $preflight['filetype_checked'],
                    'filetype' => $preflight['filetype'],
                    'text_length_checked' => $preflight['text_length_checked'],
                    'text_length' => $preflight['text_length'],
                    'should_invoke_converter' => $preflight['should_invoke_converter'],
                    'upstream_return_value' => $preflight['upstream_return_value'],
                    'upstream_return_type' => $preflight['upstream_return_type'],
                    'upstream_return_boundary' => $preflight['upstream_return_boundary'],
                ];
            }
        }

        $sidecarRejected = array_values(array_filter(
            $selectedNonPdfFilenames,
            static fn (string $filename): bool => in_array($filename, $unsupportedFiletypeFilenames, true)
        ));

        return [
            'source' => 'convert.py pool.imap(process_single_pdf, task_args) per-file preflight boundary',
            'order' => 'after_pool_imap_worker_before_convert_single_pdf',
            'review_reached' => $reached,
            'blocked_by' => $blockedBy,
            'pool_error_boundary' => $poolErrorBoundary,
            'task_args_count' => count($taskArgs),
            'task_arg_filenames' => $taskArgFilenames,
            'extension_filter_before_task_args' => false,
            'filetype_gate_requires_min_length' => true,
            'selected_non_pdf_filenames' => $selectedNonPdfFilenames,
            'sidecar_reaches_task_args_before_preflight' => $selectedNonPdfFilenames !== [],
            'sidecar_rejected_by_process_single_pdf_filenames' => $sidecarRejected,
            'sidecar_rejection_boundary' => $sidecarRejected === [] ? null : 'unsupported-filetype-return-zero',
            'preflight_reviews' => $reviews,
            'status_by_filename' => $statusByFilename,
            'filetype_by_filename' => $filetypeByFilename,
            'filetype_checked_by_filename' => $filetypeCheckedByFilename,
            'text_length_checked_by_filename' => $textLengthCheckedByFilename,
            'upstream_return_value_by_filename' => $upstreamReturnValueByFilename,
            'upstream_return_boundary_by_filename' => $upstreamReturnBoundaryByFilename,
            'existing_markdown_filenames' => $existingMarkdownFilenames,
            'unsupported_filetype_filenames' => $unsupportedFiletypeFilenames,
            'short_text_filenames' => $shortTextFilenames,
            'ready_filenames' => $readyFilenames,
            'filetype_checked_filenames' => $filetypeCheckedFilenames,
            'text_length_checked_filenames' => $textLengthCheckedFilenames,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * Native boundary for convert.py's --metadata_file json.load() path.
     *
     * @return array<string, array<string, mixed>>
     */
    public function loadMetadataFile(?string $metadataFile): array
    {
        if ($metadataFile === null || $metadataFile === '') {
            return [];
        }

        $contents = @file_get_contents($metadataFile);
        if (!is_string($contents)) {
            throw new InvalidArgumentException('Batch metadata file is not readable: ' . $metadataFile);
        }

        try {
            $metadata = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Batch metadata file must contain valid JSON.', previous: $exception);
        }
        if (!is_array($metadata)) {
            throw new InvalidArgumentException('Batch metadata file must decode to an object keyed by basename.');
        }

        $normalized = [];
        foreach ($metadata as $filename => $value) {
            if (!is_string($filename) || !is_array($value)) {
                throw new InvalidArgumentException('Batch metadata file values must be objects keyed by basename.');
            }
            $normalized[$filename] = $value;
        }

        return $normalized;
    }

    /**
     * Runtime-specific mirror of convert.py's metadata_file json.load().
     *
     * The public loader keeps the native PHP helper strict. The runtime
     * preflight needs Python's later failure boundary: json.load() may return
     * a list/string/null successfully, but task tuple construction then fails
     * when convert.py calls metadata.get(os.path.basename(f)).
     *
     * @return array{
     *     metadata: array<string, array<string, mixed>|null>,
     *     metadata_json_type: string,
     *     metadata_get_available: bool,
     *     metadata_shape_error_boundary: string|null,
     *     metadata_shape_error_class: string|null,
     *     metadata_shape_error_message: string|null,
     *     metadata_value_types: array<string, string>
     * }
     */
    private function loadRuntimeMetadataFile(string $metadataFile): array
    {
        $contents = @file_get_contents($metadataFile);
        if (!is_string($contents)) {
            throw new InvalidArgumentException('Batch metadata file is not readable: ' . $metadataFile);
        }

        try {
            $decodedObject = json_decode($contents, false, flags: JSON_THROW_ON_ERROR);
            $decodedArray = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Batch metadata file must contain valid JSON.', previous: $exception);
        }

        $jsonType = $this->jsonMetadataType($decodedObject);
        if (!$decodedObject instanceof stdClass || !is_array($decodedArray)) {
            return [
                'metadata' => [],
                'metadata_json_type' => $jsonType,
                'metadata_get_available' => false,
                'metadata_shape_error_boundary' => 'metadata-get-failed',
                'metadata_shape_error_class' => 'AttributeError',
                'metadata_shape_error_message' => "'" . $jsonType . "' object has no attribute 'get'",
                'metadata_value_types' => [],
            ];
        }

        $metadata = [];
        $metadataValueTypes = [];
        $objectValues = get_object_vars($decodedObject);
        foreach ($decodedArray as $filename => $value) {
            if (!is_string($filename)) {
                return [
                    'metadata' => [],
                    'metadata_json_type' => $jsonType,
                    'metadata_get_available' => false,
                    'metadata_shape_error_boundary' => 'metadata-get-failed',
                    'metadata_shape_error_class' => 'AttributeError',
                    'metadata_shape_error_message' => "'" . $jsonType . "' object has no attribute 'get'",
                    'metadata_value_types' => [],
                ];
            }

            $metadata[$filename] = $value;
            $metadataValueTypes[$filename] = $this->jsonMetadataType($objectValues[$filename] ?? null);
        }

        return [
            'metadata' => $metadata,
            'metadata_json_type' => 'object',
            'metadata_get_available' => true,
            'metadata_shape_error_boundary' => null,
            'metadata_shape_error_class' => null,
            'metadata_shape_error_message' => null,
            'metadata_value_types' => $metadataValueTypes,
        ];
    }

    private function jsonMetadataType(mixed $value): string
    {
        if ($value instanceof stdClass) {
            return 'dict';
        }
        if (is_array($value)) {
            return 'list';
        }
        if ($value === null) {
            return 'NoneType';
        }
        if (is_string($value)) {
            return 'str';
        }
        if (is_int($value) || is_float($value)) {
            return 'int';
        }
        if (is_bool($value)) {
            return 'bool';
        }

        return get_debug_type($value);
    }

    /**
     * Native review boundary for convert.py::process_single_pdf preflight.
     *
     * Upstream checks marker.output::markdown_exists first, then applies the
     * optional --min_length gate through marker.pdf.utils::find_filetype and
     * marker.pdf.extract_text::get_length_of_text before loading models or
     * saving Markdown. This records the same decision without invoking the
     * supplied converter, Python workers, pdftext, pypdfium, or external tools.
     *
     * @param array{filepath: string, out_folder: string, metadata?: array<string, mixed>|null, min_length?: int|null} $task
     * @return array<string, mixed>
     */
    public function processTaskPreflightPlan(array $task, ?callable $textLength = null): array
    {
        return $this->processFilePreflightPlan(
            $task['filepath'],
            $task['out_folder'],
            $task['metadata'] ?? null,
            $task['min_length'] ?? null,
            $textLength
        );
    }

    /**
     * @param array<string, mixed>|null $metadata
     * @return array<string, mixed>
     */
    public function processFilePreflightPlan(
        string $filepath,
        string $outputFolder,
        ?array $metadata,
        ?int $minLength,
        ?callable $textLength = null
    ): array {
        $filename = basename($filepath);
        $metadataKeys = $metadata === null ? [] : array_values(array_filter(array_keys($metadata), 'is_string'));
        sort($metadataKeys, SORT_STRING);

        $base = [
            'schema' => 'markerpdf.convert_process_single_pdf_preflight.v1',
            'source' => 'sddai/markerPDF convert.py::process_single_pdf + marker.output::markdown_exists + marker.pdf.utils::find_filetype + marker.pdf.extract_text::get_length_of_text',
            'filename' => $filename,
            'filepath' => $filepath,
            'out_folder' => $outputFolder,
            'metadata_keys' => $metadataKeys,
            'min_length' => $minLength,
            'preflight_order' => ['markdown_exists', 'find_filetype', 'get_length_of_text', 'convert_single_pdf', 'save_markdown'],
            'existing_markdown' => $this->writer->markdownExists($outputFolder, $filename),
            'filetype_checked' => false,
            'filetype' => null,
            'min_length_gate_active' => $this->pythonTruthyInteger($minLength),
            'text_length_checked' => false,
            'text_length' => null,
            'skip_reason' => null,
            'error_stage' => null,
            'error_boundary' => null,
            'error_class' => null,
            'error_message' => null,
            'error_output' => null,
            'should_invoke_converter' => false,
            'should_save_markdown_after_nonempty_output' => false,
            'conversion_call' => [
                'function' => 'convert_single_pdf',
                'metadata_argument_source' => 'metadata_file basename lookup',
                'receives_metadata' => $metadata !== null,
            ],
            'upstream_return_value' => null,
            'upstream_return_type' => 'python-none',
            'upstream_return_boundary' => 'conversion-or-empty-output-return-none',
            'review_only' => true,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];

        if ($base['existing_markdown']) {
            return [
                ...$base,
                'status' => 'skipped-existing',
                'skip_reason' => 'markdown_exists',
                'upstream_return_boundary' => 'markdown_exists-return-none',
            ];
        }

        if ($this->pythonTruthyInteger($minLength)) {
            $filetype = $this->filetypeDetector->findFiletype($filepath);
            $base['filetype_checked'] = true;
            $base['filetype'] = $filetype;

            if ($filetype === 'other') {
                return [
                    ...$base,
                    'status' => 'skipped-unsupported-filetype',
                    'skip_reason' => 'unsupported-filetype',
                    'upstream_return_value' => 0,
                    'upstream_return_type' => 'int',
                    'upstream_return_boundary' => 'unsupported-filetype-return-zero',
                ];
            }

            $base['text_length_checked'] = true;
            try {
                $length = $textLength === null
                    ? $this->embeddedTextLength($filepath)
                    : (int) $textLength($filepath);
                $base['text_length'] = $length;
            } catch (Throwable $throwable) {
                $errorOutput = $this->conversionErrorOutput($filepath, $throwable);

                return [
                    ...$base,
                    'status' => 'error',
                    'skip_reason' => 'preflight-exception',
                    'error_stage' => 'get_length_of_text',
                    'error_boundary' => 'preflight-exception-print-return-none',
                    'error_class' => get_class($throwable),
                    'error_message' => $throwable->getMessage(),
                    'error_output' => $errorOutput,
                    'upstream_return_value' => null,
                    'upstream_return_type' => 'python-none',
                    'upstream_return_boundary' => 'preflight-exception-print-return-none',
                ];
            }

            if ($length < $minLength) {
                return [
                    ...$base,
                    'status' => 'skipped-short-text',
                    'skip_reason' => 'short-text',
                    'upstream_return_boundary' => 'short-text-return-none',
                ];
            }
        }

        return [
            ...$base,
            'status' => 'ready-for-conversion',
            'should_invoke_converter' => true,
            'should_save_markdown_after_nonempty_output' => true,
        ];
    }

    /**
     * @param array<string, mixed>|null $metadata
     * @return array<string, mixed>
     */
    public function processFile(
        string $filepath,
        string $outputFolder,
        ?array $metadata,
        ?int $minLength,
        callable $converter,
        ?callable $textLength = null
    ): array {
        $filename = basename($filepath);
        $preflight = $this->processFilePreflightPlan($filepath, $outputFolder, $metadata, $minLength, $textLength);

        if ($preflight['status'] !== 'ready-for-conversion') {
            $result = [
                'filename' => $filename,
                'filetype' => $preflight['filetype'],
                'text_length' => $preflight['text_length'],
                'min_length' => $minLength,
                'preflight' => $preflight,
                'upstream_return_value' => $preflight['upstream_return_value'] ?? null,
                'upstream_return_type' => $preflight['upstream_return_type'] ?? 'python-none',
                'upstream_return_boundary' => $preflight['upstream_return_boundary'] ?? null,
                'executes_python_or_models' => false,
                'executes_external_pdf_tools' => false,
            ];

            if (($preflight['status'] ?? null) === 'error') {
                $result += [
                    'error' => $preflight['error_message'],
                    'error_output' => $preflight['error_output'],
                    'writes_markdown' => false,
                ];
            }

            return $this->result((string) $preflight['status'], $filepath, $result);
        }

        try {
            $conversion = $this->normalizeConversion($converter($filepath, $metadata));
        } catch (Throwable $throwable) {
            $errorOutput = $this->conversionErrorOutput($filepath, $throwable);
            $conversionResult = $this->processSinglePdfPostConversionBoundary($filepath, 'error', $throwable, $errorOutput);

            return $this->result('error', $filepath, [
                'filename' => $filename,
                'error' => $throwable->getMessage(),
                'error_output' => $errorOutput,
                'conversion_result' => $conversionResult,
                'upstream_return_value' => $conversionResult['upstream_return_value'],
                'upstream_return_type' => $conversionResult['upstream_return_type'],
                'upstream_return_boundary' => $conversionResult['upstream_return_boundary'],
                'writes_markdown' => false,
                'preflight' => $preflight,
                'executes_python_or_models' => false,
                'executes_external_pdf_tools' => false,
            ]);
        }

        if (trim($conversion['text']) === '') {
            $conversionResult = $this->processSinglePdfPostConversionBoundary($filepath, 'empty-output');

            return $this->result('skipped-empty-output', $filepath, [
                'filename' => $filename,
                'preflight' => $preflight,
                'conversion_result' => $conversionResult,
                'upstream_return_value' => $conversionResult['upstream_return_value'],
                'upstream_return_type' => $conversionResult['upstream_return_type'],
                'upstream_return_boundary' => $conversionResult['upstream_return_boundary'],
                'executes_python_or_models' => false,
                'executes_external_pdf_tools' => false,
            ]);
        }

        try {
            $subfolder = $this->writer->saveMarkdown(
                $outputFolder,
                $filename,
                $conversion['text'],
                $conversion['images'],
                $conversion['metadata']
            );
        } catch (Throwable $throwable) {
            $errorOutput = $this->conversionErrorOutput($filepath, $throwable);
            $conversionResult = $this->processSinglePdfPostConversionBoundary(
                $filepath,
                'save-markdown-error',
                $throwable,
                $errorOutput
            );

            return $this->result('error', $filepath, [
                'filename' => $filename,
                'error' => $throwable->getMessage(),
                'error_output' => $errorOutput,
                'conversion_result' => $conversionResult,
                'upstream_return_value' => $conversionResult['upstream_return_value'],
                'upstream_return_type' => $conversionResult['upstream_return_type'],
                'upstream_return_boundary' => $conversionResult['upstream_return_boundary'],
                'writes_markdown' => false,
                'preflight' => $preflight,
                'executes_python_or_models' => false,
                'executes_external_pdf_tools' => false,
            ]);
        }

        $conversionResult = $this->processSinglePdfPostConversionBoundary($filepath, 'converted');

        return $this->result('converted', $filepath, [
            'filename' => $filename,
            'output_folder' => $subfolder,
            'markdown' => $this->writer->getMarkdownFilepath($outputFolder, $filename),
            'images' => array_keys($conversion['images']),
            'preflight' => $preflight,
            'conversion_result' => $conversionResult,
            'upstream_return_value' => $conversionResult['upstream_return_value'],
            'upstream_return_type' => $conversionResult['upstream_return_type'],
            'upstream_return_boundary' => $conversionResult['upstream_return_boundary'],
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ]);
    }

    /**
     * @return list<string>
     */
    private function inputFiles(string $inputFolder): array
    {
        return $this->inputDirectoryListing($inputFolder)['file_paths'];
    }

    /**
     * @return array{output_path_exists: bool, output_path_type: string, output_folder_exists: bool, output_folder_parent_path: string, output_folder_parent_path_exists: bool, output_folder_parent_path_type: string, output_folder_parent_conflict_path: string|null, output_folder_parent_conflict_type: string|null, output_folder_parent_creation_blocked: bool, upstream_creates_output_folder: true, native_plan_creates_output_folder: false, output_folder_creation_required: bool, output_folder_creation_call: string, output_folder_creation_order: string, output_folder_creation_blocked: bool, output_folder_creation_error_boundary: string|null, output_folder_creation_error_class: string|null, output_folder_creation_error_message: string|null}
     */
    private function outputFolderCreationPlan(string $absoluteOutputFolder): array
    {
        $exists = file_exists($absoluteOutputFolder);
        $isDirectory = is_dir($absoluteOutputFolder);
        $pathType = 'missing';
        if ($isDirectory) {
            $pathType = 'directory';
        } elseif (is_file($absoluteOutputFolder)) {
            $pathType = 'file';
        } elseif ($exists) {
            $pathType = 'other';
        }

        $parentPath = dirname($absoluteOutputFolder);
        $parentConflict = $this->outputFolderParentConflict($absoluteOutputFolder);
        $targetBlocked = $exists && !$isDirectory;
        $parentBlocked = $parentConflict !== null;
        $blocked = $targetBlocked || $parentBlocked;
        $errorBoundary = null;
        $errorClass = null;
        $errorMessage = null;
        if ($targetBlocked) {
            $errorBoundary = 'output-folder-target-exists-not-directory';
            $errorClass = 'FileExistsError';
            $errorMessage = "[Errno 17] File exists: '" . $absoluteOutputFolder . "'";
        } elseif ($parentBlocked) {
            $errorBoundary = 'output-folder-parent-not-directory';
            $errorClass = 'NotADirectoryError';
            $errorMessage = "[Errno 20] Not a directory: '" . $absoluteOutputFolder . "'";
        }

        return [
            'output_path_exists' => $exists,
            'output_path_type' => $pathType,
            'output_folder_exists' => $isDirectory,
            'output_folder_parent_path' => $parentPath,
            'output_folder_parent_path_exists' => file_exists($parentPath),
            'output_folder_parent_path_type' => $this->filesystemPathType($parentPath),
            'output_folder_parent_conflict_path' => $parentConflict['path'] ?? null,
            'output_folder_parent_conflict_type' => $parentConflict['type'] ?? null,
            'output_folder_parent_creation_blocked' => $parentBlocked,
            'upstream_creates_output_folder' => true,
            'native_plan_creates_output_folder' => false,
            'output_folder_creation_required' => !$isDirectory,
            'output_folder_creation_call' => 'os.makedirs(out_folder, exist_ok=True)',
            'output_folder_creation_order' => 'after_list_input_files_before_chunk_files',
            'output_folder_creation_blocked' => $blocked,
            'output_folder_creation_error_boundary' => $errorBoundary,
            'output_folder_creation_error_class' => $errorClass,
            'output_folder_creation_error_message' => $errorMessage,
        ];
    }

    /**
     * @return array{entry_basenames: list<string>, file_paths: list<string>, file_basenames: list<string>, skipped_non_file_basenames: list<string>, non_pdf_file_basenames: list<string>}
     */
    private function inputDirectoryListing(string $inputFolder): array
    {
        if (!is_dir($inputFolder)) {
            if (file_exists($inputFolder)) {
                throw new InvalidArgumentException('Batch input folder is not a directory: ' . $inputFolder);
            }

            throw new InvalidArgumentException('Batch input folder does not exist: ' . $inputFolder);
        }
        if (!is_readable($inputFolder)) {
            throw new InvalidArgumentException('Batch input folder is not readable: ' . $inputFolder);
        }

        $entryBasenames = [];
        $filePathsByBasename = [];
        $skippedNonFileBasenames = [];

        $entries = @scandir($inputFolder);
        if ($entries === false) {
            throw new InvalidArgumentException('Batch input folder cannot be listed: ' . $inputFolder);
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryBasenames[] = $entry;
            $path = $inputFolder . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                $filePathsByBasename[$entry] = $path;
                continue;
            }

            $skippedNonFileBasenames[] = $entry;
        }

        sort($entryBasenames, SORT_STRING);
        ksort($filePathsByBasename, SORT_STRING);
        sort($skippedNonFileBasenames, SORT_STRING);
        $fileBasenames = array_keys($filePathsByBasename);

        return [
            'entry_basenames' => array_values($entryBasenames),
            'file_paths' => array_values($filePathsByBasename),
            'file_basenames' => array_values($fileBasenames),
            'skipped_non_file_basenames' => array_values($skippedNonFileBasenames),
            'non_pdf_file_basenames' => $this->nonPdfBasenames($fileBasenames),
        ];
    }

    /**
     * @return array{path: string, type: string}|null
     */
    private function outputFolderParentConflict(string $absoluteOutputFolder): ?array
    {
        $parent = dirname($absoluteOutputFolder);
        while ($parent !== '' && $parent !== dirname($parent)) {
            if (file_exists($parent)) {
                return is_dir($parent)
                    ? null
                    : ['path' => $parent, 'type' => $this->filesystemPathType($parent)];
            }

            $parent = dirname($parent);
        }

        if ($parent !== '' && file_exists($parent) && !is_dir($parent)) {
            return ['path' => $parent, 'type' => $this->filesystemPathType($parent)];
        }

        return null;
    }

    /**
     * @param list<string> $files
     * @param array<string, array<string, mixed>> $metadataByFilename
     * @return list<array{filepath: string, out_folder: string, metadata: array<string, mixed>|null, min_length: int|null}>
     */
    private function tasksForFiles(array $files, string $outputFolder, array $metadataByFilename, ?int $minLength): array
    {
        $tasks = [];
        foreach ($files as $filepath) {
            $basename = basename($filepath);
            $metadata = $metadataByFilename[$basename] ?? null;
            if ($metadata !== null && !is_array($metadata)) {
                throw new InvalidArgumentException('Batch metadata values must be arrays keyed by basename.');
            }

            $tasks[] = [
                'filepath' => $filepath,
                'out_folder' => $outputFolder,
                'metadata' => $metadata,
                'min_length' => $minLength,
            ];
        }

        return $tasks;
    }

    /**
     * Runtime task tuple planner for convert.py::main.
     *
     * Upstream passes metadata.get(os.path.basename(f)) through unchanged.
     * Per-file scalar/list values are therefore task-argument values, not
     * metadata-file shape errors; truthy non-dict values fail later inside
     * convert_single_pdf() when it calls metadata.get("languages").
     *
     * @param list<string> $files
     * @param array<string, mixed> $metadataByFilename
     * @return list<array{filepath: string, out_folder: string, metadata: mixed, min_length: int|null}>
     */
    private function runtimeTasksForFiles(array $files, string $outputFolder, array $metadataByFilename, ?int $minLength): array
    {
        $tasks = [];
        foreach ($files as $filepath) {
            $basename = basename($filepath);
            $metadata = array_key_exists($basename, $metadataByFilename)
                ? $metadataByFilename[$basename]
                : null;

            $tasks[] = [
                'filepath' => $filepath,
                'out_folder' => $outputFolder,
                'metadata' => $metadata,
                'min_length' => $minLength,
            ];
        }

        return $tasks;
    }

    /**
     * @param array<string, mixed> $metadataByFilename
     * @return array<string, string>
     */
    private function phpMetadataValueTypes(array $metadataByFilename): array
    {
        $types = [];
        foreach ($metadataByFilename as $filename => $value) {
            if (!is_string($filename)) {
                continue;
            }

            $types[$filename] = $this->phpMetadataValueType($value);
        }

        return $types;
    }

    private function phpMetadataValueType(mixed $value): string
    {
        if (is_array($value)) {
            return array_is_list($value) ? 'list' : 'dict';
        }

        return $this->jsonMetadataType($value);
    }

    /**
     * @param list<string> $selectedFilenames
     * @param array<string, mixed> $metadataByFilename
     * @param array<string, string> $metadataValueTypes
     * @return array<string, mixed>
     */
    private function runtimeMetadataValueReview(
        array $selectedFilenames,
        array $metadataByFilename,
        array $metadataValueTypes,
        ?string $blockedBy = null
    ): array {
        $reached = $blockedBy === null;
        $selectedTypes = [];
        $truthyNonMapping = [];
        $falsyNonMapping = [];

        if ($reached) {
            foreach ($selectedFilenames as $filename) {
                if (!array_key_exists($filename, $metadataByFilename)) {
                    continue;
                }

                $value = $metadataByFilename[$filename];
                $type = $metadataValueTypes[$filename] ?? $this->phpMetadataValueType($value);
                $selectedTypes[$filename] = $type;
                if ($type === 'dict') {
                    continue;
                }

                if ($this->pythonTruthyMetadataValue($value)) {
                    $truthyNonMapping[] = $filename;
                } else {
                    $falsyNonMapping[] = $filename;
                }
            }
        }

        return [
            'source' => 'convert.py metadata.get basename + convert_single_pdf metadata truthiness',
            'review_reached' => $reached,
            'blocked_by' => $blockedBy,
            'selected_metadata_value_types' => $selectedTypes,
            'truthy_non_mapping_metadata_filenames' => $truthyNonMapping,
            'falsy_non_mapping_metadata_filenames' => $falsyNonMapping,
            'conversion_error_boundary' => $truthyNonMapping === [] ? null : 'convert-single-pdf-metadata-get-failed',
            'conversion_error_class' => $truthyNonMapping === [] ? null : 'AttributeError',
            'conversion_error_message_template' => $truthyNonMapping === []
                ? null
                : "'{type}' object has no attribute 'get'",
            'blocks_task_args' => false,
            'blocks_pool_launch' => false,
            'executes_python_or_models' => false,
        ];
    }

    /**
     * @return array{text: string, images: array<string, mixed>, metadata: array<string, mixed>}
     */
    private function normalizeConversion(mixed $conversion): array
    {
        if (is_string($conversion)) {
            return ['text' => $conversion, 'images' => [], 'metadata' => []];
        }
        if (!is_array($conversion)) {
            throw new InvalidArgumentException('Batch converter must return text or a conversion array.');
        }

        $text = $conversion['text']
            ?? $conversion['full_text']
            ?? $conversion['markdown']
            ?? $conversion[0]
            ?? '';
        $images = $conversion['images'] ?? $conversion[1] ?? [];
        $metadata = $conversion['metadata'] ?? $conversion['out_metadata'] ?? $conversion[2] ?? [];

        if (!is_array($images) || !is_array($metadata)) {
            throw new InvalidArgumentException('Batch converter images and metadata must be arrays.');
        }

        return [
            'text' => (string) $text,
            'images' => $images,
            'metadata' => $metadata,
        ];
    }

    private function embeddedTextLength(string $filepath): int
    {
        $bytes = @file_get_contents($filepath);
        if (!is_string($bytes)) {
            return 0;
        }

        return $this->length(trim($this->textExtractor->extractPlainText($bytes)));
    }

    private function length(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return strlen($text);
    }

    /**
     * @return array{message_line: string, traceback: string, traceback_available: bool, review_only: true}
     */
    private function conversionErrorOutput(string $filepath, Throwable $throwable): array
    {
        $trace = $throwable->getTraceAsString();
        $traceback = get_class($throwable) . ': ' . $throwable->getMessage();
        if ($trace !== '') {
            $traceback .= "\n" . $trace;
        }

        return [
            'message_line' => 'Error converting ' . $filepath . ': ' . $throwable->getMessage(),
            'traceback' => $traceback,
            'traceback_available' => $traceback !== '',
            'review_only' => true,
        ];
    }

    /**
     * @param array{message_line: string, traceback: string, traceback_available: bool, review_only: true}|null $errorOutput
     * @return array<string, mixed>
     */
    private function processSinglePdfPostConversionBoundary(
        string $filepath,
        string $outcome,
        ?Throwable $throwable = null,
        ?array $errorOutput = null
    ): array {
        $converted = $outcome === 'converted';
        $empty = $outcome === 'empty-output';
        $error = $outcome === 'error';
        $saveError = $outcome === 'save-markdown-error';
        $errorOutput ??= $error && $throwable !== null
            ? $this->conversionErrorOutput($filepath, $throwable)
            : null;

        $stdoutMessage = null;
        $errorBoundary = null;
        $upstreamReturnBoundary = 'saved-markdown-return-none';
        if ($empty) {
            $stdoutMessage = 'Empty file: ' . $filepath . '.  Could not convert.';
            $upstreamReturnBoundary = 'empty-output-print-return-none';
        } elseif ($error) {
            $stdoutMessage = $errorOutput['message_line'] ?? null;
            $errorBoundary = 'conversion-exception-print-return-none';
            $upstreamReturnBoundary = 'conversion-exception-print-return-none';
        } elseif ($saveError) {
            $stdoutMessage = $errorOutput['message_line'] ?? null;
            $errorBoundary = 'save-markdown-exception-print-return-none';
            $upstreamReturnBoundary = 'save-markdown-exception-print-return-none';
        }

        return [
            'source' => 'convert.py process_single_pdf post-conversion boundary',
            'order' => $saveError ? 'after_nonempty_output_during_save_markdown' : 'after_convert_single_pdf_before_save_markdown',
            'conversion_reached' => true,
            'conversion_success' => !$error,
            'empty_output' => $empty,
            'save_markdown_reached' => $converted || $saveError,
            'save_markdown_writes_markdown' => $converted,
            'stdout_message_line' => $stdoutMessage,
            'error_boundary' => $errorBoundary,
            'error_class' => $throwable === null ? null : get_class($throwable),
            'error_message' => $throwable?->getMessage(),
            'traceback' => $errorOutput['traceback'] ?? null,
            'traceback_available' => (bool) ($errorOutput['traceback_available'] ?? false),
            'upstream_return_value' => null,
            'upstream_return_type' => 'python-none',
            'upstream_return_boundary' => $upstreamReturnBoundary,
            'review_only' => true,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $tasks
     * @param list<array<string, mixed>> $results
     * @return array{tasks: list<array<string, mixed>>, results: list<array<string, mixed>>, converted: int, skipped: int, errors: int, progress: array<string, mixed>}
     */
    private function folderSummary(array $tasks, array $results): array
    {
        $total = count($tasks);
        $completed = count($results);
        $converted = $this->convertedCount($results);
        $skipped = $this->skippedCount($results);
        $errors = $this->errorCount($results);

        return [
            'tasks' => $tasks,
            'results' => $results,
            'converted' => $converted,
            'skipped' => $skipped,
            'errors' => $errors,
            'progress' => [
                'description' => 'Processing PDFs',
                'unit' => 'pdf',
                'iterator' => $this->progressIterator(),
                'total' => $total,
                'completed' => $completed,
                'pending' => max(0, $total - $completed),
                'percent_complete' => $this->percentComplete($completed, $total),
                'converted' => $converted,
                'skipped' => $skipped,
                'errors' => $errors,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $task
     * @param array<string, mixed> $result
     * @param list<array<string, mixed>> $results
     * @return array<string, mixed>
     */
    private function progressEvent(int $completed, int $total, array $task, array $result, array $results): array
    {
        return [
            'description' => 'Processing PDFs',
            'unit' => 'pdf',
            'filename' => basename((string) ($task['filepath'] ?? '')),
            'filepath' => (string) ($task['filepath'] ?? ''),
            'status' => (string) ($result['status'] ?? ''),
            'completed' => $completed,
            'total' => $total,
            'pending' => max(0, $total - $completed),
            'percent_complete' => $this->percentComplete($completed, $total),
            'converted' => $this->convertedCount($results),
            'skipped' => $this->skippedCount($results),
            'errors' => $this->errorCount($results),
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param array{filepath: string, out_folder: string, metadata?: mixed, min_length?: int|null} $task
     * @return array{filepath: string, out_folder: string, metadata: mixed, min_length: int|null}
     */
    private function taskArg(array $task): array
    {
        return [
            'filepath' => $task['filepath'],
            'out_folder' => $task['out_folder'],
            'metadata' => $task['metadata'] ?? null,
            'min_length' => $task['min_length'] ?? null,
        ];
    }

    private function progressIterator(): string
    {
        return 'tqdm(pool.imap(process_single_pdf, task_args), total=len(task_args), desc="Processing PDFs", unit="pdf")';
    }

    /**
     * @return array{
     *     source: string,
     *     order: string,
     *     pool_creation_reached: true,
     *     pool_creation_success: bool,
     *     pool_creation_call: string,
     *     processes: int,
     *     error_boundary: string|null,
     *     error_class: string|null,
     *     error_message: string|null,
     *     blocks_pool_imap: bool,
     *     pool_imap_reached: bool,
     *     progress_iterator_reached: bool,
     *     executes_multiprocessing: false
     * }
     */
    private function convertMainPoolCreationPlan(int $totalProcesses): array
    {
        $success = $totalProcesses >= 1;

        return [
            'source' => 'convert.py torch.multiprocessing Pool creation boundary',
            'order' => 'after_task_args_before_pool_imap',
            'pool_creation_reached' => true,
            'pool_creation_success' => $success,
            'pool_creation_call' => 'mp.Pool(processes=total_processes, initializer=worker_init, initargs=(model_lst,))',
            'processes' => $totalProcesses,
            'error_boundary' => $success ? null : 'pool-process-count-failed',
            'error_class' => $success ? null : 'ValueError',
            'error_message' => $success ? null : 'Number of processes must be at least 1',
            'blocks_pool_imap' => !$success,
            'pool_imap_reached' => $success,
            'progress_iterator_reached' => $success,
            'executes_multiprocessing' => false,
        ];
    }

    /**
     * @param array<string, mixed> $modelHandoff
     * @return array{
     *     source: string,
     *     order: string,
     *     cleanup_reached: bool,
     *     blocked_by: string|null,
     *     pool_imap_completed_required: true,
     *     pool_imap_reached: bool,
     *     worker_handler_terminate_assignment: string,
     *     worker_handler_terminate_override_reached: bool,
     *     worker_exit_function: string,
     *     worker_exit_deletes_global_model_refs: bool,
     *     worker_exit_delete_statement: string,
     *     model_list_delete_reached: bool,
     *     model_list_delete_statement: string,
     *     parent_model_list_loaded: bool,
     *     worker_init_argument: string|null,
     *     executes_python_or_models: false,
     *     executes_multiprocessing: false,
     *     executes_external_pdf_tools: false
     * }
     */
    private function convertMainPoolCleanupPlan(int $totalProcesses, array $modelHandoff): array
    {
        $reached = $totalProcesses >= 1;

        return [
            'source' => 'convert.py pool worker_exit and model_lst cleanup boundary',
            'order' => 'after_pool_imap_before_del_model_lst',
            'cleanup_reached' => $reached,
            'blocked_by' => $reached ? null : 'pool-process-count-failed',
            'pool_imap_completed_required' => true,
            'pool_imap_reached' => $reached,
            'worker_handler_terminate_assignment' => 'pool._worker_handler.terminate = worker_exit',
            'worker_handler_terminate_override_reached' => $reached,
            'worker_exit_function' => 'worker_exit',
            'worker_exit_deletes_global_model_refs' => true,
            'worker_exit_delete_statement' => 'del model_refs',
            'model_list_delete_reached' => $reached,
            'model_list_delete_statement' => 'del model_lst',
            'parent_model_list_loaded' => $reached && (bool) ($modelHandoff['main_load_all_models'] ?? false),
            'worker_init_argument' => $reached && is_string($modelHandoff['worker_init_argument'] ?? null)
                ? $modelHandoff['worker_init_argument']
                : null,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    private function isJsonMetadataLoadFailure(InvalidArgumentException $exception): bool
    {
        return $exception->getPrevious() instanceof JsonException;
    }

    /**
     * @return array{
     *     source: string,
     *     order: string,
     *     requested_start_method: string,
     *     start_method_reached: bool,
     *     start_method_success: bool,
     *     start_method_already_set: bool,
     *     total_processes_computed_before_spawn: int,
     *     error_boundary: string|null,
     *     error_class: string|null,
     *     error_message: string|null,
     *     blocks_model_handoff: bool,
     *     blocks_conversion_summary: bool,
     *     blocks_task_args: bool,
     *     blocks_pool_launch: bool,
     *     blocked_by: string|null,
     *     executes_multiprocessing: false
     * }
     */
    private function convertMainSpawnStartMethodPlan(
        ?string $blockedBy = null,
        bool $alreadySet = false,
        int $totalProcesses = 0
    ): array {
        $reached = $blockedBy === null;
        $failed = $reached && $alreadySet;

        return [
            'source' => 'convert.py torch.multiprocessing set_start_method boundary',
            'order' => 'after_metadata_before_model_handoff',
            'requested_start_method' => 'spawn',
            'start_method_reached' => $reached,
            'start_method_success' => $reached && !$failed,
            'start_method_already_set' => $reached && $alreadySet,
            'total_processes_computed_before_spawn' => $reached ? $totalProcesses : 0,
            'error_boundary' => $failed ? 'spawn-start-method-failed' : null,
            'error_class' => $failed ? 'RuntimeError' : null,
            'error_message' => $failed
                ? 'Set start method to spawn twice. This may be a temporary issue with the script. Please try running it again.'
                : null,
            'blocks_model_handoff' => $failed,
            'blocks_conversion_summary' => $failed,
            'blocks_task_args' => $failed,
            'blocks_pool_launch' => $failed,
            'blocked_by' => $blockedBy,
            'executes_multiprocessing' => false,
        ];
    }

    /**
     * @return array{
     *     source: string,
     *     order: string,
     *     model_handoff_reached: bool,
     *     blocked_by: string|null,
     *     torch_device: string|null,
     *     torch_device_model: string|null,
     *     uses_mps_no_shared_memory_branch: bool,
     *     main_load_all_models: bool,
     *     share_memory_before_pool: bool,
     *     model_share_memory_loop_reached: bool,
     *     worker_init_argument: string|null,
     *     worker_loads_models_when_init_arg_null: bool,
     *     warning: string|null,
     *     native_plan_loads_models: false,
     *     upstream_model_execution_required: bool,
     *     executes_python_or_models: false
     * }
     */
    private function convertMainModelHandoffPlan(
        ?string $torchDevice,
        ?string $torchDeviceModel,
        ?string $blockedBy = null
    ): array {
        $reached = $blockedBy === null;
        $normalizedDevice = $torchDevice === null ? null : strtolower(trim($torchDevice));
        $normalizedDeviceModel = $torchDeviceModel === null ? null : strtolower(trim($torchDeviceModel));
        $usesMps = $reached && ($normalizedDevice === 'mps' || $normalizedDeviceModel === 'mps');

        return [
            'source' => 'convert.py settings.TORCH_DEVICE model handoff',
            'order' => 'after_spawn_start_method_before_conversion_summary',
            'model_handoff_reached' => $reached,
            'blocked_by' => $blockedBy,
            'torch_device' => $torchDevice,
            'torch_device_model' => $torchDeviceModel,
            'uses_mps_no_shared_memory_branch' => $usesMps,
            'main_load_all_models' => $reached && !$usesMps,
            'share_memory_before_pool' => $reached && !$usesMps,
            'model_share_memory_loop_reached' => $reached && !$usesMps,
            'worker_init_argument' => !$reached || $usesMps ? null : 'model_lst',
            'worker_loads_models_when_init_arg_null' => $usesMps,
            'warning' => $usesMps
                ? "Cannot use MPS with torch multiprocessing share_memory. This will make things less memory efficient. If you want to share memory, you have to use CUDA or CPU.\nSet the TORCH_DEVICE environment variable to change the device."
                : null,
            'native_plan_loads_models' => false,
            'upstream_model_execution_required' => $reached,
            'executes_python_or_models' => false,
        ];
    }

    /**
     * @return array{
     *     source: string,
     *     template: string,
     *     summary_reached: bool,
     *     message_line: string|null,
     *     selected_pdf_count: int,
     *     chunk_display_index: int|null,
     *     num_chunks: int|null,
     *     total_processes: int,
     *     out_folder: string|null,
     *     emission_order: string,
     *     emitted_before_task_args: bool,
     *     emitted_before_pool_launch: bool,
     *     blocked_by: string|null
     * }
     */
    private function conversionSummaryPlan(
        int $selectedCount,
        int $chunkIndex,
        int $numChunks,
        int $totalProcesses,
        string $outFolder,
        ?string $blockedBy = null
    ): array {
        $reached = $blockedBy === null;
        $displayChunk = $chunkIndex + 1;
        $template = 'Converting {len(files_to_convert)} pdfs in chunk {chunk_idx + 1}/{num_chunks} with {total_processes} processes, and storing in {out_folder}';
        $message = null;
        if ($reached) {
            $message = sprintf(
                'Converting %d pdfs in chunk %d/%d with %d processes, and storing in %s',
                $selectedCount,
                $displayChunk,
                $numChunks,
                $totalProcesses,
                $outFolder
            );
        }

        return [
            'source' => 'convert.py stdout conversion summary',
            'template' => $template,
            'summary_reached' => $reached,
            'message_line' => $message,
            'selected_pdf_count' => $reached ? $selectedCount : 0,
            'chunk_display_index' => $reached ? $displayChunk : null,
            'num_chunks' => $reached ? $numChunks : null,
            'total_processes' => $reached ? $totalProcesses : 0,
            'out_folder' => $reached ? $outFolder : null,
            'emission_order' => 'after_model_handoff_before_task_args',
            'emitted_before_task_args' => $reached,
            'emitted_before_pool_launch' => $reached,
            'blocked_by' => $blockedBy,
        ];
    }

    private function runtimeMainPreflightExceptionBoundary(InvalidArgumentException $exception): string
    {
        $message = $exception->getMessage();
        if (str_contains($message, 'Batch input folder does not exist')
            || str_contains($message, 'Batch input folder is not a directory')
            || str_contains($message, 'Batch input folder is not readable')
            || str_contains($message, 'Batch input folder cannot be listed')) {
            return 'input-folder-list-failed';
        }
        if (str_contains($message, 'Batch chunk count must be at least one')) {
            return 'chunk-files-failed';
        }
        if (str_contains($message, 'Batch metadata file')) {
            return 'metadata-file-load-failed';
        }

        return 'runtime-main-preflight-failed';
    }

    private function runtimeMainPreflightExceptionClass(string $errorBoundary, string $absoluteInputFolder): string
    {
        if ($errorBoundary === 'input-folder-list-failed') {
            if (!file_exists($absoluteInputFolder)) {
                return 'FileNotFoundError';
            }
            if (!is_dir($absoluteInputFolder)) {
                return 'NotADirectoryError';
            }
            if (!is_readable($absoluteInputFolder)) {
                return 'PermissionError';
            }

            return 'OSError';
        }
        if ($errorBoundary === 'chunk-files-failed') {
            return 'ZeroDivisionError';
        }

        return InvalidArgumentException::class;
    }

    private function runtimeMainPreflightUpstreamErrorMessage(
        string $errorBoundary,
        string $absoluteInputFolder,
        string $fallback
    ): string {
        if ($errorBoundary !== 'input-folder-list-failed') {
            return $fallback;
        }

        if (file_exists($absoluteInputFolder)) {
            if (is_dir($absoluteInputFolder) && !is_readable($absoluteInputFolder)) {
                return "[Errno 13] Permission denied: '" . $absoluteInputFolder . "'";
            }
            if (is_dir($absoluteInputFolder)) {
                return "[Errno 5] Input/output error: '" . $absoluteInputFolder . "'";
            }

            return "[Errno 20] Not a directory: '" . $absoluteInputFolder . "'";
        }

        return "[Errno 2] No such file or directory: '" . $absoluteInputFolder . "'";
    }

    /**
     * @return list<string>
     */
    private function runtimeMainPreflightBlockedStages(string $errorBoundary): array
    {
        if ($errorBoundary === 'input-folder-list-failed') {
            return [
                'makedirs_output_exist_ok',
                'chunk_files',
                'load_metadata_file',
                'set_spawn_start_method',
                'prepare_model_handoff',
                'print_conversion_summary',
                'build_task_args',
                'pool_imap_process_single_pdf',
            ];
        }
        if ($errorBoundary === 'chunk-files-failed') {
            return [
                'load_metadata_file',
                'set_spawn_start_method',
                'prepare_model_handoff',
                'print_conversion_summary',
                'build_task_args',
                'pool_imap_process_single_pdf',
            ];
        }

        return [
            'set_spawn_start_method',
            'prepare_model_handoff',
            'print_conversion_summary',
            'build_task_args',
            'pool_imap_process_single_pdf',
        ];
    }

    /**
     * @param list<string> $argv
     * @param list<string> $missingRequiredArguments
     * @return array<string, mixed>
     */
    private function runtimeMainArgparseErrorPlan(
        array $argv,
        string $message,
        ?string $errorArgument = null,
        array $missingRequiredArguments = []
    ): array {
        return [
            'schema' => 'markerpdf.convert_main_argparse_preflight.v1',
            'source' => 'sddai/markerPDF convert.py::main argparse.ArgumentParser.parse_args',
            'parser' => $this->runtimeMainArgparseParserPlan(),
            'argv' => $argv,
            'preflight_order' => [
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
            ],
            'parse_args' => [
                'source' => 'argparse.ArgumentParser.parse_args',
                'order' => 'after_configure_logging_before_abspath_input_output',
                'parse_args_reached' => true,
                'parse_args_success' => false,
                'exit_code' => 2,
                'error_boundary' => 'argparse-system-exit',
                'error_class' => 'SystemExit',
                'error_argument' => $errorArgument,
                'error_message' => $message,
                'missing_required_arguments' => $missingRequiredArguments,
                'filesystem_touched_before_error' => false,
                'blocks_runtime_preflight' => true,
            ],
            'arguments' => null,
            'semantic_boundaries' => [
                'input_folder_exists_checked_by_argparse' => false,
                'output_folder_exists_checked_by_argparse' => false,
                'filesystem_touched_before_error' => false,
                'metadata_file_read_before_error' => false,
                'model_handoff_reached_before_error' => false,
            ],
            'blocked_by' => 'parse_args',
            'blocked_stages' => $this->runtimeMainArgparseBlockedStages(),
            'next_stage' => null,
            'review_only' => true,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @return array<string, array{key: string, type: string}>
     */
    private function runtimeMainArgparseOptionDefinitions(): array
    {
        return [
            '--chunk_idx' => ['key' => 'chunk_idx', 'type' => 'int'],
            '--num_chunks' => ['key' => 'num_chunks', 'type' => 'int'],
            '--max' => ['key' => 'max', 'type' => 'int'],
            '--workers' => ['key' => 'workers', 'type' => 'int'],
            '--metadata_file' => ['key' => 'metadata_file', 'type' => 'str'],
            '--min_length' => ['key' => 'min_length', 'type' => 'int'],
        ];
    }

    /**
     * @param list<string> $optionNames
     * @return array{option: string, error: string|null}
     */
    private function runtimeMainArgparseResolveOptionName(string $token, array $optionNames): array
    {
        if (in_array($token, $optionNames, true)) {
            return ['option' => $token, 'error' => null];
        }

        $matches = array_values(array_filter(
            $optionNames,
            static fn (string $optionName): bool => str_starts_with($optionName, $token)
        ));

        if (count($matches) === 1) {
            return ['option' => $matches[0], 'error' => null];
        }
        if (count($matches) > 1) {
            return [
                'option' => $token,
                'error' => 'ambiguous option: ' . $token . ' could match ' . implode(', ', $matches),
            ];
        }

        return ['option' => $token, 'error' => 'unrecognized arguments: ' . $token];
    }

    /**
     * @return array<string, mixed>
     */
    private function runtimeMainArgparseParserPlan(): array
    {
        return [
            'description' => 'Convert multiple pdfs to markdown.',
            'positionals' => [
                'in_folder' => 'Input folder with pdfs.',
                'out_folder' => 'Output folder',
            ],
            'options' => [
                '--chunk_idx' => ['type' => 'int', 'default' => 0, 'dest' => 'chunk_idx'],
                '--num_chunks' => ['type' => 'int', 'default' => 1, 'dest' => 'num_chunks'],
                '--max' => ['type' => 'int', 'default' => null, 'dest' => 'max'],
                '--workers' => ['type' => 'int', 'default' => 5, 'dest' => 'workers'],
                '--metadata_file' => ['type' => 'str', 'default' => null, 'dest' => 'metadata_file'],
                '--min_length' => ['type' => 'int', 'default' => null, 'dest' => 'min_length'],
            ],
            'allow_abbrev' => true,
            'error_exit_code' => 2,
        ];
    }

    /**
     * @return list<string>
     */
    private function runtimeMainArgparseBlockedStages(): array
    {
        return [
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
        ];
    }

    /**
     * @param array<mixed> $argv
     * @return list<string>
     */
    private function normalizeRuntimeArgv(array $argv): array
    {
        $tokens = [];
        foreach (array_values($argv) as $token) {
            if (is_bool($token)) {
                $tokens[] = $token ? '1' : '0';
                continue;
            }
            if (!is_string($token) && !is_int($token) && !is_float($token)) {
                throw new InvalidArgumentException('convert.py argv tokens must be scalar CLI values.');
            }

            $tokens[] = (string) $token;
        }

        return $tokens;
    }

    private function runtimeMainArgparseIntegerValue(string $value): ?int
    {
        $trimmed = trim($value);
        if (preg_match('/^[+-]?\d+$/', $trimmed) !== 1) {
            return null;
        }

        return (int) $trimmed;
    }

    private function filesystemPathType(string $path): string
    {
        if (is_dir($path)) {
            return 'directory';
        }
        if (is_file($path)) {
            return 'file';
        }
        if (file_exists($path)) {
            return 'other';
        }

        return 'missing';
    }

    private function percentComplete(int $completed, int $total): float
    {
        if ($total === 0) {
            return 100.0;
        }

        return round(($completed / $total) * 100, 4);
    }

    private function pythonTruthyInteger(?int $value): bool
    {
        return $value !== null && $value !== 0;
    }

    private function pythonTruthyMetadataValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        if (is_string($value)) {
            return $value !== '';
        }
        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }

    /**
     * @param list<string> $basenames
     * @return list<string>
     */
    private function nonPdfBasenames(array $basenames): array
    {
        return array_values(array_filter(
            $basenames,
            fn (string $basename): bool => !$this->hasPdfExtension($basename)
        ));
    }

    private function hasPdfExtension(string $basename): bool
    {
        return strtolower(pathinfo($basename, PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * @param list<string> $items
     * @return list<string>
     */
    private function pythonSlice(array $items, int $start, int $end): array
    {
        [$start, $end] = $this->pythonSliceBounds($start, $end, count($items));
        if ($end <= $start) {
            return [];
        }

        return array_values(array_slice($items, $start, $end - $start));
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function pythonSliceBounds(int $start, int $end, int $count): array
    {
        return [
            $this->pythonSliceIndex($start, $count),
            $this->pythonSliceIndex($end, $count),
        ];
    }

    private function pythonSliceIndex(int $index, int $count): int
    {
        if ($index < 0) {
            $index += $count;
        }
        if ($index < 0) {
            return 0;
        }
        if ($index > $count) {
            return $count;
        }

        return $index;
    }

    private function absolutePath(string $path): string
    {
        $real = realpath($path);
        if (is_string($real)) {
            return $real;
        }

        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return rtrim($path, DIRECTORY_SEPARATOR);
        }

        return rtrim((string) getcwd(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * @param list<array<string, mixed>> $results
     */
    private function convertedCount(array $results): int
    {
        return count(array_filter($results, static fn (array $result): bool => ($result['status'] ?? '') === 'converted'));
    }

    /**
     * @param list<array<string, mixed>> $results
     */
    private function skippedCount(array $results): int
    {
        return count(array_filter($results, static fn (array $result): bool => str_starts_with((string) ($result['status'] ?? ''), 'skipped')));
    }

    /**
     * @param list<array<string, mixed>> $results
     */
    private function errorCount(array $results): int
    {
        return count(array_filter($results, static fn (array $result): bool => ($result['status'] ?? '') === 'error'));
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function result(string $status, string $filepath, array $extra = []): array
    {
        return ['status' => $status, 'filepath' => $filepath] + $extra;
    }
}
