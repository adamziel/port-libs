<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class BenchmarkRunner
{
    private BenchmarkReportBuilder $reportBuilder;

    public function __construct(?BenchmarkReportBuilder $reportBuilder = null)
    {
        $this->reportBuilder = $reportBuilder ?? new BenchmarkReportBuilder();
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
            'executes_external_tools' => false,
        ];

        foreach ($benchmarkFiles as $documentIndex => $pdfFilename) {
            $pdfPath = $inputFolder . DIRECTORY_SEPARATOR . $pdfFilename;
            $mdFilename = $this->markdownFilename($pdfFilename);
            $referencePath = $referenceFolder . DIRECTORY_SEPARATOR . $mdFilename;
            $reference = @file_get_contents($referencePath);
            if (!is_string($reference)) {
                throw new InvalidArgumentException('Benchmark reference markdown is not readable: ' . $referencePath);
            }

            $pages = $pageCounter === null ? 1 : (int) $pageCounter($pdfPath);
            if ($pages < 1) {
                throw new InvalidArgumentException('Benchmark page counter must return a positive integer for ' . $pdfFilename);
            }

            foreach ($methodOrder as $method) {
                $converter = $methodConverters[$method];
                $context = $this->conversionContext($runtime, $method, $pdfFilename, $documentIndex);
                $start = microtime(true);
                $conversion = $this->normalizeConversion($converter($pdfPath, $pdfFilename, $reference, $context));
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
                }

                if ($markdownOutputFolder !== null) {
                    $outPath = $markdownOutputFolder . DIRECTORY_SEPARATOR . $method . '_' . $mdFilename;
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
     *     profile_memory: bool
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

        return [
            'methods' => $methods,
            'marker_batch_multiplier' => $this->positiveIntOption($runtimeOptions['marker_batch_multiplier'] ?? $runtimeOptions['markerBatchMultiplier'] ?? 1, 'marker_batch_multiplier'),
            'nougat_batch_size' => $this->positiveIntOption($runtimeOptions['nougat_batch_size'] ?? $runtimeOptions['nougatBatchSize'] ?? 1, 'nougat_batch_size'),
            'profile_memory' => $this->boolOption($runtimeOptions['profile_memory'] ?? $runtimeOptions['profileMemory'] ?? false),
        ];
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
     * @param array{marker_batch_multiplier: int, nougat_batch_size: int, profile_memory: bool} $runtime
     * @return array<string, mixed>
     */
    private function conversionContext(array $runtime, string $method, string $document, int $documentIndex): array
    {
        $context = [
            'method' => $method,
            'document' => $document,
            'benchmark_index' => $documentIndex,
            'profile_memory' => $runtime['profile_memory'],
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
}
