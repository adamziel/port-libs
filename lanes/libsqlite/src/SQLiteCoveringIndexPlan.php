<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCoveringIndexPlan
{
    /**
     * @param list<array{sql:string,name?:string,rootPage?:int,estimatedRows?:int}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<string> $neededColumns
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return null|array<string,mixed>
     */
    public static function choose(array $indexDefinitions, array $predicate, array $neededColumns, array $orderBy = []): ?array
    {
        $plans = self::rankedPlans($indexDefinitions, $predicate, $neededColumns, $orderBy);

        return $plans[0] ?? null;
    }

    /**
     * @param list<array{sql:string,name?:string,rootPage?:int,estimatedRows?:int}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<string> $neededColumns
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return list<array<string,mixed>>
     */
    public static function rankedPlans(array $indexDefinitions, array $predicate, array $neededColumns, array $orderBy = []): array
    {
        $terms = self::flattenAndTerms($predicate);
        $constraints = self::constraintsByColumn($terms);
        $plans = [];

        foreach ($indexDefinitions as $index) {
            $sql = $index['sql'] ?? null;
            if (!is_string($sql) || $sql === '') {
                throw new \InvalidArgumentException('SQLite covering-index planner needs CREATE INDEX SQL text');
            }

            $columns = SQLiteCreateIndex::columns($sql);
            if ($columns === null) {
                continue;
            }
            if (!self::partialPredicateIsImplied($columns[0]->partialPredicate, $terms)) {
                continue;
            }

            $prefix = self::usablePrefix($columns, $constraints);
            if ($prefix['count'] === 0) {
                continue;
            }

            $covering = self::coversNeededColumns($columns, $neededColumns);
            $orderBySatisfied = self::orderBySatisfied($columns, $orderBy, $prefix['equalityPrefix']);
            $estimatedRows = self::estimatedRows($index, $prefix);
            $cost = self::estimatedCost($estimatedRows, $prefix, $covering, $orderBySatisfied, $columns[0]->partial);

            $plans[] = [
                'usable' => true,
                'name' => $index['name'] ?? self::indexName($sql),
                'rootPage' => $index['rootPage'] ?? null,
                'columns' => array_map(static fn (SQLiteIndexColumn $column): string => $column->columnName, $columns),
                'usedColumns' => $prefix['usedColumns'],
                'rangeColumn' => $prefix['rangeColumn'],
                'equalityPrefix' => $prefix['equalityPrefix'],
                'covering' => $covering,
                'orderBySatisfied' => $orderBySatisfied,
                'partial' => $columns[0]->partial,
                'estimatedRows' => $estimatedRows,
                'estimatedCost' => $cost,
                'residualPredicateRequired' => true,
            ];
        }

        usort($plans, static function (array $left, array $right): int {
            return [$left['estimatedCost'], $left['estimatedRows'], (string) $left['name']]
                <=> [$right['estimatedCost'], $right['estimatedRows'], (string) $right['name']];
        });

        return $plans;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function flattenAndTerms(array $predicate): array
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator'));
        if ($operator !== 'AND') {
            return [$predicate];
        }

        $terms = $predicate['terms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException('SQLite covering-index AND predicate needs a non-empty term list');
        }

        $flattened = [];
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite covering-index AND predicate terms must be predicates');
            }
            array_push($flattened, ...self::flattenAndTerms($term));
        }

        return $flattened;
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return array<string,list<array{column:string,operator:string,values:mixed}>>
     */
    private static function constraintsByColumn(array $terms): array
    {
        $constraints = [];
        foreach ($terms as $term) {
            $constraint = self::ordinaryConstraintFromPredicate($term);
            if ($constraint === null) {
                continue;
            }
            $constraints[strtolower($constraint['column'])][] = $constraint;
        }

        return $constraints;
    }

    /**
     * @return null|array{column:string,operator:string,values:mixed}
     */
    private static function ordinaryConstraintFromPredicate(array $predicate): ?array
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator'));
        if ($operator === '=' || $operator === '==') {
            return self::binaryConstraint($predicate, 'point');
        }
        if (in_array($operator, ['<', '<=', '>', '>='], true)) {
            return self::binaryConstraint($predicate, 'range-' . $operator);
        }
        if ($operator === 'IN') {
            $column = self::columnOperand($predicate['left'] ?? null);
            $values = $predicate['values'] ?? null;
            if ($column === null || !is_array($values) || !array_is_list($values)) {
                return null;
            }

            return ['column' => $column, 'operator' => 'IN', 'values' => self::literalList($values)];
        }
        if ($operator === 'BETWEEN') {
            $column = self::columnOperand($predicate['left'] ?? null);
            if ($column === null || !array_key_exists('lower', $predicate) || !array_key_exists('upper', $predicate)) {
                return null;
            }

            return ['column' => $column, 'operator' => 'BETWEEN', 'values' => [
                'lower' => self::literalValue($predicate['lower']),
                'upper' => self::literalValue($predicate['upper']),
            ]];
        }
        if ($operator === 'IS NOT NULL') {
            $column = self::columnOperand($predicate['left'] ?? null);

            return $column === null ? null : ['column' => $column, 'operator' => 'is-not-null', 'values' => true];
        }

        return null;
    }

    /**
     * @return null|array{column:string,operator:string,values:mixed}
     */
    private static function binaryConstraint(array $predicate, string $operator): ?array
    {
        $left = self::columnOperand($predicate['left'] ?? null);
        $right = self::columnOperand($predicate['right'] ?? null);
        if ($left !== null && $right === null && array_key_exists('right', $predicate)) {
            return ['column' => $left, 'operator' => $operator, 'values' => self::literalValue($predicate['right'])];
        }
        if ($right !== null && $left === null && array_key_exists('left', $predicate)) {
            return ['column' => $right, 'operator' => self::reverseRangeOperator($operator), 'values' => self::literalValue($predicate['left'])];
        }

        return null;
    }

    private static function columnOperand(mixed $operand): ?string
    {
        if (!is_array($operand) || array_key_exists('function', $operand)) {
            return null;
        }
        $column = $operand['column'] ?? null;

        return is_string($column) && $column !== '' ? $column : null;
    }

    /**
     * @param list<SQLiteIndexColumn> $columns
     * @param array<string,list<array{column:string,operator:string,values:mixed}>> $constraints
     * @return array{count:int,usedColumns:list<string>,rangeColumn:string|null,equalityPrefix:int}
     */
    private static function usablePrefix(array $columns, array $constraints): array
    {
        $used = [];
        $rangeColumn = null;
        $equalityPrefix = 0;
        foreach ($columns as $column) {
            $matches = $constraints[strtolower($column->columnName)] ?? [];
            $equality = self::firstConstraint($matches, ['point', 'IN']);
            if ($equality !== null && self::hasNonNullValue($equality['values'])) {
                $used[] = $column->columnName;
                $equalityPrefix++;
                continue;
            }

            $range = self::firstRangeConstraint($matches);
            if ($range !== null && self::hasNonNullValue($range['values'])) {
                $used[] = $column->columnName;
                $rangeColumn = $column->columnName;
            }
            break;
        }

        return ['count' => count($used), 'usedColumns' => $used, 'rangeColumn' => $rangeColumn, 'equalityPrefix' => $equalityPrefix];
    }

    /**
     * @param list<array{column:string,operator:string,values:mixed}> $constraints
     * @param list<string> $operators
     * @return null|array{column:string,operator:string,values:mixed}
     */
    private static function firstConstraint(array $constraints, array $operators): ?array
    {
        foreach ($constraints as $constraint) {
            if (in_array($constraint['operator'], $operators, true)) {
                return $constraint;
            }
        }

        return null;
    }

    /**
     * @param list<array{column:string,operator:string,values:mixed}> $constraints
     * @return null|array{column:string,operator:string,values:mixed}
     */
    private static function firstRangeConstraint(array $constraints): ?array
    {
        foreach ($constraints as $constraint) {
            if ($constraint['operator'] === 'BETWEEN' || $constraint['operator'] === 'is-not-null' || str_starts_with($constraint['operator'], 'range-')) {
                return $constraint;
            }
        }

        return null;
    }

    /**
     * @param list<SQLiteIndexColumn> $columns
     * @param list<string> $neededColumns
     */
    private static function coversNeededColumns(array $columns, array $neededColumns): bool
    {
        if ($neededColumns === []) {
            return false;
        }
        $available = [];
        foreach ($columns as $column) {
            $available[strtolower($column->columnName)] = true;
        }
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite covering-index needed columns must be column names');
            }
            if (!isset($available[strtolower($column)])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<SQLiteIndexColumn> $columns
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function orderBySatisfied(array $columns, array $orderBy, int $equalityPrefix): bool
    {
        if ($orderBy === []) {
            return false;
        }
        $orderingColumns = array_slice($columns, $equalityPrefix);
        if (count($orderBy) > count($orderingColumns)) {
            return false;
        }
        foreach ($orderBy as $offset => $order) {
            $column = $order['column'] ?? null;
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite covering-index ORDER BY column must be a column name');
            }
            $direction = strtoupper((string) ($order['direction'] ?? 'ASC'));
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite covering-index ORDER BY direction must be ASC or DESC');
            }
            $indexColumn = $orderingColumns[$offset] ?? null;
            if ($indexColumn === null || strcasecmp($indexColumn->columnName, $column) !== 0) {
                return false;
            }
            if ($indexColumn->descending !== ($direction === 'DESC')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{estimatedRows?:int} $index
     * @param array{count:int,equalityPrefix:int,rangeColumn:string|null} $prefix
     */
    private static function estimatedRows(array $index, array $prefix): int
    {
        $baseRows = $index['estimatedRows'] ?? 1000;
        if (!is_int($baseRows) || $baseRows < 1) {
            throw new \InvalidArgumentException('SQLite covering-index estimatedRows must be a positive integer');
        }
        $selectivity = 1.0;
        for ($i = 0; $i < $prefix['equalityPrefix']; $i++) {
            $selectivity *= 0.08;
        }
        if ($prefix['rangeColumn'] !== null) {
            $selectivity *= 0.25;
        }

        return max(1, min($baseRows, (int) ceil($baseRows * $selectivity)));
    }

    /**
     * @param array{count:int,equalityPrefix:int,rangeColumn:string|null} $prefix
     */
    private static function estimatedCost(int $estimatedRows, array $prefix, bool $covering, bool $orderBySatisfied, bool $partial): int
    {
        $cost = $estimatedRows + 50 - ($prefix['equalityPrefix'] * 12);
        if ($prefix['rangeColumn'] !== null) {
            $cost -= 4;
        }
        if ($covering) {
            $cost -= 20;
        }
        if ($orderBySatisfied) {
            $cost -= 10;
        }
        if ($partial) {
            $cost -= 3;
        }

        return max(1, $cost);
    }

    /**
     * @param list<array<string,mixed>> $terms
     */
    private static function partialPredicateIsImplied(?SQLiteIndexPredicate $predicate, array $terms): bool
    {
        if ($predicate === null) {
            return true;
        }
        if ($predicate->operator === SQLiteIndexPredicate::AND && is_array($predicate->value)) {
            foreach ($predicate->value as $subPredicate) {
                if (!$subPredicate instanceof SQLiteIndexPredicate || !self::partialPredicateIsImplied($subPredicate, $terms)) {
                    return false;
                }
            }

            return true;
        }
        if ($predicate->operator === SQLiteIndexPredicate::OR && is_array($predicate->value)) {
            foreach ($predicate->value as $subPredicate) {
                if ($subPredicate instanceof SQLiteIndexPredicate && self::partialPredicateIsImplied($subPredicate, $terms)) {
                    return true;
                }
            }

            return false;
        }
        foreach ($terms as $term) {
            $constraint = self::ordinaryConstraintFromPredicate($term);
            if ($constraint === null) {
                continue;
            }
            if ($constraint['operator'] === 'point' && $predicate->isImpliedByPointLookup($constraint['column'], $constraint['values'])) {
                return true;
            }
            if ($constraint['operator'] === 'IN' && is_array($constraint['values']) && $predicate->isImpliedByInListLookup($constraint['column'], $constraint['values'])) {
                return true;
            }
            if ($constraint['operator'] === 'is-not-null'
                && $predicate->operator === SQLiteIndexPredicate::IS_NOT_NULL
                && strcasecmp($predicate->columnName, $constraint['column']) === 0
            ) {
                return true;
            }
            if ($constraint['operator'] === 'BETWEEN' && is_array($constraint['values']) && $predicate->isImpliedByRangeLookup(
                $constraint['column'],
                $constraint['values']['lower'] ?? null,
                $constraint['values']['upper'] ?? null,
                true
            )) {
                return true;
            }
            if (str_starts_with($constraint['operator'], 'range-') && self::rangeConstraintImpliesPartialPredicate($predicate, $constraint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{column:string,operator:string,values:mixed} $constraint
     */
    private static function rangeConstraintImpliesPartialPredicate(SQLiteIndexPredicate $predicate, array $constraint): bool
    {
        return match ($constraint['operator']) {
            'range->' => $predicate->isImpliedByRangeLookup($constraint['column'], $constraint['values'], null, false),
            'range->=' => $predicate->isImpliedByRangeLookup($constraint['column'], $constraint['values'], null, true),
            'range-<' => $predicate->isImpliedByRangeLookup($constraint['column'], null, $constraint['values'], false),
            'range-<=' => $predicate->isImpliedByRangeLookup($constraint['column'], null, $constraint['values'], true),
            default => false,
        };
    }

    private static function hasNonNullValue(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::hasNonNullValue($item)) {
                    return true;
                }
            }

            return false;
        }

        return $value !== null;
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

        throw new \InvalidArgumentException('SQLite covering-index constraints need scalar, BLOB, or NULL values');
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

    private static function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite covering-index predicate needs {$key}");
        }

        return $value;
    }

    private static function indexName(string $sql): ?string
    {
        if (preg_match('/^\s*CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?(?<name>"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)/i', $sql, $matches)) {
            return trim($matches['name'], '"`[]');
        }

        return null;
    }
}
