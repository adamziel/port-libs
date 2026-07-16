<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueConflictUpsertSavepointCurrentSourceNextPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string> $conflictKeyColumns
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        array $conflictKeyColumns,
        string $savepoint = 'app_settings_rowvalue_conflict',
        string $rowIdColumn = 'setting_id',
    ): array {
        self::validateColumns($conflictKeyColumns, 'conflict key');
        if (trim($savepoint) === '') {
            throw new \InvalidArgumentException('SQLite row-value conflict UPSERT savepoint name must not be empty');
        }
        $rowIdColumn = SQLiteRowIdColumn::resolveTables($tables, $rowIdColumn, $uniqueConstraints);

        $plan = SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute(
            $tables,
            $statements,
            $uniqueConstraints,
            trim($savepoint),
            $rowIdColumn,
        );

        $table = self::firstTableName($tables);
        $beforeRows = $plan['savepoint_image_tables'][$table] ?? [];
        $attemptRows = $plan['next_source_tables'][$table] ?? [];
        $currentRows = $plan['current_source_tables'][$table] ?? [];

        $movements = self::movementRows($beforeRows, $attemptRows, $conflictKeyColumns, $rowIdColumn);
        $currentMovements = self::movementRows($beforeRows, $currentRows, $conflictKeyColumns, $rowIdColumn);
        $matchedMovedKeys = self::matchedMovedKeys($plan['executed_statements'], $movements, $conflictKeyColumns);

        return [
            'status' => $plan['rolled_back']
                ? 'rowvalue-conflict-upsert-savepoint-rolled-back-current-source-next136'
                : 'rowvalue-conflict-upsert-savepoint-released-current-source-next136',
            'savepoint' => $plan['savepoint'],
            'rolled_back' => $plan['rolled_back'],
            'rollback_reason' => $plan['rollback_reason'],
            'rollback_statement_ordinal' => $plan['rollback_statement_ordinal'],
            'table' => $table,
            'conflict_key_columns' => array_values($conflictKeyColumns),
            'executed_statements' => $plan['executed_statements'],
            'statement_actions' => array_column($plan['executed_statements'], 'action'),
            'statement_conflict_keys' => array_map(
                static fn (array $statement): ?string => $statement['conflict']['key'] ?? null,
                $plan['executed_statements'],
            ),
            'moved_conflict_keys' => $movements,
            'current_source_moved_conflict_keys' => $currentMovements,
            'matched_moved_conflict_keys' => $matchedMovedKeys,
            'yielded_returning' => $plan['yielded_returning'],
            'attempted_returning' => $plan['attempted_returning'],
            'savepoint_image_tables' => $plan['savepoint_image_tables'],
            'current_source_tables' => $plan['current_source_tables'],
            'next_source_tables' => $plan['next_source_tables'],
            'changes' => $plan['changes'],
            'attempted_changes' => $plan['attempted_changes'],
            'dependencies' => array_values(array_unique(array_merge(
                $plan['dependencies'],
                [
                    'sqlite-row-value-conflict-key-current-source-upsert',
                    'sqlite-upsert-savepoint-rollback-restores-moved-conflict-key',
                ],
            ))),
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     */
    private static function firstTableName(array $tables): string
    {
        foreach ($tables as $name => $_rows) {
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        throw new \InvalidArgumentException('SQLite row-value conflict UPSERT needs a named table');
    }

    /**
     * @param list<array<string,mixed>> $beforeRows
     * @param list<array<string,mixed>> $afterRows
     * @param list<string> $columns
     * @return list<array{row_id:int|string,before_key:string|null,after_key:string|null,before_values:array<string,mixed>,after_values:array<string,mixed>}>
     */
    private static function movementRows(array $beforeRows, array $afterRows, array $columns, string $rowIdColumn): array
    {
        $beforeById = self::indexByRowId($beforeRows, $rowIdColumn);
        $afterById = self::indexByRowId($afterRows, $rowIdColumn);
        $movements = [];

        foreach ($beforeById as $rowId => $before) {
            if (!isset($afterById[$rowId])) {
                continue;
            }
            $after = $afterById[$rowId];
            $beforeKey = self::key($before, $columns);
            $afterKey = self::key($after, $columns);
            if ($beforeKey === $afterKey) {
                continue;
            }

            $movements[] = [
                'row_id' => self::rowIdValue($before, $rowIdColumn),
                'before_key' => $beforeKey,
                'after_key' => $afterKey,
                'before_values' => self::project($before, $columns),
                'after_values' => self::project($after, $columns),
            ];
        }

        return $movements;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private static function indexByRowId(array $rows, string $rowIdColumn): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value conflict UPSERT row id column {$rowIdColumn} is missing");
            }
            $indexed[(string) self::rowIdValue($row, $rowIdColumn)] = $row;
        }

        return $indexed;
    }

    /**
     * @param list<array<string,mixed>> $statements
     * @param list<array{row_id:int|string,before_key:string|null,after_key:string|null,before_values:array<string,mixed>,after_values:array<string,mixed>}> $movements
     * @param list<string> $columns
     * @return list<array{ordinal:int,row_id:int|string,key:string}>
     */
    private static function matchedMovedKeys(array $statements, array $movements, array $columns): array
    {
        $movedKeys = [];
        foreach ($movements as $movement) {
            if ($movement['after_key'] !== null) {
                $movedKeys[$movement['after_key']] = $movement['row_id'];
            }
        }

        $matched = [];
        foreach ($statements as $statement) {
            if (($statement['action'] ?? null) !== 'update') {
                continue;
            }
            if (($statement['conflict_target'] ?? []) !== $columns) {
                continue;
            }
            $key = $statement['conflict']['key'] ?? null;
            if (is_string($key) && isset($movedKeys[$key])) {
                $matched[] = [
                    'ordinal' => (int) $statement['ordinal'],
                    'row_id' => $movedKeys[$key],
                    'key' => $key,
                ];
            }
        }

        return $matched;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     */
    private static function key(array $row, array $columns): ?string
    {
        $parts = [];
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite row-value conflict UPSERT column {$column} is missing");
            }
            if ($row[$column] === null) {
                return null;
            }
            $parts[] = is_bool($row[$column]) ? ($row[$column] ? '1' : '0') : (string) $row[$column];
        }

        return implode('|', $parts);
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function project(array $row, array $columns): array
    {
        $projected = [];
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite row-value conflict UPSERT column {$column} is missing");
            }
            $projected[$column] = $row[$column];
        }

        return $projected;
    }

    /**
     * @param list<string> $columns
     */
    private static function validateColumns(array $columns, string $label): void
    {
        if ($columns === []) {
            throw new \InvalidArgumentException("SQLite row-value conflict UPSERT {$label} columns are required");
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException("SQLite row-value conflict UPSERT {$label} columns must be non-empty strings");
            }
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowIdValue(array $row, string $rowIdColumn): int|string
    {
        $rowId = $row[$rowIdColumn];
        if (!is_int($rowId) && !is_string($rowId)) {
            throw new \InvalidArgumentException("SQLite row-value conflict UPSERT row id column {$rowIdColumn} must be scalar");
        }

        return $rowId;
    }
}
