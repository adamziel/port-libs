<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext170Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
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
        self::identifier($savepoint, 'savepoint');

        $savepointImage = self::normalizeTables($tables);
        [$current, $executed, $yielded, $aborted] = self::runUntilAbort($savepointImage, $statements, $uniqueConstraints, $rowIdColumn);
        [$retryCurrent, $retryExecuted, $retryYielded] = self::runRetry($current, $retryStatements, $uniqueConstraints, $rowIdColumn);

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
            'yielded_returning_count_before_abort' => self::returningCount($yielded),
            'retry_returning_count' => self::returningCount($retryYielded),
            'changes_before_abort' => self::changeCount($executed),
            'changes_after_retry' => self::changeCount($retryExecuted),
            'savepoint_changed_tables_after_abort' => self::changedTables($savepointImage, $current),
            'changed_tables_after_retry' => self::changedTables($savepointImage, $retryCurrent),
            'row_counts_after_abort' => self::rowCounts($current),
            'row_counts_after_retry' => self::rowCounts($retryCurrent),
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
    private static function runUntilAbort(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
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
            $executed[] = self::statementSummary($ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function runRetry(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummary($ordinal, $sql, $result, $before, $rowIdColumn);
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
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTables(array $tables): array
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

    private static function identifier(string $value, string $label): void
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
    private static function rowsByIds(array $rows, array $ids, string $rowIdColumn): array
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
    private static function rowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }
}
