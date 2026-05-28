<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteStat4RangeOrderCurrentSourceNext97Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function compare(array $preparedSource, array $currentSource, array $orderBy = []): array
    {
        $prepared = self::sourcePlan($preparedSource, $orderBy);
        $current = self::sourcePlan($currentSource, $orderBy);

        $preparedCookie = self::nonNegativeInt($preparedSource, 'schemaCookie');
        $currentCookie = self::nonNegativeInt($currentSource, 'schemaCookie');
        $preparedStat4 = self::nonNegativeInt($preparedSource, 'stat4Generation');
        $currentStat4 = self::nonNegativeInt($currentSource, 'stat4Generation');
        $preparedRange = self::rangeSignature($preparedSource);
        $currentRange = self::rangeSignature($currentSource);
        $stale = $preparedCookie !== $currentCookie
            || $preparedStat4 !== $currentStat4
            || $preparedRange !== $currentRange;
        $selected = $stale ? $current : $prepared;

        return [
            'status' => $selected['status'],
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => $preparedCookie !== $currentCookie,
            'stat4GenerationChanged' => $preparedStat4 !== $currentStat4,
            'rangeChanged' => $preparedRange !== $currentRange,
            'orderByModeChanged' => ($prepared['orderByMode'] ?? null) !== ($current['orderByMode'] ?? null),
            'estimatedRowsDelta' => (int) ($current['estimatedRows'] ?? 0) - (int) ($prepared['estimatedRows'] ?? 0),
            'preparedSource' => self::sourceSummary($preparedSource, $prepared),
            'currentSource' => self::sourceSummary($currentSource, $current),
            'selectedPlan' => $selected,
            'detail' => self::detail($stale, $selected, $currentSource),
            'dependencies' => [
                'SQLiteStat4RangeOrderCurrentSourceNext97Plan',
                'SQLiteIndexPredicate',
                'sqlite_stat4',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    private static function sourcePlan(array $source, array $orderBy): array
    {
        $indexName = self::stringValue($source, 'indexName');
        $rangeColumn = self::stringValue($source, 'rangeColumn');
        $collation = strtoupper(self::stringValue($source, 'collation', 'BINARY'));
        if (!in_array($collation, ['BINARY', 'NOCASE'], true)) {
            throw new \InvalidArgumentException('SQLite STAT4 range-order planner supports BINARY and NOCASE collations');
        }

        $rows = self::listValue($source, 'rows');
        $lower = $source['lower'] ?? null;
        $upper = $source['upper'] ?? null;
        $upperInclusive = (bool) ($source['upperInclusive'] ?? true);
        $matchingRows = [];
        $omittedNullRangeRows = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite STAT4 range-order rows must be arrays');
            }
            if (!array_key_exists($rangeColumn, $row)) {
                throw new \InvalidArgumentException('SQLite STAT4 range-order rows need range column values');
            }
            if ($row[$rangeColumn] === null) {
                ++$omittedNullRangeRows;
                continue;
            }
            if (self::within($row[$rangeColumn], $lower, $upper, $upperInclusive, $collation)) {
                $matchingRows[] = $row;
            }
        }

        $orderEvidence = self::orderEvidence($rangeColumn, $orderBy);
        usort(
            $matchingRows,
            static function (array $left, array $right) use ($rangeColumn, $collation, $orderEvidence): int {
                $comparison = self::compareValues($left[$rangeColumn] ?? null, $right[$rangeColumn] ?? null, $collation);
                if ($comparison === 0) {
                    $comparison = ((int) ($left['rowid'] ?? 0)) <=> ((int) ($right['rowid'] ?? 0));
                }

                return $orderEvidence['reverseScan'] ? -$comparison : $comparison;
            }
        );

        $samples = self::stat4Samples(self::listValue($source, 'stat4Samples'));
        $currentNext = self::currentNextForRange($samples, $lower, $upper, $upperInclusive, $collation);
        $estimate = self::estimateRows($samples, $currentNext['rangeSamples'], count($matchingRows));
        $seekCount = $matchingRows === [] ? 0 : 1;
        $sortPenalty = $orderEvidence['blockSortRequired'] ? max(1, count($matchingRows)) : 0;

        return [
            'status' => 'usable',
            'usable' => true,
            'indexName' => $indexName,
            'rangeColumn' => $rangeColumn,
            'collation' => $collation,
            'rowids' => array_values(array_map(static fn (array $row): int => (int) ($row['rowid'] ?? 0), $matchingRows)),
            'omittedNullRangeRows' => $omittedNullRangeRows,
            'stat4SamplesUsed' => count($samples),
            'stat4Current' => $currentNext['current'],
            'stat4Next' => $currentNext['next'],
            'stat4RangeSamples' => $currentNext['rangeSamples'],
            'stat4RangeNltSpan' => $currentNext['nLtSpan'],
            'estimatedRows' => $estimate,
            'estimatedCost' => $seekCount * 8 + $estimate + $sortPenalty,
            'orderByMode' => $orderEvidence['mode'],
            'orderBySatisfied' => $orderEvidence['satisfied'],
            'blockSortRequired' => $orderEvidence['blockSortRequired'],
            'reverseScan' => $orderEvidence['reverseScan'],
            'sortBlockCount' => $orderEvidence['blockSortRequired'] ? 1 : 0,
            'detail' => self::planDetail($indexName, $rangeColumn, $orderEvidence),
        ];
    }

    /**
     * @param list<array<string,mixed>> $samples
     * @return list<array{value:mixed,nEq:int,nLt:int,nDLt:int}>
     */
    private static function stat4Samples(array $samples): array
    {
        $normalized = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !array_key_exists('value', $sample)) {
                throw new \InvalidArgumentException('SQLite STAT4 range-order samples need value');
            }
            foreach (['nEq', 'nLt', 'nDLt'] as $field) {
                if (!isset($sample[$field]) || !is_int($sample[$field]) || $sample[$field] < 0) {
                    throw new \InvalidArgumentException('SQLite STAT4 range-order samples need non-negative counters');
                }
            }
            $normalized[] = [
                'value' => $sample['value'],
                'nEq' => $sample['nEq'],
                'nLt' => $sample['nLt'],
                'nDLt' => $sample['nDLt'],
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array{value:mixed,nEq:int,nLt:int,nDLt:int}> $samples
     * @return array{current:array<string,mixed>|null,next:array<string,mixed>|null,rangeSamples:int,nLtSpan:int}
     */
    private static function currentNextForRange(array $samples, mixed $lower, mixed $upper, bool $upperInclusive, string $collation): array
    {
        $inRange = [];
        foreach ($samples as $sample) {
            if (self::within($sample['value'], $lower, $upper, $upperInclusive, $collation)) {
                $inRange[] = $sample;
            }
        }
        usort($inRange, static fn (array $left, array $right): int => self::compareValues($left['value'], $right['value'], $collation));

        $first = $inRange[0] ?? null;
        $last = $inRange === [] ? null : $inRange[array_key_last($inRange)];
        $span = $first === null || $last === null ? 0 : max(0, $last['nLt'] - $first['nLt'] + $last['nEq']);

        return [
            'current' => self::sampleEvidence($first),
            'next' => self::sampleEvidence($inRange[1] ?? null),
            'rangeSamples' => count($inRange),
            'nLtSpan' => $span,
        ];
    }

    /**
     * @param array{value:mixed,nEq:int,nLt:int,nDLt:int}|null $sample
     * @return array<string,mixed>|null
     */
    private static function sampleEvidence(?array $sample): ?array
    {
        if ($sample === null) {
            return null;
        }

        return [
            'value' => $sample['value'],
            'nEq' => $sample['nEq'],
            'nLt' => $sample['nLt'],
            'nDLt' => $sample['nDLt'],
        ];
    }

    /**
     * @return array{mode:string,satisfied:bool,blockSortRequired:bool,reverseScan:bool}
     */
    private static function orderEvidence(string $rangeColumn, array $orderBy): array
    {
        if ($orderBy === []) {
            return ['mode' => 'none', 'satisfied' => false, 'blockSortRequired' => false, 'reverseScan' => false];
        }
        if (count($orderBy) !== 1) {
            return ['mode' => 'external-sort', 'satisfied' => false, 'blockSortRequired' => true, 'reverseScan' => false];
        }
        $term = $orderBy[0];
        $column = strtolower((string) ($term['column'] ?? ''));
        $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
        if ($column === '' || !in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException('SQLite STAT4 range-order ORDER BY needs column and ASC/DESC direction');
        }
        if ($column !== strtolower($rangeColumn)) {
            return ['mode' => 'external-sort', 'satisfied' => false, 'blockSortRequired' => true, 'reverseScan' => false];
        }

        return ['mode' => $direction === 'DESC' ? 'range-reverse' : 'range', 'satisfied' => true, 'blockSortRequired' => false, 'reverseScan' => $direction === 'DESC'];
    }

    private static function estimateRows(array $samples, int $rangeSamples, int $fallback): int
    {
        if ($rangeSamples === 0) {
            return max(1, $fallback);
        }

        $estimate = 0;
        foreach ($samples as $sample) {
            $estimate += min(max(1, $sample['nEq']), 4);
        }

        return max(1, min(max(1, $fallback), $estimate));
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function sourceSummary(array $source, array $plan): array
    {
        return [
            'name' => self::stringValue($source, 'name', 'source'),
            'schemaCookie' => self::nonNegativeInt($source, 'schemaCookie'),
            'stat4Generation' => self::nonNegativeInt($source, 'stat4Generation'),
            'rangeSignature' => self::rangeSignature($source),
            'rowids' => $plan['rowids'] ?? [],
            'estimatedRows' => $plan['estimatedRows'] ?? 0,
            'estimatedCost' => $plan['estimatedCost'] ?? 0,
            'orderByMode' => $plan['orderByMode'] ?? 'none',
            'orderBySatisfied' => $plan['orderBySatisfied'] ?? false,
            'stat4Current' => $plan['stat4Current'] ?? null,
            'stat4Next' => $plan['stat4Next'] ?? null,
            'stat4RangeSamples' => $plan['stat4RangeSamples'] ?? 0,
        ];
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function rangeSignature(array $source): string
    {
        return serialize([$source['lower'] ?? null, $source['upper'] ?? null, (bool) ($source['upperInclusive'] ?? true), self::stringValue($source, 'collation', 'BINARY')]);
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $currentSource
     */
    private static function detail(bool $stale, array $plan, array $currentSource): string
    {
        $action = $stale ? 'REPREPARE STAT4 RANGE ORDER USING CURRENT SOURCE ' : 'REUSE PREPARED STAT4 RANGE ORDER ';

        return $action . self::stringValue($currentSource, 'name', 'current') . ' ' . (string) ($plan['detail'] ?? 'NO PLAN');
    }

    /**
     * @param array{mode:string,satisfied:bool,blockSortRequired:bool,reverseScan:bool} $orderEvidence
     */
    private static function planDetail(string $indexName, string $rangeColumn, array $orderEvidence): string
    {
        $detail = 'SEARCH ' . $indexName . ' USING STAT4 ' . $rangeColumn . ' RANGE';
        if ($orderEvidence['satisfied']) {
            $detail .= $orderEvidence['reverseScan'] ? ' ORDER BY RANGE REVERSE' : ' ORDER BY RANGE';
        } elseif ($orderEvidence['blockSortRequired']) {
            $detail .= ' USE TEMP B-TREE FOR ORDER BY';
        }

        return $detail;
    }

    private static function within(mixed $value, mixed $lower, mixed $upper, bool $upperInclusive, string $collation): bool
    {
        if ($value === null) {
            return false;
        }
        if ($lower !== null && self::compareValues($value, $lower, $collation) < 0) {
            return false;
        }
        if ($upper !== null) {
            $comparison = self::compareValues($value, $upper, $collation);
            if ($comparison > 0 || ($comparison === 0 && !$upperInclusive)) {
                return false;
            }
        }

        return true;
    }

    private static function compareValues(mixed $left, mixed $right, string $collation): int
    {
        if ($left === null || $right === null) {
            return $left === $right ? 0 : ($left === null ? -1 : 1);
        }
        $leftText = (string) $left;
        $rightText = (string) $right;
        if ($collation === 'NOCASE') {
            $leftText = strtolower($leftText);
            $rightText = strtolower($rightText);
        }

        return strcmp($leftText, $rightText) <=> 0;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function stringValue(array $data, string $key, ?string $default = null): string
    {
        $value = $data[$key] ?? $default;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite STAT4 range-order current-source planner needs {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function nonNegativeInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite STAT4 range-order current-source planner needs non-negative integer {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $data
     * @return list<array<string,mixed>>
     */
    private static function listValue(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException("SQLite STAT4 range-order current-source planner needs list {$key}");
        }

        return $value;
    }
}
