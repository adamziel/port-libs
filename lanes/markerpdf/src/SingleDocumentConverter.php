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

                $resolvedOption = $this->runtimeArgumentResolveOptionName($optionName, array_keys($definitions));
                if ($resolvedOption['error'] !== null) {
                    return $this->runtimeArgumentErrorPlan($tokens, $resolvedOption['error'], $optionName);
                }
                $optionName = $resolvedOption['option'];

                if (!$valueProvided) {
                    $nextIndex = $index + 1;
                    if ($nextIndex >= $count || $tokens[$nextIndex] === '--' || str_starts_with($tokens[$nextIndex], '--')) {
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
                if ($definition['type'] === 'int') {
                    $integer = $this->runtimeArgumentIntegerValue((string) $value);
                    if ($integer === null) {
                        return $this->runtimeArgumentErrorPlan(
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

        $basename = basename($filename);
        if ($basename === '' || $basename === '.' || $basename === '..') {
            throw new InvalidArgumentException('Single-document runtime preflight filename must include a basename.');
        }

        $parsedLanguages = $this->parseLanguages($languages);
        $subfolder = $this->writer->getSubfolderPath($outputFolder, $basename);
        $markdownPath = $this->writer->getMarkdownFilepath($outputFolder, $basename);

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
            'model_boundary' => [
                'load_function' => 'load_all_models',
                'loads_all_models_before_conversion' => true,
                'passes_model_list_to_convert_single_pdf' => true,
                'native_plan_loads_models' => false,
                'upstream_model_execution_required' => true,
            ],
            'conversion_call' => [
                'function' => 'convert_single_pdf',
                'receives_filename' => $filename,
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
        $basename = basename($filename);
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
    private function runtimeArgumentResolveOptionName(string $token, array $optionNames): array
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

    private function runtimeArgumentIntegerValue(string $value): ?int
    {
        $trimmed = trim($value);
        if (preg_match('/^[+-]?\d+$/', $trimmed) !== 1) {
            return null;
        }

        return (int) $trimmed;
    }
}
