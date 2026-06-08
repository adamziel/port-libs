<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class SingleDocumentConverter
{
    private OutputWriter $writer;

    public function __construct(?OutputWriter $writer = null)
    {
        $this->writer = $writer ?? new OutputWriter();
    }

    /**
     * Native boundary for convert_single.py's `args.langs.split(",") if args.langs else None`.
     *
     * @return list<string>|null
     */
    public function parseLanguages(?string $languages): ?array
    {
        if ($languages === null || $languages === '') {
            return null;
        }

        return explode(',', $languages);
    }

    /**
     * Native no-execution boundary for convert_single.py::main argparse admission.
     *
     * Upstream parses CLI arguments and derives langs with
     * `args.langs.split(",") if args.langs else None` before loading models or
     * touching the output folder. This records that first runtime boundary for
     * WordPress single-upload review without importing Python, pdfium, Torch, or
     * model code.
     *
     * @param list<string|int|float|bool> $argv Arguments after the script name.
     * @return array<string, mixed>
     */
    public function runtimeArgumentPreflightPlan(array $argv): array
    {
        $tokens = $this->normalizeRuntimeArgv($argv);
        $options = [
            'max_pages' => null,
            'start_page' => null,
            'langs' => null,
            'batch_multiplier' => 2,
        ];
        $defaultsApplied = [
            'max_pages' => true,
            'start_page' => true,
            'langs' => true,
            'batch_multiplier' => true,
        ];
        $optionOccurrences = [
            'max_pages' => 0,
            'start_page' => 0,
            'langs' => 0,
            'batch_multiplier' => 0,
        ];
        $optionValueHistory = [];
        $definitions = $this->runtimeArgumentOptionDefinitions();
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

                $resolvedOption = $this->runtimeArgumentResolveOptionName($optionName, array_keys($definitions), $token);
                if ($resolvedOption['error'] !== null) {
                    return $this->runtimeArgumentErrorPlan($tokens, $resolvedOption['error'], $token);
                }
                $optionName = $resolvedOption['option'];

                if (!$valueProvided) {
                    $nextIndex = $index + 1;
                    if ($nextIndex >= $count || $this->runtimeArgumentMissingOptionValue($tokens[$nextIndex])) {
                        return $this->runtimeArgumentErrorPlan(
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
                    $integer = $this->runtimeArgumentIntegerValue((string) $value);
                    if ($integer === null) {
                        return $this->runtimeArgumentErrorPlan(
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
                return $this->runtimeArgumentErrorPlan($tokens, 'unrecognized arguments: ' . $token, $token);
            }

            $positionals[] = $token;
        }

        if (count($positionals) < 2) {
            $missing = [];
            if (!array_key_exists(0, $positionals)) {
                $missing[] = 'filename';
            }
            if (!array_key_exists(1, $positionals)) {
                $missing[] = 'output';
            }

            return $this->runtimeArgumentErrorPlan(
                $tokens,
                'the following arguments are required: ' . implode(', ', $missing),
                null,
                $missing
            );
        }

        if (count($positionals) > 2) {
            $extra = array_slice($positionals, 2);

            return $this->runtimeArgumentErrorPlan(
                $tokens,
                'unrecognized arguments: ' . implode(' ', $extra),
                $extra[0] ?? null
            );
        }

        $parsedLangs = $this->parseLanguages($options['langs']);
        $langsArgument = $options['langs'];
        $repeatedOptionCounts = array_filter(
            $optionOccurrences,
            static fn (int $count): bool => $count > 1
        );
        $repeatedOptions = array_keys($repeatedOptionCounts);
        $endOfOptionsBoundary = $this->runtimeArgumentEndOfOptionsBoundary($tokens);

        return [
            'schema' => 'markerpdf.convert_single_argparse_preflight.v1',
            'source' => 'sddai/markerPDF convert_single.py::main argparse.ArgumentParser.parse_args',
            'parser' => $this->runtimeArgumentParserPlan(),
            'argv' => $tokens,
            'preflight_order' => $this->runtimeArgumentPreflightOrder(),
            'parse_args' => [
                'source' => 'argparse.ArgumentParser.parse_args',
                'order' => 'after_configure_logging_before_parse_langs',
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
                'filename' => $positionals[0],
                'output' => $positionals[1],
                'positionals' => [
                    'filename' => $positionals[0],
                    'output' => $positionals[1],
                ],
                'options' => $options,
                'defaults_applied' => $defaultsApplied,
                'option_occurrences' => $optionOccurrences,
                'option_value_history' => $optionValueHistory,
                'repeated_options' => $repeatedOptions,
                'repeated_option_counts' => $repeatedOptionCounts,
                'last_occurrence_wins' => $repeatedOptions !== [],
                'argfile_boundary' => $this->runtimeArgumentAtFileBoundary($tokens),
                'end_of_options_boundary' => $endOfOptionsBoundary,
            ],
            'language_parse' => [
                'source' => 'args.langs.split(",") if args.langs else None',
                'order' => 'after_parse_args_before_load_all_models',
                'langs_argument' => $langsArgument,
                'parsed_langs' => $parsedLangs,
                'empty_langs_string_becomes_none' => $langsArgument === '',
                'whitespace_preserved' => is_string($langsArgument)
                    && $parsedLangs !== null
                    && preg_match('/(^\s|\s$|,\s|\s,)/', $langsArgument) === 1,
                'blocks_model_load' => false,
            ],
            'semantic_boundaries' => [
                'filename_exists_checked_by_argparse' => false,
                'output_folder_exists_checked_by_argparse' => false,
                'filesystem_touched_before_model_load' => false,
                'max_pages_less_than_one_deferred_to_convert_single_pdf' => $options['max_pages'] !== null && $options['max_pages'] < 1,
                'negative_start_page_allowed_by_argparse' => $options['start_page'] !== null && $options['start_page'] < 0,
                'batch_multiplier_less_than_one_deferred_to_convert_single_pdf' => $options['batch_multiplier'] < 1,
                'empty_langs_string_deferred_to_none' => $langsArgument === '',
                'repeated_options_last_occurrence_wins' => $repeatedOptions !== [],
                'fromfile_prefix_chars_configured' => false,
                'at_file_tokens_expand_before_parse' => false,
                'at_prefixed_tokens_seen' => $this->runtimeArgumentAtFileTokens($tokens) !== [],
                'at_prefixed_tokens_are_literal_cli_values' => $this->runtimeArgumentAtFileTokens($tokens) !== [],
                'end_of_options_terminator_supported' => true,
                'end_of_options_separator_touches_filesystem' => false,
                'option_like_positionals_allowed_after_terminator' => $endOfOptionsBoundary['option_like_tokens_after_terminator_are_positionals'],
            ],
            'blocked_by' => null,
            'blocked_stages' => [],
            'next_stage' => 'load_all_models',
            'review_only' => true,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_streamlit' => false,
            'executes_fastapi' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * Native no-execution boundary for convert_single.py runtime admission.
     *
     * Upstream convert_single.py configures logging, parses CLI options,
     * loads every model with load_all_models(), converts a single PDF, then
     * always calls save_markdown even when the returned text is empty. Unlike
     * batch convert.py it does not check marker.output::markdown_exists or
     * apply the --min_length embedded-text gate before model loading.
     *
     * @return array<string, mixed>
     */
    public function runtimePreflightPlan(
        string $filename,
        string $outputFolder,
        ?int $maxPages = null,
        ?int $startPage = null,
        ?string $languages = null,
        int $batchMultiplier = 2
    ): array {
        if (trim($filename) === '') {
            throw new InvalidArgumentException('Single-document runtime preflight filename must not be empty.');
        }
        if (trim($outputFolder) === '') {
            throw new InvalidArgumentException('Single-document runtime preflight output folder must not be empty.');
        }

        $basename = $this->upstreamOutputBasename($filename);
        if ($basename === '.' || $basename === '..') {
            throw new InvalidArgumentException('Single-document runtime preflight filename must include a basename.');
        }

        $parsedLanguages = $this->parseLanguages($languages);
        $subfolder = $this->writer->getSubfolderPath($outputFolder, $basename);
        $markdownPath = $this->writer->getMarkdownFilepath($outputFolder, $basename);
        $modelLoadSequence = $this->singleDocumentModelLoadSequence();

        return [
            'schema' => 'markerpdf.convert_single_runtime_preflight.v1',
            'source' => 'sddai/markerPDF convert_single.py::main + marker.models::load_all_models + marker.convert::convert_single_pdf + marker.output::save_markdown',
            'filename' => $basename,
            'filepath' => $filename,
            'output_folder' => $outputFolder,
            'subfolder' => $subfolder,
            'markdown_path' => $markdownPath,
            'environment' => [
                'PYTORCH_ENABLE_MPS_FALLBACK' => '1',
            ],
            'preflight_order' => [
                'configure_logging',
                'parse_args',
                'parse_langs',
                'load_all_models',
                'convert_single_pdf',
                'save_markdown',
                'print_saved_folder',
                'print_total_time',
            ],
            'options' => [
                'max_pages' => $maxPages,
                'start_page' => $startPage,
                'langs' => $parsedLanguages,
                'batch_multiplier' => $batchMultiplier,
            ],
            'model_load_sequence' => $modelLoadSequence,
            'model_boundary' => [
                'load_function' => 'load_all_models',
                'load_function_source' => 'marker.models.load_all_models',
                'loads_all_models_before_conversion' => true,
                'model_list_source' => 'load_all_models()',
                'model_list_variable' => 'model_lst',
                'model_count' => $modelLoadSequence['model_count'],
                'model_slot_order' => $modelLoadSequence['model_slot_order'],
                'model_slots' => $modelLoadSequence['model_slots'],
                'passes_model_list_to_convert_single_pdf' => true,
                'recognition_model_always_loaded_for_single_document' => true,
                'single_document_share_memory_loop' => false,
                'native_plan_loads_models' => false,
                'upstream_model_execution_required' => true,
            ],
            'conversion_call' => [
                'function' => 'convert_single_pdf',
                'receives_filename' => $filename,
                'receives_model_list' => true,
                'model_argument_source' => 'model_lst returned by load_all_models()',
                'model_slot_order' => $modelLoadSequence['model_slot_order'],
                'receives_options' => [
                    'max_pages' => $maxPages,
                    'langs' => $parsedLanguages,
                    'batch_multiplier' => $batchMultiplier,
                    'start_page' => $startPage,
                ],
                'metadata_argument_source' => null,
                'ocr_all_pages_argument_source' => 'settings.OCR_ALL_PAGES default inside marker.convert.convert_single_pdf',
            ],
            'output_policy' => [
                'function' => 'save_markdown',
                'uses_basename_after_conversion' => true,
                'basename_source' => 'os.path.basename(fname) after convert_single_pdf returns',
                'empty_basename_after_trailing_separator' => $basename === '' && str_ends_with($filename, '/'),
                'existing_markdown' => $this->writer->markdownExists($outputFolder, $basename),
                'skips_existing_markdown' => false,
                'min_length_preflight' => false,
                'filetype_preflight_before_model_load' => false,
                'saves_empty_output' => true,
                'saved_folder_message' => 'Saved markdown to the {subfolder_path} folder',
                'elapsed_message_prefix' => 'Total time: ',
            ],
            'review_only' => true,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_streamlit' => false,
            'executes_fastapi' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function singleDocumentModelLoadSequence(): array
    {
        $modelSlots = [
            [
                'index' => 0,
                'label' => 'texify',
                'setup_function' => 'setup_texify_model',
                'model_loader' => 'load_texify_model',
                'processor_loader' => 'load_texify_processor',
                'checkpoint_source' => 'settings.TEXIFY_MODEL_NAME',
                'default_device_source' => 'settings.TORCH_DEVICE_MODEL',
                'default_dtype_source' => 'settings.TEXIFY_DTYPE',
            ],
            [
                'index' => 1,
                'label' => 'layout',
                'setup_function' => 'setup_layout_model',
                'model_loader' => 'load_detection_model',
                'processor_loader' => 'load_detection_processor',
                'checkpoint_source' => 'settings.LAYOUT_MODEL_CHECKPOINT',
                'default_device_source' => null,
                'default_dtype_source' => null,
            ],
            [
                'index' => 2,
                'label' => 'order',
                'setup_function' => 'setup_order_model',
                'model_loader' => 'load_order_model',
                'processor_loader' => 'load_order_processor',
                'checkpoint_source' => null,
                'default_device_source' => null,
                'default_dtype_source' => null,
            ],
            [
                'index' => 3,
                'label' => 'detection',
                'setup_function' => 'setup_detection_model',
                'model_loader' => 'load_detection_model',
                'processor_loader' => 'load_detection_processor',
                'checkpoint_source' => null,
                'default_device_source' => null,
                'default_dtype_source' => null,
            ],
            [
                'index' => 4,
                'label' => 'ocr',
                'setup_function' => 'setup_recognition_model',
                'model_loader' => 'load_recognition_model',
                'processor_loader' => 'load_recognition_processor',
                'checkpoint_source' => null,
                'default_device_source' => null,
                'default_dtype_source' => null,
            ],
            [
                'index' => 5,
                'label' => 'table_model',
                'setup_function' => 'setup_table_rec_model',
                'model_loader' => 'load_table_model',
                'processor_loader' => 'load_table_processor',
                'checkpoint_source' => null,
                'default_device_source' => null,
                'default_dtype_source' => null,
            ],
        ];

        return [
            'source' => 'marker.models.load_all_models',
            'upstream_statement' => 'model_lst = load_all_models()',
            'order' => 'after_parse_langs_before_convert_single_pdf',
            'model_list_variable' => 'model_lst',
            'model_count' => count($modelSlots),
            'device_argument' => null,
            'dtype_argument' => null,
            'device_dtype_assertion_reached' => false,
            'uses_default_device_and_dtype' => true,
            'construction_order' => [
                'setup_detection_model',
                'setup_layout_model',
                'setup_order_model',
                'setup_recognition_model',
                'setup_texify_model',
                'setup_table_rec_model',
            ],
            'model_slot_order' => array_column($modelSlots, 'label'),
            'model_slots' => $modelSlots,
            'native_plan_loads_models' => false,
            'executes_python_or_models' => false,
        ];
    }

    /**
     * Native supplied-converter boundary for top-level convert_single.py.
     *
     * @param callable(string, array{max_pages: int|null, start_page: int|null, langs: list<string>|null, batch_multiplier: int}): mixed $converter
     * @return array{status: string, filename: string, output_folder: string, markdown: string, images: list<string>, elapsed_seconds: float, options: array{max_pages: int|null, start_page: int|null, langs: list<string>|null, batch_multiplier: int}}
     */
    public function convert(
        string $filename,
        string $outputFolder,
        callable $converter,
        ?int $maxPages = null,
        ?int $startPage = null,
        ?string $languages = null,
        int $batchMultiplier = 2
    ): array {
        $options = [
            'max_pages' => $maxPages,
            'start_page' => $startPage,
            'langs' => $this->parseLanguages($languages),
            'batch_multiplier' => $batchMultiplier,
        ];

        $started = microtime(true);
        $conversion = $this->normalizeConversion($converter($filename, $options));
        $basename = $this->upstreamOutputBasename($filename);
        $subfolder = $this->writer->saveMarkdown(
            $outputFolder,
            $basename,
            $conversion['text'],
            $conversion['images'],
            $conversion['metadata']
        );

        return [
            'status' => 'converted',
            'filename' => $basename,
            'output_folder' => $subfolder,
            'markdown' => $this->writer->getMarkdownFilepath($outputFolder, $basename),
            'images' => array_keys($conversion['images']),
            'elapsed_seconds' => microtime(true) - $started,
            'options' => $options,
        ];
    }

    private function upstreamOutputBasename(string $filename): string
    {
        $lastSeparator = strrpos($filename, '/');
        if ($lastSeparator === false) {
            return $filename;
        }

        return substr($filename, $lastSeparator + 1);
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
            throw new InvalidArgumentException('Single-document converter must return text or a conversion array.');
        }

        $text = $conversion['text']
            ?? $conversion['full_text']
            ?? $conversion['markdown']
            ?? $conversion[0]
            ?? '';
        $images = $conversion['images'] ?? $conversion[1] ?? [];
        $metadata = $conversion['metadata'] ?? $conversion['out_metadata'] ?? $conversion[2] ?? [];

        if (!is_array($images) || !is_array($metadata)) {
            throw new InvalidArgumentException('Single-document converter images and metadata must be arrays.');
        }

        return [
            'text' => (string) $text,
            'images' => $images,
            'metadata' => $metadata,
        ];
    }

    /**
     * @return list<string>
     */
    private function runtimeArgumentPreflightOrder(): array
    {
        return [
            'configure_logging',
            'parse_args',
            'parse_langs',
            'load_all_models',
            'convert_single_pdf',
            'save_markdown',
            'print_saved_folder',
            'print_total_time',
        ];
    }

    /**
     * @param list<string> $argv
     * @param list<string> $missingRequiredArguments
     * @return array<string, mixed>
     */
    private function runtimeArgumentErrorPlan(
        array $argv,
        string $message,
        ?string $errorArgument = null,
        array $missingRequiredArguments = []
    ): array {
        $endOfOptionsBoundary = $this->runtimeArgumentEndOfOptionsBoundary($argv);

        return [
            'schema' => 'markerpdf.convert_single_argparse_preflight.v1',
            'source' => 'sddai/markerPDF convert_single.py::main argparse.ArgumentParser.parse_args',
            'parser' => $this->runtimeArgumentParserPlan(),
            'argv' => $argv,
            'preflight_order' => $this->runtimeArgumentPreflightOrder(),
            'parse_args' => [
                'source' => 'argparse.ArgumentParser.parse_args',
                'order' => 'after_configure_logging_before_parse_langs',
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
            'language_parse' => [
                'source' => 'args.langs.split(",") if args.langs else None',
                'order' => 'after_parse_args_before_load_all_models',
                'parse_langs_reached' => false,
                'blocked_by' => 'parse_args',
                'langs_argument' => null,
                'parsed_langs' => null,
                'empty_langs_string_becomes_none' => false,
                'whitespace_preserved' => false,
                'blocks_model_load' => true,
            ],
            'semantic_boundaries' => [
                'filename_exists_checked_by_argparse' => false,
                'output_folder_exists_checked_by_argparse' => false,
                'filesystem_touched_before_error' => false,
                'model_handoff_reached_before_error' => false,
                'fromfile_prefix_chars_configured' => false,
                'at_file_tokens_expand_before_parse' => false,
                'at_prefixed_tokens_seen' => $this->runtimeArgumentAtFileTokens($argv) !== [],
                'at_prefixed_tokens_are_literal_cli_values' => $this->runtimeArgumentAtFileTokens($argv) !== [],
                'argfile_boundary' => $this->runtimeArgumentAtFileBoundary($argv),
                'end_of_options_boundary' => $endOfOptionsBoundary,
                'end_of_options_terminator_supported' => true,
                'end_of_options_separator_touches_filesystem' => false,
            ],
            'blocked_by' => 'parse_args',
            'blocked_stages' => $this->runtimeArgumentBlockedStages(),
            'next_stage' => null,
            'review_only' => true,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_streamlit' => false,
            'executes_fastapi' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @return array<string, array{key: string, type: string}>
     */
    private function runtimeArgumentOptionDefinitions(): array
    {
        return [
            '--max_pages' => ['key' => 'max_pages', 'type' => 'int'],
            '--start_page' => ['key' => 'start_page', 'type' => 'int'],
            '--langs' => ['key' => 'langs', 'type' => 'str'],
            '--batch_multiplier' => ['key' => 'batch_multiplier', 'type' => 'int'],
        ];
    }

    /**
     * @param list<string> $optionNames
     * @return array{option: string, error: string|null}
     */
    private function runtimeArgumentResolveOptionName(string $token, array $optionNames, ?string $displayToken = null): array
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
    private function runtimeArgumentParserPlan(): array
    {
        return [
            'description' => null,
            'positionals' => [
                'filename' => 'PDF file to parse',
                'output' => 'Output base folder path',
            ],
            'options' => [
                '--max_pages' => ['type' => 'int', 'default' => null, 'dest' => 'max_pages'],
                '--start_page' => ['type' => 'int', 'default' => null, 'dest' => 'start_page'],
                '--langs' => ['type' => 'str', 'default' => null, 'dest' => 'langs'],
                '--batch_multiplier' => ['type' => 'int', 'default' => 2, 'dest' => 'batch_multiplier'],
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
    private function runtimeArgumentBlockedStages(): array
    {
        return [
            'parse_langs',
            'load_all_models',
            'convert_single_pdf',
            'save_markdown',
            'print_saved_folder',
            'print_total_time',
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
                throw new InvalidArgumentException('convert_single.py argv tokens must be scalar CLI values.');
            }

            $tokens[] = (string) $token;
        }

        return $tokens;
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private function runtimeArgumentAtFileTokens(array $tokens): array
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
    private function runtimeArgumentAtFileBoundary(array $tokens): array
    {
        $atFileTokens = $this->runtimeArgumentAtFileTokens($tokens);

        return [
            'source' => 'convert_single.py argparse.ArgumentParser response-file boundary',
            'argument_parser_call' => 'argparse.ArgumentParser()',
            'fromfile_prefix_chars' => null,
            'response_file_expansion_enabled' => false,
            'at_prefixed_tokens' => $atFileTokens,
            'at_prefixed_token_count' => count($atFileTokens),
            'tokens_remain_in_argv' => true,
            'reads_at_files_before_parse_args' => false,
            'filesystem_touched_before_error' => false,
            'blocks_model_load' => false,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_streamlit' => false,
            'executes_fastapi' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array<string, mixed>
     */
    private function runtimeArgumentEndOfOptionsBoundary(array $tokens): array
    {
        $terminatorIndex = array_search('--', $tokens, true);
        $terminatorSeen = is_int($terminatorIndex);
        $tokensAfterTerminator = $terminatorSeen ? array_slice($tokens, $terminatorIndex + 1) : [];
        $optionLikeTokens = array_values(array_filter(
            $tokensAfterTerminator,
            static fn (string $token): bool => str_starts_with($token, '-')
        ));

        return [
            'source' => 'convert_single.py argparse -- end-of-options boundary',
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
            'blocks_model_load' => false,
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_streamlit' => false,
            'executes_fastapi' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    private function runtimeArgumentIntegerValue(string $value): ?int
    {
        $trimmed = trim($value);
        if (preg_match('/^[+-]?\d+(?:_\d+)*$/', $trimmed) !== 1) {
            return null;
        }

        return (int) str_replace('_', '', $trimmed);
    }

    private function runtimeArgumentMissingOptionValue(string $token): bool
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
}
