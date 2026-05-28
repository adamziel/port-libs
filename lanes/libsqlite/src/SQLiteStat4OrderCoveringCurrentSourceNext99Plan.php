<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteStat4OrderCoveringCurrentSourceNext99Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(array $preparedSource, array $currentSource, array $predicate, array $orderBy, array $neededColumns): array
    {
        $comparison = SQLiteStat4OrderCoveringCurrentSourceNext94Plan::compare(
            $preparedSource,
            $currentSource,
            $predicate,
            $orderBy,
            $neededColumns,
        );
        $plan = is_array($comparison['selectedPlan'] ?? null) ? $comparison['selectedPlan'] : [];
        $coveringOrder = ($comparison['coveringOrderPlan'] ?? false) === true;
        $range = is_array($plan['rangeConstraint'] ?? null) ? $plan['rangeConstraint'] : null;
        $rangeCurrentNext = is_array($plan['stat4RangeCurrentNext'] ?? null) ? $plan['stat4RangeCurrentNext'] : null;
        $segments = self::segments($plan);

        return array_merge($comparison, [
            'status' => $coveringOrder ? 'covering-order-current-source-ready' : 'requires-next-stage',
            'cursorTape' => [
                'source' => $comparison['selectedSource'],
                'indexName' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'seekOpcode' => self::seekOpcode($range),
                'stopOpcode' => self::stopOpcode($range),
                'nextOpcode' => self::nextOpcode($orderBy),
                'scanDirection' => self::scanDirection($orderBy),
                'rangeColumn' => $plan['rangeColumn'] ?? null,
                'rangeLower' => self::rangeBoundaryValue($rangeCurrentNext, 'lower'),
                'rangeUpper' => self::rangeBoundaryValue($rangeCurrentNext, 'upper'),
                'rangeLowerExact' => self::rangeBoundaryExact($rangeCurrentNext, 'lower'),
                'rangeUpperExact' => self::rangeBoundaryExact($rangeCurrentNext, 'upper'),
                'currentNextSegments' => $segments,
                'currentNextCount' => count($segments),
                'matchedKeys' => array_map(static fn (array $segment): mixed => $segment['currentKey'], $segments),
                'outputColumns' => self::outputColumns($neededColumns),
                'deferredSeekOpcode' => $coveringOrder ? null : 'DeferredSeek',
                'sorterOpen' => !$coveringOrder && ($plan['blockSortRequired'] ?? false) === true,
                'tableLookupElided' => $coveringOrder,
                'tempSortElided' => $coveringOrder,
                'program' => self::program($coveringOrder, $plan, $range, $orderBy, $neededColumns),
            ],
            'currentSourceFence' => [
                'schemaCookie' => $comparison['currentSource']['schemaCookie'] ?? null,
                'stat4Generation' => $comparison['currentSource']['stat4Generation'] ?? null,
                'indexSignature' => $comparison['currentSource']['indexSignature'] ?? null,
                'projectionSignature' => $comparison['currentSource']['projectionSignature'] ?? null,
                'orderSignature' => $comparison['orderSignature'] ?? '',
            ],
            'dependency_closure' => 'no new support component needed; next99 composes accepted STAT4 current-source planning into native covering ORDER BY cursor tape diagnostics',
            'non_overlap' => 'avoids batch94 plan invalidation-only coverage by asserting current/next cursor tape materialization and VDBE-style sorter/table-lookup elision for the selected covering ordered index',
        ]);
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<array{position:int,currentKey:mixed,nextKey:mixed,neq:int|null,nlt:int|null,ndlt:int|null,advance:string}>
     */
    private static function segments(array $plan): array
    {
        $pairs = $plan['stat4MatchedCurrentNext'] ?? [];
        if (!is_array($pairs) || !array_is_list($pairs)) {
            return [];
        }

        $segments = [];
        foreach ($pairs as $offset => $pair) {
            if (!is_array($pair) || !is_array($pair['current'] ?? null)) {
                continue;
            }
            $current = $pair['current'];
            $next = is_array($pair['next'] ?? null) ? $pair['next'] : null;
            $segments[] = [
                'position' => $offset,
                'currentKey' => $current['key'] ?? null,
                'nextKey' => $next['key'] ?? null,
                'neq' => isset($current['neq']) && is_int($current['neq']) ? $current['neq'] : null,
                'nlt' => isset($current['nlt']) && is_int($current['nlt']) ? $current['nlt'] : null,
                'ndlt' => isset($current['ndlt']) && is_int($current['ndlt']) ? $current['ndlt'] : null,
                'advance' => $next === null ? 'eof' : 'next',
            ];
        }

        return $segments;
    }

    /**
     * @param array<string,mixed>|null $range
     */
    private static function seekOpcode(?array $range): string
    {
        $operator = is_array($range) ? (string) ($range['operator'] ?? '') : '';
        if ($operator === 'range->') {
            return 'SeekGT';
        }

        return 'SeekGE';
    }

    /**
     * @param array<string,mixed>|null $range
     */
    private static function stopOpcode(?array $range): string
    {
        $operator = is_array($range) ? (string) ($range['operator'] ?? '') : '';
        if ($operator === 'range-<=' || $operator === 'BETWEEN') {
            return 'IdxGT';
        }
        if ($operator === 'range-bounded' && is_array($range['values'] ?? null) && (($range['values']['upperInclusive'] ?? false) === true)) {
            return 'IdxGT';
        }

        return 'IdxGE';
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function nextOpcode(array $orderBy): string
    {
        return self::scanDirection($orderBy) === 'descending' ? 'Prev' : 'Next';
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function scanDirection(array $orderBy): string
    {
        if ($orderBy !== [] && strtoupper((string) ($orderBy[0]['direction'] ?? 'ASC')) === 'DESC') {
            return 'descending';
        }

        return 'ascending';
    }

    /**
     * @param array<string,mixed>|null $rangeCurrentNext
     */
    private static function rangeBoundaryValue(?array $rangeCurrentNext, string $side): mixed
    {
        $boundary = is_array($rangeCurrentNext[$side] ?? null) ? $rangeCurrentNext[$side] : null;
        if ($boundary === null) {
            return null;
        }

        return $boundary['value'] ?? null;
    }

    /**
     * @param array<string,mixed>|null $rangeCurrentNext
     */
    private static function rangeBoundaryExact(?array $rangeCurrentNext, string $side): bool
    {
        $boundary = is_array($rangeCurrentNext[$side] ?? null) ? $rangeCurrentNext[$side] : null;

        return $boundary !== null && ($boundary['exact'] ?? false) === true;
    }

    /**
     * @param list<string> $neededColumns
     * @return list<array{column:string,opcode:string}>
     */
    private static function outputColumns(array $neededColumns): array
    {
        $columns = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite STAT4 order-covering current-source next99 needs output column names');
            }
            $columns[] = ['column' => $column, 'opcode' => 'Column'];
        }

        return $columns;
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed>|null $range
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function program(bool $coveringOrder, array $plan, ?array $range, array $orderBy, array $neededColumns): array
    {
        $program = [
            ['opcode' => 'OpenRead', 'target' => 'index', 'rootPage' => $plan['rootPage'] ?? null],
            ['opcode' => self::seekOpcode($range), 'column' => $plan['rangeColumn'] ?? null],
            ['opcode' => self::stopOpcode($range), 'column' => $plan['rangeColumn'] ?? null],
        ];
        if (!$coveringOrder) {
            $program[] = ['opcode' => 'DeferredSeek', 'target' => 'table'];
        }
        if (!$coveringOrder && ($plan['blockSortRequired'] ?? false) === true) {
            $program[] = ['opcode' => 'SorterOpen', 'orderBy' => $orderBy];
        }
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite STAT4 order-covering current-source next99 needs output column names');
            }
            $program[] = ['opcode' => 'Column', 'source' => $coveringOrder ? 'index' : 'table', 'column' => $column];
        }
        $program[] = ['opcode' => self::nextOpcode($orderBy), 'target' => 'index'];

        return $program;
    }
}
