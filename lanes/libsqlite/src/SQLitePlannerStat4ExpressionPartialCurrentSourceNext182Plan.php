<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext182Plan
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
            throw new \InvalidArgumentException('SQLite next182 LIMIT must be non-negative');
        }
        if ($offset < 0) {
            throw new \InvalidArgumentException('SQLite next182 OFFSET must be non-negative');
        }

        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext180Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
        );
        $rows = self::matchedRows($base);
        $window = $limit === 0 ? [] : array_slice($rows, $offset, $limit);
        $projected = self::projectRows($window, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next180-ready'
            && ($base['tableLookupRequired'] ?? true) === false
            && (
                ($base['temporarySortRequired'] ?? null) === false
                || ($base['orderBySatisfiedByIndex'] ?? false) === true
                || ((is_array($base['selectedPlan'] ?? null) && ($base['selectedPlan']['next180Ready'] ?? false) === true))
            );

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next182-ready' : 'requires-current-source-reprepare',
            'limitWindow' => [
                'limit' => $limit,
                'offset' => $offset,
                'inputRowids' => array_map(static fn (array $row): int => (int) ($row['rowid'] ?? 0), $rows),
                'rowids' => array_map(static fn (array $row): int => (int) ($row['rowid'] ?? 0), $window),
                'keys' => array_map(static fn (array $row): string => (string) ($row['expressionKey'] ?? ''), $window),
                'count' => count($window),
                'exhausted' => $offset >= count($rows),
                'signature' => self::signature([$limit, $offset, array_column($window, 'rowid'), array_column($window, 'expressionKey')]),
            ],
            'projectedRows' => $projected,
            'projectedColumns' => $neededColumns,
            'temporarySortRequired' => !$ready,
            'matchedRows' => $ready ? $window : $rows,
            'matchedRowids' => $ready ? array_column($window, 'rowid') : ($base['matchedRowids'] ?? []),
            'matchedExpressionKeys' => $ready ? array_column($window, 'expressionKey') : ($base['matchedExpressionKeys'] ?? []),
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next182Ready' => $ready,
                'next182Limit' => $limit,
                'next182Offset' => $offset,
                'next182ProjectedColumns' => $neededColumns,
                'next182WindowRowids' => array_column($window, 'rowid'),
                'next182WindowKeys' => array_column($window, 'expressionKey'),
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next182LimitSignature' => self::signature([$limit, $offset, array_column($window, 'rowid')]),
                'next182ProjectionSignature' => self::signature([$neededColumns, $projected]),
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $limit,
                $offset,
                array_column($window, 'rowid')
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT182 LIMIT WINDOW '
                . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX')
                . ($ready ? ' COVERING PAYLOAD' : ' REQUIRES CURRENT SOURCE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext180Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next182',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next182 reuses current-source STAT4 expression partial descending scan fences and adds covering LIMIT/OFFSET payload materialization',
            'non_overlap' => 'avoids accepted next180 descending partial expression-index scan direction, next177 BETWEEN admission, expression ORDER BY text execution, range-cost ranking, JSON, WAL, VFS, B-tree, and trigger clusters; this slice only windows and projects the current-source STAT4 partial expression covering row stream',
        ]);
    }

    /**
     * @param array<string,mixed> $base
     * @return list<array<string,mixed>>
     */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next182 needs matched row list');
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
                throw new \InvalidArgumentException('SQLite next182 matched rows need payload arrays');
            }
            $projected = ['rowid' => (int) ($row['rowid'] ?? 0), 'expressionKey' => $row['expressionKey'] ?? null];
            foreach ($columns as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite next182 projected columns must be non-empty strings');
                }
                if (!array_key_exists($column, $payload)) {
                    throw new \InvalidArgumentException('SQLite next182 covering payload missing column ' . $column);
                }
                $projected[$column] = $payload[$column];
            }
            $out[] = $projected;
        }

        return $out;
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
            'mode' => 'next182-current-source-stat4-expression-partial-covering',
            'limit' => $limit,
            'offset' => $offset,
            'rowids' => $rowids,
        ];
        $program[] = [
            'opcode' => 'ColumnFromCoveringIndexPayload',
            'mode' => 'next182-current-source-stat4-expression-partial-covering',
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
