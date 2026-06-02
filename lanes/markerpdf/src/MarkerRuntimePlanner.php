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

    private function envValue(string|int|float|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
