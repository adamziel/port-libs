<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext156Plan
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
        string $savepoint = 'wp_options_rowvalue_yield_batch',
        string $rowIdColumn = 'option_id',
        ?int $rollbackToAfterOrdinal = null,
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING yield savepoint needs statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING yield savepoint needs unique constraints');
        }
        if ($rollbackToAfterOrdinal !== null && $rollbackToAfterOrdinal < 0) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING rollback ordinal must be non-negative');
        }

        $savepointImage = self::normalizeTables($tables);
        $current = $savepointImage;
        $executed = [];
        $yielded = [];
        $rollbackRequested = false;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $summary = self::statementSummary($ordinal, $sql, $result, $rowIdColumn, $before);
            $executed[] = $summary;
            $yielded[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];

            if ($rollbackToAfterOrdinal === $ordinal) {
                $rollbackRequested = true;
                break;
            }
        }

        $final = $rollbackRequested ? $savepointImage : $current;

        return [
            'savepoint' => $savepoint,
            'status' => $rollbackRequested ? 'rolled-back-to-savepoint' : 'released',
            'rolled_back_to_savepoint' => $rollbackRequested,
            'savepoint_preserved' => $rollbackRequested,
            'savepoint_image_tables' => $savepointImage,
            'pre_rollback_current_source_tables' => $current,
            'current_source_tables' => $final,
            'next_source_tables' => $final,
            'executed_statements' => $rollbackRequested ? [] : $executed,
            'attempted_statements_before_rollback' => $executed,
            'yielded_returning' => $rollbackRequested ? [] : $yielded,
            'attempted_returning_before_rollback' => $yielded,
            'discarded_returning_count' => $rollbackRequested ? self::returningCount($yielded) : 0,
            'changes' => $rollbackRequested ? 0 : self::changeCount($executed),
            'attempted_changes_before_rollback' => self::changeCount($executed),
            'ignored_row_count' => self::countNestedRows($executed, 'ignored_rows'),
            'deleted_conflict_row_count' => self::countNestedRows($executed, 'deleted_conflict_rows'),
            'savepoint_changed_tables' => self::changedTables($savepointImage, $final),
            'row_counts' => self::rowCounts($final),
            'dependencies' => [
                'sqlite-update-or-ignore-rowvalue-returning-yields-successful-rows-only',
                'sqlite-update-or-replace-rowvalue-returning-deletes-conflict-before-yield',
                'sqlite-delete-returning-uses-current-source-after-rowvalue-update',
                'sqlite-rollback-to-savepoint-discards-rowvalue-returning-streams',
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
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING yield savepoint tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING yield savepoint rows must be arrays');
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
    private static function statementSummary(int $ordinal, string $sql, array $result, string $rowIdColumn, array $before): array
    {
        return [
            'ordinal' => $ordinal,
            'sql' => $sql,
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
                throw new \InvalidArgumentException("SQLite row-value UPDATE/DELETE RETURNING yield rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value UPDATE/DELETE RETURNING yield rowid column {$rowIdColumn} must be int or string");
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

    /**
     * @param list<array<string,mixed>> $executed
     */
    private static function changeCount(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param list<array<string,mixed>> $executed
     */
    private static function countNestedRows(array $executed, string $key): int
    {
        $count = 0;
        foreach ($executed as $statement) {
            $rows = $statement[$key] ?? [];
            if (is_array($rows)) {
                $count += count($rows);
            }
        }

        return $count;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTables(array $before, array $after): array
    {
        $names = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
        sort($names);
        $changed = [];
        foreach ($names as $name) {
            if (($before[$name] ?? null) !== ($after[$name] ?? null)) {
                $changed[] = $name;
            }
        }

        return $changed;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,int>
     */
    private static function rowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }
}
