<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueNestedSavepointReturningPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $innerStatements
     * @param list<string> $outerStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $innerStatements,
        array $outerStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_outer_import',
        string $innerSavepoint = 'wp_inner_plugin',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($innerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint needs inner statements');
        }
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint needs outer statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint needs unique constraints');
        }
        self::identifier($outerSavepoint, 'outer savepoint');
        self::identifier($innerSavepoint, 'inner savepoint');
        if ($outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint savepoint names must differ');
        }

        $outerImage = self::normalizeTables($tables);
        [$innerReleased, $innerExecuted, $innerReturning] = self::runStatements(
            $outerImage,
            $innerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-before-release',
        );
        [$outerAttemptCurrent, $outerExecuted, $outerReturning] = self::runStatements(
            $innerReleased,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-after-inner-release-before-rollback',
        );

        $rollbackToOuter = $outerImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatements(
            $rollbackToOuter,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'after-outer-rollback-to',
        );

        $discardedReturning = array_merge($innerReturning, $outerReturning);
        $attempted = array_merge($innerExecuted, $outerExecuted);

        return [
            'status' => 'nested-release-rolled-back-retried-current-source',
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'inner_released_into_outer' => true,
            'rolled_back_to_outer_savepoint' => true,
            'inner_savepoint_no_longer_active_after_release' => true,
            'outer_savepoint_preserved_after_rollback_to' => true,
            'released_outer_after_retry' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'inner_release_current_source_tables' => $innerReleased,
            'outer_attempt_current_source_tables' => $outerAttemptCurrent,
            'rollback_to_outer_current_source_tables' => $rollbackToOuter,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'inner_statements' => $innerExecuted,
            'outer_statements' => $outerExecuted,
            'retry_statements' => $retryExecuted,
            'inner_released_returning' => $innerReturning,
            'outer_attempt_returning' => $outerReturning,
            'discarded_by_outer_rollback_returning' => $discardedReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'inner_released_returning_count' => self::returningCount($innerReturning),
            'outer_attempt_returning_count' => self::returningCount($outerReturning),
            'discarded_by_outer_rollback_count' => self::returningCount($discardedReturning),
            'yielded_after_retry_count' => self::returningCount($retryReturning),
            'attempted_changes_before_outer_rollback' => self::changeCount($attempted),
            'changes_after_retry_release' => self::changeCount($retryExecuted),
            'row_counts' => self::rowCounts($retryCurrent),
            'changed_tables_after_retry' => self::changedTables($outerImage, $retryCurrent),
            'dependencies' => [
                'sqlite-release-nested-savepoint-merges-rowvalue-returning',
                'sqlite-rollback-to-outer-discards-released-inner-returning',
                'sqlite-rowvalue-update-delete-returning-retry-after-nested-rollback-current-source',
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
                throw new \InvalidArgumentException('SQLite row-value nested savepoint tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value nested savepoint rows must be arrays');
                }
            }
        }

        return $tables;
    }

    private static function identifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value nested savepoint {$label} is malformed");
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
                throw new \InvalidArgumentException("SQLite row-value nested savepoint rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value nested savepoint rowid column {$rowIdColumn} must be int or string");
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
    private static function returningCount(array $streams): int
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
