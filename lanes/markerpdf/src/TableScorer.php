<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class TableScorer
{
    private BenchmarkScorer $textScorer;

    public function __construct(?BenchmarkScorer $textScorer = null)
    {
        $this->textScorer = $textScorer ?? new BenchmarkScorer();
    }

    /**
     * @return list<list<string>>
     */
    public function splitToCells(string $table): array
    {
        $table = trim($table);
        $table = preg_replace('/ {2,}/', '', $table) ?? $table;
        $rows = array_filter(
            explode("\n", $table),
            static fn (string $row): bool => trim($row) !== ''
        );

        return array_values(array_map(
            static fn (string $row): array => explode('|', $row),
            $rows
        ));
    }

    /**
     * @param list<list<string>> $hypothesisRows
     * @param list<string> $referenceRow
     * @return list<float>
     */
    public function alignRows(array $hypothesisRows, array $referenceRow): array
    {
        $bestAlignment = [];
        $bestAlignmentScore = 0.0;

        foreach ($hypothesisRows as $hypothesisRow) {
            $alignments = [];
            $cellCount = count($referenceRow);
            for ($index = 0; $index < $cellCount; $index++) {
                if ($index >= count($hypothesisRow)) {
                    $alignments[] = 0.0;
                    continue;
                }

                $alignments[] = $this->textScorer->ratio($hypothesisRow[$index], $referenceRow[$index], 30.0) / 100.0;
            }

            if ($alignments === []) {
                continue;
            }

            $alignmentScore = array_sum($alignments) / count($alignments);
            if ($alignmentScore >= $bestAlignmentScore) {
                $bestAlignment = $alignments;
                $bestAlignmentScore = $alignmentScore;
            }
        }

        return $bestAlignment;
    }

    public function scoreTable(string $hypothesis, string $reference): float
    {
        $hypothesisRows = $this->splitToCells($hypothesis);
        $referenceRows = $this->splitToCells($reference);
        $alignments = [];

        foreach ($referenceRows as $referenceRow) {
            array_push($alignments, ...$this->alignRows($hypothesisRows, $referenceRow));
        }

        if ($alignments === []) {
            throw new InvalidArgumentException('Table scoring requires at least one aligned reference cell.');
        }

        return array_sum($alignments) / count($alignments);
    }
}
