<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSkipScanStat4PartialOrderPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $stat4Samples
     * @param list<array<string,mixed>> $queryTerms
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function plan(
        array $rows,
        string $indexName,
        string $skippedColumn,
        string $rangeColumn,
        mixed $lowerInclusive,
        mixed $upperBound,
        SQLiteIndexPredicate $partialPredicate,
        array $queryTerms,
        array $stat4Samples,
        array $orderBy = [],
        bool $upperInclusive = true,
        string $collation = 'BINARY',
    ): array {
        if ($indexName === '' || $skippedColumn === '' || $rangeColumn === '') {
            throw new \InvalidArgumentException('SQLite STAT4 skip-scan planner needs index and column names');
        }
        if ($skippedColumn === $rangeColumn) {
            throw new \InvalidArgumentException('SQLite STAT4 skip-scan range column must differ from skipped column');
        }

        $scan = SQLiteIndexSkipScanPlan::betweenPartialRows(
            $rows,
            $indexName,
            $skippedColumn,
            $rangeColumn,
            $lowerInclusive,
            $upperBound,
            $partialPredicate,
            $queryTerms,
            $upperInclusive,
            null,
            0,
            $collation,
        );

        if ($scan['status'] !== 'usable') {
            return $scan + [
                'stat4SamplesUsed' => 0,
                'stat4LoopEstimates' => [],
                'estimatedRows' => 0,
                'estimatedCost' => 0,
                'orderByMode' => 'none',
                'orderBySatisfied' => false,
                'partialOrderBy' => false,
                'blockSortRequired' => false,
                'sortBreakColumns' => [],
                'detail' => 'UNUSABLE PARTIAL INDEX ' . $indexName,
            ];
        }

        $sampleMap = self::samplesByPrefix($stat4Samples);
        $loopEstimates = [];
        $estimatedRows = 0;
        foreach ($scan['loops'] as $loop) {
            $prefix = $loop['prefix'];
            $samples = $sampleMap[self::key($prefix)] ?? [];
            $estimate = self::estimateLoopRows($samples, $lowerInclusive, $upperBound, $upperInclusive, $collation, (int) $loop['matched']);
            $loopEstimates[] = [
                'prefix' => $prefix,
                'matched' => $loop['matched'],
                'estimatedRows' => $estimate,
                'sampleCount' => count($samples),
                'rowids' => $loop['rowids'],
            ];
            $estimatedRows += $estimate;
        }

        $orderEvidence = self::orderEvidence($skippedColumn, $rangeColumn, $orderBy);
        $estimatedSeeks = (int) $scan['estimatedSeeks'];
        $blockSortPenalty = $orderEvidence['blockSortRequired'] ? max(1, $estimatedRows) : 0;
        $cost = $estimatedSeeks * 8 + $estimatedRows + $blockSortPenalty;

        return $scan + [
            'stat4SamplesUsed' => array_sum(array_column($loopEstimates, 'sampleCount')),
            'stat4LoopEstimates' => $loopEstimates,
            'estimatedRows' => $estimatedRows,
            'estimatedCost' => $cost,
            'orderByMode' => $orderEvidence['mode'],
            'orderBySatisfied' => $orderEvidence['satisfied'],
            'partialOrderBy' => $orderEvidence['partial'],
            'blockSortRequired' => $orderEvidence['blockSortRequired'],
            'sortBreakColumns' => $orderEvidence['breakColumns'],
            'detail' => self::detail($indexName, $skippedColumn, $rangeColumn, $orderEvidence),
        ];
    }

    /**
     * @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples
     * @return array<string,list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}>>
     */
    private static function samplesByPrefix(array $samples): array
    {
        $byPrefix = [];
        foreach ($samples as $sample) {
            foreach (['nEq', 'nLt', 'nDLt'] as $field) {
                if (!isset($sample[$field]) || !is_int($sample[$field]) || $sample[$field] < 0) {
                    throw new \InvalidArgumentException('SQLite STAT4 samples need non-negative integer counters');
                }
            }
            $byPrefix[self::key($sample['prefix'])][] = $sample;
        }

        return $byPrefix;
    }

    /**
     * @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples
     */
    private static function estimateLoopRows(array $samples, mixed $lower, mixed $upper, bool $upperInclusive, string $collation, int $fallback): int
    {
        if ($samples === []) {
            return max(1, $fallback);
        }

        $best = null;
        foreach ($samples as $sample) {
            if (!self::within($sample['suffix'], $lower, $upper, $upperInclusive, $collation)) {
                continue;
            }
            $estimate = max(1, $sample['nEq']);
            $best = $best === null ? $estimate : min($best, $estimate);
        }
        if ($best !== null) {
            return $best;
        }

        $span = 0;
        foreach ($samples as $sample) {
            $span = max($span, $sample['nEq']);
        }

        return max(1, min(max(1, $fallback), $span === 0 ? $fallback : $span));
    }

    private static function within(mixed $value, mixed $lower, mixed $upper, bool $upperInclusive, string $collation): bool
    {
        if ($value === null) {
            return false;
        }
        if ($lower !== null && self::compare($value, $lower, $collation) < 0) {
            return false;
        }
        if ($upper !== null) {
            $comparison = self::compare($value, $upper, $collation);
            if ($comparison > 0 || ($comparison === 0 && !$upperInclusive)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array{mode:string,satisfied:bool,partial:bool,blockSortRequired:bool,breakColumns:list<string>}
     */
    private static function orderEvidence(string $skippedColumn, string $rangeColumn, array $orderBy): array
    {
        if ($orderBy === []) {
            return ['mode' => 'none', 'satisfied' => false, 'partial' => false, 'blockSortRequired' => false, 'breakColumns' => []];
        }

        $columns = array_map(static fn (array $term): string => strtolower((string) ($term['column'] ?? '')), $orderBy);
        if ($columns === [strtolower($skippedColumn), strtolower($rangeColumn)]) {
            return ['mode' => 'full', 'satisfied' => true, 'partial' => false, 'blockSortRequired' => false, 'breakColumns' => []];
        }
        if ($columns[0] === strtolower($rangeColumn)) {
            return ['mode' => 'partial-current-next', 'satisfied' => false, 'partial' => true, 'blockSortRequired' => true, 'breakColumns' => [$skippedColumn]];
        }
        if ($columns[0] === strtolower($skippedColumn)) {
            return ['mode' => 'prefix-only', 'satisfied' => count($columns) === 1, 'partial' => count($columns) > 1, 'blockSortRequired' => count($columns) > 1, 'breakColumns' => [$rangeColumn]];
        }

        return ['mode' => 'external-sort', 'satisfied' => false, 'partial' => false, 'blockSortRequired' => true, 'breakColumns' => $columns];
    }

    /**
     * @param array{mode:string,satisfied:bool,partial:bool,blockSortRequired:bool,breakColumns:list<string>} $orderEvidence
     */
    private static function detail(string $indexName, string $skippedColumn, string $rangeColumn, array $orderEvidence): string
    {
        $detail = 'SEARCH USING SKIP-SCAN ' . $indexName . ' (ANY(' . $skippedColumn . ') AND ' . $rangeColumn . ' RANGE) USING STAT4';
        if ($orderEvidence['partial']) {
            return $detail . ' USE TEMP B-TREE FOR RIGHT PART OF ORDER BY';
        }
        if ($orderEvidence['satisfied']) {
            return $detail . ' ORDER BY SATISFIED';
        }

        return $detail;
    }

    private static function compare(mixed $left, mixed $right, string $collation): int
    {
        if ($left === null || $right === null) {
            return $left === $right ? 0 : ($left === null ? -1 : 1);
        }
        $leftText = (string) $left;
        $rightText = (string) $right;
        if (strtoupper($collation) === 'NOCASE') {
            $leftText = strtolower($leftText);
            $rightText = strtolower($rightText);
        }

        return strcmp($leftText, $rightText) <=> 0;
    }

    private static function key(mixed $value): string
    {
        return get_debug_type($value) . ':' . serialize($value);
    }
}
