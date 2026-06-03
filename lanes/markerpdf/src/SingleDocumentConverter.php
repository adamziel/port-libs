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
}
