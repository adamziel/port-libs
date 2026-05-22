<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
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
}
