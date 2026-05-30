<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteMultiColumnRangePlan
{
    /**
     * @param list<array{sql:string,name?:string,rootPage?:int,estimatedRows?:int,distinctValues?:array<string,int>,stat4Samples?:list<array{neq:int|list<int>|string,nlt:int|list<int>|string,ndlt?:int|list<int>|string,sample:list<mixed>}>>}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return null|array<string,mixed>
     */
    public static function choose(array $indexDefinitions, array $predicate, array $orderBy = [], array $neededColumns = []): ?array
    {
        $plans = self::rankedPlans($indexDefinitions, $predicate, $orderBy, $neededColumns);

        return $plans[0] ?? null;
    }

    /**
     * @param list<array{sql:string,name?:string,rootPage?:int,estimatedRows?:int,distinctValues?:array<string,int>,stat4Samples?:list<array{neq:int|list<int>|string,nlt:int|list<int>|string,ndlt?:int|list<int>|string,sample:list<mixed>}>>}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return null|array<string,mixed>
     */
    public static function chooseOrRange(array $indexDefinitions, array $predicate, array $orderBy = [], array $neededColumns = []): ?array
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator'));
        if ($operator !== 'OR') {
            $plan = self::choose($indexDefinitions, $predicate, $orderBy, $neededColumns);

            return $plan === null ? null : self::orRangeSummary([$plan]);
        }

        $terms = $predicate['terms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException('SQLite multicolumn range OR predicate needs a non-empty term list');
        }

        $arms = [];
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite multicolumn range OR terms must be predicates');
            }
            $plan = self::choose($indexDefinitions, $term, $orderBy, $neededColumns);
            if ($plan === null) {
                return null;
            }
            $arms[] = $plan;
        }

        return self::orRangeSummary($arms);
    }

    /**
     * @param list<array{sql:string,name?:string,rootPage?:int,estimatedRows?:int,distinctValues?:array<string,int>,stat4Samples?:list<array{neq:int|list<int>|string,nlt:int|list<int>|string,ndlt?:int|list<int>|string,sample:list<mixed>}>>}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function stat4RangeOrder(array $indexDefinitions, array $predicate, array $orderBy, array $neededColumns = []): array
    {
        self::validateOrderBy($orderBy);

        $plans = self::rankedPlans($indexDefinitions, $predicate, $orderBy, $neededColumns);
        $selected = $plans[0] ?? null;
        if ($selected === null) {
            return [
                'status' => 'no-usable-plan',
                'selected' => null,
                'rankedPlanCount' => 0,
                'orderBy' => self::orderByDiagnostics($orderBy),
                'orderBySatisfied' => false,
                'blockSortRequired' => $orderBy !== [],
                'rangeOrderMode' => $orderBy === [] ? 'unordered' : 'no-current-source',
                'detail' => $orderBy === [] ? 'SCAN wp_options' : 'SCAN wp_options USE TEMP B-TREE FOR ORDER BY',
                'dependency_closure' => 'no new support component needed; current-next92 composes native multicolumn planner STAT4 samples and ORDER BY diagnostics only',
            ];
        }

        $orderBySatisfied = (bool) ($selected['orderBySatisfied'] ?? false);
        $blockSort = $orderBy !== [] && !$orderBySatisfied;
        $rangeBoundary = is_array($selected['stat4RangeCurrentNext'] ?? null) ? $selected['stat4RangeCurrentNext'] : null;

        return [
            'status' => 'usable',
            'selected' => $selected['name'],
            'rootPage' => $selected['rootPage'],
            'rankedPlanCount' => count($plans),
            'rankedPlanNames' => array_map(static fn (array $plan): string => (string) $plan['name'], $plans),
            'orderBy' => self::orderByDiagnostics($orderBy),
            'orderBySatisfied' => $orderBySatisfied,
            'blockSortRequired' => $blockSort,
            'rangeOrderMode' => self::rangeOrderMode($selected, $orderBy, $orderBySatisfied),
            'currentSourceColumn' => $selected['stat4CurrentSourceColumn'],
            'currentSourceOffset' => $selected['stat4CurrentSourceOffset'],
            'rangeColumn' => $selected['rangeColumn'],
            'rangeConstraint' => $selected['rangeConstraint'],
            'stat4Used' => $selected['stat4Used'],
            'stat4Estimate' => $selected['stat4Estimate'],
            'stat4MatchedSamples' => $selected['stat4MatchedSamples'],
            'stat4RangeCurrentNext' => $rangeBoundary,
            'stat4MatchedCurrentNext' => $selected['stat4MatchedCurrentNext'],
            'rangeCurrentSourceKeys' => self::rangeCurrentSourceKeys($rangeBoundary),
            'matchedCurrentSourceKeys' => array_map(
                static fn (array $pair): mixed => $pair['current']['key'] ?? null,
                is_array($selected['stat4MatchedCurrentNext']) ? $selected['stat4MatchedCurrentNext'] : [],
            ),
            'estimatedRows' => $selected['estimatedRows'],
            'estimatedCost' => $selected['estimatedCost'],
            'covering' => $selected['covering'],
            'partial' => $selected['partial'],
            'residualPredicateRequired' => $selected['residualPredicateRequired'],
            'usesSkipScan' => $selected['usesSkipScan'],
            'detail' => self::rangeOrderDetail($selected, $orderBySatisfied, $blockSort),
            'nextAlternative' => isset($plans[1]) ? [
                'name' => $plans[1]['name'],
                'estimatedRows' => $plans[1]['estimatedRows'],
                'estimatedCost' => $plans[1]['estimatedCost'],
                'orderBySatisfied' => $plans[1]['orderBySatisfied'],
            ] : null,
            'dependency_closure' => 'no new support component needed; current-next92 composes native multicolumn planner STAT4 samples and ORDER BY diagnostics only',
        ];
    }

    /**
     * @param list<array{sql:string,name?:string,rootPage?:int,estimatedRows?:int,distinctValues?:array<string,int>,stat4Samples?:list<array{neq:int|list<int>|string,nlt:int|list<int>|string,ndlt?:int|list<int>|string,sample:list<mixed>}>>}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    public static function rankedPlans(array $indexDefinitions, array $predicate, array $orderBy = [], array $neededColumns = []): array
    {
        $terms = self::flattenAndTerms($predicate);
        $constraints = self::constraintsByColumn($terms);
        $plans = [];

        foreach ($indexDefinitions as $index) {
            $sql = $index['sql'] ?? null;
            if (!is_string($sql) || $sql === '') {
                throw new \InvalidArgumentException('SQLite multicolumn range planner needs CREATE INDEX SQL text');
            }
            $columns = SQLiteCreateIndex::columns($sql);
            if ($columns === null) {
                continue;
            }
            if (!self::partialPredicateIsImplied($columns[0]->partialPredicate, $terms)) {
                continue;
            }

            $prefix = self::usablePrefix($columns, $constraints);
            if ($prefix['count'] === 0 || $prefix['rangeColumn'] === null) {
                $prefix = self::usableSkipScanPrefix($columns, $constraints, $index);
                if ($prefix['count'] === 0 || $prefix['rangeColumn'] === null) {
                    continue;
                }
            }

            $estimated = self::estimatedRows($index, $prefix);
            $estimatedRows = $estimated['rows'];
            $orderBySatisfied = $prefix['usesSkipScan']
                ? self::skipScanOrderBySatisfied($columns, $orderBy, $prefix['skippedColumns'], $prefix['equalityPrefix'], $prefix['rangeColumn'])
                : self::orderBySatisfied($columns, $orderBy, $prefix['equalityPrefix'], $prefix['rangeColumn']);
            $covering = self::coversNeededColumns($columns, $neededColumns);
            $cost = $estimatedRows + 40 - ($prefix['equalityPrefix'] * 10) + (count($prefix['residualRangeColumns']) * 3) + $prefix['skipScanPenalty'];
            if ($orderBySatisfied) {
                $cost -= 8;
            }
            if ($covering) {
                $cost -= 16;
            }
            if ($columns[0]->partial) {
                $cost -= 4;
            }

            $plans[] = [
                'usable' => true,
                'name' => $index['name'] ?? self::indexName($sql),
                'rootPage' => $index['rootPage'] ?? null,
                'columns' => array_map(static fn (SQLiteIndexColumn $column): string => $column->columnName, $columns),
                'usedColumns' => $prefix['usedColumns'],
                'equalityPrefix' => $prefix['equalityPrefix'],
                'equalityConstraints' => $prefix['equalityConstraints'],
                'rangeColumn' => $prefix['rangeColumn'],
                'rangeConstraint' => $prefix['rangeConstraint'],
                'residualRangeColumns' => $prefix['residualRangeColumns'],
                'residualConstraints' => $prefix['residualConstraints'],
                'residualPredicateRequired' => $prefix['residualConstraints'] !== [],
                'usesSkipScan' => $prefix['usesSkipScan'],
                'skippedColumns' => $prefix['skippedColumns'],
                'skipScanLoops' => $prefix['skipScanLoops'],
                'skipScanPenalty' => $prefix['skipScanPenalty'],
                'currentIndexColumnOffset' => $prefix['currentIndexColumnOffset'],
                'orderBySatisfied' => $orderBySatisfied,
                'covering' => $covering,
                'partial' => $columns[0]->partial,
                'estimatedRows' => $estimatedRows,
                'estimatedCost' => max(1, $cost),
                'stat4Used' => $estimated['stat4Used'],
                'stat4Estimate' => $estimated['stat4Estimate'],
                'stat4MatchedSamples' => $estimated['stat4MatchedSamples'],
                'stat4CurrentNext' => $estimated['stat4CurrentNext'],
                'stat4MatchedCurrentNext' => $estimated['stat4MatchedCurrentNext'],
                'stat4RangeCurrentNext' => $estimated['stat4RangeCurrentNext'],
                'stat4CurrentSourceColumn' => $estimated['stat4CurrentSourceColumn'],
                'stat4CurrentSourceOffset' => $estimated['stat4CurrentSourceOffset'],
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
            throw new \InvalidArgumentException('SQLite multicolumn range AND predicate needs a non-empty term list');
        }

        $flattened = [];
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite multicolumn range AND terms must be predicates');
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
            $constraint = self::constraintFromPredicate($term);
            if ($constraint !== null) {
                $constraints[strtolower($constraint['column'])][] = $constraint;
            }
        }

        return $constraints;
    }

    /**
     * @return null|array{column:string,operator:string,values:mixed}
     */
    private static function constraintFromPredicate(array $predicate): ?array
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator'));
        if ($operator === '=' || $operator === '==') {
            return self::binaryConstraint($predicate, 'point');
        }
        if (in_array($operator, ['<', '<=', '>', '>='], true)) {
            return self::binaryConstraint($predicate, 'range-' . $operator);
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
        if ($operator === 'IN') {
            $column = self::columnOperand($predicate['left'] ?? null);
            $values = $predicate['values'] ?? null;
            if ($column === null || !is_array($values) || !array_is_list($values)) {
                return null;
            }

            return ['column' => $column, 'operator' => 'IN', 'values' => array_map(static fn (mixed $value): mixed => self::literalValue($value), $values)];
        }
        if ($operator === 'IS NULL') {
            $column = self::columnOperand($predicate['left'] ?? null);

            return $column === null ? null : ['column' => $column, 'operator' => 'is-null', 'values' => null];
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
     * @return array{count:int,usedColumns:list<string>,equalityPrefix:int,equalityConstraints:list<array{column:string,operator:string,values:mixed}>,rangeColumn:string|null,rangeConstraint:array{column:string,operator:string,values:mixed}|null,residualRangeColumns:list<string>,residualConstraints:list<array{column:string,operator:string,values:mixed}>,usesSkipScan:bool,skippedColumns:list<string>,skipScanLoops:int,skipScanPenalty:int,currentIndexColumnOffset:int}
     */
    private static function usablePrefix(array $columns, array $constraints): array
    {
        $used = [];
        $equalityConstraints = [];
        $equalityPrefix = 0;
        $rangeColumn = null;
        $rangeConstraint = null;
        $residualConstraints = [];
        $pastRange = false;

        foreach ($columns as $column) {
            $matches = $constraints[strtolower($column->columnName)] ?? [];
            if ($pastRange) {
                array_push($residualConstraints, ...self::rangeConstraints($matches));
                continue;
            }

            $equality = self::firstConstraint($matches, ['point', 'IN', 'is-null']);
            if ($equality !== null && self::equalityConstraintHasUsableValue($equality)) {
                $used[] = $column->columnName;
                $equalityConstraints[] = $equality;
                $equalityPrefix++;
                continue;
            }

            $range = self::tightRangeConstraint($matches);
            if ($range !== null && self::hasNonNullValue($range['values'])) {
                $used[] = $column->columnName;
                $rangeColumn = $column->columnName;
                $rangeConstraint = $range;
                $pastRange = true;
                continue;
            }
            break;
        }

        $residualRangeColumns = [];
        foreach ($residualConstraints as $constraint) {
            $key = strtolower($constraint['column']);
            $residualRangeColumns[$key] = $constraint['column'];
        }

        return [
            'count' => count($used),
            'usedColumns' => $used,
            'equalityPrefix' => $equalityPrefix,
            'equalityConstraints' => $equalityConstraints,
            'rangeColumn' => $rangeColumn,
            'rangeConstraint' => $rangeConstraint,
            'residualRangeColumns' => array_values($residualRangeColumns),
            'residualConstraints' => $residualConstraints,
            'usesSkipScan' => false,
            'skippedColumns' => [],
            'skipScanLoops' => 1,
            'skipScanPenalty' => 0,
            'currentIndexColumnOffset' => max(0, $equalityPrefix),
        ];
    }

    /**
     * @param list<SQLiteIndexColumn> $columns
     * @param array<string,list<array{column:string,operator:string,values:mixed}>> $constraints
     * @param array{distinctValues?:array<string,int>} $index
     * @return array{count:int,usedColumns:list<string>,equalityPrefix:int,equalityConstraints:list<array{column:string,operator:string,values:mixed}>,rangeColumn:string|null,rangeConstraint:array{column:string,operator:string,values:mixed}|null,residualRangeColumns:list<string>,residualConstraints:list<array{column:string,operator:string,values:mixed}>,usesSkipScan:bool,skippedColumns:list<string>,skipScanLoops:int,skipScanPenalty:int,currentIndexColumnOffset:int}
     */
    private static function usableSkipScanPrefix(array $columns, array $constraints, array $index): array
    {
        $empty = [
            'count' => 0,
            'usedColumns' => [],
            'equalityPrefix' => 0,
            'equalityConstraints' => [],
            'rangeColumn' => null,
            'rangeConstraint' => null,
            'residualRangeColumns' => [],
            'residualConstraints' => [],
            'usesSkipScan' => false,
            'skippedColumns' => [],
            'skipScanLoops' => 1,
            'skipScanPenalty' => 0,
            'currentIndexColumnOffset' => 0,
        ];

        $columnCount = count($columns);
        for ($start = 1; $start < $columnCount; $start++) {
            $skippedColumns = array_map(
                static fn (SQLiteIndexColumn $column): string => $column->columnName,
                array_slice($columns, 0, $start),
            );
            if (!self::skippedColumnsHaveLoopEvidence($index, $skippedColumns)) {
                continue;
            }

            $used = [];
            $equalityConstraints = [];
            $equalityPrefix = 0;
            $rangeColumn = null;
            $rangeConstraint = null;
            $residualConstraints = [];
            $pastRange = false;

            for ($offset = $start; $offset < $columnCount; $offset++) {
                $column = $columns[$offset];
                $matches = $constraints[strtolower($column->columnName)] ?? [];
                if ($pastRange) {
                    array_push($residualConstraints, ...self::rangeConstraints($matches));
                    continue;
                }

                $equality = self::firstConstraint($matches, ['point', 'IN', 'is-null']);
                if ($equality !== null && self::equalityConstraintHasUsableValue($equality)) {
                    $used[] = $column->columnName;
                    $equalityConstraints[] = $equality;
                    $equalityPrefix++;
                    continue;
                }

                $range = self::tightRangeConstraint($matches);
                if ($range !== null && self::hasNonNullValue($range['values'])) {
                    $used[] = $column->columnName;
                    $rangeColumn = $column->columnName;
                    $rangeConstraint = $range;
                    $pastRange = true;
                    continue;
                }
                break;
            }

            if ($equalityPrefix === 0 || $rangeColumn === null) {
                continue;
            }

            $residualRangeColumns = [];
            foreach ($residualConstraints as $constraint) {
                $key = strtolower($constraint['column']);
                $residualRangeColumns[$key] = $constraint['column'];
            }

            $skipScanLoops = self::skipScanLoops($index, $skippedColumns);

            return [
                'count' => count($used),
                'usedColumns' => $used,
                'equalityPrefix' => $equalityPrefix,
                'equalityConstraints' => $equalityConstraints,
                'rangeColumn' => $rangeColumn,
                'rangeConstraint' => $rangeConstraint,
                'residualRangeColumns' => array_values($residualRangeColumns),
                'residualConstraints' => $residualConstraints,
                'usesSkipScan' => true,
                'skippedColumns' => $skippedColumns,
                'skipScanLoops' => $skipScanLoops,
                'skipScanPenalty' => 18 + ($skipScanLoops * 4) + (($start - 1) * 8),
                'currentIndexColumnOffset' => $start + $equalityPrefix,
            ];
        }

        return $empty;
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
    private static function tightRangeConstraint(array $constraints): ?array
    {
        $ranges = self::rangeConstraints($constraints);
        if (count($ranges) === 1 && ($ranges[0]['operator'] ?? null) === 'BETWEEN') {
            return $ranges[0];
        }
        $lower = null;
        $upper = null;
        foreach ($ranges as $constraint) {
            if ($constraint['operator'] === 'BETWEEN' && is_array($constraint['values'])) {
                $betweenLower = ['column' => $constraint['column'], 'operator' => 'range->=', 'values' => $constraint['values']['lower'] ?? null];
                $betweenUpper = ['column' => $constraint['column'], 'operator' => 'range-<=', 'values' => $constraint['values']['upper'] ?? null];
                $lower = $lower === null || self::lowerBoundIsTighter($betweenLower, $lower) ? $betweenLower : $lower;
                $upper = $upper === null || self::upperBoundIsTighter($betweenUpper, $upper) ? $betweenUpper : $upper;
                continue;
            }
            if ($constraint['operator'] === 'range->' || $constraint['operator'] === 'range->=') {
                $lower = $lower === null || self::lowerBoundIsTighter($constraint, $lower) ? $constraint : $lower;
                continue;
            }
            if ($constraint['operator'] === 'range-<' || $constraint['operator'] === 'range-<=') {
                $upper = $upper === null || self::upperBoundIsTighter($constraint, $upper) ? $constraint : $upper;
            }
        }
        if ($lower !== null && $upper !== null) {
            $comparison = self::compareStat4Keys($lower['values'], $upper['values']);
            if ($comparison > 0 || ($comparison === 0 && ($lower['operator'] !== 'range->=' || $upper['operator'] !== 'range-<='))) {
                return null;
            }

            return [
                'column' => $lower['column'],
                'operator' => 'range-bounded',
                'values' => [
                    'lower' => $lower['values'],
                    'upper' => $upper['values'],
                    'lowerInclusive' => $lower['operator'] === 'range->=',
                    'upperInclusive' => $upper['operator'] === 'range-<=',
                ],
            ];
        }

        return $lower ?? $upper;
    }

    /**
     * @param array{operator:string,values:mixed} $candidate
     * @param array{operator:string,values:mixed} $current
     */
    private static function lowerBoundIsTighter(array $candidate, array $current): bool
    {
        $comparison = self::compareStat4Keys($candidate['values'], $current['values']);
        if ($comparison !== 0) {
            return $comparison > 0;
        }

        return $candidate['operator'] === 'range->' && $current['operator'] === 'range->=';
    }

    /**
     * @param array{operator:string,values:mixed} $candidate
     * @param array{operator:string,values:mixed} $current
     */
    private static function upperBoundIsTighter(array $candidate, array $current): bool
    {
        $comparison = self::compareStat4Keys($candidate['values'], $current['values']);
        if ($comparison !== 0) {
            return $comparison < 0;
        }

        return $candidate['operator'] === 'range-<' && $current['operator'] === 'range-<=';
    }

    /**
     * @param list<array{column:string,operator:string,values:mixed}> $constraints
     * @return list<array{column:string,operator:string,values:mixed}>
     */
    private static function rangeConstraints(array $constraints): array
    {
        return array_values(array_filter(
            $constraints,
            static fn (array $constraint): bool => $constraint['operator'] === 'BETWEEN' || str_starts_with($constraint['operator'], 'range-')
        ));
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
                throw new \InvalidArgumentException('SQLite multicolumn range covering columns must be column names');
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
    private static function orderBySatisfied(array $columns, array $orderBy, int $equalityPrefix, ?string $rangeColumn): bool
    {
        if ($orderBy === [] || $rangeColumn === null) {
            return false;
        }
        $orderingColumns = array_slice($columns, $equalityPrefix);
        if (count($orderBy) > count($orderingColumns)) {
            return false;
        }
        foreach ($orderBy as $offset => $order) {
            $column = $order['column'] ?? null;
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite multicolumn range ORDER BY column must be a column name');
            }
            $direction = strtoupper((string) ($order['direction'] ?? 'ASC'));
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite multicolumn range ORDER BY direction must be ASC or DESC');
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
     * @param non-empty-list<array<string,mixed>> $arms
     * @return array<string,mixed>
     */
    private static function orRangeSummary(array $arms): array
    {
        $indexNames = array_values(array_unique(array_map(
            static fn (array $arm): ?string => isset($arm['name']) ? (string) $arm['name'] : null,
            $arms
        )));
        $rootPages = array_values(array_unique(array_map(
            static fn (array $arm): mixed => $arm['rootPage'] ?? null,
            $arms
        ), SORT_REGULAR));
        $rangeColumns = array_values(array_unique(array_map(
            static fn (array $arm): ?string => isset($arm['rangeColumn']) ? (string) $arm['rangeColumn'] : null,
            $arms
        )));

        $estimatedRows = 0;
        $estimatedCost = 18;
        $currentNextLoops = 0;
        $residualPredicateRequired = false;
        foreach ($arms as $arm) {
            $estimatedRows += (int) ($arm['estimatedRows'] ?? 0);
            $estimatedCost += (int) ($arm['estimatedCost'] ?? 0);
            $currentNextLoops += self::currentNextLoopsForPlan($arm);
            $residualPredicateRequired = $residualPredicateRequired || (($arm['residualPredicateRequired'] ?? false) === true);
        }

        return [
            'usable' => true,
            'strategy' => count($indexNames) === 1 ? 'single-index-or' : 'multi-index-or',
            'rowidUnionRequired' => count($arms) > 1,
            'armCount' => count($arms),
            'arms' => $arms,
            'indexNames' => $indexNames,
            'rootPages' => $rootPages,
            'rangeColumns' => $rangeColumns,
            'currentNextLoops' => $currentNextLoops,
            'estimatedRows' => max(1, $estimatedRows),
            'estimatedCost' => max(1, $estimatedCost),
            'residualPredicateRequired' => $residualPredicateRequired,
        ];
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function currentNextLoopsForPlan(array $plan): int
    {
        $loops = 1;
        $equalityConstraints = $plan['equalityConstraints'] ?? [];
        if (is_array($equalityConstraints)) {
            foreach ($equalityConstraints as $constraint) {
                if (!is_array($constraint) || ($constraint['operator'] ?? null) !== 'IN') {
                    continue;
                }
                $values = $constraint['values'] ?? [];
                if (!is_array($values)) {
                    continue;
                }
                $nonNullValues = array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));
                $loops *= max(1, count(array_unique($nonNullValues, SORT_REGULAR)));
            }
        }

        return $loops;
    }

    /**
     * @param array{estimatedRows?:int,stat4Samples?:list<array{neq:int|list<int>|string,nlt:int|list<int>|string,ndlt?:int|list<int>|string,sample:list<mixed>}>>} $index
     * @param array{equalityPrefix:int,equalityConstraints:list<array{column:string,operator:string,values:mixed}>,rangeColumn:string|null,rangeConstraint:array{column:string,operator:string,values:mixed}|null,residualRangeColumns:list<string>,usesSkipScan?:bool,skipScanLoops?:int,currentIndexColumnOffset:int} $prefix
     * @return array{rows:int,stat4Used:bool,stat4Estimate:int|null,stat4MatchedSamples:int,stat4CurrentNext:list<array{current:array<string,mixed>,next:array<string,mixed>|null}>,stat4MatchedCurrentNext:list<array{current:array<string,mixed>,next:array<string,mixed>|null}>,stat4RangeCurrentNext:array<string,mixed>|null,stat4CurrentSourceColumn:string|null,stat4CurrentSourceOffset:int|null}
     */
    private static function estimatedRows(array $index, array $prefix): array
    {
        $baseRows = $index['estimatedRows'] ?? 1000;
        if (!is_int($baseRows) || $baseRows < 1) {
            throw new \InvalidArgumentException('SQLite multicolumn range estimatedRows must be a positive integer');
        }
        $selectivity = 0.25;
        for ($i = 0; $i < $prefix['equalityPrefix']; $i++) {
            $selectivity *= 0.08;
        }
        if (($prefix['usesSkipScan'] ?? false) === true) {
            $selectivity *= min(1.0, max(1, $prefix['skipScanLoops'] ?? 1) * 0.35);
        }

        $fallbackRows = max(1, min($baseRows, (int) ceil($baseRows * $selectivity)));
        $stat4 = self::stat4Estimate($index['stat4Samples'] ?? [], $prefix, $baseRows);
        if ($stat4 === null) {
            return [
                'rows' => $fallbackRows,
                'stat4Used' => false,
                'stat4Estimate' => null,
                'stat4MatchedSamples' => 0,
                'stat4CurrentNext' => [],
                'stat4MatchedCurrentNext' => [],
                'stat4RangeCurrentNext' => null,
                'stat4CurrentSourceColumn' => null,
                'stat4CurrentSourceOffset' => null,
            ];
        }

        return [
            'rows' => max(1, min($baseRows, $stat4['rows'])),
            'stat4Used' => true,
            'stat4Estimate' => $stat4['rows'],
            'stat4MatchedSamples' => $stat4['matchedSamples'],
            'stat4CurrentNext' => $stat4['currentNext'],
            'stat4MatchedCurrentNext' => $stat4['matchedCurrentNext'],
            'stat4RangeCurrentNext' => $stat4['rangeCurrentNext'],
            'stat4CurrentSourceColumn' => $stat4['currentSourceColumn'],
            'stat4CurrentSourceOffset' => $stat4['currentSourceOffset'],
        ];
    }

    /**
     * @param mixed $samples
     * @param array{equalityConstraints:list<array{column:string,operator:string,values:mixed}>,rangeColumn:string|null,rangeConstraint:array{column:string,operator:string,values:mixed}|null,currentIndexColumnOffset:int} $prefix
     * @return null|array{rows:int,matchedSamples:int,currentNext:list<array{current:array<string,mixed>,next:array<string,mixed>|null}>,matchedCurrentNext:list<array{current:array<string,mixed>,next:array<string,mixed>|null}>,rangeCurrentNext:array<string,mixed>|null,currentSourceColumn:string,currentSourceOffset:int}
     */
    private static function stat4Estimate(mixed $samples, array $prefix, int $baseRows): ?array
    {
        if ($samples === [] || $samples === null) {
            return null;
        }
        if (!is_array($samples) || !array_is_list($samples)) {
            throw new \InvalidArgumentException('SQLite multicolumn range stat4Samples must be a list');
        }
        $range = $prefix['rangeConstraint'] ?? null;
        $rangeColumn = $prefix['rangeColumn'] ?? null;
        if (!is_array($range) || !is_string($rangeColumn)) {
            return null;
        }
        $offset = $prefix['currentIndexColumnOffset'];
        if (!is_int($offset) || $offset < 0) {
            return null;
        }

        $normalized = [];
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite multicolumn range stat4Samples rows must be arrays');
            }
            $sampleValues = $sample['sample'] ?? null;
            if ($sampleValues === null && array_key_exists('prefix', $sample) && array_key_exists('suffix', $sample)) {
                return null;
            }
            if (!is_array($sampleValues) || !array_is_list($sampleValues) || !array_key_exists($offset, $sampleValues)) {
                throw new \InvalidArgumentException('SQLite multicolumn range stat4 sample must contain the current source column');
            }
            if (!self::stat4SampleMatchesEqualityPrefix($sampleValues, $prefix['equalityConstraints'])) {
                continue;
            }
            $normalized[] = [
                'key' => self::literalValue($sampleValues[$offset]),
                'neq' => self::stat4IntegerAt($sample['neq'] ?? null, 'neq', $offset),
                'nlt' => self::stat4IntegerAt($sample['nlt'] ?? null, 'nlt', $offset, true),
                'ndlt' => self::stat4IntegerAt($sample['ndlt'] ?? 0, 'ndlt', $offset, true),
                'sample' => $sampleValues,
            ];
        }
        if ($normalized === []) {
            return null;
        }

        usort($normalized, static fn (array $left, array $right): int => self::compareStat4Keys($left['key'], $right['key']));
        $matched = self::stat4MatchingSamples($normalized, $range);
        $rows = self::stat4Rows($normalized, $range, $baseRows);

        return [
            'rows' => $rows,
            'matchedSamples' => count($matched),
            'currentNext' => self::stat4CurrentNext($normalized),
            'matchedCurrentNext' => self::stat4CurrentNext($matched),
            'rangeCurrentNext' => self::stat4RangeCurrentNext($normalized, $range),
            'currentSourceColumn' => $rangeColumn,
            'currentSourceOffset' => $offset,
        ];
    }

    /**
     * @param list<mixed> $sampleValues
     * @param list<array{column:string,operator:string,values:mixed}> $constraints
     */
    private static function stat4SampleMatchesEqualityPrefix(array $sampleValues, array $constraints): bool
    {
        foreach ($constraints as $offset => $constraint) {
            if (!array_key_exists($offset, $sampleValues)) {
                return false;
            }
            $value = self::literalValue($sampleValues[$offset]);
            if (($constraint['operator'] ?? null) === 'point' && self::compareStat4Keys($value, $constraint['values']) !== 0) {
                return false;
            }
            if (($constraint['operator'] ?? null) === 'is-null' && $value !== null) {
                return false;
            }
            if (($constraint['operator'] ?? null) === 'IN') {
                $matched = false;
                foreach (is_array($constraint['values']) ? $constraint['values'] : [] as $candidate) {
                    if ($candidate !== null && self::compareStat4Keys($value, $candidate) === 0) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function stat4IntegerAt(mixed $value, string $field, int $offset, bool $allowZero = false): int
    {
        if (is_string($value) && preg_match('/^\d+(?:\s+\d+)*$/', trim($value)) === 1) {
            $parts = preg_split('/\s+/', trim($value));
            $value = (int) ($parts[$offset] ?? $parts[0] ?? 0);
        } elseif (is_array($value) && array_is_list($value)) {
            $value = $value[$offset] ?? $value[0] ?? null;
        }
        if (!is_int($value) || $value < ($allowZero ? 0 : 1)) {
            $kind = $allowZero ? 'unsigned integer' : 'positive integer';
            throw new \InvalidArgumentException("SQLite multicolumn range stat4 {$field} must contain a {$kind}");
        }

        return $value;
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int,sample:list<mixed>}> $samples
     * @param array{operator:string,values:mixed} $constraint
     */
    private static function stat4Rows(array $samples, array $constraint, int $baseRows): int
    {
        $operator = $constraint['operator'];
        $values = $constraint['values'];
        if (($operator === 'BETWEEN' || $operator === 'range-bounded') && is_array($values)) {
            $lowerInclusive = $operator === 'BETWEEN' || (bool) ($values['lowerInclusive'] ?? false);
            $upperInclusive = $operator === 'BETWEEN' || (bool) ($values['upperInclusive'] ?? false);
            $rows = self::stat4LessThanRows($samples, $values['upper'] ?? null, $upperInclusive, $baseRows)
                - self::stat4LessThanRows($samples, $values['lower'] ?? null, !$lowerInclusive, $baseRows);

            return max(1, min($baseRows, $rows));
        }
        $rows = match ($operator) {
            'range-<' => self::stat4LessThanRows($samples, $values, false, $baseRows),
            'range-<=' => self::stat4LessThanRows($samples, $values, true, $baseRows),
            'range->' => $baseRows - self::stat4LessThanRows($samples, $values, true, $baseRows),
            'range->=' => $baseRows - self::stat4LessThanRows($samples, $values, false, $baseRows),
            default => null,
        };

        return $rows === null ? $baseRows : max(1, min($baseRows, $rows));
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int,sample:list<mixed>}> $samples
     * @param array{operator:string,values:mixed} $constraint
     * @return list<array{key:mixed,neq:int,nlt:int,ndlt:int,sample:list<mixed>}>
     */
    private static function stat4MatchingSamples(array $samples, array $constraint): array
    {
        return array_values(array_filter(
            $samples,
            static fn (array $sample): bool => self::stat4SampleMatches($sample['key'], $constraint),
        ));
    }

    /**
     * @param array{operator:string,values:mixed} $constraint
     */
    private static function stat4SampleMatches(mixed $key, array $constraint): bool
    {
        $operator = $constraint['operator'];
        $values = $constraint['values'];
        if (($operator === 'BETWEEN' || $operator === 'range-bounded') && is_array($values)) {
            $lowerComparison = self::compareStat4Keys($key, $values['lower'] ?? null);
            $upperComparison = self::compareStat4Keys($key, $values['upper'] ?? null);
            $lowerMatches = ($operator === 'BETWEEN' || (bool) ($values['lowerInclusive'] ?? false)) ? $lowerComparison >= 0 : $lowerComparison > 0;
            $upperMatches = ($operator === 'BETWEEN' || (bool) ($values['upperInclusive'] ?? false)) ? $upperComparison <= 0 : $upperComparison < 0;

            return $lowerMatches && $upperMatches;
        }
        if ($operator === 'range-<') {
            return self::compareStat4Keys($key, $values) < 0;
        }
        if ($operator === 'range-<=') {
            return self::compareStat4Keys($key, $values) <= 0;
        }
        if ($operator === 'range->') {
            return self::compareStat4Keys($key, $values) > 0;
        }
        if ($operator === 'range->=') {
            return self::compareStat4Keys($key, $values) >= 0;
        }

        return false;
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int,sample:list<mixed>}> $samples
     * @return list<array{current:array<string,mixed>,next:array<string,mixed>|null}>
     */
    private static function stat4CurrentNext(array $samples): array
    {
        $pairs = [];
        foreach ($samples as $offset => $sample) {
            $pairs[] = [
                'current' => self::stat4BoundarySample($sample),
                'next' => isset($samples[$offset + 1]) ? self::stat4BoundarySample($samples[$offset + 1]) : null,
            ];
        }

        return $pairs;
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int,sample:list<mixed>}> $samples
     * @param array{operator:string,values:mixed} $constraint
     * @return array{lower:array<string,mixed>|null,upper:array<string,mixed>|null,lowerInclusive:bool,upperInclusive:bool,emptyGap:bool}
     */
    private static function stat4RangeCurrentNext(array $samples, array $constraint): array
    {
        $operator = $constraint['operator'];
        $values = $constraint['values'];
        $lower = null;
        $upper = null;
        $lowerInclusive = false;
        $upperInclusive = false;
        if (($operator === 'BETWEEN' || $operator === 'range-bounded') && is_array($values)) {
            $lower = $values['lower'] ?? null;
            $upper = $values['upper'] ?? null;
            $lowerInclusive = $operator === 'BETWEEN' || (bool) ($values['lowerInclusive'] ?? false);
            $upperInclusive = $operator === 'BETWEEN' || (bool) ($values['upperInclusive'] ?? false);
        } elseif ($operator === 'range->' || $operator === 'range->=') {
            $lower = $values;
            $lowerInclusive = $operator === 'range->=';
        } elseif ($operator === 'range-<' || $operator === 'range-<=') {
            $upper = $values;
            $upperInclusive = $operator === 'range-<=';
        }

        $lowerPair = self::stat4BoundaryCurrentNext($samples, $lower, 'lower');
        $upperPair = self::stat4BoundaryCurrentNext($samples, $upper, 'upper');

        return [
            'lower' => $lowerPair,
            'upper' => $upperPair,
            'lowerInclusive' => $lowerInclusive,
            'upperInclusive' => $upperInclusive,
            'emptyGap' => $lowerPair !== null
                && $upperPair !== null
                && ($lowerPair['current']['key'] ?? null) === ($upperPair['current']['key'] ?? null)
                && ($lowerPair['next']['key'] ?? null) === ($upperPair['next']['key'] ?? null)
                && $lower !== null
                && $upper !== null
                && self::compareStat4Keys($lower, $upper) < 0,
        ];
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int,sample:list<mixed>}> $samples
     * @return array{current:array<string,mixed>|null,next:array<string,mixed>|null,side:string,value:mixed,exact:bool}|null
     */
    private static function stat4BoundaryCurrentNext(array $samples, mixed $value, string $side): ?array
    {
        if ($value === null || $samples === []) {
            return null;
        }

        $previous = null;
        foreach ($samples as $offset => $sample) {
            $comparison = self::compareStat4Keys($sample['key'], $value);
            if ($comparison >= 0) {
                return [
                    'current' => $comparison === 0 ? self::stat4BoundarySample($sample) : ($previous === null ? null : self::stat4BoundarySample($previous)),
                    'next' => $comparison === 0
                        ? (isset($samples[$offset + 1]) ? self::stat4BoundarySample($samples[$offset + 1]) : null)
                        : self::stat4BoundarySample($sample),
                    'side' => $side,
                    'value' => $value,
                    'exact' => $comparison === 0,
                ];
            }
            $previous = $sample;
        }

        return [
            'current' => $previous === null ? null : self::stat4BoundarySample($previous),
            'next' => null,
            'side' => $side,
            'value' => $value,
            'exact' => false,
        ];
    }

    /**
     * @param array{key:mixed,neq:int,nlt:int,ndlt:int} $sample
     * @return array{key:mixed,neq:int,nlt:int,ndlt:int}
     */
    private static function stat4BoundarySample(array $sample): array
    {
        return [
            'key' => $sample['key'],
            'neq' => $sample['neq'],
            'nlt' => $sample['nlt'],
            'ndlt' => $sample['ndlt'],
        ];
    }

    private static function stat4LessThanRows(array $samples, mixed $value, bool $inclusive, int $baseRows): int
    {
        $previous = 0;
        foreach ($samples as $sample) {
            $comparison = self::compareStat4Keys($sample['key'], $value);
            if ($comparison > 0 || ($comparison === 0 && !$inclusive)) {
                return max(0, min($baseRows, $sample['nlt']));
            }
            $previous = $sample['nlt'] + $sample['neq'];
            if ($comparison === 0) {
                return max(0, min($baseRows, $inclusive ? $previous : $sample['nlt']));
            }
        }

        return max(0, min($baseRows, $previous));
    }

    private static function compareStat4Keys(mixed $left, mixed $right): int
    {
        if (is_int($left) || is_float($left) || is_int($right) || is_float($right)) {
            return ((float) $left) <=> ((float) $right);
        }

        return strcmp((string) $left, (string) $right);
    }

    /**
     * @param array{distinctValues?:array<string,int>} $index
     * @param list<string> $skippedColumns
     */
    private static function skippedColumnsHaveLoopEvidence(array $index, array $skippedColumns): bool
    {
        foreach ($skippedColumns as $column) {
            if (self::distinctValueCount($index, $column) < 2) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{distinctValues?:array<string,int>} $index
     * @param list<string> $skippedColumns
     */
    private static function skipScanLoops(array $index, array $skippedColumns): int
    {
        $loops = 1;
        foreach ($skippedColumns as $column) {
            $loops *= self::distinctValueCount($index, $column);
        }

        return max(1, $loops);
    }

    /**
     * @param array{distinctValues?:array<string,int>} $index
     */
    private static function distinctValueCount(array $index, string $column): int
    {
        $distinctValues = $index['distinctValues'] ?? [];
        if (!is_array($distinctValues)) {
            return 0;
        }
        foreach ($distinctValues as $key => $count) {
            if (strcasecmp((string) $key, $column) !== 0) {
                continue;
            }
            if (!is_int($count) || $count < 1) {
                throw new \InvalidArgumentException('SQLite multicolumn range distinctValues counts must be positive integers');
            }

            return $count;
        }

        return 0;
    }

    /**
     * @param list<SQLiteIndexColumn> $columns
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $skippedColumns
     */
    private static function skipScanOrderBySatisfied(array $columns, array $orderBy, array $skippedColumns, int $equalityPrefix, ?string $rangeColumn): bool
    {
        if ($orderBy === [] || $rangeColumn === null) {
            return false;
        }
        $leadingOrder = array_slice($orderBy, 0, count($skippedColumns));
        if (count($leadingOrder) !== count($skippedColumns)) {
            self::validateOrderBy($orderBy);

            return false;
        }
        foreach ($leadingOrder as $offset => $order) {
            $column = self::orderColumn($order);
            if (strcasecmp($column, $skippedColumns[$offset]) !== 0) {
                return false;
            }
        }

        return self::orderBySatisfied($columns, $orderBy, 0, $rangeColumn);
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function validateOrderBy(array $orderBy): void
    {
        foreach ($orderBy as $order) {
            self::orderColumn($order);
            self::orderDirection($order);
        }
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return list<array{column:string,direction:string,position:int}>
     */
    private static function orderByDiagnostics(array $orderBy): array
    {
        $diagnostics = [];
        foreach ($orderBy as $offset => $order) {
            $diagnostics[] = [
                'column' => self::orderColumn($order),
                'direction' => self::orderDirection($order),
                'position' => $offset + 1,
            ];
        }

        return $diagnostics;
    }

    /**
     * @param array<string,mixed> $selected
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function rangeOrderMode(array $selected, array $orderBy, bool $orderBySatisfied): string
    {
        if ($orderBy === []) {
            return 'unordered-range';
        }
        if ($orderBySatisfied && ($selected['usesSkipScan'] ?? false) === true) {
            return 'skip-scan-current-source-order';
        }
        if ($orderBySatisfied) {
            return 'range-current-source-order';
        }

        return 'temp-btree-order';
    }

    /**
     * @param array<string,mixed>|null $rangeBoundary
     * @return array{lower:mixed,lowerNext:mixed,upper:mixed,upperNext:mixed}
     */
    private static function rangeCurrentSourceKeys(?array $rangeBoundary): array
    {
        $lower = is_array($rangeBoundary['lower'] ?? null) ? $rangeBoundary['lower'] : null;
        $upper = is_array($rangeBoundary['upper'] ?? null) ? $rangeBoundary['upper'] : null;

        return [
            'lower' => is_array($lower['current'] ?? null) ? ($lower['current']['key'] ?? null) : null,
            'lowerNext' => is_array($lower['next'] ?? null) ? ($lower['next']['key'] ?? null) : null,
            'upper' => is_array($upper['current'] ?? null) ? ($upper['current']['key'] ?? null) : null,
            'upperNext' => is_array($upper['next'] ?? null) ? ($upper['next']['key'] ?? null) : null,
        ];
    }

    /**
     * @param array<string,mixed> $selected
     */
    private static function rangeOrderDetail(array $selected, bool $orderBySatisfied, bool $blockSort): string
    {
        $detail = 'SEARCH ' . (string) $selected['name'] . ' (' . (string) $selected['rangeColumn'] . ' RANGE)';
        if (($selected['stat4Used'] ?? false) === true) {
            $detail .= ' USING STAT4';
        }
        if ($orderBySatisfied) {
            $detail .= ' ORDER BY CURRENT SOURCE';
        } elseif ($blockSort) {
            $detail .= ' USE TEMP B-TREE FOR ORDER BY';
        }
        if (($selected['covering'] ?? false) === true) {
            $detail .= ' COVERING';
        }

        return $detail;
    }

    /**
     * @param array{column:string,direction?:string} $order
     */
    private static function orderColumn(array $order): string
    {
        $column = $order['column'] ?? null;
        if (!is_string($column) || $column === '') {
            throw new \InvalidArgumentException('SQLite multicolumn range ORDER BY column must be a column name');
        }

        return $column;
    }

    /**
     * @param array{column:string,direction?:string} $order
     */
    private static function orderDirection(array $order): string
    {
        $direction = strtoupper((string) ($order['direction'] ?? 'ASC'));
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new \InvalidArgumentException('SQLite multicolumn range ORDER BY direction must be ASC or DESC');
        }

        return $direction;
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
            $constraint = self::constraintFromPredicate($term);
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

    private static function literalValue(mixed $value): mixed
    {
        if ($value instanceof SQLiteBlobValue || $value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('SQLite multicolumn range constraints need scalar, BLOB, or NULL values');
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
            throw new \InvalidArgumentException("SQLite multicolumn range predicate needs {$key}");
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
