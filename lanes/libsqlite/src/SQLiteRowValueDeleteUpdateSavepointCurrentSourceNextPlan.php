<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan
{

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeDistinctReturningSavepoint(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_delete_update',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite row-value DELETE/UPDATE savepoint needs statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value DELETE/UPDATE savepoint needs unique constraints');
        }

        $savepointImage = self::normalizeTables($tables);
        $attempted = $savepointImage;
        $executed = [];
        $yielded = [];
        $attemptedReturning = [];
        $deletedRows = [];
        $updatedRows = [];
        $ignoredRows = [];
        $deletedConflictRows = [];
        $conflicts = [];
        $rollbackReason = null;
        $rollbackStatement = null;

        foreach ($statements as $ordinal => $sql) {
            try {
                $before = $attempted;
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $attempted, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $rollbackReason = $exception->getMessage();
                $rollbackStatement = $ordinal;
                break;
            }

            $attempted = $result['tables'];
            $sourceRows = $before[$result['table']] ?? [];
            $selectedRows = self::rowsByIds($sourceRows, $result['plan']->selectedIds, $rowIdColumn);
            $statement = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'table' => $result['table'],
                'selected_ids' => $result['plan']->selectedIds,
                'mutation_ids' => $result['plan']->mutationIds,
                'selected_rows' => $selectedRows,
                'returning_rows' => $result['returning'],
                'ignored_rows' => $result['ignored_rows'],
                'deleted_conflict_rows' => $result['deleted_conflict_rows'],
                'conflicts' => $result['conflicts'],
            ];
            $executed[] = $statement;
            $attemptedReturning[] = ['ordinal' => $ordinal, 'action' => $result['action'], 'rows' => $result['returning']];
            $yielded[] = ['ordinal' => $ordinal, 'action' => $result['action'], 'rows' => $result['returning']];

            if ($result['action'] === 'delete') {
                foreach ($selectedRows as $row) {
                    $deletedRows[] = ['ordinal' => $ordinal, 'row' => $row];
                }
            } else {
                foreach ($result['returning'] as $row) {
                    $updatedRows[] = ['ordinal' => $ordinal, 'row' => $row];
                }
            }
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

        $rolledBack = $rollbackReason !== null;

        return [
            'savepoint' => $savepoint,
            'status' => $rolledBack ? 'rolled-back-to-savepoint' : 'released',
            'rolled_back' => $rolledBack,
            'rollback_reason' => $rollbackReason,
            'rollback_statement_ordinal' => $rollbackStatement,
            'current_source_tables' => $rolledBack ? $savepointImage : $attempted,
            'next_source_tables' => $attempted,
            'savepoint_image_tables' => $savepointImage,
            'executed_statements' => $executed,
            'yielded_returning' => $rolledBack ? array_slice($yielded, 0, max(0, (int) $rollbackStatement)) : $yielded,
            'attempted_returning' => $attemptedReturning,
            'deleted_rows' => $deletedRows,
            'updated_rows' => $updatedRows,
            'ignored_rows' => $ignoredRows,
            'deleted_conflict_rows' => $deletedConflictRows,
            'conflicts' => $conflicts,
            'changes' => $rolledBack ? 0 : self::changeCountWithConflictDeletes($executed),
            'attempted_changes' => self::changeCountWithConflictDeletes($executed),
            'dependencies' => [
                'sqlite-delete-returning-current-source',
                'sqlite-row-value-update-after-delete',
                'sqlite-savepoint-current-source-delete-update-rollback',
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
                throw new \InvalidArgumentException('SQLite row-value DELETE/UPDATE savepoint tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value DELETE/UPDATE savepoint rows must be arrays');
                }
            }
        }

        return $tables;
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
                throw new \InvalidArgumentException("SQLite row-value DELETE/UPDATE rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value DELETE/UPDATE rowid column {$rowIdColumn} must be int or string");
            }
            if (isset($wanted[(string) $id])) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * @param list<array<string,mixed>> $executed
     */
    private static function changeCountWithConflictDeletes(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeBetweenCleanupSavepoint(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_between_cleanup',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite row-value DELETE/UPDATE savepoint needs statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value DELETE/UPDATE savepoint needs unique constraints');
        }

        $savepointImage = self::normalizeTables($tables);
        $attempted = $savepointImage;
        $executed = [];
        $yielded = [];
        $attemptedReturning = [];
        $deletedRows = [];
        $updatedRows = [];
        $rollbackReason = null;
        $rollbackStatement = null;

        foreach ($statements as $ordinal => $sql) {
            try {
                $before = $attempted;
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $attempted, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $rollbackReason = $exception->getMessage();
                $rollbackStatement = $ordinal;
                break;
            }

            $attempted = $result['tables'];
            $selectedRows = self::rowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn);
            $statement = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'table' => $result['table'],
                'selected_ids' => $result['plan']->selectedIds,
                'mutation_ids' => $result['plan']->mutationIds,
                'selected_rows' => $selectedRows,
                'returning_rows' => $result['returning'],
                'current_source_before_ids' => array_column($before[$result['table']] ?? [], $rowIdColumn),
                'next_source_after_ids' => array_column($attempted[$result['table']] ?? [], $rowIdColumn),
            ];
            $executed[] = $statement;
            $stream = ['ordinal' => $ordinal, 'action' => $result['action'], 'rows' => $result['returning']];
            $attemptedReturning[] = $stream;
            $yielded[] = $stream;

            if ($result['action'] === 'delete') {
                foreach ($selectedRows as $row) {
                    $deletedRows[] = ['ordinal' => $ordinal, 'row' => $row];
                }
                continue;
            }
            foreach ($result['returning'] as $row) {
                $updatedRows[] = ['ordinal' => $ordinal, 'row' => $row];
            }
        }

        $rolledBack = $rollbackReason !== null;
        $current = $rolledBack ? $savepointImage : $attempted;

        return [
            'savepoint' => $savepoint,
            'status' => $rolledBack ? 'rolled-back-to-savepoint' : 'released',
            'rolled_back' => $rolledBack,
            'rollback_reason' => $rollbackReason,
            'rollback_statement_ordinal' => $rollbackStatement,
            'current_source_tables' => $current,
            'next_source_tables' => $attempted,
            'savepoint_image_tables' => $savepointImage,
            'executed_statements' => $executed,
            'yielded_returning' => $rolledBack ? array_slice($yielded, 0, max(0, (int) $rollbackStatement)) : $yielded,
            'attempted_returning' => $attemptedReturning,
            'deleted_rows' => $deletedRows,
            'updated_rows' => $updatedRows,
            'changes' => $rolledBack ? 0 : self::changeCountFromMutationIds($executed),
            'attempted_changes' => self::changeCountFromMutationIds($executed),
            'changed_tables' => self::changedTables($savepointImage, $attempted),
            'dependencies' => [
                'sqlite-row-value-between-delete-update-current-source',
                'sqlite-delete-returning-current-source',
                'sqlite-update-row-value-between-after-delete',
                'sqlite-savepoint-current-source-delete-update-rollback',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $executed
     */
    private static function changeCountFromMutationIds(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['mutation_ids'] ?? []);
        }

        return $changes;
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
}
