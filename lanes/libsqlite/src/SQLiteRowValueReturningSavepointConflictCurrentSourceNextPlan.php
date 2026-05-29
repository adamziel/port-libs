<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueReturningSavepointConflictCurrentSourceNextPlan
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
        string $savepoint = 'wp_options_conflict_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite row-value RETURNING conflict savepoint needs statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value RETURNING conflict savepoint needs unique constraints');
        }

        $current = self::normalizeTables($tables);
        $savepointImage = $current;
        $attempted = $current;
        $executed = [];
        $yielded = [];
        $attemptedReturning = [];
        $ignored = [];
        $deletedByReplace = [];
        $conflicts = [];
        $rollbackReason = null;
        $rollbackStatement = null;

        foreach ($statements as $ordinal => $sql) {
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $attempted, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $rollbackReason = $exception->getMessage();
                $rollbackStatement = $ordinal;
                break;
            }

            $attempted = $result['tables'];
            $statement = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'table' => $result['table'],
                'selected_ids' => $result['plan']->selectedIds,
                'mutation_ids' => $result['plan']->mutationIds,
                'returning_rows' => $result['returning'],
                'ignored_rows' => $result['ignored_rows'],
                'deleted_conflict_rows' => $result['deleted_conflict_rows'],
                'conflicts' => $result['conflicts'],
            ];
            $executed[] = $statement;
            $attemptedReturning[] = ['ordinal' => $ordinal, 'rows' => $result['returning']];

            foreach ($result['ignored_rows'] as $row) {
                $ignored[] = ['ordinal' => $ordinal, 'row' => $row];
            }
            foreach ($result['deleted_conflict_rows'] as $row) {
                $deletedByReplace[] = ['ordinal' => $ordinal, 'row' => $row];
            }
            foreach ($result['conflicts'] as $conflict) {
                $conflicts[] = ['ordinal' => $ordinal] + $conflict;
            }

            $yielded[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
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
            'ignored_rows' => $ignored,
            'deleted_conflict_rows' => $deletedByReplace,
            'conflicts' => $conflicts,
            'changes' => $rolledBack ? 0 : self::changeCount($executed),
            'attempted_changes' => self::changeCount($executed),
            'dependencies' => [
                'sqlite-update-or-conflict-returning',
                'sqlite-row-value-current-source-update',
                'sqlite-savepoint-current-source-conflict-rollback',
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
                throw new \InvalidArgumentException('SQLite row-value RETURNING conflict tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value RETURNING conflict rows must be arrays');
                }
            }
        }

        return $tables;
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
}
