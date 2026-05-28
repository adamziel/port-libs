<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext162Plan
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
        string $savepoint = 'wp_options_rowvalue_fail_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next162 needs statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next162 needs unique constraints');
        }

        $savepointImage = self::normalizeTables($tables);
        $current = $savepointImage;
        $executed = [];
        $yielded = [];
        $failed = null;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints, true);
            $current = $result['tables'];
            $summary = self::statementSummary($ordinal, $sql, $result, $before, $rowIdColumn);
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
                    'partial_change_count' => self::statementChangeCount($summary),
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
                'changes' => self::changeCount($executed),
                'partial_fail' => null,
                'dependencies' => self::dependencies(),
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
            'discarded_returning_count' => self::returningCount($yielded),
            'attempted_changes_before_rollback' => self::changeCount($executed),
            'changes' => 0,
            'partial_fail' => $failed,
            'savepoint_changed_tables' => [],
            'row_counts' => self::rowCounts($savepointImage),
            'dependencies' => self::dependencies(),
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
    private static function statementSummary(int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'failed_conflict' => $result['failed_conflict'] ?? null,
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
            $changes += self::statementChangeCount($statement);
        }

        return $changes;
    }

    /**
     * @param array<string,mixed> $statement
     */
    private static function statementChangeCount(array $statement): int
    {
        return count($statement['returning_rows'] ?? []) + count($statement['deleted_conflict_rows'] ?? []);
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
     * @return list<string>
     */
    private static function dependencies(): array
    {
        return [
            'sqlite-update-or-fail-preserves-prior-rowvalue-changes-until-savepoint-rollback',
            'sqlite-rowvalue-returning-fail-stream-discarded-by-rollback-to-savepoint',
            'sqlite-delete-returning-after-partial-fail-is-not-run-before-rollback-to',
            'sqlite-savepoint-current-source-restored-after-rowvalue-or-fail',
        ];
    }
}
