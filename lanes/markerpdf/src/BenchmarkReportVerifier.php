<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class BenchmarkReportVerifier
{
    /** @var array<string, float> */
    private const MARKER_THRESHOLDS = [
        'multicolcnn.pdf' => 0.34,
        'switch_trans.pdf' => 0.40,
    ];

    private const TABLE_AVERAGE_THRESHOLD = 0.7;

    /**
     * Native boundary for scripts/verify_benchmark_scores.py CLI file dispatch.
     *
     * @return array<mixed>
     */
    public function verifyScoreFile(string $filePath, string $type = 'marker'): array
    {
        $filePath = trim($filePath);
        if ($filePath === '') {
            throw new InvalidArgumentException('Benchmark score file path must not be empty.');
        }

        $json = @file_get_contents($filePath);
        if (!is_string($json)) {
            throw new InvalidArgumentException('Benchmark score file is not readable: ' . $filePath);
        }

        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Benchmark score file is not valid JSON: ' . $filePath, previous: $exception);
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('Benchmark score file must decode to an array or object.');
        }

        if ($type === 'marker') {
            $this->verifyMarkerScores($data);

            return $data;
        }

        if ($type === 'table') {
            $this->verifyTableScores($data);

            return $data;
        }

        throw new InvalidArgumentException('Benchmark score verifier type must be marker or table.');
    }

    /**
     * Native boundary for scripts/verify_benchmark_scores.py::verify_scores.
     *
     * @param array<string, mixed> $report
     */
    public function verifyMarkerScores(array $report): void
    {
        foreach (self::MARKER_THRESHOLDS as $document => $threshold) {
            $score = $this->markerScore($report, $document);
            if ($score <= $threshold) {
                throw new RuntimeException("Marker score for {$document} is at or below the upstream threshold.");
            }
        }
    }

    /**
     * Native boundary for scripts/verify_benchmark_scores.py::verify_table_scores.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function verifyTableScores(array $rows): void
    {
        if ($rows === []) {
            throw new InvalidArgumentException('Table benchmark report must contain at least one scored row.');
        }

        $sum = 0.0;
        foreach ($rows as $index => $row) {
            if (!isset($row['score']) || !is_numeric($row['score'])) {
                throw new InvalidArgumentException("Table benchmark row {$index} is missing a numeric score.");
            }
            $sum += (float) $row['score'];
        }

        $average = $sum / count($rows);
        if ($average < self::TABLE_AVERAGE_THRESHOLD) {
            throw new RuntimeException('Average table score is below the upstream threshold.');
        }
    }

    /**
     * Native evidence map for the upstream CI benchmark_data_short.zip fixture.
     *
     * @param array<string, mixed> $fixture
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    public function verifyUpstreamCiBenchmarkEvidence(array $fixture, array $report): array
    {
        $archive = $fixture['archive'] ?? null;
        if (!is_array($archive)) {
            throw new InvalidArgumentException('Upstream CI benchmark fixture is missing archive metadata.');
        }

        $pairs = $fixture['benchmarkPairs'] ?? null;
        if (!is_array($pairs) || $pairs === []) {
            throw new InvalidArgumentException('Upstream CI benchmark fixture must contain benchmark pairs.');
        }

        $documents = [];
        foreach ($pairs as $index => $pair) {
            if (!is_array($pair)) {
                throw new InvalidArgumentException("Upstream CI benchmark pair {$index} must be an array.");
            }

            $document = $this->requiredFixtureString($pair, 'document', $index);
            $threshold = $this->requiredFixtureFloat($pair, 'scoreThreshold', $index);
            $score = $this->markerScore($report, $document);
            if ($score <= $threshold) {
                throw new RuntimeException("Marker score for {$document} does not clear the upstream CI threshold.");
            }

            $documents[] = [
                'document' => $document,
                'source_commit' => $this->requiredFixtureString($pair, 'sourceCommit', $index),
                'pdf_path' => $this->requiredFixtureString($pair, 'pdfPath', $index),
                'reference_path' => $this->requiredFixtureString($pair, 'referencePath', $index),
                'marker_example_path' => $this->requiredFixtureString($pair, 'markerPath', $index),
                'reference_kind' => $this->requiredFixtureString($pair, 'referenceKind', $index),
                'score' => $score,
                'threshold' => $threshold,
                'clears_threshold' => true,
            ];
        }

        return [
            'source' => 'sddai/markerPDF .github/workflows/tests.yml benchmark_data_short.zip',
            'archive' => [
                'filename' => $this->requiredFixtureString($archive, 'filename', -1),
                'sha256' => $this->requiredFixtureString($archive, 'sha256', -1),
                'download_id' => $this->requiredFixtureString($archive, 'downloadId', -1),
                'source' => $this->requiredFixtureString($archive, 'source', -1),
            ],
            'documents' => $documents,
            'mapped_native_fixture_count' => count($documents),
            'required_document_count' => count($pairs),
            'passes_upstream_ci_marker_thresholds' => true,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
            'heavy_runtime_exclusions' => $this->heavyRuntimeExclusions(),
        ];
    }

    /**
     * @return array<string, float>
     */
    public function markerThresholds(): array
    {
        return self::MARKER_THRESHOLDS;
    }

    public function tableAverageThreshold(): float
    {
        return self::TABLE_AVERAGE_THRESHOLD;
    }

    /**
     * @return list<string>
     */
    public function heavyRuntimeExclusions(): array
    {
        return [
            'Python benchmark process execution',
            'pdftext and pypdfium2/PDFium runtime extraction',
            'Surya/Torch OCR, layout, table, and recognition models',
            'tabled-pdf live model execution',
            'Texify and Nougat model execution',
            'Streamlit/FastAPI/Uvicorn server runtimes',
            'external OCR, raster rendering, and PDF validation tools',
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    private function markerScore(array $report, string $document): float
    {
        $score = $report['marker']['files'][$document]['score'] ?? null;
        if (!is_numeric($score)) {
            throw new InvalidArgumentException("Marker report is missing a numeric score for {$document}.");
        }

        return (float) $score;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requiredFixtureString(array $data, string $key, int $index): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '') {
            $label = $index < 0 ? 'archive metadata' : "benchmark pair {$index}";
            throw new InvalidArgumentException("Upstream CI {$label} is missing {$key}.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requiredFixtureFloat(array $data, string $key, int $index): float
    {
        $value = $data[$key] ?? null;
        if (!is_float($value) && !is_int($value)) {
            throw new InvalidArgumentException("Upstream CI benchmark pair {$index} is missing numeric {$key}.");
        }

        return (float) $value;
    }
}
