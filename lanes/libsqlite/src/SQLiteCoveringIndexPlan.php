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
     * @param list<string> $outerColumns
     * @param list<string> $neededColumns
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return null|array<string,mixed>
     */
    public static function chooseJoin(array $indexDefinitions, array $predicate, string $targetAlias, array $outerColumns, array $neededColumns, array $orderBy = []): ?array
    {
        $plans = self::rankedJoinPlans($indexDefinitions, $predicate, $targetAlias, $outerColumns, $neededColumns, $orderBy);

        return $plans[0] ?? null;
    }

    /**
     * @param list<array{sql:string,name?:string,rootPage?:int,estimatedRows?:int}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<string> $outerColumns
     * @param list<string> $neededColumns
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return list<array<string,mixed>>
     */
    public static function rankedJoinPlans(array $indexDefinitions, array $predicate, string $targetAlias, array $outerColumns, array $neededColumns, array $orderBy = []): array
    {
        if ($targetAlias === '') {
            throw new \InvalidArgumentException('SQLite covering-index join planner needs a target alias');
        }

        $outerColumnSet = self::outerColumnSet($outerColumns);
        $terms = self::flattenAndTerms($predicate);
        $constraints = self::joinConstraintsByColumn($terms, $targetAlias, $outerColumnSet);
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
            if (!self::partialPredicateIsImpliedByJoin($columns[0]->partialPredicate, $terms, $targetAlias, $outerColumnSet)) {
                continue;
            }

            $prefix = self::usablePrefix($columns, $constraints);
            if ($prefix['count'] === 0) {
                continue;
            }

            $covering = self::coversNeededColumns($columns, self::unqualifiedNeededColumns($neededColumns, $targetAlias));
            $orderBySatisfied = self::orderBySatisfied($columns, self::unqualifiedOrderBy($orderBy, $targetAlias), $prefix['equalityPrefix']);
            $estimatedRowsBeforeStat4 = self::estimatedRows($index, $prefix);
            $stat4 = self::stat4EstimateRows($index, $prefix, $constraints, $estimatedRowsBeforeStat4);
            $estimatedRows = $stat4['estimatedRows'];
            $cost = self::estimatedCost($estimatedRows, $prefix, $covering, $orderBySatisfied, $columns[0]->partial);
            $dependencies = self::outerDependenciesForColumns($prefix['usedColumns'], $constraints);

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
                'estimatedRowsBeforeStat4' => $estimatedRowsBeforeStat4,
                'stat4Used' => $stat4['used'],
                'stat4MatchedSamples' => $stat4['matchedSamples'],
                'estimatedCost' => $cost,
                'residualPredicateRequired' => true,
                'joinLoop' => 'current-next',
                'targetAlias' => $targetAlias,
                'outerDependencies' => $dependencies,
                'deferredEqualityColumns' => array_keys($dependencies),
            ];
        }

        usort($plans, static function (array $left, array $right): int {
            return [$left['estimatedCost'], $left['estimatedRows'], (string) $left['name']]
                <=> [$right['estimatedCost'], $right['estimatedRows'], (string) $right['name']];
        });

        return $plans;
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
            $estimatedRowsBeforeStat4 = self::estimatedRows($index, $prefix);
            $stat4 = self::stat4EstimateRows($index, $prefix, $constraints, $estimatedRowsBeforeStat4);
            $estimatedRows = $stat4['estimatedRows'];
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
                'estimatedRowsBeforeStat4' => $estimatedRowsBeforeStat4,
                'stat4Used' => $stat4['used'],
                'stat4MatchedSamples' => $stat4['matchedSamples'],
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
     * @param list<array<string,mixed>> $terms
     * @param array<string,true> $outerColumnSet
     * @return array<string,list<array{column:string,operator:string,values:mixed,outerColumn?:string}>>
     */
    private static function joinConstraintsByColumn(array $terms, string $targetAlias, array $outerColumnSet): array
    {
        $constraints = [];
        foreach ($terms as $term) {
            $constraint = self::joinConstraintFromPredicate($term, $targetAlias, $outerColumnSet);
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
        if ($operator === 'IS NULL') {
            $column = self::columnOperand($predicate['left'] ?? null);

            return $column === null ? null : ['column' => $column, 'operator' => 'is-null', 'values' => null];
        }

        return null;
    }

    /**
     * @param array<string,true> $outerColumnSet
     * @return null|array{column:string,operator:string,values:mixed,outerColumn?:string}
     */
    private static function joinConstraintFromPredicate(array $predicate, string $targetAlias, array $outerColumnSet): ?array
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator'));
        if ($operator === '=' || $operator === '==') {
            return self::joinBinaryConstraint($predicate, $targetAlias, $outerColumnSet);
        }
        if (in_array($operator, ['<', '<=', '>', '>='], true)) {
            return self::joinRangeConstraint($predicate, $targetAlias);
        }
        if ($operator === 'IN') {
            $column = self::targetColumnOperand($predicate['left'] ?? null, $targetAlias);
            $values = $predicate['values'] ?? null;
            if ($column === null || !is_array($values) || !array_is_list($values)) {
                return null;
            }

            return ['column' => $column, 'operator' => 'IN', 'values' => self::literalList($values)];
        }
        if ($operator === 'BETWEEN') {
            $column = self::targetColumnOperand($predicate['left'] ?? null, $targetAlias);
            if ($column === null || !array_key_exists('lower', $predicate) || !array_key_exists('upper', $predicate)) {
                return null;
            }

            return ['column' => $column, 'operator' => 'BETWEEN', 'values' => [
                'lower' => self::literalValue($predicate['lower']),
                'upper' => self::literalValue($predicate['upper']),
            ]];
        }
        if ($operator === 'IS NOT NULL') {
            $column = self::targetColumnOperand($predicate['left'] ?? null, $targetAlias);

            return $column === null ? null : ['column' => $column, 'operator' => 'is-not-null', 'values' => true];
        }
        if ($operator === 'IS NULL') {
            $column = self::targetColumnOperand($predicate['left'] ?? null, $targetAlias);

            return $column === null ? null : ['column' => $column, 'operator' => 'is-null', 'values' => null];
        }

        return null;
    }

    /**
     * @param array<string,true> $outerColumnSet
     * @return null|array{column:string,operator:string,values:mixed,outerColumn?:string}
     */
    private static function joinBinaryConstraint(array $predicate, string $targetAlias, array $outerColumnSet): ?array
    {
        $leftTarget = self::targetColumnOperand($predicate['left'] ?? null, $targetAlias);
        $rightTarget = self::targetColumnOperand($predicate['right'] ?? null, $targetAlias);
        $leftOuter = self::outerColumnOperand($predicate['left'] ?? null, $targetAlias, $outerColumnSet);
        $rightOuter = self::outerColumnOperand($predicate['right'] ?? null, $targetAlias, $outerColumnSet);

        if ($leftTarget !== null && $rightOuter !== null) {
            return ['column' => $leftTarget, 'operator' => 'point', 'values' => ['outerColumn' => $rightOuter], 'outerColumn' => $rightOuter];
        }
        if ($rightTarget !== null && $leftOuter !== null) {
            return ['column' => $rightTarget, 'operator' => 'point', 'values' => ['outerColumn' => $leftOuter], 'outerColumn' => $leftOuter];
        }
        if (($leftTarget !== null && self::columnOperand($predicate['right'] ?? null) !== null)
            || ($rightTarget !== null && self::columnOperand($predicate['left'] ?? null) !== null)
        ) {
            return null;
        }
        if ($leftTarget !== null && $rightTarget === null && array_key_exists('right', $predicate)) {
            return ['column' => $leftTarget, 'operator' => 'point', 'values' => self::literalValue($predicate['right'])];
        }
        if ($rightTarget !== null && $leftTarget === null && array_key_exists('left', $predicate)) {
            return ['column' => $rightTarget, 'operator' => 'point', 'values' => self::literalValue($predicate['left'])];
        }

        return null;
    }

    /**
     * @return null|array{column:string,operator:string,values:mixed}
     */
    private static function joinRangeConstraint(array $predicate, string $targetAlias): ?array
    {
        $operator = 'range-' . strtoupper(self::requiredString($predicate, 'operator'));
        $left = self::targetColumnOperand($predicate['left'] ?? null, $targetAlias);
        $right = self::targetColumnOperand($predicate['right'] ?? null, $targetAlias);
        if ($left !== null && $right === null && array_key_exists('right', $predicate)) {
            return ['column' => $left, 'operator' => $operator, 'values' => self::literalValue($predicate['right'])];
        }
        if ($right !== null && $left === null && array_key_exists('left', $predicate)) {
            return ['column' => $right, 'operator' => self::reverseRangeOperator($operator), 'values' => self::literalValue($predicate['left'])];
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

    private static function targetColumnOperand(mixed $operand, string $targetAlias): ?string
    {
        if (!is_array($operand) || array_key_exists('function', $operand)) {
            return null;
        }
        $column = $operand['column'] ?? null;
        if (!is_string($column) || $column === '') {
            return null;
        }
        $table = $operand['table'] ?? $operand['alias'] ?? null;
        if ($table === null) {
            return $column;
        }

        return is_string($table) && strcasecmp($table, $targetAlias) === 0 ? $column : null;
    }

    /**
     * @param array<string,true> $outerColumnSet
     */
    private static function outerColumnOperand(mixed $operand, string $targetAlias, array $outerColumnSet): ?string
    {
        if (!is_array($operand) || array_key_exists('function', $operand)) {
            return null;
        }
        $column = $operand['column'] ?? null;
        if (!is_string($column) || $column === '') {
            return null;
        }
        $table = $operand['table'] ?? $operand['alias'] ?? null;
        $qualified = is_string($table) && $table !== '' ? $table . '.' . $column : $column;
        if (is_string($table) && strcasecmp($table, $targetAlias) === 0) {
            return null;
        }

        return isset($outerColumnSet[strtolower($qualified)]) || isset($outerColumnSet[strtolower($column)]) ? $qualified : null;
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
            $equality = self::firstConstraint($matches, ['point', 'IN', 'is-null']);
            if ($equality !== null && self::equalityConstraintHasUsableValue($equality)) {
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
     * @param array<string,mixed> $index
     * @param array{count:int,usedColumns:list<string>,rangeColumn:string|null,equalityPrefix:int} $prefix
     * @param array<string,list<array{column:string,operator:string,values:mixed}>> $constraints
     * @return array{estimatedRows:int,used:bool,matchedSamples:int}
     */
    private static function stat4EstimateRows(array $index, array $prefix, array $constraints, int $fallbackRows): array
    {
        $samples = $index['stat4Samples'] ?? [];
        if ($samples === null || $samples === []) {
            return ['estimatedRows' => $fallbackRows, 'used' => false, 'matchedSamples' => 0];
        }
        if (!is_array($samples) || !array_is_list($samples)) {
            throw new \InvalidArgumentException('SQLite covering-index stat4Samples must be a list');
        }
        if ($prefix['usedColumns'] === []) {
            return ['estimatedRows' => $fallbackRows, 'used' => false, 'matchedSamples' => 0];
        }

        $matchedRows = 0;
        $matchedSamples = 0;
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite covering-index stat4 sample must be an array');
            }
            $rows = $sample['rows'] ?? null;
            if (!is_int($rows) || $rows < 1) {
                throw new \InvalidArgumentException('SQLite covering-index stat4 sample rows must be a positive integer');
            }
            $values = $sample['values'] ?? null;
            if (!is_array($values)) {
                throw new \InvalidArgumentException('SQLite covering-index stat4 sample values must be a column map');
            }
            if (self::stat4SampleMatchesPrefix($values, $prefix['usedColumns'], $constraints)) {
                $matchedRows += $rows;
                $matchedSamples++;
            }
        }

        if ($matchedSamples === 0) {
            return ['estimatedRows' => $fallbackRows, 'used' => false, 'matchedSamples' => 0];
        }

        return ['estimatedRows' => max(1, min($fallbackRows, $matchedRows)), 'used' => true, 'matchedSamples' => $matchedSamples];
    }

    /**
     * @param array<string,mixed> $values
     * @param list<string> $usedColumns
     * @param array<string,list<array{column:string,operator:string,values:mixed}>> $constraints
     */
    private static function stat4SampleMatchesPrefix(array $values, array $usedColumns, array $constraints): bool
    {
        foreach ($usedColumns as $column) {
            $sampleValue = self::stat4SampleValue($values, $column);
            if (!array_key_exists(strtolower($column), $constraints)) {
                return false;
            }
            if (!self::stat4ValueMatchesConstraints($sampleValue, $constraints[strtolower($column)])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function stat4SampleValue(array $values, string $column): mixed
    {
        foreach ($values as $name => $value) {
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('SQLite covering-index stat4 sample column names must be strings');
            }
            if (strcasecmp($name, $column) === 0) {
                return self::literalValue($value);
            }
        }

        throw new \InvalidArgumentException("SQLite covering-index stat4 sample missing {$column}");
    }

    /**
     * @param list<array{column:string,operator:string,values:mixed}> $constraints
     */
    private static function stat4ValueMatchesConstraints(mixed $sampleValue, array $constraints): bool
    {
        foreach ($constraints as $constraint) {
            if (!self::stat4ValueMatchesConstraint($sampleValue, $constraint)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{column:string,operator:string,values:mixed} $constraint
     */
    private static function stat4ValueMatchesConstraint(mixed $sampleValue, array $constraint): bool
    {
        return match ($constraint['operator']) {
            'point' => self::compareSqlValues($sampleValue, $constraint['values']) === 0,
            'is-null' => $sampleValue === null,
            'IN' => is_array($constraint['values']) && self::stat4ValueInList($sampleValue, $constraint['values']),
            'BETWEEN' => is_array($constraint['values'])
                && self::stat4CompareNullable($sampleValue, $constraint['values']['lower'] ?? null) >= 0
                && self::stat4CompareNullable($sampleValue, $constraint['values']['upper'] ?? null) <= 0,
            'range->' => self::stat4CompareNullable($sampleValue, $constraint['values']) > 0,
            'range->=' => self::stat4CompareNullable($sampleValue, $constraint['values']) >= 0,
            'range-<' => self::stat4CompareNullable($sampleValue, $constraint['values']) < 0,
            'range-<=' => self::stat4CompareNullable($sampleValue, $constraint['values']) <= 0,
            'is-not-null' => $sampleValue !== null,
            default => false,
        };
    }

    /**
     * @param list<mixed> $values
     */
    private static function stat4ValueInList(mixed $sampleValue, array $values): bool
    {
        foreach ($values as $value) {
            if (self::compareSqlValues($sampleValue, $value) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function stat4CompareNullable(mixed $left, mixed $right): int
    {
        if ($left === null || $right === null) {
            return -1;
        }

        return self::compareSqlValues($left, $right);
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
        if ($value === null) {
            return 0;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return 1;
        }
        if (is_string($value)) {
            return 2;
        }
        if ($value instanceof SQLiteBlobValue) {
            return 3;
        }

        return 4;
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
            if ($constraint['operator'] === 'is-null' && $predicate->isImpliedByPointLookup($constraint['column'], null)) {
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
     * @param list<array<string,mixed>> $terms
     * @param array<string,true> $outerColumnSet
     */
    private static function partialPredicateIsImpliedByJoin(?SQLiteIndexPredicate $predicate, array $terms, string $targetAlias, array $outerColumnSet): bool
    {
        if ($predicate === null) {
            return true;
        }
        if ($predicate->operator === SQLiteIndexPredicate::AND && is_array($predicate->value)) {
            foreach ($predicate->value as $subPredicate) {
                if (!$subPredicate instanceof SQLiteIndexPredicate || !self::partialPredicateIsImpliedByJoin($subPredicate, $terms, $targetAlias, $outerColumnSet)) {
                    return false;
                }
            }

            return true;
        }
        if ($predicate->operator === SQLiteIndexPredicate::OR && is_array($predicate->value)) {
            foreach ($predicate->value as $subPredicate) {
                if ($subPredicate instanceof SQLiteIndexPredicate && self::partialPredicateIsImpliedByJoin($subPredicate, $terms, $targetAlias, $outerColumnSet)) {
                    return true;
                }
            }

            return false;
        }
        foreach ($terms as $term) {
            $constraint = self::joinConstraintFromPredicate($term, $targetAlias, $outerColumnSet);
            if ($constraint === null) {
                continue;
            }
            if (isset($constraint['outerColumn'])) {
                if ($predicate->operator === SQLiteIndexPredicate::IS_NOT_NULL && strcasecmp($predicate->columnName, $constraint['column']) === 0) {
                    return true;
                }
                continue;
            }
            if ($constraint['operator'] === 'point' && $predicate->isImpliedByPointLookup($constraint['column'], $constraint['values'])) {
                return true;
            }
            if ($constraint['operator'] === 'is-null' && $predicate->isImpliedByPointLookup($constraint['column'], null)) {
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
        if (is_array($value) && isset($value['outerColumn']) && is_string($value['outerColumn'])) {
            return true;
        }
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
     * @param array{operator:string,values:mixed} $constraint
     */
    private static function equalityConstraintHasUsableValue(array $constraint): bool
    {
        if ($constraint['operator'] === 'is-null') {
            return true;
        }

        return self::hasNonNullValue($constraint['values']);
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

    /**
     * @param list<string> $columns
     * @return array<string,true>
     */
    private static function outerColumnSet(array $columns): array
    {
        $set = [];
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite covering-index join outer columns must be column names');
            }
            $set[strtolower($column)] = true;
        }

        return $set;
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private static function unqualifiedNeededColumns(array $columns, string $targetAlias): array
    {
        return array_map(static fn (string $column): string => self::unqualifyColumnName($column, $targetAlias), $columns);
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return list<array{column:string,direction?:string}>
     */
    private static function unqualifiedOrderBy(array $orderBy, string $targetAlias): array
    {
        $normalized = [];
        foreach ($orderBy as $order) {
            $column = $order['column'] ?? null;
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite covering-index ORDER BY column must be a column name');
            }
            $order['column'] = self::unqualifyColumnName($column, $targetAlias);
            $normalized[] = $order;
        }

        return $normalized;
    }

    private static function unqualifyColumnName(string $column, string $targetAlias): string
    {
        $prefix = $targetAlias . '.';
        if (strncasecmp($column, $prefix, strlen($prefix)) === 0) {
            return substr($column, strlen($prefix));
        }

        return $column;
    }

    /**
     * @param list<string> $usedColumns
     * @param array<string,list<array{column:string,operator:string,values:mixed,outerColumn?:string}>> $constraints
     * @return array<string,string>
     */
    private static function outerDependenciesForColumns(array $usedColumns, array $constraints): array
    {
        $dependencies = [];
        foreach ($usedColumns as $column) {
            foreach ($constraints[strtolower($column)] ?? [] as $constraint) {
                if (($constraint['operator'] ?? null) === 'point' && isset($constraint['outerColumn']) && is_string($constraint['outerColumn'])) {
                    $dependencies[$column] = $constraint['outerColumn'];
                    break;
                }
            }
        }

        return $dependencies;
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
