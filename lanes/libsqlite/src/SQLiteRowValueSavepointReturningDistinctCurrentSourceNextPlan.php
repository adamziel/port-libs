<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueSavepointReturningDistinctCurrentSourceNextPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $releasedStatements
     * @param list<string> $rollbackStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string> $distinctColumns
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $releasedStatements,
        array $rollbackStatements,
        array $retryStatements,
        array $uniqueConstraints,
        array $distinctColumns,
        string $savepoint = 'app_settings_rowvalue_distinct_current_source',
        string $rowIdColumn = 'setting_id',
    ): array {
        if ($releasedStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT RETURNING current-source savepoint needs released statements');
        }
        if ($rollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT RETURNING current-source savepoint needs rollback statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT RETURNING current-source savepoint needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT RETURNING current-source savepoint needs unique constraints');
        }
        self::validateDistinctColumns($distinctColumns);

        $outerImage = self::normalizeTables($tables);
        $rowIdColumn = SQLiteRowIdColumn::resolveTables($outerImage, $rowIdColumn, $uniqueConstraints);
        [$releasedCurrent, $releasedExecuted, $releasedStreams] = self::runStatements(
            $outerImage,
            $releasedStatements,
            $uniqueConstraints,
            $distinctColumns,
            $rowIdColumn,
            'released',
        );

        $rollbackImage = $releasedCurrent;
        $attempted = $releasedCurrent;
        $rollbackExecuted = [];
        $rollbackStreams = [];
        $rollbackReason = null;
        $rollbackOrdinal = null;

        foreach ($rollbackStatements as $ordinal => $sql) {
            try {
                [$attempted, $statement, $stream] = self::runStatement(
                    $attempted,
                    $sql,
                    $uniqueConstraints,
                    $distinctColumns,
                    $rowIdColumn,
                    'rollback',
                    $ordinal,
                );
            } catch (\InvalidArgumentException $exception) {
                $rollbackReason = $exception->getMessage();
                $rollbackOrdinal = $ordinal;
                break;
            }

            $rollbackExecuted[] = $statement;
            $rollbackStreams[] = $stream;
        }

        $rolledBack = $rollbackReason !== null;
        $retrySource = $rolledBack ? $rollbackImage : $attempted;
        [$retryCurrent, $retryExecuted, $retryStreams] = self::runStatements(
            $retrySource,
            $retryStatements,
            $uniqueConstraints,
            $distinctColumns,
            $rowIdColumn,
            'retry',
        );

        $yielded = array_merge($releasedStreams, $rolledBack ? [] : $rollbackStreams, $retryStreams);
        $attemptedStreams = array_merge($releasedStreams, $rollbackStreams);

        return [
            'savepoint' => $savepoint,
            'status' => $rolledBack ? 'rolled-back-distinct-returning-retried' : 'released-distinct-returning-retried',
            'rolled_back' => $rolledBack,
            'rollback_reason' => $rollbackReason,
            'rollback_statement_ordinal' => $rollbackOrdinal,
            'distinct_columns' => array_values($distinctColumns),
            'outer_image_tables' => $outerImage,
            'released_current_source_tables' => $releasedCurrent,
            'rollback_image_tables' => $rollbackImage,
            'attempted_current_source_tables' => $attempted,
            'retry_source_tables' => $retrySource,
            'current_source_tables' => $retryCurrent,
            'released_executed_statements' => $releasedExecuted,
            'rollback_executed_statements' => $rollbackExecuted,
            'retry_executed_statements' => $retryExecuted,
            'released_returning' => $releasedStreams,
            'rollback_attempted_returning' => $rollbackStreams,
            'retry_returning' => $retryStreams,
            'yielded_returning' => $yielded,
            'attempted_returning_before_rollback' => $attemptedStreams,
            'yielded_distinct_rows' => self::flattenDistinctRows($yielded),
            'attempted_distinct_rows' => self::flattenDistinctRows($attemptedStreams),
            'discarded_distinct_rows' => $rolledBack ? self::flattenDistinctRows($rollbackStreams) : [],
            'duplicate_returning_rows' => self::flattenDuplicateRows(array_merge($releasedStreams, $rollbackStreams, $retryStreams)),
            'yielded_distinct_keys' => self::flattenDistinctKeys($yielded),
            'attempted_distinct_keys' => self::flattenDistinctKeys($attemptedStreams),
            'changes' => self::changeCount(array_merge($releasedExecuted, $rolledBack ? [] : $rollbackExecuted, $retryExecuted)),
            'attempted_changes_before_rollback' => self::changeCount(array_merge($releasedExecuted, $rollbackExecuted)),
            'row_counts' => self::rowCounts($retryCurrent),
            'dependencies' => [
                'sqlite-row-value-is-distinct-from-current-source',
                'sqlite-returning-distinct-savepoint-stream',
                'sqlite-rollback-to-savepoint-retries-distinct-returning',
            ],
            'non_overlap' => 'covers row-value IS DISTINCT FROM / IS NOT DISTINCT FROM RETURNING stream de-duplication across savepoint rollback and retry; avoids accepted conflict-retry and DELETE-only rollback surfaces',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string> $distinctColumns
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array<string,mixed>>}
     */
    private static function runStatements(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        array $distinctColumns,
        string $rowIdColumn,
        string $phase,
    ): array {
        $current = $tables;
        $executed = [];
        $streams = [];
        foreach ($statements as $ordinal => $sql) {
            [$current, $statement, $stream] = self::runStatement(
                $current,
                $sql,
                $uniqueConstraints,
                $distinctColumns,
                $rowIdColumn,
                $phase,
                $ordinal,
            );
            $executed[] = $statement;
            $streams[] = $stream;
        }

        return [$current, $executed, $streams];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>> $uniqueConstraints
     * @param list<string> $distinctColumns
     * @return array{0:array<string,list<array<string,mixed>>>,1:array<string,mixed>,2:array<string,mixed>}
     */
    private static function runStatement(
        array $tables,
        string $sql,
        array $uniqueConstraints,
        array $distinctColumns,
        string $rowIdColumn,
        string $phase,
        int $ordinal,
    ): array {
        $before = $tables;
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints);
        $stream = self::distinctStream($result['returning'], $distinctColumns, $phase, $ordinal, $result['action']);
        $statement = [
            'phase' => $phase,
            'ordinal' => $ordinal,
            'action' => $result['action'],
            'table' => $result['table'],
            'where' => $parsed['where'],
            'returning' => $parsed['returning'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'returning_rows' => $result['returning'],
            'distinct_rows' => $stream['distinct_rows'],
            'duplicate_rows' => $stream['duplicate_rows'],
            'current_source_before_ids' => array_column($before[$result['table']] ?? [], $rowIdColumn),
            'next_source_after_ids' => array_column($result['tables'][$result['table']] ?? [], $rowIdColumn),
        ];

        return [$result['tables'], $statement, $stream];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $distinctColumns
     * @return array<string,mixed>
     */
    private static function distinctStream(array $rows, array $distinctColumns, string $phase, int $ordinal, string $action): array
    {
        $seen = [];
        $distinct = [];
        $duplicates = [];
        foreach ($rows as $row) {
            $key = self::distinctKey($row, $distinctColumns);
            $entry = ['key' => $key, 'row' => $row];
            if (isset($seen[$key])) {
                $duplicates[] = $entry;
                continue;
            }
            $seen[$key] = true;
            $distinct[] = $entry;
        }

        return [
            'phase' => $phase,
            'ordinal' => $ordinal,
            'action' => $action,
            'rows' => $rows,
            'distinct_rows' => $distinct,
            'duplicate_rows' => $duplicates,
            'distinct_count' => count($distinct),
            'duplicate_count' => count($duplicates),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     */
    private static function distinctKey(array $row, array $columns): string
    {
        $parts = [];
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite row-value DISTINCT RETURNING current-source savepoint column {$column} is missing");
            }
            $value = $row[$column];
            $parts[] = $value === null ? 'null:' : get_debug_type($value) . ':' . (string) $value;
        }

        return implode('|', $parts);
    }

    /**
     * @param list<array<string,mixed>> $streams
     * @return list<array{key:string,row:array<string,mixed>}>
     */
    private static function flattenDistinctRows(array $streams): array
    {
        $rows = [];
        foreach ($streams as $stream) {
            foreach ($stream['distinct_rows'] as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $streams
     * @return list<array{key:string,row:array<string,mixed>}>
     */
    private static function flattenDuplicateRows(array $streams): array
    {
        $rows = [];
        foreach ($streams as $stream) {
            foreach ($stream['duplicate_rows'] as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $streams
     * @return list<string>
     */
    private static function flattenDistinctKeys(array $streams): array
    {
        return array_map(static fn (array $entry): string => $entry['key'], self::flattenDistinctRows($streams));
    }

    /**
     * @param list<array<string,mixed>> $executed
     */
    private static function changeCount(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['mutation_ids']);
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
     * @param list<string> $columns
     */
    private static function validateDistinctColumns(array $columns): void
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT RETURNING current-source savepoint needs distinct columns');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite row-value DISTINCT RETURNING current-source savepoint distinct columns must be strings');
            }
        }
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value DISTINCT RETURNING current-source savepoint tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value DISTINCT RETURNING current-source savepoint rows must be arrays');
                }
            }
        }

        return $tables;
    }
}
