<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext158Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $predicate
     * @param list<array<string,mixed>> $preparedRows
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @param list<array<string,string>> $neededExpressions
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $predicate,
        array $preparedRows,
        array $currentRows,
        array $orderBy,
        array $neededColumns,
        array $neededExpressions = []
    ): array {
        $base = SQLitePlannerStat4PartialExpressionCurrentSourceNext133Plan::materialize(
            $preparedSource,
            $currentSource,
            $predicate,
            $preparedRows,
            $currentRows,
            $orderBy,
            $neededColumns,
            $neededExpressions,
        );

        $selected = self::arrayValue($base, 'selectedPlan');
        $cursor = self::arrayValue($base, 'cursorTape');
        $matchedRows = self::currentRows($cursor);
        $rangeFence = self::rangeFence($selected);
        $ready = ($base['status'] ?? null) === 'partial-expression-stat4-current-source-next-ready'
            && ($selected['operator'] ?? null) === 'range-bounded'
            && ($selected['partial'] ?? false) === true
            && ($selected['stat4Used'] ?? false) === true
            && ($cursor['deletedRowidsBlocked'] ?? []) !== []
            && $matchedRows !== [];
        $windows = self::rangeWindows($matchedRows, $selected);
        $cursorProgram = self::cursorProgram($cursor, $rangeFence, $ready);

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next158-ready' : 'requires-next-stage',
            'rangeFence' => $rangeFence,
            'rangeWindowRows' => $matchedRows,
            'rangeWindowRowids' => array_map(static fn (array $row): mixed => $row['rowid'] ?? null, $matchedRows),
            'rangeWindowKeys' => array_map(static fn (array $row): mixed => $row['key'] ?? null, $matchedRows),
            'rangeWindowCount' => count($matchedRows),
            'rangeWindows' => $windows,
            'rangeWindowCountByStat4' => count($windows),
            'lowerFenceKey' => $rangeFence['lower']['next']['key'] ?? null,
            'upperFenceKey' => $rangeFence['upper']['current']['key'] ?? null,
            'upperFenceNextKey' => $rangeFence['upper']['next']['key'] ?? null,
            'stalePreparedRowidsBlockedByRangeFence' => array_values($cursor['deletedRowidsBlocked'] ?? []),
            'currentSourceRowidsAdmittedByRangeFence' => array_values($cursor['insertedRowids'] ?? []),
            'currentSourceRowidsRefreshedByRangeFence' => array_values($cursor['updatedRowids'] ?? []),
            'rangeWindowSignature' => hash('sha256', json_encode(array_map(static fn (array $row): array => [
                'rowid' => $row['rowid'] ?? null,
                'key' => $row['key'] ?? null,
                'covering' => $row['covering'] ?? [],
            ], $matchedRows), JSON_THROW_ON_ERROR)),
            'selectedPlan' => array_replace($selected, [
                'next158Ready' => $ready,
                'next158RangeWindowCount' => count($matchedRows),
                'next158RangeWindowRowids' => array_map(static fn (array $row): mixed => $row['rowid'] ?? null, $matchedRows),
                'next158RangeWindowKeys' => array_map(static fn (array $row): mixed => $row['key'] ?? null, $matchedRows),
                'next158RangeFenceExactLower' => (bool) ($rangeFence['lower']['exact'] ?? false),
                'next158RangeFenceExactUpper' => (bool) ($rangeFence['upper']['exact'] ?? false),
                'next158UsesCurrentSourceOnly' => $ready,
            ]),
            'cursorTape' => array_replace($cursor, [
                'next158Program' => $cursorProgram,
                'rangeFenceLower' => $rangeFence['lower']['value'] ?? null,
                'rangeFenceUpper' => $rangeFence['upper']['value'] ?? null,
                'rangeWindowKeys' => array_map(static fn (array $row): mixed => $row['key'] ?? null, $matchedRows),
                'stalePreparedRowidsBlockedByRangeFence' => array_values($cursor['deletedRowidsBlocked'] ?? []),
                'tableLookupElidedForRangeWindow' => $ready,
            ]),
            'currentSourceFence' => array_replace(
                self::arrayValue($base, 'currentSourceFence'),
                [
                    'next158RangeWindowSignature' => hash('sha256', json_encode(array_map(static fn (array $row): mixed => $row['rowid'] ?? null, $matchedRows), JSON_THROW_ON_ERROR)),
                    'next158RangeFenceSignature' => hash('sha256', json_encode($rangeFence, JSON_THROW_ON_ERROR)),
                ],
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT-SOURCE NEXT158 '
                . (string) ($selected['name'] ?? 'NO INDEX')
                . ($ready ? ' RANGE WINDOW CURRENT SOURCE' : ' REQUIRES TABLE SEEK'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4PartialExpressionCurrentSourceNext133Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next158',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next158 composes native STAT4 partial expression current-source range fences with existing covering row streams',
            'non_overlap' => 'avoids accepted next133 row-generation fences, next142 partial-covering ORDER blocks, next144 point replacement, next149 skip-scan expression ranges, expression ORDER BY, JSON table, WAL, VFS, and B-tree clusters; this slice covers stale prepared row exclusion across STAT4 partial expression range windows',
        ]);
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private static function arrayValue(array $base, string $key): array
    {
        $value = $base[$key] ?? null;
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next158 needs array ' . $key);
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $cursor
     * @return list<array<string,mixed>>
     */
    private static function currentRows(array $cursor): array
    {
        $rows = [];
        $pairs = $cursor['currentNextRows'] ?? [];
        if (!is_array($pairs)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next158 cursor rows must be a list');
        }
        foreach ($pairs as $pair) {
            if (!is_array($pair) || !is_array($pair['current'] ?? null)) {
                continue;
            }
            $rows[] = $pair['current'];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $selected
     * @return array<string,mixed>
     */
    private static function rangeFence(array $selected): array
    {
        $fence = $selected['stat4RangeCurrentNext'] ?? null;
        if (!is_array($fence)) {
            return [
                'lower' => ['current' => null, 'next' => null, 'value' => null, 'exact' => false],
                'upper' => ['current' => null, 'next' => null, 'value' => null, 'exact' => false],
                'lowerInclusive' => false,
                'upperInclusive' => false,
                'emptyGap' => true,
            ];
        }

        return $fence;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $selected
     * @return list<array{anchor:mixed,rowids:list<mixed>,firstRowid:mixed,lastRowid:mixed,nextAnchor:mixed}>
     */
    private static function rangeWindows(array $rows, array $selected): array
    {
        $anchors = [];
        foreach (($selected['stat4MatchedCurrentNext'] ?? []) as $pair) {
            if (is_array($pair) && is_array($pair['current'] ?? null) && array_key_exists('key', $pair['current'])) {
                $anchors[] = $pair['current']['key'];
            }
        }

        $windows = [];
        foreach ($anchors as $offset => $anchor) {
            $rowids = [];
            foreach ($rows as $row) {
                if (($row['key'] ?? null) === $anchor) {
                    $rowids[] = $row['rowid'] ?? null;
                }
            }
            $windows[] = [
                'anchor' => $anchor,
                'rowids' => $rowids,
                'firstRowid' => $rowids[0] ?? null,
                'lastRowid' => $rowids === [] ? null : $rowids[count($rowids) - 1],
                'nextAnchor' => $anchors[$offset + 1] ?? null,
            ];
        }

        return $windows;
    }

    /**
     * @param array<string,mixed> $cursor
     * @param array<string,mixed> $rangeFence
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $cursor, array $rangeFence, bool $ready): array
    {
        $program = [
            ['opcode' => 'OpenRead', 'source' => 'partial-expression-index', 'rootPage' => $cursor['rootPage'] ?? null],
            ['opcode' => 'FenceCurrentSource', 'rangeSignature' => hash('sha256', json_encode($rangeFence, JSON_THROW_ON_ERROR))],
            ['opcode' => 'SeekGE', 'source' => 'index', 'key' => $cursor['rangeLower'] ?? null],
            ['opcode' => 'IdxGE', 'source' => 'index', 'key' => $cursor['rangeUpper'] ?? null],
            ['opcode' => 'FilterDeletedRowids', 'rowids' => array_values($cursor['deletedRowidsBlocked'] ?? [])],
            ['opcode' => $ready ? 'ResultRow' : 'DeferredSeek', 'source' => $ready ? 'current-covering-index' : 'table'],
            ['opcode' => 'Next', 'source' => 'index'],
        ];

        return $program;
    }
}
