<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectResult
{
    /**
     * @param iterable<array<string,mixed>> $rows
     * @param list<string>|null $distinctColumns
     * @param list<array{column:string,direction?:string,collation?:string,nulls?:string}> $orderBy
     * @return list<array<string,mixed>>
     */
    public static function execute(
        iterable $rows,
        ?array $distinctColumns = null,
        array $orderBy = [],
        ?int $limit = null,
        int $offset = 0,
        array $distinctCollations = []
    ): array {
        $result = array_values(is_array($rows) ? $rows : iterator_to_array($rows, false));

        if ($distinctColumns !== null) {
            $result = self::distinct($result, $distinctColumns, $distinctCollations);
        }
        if ($orderBy !== []) {
            $result = self::orderBy($result, $orderBy);
        }

        return self::limitOffset($result, $limit, $offset);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    public static function distinct(array $rows, array $columns, array $collations = []): array
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite SELECT DISTINCT needs at least one result column');
        }

        $seen = [];
        $result = [];
        foreach ($rows as $row) {
            $parts = [];
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite SELECT DISTINCT row is missing column {$column}");
                }
                $parts[] = self::valueKey(
                    $row[$column],
                    isset($collations[$column]) && is_string($collations[$column]) ? $collations[$column] : 'BINARY'
                );
            }

            $key = implode("\0", $parts);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{column:string,direction?:string,collation?:string,nulls?:string}> $terms
     * @return list<array<string,mixed>>
     */
    public static function orderBy(array $rows, array $terms): array
    {
        if ($terms === []) {
            return $rows;
        }

        foreach ($terms as $term) {
            if (($term['column'] ?? '') === '') {
                throw new \InvalidArgumentException('SQLite ORDER BY term needs a column');
            }
            $direction = strtoupper($term['direction'] ?? 'ASC');
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite ORDER BY direction must be ASC or DESC');
            }
            $collation = strtoupper($term['collation'] ?? 'BINARY');
            if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM', 'REVERSE'], true)) {
                throw new \InvalidArgumentException("Unsupported SQLite ORDER BY collation: {$term['collation']}");
            }
            $nulls = strtoupper($term['nulls'] ?? '');
            if ($nulls !== '' && $nulls !== 'FIRST' && $nulls !== 'LAST') {
                throw new \InvalidArgumentException('SQLite ORDER BY NULLS placement must be FIRST or LAST');
            }
        }

        $ordered = [];
        foreach ($rows as $index => $row) {
            foreach ($terms as $term) {
                if (!array_key_exists($term['column'], $row)) {
                    throw new \InvalidArgumentException("SQLite ORDER BY row is missing column {$term['column']}");
                }
                self::sortRank($row[$term['column']]);
            }
            $ordered[] = [$row, $index];
        }

        usort($ordered, static function (array $left, array $right) use ($terms): int {
            foreach ($terms as $term) {
                $direction = strtoupper($term['direction'] ?? 'ASC');
                $nullComparison = self::compareNullPlacement(
                    $left[0][$term['column']],
                    $right[0][$term['column']],
                    strtoupper($term['nulls'] ?? '')
                );
                if ($nullComparison !== null) {
                    return $nullComparison;
                }
                $comparison = self::compareSqlValues(
                    $left[0][$term['column']],
                    $right[0][$term['column']],
                    strtoupper($term['collation'] ?? 'BINARY'),
                );
                if ($comparison !== 0) {
                    return $direction === 'DESC' ? -$comparison : $comparison;
                }
            }

            return $left[1] <=> $right[1];
        });

        return array_column($ordered, 0);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public static function limitOffset(array $rows, ?int $limit, int $offset = 0): array
    {
        $offset = max(0, $offset);
        if ($limit !== null && $limit < 0) {
            return array_slice($rows, $offset);
        }

        return array_slice($rows, $offset, $limit);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param callable(array<string,mixed>):iterable<array<string,mixed>> $subquery
     * @return list<array<string,mixed>>
     */
    public static function whereExists(array $rows, callable $subquery, bool $negate = false): array
    {
        $result = [];
        foreach ($rows as $row) {
            $exists = false;
            foreach ($subquery($row) as $unused) {
                $exists = true;
                break;
            }

            if ($exists !== $negate) {
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param iterable<mixed> $subqueryValues
     * @return list<array<string,mixed>>
     */
    public static function whereIn(array $rows, string $column, iterable $subqueryValues, bool $negate = false): array
    {
        if ($column === '') {
            throw new \InvalidArgumentException('SQLite IN subquery filter needs a column');
        }

        $set = [];
        $hasNull = false;
        foreach ($subqueryValues as $value) {
            self::valueKey($value);
            if ($value === null) {
                $hasNull = true;
                continue;
            }
            $set[self::valueKey($value)] = true;
        }

        $result = [];
        foreach ($rows as $row) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite IN subquery filter row is missing column {$column}");
            }

            $value = $row[$column];
            self::valueKey($value);
            $matched = $value !== null && isset($set[self::valueKey($value)]);
            if ($negate) {
                if (!$matched && !$hasNull && $value !== null) {
                    $result[] = $row;
                }
                continue;
            }

            if ($matched) {
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @param callable(array<string,mixed>,array<string,mixed>):bool|null $predicate
     * @return list<array<string,mixed>>
     */
    public static function innerJoin(array $leftRows, array $rightRows, callable $predicate): array
    {
        $result = [];
        foreach ($leftRows as $left) {
            self::assertRow($left);
            foreach ($rightRows as $right) {
                self::assertRow($right);
                $matched = $predicate($left, $right);
                if ($matched !== null && !is_bool($matched)) {
                    throw new \InvalidArgumentException('SQLite INNER JOIN predicate must return bool or NULL');
                }
                if ($matched === true) {
                    $result[] = self::mergeJoinRows($left, $right);
                }
            }
        }

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @param callable(array<string,mixed>,array<string,mixed>):bool|null $predicate
     * @param list<string> $rightColumns
     * @return list<array<string,mixed>>
     */
    public static function leftJoin(array $leftRows, array $rightRows, callable $predicate, array $rightColumns): array
    {
        if ($leftRows === []) {
            return [];
        }
        if ($rightColumns === []) {
            $rightColumns = [];
        }

        $nullRight = [];
        foreach ($rightColumns as $column) {
            if ($column === '') {
                throw new \InvalidArgumentException('SQLite LEFT JOIN right-side column names cannot be empty');
            }
            $nullRight[$column] = null;
        }

        $result = [];
        foreach ($leftRows as $left) {
            self::assertRow($left);
            $matchedAny = false;
            foreach ($rightRows as $right) {
                self::assertRow($right);
                $matched = $predicate($left, $right);
                if ($matched !== null && !is_bool($matched)) {
                    throw new \InvalidArgumentException('SQLite LEFT JOIN predicate must return bool or NULL');
                }
                if ($matched === true) {
                    $matchedAny = true;
                    $result[] = self::mergeJoinRows($left, $right);
                }
            }
            if (!$matchedAny) {
                $result[] = self::mergeJoinRows($left, $nullRight);
            }
        }

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @return list<array<string,mixed>>
     */
    public static function crossJoin(array $leftRows, array $rightRows): array
    {
        return self::innerJoin($leftRows, $rightRows, static fn (): bool => true);
    }

    /**
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    public static function joinUsing(array $leftRows, array $rightRows, array $columns, bool $left = false): array
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite JOIN USING needs at least one column');
        }
        foreach ($columns as $column) {
            if ($column === '') {
                throw new \InvalidArgumentException('SQLite JOIN USING column names cannot be empty');
            }
        }

        $rightColumns = self::collectColumns($rightRows);
        $predicate = static function (array $leftRow, array $rightRow) use ($columns): bool {
            foreach ($columns as $column) {
                if (!array_key_exists($column, $leftRow) || !array_key_exists($column, $rightRow)) {
                    throw new \InvalidArgumentException("SQLite JOIN USING row is missing column {$column}");
                }
                if ($leftRow[$column] === null || $rightRow[$column] === null) {
                    return false;
                }
                if (self::valueKey($leftRow[$column]) !== self::valueKey($rightRow[$column])) {
                    return false;
                }
            }

            return true;
        };

        return $left
            ? self::leftJoin($leftRows, $rightRows, $predicate, $rightColumns)
            : self::innerJoin($leftRows, $rightRows, $predicate);
    }

    private static function valueKey(mixed $value, string $collation = 'BINARY'): string
    {
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
            $collation = strtoupper($collation);
            $value = match ($collation) {
                'BINARY' => $value,
                'NOCASE' => strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'),
                'RTRIM' => rtrim($value, ' '),
                'REVERSE' => strrev($value),
                default => throw new \InvalidArgumentException("Unsupported SQLite SELECT DISTINCT collation: {$collation}"),
            };
            return 'text:' . $value;
        }

        throw new \InvalidArgumentException('SQLite SELECT result values must be scalar, BLOB, or NULL');
    }

    private static function compareSqlValues(mixed $left, mixed $right, string $collation = 'BINARY'): int
    {
        $leftRank = self::sortRank($left);
        $rightRank = self::sortRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }
        if ($left === null && $right === null) {
            return 0;
        }
        if ($left === null || $right === null) {
            return $left === null ? -1 : 1;
        }

        $comparison = SQLiteAffinityComparison::compare($left, $right, 'NONE', 'NONE', $collation);
        if ($comparison === null) {
            throw new \InvalidArgumentException('SQLite ORDER BY comparison unexpectedly returned NULL for non-NULL values');
        }

        return $comparison;
    }

    private static function compareNullPlacement(mixed $left, mixed $right, string $nulls): ?int
    {
        if ($nulls === '' || ($left !== null && $right !== null)) {
            return null;
        }
        if ($left === null && $right === null) {
            return 0;
        }
        if ($nulls === 'FIRST') {
            return $left === null ? -1 : 1;
        }

        return $left === null ? 1 : -1;
    }

    private static function sortRank(mixed $value): int
    {
        return match (true) {
            $value === null => 0,
            is_int($value) || is_float($value) || is_bool($value) => 1,
            is_string($value) => 2,
            $value instanceof SQLiteBlobValue => 3,
            default => throw new \InvalidArgumentException('SQLite ORDER BY values must be scalar, BLOB, or NULL'),
        };
    }

    /**
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     * @return array<string,mixed>
     */
    private static function mergeJoinRows(array $left, array $right): array
    {
        $row = $left;
        foreach ($right as $column => $value) {
            if ($column === '') {
                throw new \InvalidArgumentException('SQLite JOIN result column names cannot be empty');
            }
            if (str_starts_with($column, 'right.')) {
                throw new \InvalidArgumentException("SQLite JOIN result has ambiguous column {$column}");
            }
            $target = array_key_exists($column, $row) ? 'right.' . $column : $column;
            if (array_key_exists($target, $row) && str_contains($column, '.')) {
                $suffix = 2;
                do {
                    $target = 'right' . $suffix . '.' . $column;
                    $suffix++;
                } while (array_key_exists($target, $row));
            }
            if (array_key_exists($target, $row)) {
                throw new \InvalidArgumentException("SQLite JOIN result has ambiguous column {$column}");
            }
            if (!self::isInternalMetadataColumn($target)) {
                self::valueKey($value);
            }
            $row[$target] = $value;
        }

        return $row;
    }

    private static function isInternalMetadataColumn(string $column): bool
    {
        return $column === '__sqlite_column_affinities'
            || $column === '__sqlite_column_collations'
            || str_starts_with($column, '__sqlite_hidden_wildcard_columns')
            || str_ends_with($column, '.__sqlite_column_affinities')
            || str_ends_with($column, '.__sqlite_column_collations');
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function assertRow(array $row): void
    {
        foreach ($row as $column => $value) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite SELECT result rows must have named columns');
            }
            if (self::isInternalMetadataColumn($column)) {
                continue;
            }
            self::valueKey($value);
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function collectColumns(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            foreach ($row as $column => $unused) {
                if (is_string($column) && self::isInternalMetadataColumn($column)) {
                    continue;
                }
                if (!in_array($column, $columns, true)) {
                    $columns[] = $column;
                }
            }
        }

        return $columns;
    }
}
