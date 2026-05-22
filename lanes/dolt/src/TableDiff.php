<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class TableDiff
{
    public const DIFF_ADDED = 'added';
    public const DIFF_REMOVED = 'removed';
    public const DIFF_MODIFIED = 'modified';

    /**
     * @param list<array<string, scalar|null>> $oldRows
     * @param list<array<string, scalar|null>> $newRows
     * @param non-empty-string|list<non-empty-string> $primaryKey
     * @return array{added:list<array<string, scalar|null>>, removed:list<array<string, scalar|null>>, modified:list<array{old:array<string, scalar|null>, new:array<string, scalar|null>}>}
     */
    public function diff(array $oldRows, array $newRows, string|array $primaryKey): array
    {
        $old = $this->index($oldRows, $primaryKey);
        $new = $this->index($newRows, $primaryKey);
        $added = [];
        $removed = [];
        $modified = [];

        foreach ($this->orderedKeys($old, $new) as $key) {
            if (!array_key_exists($key, $old)) {
                $added[] = $new[$key];
            } elseif (!array_key_exists($key, $new)) {
                $removed[] = $old[$key];
            } elseif (!$this->rowsEqual($old[$key], $new[$key])) {
                $modified[] = ['old' => $old[$key], 'new' => $new[$key]];
            }
        }

        return ['added' => $added, 'removed' => $removed, 'modified' => $modified];
    }

    /**
     * Project row changes into the shape used by Dolt's `DOLT_DIFF_*` tables and
     * `dolt_diff()` table function.
     *
     * @param list<array<string, scalar|null>> $fromRows
     * @param list<array<string, scalar|null>> $toRows
     * @param non-empty-string|list<non-empty-string> $primaryKey
     * @param list<non-empty-string>|null $columns
     * @return list<array<string, scalar|null>>
     */
    public function diffTableRows(
        array $fromRows,
        array $toRows,
        string|array $primaryKey,
        ?array $columns = null,
        string $fromCommit = 'FROM',
        ?string $fromCommitDate = null,
        string $toCommit = 'TO',
        ?string $toCommitDate = null,
    ): array {
        $from = $this->index($fromRows, $primaryKey);
        $to = $this->index($toRows, $primaryKey);
        $columns = $columns === null ? $this->inferColumns($fromRows, $toRows) : $this->validateColumns($columns);

        $rows = [];
        foreach ($this->orderedKeys($from, $to) as $key) {
            $fromRow = $from[$key] ?? null;
            $toRow = $to[$key] ?? null;
            if ($fromRow === null) {
                $rows[] = $this->formatDiffTableRow(
                    self::DIFF_ADDED,
                    null,
                    $toRow,
                    $columns,
                    $fromCommit,
                    $fromCommitDate,
                    $toCommit,
                    $toCommitDate
                );
            } elseif ($toRow === null) {
                $rows[] = $this->formatDiffTableRow(
                    self::DIFF_REMOVED,
                    $fromRow,
                    null,
                    $columns,
                    $fromCommit,
                    $fromCommitDate,
                    $toCommit,
                    $toCommitDate
                );
            } elseif (!$this->rowsEqual($fromRow, $toRow)) {
                $rows[] = $this->formatDiffTableRow(
                    self::DIFF_MODIFIED,
                    $fromRow,
                    $toRow,
                    $columns,
                    $fromCommit,
                    $fromCommitDate,
                    $toCommit,
                    $toCommitDate
                );
            }
        }

        return $rows;
    }

    /**
     * Project row changes through explicit Dolt schemas. This mirrors the
     * upstream diff iterator boundary where stored rows are converted into the
     * schema chosen for the diff table before `to_*` and `from_*` columns are
     * emitted.
     *
     * @param list<array<string, scalar|null>> $fromRows
     * @param list<array<string, scalar|null>> $toRows
     * @param non-empty-string|list<non-empty-string> $primaryKey
     * @param list<array{code:int, message:string}> $warnings
     * @return list<array<string, scalar|null>>
     */
    public function diffTableRowsForSchemas(
        array $fromRows,
        array $toRows,
        string|array $primaryKey,
        TableSchema $fromSchema,
        TableSchema $toSchema,
        ?TableSchema $targetFromSchema = null,
        ?TableSchema $targetToSchema = null,
        string $fromCommit = 'FROM',
        ?string $fromCommitDate = null,
        string $toCommit = 'TO',
        ?string $toCommitDate = null,
        array &$warnings = [],
        bool $skinny = false,
        array $includeColumns = [],
    ): array {
        $targetFromSchema ??= $fromSchema;
        $targetToSchema ??= $toSchema;

        $diffability = TableSchema::partitionDiffability($fromSchema, $toSchema, $fromCommit, $toCommit);
        if ($diffability['warning'] !== null) {
            $warnings[] = $diffability['warning'];
        }
        if (!$diffability['simple'] && !$diffability['fuzzy']) {
            return [];
        }

        if ($skinny) {
            [$targetFromSchema, $targetToSchema] = $this->filterSchemasToSkinnyColumns(
                $fromRows,
                $toRows,
                $primaryKey,
                $targetFromSchema,
                $targetToSchema,
                $includeColumns
            );
        }

        $from = $this->index($fromRows, $primaryKey);
        $to = $this->index($toRows, $primaryKey);
        $rows = [];
        foreach ($this->orderedKeys($from, $to) as $key) {
            $fromRow = $from[$key] ?? null;
            $toRow = $to[$key] ?? null;
            $projectedFromRow = $fromRow === null ? null : $fromSchema->projectRowTo($targetFromSchema, $fromRow, $warnings);
            $projectedToRow = $toRow === null ? null : $toSchema->projectRowTo($targetToSchema, $toRow, $warnings);

            if ($fromRow === null) {
                $rows[] = $this->formatSchemaDiffTableRow(
                    self::DIFF_ADDED,
                    null,
                    $projectedToRow,
                    $targetFromSchema,
                    $targetToSchema,
                    $fromCommit,
                    $fromCommitDate,
                    $toCommit,
                    $toCommitDate
                );
            } elseif ($toRow === null) {
                $rows[] = $this->formatSchemaDiffTableRow(
                    self::DIFF_REMOVED,
                    $projectedFromRow,
                    null,
                    $targetFromSchema,
                    $targetToSchema,
                    $fromCommit,
                    $fromCommitDate,
                    $toCommit,
                    $toCommitDate
                );
            } elseif (!$this->rowsEqual($fromRow, $toRow)) {
                $rows[] = $this->formatSchemaDiffTableRow(
                    self::DIFF_MODIFIED,
                    $projectedFromRow,
                    $projectedToRow,
                    $targetFromSchema,
                    $targetToSchema,
                    $fromCommit,
                    $fromCommitDate,
                    $toCommit,
                    $toCommitDate
                );
            }
        }

        return $rows;
    }

    /**
     * Apply a Dolt-style row predicate and row limit to already-projected
     * `DOLT_DIFF_*` / `dolt_diff()` rows. This intentionally works on the
     * `to_*` and `from_*` projected column names that upstream exposes to
     * `--where` and table-function predicates.
     *
     * @param list<array<string, scalar|null>> $rows
     * @return list<array<string, scalar|null>>
     */
    public function filterDiffTableRows(array $rows, ?string $where = null, ?int $limit = null): array
    {
        return (new DiffRowFilter())->apply($rows, $where, $limit);
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     * @param non-empty-string|list<non-empty-string> $primaryKey
     * @return array<string, array<string, scalar|null>>
     */
    private function index(array $rows, string|array $primaryKey): array
    {
        $primaryKey = $this->normalizePrimaryKey($primaryKey);
        $indexed = [];
        foreach ($rows as $row) {
            $key = $this->rowKey($row, $primaryKey);
            if (array_key_exists($key, $indexed)) {
                throw new \InvalidArgumentException('Duplicate primary key in row set: ' . implode(', ', $primaryKey));
            }
            $indexed[$key] = $row;
        }

        return $indexed;
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
     * @param array<string, array<string, scalar|null>> $left
     * @param array<string, array<string, scalar|null>> $right
     * @return list<string>
     */
    private function orderedKeys(array $left, array $right): array
    {
        $keys = array_keys($left + $right);
        sort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * @param array<string, scalar|null> $old
     * @param array<string, scalar|null> $new
     */
    private function rowsEqual(array $old, array $new): bool
    {
        if (count($old) !== count($new)) {
            return false;
        }
        foreach ($old as $column => $value) {
            if (!array_key_exists($column, $new) || $new[$column] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, scalar|null>> $fromRows
     * @param list<array<string, scalar|null>> $toRows
     * @return list<non-empty-string>
     */
    private function inferColumns(array $fromRows, array $toRows): array
    {
        $columns = [];
        foreach ([$fromRows, $toRows] as $rows) {
            foreach ($rows as $row) {
                foreach (array_keys($row) as $column) {
                    if ($column !== '' && !array_key_exists($column, $columns)) {
                        $columns[$column] = true;
                    }
                }
            }
        }

        return array_keys($columns);
    }

    /**
     * @param list<non-empty-string> $columns
     * @return list<non-empty-string>
     */
    private function validateColumns(array $columns): array
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('At least one diff column is required.');
        }

        $seen = [];
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('Diff columns must be non-empty strings.');
            }
            if (isset($seen[$column])) {
                throw new \InvalidArgumentException("Duplicate diff column: {$column}");
            }
            $seen[$column] = true;
        }

        return array_values($columns);
    }

    /**
     * @param list<array<string, scalar|null>> $fromRows
     * @param list<array<string, scalar|null>> $toRows
     * @param non-empty-string|list<non-empty-string> $primaryKey
     * @param list<string> $includeColumns
     * @return array{0:TableSchema, 1:TableSchema}
     */
    private function filterSchemasToSkinnyColumns(
        array $fromRows,
        array $toRows,
        string|array $primaryKey,
        TableSchema $fromSchema,
        TableSchema $toSchema,
        array $includeColumns,
    ): array {
        $removable = $this->skinnyRemovableColumns($fromSchema, $toSchema, $includeColumns);
        if ($removable === []) {
            return [$fromSchema, $toSchema];
        }

        $from = $this->index($fromRows, $primaryKey);
        $to = $this->index($toRows, $primaryKey);

        foreach ($this->orderedKeys($from, $to) as $key) {
            if (!array_key_exists($key, $from) || !array_key_exists($key, $to)) {
                $removable = [];
                break;
            }

            foreach (array_keys($removable) as $column) {
                if (($from[$key][$column] ?? null) !== ($to[$key][$column] ?? null)) {
                    unset($removable[$column]);
                }
            }

            if ($removable === []) {
                break;
            }
        }

        return [
            $fromSchema->withoutColumnNames($removable),
            $toSchema->withoutColumnNames($removable),
        ];
    }

    /**
     * @param list<string> $includeColumns
     * @return array<string, true>
     */
    private function skinnyRemovableColumns(TableSchema $fromSchema, TableSchema $toSchema, array $includeColumns): array
    {
        $include = $this->normalizeIncludeColumns($includeColumns);
        $removable = [];
        foreach ($fromSchema->columns() as $fromColumn) {
            if ($fromColumn['primaryKey'] || isset($include[$fromColumn['name']])) {
                continue;
            }

            $toColumn = $toSchema->columnByName($fromColumn['name']);
            if ($toColumn === null || !TableSchema::sqlTypesEqual($fromColumn['type'], $toColumn['type'])) {
                continue;
            }

            $removable[$fromColumn['name']] = true;
        }

        return $removable;
    }

    /**
     * @param list<string> $columns
     * @return array<string, true>
     */
    private function normalizeIncludeColumns(array $columns): array
    {
        $include = [];
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('Included diff columns must be non-empty strings.');
            }
            $include[$column] = true;
        }

        return $include;
    }

    /**
     * @param array<string, scalar|null>|null $fromRow
     * @param array<string, scalar|null>|null $toRow
     * @param list<non-empty-string> $columns
     * @return array<string, scalar|null>
     */
    private function formatDiffTableRow(
        string $diffType,
        ?array $fromRow,
        ?array $toRow,
        array $columns,
        string $fromCommit,
        ?string $fromCommitDate,
        string $toCommit,
        ?string $toCommitDate,
    ): array {
        $row = [];
        foreach ($columns as $column) {
            $row['to_' . $column] = $toRow[$column] ?? null;
        }
        $row['to_commit'] = $toCommit;
        $row['to_commit_date'] = $toCommitDate;
        foreach ($columns as $column) {
            $row['from_' . $column] = $fromRow[$column] ?? null;
        }
        $row['from_commit'] = $fromCommit;
        $row['from_commit_date'] = $fromCommitDate;
        $row['diff_type'] = $diffType;

        return $row;
    }

    /**
     * @param array<string, scalar|null>|null $fromRow
     * @param array<string, scalar|null>|null $toRow
     * @return array<string, scalar|null>
     */
    private function formatSchemaDiffTableRow(
        string $diffType,
        ?array $fromRow,
        ?array $toRow,
        TableSchema $targetFromSchema,
        TableSchema $targetToSchema,
        string $fromCommit,
        ?string $fromCommitDate,
        string $toCommit,
        ?string $toCommitDate,
    ): array {
        $row = [];
        foreach ($targetToSchema->columns() as $column) {
            $row['to_' . $column['name']] = $toRow[$column['name']] ?? null;
        }
        $row['to_commit'] = $toCommit;
        $row['to_commit_date'] = $toCommitDate;
        foreach ($targetFromSchema->columns() as $column) {
            $row['from_' . $column['name']] = $fromRow[$column['name']] ?? null;
        }
        $row['from_commit'] = $fromCommit;
        $row['from_commit_date'] = $fromCommitDate;
        $row['diff_type'] = $diffType;

        return $row;
    }
}
