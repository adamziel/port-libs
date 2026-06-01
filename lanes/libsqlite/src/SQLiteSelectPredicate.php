<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectPredicate
{
    /**
     * @param iterable<array<string,mixed>> $rows
     * @param array<string,mixed> $predicate
     * @return list<array<string,mixed>>
     */
    public static function filter(iterable $rows, array $predicate): array
    {
        $result = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            if (self::isTrue(self::evaluate($row, $predicate))) {
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    public static function evaluate(array $row, array $predicate): mixed
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator', 'SQLite SELECT predicate'));

        return match ($operator) {
            'TRUTH' => self::truth($row, $predicate),
            'AND' => self::andValue($row, $predicate['terms'] ?? null),
            'OR' => self::orValue($row, $predicate['terms'] ?? null),
            'NOT' => self::notValue($row, $predicate['term'] ?? null),
            'IS TRUE' => self::isTruthValue($row, $predicate, true, false),
            'IS NOT TRUE' => self::isTruthValue($row, $predicate, true, true),
            'IS FALSE' => self::isTruthValue($row, $predicate, false, false),
            'IS NOT FALSE' => self::isTruthValue($row, $predicate, false, true),
            'EXISTS' => self::exists($row, $predicate, false),
            'NOT EXISTS' => self::exists($row, $predicate, true),
            'IS DISTINCT FROM' => self::distinctFrom($row, $predicate, true),
            'IS NOT DISTINCT FROM' => self::distinctFrom($row, $predicate, false),
            '=', '==', 'IS' => self::compare($row, $predicate, static fn (int $comparison): bool => $comparison === 0, $operator === 'IS'),
            '!=', '<>', 'IS NOT' => self::compare($row, $predicate, static fn (int $comparison): bool => $comparison !== 0, $operator === 'IS NOT'),
            '<' => self::compare($row, $predicate, static fn (int $comparison): bool => $comparison < 0),
            '<=' => self::compare($row, $predicate, static fn (int $comparison): bool => $comparison <= 0),
            '>' => self::compare($row, $predicate, static fn (int $comparison): bool => $comparison > 0),
            '>=' => self::compare($row, $predicate, static fn (int $comparison): bool => $comparison >= 0),
            'BETWEEN' => self::between($row, $predicate, false),
            'NOT BETWEEN' => self::between($row, $predicate, true),
            'IN' => self::inList($row, $predicate, false),
            'NOT IN' => self::inList($row, $predicate, true),
            'LIKE' => self::like($row, $predicate, false),
            'NOT LIKE' => self::like($row, $predicate, true),
            'GLOB' => self::glob($row, $predicate, false),
            'NOT GLOB' => self::glob($row, $predicate, true),
            'MATCH' => self::matchRegexp($row, $predicate, 'MATCH', false),
            'NOT MATCH' => self::matchRegexp($row, $predicate, 'MATCH', true),
            'REGEXP' => self::matchRegexp($row, $predicate, 'REGEXP', false),
            'NOT REGEXP' => self::matchRegexp($row, $predicate, 'REGEXP', true),
            'IS NULL' => self::operand($row, $predicate, 'left') === null,
            'IS NOT NULL' => self::operand($row, $predicate, 'left') !== null,
            default => throw new \InvalidArgumentException("SQLite SELECT predicate operator {$operator} is not supported"),
        };
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function truth(array $row, array $predicate): ?bool
    {
        return self::truthValue(self::operand($row, $predicate, 'left'));
    }

    /**
     * @param array<string,mixed> $row
     * @param mixed $terms
     */
    private static function andValue(array $row, mixed $terms): ?bool
    {
        $terms = self::predicateList($terms, 'AND');
        $sawNull = false;
        foreach ($terms as $term) {
            $value = self::evaluate($row, $term);
            if (self::isFalse($value)) {
                return false;
            }
            if ($value === null) {
                $sawNull = true;
            }
        }

        return $sawNull ? null : true;
    }

    /**
     * @param array<string,mixed> $row
     * @param mixed $terms
     */
    private static function orValue(array $row, mixed $terms): ?bool
    {
        $terms = self::predicateList($terms, 'OR');
        $sawNull = false;
        foreach ($terms as $term) {
            $value = self::evaluate($row, $term);
            if (self::isTrue($value)) {
                return true;
            }
            if ($value === null) {
                $sawNull = true;
            }
        }

        return $sawNull ? null : false;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function notValue(array $row, mixed $term): ?bool
    {
        if (!is_array($term)) {
            throw new \InvalidArgumentException('SQLite SELECT NOT predicate needs a term');
        }

        $value = self::evaluate($row, $term);
        if ($value === null) {
            return null;
        }

        return !self::isTrue($value);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function isTruthValue(array $row, array $predicate, bool $expected, bool $negate): bool
    {
        $truth = self::truthValue(self::operand($row, $predicate, 'left'));
        $matched = $truth !== null && $truth === $expected;

        return $negate ? !$matched : $matched;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function distinctFrom(array $row, array $predicate, bool $distinct): bool
    {
        $left = self::operand($row, $predicate, 'left');
        $right = self::operand($row, $predicate, 'right');
        if ($left === null || $right === null) {
            $matched = $left !== $right;

            return $distinct ? $matched : !$matched;
        }

        $comparison = self::compareValues($left, $right, true, self::predicateCollations($predicate) ?? self::predicateCollation($predicate));
        $matched = $comparison !== 0;

        return $distinct ? $matched : !$matched;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     * @param callable(int): bool $accept
     */
    private static function compare(array $row, array $predicate, callable $accept, bool $nullsEqual = false): ?bool
    {
        $left = self::operand($row, $predicate, 'left');
        $right = self::operand($row, $predicate, 'right');
        if ($left === null || $right === null) {
            return $nullsEqual ? $accept($left === $right ? 0 : 1) : null;
        }

        $leftAffinity = self::operandAffinity($row, $predicate['left'] ?? null);
        $rightAffinity = self::operandAffinity($row, $predicate['right'] ?? null);
        $comparison = null;
        if ($leftAffinity !== null || $rightAffinity !== null) {
            $comparison = self::bothOperandsAreColumns($predicate)
                ? SQLiteAffinityComparison::compareColumnValues($left, $right, $leftAffinity ?? 'NONE', $rightAffinity ?? 'NONE', self::predicateCollation($predicate) ?? 'BINARY')
                : SQLiteAffinityComparison::compare($left, $right, $leftAffinity ?? 'NONE', $rightAffinity ?? 'NONE', self::predicateCollation($predicate) ?? 'BINARY');
        } else {
            $comparison = self::compareValues($left, $right, $nullsEqual, self::predicateCollations($predicate) ?? self::predicateCollation($predicate));
        }
        if ($comparison === null && $nullsEqual) {
            return $accept(1);
        }

        return $comparison === null ? null : $accept($comparison);
    }

    /**
     * @param array<string,mixed> $predicate
     */
    private static function bothOperandsAreColumns(array $predicate): bool
    {
        return self::expressionIsColumnReference($predicate['left'] ?? null)
            && self::expressionIsColumnReference($predicate['right'] ?? null);
    }

    private static function expressionIsColumnReference(mixed $expression): bool
    {
        if (!is_array($expression)) {
            return false;
        }
        if (array_key_exists('column', $expression)) {
            return true;
        }
        if (($expression['type'] ?? null) === 'column') {
            return true;
        }
        if (($expression['type'] ?? null) === 'collate') {
            return self::expressionIsColumnReference($expression['operand'] ?? null);
        }

        return false;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function between(array $row, array $predicate, bool $negate): ?bool
    {
        $value = self::operand($row, $predicate, 'left');
        $lower = self::operand($row, $predicate, 'lower');
        $upper = self::operand($row, $predicate, 'upper');
        if ($value === null) {
            return null;
        }

        $leftCollation = self::expressionCollations($predicate['left'] ?? null)
            ?? self::expressionCollation($predicate['left'] ?? null);
        $lowerCollation = $leftCollation
            ?? self::expressionCollation($predicate['lower'] ?? null);
        $upperCollation = $leftCollation
            ?? self::expressionCollation($predicate['upper'] ?? null);
        $leftAffinity = self::operandAffinity($row, $predicate['left'] ?? null);
        $lowerAffinity = self::operandAffinity($row, $predicate['lower'] ?? null);
        $upperAffinity = self::operandAffinity($row, $predicate['upper'] ?? null);
        $lowerMatched = null;
        if ($lower !== null) {
            $lowerComparison = $leftAffinity !== null || $lowerAffinity !== null
                ? SQLiteAffinityComparison::compare($value, $lower, $leftAffinity ?? 'NONE', $lowerAffinity ?? 'NONE', is_string($lowerCollation) ? $lowerCollation : 'BINARY')
                : self::compareValues($value, $lower, false, $lowerCollation);
            $lowerMatched = $lowerComparison === null ? null : $lowerComparison >= 0;
        }

        $upperMatched = null;
        if ($upper !== null) {
            $upperComparison = $leftAffinity !== null || $upperAffinity !== null
                ? SQLiteAffinityComparison::compare($value, $upper, $leftAffinity ?? 'NONE', $upperAffinity ?? 'NONE', is_string($upperCollation) ? $upperCollation : 'BINARY')
                : self::compareValues($value, $upper, false, $upperCollation);
            $upperMatched = $upperComparison === null ? null : $upperComparison <= 0;
        }

        if ($lowerMatched === false || $upperMatched === false) {
            return $negate;
        }
        if ($lowerMatched === null || $upperMatched === null) {
            return null;
        }

        return $negate ? false : true;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function inList(array $row, array $predicate, bool $negate): ?bool
    {
        $value = self::operand($row, $predicate, 'left');
        $values = $predicate['values'] ?? null;
        if (array_key_exists('valuesSubquery', $predicate)) {
            if (!is_callable($predicate['valuesSubquery'])) {
                throw new \InvalidArgumentException('SQLite SELECT IN subquery predicate needs a callable');
            }
            $values = ($predicate['valuesSubquery'])($row);
        }
        if (!is_array($values) || !array_is_list($values)) {
            throw new \InvalidArgumentException('SQLite SELECT IN predicate needs a list of values');
        }
        if ($values === []) {
            return $negate;
        }

        $sawNull = false;
        $matched = false;
        $collation = self::expressionCollations($predicate['left'] ?? null)
            ?? self::expressionCollation($predicate['left'] ?? null);
        $leftAffinity = self::operandAffinity($row, $predicate['left'] ?? null);
        foreach ($values as $candidateExpression) {
            $candidateAffinity = null;
            if (is_array($candidateExpression) && array_key_exists('__sqlite_in_value', $candidateExpression)) {
                $candidate = $candidateExpression['__sqlite_in_value'];
                $candidateAffinity = isset($candidateExpression['__sqlite_in_affinity']) && is_string($candidateExpression['__sqlite_in_affinity'])
                    ? $candidateExpression['__sqlite_in_affinity']
                    : null;
            } else {
                $candidate = is_array($candidateExpression) && array_is_list($candidateExpression)
                    ? $candidateExpression
                    : self::valueExpression($row, $candidateExpression);
            }
            if ($candidate === null) {
                $sawNull = true;
                continue;
            }
            $candidateCollation = $collation
                ?? self::expressionCollations($candidateExpression)
                ?? self::expressionCollation($candidateExpression);
            $comparison = $value === null ? null : (
                $leftAffinity !== null || $candidateAffinity !== null
                    ? SQLiteAffinityComparison::compare($value, $candidate, $leftAffinity ?? 'NONE', $candidateAffinity ?? 'NONE', is_string($candidateCollation) ? $candidateCollation : 'BINARY')
                    : self::compareValues($value, $candidate, false, $candidateCollation)
            );
            if ($comparison === 0) {
                $matched = true;
                break;
            }
            if ($comparison === null) {
                $sawNull = true;
            }
        }

        if ($negate) {
            if ($matched) {
                return false;
            }

            return $value === null || $sawNull ? null : true;
        }

        if ($matched) {
            return true;
        }

        return $value === null || $sawNull ? null : false;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function exists(array $row, array $predicate, bool $negate): bool
    {
        $subquery = $predicate['subquery'] ?? null;
        if (!is_callable($subquery)) {
            throw new \InvalidArgumentException('SQLite SELECT EXISTS predicate needs a subquery callable');
        }

        foreach ($subquery($row) as $unused) {
            return !$negate;
        }

        return $negate;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function like(array $row, array $predicate, bool $negate): ?bool
    {
        $left = self::operand($row, $predicate, 'left');
        $right = self::operand($row, $predicate, 'right');
        $escape = array_key_exists('escape', $predicate) ? self::valueExpression($row, $predicate['escape']) : null;
        if ($left === null || $right === null || $escape === null && array_key_exists('escape', $predicate) && $predicate['escape'] !== null) {
            return null;
        }

        $callback = $predicate['callback'] ?? null;
        if ($callback !== null) {
            if (!is_callable($callback)) {
                throw new \InvalidArgumentException('SQLite SELECT LIKE predicate callback must be callable');
            }

            $arguments = [$right, $left];
            if ($escape !== null) {
                $arguments[] = $escape;
            }
            $matched = self::truthValue($callback(...$arguments));

            return $matched === null ? null : ($negate ? !$matched : $matched);
        }

        $left = self::likeGlobTextOperand($left, 'LIKE left');
        $right = self::likeGlobTextOperand($right, 'LIKE pattern');
        if ($escape !== null) {
            $escape = self::likeGlobTextOperand($escape, 'LIKE escape');
        }

        $matched = SQLiteDatabase::likeMatches($left, $right, $escape, (bool) ($predicate['caseSensitive'] ?? false));

        return $negate ? !$matched : $matched;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function glob(array $row, array $predicate, bool $negate): ?bool
    {
        $left = self::operand($row, $predicate, 'left');
        $right = self::operand($row, $predicate, 'right');
        if ($left === null || $right === null) {
            return null;
        }

        $callback = $predicate['callback'] ?? null;
        if ($callback !== null) {
            if (!is_callable($callback)) {
                throw new \InvalidArgumentException('SQLite SELECT GLOB predicate callback must be callable');
            }

            $matched = self::truthValue($callback($right, $left));

            return $matched === null ? null : ($negate ? !$matched : $matched);
        }

        $left = self::likeGlobTextOperand($left, 'GLOB left');
        $right = self::likeGlobTextOperand($right, 'GLOB pattern');

        $matched = SQLiteDatabase::globMatches($left, $right);

        return $negate ? !$matched : $matched;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function matchRegexp(array $row, array $predicate, string $operator, bool $negate): ?bool
    {
        $left = self::operand($row, $predicate, 'left');
        $right = self::operand($row, $predicate, 'right');
        if ($left === null || $right === null) {
            return null;
        }

        $callback = $predicate['callback'] ?? null;
        if ($callback !== null) {
            if (!is_callable($callback)) {
                throw new \InvalidArgumentException("SQLite SELECT {$operator} predicate callback must be callable");
            }

            $matched = self::truthValue($callback($right, $left));

            return $matched === null ? null : ($negate ? !$matched : $matched);
        }

        $comparison = self::compareValues($left, $right, false, self::predicateCollations($predicate) ?? self::predicateCollation($predicate));

        $matched = $comparison === null ? null : $comparison === 0;

        return $matched === null ? null : ($negate ? !$matched : $matched);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function operand(array $row, array $predicate, string $key): mixed
    {
        if (!array_key_exists($key, $predicate)) {
            throw new \InvalidArgumentException("SQLite SELECT predicate needs {$key} operand");
        }

        return self::valueExpression($row, $predicate[$key]);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function valueExpression(array $row, mixed $expression): mixed
    {
        if (is_array($expression) && array_key_exists('column', $expression)) {
            $column = $expression['column'];
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite SELECT predicate column operands need a non-empty column name');
            }
            if (array_key_exists($column, $row)) {
                return $row[$column];
            }
            if (str_contains($column, '.')) {
                $schemaQualifiedSuffix = substr($column, strpos($column, '.') + 1);
                if (array_key_exists($schemaQualifiedSuffix, $row)) {
                    return $row[$schemaQualifiedSuffix];
                }

                $suffix = substr($column, strrpos($column, '.') + 1);
                if (array_key_exists($suffix, $row)) {
                    return $row[$suffix];
                }
            }

            throw new \InvalidArgumentException("SQLite SELECT predicate row is missing column {$column}");
        }

        if (is_array($expression) && array_key_exists('type', $expression)) {
            $type = $expression['type'];

            return match ($type) {
                'column' => self::columnExpression($row, $expression),
                'literal' => $expression['value'] ?? null,
                'function', 'cast', 'unary', 'binary', 'predicate', 'row', 'subquery', 'case' => SQLiteSelectExpression::evaluate($row, $expression),
                'collate' => self::collateExpression($row, $expression),
                default => throw new \InvalidArgumentException('SQLite SELECT predicate expression type must be column, literal, function, cast, unary, binary, predicate, row, subquery, case, or collate'),
            };
        }

        if ($expression instanceof SQLiteBlobValue || $expression === null || is_bool($expression) || is_int($expression) || is_float($expression) || is_string($expression)) {
            return $expression;
        }

        throw new \InvalidArgumentException('SQLite SELECT predicate operands must be scalar, BLOB, NULL, or column references');
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function columnExpression(array $row, array $expression): mixed
    {
        $column = $expression['name'] ?? null;
        if (!is_string($column) || $column === '') {
            throw new \InvalidArgumentException('SQLite SELECT predicate column expressions need a non-empty name');
        }
        if (array_key_exists($column, $row)) {
            return $row[$column];
        }

        if (str_contains($column, '.')) {
            $schemaQualifiedSuffix = substr($column, strpos($column, '.') + 1);
            if (array_key_exists($schemaQualifiedSuffix, $row)) {
                return $row[$schemaQualifiedSuffix];
            }

            $suffix = substr($column, strrpos($column, '.') + 1);
            if (array_key_exists($suffix, $row)) {
                return $row[$suffix];
            }
        }

        if (!str_contains($column, '.')) {
            $matches = [];
            $suffix = '.' . $column;
            foreach ($row as $rowColumn => $value) {
                if (is_string($rowColumn) && str_ends_with($rowColumn, $suffix)) {
                    $matches[] = $value;
                }
            }
            if (count($matches) === 1) {
                return $matches[0];
            }
            if (count($matches) > 1) {
                throw new \InvalidArgumentException("SQLite SELECT predicate column {$column} is ambiguous");
            }
        }

        throw new \InvalidArgumentException("SQLite SELECT predicate row is missing column {$column}");
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function collateExpression(array $row, array $expression): mixed
    {
        $operand = $expression['operand'] ?? null;
        if (!is_array($operand)) {
            throw new \InvalidArgumentException('SQLite SELECT predicate COLLATE expression needs an operand');
        }

        return self::valueExpression($row, $operand);
    }

    private static function compareValues(mixed $left, mixed $right, bool $nullsEqual = false, string|array|null $collation = null): ?int
    {
        if (is_array($left) || is_array($right)) {
            return self::compareRowValues($left, $right, $nullsEqual, is_array($collation) ? $collation : null);
        }

        self::assertValue($left);
        self::assertValue($right);
        $leftRank = self::sortRank($left);
        $rightRank = self::sortRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }
        if ($leftRank === 1) {
            return self::compareNumericValues($left, $right);
        }

        $leftText = self::comparisonText($left);
        $rightText = self::comparisonText($right);

        return self::compareText($leftText, $rightText, is_string($collation) ? $collation : 'BINARY');
    }

    private static function comparisonText(mixed $value): string
    {
        if ($value instanceof SQLiteBlobValue) {
            return $value->bytes;
        }
        if ($value instanceof SQLiteJsonSubtypeValue) {
            return $value->json;
        }

        return (string) $value;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function operandAffinity(array $row, mixed $expression): ?string
    {
        if (!is_array($expression)) {
            return null;
        }
        if (($expression['type'] ?? null) === 'collate') {
            return self::operandAffinity($row, $expression['operand'] ?? null);
        }
        if (($expression['type'] ?? null) === 'cast') {
            $target = $expression['target'] ?? null;
            if (!is_string($target) || trim($target) === '') {
                return null;
            }

            return self::castExpressionAffinity($target);
        }

        $column = null;
        if (array_key_exists('column', $expression)) {
            $column = $expression['column'];
        } elseif (($expression['type'] ?? null) === 'column') {
            $column = $expression['name'] ?? null;
        }
        if (!is_string($column) || $column === '') {
            return null;
        }

        $affinities = $row['__sqlite_column_affinities'] ?? null;
        if (!is_array($affinities)) {
            return null;
        }
        if (isset($affinities[$column]) && is_string($affinities[$column])) {
            return $affinities[$column];
        }
        if (str_contains($column, '.')) {
            $bare = substr($column, strrpos($column, '.') + 1);
            if (isset($affinities[$bare]) && is_string($affinities[$bare])) {
                return $affinities[$bare];
            }
        }

        return null;
    }

    private static function castExpressionAffinity(string $target): string
    {
        $normalized = strtoupper(trim($target));
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        if (!is_string($normalized)) {
            return 'NUMERIC';
        }

        if (str_contains($normalized, 'INT')) {
            return 'INTEGER';
        }
        if (str_contains($normalized, 'CHAR') || str_contains($normalized, 'CLOB') || str_contains($normalized, 'TEXT')) {
            return 'TEXT';
        }
        if (str_contains($normalized, 'BLOB') || $normalized === 'NONE') {
            return 'BLOB';
        }
        if (str_contains($normalized, 'REAL') || str_contains($normalized, 'FLOA') || str_contains($normalized, 'DOUB')) {
            return 'REAL';
        }

        return 'NUMERIC';
    }

    private static function compareText(string $left, string $right, string $collation): int
    {
        return match (strtoupper($collation)) {
            'BINARY' => strcmp($left, $right),
            'NOCASE' => strcmp(self::asciiLower($left), self::asciiLower($right)),
            'RTRIM' => strcmp(rtrim($left, ' '), rtrim($right, ' ')),
            default => throw new \InvalidArgumentException("Unsupported SQLite SELECT predicate collation: {$collation}"),
        };
    }

    private static function likeGlobTextOperand(mixed $value, string $context): string
    {
        if (is_string($value)) {
            return $value;
        }
        if ($value instanceof SQLiteJsonSubtypeValue) {
            return $value->json;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return self::sqliteRealText($value);
        }
        if ($value instanceof SQLiteBlobValue) {
            return $value->bytes;
        }

        throw new \InvalidArgumentException("SQLite SELECT {$context} operand must be scalar text-coercible");
    }

    private static function sqliteRealText(float $value): string
    {
        $text = sprintf('%.15G', $value);
        $text = preg_replace_callback(
            '/E([+-])(\d+)$/',
            static fn (array $matches): string => 'e' . $matches[1] . str_pad($matches[2], 2, '0', STR_PAD_LEFT),
            $text,
        ) ?? $text;

        if (!str_contains($text, '.') && !str_contains($text, 'e')) {
            $text .= '.0';
        }

        return $text;
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /**
     * @param array<string,mixed> $predicate
     */
    private static function predicateCollation(array $predicate): ?string
    {
        $left = self::expressionCollation($predicate['left'] ?? null)
            ?? self::expressionCollation($predicate['right'] ?? null);
        return $left;
    }

    /**
     * @return list<?string>|null
     */
    private static function predicateCollations(array $predicate): ?array
    {
        return self::expressionCollations($predicate['left'] ?? null)
            ?? self::expressionCollations($predicate['right'] ?? null);
    }

    /**
     * @return list<?string>|null
     */
    private static function expressionCollations(mixed $expression): ?array
    {
        if (!is_array($expression) || ($expression['type'] ?? null) !== 'row' || !isset($expression['values']) || !is_array($expression['values'])) {
            return null;
        }

        return array_map(self::expressionCollation(...), $expression['values']);
    }

    private static function expressionCollation(mixed $expression): ?string
    {
        if (!is_array($expression)) {
            return null;
        }
        $type = $expression['type'] ?? null;
        if ($type === 'collate') {
            $collation = $expression['collation'] ?? null;
            if (!is_string($collation) || $collation === '') {
                throw new \InvalidArgumentException('SQLite SELECT COLLATE expression needs a collation');
            }

            return strtoupper($collation);
        }

        if ($type === 'unary' || $type === 'cast') {
            return self::expressionCollation($expression['operand'] ?? null);
        }

        if ($type === 'binary') {
            return self::expressionCollation($expression['left'] ?? null)
                ?? self::expressionCollation($expression['right'] ?? null);
        }

        if ($type === 'function' || $type === 'row') {
            $arguments = $type === 'function'
                ? ($expression['arguments'] ?? null)
                : ($expression['values'] ?? null);
            if (!is_array($arguments) || !array_is_list($arguments)) {
                return null;
            }
            foreach ($arguments as $argument) {
                $collation = self::expressionCollation($argument);
                if ($collation !== null) {
                    return $collation;
                }
            }

            return null;
        }

        if ($type === 'case') {
            $branches = $expression['branches'] ?? null;
            if (is_array($branches)) {
                foreach ($branches as $branch) {
                    if (!is_array($branch)) {
                        continue;
                    }
                    $collation = self::expressionCollation($branch['then'] ?? null);
                    if ($collation !== null) {
                        return $collation;
                    }
                }
            }

            return self::expressionCollation($expression['else'] ?? null);
        }

        return null;
    }

    private static function sortRank(mixed $value): int
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return 1;
        }
        if (is_string($value) || $value instanceof SQLiteJsonSubtypeValue) {
            return 2;
        }
        if ($value instanceof SQLiteBlobValue) {
            return 3;
        }

        throw new \InvalidArgumentException('SQLite SELECT comparison values must be scalar, JSON subtype, BLOB, or NULL');
    }

    private static function compareNumericValues(bool|int|float $left, bool|int|float $right): int
    {
        if (is_int($left) && is_float($right)) {
            if ($right >= 9223372036854775808.0) {
                return -1;
            }
            if ($right < -9223372036854775808.0) {
                return 1;
            }
        }
        if (is_float($left) && is_int($right)) {
            if ($left >= 9223372036854775808.0) {
                return 1;
            }
            if ($left < -9223372036854775808.0) {
                return -1;
            }
        }

        return ((float) $left) <=> ((float) $right);
    }

    /**
     * @return ?int 0 for equality, -1/1 for a known ordering, null for SQL NULL/unknown
     */
    /**
     * @param list<?string>|null $collations
     */
    private static function compareRowValues(mixed $left, mixed $right, bool $nullsEqual, ?array $collations = null): ?int
    {
        if (!is_array($left) || !is_array($right) || !array_is_list($left) || !array_is_list($right)) {
            throw new \InvalidArgumentException('SQLite SELECT row-value comparisons need row operands on both sides');
        }
        if (count($left) !== count($right)) {
            throw new \InvalidArgumentException('SQLite SELECT row-value comparisons need the same number of columns');
        }
        if (count($left) < 2) {
            throw new \InvalidArgumentException('SQLite SELECT row-value comparisons need at least two columns');
        }

        $sawNull = false;
        foreach ($left as $index => $leftValue) {
            $rightValue = $right[$index];
            if ($leftValue === null || $rightValue === null) {
                if (!$nullsEqual || $leftValue !== $rightValue) {
                    $sawNull = true;
                }
                continue;
            }

            $comparison = self::compareValues($leftValue, $rightValue, $nullsEqual, $collations[$index] ?? null);
            if ($comparison === null) {
                return null;
            }
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return $sawNull ? null : 0;
    }

    private static function isTrue(mixed $value): bool
    {
        $truth = self::truthValue($value);
        if ($truth === null) {
            return false;
        }

        return $truth;
    }

    private static function truthValue(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value !== 0.0;
        }
        if ($value instanceof SQLiteBlobValue) {
            return self::numericPrefix($value->bytes) != 0;
        }
        if ($value instanceof SQLiteJsonSubtypeValue) {
            return self::numericPrefix($value->json) != 0;
        }
        if (is_string($value)) {
            return self::numericPrefix($value) != 0;
        }

        throw new \InvalidArgumentException('SQLite SELECT predicate truth values must be scalar, JSON subtype, BLOB, or NULL');
    }

    private static function numericPrefix(string $value): int|float
    {
        $trimmed = ltrim($value);
        if (preg_match('/^[+-]?(?:(?:[0-9]+(?:\.[0-9]*)?)|(?:\.[0-9]+))(?:[eE][+-]?[0-9]+)?/', $trimmed, $match) !== 1) {
            return 0;
        }
        if (preg_match('/^[+-]?[0-9]+$/', $match[0]) === 1) {
            return self::integerTextWithinInt64($match[0]) ? (int) $match[0] : (float) $match[0];
        }

        return (float) $match[0];
    }

    private static function integerTextWithinInt64(string $value): bool
    {
        $negative = str_starts_with($value, '-');
        if ($value[0] === '-' || $value[0] === '+') {
            $value = substr($value, 1);
        }

        $digits = ltrim($value, '0');
        if ($digits === '') {
            return true;
        }

        $limit = $negative ? '9223372036854775808' : '9223372036854775807';

        return strlen($digits) < strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) <= 0);
    }

    private static function isFalse(mixed $value): bool
    {
        return $value !== null && !self::isTrue($value);
    }

    /**
     * @param mixed $terms
     * @return list<array<string,mixed>>
     */
    private static function predicateList(mixed $terms, string $operator): array
    {
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException("SQLite SELECT {$operator} predicate needs a non-empty term list");
        }
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException("SQLite SELECT {$operator} predicate terms must be predicates");
            }
        }

        return $terms;
    }

    private static function requiredString(array $payload, string $key, string $context): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("{$context} needs {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function assertRow(array $row): void
    {
        foreach ($row as $column => $value) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite SELECT predicate rows must have named columns');
            }
            if ($column === '__sqlite_column_affinities' || str_ends_with($column, '.__sqlite_column_affinities')) {
                if (!is_array($value)) {
                    throw new \InvalidArgumentException('SQLite SELECT predicate column affinity metadata must be an array');
                }
                continue;
            }
            self::assertValue($value);
        }
    }

    private static function assertValue(mixed $value): void
    {
        if (
            $value instanceof SQLiteBlobValue
            || $value instanceof SQLiteJsonSubtypeValue
            || $value === null
            || is_bool($value)
            || is_int($value)
            || is_float($value)
            || is_string($value)
        ) {
            return;
        }

        throw new \InvalidArgumentException('SQLite SELECT predicate values must be scalar, BLOB, JSON subtype, or NULL');
    }
}
