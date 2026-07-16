<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class DiffSqlRenderer
{
    /**
     * Render projected `DOLT_DIFF_*` rows as the SQL output used by
     * `dolt diff -r sql`.
     *
     * @param list<array<string, scalar|null>> $diffRows
     * @param array{filter?:string|null} $options
     */
    public function render(string $tableName, TableSchema $schema, array $diffRows, array $options = []): string
    {
        return implode("\n", $this->statements($tableName, $schema, $diffRows, $options));
    }

    /**
     * Return one SQL patch statement per projected diff row.
     *
     * @param list<array<string, scalar|null>> $diffRows
     * @param array{filter?:string|null} $options
     * @return list<string>
     */
    public function statements(string $tableName, TableSchema $schema, array $diffRows, array $options = []): array
    {
        if ($tableName === '') {
            throw new \InvalidArgumentException('Diff SQL table name must be a non-empty string.');
        }

        $filter = $this->normalizeFilter($options['filter'] ?? null);
        $statements = [];
        foreach ($diffRows as $row) {
            $diffType = $this->requiredDiffType($row['diff_type'] ?? null);
            if ($filter !== null && $diffType !== $filter) {
                continue;
            }

            $statement = match ($diffType) {
                TableDiff::DIFF_ADDED => $this->insertStatement($tableName, $schema, $row),
                TableDiff::DIFF_REMOVED => $this->deleteStatement($tableName, $schema, $row),
                TableDiff::DIFF_MODIFIED => $this->updateStatement($tableName, $schema, $row),
            };

            if ($statement !== null) {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    private function normalizeFilter(mixed $filter): ?string
    {
        if ($filter === null || $filter === '' || $filter === 'all') {
            return null;
        }
        if (!is_string($filter)) {
            throw new \InvalidArgumentException('Diff SQL filter must be a string.');
        }
        if ($filter === TableDiff::DIFF_ADDED || $filter === TableDiff::DIFF_MODIFIED) {
            return $filter;
        }
        if ($filter === TableDiff::DIFF_REMOVED || $filter === TableDeltaMatcher::DIFF_DROPPED) {
            return TableDiff::DIFF_REMOVED;
        }
        if ($filter === TableDeltaMatcher::DIFF_RENAMED) {
            return TableDeltaMatcher::DIFF_RENAMED;
        }

        throw new \InvalidArgumentException(
            "invalid filter: {$filter}. Valid values are: added, modified, renamed, dropped (or removed)"
        );
    }

    private function requiredDiffType(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('Diff SQL rows must include a non-empty diff_type.');
        }
        if (!in_array($value, [TableDiff::DIFF_ADDED, TableDiff::DIFF_REMOVED, TableDiff::DIFF_MODIFIED], true)) {
            throw new \InvalidArgumentException("Unsupported row diff_type: {$value}");
        }

        return $value;
    }

    /**
     * @param array<string, scalar|null> $row
     */
    private function insertStatement(string $tableName, TableSchema $schema, array $row): string
    {
        $columns = $schema->columns();
        $names = [];
        $values = [];
        foreach ($columns as $column) {
            $names[] = $this->quoteIdentifier($column['name']);
            $values[] = $this->sqlValue($row['to_' . $column['name']] ?? null, $column);
        }

        return 'INSERT INTO ' . $this->quoteIdentifier($tableName)
            . ' (' . implode(',', $names) . ') VALUES (' . implode(',', $values) . ');';
    }

    /**
     * @param array<string, scalar|null> $row
     */
    private function deleteStatement(string $tableName, TableSchema $schema, array $row): string
    {
        return 'DELETE FROM ' . $this->quoteIdentifier($tableName)
            . ' WHERE ' . implode(' AND ', $this->deletePredicates($schema, $row, 'from_')) . ';';
    }

    /**
     * @param array<string, scalar|null> $row
     */
    private function updateStatement(string $tableName, TableSchema $schema, array $row): ?string
    {
        if ($schema->isKeyless()) {
            throw new \InvalidArgumentException('Keyless diff SQL rows must be added or removed.');
        }

        $set = [];
        foreach ($schema->columns() as $column) {
            if ($column['primaryKey']) {
                continue;
            }

            $name = $column['name'];
            $fromValue = $row['from_' . $name] ?? null;
            $toValue = $row['to_' . $name] ?? null;
            if ($fromValue === $toValue) {
                continue;
            }

            $set[] = $this->quoteIdentifier($name) . '=' . $this->sqlValue($toValue, $column);
        }

        if ($set === []) {
            return null;
        }

        return 'UPDATE ' . $this->quoteIdentifier($tableName)
            . ' SET ' . implode(',', $set)
            . ' WHERE ' . implode(' AND ', $this->primaryKeyPredicates($schema, $row, 'to_')) . ';';
    }

    /**
     * @param array<string, scalar|null> $row
     * @return list<string>
     */
    private function primaryKeyPredicates(TableSchema $schema, array $row, string $prefix): array
    {
        $predicates = [];
        foreach ($schema->primaryKeyColumns() as $column) {
            $name = $column['name'];
            $key = $prefix . $name;
            if (!array_key_exists($key, $row) || $row[$key] === null) {
                throw new \InvalidArgumentException("Diff SQL row is missing primary key column {$key}.");
            }

            $predicates[] = $this->quoteIdentifier($name) . '=' . $this->sqlValue($row[$key], $column);
        }

        if ($predicates === []) {
            throw new \InvalidArgumentException('Diff SQL rendering requires at least one primary key column.');
        }

        return $predicates;
    }

    /**
     * @param array<string, scalar|null> $row
     * @return list<string>
     */
    private function deletePredicates(TableSchema $schema, array $row, string $prefix): array
    {
        if (!$schema->isKeyless()) {
            return $this->primaryKeyPredicates($schema, $row, $prefix);
        }

        $predicates = [];
        foreach ($schema->columns() as $column) {
            $name = $column['name'];
            $key = $prefix . $name;
            if (!array_key_exists($key, $row)) {
                throw new \InvalidArgumentException("Diff SQL row is missing keyless column {$key}.");
            }
            $predicates[] = $this->quoteIdentifier($name) . '=' . $this->sqlValue($row[$key], $column);
        }

        if ($predicates === []) {
            throw new \InvalidArgumentException('Diff SQL rendering requires at least one keyless column.');
        }

        return $predicates;
    }

    /**
     * @param array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>} $column
     */
    private function sqlValue(mixed $value, array $column): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if ($this->isBinaryColumn($column)) {
            if (!is_string($value)) {
                throw new \InvalidArgumentException("Binary SQL column {$column['name']} values must be strings or null.");
            }

            return '0x' . bin2hex($value);
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Diff SQL values must be scalar or null.');
        }

        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
    }

    /**
     * @param array{type:non-empty-string} $column
     */
    private function isBinaryColumn(array $column): bool
    {
        return preg_match('/\b(varbinary|binary|vector)\b/i', $column['type']) === 1;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if ($identifier === '') {
            throw new \InvalidArgumentException('SQL identifiers must be non-empty strings.');
        }

        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
