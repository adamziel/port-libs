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
        $earlyLimitApplied = false;

        if (array_key_exists('where', $plan)) {
            $where = $plan['where'];
            if (!is_array($where)) {
                throw new \InvalidArgumentException('SQLite SELECT query where clause must be a predicate');
            }
            if (array_key_exists('select', $plan) && is_array($plan['select']) && array_is_list($plan['select'])) {
                $rows = self::materializeFilterAliases($rows, $plan['select'], $where);
            }
            $rows = SQLiteSelectPredicate::filter($rows, $where);
        }

        if (
            !array_key_exists('groupBy', $plan)
            && !array_key_exists('distinct', $plan)
            && !array_key_exists('orderBy', $plan)
            && array_key_exists('limit', $plan)
            && is_int($plan['limit'])
        ) {
            $earlyOffset = $plan['offset'] ?? 0;
            if (!is_int($earlyOffset)) {
                throw new \InvalidArgumentException('SQLite SELECT query offset must be an integer');
            }
            $rows = SQLiteSelectResult::limitOffset($rows, $plan['limit'], $earlyOffset);
            $earlyLimitApplied = true;
        }

        if (array_key_exists('groupBy', $plan)) {
            $groupBy = $plan['groupBy'];
            if (!is_array($groupBy)) {
                throw new \InvalidArgumentException('SQLite SELECT query groupBy clause must be an aggregate plan');
            }
            $rows = self::applyGroupBy($rows, $groupBy, $plan);
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
            $limit = $earlyLimitApplied ? null : $plan['limit'];
        }

        $offset = $earlyLimitApplied ? 0 : ($plan['offset'] ?? 0);
        if (!is_int($offset)) {
            throw new \InvalidArgumentException('SQLite SELECT query offset must be an integer');
        }

        return SQLiteSelectResult::execute($rows, $distinct, $orderBy, $limit, $offset);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $select
     * @return list<array<string,mixed>>
     */
    private static function materializeFilterAliases(array $rows, array $select, array $where): array
    {
        $aliases = [];
        foreach ($select as $expression) {
            $alias = $expression['alias'] ?? null;
            if (!is_string($alias) || $alias === '' || self::isFilterAliasUnsafe($expression)) {
                continue;
            }
            if (!self::predicateReferencesColumn($where, $alias)) {
                continue;
            }
            $sourceExpression = $expression['sourceExpression'] ?? $expression;
            if (!is_array($sourceExpression)) {
                continue;
            }
            unset($sourceExpression['alias'], $sourceExpression['hiddenOrderColumn'], $sourceExpression['sourceExpression']);
            $aliases[$alias] = $sourceExpression;
        }
        if ($aliases === []) {
            return $rows;
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($aliases as $alias => $expression) {
                if (array_key_exists($alias, $row)) {
                    continue;
                }
                $row[$alias] = SQLiteSelectExpression::evaluate($row, $expression);
            }
            $rows[$rowIndex] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $predicate
     */
    private static function predicateReferencesColumn(array $predicate, string $column): bool
    {
        if (($predicate['type'] ?? null) === 'column' && ($predicate['name'] ?? null) === $column) {
            return true;
        }
        if (($predicate['operator'] ?? null) === 'TRUTH' && isset($predicate['expression']) && is_array($predicate['expression'])) {
            return self::predicateReferencesColumn($predicate['expression'], $column);
        }
        foreach (['left', 'right', 'operand', 'predicate', 'expression'] as $key) {
            if (isset($predicate[$key]) && is_array($predicate[$key]) && self::predicateReferencesColumn($predicate[$key], $column)) {
                return true;
            }
        }
        foreach (['terms', 'values', 'arguments'] as $key) {
            if (!isset($predicate[$key]) || !is_array($predicate[$key]) || !array_is_list($predicate[$key])) {
                continue;
            }
            foreach ($predicate[$key] as $child) {
                if (is_array($child) && self::predicateReferencesColumn($child, $column)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $expression
     */
    private static function isFilterAliasUnsafe(array $expression): bool
    {
        if (isset($expression['sourceExpression']) && is_array($expression['sourceExpression'])) {
            return self::isFilterAliasUnsafe($expression['sourceExpression']);
        }
        if (($expression['type'] ?? null) === 'wildcard' || ($expression['type'] ?? null) === 'window') {
            return true;
        }
        if (($expression['type'] ?? null) === 'function') {
            $name = strtolower((string) ($expression['name'] ?? ''));
            $arguments = $expression['arguments'] ?? [];
            $argumentCount = is_array($arguments) && array_is_list($arguments) ? count($arguments) : 0;
            if (in_array($name, ['count', 'sum', 'total', 'avg', 'group_concat', 'json_group_array', 'jsonb_group_array', 'json_group_object', 'jsonb_group_object'], true)) {
                return true;
            }
            if (($name === 'min' || $name === 'max') && $argumentCount === 1) {
                return true;
            }
        }
        foreach (['left', 'right', 'operand', 'predicate'] as $key) {
            if (isset($expression[$key]) && is_array($expression[$key]) && self::isFilterAliasUnsafe($expression[$key])) {
                return true;
            }
        }
        foreach (['arguments', 'values'] as $key) {
            if (!isset($expression[$key]) || !is_array($expression[$key]) || !array_is_list($expression[$key])) {
                continue;
            }
            foreach ($expression[$key] as $child) {
                if (is_array($child) && self::isFilterAliasUnsafe($child)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $select
     * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>}
     */
    private static function applyWindowExpressions(array $rows, array $select): array
    {
        foreach ($select as $index => $expression) {
            [$rows, $select[$index]] = self::materializeWindowExpression($rows, $expression, '__window' . $index);
        }

        return [$rows, $select];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $expression
     * @return array{0:list<array<string,mixed>>,1:array<string,mixed>}
     */
    private static function materializeWindowExpression(array $rows, array $expression, string $columnPrefix): array
    {
        if (($expression['type'] ?? null) === 'window') {
            $values = self::windowValues($rows, $expression);
            if (count($values) !== count($rows)) {
                throw new \InvalidArgumentException('SQLite SELECT query window result row count mismatch');
            }
            foreach ($rows as $rowIndex => $row) {
                $row[$columnPrefix] = $values[$rowIndex];
                $rows[$rowIndex] = $row;
            }

            return [$rows, [
                'type' => 'column',
                'name' => $columnPrefix,
                'alias' => isset($expression['alias']) && is_string($expression['alias']) && $expression['alias'] !== ''
                    ? $expression['alias']
                    : ($expression['alias'] ?? 'expr' . ((int) preg_replace('/\D+/', '', $columnPrefix) + 1)),
            ]];
        }

        foreach (['left', 'right', 'operand', 'predicate'] as $key) {
            if (isset($expression[$key]) && is_array($expression[$key])) {
                [$rows, $expression[$key]] = self::materializeWindowExpression($rows, $expression[$key], $columnPrefix . '_' . $key);
            }
        }
        foreach (['arguments', 'values'] as $key) {
            if (isset($expression[$key]) && is_array($expression[$key]) && array_is_list($expression[$key])) {
                foreach ($expression[$key] as $childIndex => $child) {
                    if (is_array($child)) {
                        [$rows, $expression[$key][$childIndex]] = self::materializeWindowExpression($rows, $child, $columnPrefix . '_' . $key . $childIndex);
                    }
                }
            }
        }

        return [$rows, $expression];
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
        $frame = self::windowFrame($expression['frame'] ?? null);
        if ($frame !== null) {
            self::assertOrderedRangeOrGroupsFrame($orderBy, $frame);
        }
        $filter = self::windowFilter($expression['filter'] ?? null);
        $aggregateOrderBy = null;
        if (array_key_exists('aggregateOrderBy', $expression)) {
            $aggregateOrderBy = self::windowAggregateOrderList($expression['aggregateOrderBy']);
        }
        $distinct = ($expression['distinct'] ?? false) === true;

        $result = array_fill(0, count($rows), null);
        foreach (self::windowPartitions($rows, $partitionBy) as $partitionIndexes) {
            $orderedIndexes = self::orderWindowPartition($rows, $partitionIndexes, $orderBy);
            $orderedRows = array_map(static fn (int $rowIndex): array => $rows[$rowIndex], $orderedIndexes);
            $orderedValues = self::windowPartitionValues($function, $arguments, $orderedRows, $orderBy, $frame, $filter, $aggregateOrderBy, $distinct);
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
     * @param array{unit:string,preceding:int|float,following:int|float,exclude:string}|null $frame
     * @param array<string,mixed>|null $filter
     * @return list<mixed>
     */
    private static function windowPartitionValues(string $function, array $arguments, array $orderedRows, array $orderBy, ?array $frame, ?array $filter, ?array $aggregateOrderBy, bool $distinct): array
    {
        $orderKeys = array_keys($orderedRows);
        $peerKeys = $orderBy === []
            ? array_fill(0, count($orderedRows), 0)
            : array_map(static function (array $row) use ($orderBy): mixed {
                if (count($orderBy) === 1) {
                    return SQLiteSelectExpression::evaluate($row, $orderBy[0]['expression']);
                }

                return array_map(
                    static fn (array $term): mixed => SQLiteSelectExpression::evaluate($row, $term['expression']),
                    $orderBy
                );
            }, $orderedRows);
        $values = $arguments !== [] && (($arguments[0]['type'] ?? null) !== 'wildcard')
            ? array_map(static fn (array $row): mixed => SQLiteSelectExpression::evaluate($row, $arguments[0]), $orderedRows)
            : $peerKeys;

        if (in_array($function, ['lag', 'lead', 'first_value', 'last_value', 'nth_value'], true) && $arguments === []) {
            throw new \InvalidArgumentException("SQLite SELECT query {$function}() needs a value argument");
        }
        if (
            $frame === null
            && in_array($function, ['count', 'sum', 'total', 'avg', 'min', 'max', 'group_concat', 'json_group_array', 'jsonb_group_array', 'json_group_object', 'jsonb_group_object'], true)
        ) {
            $frame = self::defaultAggregateWindowFrame($orderBy, count($orderedRows));
        }
        if ($frame === null && in_array($function, ['first_value', 'last_value', 'nth_value'], true) && $orderBy !== []) {
            $frame = self::defaultAggregateWindowFrame($orderBy, count($orderedRows));
        }
        if ($frame !== null && in_array($function, ['json_group_array', 'jsonb_group_array'], true)) {
            self::assertOrderedRangeOrGroupsFrame($orderBy, $frame);
            if (count($arguments) !== 1 || (($arguments[0]['type'] ?? null) === 'wildcard')) {
                throw new \InvalidArgumentException("SQLite SELECT query {$function}() needs one value argument");
            }

            return self::jsonAggregateWindowFrameValues($function, $arguments[0], $orderedRows, $peerKeys, $frame, $filter, $aggregateOrderBy, $distinct);
        }
        if ($frame !== null && in_array($function, ['json_group_object', 'jsonb_group_object'], true)) {
            self::assertOrderedRangeOrGroupsFrame($orderBy, $frame);
            if (count($arguments) !== 2 || (($arguments[0]['type'] ?? null) === 'wildcard') || (($arguments[1]['type'] ?? null) === 'wildcard')) {
                throw new \InvalidArgumentException("SQLite SELECT query {$function}() needs label and value arguments");
            }

            return self::jsonObjectAggregateWindowFrameValues($function, $arguments[0], $arguments[1], $orderedRows, $peerKeys, $frame, $filter, $aggregateOrderBy, $distinct);
        }
        if ($distinct) {
            throw new \InvalidArgumentException("SQLite SELECT query DISTINCT window aggregate is not supported for {$function}");
        }
        if ($frame !== null && in_array($function, ['count', 'sum', 'total', 'avg', 'min', 'max', 'group_concat'], true)) {
            self::assertOrderedRangeOrGroupsFrame($orderBy, $frame);
            if ($function === 'count' && (($arguments[0]['type'] ?? null) === 'wildcard')) {
                $values = array_fill(0, count($orderedRows), 1);
            } elseif ($arguments === []) {
                throw new \InvalidArgumentException("SQLite SELECT query {$function}() needs a value argument");
            }
            $filterValues = $filter === null
                ? null
                : array_map(static fn (array $row): bool => SQLiteSelectPredicate::filter([$row], $filter) !== [], $orderedRows);

            $separator = $function === 'group_concat' && isset($arguments[1])
                ? (string) SQLiteSelectExpression::evaluate($orderedRows[0] ?? [], $arguments[1])
                : ',';

            return isset($frame['startBoundary'], $frame['endBoundary'])
                ? SQLiteWindowFunction::aggregateFrameBetweenValues(
                    $function,
                    $values,
                    $peerKeys,
                    $frame['unit'],
                    (string) $frame['startBoundary'],
                    (string) $frame['endBoundary'],
                    $frame['exclude'],
                    $filterValues,
                    $separator,
                )
                : SQLiteWindowFunction::aggregateFrameValues(
                $function,
                $values,
                $peerKeys,
                $frame['unit'],
                $frame['preceding'],
                $frame['following'],
                $frame['exclude'],
                $filterValues,
                $separator,
            );
        }
        if ($filter !== null) {
            throw new \InvalidArgumentException("SQLite SELECT query FILTER is not supported for {$function}");
        }
        if ($aggregateOrderBy !== null) {
            throw new \InvalidArgumentException("SQLite SELECT query aggregate ORDER BY is not supported for {$function}");
        }
        if ($frame === null && in_array($function, ['first_value', 'last_value', 'nth_value'], true)) {
            $frame = self::defaultAggregateWindowFrame($orderBy, count($orderedRows));
        }
        if ($frame !== null && in_array($function, ['first_value', 'last_value', 'nth_value'], true)) {
            self::assertOrderedRangeOrGroupsFrame($orderBy, $frame);

            if (isset($frame['startBoundary'], $frame['endBoundary'])) {
                return SQLiteWindowFunction::valueFrameBetweenValues(
                    $function,
                    $values,
                    $peerKeys,
                    $frame['unit'],
                    (string) $frame['startBoundary'],
                    (string) $frame['endBoundary'],
                    $frame['exclude'],
                    $function === 'nth_value' ? self::windowIntegerArgument($arguments, $orderedRows, 1, 'nth_value') : null,
                );
            }

            return SQLiteWindowFunction::valueFrameValues(
                $function,
                $values,
                $peerKeys,
                $frame['unit'],
                $frame['preceding'],
                $frame['following'],
                $frame['exclude'],
                $function === 'nth_value' ? self::windowIntegerArgument($arguments, $orderedRows, 1, 'nth_value') : null,
            );
        }
        if ($frame !== null && !in_array($function, ['row_number', 'rank', 'dense_rank', 'percent_rank', 'cume_dist', 'ntile', 'lag', 'lead'], true)) {
            throw new \InvalidArgumentException("SQLite SELECT query window frame is not supported for {$function}");
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
     * SQLite aggregate windows default to the whole partition when no window
     * ORDER BY exists, otherwise RANGE UNBOUNDED PRECEDING through CURRENT ROW.
     *
     * @param list<array{expression:array<string,mixed>,direction:string}> $orderBy
     * @return array{unit:string,preceding:int|float,following:int|float,exclude:string}
     */
    private static function defaultAggregateWindowFrame(array $orderBy, int $partitionSize): array
    {
        return $orderBy === []
            ? ['unit' => 'ROWS', 'preceding' => $partitionSize, 'following' => $partitionSize, 'exclude' => 'NO OTHERS']
            : ['unit' => 'GROUPS', 'preceding' => $partitionSize, 'following' => 0, 'exclude' => 'NO OTHERS'];
    }

    /**
     * @param list<array{expression:array<string,mixed>,direction:string}> $orderBy
     * @param array{unit:string,preceding:int|float,following:int|float,exclude:string} $frame
     */
    private static function assertOrderedRangeOrGroupsFrame(array $orderBy, array $frame): void
    {
        if ($orderBy === [] && in_array($frame['unit'], ['RANGE', 'GROUPS'], true)) {
            throw new \InvalidArgumentException('SQLite SELECT query RANGE/GROUPS window frame needs ORDER BY');
        }
    }

    /**
     * @param list<array<string,mixed>> $orderedRows
     * @param list<mixed> $peerKeys
     * @param array{unit:string,preceding:int|float,following:int|float,exclude:string} $frame
     * @param array<string,mixed>|null $filter
     * @param list<array{expression:array<string,mixed>,direction:string}>|null $aggregateOrderBy
     * @return list<string|SQLiteBlobValue>
     */
    private static function jsonAggregateWindowFrameValues(string $function, array $argument, array $orderedRows, array $peerKeys, array $frame, ?array $filter, ?array $aggregateOrderBy, bool $distinct): array
    {
        $frameRows = [];
        foreach ($orderedRows as $index => $row) {
            $frameRows[] = [
                'value' => SQLiteSelectExpression::evaluate($row, $argument),
                'frameKey' => $peerKeys[$index],
                'aggregateKey' => $aggregateOrderBy === null
                    ? []
                    : array_map(
                        static fn (array $term): array => [
                            'value' => SQLiteSelectExpression::evaluate($row, $term['expression']),
                            'direction' => $term['direction'],
                            'nulls' => $term['nulls'] ?? null,
                        ],
                        $aggregateOrderBy,
                    ),
                'filter' => $filter === null || SQLiteSelectPredicate::filter([$row], $filter) !== [],
                'position' => $index,
            ];
        }

        $frames = self::windowFrameRows($frameRows, $frame['unit'], $frame['preceding'], $frame['following'], $frame['exclude']);
        $result = [];
        foreach ($frames as $frameRows) {
            usort($frameRows, static function (array $left, array $right): int {
                foreach ($left['aggregateKey'] as $index => $leftTerm) {
                    $rightTerm = $right['aggregateKey'][$index] ?? null;
                    if (!is_array($rightTerm) || !array_key_exists('value', $rightTerm)) {
                        throw new \InvalidArgumentException('SQLite SELECT query window aggregate ORDER BY keys are malformed');
                    }
                    $comparison = self::compareAggregateOrderValues($leftTerm, $rightTerm);
                    if ($comparison === 0) {
                        continue;
                    }

                    return $comparison;
                }

                return $left['position'] <=> $right['position'];
            });
            $values = [];
            $seen = [];
            foreach ($frameRows as $row) {
                $value = $row['value'];
                if ($distinct) {
                    $key = self::jsonAggregateDistinctKey($value);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                }
                $values[] = $value;
            }
            $json = SQLiteJsonAggregate::jsonGroupArray($values);
            $result[] = $function === 'jsonb_group_array'
                ? new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($json, false, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR)))
                : $json;
        }

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $orderedRows
     * @param list<mixed> $peerKeys
     * @param array{unit:string,preceding:int|float,following:int|float,exclude:string} $frame
     * @param array<string,mixed>|null $filter
     * @param list<array{expression:array<string,mixed>,direction:string}>|null $aggregateOrderBy
     * @return list<string|SQLiteBlobValue>
     */
    private static function jsonObjectAggregateWindowFrameValues(string $function, array $labelArgument, array $valueArgument, array $orderedRows, array $peerKeys, array $frame, ?array $filter, ?array $aggregateOrderBy, bool $distinct): array
    {
        $frameRows = [];
        foreach ($orderedRows as $index => $row) {
            $frameRows[] = [
                'value' => [
                    SQLiteSelectExpression::evaluate($row, $labelArgument),
                    SQLiteSelectExpression::evaluate($row, $valueArgument),
                ],
                'frameKey' => $peerKeys[$index],
                'aggregateKey' => $aggregateOrderBy === null
                    ? []
                    : array_map(
                        static fn (array $term): array => [
                            'value' => SQLiteSelectExpression::evaluate($row, $term['expression']),
                            'direction' => $term['direction'],
                            'nulls' => $term['nulls'] ?? null,
                        ],
                        $aggregateOrderBy,
                    ),
                'filter' => $filter === null || SQLiteSelectPredicate::filter([$row], $filter) !== [],
                'position' => $index,
            ];
        }

        $frames = self::windowFrameRows($frameRows, $frame['unit'], $frame['preceding'], $frame['following'], $frame['exclude']);
        $result = [];
        foreach ($frames as $frameRows) {
            usort($frameRows, static function (array $left, array $right): int {
                foreach ($left['aggregateKey'] as $index => $leftTerm) {
                    $rightTerm = $right['aggregateKey'][$index] ?? null;
                    if (!is_array($rightTerm) || !array_key_exists('value', $rightTerm)) {
                        throw new \InvalidArgumentException('SQLite SELECT query window aggregate ORDER BY keys are malformed');
                    }
                    $comparison = self::compareAggregateOrderValues($leftTerm, $rightTerm);
                    if ($comparison === 0) {
                        continue;
                    }

                    return $comparison;
                }

                return $left['position'] <=> $right['position'];
            });
            $pairs = [];
            $seen = [];
            foreach ($frameRows as $row) {
                $pair = $row['value'];
                if ($distinct) {
                    $key = self::jsonAggregateDistinctKey($pair[0]) . "\0" . self::jsonAggregateDistinctKey($pair[1]);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                }
                $pairs[] = $pair;
            }
            $json = SQLiteJsonAggregate::jsonGroupObject($pairs);
            $result[] = $function === 'jsonb_group_object'
                ? new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($json, false, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR)))
                : $json;
        }

        return $result;
    }

    private static function jsonAggregateDistinctKey(mixed $value): string
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
     * @return list<array{expression:array<string,mixed>,direction:string}>
     */
    private static function windowAggregateOrderList(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite SELECT query window aggregate ORDER BY must be a list');
        }
        $terms = array_is_list($value) ? $value : [$value];
        if ($terms === []) {
            throw new \InvalidArgumentException('SQLite SELECT query window aggregate ORDER BY needs at least one term');
        }

        $orderBy = [];
        foreach ($terms as $term) {
            if (!is_array($term) || !isset($term['expression']) || !is_array($term['expression'])) {
                throw new \InvalidArgumentException('SQLite SELECT query window aggregate ORDER BY expression is malformed');
            }
            $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite SELECT query window aggregate ORDER BY direction must be ASC or DESC');
            }
            $nulls = null;
            if (isset($term['nulls'])) {
                $nulls = strtoupper((string) $term['nulls']);
                if ($nulls !== 'FIRST' && $nulls !== 'LAST') {
                    throw new \InvalidArgumentException('SQLite SELECT query window aggregate ORDER BY NULLS must be FIRST or LAST');
                }
            }
            $orderBy[] = [
                'expression' => $term['expression'],
                'direction' => $direction,
                ...$nulls !== null ? ['nulls' => $nulls] : [],
            ];
        }

        return $orderBy;
    }

    /**
     * @param array{value:mixed,direction:string,nulls?:string|null} $left
     * @param array{value:mixed,direction:string,nulls?:string|null} $right
     */
    private static function compareAggregateOrderValues(array $left, array $right): int
    {
        if ($left['value'] === null || $right['value'] === null) {
            if ($left['value'] === null && $right['value'] === null) {
                return 0;
            }
            $nulls = $left['nulls'] ?? null;
            if ($nulls === 'FIRST') {
                return $left['value'] === null ? -1 : 1;
            }
            if ($nulls === 'LAST') {
                return $left['value'] === null ? 1 : -1;
            }
        }

        $comparison = self::compareSqlValues($left['value'], $right['value']);
        if ($comparison === 0) {
            return 0;
        }

        return $left['direction'] === 'DESC' ? -$comparison : $comparison;
    }

    /**
     * @param list<array{value:mixed,frameKey:mixed,aggregateKey:list<array{value:mixed,direction:string}>,filter:bool,position:int}> $rows
     * @return list<list<array{value:mixed,frameKey:mixed,aggregateKey:list<array{value:mixed,direction:string}>,filter:bool,position:int}>>
     */
    private static function windowFrameRows(array $rows, string $unit, int|float $preceding, int|float $following, string $exclude): array
    {
        if ($preceding < 0 || $following < 0) {
            throw new \InvalidArgumentException('SQLite SELECT query window frame offsets must be non-negative');
        }
        if (!in_array($unit, ['ROWS', 'RANGE', 'GROUPS'], true)) {
            throw new \InvalidArgumentException('SQLite SELECT query window frame unit is not supported');
        }
        if (!in_array($exclude, ['NO OTHERS', 'CURRENT ROW', 'GROUP', 'TIES'], true)) {
            throw new \InvalidArgumentException('SQLite SELECT query window EXCLUDE mode is not supported');
        }
        $count = count($rows);
        $groups = [];
        $groupByIndex = [];
        foreach ($rows as $index => $row) {
            $lastGroup = count($groups) - 1;
            if ($lastGroup < 0 || self::compareSqlValues($row['frameKey'], $groups[$lastGroup]['key']) !== 0) {
                $groups[] = ['key' => $row['frameKey'], 'start' => $index, 'end' => $index];
                $lastGroup++;
            } else {
                $groups[$lastGroup]['end'] = $index;
            }
            $groupByIndex[$index] = $lastGroup;
        }

        $frames = [];
        foreach ($rows as $position => $current) {
            [$start, $end] = self::windowFrameRowBounds($rows, $groups, $groupByIndex, $unit, $position, $preceding, $following, $count - 1);
            $frame = [];
            for ($index = $start; $index <= $end; $index++) {
                $candidate = $rows[$index];
                $isCurrent = $index === $position;
                $isPeer = self::compareSqlValues($candidate['frameKey'], $current['frameKey']) === 0;
                if ($exclude === 'CURRENT ROW' && $isCurrent) {
                    continue;
                }
                if ($exclude === 'GROUP' && $isPeer) {
                    continue;
                }
                if ($exclude === 'TIES' && $isPeer && !$isCurrent) {
                    continue;
                }
                if (!$candidate['filter']) {
                    continue;
                }
                $frame[] = $candidate;
            }
            $frames[] = $frame;
        }

        return $frames;
    }

    /**
     * @param list<array{value:mixed,frameKey:mixed,aggregateKey:mixed,filter:bool,position:int}> $rows
     * @param list<array{key:mixed,start:int,end:int}> $groups
     * @param array<int,int> $groupByIndex
     * @return array{0:int,1:int}
     */
    private static function windowFrameRowBounds(array $rows, array $groups, array $groupByIndex, string $unit, int $position, int|float $preceding, int|float $following, int $lastIndex): array
    {
        if ($unit === 'ROWS') {
            $precedingOffset = is_float($preceding) && is_infinite($preceding) ? $lastIndex : (int) $preceding;
            $followingOffset = is_float($following) && is_infinite($following) ? $lastIndex : (int) $following;

            return [max(0, $position - $precedingOffset), min($lastIndex, $position + $followingOffset)];
        }
        if ($unit === 'GROUPS') {
            $group = $groupByIndex[$position];
            $lastGroup = count($groups) - 1;
            $precedingOffset = is_float($preceding) && is_infinite($preceding) ? $lastGroup : (int) $preceding;
            $followingOffset = is_float($following) && is_infinite($following) ? $lastGroup : (int) $following;
            $startGroup = max(0, $group - $precedingOffset);
            $endGroup = min($lastGroup, $group + $followingOffset);

            return [$groups[$startGroup]['start'], $groups[$endGroup]['end']];
        }

        $current = $rows[$position]['frameKey'];
        if (!is_int($current) && !is_float($current) && !is_bool($current)) {
            $group = $groupByIndex[$position];
            $startGroup = $group;
            $endGroup = $group;
            if (is_float($preceding) && is_infinite($preceding)) {
                $startGroup = 0;
            }
            if (is_float($following) && is_infinite($following)) {
                $endGroup = count($groups) - 1;
            }

            return [$groups[$startGroup]['start'], $groups[$endGroup]['end']];
        }
        $lower = $current - $preceding;
        $upper = $current + $following;
        $start = $position;
        $end = $position;
        foreach ($rows as $index => $row) {
            $key = $row['frameKey'];
            if (!is_int($key) && !is_float($key) && !is_bool($key)) {
                continue;
            }
            if ($key < $lower || $key > $upper) {
                continue;
            }
            $start = min($start, $index);
            $end = max($end, $index);
        }

        return [$start, $end];
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
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }
        if (is_string($value) && preg_match('/^[+-]?[0-9]+(?:\.0+)?$/', trim($value)) === 1) {
            return (int) trim($value);
        }
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

    /**
     * @return array{unit:string,preceding:int|float,following:int|float,exclude:string}|null
     */
    private static function windowFrame(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite SELECT query window frame must be an array');
        }
        foreach (['unit', 'preceding', 'following', 'exclude'] as $key) {
            if (!array_key_exists($key, $value)) {
                throw new \InvalidArgumentException('SQLite SELECT query window frame is malformed');
            }
        }
        if (!is_string($value['unit']) || !is_string($value['exclude']) || (!is_int($value['preceding']) && !is_float($value['preceding'])) || (!is_int($value['following']) && !is_float($value['following']))) {
            throw new \InvalidArgumentException('SQLite SELECT query window frame is malformed');
        }

        $frame = [
            'unit' => $value['unit'],
            'preceding' => $value['preceding'],
            'following' => $value['following'],
            'exclude' => $value['exclude'],
        ];
        if (isset($value['startBoundary'], $value['endBoundary']) && is_string($value['startBoundary']) && is_string($value['endBoundary'])) {
            $frame['startBoundary'] = $value['startBoundary'];
            $frame['endBoundary'] = $value['endBoundary'];
        }

        return $frame;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function windowFilter(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite SELECT query window FILTER must be a predicate');
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
                if (isset($join['indexedDynamicRows']) && !is_callable($join['indexedDynamicRows'])) {
                    throw new \InvalidArgumentException('SQLite SELECT query indexed dynamic join rows must be callable');
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
        $dynamicRows = $join['indexedDynamicRows'] ?? $join['dynamicRows'];
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
    private static function applyGroupBy(array $rows, array $groupBy, array $plan = []): array
    {
        if (array_key_exists('expressions', $groupBy)) {
            if (!is_array($groupBy['expressions']) || !array_is_list($groupBy['expressions'])) {
                throw new \InvalidArgumentException('SQLite SELECT query groupBy expressions must be a list');
            }
            foreach ($groupBy['expressions'] as $entry) {
                if (!is_array($entry)) {
                    throw new \InvalidArgumentException('SQLite SELECT query groupBy expression entries must be arrays');
                }
                $column = $entry['column'] ?? null;
                $expression = $entry['expression'] ?? null;
                if (!is_string($column) || $column === '' || !is_array($expression)) {
                    throw new \InvalidArgumentException('SQLite SELECT query groupBy expression entry is malformed');
                }
                foreach ($rows as $index => $row) {
                    $row[$column] = SQLiteSelectExpression::evaluate($row, $expression);
                    $rows[$index] = $row;
                }
            }
        }

        $groupColumn = self::groupColumns($groupBy);
        $valueColumn = array_key_exists('valueColumn', $groupBy)
            ? self::nullableString($groupBy, 'valueColumn', 'groupBy')
            : null;

        $jsonAggregates = $groupBy['jsonAggregates'] ?? [];
        if (!is_array($jsonAggregates) || !array_is_list($jsonAggregates)) {
            throw new \InvalidArgumentException('SQLite SELECT query JSON aggregate plans must be a list');
        }

        if ($groupColumn === []) {
            $summaries = SQLiteGroupedAggregate::summarizeAll($rows, $valueColumn, $jsonAggregates);
            if ($rows === [] && isset($plan['correlatedOuterRow']) && is_array($plan['correlatedOuterRow'])) {
                foreach ($plan['correlatedOuterRow'] as $column => $value) {
                    if (is_string($column) && !array_key_exists($column, $summaries[0])) {
                        $summaries[0][$column] = $value;
                    }
                }
            }
        } else {
            $summaries = SQLiteGroupedAggregate::summarize($rows, $groupColumn, $valueColumn, $jsonAggregates);
        }

        if (array_key_exists('having', $groupBy)) {
            if (!is_array($groupBy['having'])) {
                throw new \InvalidArgumentException('SQLite SELECT query HAVING clause must be a predicate');
            }
            if (array_key_exists('select', $plan) && is_array($plan['select']) && array_is_list($plan['select'])) {
                $summaries = self::materializeFilterAliases($summaries, $plan['select'], $groupBy['having']);
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
            if (!is_array($groupBy['columns']) || !array_is_list($groupBy['columns'])) {
                throw new \InvalidArgumentException('SQLite SELECT query groupBy columns must be a list');
            }
            if ($groupBy['columns'] === [] && ($groupBy['implicitAggregate'] ?? false) !== true) {
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

    private static function nullableString(array $input, string $key, string $context): ?string
    {
        if ($input[$key] === null) {
            return null;
        }

        return self::requiredString($input, $key, $context);
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
