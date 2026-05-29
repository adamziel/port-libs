<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueReturningFailSavepointCurrentSourceNextPlan
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
        string $savepoint = 'wp_options_fail_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite row-value RETURNING FAIL savepoint needs statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value RETURNING FAIL savepoint needs unique constraints');
        }

        $savepointImage = self::normalizeTables($tables);
        $current = $savepointImage;
        $executed = [];
        $yielded = [];
        $failedConflict = null;
        $failedOrdinal = null;

        foreach ($statements as $ordinal => $sql) {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints, true);
            $current = $result['tables'];
            $statement = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'table' => $result['table'],
                'selected_ids' => $result['plan']->selectedIds,
                'mutation_ids' => $result['plan']->mutationIds,
                'returning_rows' => $result['returning'],
                'ignored_rows' => $result['ignored_rows'],
                'deleted_conflict_rows' => $result['deleted_conflict_rows'],
                'conflicts' => $result['conflicts'],
                'failed_conflict' => $result['failed_conflict'] ?? null,
            ];
            $executed[] = $statement;
            $yielded[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];

            if (($result['failed_conflict'] ?? null) !== null) {
                $failedConflict = $result['failed_conflict'];
                $failedOrdinal = $ordinal;
                break;
            }
        }

        $failed = $failedConflict !== null;

        return [
            'savepoint' => $savepoint,
            'status' => $failed ? 'failed-savepoint-preserved' : 'released',
            'failed' => $failed,
            'failed_statement_ordinal' => $failedOrdinal,
            'failed_conflict' => $failedConflict,
            'savepoint_preserved' => $failed,
            'savepoint_image_tables' => $savepointImage,
            'current_source_tables' => $current,
            'executed_statements' => $executed,
            'yielded_returning' => $yielded,
            'changes' => self::changeCount($executed),
            'savepoint_changed_tables' => self::changedTables($savepointImage, $current),
            'row_counts' => self::rowCounts($current),
            'dependencies' => [
                'sqlite-update-or-fail-partial-rowvalue-returning',
                'sqlite-savepoint-preserves-fail-statement-changes',
                'sqlite-row-value-current-source-conflict-yield',
            ],
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
                throw new \InvalidArgumentException('SQLite row-value RETURNING FAIL savepoint tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value RETURNING FAIL savepoint rows must be arrays');
                }
            }
        }

        return $tables;
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
