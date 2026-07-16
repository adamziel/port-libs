<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use JsonException;
use stdClass;
use Throwable;

final class BatchConverter
{
    private const CONVERT_SINGLE_MODEL_SLOT_COUNT = 6;

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
     * Runtime mirror of convert.py's chunk math after argparse admission.
     *
     * argparse accepts negative --num_chunks values. Python then computes a
     * negative chunk size and applies normal slice bounds; only zero raises at
     * this stage. Keep the stricter public chunkFiles() helper unchanged for
     * native callers that are not mirroring convert.py::main exactly.
     *
     * @param list<string> $files
     * @return array{selected_files: list<string>, chunk_size: int, start_index: int, end_index: int, python_slice_start_index: int, python_slice_end_index: int}
     */
    private function runtimeChunkSelectionPlan(array $files, int $chunkIndex, int $numChunks, ?int $maxFiles): array
    {
        if ($numChunks === 0) {
            throw new InvalidArgumentException('Batch chunk count must be at least one.');
        }

        $chunkSize = (int) ceil(count($files) / $numChunks);
        $startIndex = $chunkIndex * $chunkSize;
        $endIndex = $startIndex + $chunkSize;
        [$pythonSliceStartIndex, $pythonSliceEndIndex] = $this->pythonSliceBounds(
            $startIndex,
            $endIndex,
            count($files)
        );

        $selected = $chunkSize === 0
            ? []
            : $this->pythonSlice($files, $startIndex, $endIndex);
        if ($this->pythonTruthyInteger($maxFiles)) {
            $selected = array_slice($selected, 0, (int) $maxFiles);
        }

        return [
            'selected_files' => array_values($selected),
            'chunk_size' => $chunkSize,
            'start_index' => $startIndex,
            'end_index' => $endIndex,
            'python_slice_start_index' => $pythonSliceStartIndex,
            'python_slice_end_index' => $pythonSliceEndIndex,
        ];
    }

