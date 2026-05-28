<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteStat4RangeOrderCurrentSourceNext102Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(array $preparedSource, array $currentSource, array $predicate, array $orderBy, array $neededColumns = []): array
    {
        $prepared = self::sourcePlan($preparedSource, $predicate, $orderBy, $neededColumns);
        $current = self::sourcePlan($currentSource, $predicate, $orderBy, $neededColumns);
        $preparedFence = self::fence($preparedSource, $predicate, $orderBy, $neededColumns);
        $currentFence = self::fence($currentSource, $predicate, $orderBy, $neededColumns);
        $stale = $preparedFence !== $currentFence;
        $selected = $stale ? $current : $prepared;
        $source = $stale ? $currentSource : $preparedSource;
        $range = is_array($selected['rangeConstraint'] ?? null) ? $selected['rangeConstraint'] : null;
        $stat4 = is_array($selected['stat4RangeCurrentNext'] ?? null) ? $selected['stat4RangeCurrentNext'] : null;
        $reverse = self::reverseScan($orderBy);
        $covering = ($selected['covering'] ?? false) === true;

        return [
            'status' => ($selected['status'] ?? null) === 'usable' ? 'range-order-current-source-ready' : 'no-usable-plan',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => self::nonNegativeInt($preparedSource, 'schemaCookie') !== self::nonNegativeInt($currentSource, 'schemaCookie'),
            'stat4GenerationChanged' => self::nonNegativeInt($preparedSource, 'stat4Generation') !== self::nonNegativeInt($currentSource, 'stat4Generation'),
            'indexSignatureChanged' => self::indexSignature($preparedSource) !== self::indexSignature($currentSource),
            'predicateSignatureChanged' => self::predicateSignature($predicate) !== ($preparedSource['preparedPredicateSignature'] ?? self::predicateSignature($predicate)),
            'orderSignature' => self::orderSignature($orderBy),
            'preparedPlan' => $prepared,
            'currentPlan' => $current,
            'selectedPlan' => $selected,
            'estimatedRowsDelta' => (int) ($current['estimatedRows'] ?? 0) - (int) ($prepared['estimatedRows'] ?? 0),
            'estimatedCostDelta' => (int) ($current['estimatedCost'] ?? 0) - (int) ($prepared['estimatedCost'] ?? 0),
            'cursorTape' => [
                'source' => $stale ? 'current' : 'prepared',
                'sourceName' => self::stringValue($source, 'name', 'source'),
                'indexName' => $selected['name'] ?? $selected['selected'] ?? null,
                'rootPage' => $selected['rootPage'] ?? null,
                'rangeColumn' => $selected['rangeColumn'] ?? null,
                'seekOpcode' => self::seekOpcode($range, $reverse),
                'stopOpcode' => self::stopOpcode($range, $reverse),
                'nextOpcode' => $reverse ? 'Prev' : 'Next',
                'scanDirection' => $reverse ? 'descending' : 'ascending',
                'lowerValue' => self::rangeValue($range, 'lower'),
                'upperValue' => self::rangeValue($range, 'upper'),
                'lowerInclusive' => self::rangeInclusive($range, 'lower'),
                'upperInclusive' => self::rangeInclusive($range, 'upper'),
                'stat4LowerCurrent' => self::boundaryKey($stat4, 'lower', 'current'),
                'stat4LowerNext' => self::boundaryKey($stat4, 'lower', 'next'),
                'stat4UpperCurrent' => self::boundaryKey($stat4, 'upper', 'current'),
                'stat4UpperNext' => self::boundaryKey($stat4, 'upper', 'next'),
                'stat4LowerExact' => self::boundaryExact($stat4, 'lower'),
                'stat4UpperExact' => self::boundaryExact($stat4, 'upper'),
                'stat4EmptyGap' => (bool) ($stat4['emptyGap'] ?? false),
                'stat4MatchedSamples' => $selected['stat4MatchedSamples'] ?? 0,
                'covering' => $covering,
                'deferredSeekOpcode' => $covering ? null : 'DeferredSeek',
                'sorterOpen' => ($selected['blockSortRequired'] ?? false) === true,
                'program' => self::program($selected, $range, $orderBy, $neededColumns, $covering),
            ],
            'currentSourceFence' => $currentFence,
            'detail' => ($stale ? 'REPREPARE' : 'REUSE') . ' STAT4 RANGE ORDER CURRENT SOURCE ' . (string) ($selected['detail'] ?? 'NO PLAN'),
            'dependency_closure' => 'no new support component needed; next102 composes existing STAT4 multicolumn range planning into current-source cursor tape diagnostics',
            'non_overlap' => 'avoids accepted expression-index range-cost and expression ORDER BY work by asserting STAT4 range boundary seek/stop opcodes, current-source fences, and covering/deferred cursor behavior for plain indexed option-name ranges',
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function sourcePlan(array $source, array $predicate, array $orderBy, array $neededColumns): array
    {
        $plan = SQLiteMultiColumnRangePlan::stat4RangeOrderCurrentSourceNext92(
            self::listValue($source, 'indexes'),
            $predicate,
            $orderBy,
            $neededColumns,
        );
        if (($plan['status'] ?? null) !== 'usable') {
            return $plan;
        }

        return $plan + ['status' => 'usable'];
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function fence(array $source, array $predicate, array $orderBy, array $neededColumns): array
    {
        return [
            'schemaCookie' => self::nonNegativeInt($source, 'schemaCookie'),
            'stat4Generation' => self::nonNegativeInt($source, 'stat4Generation'),
            'indexSignature' => self::indexSignature($source),
            'predicateSignature' => self::predicateSignature($predicate),
            'orderSignature' => self::orderSignature($orderBy),
            'projectionSignature' => self::projectionSignature($neededColumns),
        ];
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function indexSignature(array $source): string
    {
        $parts = [];
        foreach (self::listValue($source, 'indexes') as $index) {
            $parts[] = hash('sha256', serialize([
                $index['name'] ?? null,
                $index['rootPage'] ?? null,
                isset($index['sql']) && is_string($index['sql']) ? preg_replace('/\s+/', ' ', trim($index['sql'])) : null,
                $index['estimatedRows'] ?? null,
                $index['stat4Samples'] ?? [],
            ]));
        }
        sort($parts, SORT_STRING);

        return hash('sha256', implode("\n", $parts));
    }

    /**
     * @param array<string,mixed> $predicate
     */
    private static function predicateSignature(array $predicate): string
    {
        return hash('sha256', serialize($predicate));
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function orderSignature(array $orderBy): string
    {
        $parts = [];
        foreach ($orderBy as $term) {
            $column = $term['column'] ?? null;
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite STAT4 range-order current-source next102 needs ORDER BY columns');
            }
            $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite STAT4 range-order current-source next102 ORDER BY direction must be ASC or DESC');
            }
            $parts[] = strtolower($column) . ' ' . $direction;
        }

        return implode(',', $parts);
    }

    /**
     * @param list<string> $neededColumns
     */
    private static function projectionSignature(array $neededColumns): string
    {
        $columns = $neededColumns;
        sort($columns, SORT_STRING);

        return implode("\0", $columns);
    }

    /**
     * @param array<string,mixed>|null $range
     */
    private static function seekOpcode(?array $range, bool $reverse): string
    {
        if ($reverse) {
            return self::rangeInclusive($range, 'upper') ? 'SeekLE' : 'SeekLT';
        }

        return self::rangeInclusive($range, 'lower') ? 'SeekGE' : 'SeekGT';
    }

    /**
     * @param array<string,mixed>|null $range
     */
    private static function stopOpcode(?array $range, bool $reverse): string
    {
        if ($reverse) {
            return self::rangeInclusive($range, 'lower') ? 'IdxLT' : 'IdxLE';
        }

        return self::rangeInclusive($range, 'upper') ? 'IdxGT' : 'IdxGE';
    }

    /**
     * @param array<string,mixed>|null $range
     */
    private static function rangeValue(?array $range, string $side): mixed
    {
        if ($range === null) {
            return null;
        }
        $operator = (string) ($range['operator'] ?? '');
        $values = $range['values'] ?? null;
        if ($operator === 'BETWEEN' || $operator === 'range-bounded') {
            return is_array($values) ? ($values[$side] ?? null) : null;
        }
        if ($side === 'lower' && ($operator === 'range->' || $operator === 'range->=')) {
            return $values;
        }
        if ($side === 'upper' && ($operator === 'range-<' || $operator === 'range-<=')) {
            return $values;
        }

        return null;
    }

    /**
     * @param array<string,mixed>|null $range
     */
    private static function rangeInclusive(?array $range, string $side): bool
    {
        if ($range === null) {
            return true;
        }
        $operator = (string) ($range['operator'] ?? '');
        $values = $range['values'] ?? null;
        if ($operator === 'BETWEEN') {
            return true;
        }
        if ($operator === 'range-bounded' && is_array($values)) {
            return (bool) ($values[$side . 'Inclusive'] ?? false);
        }
        if ($side === 'lower') {
            return $operator !== 'range->';
        }

        return $operator !== 'range-<';
    }

    /**
     * @param array<string,mixed>|null $stat4
     */
    private static function boundaryKey(?array $stat4, string $side, string $which): mixed
    {
        $boundary = is_array($stat4[$side] ?? null) ? $stat4[$side] : null;
        $sample = is_array($boundary[$which] ?? null) ? $boundary[$which] : null;

        return $sample['key'] ?? null;
    }

    /**
     * @param array<string,mixed>|null $stat4
     */
    private static function boundaryExact(?array $stat4, string $side): bool
    {
        $boundary = is_array($stat4[$side] ?? null) ? $stat4[$side] : null;

        return $boundary !== null && ($boundary['exact'] ?? false) === true;
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function reverseScan(array $orderBy): bool
    {
        return $orderBy !== [] && strtoupper((string) ($orderBy[0]['direction'] ?? 'ASC')) === 'DESC';
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed>|null $range
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function program(array $plan, ?array $range, array $orderBy, array $neededColumns, bool $covering): array
    {
        $reverse = self::reverseScan($orderBy);
        $program = [
            ['opcode' => 'OpenRead', 'target' => 'index', 'rootPage' => $plan['rootPage'] ?? null],
            ['opcode' => self::seekOpcode($range, $reverse), 'column' => $plan['rangeColumn'] ?? null, 'value' => $reverse ? self::rangeValue($range, 'upper') : self::rangeValue($range, 'lower')],
            ['opcode' => self::stopOpcode($range, $reverse), 'column' => $plan['rangeColumn'] ?? null, 'value' => $reverse ? self::rangeValue($range, 'lower') : self::rangeValue($range, 'upper')],
        ];
        if (!$covering) {
            $program[] = ['opcode' => 'DeferredSeek', 'target' => 'table'];
        }
        if (($plan['blockSortRequired'] ?? false) === true) {
            $program[] = ['opcode' => 'SorterOpen', 'orderBy' => $orderBy];
        }
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite STAT4 range-order current-source next102 needs output column names');
            }
            $program[] = ['opcode' => 'Column', 'source' => $covering ? 'index' : 'table', 'column' => $column];
        }
        $program[] = ['opcode' => $reverse ? 'Prev' : 'Next', 'target' => 'index'];

        return $program;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function stringValue(array $data, string $key, ?string $default = null): string
    {
        $value = $data[$key] ?? $default;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite STAT4 range-order current-source next102 needs {$key}");
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
            throw new \InvalidArgumentException("SQLite STAT4 range-order current-source next102 needs non-negative integer {$key}");
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
            throw new \InvalidArgumentException("SQLite STAT4 range-order current-source next102 needs list {$key}");
        }

        return $value;
    }
}
