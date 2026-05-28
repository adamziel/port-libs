<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext203Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $ignoreStatements
     * @param list<string> $replaceStatements
     * @param list<string> $deleteStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
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

        $savepointImage = self::normalizeTables($tables);
        [$afterIgnore, $ignoreExecuted, $ignoreReturning] = self::runStatements(
            $savepointImage,
            $ignoreStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'ignore-conflict-current-source-next203',
        );
        self::assertConflictAction($ignoreExecuted, 'ignore');

        [$afterReplace, $replaceExecuted, $replaceReturning] = self::runStatements(
            $afterIgnore,
            $replaceStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'replace-conflict-current-source-next203',
        );
        self::assertConflictAction($replaceExecuted, 'replace');

        [$afterDelete, $deleteExecuted, $deleteReturning] = self::runStatements(
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
            'ignored_rows' => self::ignoredRows($ignoreExecuted),
            'replace_deleted_conflict_rows' => self::deletedConflictRows($replaceExecuted),
            'ignore_yielded_count' => self::returningCount($ignoreReturning),
            'replace_yielded_count' => self::returningCount($replaceReturning),
            'delete_yielded_count' => self::returningCount($deleteReturning),
            'ignore_conflict_count' => self::conflictCount($ignoreExecuted),
            'replace_conflict_count' => self::conflictCount($replaceExecuted),
            'changed_tables' => self::changedTables($savepointImage, $afterDelete),
            'row_counts' => self::rowCounts($afterDelete),
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
     * @param list<array<string,mixed>> $executed
     */
    private static function assertConflictAction(array $executed, string $expected): void
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
    private static function rowsByIds(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCount(array $yielded): int
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
    private static function ignoredRows(array $executed): array
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
    private static function deletedConflictRows(array $executed): array
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
    private static function conflictCount(array $executed): int
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
