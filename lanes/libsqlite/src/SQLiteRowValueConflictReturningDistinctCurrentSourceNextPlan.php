<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueConflictReturningDistinctCurrentSourceNextPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        string $rowIdColumn = 'option_id',
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite row-value conflict DISTINCT RETURNING needs statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value conflict DISTINCT RETURNING needs unique constraints');
        }

        $current = self::normalizeTables($tables);
        $executed = [];
        $yielded = [];
        $ignoredRows = [];
        $deletedConflictRows = [];
        $conflicts = [];
        $failed = null;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $failed = [
                    'ordinal' => $ordinal,
                    'sql' => $sql,
                    'reason' => $exception->getMessage(),
                    'statement_source_tables' => $before,
                    'current_source_tables' => $current,
                ];
                break;
            }

            $current = $result['tables'];
            $summary = self::statementSummary($ordinal, $result, $before, $rowIdColumn);
            $executed[] = $summary;
            $yielded[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
            foreach ($result['ignored_rows'] as $row) {
                $ignoredRows[] = ['ordinal' => $ordinal, 'row' => $row];
            }
            foreach ($result['deleted_conflict_rows'] as $row) {
                $deletedConflictRows[] = ['ordinal' => $ordinal, 'row' => $row];
            }
            foreach ($result['conflicts'] as $conflict) {
                $conflicts[] = ['ordinal' => $ordinal] + $conflict;
            }
        }

        return [
            'status' => $failed === null ? 'completed-current-source' : 'stopped-after-conflict',
            'failed_statement' => $failed,
            'executed_statements' => $executed,
            'yielded_returning' => $yielded,
            'ignored_rows' => $ignoredRows,
            'deleted_conflict_rows' => $deletedConflictRows,
            'conflicts' => $conflicts,
            'initial_source_tables' => $tables,
            'current_source_tables' => $current,
            'returning_count' => self::returningCount($yielded),
            'ignored_count' => count($ignoredRows),
            'deleted_conflict_count' => count($deletedConflictRows),
            'conflict_count' => count($conflicts) + ($failed === null ? 0 : 1),
            'dependencies' => [
                'sqlite-row-value-is-distinct-from-update-delete',
                'sqlite-update-returning-conflict-policy',
                'sqlite-returning-current-source-after-conflict',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value conflict DISTINCT RETURNING tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value conflict DISTINCT RETURNING rows must be arrays');
                }
            }
        }

        return $tables;
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummary(int $ordinal, array $result, array $before, string $rowIdColumn): array
    {
        return [
            'ordinal' => $ordinal,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int|string> $ids
     * @return list<array<string,mixed>>
     */
    private static function rowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value conflict DISTINCT RETURNING rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value conflict DISTINCT RETURNING rowid column {$rowIdColumn} must be int or string");
            }
            if (isset($wanted[(string) $id])) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * @param list<array{rows:list<array<string,mixed>>}> $yielded
     */
    private static function returningCount(array $yielded): int
    {
        $count = 0;
        foreach ($yielded as $stream) {
            $count += count($stream['rows']);
        }

        return $count;
    }
}
