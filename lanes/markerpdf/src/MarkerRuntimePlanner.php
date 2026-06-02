<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use RuntimeException;

final class MarkerRuntimePlanner
{
    /**
     * Native boundary for marker.logger::configure_logging.
     *
     * @return array{root_level: string, logger_levels: array<string, string>, warning_filters: list<array{action: string, category: string}>}
     */
    public function loggingPlan(): array
    {
        return [
            'root_level' => 'WARNING',
            'logger_levels' => [
                'pdfminer' => 'ERROR',
                'PIL' => 'ERROR',
                'fitz' => 'ERROR',
                'ocrmypdf' => 'ERROR',
            ],
            'warning_filters' => [
                ['action' => 'ignore', 'category' => 'FutureWarning'],
            ],
        ];
    }

    /**
     * Native planning boundary for run_marker_app.py::run.
     *
     * Upstream shells out to Streamlit with a fixed environment overlay. This
     * port records the command and environment without executing Streamlit.
     *
     * @param array<string, string|int|float|bool|null> $environment
     * @return array{command: list<string>, environment: array<string, string>, executes_subprocess: false}
     */
    public function streamlitRunPlan(string $markerProjectDir, array $environment = []): array
    {
        $projectDir = $this->absoluteProjectDir($markerProjectDir);
        $env = [];
        foreach ($environment as $key => $value) {
            if ($value === null) {
                continue;
            }
            $env[(string) $key] = $this->envValue($value);
        }

        $env['IN_STREAMLIT'] = 'true';
        $env['PDFTEXT_CPU_WORKERS'] = '1';

        return [
            'command' => ['streamlit', 'run', $projectDir . DIRECTORY_SEPARATOR . 'marker_app.py'],
            'environment' => $env,
            'executes_subprocess' => false,
        ];
    }

    /**
     * Native boundary for marker_app.py's import-time environment setup.
     *
     * @return array{PYTORCH_ENABLE_MPS_FALLBACK: string, IN_STREAMLIT: string, PDFTEXT_CPU_WORKERS: string}
     */
    public function markerAppImportEnvironment(): array
    {
        return [
            'PYTORCH_ENABLE_MPS_FALLBACK' => '1',
            'IN_STREAMLIT' => 'true',
            'PDFTEXT_CPU_WORKERS' => '1',
        ];
    }

    /**
     * Native non-executing boundary for marker_app.py's Streamlit controls.
     *
     * Upstream builds a PDF-only uploader, a Surya language multiselect from
     * CODE_TO_LANGUAGE values capped at 4 selections, a max-pages numeric input
     * defaulting to 10/min 1, and a Force OCR checkbox. This records the same
     * app configuration and normalized convert_single_pdf arguments without
     * importing Streamlit, PDFium, PIL, Python models, or file uploads.
     *
     * @param array<string, mixed> $submitted
     * @return array{
     *     page_config: array{layout: string, columns: list<float>},
     *     file_upload: array{label: string, type: list<string>, accepts_pdf_only: bool},
     *     sidebar: array{
     *         languages: array{label: string, options: list<string>, selected: list<string>, default: list<string>, max_selections: int, help: string},
     *         max_pages: array{label: string, value: int, default: int, min_value: int, help: string},
     *         ocr_all_pages: array{label: string, value: bool, default: bool, help: string},
     *         run_button: array{label: string}
     *     },
     *     preview: array{page_number_input: array{label_template: string, min_value: int, default: int, max_value_source: string}, page_image_dpi: int, image_caption: string},
     *     conversion_args: array{langs: list<string>, max_pages: int, ocr_all_pages: bool},
     *     stop_gates: array{requires_uploaded_pdf: bool, requires_run_button: bool},
     *     environment: array{PYTORCH_ENABLE_MPS_FALLBACK: string, IN_STREAMLIT: string, PDFTEXT_CPU_WORKERS: string},
     *     executes_streamlit: false,
     *     executes_pdfium: false,
     *     executes_python_or_models: false
     * }
     */
    public function markerAppConfigPlan(array $submitted = []): array
    {
        $languageOptions = array_values((new OcrLanguage())->suryaCodeToLanguage());
        sort($languageOptions, SORT_STRING);

        $selectedLanguages = $this->markerAppLanguages($submitted['languages'] ?? $submitted['langs'] ?? [], $languageOptions);
        $maxPages = $this->markerAppMaxPages($submitted['max_pages'] ?? $submitted['maxPages'] ?? 10);
        $ocrAllPages = $this->markerAppBool($submitted['ocr_all_pages'] ?? $submitted['ocrAllPages'] ?? false, 'ocr_all_pages');

        return [
            'page_config' => [
                'layout' => 'wide',
                'columns' => [0.5, 0.5],
            ],
            'file_upload' => [
                'label' => 'PDF file:',
                'type' => ['pdf'],
                'accepts_pdf_only' => true,
            ],
            'sidebar' => [
                'languages' => [
                    'label' => 'Languages',
                    'options' => $languageOptions,
                    'selected' => $selectedLanguages,
                    'default' => [],
                    'max_selections' => 4,
                    'help' => 'Select the languages in the pdf (if known) to improve OCR accuracy. Optional.',
                ],
                'max_pages' => [
                    'label' => 'Max pages to parse',
                    'value' => $maxPages,
                    'default' => 10,
                    'min_value' => 1,
                    'help' => 'Optional maximum number of pages to convert',
                ],
                'ocr_all_pages' => [
                    'label' => 'Force OCR on all pages',
                    'value' => $ocrAllPages,
                    'default' => false,
                    'help' => 'Force OCR on all pages, even if they are images',
                ],
                'run_button' => [
                    'label' => 'Run Marker',
                ],
            ],
            'preview' => [
                'page_number_input' => [
                    'label_template' => 'Page number out of {page_count}:',
                    'min_value' => 1,
                    'default' => 1,
                    'max_value_source' => 'page_count(pdf_file)',
                ],
                'page_image_dpi' => 96,
                'image_caption' => 'PDF file (preview)',
            ],
            'conversion_args' => [
                'langs' => $selectedLanguages,
                'max_pages' => $maxPages,
                'ocr_all_pages' => $ocrAllPages,
            ],
            'stop_gates' => [
                'requires_uploaded_pdf' => true,
                'requires_run_button' => true,
            ],
            'environment' => $this->markerAppImportEnvironment(),
            'executes_streamlit' => false,
            'executes_pdfium' => false,
            'executes_python_or_models' => false,
        ];
    }

