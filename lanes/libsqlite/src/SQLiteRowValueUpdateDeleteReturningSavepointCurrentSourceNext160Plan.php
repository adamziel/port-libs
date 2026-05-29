<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext160Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeStatements
     * @param list<string> $protectedStatements
     * @param list<string> $afterStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
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
        $savepoint = self::identifier($savepoint, 'savepoint');
        if ($rollbackToProtectedOrdinal !== null && ($rollbackToProtectedOrdinal < 0 || $rollbackToProtectedOrdinal >= count($protectedStatements))) {
            throw new \InvalidArgumentException('SQLite row-value savepoint next160 rollback ordinal is outside protected statement list');
        }

        $transactionImage = self::normalizeTables($tables);
        $before = self::runStatements($beforeStatements, $transactionImage, $uniqueConstraints, $rowIdColumn, 'before');
        $savepointImage = $before['tables'];

        $protectedCurrent = $savepointImage;
        $protectedExecuted = [];
        $protectedYielded = [];
        foreach ($protectedStatements as $ordinal => $sql) {
            $result = self::runStatement($sql, $protectedCurrent, $uniqueConstraints, $rowIdColumn, 'protected', $ordinal);
            $protectedCurrent = $result['tables'];
            $protectedExecuted[] = $result['statement'];
            $protectedYielded[] = $result['yield'];
            if ($rollbackToProtectedOrdinal === $ordinal) {
                break;
            }
        }

        $rolledBack = $rollbackToProtectedOrdinal !== null;
        $afterStart = $rolledBack ? $savepointImage : $protectedCurrent;
        $after = self::runStatements($afterStatements, $afterStart, $uniqueConstraints, $rowIdColumn, 'after');
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
            'discarded_returning_count' => $rolledBack ? self::returningCount($protectedYielded) : 0,
            'changes' => self::changeCount(array_merge($before['statements'], $rolledBack ? [] : $protectedExecuted, $after['statements'])),
            'attempted_changes_before_rollback' => self::changeCount(array_merge($before['statements'], $protectedExecuted)),
            'source_cursor' => self::sourceCursor($before['statements'], $protectedExecuted, $after['statements'], $rolledBack),
            'row_counts' => self::rowCounts($final),
            'changed_tables' => self::changedTables($transactionImage, $final),
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
    private static function runStatements(array $statements, array $startTables, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $startTables;
        $executed = [];
        $yielded = [];
        foreach ($statements as $ordinal => $sql) {
            $result = self::runStatement($sql, $current, $uniqueConstraints, $rowIdColumn, $phase, $ordinal);
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
    private static function runStatement(string $sql, array $tables, array $uniqueConstraints, string $rowIdColumn, string $phase, int $ordinal): array
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
                'source_rows' => self::rowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function sourceCursor(array $before, array $protected, array $after, bool $rolledBack): array
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
    private static function returningCount(array $streams): int
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
    private static function changeCount(array $statements): int
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
    private static function normalizeTables(array $tables): array
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

    private static function identifier(string $value, string $label): string
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
    private static function rowsByIds(array $rows, array $ids, string $rowIdColumn): array
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
        $changed = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $name) {
            if (($before[$name] ?? null) !== ($after[$name] ?? null)) {
                $changed[] = $name;
            }
        }

        return $changed;
    }
}
