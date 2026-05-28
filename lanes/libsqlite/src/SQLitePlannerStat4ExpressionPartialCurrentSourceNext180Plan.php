<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext180Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(array $preparedSource, array $currentSource, array $whereTerms, array $neededColumns): array
    {
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext177Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
        );
        $index = self::selectedIndex($currentSource, (string) (($base['selectedPlan']['name'] ?? '')));
        $descending = (bool) ($index['descending'] ?? false);
        $betweenFence = is_array($base['betweenFence'] ?? null) ? $base['betweenFence'] : [];
        $baseReady = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next177-ready'
            || (
                ($base['selectedSource'] ?? null) === 'prepared'
                && ($betweenFence['lowerBoundaryRowids'] ?? []) !== []
                && ($betweenFence['upperBoundaryRowids'] ?? []) !== []
            );
        $matchedRows = self::matchedRows($base);
        $descendingRows = $matchedRows;
        usort($descendingRows, static function (array $left, array $right): int {
            $keyComparison = strcmp((string) ($right['expressionKey'] ?? ''), (string) ($left['expressionKey'] ?? ''));
            if ($keyComparison !== 0) {
                return $keyComparison;
            }

            return ((int) ($left['rowid'] ?? 0)) <=> ((int) ($right['rowid'] ?? 0));
        });

        $ready = $baseReady && $descending;
        $rowids = array_map(static fn (array $row): int => (int) ($row['rowid'] ?? 0), $descendingRows);
        $keys = array_map(static fn (array $row): string => (string) ($row['expressionKey'] ?? ''), $descendingRows);
        $segments = self::descendingSegments($descendingRows);

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next180-ready' : 'requires-current-source-reprepare',
            'matchedRows' => $ready ? $descendingRows : $matchedRows,
            'matchedRowids' => $ready ? $rowids : ($base['matchedRowids'] ?? []),
            'matchedExpressionKeys' => $ready ? $keys : ($base['matchedExpressionKeys'] ?? []),
            'descendingFence' => [
                'descendingIndex' => $descending,
                'ready' => $ready,
                'scanDirection' => $descending ? 'DESC' : 'ASC',
                'seekOpcode' => $descending ? 'SeekLE' : 'SeekGE',
                'stepOpcode' => $descending ? 'Prev' : 'Next',
                'firstKey' => $keys[0] ?? null,
                'lastKey' => $keys === [] ? null : $keys[array_key_last($keys)],
                'rowids' => $rowids,
                'segments' => $segments,
                'descendingSignature' => self::signature([$rowids, $keys, $segments]),
            ],
            'cursorProgram' => self::cursorProgram((array) ($base['cursorProgram'] ?? []), $ready, $rowids),
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'descending' => $descending,
                'next180Ready' => $ready,
                'next180ScanDirection' => $descending ? 'DESC' : 'ASC',
                'next180SeekOpcode' => $descending ? 'SeekLE' : 'SeekGE',
                'next180StepOpcode' => $descending ? 'Prev' : 'Next',
                'next180DescendingRowids' => $ready ? $rowids : [],
                'next180DescendingKeys' => $ready ? $keys : [],
                'next180Segments' => $ready ? $segments : [],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next180DescendingSignature' => self::signature([$rowids, $keys]),
                'next180BaseStatus' => $base['status'] ?? null,
            ]),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT180 DESC '
                . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX')
                . ($ready ? ' REVERSE CURRENT-SOURCE COVERING SCAN' : ' REQUIRES CURRENT SOURCE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext177Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next180',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next180 reuses current-source STAT4 expression partial BETWEEN fences and adds descending covering cursor materialization',
            'non_overlap' => 'avoids accepted next177 inclusive BETWEEN admission, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, and trigger clusters; this slice only handles descending partial expression-index STAT4 current-source scan direction',
        ]);
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function selectedIndex(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next180 needs current-source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next180 indexes must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next180 could not find selected current-source index');
    }

    /**
     * @param array<string,mixed> $base
     * @return list<array<string,mixed>>
     */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next180 needs matched row list');
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{key:string,rowids:list<int>,count:int}>
     */
    private static function descendingSegments(array $rows): array
    {
        $segments = [];
        foreach ($rows as $row) {
            $key = (string) ($row['expressionKey'] ?? '');
            $last = array_key_last($segments);
            if ($last === null || $segments[$last]['key'] !== $key) {
                $segments[] = ['key' => $key, 'rowids' => [], 'count' => 0];
                $last = array_key_last($segments);
            }
            $segments[$last]['rowids'][] = (int) ($row['rowid'] ?? 0);
            $segments[$last]['count']++;
        }

        return $segments;
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param list<int> $rowids
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $rowids): array
    {
        if (!$ready) {
            return $program;
        }
        if (isset($program[1]) && is_array($program[1])) {
            $program[1]['opcode'] = 'SeekLE';
        }
        if (isset($program[2]) && is_array($program[2])) {
            $program[2]['opcode'] = 'IdxGE';
        }
        $program[] = [
            'opcode' => 'Prev',
            'mode' => 'next180-descending-current-source-stat4-expression-partial',
            'rowids' => $rowids,
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
