<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

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
}
