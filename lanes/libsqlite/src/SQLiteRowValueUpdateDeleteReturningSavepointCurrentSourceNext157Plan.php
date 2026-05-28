<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext157Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerStatements
     * @param list<string> $afterRollbackStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
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
        $outerSavepoint = self::identifier($outerSavepoint, 'outer savepoint');
        $innerSavepoint = self::identifier($innerSavepoint, 'inner savepoint');
        if (strcasecmp($outerSavepoint, $innerSavepoint) === 0) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next157 needs distinct savepoint names');
        }

        $transactionImage = self::normalizeTables($tables);
        $outerImage = $transactionImage;

        $outer = self::runStatements($outerStatements, $outerImage, $uniqueConstraints, $rowIdColumn);
        if ($outer['failed_statement'] !== null) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next157 outer statement failed: ' . $outer['failed_statement']['reason']);
        }

        $innerImage = $outer['tables'];
        $inner = self::runStatements($innerStatements, $innerImage, $uniqueConstraints, $rowIdColumn);
        $rolledBackInner = $inner['executed_statements'] !== [] || $inner['failed_statement'] !== null;
        $afterRollbackStart = $rolledBackInner ? $innerImage : $inner['tables'];

        $after = self::runStatements($afterRollbackStatements, $afterRollbackStart, $uniqueConstraints, $rowIdColumn);
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
            'discarded_inner_returning_count' => self::returningCount($inner['yielded_returning']),
            'outer_changes' => self::changeCount($outer['executed_statements']),
            'inner_attempted_changes' => self::changeCount($inner['executed_statements']),
            'after_rollback_changes' => self::changeCount($after['executed_statements']),
            'changes' => self::changeCount($outer['executed_statements']) + self::changeCount($after['executed_statements']),
            'rolled_back_inner_savepoint' => $rolledBackInner,
            'outer_savepoint_preserved' => false,
            'inner_savepoint_preserved' => false,
            'failed_inner_statement' => $inner['failed_statement'],
            'row_counts' => self::rowCounts($final),
            'changed_tables' => self::changedTables($transactionImage, $final),
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
    private static function runStatements(array $statements, array $startTables, array $uniqueConstraints, string $rowIdColumn): array
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
            $executed[] = self::statementSummary($ordinal, $result, $before, $rowIdColumn);
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
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTables(array $tables): array
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

    private static function identifier(string $value, string $label): string
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
    private static function rowsByIds(array $rows, array $ids, string $rowIdColumn): array
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
