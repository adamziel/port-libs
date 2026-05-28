<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext190Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $releaseStatements
     * @param list<string> $rollbackStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
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
        self::assertIdentifier($releaseSavepoint, 'release savepoint');
        self::assertIdentifier($rollbackSavepoint, 'rollback savepoint');

        $transactionImage = self::normalizeTables($tables);
        [$afterRelease, $releaseExecuted, $releaseReturning] = self::runStatements(
            $transactionImage,
            $releaseStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'release-savepoint',
        );

        $rollbackImage = $afterRelease;
        [$speculativeCurrent, $rollbackExecuted, $rollbackReturning] = self::runStatements(
            $rollbackImage,
            $rollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'rollback-savepoint-speculative',
        );

        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatements(
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
            'yielded_release_count' => self::returningCount($releaseReturning),
            'suppressed_by_rollback_count' => self::returningCount($rollbackReturning),
            'yielded_after_retry_count' => self::returningCount($retryReturning),
            'release_changes' => self::changeCount($releaseExecuted),
            'rollback_attempted_changes' => self::changeCount($rollbackExecuted),
            'retry_changes' => self::changeCount($retryExecuted),
            'changed_tables_after_retry' => self::changedTables($transactionImage, $afterRetry),
            'row_counts' => self::rowCounts($afterRetry),
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

    private static function assertIdentifier(string $value, string $label): void
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
    private static function rowsByIds(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCount(array $yielded): int
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
    private static function changeCount(array $executed): int
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
    private static function changedTables(array $before, array $after): array
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
    private static function rowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        ksort($counts);

        return $counts;
    }
}
