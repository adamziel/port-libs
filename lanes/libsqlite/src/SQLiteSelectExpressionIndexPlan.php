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
        $terms = self::flattenAndTerms($predicate);
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
                if ($expression->partial && !self::constraintImpliesPartialPredicate($expression->partialPredicate, $constraint)) {
                    continue;
                }
                if (!self::constraintCompatibleWithType($constraint, $expression->collation)) {
                    continue;
                }

                return [
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
                ];
            }
        }

        return null;
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
     */
    private static function constraintImpliesPartialPredicate(?SQLiteIndexPredicate $predicate, array $constraint): bool
    {
        if ($predicate === null) {
            return false;
        }

        return self::partialPredicateIsSafeNonNull($predicate, $constraint['column'])
            && self::constraintHasNonNullSearchValue($constraint['values']);
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
