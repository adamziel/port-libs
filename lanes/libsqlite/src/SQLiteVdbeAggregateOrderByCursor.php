<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVdbeAggregateOrderByCursor
{
    /** @var list<array{key:list<mixed>,rows:list<array<string,mixed>>,ordered:list<array<string,mixed>>}> */
    private array $groups;
    private int $position = 0;

    /**
     * @param iterable<array<string,mixed>> $rows
     * @param non-empty-list<string> $groupColumns
     * @param list<array{column:string,direction?:string,collation?:string,nulls?:string}> $orderBy
     */
    public function __construct(iterable $rows, private array $groupColumns, private array $orderBy)
    {
        $this->assertColumns($groupColumns, 'SQLite VDBE aggregate ORDER BY group columns');
        $this->assertOrderBy($orderBy);
        $this->groups = $this->clusterRows($rows);
    }

    public function eof(): bool
    {
        return !isset($this->groups[$this->position]);
    }

    public function next(): void
    {
        if (!$this->eof()) {
            $this->position++;
        }
    }

    /**
     * @return list<mixed>
     */
    public function currentGroupKey(): array
    {
        return $this->current()['key'];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function currentRows(): array
    {
        return $this->current()['rows'];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function currentOrderedRows(): array
    {
        return $this->current()['ordered'];
    }

    /**
     * @return list<mixed>
     */
    public function currentValues(string $column): array
    {
        $values = [];
        foreach ($this->currentOrderedRows() as $row) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite VDBE aggregate ORDER BY value column is missing: {$column}");
            }
            $values[] = $row[$column];
        }

        return $values;
    }

    public function currentGroupConcat(string $column, mixed $separator = ','): ?string
    {
        return SQLiteTextAggregate::groupConcat($this->currentValues($column), $separator);
    }

    /**
     * @return array{key:list<mixed>,rowCount:int,orderedRowids:list<mixed>,concat:?string}
     */
    public function currentSummary(string $valueColumn, string $rowidColumn = 'rowid'): array
    {
        $orderedRowids = [];
        foreach ($this->currentOrderedRows() as $row) {
            $orderedRowids[] = $row[$rowidColumn] ?? null;
        }

        return [
            'key' => $this->currentGroupKey(),
            'rowCount' => count($this->currentRows()),
            'orderedRowids' => $orderedRowids,
            'concat' => $this->currentGroupConcat($valueColumn, '|'),
        ];
    }

    /**
     * @return list<array{key:list<mixed>,rowCount:int,orderedRowids:list<mixed>,concat:?string}>
     */
    public function drainSummaries(string $valueColumn, string $rowidColumn = 'rowid'): array
    {
        $summaries = [];
        while (!$this->eof()) {
            $summaries[] = $this->currentSummary($valueColumn, $rowidColumn);
            $this->next();
        }

        return $summaries;
    }

    /**
     * @return array{key:list<mixed>,rows:list<array<string,mixed>>,ordered:list<array<string,mixed>>}
     */
    private function current(): array
    {
        if ($this->eof()) {
            throw new \OutOfBoundsException('SQLite VDBE aggregate ORDER BY cursor is at EOF');
        }

        return $this->groups[$this->position];
    }

    /**
     * @param iterable<array<string,mixed>> $rows
     * @return list<array{key:list<mixed>,rows:list<array<string,mixed>>,ordered:list<array<string,mixed>>}>
     */
    private function clusterRows(iterable $rows): array
    {
        $clusters = [];
        $groupOrder = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite VDBE aggregate ORDER BY rows must be arrays');
            }
            $keyValues = [];
            foreach ($this->groupColumns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite VDBE aggregate ORDER BY row is missing group column {$column}");
                }
                $keyValues[] = $row[$column];
            }
            foreach ($this->orderBy as $term) {
                if (!array_key_exists($term['column'], $row)) {
                    throw new \InvalidArgumentException("SQLite VDBE aggregate ORDER BY row is missing order column {$term['column']}");
                }
                if ($row[$term['column']] !== null) {
                    $this->sortRank($row[$term['column']]);
                }
            }

            $key = $this->groupKey($keyValues);
            if (!array_key_exists($key, $clusters)) {
                $clusters[$key] = ['key' => $keyValues, 'rows' => []];
                $groupOrder[] = $key;
            }
            $clusters[$key]['rows'][] = $row;
        }

        $groups = [];
        foreach ($groupOrder as $key) {
            $groupRows = $clusters[$key]['rows'];
            $groups[] = [
                'key' => $clusters[$key]['key'],
                'rows' => $groupRows,
                'ordered' => $this->orderRows($groupRows),
            ];
        }

        usort($groups, fn (array $left, array $right): int => $this->compareGroupKeys($left['key'], $right['key']));

        return $groups;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function orderRows(array $rows): array
    {
        $ordered = [];
        foreach ($rows as $sequence => $row) {
            $ordered[] = [$row, $sequence];
        }

        usort($ordered, function (array $left, array $right): int {
            foreach ($this->orderBy as $term) {
                $comparison = $this->compareSqlValues(
                    $left[0][$term['column']],
                    $right[0][$term['column']],
                    $term['collation'] ?? 'BINARY',
                    $term['nulls'] ?? null,
                );
                if ($comparison !== 0) {
                    return ($term['direction'] ?? 'ASC') === 'DESC' ? -$comparison : $comparison;
                }
            }

            return $left[1] <=> $right[1];
        });

        return array_map(static fn (array $entry): array => $entry[0], $ordered);
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     */
    private function compareGroupKeys(array $left, array $right): int
    {
        foreach ($left as $index => $value) {
            $comparison = $this->compareSqlValues($value, $right[$index] ?? null, 'BINARY', null);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }

    private function compareSqlValues(mixed $left, mixed $right, string $collation, ?string $nulls): int
    {
        if ($left === null || $right === null) {
            if ($left === null && $right === null) {
                return 0;
            }
            if ($nulls === 'LAST') {
                return $left === null ? 1 : -1;
            }

            return $left === null ? -1 : 1;
        }

        $leftRank = $this->sortRank($left);
        $rightRank = $this->sortRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }
        if ($left instanceof SQLiteBlobValue && $right instanceof SQLiteBlobValue) {
            return strcmp($left->bytes, $right->bytes);
        }
        if ((is_int($left) || is_float($left) || is_bool($left)) && (is_int($right) || is_float($right) || is_bool($right))) {
            return ((float) $left) <=> ((float) $right);
        }

        $leftText = $this->valueText($left);
        $rightText = $this->valueText($right);
        return match ($collation) {
            'BINARY' => strcmp($leftText, $rightText),
            'NOCASE' => strcasecmp($leftText, $rightText),
            default => throw new \InvalidArgumentException("SQLite VDBE aggregate ORDER BY collation is unsupported: {$collation}"),
        };
    }

    private function sortRank(mixed $value): int
    {
        return match (true) {
            is_int($value) || is_float($value) || is_bool($value) => 1,
            is_string($value) => 2,
            $value instanceof SQLiteBlobValue => 3,
            default => throw new \InvalidArgumentException('SQLite VDBE aggregate ORDER BY values must be scalar, BLOB, or NULL'),
        };
    }

    private function valueText(mixed $value): string
    {
        if ($value instanceof SQLiteBlobValue) {
            return $value->bytes;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException('SQLite VDBE aggregate ORDER BY values must be scalar, BLOB, or NULL');
    }

    /**
     * @param list<mixed> $values
     */
    private function groupKey(array $values): string
    {
        return implode("\0", array_map(function (mixed $value): string {
            if ($value === null) {
                return 'null:';
            }
            if ($value instanceof SQLiteBlobValue) {
                return 'blob:' . $value->bytes;
            }
            if (is_bool($value) || is_int($value)) {
                return 'integer:' . (int) $value;
            }
            if (is_float($value)) {
                return 'real:' . sprintf('%.17G', $value);
            }
            if (is_string($value)) {
                return 'text:' . $value;
            }

            throw new \InvalidArgumentException('SQLite VDBE aggregate ORDER BY group values must be scalar, BLOB, or NULL');
        }, $values));
    }

    /**
     * @param list<string> $columns
     */
    private function assertColumns(array $columns, string $label): void
    {
        if ($columns === [] || !array_is_list($columns)) {
            throw new \InvalidArgumentException("{$label} must be a non-empty list");
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException("{$label} must be non-empty strings");
            }
        }
    }

    /**
     * @param list<array{column:string,direction?:string,collation?:string,nulls?:string}> $orderBy
     */
    private function assertOrderBy(array $orderBy): void
    {
        if ($orderBy === [] || !array_is_list($orderBy)) {
            throw new \InvalidArgumentException('SQLite VDBE aggregate ORDER BY terms must be a non-empty list');
        }
        foreach ($orderBy as $term) {
            if (!is_array($term) || !isset($term['column']) || !is_string($term['column']) || $term['column'] === '') {
                throw new \InvalidArgumentException('SQLite VDBE aggregate ORDER BY term needs a column');
            }
            $direction = $term['direction'] ?? 'ASC';
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite VDBE aggregate ORDER BY direction must be ASC or DESC');
            }
            $collation = $term['collation'] ?? 'BINARY';
            if ($collation !== 'BINARY' && $collation !== 'NOCASE') {
                throw new \InvalidArgumentException('SQLite VDBE aggregate ORDER BY collation must be BINARY or NOCASE');
            }
            $nulls = $term['nulls'] ?? null;
            if ($nulls !== null && $nulls !== 'FIRST' && $nulls !== 'LAST') {
                throw new \InvalidArgumentException('SQLite VDBE aggregate ORDER BY NULLS must be FIRST or LAST');
            }
        }
    }
}
