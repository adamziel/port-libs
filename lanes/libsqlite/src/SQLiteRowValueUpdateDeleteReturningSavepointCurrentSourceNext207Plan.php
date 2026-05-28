<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext207Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $failStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $outerStatements,
        array $failStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_fail_next207',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next207 needs outer statements');
        }
        if ($failStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next207 needs failing statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next207 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next207 needs unique constraints');
        }
        self::assertIdentifier($savepoint, 'savepoint');

        $initial = self::normalizeTables($tables);
        [$outerCurrent, $outerSummaries, $outerReturning] = self::runStatements(
            $initial,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-fail-savepoint-next207',
            false,
        );

        $savepointImage = $outerCurrent;
        [$failCurrent, $failSummaries, $failReturning] = self::runStatements(
            $savepointImage,
            $failStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'fail-prefix-before-rollback-next207',
            true,
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retrySummaries, $retryReturning] = self::runStatements(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-fail-rollback-next207',
            false,
        );

        return [
            'status' => 'rowvalue-update-delete-returning-or-fail-savepoint-current-source-next207',
            'savepoint' => $savepoint,
            'statement_fail_preserved_prefix_next207' => true,
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'savepoint_released_after_retry' => true,
            'initial_tables' => $initial,
            'outer_current_source_tables' => $outerCurrent,
            'savepoint_image_tables' => $savepointImage,
            'fail_prefix_current_source_tables' => $failCurrent,
            'rollback_to_current_source_tables' => $rollbackCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'outer_statements' => $outerSummaries,
            'fail_statements' => $failSummaries,
            'retry_statements' => $retrySummaries,
            'outer_returning' => $outerReturning,
            'fail_prefix_returning' => $failReturning,
            'suppressed_by_rollback_returning' => $failReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'outer_returning_count' => self::returningCount($outerReturning),
            'fail_prefix_returning_count' => self::returningCount($failReturning),
            'suppressed_by_rollback_count' => self::returningCount($failReturning),
            'yielded_after_retry_count' => self::returningCount($retryReturning),
            'fail_conflict_count' => self::failedConflictCount($failSummaries),
            'changes_preserved_by_fail_before_rollback' => self::changeCount($failSummaries),
            'changes_after_retry' => self::changeCount($retrySummaries),
            'changed_tables_after_retry' => self::changedTables($initial, $retryCurrent),
            'row_counts' => self::rowCounts($retryCurrent),
            'dependency_closure_next207' => 'no new support component needed; reuses native row-value UPDATE/DELETE RETURNING execution, OR FAIL conflict prefix handling, and savepoint current-source images',
            'dependencies' => [
                'sqlite-rowvalue-update-or-fail-returning-prefix-next207',
                'sqlite-rowvalue-savepoint-rollback-discards-or-fail-prefix-next207',
                'wordpress-rowvalue-fail-retry-current-source-next207',
            ],
            'non_overlap_next207' => 'adds OR FAIL prefix-preservation plus ROLLBACK TO suppression for row-value UPDATE/DELETE RETURNING; avoids accepted OR ABORT next200, release next205, parenthesized next202, OR ROLLBACK next178, OR REPLACE/IGNORE conflict, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase, bool $preserveFailChanges): array
    {
        $current = $tables;
        $summaries = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints, $preserveFailChanges);
            $current = $result['tables'];
            $summaries[] = self::statementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $summaries, $yielded];
    }

    /**
     * @param array<string,mixed> $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
    {
        return [
            'phase' => $phase,
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
            'failed_conflict' => $result['failed_conflict'] ?? null,
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
                throw new \InvalidArgumentException('SQLite row-value OR FAIL next207 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR FAIL next207 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value OR FAIL next207 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL next207 rowid column {$rowIdColumn} must be int or string");
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
     * @param list<array<string,mixed>> $summaries
     */
    private static function failedConflictCount(array $summaries): int
    {
        $count = 0;
        foreach ($summaries as $summary) {
            if (($summary['failed_conflict'] ?? null) !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<array<string,mixed>> $summaries
     */
    private static function changeCount(array $summaries): int
    {
        $changes = 0;
        foreach ($summaries as $summary) {
            $changes += count($summary['returning_rows'] ?? []);
            $changes += count($summary['deleted_conflict_rows'] ?? []);
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
        $changed = [];
        foreach ($after as $table => $rows) {
            if (($before[$table] ?? null) !== $rows) {
                $changed[] = $table;
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
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function assertIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value OR FAIL next207 {$label} must be an identifier");
        }
    }
}
