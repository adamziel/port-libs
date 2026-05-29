<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateReturningConflictCurrentSourceNextPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        string $sql,
        array $uniqueConstraints,
        string $rowIdColumn = 'option_id',
    ): array {
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE RETURNING current-source conflict needs unique constraints');
        }

        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints);
        if ($result['action'] !== 'update') {
            throw new \InvalidArgumentException('SQLite row-value UPDATE RETURNING current-source conflict needs UPDATE SQL');
        }

        $table = $result['table'];
        $inputRows = $result['plan']->inputRows;
        $nextRows = $result['tables'][$table];
        $inputIds = self::ids($inputRows, $rowIdColumn);
        $nextIds = self::ids($nextRows, $rowIdColumn);
        $returningIds = self::ids($result['returning'], $rowIdColumn);
        $deletedConflictIds = self::ids($result['deleted_conflict_rows'], $rowIdColumn);
        $ignoredIds = self::ids($result['ignored_rows'], $rowIdColumn);

        $suppressedSelectedIds = [];
        foreach ($result['plan']->selectedIds as $rowId) {
            if (!in_array($rowId, $returningIds, true) && !in_array($rowId, $ignoredIds, true)) {
                $suppressedSelectedIds[] = $rowId;
            }
        }

        return [
            'status' => 'rowvalue-update-returning-conflict-current-source-next137-ready',
            'action' => $result['action'],
            'table' => $table,
            'conflict_action' => $result['conflict_action'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'returning' => $result['returning'],
            'returning_ids' => $returningIds,
            'ignored_ids' => $ignoredIds,
            'deleted_conflict_ids' => $deletedConflictIds,
            'suppressed_selected_ids' => $suppressedSelectedIds,
            'conflicts' => $result['conflicts'],
            'input_row_ids' => $inputIds,
            'current_source_row_ids' => $nextIds,
            'current_source_tables' => $result['tables'],
            'changed_row_count' => count($returningIds),
            'deleted_conflict_count' => count($deletedConflictIds),
            'current_source_changed' => $inputIds !== $nextIds || $deletedConflictIds !== [] || $ignoredIds !== [],
            'dependencies' => [
                'sqlite-update-or-conflict-returning',
                'sqlite-row-value-current-source-update',
                'sqlite-row-value-conflict-selected-row-admission',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function ids(array $rows, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value UPDATE RETURNING current-source conflict row is missing {$rowIdColumn}");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value UPDATE RETURNING current-source conflict rowid {$rowIdColumn} must be int or string");
            }
            $ids[] = $id;
        }

        return $ids;
    }
}
