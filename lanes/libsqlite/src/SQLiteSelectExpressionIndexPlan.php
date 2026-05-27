<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectExpressionIndexPlan
{
    /**
     * @param list<array{sql:string,rootPage?:int,name?:string}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @return null|array<string,mixed>
     */
    public static function choose(array $indexDefinitions, array $predicate): ?array
    {
        $plans = self::usablePlans($indexDefinitions, $predicate);

        return $plans[0] ?? null;
    }

    /**
     * @param list<array{sql:string,rootPage?:int,name?:string,estimatedRows?:int,coveringColumns?:list<string>}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return null|array<string,mixed>
     */
    public static function chooseLowestCost(array $indexDefinitions, array $predicate, array $orderBy = [], array $neededColumns = []): ?array
    {
        $plans = self::usablePlans($indexDefinitions, $predicate, $orderBy, $neededColumns);

        return $plans[0] ?? null;
    }

    /**
     * @param list<array{sql:string,rootPage?:int,name?:string,estimatedRows?:int,coveringColumns?:list<string>}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    public static function rankedPlans(array $indexDefinitions, array $predicate, array $orderBy = [], array $neededColumns = []): array
    {
        return self::usablePlans($indexDefinitions, $predicate, $orderBy, $neededColumns);
    }

    /**
     * @param list<array{sql:string,rootPage?:int,name?:string,estimatedRows?:int,coveringColumns?:list<string>}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function usablePlans(array $indexDefinitions, array $predicate, array $orderBy = [], array $neededColumns = []): array
    {
        $terms = self::flattenAndTerms($predicate);
        $plans = [];
        foreach ($terms as $term) {
            $constraint = self::constraintFromPredicate($term);
            if ($constraint === null) {
                continue;
            }

            foreach ($indexDefinitions as $index) {
                $sql = $index['sql'] ?? null;
                if (!is_string($sql) || $sql === '') {
                    throw new \InvalidArgumentException('SQLite SELECT expression-index planner needs index SQL text');
                }

                $expression = self::expressionForType($sql, $constraint['type']);
                if ($expression === null || strcasecmp($expression->columnName, $constraint['column']) !== 0) {
                    continue;
                }
                if ($expression->partial && !self::constraintImpliesPartialPredicate($expression->partialPredicate, $constraint, $terms)) {
                    continue;
                }
                if (!self::constraintCompatibleWithType($constraint, $expression->collation)) {
                    continue;
                }

                $estimatedRows = self::estimatedRows($index, $constraint);
                $orderCompatible = self::orderCompatible($expression, $orderBy);
                $covering = self::covering($index, $neededColumns);
                $estimatedCost = self::estimatedCost($constraint, $estimatedRows, $expression->partial, $orderCompatible, $covering);

                $plans[] = [
                    'usable' => true,
                    'rootPage' => $index['rootPage'] ?? null,
                    'name' => $index['name'] ?? null,
                    'type' => $constraint['type'],
                    'column' => $expression->columnName,
                    'operator' => $constraint['operator'],
                    'values' => $constraint['values'],
                    'collation' => $expression->collation,
                    'descending' => $expression->descending,
                    'partial' => $expression->partial,
                    'residualPredicateRequired' => $constraint['residualPredicateRequired'],
                    'estimatedRows' => $estimatedRows,
                    'estimatedCost' => $estimatedCost,
                    'orderBySatisfied' => $orderCompatible,
                    'covering' => $covering,
                ];
            }
        }

        usort($plans, static function (array $left, array $right): int {
            return [$left['estimatedCost'], $left['estimatedRows'], (string) ($left['name'] ?? '')]
                <=> [$right['estimatedCost'], $right['estimatedRows'], (string) ($right['name'] ?? '')];
        });

        return $plans;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function flattenAndTerms(array $predicate): array
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator', 'SQLite SELECT expression-index predicate'));
        if ($operator !== 'AND') {
            return [$predicate];
        }

        $terms = $predicate['terms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException('SQLite SELECT expression-index AND predicate needs a non-empty term list');
        }

        $flattened = [];
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite SELECT expression-index AND predicate terms must be predicates');
            }
            array_push($flattened, ...self::flattenAndTerms($term));
        }

        return $flattened;
    }

    /**
     * @return null|array{type:string,column:string,operator:string,values:mixed,residualPredicateRequired:bool}
     */
    private static function constraintFromPredicate(array $predicate): ?array
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator', 'SQLite SELECT expression-index predicate'));
        if ($operator === '=' || $operator === '==') {
            return self::binaryConstraint($predicate, 'point');
        }
        if (in_array($operator, ['<', '<=', '>', '>='], true)) {
            return self::binaryConstraint($predicate, 'range-' . $operator);
        }
        if ($operator === 'IN') {
            $left = self::expressionOperand($predicate['left'] ?? null);
            $values = $predicate['values'] ?? null;
            if ($left === null || !is_array($values) || !array_is_list($values)) {
                return null;
            }

            return [
                'type' => $left['type'],
                'column' => $left['column'],
                'operator' => 'IN',
                'values' => self::literalList($values),
                'residualPredicateRequired' => true,
            ];
        }
        if ($operator === 'BETWEEN') {
            $left = self::expressionOperand($predicate['left'] ?? null);
            if ($left === null || !array_key_exists('lower', $predicate) || !array_key_exists('upper', $predicate)) {
                return null;
            }

            return [
                'type' => $left['type'],
                'column' => $left['column'],
                'operator' => 'BETWEEN',
                'values' => [
                    'lower' => self::literalValue($predicate['lower']),
                    'upper' => self::literalValue($predicate['upper']),
                ],
                'residualPredicateRequired' => true,
            ];
        }

        return null;
    }

    /**
     * @return null|array{type:string,column:string,operator:string,values:mixed,residualPredicateRequired:bool}
     */
    private static function binaryConstraint(array $predicate, string $operator): ?array
    {
        $left = self::expressionOperand($predicate['left'] ?? null);
        $right = self::expressionOperand($predicate['right'] ?? null);
        if ($left !== null && $right === null && array_key_exists('right', $predicate)) {
            return [
                'type' => $left['type'],
                'column' => $left['column'],
                'operator' => $operator,
                'values' => self::literalValue($predicate['right']),
                'residualPredicateRequired' => true,
            ];
        }
        if ($right !== null && $left === null && array_key_exists('left', $predicate)) {
            return [
                'type' => $right['type'],
                'column' => $right['column'],
                'operator' => self::reverseRangeOperator($operator),
                'values' => self::literalValue($predicate['left']),
                'residualPredicateRequired' => true,
            ];
        }

        return null;
    }

    /**
     * @return null|array{type:string,column:string}
     */
    private static function expressionOperand(mixed $operand): ?array
    {
        if (!is_array($operand)) {
            return null;
        }

        $function = strtolower((string) ($operand['function'] ?? ''));
        $column = $operand['column'] ?? null;
        if (!is_string($column) || $column === '') {
            return null;
        }

        return match ($function) {
            'lower' => ['type' => 'lower', 'column' => $column],
            'upper' => ['type' => 'upper', 'column' => $column],
            'length' => ['type' => 'length', 'column' => $column],
            'cast_integer' => ['type' => 'integer-cast', 'column' => $column],
            default => null,
        };
    }

    private static function expressionForType(string $sql, string $type): ?SQLiteIndexColumn
    {
        return match ($type) {
            'lower' => SQLiteCreateIndex::firstLowerExpression($sql),
            'upper' => SQLiteCreateIndex::firstUpperExpression($sql),
            'length' => SQLiteCreateIndex::firstLengthExpression($sql),
            'integer-cast' => SQLiteCreateIndex::firstIntegerCastExpression($sql),
            default => null,
        };
    }

    /**
     * @param array{type:string,column:string,operator:string,values:mixed,residualPredicateRequired:bool} $constraint
     */
    private static function constraintCompatibleWithType(array $constraint, string $collation): bool
    {
        if ($constraint['type'] === 'lower' || $constraint['type'] === 'upper') {
            return is_string($constraint['values']) || is_array($constraint['values']);
        }
        if ($constraint['type'] === 'length' || $constraint['type'] === 'integer-cast') {
            return self::valuesAreIntegersOrNull($constraint['values']);
        }

        return $collation !== '';
    }

    /**
     * @param array{type:string,column:string,operator:string,values:mixed,residualPredicateRequired:bool} $constraint
     * @param list<array<string,mixed>> $terms
     */
    private static function constraintImpliesPartialPredicate(?SQLiteIndexPredicate $predicate, array $constraint, array $terms): bool
    {
        if ($predicate === null) {
            return false;
        }

        return self::partialPredicateImpliedByAvailableConstraints($predicate, $constraint, $terms);
    }

    /**
     * @param array{type:string,column:string,operator:string,values:mixed,residualPredicateRequired:bool} $constraint
     * @param list<array<string,mixed>> $terms
     */
    private static function partialPredicateImpliedByAvailableConstraints(SQLiteIndexPredicate $predicate, array $constraint, array $terms): bool
    {
        if ($predicate->operator === SQLiteIndexPredicate::AND) {
            if (!is_array($predicate->value) || $predicate->value === []) {
                return false;
            }
            foreach ($predicate->value as $subPredicate) {
                if (
                    !$subPredicate instanceof SQLiteIndexPredicate
                    || !self::partialPredicateImpliedByAvailableConstraints($subPredicate, $constraint, $terms)
                ) {
                    return false;
                }
            }

            return true;
        }

        if ($predicate->operator === SQLiteIndexPredicate::OR) {
            if (!is_array($predicate->value)) {
                return false;
            }
            foreach ($predicate->value as $subPredicate) {
                if (
                    $subPredicate instanceof SQLiteIndexPredicate
                    && self::partialPredicateImpliedByAvailableConstraints($subPredicate, $constraint, $terms)
                ) {
                    return true;
                }
            }

            return false;
        }

        if (
            self::partialPredicateIsSafeNonNull($predicate, $constraint['column'])
            && self::constraintHasNonNullSearchValue($constraint['values'])
        ) {
            return true;
        }
        foreach ($terms as $term) {
            $ordinaryConstraint = self::ordinaryConstraintFromPredicate($term);
            if ($ordinaryConstraint !== null && self::ordinaryConstraintImpliesPartialPredicate($predicate, $ordinaryConstraint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return null|array{column:string,operator:string,values:mixed}
     */
    private static function ordinaryConstraintFromPredicate(array $predicate): ?array
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator', 'SQLite SELECT expression-index predicate'));
        if ($operator === '=' || $operator === '==') {
            return self::ordinaryBinaryConstraint($predicate, 'point');
        }
        if (in_array($operator, ['<', '<=', '>', '>='], true)) {
            return self::ordinaryBinaryConstraint($predicate, 'range-' . $operator);
        }
        if ($operator === 'IN') {
            $left = self::columnOperand($predicate['left'] ?? null);
            $values = $predicate['values'] ?? null;
            if ($left === null || !is_array($values) || !array_is_list($values)) {
                return null;
            }

            return [
                'column' => $left,
                'operator' => 'IN',
                'values' => self::literalList($values),
            ];
        }
        if ($operator === 'BETWEEN') {
            $left = self::columnOperand($predicate['left'] ?? null);
            if ($left === null || !array_key_exists('lower', $predicate) || !array_key_exists('upper', $predicate)) {
                return null;
            }

            return [
                'column' => $left,
                'operator' => 'BETWEEN',
                'values' => [
                    'lower' => self::literalValue($predicate['lower']),
                    'upper' => self::literalValue($predicate['upper']),
                ],
            ];
        }

        return null;
    }

    /**
     * @return null|array{column:string,operator:string,values:mixed}
     */
    private static function ordinaryBinaryConstraint(array $predicate, string $operator): ?array
    {
        $left = self::columnOperand($predicate['left'] ?? null);
        $right = self::columnOperand($predicate['right'] ?? null);
        if ($left !== null && $right === null && array_key_exists('right', $predicate)) {
            return [
                'column' => $left,
                'operator' => $operator,
                'values' => self::literalValue($predicate['right']),
            ];
        }
        if ($right !== null && $left === null && array_key_exists('left', $predicate)) {
            return [
                'column' => $right,
                'operator' => self::reverseRangeOperator($operator),
                'values' => self::literalValue($predicate['left']),
            ];
        }

        return null;
    }

    private static function columnOperand(mixed $operand): ?string
    {
        if (!is_array($operand) || array_key_exists('function', $operand)) {
            return null;
        }

        $column = $operand['column'] ?? null;
        if (!is_string($column) || $column === '') {
            return null;
        }

        return $column;
    }

    /**
     * @param array{column:string,operator:string,values:mixed} $constraint
     */
    private static function ordinaryConstraintImpliesPartialPredicate(SQLiteIndexPredicate $predicate, array $constraint): bool
    {
        if ($constraint['operator'] === 'point') {
            return $predicate->isImpliedByPointLookup($constraint['column'], $constraint['values']);
        }
        if ($constraint['operator'] === 'IN' && is_array($constraint['values'])) {
            return $predicate->isImpliedByInListLookup($constraint['column'], $constraint['values']);
        }
        if ($constraint['operator'] === 'BETWEEN' && is_array($constraint['values'])) {
            return $predicate->isImpliedByRangeLookup(
                $constraint['column'],
                $constraint['values']['lower'] ?? null,
                $constraint['values']['upper'] ?? null,
                true
            );
        }
        if (str_starts_with($constraint['operator'], 'range-')) {
            return match ($constraint['operator']) {
                'range->' => $predicate->isImpliedByRangeLookup($constraint['column'], $constraint['values'], null, false),
                'range->=' => $predicate->isImpliedByRangeLookup($constraint['column'], $constraint['values'], null, true),
                'range-<' => $predicate->isImpliedByRangeLookup($constraint['column'], null, $constraint['values'], false),
                'range-<=' => $predicate->isImpliedByRangeLookup($constraint['column'], null, $constraint['values'], true),
                default => false,
            };
        }

        return false;
    }

    private static function partialPredicateIsSafeNonNull(SQLiteIndexPredicate $predicate, string $column): bool
    {
        if ($predicate->operator === SQLiteIndexPredicate::AND) {
            return is_array($predicate->value)
                && $predicate->value !== []
                && array_reduce(
                    $predicate->value,
                    static fn (bool $carry, mixed $subPredicate): bool => $carry
                        && $subPredicate instanceof SQLiteIndexPredicate
                        && self::partialPredicateIsSafeNonNull($subPredicate, $column),
                    true
                );
        }
        if ($predicate->operator === SQLiteIndexPredicate::OR) {
            return is_array($predicate->value)
                && array_reduce(
                    $predicate->value,
                    static fn (bool $carry, mixed $subPredicate): bool => $carry
                        || (
                            $subPredicate instanceof SQLiteIndexPredicate
                            && self::partialPredicateIsSafeNonNull($subPredicate, $column)
                        ),
                    false
                );
        }

        return strcasecmp($predicate->columnName, $column) === 0
            && $predicate->operator === SQLiteIndexPredicate::IS_NOT_NULL;
    }

    private static function constraintHasNonNullSearchValue(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_array($item)) {
                    if (self::constraintHasNonNullSearchValue($item)) {
                        return true;
                    }
                    continue;
                }
                if ($item !== null) {
                    return true;
                }
            }

            return false;
        }

        return $value !== null;
    }

    private static function valuesAreIntegersOrNull(mixed $value): bool
    {
        if (is_int($value) || $value === null) {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (is_array($item)) {
                if (!self::valuesAreIntegersOrNull($item)) {
                    return false;
                }
                continue;
            }
            if (!is_int($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{estimatedRows?:int} $index
     * @param array{type:string,column:string,operator:string,values:mixed,residualPredicateRequired:bool} $constraint
     */
    private static function estimatedRows(array $index, array $constraint): int
    {
        $baseRows = $index['estimatedRows'] ?? 1000;
        if (!is_int($baseRows) || $baseRows < 1) {
            throw new \InvalidArgumentException('SQLite SELECT expression-index estimatedRows must be a positive integer');
        }

        return max(1, min($baseRows, (int) ceil($baseRows * self::selectivity($constraint))));
    }

    /**
     * @param array{operator:string,values:mixed} $constraint
     */
    private static function selectivity(array $constraint): float
    {
        if ($constraint['operator'] === 'point') {
            return 0.01;
        }
        if ($constraint['operator'] === 'IN') {
            $values = is_array($constraint['values']) ? array_values(array_filter(
                $constraint['values'],
                static fn (mixed $value): bool => $value !== null
            )) : [];

            return min(0.5, max(0.01, count(array_unique($values, SORT_REGULAR)) * 0.015));
        }
        if ($constraint['operator'] === 'BETWEEN') {
            return 0.1;
        }
        if (str_starts_with($constraint['operator'], 'range-')) {
            return 0.25;
        }

        return 1.0;
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function orderCompatible(SQLiteIndexColumn $expression, array $orderBy): bool
    {
        if ($orderBy === []) {
            return false;
        }
        if (count($orderBy) !== 1) {
            return false;
        }

        $order = $orderBy[0];
        $column = $order['column'] ?? null;
        if (!is_string($column) || strcasecmp($column, $expression->columnName) !== 0) {
            return false;
        }

        $direction = strtoupper((string) ($order['direction'] ?? 'ASC'));
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new \InvalidArgumentException('SQLite SELECT expression-index ORDER BY direction must be ASC or DESC');
        }

        return $expression->descending === ($direction === 'DESC');
    }

    /**
     * @param array{coveringColumns?:list<string>} $index
     * @param list<string> $neededColumns
     */
    private static function covering(array $index, array $neededColumns): bool
    {
        if ($neededColumns === []) {
            return false;
        }
        $columns = $index['coveringColumns'] ?? [];
        if (!is_array($columns) || !array_is_list($columns)) {
            throw new \InvalidArgumentException('SQLite SELECT expression-index coveringColumns must be a list');
        }

        $normalized = [];
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite SELECT expression-index coveringColumns must contain column names');
            }
            $normalized[strtolower($column)] = true;
        }

        foreach ($neededColumns as $neededColumn) {
            if (!is_string($neededColumn) || $neededColumn === '') {
                throw new \InvalidArgumentException('SQLite SELECT expression-index needed columns must be column names');
            }
            if (!isset($normalized[strtolower($neededColumn)])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{operator:string} $constraint
     */
    private static function estimatedCost(array $constraint, int $estimatedRows, bool $partial, bool $orderCompatible, bool $covering): int
    {
        $cost = match ($constraint['operator']) {
            'point' => 5,
            'IN' => 12,
            'BETWEEN' => 18,
            default => str_starts_with($constraint['operator'], 'range-') ? 25 : 100,
        };
        $cost += $estimatedRows;
        if ($partial) {
            $cost -= 3;
        }
        if ($orderCompatible) {
            $cost -= 8;
        }
        if ($covering) {
            $cost -= 5;
        }

        return max(1, $cost);
    }

    /**
     * @param list<mixed> $values
     * @return list<mixed>
     */
    private static function literalList(array $values): array
    {
        return array_map(static fn (mixed $value): mixed => self::literalValue($value), $values);
    }

    private static function literalValue(mixed $value): mixed
    {
        if ($value instanceof SQLiteBlobValue || $value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('SQLite SELECT expression-index constraints need scalar, BLOB, or NULL values');
    }

    private static function reverseRangeOperator(string $operator): string
    {
        return match ($operator) {
            'range-<' => 'range->',
            'range-<=' => 'range->=',
            'range->' => 'range-<',
            'range->=' => 'range-<=',
            default => $operator,
        };
    }

    private static function requiredString(array $payload, string $key, string $context): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("{$context} needs {$key}");
        }

        return $value;
    }
}
