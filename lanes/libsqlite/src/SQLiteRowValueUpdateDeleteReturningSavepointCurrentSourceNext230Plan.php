<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext230Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $preStatements
     * @param list<string> $innerStatements
     * @param list<string> $afterReleaseStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
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
        self::assertIdentifier($outerSavepoint, 'outer savepoint');
        self::assertIdentifier($innerSavepoint, 'inner savepoint');
        if ($outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next230 savepoint names must differ');
        }

        $initial = self::normalizeTables($tables);
        [$preCurrent, $preSummaries, $preReturning] = self::runStatements(
            $initial,
            $preStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'pre-outer-savepoint-next230',
        );

        $outerImage = $preCurrent;
        [$innerCurrent, $innerSummaries, $innerReturning] = self::runStatements(
            $outerImage,
            $innerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-before-release-next230',
        );

        $innerReleaseImage = $innerCurrent;
        [$afterReleaseCurrent, $afterReleaseSummaries, $afterReleaseReturning] = self::runStatements(
            $innerReleaseImage,
            $afterReleaseStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'after-inner-release-next230',
        );

        $rollbackCurrent = $outerImage;
        [$retryCurrent, $retrySummaries, $retryReturning] = self::runStatements(
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
            'pre_returning_count' => self::returningCount($preReturning),
            'discarded_inner_release_returning_count' => self::returningCount($innerReturning) + self::returningCount($afterReleaseReturning),
            'yielded_after_retry_count' => self::returningCount($retryReturning),
            'changes_before_outer_rollback' => self::changeCount($innerSummaries) + self::changeCount($afterReleaseSummaries),
            'retry_changes_after_outer_rollback' => self::changeCount($retrySummaries),
            'changed_tables_after_retry' => self::changedTables($initial, $retryCurrent),
            'row_counts' => self::rowCounts($retryCurrent),
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
    private static function runStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $summaries = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $summaries[] = self::statementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function rowsByIds(array $rows, array $ids, string $rowIdColumn): array
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
    private static function returningCount(array $yielded): int
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
    private static function changeCount(array $summaries): int
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
    private static function changedTables(array $before, array $after): array
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
    private static function rowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }
        ksort($counts);

        return $counts;
    }

    private static function assertIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value nested savepoint next230 {$label} must be an identifier");
        }
    }
}
