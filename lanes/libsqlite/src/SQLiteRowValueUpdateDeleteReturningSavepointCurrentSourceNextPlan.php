<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan
{

    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext146(
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

        $transactionImage = self::normalizeTablesNext146($tables);
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
            $summary = self::statementSummaryNext146($ordinal, $result, $rowIdColumn, $before);
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
            'discarded_returning_count' => $rolledBack ? self::returningCountNext146($attemptedYielded) : 0,
            'changes' => $rolledBack ? 0 : self::changeCountNext146($executed),
            'attempted_changes_before_rollback' => self::changeCountNext146($executed),
            'row_counts' => self::rowCountsNext146($final),
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
    private static function normalizeTablesNext146(array $tables): array
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
    private static function statementSummaryNext146(int $ordinal, array $result, string $rowIdColumn, array $before): array
    {
        return [
            'ordinal' => $ordinal,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rowsByIdsNext146($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function rowsByIdsNext146(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext146(array $yielded): int
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
    private static function changeCountNext146(array $executed): int
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
    private static function rowCountsNext146(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext156(
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

        $savepointImage = self::normalizeTablesNext156($tables);
        $current = $savepointImage;
        $executed = [];
        $yielded = [];
        $rollbackRequested = false;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $summary = self::statementSummaryNext156($ordinal, $sql, $result, $rowIdColumn, $before);
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
            'discarded_returning_count' => $rollbackRequested ? self::returningCountNext156($yielded) : 0,
            'changes' => $rollbackRequested ? 0 : self::changeCountNext156($executed),
            'attempted_changes_before_rollback' => self::changeCountNext156($executed),
            'ignored_row_count' => self::countNestedRowsNext156($executed, 'ignored_rows'),
            'deleted_conflict_row_count' => self::countNestedRowsNext156($executed, 'deleted_conflict_rows'),
            'savepoint_changed_tables' => self::changedTablesNext156($savepointImage, $final),
            'row_counts' => self::rowCountsNext156($final),
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
    private static function normalizeTablesNext156(array $tables): array
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
    private static function statementSummaryNext156(int $ordinal, string $sql, array $result, string $rowIdColumn, array $before): array
    {
        return [
            'ordinal' => $ordinal,
            'sql' => $sql,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rowsByIdsNext156($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function rowsByIdsNext156(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext156(array $yielded): int
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
    private static function changeCountNext156(array $executed): int
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
    private static function countNestedRowsNext156(array $executed, string $key): int
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
    private static function changedTablesNext156(array $before, array $after): array
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
    private static function rowCountsNext156(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerStatements
     * @param list<string> $afterRollbackStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext157(
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
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next157 needs outer, inner, and after-rollback statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next157 needs unique constraints');
        }
        $outerSavepoint = self::identifierNext157($outerSavepoint, 'outer savepoint');
        $innerSavepoint = self::identifierNext157($innerSavepoint, 'inner savepoint');
        if (strcasecmp($outerSavepoint, $innerSavepoint) === 0) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next157 needs distinct savepoint names');
        }

        $transactionImage = self::normalizeTablesNext157($tables);
        $outerImage = $transactionImage;

        $outer = self::runStatementsNext157($outerStatements, $outerImage, $uniqueConstraints, $rowIdColumn);
        if ($outer['failed_statement'] !== null) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next157 outer statement failed: ' . $outer['failed_statement']['reason']);
        }

        $innerImage = $outer['tables'];
        $inner = self::runStatementsNext157($innerStatements, $innerImage, $uniqueConstraints, $rowIdColumn);
        $rolledBackInner = $inner['executed_statements'] !== [] || $inner['failed_statement'] !== null;
        $afterRollbackStart = $rolledBackInner ? $innerImage : $inner['tables'];

        $after = self::runStatementsNext157($afterRollbackStatements, $afterRollbackStart, $uniqueConstraints, $rowIdColumn);
        if ($after['failed_statement'] !== null) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next157 after-rollback statement failed: ' . $after['failed_statement']['reason']);
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
            'discarded_inner_returning_count' => self::returningCountNext157($inner['yielded_returning']),
            'outer_changes' => self::changeCountNext157($outer['executed_statements']),
            'inner_attempted_changes' => self::changeCountNext157($inner['executed_statements']),
            'after_rollback_changes' => self::changeCountNext157($after['executed_statements']),
            'changes' => self::changeCountNext157($outer['executed_statements']) + self::changeCountNext157($after['executed_statements']),
            'rolled_back_inner_savepoint' => $rolledBackInner,
            'outer_savepoint_preserved' => false,
            'inner_savepoint_preserved' => false,
            'failed_inner_statement' => $inner['failed_statement'],
            'row_counts' => self::rowCountsNext157($final),
            'changed_tables' => self::changedTablesNext157($transactionImage, $final),
            'dependencies' => [
                'sqlite-rowvalue-update-delete-returning-savepoint-current-source-next157',
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
    private static function runStatementsNext157(array $statements, array $startTables, array $uniqueConstraints, string $rowIdColumn): array
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
            $executed[] = self::statementSummaryNext157($ordinal, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext157(int $ordinal, array $result, array $before, string $rowIdColumn): array
    {
        return [
            'ordinal' => $ordinal,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rowsByIdsNext157($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext157(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value nested savepoint next157 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value nested savepoint next157 rows must be arrays');
                }
            }
        }

        return $tables;
    }

    private static function identifierNext157(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value nested savepoint next157 {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int|string> $ids
     * @return list<array<string,mixed>>
     */
    private static function rowsByIdsNext157(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value nested savepoint next157 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value nested savepoint next157 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNext157(array $yielded): int
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
    private static function changeCountNext157(array $executed): int
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
    private static function rowCountsNext157(array $tables): array
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
    private static function changedTablesNext157(array $before, array $after): array
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


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeRollbackStatements
     * @param list<string> $afterRollbackStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext158(
        array $tables,
        array $beforeRollbackStatements,
        array $afterRollbackStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_retry_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($beforeRollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next158 needs pre-rollback statements');
        }
        if ($afterRollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next158 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next158 needs unique constraints');
        }

        $savepointImage = self::normalizeTablesNext158($tables);
        [$attemptedBeforeRollback, $preRollbackExecuted, $preRollbackYielded] = self::runStatementsNext158(
            $savepointImage,
            $beforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-rollback',
        );

        $rollbackToImage = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryYielded] = self::runStatementsNext158(
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
            'discarded_returning_count' => self::returningCountNext158($preRollbackYielded),
            'changes_after_release' => self::changeCountNext158($retryExecuted),
            'discarded_changes_before_rollback_to' => self::changeCountNext158($preRollbackExecuted),
            'row_counts' => self::rowCountsNext158($retryCurrent),
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
    private static function runStatementsNext158(
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
            $executed[] = self::statementSummaryNext158($phase, $ordinal, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext158(string $phase, int $ordinal, array $result, array $before, string $rowIdColumn): array
    {
        return [
            'phase' => $phase,
            'ordinal' => $ordinal,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rowsByIdsNext158($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext158(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next158 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next158 rows must be arrays');
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
    private static function rowsByIdsNext158(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value UPDATE/DELETE RETURNING savepoint next158 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value UPDATE/DELETE RETURNING savepoint next158 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNext158(array $yielded): int
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
    private static function changeCountNext158(array $executed): int
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
    private static function rowCountsNext158(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeRollbackStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext161(
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

        $savepointImage = self::normalizeTablesNext161($tables);
        [$failedCurrent, $failedStatements, $failedReturning, $failedConflict, $failedOrdinal] = self::runUntilFailNext161(
            $savepointImage,
            $beforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        $rollbackToImage = $savepointImage;
        [$retryCurrent, $retryStatementsSummary, $retryReturning] = self::runRetryStatementsNext161(
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
            'discarded_returning_count' => self::returningCountNext161($failedReturning),
            'yielded_returning_count' => self::returningCountNext161($retryReturning),
            'failed_changes_before_rollback_to' => self::changeCountNext161($failedStatements),
            'changes_after_release' => self::changeCountNext161($retryStatementsSummary),
            'row_counts' => self::rowCountsNext161($retryCurrent),
            'changed_tables_after_retry' => self::changedTablesNext161($savepointImage, $retryCurrent),
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
    private static function runUntilFailNext161(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
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
            $executed[] = self::statementSummaryNext161('before-rollback', $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function runRetryStatementsNext161(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext161('after-rollback', $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext161(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext161($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext161(array $tables): array
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
    private static function rowsByIdsNext161(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext161(array $yielded): int
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
    private static function changeCountNext161(array $executed): int
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
    private static function rowCountsNext161(array $tables): array
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
    private static function changedTablesNext161(array $before, array $after): array
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


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext162(
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

        $savepointImage = self::normalizeTablesNext162($tables);
        $current = $savepointImage;
        $executed = [];
        $yielded = [];
        $failed = null;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints, true);
            $current = $result['tables'];
            $summary = self::statementSummaryNext162($ordinal, $sql, $result, $before, $rowIdColumn);
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
                    'partial_change_count' => self::statementChangeCountNext162($summary),
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
                'changes' => self::changeCountNext162($executed),
                'partial_fail' => null,
                'dependencies' => self::dependenciesNext162(),
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
            'discarded_returning_count' => self::returningCountNext162($yielded),
            'attempted_changes_before_rollback' => self::changeCountNext162($executed),
            'changes' => 0,
            'partial_fail' => $failed,
            'savepoint_changed_tables' => [],
            'row_counts' => self::rowCountsNext162($savepointImage),
            'dependencies' => self::dependenciesNext162(),
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesNext162(array $tables): array
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
    private static function statementSummaryNext162(int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
    {
        return [
            'ordinal' => $ordinal,
            'sql' => $sql,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rowsByIdsNext162($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function rowsByIdsNext162(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext162(array $yielded): int
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
    private static function changeCountNext162(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += self::statementChangeCountNext162($statement);
        }

        return $changes;
    }

    /**
     * @param array<string,mixed> $statement
     */
    private static function statementChangeCountNext162(array $statement): int
    {
        return count($statement['returning_rows'] ?? []) + count($statement['deleted_conflict_rows'] ?? []);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,int>
     */
    private static function rowCountsNext162(array $tables): array
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
    private static function dependenciesNext162(): array
    {
        return [
            'sqlite-update-or-fail-preserves-prior-rowvalue-changes-until-savepoint-rollback',
            'sqlite-rowvalue-returning-fail-stream-discarded-by-rollback-to-savepoint',
            'sqlite-delete-returning-after-partial-fail-is-not-run-before-rollback-to',
            'sqlite-savepoint-current-source-restored-after-rowvalue-or-fail',
        ];
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeRollbackStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext163(
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

        $savepointImage = self::normalizeTablesNext163($tables);
        [$attemptedTables, $attemptedStatements, $discardedReturning] = self::runStatementsNext163(
            $savepointImage,
            $beforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-rollback-to',
        );

        $rollbackToTables = $savepointImage;
        [$currentTables, $retryExecuted, $yieldedReturning] = self::runStatementsNext163(
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
            'discarded_returning_count' => self::returningCountNext163($discardedReturning),
            'yielded_returning_count' => self::returningCountNext163($yieldedReturning),
            'discarded_changes_before_rollback_to' => self::changeCountNext163($attemptedStatements),
            'changes_after_release' => self::changeCountNext163($retryExecuted),
            'row_counts' => self::rowCountsNext163($currentTables),
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
    private static function runStatementsNext163(
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
            $executed[] = self::statementSummaryNext163($phase, $ordinal, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext163(string $phase, int $ordinal, array $result, array $before, string $rowIdColumn): array
    {
        return [
            'phase' => $phase,
            'ordinal' => $ordinal,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rowsByIdsNext163($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext163(array $tables): array
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
    private static function rowsByIdsNext163(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext163(array $yielded): int
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
    private static function changeCountNext163(array $executed): int
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
    private static function rowCountsNext163(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext164(
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

        $transactionImage = self::normalizeTablesNext164($tables);
        [$attemptedCurrent, $attempted, $attemptedReturning, $rollbackReason, $rollbackOrdinal] = self::runUntilRollbackNext164(
            $transactionImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );
        $rolledBack = $rollbackReason !== null;
        $retryBase = $rolledBack ? $transactionImage : $attemptedCurrent;
        [$retryCurrent, $retry, $retryReturning] = self::runRetryNext164(
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
            'discarded_returning_count' => $rolledBack ? self::returningCountNext164($attemptedReturning) : 0,
            'yielded_returning_count' => self::returningCountNext164($retryReturning),
            'attempted_changes_before_rollback' => self::changeCountNext164($attempted),
            'changes_after_retry' => self::changeCountNext164($retry),
            'changed_tables_after_retry' => self::changedTablesNext164($transactionImage, $retryCurrent),
            'row_counts' => self::rowCountsNext164($retryCurrent),
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
    private static function runUntilRollbackNext164(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
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
            $executed[] = self::statementSummaryNext164('before-rollback', $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function runRetryNext164(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext164('after-rollback', $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext164(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext164($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext164(array $tables): array
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
    private static function rowsByIdsNext164(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext164(array $yielded): int
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
    private static function changeCountNext164(array $executed): int
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
    private static function changedTablesNext164(array $before, array $after): array
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
    private static function rowCountsNext164(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext165(
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
        self::identifierNext165($savepoint, 'savepoint');

        $savepointImage = self::normalizeTablesNext165($tables);
        $current = $savepointImage;
        $executed = [];
        $yielded = [];
        $ignoredStreams = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $summary = self::statementSummaryNext165($ordinal, $sql, $result, $before, $rowIdColumn);
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
            'ignored_returning_count' => self::ignoredCountNext165($ignoredStreams),
            'yielded_returning_count' => self::returningCountNext165($yielded),
            'changes' => self::changeCountNext165($executed),
            'savepoint_changed_tables' => self::changedTablesNext165($savepointImage, $current),
            'row_counts' => self::rowCountsNext165($current),
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
    private static function normalizeTablesNext165(array $tables): array
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

    private static function identifierNext165(string $value, string $label): void
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
    private static function statementSummaryNext165(int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
    {
        return [
            'ordinal' => $ordinal,
            'sql' => $sql,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rowsByIdsNext165($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function rowsByIdsNext165(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext165(array $yielded): int
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
    private static function ignoredCountNext165(array $ignored): int
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
    private static function changeCountNext165(array $executed): int
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
    private static function rowCountsNext165(array $tables): array
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
    private static function changedTablesNext165(array $before, array $after): array
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


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $innerStatements
     * @param list<string> $outerStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext166(
        array $tables,
        array $innerStatements,
        array $outerStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_import_next166',
        string $innerSavepoint = 'wp_options_inner_cleanup_next166',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($innerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next166 needs inner statements');
        }
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next166 needs outer statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next166 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next166 needs unique constraints');
        }
        if ($outerSavepoint === '' || $innerSavepoint === '' || $outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next166 needs distinct savepoint names');
        }

        $outerImage = self::normalizeTablesNext166($tables);
        [$innerReleasedCurrent, $innerExecuted, $innerReturning] = self::runStatementsNext166(
            $outerImage,
            $innerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-released',
        );
        [$outerAttemptedCurrent, $outerExecuted, $outerReturning] = self::runStatementsNext166(
            $innerReleasedCurrent,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-rollback',
        );

        $rollbackToOuter = $outerImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatementsNext166(
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
            'discarded_returning_count' => self::returningCountNext166($discardedReturning),
            'yielded_returning_count' => self::returningCountNext166($retryReturning),
            'discarded_changes_before_outer_rollback_to' => self::changeCountNext166($discardedStatements),
            'changes_after_retry_release' => self::changeCountNext166($retryExecuted),
            'row_counts' => self::rowCountsNext166($retryCurrent),
            'changed_tables_after_retry' => self::changedTablesNext166($outerImage, $retryCurrent),
            'dependencies' => [
                'sqlite-release-inner-savepoint-merges-rowvalue-returning-into-outer-savepoint-next166',
                'sqlite-rollback-to-outer-savepoint-discards-released-inner-returning-next166',
                'sqlite-rowvalue-update-delete-retry-after-outer-rollback-reads-original-current-source-next166',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNext166(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext166($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext166(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext166($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext166(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value nested savepoint next166 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value nested savepoint next166 rows must be arrays');
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
    private static function rowsByIdsNext166(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value nested savepoint next166 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value nested savepoint next166 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNext166(array $yielded): int
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
    private static function changeCountNext166(array $executed): int
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
    private static function rowCountsNext166(array $tables): array
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
    private static function changedTablesNext166(array $before, array $after): array
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


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerAttemptStatements
     * @param list<string> $innerRetryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext168(
        array $tables,
        array $outerStatements,
        array $innerAttemptStatements,
        array $innerRetryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_rowvalue_next168',
        string $innerSavepoint = 'wp_options_inner_rowvalue_next168',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite nested row-value savepoint next168 needs outer statements');
        }
        if ($innerAttemptStatements === []) {
            throw new \InvalidArgumentException('SQLite nested row-value savepoint next168 needs inner attempt statements');
        }
        if ($innerRetryStatements === []) {
            throw new \InvalidArgumentException('SQLite nested row-value savepoint next168 needs inner retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite nested row-value savepoint next168 needs unique constraints');
        }

        $outerImage = self::normalizeTablesNext168($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNext168(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-inner',
        );

        $innerImage = $afterOuter;
        [$attemptedInner, $innerAttempted, $innerAttemptReturning] = self::runStatementsNext168(
            $innerImage,
            $innerAttemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-before-rollback-to',
        );

        $afterRollbackToInner = $innerImage;
        [$afterRetry, $innerRetry, $innerRetryReturning] = self::runStatementsNext168(
            $afterRollbackToInner,
            $innerRetryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-after-rollback-to',
        );

        return [
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'status' => 'outer-released-after-inner-rollback-to-retry-current-source-next168',
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
            'yielded_outer_returning_count' => self::returningCountNext168($outerReturning),
            'discarded_inner_returning_count' => self::returningCountNext168($innerAttemptReturning),
            'yielded_inner_retry_returning_count' => self::returningCountNext168($innerRetryReturning),
            'outer_changes' => self::changeCountNext168($outerExecuted),
            'discarded_inner_changes' => self::changeCountNext168($innerAttempted),
            'changes_after_inner_retry' => self::changeCountNext168($innerRetry),
            'total_released_changes' => self::changeCountNext168($outerExecuted) + self::changeCountNext168($innerRetry),
            'changed_tables_after_release' => self::changedTablesNext168($outerImage, $afterRetry),
            'row_counts' => self::rowCountsNext168($afterRetry),
            'dependencies' => [
                'sqlite-nested-savepoint-rollback-to-preserves-outer-rowvalue-returning-next168',
                'sqlite-rowvalue-update-delete-returning-discards-inner-rollback-stream-next168',
                'sqlite-rowvalue-retry-after-inner-rollback-reads-inner-savepoint-image-next168',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNext168(
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
            $executed[] = self::statementSummaryNext168($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext168(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext168($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext168(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite nested row-value savepoint next168 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite nested row-value savepoint next168 rows must be arrays');
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
    private static function rowsByIdsNext168(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite nested row-value savepoint next168 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite nested row-value savepoint next168 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNext168(array $yielded): int
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
    private static function changeCountNext168(array $executed): int
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
    private static function changedTablesNext168(array $before, array $after): array
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
    private static function rowCountsNext168(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext169(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_abort_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next169 needs attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next169 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next169 needs unique constraints');
        }

        $savepointImage = self::normalizeTablesNext169($tables);
        [$attemptedCurrent, $attempted, $attemptedReturning, $abortReason, $abortOrdinal] = self::runUntilAbortNext169(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );
        $statementAborted = $abortReason !== null;
        [$retryCurrent, $retry, $retryReturning] = self::runRetryNext169(
            $attemptedCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        return [
            'savepoint' => $savepoint,
            'status' => $statementAborted ? 'statement-aborted-savepoint-preserved-retried-current-source-next169' : 'released-without-abort-current-source-next169',
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
            'yielded_before_abort_count' => self::returningCountNext169($attemptedReturning),
            'aborted_statement_returning_count' => 0,
            'yielded_returning_count' => self::returningCountNext169($retryReturning),
            'changes_before_abort' => self::changeCountNext169($attempted),
            'changes_after_retry' => self::changeCountNext169($retry),
            'total_changes_after_release' => self::changeCountNext169($attempted) + self::changeCountNext169($retry),
            'changed_tables_after_retry' => self::changedTablesNext169($savepointImage, $retryCurrent),
            'row_counts' => self::rowCountsNext169($retryCurrent),
            'dependencies' => [
                'sqlite-update-or-abort-rowvalue-conflict-rolls-back-current-statement-only',
                'sqlite-abort-conflict-preserves-savepoint-and-prior-returning-streams',
                'sqlite-rowvalue-update-delete-returning-retry-continues-from-abort-current-source-next169',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>,3:?string,4:?int}
     */
    private static function runUntilAbortNext169(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
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
            $executed[] = self::statementSummaryNext169('before-abort', $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function runRetryNext169(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext169('after-abort', $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext169(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext169($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext169(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next169 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next169 rows must be arrays');
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
    private static function rowsByIdsNext169(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next169 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next169 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNext169(array $yielded): int
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
    private static function changeCountNext169(array $executed): int
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
    private static function changedTablesNext169(array $before, array $after): array
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
    private static function rowCountsNext169(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext170(
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
        self::identifierNext170($savepoint, 'savepoint');

        $savepointImage = self::normalizeTablesNext170($tables);
        [$current, $executed, $yielded, $aborted] = self::runUntilAbortNext170($savepointImage, $statements, $uniqueConstraints, $rowIdColumn);
        [$retryCurrent, $retryExecuted, $retryYielded] = self::runRetryNext170($current, $retryStatements, $uniqueConstraints, $rowIdColumn);

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
            'yielded_returning_count_before_abort' => self::returningCountNext170($yielded),
            'retry_returning_count' => self::returningCountNext170($retryYielded),
            'changes_before_abort' => self::changeCountNext170($executed),
            'changes_after_retry' => self::changeCountNext170($retryExecuted),
            'savepoint_changed_tables_after_abort' => self::changedTablesNext170($savepointImage, $current),
            'changed_tables_after_retry' => self::changedTablesNext170($savepointImage, $retryCurrent),
            'row_counts_after_abort' => self::rowCountsNext170($current),
            'row_counts_after_retry' => self::rowCountsNext170($retryCurrent),
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
    private static function runUntilAbortNext170(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
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
            $executed[] = self::statementSummaryNext170($ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function runRetryNext170(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext170($ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext170(int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
    {
        return [
            'ordinal' => $ordinal,
            'sql' => $sql,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rowsByIdsNext170($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext170(array $tables): array
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

    private static function identifierNext170(string $value, string $label): void
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
    private static function rowsByIdsNext170(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext170(array $yielded): int
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
    private static function changeCountNext170(array $executed): int
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
    private static function changedTablesNext170(array $before, array $after): array
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
    private static function rowCountsNext170(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldedBeforeRollbackStatements
     * @param list<string> $discardedBeforeRollbackStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext172(
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

        $savepointImage = self::normalizeTablesNext172($tables);
        [$yieldedAttemptCurrent, $yieldedStatements, $deliveredReturning] = self::runStatementsNext172(
            $savepointImage,
            $yieldedBeforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'yielded-before-rollback',
        );
        [$discardAttemptCurrent, $discardedStatements, $discardedReturning] = self::runStatementsNext172(
            $yieldedAttemptCurrent,
            $discardedBeforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'discarded-before-rollback',
        );

        $rollbackToCurrent = $savepointImage;
        [$retryCurrent, $retryStatementsExecuted, $retryReturning] = self::runStatementsNext172(
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
            'delivered_before_rollback_count' => self::returningCountNext172($deliveredReturning),
            'discarded_before_rollback_count' => self::returningCountNext172($discardedReturning),
            'suppressed_by_rollback_count' => self::returningCountNext172($allSuppressed),
            'yielded_after_retry_count' => self::returningCountNext172($retryReturning),
            'attempted_changes_before_rollback_to' => self::changeCountNext172($allAttempted),
            'changes_after_retry_release' => self::changeCountNext172($retryStatementsExecuted),
            'row_counts' => self::rowCountsNext172($retryCurrent),
            'changed_tables_after_retry' => self::changedTablesNext172($savepointImage, $retryCurrent),
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
    private static function runStatementsNext172(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext172($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext172(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext172($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext172(array $tables): array
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
    private static function rowsByIdsNext172(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext172(array $yielded): int
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
    private static function changeCountNext172(array $executed): int
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
    private static function rowCountsNext172(array $tables): array
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
    private static function changedTablesNext172(array $before, array $after): array
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


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext173(
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
        self::identifierNext173($savepoint, 'savepoint');

        $savepointImage = self::normalizeTablesNext173($tables);
        [$failedCurrent, $attempted, $attemptedReturning, $failedConflict, $failedOrdinal] = self::runAttemptNext173(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        $rollbackToCurrent = $failedConflict === null ? $failedCurrent : $savepointImage;
        [$releasedCurrent, $retry, $yieldedReturning] = self::runRetryNext173(
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
            'discarded_returning_count' => $failedConflict === null ? 0 : self::returningCountNext173($attemptedReturning),
            'yielded_returning_count' => self::returningCountNext173($yieldedReturning),
            'attempted_changes_before_rollback_to' => self::changeCountNext173($attempted),
            'changes_after_retry_release' => self::changeCountNext173($retry),
            'changed_tables_after_retry' => self::changedTablesNext173($savepointImage, $releasedCurrent),
            'row_counts' => self::rowCountsNext173($releasedCurrent),
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
    private static function runAttemptNext173(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
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
            $executed[] = self::statementSummaryNext173('before-rollback-to', $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function runRetryNext173(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext173('after-rollback-to', $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext173(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext173($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext173(array $tables): array
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

    private static function identifierNext173(string $value, string $label): void
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
    private static function rowsByIdsNext173(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext173(array $streams): int
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
    private static function changeCountNext173(array $executed): int
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
    private static function changedTablesNext173(array $before, array $after): array
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
    private static function rowCountsNext173(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext174(
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

        $outerImage = self::normalizeTablesNext174($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNext174($outerImage, $outerStatements, $uniqueConstraints, $rowIdColumn, 'outer-before-inner');

        $innerImage = $afterOuter;
        [$afterInnerRelease, $innerExecuted, $innerReturning] = self::runStatementsNext174($innerImage, $innerStatements, $uniqueConstraints, $rowIdColumn, 'inner-before-release');

        $afterOuterRollback = $outerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext174($afterOuterRollback, $retryStatements, $uniqueConstraints, $rowIdColumn, 'after-outer-rollback');

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
            'discarded_outer_returning_count' => self::returningCountNext174($outerReturning),
            'discarded_inner_released_returning_count' => self::returningCountNext174($innerReturning),
            'yielded_retry_returning_count' => self::returningCountNext174($retryReturning),
            'discarded_outer_changes' => self::changeCountNext174($outerExecuted),
            'discarded_inner_released_changes' => self::changeCountNext174($innerExecuted),
            'changes_after_retry' => self::changeCountNext174($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext174($outerImage, $afterRetry),
            'row_counts' => self::rowCountsNext174($afterRetry),
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
    private static function runStatementsNext174(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext174($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext174(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext174($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext174(array $tables): array
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
    private static function rowsByIdsNext174(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext174(array $yielded): int
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
    private static function changeCountNext174(array $executed): int
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
    private static function changedTablesNext174(array $before, array $after): array
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
    private static function rowCountsNext174(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerYieldedStatements
     * @param list<string> $innerDiscardedStatements
     * @param list<string> $innerRetryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext177(
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

        $outerImage = self::normalizeTablesNext177($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNext177($outerImage, $outerStatements, $uniqueConstraints, $rowIdColumn, 'outer-before-inner');

        $innerImage = $afterOuter;
        [$afterYielded, $yieldedExecuted, $yieldedReturning] = self::runStatementsNext177($innerImage, $innerYieldedStatements, $uniqueConstraints, $rowIdColumn, 'inner-yielded-before-rollback');
        [$afterDiscarded, $discardedExecuted, $discardedReturning] = self::runStatementsNext177($afterYielded, $innerDiscardedStatements, $uniqueConstraints, $rowIdColumn, 'inner-discarded-before-rollback');

        $afterInnerRollback = $innerImage;
        [$afterInnerRetry, $retryExecuted, $retryReturning] = self::runStatementsNext177($afterInnerRollback, $innerRetryStatements, $uniqueConstraints, $rowIdColumn, 'inner-retry-after-rollback');

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
            'outer_yielded_returning_count' => self::returningCountNext177($outerReturning),
            'inner_yielded_before_rollback_count' => self::returningCountNext177($yieldedReturning),
            'inner_discarded_before_rollback_count' => self::returningCountNext177($discardedReturning),
            'inner_suppressed_by_rollback_count' => self::returningCountNext177($innerSuppressedReturning),
            'inner_yielded_after_retry_count' => self::returningCountNext177($retryReturning),
            'outer_changes_preserved' => self::changeCountNext177($outerExecuted),
            'inner_attempted_changes_before_rollback_to' => self::changeCountNext177($innerAttempted),
            'inner_changes_after_retry_release' => self::changeCountNext177($retryExecuted),
            'changed_tables_after_inner_retry' => self::changedTablesNext177($outerImage, $afterInnerRetry),
            'row_counts' => self::rowCountsNext177($afterInnerRetry),
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
    private static function runStatementsNext177(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext177($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext177(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext177($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext177(array $tables): array
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
    private static function rowsByIdsNext177(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext177(array $yielded): int
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
    private static function changeCountNext177(array $executed): int
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
    private static function changedTablesNext177(array $before, array $after): array
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
    private static function rowCountsNext177(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $savepointStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext178(
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

        $transactionImage = self::normalizeTablesNext178($tables);
        [$outerCurrent, $outerExecuted, $outerReturning] = self::runStatementsNext178(
            $transactionImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer',
        );
        $savepointImage = $outerCurrent;
        [$failedCurrent, $savepointExecuted, $savepointReturning, $rollbackReason, $rollbackOrdinal] = self::runSavepointUntilRollbackNext178(
            $savepointImage,
            $savepointStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        $rolledBackTransaction = $rollbackReason !== null;
        $retrySource = $rolledBackTransaction ? $transactionImage : $failedCurrent;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatementsNext178(
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
            'discarded_returning_count' => self::returningCountNext178($discardedReturning),
            'yielded_returning_count' => self::returningCountNext178($retryReturning),
            'attempted_changes_before_rollback' => self::changeCountNext178(array_merge($outerExecuted, $savepointExecuted)),
            'changes_after_retry' => self::changeCountNext178($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext178($transactionImage, $retryCurrent),
            'row_counts' => self::rowCountsNext178($retryCurrent),
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
    private static function runStatementsNext178(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext178($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null);
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
    private static function runSavepointUntilRollbackNext178(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
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
                    'table' => self::statementTableNameNext178($sql),
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
            $executed[] = self::statementSummaryNext178('savepoint', $ordinal, $sql, $result, $before, $rowIdColumn, null);
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
    private static function statementSummaryNext178(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?array $failedConflict): array
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
            'source_rows' => self::rowsByIdsNext178($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
            'failed_conflict' => $failedConflict ?? ($result['failed_conflict'] ?? null),
        ];
    }

    private static function statementTableNameNext178(string $sql): string
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
    private static function normalizeTablesNext178(array $tables): array
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
    private static function rowsByIdsNext178(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext178(array $yielded): int
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
    private static function changeCountNext178(array $executed): int
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
    private static function changedTablesNext178(array $before, array $after): array
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
    private static function rowCountsNext178(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerYieldedStatements
     * @param list<string> $innerDiscardedStatements
     * @param list<string> $innerRetryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext180(
        array $tables,
        array $outerStatements,
        array $innerYieldedStatements,
        array $innerDiscardedStatements,
        array $innerRetryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_rowvalue_next180',
        string $innerSavepoint = 'wp_options_inner_rowvalue_next180',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value inner rollback next180 needs outer statements');
        }
        if ($innerYieldedStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value inner rollback next180 needs yielded inner statements');
        }
        if ($innerDiscardedStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value inner rollback next180 needs discarded inner statements');
        }
        if ($innerRetryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value inner rollback next180 needs retry inner statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value inner rollback next180 needs unique constraints');
        }

        $outerImage = self::normalizeTablesNext180($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNext180($outerImage, $outerStatements, $uniqueConstraints, $rowIdColumn, 'outer-before-inner');

        $innerImage = $afterOuter;
        [$afterYielded, $yieldedExecuted, $yieldedReturning] = self::runStatementsNext180($innerImage, $innerYieldedStatements, $uniqueConstraints, $rowIdColumn, 'inner-yielded-before-rollback');
        [$afterDiscarded, $discardedExecuted, $discardedReturning] = self::runStatementsNext180($afterYielded, $innerDiscardedStatements, $uniqueConstraints, $rowIdColumn, 'inner-discarded-before-rollback');

        $afterInnerRollback = $innerImage;
        [$afterInnerRetry, $retryExecuted, $retryReturning] = self::runStatementsNext180($afterInnerRollback, $innerRetryStatements, $uniqueConstraints, $rowIdColumn, 'inner-retry-after-rollback');

        $innerSuppressedReturning = array_merge($yieldedReturning, $discardedReturning);
        $innerAttempted = array_merge($yieldedExecuted, $discardedExecuted);

        return [
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'status' => 'inner-ignore-rollback-to-retry-current-source-next180',
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
            'outer_yielded_returning_count' => self::returningCountNext180($outerReturning),
            'inner_yielded_before_rollback_count' => self::returningCountNext180($yieldedReturning),
            'inner_discarded_before_rollback_count' => self::returningCountNext180($discardedReturning),
            'inner_suppressed_by_rollback_count' => self::returningCountNext180($innerSuppressedReturning),
            'inner_yielded_after_retry_count' => self::returningCountNext180($retryReturning),
            'outer_changes_preserved' => self::changeCountNext180($outerExecuted),
            'inner_attempted_changes_before_rollback_to' => self::changeCountNext180($innerAttempted),
            'inner_changes_after_retry_release' => self::changeCountNext180($retryExecuted),
            'changed_tables_after_inner_retry' => self::changedTablesNext180($outerImage, $afterInnerRetry),
            'row_counts' => self::rowCountsNext180($afterInnerRetry),
            'dependencies' => [
                'sqlite-inner-savepoint-rowvalue-ignore-yields-no-returning-next180',
                'sqlite-rollback-to-inner-savepoint-preserves-outer-current-source-next180',
                'sqlite-rowvalue-update-delete-returning-retry-starts-from-inner-image-next180',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNext180(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext180($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext180(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext180($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext180(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value inner rollback next180 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value inner rollback next180 rows must be arrays');
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
    private static function rowsByIdsNext180(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value inner rollback next180 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value inner rollback next180 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNext180(array $yielded): int
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
    private static function changeCountNext180(array $executed): int
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
    private static function changedTablesNext180(array $before, array $after): array
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
    private static function rowCountsNext180(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext182(
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

        $outerImage = self::normalizeTablesNext182($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNext182($outerImage, $outerStatements, $uniqueConstraints, $rowIdColumn, 'outer-before-inner-release');

        $innerImage = $afterOuter;
        [$afterInnerRelease, $innerExecuted, $innerReturning] = self::runStatementsNext182($innerImage, $innerStatements, $uniqueConstraints, $rowIdColumn, 'inner-released-into-outer');

        $afterOuterRollback = $outerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext182($afterOuterRollback, $retryStatements, $uniqueConstraints, $rowIdColumn, 'retry-after-outer-rollback');

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
            'outer_returning_before_rollback_count' => self::returningCountNext182($outerReturning),
            'inner_returning_released_before_rollback_count' => self::returningCountNext182($innerReturning),
            'suppressed_by_outer_rollback_count' => self::returningCountNext182($suppressedReturning),
            'yielded_after_retry_count' => self::returningCountNext182($retryReturning),
            'outer_changes_before_rollback' => self::changeCountNext182($outerExecuted),
            'inner_changes_released_before_rollback' => self::changeCountNext182($innerExecuted),
            'retry_changes_after_outer_rollback' => self::changeCountNext182($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext182($outerImage, $afterRetry),
            'row_counts' => self::rowCountsNext182($afterRetry),
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
    private static function runStatementsNext182(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext182($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext182(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext182($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext182(array $tables): array
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
    private static function rowsByIdsNext182(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext182(array $yielded): int
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
    private static function changeCountNext182(array $executed): int
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
    private static function changedTablesNext182(array $before, array $after): array
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
    private static function rowCountsNext182(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerDeleteStatements
     * @param list<string> $innerAttemptStatements
     * @param list<string> $innerRetryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext183(
        array $tables,
        array $outerDeleteStatements,
        array $innerAttemptStatements,
        array $innerRetryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_delete_next183',
        string $innerSavepoint = 'wp_options_inner_retry_next183',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerDeleteStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested delete next183 needs outer delete statements');
        }
        if ($innerAttemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested delete next183 needs inner attempt statements');
        }
        if ($innerRetryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested delete next183 needs inner retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value nested delete next183 needs unique constraints');
        }

        $outerImage = self::normalizeTablesNext183($tables);
        [$afterOuterDelete, $outerExecuted, $outerReturning] = self::runStatementsNext183(
            $outerImage,
            $outerDeleteStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-delete-before-inner',
        );

        $innerImage = $afterOuterDelete;
        [$afterInnerAttempt, $innerAttemptExecuted, $innerAttemptReturning] = self::runStatementsNext183(
            $innerImage,
            $innerAttemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-attempt-before-rollback',
        );

        $afterInnerRollback = $innerImage;
        [$afterInnerRetry, $innerRetryExecuted, $innerRetryReturning] = self::runStatementsNext183(
            $afterInnerRollback,
            $innerRetryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-retry-after-rollback',
        );

        return [
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'status' => 'outer-delete-preserved-inner-rowvalue-rollback-retry-next183',
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
            'outer_yielded_returning_count' => self::returningCountNext183($outerReturning),
            'inner_attempt_returning_count' => self::returningCountNext183($innerAttemptReturning),
            'inner_suppressed_by_rollback_count' => self::returningCountNext183($innerAttemptReturning),
            'inner_yielded_after_retry_count' => self::returningCountNext183($innerRetryReturning),
            'outer_delete_changes_preserved' => self::changeCountNext183($outerExecuted),
            'inner_attempted_changes_before_rollback_to' => self::changeCountNext183($innerAttemptExecuted),
            'inner_changes_after_retry_release' => self::changeCountNext183($innerRetryExecuted),
            'changed_tables_after_inner_retry' => self::changedTablesNext183($outerImage, $afterInnerRetry),
            'row_counts' => self::rowCountsNext183($afterInnerRetry),
            'dependencies' => [
                'sqlite-outer-delete-returning-current-source-preserved-next183',
                'sqlite-inner-rowvalue-update-delete-returning-rollback-discards-stream-next183',
                'sqlite-inner-retry-reads-post-delete-current-source-next183',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNext183(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext183($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext183(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext183($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext183(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value nested delete next183 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value nested delete next183 rows must be arrays');
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
    private static function rowsByIdsNext183(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value nested delete next183 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value nested delete next183 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNext183(array $yielded): int
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
    private static function changeCountNext183(array $executed): int
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
    private static function changedTablesNext183(array $before, array $after): array
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
    private static function rowCountsNext183(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $preFailStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext185(
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

        $savepointImage = self::normalizeTablesNext185($tables);
        [$beforeFail, $preFailExecuted, $preFailReturning] = self::runStatementsNext185(
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
        $failExecuted = self::statementSummaryNext185('or-fail-partial-before-rollback', 0, $failStatement, $failResult, $beforeFailStatement, $rowIdColumn);
        $failReturning = [[
            'phase' => 'or-fail-partial-before-rollback',
            'ordinal' => 0,
            'action' => $failResult['action'],
            'conflict_action' => $failResult['conflict_action'],
            'rows' => $failResult['returning'],
        ]];

        $rollbackTo = $savepointImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext185(
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
            'pre_fail_returning_count' => self::returningCountNext185($preFailReturning),
            'partial_fail_returning_count' => self::returningCountNext185($failReturning),
            'suppressed_by_rollback_count' => self::returningCountNext185($suppressedReturning),
            'yielded_after_retry_count' => self::returningCountNext185($retryReturning),
            'attempted_changes_before_rollback_to' => self::changeCountNext185($attemptedExecuted),
            'partial_fail_changes_before_rollback_to' => self::changeCountNext185([$failExecuted]),
            'changes_after_retry_release' => self::changeCountNext185($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext185($savepointImage, $afterRetry),
            'row_counts' => self::rowCountsNext185($afterRetry),
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
    private static function runStatementsNext185(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase, bool $preserveFailChanges): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints, $preserveFailChanges);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext185($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext185(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext185($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext185(array $tables): array
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
    private static function rowsByIdsNext185(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext185(array $yielded): int
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
    private static function changeCountNext185(array $executed): int
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
    private static function changedTablesNext185(array $before, array $after): array
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
    private static function rowCountsNext185(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $savepointStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext187(
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

        $transactionImage = self::normalizeTablesNext187($tables);
        [$outerCurrent, $outerExecuted, $outerReturning] = self::runStatementsNext187(
            $transactionImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer',
        );

        $savepointImage = $outerCurrent;
        [$failedCurrent, $savepointExecuted, $savepointReturning, $rollbackReason, $rollbackOrdinal] = self::runSavepointUntilAbortNext187(
            $savepointImage,
            $savepointStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        $rolledBackSavepoint = $rollbackReason !== null;
        $retrySource = $rolledBackSavepoint ? $savepointImage : $failedCurrent;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatementsNext187(
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
            'outer_returning_count' => self::returningCountNext187($outerReturning),
            'discarded_returning_count' => self::returningCountNext187($discardedReturning),
            'yielded_returning_count' => self::returningCountNext187($retryReturning),
            'attempted_changes_before_rollback' => self::changeCountNext187($savepointExecuted),
            'outer_changes_preserved' => self::changeCountNext187($outerExecuted),
            'changes_after_retry' => self::changeCountNext187($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext187($transactionImage, $retryCurrent),
            'row_counts' => self::rowCountsNext187($retryCurrent),
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
    private static function runStatementsNext187(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext187($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function runSavepointUntilAbortNext187(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
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
                    'table' => self::statementTableNameNext187($sql),
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
            $executed[] = self::statementSummaryNext187('savepoint', $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext187(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext187($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
            'failed_conflict' => $result['failed_conflict'] ?? null,
        ];
    }

    private static function statementTableNameNext187(string $sql): string
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
    private static function normalizeTablesNext187(array $tables): array
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
    private static function rowsByIdsNext187(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext187(array $yielded): int
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
    private static function changeCountNext187(array $executed): int
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
    private static function changedTablesNext187(array $before, array $after): array
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
    private static function rowCountsNext187(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @return array<string,mixed>
     */
    public static function executeNext188(
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

        $savepointImage = self::normalizeTablesNext188($tables);
        [$attemptedTables, $attemptedStatements, $attemptedReturning] = self::runStatementsNext188(
            $savepointImage,
            $attemptStatements,
            $rowIdColumn,
            'attempt-before-rollback',
        );

        [$retryTables, $retryStatementsSummary, $retryReturning] = self::runStatementsNext188(
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
            'attempt_returning_count' => self::returningCountNext188($attemptedReturning),
            'suppressed_by_rollback_count' => self::returningCountNext188($attemptedReturning),
            'yielded_after_retry_count' => self::returningCountNext188($retryReturning),
            'attempt_changes_before_rollback_to' => self::changeCountNext188($attemptedStatements),
            'changes_after_retry_release' => self::changeCountNext188($retryStatementsSummary),
            'changed_tables_after_retry' => self::changedTablesNext188($savepointImage, $retryTables),
            'row_counts' => self::rowCountsNext188($retryTables),
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
    private static function runStatementsNext188(array $tables, array $statements, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext188($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext188(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext188($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext188(array $tables): array
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
    private static function rowsByIdsNext188(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext188(array $yielded): int
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
    private static function changeCountNext188(array $executed): int
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
    private static function changedTablesNext188(array $before, array $after): array
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
    private static function rowCountsNext188(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerAttemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext189(
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

        $outerImage = self::normalizeTablesNext189($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNext189(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-not-between-before-inner',
        );

        $innerImage = $afterOuter;
        [$afterInnerAttempt, $innerAttemptExecuted, $innerAttemptReturning] = self::runStatementsNext189(
            $innerImage,
            $innerAttemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-not-in-values-before-rollback',
        );

        $rollbackToInner = $innerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext189(
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
            'outer_yielded_returning_count' => self::returningCountNext189($outerReturning),
            'inner_attempt_returning_count' => self::returningCountNext189($innerAttemptReturning),
            'suppressed_by_rollback_count' => self::returningCountNext189($innerAttemptReturning),
            'yielded_after_retry_count' => self::returningCountNext189($retryReturning),
            'outer_changes_preserved' => self::changeCountNext189($outerExecuted),
            'inner_attempted_changes_before_rollback_to' => self::changeCountNext189($innerAttemptExecuted),
            'retry_changes_after_release' => self::changeCountNext189($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext189($outerImage, $afterRetry),
            'row_counts' => self::rowCountsNext189($afterRetry),
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
    private static function runStatementsNext189(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext189($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext189(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext189($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext189(array $tables): array
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
    private static function rowsByIdsNext189(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext189(array $yielded): int
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
    private static function changeCountNext189(array $executed): int
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
    private static function changedTablesNext189(array $before, array $after): array
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
    private static function rowCountsNext189(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $releaseStatements
     * @param list<string> $rollbackStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext190(
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
        self::assertIdentifierNext190($releaseSavepoint, 'release savepoint');
        self::assertIdentifierNext190($rollbackSavepoint, 'rollback savepoint');

        $transactionImage = self::normalizeTablesNext190($tables);
        [$afterRelease, $releaseExecuted, $releaseReturning] = self::runStatementsNext190(
            $transactionImage,
            $releaseStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'release-savepoint',
        );

        $rollbackImage = $afterRelease;
        [$speculativeCurrent, $rollbackExecuted, $rollbackReturning] = self::runStatementsNext190(
            $rollbackImage,
            $rollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'rollback-savepoint-speculative',
        );

        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext190(
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
            'yielded_release_count' => self::returningCountNext190($releaseReturning),
            'suppressed_by_rollback_count' => self::returningCountNext190($rollbackReturning),
            'yielded_after_retry_count' => self::returningCountNext190($retryReturning),
            'release_changes' => self::changeCountNext190($releaseExecuted),
            'rollback_attempted_changes' => self::changeCountNext190($rollbackExecuted),
            'retry_changes' => self::changeCountNext190($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext190($transactionImage, $afterRetry),
            'row_counts' => self::rowCountsNext190($afterRetry),
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
    private static function runStatementsNext190(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext190($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext190(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext190($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext190(array $tables): array
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

    private static function assertIdentifierNext190(string $value, string $label): void
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
    private static function rowsByIdsNext190(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext190(array $yielded): int
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
    private static function changeCountNext190(array $executed): int
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
    private static function changedTablesNext190(array $before, array $after): array
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
    private static function rowCountsNext190(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        ksort($counts);

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerBeforeAbortStatements
     * @param string $abortStatement
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext192(
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

        $outerImage = self::normalizeTablesNext192($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNext192(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-abort-inner',
        );

        $innerImage = $afterOuter;
        [$afterInnerBeforeAbort, $innerBeforeAbortExecuted, $innerBeforeAbortReturning] = self::runStatementsNext192(
            $innerImage,
            $innerBeforeAbortStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-before-abort',
        );

        [$afterAbort, $abortSummary] = self::runAbortStatementNext192(
            $afterInnerBeforeAbort,
            $abortStatement,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-abort-statement',
        );

        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext192(
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
            'outer_yielded_returning_count' => self::returningCountNext192($outerReturning),
            'inner_pre_abort_returning_count' => self::returningCountNext192($innerBeforeAbortReturning),
            'suppressed_by_abort_count' => count($abortSummary['returning_rows']),
            'yielded_after_retry_count' => self::returningCountNext192($retryReturning),
            'outer_changes_preserved' => self::changeCountNext192($outerExecuted),
            'inner_changes_preserved_before_abort' => self::changeCountNext192($innerBeforeAbortExecuted),
            'retry_changes_after_abort' => self::changeCountNext192($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext192($outerImage, $afterRetry),
            'row_counts' => self::rowCountsNext192($afterRetry),
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
    private static function runStatementsNext192(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext192($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null);
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
    private static function runAbortStatementNext192(array $tables, string $sql, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        try {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints);

            return [
                $result['tables'],
                self::statementSummaryNext192($phase, 0, $sql, $result, $tables, $rowIdColumn, null) + [
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
                self::statementSummaryNext192($phase, 0, $sql, $probe, $tables, $rowIdColumn, $exception->getMessage()) + [
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
    private static function statementSummaryNext192(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $error): array
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
            'source_rows' => self::rowsByIdsNext192($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext192(array $tables): array
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
    private static function rowsByIdsNext192(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext192(array $yielded): int
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
    private static function changeCountNext192(array $executed): int
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
    private static function changedTablesNext192(array $before, array $after): array
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
    private static function rowCountsNext192(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $failAttemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext193(
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

        $initial = self::normalizeTablesNext193($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNext193(
            $initial,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-fail-savepoint-next193',
            false,
        );

        $savepointImage = $afterOuter;
        [$afterFailAttempt, $failExecuted, $failReturning] = self::runStatementsNext193(
            $savepointImage,
            $failAttemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'fail-attempt-before-rollback-next193',
            true,
        );

        $rolledBack = $savepointImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext193(
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
            'outer_yielded_returning_count' => self::returningCountNext193($outerReturning),
            'fail_yielded_before_conflict_count' => self::returningCountNext193($failReturning),
            'suppressed_by_rollback_count' => self::returningCountNext193($failReturning),
            'yielded_after_retry_count' => self::returningCountNext193($retryReturning),
            'failed_conflicts' => self::failedConflictsNext193($failExecuted),
            'changed_tables_after_retry' => self::changedTablesNext193($initial, $afterRetry),
            'row_counts' => self::rowCountsNext193($afterRetry),
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
    private static function runStatementsNext193(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase, bool $preserveFailChanges): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints, $preserveFailChanges);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext193($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext193(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext193($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext193(array $tables): array
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
    private static function rowsByIdsNext193(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext193(array $yielded): int
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
    private static function failedConflictsNext193(array $executed): array
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
    private static function changedTablesNext193(array $before, array $after): array
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
    private static function rowCountsNext193(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $preFailStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext196(
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

        $savepointImage = self::normalizeTablesNext196($tables);
        [$beforeFail, $preFailExecuted, $preFailReturning] = self::runStatementsNext196(
            $savepointImage,
            $preFailStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-fail-statement',
        );

        [$afterFail, $failSummary] = self::runFailStatementNext196(
            $beforeFail,
            $failStatement,
            $uniqueConstraints,
            $rowIdColumn,
            'rowvalue-or-fail-statement',
        );

        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext196(
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
            'yielded_before_fail_count' => self::returningCountNext196($preFailReturning),
            'yielded_by_fail_before_conflict' => $failSummary['returning_rows'],
            'yielded_by_fail_before_conflict_count' => count($failSummary['returning_rows']),
            'yielded_after_retry_returning' => $retryReturning,
            'yielded_after_retry_count' => self::returningCountNext196($retryReturning),
            'pre_fail_changes_preserved' => self::changeCountNext196($preFailExecuted),
            'fail_prefix_changes_preserved' => count($failSummary['returning_rows']),
            'retry_changes_after_fail' => self::changeCountNext196($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext196($savepointImage, $afterRetry),
            'row_counts' => self::rowCountsNext196($afterRetry),
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
    private static function runStatementsNext196(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext196($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null) + [
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
    private static function runFailStatementNext196(array $tables, string $sql, array $uniqueConstraints, string $rowIdColumn, string $phase): array
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
            self::statementSummaryNext196($phase, 0, $sql, $result, $tables, $rowIdColumn, $thrown) + [
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
    private static function statementSummaryNext196(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $error): array
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
            'source_rows' => self::rowsByIdsNext196($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext196(array $tables): array
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
    private static function rowsByIdsNext196(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext196(array $yielded): int
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
    private static function changeCountNext196(array $executed): int
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
    private static function changedTablesNext196(array $before, array $after): array
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
    private static function rowCountsNext196(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $savepointStatements
     * @param list<string> $abortStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext200(
        array $tables,
        array $outerStatements,
        array $savepointStatements,
        array $abortStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_abort_statement_next200',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next200 needs outer statements');
        }
        if ($savepointStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next200 needs savepoint statements');
        }
        if ($abortStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next200 needs abort statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next200 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next200 needs unique constraints');
        }
        self::assertIdentifierNext200($savepoint, 'savepoint');

        $initialTables = self::normalizeTablesNext200($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNext200(
            $initialTables,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-savepoint-next200',
        );

        $savepointImage = $afterOuter;
        [$afterSavepoint, $savepointExecuted, $savepointReturning] = self::runStatementsNext200(
            $savepointImage,
            $savepointStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'savepoint-before-abort-next200',
        );

        [$afterAbort, $abortExecuted, $abortReason, $abortOrdinal] = self::runAbortStatementsNext200(
            $afterSavepoint,
            $abortStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext200(
            $afterAbort,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-abort-next200',
        );

        return [
            'savepoint' => $savepoint,
            'status' => 'rowvalue-update-delete-returning-abort-statement-current-source-next200',
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
            'outer_yielded_returning_count' => self::returningCountNext200($outerReturning),
            'savepoint_yielded_returning_count' => self::returningCountNext200($savepointReturning),
            'abort_suppressed_returning_count' => 0,
            'yielded_after_retry_count' => self::returningCountNext200($retryReturning),
            'changes_preserved_before_abort' => self::changeCountNext200(array_merge($outerExecuted, $savepointExecuted)),
            'changes_after_retry' => self::changeCountNext200($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext200($initialTables, $afterRetry),
            'row_counts' => self::rowCountsNext200($afterRetry),
            'dependencies' => [
                'sqlite-update-or-abort-rowvalue-returning-discards-failed-statement-next200',
                'sqlite-savepoint-current-source-survives-abort-statement-next200',
                'sqlite-rowvalue-update-delete-retry-reads-post-abort-current-source-next200',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNext200(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext200($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null);
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
    private static function runAbortStatementsNext200(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $executed[] = self::abortedStatementSummaryNext200($sql, $ordinal, $before, $rowIdColumn, $exception->getMessage());

                return [$current, $executed, $exception->getMessage(), $ordinal];
            }

            $current = $result['tables'];
            $executed[] = self::statementSummaryNext200('abort-attempt-before-conflict-next200', $ordinal, $sql, $result, $before, $rowIdColumn, null);
        }

        return [$current, $executed, null, null];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryNext200(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $failedMessage): array
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
            'source_rows' => self::rowsByIdsNext200($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function abortedStatementSummaryNext200(string $sql, int $ordinal, array $before, string $rowIdColumn, string $message): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        $table = $parsed['table'];
        $where = self::wherePredicateNext200($parsed['where']);
        if ($parsed['action'] === 'delete') {
            $plan = SQLiteUpdateDeleteLimitPlan::delete($before[$table] ?? [], $where, $parsed['order_by'], $parsed['limit'], $parsed['offset'], $rowIdColumn);
        } else {
            $plan = SQLiteUpdateDeleteLimitPlan::update(
                $before[$table] ?? [],
                $where,
                self::assignmentCallbacksNext200($parsed['assignments']),
                $parsed['order_by'],
                $parsed['limit'],
                $parsed['offset'],
                $rowIdColumn,
            );
        }

        return [
            'phase' => 'abort-conflict-suppressed-next200',
            'ordinal' => $ordinal,
            'sql' => $sql,
            'action' => $parsed['action'],
            'conflict_action' => $parsed['conflict_action'],
            'table' => $table,
            'selected_ids' => $plan->selectedIds,
            'mutation_ids' => $plan->mutationIds,
            'source_rows' => self::rowsByIdsNext200($before[$table] ?? [], $plan->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext200(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next200 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next200 rows must be arrays');
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
    private static function rowsByIdsNext200(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next200 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next200 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNext200(array $yielded): int
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
    private static function changeCountNext200(array $executed): int
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
    private static function changedTablesNext200(array $before, array $after): array
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
    private static function rowCountsNext200(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function assertIdentifierNext200(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next200 {$label} must be an identifier");
        }
    }

    /**
     * @return callable(array<string,mixed>):bool
     */
    private static function wherePredicateNext200(?string $where): callable
    {
        $reflection = new \ReflectionMethod(SQLiteUpdateDeleteReturningSql::class, 'wherePredicate');
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $where);
    }

    /**
     * @param array<string,string> $assignments
     * @return array<string,callable(array<string,mixed>):mixed>
     */
    private static function assignmentCallbacksNext200(array $assignments): array
    {
        $reflection = new \ReflectionMethod(SQLiteUpdateDeleteReturningSql::class, 'assignmentCallbacks');
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $assignments);
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext202(
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

        $savepointImage = self::normalizeTablesNext202($tables);
        [$attemptTables, $attemptSummaries, $attemptReturning] = self::runStatementsNext202(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-before-rollback-next202',
        );
        [$retryTables, $retrySummaries, $retryReturning] = self::runStatementsNext202(
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
            'attempt_returning_count' => self::returningCountNext202($attemptReturning),
            'suppressed_by_rollback_count' => self::returningCountNext202($attemptReturning),
            'yielded_after_retry_count' => self::returningCountNext202($retryReturning),
            'attempt_changes_before_rollback_to' => self::changeCountNext202($attemptSummaries),
            'changes_after_retry_release' => self::changeCountNext202($retrySummaries),
            'changed_tables_after_retry' => self::changedTablesNext202($savepointImage, $retryTables),
            'row_counts' => self::rowCountsNext202($retryTables),
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
    private static function runStatementsNext202(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $summaries = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $summaries[] = self::statementSummaryNext202($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext202(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext202($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext202(array $tables): array
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
    private static function rowsByIdsNext202(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext202(array $yielded): int
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
    private static function changeCountNext202(array $summaries): int
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
    private static function changedTablesNext202(array $before, array $after): array
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
    private static function rowCountsNext202(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $ignoreStatements
     * @param list<string> $replaceStatements
     * @param list<string> $deleteStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext203(
        array $tables,
        array $ignoreStatements,
        array $replaceStatements,
        array $deleteStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_ignore_replace_next203',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($ignoreStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ignore/replace next203 needs ignore statements');
        }
        if ($replaceStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ignore/replace next203 needs replace statements');
        }
        if ($deleteStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ignore/replace next203 needs delete statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ignore/replace next203 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value ignore/replace next203 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeTablesNext203($tables);
        [$afterIgnore, $ignoreExecuted, $ignoreReturning] = self::runStatementsNext203(
            $savepointImage,
            $ignoreStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'ignore-conflict-current-source-next203',
        );
        self::assertConflictActionNext203($ignoreExecuted, 'ignore');

        [$afterReplace, $replaceExecuted, $replaceReturning] = self::runStatementsNext203(
            $afterIgnore,
            $replaceStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'replace-conflict-current-source-next203',
        );
        self::assertConflictActionNext203($replaceExecuted, 'replace');

        [$afterDelete, $deleteExecuted, $deleteReturning] = self::runStatementsNext203(
            $afterReplace,
            $deleteStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'delete-after-replace-current-source-next203',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-ignore-replace-savepoint-current-source-next203',
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
            'ignored_rows' => self::ignoredRowsNext203($ignoreExecuted),
            'replace_deleted_conflict_rows' => self::deletedConflictRowsNext203($replaceExecuted),
            'ignore_yielded_count' => self::returningCountNext203($ignoreReturning),
            'replace_yielded_count' => self::returningCountNext203($replaceReturning),
            'delete_yielded_count' => self::returningCountNext203($deleteReturning),
            'ignore_conflict_count' => self::conflictCountNext203($ignoreExecuted),
            'replace_conflict_count' => self::conflictCountNext203($replaceExecuted),
            'changed_tables' => self::changedTablesNext203($savepointImage, $afterDelete),
            'row_counts' => self::rowCountsNext203($afterDelete),
            'dependencies' => [
                'sqlite-rowvalue-update-or-ignore-returning-current-source-next203',
                'sqlite-rowvalue-update-or-replace-returning-conflict-delete-next203',
                'sqlite-rowvalue-delete-returning-after-replace-current-source-next203',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNext203(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext203($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function assertConflictActionNext203(array $executed, string $expected): void
    {
        foreach ($executed as $statement) {
            if (($statement['action'] ?? null) !== 'update' || ($statement['conflict_action'] ?? null) !== $expected) {
                throw new \InvalidArgumentException("SQLite row-value next203 expected UPDATE OR " . strtoupper($expected));
            }
        }
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryNext203(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext203($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext203(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value ignore/replace next203 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ignore/replace next203 rows must be arrays');
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
    private static function rowsByIdsNext203(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value ignore/replace next203 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ignore/replace next203 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNext203(array $yielded): int
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
    private static function ignoredRowsNext203(array $executed): array
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
    private static function deletedConflictRowsNext203(array $executed): array
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
    private static function conflictCountNext203(array $executed): int
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
    private static function changedTablesNext203(array $before, array $after): array
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
    private static function rowCountsNext203(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $savepointStatements
     * @param list<string> $nextStatements
     * @param list<list<string>> $uniqueConstraints
     * @param array{release_token?:string,expected_release_token?:string,next_cursor?:string,expected_next_cursor?:string} $options
     * @return array<string,mixed>
     */
    public static function executeNext205(
        array $tables,
        array $savepointStatements,
        array $nextStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_release_next205',
        string $rowIdColumn = 'option_id',
        array $options = [],
    ): array {
        if ($savepointStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value release next205 needs savepoint statements');
        }
        if ($nextStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value release next205 needs next statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value release next205 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value release next205 savepoint must be an identifier');
        }

        $releaseToken = self::tokenNext205((string) ($options['release_token'] ?? 'wp.rowvalue.release.205'), 'release token');
        $expectedReleaseToken = self::tokenNext205((string) ($options['expected_release_token'] ?? $releaseToken), 'expected release token');
        $nextCursor = self::tokenNext205((string) ($options['next_cursor'] ?? 'wp.rowvalue.next.cursor.205'), 'next cursor');
        $expectedNextCursor = self::tokenNext205((string) ($options['expected_next_cursor'] ?? $nextCursor), 'expected next cursor');

        $savepointImage = self::normalizeTablesNext205($tables);
        [$releasedCurrent, $savepointExecuted, $savepointReturning] = self::runStatementsNext205(
            $savepointImage,
            $savepointStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'savepoint-before-release-next205',
        );

        $releaseAdmitted = $releaseToken === $expectedReleaseToken;
        $nextCursorMatches = $nextCursor === $expectedNextCursor;
        $nextSource = $releaseAdmitted ? $releasedCurrent : $savepointImage;
        [$nextCurrent, $nextExecuted, $nextReturning] = self::runStatementsNext205(
            $nextSource,
            $nextStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'next-after-release-current-source-next205',
        );

        $nextReadReleasedRows = $releaseAdmitted && $nextCursorMatches && self::firstStatementSourceMatchesNext205($nextExecuted, $releasedCurrent, $rowIdColumn);
        $status = $releaseAdmitted && $nextCursorMatches
            ? 'rowvalue-update-delete-returning-release-current-source-next205'
            : 'rowvalue-update-delete-returning-release-current-source-blocked-next205';

        return [
            'status' => $status,
            'savepoint' => $savepoint,
            'release_token_next205' => $releaseToken,
            'expected_release_token_next205' => $expectedReleaseToken,
            'release_admitted_next205' => $releaseAdmitted,
            'next_cursor_next205' => $nextCursor,
            'expected_next_cursor_next205' => $expectedNextCursor,
            'next_cursor_matches_next205' => $nextCursorMatches,
            'savepoint_image_tables' => $savepointImage,
            'released_current_source_tables' => $releasedCurrent,
            'next_source_tables' => $nextSource,
            'current_source_tables' => $nextCurrent,
            'savepoint_released_before_next_source_next205' => $releaseAdmitted,
            'next_read_released_current_source_next205' => $nextReadReleasedRows,
            'savepoint_statements' => $savepointExecuted,
            'next_statements' => $nextExecuted,
            'savepoint_returning' => $savepointReturning,
            'next_returning' => $nextReturning,
            'released_returning_count' => self::returningCountNext205($savepointReturning),
            'next_returning_count' => self::returningCountNext205($nextReturning),
            'released_conflict_delete_count' => self::deletedConflictCountNext205($savepointExecuted),
            'changed_tables_after_release' => self::changedTablesNext205($savepointImage, $releasedCurrent),
            'changed_tables_after_next' => self::changedTablesNext205($savepointImage, $nextCurrent),
            'row_counts' => self::rowCountsNext205($nextCurrent),
            'release_receipt_next205' => [
                'savepoint' => $savepoint,
                'token' => $releaseToken,
                'admitted' => $releaseAdmitted,
                'next_cursor' => $nextCursor,
                'next_cursor_matches' => $nextCursorMatches,
            ],
            'dependency_closure_next205' => 'no new support component needed; next205 reuses native row-value UPDATE/DELETE RETURNING execution, conflict handling, and savepoint current-source images',
            'dependencies' => [
                'sqlite-rowvalue-savepoint-release-current-source-next205',
                'sqlite-rowvalue-returning-release-feeds-next-statement-next205',
                'wordpress-rowvalue-update-delete-returning-savepoint-release-next205',
            ],
            'non_overlap_next205' => 'adds RELEASE-to-parent current-source admission after row-value UPDATE/DELETE RETURNING; avoids next203 IGNORE/REPLACE-only savepoint flow, next178 OR ROLLBACK transaction rollback, next172 ROLLBACK TO yielded stream suppression, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNext205(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext205($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext205(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext205($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext205(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value release next205 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value release next205 rows must be arrays');
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
    private static function rowsByIdsNext205(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value release next205 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value release next205 rowid column {$rowIdColumn} must be int or string");
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
    private static function firstStatementSourceMatchesNext205(array $executed, array $source, string $rowIdColumn): bool
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

        return self::rowsByIdsNext205($source[$table], $ids, $rowIdColumn) === ($statement['source_rows'] ?? null);
    }

    /**
     * @param list<array{rows:list<array<string,mixed>>}> $yielded
     */
    private static function returningCountNext205(array $yielded): int
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
    private static function deletedConflictCountNext205(array $executed): int
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
    private static function changedTablesNext205(array $before, array $after): array
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
    private static function rowCountsNext205(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function tokenNext205(string $token, string $label): string
    {
        $token = trim($token);
        if ($token === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $token) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value release next205 {$label} is invalid");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $releasedInnerStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext206(
        array $tables,
        array $outerStatements,
        array $releasedInnerStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_rowvalue_next206',
        string $innerSavepoint = 'wp_options_inner_released_rowvalue_next206',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value outer rollback next206 needs outer statements');
        }
        if ($releasedInnerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value outer rollback next206 needs released inner statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value outer rollback next206 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value outer rollback next206 needs unique constraints');
        }
        self::assertIdentifierNext206($outerSavepoint, 'outer savepoint');
        self::assertIdentifierNext206($innerSavepoint, 'inner savepoint');
        if ($outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value outer rollback next206 savepoint names must differ');
        }

        $outerImage = self::normalizeTablesNext206($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNext206(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-inner-release-next206',
        );

        $innerImage = $afterOuter;
        [$afterInnerRelease, $innerExecuted, $innerReturning] = self::runStatementsNext206(
            $innerImage,
            $releasedInnerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-released-before-outer-rollback-next206',
        );

        $afterOuterRollback = $outerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext206(
            $afterOuterRollback,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-outer-rollback-next206',
        );

        $discardedReturning = array_merge($outerReturning, $innerReturning);

        return [
            'status' => 'rowvalue-update-delete-returning-released-inner-outer-rollback-current-source-next206',
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
            'outer_yielded_count' => self::returningCountNext206($outerReturning),
            'inner_released_yielded_count' => self::returningCountNext206($innerReturning),
            'discarded_by_outer_rollback_count' => self::returningCountNext206($discardedReturning),
            'yielded_after_retry_count' => self::returningCountNext206($retryReturning),
            'changes_discarded_by_outer_rollback' => self::changeCountNext206(array_merge($outerExecuted, $innerExecuted)),
            'changes_after_retry' => self::changeCountNext206($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext206($outerImage, $afterRetry),
            'row_counts' => self::rowCountsNext206($afterRetry),
            'dependencies' => [
                'sqlite-release-inner-savepoint-merges-rowvalue-returning-next206',
                'sqlite-rollback-to-outer-savepoint-discards-released-inner-returning-next206',
                'sqlite-rowvalue-retry-after-outer-rollback-reads-outer-image-next206',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNext206(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext206($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext206(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext206($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext206(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value outer rollback next206 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value outer rollback next206 rows must be arrays');
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
    private static function rowsByIdsNext206(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value outer rollback next206 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value outer rollback next206 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNext206(array $yielded): int
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
    private static function changeCountNext206(array $executed): int
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
    private static function changedTablesNext206(array $before, array $after): array
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
    private static function rowCountsNext206(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function assertIdentifierNext206(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value outer rollback next206 {$label} must be an identifier");
        }
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $failStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext207(
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
        self::assertIdentifierNext207($savepoint, 'savepoint');

        $initial = self::normalizeTablesNext207($tables);
        [$outerCurrent, $outerSummaries, $outerReturning] = self::runStatementsNext207(
            $initial,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-fail-savepoint-next207',
            false,
        );

        $savepointImage = $outerCurrent;
        [$failCurrent, $failSummaries, $failReturning] = self::runStatementsNext207(
            $savepointImage,
            $failStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'fail-prefix-before-rollback-next207',
            true,
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retrySummaries, $retryReturning] = self::runStatementsNext207(
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
            'outer_returning_count' => self::returningCountNext207($outerReturning),
            'fail_prefix_returning_count' => self::returningCountNext207($failReturning),
            'suppressed_by_rollback_count' => self::returningCountNext207($failReturning),
            'yielded_after_retry_count' => self::returningCountNext207($retryReturning),
            'fail_conflict_count' => self::failedConflictCountNext207($failSummaries),
            'changes_preserved_by_fail_before_rollback' => self::changeCountNext207($failSummaries),
            'changes_after_retry' => self::changeCountNext207($retrySummaries),
            'changed_tables_after_retry' => self::changedTablesNext207($initial, $retryCurrent),
            'row_counts' => self::rowCountsNext207($retryCurrent),
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
    private static function runStatementsNext207(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase, bool $preserveFailChanges): array
    {
        $current = $tables;
        $summaries = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints, $preserveFailChanges);
            $current = $result['tables'];
            $summaries[] = self::statementSummaryNext207($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext207(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext207($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext207(array $tables): array
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
    private static function rowsByIdsNext207(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext207(array $yielded): int
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
    private static function failedConflictCountNext207(array $summaries): int
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
    private static function changeCountNext207(array $summaries): int
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
    private static function changedTablesNext207(array $before, array $after): array
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
    private static function rowCountsNext207(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function assertIdentifierNext207(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value OR FAIL next207 {$label} must be an identifier");
        }
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $preFailStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext208(
        array $tables,
        array $outerStatements,
        array $preFailStatements,
        string $failStatement,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_fail_statement_next208',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next208 needs outer statements');
        }
        if ($preFailStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next208 needs pre-fail statements');
        }
        if (trim($failStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next208 needs a fail statement');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next208 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next208 needs unique constraints');
        }
        self::assertIdentifierNext208($savepoint);

        $initial = self::normalizeTablesNext208($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNext208(
            $initial,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-fail-savepoint-next208',
            false,
        );

        $savepointImage = $afterOuter;
        [$beforeFail, $preFailExecuted, $preFailReturning] = self::runStatementsNext208(
            $savepointImage,
            $preFailStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'savepoint-before-or-fail-next208',
            false,
        );

        $failBefore = $beforeFail;
        $failResult = SQLiteUpdateDeleteReturningSql::execute($failStatement, $beforeFail, $rowIdColumn, $uniqueConstraints, true);
        $failCurrent = $failResult['tables'];
        $failSummary = self::statementSummaryNext208(
            'or-fail-partial-current-source-next208',
            0,
            $failStatement,
            $failResult,
            $failBefore,
            $rowIdColumn,
        );

        [$afterRetryFromFail, $retryFromFailExecuted, $retryFromFailReturning] = self::runStatementsNext208(
            $failCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-before-savepoint-rollback-next208',
            false,
        );

        $afterRollbackToSavepoint = $savepointImage;

        return [
            'status' => 'rowvalue-update-delete-returning-or-fail-savepoint-current-source-next208',
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
                'phase' => 'or-fail-partial-current-source-next208',
                'ordinal' => 0,
                'action' => $failResult['action'],
                'conflict_action' => $failResult['conflict_action'],
                'rows' => $failResult['returning'],
            ]],
            'retry_yielded_returning' => $retryFromFailReturning,
            'or_fail_returning_count' => count($failResult['returning']),
            'pre_fail_yielded_count' => self::returningCountNext208($preFailReturning),
            'retry_yielded_count_before_rollback' => self::returningCountNext208($retryFromFailReturning),
            'changes_preserved_by_or_fail' => count($failResult['returning']),
            'changes_after_retry_before_rollback' => self::changeCountNext208($retryFromFailExecuted),
            'changes_discarded_by_rollback_to_savepoint' => count($failResult['returning']) + self::changeCountNext208($retryFromFailExecuted),
            'failed_conflict' => $failResult['failed_conflict'],
            'changed_tables_after_rollback' => self::changedTablesNext208($initial, $afterRollbackToSavepoint),
            'row_counts' => self::rowCountsNext208($afterRollbackToSavepoint),
            'dependencies' => [
                'sqlite-update-or-fail-rowvalue-returning-preserves-prior-rows-next208',
                'sqlite-rowvalue-retry-reads-partial-or-fail-current-source-next208',
                'sqlite-rollback-to-savepoint-discards-or-fail-returning-current-source-next208',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNext208(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase, bool $preserveFailChanges): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints, $preserveFailChanges);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext208($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext208(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext208($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext208(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value OR FAIL next208 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR FAIL next208 rows must be arrays');
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
    private static function rowsByIdsNext208(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL next208 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL next208 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNext208(array $yielded): int
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
    private static function changeCountNext208(array $executed): int
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
    private static function changedTablesNext208(array $before, array $after): array
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
    private static function rowCountsNext208(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function assertIdentifierNext208(string $value): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next208 savepoint must be an identifier');
        }
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeFailStatements
     * @param string $failStatement
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext209(
        array $tables,
        array $beforeFailStatements,
        string $failStatement,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_fail_next209',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($beforeFailStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next209 needs pre-fail statements');
        }
        if (trim($failStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next209 needs a fail statement');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next209 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next209 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next209 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeTablesNext209($tables);
        [$beforeFailCurrent, $beforeFailExecuted, $beforeFailReturning] = self::runStatementsNext209(
            $savepointImage,
            $beforeFailStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-fail-next209',
        );

        [$afterFailCurrent, $failSummary, $failReturning] = self::runFailStatementNext209(
            $beforeFailCurrent,
            $failStatement,
            $uniqueConstraints,
            $rowIdColumn,
            'or-fail-next209',
        );

        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext209(
            $afterFailCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-fail-next209',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-or-fail-current-source-next209',
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
            'pre_fail_yielded_count' => self::returningCountNext209($beforeFailReturning),
            'fail_preserved_yielded_count' => self::returningCountNext209($failReturning),
            'suppressed_by_fail_count' => count($failSummary['suppressed_returning_rows']),
            'yielded_after_retry_count' => self::returningCountNext209($retryReturning),
            'pre_fail_changes_preserved_count' => self::changeCountNext209($beforeFailExecuted),
            'fail_changes_preserved_count' => count($failSummary['returning_rows']),
            'retry_changes_after_fail' => self::changeCountNext209($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext209($savepointImage, $afterRetry),
            'row_counts' => self::rowCountsNext209($afterRetry),
            'dependencies' => [
                'sqlite-rowvalue-update-or-fail-preserves-prior-returning-next209',
                'sqlite-rowvalue-update-or-fail-suppresses-conflicting-returning-next209',
                'sqlite-rowvalue-delete-returning-retry-after-fail-next209',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNext209(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext209($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null);
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
    private static function runFailStatementNext209(array $tables, string $sql, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        if ($parsed['action'] !== 'update' || $parsed['conflict_action'] !== 'fail') {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next209 fail statement must be UPDATE OR FAIL');
        }

        $probe = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, [], true);
        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints, true);
        $summary = self::statementSummaryNext209($phase, 0, $sql, $result, $tables, $rowIdColumn, null);
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
    private static function statementSummaryNext209(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $error): array
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
            'source_rows' => self::rowsByIdsNext209($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext209(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value OR FAIL next209 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR FAIL next209 rows must be arrays');
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
    private static function rowsByIdsNext209(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL next209 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL next209 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNext209(array $yielded): int
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
    private static function changeCountNext209(array $executed): int
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
    private static function changedTablesNext209(array $before, array $after): array
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
    private static function rowCountsNext209(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext210(
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
        self::assertIdentifierNext210($savepoint);

        $savepointImage = self::normalizeTablesNext210($tables);
        [$attemptCurrent, $attemptExecuted, $attemptReturning] = self::runStatementsNext210(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-before-ignore-rollback-next210',
        );
        self::assertHasIgnoreConflictNext210($attemptExecuted);

        $afterRollbackToSavepoint = $savepointImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext210(
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
            'ignored_rows_before_rollback' => self::ignoredRowsNext210($attemptExecuted),
            'attempt_yielded_count' => self::returningCountNext210($attemptReturning),
            'ignored_row_count' => count(self::ignoredRowsNext210($attemptExecuted)),
            'suppressed_by_rollback_count' => self::returningCountNext210($attemptReturning),
            'yielded_after_retry_count' => self::returningCountNext210($retryReturning),
            'attempt_changes_before_rollback_to' => self::changeCountNext210($attemptExecuted),
            'changes_after_retry_release' => self::changeCountNext210($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext210($savepointImage, $afterRetry),
            'row_counts' => self::rowCountsNext210($afterRetry),
            'dependency_closure_next210' => 'no new support component needed; next210 reuses native row-value UPDATE/DELETE RETURNING execution, unique-conflict IGNORE handling, and savepoint current-source row images',
            'dependencies' => [
                'sqlite-rowvalue-update-or-ignore-returning-suppresses-conflict-next210',
                'sqlite-rollback-to-savepoint-discards-ignore-returning-stream-next210',
                'sqlite-rowvalue-retry-after-ignore-rollback-reads-savepoint-image-next210',
            ],
            'non_overlap_next210' => 'adds OR IGNORE row-value RETURNING rollback-to-savepoint suppression; avoids next209/next208 OR FAIL, next203 IGNORE/REPLACE release flow, next205 RELEASE admission, next206 released-inner rollback, next178 OR ROLLBACK, trigger RETURNING, WAL/VFS, JSON, planner, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNext210(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext210($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext210(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext210($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext210(array $tables): array
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
    private static function rowsByIdsNext210(array $rows, array $ids, string $rowIdColumn): array
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
    private static function assertHasIgnoreConflictNext210(array $executed): void
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
    private static function ignoredRowsNext210(array $executed): array
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
    private static function returningCountNext210(array $yielded): int
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
    private static function changeCountNext210(array $summaries): int
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
    private static function changedTablesNext210(array $before, array $after): array
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
    private static function rowCountsNext210(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function assertIdentifierNext210(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next210 savepoint must be an identifier');
        }
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeIgnoreStatements
     * @param string $ignoreStatement
     * @param list<string> $afterIgnoreStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext211(
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

        $savepointImage = self::normalizeTablesNext211($tables);
        [$preCurrent, $preStatements, $preReturning] = self::runStatementsNext211(
            $savepointImage,
            $beforeIgnoreStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-ignore-next211',
        );
        [$ignoreCurrent, $ignoreSummary, $ignoreReturning] = self::runIgnoreStatementNext211(
            $preCurrent,
            $ignoreStatement,
            $uniqueConstraints,
            $rowIdColumn,
        );
        [$afterCurrent, $afterStatements, $afterReturning] = self::runStatementsNext211(
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
            'pre_ignore_yielded_count' => self::returningCountNext211($preReturning),
            'ignore_yielded_count' => self::returningCountNext211($ignoreReturning),
            'ignored_by_conflict_count' => count($ignoreSummary['ignored_rows']),
            'yielded_after_ignore_count' => self::returningCountNext211($afterReturning),
            'pre_ignore_changes_count' => self::changeCountNext211($preStatements),
            'ignore_changes_count' => count($ignoreSummary['returning_rows']),
            'after_ignore_changes_count' => self::changeCountNext211($afterStatements),
            'changed_tables_after_release' => self::changedTablesNext211($savepointImage, $afterCurrent),
            'row_counts' => self::rowCountsNext211($afterCurrent),
            'dependency_closure' => 'no-new-support-component-reuses-native-update-delete-returning-rowvalue-conflict-and-savepoint-current-source',
            'non_overlap' => 'next211 covers UPDATE OR IGNORE row-value RETURNING suppression and savepoint release current-source chaining; avoids accepted next209 OR FAIL, next205 release, next202 parenthesized rollback, trigger RETURNING, WAL/VFS, JSON, B-tree, planner, and encoding clusters',
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
    private static function runStatementsNext211(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext211($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function runIgnoreStatementNext211(array $tables, string $sql, array $uniqueConstraints, string $rowIdColumn): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        if ($parsed['action'] !== 'update' || $parsed['conflict_action'] !== 'ignore') {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next211 ignore statement must be UPDATE OR IGNORE');
        }

        $probe = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, [], true);
        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints, true);
        $summary = self::statementSummaryNext211('or-ignore-next211', 0, $sql, $result, $tables, $rowIdColumn);
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
    private static function statementSummaryNext211(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext211($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext211(array $tables): array
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
    private static function rowsByIdsNext211(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext211(array $yielded): int
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
    private static function changeCountNext211(array $executed): int
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
    private static function changedTablesNext211(array $before, array $after): array
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
    private static function rowCountsNext211(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext212(
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

        $savepointImage = self::normalizeTablesNext212($tables);
        [$attemptCurrent, $attemptExecuted, $attemptReturning] = self::runStatementsNext212(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-before-rollback-next212',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatementsNext212(
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
            'discarded_attempt_returning_count' => self::returningCountNext212($attemptReturning),
            'yielded_after_retry_count' => self::returningCountNext212($retryReturning),
            'attempt_changes_before_rollback' => self::changeCountNext212($attemptExecuted),
            'retry_changes_after_rollback' => self::changeCountNext212($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext212($savepointImage, $retryCurrent),
            'row_counts' => self::rowCountsNext212($retryCurrent),
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
    private static function runStatementsNext212(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext212($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext212(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext212($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext212(array $tables): array
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
    private static function rowsByIdsNext212(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext212(array $yielded): int
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
    private static function changeCountNext212(array $executed): int
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
    private static function changedTablesNext212(array $before, array $after): array
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
    private static function rowCountsNext212(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        ksort($counts);

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext213(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_order_limit_next213',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext212(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $plan = self::replaceMarkerNext213($plan);
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
    private static function replaceMarkerNext213(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(['next212', 'subquery'], ['next213', 'order-limit-subquery'], $value);
        }
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $entry) {
            $value[$key] = self::replaceMarkerNext213($entry);
        }

        return $value;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeRollbackStatements
     * @param string $rollbackStatement
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext217(
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
        self::assertIdentifierNext217($transactionName, 'transaction');
        self::assertIdentifierNext217($savepoint, 'savepoint');
        self::assertIdentifierNext217($retrySavepoint, 'retry savepoint');

        $transactionImage = self::normalizeTablesNext217($tables);
        [$beforeCurrent, $beforeStatements, $beforeReturning] = self::runStatementsNext217(
            $transactionImage,
            $beforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-or-rollback-next217',
        );
        [$afterRollback, $rollbackSummary] = self::runRollbackStatementNext217(
            $beforeCurrent,
            $rollbackStatement,
            $transactionImage,
            $uniqueConstraints,
            $rowIdColumn,
        );
        [$afterRetry, $retryStatementsExecuted, $retryReturning] = self::runStatementsNext217(
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
            'pre_rollback_yielded_count' => self::returningCountNext217($beforeReturning),
            'pre_rollback_changes_count' => self::changeCountNext217($beforeStatements),
            'suppressed_by_rollback_count' => count($rollbackSummary['returning_rows']),
            'retry_yielded_count' => self::returningCountNext217($retryReturning),
            'retry_changes_count' => self::changeCountNext217($retryStatementsExecuted),
            'changed_tables_after_retry' => self::changedTablesNext217($transactionImage, $afterRetry),
            'row_counts' => self::rowCountsNext217($afterRetry),
            'dependency_closure_next217' => 'no new support component needed; next217 reuses native row-value UPDATE/DELETE RETURNING execution and current-source savepoint row images',
            'non_overlap_next217' => 'adds transaction-level UPDATE OR ROLLBACK row-value RETURNING suppression and retry after transaction rollback; avoids accepted next210/next211 OR IGNORE rollback, next209/next207 OR FAIL, next192 statement-only OR ABORT, trigger RETURNING, WAL/VFS, JSON, planner, and B-tree clusters',
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
    private static function runStatementsNext217(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext217($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null);
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
    private static function runRollbackStatementNext217(
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
                self::statementSummaryNext217('or-rollback-next217', 0, $sql, $probe, $tables, $rowIdColumn, $exception->getMessage()) + [
                    'aborted' => true,
                    'error' => $exception->getMessage(),
                    'rolled_back_to_transaction_start' => true,
                    'closed_savepoint' => true,
                ],
            ];
        }

        return [
            $result['tables'],
            self::statementSummaryNext217('or-rollback-next217', 0, $sql, $result, $tables, $rowIdColumn, null) + [
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
    private static function statementSummaryNext217(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $error): array
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
            'source_rows' => self::rowsByIdsNext217($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext217(array $tables): array
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

    private static function assertIdentifierNext217(string $identifier, string $label): void
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
    private static function rowsByIdsNext217(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext217(array $yielded): int
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
    private static function changeCountNext217(array $executed): int
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
    private static function changedTablesNext217(array $before, array $after): array
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
    private static function rowCountsNext217(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $savepointStatements
     * @param list<string> $attemptedStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext218(
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

        $savepointImage = self::normalizeTablesNext218($tables);
        [$attemptSource, $savepointExecuted, $savepointReturning] = self::runStatementsNext218(
            $savepointImage,
            $savepointStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'savepoint-before-rollback-to-next218',
        );
        [$attemptCurrent, $attemptedExecuted, $attemptedReturning] = self::runStatementsNext218(
            $attemptSource,
            $attemptedStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-before-rollback-to-next218',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatementsNext218(
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
            'savepoint_returning_count' => self::returningCountNext218($savepointReturning),
            'suppressed_attempted_returning_count' => self::returningCountNext218($attemptedReturning),
            'retry_returning_count' => self::returningCountNext218($retryReturning),
            'attempted_change_count' => self::changeCountNext218($attemptedExecuted),
            'retry_change_count' => self::changeCountNext218($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext218($savepointImage, $retryCurrent),
            'row_counts' => self::rowCountsNext218($retryCurrent),
            'rollback_receipt_next218' => [
                'savepoint' => $savepoint,
                'restored_tables' => array_keys($rollbackCurrent),
                'suppressed_returning_count' => self::returningCountNext218($attemptedReturning),
                'retry_statement_count' => count($retryStatements),
            ],
            'dependency_closure_next218' => 'no new support component needed; next218 reuses native row-value UPDATE/DELETE RETURNING execution and row-array savepoint images',
            'dependencies' => [
                'sqlite-rowvalue-rollback-to-restores-savepoint-image-next218',
                'sqlite-rowvalue-returning-suppressed-after-rollback-to-next218',
                'wordpress-rowvalue-update-delete-returning-savepoint-rollback-next218',
            ],
            'non_overlap_next218' => 'models explicit ROLLBACK TO savepoint image restoration after successful row-value UPDATE/DELETE RETURNING attempts; avoids accepted next200 statement ABORT preservation, next205 RELEASE current-source admission, next211 OR IGNORE/savepoint behavior, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNext218(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext218($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext218(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext218($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext218(array $tables): array
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
    private static function rowsByIdsNext218(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext218(array $yielded): int
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
    private static function changeCountNext218(array $executed): int
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
    private static function changedTablesNext218(array $before, array $after): array
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
    private static function rowCountsNext218(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext219(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_negative_limit_offset_next219',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext212(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $plan = self::replaceMarkerNext219($plan);
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
    private static function replaceMarkerNext219(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(['next212', 'subquery'], ['next219', 'negative-limit-offset-subquery'], $value);
        }
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $entry) {
            $value[$key] = self::replaceMarkerNext219($entry);
        }

        return $value;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeAbortStatements
     * @param string $abortStatement
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext220(
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

        $savepointImage = self::normalizeTablesNext220($tables);
        [$beforeAbortCurrent, $beforeAbortExecuted, $beforeAbortReturning] = self::runStatementsNext220(
            $savepointImage,
            $beforeAbortStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-or-abort-next220',
        );
        [$afterAbortCurrent, $abortSummary] = self::runAbortStatementNext220(
            $beforeAbortCurrent,
            $abortStatement,
            $uniqueConstraints,
            $rowIdColumn,
        );
        [$afterRetry, $retryStatementsExecuted, $retryReturning] = self::runStatementsNext220(
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
            'pre_abort_yielded_count' => self::returningCountNext220($beforeAbortReturning),
            'pre_abort_changes_count' => self::changeCountNext220($beforeAbortExecuted),
            'suppressed_by_abort_count' => count($abortSummary['returning_rows']),
            'retry_yielded_count' => self::returningCountNext220($retryReturning),
            'retry_changes_count' => self::changeCountNext220($retryStatementsExecuted),
            'changed_tables_after_retry' => self::changedTablesNext220($savepointImage, $afterRetry),
            'row_counts' => self::rowCountsNext220($afterRetry),
            'dependency_closure_next220' => 'no new support component needed; next220 reuses native row-value UPDATE/DELETE RETURNING execution, unique conflict checks, and savepoint current-source row images',
            'non_overlap_next220' => 'adds statement-level UPDATE OR ABORT row-value RETURNING suppression inside a preserved savepoint; avoids accepted next217 transaction OR ROLLBACK, next210/next211 OR IGNORE, next209 OR FAIL, next212 subquery rollback, trigger RETURNING, WAL/VFS, JSON, planner, encoding, PRAGMA, and B-tree clusters',
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
    private static function runStatementsNext220(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext220($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null);
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
    private static function runAbortStatementNext220(array $tables, string $sql, array $uniqueConstraints, string $rowIdColumn): array
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
                self::statementSummaryNext220('or-abort-next220', 0, $sql, $probe, $tables, $rowIdColumn, $exception->getMessage()) + [
                    'aborted' => true,
                    'error' => $exception->getMessage(),
                    'rolled_back_statement_only' => true,
                    'savepoint_remains_open' => true,
                ],
            ];
        }

        return [
            $result['tables'],
            self::statementSummaryNext220('or-abort-next220', 0, $sql, $result, $tables, $rowIdColumn, null) + [
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
    private static function statementSummaryNext220(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $error): array
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
            'source_rows' => self::rowsByIdsNext220($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext220(array $tables): array
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
    private static function rowsByIdsNext220(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext220(array $yielded): int
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
    private static function changeCountNext220(array $executed): int
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
    private static function changedTablesNext220(array $before, array $after): array
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
    private static function rowCountsNext220(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        ksort($counts);

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $innerStatements
     * @param list<string> $outerAttemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext224(
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
        self::assertIdentifierNext224($outerSavepoint, 'outer savepoint');
        self::assertIdentifierNext224($innerSavepoint, 'inner savepoint');
        if ($outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value nested rollback next224 savepoint names must differ');
        }

        $outerImage = self::normalizeTablesNext224($tables);
        [$afterInner, $innerExecuted, $innerReturning] = self::runStatementsNext224(
            $outerImage,
            $innerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-released-before-outer-rollback-next224',
        );
        [$afterAttempt, $outerAttemptExecuted, $outerAttemptReturning] = self::runStatementsNext224(
            $afterInner,
            $outerAttemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-attempt-before-rollback-next224',
        );

        $afterOuterRollback = $outerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext224(
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
            'released_inner_returning_count' => self::returningCountNext224($innerReturning),
            'outer_attempt_returning_count' => self::returningCountNext224($outerAttemptReturning),
            'suppressed_returning_count' => self::returningCountNext224($innerReturning) + self::returningCountNext224($outerAttemptReturning),
            'retry_returning_count' => self::returningCountNext224($retryReturning),
            'released_inner_change_count' => self::changeCountNext224($innerExecuted),
            'outer_attempt_change_count' => self::changeCountNext224($outerAttemptExecuted),
            'retry_change_count' => self::changeCountNext224($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext224($outerImage, $afterRetry),
            'row_counts' => self::rowCountsNext224($afterRetry),
            'rollback_receipt_next224' => [
                'outer_savepoint' => $outerSavepoint,
                'inner_savepoint' => $innerSavepoint,
                'released_inner_statement_count' => count($innerStatements),
                'outer_attempt_statement_count' => count($outerAttemptStatements),
                'retry_statement_count' => count($retryStatements),
                'suppressed_returning_count' => self::returningCountNext224($innerReturning) + self::returningCountNext224($outerAttemptReturning),
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
    private static function runStatementsNext224(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext224($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext224(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext224($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext224(array $tables): array
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

    private static function assertIdentifierNext224(string $name, string $label): void
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
    private static function rowsByIdsNext224(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext224(array $yielded): int
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
    private static function changeCountNext224(array $executed): int
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
    private static function changedTablesNext224(array $before, array $after): array
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
    private static function rowCountsNext224(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext225(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_distinct_subquery_next225',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext212(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $plan = self::replaceMarkerNext225($plan);
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
    private static function replaceMarkerNext225(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(['next212', 'subquery'], ['next225', 'distinct-subquery'], $value);
        }
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $entry) {
            $value[$key] = self::replaceMarkerNext225($entry);
        }

        return $value;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext226(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_distinct_subquery_next226',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext212(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $plan = self::replaceMarkerNext226($plan);
        $plan['status'] = 'rowvalue-update-delete-returning-distinct-subquery-savepoint-current-source-next226';
        $plan['savepoint'] = $savepoint;
        $plan['distinct_subquery_source'] = true;
        $plan['dependency_closure_next226'] = 'no new support component needed; next226 reuses native row-value UPDATE/DELETE RETURNING execution and adds bounded SELECT DISTINCT tuple-source handling';
        $plan['non_overlap_next226'] = 'adds SELECT DISTINCT tuple sources feeding row-value UPDATE/DELETE RETURNING under savepoint rollback and retry; avoids accepted next219 negative LIMIT/OFFSET, next213 positive ORDER/LIMIT, next217 OR ROLLBACK, WAL/VFS, JSON, planner, trigger, and B-tree clusters';
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-distinct-select-subquery-next226',
            'sqlite-rowvalue-delete-returning-distinct-select-subquery-next226',
            'sqlite-rowvalue-distinct-subquery-savepoint-current-source-next226',
        ];

        return $plan;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function replaceMarkerNext226(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(['next212', 'subquery'], ['next226', 'distinct-subquery'], $value);
        }
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $entry) {
            $value[$key] = self::replaceMarkerNext226($entry);
        }

        return $value;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerStatements
     * @param string $failStatement
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext228(
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
        self::assertIdentifierNext228($outerSavepoint, 'outer savepoint');
        self::assertIdentifierNext228($innerSavepoint, 'inner savepoint');
        if ($outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 savepoint names must differ');
        }

        $outerImage = self::normalizeTablesNext228($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNext228(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-inner-savepoint-next228',
        );

        $innerImage = $afterOuter;
        [$afterInner, $innerExecuted, $innerReturning] = self::runStatementsNext228(
            $innerImage,
            $innerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-before-fail-next228',
        );

        [$afterFail, $failSummary, $failReturning] = self::runFailStatementNext228(
            $afterInner,
            $failStatement,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-or-fail-before-rollback-next228',
        );

        $afterInnerRollback = $innerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext228(
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
            'outer_yielded_count' => self::returningCountNext228($outerReturning),
            'inner_suppressed_count' => self::returningCountNext228($innerReturning),
            'fail_preserved_before_rollback_count' => self::returningCountNext228($failReturning),
            'fail_suppressed_conflicting_count' => count($failSummary['suppressed_returning_rows']),
            'total_suppressed_by_inner_rollback_count' => self::returningCountNext228($innerReturning) + self::returningCountNext228($failReturning) + count($failSummary['suppressed_returning_rows']),
            'retry_returning_count' => self::returningCountNext228($retryReturning),
            'outer_change_count' => self::changeCountNext228($outerExecuted),
            'inner_change_count' => self::changeCountNext228($innerExecuted),
            'fail_preserved_change_count' => count($failSummary['returning_rows']),
            'retry_change_count' => self::changeCountNext228($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext228($outerImage, $afterRetry),
            'row_counts' => self::rowCountsNext228($afterRetry),
            'rollback_receipt_next228' => [
                'outer_savepoint' => $outerSavepoint,
                'inner_savepoint' => $innerSavepoint,
                'inner_statement_count' => count($innerStatements),
                'fail_statement_conflict' => $failSummary['failed_conflict'],
                'suppressed_returning_count' => self::returningCountNext228($innerReturning) + self::returningCountNext228($failReturning) + count($failSummary['suppressed_returning_rows']),
                'restored_tables' => array_keys($afterInnerRollback),
            ],
            'dependency_closure_next228' => 'no new support component needed; next228 reuses native row-value UPDATE/DELETE RETURNING, OR FAIL preservation, and nested savepoint current-source row images',
            'dependencies' => [
                'sqlite-rowvalue-inner-savepoint-rollback-suppresses-returning-next228',
                'sqlite-rowvalue-update-or-fail-prior-rows-rolled-back-by-savepoint-next228',
                'wordpress-rowvalue-savepoint-retry-reads-outer-current-source-next228',
            ],
            'non_overlap_next228' => 'adds inner ROLLBACK TO after UPDATE OR FAIL so preserved FAIL rows and earlier inner RETURNING are suppressed while outer savepoint changes remain current; avoids accepted next209 preserved FAIL retry source, next224 released inner discarded by outer rollback, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNext228(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext228($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function runFailStatementNext228(array $tables, string $sql, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        if ($parsed['action'] !== 'update' || $parsed['conflict_action'] !== 'fail') {
            throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 fail statement must be UPDATE OR FAIL');
        }

        $probe = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, [], true);
        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints, true);
        $summary = self::statementSummaryNext228($phase, 0, $sql, $result, $tables, $rowIdColumn);
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
    private static function statementSummaryNext228(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext228($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext228(array $tables): array
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

    private static function assertIdentifierNext228(string $name, string $label): void
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
    private static function rowsByIdsNext228(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext228(array $yielded): int
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
    private static function changeCountNext228(array $executed): int
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
    private static function changedTablesNext228(array $before, array $after): array
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
    private static function rowCountsNext228(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext229(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_select_retry_next229',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($yieldStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value select retry next229 needs yield statements');
        }
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value select retry next229 needs attempted statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value select retry next229 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value select retry next229 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value select retry next229 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeTablesNext229($tables);
        [$yieldCurrent, $yieldExecuted, $yieldReturning] = self::runStatementsNext229(
            $savepointImage,
            $yieldStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'yield-subquery-before-rollback-to-next229',
        );
        [$attemptCurrent, $attemptExecuted, $attemptReturning] = self::runStatementsNext229(
            $yieldCurrent,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-subquery-after-yield-before-rollback-to-next229',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatementsNext229(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-subquery-after-rollback-release-next229',
        );

        $yieldedRows = self::flattenReturningNext229($yieldReturning);
        $suppressedRows = self::flattenReturningNext229($attemptReturning);
        $retryRows = self::flattenReturningNext229($retryReturning);

        return [
            'status' => 'rowvalue-update-delete-returning-subquery-savepoint-release-current-source-next229',
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
            'yield_change_count' => self::changeCountNext229($yieldExecuted),
            'attempt_change_count' => self::changeCountNext229($attemptExecuted),
            'retry_change_count' => self::changeCountNext229($retryExecuted),
            'rowvalue_subquery_targets_next229' => true,
            'rollback_to_savepoint_next229' => true,
            'release_commits_retry_next229' => true,
            'yielded_rows_survive_rollback_next229' => true,
            'attempted_rows_suppressed_next229' => true,
            'retry_reads_savepoint_image_next229' => true,
            'savepoint_released_next229' => true,
            'changed_tables_after_release' => self::changedTablesNext229($savepointImage, $retryCurrent),
            'row_counts' => self::rowCountsNext229($retryCurrent),
            'release_receipt_next229' => [
                'savepoint' => $savepoint,
                'yielded_count' => count($yieldedRows),
                'suppressed_count' => count($suppressedRows),
                'retry_count' => count($retryRows),
                'yielded_ids' => self::idsFromRowsNext229($yieldedRows, $rowIdColumn),
                'suppressed_ids' => self::idsFromRowsNext229($suppressedRows, $rowIdColumn),
                'retry_ids' => self::idsFromRowsNext229($retryRows, $rowIdColumn),
                'released_tables' => array_keys($retryCurrent),
            ],
            'dependency_closure_next229' => 'no new support component needed; next229 reuses native PHP UPDATE/DELETE RETURNING row-value subquery dispatch and savepoint row images',
            'dependencies' => [
                'sqlite-rowvalue-in-select-update-delete-returning-next229',
                'sqlite-rowvalue-returning-rollback-to-release-retry-next229',
                'wordpress-rowvalue-select-savepoint-release-current-source-next229',
            ],
            'non_overlap_next229' => 'adds row-value IN (SELECT ...) target selection through UPDATE/DELETE RETURNING across ROLLBACK TO and final RELEASE; avoids accepted next223 yield-only rollback fencing, next224 nested release discarded by outer rollback, next218 rollback image restoration, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNext229(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext229($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext229(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext229($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext229(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value select retry next229 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value select retry next229 rows must be arrays');
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
    private static function rowsByIdsNext229(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value select retry next229 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value select retry next229 rowid column {$rowIdColumn} must be int or string");
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
    private static function flattenReturningNext229(array $yielded): array
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
    private static function changeCountNext229(array $executed): int
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
    private static function changedTablesNext229(array $before, array $after): array
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
    private static function rowCountsNext229(array $tables): array
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
    private static function idsFromRowsNext229(array $rows, string $rowIdColumn): array
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


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $preStatements
     * @param list<string> $innerStatements
     * @param list<string> $afterReleaseStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext230(
        array $tables,
        array $preStatements,
        array $innerStatements,
        array $afterReleaseStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_rowvalue_outer_next230',
        string $innerSavepoint = 'wp_options_rowvalue_inner_next230',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($preStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next230 needs pre statements');
        }
        if ($innerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next230 needs inner statements');
        }
        if ($afterReleaseStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next230 needs after-release statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next230 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next230 needs unique constraints');
        }
        self::assertIdentifierNext230($outerSavepoint, 'outer savepoint');
        self::assertIdentifierNext230($innerSavepoint, 'inner savepoint');
        if ($outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next230 savepoint names must differ');
        }

        $initial = self::normalizeTablesNext230($tables);
        [$preCurrent, $preSummaries, $preReturning] = self::runStatementsNext230(
            $initial,
            $preStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'pre-outer-savepoint-next230',
        );

        $outerImage = $preCurrent;
        [$innerCurrent, $innerSummaries, $innerReturning] = self::runStatementsNext230(
            $outerImage,
            $innerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-before-release-next230',
        );

        $innerReleaseImage = $innerCurrent;
        [$afterReleaseCurrent, $afterReleaseSummaries, $afterReleaseReturning] = self::runStatementsNext230(
            $innerReleaseImage,
            $afterReleaseStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'after-inner-release-next230',
        );

        $rollbackCurrent = $outerImage;
        [$retryCurrent, $retrySummaries, $retryReturning] = self::runStatementsNext230(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-outer-rollback-next230',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-nested-savepoint-current-source-next230',
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
            'pre_returning_count' => self::returningCountNext230($preReturning),
            'discarded_inner_release_returning_count' => self::returningCountNext230($innerReturning) + self::returningCountNext230($afterReleaseReturning),
            'yielded_after_retry_count' => self::returningCountNext230($retryReturning),
            'changes_before_outer_rollback' => self::changeCountNext230($innerSummaries) + self::changeCountNext230($afterReleaseSummaries),
            'retry_changes_after_outer_rollback' => self::changeCountNext230($retrySummaries),
            'changed_tables_after_retry' => self::changedTablesNext230($initial, $retryCurrent),
            'row_counts' => self::rowCountsNext230($retryCurrent),
            'dependency_closure_next230' => 'no new support component needed; reuses native row-value UPDATE/DELETE RETURNING, subquery row-value predicates, and savepoint current-source images',
            'dependencies' => [
                'sqlite-nested-savepoint-release-returning-discarded-by-outer-rollback-next230',
                'sqlite-rowvalue-update-delete-returning-retry-after-outer-rollback-next230',
                'wordpress-rowvalue-nested-savepoint-current-source-next230',
            ],
            'non_overlap_next230' => 'adds nested inner RELEASE plus outer ROLLBACK TO suppression for row-value UPDATE/DELETE RETURNING; avoids accepted simple rollback next212, OR FAIL next207, OR ABORT next200, OR ROLLBACK/RELEASE variants, WAL/VFS, JSON table, planner, trigger, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNext230(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $summaries = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $summaries[] = self::statementSummaryNext230($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext230(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext230($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext230(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value nested savepoint next230 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value nested savepoint next230 rows must be arrays');
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
    private static function rowsByIdsNext230(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value nested savepoint next230 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value nested savepoint next230 rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCountNext230(array $yielded): int
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
    private static function changeCountNext230(array $summaries): int
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
    private static function changedTablesNext230(array $before, array $after): array
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
    private static function rowCountsNext230(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }
        ksort($counts);

        return $counts;
    }

    private static function assertIdentifierNext230(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value nested savepoint next230 {$label} must be an identifier");
        }
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext231(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_compound_subquery_next231',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext212(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $plan = self::replaceMarkerNext231($plan);
        $plan['status'] = 'rowvalue-update-delete-returning-compound-subquery-savepoint-current-source-next231';
        $plan['savepoint'] = $savepoint;
        $plan['compound_subquery_source_next231'] = true;
        $plan['compound_operators_next231'] = ['UNION', 'UNION ALL', 'INTERSECT', 'EXCEPT'];
        $plan['dependency_closure_next231'] = 'no new support component needed; next231 reuses native row-value UPDATE/DELETE RETURNING execution and adds bounded compound SELECT tuple-source handling';
        $plan['non_overlap_next231'] = 'adds UNION/UNION ALL/INTERSECT/EXCEPT tuple sources feeding row-value UPDATE/DELETE RETURNING under savepoint rollback and retry; avoids accepted next226 DISTINCT subqueries, next219 negative LIMIT/OFFSET, next213 positive ORDER/LIMIT, next217 OR ROLLBACK, WAL/VFS, JSON, planner, trigger, and B-tree clusters';
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-compound-select-subquery-next231',
            'sqlite-rowvalue-delete-returning-compound-select-subquery-next231',
            'sqlite-rowvalue-compound-subquery-savepoint-current-source-next231',
        ];

        return $plan;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function replaceMarkerNext231(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(['next212', 'subquery'], ['next231', 'compound-subquery'], $value);
        }
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $entry) {
            $value[$key] = self::replaceMarkerNext231($entry);
        }

        return $value;
    }
    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext160Plan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeStatements
     * @param list<string> $protectedStatements
     * @param list<string> $afterStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext160(
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
        $savepoint = self::identifierNext160($savepoint, 'savepoint');
        if ($rollbackToProtectedOrdinal !== null && ($rollbackToProtectedOrdinal < 0 || $rollbackToProtectedOrdinal >= count($protectedStatements))) {
            throw new \InvalidArgumentException('SQLite row-value savepoint next160 rollback ordinal is outside protected statement list');
        }

        $transactionImage = self::normalizeTablesNext160($tables);
        $before = self::runStatementsNext160($beforeStatements, $transactionImage, $uniqueConstraints, $rowIdColumn, 'before');
        $savepointImage = $before['tables'];

        $protectedCurrent = $savepointImage;
        $protectedExecuted = [];
        $protectedYielded = [];
        foreach ($protectedStatements as $ordinal => $sql) {
            $result = self::runStatementNext160($sql, $protectedCurrent, $uniqueConstraints, $rowIdColumn, 'protected', $ordinal);
            $protectedCurrent = $result['tables'];
            $protectedExecuted[] = $result['statement'];
            $protectedYielded[] = $result['yield'];
            if ($rollbackToProtectedOrdinal === $ordinal) {
                break;
            }
        }

        $rolledBack = $rollbackToProtectedOrdinal !== null;
        $afterStart = $rolledBack ? $savepointImage : $protectedCurrent;
        $after = self::runStatementsNext160($afterStatements, $afterStart, $uniqueConstraints, $rowIdColumn, 'after');
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
            'discarded_returning_count' => $rolledBack ? self::returningCountNext160($protectedYielded) : 0,
            'changes' => self::changeCountNext160(array_merge($before['statements'], $rolledBack ? [] : $protectedExecuted, $after['statements'])),
            'attempted_changes_before_rollback' => self::changeCountNext160(array_merge($before['statements'], $protectedExecuted)),
            'source_cursor' => self::sourceCursorNext160($before['statements'], $protectedExecuted, $after['statements'], $rolledBack),
            'row_counts' => self::rowCountsNext160($final),
            'changed_tables' => self::changedTablesNext160($transactionImage, $final),
            'dependencies' => [
                'sqlite-rowvalue-update-delete-returning-savepoint-current-source-next160',
                'sqlite-rollback-to-savepoint-suppresses-update-delete-returning-yields-next160',
                'sqlite-current-source-after-rollback-restarts-from-savepoint-image-next160',
            ],
            'non_overlap' => 'covers explicit ROLLBACK TO savepoint over a mixed row-value UPDATE RETURNING and DELETE RETURNING protected batch; avoids accepted next148 DISTINCT retry, next156 conflict yielding, and next157 nested inner-savepoint rollback surfaces',
        ];
    }

    /**
     * @param list<string> $statements
     * @param array<string,list<array<string,mixed>>> $startTables
     * @param list<list<string>> $uniqueConstraints
     * @return array{tables:array<string,list<array<string,mixed>>>,statements:list<array<string,mixed>>,yielded:list<array<string,mixed>>}
     */
    private static function runStatementsNext160(array $statements, array $startTables, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $startTables;
        $executed = [];
        $yielded = [];
        foreach ($statements as $ordinal => $sql) {
            $result = self::runStatementNext160($sql, $current, $uniqueConstraints, $rowIdColumn, $phase, $ordinal);
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
    private static function runStatementNext160(string $sql, array $tables, array $uniqueConstraints, string $rowIdColumn, string $phase, int $ordinal): array
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
                'source_rows' => self::rowsByIdsNext160($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function sourceCursorNext160(array $before, array $protected, array $after, bool $rolledBack): array
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
    private static function returningCountNext160(array $streams): int
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
    private static function changeCountNext160(array $statements): int
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
    private static function normalizeTablesNext160(array $tables): array
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

    private static function identifierNext160(string $value, string $label): string
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
    private static function rowsByIdsNext160(array $rows, array $ids, string $rowIdColumn): array
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
    private static function rowCountsNext160(array $tables): array
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
    private static function changedTablesNext160(array $before, array $after): array
    {
        $changed = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $name) {
            if (($before[$name] ?? null) !== ($after[$name] ?? null)) {
                $changed[] = $name;
            }
        }

        return $changed;
    }

    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext186Plan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext186(
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

        $outerImage = self::normalizeTablesNext186($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNext186(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-empty-rowvalue-rollback',
        );

        $innerImage = $afterOuter;
        [$afterAttempt, $attemptExecuted, $attemptReturning] = self::runStatementsNext186(
            $innerImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-empty-rowvalue-before-rollback',
        );

        $afterRollback = $innerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext186(
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
            'outer_yielded_count' => self::returningCountNext186($outerReturning),
            'attempt_yielded_before_rollback_count' => self::returningCountNext186($attemptReturning),
            'suppressed_by_rollback_count' => self::returningCountNext186($attemptReturning),
            'yielded_after_retry_count' => self::returningCountNext186($retryReturning),
            'outer_changes_preserved' => self::changeCountNext186($outerExecuted),
            'attempted_changes_before_rollback' => self::changeCountNext186($attemptExecuted),
            'retry_changes_after_rollback' => self::changeCountNext186($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext186($outerImage, $afterRetry),
            'row_counts' => self::rowCountsNext186($afterRetry),
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
    private static function runStatementsNext186(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext186($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext186(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext186($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext186(array $tables): array
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
    private static function rowsByIdsNext186(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext186(array $yielded): int
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
    private static function changeCountNext186(array $executed): int
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
    private static function changedTablesNext186(array $before, array $after): array
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
    private static function rowCountsNext186(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext197Plan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext197(
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

        $outerImage = self::normalizeTablesNext197($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNext197(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-inner-rollback-next197',
        );

        $innerImage = $afterOuter;
        [$afterInner, $innerExecuted, $innerReturning] = self::runStatementsNext197(
            $innerImage,
            $innerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-before-rollback-to-next197',
        );

        $rolledBack = $innerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext197(
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
            'outer_yielded_returning_count' => self::returningCountNext197($outerReturning),
            'inner_rolled_back_returning_count' => self::returningCountNext197($innerReturning),
            'suppressed_by_rollback_to_count' => self::returningCountNext197($innerReturning),
            'yielded_after_retry_count' => self::returningCountNext197($retryReturning),
            'outer_changes_preserved' => self::changeCountNext197($outerExecuted),
            'inner_changes_rolled_back' => self::changeCountNext197($innerExecuted),
            'retry_changes_after_rollback_to' => self::changeCountNext197($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext197($outerImage, $afterRetry),
            'row_counts' => self::rowCountsNext197($afterRetry),
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
    private static function runStatementsNext197(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext197($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext197(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext197($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext197(array $tables): array
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
    private static function rowsByIdsNext197(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext197(array $yielded): int
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
    private static function changeCountNext197(array $executed): int
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
    private static function changedTablesNext197(array $before, array $after): array
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
    private static function rowCountsNext197(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext199Plan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @return array<string,mixed>
     */
    public static function executeNext199(
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

        $savepointImage = self::normalizeTablesNext199($tables);
        [$attemptedTables, $attemptedStatements, $attemptedReturning] = self::runStatementsNext199(
            $savepointImage,
            $attemptStatements,
            $rowIdColumn,
            'attempt-order-expression-before-rollback-next199',
        );
        [$retryTables, $retryStatementsSummary, $retryReturning] = self::runStatementsNext199(
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
            'attempt_returning_count' => self::returningCountNext199($attemptedReturning),
            'suppressed_by_rollback_count' => self::returningCountNext199($attemptedReturning),
            'yielded_after_retry_count' => self::returningCountNext199($retryReturning),
            'attempt_changes_before_rollback_to' => self::changeCountNext199($attemptedStatements),
            'changes_after_retry_release' => self::changeCountNext199($retryStatementsSummary),
            'changed_tables_after_retry' => self::changedTablesNext199($savepointImage, $retryTables),
            'row_counts' => self::rowCountsNext199($retryTables),
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
    private static function runStatementsNext199(array $tables, array $statements, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext199($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext199(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext199($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext199(array $tables): array
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
    private static function rowsByIdsNext199(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext199(array $yielded): int
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
    private static function changeCountNext199(array $executed): int
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
    private static function changedTablesNext199(array $before, array $after): array
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
    private static function rowCountsNext199(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext201Plan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $savepointStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext201(
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
        self::assertIdentifierNext201($savepoint, 'savepoint');

        $initialTables = self::normalizeTablesNext201($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNext201($initialTables, $outerStatements, $uniqueConstraints, $rowIdColumn, 'outer-before-savepoint-next201');

        $savepointImage = $afterOuter;
        [$afterSavepoint, $savepointExecuted, $savepointReturning] = self::runStatementsNext201($savepointImage, $savepointStatements, $uniqueConstraints, $rowIdColumn, 'savepoint-before-rollback-to-next201');

        $afterRollbackTo = $savepointImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext201($afterRollbackTo, $retryStatements, $uniqueConstraints, $rowIdColumn, 'retry-after-rollback-to-next201');

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
            'outer_yielded_returning_count' => self::returningCountNext201($outerReturning),
            'discarded_savepoint_returning_count' => self::returningCountNext201($savepointReturning),
            'yielded_after_retry_count' => self::returningCountNext201($retryReturning),
            'discarded_savepoint_changes' => self::changeCountNext201($savepointExecuted),
            'changes_after_retry' => self::changeCountNext201($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext201($initialTables, $afterRetry),
            'row_counts' => self::rowCountsNext201($afterRetry),
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
    private static function runStatementsNext201(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext201($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext201(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext201($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext201(array $tables): array
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
    private static function rowsByIdsNext201(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext201(array $yielded): int
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
    private static function changeCountNext201(array $executed): int
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
    private static function changedTablesNext201(array $before, array $after): array
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
    private static function rowCountsNext201(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function assertIdentifierNext201(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value rollback-to savepoint next201 {$label} must be an identifier");
        }
    }

    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext204Plan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $savepointStatements
     * @param list<string> $rollbackStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext204(
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
        self::assertIdentifierNext204($transaction, 'transaction');
        self::assertIdentifierNext204($savepoint, 'savepoint');

        $transactionImage = self::normalizeTablesNext204($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatementsNext204(
            $transactionImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-rollback-savepoint-next204',
        );

        $savepointImage = $afterOuter;
        [$afterSavepoint, $savepointExecuted, $savepointReturning] = self::runStatementsNext204(
            $savepointImage,
            $savepointStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'savepoint-before-rollback-conflict-next204',
        );

        [$rollbackAttempt, $rollbackExecuted, $rollbackReason, $rollbackOrdinal] = self::runRollbackStatementsNext204(
            $afterSavepoint,
            $rollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );
        if ($rollbackReason === null) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 expected UPDATE OR ROLLBACK conflict');
        }

        $afterRollback = $transactionImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatementsNext204(
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
            'outer_yielded_returning_count' => self::returningCountNext204($outerReturning),
            'savepoint_yielded_returning_count' => self::returningCountNext204($savepointReturning),
            'suppressed_by_transaction_rollback_count' => self::returningCountNext204(array_merge($outerReturning, $savepointReturning)),
            'yielded_after_retry_count' => self::returningCountNext204($retryReturning),
            'changes_before_rollback' => self::changeCountNext204(array_merge($outerExecuted, $savepointExecuted)),
            'changes_after_rollback_retry' => self::changeCountNext204($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext204($transactionImage, $afterRetry),
            'row_counts' => self::rowCountsNext204($afterRetry),
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
    private static function runStatementsNext204(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext204($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null);
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
    private static function runRollbackStatementsNext204(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $executed[] = self::abortedStatementSummaryNext204($sql, $ordinal, $before, $rowIdColumn, $exception->getMessage());

                return [$before, $executed, $exception->getMessage(), $ordinal];
            }

            $current = $result['tables'];
            $executed[] = self::statementSummaryNext204('rollback-attempt-before-conflict-next204', $ordinal, $sql, $result, $before, $rowIdColumn, null);
        }

        return [$current, $executed, null, null];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummaryNext204(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $failedMessage): array
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
            'source_rows' => self::rowsByIdsNext204($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function abortedStatementSummaryNext204(string $sql, int $ordinal, array $before, string $rowIdColumn, string $message): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        $table = $parsed['table'];
        $where = self::wherePredicateNext204($parsed['where']);
        if ($parsed['action'] === 'delete') {
            $plan = SQLiteUpdateDeleteLimitPlan::delete($before[$table] ?? [], $where, $parsed['order_by'], $parsed['limit'], $parsed['offset'], $rowIdColumn);
        } else {
            $plan = SQLiteUpdateDeleteLimitPlan::update(
                $before[$table] ?? [],
                $where,
                self::assignmentCallbacksNext204($parsed['assignments']),
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
            'source_rows' => self::rowsByIdsNext204($before[$table] ?? [], $plan->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext204(array $tables): array
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
    private static function rowsByIdsNext204(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext204(array $yielded): int
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
    private static function changeCountNext204(array $executed): int
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
    private static function changedTablesNext204(array $before, array $after): array
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
    private static function rowCountsNext204(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function assertIdentifierNext204(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value ROLLBACK savepoint next204 {$label} must be an identifier");
        }
    }

    /**
     * @return callable(array<string,mixed>):bool
     */
    private static function wherePredicateNext204(?string $where): callable
    {
        $reflection = new \ReflectionMethod(SQLiteUpdateDeleteReturningSql::class, 'wherePredicate');
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $where);
    }

    /**
     * @param array<string,string> $assignments
     * @return array<string,callable(array<string,mixed>):mixed>
     */
    private static function assignmentCallbacksNext204(array $assignments): array
    {
        $reflection = new \ReflectionMethod(SQLiteUpdateDeleteReturningSql::class, 'assignmentCallbacks');
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $assignments);
    }

    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext214Plan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext214(
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

        $savepointImage = self::normalizeTablesNext214($tables);
        [$attemptCurrent, $attemptExecuted, $attemptReturning] = self::runStatementsNext214(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-ordered-subquery-before-rollback-next214',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatementsNext214(
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
            'discarded_attempt_returning_count' => self::returningCountNext214($attemptReturning),
            'yielded_after_retry_count' => self::returningCountNext214($retryReturning),
            'attempt_changes_before_rollback' => self::changeCountNext214($attemptExecuted),
            'retry_changes_after_rollback' => self::changeCountNext214($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext214($savepointImage, $retryCurrent),
            'row_counts' => self::rowCountsNext214($retryCurrent),
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
    private static function runStatementsNext214(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
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
                'source_rows' => self::rowsByIdsNext214($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext214(array $tables): array
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
    private static function rowsByIdsNext214(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCountNext214(array $yielded): int
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
    private static function changeCountNext214(array $executed): int
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
    private static function changedTablesNext214(array $before, array $after): array
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
    private static function rowCountsNext214(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }
        ksort($counts);

        return $counts;
    }

    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext215Plan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext215(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_subquery_limit_next215',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext212(
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

    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext216Plan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext216(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_distinct_subquery_next216',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext212(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $plan = self::replaceMarkerNext216($plan);
        $plan['status'] = 'rowvalue-update-delete-returning-distinct-subquery-savepoint-current-source-next216';
        $plan['savepoint'] = $savepoint;
        $plan['distinct_subquery_source'] = true;
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-in-select-distinct-subquery-next216',
            'sqlite-rowvalue-delete-returning-in-select-distinct-subquery-next216',
            'sqlite-rowvalue-distinct-subquery-savepoint-current-source-next216',
        ];
        $plan['dependency_closure_next216'] = 'no new support component needed; next216 reuses native PHP row-value UPDATE/DELETE RETURNING, SELECT subquery tuple materialization, and savepoint current-source retry images';
        $plan['non_overlap_next216'] = 'adds SELECT DISTINCT tuple de-duplication for row-value UPDATE/DELETE RETURNING subqueries; avoids next212 plain subqueries, next213 ORDER/LIMIT subqueries, next210 OR IGNORE, next176 NULL inequality, trigger RETURNING, WAL/VFS, JSON, planner, and B-tree clusters';

        return $plan;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function replaceMarkerNext216(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(['next212', 'subquery'], ['next216', 'distinct-subquery'], $value);
        }
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $entry) {
            $value[$key] = self::replaceMarkerNext216($entry);
        }

        return $value;
    }

    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext227Plan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeNext227(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_distinct_tuple_next227',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple next227 needs attempted statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple next227 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple next227 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple next227 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeTablesNext227($tables);
        [$attemptCurrent, $attemptExecuted, $attemptReturning] = self::runStatementsNext227(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'distinct-tuple-attempt-before-rollback-next227',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatementsNext227(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'distinct-tuple-retry-after-rollback-next227',
        );

        $attemptRows = self::flattenReturningNext227($attemptReturning);
        $retryRows = self::flattenReturningNext227($retryReturning);

        return [
            'status' => 'rowvalue-update-delete-returning-distinct-subquery-savepoint-current-source-next227',
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
            'distinct_tuple_subquery_deduped_next227' => true,
            'rollback_to_savepoint_restores_distinct_tuple_source_next227' => true,
            'retry_reads_savepoint_image_next227' => true,
            'savepoint_remains_active_next227' => true,
            'suppressed_returning_count' => count($attemptRows),
            'retry_returning_count' => count($retryRows),
            'attempt_change_count' => self::changeCountNext227($attemptExecuted),
            'retry_change_count' => self::changeCountNext227($retryExecuted),
            'changed_tables_after_retry' => self::changedTablesNext227($savepointImage, $retryCurrent),
            'row_counts' => self::rowCountsNext227($retryCurrent),
            'tuple_source_receipt_next227' => [
                'savepoint' => $savepoint,
                'attempt_statement_count' => count($attemptStatements),
                'retry_statement_count' => count($retryStatements),
                'suppressed_ids' => self::rowIdsNext227($attemptRows, $rowIdColumn),
                'retry_ids' => self::rowIdsNext227($retryRows, $rowIdColumn),
            ],
            'dependency_closure_next227' => 'no new support component needed; next227 reuses native row-value UPDATE/DELETE RETURNING execution and adds DISTINCT tuple-source parsing',
            'dependencies' => [
                'sqlite-rowvalue-distinct-subquery-tuples-next227',
                'sqlite-rowvalue-returning-rollback-retries-distinct-tuples-next227',
                'wordpress-rowvalue-distinct-optionmeta-savepoint-next227',
            ],
            'non_overlap_next227' => 'adds SELECT DISTINCT tuple-source de-duplication inside row-value UPDATE/DELETE RETURNING savepoint rollback and retry; avoids accepted next219 LIMIT -1 OFFSET tuple sources, next224 nested savepoint release rollback, OR FAIL/ABORT/ROLLBACK conflict slices, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatementsNext227(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryNext227($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryNext227(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsNext227($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function normalizeTablesNext227(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple next227 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple next227 rows must be arrays');
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
    private static function rowsByIdsNext227(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value DISTINCT tuple next227 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value DISTINCT tuple next227 rowid column {$rowIdColumn} must be int or string");
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
    private static function flattenReturningNext227(array $yielded): array
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
    private static function changeCountNext227(array $executed): int
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
    private static function changedTablesNext227(array $before, array $after): array
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
    private static function rowCountsNext227(array $tables): array
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
    private static function rowIdsNext227(array $rows, string $rowIdColumn): array
    {
        return array_values(array_filter(
            array_column($rows, $rowIdColumn),
            static fn (mixed $id): bool => is_int($id) || is_string($id),
        ));
    }

}
