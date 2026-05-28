<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4PartialCoveringCurrentSourceNext142Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $predicate,
        array $orderBy,
        array $neededColumns
    ): array {
        self::validateOrderBy($orderBy);

        $base = SQLitePlannerStat4PartialCoveringCurrentSourceNext135Plan::materialize(
            $preparedSource,
            $currentSource,
            $predicate,
            $orderBy,
            $neededColumns,
        );
        $rows = self::orderedRows(
            is_array($base['coveredRows'] ?? null) ? $base['coveredRows'] : [],
            $orderBy,
        );
        $ready = ($base['status'] ?? null) === 'stat4-partial-covering-current-source-next135-ready'
            && $rows !== []
            && self::selectedPlanArray($base)['usesSkipScan'] !== true;
        $blockSort = $ready && self::requiresRightPartSort(self::selectedPlanArray($base), $orderBy);
        $blocks = self::stat4Blocks($rows, $orderBy);
        $selectedPlan = array_replace(self::selectedPlanArray($base), [
            'next142Ready' => $ready,
            'next142OrderedRowCount' => count($rows),
            'next142OrderedRowids' => array_column($rows, 'rowid'),
            'next142OrderByMode' => self::orderMode(self::selectedPlanArray($base), $orderBy, $blockSort),
            'next142BlockSortRequired' => $blockSort,
            'next142Stat4BlockCount' => count($blocks),
            'next142Stat4BlockKeys' => array_column($blocks, 'key'),
            'next142CursorProgram' => self::cursorProgram(self::selectedPlanArray($base), $neededColumns, $blockSort),
            'nextSource' => $ready ? 'partial-covering-stat4-current-next142' : 'table-rowid-lookup',
        ]);

        return array_replace($base, [
            'status' => $ready ? 'stat4-partial-covering-current-source-next142-ready' : 'requires-next-stage',
            'coveredRows' => $rows,
            'currentNextRows' => self::currentNextRows($rows),
            'stat4AnchorBlocks' => $blocks,
            'stat4AnchorBlockCount' => count($blocks),
            'tempBtreeForRightPartOrderBy' => $blockSort,
            'tableLookupElided' => $ready,
            'deferredSeekOpcode' => $ready ? null : 'DeferredSeek',
            'selectedPlan' => $selectedPlan,
            'currentSourceFence' => array_replace(
                is_array($base['currentSourceFence'] ?? null) ? $base['currentSourceFence'] : [],
                [
                    'next142RowStreamSignature' => hash('sha256', json_encode(array_column($rows, 'rowid'), JSON_THROW_ON_ERROR)),
                    'next142OrderSignature' => self::orderSignature($orderBy),
                    'next142Stat4BlockSignature' => hash('sha256', json_encode(array_column($blocks, 'key'), JSON_THROW_ON_ERROR)),
                ],
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 PARTIAL COVERING CURRENT SOURCE NEXT142 '
                . (string) ($selectedPlan['name'] ?? 'NO INDEX')
                . ($blockSort ? ' USE TEMP B-TREE FOR RIGHT PART OF ORDER BY' : ' ORDERED BY COVERING INDEX'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4PartialCoveringCurrentSourceNext135Plan',
                    'sqlite-sqlplanner-stat4-partial-covering-current-source-next142',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next142 reuses native partial-covering STAT4 current-source row streams and adds lane-local ORDER/current-next block materialization',
            'non_overlap' => 'avoids accepted next135 row-stream admission, next138 non-partial STAT4 ranges, expression ORDER BY, expression-index range costs, skip-scan, JSON table, WAL, VFS, and B-tree clusters; this slice covers partial-covering STAT4 current/next ORDER block materialization',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return list<array<string,mixed>>
     */
    private static function orderedRows(array $rows, array $orderBy): array
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next142 rows must be arrays');
            }
        }

        if ($orderBy === []) {
            return $rows;
        }

        usort($rows, static function (array $left, array $right) use ($orderBy): int {
            foreach ($orderBy as $term) {
                $column = $term['column'];
                $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
                $comparison = self::rowColumnCompare($left, $right, $column);
                if ($comparison === 0) {
                    continue;
                }

                return $direction === 'DESC' ? -$comparison : $comparison;
            }

            return ((int) ($left['rowid'] ?? $left['sourceOffset'] ?? 0)) <=> ((int) ($right['rowid'] ?? $right['sourceOffset'] ?? 0));
        });

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{current:array<string,mixed>,next:array<string,mixed>|null}>
     */
    private static function currentNextRows(array $rows): array
    {
        $pairs = [];
        foreach ($rows as $offset => $row) {
            $pairs[] = [
                'current' => $row,
                'next' => $rows[$offset + 1] ?? null,
            ];
        }

        return $pairs;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return list<array{key:mixed,rowids:list<mixed>,anchorCount:int,firstRowid:mixed,lastRowid:mixed,nextKey:mixed}>
     */
    private static function stat4Blocks(array $rows, array $orderBy): array
    {
        $column = $orderBy[0]['column'] ?? 'rangeKey';
        $blocks = [];
        foreach ($rows as $row) {
            $key = self::rowColumnValue($row, $column);
            $signature = self::keySignature($key);
            if (!isset($blocks[$signature])) {
                $blocks[$signature] = [
                    'key' => $key,
                    'rowids' => [],
                    'anchorCount' => 0,
                    'firstRowid' => $row['rowid'] ?? null,
                    'lastRowid' => $row['rowid'] ?? null,
                    'nextKey' => null,
                ];
            }
            $blocks[$signature]['rowids'][] = $row['rowid'] ?? null;
            $blocks[$signature]['lastRowid'] = $row['rowid'] ?? null;
            if (($row['stat4Anchor'] ?? false) === true) {
                ++$blocks[$signature]['anchorCount'];
            }
        }

        $ordered = array_values($blocks);
        foreach ($ordered as $offset => $block) {
            $ordered[$offset]['nextKey'] = $ordered[$offset + 1]['key'] ?? null;
        }

        return $ordered;
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function requiresRightPartSort(array $plan, array $orderBy): bool
    {
        if ($orderBy === []) {
            return false;
        }
        if (($plan['orderBySatisfied'] ?? false) === true) {
            return false;
        }

        return ($plan['partialPredicateImplied'] ?? false) === true
            && ($plan['covering'] ?? false) === true;
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function orderMode(array $plan, array $orderBy, bool $blockSort): string
    {
        if ($orderBy === []) {
            return 'rowid-current-next';
        }
        if (($plan['orderBySatisfied'] ?? false) === true) {
            return 'covering-index-order';
        }
        if ($blockSort) {
            return 'partial-covering-right-part-sort';
        }

        return 'external-sort';
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $plan, array $neededColumns, bool $blockSort): array
    {
        if (($plan['usable'] ?? false) !== true) {
            return [['opcode' => 'Rewind', 'source' => 'table']];
        }

        $program = [[
            'opcode' => 'OpenRead',
            'target' => 'index',
            'index' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
        ]];
        $program[] = [
            'opcode' => 'SeekStat4',
            'column' => $plan['rangeColumn'] ?? null,
            'anchors' => array_values(array_filter($plan['stat4AnchorKeys'] ?? [], static fn (mixed $key): bool => $key !== null)),
        ];
        foreach ($neededColumns as $column) {
            $program[] = [
                'opcode' => 'Column',
                'source' => 'covering-index',
                'column' => $column,
            ];
        }
        if ($blockSort) {
            $program[] = ['opcode' => 'SorterInsert', 'source' => 'covering-index'];
            $program[] = ['opcode' => 'SorterNext', 'target' => 'right-part-order'];
        }
        $program[] = ['opcode' => 'Next', 'target' => 'index'];

        return $program;
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function validateOrderBy(array $orderBy): void
    {
        foreach ($orderBy as $term) {
            if (!isset($term['column']) || !is_string($term['column']) || $term['column'] === '') {
                throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next142 ORDER BY terms need columns');
            }
            $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next142 ORDER BY direction must be ASC or DESC');
            }
        }
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function orderSignature(array $orderBy): string
    {
        if ($orderBy === []) {
            return 'rowid ASC';
        }

        return implode(', ', array_map(static fn (array $term): string => $term['column'] . ' ' . strtoupper((string) ($term['direction'] ?? 'ASC')), $orderBy));
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private static function selectedPlanArray(array $base): array
    {
        return is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [];
    }

    /**
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     */
    private static function rowColumnCompare(array $left, array $right, string $column): int
    {
        return self::compareValues(self::rowColumnValue($left, $column), self::rowColumnValue($right, $column));
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowColumnValue(array $row, string $column): mixed
    {
        if ($column === 'rangeKey') {
            return $row['rangeKey'] ?? null;
        }
        if (array_key_exists($column, $row)) {
            return $row[$column];
        }
        $covering = $row['covering'] ?? [];
        if (is_array($covering) && array_key_exists($column, $covering)) {
            return $covering[$column];
        }
        if (array_key_exists('rangeKey', $row)) {
            return $row['rangeKey'];
        }

        return null;
    }

    private static function compareValues(mixed $left, mixed $right): int
    {
        if ($left === null && $right === null) {
            return 0;
        }
        if ($left === null) {
            return -1;
        }
        if ($right === null) {
            return 1;
        }
        if (is_int($left) || is_float($left) || is_int($right) || is_float($right)) {
            return ((float) $left) <=> ((float) $right);
        }

        return strcmp((string) $left, (string) $right);
    }

    private static function keySignature(mixed $value): string
    {
        return get_debug_type($value) . ':' . serialize($value);
    }
}