    /**
     * @param array{filepath: string, out_folder: string, metadata?: mixed, min_length?: int|null} $task
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
        $optionOccurrences = [
            'chunk_idx' => 0,
            'num_chunks' => 0,
            'max' => 0,
            'workers' => 0,
            'metadata_file' => 0,
            'min_length' => 0,
        ];
        $optionValueHistory = [];
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

                $resolvedOption = $this->runtimeMainArgparseResolveOptionName($optionName, array_keys($definitions), $token);
                if ($resolvedOption['error'] !== null) {
                    return $this->runtimeMainArgparseErrorPlan(
                        $tokens,
                        $resolvedOption['error'],
                        $token
                    );
                }
                $optionName = $resolvedOption['option'];

                if (!$valueProvided) {
                    $nextIndex = $index + 1;
                    if ($nextIndex >= $count || $this->runtimeMainArgparseMissingOptionValue($tokens[$nextIndex])) {
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
                $previousValue = $options[$key];
                if ($definition['type'] === 'int') {
                    $integer = $this->runtimeMainArgparseIntegerValue((string) $value);
                    if ($integer === null) {
                        return $this->runtimeMainArgparseErrorPlan(
                            $tokens,
                            "argument {$optionName}: invalid int value: '" . (string) $value . "'",
                            $optionName
                        );
                    }

                    $parsedValue = $integer;
                } else {
                    $parsedValue = (string) $value;
                }

                $optionOccurrences[$key]++;
                $optionValueHistory[] = [
                    'option' => $optionName,
                    'key' => $key,
                    'value' => $parsedValue,
                    'value_type' => $definition['type'],
                    'occurrence' => $optionOccurrences[$key],
                    'previous_value' => $optionOccurrences[$key] > 1 ? $previousValue : null,
                    'overrides_previous' => $optionOccurrences[$key] > 1,
                ];

                $options[$key] = $parsedValue;
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

        $metadataFileTruthy = is_string($options['metadata_file']) && $options['metadata_file'] !== '';
        $repeatedOptionCounts = array_filter(
            $optionOccurrences,
            static fn (int $count): bool => $count > 1
        );
        $repeatedOptions = array_keys($repeatedOptionCounts);
        $endOfOptionsBoundary = $this->runtimeMainArgparseEndOfOptionsBoundary($tokens);

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
                'end_of_options_terminator_seen' => $endOfOptionsBoundary['terminator_seen'],
                'end_of_options_terminator_index' => $endOfOptionsBoundary['terminator_index'],
                'option_like_tokens_after_terminator_are_positionals' => $endOfOptionsBoundary['option_like_tokens_after_terminator_are_positionals'],
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
                'option_occurrences' => $optionOccurrences,
                'option_value_history' => $optionValueHistory,
                'repeated_options' => $repeatedOptions,
                'repeated_option_counts' => $repeatedOptionCounts,
                'last_occurrence_wins' => $repeatedOptions !== [],
                'argfile_boundary' => $this->runtimeMainArgparseAtFileBoundary($tokens),
                'end_of_options_boundary' => $endOfOptionsBoundary,
            ],
            'semantic_boundaries' => [
                'input_folder_exists_checked_by_argparse' => false,
                'output_folder_exists_checked_by_argparse' => false,
                'num_chunks_less_than_one_deferred_to_chunk_files' => $options['num_chunks'] < 1,
                'negative_chunk_idx_allowed_by_argparse' => $options['chunk_idx'] < 0,
                'negative_max_allowed_by_argparse' => $options['max'] !== null && $options['max'] < 0,
                'workers_less_than_one_deferred_to_pool_creation' => $options['workers'] < 1,
                'negative_min_length_allowed_by_argparse' => $options['min_length'] !== null && $options['min_length'] < 0,
                'metadata_file_read_deferred_until_after_chunk_files' => $metadataFileTruthy,
                'metadata_file_truthy_for_json_load' => $metadataFileTruthy,
                'empty_metadata_file_skips_json_load' => $options['metadata_file'] === '',
                'repeated_options_last_occurrence_wins' => $repeatedOptions !== [],
                'fromfile_prefix_chars_configured' => false,
                'at_file_tokens_expand_before_parse' => false,
                'at_prefixed_tokens_seen' => $this->runtimeMainArgparseAtFileTokens($tokens) !== [],
                'at_prefixed_tokens_are_literal_cli_values' => $this->runtimeMainArgparseAtFileTokens($tokens) !== [],
                'end_of_options_terminator_supported' => true,
                'end_of_options_separator_touches_filesystem' => false,
                'option_like_positionals_allowed_after_terminator' => $endOfOptionsBoundary['option_like_tokens_after_terminator_are_positionals'],
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
        bool $spawnStartMethodAlreadySet = false,
        ?array $modelSlots = null
    ): array {
        $absoluteInputFolder = $this->absolutePath($inputFolder);
        $absoluteOutputFolder = $this->absolutePath($outputFolder);
        $pathResolution = $this->runtimeInputOutputPathPlan(
            $inputFolder,
            $outputFolder,
            $absoluteInputFolder,
            $absoluteOutputFolder
        );
        $inputListing = $this->inputDirectoryListing($absoluteInputFolder, preserveDirectoryOrder: true);
        $inputFiles = $inputListing['file_paths'];
        $outputCreation = $this->outputFolderCreationPlan($absoluteOutputFolder);
        $absoluteMetadataFile = $metadataFile === null || $metadataFile === ''
            ? null
            : $this->absolutePath($metadataFile);
        $metadataPath = $this->runtimeMetadataFilePathPlan(
            $metadataFile,
            $absoluteMetadataFile,
            $absoluteInputFolder,
            $absoluteOutputFolder
        );

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
                    'path_resolution' => $pathResolution,
                    ...$outputCreation,
                ],
                'input_listing' => [
                    'source' => 'os.listdir + os.path.isfile',
                    'entry_order_source' => $inputListing['entry_order_source'],
                    'sort_applied_before_chunking' => $inputListing['sort_applied_before_chunking'],
                    'preserves_os_listdir_order' => $inputListing['preserves_os_listdir_order'],
                    'entry_count' => count($inputListing['entry_basenames']),
                    'entry_basenames' => $inputListing['entry_basenames'],
                    'file_count' => count($inputListing['file_basenames']),
                    'file_basenames' => $inputListing['file_basenames'],
                    'skipped_non_file_count' => count($inputListing['skipped_non_file_basenames']),
                    'skipped_non_file_basenames' => $inputListing['skipped_non_file_basenames'],
                    'skipped_non_file_records' => $inputListing['skipped_non_file_records'],
                    'special_file_basenames' => $inputListing['special_file_basenames'],
                    'fifo_basenames' => $inputListing['fifo_basenames'],
                    'symlink_filter' => 'os.path.isfile follows regular-file symlinks and excludes directory or broken symlinks',
                    'symlink_basenames' => $inputListing['symlink_basenames'],
                    'file_symlink_basenames' => $inputListing['file_symlink_basenames'],
                    'skipped_symlink_basenames' => $inputListing['skipped_symlink_basenames'],
                    'broken_symlink_basenames' => $inputListing['broken_symlink_basenames'],
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
                    'negative_num_chunks_active' => $numChunks < 0,
                    'num_chunks_less_than_one_active' => $numChunks < 1,
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
                    ...$metadataPath,
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

        $chunkPlan = $this->runtimeChunkSelectionPlan($inputFiles, $chunkIndex, $numChunks, $maxFiles);
        $selectedFiles = $chunkPlan['selected_files'];
        $selectedFilenames = array_map(static fn (string $filepath): string => basename($filepath), $selectedFiles);
        $chunkSize = $chunkPlan['chunk_size'];
        $startIndex = $chunkPlan['start_index'];
        $endIndex = $chunkPlan['end_index'];
        $pythonSliceStartIndex = $chunkPlan['python_slice_start_index'];
        $pythonSliceEndIndex = $chunkPlan['python_slice_end_index'];

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
                    'metadata_top_level_key_order' => array_map(
                        static fn (int|string $filename): string => (string) $filename,
                        array_keys($metadataByFilename)
                    ),
                    'metadata_duplicate_keys' => [],
                    'metadata_duplicate_key_counts' => [],
                    'metadata_duplicate_key_last_value_types' => [],
                ]
                : $this->loadRuntimeMetadataFile($absoluteMetadataFile);
        } catch (InvalidArgumentException $exception) {
            $metadataErrorBoundary = $this->isJsonMetadataLoadFailure($exception)
                ? 'metadata-file-json-load-failed'
                : $this->runtimeMainPreflightExceptionBoundary($exception);
            if (!in_array($metadataErrorBoundary, ['metadata-file-load-failed', 'metadata-file-json-load-failed'], true)) {
                throw $exception;
            }
            $metadataErrorClass = $metadataErrorBoundary === 'metadata-file-json-load-failed'
                ? 'JSONDecodeError'
                : $this->runtimeMetadataFileLoadExceptionClass($absoluteMetadataFile);
            $metadataErrorMessage = $metadataErrorBoundary === 'metadata-file-json-load-failed'
                ? $exception->getMessage()
                : $this->runtimeMetadataFileLoadUpstreamErrorMessage($absoluteMetadataFile, $exception->getMessage());

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
                    'path_resolution' => $pathResolution,
                    ...$outputCreation,
                ],
                'input_listing' => [
                    'source' => 'os.listdir + os.path.isfile',
                    'entry_order_source' => $inputListing['entry_order_source'],
                    'sort_applied_before_chunking' => $inputListing['sort_applied_before_chunking'],
                    'preserves_os_listdir_order' => $inputListing['preserves_os_listdir_order'],
                    'entry_count' => count($inputListing['entry_basenames']),
                    'entry_basenames' => $inputListing['entry_basenames'],
                    'file_count' => count($inputListing['file_basenames']),
                    'file_basenames' => $inputListing['file_basenames'],
                    'skipped_non_file_count' => count($inputListing['skipped_non_file_basenames']),
                    'skipped_non_file_basenames' => $inputListing['skipped_non_file_basenames'],
                    'skipped_non_file_records' => $inputListing['skipped_non_file_records'],
                    'special_file_basenames' => $inputListing['special_file_basenames'],
                    'fifo_basenames' => $inputListing['fifo_basenames'],
                    'symlink_filter' => 'os.path.isfile follows regular-file symlinks and excludes directory or broken symlinks',
                    'symlink_basenames' => $inputListing['symlink_basenames'],
                    'file_symlink_basenames' => $inputListing['file_symlink_basenames'],
                    'skipped_symlink_basenames' => $inputListing['skipped_symlink_basenames'],
                    'broken_symlink_basenames' => $inputListing['broken_symlink_basenames'],
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
                    'negative_num_chunks_active' => $numChunks < 0,
                    'num_chunks_less_than_one_active' => $numChunks < 1,
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
                    ...$metadataPath,
                    'metadata_load_reached' => true,
                    'metadata_load_success' => false,
                    'metadata_error_boundary' => $metadataErrorBoundary,
                    'metadata_error_class' => $metadataErrorClass,
                    'metadata_error_message' => $metadataErrorMessage,
                    'metadata_json_type' => null,
                    'metadata_get_available' => false,
                    'metadata_shape_error_boundary' => null,
                    'metadata_shape_error_class' => null,
                    'metadata_shape_error_message' => null,
                    'metadata_value_types' => [],
                    'metadata_value_review' => $this->runtimeMetadataValueReview([], [], [], $metadataErrorBoundary),
                    'metadata_filenames' => [],
                    'selected_metadata_filenames' => [],
                    'missing_metadata_filenames' => [],
                ],
                'spawn_start_method' => $this->convertMainSpawnStartMethodPlan(
                    $metadataErrorBoundary,
                    $spawnStartMethodAlreadySet
                ),
                'model_handoff' => $this->convertMainModelHandoffPlan(
                    $torchDevice,
                    $torchDeviceModel,
                    $metadataErrorBoundary
                ),
                'worker_pool' => [
                    'requested_workers' => $workers,
                    'total_processes' => 0,
                    'pool_launchable' => false,
                    'pool_error_boundary' => $metadataErrorBoundary,
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
                    $metadataErrorBoundary
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
        $metadataFilenames = array_map(static fn (int|string $filename): string => (string) $filename, array_keys($runtimeMetadata));
        sort($metadataFilenames, SORT_STRING);
        $selectedMetadataFilenames = array_values(array_filter(
            $selectedFilenames,
            static fn (string $filename): bool => array_key_exists($filename, $runtimeMetadata)
        ));
        $missingMetadataFilenames = array_values(array_filter(
            $selectedFilenames,
            static fn (string $filename): bool => !array_key_exists($filename, $runtimeMetadata)
        ));
        $metadataBasenameLookupReview = $this->runtimeMetadataBasenameLookupReview(
            $selectedFilenames,
            $runtimeMetadataPlan
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
                    'path_resolution' => $pathResolution,
                    ...$outputCreation,
                ],
                'input_listing' => [
                    'source' => 'os.listdir + os.path.isfile',
                    'entry_order_source' => $inputListing['entry_order_source'],
                    'sort_applied_before_chunking' => $inputListing['sort_applied_before_chunking'],
                    'preserves_os_listdir_order' => $inputListing['preserves_os_listdir_order'],
                    'entry_count' => count($inputListing['entry_basenames']),
                    'entry_basenames' => $inputListing['entry_basenames'],
                    'file_count' => count($inputListing['file_basenames']),
                    'file_basenames' => $inputListing['file_basenames'],
                    'skipped_non_file_count' => count($inputListing['skipped_non_file_basenames']),
                    'skipped_non_file_basenames' => $inputListing['skipped_non_file_basenames'],
                    'skipped_non_file_records' => $inputListing['skipped_non_file_records'],
                    'special_file_basenames' => $inputListing['special_file_basenames'],
                    'fifo_basenames' => $inputListing['fifo_basenames'],
                    'symlink_filter' => 'os.path.isfile follows regular-file symlinks and excludes directory or broken symlinks',
                    'symlink_basenames' => $inputListing['symlink_basenames'],
                    'file_symlink_basenames' => $inputListing['file_symlink_basenames'],
                    'skipped_symlink_basenames' => $inputListing['skipped_symlink_basenames'],
                    'broken_symlink_basenames' => $inputListing['broken_symlink_basenames'],
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
                    'negative_num_chunks_active' => $numChunks < 0,
                    'num_chunks_less_than_one_active' => $numChunks < 1,
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
                    ...$metadataPath,
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
                    'metadata_top_level_key_order' => $runtimeMetadataPlan['metadata_top_level_key_order'],
                    'metadata_duplicate_key_review' => $this->runtimeMetadataDuplicateKeyReview(
                        $selectedFilenames,
                        $runtimeMetadataPlan
                    ),
                    'metadata_basename_lookup_review' => $metadataBasenameLookupReview,
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

        $modelHandoff = $this->convertMainModelHandoffPlan($torchDevice, $torchDeviceModel, modelSlots: $modelSlots);
        $modelHandoffErrorBoundary = $this->convertMainModelHandoffErrorBoundary($modelHandoff);
        if ($modelHandoffErrorBoundary !== null) {
            $metadataValueReview = $this->runtimeMetadataValueReview(
                $selectedFilenames,
                $runtimeMetadata,
                $runtimeMetadataPlan['metadata_value_types']
            );

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
                    'path_resolution' => $pathResolution,
                    ...$outputCreation,
                ],
                'input_listing' => [
                    'source' => 'os.listdir + os.path.isfile',
                    'entry_order_source' => $inputListing['entry_order_source'],
                    'sort_applied_before_chunking' => $inputListing['sort_applied_before_chunking'],
                    'preserves_os_listdir_order' => $inputListing['preserves_os_listdir_order'],
                    'entry_count' => count($inputListing['entry_basenames']),
                    'entry_basenames' => $inputListing['entry_basenames'],
                    'file_count' => count($inputListing['file_basenames']),
                    'file_basenames' => $inputListing['file_basenames'],
                    'skipped_non_file_count' => count($inputListing['skipped_non_file_basenames']),
                    'skipped_non_file_basenames' => $inputListing['skipped_non_file_basenames'],
                    'skipped_non_file_records' => $inputListing['skipped_non_file_records'],
                    'special_file_basenames' => $inputListing['special_file_basenames'],
                    'fifo_basenames' => $inputListing['fifo_basenames'],
                    'symlink_filter' => 'os.path.isfile follows regular-file symlinks and excludes directory or broken symlinks',
                    'symlink_basenames' => $inputListing['symlink_basenames'],
                    'file_symlink_basenames' => $inputListing['file_symlink_basenames'],
                    'skipped_symlink_basenames' => $inputListing['skipped_symlink_basenames'],
                    'broken_symlink_basenames' => $inputListing['broken_symlink_basenames'],
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
                    'input_order_source' => 'os.listdir filesystem order after os.path.isfile',
                    'sorts_before_chunking' => false,
                    'preserves_input_listing_order' => true,
                    'python_slice_start_index' => $pythonSliceStartIndex,
                    'python_slice_end_index' => $pythonSliceEndIndex,
                    'negative_chunk_index_active' => $chunkIndex < 0,
                    'negative_num_chunks_active' => $numChunks < 0,
                    'num_chunks_less_than_one_active' => $numChunks < 1,
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
                    ...$metadataPath,
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
                    'metadata_top_level_key_order' => $runtimeMetadataPlan['metadata_top_level_key_order'],
                    'metadata_duplicate_key_review' => $this->runtimeMetadataDuplicateKeyReview(
                        $selectedFilenames,
                        $runtimeMetadataPlan
                    ),
                    'metadata_basename_lookup_review' => $metadataBasenameLookupReview,
                    'metadata_value_review' => $metadataValueReview,
                    'metadata_filenames' => $metadataFilenames,
                    'selected_metadata_filenames' => $selectedMetadataFilenames,
                    'missing_metadata_filenames' => $missingMetadataFilenames,
                ],
                'spawn_start_method' => $spawnStartMethod,
                'model_handoff' => $modelHandoff,
                'worker_pool' => [
                    'requested_workers' => $workers,
                    'total_processes' => 0,
                    'pool_launchable' => false,
                    'pool_error_boundary' => $modelHandoffErrorBoundary,
                    'start_method' => 'spawn',
                    'process_function' => 'process_single_pdf',
                    'task_args_count' => 0,
                    'task_args' => [],
                    'task_args_error_boundary' => $modelHandoffErrorBoundary,
                    'progress_iterator' => $this->progressIterator(),
                ],
                'console_summary' => $this->conversionSummaryPlan(
                    0,
                    $chunkIndex,
                    $numChunks,
                    0,
                    $absoluteOutputFolder,
                    $modelHandoffErrorBoundary
                ),
                'conversion_boundary' => [
                    'min_length' => $minLength,
                    'per_file_preflight_function' => 'process_single_pdf',
                    'converter_function' => 'convert_single_pdf',
                    'metadata_lookup' => 'metadata.get(os.path.basename(f))',
                    'model_handoff_error_boundary' => $modelHandoffErrorBoundary,
                    'empty_output_policy' => 'print_empty_file_without_save_markdown',
                ],
                'review_only' => true,
                'executes_python_or_models' => false,
                'executes_multiprocessing' => false,
                'executes_external_pdf_tools' => false,
            ];
        }
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
                    'path_resolution' => $pathResolution,
                    ...$outputCreation,
                ],
                'input_listing' => [
                    'source' => 'os.listdir + os.path.isfile',
                    'entry_order_source' => $inputListing['entry_order_source'],
                    'sort_applied_before_chunking' => $inputListing['sort_applied_before_chunking'],
                    'preserves_os_listdir_order' => $inputListing['preserves_os_listdir_order'],
                    'entry_count' => count($inputListing['entry_basenames']),
                    'entry_basenames' => $inputListing['entry_basenames'],
                    'file_count' => count($inputListing['file_basenames']),
                    'file_basenames' => $inputListing['file_basenames'],
                    'skipped_non_file_count' => count($inputListing['skipped_non_file_basenames']),
                    'skipped_non_file_basenames' => $inputListing['skipped_non_file_basenames'],
                    'skipped_non_file_records' => $inputListing['skipped_non_file_records'],
                    'special_file_basenames' => $inputListing['special_file_basenames'],
                    'fifo_basenames' => $inputListing['fifo_basenames'],
                    'symlink_filter' => 'os.path.isfile follows regular-file symlinks and excludes directory or broken symlinks',
                    'symlink_basenames' => $inputListing['symlink_basenames'],
                    'file_symlink_basenames' => $inputListing['file_symlink_basenames'],
                    'skipped_symlink_basenames' => $inputListing['skipped_symlink_basenames'],
                    'broken_symlink_basenames' => $inputListing['broken_symlink_basenames'],
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
                    'negative_num_chunks_active' => $numChunks < 0,
                    'num_chunks_less_than_one_active' => $numChunks < 1,
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
                    ...$metadataPath,
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
                    'metadata_top_level_key_order' => $runtimeMetadataPlan['metadata_top_level_key_order'],
                    'metadata_duplicate_key_review' => $this->runtimeMetadataDuplicateKeyReview(
                        $selectedFilenames,
                        $runtimeMetadataPlan
                    ),
                    'metadata_basename_lookup_review' => $metadataBasenameLookupReview,
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
        $taskArgIdentityReview = $this->runtimeTaskArgIdentityReview($taskArgs, $poolErrorBoundary);
        $processSinglePdfPreflight = $this->runtimeProcessSinglePdfPreflightReview(
            $taskArgs,
            $runtimeMetadataPlan['metadata_value_types'],
            $poolErrorBoundary
        );
        $poolResultDrain = $this->runtimePoolResultDrainReview(
            $taskArgs,
            $processSinglePdfPreflight,
            $poolErrorBoundary
        );
        $consoleSummary = $this->conversionSummaryPlan(
            count($taskArgs),
            $chunkIndex,
            $numChunks,
            $totalProcesses,
            $absoluteOutputFolder
        );
        $emptyTaskQueueModelHandoff = $this->runtimeEmptyTaskQueueModelHandoffReview(
            $taskArgs,
            $poolErrorBoundary,
            $spawnStartMethod,
            $modelHandoff,
            $consoleSummary
        );

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
                'path_resolution' => $pathResolution,
                ...$outputCreation,
            ],
            'input_listing' => [
                'source' => 'os.listdir + os.path.isfile',
                'entry_order_source' => $inputListing['entry_order_source'],
                'sort_applied_before_chunking' => $inputListing['sort_applied_before_chunking'],
                'preserves_os_listdir_order' => $inputListing['preserves_os_listdir_order'],
                'entry_count' => count($inputListing['entry_basenames']),
                'entry_basenames' => $inputListing['entry_basenames'],
                'file_count' => count($inputListing['file_basenames']),
                'file_basenames' => $inputListing['file_basenames'],
                'skipped_non_file_count' => count($inputListing['skipped_non_file_basenames']),
                'skipped_non_file_basenames' => $inputListing['skipped_non_file_basenames'],
                'skipped_non_file_records' => $inputListing['skipped_non_file_records'],
                'special_file_basenames' => $inputListing['special_file_basenames'],
                'fifo_basenames' => $inputListing['fifo_basenames'],
                'symlink_filter' => 'os.path.isfile follows regular-file symlinks and excludes directory or broken symlinks',
                'symlink_basenames' => $inputListing['symlink_basenames'],
                'file_symlink_basenames' => $inputListing['file_symlink_basenames'],
                'skipped_symlink_basenames' => $inputListing['skipped_symlink_basenames'],
                'broken_symlink_basenames' => $inputListing['broken_symlink_basenames'],
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
                'input_order_source' => 'os.listdir filesystem order after os.path.isfile',
                'sorts_before_chunking' => false,
                'preserves_input_listing_order' => true,
                'python_slice_start_index' => $pythonSliceStartIndex,
                'python_slice_end_index' => $pythonSliceEndIndex,
                'negative_chunk_index_active' => $chunkIndex < 0,
                'negative_num_chunks_active' => $numChunks < 0,
                'num_chunks_less_than_one_active' => $numChunks < 1,
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
                ...$metadataPath,
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
                'metadata_top_level_key_order' => $runtimeMetadataPlan['metadata_top_level_key_order'],
                'metadata_duplicate_key_review' => $this->runtimeMetadataDuplicateKeyReview(
                    $selectedFilenames,
                    $runtimeMetadataPlan
                ),
                'metadata_basename_lookup_review' => $metadataBasenameLookupReview,
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
                'pool_context_manager' => $this->convertMainPoolContextManagerPlan($totalProcesses, $modelHandoff),
                'worker_initializer' => $this->convertMainWorkerInitializerPlan($totalProcesses, $modelHandoff),
                'task_arg_identity_review' => $taskArgIdentityReview,
                'empty_task_queue_model_handoff' => $emptyTaskQueueModelHandoff,
                'process_single_pdf_preflight' => $processSinglePdfPreflight,
                'pool_result_drain' => $poolResultDrain,
                'pool_cleanup' => $this->convertMainPoolCleanupPlan($totalProcesses, $modelHandoff),
                'progress_iterator' => $this->progressIterator(),
            ],
            'console_summary' => $consoleSummary,
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
        bool $spawnStartMethodAlreadySet = false,
        ?array $modelSlots = null
    ): array {
        $absoluteInputFolder = $this->absolutePath($inputFolder);
        $absoluteOutputFolder = $this->absolutePath($outputFolder);
        $pathResolution = $this->runtimeInputOutputPathPlan(
            $inputFolder,
            $outputFolder,
            $absoluteInputFolder,
            $absoluteOutputFolder
        );
        $absoluteMetadataFile = $metadataFile === null || $metadataFile === ''
            ? null
            : $this->absolutePath($metadataFile);
        $metadataPath = $this->runtimeMetadataFilePathPlan(
            $metadataFile,
            $absoluteMetadataFile,
            $absoluteInputFolder,
            $absoluteOutputFolder
        );

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
                $spawnStartMethodAlreadySet,
                $modelSlots
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
                    'path_resolution' => $pathResolution,
                    'input_path_exists' => file_exists($absoluteInputFolder),
                    'input_path_type' => $this->filesystemPathType($absoluteInputFolder),
                    'output_folder_creation_reached' => true,
                    'metadata_file' => $absoluteMetadataFile,
                    ...$metadataPath,
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
            $successfulInputListing = null;
            $outputCreation = null;
            if ($errorBoundary !== 'input-folder-list-failed') {
                $successfulInputListing = $this->inputDirectoryListing($absoluteInputFolder, preserveDirectoryOrder: true);
                $outputCreation = $this->outputFolderCreationPlan($absoluteOutputFolder);
            }
            $inputFileCount = is_array($successfulInputListing) ? count($successfulInputListing['file_paths']) : 0;

            return [
                'schema' => 'markerpdf.convert_main_runtime_preflight_error_boundary.v1',
                'source' => 'sddai/markerPDF convert.py::main + os.listdir + os.makedirs + chunk_files error boundary',
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
                    'path_resolution' => $pathResolution,
                    'input_path_exists' => file_exists($absoluteInputFolder),
                    'input_path_type' => $this->filesystemPathType($absoluteInputFolder),
                    'output_folder_creation_reached' => $errorBoundary !== 'input-folder-list-failed',
                    ...($outputCreation ?? [
                        'output_folder_creation_required' => false,
                        'output_folder_creation_blocked' => false,
                    ]),
                    'metadata_file' => $absoluteMetadataFile,
                    ...$metadataPath,
                ],
                'input_listing' => [
                    'source' => 'os.listdir + os.path.isfile',
                    'listing_reached' => $errorBoundary === 'input-folder-list-failed' || $successfulInputListing !== null,
                    'listing_success' => $successfulInputListing !== null,
                    'entry_order_source' => $successfulInputListing['entry_order_source'] ?? null,
                    'sort_applied_before_chunking' => $successfulInputListing['sort_applied_before_chunking'] ?? false,
                    'preserves_os_listdir_order' => $successfulInputListing['preserves_os_listdir_order'] ?? false,
                    'entry_count' => $successfulInputListing === null ? 0 : count($successfulInputListing['entry_basenames']),
                    'entry_basenames' => $successfulInputListing['entry_basenames'] ?? [],
                    'file_count' => $successfulInputListing === null ? 0 : count($successfulInputListing['file_basenames']),
                    'file_basenames' => $successfulInputListing['file_basenames'] ?? [],
                    'skipped_non_file_count' => $successfulInputListing === null ? 0 : count($successfulInputListing['skipped_non_file_basenames']),
                    'skipped_non_file_basenames' => $successfulInputListing['skipped_non_file_basenames'] ?? [],
                    'skipped_non_file_records' => $successfulInputListing['skipped_non_file_records'] ?? [],
                    'special_file_basenames' => $successfulInputListing['special_file_basenames'] ?? [],
                    'fifo_basenames' => $successfulInputListing['fifo_basenames'] ?? [],
                    'symlink_filter' => 'os.path.isfile follows regular-file symlinks and excludes directory or broken symlinks',
                    'symlink_basenames' => $successfulInputListing['symlink_basenames'] ?? [],
                    'file_symlink_basenames' => $successfulInputListing['file_symlink_basenames'] ?? [],
                    'skipped_symlink_basenames' => $successfulInputListing['skipped_symlink_basenames'] ?? [],
                    'broken_symlink_basenames' => $successfulInputListing['broken_symlink_basenames'] ?? [],
                    'file_filter' => 'os.path.isfile',
                    'extension_filter_active' => false,
                    'non_pdf_files_are_task_candidates' => ($successfulInputListing['non_pdf_file_basenames'] ?? []) !== [],
                    'non_pdf_file_basenames' => $successfulInputListing['non_pdf_file_basenames'] ?? [],
                    'error_boundary' => $errorBoundary,
                ],
                'chunking' => [
                    'chunk_index' => $chunkIndex,
                    'num_chunks' => $numChunks,
                    'chunk_size' => 0,
                    'chunk_size_expression' => 'math.ceil(len(files) / args.num_chunks)',
                    'start_index' => 0,
                    'end_index' => 0,
                    'python_slice_start_index' => 0,
                    'python_slice_end_index' => 0,
                    'negative_chunk_index_active' => $chunkIndex < 0,
                    'negative_num_chunks_active' => $numChunks < 0,
                    'num_chunks_less_than_one_active' => $numChunks < 1,
                    'max_files' => $maxFiles,
                    'max_files_limit_active' => $this->pythonTruthyInteger($maxFiles),
                    'input_file_count' => $inputFileCount,
                    'selected_count' => 0,
                    'selected_filenames' => [],
                    'chunking_reached' => $errorBoundary === 'chunk-files-failed',
                    'chunk_error_boundary' => $errorBoundary === 'chunk-files-failed' ? 'chunk-files-failed' : $errorBoundary,
                    'chunk_error_class' => $errorBoundary === 'chunk-files-failed' ? 'ZeroDivisionError' : null,
                    'chunk_error_message' => $errorBoundary === 'chunk-files-failed' ? 'division by zero' : null,
                ],
                'metadata' => [
                    'metadata_file' => $absoluteMetadataFile,
                    ...$metadataPath,
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
     * @param list<array{filepath: string, out_folder: string, metadata: mixed, min_length: int|null}> $taskArgs
     * @return array<string, mixed>
     */
    private function runtimeTaskArgIdentityReview(array $taskArgs, ?string $poolErrorBoundary = null): array
    {
        $records = [];
        $tupleRows = [];
        $recordsByResolvedTarget = [];
        $recordsByFileIdentity = [];
        foreach ($taskArgs as $taskArg) {
            $filepath = (string) $taskArg['filepath'];
            $resolvedTarget = realpath($filepath);
            $logicalResolvedTarget = is_string($resolvedTarget)
                ? (is_link(dirname($filepath)) ? $this->logicalReviewPath($resolvedTarget) : $resolvedTarget)
                : $filepath;
            $stat = @stat($filepath);
            $device = is_array($stat) && array_key_exists('dev', $stat) ? (string) $stat['dev'] : null;
            $inode = is_array($stat) && array_key_exists('ino', $stat) ? (string) $stat['ino'] : null;
            $fileIdentityKey = $device !== null && $inode !== null ? $device . ':' . $inode : null;
            $record = [
                'filename' => basename($filepath),
                'filepath' => $filepath,
                'is_symlink' => is_link($filepath),
                'resolved_target' => $logicalResolvedTarget,
                'resolved_target_available' => is_string($resolvedTarget),
                'device' => $device,
                'inode' => $inode,
                'file_identity_key' => $fileIdentityKey,
                'file_identity_available' => $fileIdentityKey !== null,
            ];
            $records[] = $record;
            $tupleRows[] = [
                $filepath,
                (string) $taskArg['out_folder'],
                $taskArg['metadata'],
                $taskArg['min_length'],
            ];
            $recordsByResolvedTarget[$record['resolved_target']][] = $record;
            if ($fileIdentityKey !== null) {
                $recordsByFileIdentity[$fileIdentityKey][] = $record;
            }
        }

        $duplicateGroups = [];
        $duplicateFilenames = [];
        foreach ($recordsByResolvedTarget as $resolvedTarget => $groupRecords) {
            if (count($groupRecords) < 2) {
                continue;
            }

            $filenames = array_map(static fn (array $record): string => (string) $record['filename'], $groupRecords);
            $filepaths = array_map(static fn (array $record): string => (string) $record['filepath'], $groupRecords);
            $symlinkFilenames = array_values(array_map(
                static fn (array $record): string => (string) $record['filename'],
                array_filter($groupRecords, static fn (array $record): bool => (bool) $record['is_symlink'])
            ));
            $duplicateFilenames = array_merge($duplicateFilenames, $filenames);

            $duplicateGroups[] = [
                'resolved_target' => $resolvedTarget,
                'entry_count' => count($groupRecords),
                'filenames' => $filenames,
                'filepaths' => $filepaths,
                'contains_symlink' => $symlinkFilenames !== [],
                'symlink_filenames' => $symlinkFilenames,
                'queued_separately' => true,
                'deduplicated_by_realpath' => false,
            ];
        }

        $duplicateFileIdentityGroups = [];
        $duplicateFileIdentityFilenames = [];
        $hardlinkFileIdentityGroups = [];
        $hardlinkFileIdentityFilenames = [];
        foreach ($recordsByFileIdentity as $fileIdentityKey => $groupRecords) {
            if (count($groupRecords) < 2) {
                continue;
            }

            $filenames = array_map(static fn (array $record): string => (string) $record['filename'], $groupRecords);
            $filepaths = array_map(static fn (array $record): string => (string) $record['filepath'], $groupRecords);
            $resolvedTargets = array_values(array_unique(array_map(
                static fn (array $record): string => (string) $record['resolved_target'],
                $groupRecords
            )));
            $symlinkFilenames = array_values(array_map(
                static fn (array $record): string => (string) $record['filename'],
                array_filter($groupRecords, static fn (array $record): bool => (bool) $record['is_symlink'])
            ));
            $hardlinkCandidate = count($resolvedTargets) > 1 && $symlinkFilenames === [];

            $duplicateFileIdentityFilenames = array_merge($duplicateFileIdentityFilenames, $filenames);

            $group = [
                'file_identity_key' => $fileIdentityKey,
                'device' => $groupRecords[0]['device'],
                'inode' => $groupRecords[0]['inode'],
                'entry_count' => count($groupRecords),
                'filenames' => $filenames,
                'filepaths' => $filepaths,
                'resolved_targets' => $resolvedTargets,
                'resolved_target_count' => count($resolvedTargets),
                'contains_symlink' => $symlinkFilenames !== [],
                'symlink_filenames' => $symlinkFilenames,
                'hardlink_candidate' => $hardlinkCandidate,
                'queued_separately' => true,
                'deduplicated_by_file_identity' => false,
                'deduplicated_by_inode' => false,
            ];

            $duplicateFileIdentityGroups[] = $group;

            if ($hardlinkCandidate) {
                $hardlinkFileIdentityGroups[] = $group;
                $hardlinkFileIdentityFilenames = array_merge($hardlinkFileIdentityFilenames, $filenames);
            }
        }

        $reviewReached = $taskArgs !== [];

        return [
            'source' => 'convert.py os.listdir/os.path.isfile task tuple identity boundary',
            'order' => 'after_task_args_before_pool_imap',
            'review_reached' => $reviewReached,
            'blocked_by' => $reviewReached ? null : $poolErrorBoundary,
            'pool_error_boundary' => $poolErrorBoundary,
            'task_args_count' => count($taskArgs),
            'task_arg_filenames' => array_map(static fn (array $record): string => (string) $record['filename'], $records),
            'task_arg_tuple_source' => 'task_args = [(f, out_folder, metadata.get(os.path.basename(f)), args.min_length) for f in files_to_convert]',
            'process_single_pdf_unpack' => 'filepath, out_folder, metadata, min_length = args',
            'task_arg_tuple_order' => ['filepath', 'out_folder', 'metadata', 'min_length'],
            'task_arg_tuple_arity' => 4,
            'task_arg_tuple_rows' => $tupleRows,
            'metadata_tuple_position' => 2,
            'min_length_tuple_position' => 3,
            'tuple_order_preserved' => $reviewReached,
            'task_arg_identity_rows' => $records,
            'duplicate_resolved_targets_found' => $duplicateGroups !== [],
            'duplicate_resolved_target_group_count' => count($duplicateGroups),
            'duplicate_resolved_target_groups' => $duplicateGroups,
            'duplicate_resolved_target_filenames' => array_values($duplicateFilenames),
            'duplicate_file_identities_found' => $duplicateFileIdentityGroups !== [],
            'duplicate_file_identity_group_count' => count($duplicateFileIdentityGroups),
            'duplicate_file_identity_groups' => $duplicateFileIdentityGroups,
            'duplicate_file_identity_filenames' => array_values($duplicateFileIdentityFilenames),
            'hardlink_file_identity_found' => $hardlinkFileIdentityGroups !== [],
            'hardlink_file_identity_group_count' => count($hardlinkFileIdentityGroups),
            'hardlink_file_identity_groups' => $hardlinkFileIdentityGroups,
            'hardlink_file_identity_filenames' => array_values($hardlinkFileIdentityFilenames),
            'no_dedupe_before_task_args' => $duplicateGroups !== [] || $duplicateFileIdentityGroups !== [],
            'metadata_lookup' => 'metadata.get(os.path.basename(f))',
            'metadata_lookup_uses_entry_basename' => true,
            'target_basename_metadata_fallback' => false,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
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
     * @param array<string, string> $metadataValueTypes
     * @return array<string, mixed>
     */
    private function runtimeProcessSinglePdfPreflightReview(
        array $taskArgs,
        array $metadataValueTypes,
        ?string $poolErrorBoundary = null
    ): array {
        $blockedBy = $poolErrorBoundary === null ? null : 'pool-process-count-failed';
        $reached = $blockedBy === null;
        $taskArgFilenames = array_map(static fn (array $task): string => basename((string) $task['filepath']), $taskArgs);
        $selectedNonPdfFilenames = $this->nonPdfBasenames($taskArgFilenames);

        $reviews = [];
        $statusByFilename = [];
        $filetypeByFilename = [];
        $filetypeReviewByFilename = [];
        $filetypeStdoutMessageByFilename = [];
        $filetypeCheckedByFilename = [];
        $textLengthCheckedByFilename = [];
        $markdownExistsPathByFilename = [];
        $markdownExistsPathTypeByFilename = [];
        $markdownExistsPathIsSymlinkByFilename = [];
        $markdownExistsSymlinkTargetExistsByFilename = [];
        $markdownExistsSymlinkTargetTypeByFilename = [];
        $markdownExistsBrokenSymlinkByFilename = [];
        $markdownExistsSymlinkCountsAsExistingByFilename = [];
        $markdownExistsBrokenSymlinkDoesNotCountAsExistingByFilename = [];
        $filepathIsFileAtWorkerPreflightByFilename = [];
        $filepathIsReadableAtWorkerPreflightByFilename = [];
        $filepathPathTypeAtWorkerPreflightByFilename = [];
        $workerFileAvailabilityBoundaryByFilename = [];
        $workerFileAvailabilityRuntimeBoundaryByFilename = [];
        $upstreamReturnValueByFilename = [];
        $upstreamReturnBoundaryByFilename = [];
        $existingMarkdownFilenames = [];
        $markdownExistsDirectoryFilenames = [];
        $markdownExistsSymlinkFilenames = [];
        $markdownExistsLiveSymlinkFilenames = [];
        $markdownExistsBrokenSymlinkFilenames = [];
        $selectedInputMissingFilenames = [];
        $selectedInputBrokenSymlinkFilenames = [];
        $selectedInputNotFileFilenames = [];
        $selectedInputDirectoryFilenames = [];
        $selectedInputUnreadableFilenames = [];
        $unavailableInputHandledBeforeConverterFilenames = [];
        $unavailableInputReachesConverterFilenames = [];
        $unavailableInputHandlingStageByFilename = [];
        $unavailableInputReturnBoundaryByFilename = [];
        $unsupportedFiletypeFilenames = [];
        $filetypeStdoutFilenames = [];
        $shortTextFilenames = [];
        $readyFilenames = [];
        $filetypeCheckedFilenames = [];
        $textLengthCheckedFilenames = [];
        $metadataValueTypeByFilename = [];
        $metadataIsMappingByFilename = [];
        $metadataIsListByFilename = [];
        $metadataPythonTruthyByFilename = [];
        $metadataNonMappingBoundaryByFilename = [];
        $truthyNonMappingMetadataFilenames = [];
        $falsyNonMappingMetadataFilenames = [];

        if ($reached) {
            foreach ($taskArgs as $taskArg) {
                $preflight = $this->processFilePreflightPlan(
                    (string) $taskArg['filepath'],
                    (string) $taskArg['out_folder'],
                    $taskArg['metadata'] ?? null,
                    $taskArg['min_length'] ?? null,
                    metadataValueType: $metadataValueTypes[basename((string) $taskArg['filepath'])] ?? null
                );
                $filename = (string) $preflight['filename'];
                $status = (string) $preflight['status'];

                $statusByFilename[$filename] = $status;
                $metadataValueTypeByFilename[$filename] = $preflight['metadata_value_type'];
                $metadataIsMappingByFilename[$filename] = $preflight['metadata_is_mapping'];
                $metadataIsListByFilename[$filename] = $preflight['metadata_is_list'];
                $metadataPythonTruthyByFilename[$filename] = $preflight['metadata_python_truthy'];
                $metadataNonMappingBoundaryByFilename[$filename] = $preflight['metadata_non_mapping_boundary'];
                $filetypeByFilename[$filename] = $preflight['filetype'];
                $filetypeReviewByFilename[$filename] = $preflight['filetype_review'];
                $filetypeStdoutMessageByFilename[$filename] = is_array($preflight['filetype_review'])
                    ? $preflight['filetype_review']['stdout_message_line']
                    : null;
                $filetypeCheckedByFilename[$filename] = (bool) $preflight['filetype_checked'];
                $textLengthCheckedByFilename[$filename] = (bool) $preflight['text_length_checked'];
                $markdownExistsPathByFilename[$filename] = $preflight['markdown_exists_path'];
                $markdownExistsPathTypeByFilename[$filename] = $preflight['markdown_exists_path_type'];
                $markdownExistsPathIsSymlinkByFilename[$filename] = (bool) $preflight['markdown_exists_path_is_symlink'];
                $markdownExistsSymlinkTargetExistsByFilename[$filename] = (bool) $preflight['markdown_exists_symlink_target_exists'];
                $markdownExistsSymlinkTargetTypeByFilename[$filename] = $preflight['markdown_exists_symlink_target_type'];
                $markdownExistsBrokenSymlinkByFilename[$filename] = (bool) $preflight['markdown_exists_broken_symlink'];
                $markdownExistsSymlinkCountsAsExistingByFilename[$filename] = (bool) $preflight['markdown_exists_symlink_counts_as_existing'];
                $markdownExistsBrokenSymlinkDoesNotCountAsExistingByFilename[$filename] = (bool) $preflight['markdown_exists_broken_symlink_does_not_count_as_existing'];
                $filepathIsFileAtWorkerPreflightByFilename[$filename] = $preflight['filepath_is_file_at_worker_preflight'];
                $filepathIsReadableAtWorkerPreflightByFilename[$filename] = $preflight['filepath_is_readable_at_worker_preflight'];
                $filepathPathTypeAtWorkerPreflightByFilename[$filename] = $preflight['filepath_path_type_at_worker_preflight'];
                $workerFileAvailabilityBoundaryByFilename[$filename] = $preflight['worker_file_availability_boundary'];
                $workerRuntimeBoundary = is_array($preflight['worker_file_availability_runtime_boundary'] ?? null)
                    ? $preflight['worker_file_availability_runtime_boundary']
                    : [];
                $workerFileAvailabilityRuntimeBoundaryByFilename[$filename] = $workerRuntimeBoundary;
                $upstreamReturnValueByFilename[$filename] = $preflight['upstream_return_value'];
                $upstreamReturnBoundaryByFilename[$filename] = $preflight['upstream_return_boundary'];

                if ((bool) $preflight['existing_markdown']) {
                    $existingMarkdownFilenames[] = $filename;
                }
                if ((bool) $preflight['markdown_exists_directory_counts_as_existing']) {
                    $markdownExistsDirectoryFilenames[] = $filename;
                }
                if ((bool) $preflight['markdown_exists_path_is_symlink']) {
                    $markdownExistsSymlinkFilenames[] = $filename;
                }
                if ((bool) $preflight['markdown_exists_symlink_counts_as_existing']) {
                    $markdownExistsLiveSymlinkFilenames[] = $filename;
                }
                if ((bool) $preflight['markdown_exists_broken_symlink']) {
                    $markdownExistsBrokenSymlinkFilenames[] = $filename;
                }
                if ((bool) $preflight['selected_input_missing_at_worker_preflight']) {
                    $selectedInputMissingFilenames[] = $filename;
                }
                if ((bool) $preflight['selected_input_broken_symlink_at_worker_preflight']) {
                    $selectedInputBrokenSymlinkFilenames[] = $filename;
                }
                if ((bool) $preflight['selected_input_not_file_at_worker_preflight']) {
                    $selectedInputNotFileFilenames[] = $filename;
                }
                if ((bool) $preflight['selected_input_directory_at_worker_preflight']) {
                    $selectedInputDirectoryFilenames[] = $filename;
                }
                if ((bool) $preflight['selected_input_unreadable_at_worker_preflight']) {
                    $selectedInputUnreadableFilenames[] = $filename;
                }
                if (($workerRuntimeBoundary['unavailable_at_worker_preflight'] ?? false) === true) {
                    $unavailableInputHandlingStageByFilename[$filename] = $workerRuntimeBoundary['handling_stage'] ?? null;
                    $unavailableInputReturnBoundaryByFilename[$filename] = $workerRuntimeBoundary['upstream_return_boundary_if_unavailable'] ?? null;
                }
                if (($workerRuntimeBoundary['handled_before_converter'] ?? false) === true) {
                    $unavailableInputHandledBeforeConverterFilenames[] = $filename;
                }
                if (($workerRuntimeBoundary['unavailable_input_reaches_converter'] ?? false) === true) {
                    $unavailableInputReachesConverterFilenames[] = $filename;
                }
                if ((bool) $preflight['filetype_checked']) {
                    $filetypeCheckedFilenames[] = $filename;
                }
                if (is_array($preflight['filetype_review']) && $preflight['filetype_review']['prints_stdout_message']) {
                    $filetypeStdoutFilenames[] = $filename;
                }
                if ((bool) $preflight['text_length_checked']) {
                    $textLengthCheckedFilenames[] = $filename;
                }
                if (
                    $preflight['metadata_non_mapping_boundary'] === 'convert-single-pdf-metadata-get-failed'
                    && (bool) $preflight['metadata_python_truthy']
                ) {
                    $truthyNonMappingMetadataFilenames[] = $filename;
                } elseif (
                    $preflight['metadata_non_mapping_boundary'] === 'falsy-non-dict-metadata-skips-language-lookup'
                ) {
                    $falsyNonMappingMetadataFilenames[] = $filename;
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
                    'metadata_value_type' => $preflight['metadata_value_type'],
                    'metadata_is_mapping' => $preflight['metadata_is_mapping'],
                    'metadata_is_list' => $preflight['metadata_is_list'],
                    'metadata_python_truthy' => $preflight['metadata_python_truthy'],
                    'metadata_non_mapping_boundary' => $preflight['metadata_non_mapping_boundary'],
                    'conversion_call' => $preflight['conversion_call'],
                    'existing_markdown' => $preflight['existing_markdown'],
                    'markdown_exists_path' => $preflight['markdown_exists_path'],
                    'markdown_exists_function' => $preflight['markdown_exists_function'],
                    'markdown_exists_path_exists' => $preflight['markdown_exists_path_exists'],
                    'markdown_exists_path_type' => $preflight['markdown_exists_path_type'],
                    'markdown_exists_directory_counts_as_existing' => $preflight['markdown_exists_directory_counts_as_existing'],
                    'markdown_exists_path_is_symlink' => $preflight['markdown_exists_path_is_symlink'],
                    'markdown_exists_symlink_target_exists' => $preflight['markdown_exists_symlink_target_exists'],
                    'markdown_exists_symlink_target_type' => $preflight['markdown_exists_symlink_target_type'],
                    'markdown_exists_broken_symlink' => $preflight['markdown_exists_broken_symlink'],
                    'markdown_exists_symlink_counts_as_existing' => $preflight['markdown_exists_symlink_counts_as_existing'],
                    'markdown_exists_broken_symlink_does_not_count_as_existing' => $preflight['markdown_exists_broken_symlink_does_not_count_as_existing'],
                    'filepath_exists_at_worker_preflight' => $preflight['filepath_exists_at_worker_preflight'],
                    'filepath_is_file_at_worker_preflight' => $preflight['filepath_is_file_at_worker_preflight'],
                    'filepath_is_readable_at_worker_preflight' => $preflight['filepath_is_readable_at_worker_preflight'],
                    'filepath_path_type_at_worker_preflight' => $preflight['filepath_path_type_at_worker_preflight'],
                    'worker_file_availability_boundary' => $preflight['worker_file_availability_boundary'],
                    'worker_file_availability_runtime_boundary' => $preflight['worker_file_availability_runtime_boundary'],
                    'selected_input_missing_at_worker_preflight' => $preflight['selected_input_missing_at_worker_preflight'],
                    'selected_input_broken_symlink_at_worker_preflight' => $preflight['selected_input_broken_symlink_at_worker_preflight'],
                    'selected_input_not_file_at_worker_preflight' => $preflight['selected_input_not_file_at_worker_preflight'],
                    'selected_input_directory_at_worker_preflight' => $preflight['selected_input_directory_at_worker_preflight'],
                    'selected_input_unreadable_at_worker_preflight' => $preflight['selected_input_unreadable_at_worker_preflight'],
                    'min_length_gate_active' => $preflight['min_length_gate_active'],
                    'filetype_checked' => $preflight['filetype_checked'],
                    'filetype' => $preflight['filetype'],
                    'filetype_review' => $preflight['filetype_review'],
                    'filetype_stdout_message_line' => is_array($preflight['filetype_review'])
                        ? $preflight['filetype_review']['stdout_message_line']
                        : null,
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
            'metadata_value_type_by_filename' => $metadataValueTypeByFilename,
            'metadata_is_mapping_by_filename' => $metadataIsMappingByFilename,
            'metadata_is_list_by_filename' => $metadataIsListByFilename,
            'metadata_python_truthy_by_filename' => $metadataPythonTruthyByFilename,
            'metadata_non_mapping_boundary_by_filename' => $metadataNonMappingBoundaryByFilename,
            'truthy_non_mapping_metadata_filenames' => $truthyNonMappingMetadataFilenames,
            'falsy_non_mapping_metadata_filenames' => $falsyNonMappingMetadataFilenames,
            'filetype_by_filename' => $filetypeByFilename,
            'filetype_review_by_filename' => $filetypeReviewByFilename,
            'filetype_stdout_message_by_filename' => $filetypeStdoutMessageByFilename,
            'filetype_checked_by_filename' => $filetypeCheckedByFilename,
            'text_length_checked_by_filename' => $textLengthCheckedByFilename,
            'markdown_exists_path_by_filename' => $markdownExistsPathByFilename,
            'markdown_exists_path_type_by_filename' => $markdownExistsPathTypeByFilename,
            'markdown_exists_path_is_symlink_by_filename' => $markdownExistsPathIsSymlinkByFilename,
            'markdown_exists_symlink_target_exists_by_filename' => $markdownExistsSymlinkTargetExistsByFilename,
            'markdown_exists_symlink_target_type_by_filename' => $markdownExistsSymlinkTargetTypeByFilename,
            'markdown_exists_broken_symlink_by_filename' => $markdownExistsBrokenSymlinkByFilename,
            'markdown_exists_symlink_counts_as_existing_by_filename' => $markdownExistsSymlinkCountsAsExistingByFilename,
            'markdown_exists_broken_symlink_does_not_count_as_existing_by_filename' => $markdownExistsBrokenSymlinkDoesNotCountAsExistingByFilename,
            'filepath_is_file_at_worker_preflight_by_filename' => $filepathIsFileAtWorkerPreflightByFilename,
            'filepath_is_readable_at_worker_preflight_by_filename' => $filepathIsReadableAtWorkerPreflightByFilename,
            'filepath_path_type_at_worker_preflight_by_filename' => $filepathPathTypeAtWorkerPreflightByFilename,
            'worker_file_availability_boundary_by_filename' => $workerFileAvailabilityBoundaryByFilename,
            'worker_file_availability_runtime_boundary_by_filename' => $workerFileAvailabilityRuntimeBoundaryByFilename,
            'upstream_return_value_by_filename' => $upstreamReturnValueByFilename,
            'upstream_return_boundary_by_filename' => $upstreamReturnBoundaryByFilename,
            'existing_markdown_filenames' => $existingMarkdownFilenames,
            'markdown_exists_directory_filenames' => $markdownExistsDirectoryFilenames,
            'markdown_exists_symlink_filenames' => $markdownExistsSymlinkFilenames,
            'markdown_exists_live_symlink_filenames' => $markdownExistsLiveSymlinkFilenames,
            'markdown_exists_broken_symlink_filenames' => $markdownExistsBrokenSymlinkFilenames,
            'selected_input_missing_filenames' => $selectedInputMissingFilenames,
            'selected_input_broken_symlink_filenames' => $selectedInputBrokenSymlinkFilenames,
            'selected_input_not_file_filenames' => $selectedInputNotFileFilenames,
            'selected_input_directory_filenames' => $selectedInputDirectoryFilenames,
            'selected_input_unreadable_filenames' => $selectedInputUnreadableFilenames,
            'unavailable_input_handled_before_converter_filenames' => $unavailableInputHandledBeforeConverterFilenames,
            'unavailable_input_reaches_converter_filenames' => $unavailableInputReachesConverterFilenames,
            'unavailable_input_handling_stage_by_filename' => $unavailableInputHandlingStageByFilename,
            'unavailable_input_return_boundary_by_filename' => $unavailableInputReturnBoundaryByFilename,
            'unsupported_filetype_filenames' => $unsupportedFiletypeFilenames,
            'short_text_filenames' => $shortTextFilenames,
            'ready_filenames' => $readyFilenames,
            'filetype_checked_filenames' => $filetypeCheckedFilenames,
            'filetype_stdout_filenames' => $filetypeStdoutFilenames,
            'text_length_checked_filenames' => $textLengthCheckedFilenames,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * Runtime main-loop mirror for `list(tqdm(pool.imap(...)))`.
     *
     * Upstream drains every worker return value so sidecar `0` returns and
     * Python `None` returns advance progress, but no result variable is kept.
     *
     * @param list<array{filepath: string, out_folder: string, metadata: mixed, min_length: int|null}> $taskArgs
     * @param array<string, mixed> $processSinglePdfPreflight
     * @return array<string, mixed>
     */
    private function runtimePoolResultDrainReview(
        array $taskArgs,
        array $processSinglePdfPreflight,
        ?string $poolErrorBoundary = null
    ): array {
        $blockedBy = $poolErrorBoundary === null ? null : 'pool-process-count-failed';
        $reached = $blockedBy === null;
        $returnValues = $processSinglePdfPreflight['upstream_return_value_by_filename'] ?? [];
        $returnBoundaries = $processSinglePdfPreflight['upstream_return_boundary_by_filename'] ?? [];
        $statuses = $processSinglePdfPreflight['status_by_filename'] ?? [];

        $rows = [];
        $returnValueByFilename = [];
        $returnTypeByFilename = [];
        $returnBoundaryByFilename = [];
        $statusByFilename = [];
        $zeroReturnFilenames = [];
        $noneReturnFilenames = [];
        $nonNullReturnFilenames = [];

        if ($reached) {
            foreach ($taskArgs as $taskArg) {
                $filename = basename((string) $taskArg['filepath']);
                $returnValue = array_key_exists($filename, $returnValues)
                    ? $returnValues[$filename]
                    : null;
                $returnType = $this->runtimeReturnValueType($returnValue);
                $returnBoundary = array_key_exists($filename, $returnBoundaries)
                    ? $returnBoundaries[$filename]
                    : null;
                $status = array_key_exists($filename, $statuses)
                    ? $statuses[$filename]
                    : null;

                $returnValueByFilename[$filename] = $returnValue;
                $returnTypeByFilename[$filename] = $returnType;
                $returnBoundaryByFilename[$filename] = $returnBoundary;
                $statusByFilename[$filename] = $status;

                if ($returnValue === null) {
                    $noneReturnFilenames[] = $filename;
                } else {
                    $nonNullReturnFilenames[] = $filename;
                }
                if ($returnValue === 0) {
                    $zeroReturnFilenames[] = $filename;
                }

                $rows[] = [
                    'filename' => $filename,
                    'status' => $status,
                    'return_value' => $returnValue,
                    'return_type' => $returnType,
                    'return_boundary' => $returnBoundary,
                    'ignored_by_main_loop' => true,
                ];
            }
        }

        return [
            'source' => 'convert.py list(tqdm(pool.imap(...))) result drain boundary',
            'order' => 'after_pool_imap_before_pool_cleanup',
            'review_reached' => $reached,
            'blocked_by' => $blockedBy,
            'pool_error_boundary' => $poolErrorBoundary,
            'pool_imap_call' => 'pool.imap(process_single_pdf, task_args)',
            'result_drain_call' => 'list(tqdm(pool.imap(process_single_pdf, task_args), total=len(task_args), desc="Processing PDFs", unit="pdf"))',
            'result_assignment' => null,
            'result_values_ignored' => $reached,
            'return_values_do_not_affect_summary' => $reached,
            'task_args_count' => count($taskArgs),
            'result_count' => $reached ? count($rows) : 0,
            'progress_total' => $reached ? count($taskArgs) : 0,
            'progress_total_source' => 'len(task_args)',
            'result_rows' => $rows,
            'return_value_by_filename' => $returnValueByFilename,
            'return_type_by_filename' => $returnTypeByFilename,
            'return_boundary_by_filename' => $returnBoundaryByFilename,
            'status_by_filename' => $statusByFilename,
            'zero_return_filenames' => $zeroReturnFilenames,
            'none_return_filenames' => $noneReturnFilenames,
            'non_null_return_filenames' => $nonNullReturnFilenames,
            'cleanup_after_result_drain' => $reached,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param list<array{filepath: string, out_folder: string, metadata: mixed, min_length: int|null}> $taskArgs
     * @param array<string, mixed> $spawnStartMethod
     * @param array<string, mixed> $modelHandoff
     * @param array<string, mixed> $consoleSummary
     * @return array<string, mixed>
     */
    private function runtimeEmptyTaskQueueModelHandoffReview(
        array $taskArgs,
        ?string $poolErrorBoundary,
        array $spawnStartMethod,
        array $modelHandoff,
        array $consoleSummary
    ): array {
        $taskArgCount = count($taskArgs);
        $emptyQueue = $taskArgCount === 0;
        $reviewReached = $emptyQueue && $poolErrorBoundary === 'empty-task-queue';
        $shareMemoryReview = is_array($modelHandoff['model_share_memory_review'] ?? null)
            ? $modelHandoff['model_share_memory_review']
            : [];
        $workerModelLoadPlanned = $reviewReached
            && (bool) ($modelHandoff['worker_loads_models_when_init_arg_null'] ?? false);

        return [
            'source' => 'convert.py empty files_to_convert model handoff boundary',
            'order' => 'after_metadata_spawn_model_handoff_summary_and_task_args_before_pool_creation_error',
            'review_reached' => $reviewReached,
            'blocked_by' => $reviewReached ? null : ($emptyQueue ? $poolErrorBoundary : 'not-empty-task-queue'),
            'selected_count' => $taskArgCount,
            'task_args_count' => $taskArgCount,
            'empty_files_to_convert' => $emptyQueue,
            'empty_queue_short_circuits_before_spawn' => false,
            'spawn_start_method_reached_before_empty_pool_failure' => $reviewReached
                && (bool) ($spawnStartMethod['start_method_reached'] ?? false),
            'spawn_start_method_success_before_empty_pool_failure' => $reviewReached
                && (bool) ($spawnStartMethod['start_method_success'] ?? false),
            'total_processes_computed_before_spawn' => $reviewReached
                ? (int) ($spawnStartMethod['total_processes_computed_before_spawn'] ?? 0)
                : 0,
            'model_handoff_reached_before_empty_pool_failure' => $reviewReached
                && (bool) ($modelHandoff['model_handoff_reached'] ?? false),
            'parent_load_all_models_before_empty_pool_failure' => $reviewReached
                && (bool) ($modelHandoff['main_load_all_models'] ?? false),
            'parent_share_memory_before_empty_pool_failure' => $reviewReached
                && (bool) ($modelHandoff['share_memory_before_pool'] ?? false),
            'share_memory_loop_reached_before_empty_pool_failure' => $reviewReached
                && (bool) ($modelHandoff['model_share_memory_loop_reached'] ?? false),
            'share_memory_model_slot_indexes_before_empty_pool_failure' => $reviewReached
                ? ($shareMemoryReview['share_memory_model_slot_indexes'] ?? [])
                : [],
            'none_model_slot_indexes_before_empty_pool_failure' => $reviewReached
                ? ($shareMemoryReview['none_model_slot_indexes'] ?? [])
                : [],
            'mps_worker_model_load_planned_before_empty_pool_failure' => $workerModelLoadPlanned,
            'mps_worker_model_load_blocked_by_empty_pool' => $workerModelLoadPlanned,
            'conversion_summary_reached_before_empty_pool_failure' => $reviewReached
                && (bool) ($consoleSummary['summary_reached'] ?? false),
            'conversion_summary_line' => $reviewReached ? ($consoleSummary['message_line'] ?? null) : null,
            'task_args_built_before_empty_pool_failure' => $reviewReached,
            'pool_creation_reached_after_empty_summary' => $reviewReached,
            'worker_pool_error_boundary' => $poolErrorBoundary,
            'pool_creation_error_boundary' => $reviewReached ? 'pool-process-count-failed' : null,
            'pool_imap_reached' => false,
            'worker_initializer_reached' => false,
            'cleanup_reached' => false,
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
     *     metadata: array<string, mixed>,
     *     metadata_json_type: string,
     *     metadata_get_available: bool,
     *     metadata_shape_error_boundary: string|null,
     *     metadata_shape_error_class: string|null,
     *     metadata_shape_error_message: string|null,
     *     metadata_value_types: array<string, string>,
     *     metadata_top_level_key_order: list<string>,
     *     metadata_duplicate_keys: list<string>,
     *     metadata_duplicate_key_counts: array<string, int>,
     *     metadata_duplicate_key_last_value_types: array<string, string>
     * }
     */
    private function loadRuntimeMetadataFile(string $metadataFile): array
    {
        if (is_dir($metadataFile)) {
            throw new InvalidArgumentException('Batch metadata file is a directory: ' . $metadataFile);
        }

        $contents = @file_get_contents($metadataFile);
        if (!is_string($contents)) {
            throw new InvalidArgumentException('Batch metadata file is not readable: ' . $metadataFile);
        }

        try {
            $decodedObject = json_decode($contents, false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Batch metadata file must contain valid JSON.', previous: $exception);
        }

        $jsonType = $this->jsonMetadataType($decodedObject);
        if (!$decodedObject instanceof stdClass) {
            return [
                'metadata' => [],
                'metadata_json_type' => $jsonType,
                'metadata_get_available' => false,
                'metadata_shape_error_boundary' => 'metadata-get-failed',
                'metadata_shape_error_class' => 'AttributeError',
                'metadata_shape_error_message' => "'" . $jsonType . "' object has no attribute 'get'",
                'metadata_value_types' => [],
                'metadata_top_level_key_order' => [],
                'metadata_duplicate_keys' => [],
                'metadata_duplicate_key_counts' => [],
                'metadata_duplicate_key_last_value_types' => [],
            ];
        }

        $topLevelKeyOrder = $this->jsonTopLevelObjectKeys($contents);
        $duplicateKeyCounts = $this->duplicateStringCounts($topLevelKeyOrder);
        $duplicateKeys = [];
        $duplicateKeySeen = [];
        foreach ($topLevelKeyOrder as $key) {
            if (($duplicateKeyCounts[$key] ?? 0) < 2 || isset($duplicateKeySeen[$key])) {
                continue;
            }

            $duplicateKeys[] = $key;
            $duplicateKeySeen[$key] = true;
        }

        $metadata = [];
        $metadataValueTypes = [];
        $objectValues = get_object_vars($decodedObject);
        foreach ($objectValues as $filename => $value) {
            $filename = (string) $filename;
            $metadata[$filename] = $this->runtimeMetadataPhpValue($value);
            $metadataValueTypes[$filename] = $this->jsonMetadataType($value);
        }
        $duplicateKeyLastValueTypes = [];
        foreach ($duplicateKeys as $filename) {
            if (isset($metadataValueTypes[$filename])) {
                $duplicateKeyLastValueTypes[$filename] = $metadataValueTypes[$filename];
            }
        }

        return [
            'metadata' => $metadata,
            'metadata_json_type' => 'object',
            'metadata_get_available' => true,
            'metadata_shape_error_boundary' => null,
            'metadata_shape_error_class' => null,
            'metadata_shape_error_message' => null,
            'metadata_value_types' => $metadataValueTypes,
            'metadata_top_level_key_order' => $topLevelKeyOrder,
            'metadata_duplicate_keys' => $duplicateKeys,
            'metadata_duplicate_key_counts' => $duplicateKeyCounts,
            'metadata_duplicate_key_last_value_types' => $duplicateKeyLastValueTypes,
        ];
    }

    /**
     * Python's json.load() silently keeps the last value for duplicate object
     * keys. Preserve that runtime boundary for metadata files keyed by basename.
     *
     * @param list<string> $selectedFilenames
     * @param array<string, mixed> $runtimeMetadataPlan
     * @return array<string, mixed>
     */
    private function runtimeMetadataDuplicateKeyReview(array $selectedFilenames, array $runtimeMetadataPlan): array
    {
        $duplicateKeys = $runtimeMetadataPlan['metadata_duplicate_keys'] ?? [];
        $duplicateKeyCounts = $runtimeMetadataPlan['metadata_duplicate_key_counts'] ?? [];
        $duplicateKeyLastValueTypes = $runtimeMetadataPlan['metadata_duplicate_key_last_value_types'] ?? [];
        $selectedDuplicateFilenames = array_values(array_filter(
            $selectedFilenames,
            static fn (string $filename): bool => isset($duplicateKeyCounts[$filename])
        ));

        return [
            'source' => 'convert.py metadata_file json.load duplicate basename boundary',
            'review_reached' => true,
            'json_loader' => 'json.load',
            'duplicate_key_policy' => 'python-json-load-last-value-wins',
            'duplicate_keys_found' => $duplicateKeys !== [],
            'duplicate_key_count' => count($duplicateKeys),
            'duplicate_keys' => $duplicateKeys,
            'duplicate_key_occurrence_counts' => $duplicateKeyCounts,
            'duplicate_key_last_value_types' => $duplicateKeyLastValueTypes,
            'selected_duplicate_filenames' => $selectedDuplicateFilenames,
            'selected_duplicate_count' => count($selectedDuplicateFilenames),
            'task_args_receive_last_values' => $selectedDuplicateFilenames !== [],
            'stale_duplicate_values_excluded_from_task_args' => $selectedDuplicateFilenames !== [],
            'blocks_task_args' => false,
            'blocks_model_handoff' => false,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * convert.py queues worker metadata with metadata.get(os.path.basename(f)).
     * Path-shaped metadata keys stay loaded for review, but do not satisfy a
     * selected file unless the JSON object also contains the exact basename key.
     *
     * @param list<string> $selectedFilenames
     * @param array<string, mixed> $runtimeMetadataPlan
     * @return array<string, mixed>
     */
    private function runtimeMetadataBasenameLookupReview(array $selectedFilenames, array $runtimeMetadataPlan): array
    {
        $metadata = $runtimeMetadataPlan['metadata'] ?? [];
        $metadataValueTypes = $runtimeMetadataPlan['metadata_value_types'] ?? [];
        $metadataKeys = array_map(static fn (int|string $filename): string => (string) $filename, array_keys($metadata));
        sort($metadataKeys, SORT_STRING);

        $selectedMetadataFilenames = array_values(array_filter(
            $selectedFilenames,
            static fn (string $filename): bool => array_key_exists($filename, $metadata)
        ));
        $missingMetadataFilenames = array_values(array_filter(
            $selectedFilenames,
            static fn (string $filename): bool => !array_key_exists($filename, $metadata)
        ));

        $selectedFilenameSet = array_fill_keys($selectedFilenames, true);
        $pathLikeMetadataKeys = [];
        $pathLikeMetadataKeyBasenames = [];
        $pathLikeMetadataKeyValueTypes = [];
        $pathLikeKeysByBasename = [];
        foreach ($metadataKeys as $metadataKey) {
            if (!$this->runtimeMetadataKeyIsPathLike($metadataKey)) {
                continue;
            }

            $basename = $this->runtimeMetadataKeyBasename($metadataKey);
            $pathLikeMetadataKeys[] = $metadataKey;
            $pathLikeMetadataKeyBasenames[$metadataKey] = $basename;
            $pathLikeMetadataKeyValueTypes[$metadataKey] = $metadataValueTypes[$metadataKey]
                ?? $this->phpMetadataValueType($metadata[$metadataKey] ?? null);
            $pathLikeKeysByBasename[$basename] ??= [];
            $pathLikeKeysByBasename[$basename][] = $metadataKey;
        }

        ksort($pathLikeMetadataKeyBasenames, SORT_STRING);
        ksort($pathLikeMetadataKeyValueTypes, SORT_STRING);

        $pathLikeMetadataKeysWithSelectedBasenames = array_values(array_filter(
            $pathLikeMetadataKeys,
            static fn (string $metadataKey): bool => isset($selectedFilenameSet[$pathLikeMetadataKeyBasenames[$metadataKey]])
        ));
        $exactBasenameKeysWithPathLikeDecoys = array_values(array_filter(
            $selectedMetadataFilenames,
            static fn (string $filename): bool => isset($pathLikeKeysByBasename[$filename])
        ));
        $missingMetadataFilenamesDueToPathLikeKeys = array_values(array_filter(
            $missingMetadataFilenames,
            static fn (string $filename): bool => isset($pathLikeKeysByBasename[$filename])
        ));

        return [
            'source' => 'convert.py task_args metadata.get(os.path.basename(f))',
            'review_reached' => true,
            'metadata_lookup' => 'metadata.get(os.path.basename(f))',
            'lookup_key_source' => 'os.path.basename(f)',
            'basename_only_lookup_preserved' => true,
            'metadata_get_available' => $runtimeMetadataPlan['metadata_get_available'] ?? false,
            'selected_filenames' => $selectedFilenames,
            'metadata_key_count' => count($metadataKeys),
            'metadata_keys' => $metadataKeys,
            'selected_metadata_filenames' => $selectedMetadataFilenames,
            'missing_metadata_filenames' => $missingMetadataFilenames,
            'path_like_metadata_keys_found' => $pathLikeMetadataKeys !== [],
            'path_like_metadata_key_count' => count($pathLikeMetadataKeys),
            'path_like_metadata_keys' => $pathLikeMetadataKeys,
            'path_like_metadata_key_basenames' => $pathLikeMetadataKeyBasenames,
            'path_like_metadata_key_value_types' => $pathLikeMetadataKeyValueTypes,
            'path_like_metadata_keys_with_selected_basenames' => $pathLikeMetadataKeysWithSelectedBasenames,
            'path_like_metadata_values_excluded_from_task_args' => $pathLikeMetadataKeysWithSelectedBasenames !== [],
            'exact_basename_keys_with_path_like_decoys' => $exactBasenameKeysWithPathLikeDecoys,
            'exact_basename_values_preferred_over_path_like_keys' => $exactBasenameKeysWithPathLikeDecoys !== [],
            'missing_metadata_filenames_due_to_path_like_keys' => $missingMetadataFilenamesDueToPathLikeKeys,
            'task_args_receive_path_like_values' => false,
            'blocks_task_args' => false,
            'blocks_model_handoff' => false,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    private function runtimeMetadataKeyIsPathLike(string $metadataKey): bool
    {
        return str_contains($metadataKey, '/') || str_contains($metadataKey, '\\');
    }

    private function runtimeMetadataKeyBasename(string $metadataKey): string
    {
        return basename(str_replace('\\', '/', $metadataKey));
    }

    /**
     * @return list<string>
     */
    private function jsonTopLevelObjectKeys(string $json): array
    {
        $length = strlen($json);
        $index = $this->skipJsonWhitespace($json, 0);
        if ($index >= $length || $json[$index] !== '{') {
            return [];
        }

        $index++;
        $keys = [];
        while ($index < $length) {
            $index = $this->skipJsonWhitespace($json, $index);
            if ($index >= $length || $json[$index] === '}') {
                break;
            }
            if ($json[$index] !== '"') {
                break;
            }

            [$rawKey, $index] = $this->readJsonStringToken($json, $index);
            try {
                $key = json_decode($rawKey, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                break;
            }
            if (is_string($key)) {
                $keys[] = $key;
            }

            $index = $this->skipJsonWhitespace($json, $index);
            if ($index >= $length || $json[$index] !== ':') {
                break;
            }

            $index = $this->skipJsonValue($json, $index + 1);
            $index = $this->skipJsonWhitespace($json, $index);
            if ($index < $length && $json[$index] === ',') {
                $index++;
                continue;
            }
            if ($index < $length && $json[$index] === '}') {
                break;
            }
        }

        return $keys;
    }

    private function skipJsonWhitespace(string $json, int $index): int
    {
        $length = strlen($json);
        while ($index < $length && str_contains(" \t\r\n", $json[$index])) {
            $index++;
        }

        return $index;
    }

    /**
     * @return array{string, int}
     */
    private function readJsonStringToken(string $json, int $index): array
    {
        $start = $index;
        $length = strlen($json);
        $index++;
        $escaped = false;
        while ($index < $length) {
            $char = $json[$index];
            if ($escaped) {
                $escaped = false;
                $index++;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                $index++;
                continue;
            }
            if ($char === '"') {
                $index++;

                return [substr($json, $start, $index - $start), $index];
            }

            $index++;
        }

        return [substr($json, $start), $length];
    }

    private function skipJsonValue(string $json, int $index): int
    {
        $index = $this->skipJsonWhitespace($json, $index);
        $length = strlen($json);
        if ($index >= $length) {
            return $index;
        }

        $char = $json[$index];
        if ($char === '"') {
            [, $index] = $this->readJsonStringToken($json, $index);

            return $index;
        }

        if ($char === '{' || $char === '[') {
            $stack = [$char];
            $index++;
            while ($index < $length && $stack !== []) {
                $char = $json[$index];
                if ($char === '"') {
                    [, $index] = $this->readJsonStringToken($json, $index);
                    continue;
                }
                if ($char === '{' || $char === '[') {
                    $stack[] = $char;
                    $index++;
                    continue;
                }
                if ($char === '}' || $char === ']') {
                    array_pop($stack);
                    $index++;
                    continue;
                }

                $index++;
            }

            return $index;
        }

        while ($index < $length && $json[$index] !== ',' && $json[$index] !== '}') {
            $index++;
        }

        return $index;
    }

    /**
     * @param list<string> $values
     * @return array<string, int>
     */
    private function duplicateStringCounts(array $values): array
    {
        $counts = [];
        foreach ($values as $value) {
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        return array_filter($counts, static fn (int $count): bool => $count > 1);
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
        if (is_int($value)) {
            return 'int';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_bool($value)) {
            return 'bool';
        }

        return get_debug_type($value);
    }

    private function runtimeMetadataPhpValue(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $converted = [];
            foreach (get_object_vars($value) as $key => $child) {
                $converted[(string) $key] = $this->runtimeMetadataPhpValue($child);
            }

            return $converted;
        }

        if (is_array($value)) {
            return array_map(fn (mixed $child): mixed => $this->runtimeMetadataPhpValue($child), $value);
        }

        return $value;
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
     * @param array{filepath: string, out_folder: string, metadata?: mixed, min_length?: int|null} $task
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
     * @return array<string, mixed>
     */
    public function processFilePreflightPlan(
        string $filepath,
        string $outputFolder,
        mixed $metadata,
        ?int $minLength,
        ?callable $textLength = null,
        ?string $metadataValueType = null
    ): array {
        $filename = basename($filepath);
        $markdownPath = $this->writer->getMarkdownFilepath($outputFolder, $filename);
        $markdownPathExists = $this->writer->markdownExists($outputFolder, $filename);
        $markdownPathType = $this->filesystemPathType($markdownPath);
        $markdownPathReview = $this->markdownExistsPathReview($markdownPath, $markdownPathExists, $markdownPathType);
        $metadataKeys = is_array($metadata) ? array_values(array_filter(array_keys($metadata), 'is_string')) : [];
        sort($metadataKeys, SORT_STRING);
        $workerFileAvailability = $this->workerFileAvailabilityReview($filepath);
        $minLengthGateActive = $this->pythonTruthyInteger($minLength);
        $workerFileAvailabilityRuntimeBoundary = $this->workerFileAvailabilityRuntimeBoundary(
            $filepath,
            $workerFileAvailability,
            $markdownPathExists,
            $minLengthGateActive
        );
        $metadataValueType = $metadataValueType ?? $this->phpMetadataValueType($metadata);
        $metadataIsMapping = $metadataValueType === 'dict';
        $metadataIsList = $metadataValueType === 'list';
        $metadataPythonTruthy = $this->pythonTruthyMetadataValue($metadata);
        $metadataNonMappingBoundary = null;
        if ($metadata !== null && !$metadataIsMapping) {
            $metadataNonMappingBoundary = $metadataPythonTruthy
                ? 'convert-single-pdf-metadata-get-failed'
                : 'falsy-non-dict-metadata-skips-language-lookup';
        }

        $base = [
            'schema' => 'markerpdf.convert_process_single_pdf_preflight.v1',
            'source' => 'sddai/markerPDF convert.py::process_single_pdf + marker.output::markdown_exists + marker.pdf.utils::find_filetype + marker.pdf.extract_text::get_length_of_text',
            'filename' => $filename,
            'filepath' => $filepath,
            'out_folder' => $outputFolder,
            'metadata_keys' => $metadataKeys,
            'metadata_value_type' => $metadataValueType,
            'metadata_is_mapping' => $metadataIsMapping,
            'metadata_is_list' => $metadataIsList,
            'metadata_python_truthy' => $metadataPythonTruthy,
            'metadata_non_mapping_boundary' => $metadataNonMappingBoundary,
            'min_length' => $minLength,
            'preflight_order' => ['markdown_exists', 'find_filetype', 'get_length_of_text', 'convert_single_pdf', 'save_markdown'],
            'existing_markdown' => $markdownPathExists,
            'markdown_exists_path' => $markdownPath,
            'markdown_exists_function' => 'os.path.exists',
            'markdown_exists_path_exists' => $markdownPathExists,
            'markdown_exists_path_type' => $markdownPathType,
            'markdown_exists_directory_counts_as_existing' => $markdownPathType === 'directory',
            ...$markdownPathReview,
            ...$workerFileAvailability,
            'worker_file_availability_runtime_boundary' => $workerFileAvailabilityRuntimeBoundary,
            'filetype_checked' => false,
            'filetype' => null,
            'filetype_review' => null,
            'min_length_gate_active' => $minLengthGateActive,
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
                'metadata_argument_value_type' => $metadataValueType,
                'metadata_argument_is_mapping' => $metadataIsMapping,
                'metadata_argument_is_list' => $metadataIsList,
                'metadata_argument_python_truthy' => $metadataPythonTruthy,
                'metadata_argument_non_mapping_boundary' => $metadataNonMappingBoundary,
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

        if ($minLengthGateActive) {
            $filetypeReview = $this->filetypeDetector->findFiletypeReview($filepath);
            $filetype = (string) $filetypeReview['filetype'];
            $base['filetype_checked'] = true;
            $base['filetype'] = $filetype;
            $base['filetype_review'] = $filetypeReview;

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
     * @return array{
     *     filepath_exists_at_worker_preflight: bool,
     *     filepath_is_file_at_worker_preflight: bool,
     *     filepath_is_readable_at_worker_preflight: bool,
     *     filepath_path_type_at_worker_preflight: string,
     *     worker_file_availability_boundary: string|null,
     *     selected_input_missing_at_worker_preflight: bool,
     *     selected_input_broken_symlink_at_worker_preflight: bool,
     *     selected_input_not_file_at_worker_preflight: bool,
     *     selected_input_directory_at_worker_preflight: bool,
     *     selected_input_unreadable_at_worker_preflight: bool
     * }
     */
    private function workerFileAvailabilityReview(string $filepath): array
    {
        $pathType = $this->filesystemPathType($filepath);
        $pathExists = file_exists($filepath);
        $isFile = is_file($filepath);
        $isReadable = is_readable($filepath);
        $isMissing = $pathType === 'missing';
        $isBrokenSymlink = $pathType === 'broken-symlink';
        $isDirectory = $pathType === 'directory';
        $isNotFile = $pathExists && !$isFile;
        $isUnreadable = $isFile && !$isReadable;

        $boundary = null;
        if ($isMissing) {
            $boundary = 'selected-input-missing-before-worker-preflight';
        } elseif ($isBrokenSymlink) {
            $boundary = 'selected-input-broken-symlink-before-worker-preflight';
        } elseif ($isNotFile) {
            $boundary = 'selected-input-not-file-before-worker-preflight';
        } elseif ($isUnreadable) {
            $boundary = 'selected-input-unreadable-before-worker-preflight';
        }

        return [
            'filepath_exists_at_worker_preflight' => $pathExists,
            'filepath_is_file_at_worker_preflight' => $isFile,
            'filepath_is_readable_at_worker_preflight' => $isReadable,
            'filepath_path_type_at_worker_preflight' => $pathType,
            'worker_file_availability_boundary' => $boundary,
            'selected_input_missing_at_worker_preflight' => $isMissing,
            'selected_input_broken_symlink_at_worker_preflight' => $isBrokenSymlink,
            'selected_input_not_file_at_worker_preflight' => $isNotFile,
            'selected_input_directory_at_worker_preflight' => $isDirectory,
            'selected_input_unreadable_at_worker_preflight' => $isUnreadable,
        ];
    }

    /**
     * @param array<string, mixed> $availability
     * @return array<string, mixed>
     */
    private function workerFileAvailabilityRuntimeBoundary(
        string $filepath,
        array $availability,
        bool $existingMarkdown,
        bool $minLengthGateActive
    ): array {
        $availabilityBoundary = $availability['worker_file_availability_boundary'] ?? null;
        $unavailable = is_string($availabilityBoundary);
        $handlingStage = null;
        $handledBeforeConverter = false;
        $unavailableInputReachesConverter = false;
        $returnBoundary = null;
        $conversionExceptionCaught = false;

        if ($unavailable && $existingMarkdown) {
            $handlingStage = 'markdown_exists';
            $handledBeforeConverter = true;
            $returnBoundary = 'markdown_exists-return-none';
        } elseif ($unavailable && $minLengthGateActive) {
            $handlingStage = 'find_filetype';
            $handledBeforeConverter = true;
            $returnBoundary = 'unsupported-filetype-return-zero';
        } elseif ($unavailable) {
            $handlingStage = 'convert_single_pdf';
            $unavailableInputReachesConverter = true;
            $returnBoundary = 'conversion-exception-print-return-none';
            $conversionExceptionCaught = true;
        }

        return [
            'source' => 'convert.py process_single_pdf worker file availability boundary',
            'order' => 'after_markdown_exists_before_optional_find_filetype_or_convert_single_pdf',
            'filepath' => $filepath,
            'path_type' => $availability['filepath_path_type_at_worker_preflight'] ?? 'unknown',
            'path_exists' => (bool) ($availability['filepath_exists_at_worker_preflight'] ?? false),
            'path_is_file' => (bool) ($availability['filepath_is_file_at_worker_preflight'] ?? false),
            'path_is_readable' => (bool) ($availability['filepath_is_readable_at_worker_preflight'] ?? false),
            'availability_boundary' => $availabilityBoundary,
            'unavailable_at_worker_preflight' => $unavailable,
            'explicit_worker_isfile_gate' => false,
            'main_isfile_gate_already_passed' => true,
            'markdown_exists_checked_before_file_access' => true,
            'existing_markdown_short_circuits_before_filetype' => $unavailable && $existingMarkdown,
            'min_length_gate_active' => $minLengthGateActive,
            'handled_before_converter' => $handledBeforeConverter,
            'handling_stage' => $handlingStage,
            'unavailable_input_reaches_converter' => $unavailableInputReachesConverter,
            'conversion_exception_caught_by_process_single_pdf' => $conversionExceptionCaught,
            'upstream_return_boundary_if_unavailable' => $returnBoundary,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @return array{
     *     markdown_exists_path_is_symlink: bool,
     *     markdown_exists_symlink_target_exists: bool,
     *     markdown_exists_symlink_target_type: string|null,
     *     markdown_exists_broken_symlink: bool,
     *     markdown_exists_symlink_counts_as_existing: bool,
     *     markdown_exists_broken_symlink_does_not_count_as_existing: bool
     * }
     */
    private function markdownExistsPathReview(string $markdownPath, bool $markdownPathExists, string $markdownPathType): array
    {
        $isSymlink = is_link($markdownPath);
        $targetExists = $isSymlink && $markdownPathExists;

        return [
            'markdown_exists_path_is_symlink' => $isSymlink,
            'markdown_exists_symlink_target_exists' => $targetExists,
            'markdown_exists_symlink_target_type' => $isSymlink
                ? ($targetExists ? $markdownPathType : 'missing')
                : null,
            'markdown_exists_broken_symlink' => $isSymlink && !$targetExists,
            'markdown_exists_symlink_counts_as_existing' => $isSymlink && $markdownPathExists,
            'markdown_exists_broken_symlink_does_not_count_as_existing' => $isSymlink && !$markdownPathExists,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function processFile(
        string $filepath,
        string $outputFolder,
        mixed $metadata,
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
     * @return array{output_path_exists: bool, output_path_type: string, output_folder_exists: bool, output_folder_is_symlink: bool, output_folder_makedirs_follows_symlink: bool, output_folder_symlink_target_exists: bool, output_folder_symlink_target_type: string|null, output_folder_broken_symlink: bool, output_folder_symlink_target_blocked: bool, output_folder_parent_path: string, output_folder_parent_path_exists: bool, output_folder_parent_path_type: string, output_folder_parent_conflict_path: string|null, output_folder_parent_conflict_type: string|null, output_folder_parent_creation_blocked: bool, upstream_creates_output_folder: true, native_plan_creates_output_folder: false, output_folder_creation_required: bool, output_folder_creation_call: string, output_folder_creation_order: string, output_folder_creation_blocked: bool, output_folder_creation_error_boundary: string|null, output_folder_creation_error_class: string|null, output_folder_creation_error_message: string|null}
     */
    private function outputFolderCreationPlan(string $absoluteOutputFolder): array
    {
        $exists = file_exists($absoluteOutputFolder);
        $isDirectory = is_dir($absoluteOutputFolder);
        $isSymlink = is_link($absoluteOutputFolder);
        $pathType = 'missing';
        if ($isDirectory) {
            $pathType = 'directory';
        } elseif (is_file($absoluteOutputFolder)) {
            $pathType = 'file';
        } elseif ($isSymlink && !$exists) {
            $pathType = 'broken-symlink';
        } elseif ($exists) {
            $pathType = 'other';
        }

        $parentPath = dirname($absoluteOutputFolder);
        $parentConflict = $this->outputFolderParentConflict($absoluteOutputFolder);
        $targetBlocked = ($exists && !$isDirectory) || ($isSymlink && !$isDirectory);
        $parentBlocked = $parentConflict !== null;
        $creationRequired = !$isDirectory;
        $permissionPlan = $this->outputFolderCreationPermissionPlan(
            $absoluteOutputFolder,
            $creationRequired,
            $targetBlocked,
            $parentBlocked
        );
        $permissionBlocked = $permissionPlan['output_folder_parent_permission_blocked'];
        $blocked = $targetBlocked || $parentBlocked || $permissionBlocked;
        $symlinkTargetExists = $isSymlink && $exists;
        $symlinkTargetType = $isSymlink
            ? ($symlinkTargetExists ? $this->filesystemPathType($absoluteOutputFolder) : 'missing')
            : null;
        $errorBoundary = null;
        $errorClass = null;
        $errorMessage = null;
        if ($targetBlocked) {
            $errorBoundary = 'output-folder-target-exists-not-directory';
            $errorClass = 'FileExistsError';
            $errorMessage = "[Errno 17] File exists: '" . $absoluteOutputFolder . "'";
        } elseif ($parentBlocked) {
            if (($parentConflict['type'] ?? null) === 'broken-symlink') {
                $errorBoundary = 'output-folder-parent-broken-symlink';
                $errorClass = 'FileNotFoundError';
                $errorMessage = "[Errno 2] No such file or directory: '" . $absoluteOutputFolder . "'";
            } else {
                $errorBoundary = 'output-folder-parent-not-directory';
                $errorClass = 'NotADirectoryError';
                $errorMessage = "[Errno 20] Not a directory: '" . $absoluteOutputFolder . "'";
            }
        } elseif ($permissionBlocked) {
            $errorBoundary = 'output-folder-parent-permission-denied';
            $errorClass = 'PermissionError';
            $errorMessage = "[Errno 13] Permission denied: '" . $absoluteOutputFolder . "'";
        }

        return [
            'output_path_exists' => $exists,
            'output_path_type' => $pathType,
            'output_folder_exists' => $isDirectory,
            'output_folder_is_symlink' => $isSymlink,
            'output_folder_makedirs_follows_symlink' => $isSymlink && $isDirectory,
            'output_folder_symlink_target_exists' => $symlinkTargetExists,
            'output_folder_symlink_target_type' => $symlinkTargetType,
            'output_folder_broken_symlink' => $isSymlink && !$symlinkTargetExists,
            'output_folder_symlink_target_blocked' => $isSymlink && !$isDirectory,
            'output_folder_parent_path' => $parentPath,
            'output_folder_parent_path_exists' => file_exists($parentPath),
            'output_folder_parent_path_type' => $this->filesystemPathType($parentPath),
            'output_folder_parent_conflict_path' => $parentConflict['path'] ?? null,
            'output_folder_parent_conflict_type' => $parentConflict['type'] ?? null,
            'output_folder_parent_creation_blocked' => $parentBlocked,
            ...$permissionPlan,
            'upstream_creates_output_folder' => true,
            'native_plan_creates_output_folder' => false,
            'output_folder_creation_required' => $creationRequired,
            'output_folder_creation_call' => 'os.makedirs(out_folder, exist_ok=True)',
            'output_folder_creation_order' => 'after_list_input_files_before_chunk_files',
            'output_folder_creation_blocked' => $blocked,
            'output_folder_creation_error_boundary' => $errorBoundary,
            'output_folder_creation_error_class' => $errorClass,
            'output_folder_creation_error_message' => $errorMessage,
        ];
    }

    /**
     * @return array{entry_order_source: string, sort_applied_before_chunking: bool, preserves_os_listdir_order: bool, entry_basenames: list<string>, file_paths: list<string>, file_basenames: list<string>, skipped_non_file_basenames: list<string>, skipped_non_file_records: list<array{basename: string, path: string, path_type: string, is_symlink: bool, os_path_isfile: false, task_candidate: false}>, special_file_basenames: list<string>, fifo_basenames: list<string>, non_pdf_file_basenames: list<string>, symlink_basenames: list<string>, file_symlink_basenames: list<string>, skipped_symlink_basenames: list<string>, broken_symlink_basenames: list<string>}
     */
    private function inputDirectoryListing(string $inputFolder, bool $preserveDirectoryOrder = false): array
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
        $skippedNonFileRecords = [];
        $specialFileBasenames = [];
        $fifoBasenames = [];
        $symlinkBasenames = [];
        $fileSymlinkBasenames = [];
        $skippedSymlinkBasenames = [];
        $brokenSymlinkBasenames = [];

        $entries = $preserveDirectoryOrder
            ? $this->directoryEntriesInFilesystemOrder($inputFolder)
            : @scandir($inputFolder);
        if ($entries === false) {
            throw new InvalidArgumentException('Batch input folder cannot be listed: ' . $inputFolder);
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryBasenames[] = $entry;
            $path = $inputFolder . DIRECTORY_SEPARATOR . $entry;
            $isSymlink = is_link($path);
            if ($isSymlink) {
                $symlinkBasenames[] = $entry;
                if (!file_exists($path)) {
                    $brokenSymlinkBasenames[] = $entry;
                }
            }
            if (is_file($path)) {
                $filePathsByBasename[$entry] = $path;
                if ($isSymlink) {
                    $fileSymlinkBasenames[] = $entry;
                }
                continue;
            }

            $skippedNonFileBasenames[] = $entry;
            $pathType = $this->filesystemPathType($path);
            $skippedNonFileRecords[] = [
                'basename' => $entry,
                'path' => $path,
                'path_type' => $pathType,
                'is_symlink' => $isSymlink,
                'os_path_isfile' => false,
                'task_candidate' => false,
            ];
            if (!in_array($pathType, ['directory', 'broken-symlink', 'missing'], true)) {
                $specialFileBasenames[] = $entry;
            }
            if ($pathType === 'fifo') {
                $fifoBasenames[] = $entry;
            }
            if ($isSymlink) {
                $skippedSymlinkBasenames[] = $entry;
            }
        }

        if (!$preserveDirectoryOrder) {
            sort($entryBasenames, SORT_STRING);
            ksort($filePathsByBasename, SORT_STRING);
            sort($skippedNonFileBasenames, SORT_STRING);
            usort(
                $skippedNonFileRecords,
                static fn (array $left, array $right): int => strcmp($left['basename'], $right['basename'])
            );
            sort($specialFileBasenames, SORT_STRING);
            sort($fifoBasenames, SORT_STRING);
            sort($symlinkBasenames, SORT_STRING);
            sort($fileSymlinkBasenames, SORT_STRING);
            sort($skippedSymlinkBasenames, SORT_STRING);
            sort($brokenSymlinkBasenames, SORT_STRING);
        }
        $fileBasenames = array_keys($filePathsByBasename);

        return [
            'entry_order_source' => $preserveDirectoryOrder
                ? 'os.listdir filesystem order'
                : 'php.scandir sorted order',
            'sort_applied_before_chunking' => !$preserveDirectoryOrder,
            'preserves_os_listdir_order' => $preserveDirectoryOrder,
            'entry_basenames' => array_values($entryBasenames),
            'file_paths' => array_values($filePathsByBasename),
            'file_basenames' => array_values($fileBasenames),
            'skipped_non_file_basenames' => array_values($skippedNonFileBasenames),
            'skipped_non_file_records' => array_values($skippedNonFileRecords),
            'special_file_basenames' => array_values($specialFileBasenames),
            'fifo_basenames' => array_values($fifoBasenames),
            'non_pdf_file_basenames' => $this->nonPdfBasenames($fileBasenames),
            'symlink_basenames' => array_values($symlinkBasenames),
            'file_symlink_basenames' => array_values($fileSymlinkBasenames),
            'skipped_symlink_basenames' => array_values($skippedSymlinkBasenames),
            'broken_symlink_basenames' => array_values($brokenSymlinkBasenames),
        ];
    }

    /**
     * @return list<string>|false
     */
    private function directoryEntriesInFilesystemOrder(string $inputFolder): array|false
    {
        $handle = @opendir($inputFolder);
        if ($handle === false) {
            return false;
        }

        $entries = [];
        try {
            while (($entry = readdir($handle)) !== false) {
                $entries[] = $entry;
            }
        } finally {
            closedir($handle);
        }

        return $entries;
    }

    /**
     * @return array{path: string, type: string}|null
     */
    private function outputFolderParentConflict(string $absoluteOutputFolder): ?array
    {
        $parent = dirname($absoluteOutputFolder);
        while ($parent !== '' && $parent !== dirname($parent)) {
            if (file_exists($parent) || is_link($parent)) {
                return is_dir($parent)
                    ? null
                    : ['path' => $parent, 'type' => $this->filesystemPathType($parent)];
            }

            $parent = dirname($parent);
        }

        if ($parent !== '' && (file_exists($parent) || is_link($parent)) && !is_dir($parent)) {
            return ['path' => $parent, 'type' => $this->filesystemPathType($parent)];
        }

        return null;
    }

    /**
     * @return array{output_folder_creation_permission_path: string|null, output_folder_creation_permission_path_type: string|null, output_folder_creation_parent_writable: bool|null, output_folder_creation_parent_searchable: bool|null, output_folder_parent_permission_blocked: bool}
     */
    private function outputFolderCreationPermissionPlan(
        string $absoluteOutputFolder,
        bool $creationRequired,
        bool $targetBlocked,
        bool $parentBlocked
    ): array {
        $permissionPath = null;
        $permissionPathType = null;
        $parentWritable = null;
        $parentSearchable = null;
        $permissionBlocked = false;

        if ($creationRequired && !$targetBlocked && !$parentBlocked) {
            $permissionPath = $this->nearestExistingOutputCreationParent($absoluteOutputFolder);
            if ($permissionPath !== null) {
                $permissionPathType = $this->filesystemPathType($permissionPath);
                $parentWritable = is_writable($permissionPath);
                $parentSearchable = is_executable($permissionPath);
                $permissionBlocked = $permissionPathType === 'directory'
                    && (!$parentWritable || !$parentSearchable);
            }
        }

        return [
            'output_folder_creation_permission_path' => $permissionPath,
            'output_folder_creation_permission_path_type' => $permissionPathType,
            'output_folder_creation_parent_writable' => $parentWritable,
            'output_folder_creation_parent_searchable' => $parentSearchable,
            'output_folder_parent_permission_blocked' => $permissionBlocked,
        ];
    }

    private function nearestExistingOutputCreationParent(string $absoluteOutputFolder): ?string
    {
        $parent = dirname($absoluteOutputFolder);
        while ($parent !== '' && $parent !== dirname($parent)) {
            if (file_exists($parent) || is_link($parent)) {
                return is_dir($parent) ? $parent : null;
            }

            $parent = dirname($parent);
        }

        if ($parent !== '' && is_dir($parent)) {
            return $parent;
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
     *     context_manager_reached: true,
     *     context_enter_success: bool,
     *     blocked_by: string|null,
     *     context_manager_call: string,
     *     pool_variable: string,
     *     processes: int,
     *     worker_init_argument: string|null,
     *     wraps_pool_imap: bool,
     *     result_drain_inside_context: bool,
     *     worker_handler_override_inside_context: bool,
     *     context_exit_reached: bool,
     *     context_exit_after_worker_handler_override: bool,
     *     model_list_delete_after_context_exit: bool,
     *     executes_python_or_models: false,
     *     executes_multiprocessing: false,
     *     executes_external_pdf_tools: false
     * }
     */
    private function convertMainPoolContextManagerPlan(int $totalProcesses, array $modelHandoff): array
    {
        $entered = $totalProcesses >= 1;

        return [
            'source' => 'convert.py with mp.Pool context manager boundary',
            'order' => 'after_task_args_wraps_pool_imap_until_before_del_model_lst',
            'context_manager_reached' => true,
            'context_enter_success' => $entered,
            'blocked_by' => $entered ? null : 'pool-process-count-failed',
            'context_manager_call' => 'with mp.Pool(processes=total_processes, initializer=worker_init, initargs=(model_lst,)) as pool',
            'pool_variable' => 'pool',
            'processes' => $totalProcesses,
            'worker_init_argument' => $entered && is_string($modelHandoff['worker_init_argument'] ?? null)
                ? $modelHandoff['worker_init_argument']
                : null,
            'wraps_pool_imap' => $entered,
            'result_drain_inside_context' => $entered,
            'worker_handler_override_inside_context' => $entered,
            'context_exit_reached' => $entered,
            'context_exit_after_worker_handler_override' => $entered,
            'model_list_delete_after_context_exit' => $entered,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param array<string, mixed> $modelHandoff
     * @return array{
     *     source: string,
     *     order: string,
     *     initializer_reached: bool,
     *     blocked_by: string|null,
     *     initializer: string,
     *     initializer_argument_name: string,
     *     pool_initargs_source: string,
     *     processes: int,
     *     shared_model_value: string|null,
     *     shared_model_is_none: bool,
     *     loads_models_in_worker: bool,
     *     parent_shared_model_reused: bool,
     *     load_all_models_call: string|null,
     *     worker_global_variable: string,
     *     model_refs_assignment: string,
     *     model_refs_source: string|null,
     *     process_single_pdf_after_initializer: bool,
     *     upstream_worker_model_execution_required: bool,
     *     executes_python_or_models: false,
     *     executes_multiprocessing: false,
     *     executes_external_pdf_tools: false
     * }
     */
    private function convertMainWorkerInitializerPlan(int $totalProcesses, array $modelHandoff): array
    {
        $reached = $totalProcesses >= 1;
        $usesWorkerModelLoad = $reached && (bool) ($modelHandoff['worker_loads_models_when_init_arg_null'] ?? false);
        $reusesParentModelList = $reached && !$usesWorkerModelLoad;
        $shareMemoryReview = is_array($modelHandoff['model_share_memory_review'] ?? null)
            ? $modelHandoff['model_share_memory_review']
            : [];
        $sharedModelIsEmptyList = $reusesParentModelList && ($shareMemoryReview['model_list_empty'] ?? false) === true;
        $downstreamModelUnpackBoundary = is_string($modelHandoff['model_list_arity_error_boundary'] ?? null)
            ? (string) $modelHandoff['model_list_arity_error_boundary']
            : ($sharedModelIsEmptyList ? 'convert-single-pdf-model-unpack-failed' : null);

        return [
            'source' => 'convert.py worker_init shared_model boundary',
            'order' => 'after_pool_enter_before_process_single_pdf',
            'initializer_reached' => $reached,
            'blocked_by' => $reached ? null : 'pool-process-count-failed',
            'initializer' => 'worker_init',
            'initializer_argument_name' => 'shared_model',
            'pool_initargs_source' => 'initargs=(model_lst,)',
            'processes' => $totalProcesses,
            'shared_model_value' => $reached
                ? ($usesWorkerModelLoad ? 'None' : ($sharedModelIsEmptyList ? '[]' : 'model_lst'))
                : null,
            'shared_model_is_none' => $usesWorkerModelLoad,
            'shared_model_is_empty_list' => $sharedModelIsEmptyList,
            'worker_init_reload_condition' => 'shared_model is None',
            'loads_models_in_worker' => $usesWorkerModelLoad,
            'parent_shared_model_reused' => $reusesParentModelList,
            'empty_list_does_not_trigger_worker_load' => $sharedModelIsEmptyList,
            'load_all_models_call' => $usesWorkerModelLoad ? 'load_all_models()' : null,
            'worker_global_variable' => 'model_refs',
            'model_refs_assignment' => 'model_refs = shared_model',
            'model_refs_source' => $reached
                ? ($usesWorkerModelLoad ? 'worker-loaded-model-list' : ($sharedModelIsEmptyList ? 'parent-shared-empty-model-list' : 'parent-shared-model-list'))
                : null,
            'process_single_pdf_after_initializer' => $reached,
            'downstream_convert_single_pdf_model_unpack_boundary' => $downstreamModelUnpackBoundary,
            'downstream_convert_single_pdf_model_unpack_error_class' => $modelHandoff['model_list_arity_error_class'] ?? null,
            'downstream_convert_single_pdf_model_unpack_error_message' => $modelHandoff['model_list_arity_error_message'] ?? null,
            'model_slot_expected_count' => self::CONVERT_SINGLE_MODEL_SLOT_COUNT,
            'model_slot_count' => $shareMemoryReview['model_slot_count'] ?? null,
            'model_list_arity_checked_in_worker_init' => false,
            'model_list_arity_deferred_to_convert_single_pdf' => $downstreamModelUnpackBoundary !== null,
            'process_single_pdf_catches_downstream_unpack_error' => $downstreamModelUnpackBoundary !== null,
            'upstream_worker_model_execution_required' => $usesWorkerModelLoad,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
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
     *     model_list_value_before_delete: string|null,
     *     model_list_delete_deletes_none_reference: bool,
     *     cleanup_after_context_exit: bool,
     *     parent_model_list_loaded: bool,
     *     parent_share_memory_before_cleanup: bool,
     *     parent_shared_models_deleted_after_context_exit: bool,
     *     worker_init_argument: string|null,
     *     worker_model_load_branch_cleanup: bool,
     *     worker_exit_required_for_worker_loaded_models: bool,
     *     executes_python_or_models: false,
     *     executes_multiprocessing: false,
     *     executes_external_pdf_tools: false
     * }
     */
    private function convertMainPoolCleanupPlan(int $totalProcesses, array $modelHandoff): array
    {
        $reached = $totalProcesses >= 1;
        $parentModelListLoaded = $reached && (bool) ($modelHandoff['main_load_all_models'] ?? false);
        $parentShareMemoryBeforeCleanup = $reached && (bool) ($modelHandoff['share_memory_before_pool'] ?? false);
        $workerModelLoadBranch = $reached && (bool) ($modelHandoff['worker_loads_models_when_init_arg_null'] ?? false);
        $modelListValueBeforeDelete = null;
        if ($reached) {
            $modelListValueBeforeDelete = $parentModelListLoaded ? 'model_lst' : 'None';
        }

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
            'model_list_value_before_delete' => $modelListValueBeforeDelete,
            'model_list_delete_deletes_none_reference' => $workerModelLoadBranch,
            'cleanup_after_context_exit' => $reached,
            'parent_model_list_loaded' => $parentModelListLoaded,
            'parent_share_memory_before_cleanup' => $parentShareMemoryBeforeCleanup,
            'parent_shared_models_deleted_after_context_exit' => $parentModelListLoaded,
            'worker_init_argument' => $reached && is_string($modelHandoff['worker_init_argument'] ?? null)
                ? $modelHandoff['worker_init_argument']
                : null,
            'worker_model_load_branch_cleanup' => $workerModelLoadBranch,
            'worker_exit_required_for_worker_loaded_models' => $workerModelLoadBranch,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    private function isJsonMetadataLoadFailure(InvalidArgumentException $exception): bool
    {
        return $exception->getPrevious() instanceof JsonException;
    }

    private function runtimeMetadataFileLoadExceptionClass(?string $absoluteMetadataFile): string
    {
        if ($absoluteMetadataFile === null || $absoluteMetadataFile === '') {
            return InvalidArgumentException::class;
        }
        if (!file_exists($absoluteMetadataFile)) {
            return 'FileNotFoundError';
        }
        if (is_dir($absoluteMetadataFile)) {
            return 'IsADirectoryError';
        }
        if (!is_readable($absoluteMetadataFile)) {
            return 'PermissionError';
        }

        return 'OSError';
    }

    private function runtimeMetadataFileLoadUpstreamErrorMessage(?string $absoluteMetadataFile, string $fallback): string
    {
        if ($absoluteMetadataFile === null || $absoluteMetadataFile === '') {
            return $fallback;
        }
        if (!file_exists($absoluteMetadataFile)) {
            return "[Errno 2] No such file or directory: '" . $absoluteMetadataFile . "'";
        }
        if (is_dir($absoluteMetadataFile)) {
            return "[Errno 21] Is a directory: '" . $absoluteMetadataFile . "'";
        }
        if (!is_readable($absoluteMetadataFile)) {
            return "[Errno 13] Permission denied: '" . $absoluteMetadataFile . "'";
        }

        return $fallback;
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
     *     model_share_memory_review: array<string, mixed>,
     *     native_plan_loads_models: false,
     *     upstream_model_execution_required: bool,
     *     executes_python_or_models: false
     * }
     */
    private function convertMainModelHandoffPlan(
        ?string $torchDevice,
        ?string $torchDeviceModel,
        ?string $blockedBy = null,
        ?array $modelSlots = null
    ): array {
        $reached = $blockedBy === null;
        $normalizedDevice = $torchDevice;
        $normalizedDeviceModel = $torchDeviceModel;
        $usesMps = $reached && ($normalizedDevice === 'mps' || $normalizedDeviceModel === 'mps');
        $shareMemoryReview = $this->convertMainModelShareMemoryReview(
            $reached,
            $usesMps,
            $blockedBy,
            $modelSlots
        );
        $shareMemoryErrorBoundary = ($shareMemoryReview['share_memory_error_found'] ?? false) === true
            ? 'model-share-memory-failed'
            : null;
        $modelListEmpty = ($shareMemoryReview['model_list_empty'] ?? false) === true;

        return [
            'source' => 'convert.py settings.TORCH_DEVICE model handoff',
            'order' => 'after_spawn_start_method_before_conversion_summary',
            'model_handoff_reached' => $reached,
            'model_handoff_success' => $reached && $shareMemoryErrorBoundary === null,
            'model_handoff_error_boundary' => $shareMemoryErrorBoundary,
            'blocks_conversion_summary' => $shareMemoryErrorBoundary !== null,
            'blocks_task_args' => $shareMemoryErrorBoundary !== null,
            'blocks_pool_launch' => $shareMemoryErrorBoundary !== null,
            'blocked_by' => $blockedBy,
            'torch_device' => $torchDevice,
            'torch_device_model' => $torchDeviceModel,
            'uses_mps_no_shared_memory_branch' => $usesMps,
            'main_load_all_models' => $reached && !$usesMps,
            'share_memory_before_pool' => $reached && !$usesMps,
            'model_share_memory_loop_reached' => $reached && !$usesMps,
            'model_list_empty' => $modelListEmpty,
            'worker_init_receives_empty_model_list' => $modelListEmpty,
            'worker_loads_models_when_empty_list' => false,
            'empty_model_list_conversion_error_boundary' => $modelListEmpty
                ? 'convert-single-pdf-model-unpack-failed'
                : null,
            'empty_model_list_conversion_error_class' => $modelListEmpty
                ? 'ValueError'
                : null,
            'empty_model_list_conversion_error_message' => $modelListEmpty
                ? 'not enough values to unpack (expected 6, got 0)'
                : null,
            'empty_model_list_caught_by_process_single_pdf' => $modelListEmpty,
            'model_slot_expected_count' => self::CONVERT_SINGLE_MODEL_SLOT_COUNT,
            'model_slot_count' => $shareMemoryReview['model_slot_count'] ?? null,
            'model_list_arity_matches_convert_single_pdf_unpack' => $shareMemoryReview['model_slot_count_matches_convert_single_pdf_unpack'] ?? null,
            'model_list_arity_error_boundary' => $shareMemoryReview['model_list_arity_error_boundary'] ?? null,
            'model_list_arity_error_class' => $shareMemoryReview['model_list_arity_error_class'] ?? null,
            'model_list_arity_error_message' => $shareMemoryReview['model_list_arity_error_message'] ?? null,
            'model_list_arity_error_caught_by_process_single_pdf' => ($shareMemoryReview['model_list_arity_error_boundary'] ?? null) !== null,
            'model_list_arity_not_checked_before_pool_launch' => ($shareMemoryReview['model_list_arity_error_boundary'] ?? null) !== null,
            'worker_init_argument' => !$reached || $usesMps || $shareMemoryErrorBoundary !== null ? null : 'model_lst',
            'worker_loads_models_when_init_arg_null' => $usesMps,
            'warning' => $usesMps
                ? "Cannot use MPS with torch multiprocessing share_memory. This will make things less memory efficient. If you want to share memory, you have to use CUDA or CPU.\nSet the TORCH_DEVICE environment variable to change the device."
                : null,
            'model_share_memory_review' => $shareMemoryReview,
            'native_plan_loads_models' => false,
            'upstream_model_execution_required' => $reached,
            'executes_python_or_models' => false,
        ];
    }

    /**
     * @param list<mixed>|null $modelSlots
     * @return array<string, mixed>
     */
    private function convertMainModelShareMemoryReview(
        bool $modelHandoffReached,
        bool $usesMps,
        ?string $blockedBy,
        ?array $modelSlots
    ): array {
        $reviewReached = $modelHandoffReached && !$usesMps;
        $effectiveBlockedBy = null;
        if (!$modelHandoffReached) {
            $effectiveBlockedBy = $blockedBy ?? 'model-handoff-blocked';
        } elseif ($usesMps) {
            $effectiveBlockedBy = 'mps-worker-loads-models';
        }

        $slotRows = [];
        $noneSlotIndexes = [];
        $shareMemorySlotIndexes = [];
        $successfulShareMemorySlotIndexes = [];
        $shareMemoryErrorSlotIndexes = [];
        $slotsAfterFirstErrorNotCalled = [];
        $firstError = null;
        $containsErrorFixture = $reviewReached
            && $modelSlots !== null
            && $this->runtimeModelSlotsContainShareMemoryError($modelSlots);
        $stoppedOnError = false;
        if ($reviewReached && $modelSlots !== null) {
            foreach (array_values($modelSlots) as $index => $slot) {
                $isNone = $this->runtimeModelSlotIsNone($slot);
                if ($isNone) {
                    $noneSlotIndexes[] = $index;
                }

                $row = [
                    'index' => $index,
                    'label' => $this->runtimeModelSlotLabel($slot),
                    'is_none' => $isNone,
                    'share_memory_called' => false,
                ];

                if ($containsErrorFixture) {
                    $row['share_memory_success'] = null;
                    $row['blocked_by_previous_share_memory_error'] = $stoppedOnError;
                }

                if ($stoppedOnError) {
                    $slotsAfterFirstErrorNotCalled[] = $index;
                    $slotRows[] = $row;
                    continue;
                }

                if ($isNone) {
                    $slotRows[] = $row;
                    continue;
                }

                $shareMemorySlotIndexes[] = $index;
                $row['share_memory_called'] = true;
                $error = $this->runtimeModelSlotShareMemoryError($slot);
                if ($error === null) {
                    $successfulShareMemorySlotIndexes[] = $index;
                    if ($containsErrorFixture) {
                        $row['share_memory_success'] = true;
                    }
                    $slotRows[] = $row;
                    continue;
                }

                $shareMemoryErrorSlotIndexes[] = $index;
                $firstError ??= [
                    'index' => $index,
                    'label' => $row['label'],
                    'class' => $error['class'],
                    'message' => $error['message'],
                ];
                $row['share_memory_success'] = false;
                $row['share_memory_error_boundary'] = 'model-share-memory-failed';
                $row['share_memory_error_class'] = $error['class'];
                $row['share_memory_error_message'] = $error['message'];
                $slotRows[] = $row;
                $stoppedOnError = true;
            }
        }

        $fixtureUsed = $reviewReached && $modelSlots !== null;
        $modelListEmpty = $fixtureUsed && $modelSlots === [];
        $modelSlotCount = $fixtureUsed ? count($modelSlots) : null;
        $modelListArityMismatch = $modelSlotCount !== null && $modelSlotCount !== self::CONVERT_SINGLE_MODEL_SLOT_COUNT;
        $modelListArityErrorMessage = null;
        if ($modelListArityMismatch && $modelSlotCount < self::CONVERT_SINGLE_MODEL_SLOT_COUNT) {
            $modelListArityErrorMessage = sprintf(
                'not enough values to unpack (expected %d, got %d)',
                self::CONVERT_SINGLE_MODEL_SLOT_COUNT,
                $modelSlotCount
            );
        } elseif ($modelListArityMismatch) {
            $modelListArityErrorMessage = sprintf(
                'too many values to unpack (expected %d)',
                self::CONVERT_SINGLE_MODEL_SLOT_COUNT
            );
        }
        $shareMemoryErrorFound = $shareMemoryErrorSlotIndexes !== [];
        $modelListValue = null;
        if ($modelHandoffReached) {
            if ($usesMps) {
                $modelListValue = 'None';
            } elseif ($modelListEmpty) {
                $modelListValue = '[]';
            } else {
                $modelListValue = 'model_lst';
            }
        }

        return [
            'source' => 'convert.py load_all_models share_memory slot boundary',
            'order' => 'after_load_all_models_before_conversion_summary',
            'review_reached' => $reviewReached,
            'blocked_by' => $effectiveBlockedBy,
            'model_list_source' => $reviewReached ? 'load_all_models()' : null,
            'model_list_value' => $modelListValue,
            'model_list_fixture_used' => $fixtureUsed,
            'model_list_empty' => $fixtureUsed ? $modelListEmpty : null,
            'model_list_python_truthy' => $fixtureUsed ? !$modelListEmpty : null,
            'model_slot_fixture_used' => $fixtureUsed,
            'model_slot_expected_count' => self::CONVERT_SINGLE_MODEL_SLOT_COUNT,
            'model_slot_count' => $modelSlotCount,
            'model_slot_count_matches_convert_single_pdf_unpack' => $fixtureUsed ? !$modelListArityMismatch : null,
            'model_list_arity_error_boundary' => $modelListArityMismatch
                ? 'convert-single-pdf-model-unpack-failed'
                : null,
            'model_list_arity_error_class' => $modelListArityMismatch ? 'ValueError' : null,
            'model_list_arity_error_message' => $modelListArityErrorMessage,
            'model_list_arity_error_stage' => $modelListArityMismatch
                ? 'convert_single_pdf model_lst unpack'
                : null,
            'model_list_arity_error_caught_by_process_single_pdf' => $modelListArityMismatch,
            'model_list_arity_not_checked_before_pool_launch' => $modelListArityMismatch,
            'model_slots' => $slotRows,
            'none_model_slot_indexes' => $noneSlotIndexes,
            'share_memory_model_slot_indexes' => $shareMemorySlotIndexes,
            'share_memory_successful_model_slot_indexes' => $successfulShareMemorySlotIndexes,
            'share_memory_error_slot_indexes' => $shareMemoryErrorSlotIndexes,
            'share_memory_call_count' => $fixtureUsed ? count($shareMemorySlotIndexes) : null,
            'share_memory_error_found' => $shareMemoryErrorFound,
            'first_share_memory_error_index' => $firstError['index'] ?? null,
            'first_share_memory_error_label' => $firstError['label'] ?? null,
            'first_share_memory_error_class' => $firstError['class'] ?? null,
            'first_share_memory_error_message' => $firstError['message'] ?? null,
            'share_memory_loop_stops_on_first_error' => $shareMemoryErrorFound,
            'model_slots_after_first_error_not_called' => $slotsAfterFirstErrorNotCalled,
            'none_slots_skipped_before_share_memory' => $fixtureUsed ? $noneSlotIndexes !== [] : null,
            'none_skip_condition' => 'if model is None: continue',
            'share_memory_call' => 'model.share_memory()',
            'share_memory_loop_reached' => $reviewReached,
            'blocks_conversion_summary' => !$modelHandoffReached || $shareMemoryErrorFound,
            'blocks_task_args' => !$modelHandoffReached || $shareMemoryErrorFound,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param array<string, mixed> $modelHandoff
     */
    private function convertMainModelHandoffErrorBoundary(array $modelHandoff): ?string
    {
        $review = $modelHandoff['model_share_memory_review'] ?? null;
        if (is_array($review) && ($review['share_memory_error_found'] ?? false) === true) {
            return 'model-share-memory-failed';
        }

        return null;
    }

    /**
     * @param list<mixed> $modelSlots
     */
    private function runtimeModelSlotsContainShareMemoryError(array $modelSlots): bool
    {
        foreach ($modelSlots as $slot) {
            if ($this->runtimeModelSlotShareMemoryError($slot) !== null) {
                return true;
            }
        }

        return false;
    }

    private function runtimeModelSlotIsNone(mixed $slot): bool
    {
        return $slot === null
            || (is_array($slot) && ($slot['is_none'] ?? false) === true);
    }

    /**
     * @return array{class: string, message: string}|null
     */
    private function runtimeModelSlotShareMemoryError(mixed $slot): ?array
    {
        if (!is_array($slot)) {
            return null;
        }

        $hasErrorMessage = array_key_exists('share_memory_error', $slot)
            || array_key_exists('share_memory_error_message', $slot)
            || (($slot['share_memory_success'] ?? true) === false);
        if (!$hasErrorMessage) {
            return null;
        }

        $message = $slot['share_memory_error'] ?? $slot['share_memory_error_message'] ?? 'model.share_memory() failed';
        $class = $slot['share_memory_error_class'] ?? 'RuntimeError';

        return [
            'class' => is_string($class) && trim($class) !== '' ? trim($class) : 'RuntimeError',
            'message' => is_string($message) && trim($message) !== '' ? trim($message) : 'model.share_memory() failed',
        ];
    }

    private function runtimeModelSlotLabel(mixed $slot): ?string
    {
        if ($slot === null) {
            return null;
        }
        if (is_array($slot) && array_key_exists('label', $slot)) {
            $label = $slot['label'];

            return is_scalar($label) || $label === null ? ($label === null ? null : (string) $label) : get_debug_type($label);
        }
        if (is_bool($slot)) {
            return $slot ? 'true' : 'false';
        }
        if (is_scalar($slot)) {
            return (string) $slot;
        }

        return get_debug_type($slot);
    }

    private function runtimeReturnValueType(mixed $value): string
    {
        if ($value === null) {
            return 'NoneType';
        }
        if (is_int($value)) {
            return 'int';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_bool($value)) {
            return 'bool';
        }
        if (is_string($value)) {
            return 'str';
        }
        if (is_array($value)) {
            return array_is_list($value) ? 'list' : 'dict';
        }

        return get_debug_type($value);
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
        if ($errorBoundary === 'chunk-files-failed') {
            return 'division by zero';
        }
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
        $endOfOptionsBoundary = $this->runtimeMainArgparseEndOfOptionsBoundary($argv);

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
                'end_of_options_terminator_seen' => $endOfOptionsBoundary['terminator_seen'],
                'end_of_options_terminator_index' => $endOfOptionsBoundary['terminator_index'],
                'option_like_tokens_after_terminator_are_positionals' => $endOfOptionsBoundary['option_like_tokens_after_terminator_are_positionals'],
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
                'fromfile_prefix_chars_configured' => false,
                'at_file_tokens_expand_before_parse' => false,
                'at_prefixed_tokens_seen' => $this->runtimeMainArgparseAtFileTokens($argv) !== [],
                'at_prefixed_tokens_are_literal_cli_values' => $this->runtimeMainArgparseAtFileTokens($argv) !== [],
                'argfile_boundary' => $this->runtimeMainArgparseAtFileBoundary($argv),
                'end_of_options_boundary' => $endOfOptionsBoundary,
                'end_of_options_terminator_supported' => true,
                'end_of_options_separator_touches_filesystem' => false,
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
    private function runtimeMainArgparseResolveOptionName(string $token, array $optionNames, ?string $displayToken = null): array
    {
        $displayToken ??= $token;

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
                'error' => 'ambiguous option: ' . $displayToken . ' could match ' . implode(', ', $matches),
            ];
        }

        return ['option' => $token, 'error' => 'unrecognized arguments: ' . $displayToken];
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
            'end_of_options_terminator' => '--',
            'supports_end_of_options_terminator' => true,
            'fromfile_prefix_chars' => null,
            'expands_response_files' => false,
            'at_file_tokens_are_literals' => true,
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

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private function runtimeMainArgparseAtFileTokens(array $tokens): array
    {
        return array_values(array_filter(
            $tokens,
            static fn (string $token): bool => str_starts_with($token, '@')
        ));
    }

    /**
     * @param list<string> $tokens
     * @return array<string, mixed>
     */
    private function runtimeMainArgparseAtFileBoundary(array $tokens): array
    {
        $atFileTokens = $this->runtimeMainArgparseAtFileTokens($tokens);

        return [
            'source' => 'convert.py argparse.ArgumentParser response-file boundary',
            'argument_parser_call' => 'argparse.ArgumentParser(description="Convert multiple pdfs to markdown.")',
            'fromfile_prefix_chars' => null,
            'response_file_expansion_enabled' => false,
            'at_prefixed_tokens' => $atFileTokens,
            'at_prefixed_token_count' => count($atFileTokens),
            'tokens_remain_in_argv' => true,
            'reads_at_files_before_parse_args' => false,
            'filesystem_touched_before_error' => false,
            'blocks_runtime_preflight' => false,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array<string, mixed>
     */
    private function runtimeMainArgparseEndOfOptionsBoundary(array $tokens): array
    {
        $terminatorIndex = array_search('--', $tokens, true);
        $terminatorSeen = is_int($terminatorIndex);
        $tokensAfterTerminator = $terminatorSeen ? array_slice($tokens, $terminatorIndex + 1) : [];
        $optionLikeTokens = array_values(array_filter(
            $tokensAfterTerminator,
            static fn (string $token): bool => str_starts_with($token, '-')
        ));

        return [
            'source' => 'convert.py argparse -- end-of-options boundary',
            'argument_parser_call' => 'parser.parse_args()',
            'terminator' => '--',
            'terminator_seen' => $terminatorSeen,
            'terminator_index' => $terminatorSeen ? $terminatorIndex : null,
            'tokens_after_terminator' => $tokensAfterTerminator,
            'token_count_after_terminator' => count($tokensAfterTerminator),
            'option_like_tokens_after_terminator' => $optionLikeTokens,
            'option_like_token_count_after_terminator' => count($optionLikeTokens),
            'option_like_tokens_after_terminator_are_positionals' => $optionLikeTokens !== [],
            'terminator_consumed_by_argparse' => $terminatorSeen,
            'filesystem_touched_before_terminator_handling' => false,
            'blocks_runtime_preflight' => false,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    private function runtimeMainArgparseIntegerValue(string $value): ?int
    {
        $trimmed = trim($value);
        if (preg_match('/^[+-]?\d+(?:_\d+)*$/', $trimmed) !== 1) {
            return null;
        }

        return (int) str_replace('_', '', $trimmed);
    }

    private function runtimeMainArgparseMissingOptionValue(string $token): bool
    {
        if ($token === '-') {
            return false;
        }
        if ($token === '--' || str_starts_with($token, '--')) {
            return true;
        }
        if (!str_starts_with($token, '-')) {
            return false;
        }

        return preg_match('/^-(?:\d|\.\d)/', $token) !== 1;
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
            $type = @filetype($path);
            if (is_string($type) && $type !== '') {
                return $type;
            }

            return 'other';
        }
        if (is_link($path)) {
            return 'broken-symlink';
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

    /**
     * @return array<string, mixed>
     */
    private function runtimeInputOutputPathPlan(
        string $inputFolder,
        string $outputFolder,
        string $absoluteInputFolder,
        string $absoluteOutputFolder
    ): array {
        $inputWasAbsolute = str_starts_with($inputFolder, DIRECTORY_SEPARATOR);
        $outputWasAbsolute = str_starts_with($outputFolder, DIRECTORY_SEPARATOR);
        $inputHasLeadingTilde = $this->pathHasLeadingTilde($inputFolder);
        $outputHasLeadingTilde = $this->pathHasLeadingTilde($outputFolder);
        $processCwd = $this->logicalCurrentWorkingDirectory();
        $inputIsSymlink = is_link($absoluteInputFolder);
        $inputSymlinkTargetExists = $inputIsSymlink && file_exists($absoluteInputFolder);
        $inputRealpath = realpath($absoluteInputFolder);
        $logicalInputRealpath = is_string($inputRealpath) ? $this->logicalReviewPath($inputRealpath) : null;
        $inputRelativeToOutput = $this->pathIsStrictDescendant($absoluteInputFolder, $absoluteOutputFolder);
        $outputRelativeToInput = $this->pathIsStrictDescendant($absoluteOutputFolder, $absoluteInputFolder);
        $sameFolder = $absoluteInputFolder === $absoluteOutputFolder;
        $outputExistedBeforeListing = file_exists($absoluteOutputFolder) || is_link($absoluteOutputFolder);
        $outputPathTypeBeforeListing = $this->filesystemPathType($absoluteOutputFolder);
        $outputTaskCandidateBeforeCreation = $outputRelativeToInput
            && is_file($absoluteOutputFolder);
        $listdirBoundaryReview = $this->inputFolderListdirBoundaryReview($absoluteInputFolder);
        $sameFolderReview = [
            'source' => 'convert.py same input/output folder runtime preflight',
            'review_reached' => $sameFolder,
            'input_output_same_folder' => $sameFolder,
            'ordering' => 'os.listdir(in_folder) before os.makedirs(out_folder, exist_ok=True)',
            'listdir_runs_before_makedirs' => $sameFolder,
            'makedirs_exist_ok_directory_noop' => $sameFolder && is_dir($absoluteOutputFolder),
            'no_same_folder_runtime_guard' => $sameFolder,
            'task_args_out_folder_equals_input_folder' => $sameFolder,
            'existing_input_files_remain_task_candidates' => $sameFolder,
            'same_folder_output_artifact_directories_filtered_only_by_isfile' => $sameFolder,
            'native_plan_creates_output_folder' => false,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];

        return [
            'source' => 'convert.py os.path.abspath input/output boundary',
            'order' => 'after_parse_args_before_list_input_files',
            'input_folder_argument' => $inputFolder,
            'output_folder_argument' => $outputFolder,
            'input_folder_abspath_call' => 'os.path.abspath(args.in_folder)',
            'output_folder_abspath_call' => 'os.path.abspath(args.out_folder)',
            'input_folder_was_absolute' => $inputWasAbsolute,
            'output_folder_was_absolute' => $outputWasAbsolute,
            'input_folder_has_leading_tilde' => $inputHasLeadingTilde,
            'output_folder_has_leading_tilde' => $outputHasLeadingTilde,
            'input_folder_tilde_expanded_to_home' => false,
            'output_folder_tilde_expanded_to_home' => false,
            'input_folder_literal_tilde_path' => $inputHasLeadingTilde ? $absoluteInputFolder : null,
            'output_folder_literal_tilde_path' => $outputHasLeadingTilde ? $absoluteOutputFolder : null,
            'literal_tilde_segment_preserved' => $inputHasLeadingTilde || $outputHasLeadingTilde,
            'input_folder_abspath_base' => $inputWasAbsolute ? 'already_absolute' : 'process_cwd',
            'output_folder_abspath_base' => $outputWasAbsolute ? 'already_absolute' : 'process_cwd',
            'process_cwd' => $processCwd,
            'absolute_input_folder' => $absoluteInputFolder,
            'absolute_output_folder' => $absoluteOutputFolder,
            'input_folder_is_symlink' => $inputIsSymlink,
            'input_folder_symlink_target_exists' => $inputSymlinkTargetExists,
            'input_folder_symlink_target_type' => $inputIsSymlink
                ? ($inputSymlinkTargetExists ? $this->filesystemPathType($absoluteInputFolder) : 'missing')
                : null,
            'input_folder_broken_symlink' => $inputIsSymlink && !$inputSymlinkTargetExists,
            'input_folder_listdir_follows_symlink' => $inputIsSymlink && is_dir($absoluteInputFolder),
            'input_folder_realpath' => $logicalInputRealpath,
            'input_folder_realpath_differs_from_absolute' => $logicalInputRealpath !== null
                && $logicalInputRealpath !== $absoluteInputFolder,
            'input_folder_abspath_does_not_resolve_symlink' => $inputIsSymlink,
            'task_filepaths_preserve_input_folder_prefix' => true,
            'input_folder_listdir_boundary_review' => $listdirBoundaryReview,
            'input_folder_relative_to_process_cwd' => !$inputWasAbsolute,
            'output_folder_relative_to_process_cwd' => !$outputWasAbsolute,
            'input_folder_relative_to_output_folder' => $inputRelativeToOutput,
            'output_folder_relative_to_input_folder' => $outputRelativeToInput,
            'input_output_same_folder' => $sameFolder,
            'input_output_same_folder_review' => $sameFolderReview,
            'output_folder_nested_in_input_folder' => $outputRelativeToInput,
            'output_folder_existed_before_input_listing' => $outputExistedBeforeListing,
            'output_folder_path_type_before_input_listing' => $outputPathTypeBeforeListing,
            'output_folder_creation_after_input_listing_required' => !$outputExistedBeforeListing,
            'nested_output_folder_created_after_listing_not_task_candidate' => $outputRelativeToInput
                && !$outputExistedBeforeListing,
            'output_folder_task_candidate_before_creation' => $outputTaskCandidateBeforeCreation,
            'input_listing_uses_absolute_input_folder' => true,
            'output_creation_uses_absolute_output_folder' => true,
            'filesystem_touched_by_abspath' => false,
            'review_only' => true,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inputFolderListdirBoundaryReview(string $absoluteInputFolder): array
    {
        $pathExists = file_exists($absoluteInputFolder);
        $pathType = $this->filesystemPathType($absoluteInputFolder);
        $isSymlink = is_link($absoluteInputFolder);
        $symlinkTargetExists = $isSymlink && $pathExists;
        $isDirectory = is_dir($absoluteInputFolder);
        $isReadable = is_readable($absoluteInputFolder);
        $errorReason = null;
        $errorClass = null;

        if ($isSymlink && !$symlinkTargetExists) {
            $errorReason = 'broken-symlink';
            $errorClass = 'FileNotFoundError';
        } elseif (!$pathExists) {
            $errorReason = 'missing';
            $errorClass = 'FileNotFoundError';
        } elseif (!$isDirectory) {
            $errorReason = 'not-directory';
            $errorClass = 'NotADirectoryError';
        } elseif (!$isReadable) {
            $errorReason = 'permission-denied';
            $errorClass = 'PermissionError';
        }

        $errorBoundary = $errorReason === null ? null : 'input-folder-list-failed';
        $blocked = $errorBoundary !== null;

        return [
            'source' => 'convert.py os.listdir input-folder boundary',
            'order' => 'after_abspath_before_output_makedirs',
            'input_folder' => $absoluteInputFolder,
            'input_path_exists' => $pathExists,
            'input_path_type' => $pathType,
            'input_folder_is_symlink' => $isSymlink,
            'input_folder_symlink_target_exists' => $symlinkTargetExists,
            'input_folder_broken_symlink' => $isSymlink && !$symlinkTargetExists,
            'listdir_call' => 'os.listdir(in_folder)',
            'listdir_reached' => true,
            'listdir_success' => !$blocked,
            'error_boundary' => $errorBoundary,
            'error_reason' => $errorReason,
            'error_class' => $errorClass,
            'upstream_error_message_preview' => $blocked
                ? $this->runtimeMainPreflightUpstreamErrorMessage(
                    'input-folder-list-failed',
                    $absoluteInputFolder,
                    'Batch input folder cannot be listed: ' . $absoluteInputFolder
                )
                : null,
            'output_creation_blocked_by_listdir_failure' => $blocked,
            'chunking_blocked_by_listdir_failure' => $blocked,
            'metadata_load_blocked_by_listdir_failure' => $blocked,
            'spawn_start_method_blocked_by_listdir_failure' => $blocked,
            'model_handoff_blocked_by_listdir_failure' => $blocked,
            'task_args_blocked_by_listdir_failure' => $blocked,
            'pool_launch_blocked_by_listdir_failure' => $blocked,
            'review_only' => true,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    private function absolutePath(string $path): string
    {
        $absolute = str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : rtrim($this->logicalCurrentWorkingDirectory(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;

        return $this->normalizeAbsolutePath($absolute);
    }

    private function logicalCurrentWorkingDirectory(): string
    {
        $cwd = getcwd();
        if (!is_string($cwd) || $cwd === '') {
            return DIRECTORY_SEPARATOR;
        }

        return $this->logicalReviewPath($cwd);
    }

    private function logicalReviewPath(string $path): string
    {
        $logicalTemp = $this->normalizeAbsolutePath(sys_get_temp_dir());
        $physicalTemp = realpath($logicalTemp);
        if (
            is_string($physicalTemp)
            && $physicalTemp !== $logicalTemp
            && ($path === $physicalTemp || str_starts_with($path, rtrim($physicalTemp, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))
        ) {
            return $logicalTemp . substr($path, strlen($physicalTemp));
        }

        return $path;
    }

    private function pathHasLeadingTilde(string $path): bool
    {
        return $path === '~'
            || str_starts_with($path, '~/')
            || preg_match('/^~[^\/\\\\]*/', $path) === 1;
    }

    private function normalizeAbsolutePath(string $path): string
    {
        $parts = preg_split('#/+#', $path);
        $segments = [];
        foreach ($parts === false ? [] : $parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $part;
        }

        return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
    }

    private function pathIsStrictDescendant(string $path, string $parent): bool
    {
        $path = rtrim($this->normalizeAbsolutePath($path), DIRECTORY_SEPARATOR);
        $parent = rtrim($this->normalizeAbsolutePath($parent), DIRECTORY_SEPARATOR);

        if ($path === '' || $parent === '' || $path === $parent) {
            return false;
        }
        if ($parent === '') {
            return false;
        }

        return str_starts_with($path . DIRECTORY_SEPARATOR, $parent . DIRECTORY_SEPARATOR);
    }

    /**
     * @return array<string, mixed>
     */
    private function runtimeMetadataFilePathPlan(
        ?string $metadataFile,
        ?string $absoluteMetadataFile,
        string $absoluteInputFolder,
        string $absoluteOutputFolder
    ): array {
        $hasMetadataFile = $absoluteMetadataFile !== null;
        $input = $hasMetadataFile ? (string) $metadataFile : null;
        $isAbsoluteInput = $input !== null && str_starts_with($input, DIRECTORY_SEPARATOR);
        $isDashLiteral = $input === '-';
        $hasLeadingTilde = $input !== null && $this->pathHasLeadingTilde($input);
        $processCwd = $hasMetadataFile ? $this->absolutePath('.') : null;
        $inputFolderCandidate = $hasMetadataFile && !$isAbsoluteInput
            ? $this->normalizeAbsolutePath($absoluteInputFolder . DIRECTORY_SEPARATOR . $input)
            : null;
        $outputFolderCandidate = $hasMetadataFile && !$isAbsoluteInput
            ? $this->normalizeAbsolutePath($absoluteOutputFolder . DIRECTORY_SEPARATOR . $input)
            : null;
        $metadataPathExists = $hasMetadataFile && file_exists($absoluteMetadataFile);
        $metadataPathType = $hasMetadataFile ? $this->filesystemPathType($absoluteMetadataFile) : null;
        $metadataPathIsSymlink = $hasMetadataFile && is_link($absoluteMetadataFile);
        $metadataSymlinkTargetExists = $metadataPathIsSymlink && file_exists($absoluteMetadataFile);
        $metadataBrokenSymlink = $metadataPathIsSymlink && !$metadataSymlinkTargetExists;

        return [
            'metadata_file_input' => $input,
            'metadata_file_abspath_call' => $hasMetadataFile ? 'os.path.abspath(args.metadata_file)' : null,
            'metadata_file_abspath_order' => $hasMetadataFile ? 'after_chunk_files_before_json_load' : null,
            'metadata_file_abspath_base' => $hasMetadataFile ? ($isAbsoluteInput ? 'already_absolute' : 'process_cwd') : null,
            'metadata_file_process_cwd' => $processCwd,
            'metadata_file_has_leading_tilde' => $hasLeadingTilde,
            'metadata_file_tilde_expanded_to_home' => false,
            'metadata_file_literal_tilde_path' => $hasLeadingTilde ? $absoluteMetadataFile : null,
            'metadata_file_is_dash_literal' => $isDashLiteral,
            'metadata_file_dash_path' => $isDashLiteral ? $absoluteMetadataFile : null,
            'metadata_file_dash_treated_as_stdin' => false,
            'metadata_file_stdin_read' => false,
            'metadata_file_open_uses_filesystem_path' => $hasMetadataFile,
            'metadata_file_path_exists' => $metadataPathExists,
            'metadata_file_path_type' => $metadataPathType,
            'metadata_file_is_symlink' => $metadataPathIsSymlink,
            'metadata_file_open_follows_symlink' => $metadataPathIsSymlink,
            'metadata_file_symlink_target_exists' => $metadataSymlinkTargetExists,
            'metadata_file_symlink_target_type' => $metadataPathIsSymlink
                ? ($metadataSymlinkTargetExists ? $metadataPathType : 'missing')
                : null,
            'metadata_file_broken_symlink' => $metadataBrokenSymlink,
            'metadata_file_open_broken_symlink_fails' => $metadataBrokenSymlink,
            'metadata_file_open_call' => $hasMetadataFile ? 'open(metadata_file, "r")' : null,
            'metadata_file_open_order' => $hasMetadataFile ? 'after_abspath_before_json_load' : null,
            'metadata_file_relative_to_process_cwd' => $hasMetadataFile && !$isAbsoluteInput,
            'metadata_file_relative_to_input_folder' => false,
            'metadata_file_relative_to_output_folder' => false,
            'metadata_file_input_folder_candidate' => $inputFolderCandidate,
            'metadata_file_output_folder_candidate' => $outputFolderCandidate,
            'metadata_file_input_folder_candidate_exists' => $inputFolderCandidate === null ? false : file_exists($inputFolderCandidate),
            'metadata_file_output_folder_candidate_exists' => $outputFolderCandidate === null ? false : file_exists($outputFolderCandidate),
        ];
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
