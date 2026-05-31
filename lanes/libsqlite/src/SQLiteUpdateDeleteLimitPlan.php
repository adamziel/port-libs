<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpdateDeleteLimitPlan
{
    /**
     * @param list<array<string,mixed>> $inputRows
     * @param list<array<string,mixed>> $qualifiedRows
     * @param list<array<string,mixed>> $selectedRows
     * @param list<array<string,mixed>> $mutationRows
     * @param list<array<string,mixed>> $resultRows
     * @param list<array{column:string,direction?:string,nulls?:string,expression?:string,value?:callable(array<string,mixed>):mixed}> $orderBy
     * @param list<int|string> $selectedIds
     * @param list<int|string> $mutationIds
     * @param array<string,mixed> $assignments
     */
    private function __construct(
        public readonly string $action,
        public readonly array $inputRows,
        public readonly array $qualifiedRows,
        public readonly array $selectedRows,
        public readonly array $mutationRows,
        public readonly array $resultRows,
        public readonly array $orderBy,
        public readonly ?int $limit,
        public readonly int $offset,
        public readonly string $rowIdColumn,
        public readonly array $selectedIds,
        public readonly array $mutationIds,
        public readonly array $assignments = [],
    ) {
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param callable(array<string,mixed>):bool|null $where
     * @param list<array{column:string,direction?:string,nulls?:string,expression?:string,value?:callable(array<string,mixed>):mixed}> $orderBy
     */
    public static function delete(
        array $rows,
        callable $where,
        array $orderBy = [],
        ?int $limit = null,
        int $offset = 0,
        string $rowIdColumn = 'rowid',
    ): self {
        $prepared = self::prepare($rows, $where, $orderBy, $limit, $offset, $rowIdColumn);
        $selectedIndexes = array_fill_keys(array_column($prepared['selected'], '__sqlite_udl_index'), true);
        $remaining = [];
        foreach ($prepared['indexed'] as $indexedRow) {
            if (!isset($selectedIndexes[$indexedRow['__sqlite_udl_index']])) {
                $remaining[] = self::stripInternal($indexedRow);
            }
        }

        return new self(
            'delete',
            $rows,
            array_map(self::stripInternal(...), $prepared['qualified']),
            array_map(self::stripInternal(...), $prepared['selected']),
            array_map(self::stripInternal(...), $prepared['mutation']),
            $remaining,
            $orderBy,
            $limit,
            $offset,
            $rowIdColumn,
            self::ids($prepared['selected'], $rowIdColumn),
            self::ids($prepared['mutation'], $rowIdColumn),
        );
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param callable(array<string,mixed>):bool|null $where
     * @param array<string,mixed|callable(array<string,mixed>):mixed> $assignments
     * @param list<array{column:string,direction?:string,nulls?:string,expression?:string,value?:callable(array<string,mixed>):mixed}> $orderBy
     */
    public static function update(
        array $rows,
        callable $where,
        array $assignments,
        array $orderBy = [],
        ?int $limit = null,
        int $offset = 0,
        string $rowIdColumn = 'rowid',
    ): self {
        if ($assignments === []) {
            throw new \InvalidArgumentException('SQLite UPDATE LIMIT plan needs assignments');
        }
        foreach ($assignments as $column => $value) {
            if (!is_string($column) || $column === '' || $column === '__sqlite_udl_index') {
                throw new \InvalidArgumentException('SQLite UPDATE LIMIT assignment columns must be non-empty strings');
            }
            if (is_array($value)) {
                throw new \InvalidArgumentException('SQLite UPDATE LIMIT assignment values must be scalars, NULL, objects, or callables');
            }
        }

        $prepared = self::prepare($rows, $where, $orderBy, $limit, $offset, $rowIdColumn);
        $selectedIndexes = array_fill_keys(array_column($prepared['selected'], '__sqlite_udl_index'), true);
        $mutation = [];
        $updated = [];
        foreach ($prepared['indexed'] as $indexedRow) {
            if (!isset($selectedIndexes[$indexedRow['__sqlite_udl_index']])) {
                $updated[] = self::stripInternal($indexedRow);
                continue;
            }

            $newRow = $indexedRow;
            foreach ($assignments as $column => $value) {
                $newRow[$column] = is_callable($value) ? $value(self::stripInternal($indexedRow)) : $value;
            }
            $mutation[] = $newRow;
            $updated[] = self::stripInternal($newRow);
        }

        return new self(
            'update',
            $rows,
            array_map(self::stripInternal(...), $prepared['qualified']),
            array_map(self::stripInternal(...), $prepared['selected']),
            array_map(self::stripInternal(...), $mutation),
            $updated,
            $orderBy,
            $limit,
            $offset,
            $rowIdColumn,
            self::ids($prepared['selected'], $rowIdColumn),
            self::ids($mutation, $rowIdColumn),
            self::assignmentSummary($assignments),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'input_rows' => count($this->inputRows),
            'qualified_rows' => count($this->qualifiedRows),
            'selected_rows' => count($this->selectedRows),
            'result_rows' => count($this->resultRows),
            'selected_ids' => $this->selectedIds,
            'mutation_ids' => $this->mutationIds,
            'order_by' => self::orderBySummary($this->orderBy),
            'limit' => $this->limit,
            'offset' => $this->offset,
            'rowid_column' => $this->rowIdColumn,
            'assignments' => $this->assignments,
        ];
    }

    /**
     * @param list<string>|array<string,string|callable(array<string,mixed>):mixed>|null $projection
     * @return list<array<string,mixed>>
     */
    public function returningRows(?array $projection = null): array
    {
        if ($projection === null || $projection === []) {
            return $this->mutationRows;
        }

        $rows = [];
        foreach ($this->mutationRows as $row) {
            $returned = [];
            foreach ($projection as $alias => $expression) {
                if (is_int($alias)) {
                    if (!is_string($expression) || $expression === '') {
                        throw new \InvalidArgumentException('SQLite RETURNING projection columns must be non-empty strings');
                    }
                    if ($expression === '*') {
                        foreach ($row as $column => $value) {
                            $returned[$column] = $value;
                        }
                        continue;
                    }
                    if (!array_key_exists($expression, $row)) {
                        throw new \InvalidArgumentException("SQLite RETURNING projection column {$expression} is missing");
                    }
                    $returned[$expression] = $row[$expression];
                    continue;
                }

                if (!is_string($alias) || $alias === '') {
                    throw new \InvalidArgumentException('SQLite RETURNING projection aliases must be non-empty strings');
                }
                if (is_string($expression)) {
                    if ($expression === '') {
                        throw new \InvalidArgumentException('SQLite RETURNING projection columns must be non-empty strings');
                    }
                    if (!array_key_exists($expression, $row)) {
                        throw new \InvalidArgumentException("SQLite RETURNING projection column {$expression} is missing");
                    }
                    $returned[$alias] = $row[$expression];
                    continue;
                }
                if (!is_callable($expression)) {
                    throw new \InvalidArgumentException('SQLite RETURNING projection expressions must be column names or callables');
                }
                $returned[$alias] = $expression($row);
            }
            $rows[] = $returned;
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param callable(array<string,mixed>):bool|null $where
     * @param list<array{column:string,direction?:string,nulls?:string,expression?:string,value?:callable(array<string,mixed>):mixed}> $orderBy
     * @return array{indexed:list<array<string,mixed>>,qualified:list<array<string,mixed>>,selected:list<array<string,mixed>>,mutation:list<array<string,mixed>>}
     */
    private static function prepare(array $rows, callable $where, array $orderBy, ?int $limit, int $offset, string $rowIdColumn): array
    {
        if ($rowIdColumn === '' || $rowIdColumn === '__sqlite_udl_index') {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT rowid column must be a non-empty user column');
        }
        if ($limit === null && $offset !== 0) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT OFFSET requires LIMIT');
        }

        $indexed = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT rows must be arrays');
            }
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT row is missing rowid column {$rowIdColumn}");
            }
            foreach (array_keys($row) as $column) {
                if (str_starts_with((string) $column, '__sqlite_udl_')) {
                    throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT rows cannot use internal columns');
                }
            }
            $row['__sqlite_udl_index'] = $index;
            $indexed[] = $row;
        }

        $qualified = [];
        foreach ($indexed as $row) {
            $matched = $where(self::stripInternal($row));
            if ($matched !== null && !is_bool($matched)) {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT WHERE predicate must return bool or NULL');
            }
            if ($matched === true) {
                $qualified[] = $row;
            }
        }

        $ordered = $orderBy === [] ? $qualified : SQLiteSelectResult::orderBy(
            self::withOrderValues($qualified, $orderBy),
            self::orderByColumns($orderBy)
        );
        $selected = SQLiteSelectResult::limitOffset($ordered, $limit, max(0, $offset));
        $selectedIndexes = array_fill_keys(array_column($selected, '__sqlite_udl_index'), true);
        $mutation = [];
        foreach ($indexed as $row) {
            if (isset($selectedIndexes[$row['__sqlite_udl_index']])) {
                $mutation[] = $row;
            }
        }

        return ['indexed' => $indexed, 'qualified' => $qualified, 'selected' => $selected, 'mutation' => $mutation];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function stripInternal(array $row): array
    {
        foreach (array_keys($row) as $column) {
            if (str_starts_with((string) $column, '__sqlite_udl_')) {
                unset($row[$column]);
            }
        }

        return $row;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function ids(array $rows, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $value = $row[$rowIdColumn] ?? null;
            if (!is_int($value) && !is_string($value)) {
                throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT rowid column {$rowIdColumn} must contain integers or strings");
            }
            $ids[] = $value;
        }

        return $ids;
    }

    /**
     * @param array<string,mixed|callable(array<string,mixed>):mixed> $assignments
     * @return array<string,mixed>
     */
    private static function assignmentSummary(array $assignments): array
    {
        $summary = [];
        foreach ($assignments as $column => $value) {
            $summary[$column] = is_callable($value) ? 'callable' : $value;
        }

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{column:string,direction?:string,nulls?:string,expression?:string,value?:callable(array<string,mixed>):mixed}> $orderBy
     * @return list<array<string,mixed>>
     */
    private static function withOrderValues(array $rows, array $orderBy): array
    {
        $prepared = [];
        foreach ($rows as $row) {
            $base = self::stripInternal($row);
            foreach ($orderBy as $term) {
                if (isset($term['value'])) {
                    $row[$term['column']] = $term['value']($base);
                }
            }
            $prepared[] = $row;
        }

        return $prepared;
    }

    /**
     * @param list<array{column:string,direction?:string,nulls?:string,expression?:string,value?:callable(array<string,mixed>):mixed}> $orderBy
     * @return list<array{column:string,direction?:string,nulls?:string}>
     */
    private static function orderByColumns(array $orderBy): array
    {
        return array_map(
            static function (array $term): array {
                $summary = ['column' => $term['column']];
                if (isset($term['direction'])) {
                    $summary['direction'] = $term['direction'];
                }
                if (isset($term['nulls'])) {
                    $summary['nulls'] = $term['nulls'];
                }

                return $summary;
            },
            $orderBy,
        );
    }

    /**
     * @param list<array{column:string,direction?:string,nulls?:string,expression?:string,value?:callable(array<string,mixed>):mixed}> $orderBy
     * @return list<array{column:string,direction?:string,nulls?:string,expression?:string}>
     */
    private static function orderBySummary(array $orderBy): array
    {
        return array_map(
            static function (array $term): array {
                $summary = ['column' => $term['column']];
                if (isset($term['expression'])) {
                    $summary['expression'] = $term['expression'];
                }
                if (isset($term['direction'])) {
                    $summary['direction'] = $term['direction'];
                }
                if (isset($term['nulls'])) {
                    $summary['nulls'] = $term['nulls'];
                }

                return $summary;
            },
            $orderBy,
        );
    }
}
