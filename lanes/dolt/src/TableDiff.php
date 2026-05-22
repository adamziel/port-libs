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
     * Project keyless row changes into Dolt's diff row shape.
     *
     * Upstream keyless table diffs compare complete row values as a multiset:
     * duplicate cardinality increases become repeated `added` rows, decreases
     * become repeated `removed` rows, and value changes are represented as one
     * removal plus one addition rather than a `modified` row.
     *
     * @param list<array<string, scalar|null>> $fromRows
     * @param list<array<string, scalar|null>> $toRows
     * @param list<non-empty-string>|null $columns
     * @return list<array<string, scalar|null>>
     */
    public function keylessDiffTableRows(
        array $fromRows,
        array $toRows,
        ?array $columns = null,
        string $fromCommit = 'FROM',
        ?string $fromCommitDate = null,
        string $toCommit = 'TO',
        ?string $toCommitDate = null,
    ): array {
        $columns = $columns === null ? $this->inferColumns($fromRows, $toRows) : $this->validateColumns($columns);
        $from = $this->rowMultisetEntries($fromRows);
        $to = $this->rowMultisetEntries($toRows);

        $rows = [];
        foreach ($this->orderedKeys($from, $to) as $key) {
            $fromEntry = $from[$key] ?? ['row' => null, 'count' => 0];
            $toEntry = $to[$key] ?? ['row' => null, 'count' => 0];
            $delta = $toEntry['count'] - $fromEntry['count'];

            if ($delta > 0) {
                for ($i = 0; $i < $delta; $i++) {
                    $rows[] = $this->formatDiffTableRow(
                        self::DIFF_ADDED,
                        null,
                        $toEntry['row'],
                        $columns,
                        $fromCommit,
                        $fromCommitDate,
                        $toCommit,
                        $toCommitDate
                    );
                }
            } elseif ($delta < 0) {
                for ($i = 0; $i < -$delta; $i++) {
                    $rows[] = $this->formatDiffTableRow(
                        self::DIFF_REMOVED,
                        $fromEntry['row'],
                        null,
                        $columns,
                        $fromCommit,
                        $fromCommitDate,
                        $toCommit,
                        $toCommitDate
                    );
                }
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
     * Compute the row shape returned by upstream `dolt_diff_stat()` for a
     * single table delta. A null return matches upstream's empty result when
     * a table was created without rows or an unchanged table has no data/cell
     * count difference.
     *
     * @param list<array<string, scalar|null>> $fromRows
     * @param list<array<string, scalar|null>> $toRows
     * @param non-empty-string|list<non-empty-string>|null $primaryKey
     * @param list<array{code:int, message:string}> $warnings
     * @return array{table_name:string, rows_unmodified:int|null, rows_added:int, rows_deleted:int, rows_modified:int|null, cells_added:int|null, cells_deleted:int|null, cells_modified:int|null, old_row_count:int|null, new_row_count:int|null, old_cell_count:int|null, new_cell_count:int|null}|null
     */
    public function diffStatRow(
        string $tableName,
        array $fromRows,
        array $toRows,
        string|array|null $primaryKey,
        ?TableSchema $fromSchema,
        ?TableSchema $toSchema,
        array &$warnings = [],
        bool $errorOnPrimaryKeyChange = true,
        bool $keyless = false,
        string $fromCommit = 'FROM',
        string $toCommit = 'TO',
    ): ?array {
        if ($tableName === '') {
            throw new \InvalidArgumentException('Diff stat table name must be a non-empty string.');
        }
        if ($fromSchema === null && $toSchema === null) {
            throw new \InvalidArgumentException("Table {$tableName} could not be found.");
        }

        if (!$keyless && $fromSchema !== null && $toSchema !== null && !TableSchema::primaryKeySetsDiffable($fromSchema, $toSchema)) {
            if ($errorOnPrimaryKeyChange) {
                throw new \RuntimeException("failed to compute diff stat for table {$tableName}: primary key set changed");
            }
            $warnings[] = [
                'code' => TableSchema::WARNING_UNKNOWN,
                'message' => "stat for table {$tableName} cannot be determined. Primary key set changed.",
            ];

            return $this->formatDiffStatRow($tableName, 0, 0, 0, 0, 0, 0, 0, false);
        }

        $oldColumnCount = $fromSchema === null ? 0 : count($fromSchema->columns());
        $newColumnCount = $toSchema === null ? 0 : count($toSchema->columns());
        $oldRowCount = count($fromRows);
        $newRowCount = count($toRows);
        $oldCellCount = $oldRowCount * $oldColumnCount;
        $newCellCount = $newRowCount * $newColumnCount;

        if ($keyless || $primaryKey === null) {
            [$added, $deleted] = $this->keylessRowCounts($fromRows, $toRows);
            if ($added + $deleted === 0) {
                return null;
            }

            return $this->formatKeylessDiffStatRow($tableName, $added, $deleted);
        }

        $diff = $this->diff($fromRows, $toRows, $primaryKey);
        $added = count($diff['added']);
        $deleted = count($diff['removed']);
        $modified = count($diff['modified']);
        $cellChanges = $this->modifiedCellCount($diff['modified'], $fromSchema, $toSchema);

        if ($added + $deleted + $modified === 0 && $oldCellCount === $newCellCount) {
            return null;
        }

        return $this->formatDiffStatRow(
            $tableName,
            $oldRowCount,
            $newRowCount,
            $oldCellCount,
            $newCellCount,
            $added,
            $deleted,
            $modified,
            false,
            $cellChanges
        );
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
     * @param list<array<string, scalar|null>> $fromRows
     * @param list<array<string, scalar|null>> $toRows
     * @return array{0:int, 1:int}
     */
    private function keylessRowCounts(array $fromRows, array $toRows): array
    {
        $from = $this->rowMultiset($fromRows);
        $to = $this->rowMultiset($toRows);
        $added = 0;
        $deleted = 0;

        foreach ($from as $key => $count) {
            $toCount = $to[$key] ?? 0;
            if ($count > $toCount) {
                $deleted += $count - $toCount;
            }
        }
        foreach ($to as $key => $count) {
            $fromCount = $from[$key] ?? 0;
            if ($count > $fromCount) {
                $added += $count - $fromCount;
            }
        }

        return [$added, $deleted];
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     * @return array<string, int>
     */
    private function rowMultiset(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $key = $this->canonicalRowKey($row);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     * @return array<string, array{row:array<string, scalar|null>, count:int}>
     */
    private function rowMultisetEntries(array $rows): array
    {
        $entries = [];
        foreach ($rows as $row) {
            $key = $this->canonicalRowKey($row);
            if (!isset($entries[$key])) {
                $entries[$key] = ['row' => $row, 'count' => 0];
            }
            $entries[$key]['count']++;
        }

        return $entries;
    }

    /**
     * @param array<string, scalar|null> $row
     */
    private function canonicalRowKey(array $row): string
    {
        ksort($row, SORT_STRING);

        return json_encode($row, JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array{old:array<string, scalar|null>, new:array<string, scalar|null>}> $modifiedRows
     */
    private function modifiedCellCount(array $modifiedRows, ?TableSchema $fromSchema, ?TableSchema $toSchema): int
    {
        $count = 0;
        foreach ($modifiedRows as $change) {
            foreach ($this->statComparableColumns($change['old'], $change['new'], $fromSchema, $toSchema) as $column) {
                if (($change['old'][$column] ?? null) !== ($change['new'][$column] ?? null)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @param array<string, scalar|null> $fromRow
     * @param array<string, scalar|null> $toRow
     * @return list<string>
     */
    private function statComparableColumns(array $fromRow, array $toRow, ?TableSchema $fromSchema, ?TableSchema $toSchema): array
    {
        $columns = [];
        foreach ([$fromSchema, $toSchema] as $schema) {
            if ($schema === null) {
                continue;
            }
            foreach ($schema->columns() as $column) {
                $columns[$column['name']] = true;
            }
        }
        foreach (array_keys($fromRow + $toRow) as $column) {
            $columns[$column] = true;
        }

        return array_keys($columns);
    }

    /**
     * @return array{table_name:string, rows_unmodified:int|null, rows_added:int, rows_deleted:int, rows_modified:int|null, cells_added:int|null, cells_deleted:int|null, cells_modified:int|null, old_row_count:int|null, new_row_count:int|null, old_cell_count:int|null, new_cell_count:int|null}
     */
    private function formatDiffStatRow(
        string $tableName,
        int $oldRowCount,
        int $newRowCount,
        int $oldCellCount,
        int $newCellCount,
        int $added,
        int $deleted,
        int $modified,
        bool $keyless,
        int $cellChanges = 0,
    ): array {
        if ($keyless) {
            return $this->formatKeylessDiffStatRow($tableName, $added, $deleted);
        }

        [$cellsAdded, $cellsDeleted] = $this->cellsAddedAndDeleted(
            $added,
            $deleted,
            $oldCellCount,
            $newCellCount,
            $newRowCount === 0 ? 0 : intdiv($newCellCount, $newRowCount)
        );

        return [
            'table_name' => $tableName,
            'rows_unmodified' => $oldRowCount - $modified - $deleted,
            'rows_added' => $added,
            'rows_deleted' => $deleted,
            'rows_modified' => $modified,
            'cells_added' => $cellsAdded,
            'cells_deleted' => $cellsDeleted,
            'cells_modified' => $cellChanges,
            'old_row_count' => $oldRowCount,
            'new_row_count' => $newRowCount,
            'old_cell_count' => $oldCellCount,
            'new_cell_count' => $newCellCount,
        ];
    }

    /**
     * @return array{table_name:string, rows_unmodified:int|null, rows_added:int, rows_deleted:int, rows_modified:int|null, cells_added:int|null, cells_deleted:int|null, cells_modified:int|null, old_row_count:int|null, new_row_count:int|null, old_cell_count:int|null, new_cell_count:int|null}
     */
    private function formatKeylessDiffStatRow(string $tableName, int $added, int $deleted): array
    {
        return [
            'table_name' => $tableName,
            'rows_unmodified' => null,
            'rows_added' => $added,
            'rows_deleted' => $deleted,
            'rows_modified' => null,
            'cells_added' => null,
            'cells_deleted' => null,
            'cells_modified' => null,
            'old_row_count' => null,
            'new_row_count' => null,
            'old_cell_count' => null,
            'new_cell_count' => null,
        ];
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function cellsAddedAndDeleted(int $added, int $deleted, int $oldCellCount, int $newCellCount, int $newColumnCount): array
    {
        $rowToCellInserts = $added * $newColumnCount;
        $rowToCellDeletes = $deleted * $newColumnCount;
        $cellDiff = $newCellCount - $oldCellCount;

        if ($cellDiff > 0) {
            return [$cellDiff + $rowToCellDeletes, $rowToCellDeletes];
        }
        if ($cellDiff < 0) {
            return [$rowToCellInserts, abs($cellDiff) + $rowToCellInserts];
        }
        if ($rowToCellInserts !== $rowToCellDeletes) {
            $max = max($rowToCellDeletes, $rowToCellInserts);

            return [$max, $max];
        }

        return [$rowToCellInserts, $rowToCellDeletes];
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
