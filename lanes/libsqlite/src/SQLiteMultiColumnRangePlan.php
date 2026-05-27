<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteMultiColumnRangePlan
{
    /**
     * @param list<array{sql:string,name?:string,rootPage?:int,estimatedRows?:int,distinctValues?:array<string,int>}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return null|array<string,mixed>
     */
    public static function choose(array $indexDefinitions, array $predicate, array $orderBy = []): ?array
    {
        $plans = self::rankedPlans($indexDefinitions, $predicate, $orderBy);

        return $plans[0] ?? null;
    }

    /**
     * @param list<array{sql:string,name?:string,rootPage?:int,estimatedRows?:int,distinctValues?:array<string,int>}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return list<array<string,mixed>>
     */
    public static function rankedPlans(array $indexDefinitions, array $predicate, array $orderBy = []): array
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

            $prefix = self::usablePrefix($columns, $constraints);
            if ($prefix['count'] === 0 || $prefix['rangeColumn'] === null) {
                $prefix = self::usableSkipScanPrefix($columns, $constraints, $index);
                if ($prefix['count'] === 0 || $prefix['rangeColumn'] === null) {
                    continue;
                }
            }

            $estimatedRows = self::estimatedRows($index, $prefix);
            $orderBySatisfied = $prefix['usesSkipScan']
                ? self::skipScanOrderBySatisfied($columns, $orderBy, $prefix['skippedColumns'], $prefix['equalityPrefix'], $prefix['rangeColumn'])
                : self::orderBySatisfied($columns, $orderBy, $prefix['equalityPrefix'], $prefix['rangeColumn']);
            $cost = $estimatedRows + 40 - ($prefix['equalityPrefix'] * 10) + (count($prefix['residualRangeColumns']) * 3) + $prefix['skipScanPenalty'];
            if ($orderBySatisfied) {
                $cost -= 8;
            }

            $plans[] = [
                'usable' => true,
                'name' => $index['name'] ?? self::indexName($sql),
                'rootPage' => $index['rootPage'] ?? null,
                'columns' => array_map(static fn (SQLiteIndexColumn $column): string => $column->columnName, $columns),
                'usedColumns' => $prefix['usedColumns'],
                'equalityPrefix' => $prefix['equalityPrefix'],
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
                'estimatedRows' => $estimatedRows,
                'estimatedCost' => max(1, $cost),
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
     * @return array{count:int,usedColumns:list<string>,equalityPrefix:int,rangeColumn:string|null,rangeConstraint:array{column:string,operator:string,values:mixed}|null,residualRangeColumns:list<string>,residualConstraints:list<array{column:string,operator:string,values:mixed}>,usesSkipScan:bool,skippedColumns:list<string>,skipScanLoops:int,skipScanPenalty:int,currentIndexColumnOffset:int}
     */
    private static function usablePrefix(array $columns, array $constraints): array
    {
        $used = [];
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
     * @return array{count:int,usedColumns:list<string>,equalityPrefix:int,rangeColumn:string|null,rangeConstraint:array{column:string,operator:string,values:mixed}|null,residualRangeColumns:list<string>,residualConstraints:list<array{column:string,operator:string,values:mixed}>,usesSkipScan:bool,skippedColumns:list<string>,skipScanLoops:int,skipScanPenalty:int,currentIndexColumnOffset:int}
     */
    private static function usableSkipScanPrefix(array $columns, array $constraints, array $index): array
    {
        $empty = [
            'count' => 0,
            'usedColumns' => [],
            'equalityPrefix' => 0,
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
    private static function firstRangeConstraint(array $constraints): ?array
    {
        return self::rangeConstraints($constraints)[0] ?? null;
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
     * @param array{estimatedRows?:int} $index
     * @param array{equalityPrefix:int,residualRangeColumns:list<string>,usesSkipScan?:bool,skipScanLoops?:int} $prefix
     */
    private static function estimatedRows(array $index, array $prefix): int
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

        return max(1, min($baseRows, (int) ceil($baseRows * $selectivity)));
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
