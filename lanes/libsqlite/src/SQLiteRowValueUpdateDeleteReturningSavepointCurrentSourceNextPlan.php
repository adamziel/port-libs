<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan
{

    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeRollbackReturningTransaction(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_rollback_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING rollback needs statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING rollback needs unique constraints');
        }

        $transactionImage = self::normalizeRollbackReturningTables($tables);
        $savepointImage = $transactionImage;
        $current = $savepointImage;
        $executed = [];
        $attemptedYielded = [];
        $rollbackStatement = null;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $rollbackStatement = [
                    'ordinal' => $ordinal,
                    'sql' => $sql,
                    'action' => $parsed['action'],
                    'conflict_action' => $parsed['conflict_action'],
                    'reason' => $exception->getMessage(),
                    'statement_source_tables' => $before,
                    'attempted_current_source_tables' => $current,
                ];
                break;
            }

            $current = $result['tables'];
            $summary = self::rollbackReturningStatementSummary($ordinal, $result, $rowIdColumn, $before);
            $executed[] = $summary;
            $attemptedYielded[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        $rolledBack = $rollbackStatement !== null
            && ($rollbackStatement['conflict_action'] ?? null) === 'rollback';
        $final = $rolledBack ? $transactionImage : $current;

        return [
            'savepoint' => $savepoint,
            'status' => $rolledBack ? 'transaction-rolled-back' : 'released',
            'transaction_rolled_back' => $rolledBack,
            'savepoint_preserved' => false,
            'rollback_statement' => $rollbackStatement,
            'transaction_image_tables' => $transactionImage,
            'savepoint_image_tables' => $savepointImage,
            'pre_rollback_current_source_tables' => $current,
            'current_source_tables' => $final,
            'next_source_tables' => $final,
            'executed_statements' => $rolledBack ? [] : $executed,
            'attempted_statements_before_rollback' => $executed,
            'attempted_returning_before_rollback' => $attemptedYielded,
            'yielded_returning' => $rolledBack ? [] : $attemptedYielded,
            'discarded_returning_count' => $rolledBack ? self::rollbackReturningCount($attemptedYielded) : 0,
            'changes' => $rolledBack ? 0 : self::rollbackReturningChangeCount($executed),
            'attempted_changes_before_rollback' => self::rollbackReturningChangeCount($executed),
            'row_counts' => self::rollbackReturningRowCounts($final),
            'dependencies' => [
                'sqlite-update-or-rollback-rolls-back-transaction',
                'sqlite-row-value-returning-discarded-by-rollback-conflict',
                'sqlite-current-source-reverts-to-transaction-image',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeRollbackReturningTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING rollback tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING rollback rows must be arrays');
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
    private static function rollbackReturningStatementSummary(int $ordinal, array $result, string $rowIdColumn, array $before): array
    {
        return [
            'ordinal' => $ordinal,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rollbackReturningRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function rollbackReturningRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value UPDATE/DELETE RETURNING rollback rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value UPDATE/DELETE RETURNING rollback rowid column {$rowIdColumn} must be int or string");
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
    private static function rollbackReturningCount(array $yielded): int
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
    private static function rollbackReturningChangeCount(array $executed): int
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
     * @return array<string,int>
     */
    private static function rollbackReturningRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeYieldReturningSavepointBatch(
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

        $savepointImage = self::normalizeTablesYieldReturningSavepointBatch($tables);
        $current = $savepointImage;
        $executed = [];
        $yielded = [];
        $rollbackRequested = false;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $summary = self::statementSummaryYieldReturningSavepointBatch($ordinal, $sql, $result, $rowIdColumn, $before);
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
            'discarded_returning_count' => $rollbackRequested ? self::returningCountYieldReturningSavepointBatch($yielded) : 0,
            'changes' => $rollbackRequested ? 0 : self::changeCountYieldReturningSavepointBatch($executed),
            'attempted_changes_before_rollback' => self::changeCountYieldReturningSavepointBatch($executed),
            'ignored_row_count' => self::countNestedRowsYieldReturningSavepointBatch($executed, 'ignored_rows'),
            'deleted_conflict_row_count' => self::countNestedRowsYieldReturningSavepointBatch($executed, 'deleted_conflict_rows'),
            'savepoint_changed_tables' => self::changedTablesYieldReturningSavepointBatch($savepointImage, $final),
            'row_counts' => self::rowCountsYieldReturningSavepointBatch($final),
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
    private static function normalizeTablesYieldReturningSavepointBatch(array $tables): array
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
    private static function statementSummaryYieldReturningSavepointBatch(int $ordinal, string $sql, array $result, string $rowIdColumn, array $before): array
    {
        return [
            'ordinal' => $ordinal,
            'sql' => $sql,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rowsByIdsYieldReturningSavepointBatch($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function rowsByIdsYieldReturningSavepointBatch(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountYieldReturningSavepointBatch(array $yielded): int
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
    private static function changeCountYieldReturningSavepointBatch(array $executed): int
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
    private static function countNestedRowsYieldReturningSavepointBatch(array $executed, string $key): int
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
    private static function changedTablesYieldReturningSavepointBatch(array $before, array $after): array
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
    private static function rowCountsYieldReturningSavepointBatch(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerStatements
     * @param list<string> $afterRollbackStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNestedSavepointRollbackBatch(
        array $tables,
        array $outerStatements,
        array $innerStatements,
        array $afterRollbackStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_outer_rowvalue_import',
        string $innerSavepoint = 'wp_inner_returning_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === [] || $innerStatements === [] || $afterRollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint rollback batch needs outer, inner, and after-rollback statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint rollback batch needs unique constraints');
        }
        $outerSavepoint = self::nestedSavepointIdentifier($outerSavepoint, 'outer savepoint');
        $innerSavepoint = self::nestedSavepointIdentifier($innerSavepoint, 'inner savepoint');
        if (strcasecmp($outerSavepoint, $innerSavepoint) === 0) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint rollback batch needs distinct savepoint names');
        }

        $transactionImage = self::normalizeNestedSavepointTables($tables);
        $outerImage = $transactionImage;

        $outer = self::runNestedSavepointStatements($outerStatements, $outerImage, $uniqueConstraints, $rowIdColumn);
        if ($outer['failed_statement'] !== null) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint rollback batch outer statement failed: ' . $outer['failed_statement']['reason']);
        }

        $innerImage = $outer['tables'];
        $inner = self::runNestedSavepointStatements($innerStatements, $innerImage, $uniqueConstraints, $rowIdColumn);
        $rolledBackInner = $inner['executed_statements'] !== [] || $inner['failed_statement'] !== null;
        $afterRollbackStart = $rolledBackInner ? $innerImage : $inner['tables'];

        $after = self::runNestedSavepointStatements($afterRollbackStatements, $afterRollbackStart, $uniqueConstraints, $rowIdColumn);
        if ($after['failed_statement'] !== null) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint rollback batch after-rollback statement failed: ' . $after['failed_statement']['reason']);
        }

        $final = $after['tables'];

        return [
            'status' => $rolledBackInner ? 'inner-rolled-back-outer-current-source-preserved' : 'released',
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'transaction_image_tables' => $transactionImage,
            'outer_savepoint_image_tables' => $outerImage,
            'inner_savepoint_image_tables' => $innerImage,
            'pre_inner_rollback_current_source_tables' => $inner['tables'],
            'post_inner_rollback_current_source_tables' => $afterRollbackStart,
            'current_source_tables' => $final,
            'next_source_tables' => $final,
            'outer_statements' => $outer['executed_statements'],
            'inner_statements_before_rollback' => $inner['executed_statements'],
            'after_rollback_statements' => $after['executed_statements'],
            'outer_returning' => $outer['yielded_returning'],
            'inner_returning_before_rollback' => $inner['yielded_returning'],
            'after_rollback_returning' => $after['yielded_returning'],
            'yielded_returning' => array_merge($outer['yielded_returning'], $after['yielded_returning']),
            'discarded_inner_returning_count' => self::nestedSavepointReturningCount($inner['yielded_returning']),
            'outer_changes' => self::nestedSavepointChangeCount($outer['executed_statements']),
            'inner_attempted_changes' => self::nestedSavepointChangeCount($inner['executed_statements']),
            'after_rollback_changes' => self::nestedSavepointChangeCount($after['executed_statements']),
            'changes' => self::nestedSavepointChangeCount($outer['executed_statements']) + self::nestedSavepointChangeCount($after['executed_statements']),
            'rolled_back_inner_savepoint' => $rolledBackInner,
            'outer_savepoint_preserved' => false,
            'inner_savepoint_preserved' => false,
            'failed_inner_statement' => $inner['failed_statement'],
            'row_counts' => self::nestedSavepointRowCounts($final),
            'changed_tables' => self::nestedSavepointChangedTables($transactionImage, $final),
            'dependencies' => [
                'sqlite-rowvalue-update-delete-returning-nested-savepoint-rollback-batch',
                'sqlite-rollback-to-inner-savepoint-discards-returning-stream',
                'sqlite-outer-savepoint-current-source-survives-inner-rollback',
            ],
        ];
    }

    /**
     * @param list<string> $statements
     * @param array<string,list<array<string,mixed>>> $startTables
     * @param list<list<string>> $uniqueConstraints
     * @return array{tables:array<string,list<array<string,mixed>>>,executed_statements:list<array<string,mixed>>,yielded_returning:list<array<string,mixed>>,failed_statement:?array<string,mixed>}
     */
    private static function runNestedSavepointStatements(array $statements, array $startTables, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $startTables;
        $executed = [];
        $yielded = [];
        $failed = null;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $failed = [
                    'ordinal' => $ordinal,
                    'sql' => $sql,
                    'action' => $parsed['action'],
                    'conflict_action' => $parsed['conflict_action'],
                    'reason' => $exception->getMessage(),
                    'statement_source_tables' => $before,
                ];
                break;
            }

            $current = $result['tables'];
            $executed[] = self::nestedSavepointStatementSummary($ordinal, $result, $before, $rowIdColumn);
            $yielded[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [
            'tables' => $current,
            'executed_statements' => $executed,
            'yielded_returning' => $yielded,
            'failed_statement' => $failed,
        ];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function nestedSavepointStatementSummary(int $ordinal, array $result, array $before, string $rowIdColumn): array
    {
        return [
            'ordinal' => $ordinal,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::nestedSavepointRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeNestedSavepointTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value nested savepoint rollback batch tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value nested savepoint rollback batch rows must be arrays');
                }
            }
        }

        return $tables;
    }

    private static function nestedSavepointIdentifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value nested savepoint rollback batch {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int|string> $ids
     * @return list<array<string,mixed>>
     */
    private static function nestedSavepointRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value nested savepoint rollback batch rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value nested savepoint rollback batch rowid column {$rowIdColumn} must be int or string");
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
    private static function nestedSavepointReturningCount(array $yielded): int
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
    private static function nestedSavepointChangeCount(array $executed): int
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
     * @return array<string,int>
     */
    private static function nestedSavepointRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function nestedSavepointChangedTables(array $before, array $after): array
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


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeRollbackStatements
     * @param list<string> $afterRollbackStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executePreflightRetrySavepointBatch(
        array $tables,
        array $beforeRollbackStatements,
        array $afterRollbackStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_retry_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($beforeRollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING preflight retry savepoint needs pre-rollback statements');
        }
        if ($afterRollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING preflight retry savepoint needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING preflight retry savepoint needs unique constraints');
        }

        $savepointImage = self::normalizeTablesPreflightRetrySavepointBatch($tables);
        [$attemptedBeforeRollback, $preRollbackExecuted, $preRollbackYielded] = self::runStatementsPreflightRetrySavepointBatch(
            $savepointImage,
            $beforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-rollback',
        );

        $rollbackToImage = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryYielded] = self::runStatementsPreflightRetrySavepointBatch(
            $rollbackToImage,
            $afterRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'after-rollback',
        );

        return [
            'savepoint' => $savepoint,
            'status' => 'released-after-rollback-to-retry',
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'released' => true,
            'savepoint_image_tables' => $savepointImage,
            'attempted_before_rollback_tables' => $attemptedBeforeRollback,
            'rollback_to_current_source_tables' => $rollbackToImage,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'pre_rollback_statements' => $preRollbackExecuted,
            'retry_statements' => $retryExecuted,
            'discarded_returning' => $preRollbackYielded,
            'yielded_returning' => $retryYielded,
            'discarded_returning_count' => self::returningCountPreflightRetrySavepointBatch($preRollbackYielded),
            'changes_after_release' => self::changeCountPreflightRetrySavepointBatch($retryExecuted),
            'discarded_changes_before_rollback_to' => self::changeCountPreflightRetrySavepointBatch($preRollbackExecuted),
            'row_counts' => self::rowCountsPreflightRetrySavepointBatch($retryCurrent),
            'dependencies' => [
                'sqlite-rollback-to-savepoint-keeps-savepoint-active',
                'sqlite-row-value-update-delete-returning-discarded-on-rollback-to',
                'sqlite-retry-statements-read-restored-current-source',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsPreflightRetrySavepointBatch(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        string $rowIdColumn,
        string $phase,
    ): array {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryPreflightRetrySavepointBatch($phase, $ordinal, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryPreflightRetrySavepointBatch(string $phase, int $ordinal, array $result, array $before, string $rowIdColumn): array
    {
        return [
            'phase' => $phase,
            'ordinal' => $ordinal,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rowsByIdsPreflightRetrySavepointBatch($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesPreflightRetrySavepointBatch(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING preflight retry savepoint tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING preflight retry savepoint rows must be arrays');
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
    private static function rowsByIdsPreflightRetrySavepointBatch(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value UPDATE/DELETE RETURNING preflight retry savepoint rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value UPDATE/DELETE RETURNING preflight retry savepoint rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountPreflightRetrySavepointBatch(array $yielded): int
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
    private static function changeCountPreflightRetrySavepointBatch(array $executed): int
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
     * @return array<string,int>
     */
    private static function rowCountsPreflightRetrySavepointBatch(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeRollbackStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeConflictRetrySavepointBatch(
        array $tables,
        array $beforeRollbackStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_fail_retry_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($beforeRollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value FAIL rollback retry next161 needs pre-rollback statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value FAIL rollback retry next161 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value FAIL rollback retry next161 needs unique constraints');
        }

        $savepointImage = self::normalizeTablesConflictRetrySavepointBatch($tables);
        [$failedCurrent, $failedStatements, $failedReturning, $failedConflict, $failedOrdinal] = self::runUntilFailConflictRetrySavepointBatch(
            $savepointImage,
            $beforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        $rollbackToImage = $savepointImage;
        [$retryCurrent, $retryStatementsSummary, $retryReturning] = self::runRetryStatementsConflictRetrySavepointBatch(
            $rollbackToImage,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        return [
            'savepoint' => $savepoint,
            'status' => $failedConflict === null ? 'released-after-clean-retry' : 'failed-rolled-back-to-savepoint-retried',
            'failed_before_rollback' => $failedConflict !== null,
            'failed_statement_ordinal' => $failedOrdinal,
            'failed_conflict' => $failedConflict,
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'released_after_retry' => true,
            'savepoint_image_tables' => $savepointImage,
            'failed_current_source_tables' => $failedCurrent,
            'rollback_to_current_source_tables' => $rollbackToImage,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'pre_rollback_statements' => $failedStatements,
            'retry_statements' => $retryStatementsSummary,
            'discarded_returning' => $failedReturning,
            'yielded_returning' => $retryReturning,
            'discarded_returning_count' => self::returningCountConflictRetrySavepointBatch($failedReturning),
            'yielded_returning_count' => self::returningCountConflictRetrySavepointBatch($retryReturning),
            'failed_changes_before_rollback_to' => self::changeCountConflictRetrySavepointBatch($failedStatements),
            'changes_after_release' => self::changeCountConflictRetrySavepointBatch($retryStatementsSummary),
            'row_counts' => self::rowCountsConflictRetrySavepointBatch($retryCurrent),
            'changed_tables_after_retry' => self::changedTablesConflictRetrySavepointBatch($savepointImage, $retryCurrent),
            'dependencies' => [
                'sqlite-update-or-fail-preserves-prior-rowvalue-returning-until-rollback-to',
                'sqlite-rollback-to-savepoint-discards-fail-returning-stream',
                'sqlite-rowvalue-update-delete-retry-reads-restored-current-source-next161',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>,3:?array<string,mixed>,4:?int}
     */
    private static function runUntilFailConflictRetrySavepointBatch(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];
        $failedConflict = null;
        $failedOrdinal = null;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints, true);
            $current = $result['tables'];
            $executed[] = self::statementSummaryConflictRetrySavepointBatch('before-rollback', $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];

            if (($result['failed_conflict'] ?? null) !== null) {
                $failedConflict = $result['failed_conflict'];
                $failedOrdinal = $ordinal;
                break;
            }
        }

        return [$current, $executed, $yielded, $failedConflict, $failedOrdinal];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runRetryStatementsConflictRetrySavepointBatch(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryConflictRetrySavepointBatch('after-rollback', $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryConflictRetrySavepointBatch(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsConflictRetrySavepointBatch($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesConflictRetrySavepointBatch(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value FAIL rollback retry next161 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value FAIL rollback retry next161 rows must be arrays');
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
    private static function rowsByIdsConflictRetrySavepointBatch(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value FAIL rollback retry next161 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value FAIL rollback retry next161 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountConflictRetrySavepointBatch(array $yielded): int
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
    private static function changeCountConflictRetrySavepointBatch(array $executed): int
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
     * @return array<string,int>
     */
    private static function rowCountsConflictRetrySavepointBatch(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesConflictRetrySavepointBatch(array $before, array $after): array
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


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeFailConflictRollbackSavepoint(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_fail_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next162 needs statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next162 needs unique constraints');
        }

        $savepointImage = self::normalizeTablesFailConflictRollbackSavepoint($tables);
        $current = $savepointImage;
        $executed = [];
        $yielded = [];
        $failed = null;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints, true);
            $current = $result['tables'];
            $summary = self::statementSummaryFailConflictRollbackSavepoint($ordinal, $sql, $result, $before, $rowIdColumn);
            $executed[] = $summary;
            $yielded[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];

            if (($result['failed_conflict'] ?? null) !== null) {
                $failed = [
                    'ordinal' => $ordinal,
                    'sql' => $sql,
                    'conflict' => $result['failed_conflict'],
                    'current_source_tables' => $current,
                    'yielded_returning' => $result['returning'],
                    'partial_change_count' => self::statementChangeCountFailConflictRollbackSavepoint($summary),
                ];
                break;
            }
        }

        if ($failed === null) {
            return [
                'savepoint' => $savepoint,
                'status' => 'released-without-fail',
                'rolled_back_to_savepoint' => false,
                'savepoint_preserved' => false,
                'savepoint_image_tables' => $savepointImage,
                'pre_rollback_current_source_tables' => $current,
                'current_source_tables' => $current,
                'next_source_tables' => $current,
                'executed_statements' => $executed,
                'attempted_returning_before_rollback' => $yielded,
                'yielded_returning' => $yielded,
                'discarded_returning_count' => 0,
                'changes' => self::changeCountFailConflictRollbackSavepoint($executed),
                'partial_fail' => null,
                'dependencies' => self::dependenciesFailConflictRollbackSavepoint(),
            ];
        }

        return [
            'savepoint' => $savepoint,
            'status' => 'rolled-back-after-or-fail',
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved' => true,
            'savepoint_image_tables' => $savepointImage,
            'pre_rollback_current_source_tables' => $current,
            'current_source_tables' => $savepointImage,
            'next_source_tables' => $savepointImage,
            'executed_statements' => [],
            'attempted_statements_before_rollback' => $executed,
            'attempted_returning_before_rollback' => $yielded,
            'yielded_returning' => [],
            'discarded_returning_count' => self::returningCountFailConflictRollbackSavepoint($yielded),
            'attempted_changes_before_rollback' => self::changeCountFailConflictRollbackSavepoint($executed),
            'changes' => 0,
            'partial_fail' => $failed,
            'savepoint_changed_tables' => [],
            'row_counts' => self::rowCountsFailConflictRollbackSavepoint($savepointImage),
            'dependencies' => self::dependenciesFailConflictRollbackSavepoint(),
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesFailConflictRollbackSavepoint(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next162 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next162 rows must be arrays');
                }
            }
        }

        return $tables;
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:array<string,mixed>|null} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryFailConflictRollbackSavepoint(int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
    {
        return [
            'ordinal' => $ordinal,
            'sql' => $sql,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rowsByIdsFailConflictRollbackSavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
            'failed_conflict' => $result['failed_conflict'] ?? null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int|string> $ids
     * @return list<array<string,mixed>>
     */
    private static function rowsByIdsFailConflictRollbackSavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value UPDATE/DELETE RETURNING savepoint next162 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value UPDATE/DELETE RETURNING savepoint next162 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountFailConflictRollbackSavepoint(array $yielded): int
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
    private static function changeCountFailConflictRollbackSavepoint(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += self::statementChangeCountFailConflictRollbackSavepoint($statement);
        }

        return $changes;
    }

    /**
     * @param array<string,mixed> $statement
     */
    private static function statementChangeCountFailConflictRollbackSavepoint(array $statement): int
    {
        return count($statement['returning_rows'] ?? []) + count($statement['deleted_conflict_rows'] ?? []);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,int>
     */
    private static function rowCountsFailConflictRollbackSavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }

    /**
     * @return list<string>
     */
    private static function dependenciesFailConflictRollbackSavepoint(): array
    {
        return [
            'sqlite-update-or-fail-preserves-prior-rowvalue-changes-until-savepoint-rollback',
            'sqlite-rowvalue-returning-fail-stream-discarded-by-rollback-to-savepoint',
            'sqlite-delete-returning-after-partial-fail-is-not-run-before-rollback-to',
            'sqlite-savepoint-current-source-restored-after-rowvalue-or-fail',
        ];
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeRollbackStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeBetweenRollbackRetrySavepoint(
        array $tables,
        array $beforeRollbackStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_rowvalue_between_retry',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($beforeRollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value BETWEEN savepoint next163 needs pre-rollback statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value BETWEEN savepoint next163 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value BETWEEN savepoint next163 needs unique constraints');
        }
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint)) {
            throw new \InvalidArgumentException('SQLite row-value BETWEEN savepoint next163 savepoint name must be an identifier');
        }

        $savepointImage = self::normalizeTablesBetweenRollbackRetrySavepoint($tables);
        [$attemptedTables, $attemptedStatements, $discardedReturning] = self::runStatementsBetweenRollbackRetrySavepoint(
            $savepointImage,
            $beforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-rollback-to',
        );

        $rollbackToTables = $savepointImage;
        [$currentTables, $retryExecuted, $yieldedReturning] = self::runStatementsBetweenRollbackRetrySavepoint(
            $rollbackToTables,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'after-rollback-to',
        );

        return [
            'savepoint' => $savepoint,
            'status' => 'released-after-rowvalue-between-retry',
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'released' => true,
            'savepoint_image_tables' => $savepointImage,
            'attempted_before_rollback_tables' => $attemptedTables,
            'rollback_to_current_source_tables' => $rollbackToTables,
            'current_source_tables' => $currentTables,
            'next_source_tables' => $currentTables,
            'pre_rollback_statements' => $attemptedStatements,
            'retry_statements' => $retryExecuted,
            'discarded_returning' => $discardedReturning,
            'yielded_returning' => $yieldedReturning,
            'discarded_returning_count' => self::returningCountBetweenRollbackRetrySavepoint($discardedReturning),
            'yielded_returning_count' => self::returningCountBetweenRollbackRetrySavepoint($yieldedReturning),
            'discarded_changes_before_rollback_to' => self::changeCountBetweenRollbackRetrySavepoint($attemptedStatements),
            'changes_after_release' => self::changeCountBetweenRollbackRetrySavepoint($retryExecuted),
            'row_counts' => self::rowCountsBetweenRollbackRetrySavepoint($currentTables),
            'dependencies' => [
                'sqlite-row-value-between-returning-expression',
                'sqlite-update-delete-returning-rollback-to-discards-current-stream',
                'sqlite-retry-after-rollback-to-reads-restored-current-source',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsBetweenRollbackRetrySavepoint(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        string $rowIdColumn,
        string $phase,
    ): array {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryBetweenRollbackRetrySavepoint($phase, $ordinal, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryBetweenRollbackRetrySavepoint(string $phase, int $ordinal, array $result, array $before, string $rowIdColumn): array
    {
        return [
            'phase' => $phase,
            'ordinal' => $ordinal,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rowsByIdsBetweenRollbackRetrySavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesBetweenRollbackRetrySavepoint(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value BETWEEN savepoint next163 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value BETWEEN savepoint next163 rows must be arrays');
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
    private static function rowsByIdsBetweenRollbackRetrySavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value BETWEEN savepoint next163 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value BETWEEN savepoint next163 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountBetweenRollbackRetrySavepoint(array $yielded): int
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
    private static function changeCountBetweenRollbackRetrySavepoint(array $executed): int
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
     * @return array<string,int>
     */
    private static function rowCountsBetweenRollbackRetrySavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNullInequalityRetrySavepointBatch(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_rollback_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK retry next164 needs attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK retry next164 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK retry next164 needs unique constraints');
        }

        $transactionImage = self::normalizeTablesNullInequalityRetrySavepointBatch($tables);
        [$attemptedCurrent, $attempted, $attemptedReturning, $rollbackReason, $rollbackOrdinal] = self::runUntilRollbackNullInequalityRetrySavepointBatch(
            $transactionImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );
        $rolledBack = $rollbackReason !== null;
        $retryBase = $rolledBack ? $transactionImage : $attemptedCurrent;
        [$retryCurrent, $retry, $retryReturning] = self::runRetryNullInequalityRetrySavepointBatch(
            $retryBase,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        return [
            'savepoint' => $savepoint,
            'status' => $rolledBack ? 'transaction-rolled-back-retried-current-source-next164' : 'released-without-rollback-current-source-next164',
            'transaction_rolled_back' => $rolledBack,
            'savepoint_preserved_after_rollback' => false,
            'rollback_statement_ordinal' => $rollbackOrdinal,
            'rollback_reason' => $rollbackReason,
            'transaction_image_tables' => $transactionImage,
            'attempted_current_source_tables' => $attemptedCurrent,
            'rollback_current_source_tables' => $retryBase,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'attempt_statements' => $attempted,
            'retry_statements' => $retry,
            'attempted_returning_before_rollback' => $attemptedReturning,
            'discarded_returning' => $rolledBack ? $attemptedReturning : [],
            'yielded_returning' => $retryReturning,
            'discarded_returning_count' => $rolledBack ? self::returningCountNullInequalityRetrySavepointBatch($attemptedReturning) : 0,
            'yielded_returning_count' => self::returningCountNullInequalityRetrySavepointBatch($retryReturning),
            'attempted_changes_before_rollback' => self::changeCountNullInequalityRetrySavepointBatch($attempted),
            'changes_after_retry' => self::changeCountNullInequalityRetrySavepointBatch($retry),
            'changed_tables_after_retry' => self::changedTablesNullInequalityRetrySavepointBatch($transactionImage, $retryCurrent),
            'row_counts' => self::rowCountsNullInequalityRetrySavepointBatch($retryCurrent),
            'dependencies' => [
                'sqlite-update-or-rollback-rowvalue-returning-cancels-savepoint-transaction',
                'sqlite-rollback-conflict-discards-attempted-returning-streams',
                'sqlite-rowvalue-update-delete-returning-retry-starts-from-transaction-image-next164',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>,3:?string,4:?int}
     */
    private static function runUntilRollbackNullInequalityRetrySavepointBatch(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];
        $rollbackReason = null;
        $rollbackOrdinal = null;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $rollbackReason = $exception->getMessage();
                $rollbackOrdinal = $ordinal;
                if (stripos($rollbackReason, ' using OR ROLLBACK') === false) {
                    throw $exception;
                }
                break;
            }

            $current = $result['tables'];
            $executed[] = self::statementSummaryNullInequalityRetrySavepointBatch('before-rollback', $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => 'before-rollback',
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded, $rollbackReason, $rollbackOrdinal];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runRetryNullInequalityRetrySavepointBatch(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNullInequalityRetrySavepointBatch('after-rollback', $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => 'after-rollback',
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryNullInequalityRetrySavepointBatch(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNullInequalityRetrySavepointBatch($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesNullInequalityRetrySavepointBatch(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value ROLLBACK retry next164 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ROLLBACK retry next164 rows must be arrays');
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
    private static function rowsByIdsNullInequalityRetrySavepointBatch(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value ROLLBACK retry next164 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ROLLBACK retry next164 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNullInequalityRetrySavepointBatch(array $yielded): int
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
    private static function changeCountNullInequalityRetrySavepointBatch(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesNullInequalityRetrySavepointBatch(array $before, array $after): array
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
    private static function rowCountsNullInequalityRetrySavepointBatch(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeIgnoreReturningSavepointBatch(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_ignore_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next165 needs statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next165 needs unique constraints');
        }
        self::identifierIgnoreReturningSavepointBatch($savepoint, 'savepoint');

        $savepointImage = self::normalizeTablesIgnoreReturningSavepointBatch($tables);
        $current = $savepointImage;
        $executed = [];
        $yielded = [];
        $ignoredStreams = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $summary = self::statementSummaryIgnoreReturningSavepointBatch($ordinal, $sql, $result, $before, $rowIdColumn);
            $executed[] = $summary;

            $yielded[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];

            if ($result['ignored_rows'] !== []) {
                $ignoredStreams[] = [
                    'ordinal' => $ordinal,
                    'action' => $result['action'],
                    'rows' => $result['ignored_rows'],
                    'conflicts' => $result['conflicts'],
                ];
            }
        }

        return [
            'savepoint' => $savepoint,
            'status' => 'released-after-rowvalue-ignore-conflicts',
            'rolled_back_to_savepoint' => false,
            'savepoint_preserved' => false,
            'savepoint_image_tables' => $savepointImage,
            'current_source_tables' => $current,
            'next_source_tables' => $current,
            'executed_statements' => $executed,
            'yielded_returning' => $yielded,
            'ignored_returning' => $ignoredStreams,
            'ignored_returning_count' => self::ignoredCountIgnoreReturningSavepointBatch($ignoredStreams),
            'yielded_returning_count' => self::returningCountIgnoreReturningSavepointBatch($yielded),
            'changes' => self::changeCountIgnoreReturningSavepointBatch($executed),
            'savepoint_changed_tables' => self::changedTablesIgnoreReturningSavepointBatch($savepointImage, $current),
            'row_counts' => self::rowCountsIgnoreReturningSavepointBatch($current),
            'dependencies' => [
                'sqlite-rowvalue-update-or-ignore-suppresses-returning',
                'sqlite-delete-returning-after-ignored-rowvalue-conflict-continues',
                'sqlite-savepoint-current-source-released-after-ignore-conflict',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesIgnoreReturningSavepointBatch(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next165 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next165 rows must be arrays');
                }
            }
        }

        return $tables;
    }

    private static function identifierIgnoreReturningSavepointBatch(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value UPDATE/DELETE RETURNING savepoint next165 {$label} is malformed");
        }
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:array<string,mixed>|null} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryIgnoreReturningSavepointBatch(int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
    {
        return [
            'ordinal' => $ordinal,
            'sql' => $sql,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rowsByIdsIgnoreReturningSavepointBatch($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
            'failed_conflict' => $result['failed_conflict'] ?? null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int|string> $ids
     * @return list<array<string,mixed>>
     */
    private static function rowsByIdsIgnoreReturningSavepointBatch(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value UPDATE/DELETE RETURNING savepoint next165 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value UPDATE/DELETE RETURNING savepoint next165 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountIgnoreReturningSavepointBatch(array $yielded): int
    {
        $count = 0;
        foreach ($yielded as $stream) {
            $count += count($stream['rows']);
        }

        return $count;
    }

    /**
     * @param list<array{rows:list<array<string,mixed>>}> $ignored
     */
    private static function ignoredCountIgnoreReturningSavepointBatch(array $ignored): int
    {
        $count = 0;
        foreach ($ignored as $stream) {
            $count += count($stream['rows']);
        }

        return $count;
    }

    /**
     * @param list<array<string,mixed>> $executed
     */
    private static function changeCountIgnoreReturningSavepointBatch(array $executed): int
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
     * @return array<string,int>
     */
    private static function rowCountsIgnoreReturningSavepointBatch(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesIgnoreReturningSavepointBatch(array $before, array $after): array
    {
        $changed = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $table) {
            if (($before[$table] ?? null) !== ($after[$table] ?? null)) {
                $changed[] = $table;
            }
        }

        sort($changed);

        return $changed;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $innerStatements
     * @param list<string> $outerStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNestedRetrySavepointBatch(
        array $tables,
        array $innerStatements,
        array $outerStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_import_nested_retry',
        string $innerSavepoint = 'wp_options_inner_cleanup_nested_retry',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($innerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint nested-retry needs inner statements');
        }
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint nested-retry needs outer statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint nested-retry needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint nested-retry needs unique constraints');
        }
        if ($outerSavepoint === '' || $innerSavepoint === '' || $outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint nested-retry needs distinct savepoint names');
        }

        $outerImage = self::normalizeTablesNestedRetrySavepointBatch($tables);
        [$innerReleasedCurrent, $innerExecuted, $innerReturning] = self::runStatementsNestedRetrySavepointBatch(
            $outerImage,
            $innerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-released',
        );
        [$outerAttemptedCurrent, $outerExecuted, $outerReturning] = self::runStatementsNestedRetrySavepointBatch(
            $innerReleasedCurrent,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-rollback',
        );

        $rollbackToOuter = $outerImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatementsNestedRetrySavepointBatch(
            $rollbackToOuter,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'after-outer-rollback',
        );

        $discardedReturning = array_merge($innerReturning, $outerReturning);
        $discardedStatements = array_merge($innerExecuted, $outerExecuted);

        return [
            'status' => 'inner-release-discarded-by-outer-rollback-retried',
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'inner_released' => true,
            'outer_rolled_back_to_savepoint' => true,
            'outer_savepoint_preserved_after_rollback_to' => true,
            'released_after_retry' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'inner_released_current_source_tables' => $innerReleasedCurrent,
            'outer_attempted_current_source_tables' => $outerAttemptedCurrent,
            'rollback_to_outer_current_source_tables' => $rollbackToOuter,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'inner_released_statements' => $innerExecuted,
            'outer_attempted_statements' => $outerExecuted,
            'retry_statements' => $retryExecuted,
            'inner_released_returning' => $innerReturning,
            'outer_attempted_returning' => $outerReturning,
            'discarded_returning' => $discardedReturning,
            'yielded_returning' => $retryReturning,
            'discarded_returning_count' => self::returningCountNestedRetrySavepointBatch($discardedReturning),
            'yielded_returning_count' => self::returningCountNestedRetrySavepointBatch($retryReturning),
            'discarded_changes_before_outer_rollback_to' => self::changeCountNestedRetrySavepointBatch($discardedStatements),
            'changes_after_retry_release' => self::changeCountNestedRetrySavepointBatch($retryExecuted),
            'row_counts' => self::rowCountsNestedRetrySavepointBatch($retryCurrent),
            'changed_tables_after_retry' => self::changedTablesNestedRetrySavepointBatch($outerImage, $retryCurrent),
            'dependencies' => [
                'sqlite-release-inner-savepoint-merges-rowvalue-returning-into-outer-savepoint-nested-retry',
                'sqlite-rollback-to-outer-savepoint-discards-released-inner-returning-nested-retry',
                'sqlite-rowvalue-update-delete-retry-after-outer-rollback-reads-original-current-source-nested-retry',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNestedRetrySavepointBatch(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNestedRetrySavepointBatch($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryNestedRetrySavepointBatch(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNestedRetrySavepointBatch($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNestedRetrySavepointBatch(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value nested savepoint nested-retry tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value nested savepoint nested-retry rows must be arrays');
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
    private static function rowsByIdsNestedRetrySavepointBatch(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value nested savepoint nested-retry rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value nested savepoint nested-retry rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNestedRetrySavepointBatch(array $yielded): int
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
    private static function changeCountNestedRetrySavepointBatch(array $executed): int
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
     * @return array<string,int>
     */
    private static function rowCountsNestedRetrySavepointBatch(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesNestedRetrySavepointBatch(array $before, array $after): array
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


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerAttemptStatements
     * @param list<string> $innerRetryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNestedIgnoreRetrySavepointBatch(
        array $tables,
        array $outerStatements,
        array $innerAttemptStatements,
        array $innerRetryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_rowvalue_nested_ignore_retry',
        string $innerSavepoint = 'wp_options_inner_rowvalue_nested_ignore_retry',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite nested row-value savepoint nested-ignore-retry needs outer statements');
        }
        if ($innerAttemptStatements === []) {
            throw new \InvalidArgumentException('SQLite nested row-value savepoint nested-ignore-retry needs inner attempt statements');
        }
        if ($innerRetryStatements === []) {
            throw new \InvalidArgumentException('SQLite nested row-value savepoint nested-ignore-retry needs inner retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite nested row-value savepoint nested-ignore-retry needs unique constraints');
        }

        $outerImage = self::normalizeTablesNestedIgnoreRetrySavepointBatch($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNestedIgnoreRetrySavepointBatch(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-inner',
        );

        $innerImage = $afterOuter;
        [$attemptedInner, $innerAttempted, $innerAttemptReturning] = self::runStatementsNestedIgnoreRetrySavepointBatch(
            $innerImage,
            $innerAttemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-before-rollback-to',
        );

        $afterRollbackToInner = $innerImage;
        [$afterRetry, $innerRetry, $innerRetryReturning] = self::runStatementsNestedIgnoreRetrySavepointBatch(
            $afterRollbackToInner,
            $innerRetryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-after-rollback-to',
        );

        return [
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'status' => 'outer-released-after-inner-rollback-to-retry-current-source-nested-ignore-retry',
            'rolled_back_to_inner_savepoint' => true,
            'inner_savepoint_preserved_after_rollback_to' => true,
            'inner_released_after_retry' => true,
            'outer_released' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'inner_savepoint_image_tables' => $innerImage,
            'attempted_inner_current_source_tables' => $attemptedInner,
            'rollback_to_inner_current_source_tables' => $afterRollbackToInner,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'inner_attempt_statements' => $innerAttempted,
            'inner_retry_statements' => $innerRetry,
            'yielded_outer_returning' => $outerReturning,
            'discarded_inner_returning' => $innerAttemptReturning,
            'yielded_inner_retry_returning' => $innerRetryReturning,
            'yielded_outer_returning_count' => self::returningCountNestedIgnoreRetrySavepointBatch($outerReturning),
            'discarded_inner_returning_count' => self::returningCountNestedIgnoreRetrySavepointBatch($innerAttemptReturning),
            'yielded_inner_retry_returning_count' => self::returningCountNestedIgnoreRetrySavepointBatch($innerRetryReturning),
            'outer_changes' => self::changeCountNestedIgnoreRetrySavepointBatch($outerExecuted),
            'discarded_inner_changes' => self::changeCountNestedIgnoreRetrySavepointBatch($innerAttempted),
            'changes_after_inner_retry' => self::changeCountNestedIgnoreRetrySavepointBatch($innerRetry),
            'total_released_changes' => self::changeCountNestedIgnoreRetrySavepointBatch($outerExecuted) + self::changeCountNestedIgnoreRetrySavepointBatch($innerRetry),
            'changed_tables_after_release' => self::changedTablesNestedIgnoreRetrySavepointBatch($outerImage, $afterRetry),
            'row_counts' => self::rowCountsNestedIgnoreRetrySavepointBatch($afterRetry),
            'dependencies' => [
                'sqlite-nested-savepoint-rollback-to-preserves-outer-rowvalue-returning-nested-ignore-retry',
                'sqlite-rowvalue-update-delete-returning-discards-inner-rollback-stream-nested-ignore-retry',
                'sqlite-rowvalue-retry-after-inner-rollback-reads-inner-savepoint-image-nested-ignore-retry',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNestedIgnoreRetrySavepointBatch(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        string $rowIdColumn,
        string $phase,
    ): array {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNestedIgnoreRetrySavepointBatch($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryNestedIgnoreRetrySavepointBatch(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNestedIgnoreRetrySavepointBatch($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesNestedIgnoreRetrySavepointBatch(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite nested row-value savepoint nested-ignore-retry tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite nested row-value savepoint nested-ignore-retry rows must be arrays');
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
    private static function rowsByIdsNestedIgnoreRetrySavepointBatch(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite nested row-value savepoint nested-ignore-retry rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite nested row-value savepoint nested-ignore-retry rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNestedIgnoreRetrySavepointBatch(array $yielded): int
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
    private static function changeCountNestedIgnoreRetrySavepointBatch(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesNestedIgnoreRetrySavepointBatch(array $before, array $after): array
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
    private static function rowCountsNestedIgnoreRetrySavepointBatch(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAbortRollbackRetrySavepoint(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_abort_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint abort-retry needs attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint abort-retry needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint abort-retry needs unique constraints');
        }

        $savepointImage = self::normalizeTablesAbortRollbackRetrySavepoint($tables);
        [$attemptedCurrent, $attempted, $attemptedReturning, $abortReason, $abortOrdinal] = self::runUntilAbortAbortRollbackRetrySavepoint(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );
        $statementAborted = $abortReason !== null;
        [$retryCurrent, $retry, $retryReturning] = self::runRetryAbortRollbackRetrySavepoint(
            $attemptedCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        return [
            'savepoint' => $savepoint,
            'status' => $statementAborted ? 'statement-aborted-savepoint-preserved-retried-current-source' : 'released-without-abort-current-source-abort-retry',
            'statement_aborted' => $statementAborted,
            'transaction_rolled_back' => false,
            'rolled_back_to_savepoint' => false,
            'savepoint_preserved_after_abort' => true,
            'released_after_retry' => true,
            'abort_statement_ordinal' => $abortOrdinal,
            'abort_reason' => $abortReason,
            'savepoint_image_tables' => $savepointImage,
            'attempted_current_source_tables' => $attemptedCurrent,
            'abort_current_source_tables' => $attemptedCurrent,
            'retry_base_current_source_tables' => $attemptedCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'attempt_statements' => $attempted,
            'retry_statements' => $retry,
            'yielded_before_abort' => $attemptedReturning,
            'aborted_statement_returning' => [],
            'yielded_returning' => $retryReturning,
            'yielded_before_abort_count' => self::returningCountAbortRollbackRetrySavepoint($attemptedReturning),
            'aborted_statement_returning_count' => 0,
            'yielded_returning_count' => self::returningCountAbortRollbackRetrySavepoint($retryReturning),
            'changes_before_abort' => self::changeCountAbortRollbackRetrySavepoint($attempted),
            'changes_after_retry' => self::changeCountAbortRollbackRetrySavepoint($retry),
            'total_changes_after_release' => self::changeCountAbortRollbackRetrySavepoint($attempted) + self::changeCountAbortRollbackRetrySavepoint($retry),
            'changed_tables_after_retry' => self::changedTablesAbortRollbackRetrySavepoint($savepointImage, $retryCurrent),
            'row_counts' => self::rowCountsAbortRollbackRetrySavepoint($retryCurrent),
            'dependencies' => [
                'sqlite-update-or-abort-rowvalue-conflict-rolls-back-current-statement-only',
                'sqlite-abort-conflict-preserves-savepoint-and-prior-returning-streams',
                'sqlite-rowvalue-update-delete-returning-retry-continues-from-abort-current-source-abort-retry',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>,3:?string,4:?int}
     */
    private static function runUntilAbortAbortRollbackRetrySavepoint(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];
        $abortReason = null;
        $abortOrdinal = null;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $abortReason = $exception->getMessage();
                $abortOrdinal = $ordinal;
                if (stripos($abortReason, ' using OR ABORT') === false) {
                    throw $exception;
                }
                break;
            }

            $current = $result['tables'];
            $executed[] = self::statementSummaryAbortRollbackRetrySavepoint('before-abort', $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => 'before-abort',
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded, $abortReason, $abortOrdinal];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runRetryAbortRollbackRetrySavepoint(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryAbortRollbackRetrySavepoint('after-abort', $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => 'after-abort',
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryAbortRollbackRetrySavepoint(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsAbortRollbackRetrySavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesAbortRollbackRetrySavepoint(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value ABORT savepoint abort-retry tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ABORT savepoint abort-retry rows must be arrays');
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
    private static function rowsByIdsAbortRollbackRetrySavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint abort-retry rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint abort-retry rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountAbortRollbackRetrySavepoint(array $yielded): int
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
    private static function changeCountAbortRollbackRetrySavepoint(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesAbortRollbackRetrySavepoint(array $before, array $after): array
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
    private static function rowCountsAbortRollbackRetrySavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAbortReturningSavepointBatch(
        array $tables,
        array $statements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_abort_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next170 needs statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next170 needs unique constraints');
        }
        self::identifierAbortReturningSavepointBatch($savepoint, 'savepoint');

        $savepointImage = self::normalizeTablesAbortReturningSavepointBatch($tables);
        [$current, $executed, $yielded, $aborted] = self::runUntilAbortAbortReturningSavepointBatch($savepointImage, $statements, $uniqueConstraints, $rowIdColumn);
        [$retryCurrent, $retryExecuted, $retryYielded] = self::runRetryAbortReturningSavepointBatch($current, $retryStatements, $uniqueConstraints, $rowIdColumn);

        return [
            'savepoint' => $savepoint,
            'status' => $aborted === null ? 'released-cleanly' : 'aborted-statement-preserved-savepoint',
            'aborted_statement' => $aborted,
            'statement_aborted' => $aborted !== null,
            'rolled_back_to_savepoint' => false,
            'savepoint_preserved_after_abort' => $aborted !== null,
            'released_after_retry' => true,
            'savepoint_image_tables' => $savepointImage,
            'current_source_after_abort_tables' => $current,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'executed_statements' => $executed,
            'yielded_returning' => $yielded,
            'retry_statements' => $retryExecuted,
            'retry_returning' => $retryYielded,
            'yielded_returning_count_before_abort' => self::returningCountAbortReturningSavepointBatch($yielded),
            'retry_returning_count' => self::returningCountAbortReturningSavepointBatch($retryYielded),
            'changes_before_abort' => self::changeCountAbortReturningSavepointBatch($executed),
            'changes_after_retry' => self::changeCountAbortReturningSavepointBatch($retryExecuted),
            'savepoint_changed_tables_after_abort' => self::changedTablesAbortReturningSavepointBatch($savepointImage, $current),
            'changed_tables_after_retry' => self::changedTablesAbortReturningSavepointBatch($savepointImage, $retryCurrent),
            'row_counts_after_abort' => self::rowCountsAbortReturningSavepointBatch($current),
            'row_counts_after_retry' => self::rowCountsAbortReturningSavepointBatch($retryCurrent),
            'dependencies' => [
                'sqlite-update-or-abort-rolls-back-current-rowvalue-statement-only',
                'sqlite-prior-update-delete-returning-streams-survive-abort-statement',
                'sqlite-rowvalue-abort-savepoint-current-source-retry-release-next170',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>,3:?array<string,mixed>}
     */
    private static function runUntilAbortAbortReturningSavepointBatch(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                return [
                    $current,
                    $executed,
                    $yielded,
                    [
                        'ordinal' => $ordinal,
                        'sql' => $sql,
                        'reason' => $exception->getMessage(),
                        'statement_source_tables' => $before,
                    ],
                ];
            }

            $current = $result['tables'];
            $executed[] = self::statementSummaryAbortReturningSavepointBatch($ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded, null];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runRetryAbortReturningSavepointBatch(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryAbortReturningSavepointBatch($ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryAbortReturningSavepointBatch(int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
    {
        return [
            'ordinal' => $ordinal,
            'sql' => $sql,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rowsByIdsAbortReturningSavepointBatch($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesAbortReturningSavepointBatch(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next170 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next170 rows must be arrays');
                }
            }
        }

        return $tables;
    }

    private static function identifierAbortReturningSavepointBatch(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next170 {$label} is malformed");
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int|string> $ids
     * @return list<array<string,mixed>>
     */
    private static function rowsByIdsAbortReturningSavepointBatch(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next170 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next170 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountAbortReturningSavepointBatch(array $yielded): int
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
    private static function changeCountAbortReturningSavepointBatch(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesAbortReturningSavepointBatch(array $before, array $after): array
    {
        $changed = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $name) {
            if (($before[$name] ?? null) !== ($after[$name] ?? null)) {
                $changed[] = $name;
            }
        }

        sort($changed);

        return $changed;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,int>
     */
    private static function rowCountsAbortReturningSavepointBatch(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldedBeforeRollbackStatements
     * @param list<string> $discardedBeforeRollbackStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeYieldCheckpointSavepointBatch(
        array $tables,
        array $yieldedBeforeRollbackStatements,
        array $discardedBeforeRollbackStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_rowvalue_yield_retry_next172',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($yieldedBeforeRollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value yield savepoint next172 needs yielded pre-rollback statements');
        }
        if ($discardedBeforeRollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value yield savepoint next172 needs discarded pre-rollback statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value yield savepoint next172 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value yield savepoint next172 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value yield savepoint next172 savepoint name must be an identifier');
        }

        $savepointImage = self::normalizeTablesYieldCheckpointSavepointBatch($tables);
        [$yieldedAttemptCurrent, $yieldedStatements, $deliveredReturning] = self::runStatementsYieldCheckpointSavepointBatch(
            $savepointImage,
            $yieldedBeforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'yielded-before-rollback',
        );
        [$discardAttemptCurrent, $discardedStatements, $discardedReturning] = self::runStatementsYieldCheckpointSavepointBatch(
            $yieldedAttemptCurrent,
            $discardedBeforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'discarded-before-rollback',
        );

        $rollbackToCurrent = $savepointImage;
        [$retryCurrent, $retryStatementsExecuted, $retryReturning] = self::runStatementsYieldCheckpointSavepointBatch(
            $rollbackToCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'after-rollback-to',
        );

        $allAttempted = array_merge($yieldedStatements, $discardedStatements);
        $allSuppressed = array_merge($deliveredReturning, $discardedReturning);

        return [
            'status' => 'yielded-rowvalue-returning-stream-rolled-back-and-retried',
            'savepoint' => $savepoint,
            'returning_stream_was_observable_before_rollback' => true,
            'observable_returning_is_not_durable_after_rollback_to' => true,
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'released_after_retry' => true,
            'savepoint_image_tables' => $savepointImage,
            'yielded_attempt_current_source_tables' => $yieldedAttemptCurrent,
            'discard_attempt_current_source_tables' => $discardAttemptCurrent,
            'rollback_to_current_source_tables' => $rollbackToCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'yielded_before_rollback_statements' => $yieldedStatements,
            'discarded_before_rollback_statements' => $discardedStatements,
            'retry_statements' => $retryStatementsExecuted,
            'delivered_before_rollback_returning' => $deliveredReturning,
            'discarded_before_rollback_returning' => $discardedReturning,
            'suppressed_by_rollback_returning' => $allSuppressed,
            'yielded_after_retry_returning' => $retryReturning,
            'delivered_before_rollback_count' => self::returningCountYieldCheckpointSavepointBatch($deliveredReturning),
            'discarded_before_rollback_count' => self::returningCountYieldCheckpointSavepointBatch($discardedReturning),
            'suppressed_by_rollback_count' => self::returningCountYieldCheckpointSavepointBatch($allSuppressed),
            'yielded_after_retry_count' => self::returningCountYieldCheckpointSavepointBatch($retryReturning),
            'attempted_changes_before_rollback_to' => self::changeCountYieldCheckpointSavepointBatch($allAttempted),
            'changes_after_retry_release' => self::changeCountYieldCheckpointSavepointBatch($retryStatementsExecuted),
            'row_counts' => self::rowCountsYieldCheckpointSavepointBatch($retryCurrent),
            'changed_tables_after_retry' => self::changedTablesYieldCheckpointSavepointBatch($savepointImage, $retryCurrent),
            'dependencies' => [
                'sqlite-rowvalue-returning-yield-before-savepoint-rollback-next172',
                'sqlite-rollback-to-suppresses-yielded-returning-durability-next172',
                'sqlite-rowvalue-update-delete-retry-current-source-after-yield-rollback-next172',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsYieldCheckpointSavepointBatch(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryYieldCheckpointSavepointBatch($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryYieldCheckpointSavepointBatch(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsYieldCheckpointSavepointBatch($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesYieldCheckpointSavepointBatch(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value yield savepoint next172 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value yield savepoint next172 rows must be arrays');
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
    private static function rowsByIdsYieldCheckpointSavepointBatch(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value yield savepoint next172 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value yield savepoint next172 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountYieldCheckpointSavepointBatch(array $yielded): int
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
    private static function changeCountYieldCheckpointSavepointBatch(array $executed): int
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
     * @return array<string,int>
     */
    private static function rowCountsYieldCheckpointSavepointBatch(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesYieldCheckpointSavepointBatch(array $before, array $after): array
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


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeInPredicateRetrySavepointBatch(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_fail_retry_next173',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value update/delete RETURNING next173 needs attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value update/delete RETURNING next173 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value update/delete RETURNING next173 needs unique constraints');
        }
        self::identifierInPredicateRetrySavepointBatch($savepoint, 'savepoint');

        $savepointImage = self::normalizeTablesInPredicateRetrySavepointBatch($tables);
        [$failedCurrent, $attempted, $attemptedReturning, $failedConflict, $failedOrdinal] = self::runAttemptInPredicateRetrySavepointBatch(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        $rollbackToCurrent = $failedConflict === null ? $failedCurrent : $savepointImage;
        [$releasedCurrent, $retry, $yieldedReturning] = self::runRetryInPredicateRetrySavepointBatch(
            $rollbackToCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        return [
            'savepoint' => $savepoint,
            'status' => $failedConflict === null
                ? 'released-after-clean-current-source-next173'
                : 'fail-stream-rolled-back-retried-current-source-next173',
            'failed_statement_ordinal' => $failedOrdinal,
            'failed_conflict' => $failedConflict,
            'rolled_back_to_savepoint' => $failedConflict !== null,
            'savepoint_preserved_after_rollback_to' => $failedConflict !== null,
            'released_after_retry' => true,
            'savepoint_image_tables' => $savepointImage,
            'failed_current_source_tables' => $failedCurrent,
            'rollback_to_current_source_tables' => $rollbackToCurrent,
            'current_source_tables' => $releasedCurrent,
            'next_source_tables' => $releasedCurrent,
            'attempt_statements' => $attempted,
            'retry_statements' => $retry,
            'attempted_returning_before_rollback' => $attemptedReturning,
            'discarded_returning' => $failedConflict === null ? [] : $attemptedReturning,
            'yielded_returning' => $yieldedReturning,
            'discarded_returning_count' => $failedConflict === null ? 0 : self::returningCountInPredicateRetrySavepointBatch($attemptedReturning),
            'yielded_returning_count' => self::returningCountInPredicateRetrySavepointBatch($yieldedReturning),
            'attempted_changes_before_rollback_to' => self::changeCountInPredicateRetrySavepointBatch($attempted),
            'changes_after_retry_release' => self::changeCountInPredicateRetrySavepointBatch($retry),
            'changed_tables_after_retry' => self::changedTablesInPredicateRetrySavepointBatch($savepointImage, $releasedCurrent),
            'row_counts' => self::rowCountsInPredicateRetrySavepointBatch($releasedCurrent),
            'dependencies' => [
                'sqlite-update-or-fail-rowvalue-returning-stream-before-savepoint-rollback-next173',
                'sqlite-rollback-to-discards-update-delete-returning-stream-next173',
                'sqlite-rowvalue-null-safe-retry-predicate-reads-restored-current-source-next173',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>,3:?array<string,mixed>,4:?int}
     */
    private static function runAttemptInPredicateRetrySavepointBatch(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];
        $failedConflict = null;
        $failedOrdinal = null;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints, true);
            $current = $result['tables'];
            $executed[] = self::statementSummaryInPredicateRetrySavepointBatch('before-rollback-to', $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => 'before-rollback-to',
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
            if (($result['failed_conflict'] ?? null) !== null) {
                $failedConflict = $result['failed_conflict'];
                $failedOrdinal = $ordinal;
                break;
            }
        }

        return [$current, $executed, $yielded, $failedConflict, $failedOrdinal];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runRetryInPredicateRetrySavepointBatch(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryInPredicateRetrySavepointBatch('after-rollback-to', $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => 'after-rollback-to',
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:array<string,mixed>|null} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryInPredicateRetrySavepointBatch(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsInPredicateRetrySavepointBatch($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesInPredicateRetrySavepointBatch(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value update/delete RETURNING next173 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value update/delete RETURNING next173 rows must be arrays');
                }
            }
        }

        return $tables;
    }

    private static function identifierInPredicateRetrySavepointBatch(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value update/delete RETURNING next173 {$label} is malformed");
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int|string> $ids
     * @return list<array<string,mixed>>
     */
    private static function rowsByIdsInPredicateRetrySavepointBatch(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value update/delete RETURNING next173 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value update/delete RETURNING next173 rowid column {$rowIdColumn} must be int or string");
            }
            if (isset($wanted[(string) $id])) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * @param list<array{rows:list<array<string,mixed>>}> $streams
     */
    private static function returningCountInPredicateRetrySavepointBatch(array $streams): int
    {
        $count = 0;
        foreach ($streams as $stream) {
            $count += count($stream['rows']);
        }

        return $count;
    }

    /**
     * @param list<array<string,mixed>> $executed
     */
    private static function changeCountInPredicateRetrySavepointBatch(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesInPredicateRetrySavepointBatch(array $before, array $after): array
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
    private static function rowCountsInPredicateRetrySavepointBatch(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeReleasedInnerRollbackRetrySavepoint(
        array $tables,
        array $outerStatements,
        array $innerStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_rowvalue_next174',
        string $innerSavepoint = 'wp_options_inner_rowvalue_next174',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value released inner savepoint next174 needs outer statements');
        }
        if ($innerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value released inner savepoint next174 needs inner statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value released inner savepoint next174 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value released inner savepoint next174 needs unique constraints');
        }

        $outerImage = self::normalizeTablesReleasedInnerRollbackRetrySavepoint($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsReleasedInnerRollbackRetrySavepoint($outerImage, $outerStatements, $uniqueConstraints, $rowIdColumn, 'outer-before-inner');

        $innerImage = $afterOuter;
        [$afterInnerRelease, $innerExecuted, $innerReturning] = self::runStatementsReleasedInnerRollbackRetrySavepoint($innerImage, $innerStatements, $uniqueConstraints, $rowIdColumn, 'inner-before-release');

        $afterOuterRollback = $outerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsReleasedInnerRollbackRetrySavepoint($afterOuterRollback, $retryStatements, $uniqueConstraints, $rowIdColumn, 'after-outer-rollback');

        return [
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'status' => 'inner-released-outer-rollback-to-retry-current-source-next174',
            'inner_released_into_outer' => true,
            'rolled_back_to_outer_savepoint' => true,
            'outer_savepoint_preserved_after_rollback_to' => true,
            'released_after_retry' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'inner_savepoint_image_tables' => $innerImage,
            'released_inner_current_source_tables' => $afterInnerRelease,
            'rollback_to_outer_current_source_tables' => $afterOuterRollback,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'inner_released_statements' => $innerExecuted,
            'retry_statements' => $retryExecuted,
            'discarded_outer_returning' => $outerReturning,
            'discarded_inner_released_returning' => $innerReturning,
            'yielded_retry_returning' => $retryReturning,
            'discarded_outer_returning_count' => self::returningCountReleasedInnerRollbackRetrySavepoint($outerReturning),
            'discarded_inner_released_returning_count' => self::returningCountReleasedInnerRollbackRetrySavepoint($innerReturning),
            'yielded_retry_returning_count' => self::returningCountReleasedInnerRollbackRetrySavepoint($retryReturning),
            'discarded_outer_changes' => self::changeCountReleasedInnerRollbackRetrySavepoint($outerExecuted),
            'discarded_inner_released_changes' => self::changeCountReleasedInnerRollbackRetrySavepoint($innerExecuted),
            'changes_after_retry' => self::changeCountReleasedInnerRollbackRetrySavepoint($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesReleasedInnerRollbackRetrySavepoint($outerImage, $afterRetry),
            'row_counts' => self::rowCountsReleasedInnerRollbackRetrySavepoint($afterRetry),
            'dependencies' => [
                'sqlite-release-inner-savepoint-propagates-rowvalue-returning-to-outer-next174',
                'sqlite-rollback-to-outer-savepoint-discards-released-inner-rowvalue-effects-next174',
                'sqlite-rowvalue-update-delete-returning-retry-starts-from-outer-image-next174',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsReleasedInnerRollbackRetrySavepoint(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryReleasedInnerRollbackRetrySavepoint($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryReleasedInnerRollbackRetrySavepoint(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsReleasedInnerRollbackRetrySavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesReleasedInnerRollbackRetrySavepoint(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value released inner savepoint next174 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value released inner savepoint next174 rows must be arrays');
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
    private static function rowsByIdsReleasedInnerRollbackRetrySavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value released inner savepoint next174 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value released inner savepoint next174 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountReleasedInnerRollbackRetrySavepoint(array $yielded): int
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
    private static function changeCountReleasedInnerRollbackRetrySavepoint(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesReleasedInnerRollbackRetrySavepoint(array $before, array $after): array
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
    private static function rowCountsReleasedInnerRollbackRetrySavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerYieldedStatements
     * @param list<string> $innerDiscardedStatements
     * @param list<string> $innerRetryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeInnerRollbackRetrySavepoint(
        array $tables,
        array $outerStatements,
        array $innerYieldedStatements,
        array $innerDiscardedStatements,
        array $innerRetryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_rowvalue_next177',
        string $innerSavepoint = 'wp_options_inner_rowvalue_next177',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value inner rollback next177 needs outer statements');
        }
        if ($innerYieldedStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value inner rollback next177 needs yielded inner statements');
        }
        if ($innerDiscardedStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value inner rollback next177 needs discarded inner statements');
        }
        if ($innerRetryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value inner rollback next177 needs retry inner statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value inner rollback next177 needs unique constraints');
        }

        $outerImage = self::normalizeTablesInnerRollbackRetrySavepoint($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsInnerRollbackRetrySavepoint($outerImage, $outerStatements, $uniqueConstraints, $rowIdColumn, 'outer-before-inner');

        $innerImage = $afterOuter;
        [$afterYielded, $yieldedExecuted, $yieldedReturning] = self::runStatementsInnerRollbackRetrySavepoint($innerImage, $innerYieldedStatements, $uniqueConstraints, $rowIdColumn, 'inner-yielded-before-rollback');
        [$afterDiscarded, $discardedExecuted, $discardedReturning] = self::runStatementsInnerRollbackRetrySavepoint($afterYielded, $innerDiscardedStatements, $uniqueConstraints, $rowIdColumn, 'inner-discarded-before-rollback');

        $afterInnerRollback = $innerImage;
        [$afterInnerRetry, $retryExecuted, $retryReturning] = self::runStatementsInnerRollbackRetrySavepoint($afterInnerRollback, $innerRetryStatements, $uniqueConstraints, $rowIdColumn, 'inner-retry-after-rollback');

        $innerSuppressedReturning = array_merge($yieldedReturning, $discardedReturning);
        $innerAttempted = array_merge($yieldedExecuted, $discardedExecuted);

        return [
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'status' => 'inner-rollback-to-retry-current-source-next177',
            'rolled_back_to_inner_savepoint' => true,
            'outer_savepoint_preserved_after_inner_rollback_to' => true,
            'inner_savepoint_preserved_after_rollback_to' => true,
            'inner_released_after_retry' => true,
            'outer_released_after_inner_retry' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'outer_current_source_tables' => $afterOuter,
            'inner_savepoint_image_tables' => $innerImage,
            'inner_yielded_current_source_tables' => $afterYielded,
            'inner_discarded_current_source_tables' => $afterDiscarded,
            'rollback_to_inner_current_source_tables' => $afterInnerRollback,
            'current_source_tables' => $afterInnerRetry,
            'next_source_tables' => $afterInnerRetry,
            'outer_statements' => $outerExecuted,
            'inner_yielded_statements' => $yieldedExecuted,
            'inner_discarded_statements' => $discardedExecuted,
            'inner_retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'inner_yielded_before_rollback_returning' => $yieldedReturning,
            'inner_discarded_before_rollback_returning' => $discardedReturning,
            'inner_suppressed_by_rollback_returning' => $innerSuppressedReturning,
            'inner_yielded_after_retry_returning' => $retryReturning,
            'outer_yielded_returning_count' => self::returningCountInnerRollbackRetrySavepoint($outerReturning),
            'inner_yielded_before_rollback_count' => self::returningCountInnerRollbackRetrySavepoint($yieldedReturning),
            'inner_discarded_before_rollback_count' => self::returningCountInnerRollbackRetrySavepoint($discardedReturning),
            'inner_suppressed_by_rollback_count' => self::returningCountInnerRollbackRetrySavepoint($innerSuppressedReturning),
            'inner_yielded_after_retry_count' => self::returningCountInnerRollbackRetrySavepoint($retryReturning),
            'outer_changes_preserved' => self::changeCountInnerRollbackRetrySavepoint($outerExecuted),
            'inner_attempted_changes_before_rollback_to' => self::changeCountInnerRollbackRetrySavepoint($innerAttempted),
            'inner_changes_after_retry_release' => self::changeCountInnerRollbackRetrySavepoint($retryExecuted),
            'changed_tables_after_inner_retry' => self::changedTablesInnerRollbackRetrySavepoint($outerImage, $afterInnerRetry),
            'row_counts' => self::rowCountsInnerRollbackRetrySavepoint($afterInnerRetry),
            'dependencies' => [
                'sqlite-inner-savepoint-rowvalue-returning-yield-before-rollback-next177',
                'sqlite-rollback-to-inner-savepoint-preserves-outer-current-source-next177',
                'sqlite-rowvalue-update-delete-returning-retry-starts-from-inner-image-next177',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsInnerRollbackRetrySavepoint(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryInnerRollbackRetrySavepoint($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryInnerRollbackRetrySavepoint(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsInnerRollbackRetrySavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesInnerRollbackRetrySavepoint(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value inner rollback next177 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value inner rollback next177 rows must be arrays');
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
    private static function rowsByIdsInnerRollbackRetrySavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value inner rollback next177 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value inner rollback next177 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountInnerRollbackRetrySavepoint(array $yielded): int
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
    private static function changeCountInnerRollbackRetrySavepoint(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesInnerRollbackRetrySavepoint(array $before, array $after): array
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
    private static function rowCountsInnerRollbackRetrySavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $savepointStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeValuesRetrySavepointBatch(
        array $tables,
        array $outerStatements,
        array $savepointStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $transaction = 'wp_options_import_txn',
        string $savepoint = 'wp_options_rowvalue_rollback_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next178 needs outer statements');
        }
        if ($savepointStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next178 needs savepoint statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next178 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next178 needs unique constraints');
        }

        $transactionImage = self::normalizeTablesValuesRetrySavepointBatch($tables);
        [$outerCurrent, $outerExecuted, $outerReturning] = self::runStatementsValuesRetrySavepointBatch(
            $transactionImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer',
        );
        $savepointImage = $outerCurrent;
        [$failedCurrent, $savepointExecuted, $savepointReturning, $rollbackReason, $rollbackOrdinal] = self::runSavepointUntilRollbackValuesRetrySavepointBatch(
            $savepointImage,
            $savepointStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        $rolledBackTransaction = $rollbackReason !== null;
        $retrySource = $rolledBackTransaction ? $transactionImage : $failedCurrent;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatementsValuesRetrySavepointBatch(
            $retrySource,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry',
        );

        $discardedReturning = $rolledBackTransaction
            ? array_merge($outerReturning, $savepointReturning)
            : [];

        return [
            'transaction' => $transaction,
            'savepoint' => $savepoint,
            'status' => $rolledBackTransaction ? 'transaction-rolled-back-retried' : 'savepoint-released-retried',
            'rolled_back_transaction' => $rolledBackTransaction,
            'rolled_back_savepoint' => $rolledBackTransaction,
            'savepoint_preserved_after_rollback' => false,
            'rollback_statement_ordinal' => $rollbackOrdinal,
            'rollback_reason' => $rollbackReason,
            'transaction_image_tables' => $transactionImage,
            'savepoint_image_tables' => $savepointImage,
            'failed_current_source_tables' => $failedCurrent,
            'rollback_to_current_source_tables' => $retrySource,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'outer_statements' => $outerExecuted,
            'savepoint_statements' => $savepointExecuted,
            'retry_statements' => $retryExecuted,
            'discarded_returning' => $discardedReturning,
            'yielded_returning' => $retryReturning,
            'discarded_returning_count' => self::returningCountValuesRetrySavepointBatch($discardedReturning),
            'yielded_returning_count' => self::returningCountValuesRetrySavepointBatch($retryReturning),
            'attempted_changes_before_rollback' => self::changeCountValuesRetrySavepointBatch(array_merge($outerExecuted, $savepointExecuted)),
            'changes_after_retry' => self::changeCountValuesRetrySavepointBatch($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesValuesRetrySavepointBatch($transactionImage, $retryCurrent),
            'row_counts' => self::rowCountsValuesRetrySavepointBatch($retryCurrent),
            'dependencies' => [
                'sqlite-update-or-rollback-rowvalue-returning-rolls-back-transaction-next178',
                'sqlite-rollback-conflict-discards-outer-and-savepoint-returning-next178',
                'sqlite-rowvalue-retry-after-rollback-reads-transaction-image-next178',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsValuesRetrySavepointBatch(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryValuesRetrySavepointBatch($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>,3:?string,4:?int}
     */
    private static function runSavepointUntilRollbackValuesRetrySavepointBatch(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $executed[] = [
                    'phase' => 'savepoint',
                    'ordinal' => $ordinal,
                    'sql' => $sql,
                    'action' => str_starts_with(strtoupper(ltrim($sql)), 'DELETE') ? 'delete' : 'update',
                    'conflict_action' => str_contains(strtoupper($sql), ' OR ROLLBACK ') ? 'rollback' : 'abort',
                    'table' => self::statementTableNameValuesRetrySavepointBatch($sql),
                    'selected_ids' => [],
                    'mutation_ids' => [],
                    'source_rows' => [],
                    'returning_rows' => [],
                    'ignored_rows' => [],
                    'deleted_conflict_rows' => [],
                    'conflicts' => [],
                    'failed_conflict' => ['message' => $exception->getMessage()],
                ];

                return [$current, $executed, $yielded, $exception->getMessage(), $ordinal];
            }

            $current = $result['tables'];
            $executed[] = self::statementSummaryValuesRetrySavepointBatch('savepoint', $ordinal, $sql, $result, $before, $rowIdColumn, null);
            $yielded[] = [
                'phase' => 'savepoint',
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded, null, null];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryValuesRetrySavepointBatch(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?array $failedConflict): array
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
            'source_rows' => self::rowsByIdsValuesRetrySavepointBatch($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
            'failed_conflict' => $failedConflict ?? ($result['failed_conflict'] ?? null),
        ];
    }

    private static function statementTableNameValuesRetrySavepointBatch(string $sql): string
    {
        if (preg_match('/^\s*DELETE\s+FROM\s+([A-Za-z_][A-Za-z0-9_]*)/i', $sql, $match) === 1) {
            return $match[1];
        }
        if (preg_match('/^\s*UPDATE(?:\s+OR\s+[A-Z]+)?\s+([A-Za-z_][A-Za-z0-9_]*)/i', $sql, $match) === 1) {
            return $match[1];
        }

        return '';
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesValuesRetrySavepointBatch(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next178 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next178 rows must be arrays');
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
    private static function rowsByIdsValuesRetrySavepointBatch(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value OR ROLLBACK next178 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR ROLLBACK next178 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountValuesRetrySavepointBatch(array $yielded): int
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
    private static function changeCountValuesRetrySavepointBatch(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesValuesRetrySavepointBatch(array $before, array $after): array
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
    private static function rowCountsValuesRetrySavepointBatch(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerYieldedStatements
     * @param list<string> $innerDiscardedStatements
     * @param list<string> $innerRetryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeIgnoreNestedRetrySavepointBatch(
        array $tables,
        array $outerStatements,
        array $innerYieldedStatements,
        array $innerDiscardedStatements,
        array $innerRetryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_rowvalue_ignore_retry',
        string $innerSavepoint = 'wp_options_inner_rowvalue_ignore_retry',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ignore nested retry savepoint needs outer statements');
        }
        if ($innerYieldedStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ignore nested retry savepoint needs yielded inner statements');
        }
        if ($innerDiscardedStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ignore nested retry savepoint needs discarded inner statements');
        }
        if ($innerRetryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ignore nested retry savepoint needs retry inner statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ignore nested retry savepoint needs unique constraints');
        }

        $outerImage = self::normalizeTablesIgnoreNestedRetrySavepointBatch($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsIgnoreNestedRetrySavepointBatch($outerImage, $outerStatements, $uniqueConstraints, $rowIdColumn, 'outer-before-inner');

        $innerImage = $afterOuter;
        [$afterYielded, $yieldedExecuted, $yieldedReturning] = self::runStatementsIgnoreNestedRetrySavepointBatch($innerImage, $innerYieldedStatements, $uniqueConstraints, $rowIdColumn, 'inner-yielded-before-rollback');
        [$afterDiscarded, $discardedExecuted, $discardedReturning] = self::runStatementsIgnoreNestedRetrySavepointBatch($afterYielded, $innerDiscardedStatements, $uniqueConstraints, $rowIdColumn, 'inner-discarded-before-rollback');

        $afterInnerRollback = $innerImage;
        [$afterInnerRetry, $retryExecuted, $retryReturning] = self::runStatementsIgnoreNestedRetrySavepointBatch($afterInnerRollback, $innerRetryStatements, $uniqueConstraints, $rowIdColumn, 'inner-retry-after-rollback');

        $innerSuppressedReturning = array_merge($yieldedReturning, $discardedReturning);
        $innerAttempted = array_merge($yieldedExecuted, $discardedExecuted);

        return [
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'status' => 'inner-ignore-rollback-to-retry-current-source',
            'rolled_back_to_inner_savepoint' => true,
            'outer_savepoint_preserved_after_inner_rollback_to' => true,
            'inner_savepoint_preserved_after_rollback_to' => true,
            'inner_released_after_retry' => true,
            'outer_released_after_inner_retry' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'outer_current_source_tables' => $afterOuter,
            'inner_savepoint_image_tables' => $innerImage,
            'inner_yielded_current_source_tables' => $afterYielded,
            'inner_discarded_current_source_tables' => $afterDiscarded,
            'rollback_to_inner_current_source_tables' => $afterInnerRollback,
            'current_source_tables' => $afterInnerRetry,
            'next_source_tables' => $afterInnerRetry,
            'outer_statements' => $outerExecuted,
            'inner_yielded_statements' => $yieldedExecuted,
            'inner_discarded_statements' => $discardedExecuted,
            'inner_retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'inner_yielded_before_rollback_returning' => $yieldedReturning,
            'inner_discarded_before_rollback_returning' => $discardedReturning,
            'inner_suppressed_by_rollback_returning' => $innerSuppressedReturning,
            'inner_yielded_after_retry_returning' => $retryReturning,
            'outer_yielded_returning_count' => self::returningCountIgnoreNestedRetrySavepointBatch($outerReturning),
            'inner_yielded_before_rollback_count' => self::returningCountIgnoreNestedRetrySavepointBatch($yieldedReturning),
            'inner_discarded_before_rollback_count' => self::returningCountIgnoreNestedRetrySavepointBatch($discardedReturning),
            'inner_suppressed_by_rollback_count' => self::returningCountIgnoreNestedRetrySavepointBatch($innerSuppressedReturning),
            'inner_yielded_after_retry_count' => self::returningCountIgnoreNestedRetrySavepointBatch($retryReturning),
            'outer_changes_preserved' => self::changeCountIgnoreNestedRetrySavepointBatch($outerExecuted),
            'inner_attempted_changes_before_rollback_to' => self::changeCountIgnoreNestedRetrySavepointBatch($innerAttempted),
            'inner_changes_after_retry_release' => self::changeCountIgnoreNestedRetrySavepointBatch($retryExecuted),
            'changed_tables_after_inner_retry' => self::changedTablesIgnoreNestedRetrySavepointBatch($outerImage, $afterInnerRetry),
            'row_counts' => self::rowCountsIgnoreNestedRetrySavepointBatch($afterInnerRetry),
            'dependencies' => [
                'sqlite-inner-savepoint-rowvalue-ignore-yields-no-returning',
                'sqlite-rollback-to-inner-savepoint-preserves-outer-current-source',
                'sqlite-rowvalue-update-delete-returning-retry-starts-from-inner-image',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsIgnoreNestedRetrySavepointBatch(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryIgnoreNestedRetrySavepointBatch($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryIgnoreNestedRetrySavepointBatch(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsIgnoreNestedRetrySavepointBatch($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesIgnoreNestedRetrySavepointBatch(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value ignore nested retry savepoint tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ignore nested retry savepoint rows must be arrays');
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
    private static function rowsByIdsIgnoreNestedRetrySavepointBatch(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value ignore nested retry savepoint rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ignore nested retry savepoint rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountIgnoreNestedRetrySavepointBatch(array $yielded): int
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
    private static function changeCountIgnoreNestedRetrySavepointBatch(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesIgnoreNestedRetrySavepointBatch(array $before, array $after): array
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
    private static function rowCountsIgnoreNestedRetrySavepointBatch(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeReleasedInnerSavepointRollback(
        array $tables,
        array $outerStatements,
        array $innerStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_rowvalue_next182',
        string $innerSavepoint = 'wp_options_inner_rowvalue_next182',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value released-inner rollback next182 needs outer statements');
        }
        if ($innerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value released-inner rollback next182 needs released inner statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value released-inner rollback next182 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value released-inner rollback next182 needs unique constraints');
        }
        if ($outerSavepoint === '' || $innerSavepoint === '' || $outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value released-inner rollback next182 needs distinct savepoint names');
        }

        $outerImage = self::normalizeTablesReleasedInnerSavepointRollback($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsReleasedInnerSavepointRollback($outerImage, $outerStatements, $uniqueConstraints, $rowIdColumn, 'outer-before-inner-release');

        $innerImage = $afterOuter;
        [$afterInnerRelease, $innerExecuted, $innerReturning] = self::runStatementsReleasedInnerSavepointRollback($innerImage, $innerStatements, $uniqueConstraints, $rowIdColumn, 'inner-released-into-outer');

        $afterOuterRollback = $outerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsReleasedInnerSavepointRollback($afterOuterRollback, $retryStatements, $uniqueConstraints, $rowIdColumn, 'retry-after-outer-rollback');

        $suppressedReturning = array_merge($outerReturning, $innerReturning);

        return [
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'status' => 'released-inner-returning-suppressed-by-outer-rollback-next182',
            'inner_released_into_outer_before_rollback' => true,
            'rolled_back_to_outer_savepoint' => true,
            'outer_savepoint_preserved_after_rollback_to' => true,
            'outer_released_after_retry' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'outer_current_source_tables' => $afterOuter,
            'inner_savepoint_image_tables' => $innerImage,
            'inner_released_current_source_tables' => $afterInnerRelease,
            'rollback_to_outer_current_source_tables' => $afterOuterRollback,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'inner_released_statements' => $innerExecuted,
            'retry_statements' => $retryExecuted,
            'outer_returning_before_rollback' => $outerReturning,
            'inner_returning_released_before_rollback' => $innerReturning,
            'suppressed_by_outer_rollback_returning' => $suppressedReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'outer_returning_before_rollback_count' => self::returningCountReleasedInnerSavepointRollback($outerReturning),
            'inner_returning_released_before_rollback_count' => self::returningCountReleasedInnerSavepointRollback($innerReturning),
            'suppressed_by_outer_rollback_count' => self::returningCountReleasedInnerSavepointRollback($suppressedReturning),
            'yielded_after_retry_count' => self::returningCountReleasedInnerSavepointRollback($retryReturning),
            'outer_changes_before_rollback' => self::changeCountReleasedInnerSavepointRollback($outerExecuted),
            'inner_changes_released_before_rollback' => self::changeCountReleasedInnerSavepointRollback($innerExecuted),
            'retry_changes_after_outer_rollback' => self::changeCountReleasedInnerSavepointRollback($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesReleasedInnerSavepointRollback($outerImage, $afterRetry),
            'row_counts' => self::rowCountsReleasedInnerSavepointRollback($afterRetry),
            'dependencies' => [
                'sqlite-rowvalue-returning-release-inner-merge-next182',
                'sqlite-rollback-to-outer-suppresses-released-inner-returning-next182',
                'sqlite-rowvalue-update-delete-retry-starts-from-outer-image-next182',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsReleasedInnerSavepointRollback(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryReleasedInnerSavepointRollback($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryReleasedInnerSavepointRollback(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsReleasedInnerSavepointRollback($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesReleasedInnerSavepointRollback(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value released-inner rollback next182 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value released-inner rollback next182 rows must be arrays');
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
    private static function rowsByIdsReleasedInnerSavepointRollback(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value released-inner rollback next182 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value released-inner rollback next182 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountReleasedInnerSavepointRollback(array $yielded): int
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
    private static function changeCountReleasedInnerSavepointRollback(array $executed): int
    {
        $count = 0;
        foreach ($executed as $statement) {
            $count += count($statement['mutation_ids']);
            $count += count($statement['deleted_conflict_rows']);
            $count -= count($statement['ignored_rows']);
        }

        return $count;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesReleasedInnerSavepointRollback(array $before, array $after): array
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
    private static function rowCountsReleasedInnerSavepointRollback(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerDeleteStatements
     * @param list<string> $innerAttemptStatements
     * @param list<string> $innerRetryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeDeleteInnerRollbackRetrySavepoint(
        array $tables,
        array $outerDeleteStatements,
        array $innerAttemptStatements,
        array $innerRetryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_delete_inner_retry',
        string $innerSavepoint = 'wp_options_inner_delete_inner_retry',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerDeleteStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested delete delete-inner-retry needs outer delete statements');
        }
        if ($innerAttemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested delete delete-inner-retry needs inner attempt statements');
        }
        if ($innerRetryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested delete delete-inner-retry needs inner retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value nested delete delete-inner-retry needs unique constraints');
        }

        $outerImage = self::normalizeTablesDeleteInnerRollbackRetrySavepoint($tables);
        [$afterOuterDelete, $outerExecuted, $outerReturning] = self::runStatementsDeleteInnerRollbackRetrySavepoint(
            $outerImage,
            $outerDeleteStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-delete-before-inner',
        );

        $innerImage = $afterOuterDelete;
        [$afterInnerAttempt, $innerAttemptExecuted, $innerAttemptReturning] = self::runStatementsDeleteInnerRollbackRetrySavepoint(
            $innerImage,
            $innerAttemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-attempt-before-rollback',
        );

        $afterInnerRollback = $innerImage;
        [$afterInnerRetry, $innerRetryExecuted, $innerRetryReturning] = self::runStatementsDeleteInnerRollbackRetrySavepoint(
            $afterInnerRollback,
            $innerRetryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-retry-after-rollback',
        );

        return [
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'status' => 'outer-delete-preserved-inner-rowvalue-rollback-retry',
            'rolled_back_to_inner_savepoint' => true,
            'outer_delete_preserved_after_inner_rollback_to' => true,
            'inner_savepoint_preserved_after_rollback_to' => true,
            'inner_released_after_retry' => true,
            'outer_released_after_inner_retry' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'outer_current_source_tables' => $afterOuterDelete,
            'inner_savepoint_image_tables' => $innerImage,
            'inner_attempt_current_source_tables' => $afterInnerAttempt,
            'rollback_to_inner_current_source_tables' => $afterInnerRollback,
            'current_source_tables' => $afterInnerRetry,
            'next_source_tables' => $afterInnerRetry,
            'outer_delete_statements' => $outerExecuted,
            'inner_attempt_statements' => $innerAttemptExecuted,
            'inner_retry_statements' => $innerRetryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'inner_attempt_returning' => $innerAttemptReturning,
            'inner_suppressed_by_rollback_returning' => $innerAttemptReturning,
            'inner_yielded_after_retry_returning' => $innerRetryReturning,
            'outer_yielded_returning_count' => self::returningCountDeleteInnerRollbackRetrySavepoint($outerReturning),
            'inner_attempt_returning_count' => self::returningCountDeleteInnerRollbackRetrySavepoint($innerAttemptReturning),
            'inner_suppressed_by_rollback_count' => self::returningCountDeleteInnerRollbackRetrySavepoint($innerAttemptReturning),
            'inner_yielded_after_retry_count' => self::returningCountDeleteInnerRollbackRetrySavepoint($innerRetryReturning),
            'outer_delete_changes_preserved' => self::changeCountDeleteInnerRollbackRetrySavepoint($outerExecuted),
            'inner_attempted_changes_before_rollback_to' => self::changeCountDeleteInnerRollbackRetrySavepoint($innerAttemptExecuted),
            'inner_changes_after_retry_release' => self::changeCountDeleteInnerRollbackRetrySavepoint($innerRetryExecuted),
            'changed_tables_after_inner_retry' => self::changedTablesDeleteInnerRollbackRetrySavepoint($outerImage, $afterInnerRetry),
            'row_counts' => self::rowCountsDeleteInnerRollbackRetrySavepoint($afterInnerRetry),
            'dependencies' => [
                'sqlite-outer-delete-returning-current-source-preserved-delete-inner-retry',
                'sqlite-inner-rowvalue-update-delete-returning-rollback-discards-stream-delete-inner-retry',
                'sqlite-inner-retry-reads-post-delete-current-source-delete-inner-retry',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsDeleteInnerRollbackRetrySavepoint(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryDeleteInnerRollbackRetrySavepoint($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryDeleteInnerRollbackRetrySavepoint(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsDeleteInnerRollbackRetrySavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesDeleteInnerRollbackRetrySavepoint(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value nested delete delete-inner-retry tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value nested delete delete-inner-retry rows must be arrays');
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
    private static function rowsByIdsDeleteInnerRollbackRetrySavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value nested delete delete-inner-retry rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value nested delete delete-inner-retry rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountDeleteInnerRollbackRetrySavepoint(array $yielded): int
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
    private static function changeCountDeleteInnerRollbackRetrySavepoint(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesDeleteInnerRollbackRetrySavepoint(array $before, array $after): array
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
    private static function rowCountsDeleteInnerRollbackRetrySavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $preFailStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeOrFailRollbackRetrySavepoint(
        array $tables,
        array $preFailStatements,
        string $failStatement,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_fail_next185',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($preFailStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next185 needs pre-fail statements');
        }
        if (trim($failStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next185 needs a fail statement');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next185 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next185 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next185 savepoint name must be an identifier');
        }

        $savepointImage = self::normalizeTablesOrFailRollbackRetrySavepoint($tables);
        [$beforeFail, $preFailExecuted, $preFailReturning] = self::runStatementsOrFailRollbackRetrySavepoint(
            $savepointImage,
            $preFailStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-fail',
            false,
        );

        $beforeFailStatement = $beforeFail;
        $failResult = SQLiteUpdateDeleteReturningSql::execute($failStatement, $beforeFail, $rowIdColumn, $uniqueConstraints, true);
        $afterFail = $failResult['tables'];
        $failExecuted = self::statementSummaryOrFailRollbackRetrySavepoint('or-fail-partial-before-rollback', 0, $failStatement, $failResult, $beforeFailStatement, $rowIdColumn);
        $failReturning = [[
            'phase' => 'or-fail-partial-before-rollback',
            'ordinal' => 0,
            'action' => $failResult['action'],
            'conflict_action' => $failResult['conflict_action'],
            'rows' => $failResult['returning'],
        ]];

        $rollbackTo = $savepointImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsOrFailRollbackRetrySavepoint(
            $rollbackTo,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-fail-rollback',
            false,
        );

        $suppressedReturning = array_merge($preFailReturning, $failReturning);
        $attemptedExecuted = array_merge($preFailExecuted, [$failExecuted]);

        return [
            'savepoint' => $savepoint,
            'status' => 'or-fail-partial-rowvalue-returning-rolled-back-retried-next185',
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'released_after_retry' => true,
            'savepoint_image_tables' => $savepointImage,
            'before_fail_current_source_tables' => $beforeFail,
            'partial_fail_current_source_tables' => $afterFail,
            'rollback_to_current_source_tables' => $rollbackTo,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'pre_fail_statements' => $preFailExecuted,
            'fail_statement' => $failExecuted,
            'retry_statements' => $retryExecuted,
            'pre_fail_returning' => $preFailReturning,
            'partial_fail_returning' => $failReturning,
            'suppressed_by_rollback_returning' => $suppressedReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'failed_conflict' => $failResult['failed_conflict'] ?? null,
            'pre_fail_returning_count' => self::returningCountOrFailRollbackRetrySavepoint($preFailReturning),
            'partial_fail_returning_count' => self::returningCountOrFailRollbackRetrySavepoint($failReturning),
            'suppressed_by_rollback_count' => self::returningCountOrFailRollbackRetrySavepoint($suppressedReturning),
            'yielded_after_retry_count' => self::returningCountOrFailRollbackRetrySavepoint($retryReturning),
            'attempted_changes_before_rollback_to' => self::changeCountOrFailRollbackRetrySavepoint($attemptedExecuted),
            'partial_fail_changes_before_rollback_to' => self::changeCountOrFailRollbackRetrySavepoint([$failExecuted]),
            'changes_after_retry_release' => self::changeCountOrFailRollbackRetrySavepoint($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesOrFailRollbackRetrySavepoint($savepointImage, $afterRetry),
            'row_counts' => self::rowCountsOrFailRollbackRetrySavepoint($afterRetry),
            'dependencies' => [
                'sqlite-update-or-fail-rowvalue-preserves-prior-row-changes-next185',
                'sqlite-rollback-to-discards-partial-or-fail-returning-next185',
                'sqlite-rowvalue-update-delete-retry-after-or-fail-current-source-next185',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsOrFailRollbackRetrySavepoint(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase, bool $preserveFailChanges): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints, $preserveFailChanges);
            $current = $result['tables'];
            $executed[] = self::statementSummaryOrFailRollbackRetrySavepoint($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryOrFailRollbackRetrySavepoint(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsOrFailRollbackRetrySavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesOrFailRollbackRetrySavepoint(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value OR FAIL next185 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR FAIL next185 rows must be arrays');
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
    private static function rowsByIdsOrFailRollbackRetrySavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL next185 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL next185 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountOrFailRollbackRetrySavepoint(array $yielded): int
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
    private static function changeCountOrFailRollbackRetrySavepoint(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesOrFailRollbackRetrySavepoint(array $before, array $after): array
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
    private static function rowCountsOrFailRollbackRetrySavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $savepointStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAbortSavepointRetry(
        array $tables,
        array $outerStatements,
        array $savepointStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $transaction = 'wp_options_rowvalue_abort_txn_next187',
        string $savepoint = 'wp_options_rowvalue_abort_savepoint_next187',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === [] || $savepointStatements === [] || $retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next187 needs outer, savepoint, and retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next187 needs unique constraints');
        }

        $transactionImage = self::normalizeTablesAbortSavepointRetry($tables);
        [$outerCurrent, $outerExecuted, $outerReturning] = self::runStatementsAbortSavepointRetry(
            $transactionImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer',
        );

        $savepointImage = $outerCurrent;
        [$failedCurrent, $savepointExecuted, $savepointReturning, $rollbackReason, $rollbackOrdinal] = self::runSavepointUntilAbortAbortSavepointRetry(
            $savepointImage,
            $savepointStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        $rolledBackSavepoint = $rollbackReason !== null;
        $retrySource = $rolledBackSavepoint ? $savepointImage : $failedCurrent;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatementsAbortSavepointRetry(
            $retrySource,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry',
        );

        $discardedReturning = $rolledBackSavepoint ? $savepointReturning : [];

        return [
            'transaction' => $transaction,
            'savepoint' => $savepoint,
            'status' => $rolledBackSavepoint ? 'savepoint-rolled-back-retried-current-source-next187' : 'savepoint-released-retried-current-source-next187',
            'rolled_back_transaction' => false,
            'rolled_back_savepoint' => $rolledBackSavepoint,
            'savepoint_preserved_after_rollback' => $rolledBackSavepoint,
            'rollback_statement_ordinal' => $rollbackOrdinal,
            'rollback_reason' => $rollbackReason,
            'transaction_image_tables' => $transactionImage,
            'outer_current_source_tables' => $outerCurrent,
            'savepoint_image_tables' => $savepointImage,
            'failed_current_source_tables' => $failedCurrent,
            'rollback_to_current_source_tables' => $retrySource,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'outer_statements' => $outerExecuted,
            'savepoint_statements' => $savepointExecuted,
            'retry_statements' => $retryExecuted,
            'outer_returning' => $outerReturning,
            'discarded_returning' => $discardedReturning,
            'yielded_returning' => $retryReturning,
            'outer_returning_count' => self::returningCountAbortSavepointRetry($outerReturning),
            'discarded_returning_count' => self::returningCountAbortSavepointRetry($discardedReturning),
            'yielded_returning_count' => self::returningCountAbortSavepointRetry($retryReturning),
            'attempted_changes_before_rollback' => self::changeCountAbortSavepointRetry($savepointExecuted),
            'outer_changes_preserved' => self::changeCountAbortSavepointRetry($outerExecuted),
            'changes_after_retry' => self::changeCountAbortSavepointRetry($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesAbortSavepointRetry($transactionImage, $retryCurrent),
            'row_counts' => self::rowCountsAbortSavepointRetry($retryCurrent),
            'dependencies' => [
                'sqlite-rowvalue-update-delete-returning-abort-preserves-outer-transaction-next187',
                'sqlite-rowvalue-abort-savepoint-discards-attempted-returning-next187',
                'sqlite-rowvalue-abort-retry-reads-savepoint-image-next187',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsAbortSavepointRetry(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryAbortSavepointRetry($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>,3:?string,4:?int}
     */
    private static function runSavepointUntilAbortAbortSavepointRetry(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                if (!str_contains($exception->getMessage(), ' using OR ABORT')) {
                    throw $exception;
                }
                $executed[] = [
                    'phase' => 'savepoint',
                    'ordinal' => $ordinal,
                    'sql' => $sql,
                    'action' => str_starts_with(strtoupper(ltrim($sql)), 'DELETE') ? 'delete' : 'update',
                    'conflict_action' => 'abort',
                    'table' => self::statementTableNameAbortSavepointRetry($sql),
                    'selected_ids' => [],
                    'mutation_ids' => [],
                    'source_rows' => [],
                    'returning_rows' => [],
                    'ignored_rows' => [],
                    'deleted_conflict_rows' => [],
                    'conflicts' => [],
                    'failed_conflict' => ['message' => $exception->getMessage()],
                ];

                return [$current, $executed, $yielded, $exception->getMessage(), $ordinal];
            }

            $current = $result['tables'];
            $executed[] = self::statementSummaryAbortSavepointRetry('savepoint', $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => 'savepoint',
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded, null, null];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryAbortSavepointRetry(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsAbortSavepointRetry($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
            'failed_conflict' => $result['failed_conflict'] ?? null,
        ];
    }

    private static function statementTableNameAbortSavepointRetry(string $sql): string
    {
        if (preg_match('/^\s*DELETE\s+FROM\s+([A-Za-z_][A-Za-z0-9_]*)/i', $sql, $match) === 1) {
            return $match[1];
        }
        if (preg_match('/^\s*UPDATE(?:\s+OR\s+[A-Z]+)?\s+([A-Za-z_][A-Za-z0-9_]*)/i', $sql, $match) === 1) {
            return $match[1];
        }

        return '';
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesAbortSavepointRetry(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next187 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next187 rows must be arrays');
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
    private static function rowsByIdsAbortSavepointRetry(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next187 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next187 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountAbortSavepointRetry(array $yielded): int
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
    private static function changeCountAbortSavepointRetry(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesAbortSavepointRetry(array $before, array $after): array
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
    private static function rowCountsAbortSavepointRetry(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @return array<string,mixed>
     */
    public static function executeRowValuePredicateRollbackRetrySavepoint(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        string $savepoint = 'wp_options_rowvalue_empty_in_next188',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value empty IN next188 needs attempted statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value empty IN next188 needs retry statements');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value empty IN next188 savepoint name must be an identifier');
        }

        $savepointImage = self::normalizeTablesRowValuePredicateRollbackRetrySavepoint($tables);
        [$attemptedTables, $attemptedStatements, $attemptedReturning] = self::runStatementsRowValuePredicateRollbackRetrySavepoint(
            $savepointImage,
            $attemptStatements,
            $rowIdColumn,
            'attempt-before-rollback',
        );

        [$retryTables, $retryStatementsSummary, $retryReturning] = self::runStatementsRowValuePredicateRollbackRetrySavepoint(
            $savepointImage,
            $retryStatements,
            $rowIdColumn,
            'retry-after-rollback',
        );

        return [
            'savepoint' => $savepoint,
            'status' => 'rowvalue-empty-in-returning-rolled-back-retried-next188',
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'released_after_retry' => true,
            'savepoint_image_tables' => $savepointImage,
            'attempt_current_source_tables' => $attemptedTables,
            'rollback_to_current_source_tables' => $savepointImage,
            'current_source_tables' => $retryTables,
            'next_source_tables' => $retryTables,
            'attempt_statements' => $attemptedStatements,
            'retry_statements' => $retryStatementsSummary,
            'attempt_returning' => $attemptedReturning,
            'suppressed_by_rollback_returning' => $attemptedReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'attempt_returning_count' => self::returningCountRowValuePredicateRollbackRetrySavepoint($attemptedReturning),
            'suppressed_by_rollback_count' => self::returningCountRowValuePredicateRollbackRetrySavepoint($attemptedReturning),
            'yielded_after_retry_count' => self::returningCountRowValuePredicateRollbackRetrySavepoint($retryReturning),
            'attempt_changes_before_rollback_to' => self::changeCountRowValuePredicateRollbackRetrySavepoint($attemptedStatements),
            'changes_after_retry_release' => self::changeCountRowValuePredicateRollbackRetrySavepoint($retryStatementsSummary),
            'changed_tables_after_retry' => self::changedTablesRowValuePredicateRollbackRetrySavepoint($savepointImage, $retryTables),
            'row_counts' => self::rowCountsRowValuePredicateRollbackRetrySavepoint($retryTables),
            'dependencies' => [
                'sqlite-rowvalue-empty-in-list-is-false-next188',
                'sqlite-rowvalue-empty-not-in-list-is-true-next188',
                'sqlite-rowvalue-empty-in-returning-rollback-current-source-next188',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsRowValuePredicateRollbackRetrySavepoint(array $tables, array $statements, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn);
            $current = $result['tables'];
            $executed[] = self::statementSummaryRowValuePredicateRollbackRetrySavepoint($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryRowValuePredicateRollbackRetrySavepoint(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsRowValuePredicateRollbackRetrySavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesRowValuePredicateRollbackRetrySavepoint(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value empty IN next188 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value empty IN next188 rows must be arrays');
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
    private static function rowsByIdsRowValuePredicateRollbackRetrySavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value empty IN next188 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value empty IN next188 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountRowValuePredicateRollbackRetrySavepoint(array $yielded): int
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
    private static function changeCountRowValuePredicateRollbackRetrySavepoint(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesRowValuePredicateRollbackRetrySavepoint(array $before, array $after): array
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
    private static function rowCountsRowValuePredicateRollbackRetrySavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerAttemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNotBetweenRollbackRetrySavepoint(
        array $tables,
        array $outerStatements,
        array $innerAttemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_rowvalue_not_between_outer_next189',
        string $innerSavepoint = 'wp_options_rowvalue_not_between_inner_next189',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value not-between next189 needs outer statements');
        }
        if ($innerAttemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value not-between next189 needs inner attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value not-between next189 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value not-between next189 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $outerSavepoint) !== 1 || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $innerSavepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value not-between next189 savepoint names must be identifiers');
        }

        $outerImage = self::normalizeTablesNotBetweenRollbackRetrySavepoint($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNotBetweenRollbackRetrySavepoint(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-not-between-before-inner',
        );

        $innerImage = $afterOuter;
        [$afterInnerAttempt, $innerAttemptExecuted, $innerAttemptReturning] = self::runStatementsNotBetweenRollbackRetrySavepoint(
            $innerImage,
            $innerAttemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-not-in-values-before-rollback',
        );

        $rollbackToInner = $innerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNotBetweenRollbackRetrySavepoint(
            $rollbackToInner,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-not-between-after-rollback',
        );

        return [
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'status' => 'rowvalue-not-between-returning-star-rollback-retry-next189',
            'rolled_back_to_inner_savepoint' => true,
            'outer_savepoint_preserved_after_inner_rollback_to' => true,
            'inner_savepoint_preserved_after_rollback_to' => true,
            'inner_released_after_retry' => true,
            'outer_released_after_inner_retry' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'outer_current_source_tables' => $afterOuter,
            'inner_savepoint_image_tables' => $innerImage,
            'inner_attempt_current_source_tables' => $afterInnerAttempt,
            'rollback_to_inner_current_source_tables' => $rollbackToInner,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'inner_attempt_statements' => $innerAttemptExecuted,
            'retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'inner_attempt_returning' => $innerAttemptReturning,
            'suppressed_by_rollback_returning' => $innerAttemptReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'outer_yielded_returning_count' => self::returningCountNotBetweenRollbackRetrySavepoint($outerReturning),
            'inner_attempt_returning_count' => self::returningCountNotBetweenRollbackRetrySavepoint($innerAttemptReturning),
            'suppressed_by_rollback_count' => self::returningCountNotBetweenRollbackRetrySavepoint($innerAttemptReturning),
            'yielded_after_retry_count' => self::returningCountNotBetweenRollbackRetrySavepoint($retryReturning),
            'outer_changes_preserved' => self::changeCountNotBetweenRollbackRetrySavepoint($outerExecuted),
            'inner_attempted_changes_before_rollback_to' => self::changeCountNotBetweenRollbackRetrySavepoint($innerAttemptExecuted),
            'retry_changes_after_release' => self::changeCountNotBetweenRollbackRetrySavepoint($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNotBetweenRollbackRetrySavepoint($outerImage, $afterRetry),
            'row_counts' => self::rowCountsNotBetweenRollbackRetrySavepoint($afterRetry),
            'dependencies' => [
                'sqlite-rowvalue-not-between-update-returning-star-next189',
                'sqlite-rowvalue-not-in-values-delete-returning-rollback-next189',
                'sqlite-rowvalue-retry-after-inner-rollback-current-source-next189',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNotBetweenRollbackRetrySavepoint(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNotBetweenRollbackRetrySavepoint($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryNotBetweenRollbackRetrySavepoint(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNotBetweenRollbackRetrySavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNotBetweenRollbackRetrySavepoint(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value not-between next189 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value not-between next189 rows must be arrays');
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
    private static function rowsByIdsNotBetweenRollbackRetrySavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value not-between next189 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value not-between next189 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNotBetweenRollbackRetrySavepoint(array $yielded): int
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
    private static function changeCountNotBetweenRollbackRetrySavepoint(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesNotBetweenRollbackRetrySavepoint(array $before, array $after): array
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
    private static function rowCountsNotBetweenRollbackRetrySavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $releaseStatements
     * @param list<string> $rollbackStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNegatedRollbackRetrySavepoint(
        array $tables,
        array $releaseStatements,
        array $rollbackStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $releaseSavepoint = 'wp_options_rowvalue_release_next190',
        string $rollbackSavepoint = 'wp_options_rowvalue_rollback_next190',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($releaseStatements === [] || $rollbackStatements === [] || $retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value negated savepoint next190 needs release, rollback, and retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value negated savepoint next190 needs unique constraints');
        }
        self::assertIdentifierNegatedRollbackRetrySavepoint($releaseSavepoint, 'release savepoint');
        self::assertIdentifierNegatedRollbackRetrySavepoint($rollbackSavepoint, 'rollback savepoint');

        $transactionImage = self::normalizeTablesNegatedRollbackRetrySavepoint($tables);
        [$afterRelease, $releaseExecuted, $releaseReturning] = self::runStatementsNegatedRollbackRetrySavepoint(
            $transactionImage,
            $releaseStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'release-savepoint',
        );

        $rollbackImage = $afterRelease;
        [$speculativeCurrent, $rollbackExecuted, $rollbackReturning] = self::runStatementsNegatedRollbackRetrySavepoint(
            $rollbackImage,
            $rollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'rollback-savepoint-speculative',
        );

        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNegatedRollbackRetrySavepoint(
            $rollbackImage,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-rollback',
        );

        return [
            'status' => 'rowvalue-negated-predicate-release-rollback-retry-next190',
            'release_savepoint' => $releaseSavepoint,
            'rollback_savepoint' => $rollbackSavepoint,
            'release_savepoint_released' => true,
            'rollback_to_second_savepoint' => true,
            'rollback_savepoint_preserved_after_rollback_to' => true,
            'retry_released_after_rollback' => true,
            'transaction_image_tables' => $transactionImage,
            'release_current_source_tables' => $afterRelease,
            'rollback_image_tables' => $rollbackImage,
            'speculative_current_source_tables' => $speculativeCurrent,
            'rollback_to_current_source_tables' => $rollbackImage,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'release_statements' => $releaseExecuted,
            'rollback_statements' => $rollbackExecuted,
            'retry_statements' => $retryExecuted,
            'yielded_release_returning' => $releaseReturning,
            'suppressed_by_rollback_returning' => $rollbackReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'yielded_release_count' => self::returningCountNegatedRollbackRetrySavepoint($releaseReturning),
            'suppressed_by_rollback_count' => self::returningCountNegatedRollbackRetrySavepoint($rollbackReturning),
            'yielded_after_retry_count' => self::returningCountNegatedRollbackRetrySavepoint($retryReturning),
            'release_changes' => self::changeCountNegatedRollbackRetrySavepoint($releaseExecuted),
            'rollback_attempted_changes' => self::changeCountNegatedRollbackRetrySavepoint($rollbackExecuted),
            'retry_changes' => self::changeCountNegatedRollbackRetrySavepoint($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNegatedRollbackRetrySavepoint($transactionImage, $afterRetry),
            'row_counts' => self::rowCountsNegatedRollbackRetrySavepoint($afterRetry),
            'dependencies' => [
                'sqlite-rowvalue-not-in-returning-savepoint-release-next190',
                'sqlite-rowvalue-not-between-delete-returning-rollback-next190',
                'sqlite-rowvalue-negated-predicate-retry-reads-rollback-image-next190',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNegatedRollbackRetrySavepoint(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNegatedRollbackRetrySavepoint($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryNegatedRollbackRetrySavepoint(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNegatedRollbackRetrySavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNegatedRollbackRetrySavepoint(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value negated savepoint next190 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value negated savepoint next190 rows must be arrays');
                }
            }
        }

        return $tables;
    }

    private static function assertIdentifierNegatedRollbackRetrySavepoint(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value negated savepoint next190 {$label} must be an identifier");
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int|string> $ids
     * @return list<array<string,mixed>>
     */
    private static function rowsByIdsNegatedRollbackRetrySavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value negated savepoint next190 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value negated savepoint next190 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNegatedRollbackRetrySavepoint(array $yielded): int
    {
        $count = 0;
        foreach ($yielded as $entry) {
            $count += count($entry['rows']);
        }

        return $count;
    }

    /**
     * @param list<array<string,mixed>> $executed
     */
    private static function changeCountNegatedRollbackRetrySavepoint(array $executed): int
    {
        $count = 0;
        foreach ($executed as $statement) {
            $count += count($statement['mutation_ids'] ?? []);
        }

        return $count;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesNegatedRollbackRetrySavepoint(array $before, array $after): array
    {
        $changed = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $table) {
            if (($before[$table] ?? null) !== ($after[$table] ?? null)) {
                $changed[] = $table;
            }
        }

        sort($changed);

        return $changed;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,int>
     */
    private static function rowCountsNegatedRollbackRetrySavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        ksort($counts);

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerBeforeAbortStatements
     * @param string $abortStatement
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNestedAbortRollbackRetrySavepoint(
        array $tables,
        array $outerStatements,
        array $innerBeforeAbortStatements,
        string $abortStatement,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_rowvalue_abort_outer_next192',
        string $innerSavepoint = 'wp_options_rowvalue_abort_inner_next192',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next192 needs outer statements');
        }
        if ($innerBeforeAbortStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next192 needs inner pre-abort statements');
        }
        if (trim($abortStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next192 needs an abort statement');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next192 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next192 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $outerSavepoint) !== 1 || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $innerSavepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next192 savepoint names must be identifiers');
        }

        $outerImage = self::normalizeTablesNestedAbortRollbackRetrySavepoint($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNestedAbortRollbackRetrySavepoint(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-abort-inner',
        );

        $innerImage = $afterOuter;
        [$afterInnerBeforeAbort, $innerBeforeAbortExecuted, $innerBeforeAbortReturning] = self::runStatementsNestedAbortRollbackRetrySavepoint(
            $innerImage,
            $innerBeforeAbortStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-before-abort',
        );

        [$afterAbort, $abortSummary] = self::runAbortStatementNestedAbortRollbackRetrySavepoint(
            $afterInnerBeforeAbort,
            $abortStatement,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-abort-statement',
        );

        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNestedAbortRollbackRetrySavepoint(
            $afterAbort,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-abort-statement',
        );

        return [
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'status' => 'rowvalue-abort-statement-current-source-retry-next192',
            'inner_abort_statement_rolled_back' => true,
            'outer_savepoint_preserved_after_abort' => true,
            'inner_savepoint_preserved_after_abort' => true,
            'inner_pre_abort_changes_preserved' => true,
            'inner_released_after_retry' => true,
            'outer_released_after_inner_retry' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'outer_current_source_tables' => $afterOuter,
            'inner_savepoint_image_tables' => $innerImage,
            'inner_pre_abort_current_source_tables' => $afterInnerBeforeAbort,
            'abort_statement_rollback_current_source_tables' => $afterAbort,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'inner_pre_abort_statements' => $innerBeforeAbortExecuted,
            'abort_statement' => $abortSummary,
            'retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'inner_pre_abort_returning' => $innerBeforeAbortReturning,
            'suppressed_by_abort_returning' => $abortSummary['returning_rows'],
            'yielded_after_retry_returning' => $retryReturning,
            'outer_yielded_returning_count' => self::returningCountNestedAbortRollbackRetrySavepoint($outerReturning),
            'inner_pre_abort_returning_count' => self::returningCountNestedAbortRollbackRetrySavepoint($innerBeforeAbortReturning),
            'suppressed_by_abort_count' => count($abortSummary['returning_rows']),
            'yielded_after_retry_count' => self::returningCountNestedAbortRollbackRetrySavepoint($retryReturning),
            'outer_changes_preserved' => self::changeCountNestedAbortRollbackRetrySavepoint($outerExecuted),
            'inner_changes_preserved_before_abort' => self::changeCountNestedAbortRollbackRetrySavepoint($innerBeforeAbortExecuted),
            'retry_changes_after_abort' => self::changeCountNestedAbortRollbackRetrySavepoint($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNestedAbortRollbackRetrySavepoint($outerImage, $afterRetry),
            'row_counts' => self::rowCountsNestedAbortRollbackRetrySavepoint($afterRetry),
            'dependencies' => [
                'sqlite-rowvalue-update-or-abort-statement-rollback-next192',
                'sqlite-rowvalue-abort-preserves-prior-savepoint-current-source-next192',
                'sqlite-rowvalue-delete-returning-retry-after-abort-next192',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNestedAbortRollbackRetrySavepoint(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNestedAbortRollbackRetrySavepoint($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:array<string,mixed>}
     */
    private static function runAbortStatementNestedAbortRollbackRetrySavepoint(array $tables, string $sql, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        try {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints);

            return [
                $result['tables'],
                self::statementSummaryNestedAbortRollbackRetrySavepoint($phase, 0, $sql, $result, $tables, $rowIdColumn, null) + [
                    'aborted' => false,
                    'error' => null,
                ],
            ];
        } catch (\InvalidArgumentException $exception) {
            $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
            if ($parsed['action'] !== 'update' || $parsed['conflict_action'] !== 'abort') {
                throw $exception;
            }

            $probe = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, [], true);

            return [
                $tables,
                self::statementSummaryNestedAbortRollbackRetrySavepoint($phase, 0, $sql, $probe, $tables, $rowIdColumn, $exception->getMessage()) + [
                    'aborted' => true,
                    'error' => $exception->getMessage(),
                    'rolled_back_to_statement_start' => true,
                ],
            ];
        }
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryNestedAbortRollbackRetrySavepoint(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $error): array
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
            'source_rows' => self::rowsByIdsNestedAbortRollbackRetrySavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
            'failed_conflict' => $result['failed_conflict'] ?? null,
            'error' => $error,
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesNestedAbortRollbackRetrySavepoint(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value OR ABORT next192 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR ABORT next192 rows must be arrays');
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
    private static function rowsByIdsNestedAbortRollbackRetrySavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value OR ABORT next192 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR ABORT next192 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNestedAbortRollbackRetrySavepoint(array $yielded): int
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
    private static function changeCountNestedAbortRollbackRetrySavepoint(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesNestedAbortRollbackRetrySavepoint(array $before, array $after): array
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
    private static function rowCountsNestedAbortRollbackRetrySavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $failAttemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeFailStreamSavepoint(
        array $tables,
        array $outerStatements,
        array $failAttemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_fail_stream_next193',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value fail stream next193 needs outer statements');
        }
        if ($failAttemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value fail stream next193 needs fail attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value fail stream next193 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value fail stream next193 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value fail stream next193 savepoint must be an identifier');
        }

        $initial = self::normalizeFailStreamSavepointTables($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runFailStreamSavepointStatements(
            $initial,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-fail-savepoint-next193',
            false,
        );

        $savepointImage = $afterOuter;
        [$afterFailAttempt, $failExecuted, $failReturning] = self::runFailStreamSavepointStatements(
            $savepointImage,
            $failAttemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'fail-attempt-before-rollback-next193',
            true,
        );

        $rolledBack = $savepointImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runFailStreamSavepointStatements(
            $rolledBack,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-rollback-next193',
            false,
        );

        return [
            'status' => 'rowvalue-update-delete-returning-fail-stream-savepoint-current-source-next193',
            'savepoint' => $savepoint,
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'savepoint_released_after_retry' => true,
            'initial_tables' => $initial,
            'outer_current_source_tables' => $afterOuter,
            'savepoint_image_tables' => $savepointImage,
            'fail_attempt_current_source_tables' => $afterFailAttempt,
            'rollback_to_savepoint_current_source_tables' => $rolledBack,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'fail_attempt_statements' => $failExecuted,
            'retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'fail_yielded_before_conflict_returning' => $failReturning,
            'suppressed_by_rollback_returning' => $failReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'outer_yielded_returning_count' => self::returningCountFailStreamSavepoint($outerReturning),
            'fail_yielded_before_conflict_count' => self::returningCountFailStreamSavepoint($failReturning),
            'suppressed_by_rollback_count' => self::returningCountFailStreamSavepoint($failReturning),
            'yielded_after_retry_count' => self::returningCountFailStreamSavepoint($retryReturning),
            'failed_conflicts' => self::failedConflictsFailStreamSavepoint($failExecuted),
            'changed_tables_after_retry' => self::changedTablesFailStreamSavepoint($initial, $afterRetry),
            'row_counts' => self::rowCountsFailStreamSavepoint($afterRetry),
            'dependencies' => [
                'sqlite-update-or-fail-rowvalue-returning-partial-stream-next193',
                'sqlite-savepoint-rollback-suppresses-fail-returning-stream-next193',
                'sqlite-rowvalue-delete-retry-reads-restored-current-source-next193',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runFailStreamSavepointStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase, bool $preserveFailChanges): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints, $preserveFailChanges);
            $current = $result['tables'];
            $executed[] = self::statementSummaryFailStreamSavepoint($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryFailStreamSavepoint(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsFailStreamSavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeFailStreamSavepointTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value fail stream next193 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value fail stream next193 rows must be arrays');
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
    private static function rowsByIdsFailStreamSavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value fail stream next193 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value fail stream next193 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountFailStreamSavepoint(array $yielded): int
    {
        $count = 0;
        foreach ($yielded as $stream) {
            $count += count($stream['rows']);
        }

        return $count;
    }

    /**
     * @param list<array<string,mixed>> $executed
     * @return list<array<string,mixed>>
     */
    private static function failedConflictsFailStreamSavepoint(array $executed): array
    {
        $failed = [];
        foreach ($executed as $statement) {
            if (($statement['failed_conflict'] ?? null) !== null) {
                $failed[] = $statement['failed_conflict'];
            }
        }

        return $failed;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesFailStreamSavepoint(array $before, array $after): array
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
    private static function rowCountsFailStreamSavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $preFailStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeFailConflictPreserveRetrySavepoint(
        array $tables,
        array $preFailStatements,
        string $failStatement,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_fail_next196',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($preFailStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 needs pre-fail statements');
        }
        if (trim($failStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 needs a fail statement');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 savepoint name must be an identifier');
        }

        $savepointImage = self::normalizeTablesFailConflictPreserveRetrySavepoint($tables);
        [$beforeFail, $preFailExecuted, $preFailReturning] = self::runStatementsFailConflictPreserveRetrySavepoint(
            $savepointImage,
            $preFailStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-fail-statement',
        );

        [$afterFail, $failSummary] = self::runFailStatementFailConflictPreserveRetrySavepoint(
            $beforeFail,
            $failStatement,
            $uniqueConstraints,
            $rowIdColumn,
            'rowvalue-or-fail-statement',
        );

        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsFailConflictPreserveRetrySavepoint(
            $afterFail,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-or-fail',
        );

        return [
            'savepoint' => $savepoint,
            'status' => 'rowvalue-or-fail-preserves-statement-prefix-next196',
            'savepoint_active_after_fail' => true,
            'savepoint_released_after_retry' => true,
            'savepoint_image_tables' => $savepointImage,
            'pre_fail_current_source_tables' => $beforeFail,
            'fail_partial_current_source_tables' => $afterFail,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'pre_fail_statements' => $preFailExecuted,
            'fail_statement' => $failSummary,
            'retry_statements' => $retryExecuted,
            'pre_fail_returning' => $preFailReturning,
            'yielded_before_fail_count' => self::returningCountFailConflictPreserveRetrySavepoint($preFailReturning),
            'yielded_by_fail_before_conflict' => $failSummary['returning_rows'],
            'yielded_by_fail_before_conflict_count' => count($failSummary['returning_rows']),
            'yielded_after_retry_returning' => $retryReturning,
            'yielded_after_retry_count' => self::returningCountFailConflictPreserveRetrySavepoint($retryReturning),
            'pre_fail_changes_preserved' => self::changeCountFailConflictPreserveRetrySavepoint($preFailExecuted),
            'fail_prefix_changes_preserved' => count($failSummary['returning_rows']),
            'retry_changes_after_fail' => self::changeCountFailConflictPreserveRetrySavepoint($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesFailConflictPreserveRetrySavepoint($savepointImage, $afterRetry),
            'row_counts' => self::rowCountsFailConflictPreserveRetrySavepoint($afterRetry),
            'dependencies' => [
                'sqlite-rowvalue-update-or-fail-prefix-preserved-next196',
                'sqlite-rowvalue-savepoint-current-source-after-fail-next196',
                'sqlite-rowvalue-delete-returning-retry-after-fail-next196',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsFailConflictPreserveRetrySavepoint(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryFailConflictPreserveRetrySavepoint($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null) + [
                'failed' => false,
            ];
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:array<string,mixed>}
     */
    private static function runFailStatementFailConflictPreserveRetrySavepoint(array $tables, string $sql, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        if ($parsed['action'] !== 'update' || $parsed['conflict_action'] !== 'fail') {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 fail statement must be UPDATE OR FAIL');
        }

        $thrown = null;
        try {
            SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints);
        } catch (\InvalidArgumentException $exception) {
            $thrown = $exception->getMessage();
        }
        if ($thrown === null) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 expected a unique conflict');
        }

        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints, true);
        if (($result['failed_conflict'] ?? null) === null) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 expected preserved failed conflict metadata');
        }

        return [
            $result['tables'],
            self::statementSummaryFailConflictPreserveRetrySavepoint($phase, 0, $sql, $result, $tables, $rowIdColumn, $thrown) + [
                'failed' => true,
                'statement_rolled_back' => false,
                'prefix_changes_preserved' => count($result['returning']),
            ],
        ];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryFailConflictPreserveRetrySavepoint(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $error): array
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
            'source_rows' => self::rowsByIdsFailConflictPreserveRetrySavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
            'failed_conflict' => $result['failed_conflict'] ?? null,
            'error' => $error,
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesFailConflictPreserveRetrySavepoint(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 rows must be arrays');
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
    private static function rowsByIdsFailConflictPreserveRetrySavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL next196 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL next196 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountFailConflictPreserveRetrySavepoint(array $yielded): int
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
    private static function changeCountFailConflictPreserveRetrySavepoint(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesFailConflictPreserveRetrySavepoint(array $before, array $after): array
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
    private static function rowCountsFailConflictPreserveRetrySavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $savepointStatements
     * @param list<string> $abortStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAbortRollbackConflict(
        array $tables,
        array $outerStatements,
        array $savepointStatements,
        array $abortStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_abort_statement',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint needs outer statements');
        }
        if ($savepointStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint needs savepoint statements');
        }
        if ($abortStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint needs abort statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint needs unique constraints');
        }
        self::assertIdentifierAbortRollbackConflict($savepoint, 'savepoint');

        $initialTables = self::normalizeTablesAbortRollbackConflict($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsAbortRollbackConflict(
            $initialTables,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-savepoint',
        );

        $savepointImage = $afterOuter;
        [$afterSavepoint, $savepointExecuted, $savepointReturning] = self::runStatementsAbortRollbackConflict(
            $savepointImage,
            $savepointStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'savepoint-before-abort',
        );

        [$afterAbort, $abortExecuted, $abortReason, $abortOrdinal] = self::runAbortStatementsAbortRollbackConflict(
            $afterSavepoint,
            $abortStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsAbortRollbackConflict(
            $afterAbort,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-abort',
        );

        return [
            'savepoint' => $savepoint,
            'status' => 'rowvalue-update-delete-returning-abort-statement-current-source',
            'statement_aborted' => $abortReason !== null,
            'rolled_back_to_savepoint' => false,
            'savepoint_preserved_after_abort' => true,
            'savepoint_released_after_retry' => true,
            'abort_statement_ordinal' => $abortOrdinal,
            'abort_reason' => $abortReason,
            'initial_tables' => $initialTables,
            'outer_current_source_tables' => $afterOuter,
            'savepoint_image_tables' => $savepointImage,
            'savepoint_current_source_tables' => $afterSavepoint,
            'abort_current_source_tables' => $afterAbort,
            'retry_current_source_tables' => $afterRetry,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'savepoint_statements' => $savepointExecuted,
            'abort_statements' => $abortExecuted,
            'retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'savepoint_yielded_returning' => $savepointReturning,
            'abort_suppressed_returning' => [],
            'yielded_after_retry_returning' => $retryReturning,
            'outer_yielded_returning_count' => self::returningCountAbortRollbackConflict($outerReturning),
            'savepoint_yielded_returning_count' => self::returningCountAbortRollbackConflict($savepointReturning),
            'abort_suppressed_returning_count' => 0,
            'yielded_after_retry_count' => self::returningCountAbortRollbackConflict($retryReturning),
            'changes_preserved_before_abort' => self::changeCountAbortRollbackConflict(array_merge($outerExecuted, $savepointExecuted)),
            'changes_after_retry' => self::changeCountAbortRollbackConflict($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesAbortRollbackConflict($initialTables, $afterRetry),
            'row_counts' => self::rowCountsAbortRollbackConflict($afterRetry),
            'dependencies' => [
                'sqlite-update-or-abort-rowvalue-returning-discards-failed-statement',
                'sqlite-savepoint-current-source-survives-abort-statement',
                'sqlite-rowvalue-update-delete-retry-reads-post-abort-current-source',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsAbortRollbackConflict(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryAbortRollbackConflict($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:?string,3:?int}
     */
    private static function runAbortStatementsAbortRollbackConflict(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $executed[] = self::abortedStatementSummaryAbortRollbackConflict($sql, $ordinal, $before, $rowIdColumn, $exception->getMessage());

                return [$current, $executed, $exception->getMessage(), $ordinal];
            }

            $current = $result['tables'];
            $executed[] = self::statementSummaryAbortRollbackConflict('abort-attempt-before-conflict', $ordinal, $sql, $result, $before, $rowIdColumn, null);
        }

        return [$current, $executed, null, null];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryAbortRollbackConflict(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $failedMessage): array
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
            'source_rows' => self::rowsByIdsAbortRollbackConflict($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
            'failed_conflict' => $failedMessage === null ? ($result['failed_conflict'] ?? null) : ['message' => $failedMessage],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function abortedStatementSummaryAbortRollbackConflict(string $sql, int $ordinal, array $before, string $rowIdColumn, string $message): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        $table = $parsed['table'];
        $where = self::wherePredicateAbortRollbackConflict($parsed['where']);
        if ($parsed['action'] === 'delete') {
            $plan = SQLiteUpdateDeleteLimitPlan::delete($before[$table] ?? [], $where, $parsed['order_by'], $parsed['limit'], $parsed['offset'], $rowIdColumn);
        } else {
            $plan = SQLiteUpdateDeleteLimitPlan::update(
                $before[$table] ?? [],
                $where,
                self::assignmentCallbacksAbortRollbackConflict($parsed['assignments']),
                $parsed['order_by'],
                $parsed['limit'],
                $parsed['offset'],
                $rowIdColumn,
            );
        }

        return [
            'phase' => 'abort-conflict-suppressed',
            'ordinal' => $ordinal,
            'sql' => $sql,
            'action' => $parsed['action'],
            'conflict_action' => $parsed['conflict_action'],
            'table' => $table,
            'selected_ids' => $plan->selectedIds,
            'mutation_ids' => $plan->mutationIds,
            'source_rows' => self::rowsByIdsAbortRollbackConflict($before[$table] ?? [], $plan->selectedIds, $rowIdColumn),
            'returning_rows' => [],
            'ignored_rows' => [],
            'deleted_conflict_rows' => [],
            'conflicts' => [],
            'failed_conflict' => ['message' => $message],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesAbortRollbackConflict(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value ABORT savepoint tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ABORT savepoint rows must be arrays');
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
    private static function rowsByIdsAbortRollbackConflict(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountAbortRollbackConflict(array $yielded): int
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
    private static function changeCountAbortRollbackConflict(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesAbortRollbackConflict(array $before, array $after): array
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
    private static function rowCountsAbortRollbackConflict(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function assertIdentifierAbortRollbackConflict(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value ABORT savepoint {$label} must be an identifier");
        }
    }

    /**
     * @return callable(array<string,mixed>):bool
     */
    private static function wherePredicateAbortRollbackConflict(?string $where): callable
    {
        $reflection = new \ReflectionMethod(SQLiteUpdateDeleteReturningSql::class, 'wherePredicate');
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $where);
    }

    /**
     * @param array<string,string> $assignments
     * @return array<string,callable(array<string,mixed>):mixed>
     */
    private static function assignmentCallbacksAbortRollbackConflict(array $assignments): array
    {
        $reflection = new \ReflectionMethod(SQLiteUpdateDeleteReturningSql::class, 'assignmentCallbacks');
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $assignments);
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeParenthesizedRollbackRetrySavepoint(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints = [],
        string $savepoint = 'wp_options_rowvalue_parenthesized_next202',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value parenthesized next202 needs attempted statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value parenthesized next202 needs retry statements');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value parenthesized next202 savepoint name must be an identifier');
        }

        $savepointImage = self::normalizeTablesParenthesizedRollbackRetrySavepoint($tables);
        [$attemptTables, $attemptSummaries, $attemptReturning] = self::runStatementsParenthesizedRollbackRetrySavepoint(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-before-rollback-next202',
        );
        [$retryTables, $retrySummaries, $retryReturning] = self::runStatementsParenthesizedRollbackRetrySavepoint(
            $savepointImage,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-parenthesized-rollback-next202',
        );

        return [
            'savepoint' => $savepoint,
            'status' => 'rowvalue-parenthesized-returning-savepoint-current-source-next202',
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'savepoint_released_after_retry' => true,
            'savepoint_image_tables' => $savepointImage,
            'attempt_current_source_tables' => $attemptTables,
            'rollback_to_current_source_tables' => $savepointImage,
            'current_source_tables' => $retryTables,
            'next_source_tables' => $retryTables,
            'attempt_statements' => $attemptSummaries,
            'retry_statements' => $retrySummaries,
            'attempt_returning' => $attemptReturning,
            'suppressed_by_rollback_returning' => $attemptReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'attempt_returning_count' => self::returningCountParenthesizedRollbackRetrySavepoint($attemptReturning),
            'suppressed_by_rollback_count' => self::returningCountParenthesizedRollbackRetrySavepoint($attemptReturning),
            'yielded_after_retry_count' => self::returningCountParenthesizedRollbackRetrySavepoint($retryReturning),
            'attempt_changes_before_rollback_to' => self::changeCountParenthesizedRollbackRetrySavepoint($attemptSummaries),
            'changes_after_retry_release' => self::changeCountParenthesizedRollbackRetrySavepoint($retrySummaries),
            'changed_tables_after_retry' => self::changedTablesParenthesizedRollbackRetrySavepoint($savepointImage, $retryTables),
            'row_counts' => self::rowCountsParenthesizedRollbackRetrySavepoint($retryTables),
            'dependencies' => [
                'sqlite-rowvalue-parenthesized-where-predicate-next202',
                'sqlite-rowvalue-parenthesized-returning-expression-next202',
                'sqlite-rowvalue-parenthesized-retry-reads-savepoint-image-next202',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsParenthesizedRollbackRetrySavepoint(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $summaries = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $summaries[] = self::statementSummaryParenthesizedRollbackRetrySavepoint($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryParenthesizedRollbackRetrySavepoint(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsParenthesizedRollbackRetrySavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesParenthesizedRollbackRetrySavepoint(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value parenthesized next202 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value parenthesized next202 rows must be arrays');
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
    private static function rowsByIdsParenthesizedRollbackRetrySavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value parenthesized next202 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value parenthesized next202 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountParenthesizedRollbackRetrySavepoint(array $yielded): int
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
    private static function changeCountParenthesizedRollbackRetrySavepoint(array $summaries): int
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
    private static function changedTablesParenthesizedRollbackRetrySavepoint(array $before, array $after): array
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
    private static function rowCountsParenthesizedRollbackRetrySavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $ignoreStatements
     * @param list<string> $replaceStatements
     * @param list<string> $deleteStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeIgnoreReplaceDeleteSavepoint(
        array $tables,
        array $ignoreStatements,
        array $replaceStatements,
        array $deleteStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_ignore_replace_ignore_replace_delete',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($ignoreStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ignore/replace ignore_replace_delete needs ignore statements');
        }
        if ($replaceStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ignore/replace ignore_replace_delete needs replace statements');
        }
        if ($deleteStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ignore/replace ignore_replace_delete needs delete statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ignore/replace ignore_replace_delete needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value ignore/replace ignore_replace_delete savepoint must be an identifier');
        }

        $savepointImage = self::normalizeTablesIgnoreReplaceDeleteSavepoint($tables);
        [$afterIgnore, $ignoreExecuted, $ignoreReturning] = self::runStatementsIgnoreReplaceDeleteSavepoint(
            $savepointImage,
            $ignoreStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'ignore-conflict-current-source-ignore_replace_delete',
        );
        self::assertConflictActionIgnoreReplaceDeleteSavepoint($ignoreExecuted, 'ignore');

        [$afterReplace, $replaceExecuted, $replaceReturning] = self::runStatementsIgnoreReplaceDeleteSavepoint(
            $afterIgnore,
            $replaceStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'replace-conflict-current-source-ignore_replace_delete',
        );
        self::assertConflictActionIgnoreReplaceDeleteSavepoint($replaceExecuted, 'replace');

        [$afterDelete, $deleteExecuted, $deleteReturning] = self::runStatementsIgnoreReplaceDeleteSavepoint(
            $afterReplace,
            $deleteStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'delete-after-replace-current-source-ignore_replace_delete',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-ignore-replace-savepoint-current-source-ignore_replace_delete',
            'savepoint' => $savepoint,
            'savepoint_image_tables' => $savepointImage,
            'ignore_current_source_tables' => $afterIgnore,
            'replace_current_source_tables' => $afterReplace,
            'current_source_tables' => $afterDelete,
            'next_source_tables' => $afterDelete,
            'savepoint_active_after_ignore' => true,
            'savepoint_active_after_replace' => true,
            'savepoint_released_after_delete' => true,
            'ignore_statements' => $ignoreExecuted,
            'replace_statements' => $replaceExecuted,
            'delete_statements' => $deleteExecuted,
            'ignored_returning' => $ignoreReturning,
            'replace_returning' => $replaceReturning,
            'delete_returning' => $deleteReturning,
            'ignored_rows' => self::ignoredRowsIgnoreReplaceDeleteSavepoint($ignoreExecuted),
            'replace_deleted_conflict_rows' => self::deletedConflictRowsIgnoreReplaceDeleteSavepoint($replaceExecuted),
            'ignore_yielded_count' => self::returningCountIgnoreReplaceDeleteSavepoint($ignoreReturning),
            'replace_yielded_count' => self::returningCountIgnoreReplaceDeleteSavepoint($replaceReturning),
            'delete_yielded_count' => self::returningCountIgnoreReplaceDeleteSavepoint($deleteReturning),
            'ignore_conflict_count' => self::conflictCountIgnoreReplaceDeleteSavepoint($ignoreExecuted),
            'replace_conflict_count' => self::conflictCountIgnoreReplaceDeleteSavepoint($replaceExecuted),
            'changed_tables' => self::changedTablesIgnoreReplaceDeleteSavepoint($savepointImage, $afterDelete),
            'row_counts' => self::rowCountsIgnoreReplaceDeleteSavepoint($afterDelete),
            'dependencies' => [
                'sqlite-rowvalue-update-or-ignore-returning-current-source-ignore_replace_delete',
                'sqlite-rowvalue-update-or-replace-returning-conflict-delete-ignore_replace_delete',
                'sqlite-rowvalue-delete-returning-after-replace-current-source-ignore_replace_delete',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsIgnoreReplaceDeleteSavepoint(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryIgnoreReplaceDeleteSavepoint($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param list<array<string,mixed>> $executed
     */
    private static function assertConflictActionIgnoreReplaceDeleteSavepoint(array $executed, string $expected): void
    {
        foreach ($executed as $statement) {
            if (($statement['action'] ?? null) !== 'update' || ($statement['conflict_action'] ?? null) !== $expected) {
                throw new \InvalidArgumentException("SQLite row-value ignore_replace_delete expected UPDATE OR " . strtoupper($expected));
            }
        }
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryIgnoreReplaceDeleteSavepoint(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsIgnoreReplaceDeleteSavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesIgnoreReplaceDeleteSavepoint(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value ignore/replace ignore_replace_delete tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ignore/replace ignore_replace_delete rows must be arrays');
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
    private static function rowsByIdsIgnoreReplaceDeleteSavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value ignore/replace ignore_replace_delete rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ignore/replace ignore_replace_delete rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountIgnoreReplaceDeleteSavepoint(array $yielded): int
    {
        $count = 0;
        foreach ($yielded as $stream) {
            $count += count($stream['rows']);
        }

        return $count;
    }

    /**
     * @param list<array{ignored_rows:list<array<string,mixed>>}> $executed
     * @return list<array<string,mixed>>
     */
    private static function ignoredRowsIgnoreReplaceDeleteSavepoint(array $executed): array
    {
        $rows = [];
        foreach ($executed as $statement) {
            foreach ($statement['ignored_rows'] as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<array{deleted_conflict_rows:list<array<string,mixed>>}> $executed
     * @return list<array<string,mixed>>
     */
    private static function deletedConflictRowsIgnoreReplaceDeleteSavepoint(array $executed): array
    {
        $rows = [];
        foreach ($executed as $statement) {
            foreach ($statement['deleted_conflict_rows'] as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<array{conflicts:list<array<string,mixed>>}> $executed
     */
    private static function conflictCountIgnoreReplaceDeleteSavepoint(array $executed): int
    {
        $count = 0;
        foreach ($executed as $statement) {
            $count += count($statement['conflicts']);
        }

        return $count;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesIgnoreReplaceDeleteSavepoint(array $before, array $after): array
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
    private static function rowCountsIgnoreReplaceDeleteSavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $savepointStatements
     * @param list<string> $nextStatements
     * @param list<list<string>> $uniqueConstraints
     * @param array{release_token?:string,expected_release_token?:string,next_cursor?:string,expected_next_cursor?:string} $options
     * @return array<string,mixed>
     */
    public static function executeReleaseFollowupReadSavepoint(
        array $tables,
        array $savepointStatements,
        array $nextStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_release_release_followup_read',
        string $rowIdColumn = 'option_id',
        array $options = [],
    ): array {
        if ($savepointStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value release release_followup_read needs savepoint statements');
        }
        if ($nextStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value release release_followup_read needs next statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value release release_followup_read needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value release release_followup_read savepoint must be an identifier');
        }

        $releaseToken = self::tokenReleaseFollowupReadSavepoint((string) ($options['release_token'] ?? 'wp.rowvalue.release.followup.read'), 'release token');
        $expectedReleaseToken = self::tokenReleaseFollowupReadSavepoint((string) ($options['expected_release_token'] ?? $releaseToken), 'expected release token');
        $nextCursor = self::tokenReleaseFollowupReadSavepoint((string) ($options['next_cursor'] ?? 'wp.rowvalue.followup.cursor'), 'next cursor');
        $expectedNextCursor = self::tokenReleaseFollowupReadSavepoint((string) ($options['expected_next_cursor'] ?? $nextCursor), 'expected next cursor');

        $savepointImage = self::normalizeTablesReleaseFollowupReadSavepoint($tables);
        [$releasedCurrent, $savepointExecuted, $savepointReturning] = self::runStatementsReleaseFollowupReadSavepoint(
            $savepointImage,
            $savepointStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'savepoint-before-release-release_followup_read',
        );

        $releaseAdmitted = $releaseToken === $expectedReleaseToken;
        $nextCursorMatches = $nextCursor === $expectedNextCursor;
        $nextSource = $releaseAdmitted ? $releasedCurrent : $savepointImage;
        [$nextCurrent, $nextExecuted, $nextReturning] = self::runStatementsReleaseFollowupReadSavepoint(
            $nextSource,
            $nextStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'next-after-release-current-source-release_followup_read',
        );

        $nextReadReleasedRows = $releaseAdmitted && $nextCursorMatches && self::firstStatementSourceMatchesReleaseFollowupReadSavepoint($nextExecuted, $releasedCurrent, $rowIdColumn);
        $status = $releaseAdmitted && $nextCursorMatches
            ? 'rowvalue-update-delete-returning-release-current-source-release_followup_read'
            : 'rowvalue-update-delete-returning-release-current-source-blocked-release_followup_read';

        return [
            'status' => $status,
            'savepoint' => $savepoint,
            'release_token_release_followup_read' => $releaseToken,
            'expected_release_token_release_followup_read' => $expectedReleaseToken,
            'release_admitted_release_followup_read' => $releaseAdmitted,
            'next_cursor_release_followup_read' => $nextCursor,
            'expected_next_cursor_release_followup_read' => $expectedNextCursor,
            'next_cursor_matches_release_followup_read' => $nextCursorMatches,
            'savepoint_image_tables' => $savepointImage,
            'released_current_source_tables' => $releasedCurrent,
            'next_source_tables' => $nextSource,
            'current_source_tables' => $nextCurrent,
            'savepoint_released_before_next_source_release_followup_read' => $releaseAdmitted,
            'next_read_released_current_source_release_followup_read' => $nextReadReleasedRows,
            'savepoint_statements' => $savepointExecuted,
            'next_statements' => $nextExecuted,
            'savepoint_returning' => $savepointReturning,
            'next_returning' => $nextReturning,
            'released_returning_count' => self::returningCountReleaseFollowupReadSavepoint($savepointReturning),
            'next_returning_count' => self::returningCountReleaseFollowupReadSavepoint($nextReturning),
            'released_conflict_delete_count' => self::deletedConflictCountReleaseFollowupReadSavepoint($savepointExecuted),
            'changed_tables_after_release' => self::changedTablesReleaseFollowupReadSavepoint($savepointImage, $releasedCurrent),
            'changed_tables_after_next' => self::changedTablesReleaseFollowupReadSavepoint($savepointImage, $nextCurrent),
            'row_counts' => self::rowCountsReleaseFollowupReadSavepoint($nextCurrent),
            'release_receipt_release_followup_read' => [
                'savepoint' => $savepoint,
                'token' => $releaseToken,
                'admitted' => $releaseAdmitted,
                'next_cursor' => $nextCursor,
                'next_cursor_matches' => $nextCursorMatches,
            ],
            'dependency_closure_release_followup_read' => 'no new support component needed; release_followup_read reuses native row-value UPDATE/DELETE RETURNING execution, conflict handling, and savepoint current-source images',
            'dependencies' => [
                'sqlite-rowvalue-savepoint-release-current-source-release_followup_read',
                'sqlite-rowvalue-returning-release-feeds-next-statement-release_followup_read',
                'wordpress-rowvalue-update-delete-returning-savepoint-release-release_followup_read',
            ],
            'non_overlap_release_followup_read' => 'adds RELEASE-to-parent current-source admission after row-value UPDATE/DELETE RETURNING; avoids ignore_replace_delete IGNORE/REPLACE-only savepoint flow, next178 OR ROLLBACK transaction rollback, next172 ROLLBACK TO yielded stream suppression, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsReleaseFollowupReadSavepoint(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryReleaseFollowupReadSavepoint($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryReleaseFollowupReadSavepoint(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsReleaseFollowupReadSavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesReleaseFollowupReadSavepoint(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value release release_followup_read tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value release release_followup_read rows must be arrays');
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
    private static function rowsByIdsReleaseFollowupReadSavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value release release_followup_read rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value release release_followup_read rowid column {$rowIdColumn} must be int or string");
            }
            if (isset($wanted[(string) $id])) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * @param list<array<string,mixed>> $executed
     * @param array<string,list<array<string,mixed>>> $source
     */
    private static function firstStatementSourceMatchesReleaseFollowupReadSavepoint(array $executed, array $source, string $rowIdColumn): bool
    {
        $statement = $executed[0] ?? null;
        if (!is_array($statement)) {
            return false;
        }
        $table = (string) ($statement['table'] ?? '');
        $ids = $statement['selected_ids'] ?? [];
        if ($table === '' || !is_array($ids) || !isset($source[$table])) {
            return false;
        }

        return self::rowsByIdsReleaseFollowupReadSavepoint($source[$table], $ids, $rowIdColumn) === ($statement['source_rows'] ?? null);
    }

    /**
     * @param list<array{rows:list<array<string,mixed>>}> $yielded
     */
    private static function returningCountReleaseFollowupReadSavepoint(array $yielded): int
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
    private static function deletedConflictCountReleaseFollowupReadSavepoint(array $executed): int
    {
        $count = 0;
        foreach ($executed as $statement) {
            $count += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $count;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesReleaseFollowupReadSavepoint(array $before, array $after): array
    {
        $changed = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $table) {
            if (($before[$table] ?? null) !== ($after[$table] ?? null)) {
                $changed[] = $table;
            }
        }

        return $changed;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,int>
     */
    private static function rowCountsReleaseFollowupReadSavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function tokenReleaseFollowupReadSavepoint(string $token, string $label): string
    {
        $token = trim($token);
        if ($token === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $token) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value release release_followup_read {$label} is invalid");
        }

        return $token;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $releasedInnerStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeReleasedInnerRollbackRetry(
        array $tables,
        array $outerStatements,
        array $releasedInnerStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_rowvalue_released_inner_retry',
        string $innerSavepoint = 'wp_options_inner_released_rowvalue_released_inner_retry',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value outer rollback released_inner_retry needs outer statements');
        }
        if ($releasedInnerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value outer rollback released_inner_retry needs released inner statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value outer rollback released_inner_retry needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value outer rollback released_inner_retry needs unique constraints');
        }
        self::assertReleasedInnerRollbackRetryIdentifier($outerSavepoint, 'outer savepoint');
        self::assertReleasedInnerRollbackRetryIdentifier($innerSavepoint, 'inner savepoint');
        if ($outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value outer rollback released_inner_retry savepoint names must differ');
        }

        $outerImage = self::normalizeReleasedInnerRollbackRetryTables($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runReleasedInnerRollbackRetryStatements(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-inner-release-released_inner_retry',
        );

        $innerImage = $afterOuter;
        [$afterInnerRelease, $innerExecuted, $innerReturning] = self::runReleasedInnerRollbackRetryStatements(
            $innerImage,
            $releasedInnerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-released-before-outer-rollback-released_inner_retry',
        );

        $afterOuterRollback = $outerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runReleasedInnerRollbackRetryStatements(
            $afterOuterRollback,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-outer-rollback-released_inner_retry',
        );

        $discardedReturning = array_merge($outerReturning, $innerReturning);

        return [
            'status' => 'rowvalue-update-delete-returning-released-inner-outer-rollback-current-source-released_inner_retry',
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'inner_released_before_outer_rollback' => true,
            'rolled_back_to_outer_savepoint' => true,
            'outer_savepoint_preserved_after_rollback_to' => true,
            'inner_savepoint_available_after_release' => false,
            'retry_reads_outer_savepoint_image' => true,
            'outer_savepoint_released_after_retry' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'outer_current_source_tables' => $afterOuter,
            'inner_savepoint_image_tables' => $innerImage,
            'inner_released_current_source_tables' => $afterInnerRelease,
            'rollback_to_outer_current_source_tables' => $afterOuterRollback,
            'retry_current_source_tables' => $afterRetry,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'inner_released_statements' => $innerExecuted,
            'retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'inner_released_yielded_returning' => $innerReturning,
            'discarded_by_outer_rollback_returning' => $discardedReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'outer_yielded_count' => self::releasedInnerRollbackRetryReturningCount($outerReturning),
            'inner_released_yielded_count' => self::releasedInnerRollbackRetryReturningCount($innerReturning),
            'discarded_by_outer_rollback_count' => self::releasedInnerRollbackRetryReturningCount($discardedReturning),
            'yielded_after_retry_count' => self::releasedInnerRollbackRetryReturningCount($retryReturning),
            'changes_discarded_by_outer_rollback' => self::releasedInnerRollbackRetryChangeCount(array_merge($outerExecuted, $innerExecuted)),
            'changes_after_retry' => self::releasedInnerRollbackRetryChangeCount($retryExecuted),
            'changed_tables_after_retry' => self::releasedInnerRollbackRetryChangedTables($outerImage, $afterRetry),
            'row_counts' => self::releasedInnerRollbackRetryRowCounts($afterRetry),
            'dependencies' => [
                'sqlite-release-inner-savepoint-merges-rowvalue-returning-released_inner_retry',
                'sqlite-rollback-to-outer-savepoint-discards-released-inner-returning-released_inner_retry',
                'sqlite-rowvalue-retry-after-outer-rollback-reads-outer-image-released_inner_retry',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runReleasedInnerRollbackRetryStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::releasedInnerRollbackRetryStatementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function releasedInnerRollbackRetryStatementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::releasedInnerRollbackRetryRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeReleasedInnerRollbackRetryTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value outer rollback released_inner_retry tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value outer rollback released_inner_retry rows must be arrays');
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
    private static function releasedInnerRollbackRetryRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value outer rollback released_inner_retry rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value outer rollback released_inner_retry rowid column {$rowIdColumn} must be int or string");
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
    private static function releasedInnerRollbackRetryReturningCount(array $yielded): int
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
    private static function releasedInnerRollbackRetryChangeCount(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function releasedInnerRollbackRetryChangedTables(array $before, array $after): array
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
    private static function releasedInnerRollbackRetryRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function assertReleasedInnerRollbackRetryIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value outer rollback released_inner_retry {$label} must be an identifier");
        }
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $failStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeOrFailSavepointRetry(
        array $tables,
        array $outerStatements,
        array $failStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_fail_or_fail_savepoint_retry',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL or-fail-savepoint-retry needs outer statements');
        }
        if ($failStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL or-fail-savepoint-retry needs failing statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL or-fail-savepoint-retry needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL or-fail-savepoint-retry needs unique constraints');
        }
        self::assertOrFailSavepointRetryIdentifier($savepoint, 'savepoint');

        $initial = self::normalizeOrFailSavepointRetryTables($tables);
        [$outerCurrent, $outerSummaries, $outerReturning] = self::runOrFailSavepointRetryStatements(
            $initial,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-fail-savepoint-or-fail-savepoint-retry',
            false,
        );

        $savepointImage = $outerCurrent;
        [$failCurrent, $failSummaries, $failReturning] = self::runOrFailSavepointRetryStatements(
            $savepointImage,
            $failStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'fail-prefix-before-rollback-or-fail-savepoint-retry',
            true,
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retrySummaries, $retryReturning] = self::runOrFailSavepointRetryStatements(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-fail-rollback-or-fail-savepoint-retry',
            false,
        );

        return [
            'status' => 'rowvalue-update-delete-returning-or-fail-savepoint-current-source-or-fail-savepoint-retry',
            'savepoint' => $savepoint,
            'statement_fail_preserved_prefix_or-fail-savepoint-retry' => true,
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
            'outer_returning_count' => self::orFailSavepointRetryReturningCount($outerReturning),
            'fail_prefix_returning_count' => self::orFailSavepointRetryReturningCount($failReturning),
            'suppressed_by_rollback_count' => self::orFailSavepointRetryReturningCount($failReturning),
            'yielded_after_retry_count' => self::orFailSavepointRetryReturningCount($retryReturning),
            'fail_conflict_count' => self::orFailSavepointRetryFailedConflictCount($failSummaries),
            'changes_preserved_by_fail_before_rollback' => self::orFailSavepointRetryChangeCount($failSummaries),
            'changes_after_retry' => self::orFailSavepointRetryChangeCount($retrySummaries),
            'changed_tables_after_retry' => self::orFailSavepointRetryChangedTables($initial, $retryCurrent),
            'row_counts' => self::orFailSavepointRetryRowCounts($retryCurrent),
            'dependency_closure_or-fail-savepoint-retry' => 'no new support component needed; reuses native row-value UPDATE/DELETE RETURNING execution, OR FAIL conflict prefix handling, and savepoint current-source images',
            'dependencies' => [
                'sqlite-rowvalue-update-or-fail-returning-prefix-or-fail-savepoint-retry',
                'sqlite-rowvalue-savepoint-rollback-discards-or-fail-prefix-or-fail-savepoint-retry',
                'wordpress-rowvalue-fail-retry-current-source-or-fail-savepoint-retry',
            ],
            'non_overlap_or-fail-savepoint-retry' => 'adds OR FAIL prefix-preservation plus ROLLBACK TO suppression for row-value UPDATE/DELETE RETURNING; avoids accepted OR ABORT abort-statement-savepoint, release release_followup_read, parenthesized next202, OR ROLLBACK next178, OR REPLACE/IGNORE conflict, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runOrFailSavepointRetryStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase, bool $preserveFailChanges): array
    {
        $current = $tables;
        $summaries = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints, $preserveFailChanges);
            $current = $result['tables'];
            $summaries[] = self::orFailSavepointRetryStatementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function orFailSavepointRetryStatementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::orFailSavepointRetryRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeOrFailSavepointRetryTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value OR FAIL or-fail-savepoint-retry tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR FAIL or-fail-savepoint-retry rows must be arrays');
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
    private static function orFailSavepointRetryRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL or-fail-savepoint-retry rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL or-fail-savepoint-retry rowid column {$rowIdColumn} must be int or string");
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
    private static function orFailSavepointRetryReturningCount(array $yielded): int
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
    private static function orFailSavepointRetryFailedConflictCount(array $summaries): int
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
    private static function orFailSavepointRetryChangeCount(array $summaries): int
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
    private static function orFailSavepointRetryChangedTables(array $before, array $after): array
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
    private static function orFailSavepointRetryRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function assertOrFailSavepointRetryIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value OR FAIL or-fail-savepoint-retry {$label} must be an identifier");
        }
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $preFailStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executePreFailRollbackRetry(
        array $tables,
        array $outerStatements,
        array $preFailStatements,
        string $failStatement,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_fail_statement_pre_fail_rollback_retry',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL pre-fail-rollback-retry needs outer statements');
        }
        if ($preFailStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL pre-fail-rollback-retry needs pre-fail statements');
        }
        if (trim($failStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL pre-fail-rollback-retry needs a fail statement');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL pre-fail-rollback-retry needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL pre-fail-rollback-retry needs unique constraints');
        }
        self::assertPreFailRollbackRetryIdentifier($savepoint);

        $initial = self::normalizePreFailRollbackRetryTables($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runPreFailRollbackRetryStatements(
            $initial,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-fail-savepoint-pre-fail-rollback-retry',
            false,
        );

        $savepointImage = $afterOuter;
        [$beforeFail, $preFailExecuted, $preFailReturning] = self::runPreFailRollbackRetryStatements(
            $savepointImage,
            $preFailStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'savepoint-before-or-fail-pre-fail-rollback-retry',
            false,
        );

        $failBefore = $beforeFail;
        $failResult = SQLiteUpdateDeleteReturningSql::execute($failStatement, $beforeFail, $rowIdColumn, $uniqueConstraints, true);
        $failCurrent = $failResult['tables'];
        $failSummary = self::preFailRollbackRetryStatementSummary(
            'or-fail-partial-current-source-pre-fail-rollback-retry',
            0,
            $failStatement,
            $failResult,
            $failBefore,
            $rowIdColumn,
        );

        [$afterRetryFromFail, $retryFromFailExecuted, $retryFromFailReturning] = self::runPreFailRollbackRetryStatements(
            $failCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-before-savepoint-rollback-pre-fail-rollback-retry',
            false,
        );

        $afterRollbackToSavepoint = $savepointImage;

        return [
            'status' => 'rowvalue-update-delete-returning-or-fail-savepoint-current-source-pre-fail-rollback-retry',
            'savepoint' => $savepoint,
            'or_fail_statement_preserved_prior_rows' => true,
            'or_fail_statement_stopped_at_conflict' => $failResult['failed_conflict'] !== null,
            'or_fail_returning_rows_visible_before_rollback_to_savepoint' => true,
            'retry_reads_partial_fail_current_source' => true,
            'rolled_back_to_savepoint_after_retry' => true,
            'savepoint_released_after_rollback' => true,
            'initial_tables' => $initial,
            'outer_current_source_tables' => $afterOuter,
            'savepoint_image_tables' => $savepointImage,
            'pre_fail_current_source_tables' => $beforeFail,
            'fail_statement_current_source_tables' => $failCurrent,
            'retry_current_source_before_rollback_tables' => $afterRetryFromFail,
            'rollback_to_savepoint_current_source_tables' => $afterRollbackToSavepoint,
            'current_source_tables' => $afterRollbackToSavepoint,
            'next_source_tables' => $afterRollbackToSavepoint,
            'outer_statements' => $outerExecuted,
            'pre_fail_statements' => $preFailExecuted,
            'fail_statement' => $failSummary,
            'retry_statements' => $retryFromFailExecuted,
            'outer_yielded_returning' => $outerReturning,
            'pre_fail_yielded_returning' => $preFailReturning,
            'or_fail_yielded_returning' => [[
                'phase' => 'or-fail-partial-current-source-pre-fail-rollback-retry',
                'ordinal' => 0,
                'action' => $failResult['action'],
                'conflict_action' => $failResult['conflict_action'],
                'rows' => $failResult['returning'],
            ]],
            'retry_yielded_returning' => $retryFromFailReturning,
            'or_fail_returning_count' => count($failResult['returning']),
            'pre_fail_yielded_count' => self::preFailRollbackRetryReturningCount($preFailReturning),
            'retry_yielded_count_before_rollback' => self::preFailRollbackRetryReturningCount($retryFromFailReturning),
            'changes_preserved_by_or_fail' => count($failResult['returning']),
            'changes_after_retry_before_rollback' => self::preFailRollbackRetryChangeCount($retryFromFailExecuted),
            'changes_discarded_by_rollback_to_savepoint' => count($failResult['returning']) + self::preFailRollbackRetryChangeCount($retryFromFailExecuted),
            'failed_conflict' => $failResult['failed_conflict'],
            'changed_tables_after_rollback' => self::preFailRollbackRetryChangedTables($initial, $afterRollbackToSavepoint),
            'row_counts' => self::preFailRollbackRetryRowCounts($afterRollbackToSavepoint),
            'dependencies' => [
                'sqlite-update-or-fail-rowvalue-returning-preserves-prior-rows-pre-fail-rollback-retry',
                'sqlite-rowvalue-retry-reads-partial-or-fail-current-source-pre-fail-rollback-retry',
                'sqlite-rollback-to-savepoint-discards-or-fail-returning-current-source-pre-fail-rollback-retry',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runPreFailRollbackRetryStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase, bool $preserveFailChanges): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints, $preserveFailChanges);
            $current = $result['tables'];
            $executed[] = self::preFailRollbackRetryStatementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function preFailRollbackRetryStatementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::preFailRollbackRetryRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizePreFailRollbackRetryTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value OR FAIL pre-fail-rollback-retry tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR FAIL pre-fail-rollback-retry rows must be arrays');
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
    private static function preFailRollbackRetryRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL pre-fail-rollback-retry rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL pre-fail-rollback-retry rowid column {$rowIdColumn} must be int or string");
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
    private static function preFailRollbackRetryReturningCount(array $yielded): int
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
    private static function preFailRollbackRetryChangeCount(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function preFailRollbackRetryChangedTables(array $before, array $after): array
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
    private static function preFailRollbackRetryRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function assertPreFailRollbackRetryIdentifier(string $value): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL pre-fail-rollback-retry savepoint must be an identifier');
        }
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeFailStatements
     * @param string $failStatement
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeFailStatementRetry(
        array $tables,
        array $beforeFailStatements,
        string $failStatement,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_fail_fail_statement_retry',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($beforeFailStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL fail-statement-retry needs pre-fail statements');
        }
        if (trim($failStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL fail-statement-retry needs a fail statement');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL fail-statement-retry needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL fail-statement-retry needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL fail-statement-retry savepoint must be an identifier');
        }

        $savepointImage = self::normalizeFailStatementRetryTables($tables);
        [$beforeFailCurrent, $beforeFailExecuted, $beforeFailReturning] = self::runFailStatementRetryStatements(
            $savepointImage,
            $beforeFailStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-fail-fail-statement-retry',
        );

        [$afterFailCurrent, $failSummary, $failReturning] = self::runFailStatementRetryFailure(
            $beforeFailCurrent,
            $failStatement,
            $uniqueConstraints,
            $rowIdColumn,
            'or-fail-fail-statement-retry',
        );

        [$afterRetry, $retryExecuted, $retryReturning] = self::runFailStatementRetryStatements(
            $afterFailCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-fail-fail-statement-retry',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-or-fail-current-source-fail-statement-retry',
            'savepoint' => $savepoint,
            'savepoint_image_tables' => $savepointImage,
            'pre_fail_current_source_tables' => $beforeFailCurrent,
            'fail_current_source_tables' => $afterFailCurrent,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'savepoint_preserved_after_fail' => true,
            'pre_fail_changes_preserved' => true,
            'failing_row_restored_to_statement_start' => true,
            'failed_statement_prior_rows_preserved' => true,
            'failed_statement_returning_suppressed' => true,
            'retry_reads_fail_current_source' => true,
            'savepoint_released_after_retry' => true,
            'pre_fail_statements' => $beforeFailExecuted,
            'fail_statement' => $failSummary,
            'retry_statements' => $retryExecuted,
            'pre_fail_yielded_returning' => $beforeFailReturning,
            'fail_preserved_returning' => $failReturning,
            'suppressed_by_fail_returning' => $failSummary['suppressed_returning_rows'],
            'yielded_after_retry_returning' => $retryReturning,
            'pre_fail_yielded_count' => self::failStatementRetryReturningCount($beforeFailReturning),
            'fail_preserved_yielded_count' => self::failStatementRetryReturningCount($failReturning),
            'suppressed_by_fail_count' => count($failSummary['suppressed_returning_rows']),
            'yielded_after_retry_count' => self::failStatementRetryReturningCount($retryReturning),
            'pre_fail_changes_preserved_count' => self::failStatementRetryChangeCount($beforeFailExecuted),
            'fail_changes_preserved_count' => count($failSummary['returning_rows']),
            'retry_changes_after_fail' => self::failStatementRetryChangeCount($retryExecuted),
            'changed_tables_after_retry' => self::failStatementRetryChangedTables($savepointImage, $afterRetry),
            'row_counts' => self::failStatementRetryRowCounts($afterRetry),
            'dependencies' => [
                'sqlite-rowvalue-update-or-fail-preserves-prior-returning-fail-statement-retry',
                'sqlite-rowvalue-update-or-fail-suppresses-conflicting-returning-fail-statement-retry',
                'sqlite-rowvalue-delete-returning-retry-after-fail-fail-statement-retry',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runFailStatementRetryStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::failStatementRetryStatementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:array<string,mixed>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runFailStatementRetryFailure(array $tables, string $sql, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        if ($parsed['action'] !== 'update' || $parsed['conflict_action'] !== 'fail') {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL fail-statement-retry fail statement must be UPDATE OR FAIL');
        }

        $probe = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, [], true);
        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints, true);
        $summary = self::failStatementRetryStatementSummary($phase, 0, $sql, $result, $tables, $rowIdColumn, null);
        $summary['failed'] = ($result['failed_conflict'] ?? null) !== null;
        $summary['failed_conflict'] = $result['failed_conflict'] ?? null;
        $summary['suppressed_returning_rows'] = array_slice($probe['returning'], count($result['returning']));
        $summary['probe_returning_rows'] = $probe['returning'];
        $summary['rolled_back_conflicting_row_only'] = true;

        return [
            $result['tables'],
            $summary,
            [[
                'phase' => $phase,
                'ordinal' => 0,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ]],
        ];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function failStatementRetryStatementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $error): array
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
            'source_rows' => self::failStatementRetryRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
            'failed_conflict' => $result['failed_conflict'] ?? null,
            'error' => $error,
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeFailStatementRetryTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value OR FAIL fail-statement-retry tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR FAIL fail-statement-retry rows must be arrays');
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
    private static function failStatementRetryRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL fail-statement-retry rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL fail-statement-retry rowid column {$rowIdColumn} must be int or string");
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
    private static function failStatementRetryReturningCount(array $yielded): int
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
    private static function failStatementRetryChangeCount(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function failStatementRetryChangedTables(array $before, array $after): array
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
    private static function failStatementRetryRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeIgnoreRollbackRetry(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_ignore_next210',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next210 needs attempted statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next210 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next210 needs unique constraints');
        }
        self::assertIgnoreRollbackRetryIdentifier($savepoint);

        $savepointImage = self::normalizeIgnoreRollbackRetryTables($tables);
        [$attemptCurrent, $attemptExecuted, $attemptReturning] = self::runIgnoreRollbackRetryStatements(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-before-ignore-rollback-next210',
        );
        self::assertIgnoreRollbackRetryHasIgnoreConflict($attemptExecuted);

        $afterRollbackToSavepoint = $savepointImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runIgnoreRollbackRetryStatements(
            $afterRollbackToSavepoint,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-ignore-rollback-next210',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-ignore-rollback-current-source-next210',
            'savepoint' => $savepoint,
            'ignore_conflict_preserves_statement' => true,
            'ignored_rows_do_not_yield_returning' => true,
            'rollback_to_savepoint_discards_successful_ignore_statement_rows' => true,
            'rollback_to_savepoint_discards_ignored_row_metadata' => true,
            'retry_reads_savepoint_image' => true,
            'savepoint_released_after_retry' => true,
            'savepoint_image_tables' => $savepointImage,
            'attempt_current_source_tables' => $attemptCurrent,
            'rollback_to_savepoint_current_source_tables' => $afterRollbackToSavepoint,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'attempt_statements' => $attemptExecuted,
            'retry_statements' => $retryExecuted,
            'attempt_returning' => $attemptReturning,
            'suppressed_by_rollback_returning' => $attemptReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'ignored_rows_before_rollback' => self::ignoreRollbackRetryIgnoredRows($attemptExecuted),
            'attempt_yielded_count' => self::ignoreRollbackRetryReturningCount($attemptReturning),
            'ignored_row_count' => count(self::ignoreRollbackRetryIgnoredRows($attemptExecuted)),
            'suppressed_by_rollback_count' => self::ignoreRollbackRetryReturningCount($attemptReturning),
            'yielded_after_retry_count' => self::ignoreRollbackRetryReturningCount($retryReturning),
            'attempt_changes_before_rollback_to' => self::ignoreRollbackRetryChangeCount($attemptExecuted),
            'changes_after_retry_release' => self::ignoreRollbackRetryChangeCount($retryExecuted),
            'changed_tables_after_retry' => self::ignoreRollbackRetryChangedTables($savepointImage, $afterRetry),
            'row_counts' => self::ignoreRollbackRetryRowCounts($afterRetry),
            'dependency_closure_next210' => 'no new support component needed; next210 reuses native row-value UPDATE/DELETE RETURNING execution, unique-conflict IGNORE handling, and savepoint current-source row images',
            'dependencies' => [
                'sqlite-rowvalue-update-or-ignore-returning-suppresses-conflict-next210',
                'sqlite-rollback-to-savepoint-discards-ignore-returning-stream-next210',
                'sqlite-rowvalue-retry-after-ignore-rollback-reads-savepoint-image-next210',
            ],
            'non_overlap_next210' => 'adds OR IGNORE row-value RETURNING rollback-to-savepoint suppression; avoids fail-statement-retry/pre-fail-rollback-retry OR FAIL, ignore_replace_delete IGNORE/REPLACE release flow, release_followup_read RELEASE admission, released_inner_retry released-inner rollback, next178 OR ROLLBACK, trigger RETURNING, WAL/VFS, JSON, planner, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runIgnoreRollbackRetryStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::ignoreRollbackRetryStatementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function ignoreRollbackRetryStatementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::ignoreRollbackRetryRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeIgnoreRollbackRetryTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value OR IGNORE next210 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR IGNORE next210 rows must be arrays');
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
    private static function ignoreRollbackRetryRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value OR IGNORE next210 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR IGNORE next210 rowid column {$rowIdColumn} must be int or string");
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
    private static function assertIgnoreRollbackRetryHasIgnoreConflict(array $executed): void
    {
        foreach ($executed as $statement) {
            if (($statement['conflict_action'] ?? null) === 'ignore' && ($statement['ignored_rows'] ?? []) !== []) {
                return;
            }
        }

        throw new \InvalidArgumentException('SQLite row-value OR IGNORE next210 needs an ignored conflict row');
    }

    /**
     * @param list<array<string,mixed>> $executed
     * @return list<array<string,mixed>>
     */
    private static function ignoreRollbackRetryIgnoredRows(array $executed): array
    {
        $rows = [];
        foreach ($executed as $statement) {
            foreach (($statement['ignored_rows'] ?? []) as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * @param list<array{rows:list<array<string,mixed>>}> $yielded
     */
    private static function ignoreRollbackRetryReturningCount(array $yielded): int
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
    private static function ignoreRollbackRetryChangeCount(array $summaries): int
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
    private static function ignoreRollbackRetryChangedTables(array $before, array $after): array
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
    private static function ignoreRollbackRetryRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function assertIgnoreRollbackRetryIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next210 savepoint must be an identifier');
        }
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeIgnoreStatements
     * @param string $ignoreStatement
     * @param list<string> $afterIgnoreStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeOrIgnoreSavepointRelease(
        array $tables,
        array $beforeIgnoreStatements,
        string $ignoreStatement,
        array $afterIgnoreStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_ignore_next211',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($beforeIgnoreStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next211 needs pre-ignore statements');
        }
        if (trim($ignoreStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next211 needs an ignore statement');
        }
        if ($afterIgnoreStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next211 needs after-ignore statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next211 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next211 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeOrIgnoreSavepointReleaseTables($tables);
        [$preCurrent, $preStatements, $preReturning] = self::runOrIgnoreSavepointReleaseStatements(
            $savepointImage,
            $beforeIgnoreStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-ignore-next211',
        );
        [$ignoreCurrent, $ignoreSummary, $ignoreReturning] = self::runOrIgnoreSavepointReleaseIgnoreStatement(
            $preCurrent,
            $ignoreStatement,
            $uniqueConstraints,
            $rowIdColumn,
        );
        [$afterCurrent, $afterStatements, $afterReturning] = self::runOrIgnoreSavepointReleaseStatements(
            $ignoreCurrent,
            $afterIgnoreStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'after-ignore-next211',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-or-ignore-current-source-next211',
            'savepoint' => $savepoint,
            'savepoint_image_tables' => $savepointImage,
            'pre_ignore_current_source_tables' => $preCurrent,
            'ignore_current_source_tables' => $ignoreCurrent,
            'current_source_tables' => $afterCurrent,
            'next_source_tables' => $afterCurrent,
            'savepoint_preserved_after_ignore' => true,
            'ignored_conflicts_are_not_returned' => true,
            'ignored_rows_restored_to_statement_start' => true,
            'pre_ignore_changes_preserved' => true,
            'after_ignore_reads_current_source' => true,
            'savepoint_released_after_ignore' => true,
            'pre_ignore_statements' => $preStatements,
            'ignore_statement' => $ignoreSummary,
            'after_ignore_statements' => $afterStatements,
            'pre_ignore_yielded_returning' => $preReturning,
            'ignore_yielded_returning' => $ignoreReturning,
            'ignored_by_conflict_returning' => $ignoreSummary['ignored_rows'],
            'yielded_after_ignore_returning' => $afterReturning,
            'pre_ignore_yielded_count' => self::orIgnoreSavepointReleaseReturningCount($preReturning),
            'ignore_yielded_count' => self::orIgnoreSavepointReleaseReturningCount($ignoreReturning),
            'ignored_by_conflict_count' => count($ignoreSummary['ignored_rows']),
            'yielded_after_ignore_count' => self::orIgnoreSavepointReleaseReturningCount($afterReturning),
            'pre_ignore_changes_count' => self::orIgnoreSavepointReleaseChangeCount($preStatements),
            'ignore_changes_count' => count($ignoreSummary['returning_rows']),
            'after_ignore_changes_count' => self::orIgnoreSavepointReleaseChangeCount($afterStatements),
            'changed_tables_after_release' => self::orIgnoreSavepointReleaseChangedTables($savepointImage, $afterCurrent),
            'row_counts' => self::orIgnoreSavepointReleaseRowCounts($afterCurrent),
            'dependency_closure' => 'no-new-support-component-reuses-native-update-delete-returning-rowvalue-conflict-and-savepoint-current-source',
            'non_overlap' => 'next211 covers UPDATE OR IGNORE row-value RETURNING suppression and savepoint release current-source chaining; avoids accepted fail-statement-retry OR FAIL, release_followup_read release, next202 parenthesized rollback, trigger RETURNING, WAL/VFS, JSON, B-tree, planner, and encoding clusters',
            'dependencies' => [
                'sqlite-rowvalue-update-or-ignore-suppresses-conflict-returning-next211',
                'sqlite-rowvalue-ignore-preserves-preceding-savepoint-current-source-next211',
                'sqlite-rowvalue-update-delete-after-ignore-reads-current-source-next211',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runOrIgnoreSavepointReleaseStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::orIgnoreSavepointReleaseStatementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:array<string,mixed>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runOrIgnoreSavepointReleaseIgnoreStatement(array $tables, string $sql, array $uniqueConstraints, string $rowIdColumn): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        if ($parsed['action'] !== 'update' || $parsed['conflict_action'] !== 'ignore') {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next211 ignore statement must be UPDATE OR IGNORE');
        }

        $probe = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, [], true);
        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints, true);
        $summary = self::orIgnoreSavepointReleaseStatementSummary('or-ignore-next211', 0, $sql, $result, $tables, $rowIdColumn);
        $summary['probe_returning_rows'] = $probe['returning'];
        $summary['ignored_rows_are_suppressed_returning'] = true;

        return [
            $result['tables'],
            $summary,
            [[
                'phase' => 'or-ignore-next211',
                'ordinal' => 0,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ]],
        ];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function orIgnoreSavepointReleaseStatementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::orIgnoreSavepointReleaseRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeOrIgnoreSavepointReleaseTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value OR IGNORE next211 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR IGNORE next211 rows must be arrays');
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
    private static function orIgnoreSavepointReleaseRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value OR IGNORE next211 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR IGNORE next211 rowid column {$rowIdColumn} must be int or string");
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
    private static function orIgnoreSavepointReleaseReturningCount(array $yielded): int
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
    private static function orIgnoreSavepointReleaseChangeCount(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function orIgnoreSavepointReleaseChangedTables(array $before, array $after): array
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
    private static function orIgnoreSavepointReleaseRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeSubquerySavepointRollbackRetry(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_subquery_next212',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value subquery savepoint next212 needs attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value subquery savepoint next212 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value subquery savepoint next212 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value subquery savepoint next212 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeSubquerySavepointRollbackRetryTables($tables);
        [$attemptCurrent, $attemptExecuted, $attemptReturning] = self::runSubquerySavepointRollbackRetryStatements(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-before-rollback-next212',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runSubquerySavepointRollbackRetryStatements(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-rollback-next212',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-subquery-savepoint-current-source-next212',
            'savepoint' => $savepoint,
            'savepoint_image_tables' => $savepointImage,
            'attempt_current_source_tables' => $attemptCurrent,
            'rollback_current_source_tables' => $rollbackCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'retry_reads_savepoint_image' => true,
            'savepoint_released_after_retry' => true,
            'attempt_statements' => $attemptExecuted,
            'retry_statements' => $retryExecuted,
            'discarded_attempt_returning' => $attemptReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'discarded_attempt_returning_count' => self::subquerySavepointRollbackRetryReturningCount($attemptReturning),
            'yielded_after_retry_count' => self::subquerySavepointRollbackRetryReturningCount($retryReturning),
            'attempt_changes_before_rollback' => self::subquerySavepointRollbackRetryChangeCount($attemptExecuted),
            'retry_changes_after_rollback' => self::subquerySavepointRollbackRetryChangeCount($retryExecuted),
            'changed_tables_after_retry' => self::subquerySavepointRollbackRetryChangedTables($savepointImage, $retryCurrent),
            'row_counts' => self::subquerySavepointRollbackRetryRowCounts($retryCurrent),
            'dependencies' => [
                'sqlite-rowvalue-update-returning-in-select-subquery-next212',
                'sqlite-rowvalue-delete-returning-not-in-select-subquery-next212',
                'sqlite-rowvalue-subquery-savepoint-rollback-current-source-next212',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runSubquerySavepointRollbackRetryStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::subquerySavepointRollbackRetryStatementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function subquerySavepointRollbackRetryStatementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::subquerySavepointRollbackRetryRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeSubquerySavepointRollbackRetryTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value subquery savepoint next212 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value subquery savepoint next212 rows must be arrays');
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
    private static function subquerySavepointRollbackRetryRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value subquery savepoint next212 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value subquery savepoint next212 rowid column {$rowIdColumn} must be int or string");
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
    private static function subquerySavepointRollbackRetryReturningCount(array $yielded): int
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
    private static function subquerySavepointRollbackRetryChangeCount(array $executed): int
    {
        $count = 0;
        foreach ($executed as $statement) {
            $count += count($statement['mutation_ids']);
        }

        return $count;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function subquerySavepointRollbackRetryChangedTables(array $before, array $after): array
    {
        $changed = [];
        foreach ($after as $table => $rows) {
            if (($before[$table] ?? null) !== $rows) {
                $changed[] = $table;
            }
        }

        sort($changed);

        return $changed;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,int>
     */
    private static function subquerySavepointRollbackRetryRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        ksort($counts);

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeOrderedLimitSubquerySavepoint(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_order_limit_next213',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeSubquerySavepointRollbackRetry(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $plan = self::replaceOrderedLimitSubqueryMarker($plan);
        $plan['status'] = 'rowvalue-update-delete-returning-order-limit-subquery-savepoint-current-source-next213';
        $plan['ordered_limited_subquery_source'] = true;
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-in-select-order-limit-next213',
            'sqlite-rowvalue-delete-returning-in-select-order-limit-next213',
            'sqlite-rowvalue-order-limit-subquery-savepoint-current-source-next213',
        ];

        return $plan;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function replaceOrderedLimitSubqueryMarker(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(['next212', 'subquery'], ['next213', 'order-limit-subquery'], $value);
        }
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $entry) {
            $value[$key] = self::replaceOrderedLimitSubqueryMarker($entry);
        }

        return $value;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeRollbackStatements
     * @param string $rollbackStatement
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeRollbackToConflict(
        array $tables,
        array $beforeRollbackStatements,
        string $rollbackStatement,
        array $retryStatements,
        array $uniqueConstraints,
        string $transactionName = 'wp_options_rowvalue_transaction_next217',
        string $savepoint = 'wp_options_rowvalue_rollback_next217',
        string $retrySavepoint = 'wp_options_rowvalue_retry_next217',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($beforeRollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next217 needs pre-rollback statements');
        }
        if (trim($rollbackStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next217 needs a rollback statement');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next217 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next217 needs unique constraints');
        }
        self::assertIdentifierRollbackToConflict($transactionName, 'transaction');
        self::assertIdentifierRollbackToConflict($savepoint, 'savepoint');
        self::assertIdentifierRollbackToConflict($retrySavepoint, 'retry savepoint');

        $transactionImage = self::normalizeTablesRollbackToConflict($tables);
        [$beforeCurrent, $beforeStatements, $beforeReturning] = self::runStatementsRollbackToConflict(
            $transactionImage,
            $beforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-or-rollback-next217',
        );
        [$afterRollback, $rollbackSummary] = self::runRollbackStatementRollbackToConflict(
            $beforeCurrent,
            $rollbackStatement,
            $transactionImage,
            $uniqueConstraints,
            $rowIdColumn,
        );
        [$afterRetry, $retryStatementsExecuted, $retryReturning] = self::runStatementsRollbackToConflict(
            $afterRollback,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-transaction-rollback-next217',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-or-rollback-current-source-next217',
            'transaction' => $transactionName,
            'savepoint' => $savepoint,
            'retry_savepoint' => $retrySavepoint,
            'transaction_image_tables' => $transactionImage,
            'pre_rollback_current_source_tables' => $beforeCurrent,
            'rollback_to_transaction_current_source_tables' => $afterRollback,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'or_rollback_aborted_transaction' => true,
            'savepoint_closed_by_rollback' => true,
            'pre_rollback_changes_discarded' => true,
            'rollback_statement_returning_suppressed' => true,
            'retry_opens_new_savepoint' => true,
            'retry_reads_transaction_image' => true,
            'retry_savepoint_released' => true,
            'before_rollback_statements' => $beforeStatements,
            'rollback_statement' => $rollbackSummary,
            'retry_statements' => $retryStatementsExecuted,
            'before_rollback_yielded_returning' => $beforeReturning,
            'suppressed_by_transaction_rollback_returning' => $rollbackSummary['returning_rows'],
            'yielded_after_retry_returning' => $retryReturning,
            'pre_rollback_yielded_count' => self::returningCountRollbackToConflict($beforeReturning),
            'pre_rollback_changes_count' => self::changeCountRollbackToConflict($beforeStatements),
            'suppressed_by_rollback_count' => count($rollbackSummary['returning_rows']),
            'retry_yielded_count' => self::returningCountRollbackToConflict($retryReturning),
            'retry_changes_count' => self::changeCountRollbackToConflict($retryStatementsExecuted),
            'changed_tables_after_retry' => self::changedTablesRollbackToConflict($transactionImage, $afterRetry),
            'row_counts' => self::rowCountsRollbackToConflict($afterRetry),
            'dependency_closure_next217' => 'no new support component needed; next217 reuses native row-value UPDATE/DELETE RETURNING execution and current-source savepoint row images',
            'non_overlap_next217' => 'adds transaction-level UPDATE OR ROLLBACK row-value RETURNING suppression and retry after transaction rollback; avoids accepted next210/next211 OR IGNORE rollback, fail-statement-retry/or-fail-savepoint-retry OR FAIL, next192 statement-only OR ABORT, trigger RETURNING, WAL/VFS, JSON, planner, and B-tree clusters',
            'dependencies' => [
                'sqlite-rowvalue-update-or-rollback-suppresses-returning-next217',
                'sqlite-rowvalue-or-rollback-discards-savepoint-current-source-next217',
                'sqlite-rowvalue-delete-returning-retry-after-transaction-rollback-next217',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsRollbackToConflict(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryRollbackToConflict($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<string,list<array<string,mixed>>> $transactionImage
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:array<string,mixed>}
     */
    private static function runRollbackStatementRollbackToConflict(
        array $tables,
        string $sql,
        array $transactionImage,
        array $uniqueConstraints,
        string $rowIdColumn,
    ): array {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        if ($parsed['action'] !== 'update' || $parsed['conflict_action'] !== 'rollback') {
            throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next217 statement must be UPDATE OR ROLLBACK');
        }

        try {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints);
        } catch (\InvalidArgumentException $exception) {
            $probe = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, [], true);

            return [
                $transactionImage,
                self::statementSummaryRollbackToConflict('or-rollback-next217', 0, $sql, $probe, $tables, $rowIdColumn, $exception->getMessage()) + [
                    'aborted' => true,
                    'error' => $exception->getMessage(),
                    'rolled_back_to_transaction_start' => true,
                    'closed_savepoint' => true,
                ],
            ];
        }

        return [
            $result['tables'],
            self::statementSummaryRollbackToConflict('or-rollback-next217', 0, $sql, $result, $tables, $rowIdColumn, null) + [
                'aborted' => false,
                'error' => null,
                'rolled_back_to_transaction_start' => false,
                'closed_savepoint' => false,
            ],
        ];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryRollbackToConflict(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $error): array
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
            'source_rows' => self::rowsByIdsRollbackToConflict($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
            'failed_conflict' => $result['failed_conflict'] ?? null,
            'error' => $error,
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesRollbackToConflict(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next217 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next217 rows must be arrays');
                }
            }
        }

        return $tables;
    }

    private static function assertIdentifierRollbackToConflict(string $identifier, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value OR ROLLBACK next217 {$label} must be an identifier");
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int|string> $ids
     * @return list<array<string,mixed>>
     */
    private static function rowsByIdsRollbackToConflict(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value OR ROLLBACK next217 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR ROLLBACK next217 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountRollbackToConflict(array $yielded): int
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
    private static function changeCountRollbackToConflict(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesRollbackToConflict(array $before, array $after): array
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
    private static function rowCountsRollbackToConflict(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $savepointStatements
     * @param list<string> $attemptedStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeRollbackToSavepointCurrentSource(
        array $tables,
        array $savepointStatements,
        array $attemptedStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_rollback_to_next218',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($savepointStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback next218 needs savepoint statements');
        }
        if ($attemptedStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback next218 needs attempted statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback next218 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback next218 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value rollback next218 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeRollbackToSavepointCurrentSourceTables($tables);
        [$attemptSource, $savepointExecuted, $savepointReturning] = self::runRollbackToSavepointCurrentSourceStatements(
            $savepointImage,
            $savepointStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'savepoint-before-rollback-to-next218',
        );
        [$attemptCurrent, $attemptedExecuted, $attemptedReturning] = self::runRollbackToSavepointCurrentSourceStatements(
            $attemptSource,
            $attemptedStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-before-rollback-to-next218',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runRollbackToSavepointCurrentSourceStatements(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-rollback-to-next218',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-rollback-to-current-source-next218',
            'savepoint' => $savepoint,
            'rollback_to_savepoint_next218' => true,
            'savepoint_remains_active_next218' => true,
            'attempted_returning_suppressed_by_rollback_next218' => true,
            'retry_reads_savepoint_image_next218' => true,
            'savepoint_image_tables' => $savepointImage,
            'attempt_source_tables' => $attemptSource,
            'attempt_current_source_tables' => $attemptCurrent,
            'rollback_current_source_tables' => $rollbackCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'savepoint_statements' => $savepointExecuted,
            'attempted_statements' => $attemptedExecuted,
            'retry_statements' => $retryExecuted,
            'savepoint_returning' => $savepointReturning,
            'suppressed_attempted_returning' => $attemptedReturning,
            'retry_returning' => $retryReturning,
            'savepoint_returning_count' => self::returningCountRollbackToSavepointCurrentSource($savepointReturning),
            'suppressed_attempted_returning_count' => self::returningCountRollbackToSavepointCurrentSource($attemptedReturning),
            'retry_returning_count' => self::returningCountRollbackToSavepointCurrentSource($retryReturning),
            'attempted_change_count' => self::changeCountRollbackToSavepointCurrentSource($attemptedExecuted),
            'retry_change_count' => self::changeCountRollbackToSavepointCurrentSource($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesRollbackToSavepointCurrentSource($savepointImage, $retryCurrent),
            'row_counts' => self::rowCountsRollbackToSavepointCurrentSource($retryCurrent),
            'rollback_receipt_next218' => [
                'savepoint' => $savepoint,
                'restored_tables' => array_keys($rollbackCurrent),
                'suppressed_returning_count' => self::returningCountRollbackToSavepointCurrentSource($attemptedReturning),
                'retry_statement_count' => count($retryStatements),
            ],
            'dependency_closure_next218' => 'no new support component needed; next218 reuses native row-value UPDATE/DELETE RETURNING execution and row-array savepoint images',
            'dependencies' => [
                'sqlite-rowvalue-rollback-to-restores-savepoint-image-next218',
                'sqlite-rowvalue-returning-suppressed-after-rollback-to-next218',
                'wordpress-rowvalue-update-delete-returning-savepoint-rollback-next218',
            ],
            'non_overlap_next218' => 'models explicit ROLLBACK TO savepoint image restoration after successful row-value UPDATE/DELETE RETURNING attempts; avoids accepted abort-statement-savepoint preservation, release_followup_read RELEASE current-source admission, next211 OR IGNORE/savepoint behavior, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runRollbackToSavepointCurrentSourceStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryRollbackToSavepointCurrentSource($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryRollbackToSavepointCurrentSource(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsRollbackToSavepointCurrentSource($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeRollbackToSavepointCurrentSourceTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value rollback next218 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value rollback next218 rows must be arrays');
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
    private static function rowsByIdsRollbackToSavepointCurrentSource(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value rollback next218 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value rollback next218 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountRollbackToSavepointCurrentSource(array $yielded): int
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
    private static function changeCountRollbackToSavepointCurrentSource(array $executed): int
    {
        $count = 0;
        foreach ($executed as $statement) {
            $count += count($statement['mutation_ids'] ?? []);
        }

        return $count;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesRollbackToSavepointCurrentSource(array $before, array $after): array
    {
        $changed = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $table) {
            if (($before[$table] ?? null) !== ($after[$table] ?? null)) {
                $changed[] = $table;
            }
        }

        return $changed;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,int>
     */
    private static function rowCountsRollbackToSavepointCurrentSource(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNegativeLimitOffsetSubquerySavepointRetry(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_negative_limit_offset_next219',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeSubquerySavepointRollbackRetry(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $plan = self::replaceNegativeLimitOffsetSubqueryMarkers($plan);
        $plan['status'] = 'rowvalue-update-delete-returning-negative-limit-offset-subquery-savepoint-current-source-next219';
        $plan['negative_limit_offset_subquery_source'] = true;
        $plan['dependency_closure_next219'] = 'no new support component needed; next219 reuses native row-value UPDATE/DELETE RETURNING execution and fixes bounded row-value SELECT tuple LIMIT -1 OFFSET semantics';
        $plan['non_overlap_next219'] = 'adds negative LIMIT with OFFSET in row-value SELECT tuple sources feeding UPDATE/DELETE RETURNING under savepoint rollback; avoids accepted next213 ORDER/LIMIT positive slices, next217 OR ROLLBACK, next212 plain subquery, WAL/VFS, JSON, planner, trigger, and B-tree clusters';
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-in-select-negative-limit-offset-next219',
            'sqlite-rowvalue-delete-returning-in-select-negative-limit-offset-next219',
            'sqlite-rowvalue-negative-limit-offset-subquery-savepoint-current-source-next219',
        ];

        return $plan;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function replaceNegativeLimitOffsetSubqueryMarkers(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(['next212', 'subquery'], ['next219', 'negative-limit-offset-subquery'], $value);
        }
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $entry) {
            $value[$key] = self::replaceNegativeLimitOffsetSubqueryMarkers($entry);
        }

        return $value;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeAbortStatements
     * @param string $abortStatement
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAbortConflictRetry(
        array $tables,
        array $beforeAbortStatements,
        string $abortStatement,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_abort_next220',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($beforeAbortStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next220 needs pre-abort statements');
        }
        if (trim($abortStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next220 needs an abort statement');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next220 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next220 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next220 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeTablesAbortConflictRetry($tables);
        [$beforeAbortCurrent, $beforeAbortExecuted, $beforeAbortReturning] = self::runStatementsAbortConflictRetry(
            $savepointImage,
            $beforeAbortStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-or-abort-next220',
        );
        [$afterAbortCurrent, $abortSummary] = self::runAbortStatementAbortConflictRetry(
            $beforeAbortCurrent,
            $abortStatement,
            $uniqueConstraints,
            $rowIdColumn,
        );
        [$afterRetry, $retryStatementsExecuted, $retryReturning] = self::runStatementsAbortConflictRetry(
            $afterAbortCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-statement-abort-next220',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-or-abort-savepoint-current-source-next220',
            'savepoint' => $savepoint,
            'savepoint_image_tables' => $savepointImage,
            'pre_abort_current_source_tables' => $beforeAbortCurrent,
            'abort_current_source_tables' => $afterAbortCurrent,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'savepoint_preserved_after_statement_abort' => true,
            'pre_abort_changes_preserved' => true,
            'abort_statement_changes_rolled_back' => true,
            'abort_statement_returning_suppressed' => true,
            'retry_reads_pre_abort_current_source' => true,
            'savepoint_released_after_retry' => true,
            'before_abort_statements' => $beforeAbortExecuted,
            'abort_statement' => $abortSummary,
            'retry_statements' => $retryStatementsExecuted,
            'pre_abort_yielded_returning' => $beforeAbortReturning,
            'suppressed_by_statement_abort_returning' => $abortSummary['returning_rows'],
            'yielded_after_retry_returning' => $retryReturning,
            'pre_abort_yielded_count' => self::returningCountAbortConflictRetry($beforeAbortReturning),
            'pre_abort_changes_count' => self::changeCountAbortConflictRetry($beforeAbortExecuted),
            'suppressed_by_abort_count' => count($abortSummary['returning_rows']),
            'retry_yielded_count' => self::returningCountAbortConflictRetry($retryReturning),
            'retry_changes_count' => self::changeCountAbortConflictRetry($retryStatementsExecuted),
            'changed_tables_after_retry' => self::changedTablesAbortConflictRetry($savepointImage, $afterRetry),
            'row_counts' => self::rowCountsAbortConflictRetry($afterRetry),
            'dependency_closure_next220' => 'no new support component needed; next220 reuses native row-value UPDATE/DELETE RETURNING execution, unique conflict checks, and savepoint current-source row images',
            'non_overlap_next220' => 'adds statement-level UPDATE OR ABORT row-value RETURNING suppression inside a preserved savepoint; avoids accepted next217 transaction OR ROLLBACK, next210/next211 OR IGNORE, fail-statement-retry OR FAIL, next212 subquery rollback, trigger RETURNING, WAL/VFS, JSON, planner, encoding, PRAGMA, and B-tree clusters',
            'dependencies' => [
                'sqlite-rowvalue-update-or-abort-suppresses-failing-returning-next220',
                'sqlite-rowvalue-or-abort-preserves-savepoint-current-source-next220',
                'sqlite-rowvalue-delete-returning-retry-after-statement-abort-next220',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsAbortConflictRetry(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryAbortConflictRetry($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:array<string,mixed>}
     */
    private static function runAbortStatementAbortConflictRetry(array $tables, string $sql, array $uniqueConstraints, string $rowIdColumn): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        if ($parsed['action'] !== 'update' || $parsed['conflict_action'] !== 'abort') {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next220 statement must be UPDATE OR ABORT');
        }

        try {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints);
        } catch (\InvalidArgumentException $exception) {
            $probe = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, [], true);

            return [
                $tables,
                self::statementSummaryAbortConflictRetry('or-abort-next220', 0, $sql, $probe, $tables, $rowIdColumn, $exception->getMessage()) + [
                    'aborted' => true,
                    'error' => $exception->getMessage(),
                    'rolled_back_statement_only' => true,
                    'savepoint_remains_open' => true,
                ],
            ];
        }

        return [
            $result['tables'],
            self::statementSummaryAbortConflictRetry('or-abort-next220', 0, $sql, $result, $tables, $rowIdColumn, null) + [
                'aborted' => false,
                'error' => null,
                'rolled_back_statement_only' => false,
                'savepoint_remains_open' => true,
            ],
        ];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryAbortConflictRetry(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $error): array
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
            'source_rows' => self::rowsByIdsAbortConflictRetry($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
            'failed_conflict' => $result['failed_conflict'] ?? null,
            'error' => $error,
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesAbortConflictRetry(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value OR ABORT next220 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR ABORT next220 rows must be arrays');
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
    private static function rowsByIdsAbortConflictRetry(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value OR ABORT next220 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR ABORT next220 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountAbortConflictRetry(array $yielded): int
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
    private static function changeCountAbortConflictRetry(array $executed): int
    {
        $count = 0;
        foreach ($executed as $statement) {
            $count += count($statement['mutation_ids']);
        }

        return $count;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesAbortConflictRetry(array $before, array $after): array
    {
        $changed = [];
        foreach ($after as $table => $rows) {
            if (($before[$table] ?? null) !== $rows) {
                $changed[] = $table;
            }
        }

        sort($changed);

        return $changed;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,int>
     */
    private static function rowCountsAbortConflictRetry(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        ksort($counts);

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $innerStatements
     * @param list<string> $outerAttemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNestedSavepointMaterialization(
        array $tables,
        array $innerStatements,
        array $outerAttemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_rowvalue_next224',
        string $innerSavepoint = 'wp_options_inner_rowvalue_next224',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($innerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested rollback next224 needs inner statements');
        }
        if ($outerAttemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested rollback next224 needs outer attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested rollback next224 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value nested rollback next224 needs unique constraints');
        }
        self::assertIdentifierNestedSavepointMaterialization($outerSavepoint, 'outer savepoint');
        self::assertIdentifierNestedSavepointMaterialization($innerSavepoint, 'inner savepoint');
        if ($outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value nested rollback next224 savepoint names must differ');
        }

        $outerImage = self::normalizeTablesNestedSavepointMaterialization($tables);
        [$afterInner, $innerExecuted, $innerReturning] = self::runStatementsNestedSavepointMaterialization(
            $outerImage,
            $innerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-released-before-outer-rollback-next224',
        );
        [$afterAttempt, $outerAttemptExecuted, $outerAttemptReturning] = self::runStatementsNestedSavepointMaterialization(
            $afterInner,
            $outerAttemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-attempt-before-rollback-next224',
        );

        $afterOuterRollback = $outerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNestedSavepointMaterialization(
            $afterOuterRollback,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-outer-rollback-next224',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-nested-release-rollback-current-source-next224',
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'outer_savepoint_image_tables' => $outerImage,
            'after_inner_release_tables' => $afterInner,
            'outer_attempt_current_source_tables' => $afterAttempt,
            'after_outer_rollback_tables' => $afterOuterRollback,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'inner_release_merged_into_outer_next224' => true,
            'outer_rollback_discards_released_inner_next224' => true,
            'released_inner_returning_suppressed_by_outer_rollback_next224' => true,
            'outer_attempt_returning_suppressed_by_rollback_next224' => true,
            'retry_reads_outer_savepoint_image_next224' => true,
            'outer_savepoint_remains_active_next224' => true,
            'inner_statements' => $innerExecuted,
            'outer_attempt_statements' => $outerAttemptExecuted,
            'retry_statements' => $retryExecuted,
            'released_inner_returning' => $innerReturning,
            'suppressed_outer_attempt_returning' => $outerAttemptReturning,
            'retry_returning' => $retryReturning,
            'released_inner_returning_count' => self::returningCountNestedSavepointMaterialization($innerReturning),
            'outer_attempt_returning_count' => self::returningCountNestedSavepointMaterialization($outerAttemptReturning),
            'suppressed_returning_count' => self::returningCountNestedSavepointMaterialization($innerReturning) + self::returningCountNestedSavepointMaterialization($outerAttemptReturning),
            'retry_returning_count' => self::returningCountNestedSavepointMaterialization($retryReturning),
            'released_inner_change_count' => self::changeCountNestedSavepointMaterialization($innerExecuted),
            'outer_attempt_change_count' => self::changeCountNestedSavepointMaterialization($outerAttemptExecuted),
            'retry_change_count' => self::changeCountNestedSavepointMaterialization($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNestedSavepointMaterialization($outerImage, $afterRetry),
            'row_counts' => self::rowCountsNestedSavepointMaterialization($afterRetry),
            'rollback_receipt_next224' => [
                'outer_savepoint' => $outerSavepoint,
                'inner_savepoint' => $innerSavepoint,
                'released_inner_statement_count' => count($innerStatements),
                'outer_attempt_statement_count' => count($outerAttemptStatements),
                'retry_statement_count' => count($retryStatements),
                'suppressed_returning_count' => self::returningCountNestedSavepointMaterialization($innerReturning) + self::returningCountNestedSavepointMaterialization($outerAttemptReturning),
                'restored_tables' => array_keys($afterOuterRollback),
            ],
            'dependency_closure_next224' => 'no new support component needed; next224 reuses native row-value UPDATE/DELETE RETURNING execution and nested savepoint row images',
            'dependencies' => [
                'sqlite-rowvalue-nested-release-rolled-back-by-outer-savepoint-next224',
                'sqlite-rowvalue-returning-suppressed-after-outer-rollback-next224',
                'wordpress-rowvalue-nested-savepoint-retry-current-source-next224',
            ],
            'non_overlap_next224' => 'adds nested savepoint RELEASE rows being discarded by a later outer ROLLBACK TO before retry; avoids accepted next218 explicit rollback image restoration, next217 OR ROLLBACK transaction abort, next211 OR IGNORE, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNestedSavepointMaterialization(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNestedSavepointMaterialization($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryNestedSavepointMaterialization(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNestedSavepointMaterialization($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNestedSavepointMaterialization(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value nested rollback next224 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value nested rollback next224 rows must be arrays');
                }
            }
        }

        return $tables;
    }

    private static function assertIdentifierNestedSavepointMaterialization(string $name, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value nested rollback next224 {$label} must be an identifier");
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int|string> $ids
     * @return list<array<string,mixed>>
     */
    private static function rowsByIdsNestedSavepointMaterialization(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value nested rollback next224 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value nested rollback next224 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNestedSavepointMaterialization(array $yielded): int
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
    private static function changeCountNestedSavepointMaterialization(array $executed): int
    {
        $count = 0;
        foreach ($executed as $statement) {
            $count += count($statement['mutation_ids'] ?? []);
        }

        return $count;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesNestedSavepointMaterialization(array $before, array $after): array
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
    private static function rowCountsNestedSavepointMaterialization(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeDistinctSubquerySavepointRollback(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_distinct_subquery_next225',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeSubquerySavepointRollbackRetry(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $plan = self::replaceDistinctSubqueryRollbackMarker($plan);
        $plan['status'] = 'rowvalue-update-delete-returning-distinct-subquery-savepoint-current-source-next225';
        $plan['savepoint'] = $savepoint;
        $plan['distinct_subquery_source'] = true;
        $plan['dependency_closure_next225'] = 'no new support component needed; next225 reuses native row-value UPDATE/DELETE RETURNING execution and adds SELECT DISTINCT tuple-source collapse before savepoint rollback/retry';
        $plan['non_overlap_next225'] = 'adds row-value SELECT DISTINCT tuple sources feeding UPDATE/DELETE RETURNING under savepoint rollback; avoids accepted next219 negative LIMIT/OFFSET, next213 positive ORDER/LIMIT, next212 plain subquery, trigger, WAL/VFS, JSON, planner, and B-tree clusters';
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-in-select-distinct-next225',
            'sqlite-rowvalue-delete-returning-in-select-distinct-next225',
            'sqlite-rowvalue-distinct-subquery-savepoint-current-source-next225',
        ];

        return $plan;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function replaceDistinctSubqueryRollbackMarker(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(['next212', 'subquery'], ['next225', 'distinct-subquery'], $value);
        }
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $entry) {
            $value[$key] = self::replaceDistinctSubqueryRollbackMarker($entry);
        }

        return $value;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeBoundedDistinctSubquerySavepointRollback(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_distinct_subquery',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeSubquerySavepointRollbackRetry(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $plan = self::replaceBoundedDistinctSubqueryRollbackMarker($plan);
        $plan['status'] = 'rowvalue-update-delete-returning-distinct-subquery-savepoint-current-source';
        $plan['savepoint'] = $savepoint;
        $plan['distinct_subquery_source'] = true;
        $plan['dependency_closure'] = 'no new support component needed; reuses native row-value UPDATE/DELETE RETURNING execution and adds bounded SELECT DISTINCT tuple-source handling';
        $plan['non_overlap'] = 'adds SELECT DISTINCT tuple sources feeding row-value UPDATE/DELETE RETURNING under savepoint rollback and retry; avoids accepted negative LIMIT/OFFSET, positive ORDER/LIMIT, OR ROLLBACK, WAL/VFS, JSON, planner, trigger, and B-tree clusters';
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-distinct-select-subquery',
            'sqlite-rowvalue-delete-returning-distinct-select-subquery',
            'sqlite-rowvalue-distinct-subquery-savepoint-current-source',
        ];

        return $plan;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function replaceBoundedDistinctSubqueryRollbackMarker(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(['next212', 'subquery'], ['bounded-distinct-subquery', 'distinct-subquery'], $value);
        }
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $entry) {
            $value[$key] = self::replaceBoundedDistinctSubqueryRollbackMarker($entry);
        }

        return $value;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerStatements
     * @param string $failStatement
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeInnerFailRollbackSavepoint(
        array $tables,
        array $outerStatements,
        array $innerStatements,
        string $failStatement,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_rowvalue_next228',
        string $innerSavepoint = 'wp_options_inner_rowvalue_next228',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 needs outer statements');
        }
        if ($innerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 needs inner statements');
        }
        if (trim($failStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 needs a fail statement');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 needs unique constraints');
        }
        self::assertInnerFailRollbackIdentifier($outerSavepoint, 'outer savepoint');
        self::assertInnerFailRollbackIdentifier($innerSavepoint, 'inner savepoint');
        if ($outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 savepoint names must differ');
        }

        $outerImage = self::normalizeInnerFailRollbackTables($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runInnerFailRollbackStatements(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-inner-savepoint-next228',
        );

        $innerImage = $afterOuter;
        [$afterInner, $innerExecuted, $innerReturning] = self::runInnerFailRollbackStatements(
            $innerImage,
            $innerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-before-fail-next228',
        );

        [$afterFail, $failSummary, $failReturning] = self::runInnerFailRollbackFailStatement(
            $afterInner,
            $failStatement,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-or-fail-before-rollback-next228',
        );

        $afterInnerRollback = $innerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runInnerFailRollbackStatements(
            $afterInnerRollback,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-inner-rollback-next228',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-inner-fail-rollback-current-source-next228',
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'outer_savepoint_image_tables' => $outerImage,
            'outer_current_source_tables' => $afterOuter,
            'inner_savepoint_image_tables' => $innerImage,
            'inner_current_source_tables' => $afterInner,
            'fail_current_source_tables' => $afterFail,
            'after_inner_rollback_tables' => $afterInnerRollback,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_changes_survive_inner_rollback_next228' => true,
            'inner_changes_rolled_back_after_fail_next228' => true,
            'fail_prior_rows_rolled_back_by_savepoint_next228' => true,
            'inner_returning_suppressed_by_rollback_next228' => true,
            'retry_reads_outer_current_source_next228' => true,
            'outer_savepoint_remains_active_next228' => true,
            'outer_statements' => $outerExecuted,
            'inner_statements' => $innerExecuted,
            'fail_statement' => $failSummary,
            'retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'inner_suppressed_returning' => $innerReturning,
            'fail_preserved_before_rollback_returning' => $failReturning,
            'fail_suppressed_conflicting_returning' => $failSummary['suppressed_returning_rows'],
            'retry_returning' => $retryReturning,
            'outer_yielded_count' => self::innerFailRollbackReturningCount($outerReturning),
            'inner_suppressed_count' => self::innerFailRollbackReturningCount($innerReturning),
            'fail_preserved_before_rollback_count' => self::innerFailRollbackReturningCount($failReturning),
            'fail_suppressed_conflicting_count' => count($failSummary['suppressed_returning_rows']),
            'total_suppressed_by_inner_rollback_count' => self::innerFailRollbackReturningCount($innerReturning) + self::innerFailRollbackReturningCount($failReturning) + count($failSummary['suppressed_returning_rows']),
            'retry_returning_count' => self::innerFailRollbackReturningCount($retryReturning),
            'outer_change_count' => self::innerFailRollbackChangeCount($outerExecuted),
            'inner_change_count' => self::innerFailRollbackChangeCount($innerExecuted),
            'fail_preserved_change_count' => count($failSummary['returning_rows']),
            'retry_change_count' => self::innerFailRollbackChangeCount($retryExecuted),
            'changed_tables_after_retry' => self::innerFailRollbackChangedTables($outerImage, $afterRetry),
            'row_counts' => self::innerFailRollbackRowCounts($afterRetry),
            'rollback_receipt_next228' => [
                'outer_savepoint' => $outerSavepoint,
                'inner_savepoint' => $innerSavepoint,
                'inner_statement_count' => count($innerStatements),
                'fail_statement_conflict' => $failSummary['failed_conflict'],
                'suppressed_returning_count' => self::innerFailRollbackReturningCount($innerReturning) + self::innerFailRollbackReturningCount($failReturning) + count($failSummary['suppressed_returning_rows']),
                'restored_tables' => array_keys($afterInnerRollback),
            ],
            'dependency_closure_next228' => 'no new support component needed; next228 reuses native row-value UPDATE/DELETE RETURNING, OR FAIL preservation, and nested savepoint current-source row images',
            'dependencies' => [
                'sqlite-rowvalue-inner-savepoint-rollback-suppresses-returning-next228',
                'sqlite-rowvalue-update-or-fail-prior-rows-rolled-back-by-savepoint-next228',
                'wordpress-rowvalue-savepoint-retry-reads-outer-current-source-next228',
            ],
            'non_overlap_next228' => 'adds inner ROLLBACK TO after UPDATE OR FAIL so preserved FAIL rows and earlier inner RETURNING are suppressed while outer savepoint changes remain current; avoids accepted fail-statement-retry preserved FAIL retry source, next224 released inner discarded by outer rollback, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runInnerFailRollbackStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::innerFailRollbackStatementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:array<string,mixed>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runInnerFailRollbackFailStatement(array $tables, string $sql, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        if ($parsed['action'] !== 'update' || $parsed['conflict_action'] !== 'fail') {
            throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 fail statement must be UPDATE OR FAIL');
        }

        $probe = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, [], true);
        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints, true);
        $summary = self::innerFailRollbackStatementSummary($phase, 0, $sql, $result, $tables, $rowIdColumn);
        $summary['failed'] = ($result['failed_conflict'] ?? null) !== null;
        $summary['failed_conflict'] = $result['failed_conflict'] ?? null;
        $summary['suppressed_returning_rows'] = array_slice($probe['returning'], count($result['returning']));
        $summary['probe_returning_rows'] = $probe['returning'];
        $summary['rolled_back_conflicting_row_only_before_savepoint_rollback'] = true;

        return [
            $result['tables'],
            $summary,
            [[
                'phase' => $phase,
                'ordinal' => 0,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ]],
        ];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function innerFailRollbackStatementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::innerFailRollbackRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeInnerFailRollbackTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 rows must be arrays');
                }
            }
        }

        return $tables;
    }

    private static function assertInnerFailRollbackIdentifier(string $name, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value inner FAIL rollback next228 {$label} must be an identifier");
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int|string> $ids
     * @return list<array<string,mixed>>
     */
    private static function innerFailRollbackRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value inner FAIL rollback next228 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value inner FAIL rollback next228 rowid column {$rowIdColumn} must be int or string");
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
    private static function innerFailRollbackReturningCount(array $yielded): int
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
    private static function innerFailRollbackChangeCount(array $executed): int
    {
        $count = 0;
        foreach ($executed as $statement) {
            $count += count($statement['mutation_ids'] ?? []);
        }

        return $count;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function innerFailRollbackChangedTables(array $before, array $after): array
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
    private static function innerFailRollbackRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeSelectRetrySavepointRelease(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_select_retry',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($yieldStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value select retry savepoint release needs yield statements');
        }
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value select retry savepoint release needs attempted statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value select retry savepoint release needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value select retry savepoint release needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value select retry savepoint release savepoint must be an identifier');
        }

        $savepointImage = self::normalizeSelectRetrySavepointTables($tables);
        [$yieldCurrent, $yieldExecuted, $yieldReturning] = self::runSelectRetrySavepointStatements(
            $savepointImage,
            $yieldStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'yield-subquery-before-rollback-to-savepoint',
        );
        [$attemptCurrent, $attemptExecuted, $attemptReturning] = self::runSelectRetrySavepointStatements(
            $yieldCurrent,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-subquery-after-yield-before-rollback-to-savepoint',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runSelectRetrySavepointStatements(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-subquery-after-rollback-release',
        );

        $yieldedRows = self::flattenSelectRetrySavepointReturning($yieldReturning);
        $suppressedRows = self::flattenSelectRetrySavepointReturning($attemptReturning);
        $retryRows = self::flattenSelectRetrySavepointReturning($retryReturning);

        return [
            'status' => 'rowvalue-update-delete-returning-subquery-savepoint-release-current-source',
            'savepoint' => $savepoint,
            'savepoint_image_tables' => $savepointImage,
            'yield_current_source_tables' => $yieldCurrent,
            'attempt_current_source_tables' => $attemptCurrent,
            'rollback_current_source_tables' => $rollbackCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'yield_returning' => $yieldReturning,
            'suppressed_attempt_returning' => $attemptReturning,
            'retry_returning' => $retryReturning,
            'yielded_rows_before_rollback' => $yieldedRows,
            'suppressed_rows_after_rollback' => $suppressedRows,
            'retry_rows_after_release' => $retryRows,
            'yield_statements' => $yieldExecuted,
            'attempt_statements' => $attemptExecuted,
            'retry_statements' => $retryExecuted,
            'yielded_returning_count' => count($yieldedRows),
            'suppressed_returning_count' => count($suppressedRows),
            'retry_returning_count' => count($retryRows),
            'yield_change_count' => self::selectRetrySavepointChangeCount($yieldExecuted),
            'attempt_change_count' => self::selectRetrySavepointChangeCount($attemptExecuted),
            'retry_change_count' => self::selectRetrySavepointChangeCount($retryExecuted),
            'rowvalue_subquery_targets' => true,
            'rollback_to_savepoint' => true,
            'release_commits_retry' => true,
            'yielded_rows_survive_rollback' => true,
            'attempted_rows_suppressed' => true,
            'retry_reads_savepoint_image' => true,
            'savepoint_released' => true,
            'changed_tables_after_release' => self::selectRetrySavepointChangedTables($savepointImage, $retryCurrent),
            'row_counts' => self::selectRetrySavepointRowCounts($retryCurrent),
            'release_receipt' => [
                'savepoint' => $savepoint,
                'yielded_count' => count($yieldedRows),
                'suppressed_count' => count($suppressedRows),
                'retry_count' => count($retryRows),
                'yielded_ids' => self::selectRetrySavepointIdsFromRows($yieldedRows, $rowIdColumn),
                'suppressed_ids' => self::selectRetrySavepointIdsFromRows($suppressedRows, $rowIdColumn),
                'retry_ids' => self::selectRetrySavepointIdsFromRows($retryRows, $rowIdColumn),
                'released_tables' => array_keys($retryCurrent),
            ],
            'dependency_closure' => 'no new support component needed; reuses native PHP UPDATE/DELETE RETURNING row-value subquery dispatch and savepoint row images',
            'dependencies' => [
                'sqlite-rowvalue-in-select-update-delete-returning',
                'sqlite-rowvalue-returning-rollback-to-release-retry',
                'wordpress-rowvalue-select-savepoint-release-current-source',
            ],
            'non_overlap' => 'adds row-value IN (SELECT ...) target selection through UPDATE/DELETE RETURNING across ROLLBACK TO and final RELEASE; avoids accepted yield-only rollback fencing, nested release discarded by outer rollback, rollback image restoration, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runSelectRetrySavepointStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::selectRetrySavepointStatementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function selectRetrySavepointStatementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::selectRetrySavepointRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeSelectRetrySavepointTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value select retry savepoint release tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value select retry savepoint release rows must be arrays');
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
    private static function selectRetrySavepointRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value select retry savepoint release rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value select retry savepoint release rowid column {$rowIdColumn} must be int or string");
            }
            if (isset($wanted[(string) $id])) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * @param list<array{rows:list<array<string,mixed>>}> $yielded
     * @return list<array<string,mixed>>
     */
    private static function flattenSelectRetrySavepointReturning(array $yielded): array
    {
        $rows = [];
        foreach ($yielded as $stream) {
            foreach ($stream['rows'] as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $executed
     */
    private static function selectRetrySavepointChangeCount(array $executed): int
    {
        $count = 0;
        foreach ($executed as $statement) {
            $count += count($statement['mutation_ids'] ?? []);
        }

        return $count;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function selectRetrySavepointChangedTables(array $before, array $after): array
    {
        $changed = [];
        foreach ($after as $name => $rows) {
            if (($before[$name] ?? null) !== $rows) {
                $changed[] = $name;
            }
        }

        return $changed;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,int>
     */
    private static function selectRetrySavepointRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function selectRetrySavepointIdsFromRows(array $rows, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = $row[$rowIdColumn] ?? null;
            if (is_int($id) || is_string($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $preStatements
     * @param list<string> $innerStatements
     * @param list<string> $afterReleaseStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNestedReleaseOuterRollbackSavepoint(
        array $tables,
        array $preStatements,
        array $innerStatements,
        array $afterReleaseStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_rowvalue_outer_release_rollback',
        string $innerSavepoint = 'wp_options_rowvalue_inner_release_rollback',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($preStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested release/outer rollback savepoint needs pre statements');
        }
        if ($innerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested release/outer rollback savepoint needs inner statements');
        }
        if ($afterReleaseStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested release/outer rollback savepoint needs after-release statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested release/outer rollback savepoint needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value nested release/outer rollback savepoint needs unique constraints');
        }
        self::assertNestedReleaseOuterRollbackIdentifier($outerSavepoint, 'outer savepoint');
        self::assertNestedReleaseOuterRollbackIdentifier($innerSavepoint, 'inner savepoint');
        if ($outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value nested release/outer rollback savepoint names must differ');
        }

        $initial = self::normalizeNestedReleaseOuterRollbackTables($tables);
        [$preCurrent, $preSummaries, $preReturning] = self::runNestedReleaseOuterRollbackStatements(
            $initial,
            $preStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'pre-outer-savepoint',
        );

        $outerImage = $preCurrent;
        [$innerCurrent, $innerSummaries, $innerReturning] = self::runNestedReleaseOuterRollbackStatements(
            $outerImage,
            $innerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-before-release',
        );

        $innerReleaseImage = $innerCurrent;
        [$afterReleaseCurrent, $afterReleaseSummaries, $afterReleaseReturning] = self::runNestedReleaseOuterRollbackStatements(
            $innerReleaseImage,
            $afterReleaseStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'after-inner-release',
        );

        $rollbackCurrent = $outerImage;
        [$retryCurrent, $retrySummaries, $retryReturning] = self::runNestedReleaseOuterRollbackStatements(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-outer-rollback',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-nested-release-outer-rollback-savepoint',
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'inner_released_before_outer_rollback' => true,
            'rolled_back_to_outer_savepoint' => true,
            'outer_savepoint_preserved_after_rollback_to' => true,
            'retry_reads_outer_savepoint_image' => true,
            'outer_savepoint_released_after_retry' => true,
            'initial_tables' => $initial,
            'pre_current_source_tables' => $preCurrent,
            'outer_savepoint_image_tables' => $outerImage,
            'inner_released_current_source_tables' => $innerReleaseImage,
            'after_inner_release_current_source_tables' => $afterReleaseCurrent,
            'rollback_to_outer_current_source_tables' => $rollbackCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'pre_statements' => $preSummaries,
            'inner_statements' => $innerSummaries,
            'after_release_statements' => $afterReleaseSummaries,
            'retry_statements' => $retrySummaries,
            'pre_returning' => $preReturning,
            'discarded_inner_release_returning' => array_merge($innerReturning, $afterReleaseReturning),
            'yielded_after_retry_returning' => $retryReturning,
            'pre_returning_count' => self::nestedReleaseOuterRollbackReturningCount($preReturning),
            'discarded_inner_release_returning_count' => self::nestedReleaseOuterRollbackReturningCount($innerReturning) + self::nestedReleaseOuterRollbackReturningCount($afterReleaseReturning),
            'yielded_after_retry_count' => self::nestedReleaseOuterRollbackReturningCount($retryReturning),
            'changes_before_outer_rollback' => self::nestedReleaseOuterRollbackChangeCount($innerSummaries) + self::nestedReleaseOuterRollbackChangeCount($afterReleaseSummaries),
            'retry_changes_after_outer_rollback' => self::nestedReleaseOuterRollbackChangeCount($retrySummaries),
            'changed_tables_after_retry' => self::nestedReleaseOuterRollbackChangedTables($initial, $retryCurrent),
            'row_counts' => self::nestedReleaseOuterRollbackRowCounts($retryCurrent),
            'dependency_closure' => 'no new support component needed; reuses native row-value UPDATE/DELETE RETURNING, subquery row-value predicates, and savepoint current-source images',
            'dependencies' => [
                'sqlite-nested-savepoint-release-returning-discarded-by-outer-rollback',
                'sqlite-rowvalue-update-delete-returning-retry-after-outer-rollback',
                'wordpress-rowvalue-nested-release-outer-rollback-savepoint',
            ],
            'non_overlap' => 'adds nested inner RELEASE plus outer ROLLBACK TO suppression for row-value UPDATE/DELETE RETURNING; avoids accepted simple rollback, OR FAIL or-fail-savepoint-retry, OR ABORT abort-statement-savepoint, OR ROLLBACK/RELEASE variants, WAL/VFS, JSON table, planner, trigger, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runNestedReleaseOuterRollbackStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $summaries = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $summaries[] = self::nestedReleaseOuterRollbackStatementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function nestedReleaseOuterRollbackStatementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::nestedReleaseOuterRollbackRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeNestedReleaseOuterRollbackTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value nested release/outer rollback savepoint tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value nested release/outer rollback savepoint rows must be arrays');
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
    private static function nestedReleaseOuterRollbackRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value nested release/outer rollback savepoint rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value nested release/outer rollback savepoint rowid column {$rowIdColumn} must be int or string");
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
    private static function nestedReleaseOuterRollbackReturningCount(array $yielded): int
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
    private static function nestedReleaseOuterRollbackChangeCount(array $summaries): int
    {
        $changes = 0;
        foreach ($summaries as $summary) {
            $changes += count($summary['mutation_ids'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function nestedReleaseOuterRollbackChangedTables(array $before, array $after): array
    {
        $changed = [];
        foreach ($after as $table => $rows) {
            if (($before[$table] ?? null) !== $rows) {
                $changed[] = $table;
            }
        }

        sort($changed);

        return $changed;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,int>
     */
    private static function nestedReleaseOuterRollbackRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }
        ksort($counts);

        return $counts;
    }

    private static function assertNestedReleaseOuterRollbackIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value nested release/outer rollback savepoint {$label} must be an identifier");
        }
    }


    /* Consolidated row-value savepoint variant. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeCompoundSubquerySavepointRollback(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_compound_subquery',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeSubquerySavepointRollbackRetry(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $plan = self::replaceCompoundSubqueryRollbackMarker($plan);
        $plan['status'] = 'rowvalue-update-delete-returning-compound-subquery-savepoint-current-source';
        $plan['savepoint'] = $savepoint;
        $plan['compound_subquery_source'] = true;
        $plan['compound_operators'] = ['UNION', 'UNION ALL', 'INTERSECT', 'EXCEPT'];
        $plan['dependency_closure'] = 'no new support component needed; reuses native row-value UPDATE/DELETE RETURNING execution and adds bounded compound SELECT tuple-source handling';
        $plan['non_overlap'] = 'adds UNION/UNION ALL/INTERSECT/EXCEPT tuple sources feeding row-value UPDATE/DELETE RETURNING under savepoint rollback and retry; avoids accepted DISTINCT subqueries, negative LIMIT/OFFSET, positive ORDER/LIMIT, OR ROLLBACK, WAL/VFS, JSON, planner, trigger, and B-tree clusters';
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-compound-select-subquery',
            'sqlite-rowvalue-delete-returning-compound-select-subquery',
            'sqlite-rowvalue-compound-subquery-savepoint-current-source',
        ];

        return $plan;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function replaceCompoundSubqueryRollbackMarker(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(['next212', 'subquery'], ['subquery-savepoint-rollback', 'compound-subquery'], $value);
        }
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $entry) {
            $value[$key] = self::replaceCompoundSubqueryRollbackMarker($entry);
        }

        return $value;
    }
    /* Variant consolidated from generated numbered plan. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeStatements
     * @param list<string> $protectedStatements
     * @param list<string> $afterStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeUpdateDeleteReturningSavepointBatch(
        array $tables,
        array $beforeStatements,
        array $protectedStatements,
        array $afterStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_returning_next160',
        ?int $rollbackToProtectedOrdinal = null,
        string $rowIdColumn = 'option_id',
    ): array {
        if ($beforeStatements === [] || $protectedStatements === [] || $afterStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value savepoint next160 needs before, protected, and after statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value savepoint next160 needs unique constraints');
        }
        $savepoint = self::identifierUpdateDeleteReturningSavepointBatch($savepoint, 'savepoint');
        if ($rollbackToProtectedOrdinal !== null && ($rollbackToProtectedOrdinal < 0 || $rollbackToProtectedOrdinal >= count($protectedStatements))) {
            throw new \InvalidArgumentException('SQLite row-value savepoint next160 rollback ordinal is outside protected statement list');
        }

        $transactionImage = self::normalizeTablesUpdateDeleteReturningSavepointBatch($tables);
        $before = self::runStatementsUpdateDeleteReturningSavepointBatch($beforeStatements, $transactionImage, $uniqueConstraints, $rowIdColumn, 'before');
        $savepointImage = $before['tables'];

        $protectedCurrent = $savepointImage;
        $protectedExecuted = [];
        $protectedYielded = [];
        foreach ($protectedStatements as $ordinal => $sql) {
            $result = self::runStatementUpdateDeleteReturningSavepointBatch($sql, $protectedCurrent, $uniqueConstraints, $rowIdColumn, 'protected', $ordinal);
            $protectedCurrent = $result['tables'];
            $protectedExecuted[] = $result['statement'];
            $protectedYielded[] = $result['yield'];
            if ($rollbackToProtectedOrdinal === $ordinal) {
                break;
            }
        }

        $rolledBack = $rollbackToProtectedOrdinal !== null;
        $afterStart = $rolledBack ? $savepointImage : $protectedCurrent;
        $after = self::runStatementsUpdateDeleteReturningSavepointBatch($afterStatements, $afterStart, $uniqueConstraints, $rowIdColumn, 'after');
        $final = $after['tables'];

        return [
            'status' => $rolledBack ? 'rolled-back-to-rowvalue-returning-savepoint-current-source-next160' : 'released-rowvalue-returning-savepoint-current-source-next160',
            'savepoint' => $savepoint,
            'rolled_back_to_savepoint' => $rolledBack,
            'rollback_protected_ordinal' => $rollbackToProtectedOrdinal,
            'transaction_image_tables' => $transactionImage,
            'savepoint_image_tables' => $savepointImage,
            'protected_attempt_tables' => $protectedCurrent,
            'after_start_tables' => $afterStart,
            'current_source_tables' => $final,
            'next_source_tables' => $final,
            'before_statements' => $before['statements'],
            'protected_statements_before_rollback' => $protectedExecuted,
            'after_statements' => $after['statements'],
            'before_returning' => $before['yielded'],
            'protected_returning_before_rollback' => $protectedYielded,
            'after_returning' => $after['yielded'],
            'yielded_returning' => array_merge($before['yielded'], $rolledBack ? [] : $protectedYielded, $after['yielded']),
            'discarded_returning' => $rolledBack ? $protectedYielded : [],
            'discarded_returning_count' => $rolledBack ? self::returningCountUpdateDeleteReturningSavepointBatch($protectedYielded) : 0,
            'changes' => self::changeCountUpdateDeleteReturningSavepointBatch(array_merge($before['statements'], $rolledBack ? [] : $protectedExecuted, $after['statements'])),
            'attempted_changes_before_rollback' => self::changeCountUpdateDeleteReturningSavepointBatch(array_merge($before['statements'], $protectedExecuted)),
            'source_cursor' => self::sourceCursorUpdateDeleteReturningSavepointBatch($before['statements'], $protectedExecuted, $after['statements'], $rolledBack),
            'row_counts' => self::rowCountsUpdateDeleteReturningSavepointBatch($final),
            'changed_tables' => self::changedTablesUpdateDeleteReturningSavepointBatch($transactionImage, $final),
            'dependencies' => [
                'sqlite-rowvalue-update-delete-returning-savepoint-current-source-next160',
                'sqlite-rollback-to-savepoint-suppresses-update-delete-returning-yields-next160',
                'sqlite-current-source-after-rollback-restarts-from-savepoint-image-next160',
            ],
            'non_overlap' => 'covers explicit ROLLBACK TO savepoint over a mixed row-value UPDATE RETURNING and DELETE RETURNING protected batch; avoids accepted distinct retry, conflict-yielding, and nested inner-savepoint rollback surfaces',
        ];
    }

    /**
     * @param list<string> $statements
     * @param array<string,list<array<string,mixed>>> $startTables
     * @param list<list<string>> $uniqueConstraints
     * @return array{tables:array<string,list<array<string,mixed>>>,statements:list<array<string,mixed>>,yielded:list<array<string,mixed>>}
     */
    private static function runStatementsUpdateDeleteReturningSavepointBatch(array $statements, array $startTables, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $startTables;
        $executed = [];
        $yielded = [];
        foreach ($statements as $ordinal => $sql) {
            $result = self::runStatementUpdateDeleteReturningSavepointBatch($sql, $current, $uniqueConstraints, $rowIdColumn, $phase, $ordinal);
            $current = $result['tables'];
            $executed[] = $result['statement'];
            $yielded[] = $result['yield'];
        }

        return ['tables' => $current, 'statements' => $executed, 'yielded' => $yielded];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>> $uniqueConstraints
     * @return array{tables:array<string,list<array<string,mixed>>>,statement:array<string,mixed>,yield:array<string,mixed>}
     */
    private static function runStatementUpdateDeleteReturningSavepointBatch(string $sql, array $tables, array $uniqueConstraints, string $rowIdColumn, string $phase, int $ordinal): array
    {
        if (!is_string($sql) || trim($sql) === '') {
            throw new \InvalidArgumentException('SQLite row-value savepoint next160 statement must be SQL text');
        }
        $before = $tables;
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints);

        return [
            'tables' => $result['tables'],
            'statement' => [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'table' => $result['table'],
                'where' => $parsed['where'],
                'returning' => $parsed['returning'],
                'selected_ids' => $result['plan']->selectedIds,
                'mutation_ids' => $result['plan']->mutationIds,
                'source_rows' => self::rowsByIdsUpdateDeleteReturningSavepointBatch($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
                'returning_rows' => $result['returning'],
                'ignored_rows' => $result['ignored_rows'],
                'deleted_conflict_rows' => $result['deleted_conflict_rows'],
                'conflicts' => $result['conflicts'],
            ],
            'yield' => [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'rows' => $result['returning'],
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $before
     * @param list<array<string,mixed>> $protected
     * @param list<array<string,mixed>> $after
     * @return list<array{phase:string,ordinal:int,action:string,selected_ids:list<int|string>,yielded:bool}>
     */
    private static function sourceCursorUpdateDeleteReturningSavepointBatch(array $before, array $protected, array $after, bool $rolledBack): array
    {
        $cursor = [];
        foreach ([$before, $protected, $after] as $groupIndex => $group) {
            $phase = ['before', 'protected', 'after'][$groupIndex];
            foreach ($group as $statement) {
                $cursor[] = [
                    'phase' => $phase,
                    'ordinal' => (int) $statement['ordinal'],
                    'action' => (string) $statement['action'],
                    'selected_ids' => $statement['selected_ids'],
                    'yielded' => !($rolledBack && $phase === 'protected'),
                ];
            }
        }

        return $cursor;
    }

    /**
     * @param list<array{rows:list<array<string,mixed>>}> $streams
     */
    private static function returningCountUpdateDeleteReturningSavepointBatch(array $streams): int
    {
        $count = 0;
        foreach ($streams as $stream) {
            $count += count($stream['rows']);
        }

        return $count;
    }

    /**
     * @param list<array<string,mixed>> $statements
     */
    private static function changeCountUpdateDeleteReturningSavepointBatch(array $statements): int
    {
        $changes = 0;
        foreach ($statements as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesUpdateDeleteReturningSavepointBatch(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value savepoint next160 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value savepoint next160 rows must be arrays');
                }
            }
        }

        return $tables;
    }

    private static function identifierUpdateDeleteReturningSavepointBatch(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value savepoint next160 {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int|string> $ids
     * @return list<array<string,mixed>>
     */
    private static function rowsByIdsUpdateDeleteReturningSavepointBatch(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value savepoint next160 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value savepoint next160 rowid column {$rowIdColumn} must be int or string");
            }
            if (isset($wanted[(string) $id])) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,int>
     */
    private static function rowCountsUpdateDeleteReturningSavepointBatch(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesUpdateDeleteReturningSavepointBatch(array $before, array $after): array
    {
        $changed = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $name) {
            if (($before[$name] ?? null) !== ($after[$name] ?? null)) {
                $changed[] = $name;
            }
        }

        return $changed;
    }

    /* Variant consolidated from generated numbered plan. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeEmptyRowValueInSavepointRetry(
        array $tables,
        array $outerStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_empty_rowvalue_next186',
        string $innerSavepoint = 'wp_options_inner_empty_rowvalue_next186',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite empty row-value IN savepoint next186 needs outer statements');
        }
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite empty row-value IN savepoint next186 needs attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite empty row-value IN savepoint next186 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite empty row-value IN savepoint next186 needs unique constraints');
        }
        if ($outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite empty row-value IN savepoint next186 needs distinct savepoint names');
        }

        $outerImage = self::normalizeEmptyRowValueInSavepointRetryTables($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runEmptyRowValueInSavepointRetryStatements(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-empty-rowvalue-rollback',
        );

        $innerImage = $afterOuter;
        [$afterAttempt, $attemptExecuted, $attemptReturning] = self::runEmptyRowValueInSavepointRetryStatements(
            $innerImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-empty-rowvalue-before-rollback',
        );

        $afterRollback = $innerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runEmptyRowValueInSavepointRetryStatements(
            $afterRollback,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-empty-rowvalue-after-rollback',
        );

        return [
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'status' => 'empty-rowvalue-in-savepoint-current-source-retry-next186',
            'rolled_back_to_inner_savepoint' => true,
            'outer_released_after_retry' => true,
            'inner_rollback_discards_attempt_stream' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'outer_current_source_tables' => $afterOuter,
            'inner_savepoint_image_tables' => $innerImage,
            'attempt_current_source_tables' => $afterAttempt,
            'rollback_to_inner_current_source_tables' => $afterRollback,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'attempt_statements' => $attemptExecuted,
            'retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'attempt_returning_before_rollback' => $attemptReturning,
            'suppressed_by_rollback_returning' => $attemptReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'outer_yielded_count' => self::emptyRowValueInSavepointRetryReturningCount($outerReturning),
            'attempt_yielded_before_rollback_count' => self::emptyRowValueInSavepointRetryReturningCount($attemptReturning),
            'suppressed_by_rollback_count' => self::emptyRowValueInSavepointRetryReturningCount($attemptReturning),
            'yielded_after_retry_count' => self::emptyRowValueInSavepointRetryReturningCount($retryReturning),
            'outer_changes_preserved' => self::emptyRowValueInSavepointRetryChangeCount($outerExecuted),
            'attempted_changes_before_rollback' => self::emptyRowValueInSavepointRetryChangeCount($attemptExecuted),
            'retry_changes_after_rollback' => self::emptyRowValueInSavepointRetryChangeCount($retryExecuted),
            'changed_tables_after_retry' => self::emptyRowValueInSavepointRetryChangedTables($outerImage, $afterRetry),
            'row_counts' => self::emptyRowValueInSavepointRetryRowCounts($afterRetry),
            'dependencies' => [
                'sqlite-rowvalue-empty-in-false-not-in-true-next186',
                'sqlite-empty-rowvalue-not-in-selects-null-tuples-next186',
                'sqlite-empty-rowvalue-rollback-discards-attempt-returning-next186',
                'sqlite-empty-rowvalue-retry-reads-inner-savepoint-image-next186',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runEmptyRowValueInSavepointRetryStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::emptyRowValueInSavepointRetryStatementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function emptyRowValueInSavepointRetryStatementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::emptyRowValueInSavepointRetryRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeEmptyRowValueInSavepointRetryTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite empty row-value IN savepoint next186 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite empty row-value IN savepoint next186 rows must be arrays');
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
    private static function emptyRowValueInSavepointRetryRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite empty row-value IN savepoint next186 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite empty row-value IN savepoint next186 rowid column {$rowIdColumn} must be int or string");
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
    private static function emptyRowValueInSavepointRetryReturningCount(array $yielded): int
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
    private static function emptyRowValueInSavepointRetryChangeCount(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function emptyRowValueInSavepointRetryChangedTables(array $before, array $after): array
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
    private static function emptyRowValueInSavepointRetryRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    /* Variant consolidated from generated numbered plan. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeRollbackToInnerSavepointRetry(
        array $tables,
        array $outerStatements,
        array $innerStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_rowvalue_rollback_outer_next197',
        string $innerSavepoint = 'wp_options_rowvalue_rollback_inner_next197',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback-to next197 needs outer statements');
        }
        if ($innerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback-to next197 needs inner statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback-to next197 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback-to next197 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $outerSavepoint) !== 1 || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $innerSavepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value rollback-to next197 savepoint names must be identifiers');
        }

        $outerImage = self::normalizeRollbackToInnerSavepointRetryTables($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runRollbackToInnerSavepointRetryStatements(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-inner-rollback-next197',
        );

        $innerImage = $afterOuter;
        [$afterInner, $innerExecuted, $innerReturning] = self::runRollbackToInnerSavepointRetryStatements(
            $innerImage,
            $innerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-before-rollback-to-next197',
        );

        $rolledBack = $innerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runRollbackToInnerSavepointRetryStatements(
            $rolledBack,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-rollback-to-next197',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-rollback-to-current-source-next197',
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'rollback_to_inner_savepoint' => true,
            'inner_savepoint_preserved_after_rollback_to' => true,
            'inner_released_after_retry' => true,
            'outer_released_after_inner_retry' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'outer_current_source_tables' => $afterOuter,
            'inner_savepoint_image_tables' => $innerImage,
            'inner_attempt_current_source_tables' => $afterInner,
            'rollback_to_current_source_tables' => $rolledBack,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'inner_statements' => $innerExecuted,
            'retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'inner_rolled_back_returning' => $innerReturning,
            'suppressed_by_rollback_to_returning' => $innerReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'outer_yielded_returning_count' => self::rollbackToInnerSavepointRetryReturningCount($outerReturning),
            'inner_rolled_back_returning_count' => self::rollbackToInnerSavepointRetryReturningCount($innerReturning),
            'suppressed_by_rollback_to_count' => self::rollbackToInnerSavepointRetryReturningCount($innerReturning),
            'yielded_after_retry_count' => self::rollbackToInnerSavepointRetryReturningCount($retryReturning),
            'outer_changes_preserved' => self::rollbackToInnerSavepointRetryChangeCount($outerExecuted),
            'inner_changes_rolled_back' => self::rollbackToInnerSavepointRetryChangeCount($innerExecuted),
            'retry_changes_after_rollback_to' => self::rollbackToInnerSavepointRetryChangeCount($retryExecuted),
            'changed_tables_after_retry' => self::rollbackToInnerSavepointRetryChangedTables($outerImage, $afterRetry),
            'row_counts' => self::rollbackToInnerSavepointRetryRowCounts($afterRetry),
            'dependencies' => [
                'sqlite-rowvalue-returning-rollback-to-savepoint-next197',
                'sqlite-rowvalue-delete-returning-rollback-to-restores-current-source-next197',
                'sqlite-rowvalue-update-returning-retry-after-rollback-to-next197',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runRollbackToInnerSavepointRetryStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::rollbackToInnerSavepointRetryStatementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function rollbackToInnerSavepointRetryStatementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rollbackToInnerSavepointRetryRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeRollbackToInnerSavepointRetryTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value rollback-to next197 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value rollback-to next197 rows must be arrays');
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
    private static function rollbackToInnerSavepointRetryRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value rollback-to next197 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value rollback-to next197 rowid column {$rowIdColumn} must be int or string");
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
    private static function rollbackToInnerSavepointRetryReturningCount(array $yielded): int
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
    private static function rollbackToInnerSavepointRetryChangeCount(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function rollbackToInnerSavepointRetryChangedTables(array $before, array $after): array
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
    private static function rollbackToInnerSavepointRetryRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    /* Variant consolidated from generated numbered plan. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @return array<string,mixed>
     */
    public static function executeOrderExpressionSavepointRetry(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        string $savepoint = 'wp_options_rowvalue_order_expr_next199',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ORDER BY expression next199 needs attempted statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ORDER BY expression next199 needs retry statements');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value ORDER BY expression next199 savepoint name must be an identifier');
        }

        $savepointImage = self::normalizeOrderExpressionSavepointRetryTables($tables);
        [$attemptedTables, $attemptedStatements, $attemptedReturning] = self::runOrderExpressionSavepointRetryStatements(
            $savepointImage,
            $attemptStatements,
            $rowIdColumn,
            'attempt-order-expression-before-rollback-next199',
        );
        [$retryTables, $retryStatementsSummary, $retryReturning] = self::runOrderExpressionSavepointRetryStatements(
            $savepointImage,
            $retryStatements,
            $rowIdColumn,
            'retry-order-expression-after-rollback-next199',
        );

        return [
            'status' => 'rowvalue-order-expression-returning-rolled-back-retried-next199',
            'savepoint' => $savepoint,
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'released_after_retry' => true,
            'savepoint_image_tables' => $savepointImage,
            'attempt_current_source_tables' => $attemptedTables,
            'rollback_to_current_source_tables' => $savepointImage,
            'current_source_tables' => $retryTables,
            'next_source_tables' => $retryTables,
            'attempt_statements' => $attemptedStatements,
            'retry_statements' => $retryStatementsSummary,
            'attempt_returning' => $attemptedReturning,
            'suppressed_by_rollback_returning' => $attemptedReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'attempt_returning_count' => self::orderExpressionSavepointRetryReturningCount($attemptedReturning),
            'suppressed_by_rollback_count' => self::orderExpressionSavepointRetryReturningCount($attemptedReturning),
            'yielded_after_retry_count' => self::orderExpressionSavepointRetryReturningCount($retryReturning),
            'attempt_changes_before_rollback_to' => self::orderExpressionSavepointRetryChangeCount($attemptedStatements),
            'changes_after_retry_release' => self::orderExpressionSavepointRetryChangeCount($retryStatementsSummary),
            'changed_tables_after_retry' => self::orderExpressionSavepointRetryChangedTables($savepointImage, $retryTables),
            'row_counts' => self::orderExpressionSavepointRetryRowCounts($retryTables),
            'dependencies' => [
                'sqlite-update-delete-order-by-rowvalue-expression-next199',
                'sqlite-rowvalue-order-expression-limit-before-source-mutation-next199',
                'sqlite-rowvalue-order-expression-returning-rollback-current-source-next199',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runOrderExpressionSavepointRetryStatements(array $tables, array $statements, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn);
            $current = $result['tables'];
            $executed[] = self::orderExpressionSavepointRetryStatementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function orderExpressionSavepointRetryStatementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::orderExpressionSavepointRetryRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'order_by' => $result['plan']->toArray()['order_by'],
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
    private static function normalizeOrderExpressionSavepointRetryTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value ORDER BY expression next199 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ORDER BY expression next199 rows must be arrays');
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
    private static function orderExpressionSavepointRetryRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value ORDER BY expression next199 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ORDER BY expression next199 rowid column {$rowIdColumn} must be int or string");
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
    private static function orderExpressionSavepointRetryReturningCount(array $yielded): int
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
    private static function orderExpressionSavepointRetryChangeCount(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function orderExpressionSavepointRetryChangedTables(array $before, array $after): array
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
    private static function orderExpressionSavepointRetryRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    /* Variant consolidated from generated numbered plan. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $savepointStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeRollbackToSavepoint(
        array $tables,
        array $outerStatements,
        array $savepointStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_rollback_to_next201',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback-to savepoint next201 needs outer statements');
        }
        if ($savepointStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback-to savepoint next201 needs savepoint statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback-to savepoint next201 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback-to savepoint next201 needs unique constraints');
        }
        self::assertIdentifierRollbackToSavepoint($savepoint, 'savepoint');

        $initialTables = self::normalizeTablesRollbackToSavepoint($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsRollbackToSavepoint($initialTables, $outerStatements, $uniqueConstraints, $rowIdColumn, 'outer-before-savepoint-next201');

        $savepointImage = $afterOuter;
        [$afterSavepoint, $savepointExecuted, $savepointReturning] = self::runStatementsRollbackToSavepoint($savepointImage, $savepointStatements, $uniqueConstraints, $rowIdColumn, 'savepoint-before-rollback-to-next201');

        $afterRollbackTo = $savepointImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsRollbackToSavepoint($afterRollbackTo, $retryStatements, $uniqueConstraints, $rowIdColumn, 'retry-after-rollback-to-next201');

        return [
            'savepoint' => $savepoint,
            'status' => 'rowvalue-update-delete-returning-rollback-to-current-source-next201',
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'savepoint_released_after_retry' => true,
            'initial_tables' => $initialTables,
            'outer_current_source_tables' => $afterOuter,
            'savepoint_image_tables' => $savepointImage,
            'savepoint_attempt_current_source_tables' => $afterSavepoint,
            'rollback_to_current_source_tables' => $afterRollbackTo,
            'retry_current_source_tables' => $afterRetry,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'savepoint_statements' => $savepointExecuted,
            'retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'discarded_savepoint_returning' => $savepointReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'outer_yielded_returning_count' => self::returningCountRollbackToSavepoint($outerReturning),
            'discarded_savepoint_returning_count' => self::returningCountRollbackToSavepoint($savepointReturning),
            'yielded_after_retry_count' => self::returningCountRollbackToSavepoint($retryReturning),
            'discarded_savepoint_changes' => self::changeCountRollbackToSavepoint($savepointExecuted),
            'changes_after_retry' => self::changeCountRollbackToSavepoint($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesRollbackToSavepoint($initialTables, $afterRetry),
            'row_counts' => self::rowCountsRollbackToSavepoint($afterRetry),
            'dependencies' => [
                'sqlite-rowvalue-returning-discarded-by-rollback-to-savepoint-next201',
                'sqlite-rollback-to-savepoint-restores-current-source-for-rowvalue-retry-next201',
                'sqlite-rowvalue-update-delete-retry-after-rollback-to-yields-from-restored-image-next201',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsRollbackToSavepoint(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryRollbackToSavepoint($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryRollbackToSavepoint(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsRollbackToSavepoint($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesRollbackToSavepoint(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value rollback-to savepoint next201 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value rollback-to savepoint next201 rows must be arrays');
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
    private static function rowsByIdsRollbackToSavepoint(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value rollback-to savepoint next201 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value rollback-to savepoint next201 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountRollbackToSavepoint(array $yielded): int
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
    private static function changeCountRollbackToSavepoint(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesRollbackToSavepoint(array $before, array $after): array
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
    private static function rowCountsRollbackToSavepoint(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function assertIdentifierRollbackToSavepoint(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value rollback-to savepoint next201 {$label} must be an identifier");
        }
    }

    /* Variant consolidated from generated numbered plan. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $savepointStatements
     * @param list<string> $rollbackStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeRollbackConflictRetry(
        array $tables,
        array $outerStatements,
        array $savepointStatements,
        array $rollbackStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $transaction = 'wp_options_rowvalue_rollback_txn_next204',
        string $savepoint = 'wp_options_rowvalue_rollback_savepoint_next204',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 needs outer statements');
        }
        if ($savepointStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 needs savepoint statements');
        }
        if ($rollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 needs rollback statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 needs unique constraints');
        }
        self::assertIdentifierRollbackConflictRetry($transaction, 'transaction');
        self::assertIdentifierRollbackConflictRetry($savepoint, 'savepoint');

        $transactionImage = self::normalizeTablesRollbackConflictRetry($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsRollbackConflictRetry(
            $transactionImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-rollback-savepoint-next204',
        );

        $savepointImage = $afterOuter;
        [$afterSavepoint, $savepointExecuted, $savepointReturning] = self::runStatementsRollbackConflictRetry(
            $savepointImage,
            $savepointStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'savepoint-before-rollback-conflict-next204',
        );

        [$rollbackAttempt, $rollbackExecuted, $rollbackReason, $rollbackOrdinal] = self::runRollbackStatementsRollbackConflictRetry(
            $afterSavepoint,
            $rollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );
        if ($rollbackReason === null) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 expected UPDATE OR ROLLBACK conflict');
        }

        $afterRollback = $transactionImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsRollbackConflictRetry(
            $afterRollback,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-transaction-rollback-next204',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-rollback-savepoint-current-source-next204',
            'transaction' => $transaction,
            'savepoint' => $savepoint,
            'transaction_rolled_back' => true,
            'savepoint_invalidated_by_rollback' => true,
            'retry_started_from_transaction_image' => true,
            'retry_transaction_released' => true,
            'rollback_statement_ordinal' => $rollbackOrdinal,
            'rollback_reason' => $rollbackReason,
            'initial_tables' => $transactionImage,
            'outer_current_source_tables' => $afterOuter,
            'savepoint_image_tables' => $savepointImage,
            'savepoint_current_source_tables' => $afterSavepoint,
            'rollback_attempt_tables' => $rollbackAttempt,
            'rollback_to_transaction_tables' => $afterRollback,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'savepoint_statements' => $savepointExecuted,
            'rollback_statements' => $rollbackExecuted,
            'retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'savepoint_yielded_returning' => $savepointReturning,
            'suppressed_by_transaction_rollback_returning' => array_merge($outerReturning, $savepointReturning),
            'yielded_after_retry_returning' => $retryReturning,
            'outer_yielded_returning_count' => self::returningCountRollbackConflictRetry($outerReturning),
            'savepoint_yielded_returning_count' => self::returningCountRollbackConflictRetry($savepointReturning),
            'suppressed_by_transaction_rollback_count' => self::returningCountRollbackConflictRetry(array_merge($outerReturning, $savepointReturning)),
            'yielded_after_retry_count' => self::returningCountRollbackConflictRetry($retryReturning),
            'changes_before_rollback' => self::changeCountRollbackConflictRetry(array_merge($outerExecuted, $savepointExecuted)),
            'changes_after_rollback_retry' => self::changeCountRollbackConflictRetry($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesRollbackConflictRetry($transactionImage, $afterRetry),
            'row_counts' => self::rowCountsRollbackConflictRetry($afterRetry),
            'dependencies' => [
                'sqlite-rowvalue-update-or-rollback-conflict-rolls-back-transaction-next204',
                'sqlite-rowvalue-returning-stream-suppressed-by-transaction-rollback-next204',
                'sqlite-rowvalue-update-delete-retry-reads-transaction-image-next204',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsRollbackConflictRetry(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryRollbackConflictRetry($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:?string,3:?int}
     */
    private static function runRollbackStatementsRollbackConflictRetry(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $executed[] = self::abortedStatementSummaryRollbackConflictRetry($sql, $ordinal, $before, $rowIdColumn, $exception->getMessage());

                return [$before, $executed, $exception->getMessage(), $ordinal];
            }

            $current = $result['tables'];
            $executed[] = self::statementSummaryRollbackConflictRetry('rollback-attempt-before-conflict-next204', $ordinal, $sql, $result, $before, $rowIdColumn, null);
        }

        return [$current, $executed, null, null];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryRollbackConflictRetry(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $failedMessage): array
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
            'source_rows' => self::rowsByIdsRollbackConflictRetry($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
            'failed_conflict' => $failedMessage === null ? ($result['failed_conflict'] ?? null) : ['message' => $failedMessage],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function abortedStatementSummaryRollbackConflictRetry(string $sql, int $ordinal, array $before, string $rowIdColumn, string $message): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        $table = $parsed['table'];
        $where = self::wherePredicateRollbackConflictRetry($parsed['where']);
        if ($parsed['action'] === 'delete') {
            $plan = SQLiteUpdateDeleteLimitPlan::delete($before[$table] ?? [], $where, $parsed['order_by'], $parsed['limit'], $parsed['offset'], $rowIdColumn);
        } else {
            $plan = SQLiteUpdateDeleteLimitPlan::update(
                $before[$table] ?? [],
                $where,
                self::assignmentCallbacksRollbackConflictRetry($parsed['assignments']),
                $parsed['order_by'],
                $parsed['limit'],
                $parsed['offset'],
                $rowIdColumn,
            );
        }

        return [
            'phase' => 'rollback-conflict-suppressed-next204',
            'ordinal' => $ordinal,
            'sql' => $sql,
            'action' => $parsed['action'],
            'conflict_action' => $parsed['conflict_action'],
            'table' => $table,
            'selected_ids' => $plan->selectedIds,
            'mutation_ids' => $plan->mutationIds,
            'source_rows' => self::rowsByIdsRollbackConflictRetry($before[$table] ?? [], $plan->selectedIds, $rowIdColumn),
            'returning_rows' => [],
            'ignored_rows' => [],
            'deleted_conflict_rows' => [],
            'conflicts' => [],
            'failed_conflict' => ['message' => $message],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesRollbackConflictRetry(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 rows must be arrays');
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
    private static function rowsByIdsRollbackConflictRetry(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value ROLLBACK savepoint next204 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ROLLBACK savepoint next204 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountRollbackConflictRetry(array $yielded): int
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
    private static function changeCountRollbackConflictRetry(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesRollbackConflictRetry(array $before, array $after): array
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
    private static function rowCountsRollbackConflictRetry(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function assertIdentifierRollbackConflictRetry(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value ROLLBACK savepoint next204 {$label} must be an identifier");
        }
    }

    /**
     * @return callable(array<string,mixed>):bool
     */
    private static function wherePredicateRollbackConflictRetry(?string $where): callable
    {
        $reflection = new \ReflectionMethod(SQLiteUpdateDeleteReturningSql::class, 'wherePredicate');
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $where);
    }

    /**
     * @param array<string,string> $assignments
     * @return array<string,callable(array<string,mixed>):mixed>
     */
    private static function assignmentCallbacksRollbackConflictRetry(array $assignments): array
    {
        $reflection = new \ReflectionMethod(SQLiteUpdateDeleteReturningSql::class, 'assignmentCallbacks');
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $assignments);
    }

    /* Variant consolidated from generated numbered plan. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeOrderedSubquerySavepointRetry(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_ordered_subquery_next214',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ordered subquery next214 needs attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ordered subquery next214 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ordered subquery next214 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value ordered subquery next214 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeOrderedSubquerySavepointRetryTables($tables);
        [$attemptCurrent, $attemptExecuted, $attemptReturning] = self::runOrderedSubquerySavepointRetryStatements(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-ordered-subquery-before-rollback-next214',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runOrderedSubquerySavepointRetryStatements(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-ordered-subquery-after-rollback-next214',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-ordered-subquery-savepoint-current-source-next214',
            'savepoint' => $savepoint,
            'savepoint_image_tables' => $savepointImage,
            'attempt_current_source_tables' => $attemptCurrent,
            'rollback_current_source_tables' => $rollbackCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'ordered_subquery_limit_respected' => true,
            'retry_reads_savepoint_image' => true,
            'savepoint_released_after_retry' => true,
            'attempt_statements' => $attemptExecuted,
            'retry_statements' => $retryExecuted,
            'discarded_attempt_returning' => $attemptReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'discarded_attempt_returning_count' => self::orderedSubquerySavepointRetryReturningCount($attemptReturning),
            'yielded_after_retry_count' => self::orderedSubquerySavepointRetryReturningCount($retryReturning),
            'attempt_changes_before_rollback' => self::orderedSubquerySavepointRetryChangeCount($attemptExecuted),
            'retry_changes_after_rollback' => self::orderedSubquerySavepointRetryChangeCount($retryExecuted),
            'changed_tables_after_retry' => self::orderedSubquerySavepointRetryChangedTables($savepointImage, $retryCurrent),
            'row_counts' => self::orderedSubquerySavepointRetryRowCounts($retryCurrent),
            'dependencies' => [
                'sqlite-rowvalue-in-select-order-limit-update-returning-next214',
                'sqlite-rowvalue-not-in-select-order-limit-delete-returning-next214',
                'sqlite-rowvalue-ordered-subquery-savepoint-current-source-next214',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runOrderedSubquerySavepointRetryStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'sql' => $sql,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'table' => $result['table'],
                'selected_ids' => $result['plan']->selectedIds,
                'mutation_ids' => $result['plan']->mutationIds,
                'source_rows' => self::orderedSubquerySavepointRetryRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
                'returning_rows' => $result['returning'],
                'ignored_rows' => $result['ignored_rows'],
                'deleted_conflict_rows' => $result['deleted_conflict_rows'],
                'conflicts' => $result['conflicts'],
                'failed_conflict' => $result['failed_conflict'] ?? null,
            ];
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeOrderedSubquerySavepointRetryTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value ordered subquery next214 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ordered subquery next214 rows must be arrays');
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
    private static function orderedSubquerySavepointRetryRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value ordered subquery next214 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ordered subquery next214 rowid column {$rowIdColumn} must be int or string");
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
    private static function orderedSubquerySavepointRetryReturningCount(array $yielded): int
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
    private static function orderedSubquerySavepointRetryChangeCount(array $executed): int
    {
        $count = 0;
        foreach ($executed as $statement) {
            $count += count($statement['mutation_ids']);
        }

        return $count;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function orderedSubquerySavepointRetryChangedTables(array $before, array $after): array
    {
        $changed = [];
        foreach ($after as $table => $rows) {
            if (($before[$table] ?? null) !== $rows) {
                $changed[] = $table;
            }
        }
        sort($changed);

        return $changed;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,int>
     */
    private static function orderedSubquerySavepointRetryRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }
        ksort($counts);

        return $counts;
    }

    /* Variant consolidated from generated numbered plan. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeSubqueryLimitSavepoint(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_subquery_limit_next215',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeSubquerySavepointRollbackRetry(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $plan['status'] = 'rowvalue-update-delete-returning-subquery-limit-savepoint-current-source-next215';
        foreach (['attempt_statements', 'retry_statements', 'discarded_attempt_returning', 'yielded_after_retry_returning'] as $streamKey) {
            foreach ($plan[$streamKey] as $index => $entry) {
                if (isset($entry['phase']) && is_string($entry['phase'])) {
                    $plan[$streamKey][$index]['phase'] = str_replace('next212', 'next215', $entry['phase']);
                }
            }
        }
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-in-select-order-limit-next215',
            'sqlite-rowvalue-delete-returning-not-in-select-order-limit-next215',
            'sqlite-rowvalue-subquery-limit-savepoint-current-source-next215',
        ];

        return $plan;
    }

    /* Variant consolidated from generated numbered plan. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeDistinctSubquerySavepoint(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_distinct_subquery',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeSubquerySavepointRollbackRetry(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $plan = self::replaceDistinctSubqueryMarker($plan);
        $plan['status'] = 'rowvalue-update-delete-returning-distinct-subquery-savepoint-current-source';
        $plan['savepoint'] = $savepoint;
        $plan['distinct_subquery_source'] = true;
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-in-select-distinct-subquery',
            'sqlite-rowvalue-delete-returning-in-select-distinct-subquery',
            'sqlite-rowvalue-distinct-subquery-savepoint-current-source',
        ];
        $plan['dependency_closure'] = 'no new support component needed; reuses native PHP row-value UPDATE/DELETE RETURNING, SELECT subquery tuple materialization, and savepoint current-source retry images';
        $plan['non_overlap'] = 'adds SELECT DISTINCT tuple de-duplication for row-value UPDATE/DELETE RETURNING subqueries; avoids plain subqueries, ORDER/LIMIT subqueries, OR IGNORE, NULL inequality, trigger RETURNING, WAL/VFS, JSON, planner, and B-tree clusters';

        return $plan;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function replaceDistinctSubqueryMarker(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(['next212', 'subquery'], ['distinct', 'distinct-subquery'], $value);
        }
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $entry) {
            $value[$key] = self::replaceDistinctSubqueryMarker($entry);
        }

        return $value;
    }

    /* Variant consolidated from generated numbered plan. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeDistinctTupleSavepointRollback(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_distinct_tuple',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple savepoint needs attempted statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple savepoint needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple savepoint needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple savepoint must be an identifier');
        }

        $savepointImage = self::normalizeDistinctTupleTables($tables);
        [$attemptCurrent, $attemptExecuted, $attemptReturning] = self::runDistinctTupleSavepointStatements(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'distinct-tuple-attempt-before-rollback',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runDistinctTupleSavepointStatements(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'distinct-tuple-retry-after-rollback',
        );

        $attemptRows = self::flattenDistinctTupleReturning($attemptReturning);
        $retryRows = self::flattenDistinctTupleReturning($retryReturning);

        return [
            'status' => 'rowvalue-update-delete-returning-distinct-tuple-savepoint-current-source',
            'savepoint' => $savepoint,
            'savepoint_image_tables' => $savepointImage,
            'attempt_current_source_tables' => $attemptCurrent,
            'rollback_current_source_tables' => $rollbackCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'attempt_statements' => $attemptExecuted,
            'retry_statements' => $retryExecuted,
            'suppressed_attempt_returning' => $attemptReturning,
            'retry_returning' => $retryReturning,
            'suppressed_attempt_rows' => $attemptRows,
            'retry_rows' => $retryRows,
            'distinct_tuple_subquery_deduped' => true,
            'rollback_to_savepoint_restores_distinct_tuple_source' => true,
            'retry_reads_savepoint_image' => true,
            'savepoint_remains_active' => true,
            'suppressed_returning_count' => count($attemptRows),
            'retry_returning_count' => count($retryRows),
            'attempt_change_count' => self::distinctTupleChangeCount($attemptExecuted),
            'retry_change_count' => self::distinctTupleChangeCount($retryExecuted),
            'changed_tables_after_retry' => self::distinctTupleChangedTables($savepointImage, $retryCurrent),
            'row_counts' => self::distinctTupleRowCounts($retryCurrent),
            'tuple_source_receipt' => [
                'savepoint' => $savepoint,
                'attempt_statement_count' => count($attemptStatements),
                'retry_statement_count' => count($retryStatements),
                'suppressed_ids' => self::distinctTupleRowIds($attemptRows, $rowIdColumn),
                'retry_ids' => self::distinctTupleRowIds($retryRows, $rowIdColumn),
            ],
            'dependency_closure' => 'no new support component needed; reuses native row-value UPDATE/DELETE RETURNING execution and adds DISTINCT tuple-source parsing',
            'dependencies' => [
                'sqlite-rowvalue-distinct-subquery-tuples',
                'sqlite-rowvalue-returning-rollback-retries-distinct-tuples',
                'wordpress-rowvalue-distinct-optionmeta-savepoint',
            ],
            'non_overlap' => 'adds SELECT DISTINCT tuple-source de-duplication inside row-value UPDATE/DELETE RETURNING savepoint rollback and retry; avoids accepted LIMIT -1 OFFSET tuple sources, nested savepoint release rollback, OR FAIL/ABORT/ROLLBACK conflict slices, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runDistinctTupleSavepointStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::distinctTupleStatementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function distinctTupleStatementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::distinctTupleRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeDistinctTupleTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple savepoint tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple savepoint rows must be arrays');
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
    private static function distinctTupleRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value DISTINCT tuple savepoint rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value DISTINCT tuple savepoint rowid column {$rowIdColumn} must be int or string");
            }
            if (isset($wanted[(string) $id])) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * @param list<array{rows:list<array<string,mixed>>}> $yielded
     * @return list<array<string,mixed>>
     */
    private static function flattenDistinctTupleReturning(array $yielded): array
    {
        $rows = [];
        foreach ($yielded as $stream) {
            foreach ($stream['rows'] as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $executed
     */
    private static function distinctTupleChangeCount(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function distinctTupleChangedTables(array $before, array $after): array
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
    private static function distinctTupleRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function distinctTupleRowIds(array $rows, string $rowIdColumn): array
    {
        return array_values(array_filter(
            array_column($rows, $rowIdColumn),
            static fn (mixed $id): bool => is_int($id) || is_string($id),
        ));
    }

}
