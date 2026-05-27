<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectQuery
{
    /**
     * @param array<string,mixed> $plan
     * @return list<array<string,mixed>>
     */
    public static function execute(array $plan): array
    {
        $rows = self::sourceRows($plan);
        $rows = self::applyJoins($rows, $plan['joins'] ?? []);

        if (array_key_exists('where', $plan)) {
            $where = $plan['where'];
            if (!is_array($where)) {
                throw new \InvalidArgumentException('SQLite SELECT query where clause must be a predicate');
            }
            $rows = SQLiteSelectPredicate::filter($rows, $where);
        }

        if (array_key_exists('groupBy', $plan)) {
            $groupBy = $plan['groupBy'];
            if (!is_array($groupBy)) {
                throw new \InvalidArgumentException('SQLite SELECT query groupBy clause must be an aggregate plan');
            }
            $rows = self::applyGroupBy($rows, $groupBy);
        }

        if (array_key_exists('select', $plan)) {
            $select = $plan['select'];
            if (!is_array($select) || !array_is_list($select)) {
                throw new \InvalidArgumentException('SQLite SELECT query select list must be a list');
            }
            foreach ($select as $expression) {
                if (!is_array($expression)) {
                    throw new \InvalidArgumentException('SQLite SELECT query select expressions must be arrays');
                }
            }
            [$rows, $select] = self::applyWindowExpressions($rows, $select);
            $rows = SQLiteSelectProjection::project($rows, $select);
        }

        $distinct = null;
        if (array_key_exists('distinct', $plan)) {
            if ($plan['distinct'] === true) {
                $distinct = $rows === [] ? null : array_keys($rows[0]);
            } elseif (!is_array($plan['distinct']) || !array_is_list($plan['distinct'])) {
                throw new \InvalidArgumentException('SQLite SELECT query distinct columns must be a list');
            } else {
                $distinct = $plan['distinct'];
            }
            if ($distinct !== null) {
                foreach ($distinct as $column) {
                    if (!is_string($column)) {
                        throw new \InvalidArgumentException('SQLite SELECT query distinct columns must be strings');
                    }
                }
            }
        }

        $orderBy = $plan['orderBy'] ?? [];
        if (!is_array($orderBy) || !array_is_list($orderBy)) {
            throw new \InvalidArgumentException('SQLite SELECT query orderBy terms must be a list');
        }

        $limit = null;
        if (array_key_exists('limit', $plan)) {
            if (!is_int($plan['limit'])) {
                throw new \InvalidArgumentException('SQLite SELECT query limit must be an integer');
            }
            $limit = $plan['limit'];
        }

        $offset = $plan['offset'] ?? 0;
        if (!is_int($offset)) {
            throw new \InvalidArgumentException('SQLite SELECT query offset must be an integer');
        }

        return SQLiteSelectResult::execute($rows, $distinct, $orderBy, $limit, $offset);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $select
     * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>}
     */
    private static function applyWindowExpressions(array $rows, array $select): array
    {
        foreach ($select as $index => $expression) {
            if (($expression['type'] ?? null) !== 'window') {
                continue;
            }

            $column = '__window' . $index;
            $values = self::windowValues($rows, $expression);
            if (count($values) !== count($rows)) {
                throw new \InvalidArgumentException('SQLite SELECT query window result row count mismatch');
            }
            foreach ($rows as $rowIndex => $row) {
                $row[$column] = $values[$rowIndex];
                $rows[$rowIndex] = $row;
            }

            $replacement = [
                'type' => 'column',
                'name' => $column,
                'alias' => isset($expression['alias']) && is_string($expression['alias']) && $expression['alias'] !== ''
                    ? $expression['alias']
                    : 'expr' . ($index + 1),
            ];
            $select[$index] = $replacement;
        }

        return [$rows, $select];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $expression
     * @return list<mixed>
     */
    private static function windowValues(array $rows, array $expression): array
    {
        $function = self::requiredString($expression, 'function', 'window expression');
        $arguments = self::expressionList($expression['arguments'] ?? [], 'window arguments');
        $partitionBy = self::expressionList($expression['partitionBy'] ?? [], 'window partition expressions');
        $orderBy = self::windowOrderList($expression['orderBy'] ?? []);

        $result = array_fill(0, count($rows), null);
        foreach (self::windowPartitions($rows, $partitionBy) as $partitionIndexes) {
            $orderedIndexes = self::orderWindowPartition($rows, $partitionIndexes, $orderBy);
            $orderedRows = array_map(static fn (int $rowIndex): array => $rows[$rowIndex], $orderedIndexes);
            $orderedValues = self::windowPartitionValues($function, $arguments, $orderedRows, $orderBy);
            foreach ($orderedIndexes as $offset => $rowIndex) {
                $result[$rowIndex] = $orderedValues[$offset];
            }
        }

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $partitionBy
     * @return list<list<int>>
     */
    private static function windowPartitions(array $rows, array $partitionBy): array
    {
        if ($partitionBy === []) {
            return [array_keys($rows)];
        }

        $partitions = [];
        $order = [];
        foreach ($rows as $index => $row) {
            $key = serialize(array_map(static fn (array $expression): mixed => SQLiteSelectExpression::evaluate($row, $expression), $partitionBy));
            if (!array_key_exists($key, $partitions)) {
                $partitions[$key] = [];
                $order[] = $key;
            }
            $partitions[$key][] = $index;
        }

        return array_map(static fn (string $key): array => $partitions[$key], $order);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int> $indexes
     * @param list<array{expression:array<string,mixed>,direction:string}> $orderBy
     * @return list<int>
     */
    private static function orderWindowPartition(array $rows, array $indexes, array $orderBy): array
    {
        if ($orderBy === []) {
            return array_values($indexes);
        }

        usort($indexes, static function (int $leftIndex, int $rightIndex) use ($rows, $orderBy): int {
            foreach ($orderBy as $term) {
                $comparison = self::compareSqlValues(
                    SQLiteSelectExpression::evaluate($rows[$leftIndex], $term['expression']),
                    SQLiteSelectExpression::evaluate($rows[$rightIndex], $term['expression'])
                );
                if ($comparison === 0) {
                    continue;
                }

                return $term['direction'] === 'DESC' ? -$comparison : $comparison;
            }

            return $leftIndex <=> $rightIndex;
        });

        return array_values($indexes);
    }

    /**
     * @param list<array<string,mixed>> $arguments
     * @param list<array<string,mixed>> $orderedRows
     * @param list<array{expression:array<string,mixed>,direction:string}> $orderBy
     * @return list<mixed>
     */
    private static function windowPartitionValues(string $function, array $arguments, array $orderedRows, array $orderBy): array
    {
        $orderKeys = array_keys($orderedRows);
        $peerKeys = $orderBy === []
            ? $orderKeys
            : array_map(static fn (array $row): mixed => SQLiteSelectExpression::evaluate($row, $orderBy[0]['expression']), $orderedRows);
        $values = $arguments !== []
            ? array_map(static fn (array $row): mixed => SQLiteSelectExpression::evaluate($row, $arguments[0]), $orderedRows)
            : $peerKeys;

        if (in_array($function, ['lag', 'lead', 'first_value', 'last_value', 'nth_value'], true) && $arguments === []) {
            throw new \InvalidArgumentException("SQLite SELECT query {$function}() needs a value argument");
        }

        return match ($function) {
            'row_number' => SQLiteWindowFunction::rowNumber($orderKeys),
            'rank' => SQLiteWindowFunction::rank($peerKeys),
            'dense_rank' => SQLiteWindowFunction::denseRank($peerKeys),
            'percent_rank' => SQLiteWindowFunction::percentRank($peerKeys),
            'cume_dist' => SQLiteWindowFunction::cumeDist($peerKeys),
            'ntile' => SQLiteWindowFunction::ntile($orderKeys, self::windowIntegerArgument($arguments, $orderedRows, 0, 'ntile')),
            'lag' => SQLiteWindowFunction::lag($values, self::windowIntegerArgument($arguments, $orderedRows, 1, 'lag', 1), self::windowDefaultArgument($arguments, $orderedRows, 2)),
            'lead' => SQLiteWindowFunction::lead($values, self::windowIntegerArgument($arguments, $orderedRows, 1, 'lead', 1), self::windowDefaultArgument($arguments, $orderedRows, 2)),
            'first_value' => SQLiteWindowFunction::firstValue($values),
            'last_value' => SQLiteWindowFunction::lastValue($values),
            'nth_value' => SQLiteWindowFunction::nthValue($values, self::windowIntegerArgument($arguments, $orderedRows, 1, 'nth_value')),
            default => throw new \InvalidArgumentException("SQLite SELECT query window function {$function} is not supported"),
        };
    }

    /**
     * @param list<array<string,mixed>> $arguments
     * @param list<array<string,mixed>> $rows
     */
    private static function windowIntegerArgument(array $arguments, array $rows, int $index, string $function, ?int $default = null): int
    {
        if (!array_key_exists($index, $arguments)) {
            if ($default !== null) {
                return $default;
            }
            throw new \InvalidArgumentException("SQLite SELECT query {$function}() needs integer argument " . ($index + 1));
        }
        $value = SQLiteSelectExpression::evaluate($rows[0] ?? [], $arguments[$index]);
        if (!is_int($value) && !is_bool($value)) {
            throw new \InvalidArgumentException("SQLite SELECT query {$function}() integer argument must be integer");
        }

        return (int) $value;
    }

    /**
     * @param list<array<string,mixed>> $arguments
     * @param list<array<string,mixed>> $rows
     */
    private static function windowDefaultArgument(array $arguments, array $rows, int $index): mixed
    {
        return array_key_exists($index, $arguments)
            ? SQLiteSelectExpression::evaluate($rows[0] ?? [], $arguments[$index])
            : null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function expressionList(mixed $value, string $context): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException("SQLite SELECT query {$context} must be a list");
        }
        foreach ($value as $expression) {
            if (!is_array($expression)) {
                throw new \InvalidArgumentException("SQLite SELECT query {$context} must contain expressions");
            }
        }

        return $value;
    }

    /**
     * @return list<array{expression:array<string,mixed>,direction:string}>
     */
    private static function windowOrderList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite SELECT query window order terms must be a list');
        }
        foreach ($value as $term) {
            if (!is_array($term) || !isset($term['expression']) || !is_array($term['expression'])) {
                throw new \InvalidArgumentException('SQLite SELECT query window order terms must include expressions');
            }
            if (($term['direction'] ?? null) !== 'ASC' && ($term['direction'] ?? null) !== 'DESC') {
                throw new \InvalidArgumentException('SQLite SELECT query window order direction must be ASC or DESC');
            }
        }

        return $value;
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
            default => throw new \InvalidArgumentException('SQLite SELECT query window ORDER BY values must be scalar, BLOB, or NULL'),
        };
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<array<string,mixed>>
     */
    private static function sourceRows(array $plan): array
    {
        if (!array_key_exists('from', $plan)) {
            throw new \InvalidArgumentException('SQLite SELECT query needs from rows');
        }
        if (!is_array($plan['from']) || !array_is_list($plan['from'])) {
            throw new \InvalidArgumentException('SQLite SELECT query from rows must be a list');
        }

        foreach ($plan['from'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite SELECT query source rows must be arrays');
            }
        }

        return $plan['from'];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param mixed $joins
     * @return list<array<string,mixed>>
     */
    private static function applyJoins(array $rows, mixed $joins): array
    {
        if ($joins === []) {
            return $rows;
        }
        if (!is_array($joins) || !array_is_list($joins)) {
            throw new \InvalidArgumentException('SQLite SELECT query joins must be a list');
        }

        foreach ($joins as $join) {
            if (!is_array($join)) {
                throw new \InvalidArgumentException('SQLite SELECT query join must be an array');
            }

            $type = strtoupper(self::requiredString($join, 'type', 'join'));
            if (isset($join['dynamicRows'])) {
                if (!is_callable($join['dynamicRows'])) {
                    throw new \InvalidArgumentException('SQLite SELECT query dynamic join rows must be callable');
                }
                $rows = self::applyDynamicJoin($rows, $join, $type);
                continue;
            }

            $rightRows = self::rightRows($join);
            $rows = match ($type) {
                'INNER' => SQLiteSelectResult::innerJoin($rows, $rightRows, self::requiredPredicate($join, 'INNER')),
                'LEFT' => SQLiteSelectResult::leftJoin($rows, $rightRows, self::requiredPredicate($join, 'LEFT'), self::rightColumns($join, $rightRows)),
                'RIGHT' => self::rightJoin($rows, $rightRows, self::requiredPredicate($join, 'RIGHT'), self::collectColumns($rows)),
                'FULL' => self::fullJoin($rows, $rightRows, self::requiredPredicate($join, 'FULL'), self::collectColumns($rows), self::rightColumns($join, $rightRows)),
                'CROSS' => SQLiteSelectResult::crossJoin($rows, $rightRows),
                'USING' => SQLiteSelectResult::joinUsing($rows, $rightRows, self::usingColumns($join), (bool) ($join['left'] ?? false)),
                default => throw new \InvalidArgumentException("SQLite SELECT query join type {$type} is not supported"),
            };
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $join
     * @return list<array<string,mixed>>
     */
    private static function applyDynamicJoin(array $rows, array $join, string $type): array
    {
        $dynamicRows = $join['dynamicRows'];
        $predicate = $type === 'CROSS' ? null : self::requiredPredicate($join, $type);
        $joined = [];
        $rightColumns = self::rightColumns($join, []);

        foreach ($rows as $left) {
            $rightRows = $dynamicRows($left);
            if (!is_array($rightRows) || !array_is_list($rightRows)) {
                throw new \InvalidArgumentException('SQLite SELECT query dynamic join rows must return a list');
            }

            $matched = false;
            foreach ($rightRows as $right) {
                if (!is_array($right)) {
                    throw new \InvalidArgumentException('SQLite SELECT query dynamic join rows must be arrays');
                }
                if ($type !== 'CROSS' && !$predicate($left, $right)) {
                    continue;
                }
                $matched = true;
                $joined[] = array_merge($left, $right);
            }

            if ($type === 'LEFT' && !$matched) {
                $nulls = [];
                foreach ($rightColumns as $column) {
                    $nulls[$column] = null;
                }
                $joined[] = array_merge($left, $nulls);
            }
        }

        if ($type === 'INNER' || $type === 'LEFT' || $type === 'CROSS') {
            return $joined;
        }

        throw new \InvalidArgumentException("SQLite SELECT query join type {$type} is not supported");
    }

    /**
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @param callable(array<string,mixed>,array<string,mixed>):bool|null $predicate
     * @param list<string> $leftColumns
     * @return list<array<string,mixed>>
     */
    private static function rightJoin(array $leftRows, array $rightRows, callable $predicate, array $leftColumns): array
    {
        $result = [];

        foreach ($rightRows as $right) {
            $matchedAny = false;
            foreach ($leftRows as $left) {
                $matched = $predicate($left, $right);
                if ($matched !== null && !is_bool($matched)) {
                    throw new \InvalidArgumentException('SQLite RIGHT JOIN predicate must return bool or NULL');
                }
                if ($matched !== true) {
                    continue;
                }
                $matchedAny = true;
                $result[] = array_merge($left, $right);
            }

            if (!$matchedAny) {
                $leftNulls = [];
                foreach ($leftColumns as $column) {
                    $leftNulls[$column] = null;
                }
                $result[] = array_merge($leftNulls, $right);
            }
        }

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @param callable(array<string,mixed>,array<string,mixed>):bool|null $predicate
     * @param list<string> $leftColumns
     * @param list<string> $rightColumns
     * @return list<array<string,mixed>>
     */
    private static function fullJoin(array $leftRows, array $rightRows, callable $predicate, array $leftColumns, array $rightColumns): array
    {
        $matchedRightIndexes = [];
        $result = [];

        foreach ($leftRows as $left) {
            $matchedAny = false;
            foreach ($rightRows as $rightIndex => $right) {
                $matched = $predicate($left, $right);
                if ($matched !== null && !is_bool($matched)) {
                    throw new \InvalidArgumentException('SQLite FULL JOIN predicate must return bool or NULL');
                }
                if ($matched !== true) {
                    continue;
                }
                $matchedAny = true;
                $matchedRightIndexes[$rightIndex] = true;
                $result[] = array_merge($left, $right);
            }

            if (!$matchedAny) {
                $rightNulls = [];
                foreach ($rightColumns as $column) {
                    $rightNulls[$column] = null;
                }
                $result[] = array_merge($left, $rightNulls);
            }
        }

        foreach ($rightRows as $rightIndex => $right) {
            if (isset($matchedRightIndexes[$rightIndex])) {
                continue;
            }
            $leftNulls = [];
            foreach ($leftColumns as $column) {
                $leftNulls[$column] = null;
            }
            $result[] = array_merge($leftNulls, $right);
        }

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $groupBy
     * @return list<array<string,mixed>>
     */
    private static function applyGroupBy(array $rows, array $groupBy): array
    {
        $groupColumn = self::groupColumns($groupBy);
        $valueColumn = self::requiredString($groupBy, 'valueColumn', 'groupBy');

        $summaries = SQLiteGroupedAggregate::summarize($rows, $groupColumn, $valueColumn);

        if (array_key_exists('having', $groupBy)) {
            if (!is_array($groupBy['having'])) {
                throw new \InvalidArgumentException('SQLite SELECT query HAVING clause must be a predicate');
            }
            $summaries = SQLiteSelectPredicate::filter($summaries, $groupBy['having']);
        }

        if (array_key_exists('orderBy', $groupBy)) {
            if (!is_array($groupBy['orderBy']) || !array_is_list($groupBy['orderBy'])) {
                throw new \InvalidArgumentException('SQLite SELECT query aggregate orderBy terms must be a list');
            }
            $summaries = SQLiteSelectResult::orderBy($summaries, $groupBy['orderBy']);
        }

        if (array_key_exists('limit', $groupBy)) {
            if (!is_int($groupBy['limit'])) {
                throw new \InvalidArgumentException('SQLite SELECT query aggregate limit must be an integer');
            }
            $offset = $groupBy['offset'] ?? 0;
            if (!is_int($offset)) {
                throw new \InvalidArgumentException('SQLite SELECT query aggregate offset must be an integer');
            }
            $summaries = SQLiteSelectResult::limitOffset($summaries, $groupBy['limit'], $offset);
        } elseif (array_key_exists('offset', $groupBy)) {
            throw new \InvalidArgumentException('SQLite SELECT query aggregate offset requires limit');
        }

        return $summaries;
    }

    /**
     * @param array<string,mixed> $join
     * @return list<array<string,mixed>>
     */
    private static function rightRows(array $join): array
    {
        if (!isset($join['rows']) || !is_array($join['rows']) || !array_is_list($join['rows'])) {
            throw new \InvalidArgumentException('SQLite SELECT query join rows must be a list');
        }
        foreach ($join['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite SELECT query join rows must be arrays');
            }
        }

        return $join['rows'];
    }

    /**
     * @param array<string,mixed> $join
     * @return callable(array<string,mixed>,array<string,mixed>):bool|null
     */
    private static function requiredPredicate(array $join, string $type): callable
    {
        if (!isset($join['predicate']) || !is_callable($join['predicate'])) {
            throw new \InvalidArgumentException("SQLite SELECT query {$type} join needs a predicate");
        }

        return $join['predicate'];
    }

    /**
     * @param array<string,mixed> $join
     * @param list<array<string,mixed>> $rightRows
     * @return list<string>
     */
    private static function rightColumns(array $join, array $rightRows): array
    {
        if (array_key_exists('rightColumns', $join)) {
            if (!is_array($join['rightColumns']) || !array_is_list($join['rightColumns'])) {
                throw new \InvalidArgumentException('SQLite SELECT query LEFT join rightColumns must be a list');
            }
            foreach ($join['rightColumns'] as $column) {
                if (!is_string($column)) {
                    throw new \InvalidArgumentException('SQLite SELECT query LEFT join rightColumns must be strings');
                }
            }

            return $join['rightColumns'];
        }

        $columns = [];
        foreach ($rightRows as $row) {
            foreach (array_keys($row) as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite SELECT query join column names must be non-empty strings');
                }
                if (!in_array($column, $columns, true)) {
                    $columns[] = $column;
                }
            }
        }

        return $columns;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function collectColumns(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite SELECT query rows must be arrays');
            }
            foreach (array_keys($row) as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite SELECT query row column names must be non-empty strings');
                }
                if (!in_array($column, $columns, true)) {
                    $columns[] = $column;
                }
            }
        }

        return $columns;
    }

    /**
     * @param array<string,mixed> $join
     * @return list<string>
     */
    private static function usingColumns(array $join): array
    {
        if (!isset($join['columns']) || !is_array($join['columns']) || !array_is_list($join['columns'])) {
            throw new \InvalidArgumentException('SQLite SELECT query USING join columns must be a list');
        }

        return $join['columns'];
    }

    private static function groupColumns(array $groupBy): string|array
    {
        if (array_key_exists('columns', $groupBy)) {
            if (!is_array($groupBy['columns']) || !array_is_list($groupBy['columns']) || $groupBy['columns'] === []) {
                throw new \InvalidArgumentException('SQLite SELECT query groupBy columns must be a non-empty list');
            }
            foreach ($groupBy['columns'] as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite SELECT query groupBy columns must be non-empty strings');
                }
            }

            return $groupBy['columns'];
        }

        return self::requiredString($groupBy, 'column', 'groupBy');
    }

    /**
     * @param array<string,mixed> $input
     */
    private static function requiredString(array $input, string $key, string $context): string
    {
        if (!isset($input[$key]) || !is_string($input[$key]) || $input[$key] === '') {
            throw new \InvalidArgumentException("SQLite SELECT query {$context} needs {$key}");
        }

        return $input[$key];
    }
}
