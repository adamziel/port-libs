<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class PreviewMergeConflictsTable
{
    public const DIFF_ADDED = 'added';
    public const DIFF_MODIFIED = 'modified';
    public const DIFF_REMOVED = 'removed';

    /**
     * Project rows returned by upstream `dolt_preview_merge_conflicts_summary`.
     *
     * When a table has schema conflicts, upstream returns `NULL` for
     * `num_data_conflicts` because the row-level preview cannot be calculated.
     *
     * @param list<string|array<string,mixed>> $dataConflictTables
     * @param list<string|array<string,mixed>> $schemaConflictTables
     * @return list<array{table:string, num_data_conflicts:int|null, num_schema_conflicts:int}>
     */
    public function summaryRows(array $dataConflictTables = [], array $schemaConflictTables = []): array
    {
        $stats = [];
        $order = [];

        foreach ($dataConflictTables as $item) {
            $conflict = $this->normalizeSummaryItem($item, 'data');
            if ($conflict['count'] === 0) {
                continue;
            }
            $this->markSummary($stats, $order, $conflict['table'], 'data', $conflict['count']);
        }

        foreach ($schemaConflictTables as $item) {
            $conflict = $this->normalizeSummaryItem($item, 'schema');
            if ($conflict['count'] === 0) {
                continue;
            }
            $this->markSummary($stats, $order, $conflict['table'], 'schema', $conflict['count']);
        }

        $rows = [];
        foreach ($order as $tableName) {
            $row = $stats[$tableName];
            $rows[] = [
                'table' => $tableName,
                'num_data_conflicts' => $row['schema'] > 0 ? null : $row['data'],
                'num_schema_conflicts' => $row['schema'],
            ];
        }

        return $rows;
    }

    /**
     * Project rows returned by upstream `dolt_preview_merge_conflicts`.
     *
     * This covers keyed table data conflicts: divergent modify/modify,
     * add/add, and delete/modify rows. Schema conflict handling is represented
     * by the upstream error boundary, because the data preview cannot be
     * calculated when `dolt_preview_merge_conflicts_summary` reports schema
     * conflicts for the table.
     *
     * @param list<array<string, scalar|null>> $baseRows
     * @param list<array<string, scalar|null>> $ourRows
     * @param list<array<string, scalar|null>> $theirRows
     * @param non-empty-string|list<non-empty-string> $primaryKey
     * @param list<non-empty-string>|null $columns
     * @return list<array<string, scalar|null>>
     */
    public function conflictRows(
        array $baseRows,
        array $ourRows,
        array $theirRows,
        string|array $primaryKey,
        ?array $columns = null,
        string $theirRootish = 'THEIRS',
        int $schemaConflictCount = 0,
    ): array {
        if ($schemaConflictCount < 0) {
            throw new \InvalidArgumentException('Dolt preview schema conflict count must be a non-negative integer.');
        }
        if ($schemaConflictCount > 0) {
            throw new \InvalidArgumentException($this->schemaConflictError($schemaConflictCount));
        }
        if ($theirRootish === '') {
            throw new \InvalidArgumentException('Dolt preview from_root_ish must be a non-empty string.');
        }

        $primaryKey = $this->normalizePrimaryKey($primaryKey);
        $columns = $columns === null
            ? $this->inferColumns($baseRows, $ourRows, $theirRows)
            : $this->validateColumns($columns);

        $base = $this->index($baseRows, $primaryKey);
        $ours = $this->index($ourRows, $primaryKey);
        $theirs = $this->index($theirRows, $primaryKey);

        $rows = [];
        foreach ($this->orderedKeys($base, $ours, $theirs) as $key) {
            $baseRow = $base[$key] ?? null;
            $ourRow = $ours[$key] ?? null;
            $theirRow = $theirs[$key] ?? null;

            $ourDiff = $this->diffType($baseRow, $ourRow);
            $theirDiff = $this->diffType($baseRow, $theirRow);
            if ($ourDiff === null || $theirDiff === null || $this->rowsSame($ourRow, $theirRow)) {
                continue;
            }

            $rows[] = $this->formatConflictRow(
                $key,
                $theirRootish,
                $baseRow,
                $ourRow,
                $theirRow,
                $ourDiff,
                $theirDiff,
                $columns
            );
        }

        return $rows;
    }

    public function schemaConflictError(int $schemaConflictCount): string
    {
        if ($schemaConflictCount < 0) {
            throw new \InvalidArgumentException('Dolt preview schema conflict count must be a non-negative integer.');
        }

        return "schema conflicts found: {$schemaConflictCount}";
    }

    /**
     * @param array<string,array{data:int,schema:int}> $stats
     * @param list<non-empty-string> $order
     */
    private function markSummary(array &$stats, array &$order, string $tableName, string $kind, int $count): void
    {
        if (!isset($stats[$tableName])) {
            $stats[$tableName] = ['data' => 0, 'schema' => 0];
            $order[] = $tableName;
        }

        $stats[$tableName][$kind] += $count;
    }

    /**
     * @param string|array<string,mixed> $item
     * @return array{table:non-empty-string, count:int}
     */
    private function normalizeSummaryItem(string|array $item, string $kind): array
    {
        if (is_string($item)) {
            if ($item === '') {
                throw new \InvalidArgumentException('Dolt preview conflict table names must be non-empty strings.');
            }

            return ['table' => $item, 'count' => 1];
        }

        $tableName = $item['table'] ?? $item['name'] ?? null;
        if (!is_string($tableName) || $tableName === '') {
            throw new \InvalidArgumentException('Dolt preview conflict rows must include a non-empty table name.');
        }

        $candidateKeys = $kind === 'schema'
            ? ['num_schema_conflicts', 'numSchemaConflicts', 'num_conflicts', 'numConflicts', 'count']
            : ['num_data_conflicts', 'numDataConflicts', 'num_conflicts', 'numConflicts', 'count'];

        $count = 1;
        foreach ($candidateKeys as $key) {
            if (array_key_exists($key, $item)) {
                $count = $item[$key];
                break;
            }
        }
        if (!is_int($count) || $count < 0) {
            throw new \InvalidArgumentException("Dolt preview conflict count for {$tableName} must be a non-negative integer.");
        }

        return ['table' => $tableName, 'count' => $count];
    }

    /**
     * @param non-empty-string|list<non-empty-string> $primaryKey
     * @return list<non-empty-string>
     */
    private function normalizePrimaryKey(string|array $primaryKey): array
    {
        $columns = is_string($primaryKey) ? [$primaryKey] : $primaryKey;
        if ($columns === []) {
            throw new \InvalidArgumentException('At least one primary key column is required.');
        }

        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('Primary key columns must be non-empty strings.');
            }
        }

        return array_values($columns);
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     * @param list<non-empty-string> $primaryKey
     * @return array<string, array<string, scalar|null>>
     */
    private function index(array $rows, array $primaryKey): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $key = $this->rowKey($row, $primaryKey);
            if (array_key_exists($key, $indexed)) {
                throw new \InvalidArgumentException('Duplicate primary key in Dolt preview row set: ' . implode(', ', $primaryKey));
            }
            $indexed[$key] = $row;
        }

        return $indexed;
    }

    /**
     * @param array<string, scalar|null> $row
     * @param list<non-empty-string> $primaryKey
     */
    private function rowKey(array $row, array $primaryKey): string
    {
        $values = [];
        foreach ($primaryKey as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("Row is missing primary key: {$column}");
            }
            if ($row[$column] === null) {
                throw new \InvalidArgumentException("Primary key column cannot be null: {$column}");
            }
            $values[$column] = $row[$column];
        }

        return json_encode($values, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, array<string, scalar|null>> ...$groups
     * @return list<string>
     */
    private function orderedKeys(array ...$groups): array
    {
        $keys = [];
        foreach ($groups as $group) {
            foreach (array_keys($group) as $key) {
                $keys[$key] = true;
            }
        }

        $keys = array_keys($keys);
        sort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * @param array<string, scalar|null>|null $base
     * @param array<string, scalar|null>|null $row
     */
    private function diffType(?array $base, ?array $row): ?string
    {
        if ($base === null && $row === null) {
            return null;
        }
        if ($base === null) {
            return self::DIFF_ADDED;
        }
        if ($row === null) {
            return self::DIFF_REMOVED;
        }
        if ($this->rowsSame($base, $row)) {
            return null;
        }

        return self::DIFF_MODIFIED;
    }

    /**
     * @param array<string, scalar|null>|null $left
     * @param array<string, scalar|null>|null $right
     */
    private function rowsSame(?array $left, ?array $right): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }
        if (count($left) !== count($right)) {
            return false;
        }
        foreach ($left as $column => $value) {
            if (!array_key_exists($column, $right) || $right[$column] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, scalar|null>> ...$rowSets
     * @return list<non-empty-string>
     */
    private function inferColumns(array ...$rowSets): array
    {
        $columns = [];
        foreach ($rowSets as $rows) {
            foreach ($rows as $row) {
                foreach (array_keys($row) as $column) {
                    if ($column !== '' && !isset($columns[$column])) {
                        $columns[$column] = true;
                    }
                }
            }
        }

        return $this->validateColumns(array_keys($columns));
    }

    /**
     * @param list<non-empty-string> $columns
     * @return list<non-empty-string>
     */
    private function validateColumns(array $columns): array
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('At least one Dolt preview conflict column is required.');
        }

        $seen = [];
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('Dolt preview conflict columns must be non-empty strings.');
            }
            if (isset($seen[$column])) {
                throw new \InvalidArgumentException("Duplicate Dolt preview conflict column: {$column}");
            }
            $seen[$column] = true;
        }

        return array_values($columns);
    }

    /**
     * @param array<string, scalar|null>|null $baseRow
     * @param array<string, scalar|null>|null $ourRow
     * @param array<string, scalar|null>|null $theirRow
     * @param list<non-empty-string> $columns
     * @return array<string, scalar|null>
     */
    private function formatConflictRow(
        string $key,
        string $theirRootish,
        ?array $baseRow,
        ?array $ourRow,
        ?array $theirRow,
        string $ourDiff,
        string $theirDiff,
        array $columns,
    ): array {
        $row = ['from_root_ish' => $theirRootish];

        foreach ($columns as $column) {
            $row["base_{$column}"] = $baseRow[$column] ?? null;
        }
        foreach ($columns as $column) {
            $row["our_{$column}"] = $ourRow[$column] ?? null;
        }
        $row['our_diff_type'] = $ourDiff;
        foreach ($columns as $column) {
            $row["their_{$column}"] = $theirRow[$column] ?? null;
        }
        $row['their_diff_type'] = $theirDiff;
        $row['dolt_conflict_id'] = $this->conflictId($key, $theirRootish);

        return $row;
    }

    private function conflictId(string $key, string $theirRootish): string
    {
        return rtrim(base64_encode(substr(hash('sha256', $key . "\0" . $theirRootish, true), 0, 16)), '=');
    }
}
