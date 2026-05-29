<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $releasedStatements
     * @param list<string> $rollbackStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $releasedStatements,
        array $rollbackStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_delete_outer_next144',
        string $releasedSavepoint = 'wp_options_delete_released_next144',
        string $rollbackSavepoint = 'wp_options_delete_rollback_next144',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($releasedStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value DELETE RETURNING next144 needs released statements');
        }
        if ($rollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value DELETE RETURNING next144 needs rollback statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value DELETE RETURNING next144 needs unique constraints');
        }

        $outerImage = self::normalizeTables($tables);
        $current = $outerImage;

        [$current, $releasedExecuted, $releasedStreams, $releasedDeleted] = self::runDeleteStatements(
            $current,
            $releasedStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'released',
        );

        $rollbackImage = $current;
        $attempted = $current;
        $rollbackExecuted = [];
        $rollbackAttemptedStreams = [];
        $rollbackDeleted = [];
        $rollbackReason = null;
        $rollbackOrdinal = null;

        foreach ($rollbackStatements as $ordinal => $sql) {
            try {
                [$attempted, $statement, $stream, $deletedRows] = self::runDeleteStatement(
                    $attempted,
                    $sql,
                    $uniqueConstraints,
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
            $rollbackAttemptedStreams[] = $stream;
            foreach ($deletedRows as $row) {
                $rollbackDeleted[] = ['phase' => 'rollback', 'ordinal' => $ordinal, 'row' => $row];
            }
        }

        $rolledBack = $rollbackReason !== null;
        $current = $rolledBack ? $rollbackImage : $attempted;
        $allExecuted = array_merge($releasedExecuted, $rollbackExecuted);
        $allDeleted = array_merge($releasedDeleted, $rolledBack ? [] : $rollbackDeleted);
        $yielded = $rolledBack ? $releasedStreams : array_merge($releasedStreams, $rollbackAttemptedStreams);

        return [
            'outer_savepoint' => $outerSavepoint,
            'released_savepoint' => $releasedSavepoint,
            'rollback_savepoint' => $rollbackSavepoint,
            'status' => $rolledBack ? 'rollback-savepoint-rolled-back' : 'released',
            'rolled_back' => $rolledBack,
            'rollback_reason' => $rollbackReason,
            'rollback_statement_ordinal' => $rollbackOrdinal,
            'outer_image_tables' => $outerImage,
            'released_source_tables' => $rollbackImage,
            'rollback_image_tables' => $rollbackImage,
            'current_source_tables' => $current,
            'next_source_tables' => $attempted,
            'released_executed_statements' => $releasedExecuted,
            'rollback_executed_statements' => $rollbackExecuted,
            'executed_statements' => $allExecuted,
            'released_returning' => $releasedStreams,
            'rollback_attempted_returning' => $rollbackAttemptedStreams,
            'yielded_returning' => $yielded,
            'released_deleted_rows' => $releasedDeleted,
            'rollback_deleted_rows' => $rolledBack ? [] : $rollbackDeleted,
            'attempted_rollback_deleted_rows' => $rollbackDeleted,
            'deleted_rows' => $allDeleted,
            'released_changes' => self::changeCount($releasedExecuted),
            'rollback_attempted_changes' => self::changeCount($rollbackExecuted),
            'changes' => self::changeCount($releasedExecuted) + ($rolledBack ? 0 : self::changeCount($rollbackExecuted)),
            'attempted_changes' => self::changeCount($allExecuted),
            'changed_tables' => self::changedTables($outerImage, $current),
            'attempted_changed_tables' => self::changedTables($outerImage, $attempted),
            'dependencies' => [
                'sqlite-delete-returning-row-value-current-source-next144',
                'sqlite-delete-returning-yields-before-savepoint-rollback-next144',
                'sqlite-released-savepoint-delete-survives-inner-rollback-next144',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array<string,mixed>>,3:list<array{phase:string,ordinal:int,row:array<string,mixed>}>}
     */
    private static function runDeleteStatements(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        string $rowIdColumn,
        string $phase,
    ): array {
        $current = $tables;
        $executed = [];
        $streams = [];
        $deleted = [];

        foreach ($statements as $ordinal => $sql) {
            [$current, $statement, $stream, $deletedRows] = self::runDeleteStatement(
                $current,
                $sql,
                $uniqueConstraints,
                $rowIdColumn,
                $phase,
                $ordinal,
            );
            $executed[] = $statement;
            $streams[] = $stream;
            foreach ($deletedRows as $row) {
                $deleted[] = ['phase' => $phase, 'ordinal' => $ordinal, 'row' => $row];
            }
        }

        return [$current, $executed, $streams, $deleted];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:array<string,mixed>,2:array<string,mixed>,3:list<array<string,mixed>>}
     */
    private static function runDeleteStatement(
        array $tables,
        string $sql,
        array $uniqueConstraints,
        string $rowIdColumn,
        string $phase,
        int $ordinal,
    ): array {
        $before = $tables;
        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints);
        if ($result['action'] !== 'delete') {
            throw new \InvalidArgumentException('SQLite row-value DELETE RETURNING next144 only accepts DELETE statements');
        }

        $deletedRows = self::rowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn);
        $statement = [
            'phase' => $phase,
            'ordinal' => $ordinal,
            'action' => $result['action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'selected_rows' => $deletedRows,
            'returning_rows' => $result['returning'],
            'current_source_before_ids' => array_column($before[$result['table']] ?? [], $rowIdColumn),
            'next_source_after_ids' => array_column($result['tables'][$result['table']] ?? [], $rowIdColumn),
        ];
        $stream = [
            'phase' => $phase,
            'ordinal' => $ordinal,
            'action' => 'delete',
            'rows' => $result['returning'],
        ];

        return [$result['tables'], $statement, $stream, $deletedRows];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value DELETE RETURNING next144 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value DELETE RETURNING next144 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value DELETE RETURNING next144 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value DELETE RETURNING next144 rowid column {$rowIdColumn} must be int or string");
            }
            if (isset($wanted[(string) $id])) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * @param list<array<string,mixed>> $executed
     */
    private static function changeCount(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['mutation_ids'] ?? []);
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
