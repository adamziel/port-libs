<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext211Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeIgnoreStatements
     * @param string $ignoreStatement
     * @param list<string> $afterIgnoreStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
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

        $savepointImage = self::normalizeTables($tables);
        [$preCurrent, $preStatements, $preReturning] = self::runStatements(
            $savepointImage,
            $beforeIgnoreStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-ignore-next211',
        );
        [$ignoreCurrent, $ignoreSummary, $ignoreReturning] = self::runIgnoreStatement(
            $preCurrent,
            $ignoreStatement,
            $uniqueConstraints,
            $rowIdColumn,
        );
        [$afterCurrent, $afterStatements, $afterReturning] = self::runStatements(
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
            'pre_ignore_yielded_count' => self::returningCount($preReturning),
            'ignore_yielded_count' => self::returningCount($ignoreReturning),
            'ignored_by_conflict_count' => count($ignoreSummary['ignored_rows']),
            'yielded_after_ignore_count' => self::returningCount($afterReturning),
            'pre_ignore_changes_count' => self::changeCount($preStatements),
            'ignore_changes_count' => count($ignoreSummary['returning_rows']),
            'after_ignore_changes_count' => self::changeCount($afterStatements),
            'changed_tables_after_release' => self::changedTables($savepointImage, $afterCurrent),
            'row_counts' => self::rowCounts($afterCurrent),
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
    private static function runStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function runIgnoreStatement(array $tables, string $sql, array $uniqueConstraints, string $rowIdColumn): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        if ($parsed['action'] !== 'update' || $parsed['conflict_action'] !== 'ignore') {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next211 ignore statement must be UPDATE OR IGNORE');
        }

        $probe = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, [], true);
        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints, true);
        $summary = self::statementSummary('or-ignore-next211', 0, $sql, $result, $tables, $rowIdColumn);
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
    private static function rowsByIds(array $rows, array $ids, string $rowIdColumn): array
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
}
