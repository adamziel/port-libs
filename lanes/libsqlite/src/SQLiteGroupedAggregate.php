<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteGroupedAggregate
{
    /**
     * @param iterable<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public static function summarize(iterable $rows, string|array $groupColumn, ?string $valueColumn, array $jsonAggregates = [], array $filteredAggregates = []): array
    {
        $groupColumns = self::groupColumns($groupColumn);
        $groups = [];
        foreach ($rows as $row) {
            $groupValues = [];
            foreach ($groupColumns as $column) {
                $groupValues[$column] = self::rowValue($row, $column, 'GROUP BY');
            }
            $value = $valueColumn === null ? null : self::rowValue($row, $valueColumn, 'aggregate');

            $key = self::compositeGroupKey($groupValues);
            $groups[$key] ??= [
                'group' => $groupValues[$groupColumns[0]],
                'groupValues' => $groupValues,
                'rows' => [],
                'values' => [],
            ];
            $groups[$key]['rows'][] = $row;
            if ($valueColumn !== null) {
                $groups[$key]['values'][] = $value;
            }
        }

        $groups = self::sortGroupsByKey($groups, $groupColumns);

        $summaries = [];
        foreach ($groups as $group) {
            $values = $group['values'];
            $summary = [
                'group' => $group['group'],
                'countAll' => SQLiteNumericAggregate::countAll($group['rows']),
                'countValue' => SQLiteNumericAggregate::countValue($values),
                'countDistinct' => SQLiteNumericAggregate::countDistinct($values),
                'sum' => SQLiteNumericAggregate::sum($values),
                'total' => SQLiteNumericAggregate::total($values),
                'avg' => SQLiteNumericAggregate::avg($values),
                'min' => SQLiteNumericAggregate::min($values),
                'max' => SQLiteNumericAggregate::max($values),
                'groupConcat' => SQLiteTextAggregate::groupConcat($values, '|'),
            ];
            foreach (self::invariantColumns($group['rows']) as $column => $value) {
                if (!array_key_exists($column, $summary)) {
                    $summary[$column] = $value;
                }
            }
            foreach (($group['rows'][0] ?? []) as $column => $value) {
                if (is_string($column) && !array_key_exists($column, $summary)) {
                    $summary[$column] = $value;
                }
            }
            foreach ($group['groupValues'] as $column => $value) {
                $summary[$column] = $value;
            }
            foreach ($jsonAggregates as $aggregate) {
                self::applyJsonAggregate($summary, $group['rows'], $aggregate);
            }
            foreach ($filteredAggregates as $aggregate) {
                self::applyFilteredAggregate($summary, $group['rows'], $aggregate);
            }
            $summaries[] = $summary;
        }

        return $summaries;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public static function summarizeAll(array $rows, ?string $valueColumn, array $jsonAggregates = [], array $filteredAggregates = []): array
    {
        if ($valueColumn !== null) {
            foreach ($rows as $row) {
                self::rowValue($row, $valueColumn, 'aggregate');
            }
            $values = array_map(static fn (array $row): mixed => self::rowValue($row, $valueColumn, 'aggregate'), $rows);
        } else {
            $values = [];
        }

        $summary = [
            'countAll' => SQLiteNumericAggregate::countAll($rows),
            'countValue' => SQLiteNumericAggregate::countValue($values),
            'countDistinct' => SQLiteNumericAggregate::countDistinct($values),
            'sum' => SQLiteNumericAggregate::sum($values),
            'total' => SQLiteNumericAggregate::total($values),
            'avg' => SQLiteNumericAggregate::avg($values),
            'min' => SQLiteNumericAggregate::min($values),
            'max' => SQLiteNumericAggregate::max($values),
            'groupConcat' => SQLiteTextAggregate::groupConcat($values, '|'),
        ];
        foreach (self::invariantColumns($rows) as $column => $value) {
            if (!array_key_exists($column, $summary)) {
                $summary[$column] = $value;
            }
        }
        foreach (($rows[0] ?? []) as $column => $value) {
            if (is_string($column) && !array_key_exists($column, $summary)) {
                $summary[$column] = $value;
            }
        }
        foreach ($jsonAggregates as $aggregate) {
            self::applyJsonAggregate($summary, $rows, $aggregate);
        }
        foreach ($filteredAggregates as $aggregate) {
            self::applyFilteredAggregate($summary, $rows, $aggregate);
        }

        return [$summary];
    }

    /**
     * @param array<string,mixed> $summary
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $aggregate
     */
    private static function applyFilteredAggregate(array &$summary, array $rows, array $aggregate): void
    {
        $summaryColumn = $aggregate['summaryColumn'] ?? null;
        $function = $aggregate['function'] ?? null;
        $argument = $aggregate['argument'] ?? null;
        if (!is_string($summaryColumn) || $summaryColumn === '' || !is_string($function) || !is_array($argument)) {
            throw new \InvalidArgumentException('SQLite filtered aggregate plan is malformed');
        }

        $filteredValues = [];
        foreach ($rows as $row) {
            if (isset($aggregate['filter']) && is_array($aggregate['filter']) && SQLiteSelectPredicate::filter([$row], $aggregate['filter']) === []) {
                continue;
            }

            if ($function === 'count' && (($argument['type'] ?? null) === 'wildcard')) {
                $filteredValues[] = 1;
                continue;
            }
            if ($function === 'count' && (($argument['type'] ?? null) === 'literal')) {
                $filteredValues[] = ($argument['value'] ?? null) === null ? null : 1;
                continue;
            }

            $filteredValues[] = SQLiteSelectExpression::evaluate($row, $argument);
        }

        $summary[$summaryColumn] = match ($function) {
            'count' => ($aggregate['distinct'] ?? false) === true
                ? SQLiteNumericAggregate::countDistinct($filteredValues)
                : SQLiteNumericAggregate::countValue($filteredValues),
            'sum' => SQLiteNumericAggregate::sum($filteredValues),
            'total' => SQLiteNumericAggregate::total($filteredValues),
            'avg' => SQLiteNumericAggregate::avg($filteredValues),
            'min' => SQLiteNumericAggregate::min($filteredValues),
            'max' => SQLiteNumericAggregate::max($filteredValues),
            'group_concat' => SQLiteTextAggregate::groupConcat($filteredValues, '|'),
            default => throw new \InvalidArgumentException("SQLite filtered aggregate {$function} is not supported"),
        };
    }

    /**
     * @param array<string,mixed> $summary
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $aggregate
     */
    private static function applyJsonAggregate(array &$summary, array $rows, array $aggregate): void
    {
        $column = $aggregate['column'] ?? null;
        $summaryColumn = $aggregate['summaryColumn'] ?? null;
        $function = $aggregate['function'] ?? null;
        if (!is_string($column) || !is_string($summaryColumn) || !is_string($function)) {
            throw new \InvalidArgumentException('SQLite JSON aggregate plan is malformed');
        }

        $filtered = [];
        foreach ($rows as $position => $row) {
            $valueColumn = $aggregate['valueColumn'] ?? null;
            $value = self::rowValue($row, $column, 'JSON aggregate');
            if (is_string($valueColumn)) {
                $value = [$value, self::rowValue($row, $valueColumn, 'JSON aggregate')];
            }
            if (isset($aggregate['filter']) && is_array($aggregate['filter']) && SQLiteSelectPredicate::filter([$row], $aggregate['filter']) === []) {
                continue;
            }
            $filtered[] = ['row' => $row, 'position' => $position, 'value' => $value];
        }

        $orderTerms = self::jsonAggregateOrderTerms($aggregate);
        if ($orderTerms !== []) {
            usort($filtered, static function (array $left, array $right) use ($orderTerms): int {
                foreach ($orderTerms as $orderTerm) {
                    $leftValue = self::jsonAggregateOrderValue($left['row'], $orderTerm);
                    $rightValue = self::jsonAggregateOrderValue($right['row'], $orderTerm);
                    $comparison = self::compareSqlValues($leftValue, $rightValue);
                    if ($comparison === 0) {
                        continue;
                    }
                    if ($orderTerm['direction'] === 'DESC') {
                        $comparison = -$comparison;
                    }

                    return $comparison;
                }

                return $left['position'] <=> $right['position'];
            });
        }

        $values = [];
        $seen = [];
        $distinct = ($aggregate['distinct'] ?? false) === true;
        foreach ($filtered as $entry) {
            $value = $entry['value'];
            if ($distinct) {
                $key = self::distinctJsonAggregateKey($value);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
            }
            $values[] = $value;
        }
        $summary[$summaryColumn] = str_contains($function, '_object')
            ? SQLiteJsonAggregate::jsonGroupObjectSqlFunction($function, $values)
            : SQLiteJsonAggregate::jsonGroupArraySqlFunction($function, $values);
    }

    /**
     * @param array<string,mixed> $aggregate
     * @return list<array{column?:string,expression?:array<string,mixed>,direction:string}>
     */
    private static function jsonAggregateOrderTerms(array $aggregate): array
    {
        if (isset($aggregate['orderByTerms'])) {
            if (!is_array($aggregate['orderByTerms']) || !array_is_list($aggregate['orderByTerms'])) {
                throw new \InvalidArgumentException('SQLite JSON aggregate ORDER BY terms are malformed');
            }
            $terms = [];
            foreach ($aggregate['orderByTerms'] as $term) {
                if (!is_array($term)) {
                    throw new \InvalidArgumentException('SQLite JSON aggregate ORDER BY column is malformed');
                }
                if (isset($term['expression'])) {
                    if (!is_array($term['expression'])) {
                        throw new \InvalidArgumentException('SQLite JSON aggregate ORDER BY expression is malformed');
                    }
                    $order = ['expression' => $term['expression']];
                } elseif (isset($term['column']) && is_string($term['column'])) {
                    $order = ['column' => $term['column']];
                } else {
                    throw new \InvalidArgumentException('SQLite JSON aggregate ORDER BY column is malformed');
                }
                $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
                if ($direction !== 'ASC' && $direction !== 'DESC') {
                    throw new \InvalidArgumentException('SQLite JSON aggregate ORDER BY direction must be ASC or DESC');
                }
                $order['direction'] = $direction;
                $terms[] = $order;
            }

            return $terms;
        }

        if (!isset($aggregate['orderBy'])) {
            return [];
        }
        $orderColumn = $aggregate['orderBy'];
        if (!is_string($orderColumn)) {
            throw new \InvalidArgumentException('SQLite JSON aggregate ORDER BY column is malformed');
        }
        $direction = strtoupper((string) ($aggregate['orderDirection'] ?? 'ASC'));
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new \InvalidArgumentException('SQLite JSON aggregate ORDER BY direction must be ASC or DESC');
        }

        return [['column' => $orderColumn, 'direction' => $direction]];
    }

    /**
     * @param array<string,mixed> $row
     * @param array{column?:string,expression?:array<string,mixed>,direction:string} $orderTerm
     */
    private static function jsonAggregateOrderValue(array $row, array $orderTerm): mixed
    {
        if (isset($orderTerm['expression'])) {
            return SQLiteSelectExpression::evaluate($row, $orderTerm['expression']);
        }

        $orderColumn = $orderTerm['column'] ?? null;
        if (!is_string($orderColumn) || $orderColumn === '') {
            throw new \InvalidArgumentException("SQLite JSON aggregate ORDER BY row is missing column {$orderColumn}");
        }

        return self::rowValue($row, $orderColumn, 'JSON aggregate ORDER BY');
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowValue(array $row, string $column, string $context): mixed
    {
        if (array_key_exists($column, $row)) {
            return $row[$column];
        }

        if (!str_contains($column, '.')) {
            $matches = [];
            $suffix = '.' . $column;
            foreach ($row as $candidate => $value) {
                if (is_string($candidate) && str_ends_with($candidate, $suffix)) {
                    $matches[] = $value;
                }
            }
            if (count($matches) === 1) {
                return $matches[0];
            }
            if (count($matches) > 1) {
                throw new \InvalidArgumentException("SQLite {$context} row column {$column} is ambiguous");
            }
        }

        throw new \InvalidArgumentException("SQLite {$context} row is missing column {$column}");
    }

    private static function distinctJsonAggregateKey(mixed $value): string
    {
        if ($value instanceof SQLiteBlobValue) {
            return 'blob:' . $value->bytes;
        }
        if ($value instanceof SQLiteJsonSubtypeValue) {
            return 'json:' . $value->json;
        }
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return 'bool:' . ($value ? '1' : '0');
        }
        if (is_int($value)) {
            return 'int:' . $value;
        }
        if (is_float($value)) {
            return 'float:' . sprintf('%.17G', $value);
        }
        if (is_string($value)) {
            return 'text:' . $value;
        }

        return 'json:' . json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param list<array<string,mixed>> $summaries
     * @return list<array<string,mixed>>
     */
    public static function havingCountAtLeast(array $summaries, int $minimum): array
    {
        return array_values(array_filter(
            $summaries,
            static fn (array $summary): bool => (int) ($summary['countAll'] ?? 0) >= $minimum
        ));
    }

    /**
     * @param list<array<string,mixed>> $summaries
     * @return list<array<string,mixed>>
     */
    public static function havingSumGreaterThan(array $summaries, int|float $threshold): array
    {
        return array_values(array_filter(
            $summaries,
            static fn (array $summary): bool => $summary['sum'] !== null && (float) $summary['sum'] > (float) $threshold
        ));
    }

    /**
     * @param list<array<string,mixed>> $summaries
     * @return list<array<string,mixed>>
     */
    public static function orderBy(array $summaries, string $column, string $direction = 'ASC'): array
    {
        $direction = strtoupper($direction);
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new \InvalidArgumentException('SQLite grouped aggregate ORDER BY direction must be ASC or DESC');
        }

        foreach ($summaries as $summary) {
            if (!array_key_exists($column, $summary)) {
                throw new \InvalidArgumentException("SQLite grouped aggregate ORDER BY column is missing: {$column}");
            }
        }

        $ordered = [];
        foreach ($summaries as $index => $summary) {
            $ordered[] = [$summary, $index];
        }

        usort($ordered, static function (array $left, array $right) use ($column, $direction): int {
            $comparison = self::compareSqlValues($left[0][$column], $right[0][$column]);
            if ($comparison === 0) {
                $comparison = $left[1] <=> $right[1];
            }

            return $direction === 'DESC' ? -$comparison : $comparison;
        });

        return array_column($ordered, 0);
    }

    private static function groupKey(mixed $value): string
    {
        if ($value instanceof SQLiteBlobValue) {
            return 'blob:' . $value->bytes;
        }
        if ($value === null) {
            return 'null:';
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

        throw new \InvalidArgumentException('SQLite GROUP BY values must be scalar, BLOB, or NULL');
    }

    /**
     * @param array<string,array{group:mixed,groupValues:array<string,mixed>,rows:list<array<string,mixed>>,values:list<mixed>}> $groups
     * @param non-empty-list<string> $groupColumns
     * @return list<array{group:mixed,groupValues:array<string,mixed>,rows:list<array<string,mixed>>,values:list<mixed>}>
     */
    private static function sortGroupsByKey(array $groups, array $groupColumns): array
    {
        $ordered = array_values($groups);
        usort($ordered, static function (array $left, array $right) use ($groupColumns): int {
            foreach ($groupColumns as $column) {
                $comparison = self::compareSqlValues($left['groupValues'][$column], $right['groupValues'][$column]);
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return 0;
        });

        return $ordered;
    }

    /**
     * @return non-empty-list<string>
     */
    private static function groupColumns(string|array $groupColumn): array
    {
        if (is_string($groupColumn)) {
            if ($groupColumn === '') {
                throw new \InvalidArgumentException('SQLite GROUP BY column must be a non-empty string');
            }

            return [$groupColumn];
        }

        if (!array_is_list($groupColumn) || $groupColumn === []) {
            throw new \InvalidArgumentException('SQLite GROUP BY columns must be a non-empty list');
        }

        $columns = [];
        foreach ($groupColumn as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite GROUP BY columns must be non-empty strings');
            }
            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function compositeGroupKey(array $values): string
    {
        $parts = [];
        foreach ($values as $column => $value) {
            $parts[] = $column . '=' . self::groupKey($value);
        }

        return implode("\0", $parts);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function invariantColumns(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $first = $rows[0];
        $invariant = [];
        foreach ($first as $column => $value) {
            $same = true;
            foreach ($rows as $row) {
                if (!array_key_exists($column, $row) || $row[$column] !== $value) {
                    $same = false;
                    break;
                }
            }
            if ($same) {
                $invariant[$column] = $value;
            }
        }

        return $invariant;
    }

    private static function compareSqlValues(mixed $left, mixed $right): int
    {
        $leftRank = self::sortRank($left);
        $rightRank = self::sortRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }
        if ($left === null || $right === null) {
            return 0;
        }
        if ($left instanceof SQLiteBlobValue && $right instanceof SQLiteBlobValue) {
            return strcmp($left->bytes, $right->bytes);
        }
        if ((is_int($left) || is_float($left) || is_bool($left)) && (is_int($right) || is_float($right) || is_bool($right))) {
            return ((float) $left) <=> ((float) $right);
        }

        return strcmp((string) $left, (string) $right);
    }

    private static function sortRank(mixed $value): int
    {
        return match (true) {
            $value === null => 0,
            is_int($value) || is_float($value) || is_bool($value) => 1,
            is_string($value) => 2,
            $value instanceof SQLiteBlobValue => 3,
            default => throw new \InvalidArgumentException('SQLite grouped aggregate ORDER BY values must be scalar, BLOB, or NULL'),
        };
    }
}
