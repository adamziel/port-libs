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
     * @param list<string> $coveringColumns
     * @param list<string> $neededColumns
     * @return array<string,mixed>|null
     */
    public static function coveringCurrentSourcePlan(
        array $rows,
        string $indexName,
        string $skippedColumn,
        string $rangeColumn,
        mixed $lowerInclusive,
        mixed $upperBound,
        SQLiteIndexPredicate $partialPredicate,
        array $queryTerms,
        array $stat4Samples,
        array $orderBy,
        array $coveringColumns,
        array $neededColumns,
        bool $upperInclusive = true,
        string $collation = 'BINARY',
    ): ?array {
        $coveringColumns = self::validatedColumnList($coveringColumns, 'SQLite STAT4 skip-scan covering index');
        $neededColumns = self::validatedColumnList($neededColumns, 'SQLite STAT4 skip-scan covering projection');

        $plan = self::plan(
            $rows,
            $indexName,
            $skippedColumn,
            $rangeColumn,
            $lowerInclusive,
            $upperBound,
            $partialPredicate,
            $queryTerms,
            $stat4Samples,
            $orderBy,
            $upperInclusive,
            $collation,
        );

        if (($plan['status'] ?? null) !== 'usable') {
            return null;
        }

        $coveringSet = array_fill_keys(array_map('strtolower', $coveringColumns), true);
        $missing = [];
        foreach ($neededColumns as $column) {
            if (!isset($coveringSet[strtolower($column)])) {
                $missing[] = $column;
            }
        }
        if ($missing !== []) {
            return $plan + [
                'covering' => false,
                'coveringRejectedColumns' => $missing,
                'tableSeekRequired' => true,
                'deferredSeekOpcode' => 'DeferredSeek',
                'dependencies' => ['sqlite-stat4-skipscan-covering-current-source-next120'],
            ];
        }

        $currentNextRows = [];
        $matchRows = array_values($plan['rows'] ?? []);
        foreach ($matchRows as $offset => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite STAT4 skip-scan covering rows must be row arrays');
            }
            $currentNextRows[] = [
                'current' => self::coveringRowEvidence($row, $neededColumns, $offset),
                'next' => isset($matchRows[$offset + 1]) ? self::coveringRowEvidence($matchRows[$offset + 1], $neededColumns, $offset + 1) : null,
            ];
        }

        $cursorProgram = [
            ['opcode' => $plan['reverseScan'] ? 'LastPrefix' : 'RewindPrefix', 'source' => 'index', 'index' => $indexName],
            ['opcode' => $plan['upperInclusive'] ? 'SeekGE' : 'SeekGT', 'column' => $rangeColumn, 'value' => $lowerInclusive],
            ['opcode' => $plan['upperInclusive'] ? 'IdxGT' : 'IdxGE', 'column' => $rangeColumn, 'value' => $upperBound],
            ['opcode' => 'Column', 'source' => 'index', 'columns' => $neededColumns],
            ['opcode' => $plan['reverseScan'] ? 'Prev' : 'Next', 'target' => 'index'],
        ];

        return array_replace($plan, [
            'covering' => true,
            'coveringColumns' => $coveringColumns,
            'neededColumns' => $neededColumns,
            'coveringRejectedColumns' => [],
            'tableSeekRequired' => false,
            'deferredSeekOpcode' => null,
            'currentNextCoveringRows' => $currentNextRows,
            'coveredRowCount' => count($currentNextRows),
            'cursorProgram' => $cursorProgram,
            'coveringMode' => ($plan['blockSortRequired'] ?? false) ? 'covering-skipscan-block-sort' : 'covering-skipscan',
            'dependencies' => ['sqlite-stat4-skipscan-covering-current-source-next120'],
            'detail' => $plan['detail'] . ' USING COVERING INDEX',
        ]);
    }

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
            $currentNext = self::currentNextForRange($samples, $lowerInclusive, $upperBound, $upperInclusive, $collation);
            $loopEstimates[] = [
                'prefix' => $prefix,
                'matched' => $loop['matched'],
                'estimatedRows' => $estimate,
                'sampleCount' => count($samples),
                'rowids' => $loop['rowids'],
                'current' => $currentNext['current'],
                'next' => $currentNext['next'],
                'rangeSamples' => $currentNext['rangeSamples'],
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
            'stat4CurrentNextByPrefix' => self::currentNextByPrefix($loopEstimates),
            'estimatedRows' => $estimatedRows,
            'estimatedCost' => $cost,
            'orderByMode' => $orderEvidence['mode'],
            'orderBySatisfied' => $orderEvidence['satisfied'],
            'partialOrderBy' => $orderEvidence['partial'],
            'blockSortRequired' => $orderEvidence['blockSortRequired'],
            'sortBreakColumns' => $orderEvidence['breakColumns'],
            'orderByDirections' => $orderEvidence['directions'],
            'reverseScan' => $orderEvidence['reverseScan'],
            'sortBlockCount' => $orderEvidence['blockSortRequired'] ? $estimatedSeeks : 0,
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

    /**
     * @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples
     * @return array{current:array<string,mixed>|null,next:array<string,mixed>|null,rangeSamples:int}
     */
    private static function currentNextForRange(array $samples, mixed $lower, mixed $upper, bool $upperInclusive, string $collation): array
    {
        $inRange = [];
        foreach ($samples as $sample) {
            if (self::within($sample['suffix'], $lower, $upper, $upperInclusive, $collation)) {
                $inRange[] = $sample;
            }
        }

        usort($inRange, static fn (array $left, array $right): int => self::compare($left['suffix'], $right['suffix'], $collation));

        return [
            'current' => self::sampleEvidence($inRange[0] ?? null),
            'next' => self::sampleEvidence($inRange[1] ?? null),
            'rangeSamples' => count($inRange),
        ];
    }

    /**
     * @param array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}|null $sample
     * @return array<string,mixed>|null
     */
    private static function sampleEvidence(?array $sample): ?array
    {
        if ($sample === null) {
            return null;
        }

        return [
            'prefix' => $sample['prefix'],
            'suffix' => $sample['suffix'],
            'nEq' => $sample['nEq'],
            'nLt' => $sample['nLt'],
            'nDLt' => $sample['nDLt'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $loopEstimates
     * @return list<array{prefix:mixed,current:array<string,mixed>|null,next:array<string,mixed>|null,rangeSamples:int}>
     */
    private static function currentNextByPrefix(array $loopEstimates): array
    {
        return array_map(
            static fn (array $loop): array => [
                'prefix' => $loop['prefix'],
                'current' => $loop['current'] ?? null,
                'next' => $loop['next'] ?? null,
                'rangeSamples' => (int) ($loop['rangeSamples'] ?? 0),
            ],
            $loopEstimates,
        );
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
     * @return array{mode:string,satisfied:bool,partial:bool,blockSortRequired:bool,breakColumns:list<string>,directions:list<string>,reverseScan:bool}
     */
    private static function orderEvidence(string $skippedColumn, string $rangeColumn, array $orderBy): array
    {
        if ($orderBy === []) {
            return ['mode' => 'none', 'satisfied' => false, 'partial' => false, 'blockSortRequired' => false, 'breakColumns' => [], 'directions' => [], 'reverseScan' => false];
        }

        $columns = [];
        $directions = [];
        foreach ($orderBy as $term) {
            $column = strtolower((string) ($term['column'] ?? ''));
            $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                throw new \InvalidArgumentException('SQLite STAT4 skip-scan ORDER BY direction must be ASC or DESC');
            }
            $columns[] = $column;
            $directions[] = $direction;
        }

        $allDesc = $directions !== [] && count(array_unique($directions)) === 1 && $directions[0] === 'DESC';
        if ($columns === [strtolower($skippedColumn), strtolower($rangeColumn)]) {
            if (count(array_unique($directions)) === 1) {
                return ['mode' => $allDesc ? 'full-reverse' : 'full', 'satisfied' => true, 'partial' => false, 'blockSortRequired' => false, 'breakColumns' => [], 'directions' => $directions, 'reverseScan' => $allDesc];
            }

            return ['mode' => 'mixed-direction-external-sort', 'satisfied' => false, 'partial' => false, 'blockSortRequired' => true, 'breakColumns' => [$skippedColumn, $rangeColumn], 'directions' => $directions, 'reverseScan' => false];
        }
        if ($columns[0] === strtolower($rangeColumn)) {
            return ['mode' => 'partial-current-next', 'satisfied' => false, 'partial' => true, 'blockSortRequired' => true, 'breakColumns' => [$skippedColumn], 'directions' => $directions, 'reverseScan' => $directions[0] === 'DESC'];
        }
        if ($columns[0] === strtolower($skippedColumn)) {
            return ['mode' => $directions[0] === 'DESC' ? 'prefix-only-reverse' : 'prefix-only', 'satisfied' => count($columns) === 1, 'partial' => count($columns) > 1, 'blockSortRequired' => count($columns) > 1, 'breakColumns' => [$rangeColumn], 'directions' => $directions, 'reverseScan' => $directions[0] === 'DESC'];
        }

        return ['mode' => 'external-sort', 'satisfied' => false, 'partial' => false, 'blockSortRequired' => true, 'breakColumns' => $columns, 'directions' => $directions, 'reverseScan' => false];
    }

    /**
     * @param array{mode:string,satisfied:bool,partial:bool,blockSortRequired:bool,breakColumns:list<string>,directions:list<string>,reverseScan:bool} $orderEvidence
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

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private static function validatedColumnList(array $columns, string $context): array
    {
        if ($columns === []) {
            throw new \InvalidArgumentException($context . ' needs at least one column');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException($context . ' columns must be non-empty strings');
            }
        }

        return array_values($columns);
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function coveringRowEvidence(array $row, array $columns, int $sourceOffset): array
    {
        $covering = [];
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite STAT4 skip-scan covering row is missing column {$column}");
            }
            $covering[$column] = $row[$column];
        }

        return [
            'rowid' => (int) ($row['rowid'] ?? 0),
            'sourceOffset' => $sourceOffset,
            'covering' => $covering,
        ];
    }
}
