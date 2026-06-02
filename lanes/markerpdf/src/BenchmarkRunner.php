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
     * @param array<string, callable(string, string, string): mixed> $methodConverters
     * @param callable(string): int|null $pageCounter
     * @param array<string, int> $chunkLengths
     * @return array{
     *     benchmark_files: list<string>,
     *     runs: list<array<string, mixed>>,
     *     report: array<string, mixed>,
     *     report_output: string|null,
     *     output_tables: array<string, mixed>,
     *     written_markdown: list<string>
     * }
     */
    public function run(
        string $inputFolder,
        string $referenceFolder,
        array $methodConverters,
        ?callable $pageCounter = null,
        ?string $markdownOutputFolder = null,
        array $chunkLengths = [],
        ?string $reportOutputFile = null
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

        $benchmarkFiles = $this->benchmarkFiles($inputFolder);
        if ($benchmarkFiles === []) {
            throw new InvalidArgumentException('Benchmark input folder must contain at least one PDF file.');
        }

        $runs = [];
        $writtenMarkdown = [];

        foreach ($benchmarkFiles as $pdfFilename) {
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

            foreach ($methodConverters as $method => $converter) {
                $start = microtime(true);
                $conversion = $this->normalizeConversion($converter($pdfPath, $pdfFilename, $reference));
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
}
