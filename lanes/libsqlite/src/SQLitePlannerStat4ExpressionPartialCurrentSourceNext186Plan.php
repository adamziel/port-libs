<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext186Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $whereTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        if ($limit < 0) {
            throw new \InvalidArgumentException('SQLite next186 LIMIT must be non-negative');
        }
        if ($offset < 0) {
            throw new \InvalidArgumentException('SQLite next186 OFFSET must be non-negative');
        }

        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext183Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
        );
        $rows = self::rowList($base['matchedRows'] ?? []);
        $window = $limit === 0 ? [] : array_slice($rows, $offset, $limit);
        $projected = self::projectRows($window, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next183-ready'
            && count($rows) >= count($window);

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next186-ready' : 'requires-next-stage',
            'limitWindow' => [
                'limit' => $limit,
                'offset' => $offset,
                'inputRowids' => array_column($rows, 'rowid'),
                'rowids' => array_column($window, 'rowid'),
                'keys' => array_column($window, 'expressionKey'),
                'inOrdinals' => array_column($window, 'inOrdinal'),
                'inValues' => array_column($window, 'inValue'),
                'count' => count($window),
                'exhausted' => $offset >= count($rows),
                'signature' => self::signature([$limit, $offset, array_column($window, 'rowid'), array_column($window, 'expressionKey')]),
            ],
            'projectedRows' => $ready ? $projected : [],
            'projectedColumns' => $neededColumns,
            'matchedRows' => $ready ? $window : [],
            'matchedRowids' => $ready ? array_column($window, 'rowid') : [],
            'matchedExpressionKeys' => $ready ? array_column($window, 'expressionKey') : [],
            'inOrderFence' => array_replace(is_array($base['inOrderFence'] ?? null) ? $base['inOrderFence'] : [], [
                'next186LimitSignature' => self::signature([$limit, $offset, array_column($window, 'rowid')]),
                'next186ProjectionSignature' => self::signature([$neededColumns, $projected]),
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $limit,
                $offset,
                array_column($window, 'rowid')
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT186 IN LIMIT WINDOW',
            'dependencies' => [
                'SQLitePlannerStat4ExpressionPartialCurrentSourceNext183Plan',
                'sqlite-sqlplanner-stat4-expression-partial-current-source-next186',
            ],
            'dependency_closure' => 'no new support component needed; next186 reuses lane-local STAT4 partial expression IN-list probes and adds current-source LIMIT/OFFSET covering projection',
            'non_overlap' => 'avoids accepted next183 unwindowed IN-list multi-probe, next182 single range LIMIT projection, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, and trigger clusters; this slice windows and projects the current-source IN-list row stream only',
        ]);
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowList(mixed $rows): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next186 matched rows must be a list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next186 matched rows must be arrays');
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private static function projectRows(array $rows, array $columns): array
    {
        $out = [];
        foreach ($rows as $row) {
            $payload = $row['payload'] ?? null;
            if (!is_array($payload)) {
                throw new \InvalidArgumentException('SQLite next186 matched rows need covering payload arrays');
            }
            $projected = [
                'rowid' => self::rowid($row),
                'expressionKey' => $row['expressionKey'] ?? null,
                'inOrdinal' => $row['inOrdinal'] ?? null,
                'inValue' => $row['inValue'] ?? null,
            ];
            foreach ($columns as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite next186 projected columns must be non-empty strings');
                }
                if (!array_key_exists($column, $payload)) {
                    throw new \InvalidArgumentException('SQLite next186 covering payload missing column ' . $column);
                }
                $projected[$column] = $payload[$column];
            }
            $out[] = $projected;
        }

        return $out;
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        $rowid = $row['rowid'] ?? null;
        if (!is_int($rowid) || $rowid < 0) {
            throw new \InvalidArgumentException('SQLite next186 rowid must be a non-negative integer');
        }

        return $rowid;
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param list<int> $rowids
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, int $limit, int $offset, array $rowids): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'LimitOffsetWindow',
            'mode' => 'next186-current-source-stat4-expression-partial-in-covering',
            'limit' => $limit,
            'offset' => $offset,
            'rowids' => $rowids,
        ];
        $program[] = [
            'opcode' => 'ColumnFromCoveringIndexPayload',
            'mode' => 'next186-current-source-stat4-expression-partial-in-covering',
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