    /**
     * Native boundary for convert.py's import-time environment setup.
     *
     * @return array{PYTORCH_ENABLE_MPS_FALLBACK: string, IN_STREAMLIT: string, PDFTEXT_CPU_WORKERS: string}
     */
    public function conversionImportEnvironment(): array
    {
        return [
            'PYTORCH_ENABLE_MPS_FALLBACK' => '1',
            'IN_STREAMLIT' => 'true',
            'PDFTEXT_CPU_WORKERS' => '1',
        ];
    }

    /**
     * Native planning boundary for convert.py's torch multiprocessing pool.
     *
     * @param list<array{filepath: string, out_folder: string, metadata?: array<string, mixed>|null, min_length?: int|null}> $tasks
     * @return array{
     *     environment: array{PYTORCH_ENABLE_MPS_FALLBACK: string, IN_STREAMLIT: string, PDFTEXT_CPU_WORKERS: string},
     *     start_method: string,
     *     total_processes: int,
     *     task_args: list<array{filepath: string, out_folder: string, metadata: array<string, mixed>|null, min_length: int|null}>,
     *     model_handoff: array{
     *         main_load_all_models: bool,
     *         share_memory_before_pool: bool,
     *         worker_init_argument: string|null,
     *         worker_loads_models_when_init_arg_null: bool,
     *         mps_disables_shared_model_list: bool,
     *         warning: string|null
     *     },
     *     pool: array{initializer: string, process_function: string, progress_iterator: string, progress_unit: string},
     *     executes_python_or_models: false,
     *     executes_multiprocessing: false,
     *     executes_external_pdf_tools: false
     * }
     */
    public function convertPyMultiprocessingPlan(
        array $tasks,
        int $workers = 5,
        ?string $torchDevice = null,
        ?string $torchDeviceModel = null,
        bool $spawnStartMethodAlreadySet = false
    ): array {
        if ($spawnStartMethodAlreadySet) {
            throw new RuntimeException(
                "Set start method to spawn twice.\nThis may be a temporary issue with the script. Please try running it again."
            );
        }
        if ($workers < 1) {
            throw new InvalidArgumentException('convert.py workers must be at least one.');
        }

        $taskArgs = $this->normalizeConversionTasks($tasks);
        $usesMps = strtolower((string) $torchDevice) === 'mps'
            || strtolower((string) $torchDeviceModel) === 'mps';

        return [
            'environment' => $this->conversionImportEnvironment(),
            'start_method' => 'spawn',
            'total_processes' => min(count($taskArgs), $workers),
            'task_args' => $taskArgs,
            'model_handoff' => [
                'main_load_all_models' => !$usesMps,
                'share_memory_before_pool' => !$usesMps,
                'worker_init_argument' => $usesMps ? null : 'shared_model_list',
                'worker_loads_models_when_init_arg_null' => true,
                'mps_disables_shared_model_list' => $usesMps,
                'warning' => $usesMps
                    ? "Cannot use MPS with torch multiprocessing share_memory. This will make things less memory efficient. If you want to share memory, you have to use CUDA or CPU.\nSet the TORCH_DEVICE environment variable to change the device."
                    : null,
            ],
            'pool' => [
                'initializer' => 'worker_init',
                'process_function' => 'process_single_pdf',
                'progress_iterator' => 'tqdm(pool.imap(process_single_pdf, task_args))',
                'progress_unit' => 'pdf',
            ],
            'executes_python_or_models' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param list<array{filepath: string, out_folder: string, metadata?: array<string, mixed>|null, min_length?: int|null}> $tasks
     * @return list<array{filepath: string, out_folder: string, metadata: array<string, mixed>|null, min_length: int|null}>
     */
    private function normalizeConversionTasks(array $tasks): array
    {
        $normalized = [];
        foreach (array_values($tasks) as $task) {
            if (!is_array($task)) {
                throw new InvalidArgumentException('convert.py tasks must be arrays.');
            }
            if (!isset($task['filepath']) || !is_string($task['filepath']) || trim($task['filepath']) === '') {
                throw new InvalidArgumentException('convert.py task filepath must be a non-empty string.');
            }
            if (!isset($task['out_folder']) || !is_string($task['out_folder']) || trim($task['out_folder']) === '') {
                throw new InvalidArgumentException('convert.py task output folder must be a non-empty string.');
            }

            $metadata = $task['metadata'] ?? null;
            if ($metadata !== null && !is_array($metadata)) {
                throw new InvalidArgumentException('convert.py task metadata must be an array or null.');
            }

            $minLength = $task['min_length'] ?? null;
            if ($minLength !== null && !is_int($minLength)) {
                throw new InvalidArgumentException('convert.py task min_length must be an integer or null.');
            }

            $normalized[] = [
                'filepath' => $task['filepath'],
                'out_folder' => $task['out_folder'],
                'metadata' => $metadata,
                'min_length' => $minLength,
            ];
        }

        return $normalized;
    }

    private function absoluteProjectDir(string $markerProjectDir): string
    {
        $markerProjectDir = trim($markerProjectDir);
        if ($markerProjectDir === '') {
            throw new InvalidArgumentException('Marker project directory must not be empty.');
        }

        $normalized = rtrim($markerProjectDir, "/\\");
        if ($normalized === '') {
            return DIRECTORY_SEPARATOR;
        }

        if ($this->isAbsolutePath($normalized)) {
            return $normalized;
        }

        return getcwd() . DIRECTORY_SEPARATOR . $normalized;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    /**
     * @param mixed $value
     * @param list<string> $languageOptions
     * @return list<string>
     */
    private function markerAppLanguages(mixed $value, array $languageOptions): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('Marker app languages must be a list of upstream language labels.');
        }

        $allowed = array_fill_keys($languageOptions, true);
        $selected = [];
        foreach (array_values($value) as $language) {
            if (!is_string($language) || trim($language) === '') {
                throw new InvalidArgumentException('Marker app languages must be non-empty strings.');
            }

            $language = trim($language);
            if (!isset($allowed[$language])) {
                throw new InvalidArgumentException('Marker app language selection must use upstream Surya language labels.');
            }
            if (in_array($language, $selected, true)) {
                throw new InvalidArgumentException('Marker app language selections must not contain duplicates.');
            }

            $selected[] = $language;
        }

        if (count($selected) > 4) {
            throw new InvalidArgumentException('Marker app language selection is limited to 4 entries.');
        }

        return $selected;
    }

    private function markerAppMaxPages(mixed $value): int
    {
        if (is_int($value)) {
            $maxPages = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            $maxPages = (int) trim($value);
        } else {
            throw new InvalidArgumentException('Marker app max_pages must be an integer.');
        }

        if ($maxPages < 1) {
            throw new InvalidArgumentException('Marker app max_pages must be at least 1.');
        }

        return $maxPages;
    }

    private function markerAppBool(mixed $value, string $name): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        throw new InvalidArgumentException("Marker app {$name} must be a boolean.");
    }

    private function envValue(string|int|float|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
