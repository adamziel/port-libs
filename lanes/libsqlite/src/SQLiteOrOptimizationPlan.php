<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteOrOptimizationPlan
{
    /**
     * @param list<array{sql:string,name?:string,rootPage?:int,estimatedRows?:int,coveringColumns?:list<string>}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<string> $neededColumns
     * @return null|array<string,mixed>
     */
    public static function choose(array $indexDefinitions, array $predicate, array $neededColumns = []): ?array
    {
        $plans = self::rankedPlans($indexDefinitions, $predicate, $neededColumns);

        return $plans[0] ?? null;
    }

    /**
     * @param list<array{sql:string,name?:string,rootPage?:int,estimatedRows?:int,coveringColumns?:list<string>}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    public static function rankedPlans(array $indexDefinitions, array $predicate, array $neededColumns = []): array
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator'));
        if ($operator !== 'OR') {
            return [];
        }

        $terms = $predicate['terms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms) || count($terms) < 2) {
            throw new \InvalidArgumentException('SQLite OR optimization needs at least two OR terms');
        }

        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite OR optimization needed columns must be non-empty strings');
            }
        }

        $arms = [];
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite OR optimization terms must be predicates');
            }

            $arm = self::bestArmPlan($indexDefinitions, $term, $neededColumns);
            if ($arm === null) {
                return [];
            }
            $arms[] = $arm;
        }

        $plans = [self::unionPlan($arms)];
        $inPlan = self::inRewritePlan($indexDefinitions, $terms, $neededColumns);
        if ($inPlan !== null) {
            $plans[] = $inPlan;
        }

        usort($plans, static function (array $left, array $right): int {
            return [$left['estimatedCost'], $left['estimatedRows'], (string) $left['strategy']]
                <=> [$right['estimatedCost'], $right['estimatedRows'], (string) $right['strategy']];
        });

        return $plans;
    }

    /**
     * @param list<array{sql:string,name?:string,rootPage?:int,estimatedRows?:int,coveringColumns?:list<string>}> $indexDefinitions
     * @param list<string> $neededColumns
     * @return null|array<string,mixed>
     */
    private static function bestArmPlan(array $indexDefinitions, array $term, array $neededColumns): ?array
    {
        $constraint = self::constraintFromPredicate($term);
        if ($constraint === null) {
            return null;
        }

        $plans = [];
        foreach ($indexDefinitions as $index) {
            $sql = $index['sql'] ?? null;
            if (!is_string($sql) || $sql === '') {
                throw new \InvalidArgumentException('SQLite OR optimization needs CREATE INDEX SQL text');
            }

            $columns = SQLiteCreateIndex::columns($sql);
            if ($columns === null || $columns === []) {
                continue;
            }

            $firstColumn = $columns[0];
            if (strcasecmp($firstColumn->columnName, $constraint['column']) !== 0) {
                continue;
            }

            $estimatedRows = self::estimatedRows($index, $constraint);
            $covering = self::covering($index, $neededColumns);
            $cost = $estimatedRows + ($covering ? 4 : 16);
            if ($constraint['operator'] === 'point') {
                $cost -= 3;
            }

            $plans[] = [
                'index' => $index['name'] ?? self::indexName($sql),
                'rootPage' => $index['rootPage'] ?? null,
                'column' => $constraint['column'],
                'operator' => $constraint['operator'],
                'values' => $constraint['values'],
                'estimatedRows' => $estimatedRows,
                'estimatedCost' => max(1, $cost),
                'covering' => $covering,
                'residualPredicateRequired' => $constraint['operator'] !== 'point',
            ];
        }

        usort($plans, static function (array $left, array $right): int {
            return [$left['estimatedCost'], $left['estimatedRows'], (string) $left['index']]
                <=> [$right['estimatedCost'], $right['estimatedRows'], (string) $right['index']];
        });

        return $plans[0] ?? null;
    }

    /**
     * @param list<array<string,mixed>> $arms
     * @return array<string,mixed>
     */
    private static function unionPlan(array $arms): array
    {
        $signatures = [];
        $deduped = [];
        foreach ($arms as $arm) {
            $signature = $arm['index'] . ':' . $arm['column'] . ':' . $arm['operator'] . ':' . json_encode($arm['values']);
            if (isset($signatures[$signature])) {
                continue;
            }
            $signatures[$signature] = true;
            $deduped[] = $arm;
        }

        $estimatedRows = array_sum(array_column($deduped, 'estimatedRows'));
        $estimatedCost = array_sum(array_column($deduped, 'estimatedCost')) + (count($deduped) * 7);
        $indexes = [];
        foreach ($deduped as $arm) {
            $indexes[$arm['index']] = $arm['index'];
        }

        return [
            'usable' => true,
            'strategy' => 'or-index-union',
            'arms' => $deduped,
            'indexes' => array_values($indexes),
            'requiresRowidUnion' => true,
            'deduplicatesRowids' => true,
            'residualPredicateRequired' => in_array(true, array_column($deduped, 'residualPredicateRequired'), true),
            'covering' => !in_array(false, array_column($deduped, 'covering'), true),
            'estimatedRows' => $estimatedRows,
            'estimatedCost' => max(1, $estimatedCost),
        ];
    }

    /**
     * @param list<array{sql:string,name?:string,rootPage?:int,estimatedRows?:int,coveringColumns?:list<string>}> $indexDefinitions
     * @param list<array<string,mixed>> $terms
     * @param list<string> $neededColumns
     * @return null|array<string,mixed>
     */
    private static function inRewritePlan(array $indexDefinitions, array $terms, array $neededColumns): ?array
    {
        $column = null;
        $originalColumn = null;
        $values = [];
        foreach ($terms as $term) {
            $constraint = self::constraintFromPredicate($term);
            if ($constraint === null || $constraint['operator'] !== 'point') {
                return null;
            }
            $column ??= strtolower($constraint['column']);
            $originalColumn ??= $constraint['column'];
            if ($column !== strtolower($constraint['column'])) {
                return null;
            }
            $values[] = $constraint['values'];
        }

        $deduped = [];
        foreach ($values as $value) {
            $deduped[json_encode($value)] = $value;
        }

        $arm = self::bestArmPlan($indexDefinitions, [
            'operator' => '=',
            'left' => ['column' => $originalColumn],
            'right' => reset($deduped),
        ], $neededColumns);
        if ($arm === null) {
            return null;
        }

        $estimatedRows = max(1, $arm['estimatedRows'] * count($deduped));

        return [
            'usable' => true,
            'strategy' => 'or-to-in',
            'index' => $arm['index'],
            'rootPage' => $arm['rootPage'],
            'column' => $arm['column'],
            'operator' => 'IN',
            'values' => array_values($deduped),
            'requiresRowidUnion' => false,
            'deduplicatesRowids' => false,
            'residualPredicateRequired' => false,
            'covering' => $arm['covering'],
            'estimatedRows' => $estimatedRows,
            'estimatedCost' => max(1, $estimatedRows + ($arm['covering'] ? 5 : 18)),
        ];
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

    private static function estimatedRows(array $index, array $constraint): int
    {
        $base = $index['estimatedRows'] ?? 1000;
        if (!is_int($base) || $base < 1) {
            throw new \InvalidArgumentException('SQLite OR optimization estimatedRows must be a positive integer');
        }

        $divisor = match ($constraint['operator']) {
            'point' => 100,
            'BETWEEN' => 12,
            default => 8,
        };

        return max(1, intdiv($base, $divisor));
    }

    /**
     * @param list<string> $neededColumns
     */
    private static function covering(array $index, array $neededColumns): bool
    {
        if ($neededColumns === []) {
            return false;
        }

        $covering = $index['coveringColumns'] ?? [];
        if (!is_array($covering) || !array_is_list($covering)) {
            return false;
        }

        $available = array_fill_keys(array_map('strtolower', array_filter($covering, 'is_string')), true);
        foreach ($neededColumns as $column) {
            if (!isset($available[strtolower($column)])) {
                return false;
            }
        }

        return true;
    }

    private static function literalValue(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_key_exists('literal', $value)) {
                return $value['literal'];
            }
            throw new \InvalidArgumentException('SQLite OR optimization only accepts scalar literal constraints');
        }
        if ($value === null || is_int($value) || is_float($value) || is_string($value) || is_bool($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('SQLite OR optimization only accepts scalar literal constraints');
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

    private static function requiredString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite OR optimization missing {$key}");
        }

        return $value;
    }

    private static function indexName(string $sql): string
    {
        if (preg_match('/CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:"([^"]+)"|`([^`]+)`|\[([^\]]+)\]|([A-Za-z_][A-Za-z0-9_]*))/i', $sql, $match) === 1) {
            return $match[1] !== '' ? $match[1] : ($match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : $match[4]));
        }

        return 'sqlite_autoindex_or';
    }
}
