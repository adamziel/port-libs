<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class BenchmarkReportBuilder
{
    private BenchmarkScorer $scorer;

    public function __construct(?BenchmarkScorer $scorer = null)
    {
        $this->scorer = $scorer ?? new BenchmarkScorer();
    }

    /**
     * Native boundary for benchmarks/overall.py benchmark report aggregation.
     *
     * @param list<array<string, mixed>> $runs
     * @return array<string, array{files: array<string, array{time: float, score: float, pages: int}>, avg_score: float, time_per_page: float, time_per_doc: float}>
     */
    public function build(array $runs): array
    {
        if ($runs === []) {
            throw new InvalidArgumentException('Benchmark report requires at least one run.');
        }

        $methods = [];
        $pagesByDocument = [];

        foreach ($runs as $index => $run) {
            if (!is_array($run)) {
                throw new InvalidArgumentException("Benchmark run {$index} must be an array.");
            }

            $method = $this->requiredString($run, 'method', $index);
            $document = $this->requiredString($run, 'document', $index);
            $hypothesis = $this->requiredString($run, 'hypothesis', $index);
            $reference = $this->requiredString($run, 'reference', $index);
            $time = $this->requiredFloat($run, 'time', $index, allowZero: true);
            $pages = $this->requiredInt($run, 'pages', $index);
            $chunkLength = isset($run['chunkLength']) ? $this->requiredInt($run, 'chunkLength', $index) : 500;

            if ($pages < 1) {
                throw new InvalidArgumentException("Benchmark run {$index} pages must be positive.");
            }
            if ($chunkLength < 1) {
                throw new InvalidArgumentException("Benchmark run {$index} chunkLength must be positive.");
            }
            if (isset($methods[$method]['files'][$document])) {
                throw new InvalidArgumentException("Duplicate benchmark run for {$method}/{$document}.");
            }
            if (isset($pagesByDocument[$document]) && $pagesByDocument[$document] !== $pages) {
                throw new InvalidArgumentException("Conflicting page count for {$document}.");
            }

            $pagesByDocument[$document] = $pages;
            $methods[$method]['files'][$document] = [
                'time' => $time,
                'score' => $this->scorer->scoreText($hypothesis, $reference, $chunkLength),
                'pages' => $pages,
            ];
        }

        $totalPages = array_sum($pagesByDocument);
        if ($totalPages < 1) {
            throw new InvalidArgumentException('Benchmark report requires at least one page.');
        }

        $report = [];
        foreach ($methods as $method => $methodReport) {
            $files = $methodReport['files'];
            $scores = array_map(static fn (array $file): float => (float) $file['score'], $files);
            $times = array_map(static fn (array $file): float => (float) $file['time'], $files);
            $totalTime = array_sum($times);

            $report[$method] = [
                'files' => $files,
                'avg_score' => array_sum($scores) / count($scores),
                'time_per_page' => $totalTime / $totalPages,
                'time_per_doc' => $totalTime / count($scores),
            ];
        }

        return $report;
    }

    /**
     * Native boundary for benchmarks/overall.py's final json.dump() and tabulate source rows.
     *
     * @param array<string, array{files: array<string, array{time: float, score: float, pages: int}>, avg_score: float, time_per_page: float, time_per_doc: float}> $report
     * @return array{
     *     summary_headers: list<string>,
     *     summary_rows: list<list<string|float>>,
     *     score_headers: list<string>,
     *     score_rows: list<list<string|float>>
     * }
     */
    public function outputTables(array $report): array
    {
        if ($report === []) {
            throw new InvalidArgumentException('Benchmark report output requires at least one method.');
        }

        $summaryRows = [];
        $scoreRows = [];
        $scoreHeaders = null;

        foreach ($report as $method => $methodReport) {
            if (!is_string($method) || $method === '') {
                throw new InvalidArgumentException('Benchmark report output methods must be non-empty strings.');
            }
            if (!is_array($methodReport)) {
                throw new InvalidArgumentException("Benchmark report output for {$method} must be an array.");
            }

            $files = $methodReport['files'] ?? null;
            if (!is_array($files) || $files === []) {
                throw new InvalidArgumentException("Benchmark report output for {$method} requires files.");
            }

            $documentNames = array_values(array_keys($files));
            if ($scoreHeaders === null) {
                $scoreHeaders = $documentNames;
            }

            $summaryRows[] = [
                $method,
                $this->numericReportValue($methodReport, 'avg_score', $method),
                $this->numericReportValue($methodReport, 'time_per_page', $method),
                $this->numericReportValue($methodReport, 'time_per_doc', $method),
            ];

            $scoreRow = [$method];
            foreach ($scoreHeaders as $document) {
                $file = $files[$document] ?? null;
                if (!is_array($file)) {
                    throw new InvalidArgumentException("Benchmark report output for {$method} is missing {$document}.");
                }

                $scoreRow[] = $this->numericReportValue($file, 'score', "{$method}/{$document}");
            }
            $scoreRows[] = $scoreRow;
        }

        return [
            'summary_headers' => ['Method', 'Average Score', 'Time per page', 'Time per document'],
            'summary_rows' => $summaryRows,
            'score_headers' => array_merge(['Method'], $scoreHeaders ?? []),
            'score_rows' => $scoreRows,
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function writeJsonReport(string $outputFile, array $report): void
    {
        $outputFile = trim($outputFile);
        if ($outputFile === '') {
            throw new InvalidArgumentException('Benchmark report output file must not be empty.');
        }

        $this->outputTables($report);

        try {
            $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Unable to encode markerPDF benchmark report as JSON.', previous: $exception);
        }

        if (@file_put_contents($outputFile, $json) === false) {
            throw new RuntimeException('Unable to write markerPDF benchmark report: ' . $outputFile);
        }
    }

    /**
     * @param array<string, mixed> $run
     */
    private function requiredString(array $run, string $key, int $index): string
    {
        $value = $run[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException("Benchmark run {$index} is missing a non-empty {$key} string.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $run
     */
    private function requiredFloat(array $run, string $key, int $index, bool $allowZero = false): float
    {
        $value = $run[$key] ?? null;
        if (!is_float($value) && !is_int($value)) {
            throw new InvalidArgumentException("Benchmark run {$index} is missing a numeric {$key}.");
        }
        if ($value < 0 || (!$allowZero && $value == 0)) {
            throw new InvalidArgumentException("Benchmark run {$index} {$key} must be positive.");
        }

        return (float) $value;
    }

    /**
     * @param array<string, mixed> $run
     */
    private function requiredInt(array $run, string $key, int $index): int
    {
        $value = $run[$key] ?? null;
        if (!is_int($value)) {
            throw new InvalidArgumentException("Benchmark run {$index} is missing an integer {$key}.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function numericReportValue(array $values, string $key, string $context): float
    {
        $value = $values[$key] ?? null;
        if (!is_float($value) && !is_int($value)) {
            throw new InvalidArgumentException("Benchmark report output {$context} is missing numeric {$key}.");
        }

        return (float) $value;
    }
}
