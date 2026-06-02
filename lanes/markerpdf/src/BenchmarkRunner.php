<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class BenchmarkRunner
{
    private BenchmarkReportBuilder $reportBuilder;

    /** @var array<string, mixed>|null */
    private ?array $activeRuntimeFailureContext = null;

    public function __construct(?BenchmarkReportBuilder $reportBuilder = null)
    {
        $this->reportBuilder = $reportBuilder ?? new BenchmarkReportBuilder();
    }

    /**
     * WordPress-safe wrapper for benchmarks/overall.py runtime failures.
     *
     * Upstream fails fast when PDFium page counting or a conversion method
     * raises. This wrapper preserves that default while exposing the active
     * method/document phase as review-only telemetry for import queues.
     *
     * @param array<string, callable(string, string, string, array<string, mixed>): mixed> $methodConverters
     * @param callable(string): int|null $pageCounter
     * @param array<string, int> $chunkLengths
     * @return array{
     *     success: bool,
     *     result: array<string, mixed>|null,
     *     error: string|null,
     *     telemetry: array<string, mixed>|null,
     *     executes_external_tools: false,
     *     executes_python_or_models: false
     * }
     */
    public function runWithErrorTelemetry(
        string $inputFolder,
        string $referenceFolder,
        array $methodConverters,
        ?callable $pageCounter = null,
        ?string $markdownOutputFolder = null,
        array $chunkLengths = [],
        ?string $reportOutputFile = null,
        array $runtimeOptions = []
    ): array {
        $this->activeRuntimeFailureContext = [
            'phase' => 'preflight',
            'input_folder' => $inputFolder,
            'reference_folder' => $referenceFolder,
            'markdown_output_folder' => $markdownOutputFolder,
            'report_output' => $reportOutputFile,
        ];

        try {
            $result = $this->run(
                $inputFolder,
                $referenceFolder,
                $methodConverters,
                $pageCounter,
                $markdownOutputFolder,
                $chunkLengths,
                $reportOutputFile,
                $runtimeOptions
            );

            return [
                'success' => true,
                'result' => $result,
                'error' => null,
                'telemetry' => null,
                'executes_external_tools' => false,
                'executes_python_or_models' => false,
            ];
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'result' => null,
                'error' => $throwable->getMessage(),
                'telemetry' => $this->runtimeFailureTelemetry($throwable, $this->activeRuntimeFailureContext ?? []),
                'executes_external_tools' => false,
                'executes_python_or_models' => false,
            ];
        } finally {
            $this->activeRuntimeFailureContext = null;
        }
    }

    /**
     * Native supplied-converter boundary for benchmarks/overall.py::main.
     *
     * @param array<string, callable(string, string, string, array<string, mixed>): mixed> $methodConverters
     * @param callable(string): int|null $pageCounter
     * @param array<string, int> $chunkLengths
     * @return array{
     *     benchmark_files: list<string>,
     *     runs: list<array<string, mixed>>,
     *     report: array<string, mixed>,
     *     report_output: string|null,
     *     output_tables: array<string, mixed>,
     *     written_markdown: list<string>,
     *     runtime: array<string, mixed>
     * }
     */
    public function run(
        string $inputFolder,
        string $referenceFolder,
        array $methodConverters,
        ?callable $pageCounter = null,
        ?string $markdownOutputFolder = null,
        array $chunkLengths = [],
        ?string $reportOutputFile = null,
        array $runtimeOptions = []
    ): array {
        if (!isset($methodConverters['marker'])) {
            throw new InvalidArgumentException('Benchmark runner requires a marker method converter.');
        }
        if (!is_dir($inputFolder)) {
            throw new InvalidArgumentException('Benchmark input folder does not exist: ' . $inputFolder);
        }
        if (!is_dir($referenceFolder)) {
            throw new InvalidArgumentException('Benchmark reference folder does not exist: ' . $referenceFolder);
        }
        if ($markdownOutputFolder !== null && !is_dir($markdownOutputFolder)) {
            throw new InvalidArgumentException('Benchmark markdown output folder does not exist: ' . $markdownOutputFolder);
        }

        foreach ($methodConverters as $method => $converter) {
            if (!is_string($method) || $method === '' || !is_callable($converter)) {
                throw new InvalidArgumentException('Benchmark method converters must be keyed by non-empty method names.');
            }
        }

        $runtime = $this->normalizeRuntimeOptions($runtimeOptions);
        $methodOrder = $runtime['methods'] ?? array_values(array_keys($methodConverters));
        foreach ($methodOrder as $method) {
            if (!isset($methodConverters[$method])) {
                throw new InvalidArgumentException("Benchmark runtime method {$method} requires a supplied converter.");
            }
        }

        $benchmarkFiles = $this->benchmarkFiles($inputFolder);
        if ($benchmarkFiles === []) {
            throw new InvalidArgumentException('Benchmark input folder must contain at least one PDF file.');
        }

        $runs = [];
        $writtenMarkdown = [];
        $runtimeReport = [
            'methods' => $methodOrder,
            'marker_batch_multiplier' => $runtime['marker_batch_multiplier'],
            'nougat_batch_size' => $runtime['nougat_batch_size'],
            'profile_memory' => $runtime['profile_memory'],
            'model_load_snapshot' => $runtime['profile_memory'] ? 'model_load.pickle' : null,
            'conversion_snapshots' => [],
            'memory_snapshot_failures' => [],
            'memory_snapshot_failure_count' => 0,
            'continues_after_memory_snapshot_failure' => true,
            'executes_external_tools' => false,
            'callback_sandbox' => [
                'enabled' => $runtime['callback_sandbox'],
                'watched_inputs' => $markdownOutputFolder === null
                    ? ['pdf', 'reference']
                    : ['pdf', 'reference', 'markdown_output_folder'],
                'runner_writes_markdown_after_callback' => $markdownOutputFolder !== null,
            ],
        ];
        if ($runtime['profile_memory']) {
            $this->appendMemorySnapshotFailure(
                $runtimeReport,
                'model_load.pickle',
                $runtime['memory_snapshot_errors'],
                [
                    'phase' => 'model_load',
                    'method' => null,
                    'document' => null,
                    'benchmark_index' => null,
                ]
            );
        }

        foreach ($benchmarkFiles as $documentIndex => $pdfFilename) {
            $pdfPath = $inputFolder . DIRECTORY_SEPARATOR . $pdfFilename;
            $mdFilename = $this->markdownFilename($pdfFilename);
            $referencePath = $referenceFolder . DIRECTORY_SEPARATOR . $mdFilename;
            $this->activeRuntimeFailureContext = $this->runtimeFailureContext(
                'reference_read',
                null,
                $pdfFilename,
                $pdfPath,
                $referencePath,
                $documentIndex,
                ['callback_sandbox' => $runtime['callback_sandbox']],
                $markdownOutputFolder,
                $reportOutputFile
            );
            $reference = @file_get_contents($referencePath);
            if (!is_string($reference)) {
                throw new InvalidArgumentException('Benchmark reference markdown is not readable: ' . $referencePath);
            }

            $pageCounterSnapshot = $runtime['callback_sandbox'] && $pageCounter !== null
                ? $this->sandboxSnapshot($pdfPath, $referencePath, $markdownOutputFolder)
                : null;
            $this->activeRuntimeFailureContext = $this->runtimeFailureContext(
                'page_counter',
                null,
                $pdfFilename,
                $pdfPath,
                $referencePath,
                $documentIndex,
                ['callback_sandbox' => $runtime['callback_sandbox']],
                $markdownOutputFolder,
                $reportOutputFile
            );
            try {
                $pages = $pageCounter === null ? 1 : (int) $pageCounter($pdfPath);
            } catch (Throwable $throwable) {
                if ($pageCounterSnapshot !== null) {
                    $this->assertSandboxUnchanged($pageCounterSnapshot, "page counter for {$pdfFilename}");
                }
                throw $throwable;
            }
            if ($pageCounterSnapshot !== null) {
                $this->assertSandboxUnchanged($pageCounterSnapshot, "page counter for {$pdfFilename}");
            }
            if ($pages < 1) {
                throw new InvalidArgumentException('Benchmark page counter must return a positive integer for ' . $pdfFilename);
            }

            foreach ($methodOrder as $method) {
                $converter = $methodConverters[$method];
                $context = $this->conversionContext($runtime, $method, $pdfFilename, $documentIndex);
                $converterSnapshot = $runtime['callback_sandbox']
                    ? $this->sandboxSnapshot($pdfPath, $referencePath, $markdownOutputFolder)
                    : null;
                $start = microtime(true);
                $this->activeRuntimeFailureContext = $this->runtimeFailureContext(
                    'converter',
                    $method,
                    $pdfFilename,
                    $pdfPath,
                    $referencePath,
                    $documentIndex,
                    $context,
                    $markdownOutputFolder,
                    $reportOutputFile
                );
                try {
                    $conversion = $this->normalizeConversion($converter($pdfPath, $pdfFilename, $reference, $context));
                } catch (Throwable $throwable) {
                    if ($converterSnapshot !== null) {
                        $this->assertSandboxUnchanged($converterSnapshot, "{$method}/{$pdfFilename}");
                    }
                    throw $throwable;
                }
                if ($converterSnapshot !== null) {
                    $this->assertSandboxUnchanged($converterSnapshot, "{$method}/{$pdfFilename}");
                }
                $elapsed = microtime(true) - $start;

                $runs[] = [
                    'method' => $method,
                    'document' => $pdfFilename,
                    'hypothesis' => $conversion['text'],
                    'reference' => $reference,
                    'time' => $elapsed,
                    'pages' => $pages,
                    'chunkLength' => $chunkLengths[$pdfFilename] ?? 500,
                ];

                if (isset($context['memory_snapshot']) && is_string($context['memory_snapshot'])) {
                    $runtimeReport['conversion_snapshots'][] = [
                        'method' => $method,
                        'document' => $pdfFilename,
                        'snapshot' => $context['memory_snapshot'],
                    ];
                    $this->appendMemorySnapshotFailure(
                        $runtimeReport,
                        $context['memory_snapshot'],
                        $runtime['memory_snapshot_errors'],
                        [
                            'phase' => 'converter',
                            'method' => $method,
                            'document' => $pdfFilename,
                            'benchmark_index' => $documentIndex,
                        ]
                    );
                }

                if ($markdownOutputFolder !== null) {
                    $outPath = $markdownOutputFolder . DIRECTORY_SEPARATOR . $method . '_' . $mdFilename;
                    $this->activeRuntimeFailureContext = $this->runtimeFailureContext(
                        'markdown_write',
                        $method,
                        $pdfFilename,
                        $pdfPath,
                        $referencePath,
                        $documentIndex,
                        $context + ['markdown_output' => $outPath],
                        $markdownOutputFolder,
                        $reportOutputFile
                    );
                    if (file_put_contents($outPath, $conversion['text']) === false) {
                        throw new InvalidArgumentException('Benchmark markdown output is not writable: ' . $outPath);
                    }
                    $writtenMarkdown[] = $outPath;
                }
            }
        }

        $report = $this->reportBuilder->build($runs);
        $outputTables = $this->reportBuilder->outputTables($report);
        if ($reportOutputFile !== null) {
            $this->activeRuntimeFailureContext = [
                'phase' => 'report_write',
                'report_output' => $reportOutputFile,
                'markdown_output_folder' => $markdownOutputFolder,
            ];
            $this->reportBuilder->writeJsonReport($reportOutputFile, $report);
        }

        return [
            'benchmark_files' => $benchmarkFiles,
            'runs' => $runs,
            'report' => $report,
            'report_output' => $reportOutputFile,
            'output_tables' => $outputTables,
            'written_markdown' => $writtenMarkdown,
            'runtime' => $runtimeReport,
        ];
    }

    /**
     * Native non-executing boundary for benchmarks/overall.py::stop_memory_profiling.
     *
     * Upstream logs snapshot dump failures and still disables CUDA memory
     * history. The PHP port records that review metadata without touching CUDA.
     *
     * @return array{snapshot: string, error: string, log_line: string, continues_after_failure: true, recording_disabled_after_error: true, executes_cuda_memory_history: false, review_only: true}
     */
    public function memorySnapshotFailureReport(string $snapshotFile, Throwable|string $error): array
    {
        $snapshotFile = trim($snapshotFile);
        if ($snapshotFile === '') {
            throw new InvalidArgumentException('Benchmark memory snapshot file must be a non-empty string.');
        }

        $message = $error instanceof Throwable ? $error->getMessage() : (string) $error;

        return [
            'snapshot' => $snapshotFile,
            'error' => $message,
            'log_line' => 'Failed to capture memory snapshot ' . $message,
            'continues_after_failure' => true,
            'recording_disabled_after_error' => true,
            'executes_cuda_memory_history' => false,
            'review_only' => true,
        ];
    }

    /**
     * @param array<string, mixed> $runtimeReport
     * @param array<string, Throwable|string> $memorySnapshotErrors
     * @param array{phase: string, method: string|null, document: string|null, benchmark_index: int|null} $context
     */
    private function appendMemorySnapshotFailure(
        array &$runtimeReport,
        string $snapshotFile,
        array $memorySnapshotErrors,
        array $context
    ): void {
        if (!array_key_exists($snapshotFile, $memorySnapshotErrors)) {
            return;
        }

        $runtimeReport['memory_snapshot_failures'][] = $context + $this->memorySnapshotFailureReport(
            $snapshotFile,
            $memorySnapshotErrors[$snapshotFile]
        );
        $runtimeReport['memory_snapshot_failure_count'] = count($runtimeReport['memory_snapshot_failures']);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function runtimeFailureTelemetry(Throwable $throwable, array $context): array
    {
        $phase = isset($context['phase']) && is_string($context['phase'])
            ? $context['phase']
            : 'preflight';
        $method = isset($context['method']) && is_string($context['method'])
            ? $context['method']
            : null;
        $document = isset($context['document']) && is_string($context['document'])
            ? $context['document']
            : null;

        $trace = $throwable->getTraceAsString();
        $traceback = get_class($throwable) . ': ' . $throwable->getMessage();
        if ($trace !== '') {
            $traceback .= "\n" . $trace;
        }

        return [
            'phase' => $phase,
            'method' => $method,
            'document' => $document,
            'benchmark_index' => $context['benchmark_index'] ?? null,
            'input_folder' => $context['input_folder'] ?? null,
            'reference_folder' => $context['reference_folder'] ?? null,
            'pdf_path' => $context['pdf_path'] ?? null,
            'reference_path' => $context['reference_path'] ?? null,
            'markdown_output_folder' => $context['markdown_output_folder'] ?? null,
            'markdown_output' => $context['markdown_output'] ?? null,
            'report_output' => $context['report_output'] ?? null,
            'memory_snapshot' => $context['memory_snapshot'] ?? null,
            'callback_sandbox' => $context['callback_sandbox'] ?? null,
            'error' => $throwable->getMessage(),
            'message_line' => $this->runtimeFailureMessageLine($phase, $method, $document, $throwable),
            'traceback' => $traceback,
            'traceback_available' => $traceback !== '',
            'default_runner_fails_fast' => true,
            'continues_after_failure' => false,
            'writes_markdown_after_failure' => false,
            'executes_external_tools' => false,
            'executes_python_or_models' => false,
            'review_only' => true,
        ];
    }

    /**
     * @param array<string, mixed>|null $context
     * @return array<string, mixed>
     */
    private function runtimeFailureContext(
        string $phase,
        ?string $method,
        string $document,
        string $pdfPath,
        string $referencePath,
        int $documentIndex,
        ?array $context,
        ?string $markdownOutputFolder,
        ?string $reportOutputFile
    ): array {
        return [
            'phase' => $phase,
            'method' => $method,
            'document' => $document,
            'benchmark_index' => $documentIndex,
            'pdf_path' => $pdfPath,
            'reference_path' => $referencePath,
            'markdown_output_folder' => $markdownOutputFolder,
            'markdown_output' => $context['markdown_output'] ?? null,
            'report_output' => $reportOutputFile,
            'memory_snapshot' => $context['memory_snapshot'] ?? null,
            'callback_sandbox' => $context['callback_sandbox'] ?? null,
        ];
    }

    private function runtimeFailureMessageLine(
        string $phase,
        ?string $method,
        ?string $document,
        Throwable $throwable
    ): string {
        if ($phase === 'converter' && $method !== null && $document !== null) {
            return "Benchmark method {$method} failed for {$document}: " . $throwable->getMessage();
        }
        if ($phase === 'page_counter' && $document !== null) {
            return "Benchmark page counter failed for {$document}: " . $throwable->getMessage();
        }
        if ($phase === 'reference_read' && $document !== null) {
            return "Benchmark reference read failed for {$document}: " . $throwable->getMessage();
        }
        if ($phase === 'markdown_write' && $method !== null && $document !== null) {
            return "Benchmark markdown write failed for {$method}/{$document}: " . $throwable->getMessage();
        }
        if ($phase === 'report_write') {
            return 'Benchmark report write failed: ' . $throwable->getMessage();
        }

        return 'Benchmark runner failed during ' . $phase . ': ' . $throwable->getMessage();
    }

    /**
     * @return list<string>
     */
    private function benchmarkFiles(string $inputFolder): array
    {
        $files = [];
        foreach (scandir($inputFolder) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || !str_ends_with($entry, '.pdf')) {
                continue;
            }

            $path = $inputFolder . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                $files[] = $entry;
            }
        }
        sort($files, SORT_STRING);

        return $files;
    }

    private function markdownFilename(string $pdfFilename): string
    {
        $withoutExtension = preg_replace('/\.[^.]*$/', '', $pdfFilename) ?? $pdfFilename;

        return $withoutExtension . '.md';
    }

    /**
     * @return array{text: string}
     */
    private function normalizeConversion(mixed $conversion): array
    {
        if (is_string($conversion)) {
            return ['text' => $conversion];
        }
        if (!is_array($conversion)) {
            throw new InvalidArgumentException('Benchmark converter must return text or a conversion array.');
        }

        $text = $conversion['text']
            ?? $conversion['full_text']
            ?? $conversion['markdown']
            ?? $conversion[0]
            ?? null;
        if (!is_string($text)) {
            throw new InvalidArgumentException('Benchmark converter result must contain text.');
        }

        return ['text' => $text];
    }

    /**
     * @param array<string, mixed> $runtimeOptions
     * @return array{
     *     methods: list<string>|null,
     *     marker_batch_multiplier: int,
     *     nougat_batch_size: int,
     *     profile_memory: bool,
     *     callback_sandbox: bool,
     *     memory_snapshot_errors: array<string, Throwable|string>
     * }
     */
    private function normalizeRuntimeOptions(array $runtimeOptions): array
    {
        $methods = null;
        if (array_key_exists('methods', $runtimeOptions)) {
            $methods = $this->methodList($runtimeOptions['methods']);
        } elseif ($this->boolOption($runtimeOptions['nougat'] ?? $runtimeOptions['include_nougat'] ?? false)) {
            $methods = ['marker', 'nougat'];
        }
        $profileMemory = $this->boolOption($runtimeOptions['profile_memory'] ?? $runtimeOptions['profileMemory'] ?? false);
        $memorySnapshotErrors = $this->memorySnapshotErrors(
            $runtimeOptions['memory_snapshot_errors'] ?? $runtimeOptions['memorySnapshotErrors'] ?? []
        );
        if ($memorySnapshotErrors !== [] && !$profileMemory) {
            throw new InvalidArgumentException('Benchmark memory snapshot errors require profile_memory.');
        }

        return [
            'methods' => $methods,
            'marker_batch_multiplier' => $this->positiveIntOption($runtimeOptions['marker_batch_multiplier'] ?? $runtimeOptions['markerBatchMultiplier'] ?? 1, 'marker_batch_multiplier'),
            'nougat_batch_size' => $this->positiveIntOption($runtimeOptions['nougat_batch_size'] ?? $runtimeOptions['nougatBatchSize'] ?? 1, 'nougat_batch_size'),
            'profile_memory' => $profileMemory,
            'callback_sandbox' => $this->boolOption($runtimeOptions['sandbox_callbacks'] ?? $runtimeOptions['sandboxCallbacks'] ?? true),
            'memory_snapshot_errors' => $memorySnapshotErrors,
        ];
    }

    /**
     * @return array<string, Throwable|string>
     */
    private function memorySnapshotErrors(mixed $value): array
    {
        if ($value === null || $value === []) {
            return [];
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('Benchmark memory snapshot errors must be keyed by snapshot filename.');
        }

        $errors = [];
        foreach ($value as $snapshot => $error) {
            if (!is_string($snapshot) || trim($snapshot) === '') {
                throw new InvalidArgumentException('Benchmark memory snapshot error keys must be non-empty snapshot filenames.');
            }
            if (!$error instanceof Throwable && !is_string($error)) {
                throw new InvalidArgumentException('Benchmark memory snapshot errors must be strings or Throwable instances.');
            }

            $errors[trim($snapshot)] = $error;
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function methodList(mixed $methods): array
    {
        if (!is_array($methods) || $methods === []) {
            throw new InvalidArgumentException('Benchmark runtime methods must be a non-empty list.');
        }

        $normalized = [];
        foreach ($methods as $method) {
            if (!is_string($method) || $method === '') {
                throw new InvalidArgumentException('Benchmark runtime methods must contain non-empty method names.');
            }
            if (in_array($method, $normalized, true)) {
                throw new InvalidArgumentException('Benchmark runtime methods must not contain duplicates.');
            }
            $normalized[] = $method;
        }

        return $normalized;
    }

    private function positiveIntOption(mixed $value, string $name): int
    {
        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $number = (int) $value;
        } else {
            throw new InvalidArgumentException("Benchmark runtime {$name} must be a positive integer.");
        }

        if ($number < 1) {
            throw new InvalidArgumentException("Benchmark runtime {$name} must be a positive integer.");
        }

        return $number;
    }

    private function boolOption(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return (bool) $value;
    }

    /**
     * @param array{marker_batch_multiplier: int, nougat_batch_size: int, profile_memory: bool, callback_sandbox: bool} $runtime
     * @return array<string, mixed>
     */
    private function conversionContext(array $runtime, string $method, string $document, int $documentIndex): array
    {
        $context = [
            'method' => $method,
            'document' => $document,
            'benchmark_index' => $documentIndex,
            'profile_memory' => $runtime['profile_memory'],
            'callback_sandbox' => $runtime['callback_sandbox'],
            'executes_external_tools' => false,
        ];

        if ($method === 'marker') {
            $context['batch_multiplier'] = $runtime['marker_batch_multiplier'];
            if ($runtime['profile_memory']) {
                $context['memory_snapshot'] = "marker_memory_{$documentIndex}.pickle";
            }
        }
        if ($method === 'nougat') {
            $context['batch_size'] = $runtime['nougat_batch_size'];
        }

        return $context;
    }

    /**
     * @return array{
     *     pdf_path: string,
     *     reference_path: string,
     *     markdown_output_folder: string|null,
     *     pdf: array<string, mixed>,
     *     reference: array<string, mixed>,
     *     markdown_output: array<string, array<string, mixed>>|null
     * }
     */
    private function sandboxSnapshot(string $pdfPath, string $referencePath, ?string $markdownOutputFolder): array
    {
        return [
            'pdf_path' => $pdfPath,
            'reference_path' => $referencePath,
            'markdown_output_folder' => $markdownOutputFolder,
            'pdf' => $this->fileFingerprint($pdfPath),
            'reference' => $this->fileFingerprint($referencePath),
            'markdown_output' => $markdownOutputFolder === null
                ? null
                : $this->directoryFingerprint($markdownOutputFolder),
        ];
    }

    /**
     * @param array{
     *     pdf_path: string,
     *     reference_path: string,
     *     markdown_output_folder: string|null,
     *     pdf: array<string, mixed>,
     *     reference: array<string, mixed>,
     *     markdown_output: array<string, array<string, mixed>>|null
     * } $before
     */
    private function assertSandboxUnchanged(array $before, string $label): void
    {
        $after = $this->sandboxSnapshot(
            $before['pdf_path'],
            $before['reference_path'],
            $before['markdown_output_folder']
        );

        $violations = [];
        if ($before['pdf'] !== $after['pdf']) {
            $violations[] = 'pdf';
        }
        if ($before['reference'] !== $after['reference']) {
            $violations[] = 'reference';
        }
        if ($before['markdown_output'] !== $after['markdown_output']) {
            $violations[] = 'markdown_output_folder';
        }

        if ($violations !== []) {
            throw new RuntimeException(
                'Benchmark callback sandbox violation for ' . $label . ': modified ' . implode(', ', $violations) . '.'
            );
        }
    }

    /**
     * @return array{exists: bool, size?: int, sha256?: string}
     */
    private function fileFingerprint(string $path): array
    {
        clearstatcache(true, $path);
        if (!is_file($path)) {
            return ['exists' => false];
        }

        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new RuntimeException('Unable to fingerprint benchmark sandbox file: ' . $path);
        }

        return [
            'exists' => true,
            'size' => filesize($path) ?: 0,
            'sha256' => $hash,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function directoryFingerprint(string $path): array
    {
        clearstatcache(true, $path);
        if (!is_dir($path)) {
            return [];
        }

        $entries = [];
        $this->appendDirectoryFingerprints($entries, $path, $path);
        ksort($entries, SORT_STRING);

        return $entries;
    }

    /**
     * @param array<string, array<string, mixed>> $entries
     */
    private function appendDirectoryFingerprints(array &$entries, string $root, string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1));
            clearstatcache(true, $path);
            if (is_dir($path)) {
                $entries[$relative] = ['type' => 'dir'];
                $this->appendDirectoryFingerprints($entries, $root, $path);
                continue;
            }
            if (is_file($path)) {
                $hash = hash_file('sha256', $path);
                if (!is_string($hash)) {
                    throw new RuntimeException('Unable to fingerprint benchmark sandbox output file: ' . $path);
                }
                $entries[$relative] = [
                    'type' => 'file',
                    'size' => filesize($path) ?: 0,
                    'sha256' => $hash,
                ];
            }
        }
    }
}
