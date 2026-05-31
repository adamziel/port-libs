<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectExpression
{
    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    public static function evaluate(array $row, array $expression): mixed
    {
        $kind = $expression['type'] ?? null;

        return match ($kind) {
            'column' => self::columnValue($row, self::requiredString($expression, 'name', 'column expression')),
            'literal' => $expression['value'] ?? null,
            'collate' => self::collateValue($row, $expression),
            'function' => self::functionValue($row, $expression),
            'cast' => self::castValue($row, $expression),
            'unary' => self::unaryValue($row, $expression),
            'binary' => self::binaryValue($row, $expression),
            'predicate' => self::predicateValue($row, $expression),
            'row' => self::rowValue($row, $expression),
            'subquery' => self::subqueryValue($row, $expression),
            'case' => self::caseValue($row, $expression),
            default => throw new \InvalidArgumentException('SQLite SELECT expression type must be column, literal, collate, function, cast, unary, binary, predicate, row, subquery, or case'),
        };
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function collateValue(array $row, array $expression): mixed
    {
        $operand = $expression['operand'] ?? null;
        if (!is_array($operand)) {
            throw new \InvalidArgumentException('SQLite SELECT COLLATE expression needs an operand');
        }

        return self::evaluate($row, $operand);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     * @return list<mixed>
     */
    private static function rowValue(array $row, array $expression): array
    {
        $values = $expression['values'] ?? null;
        if (!is_array($values) || !array_is_list($values) || count($values) < 2) {
            throw new \InvalidArgumentException('SQLite SELECT row-value expression needs at least two values');
        }

        $evaluated = [];
        foreach ($values as $valueExpression) {
            if (!is_array($valueExpression)) {
                throw new \InvalidArgumentException('SQLite SELECT row-value expression values must be expressions');
            }
            $evaluated[] = self::evaluate($row, $valueExpression);
        }

        return $evaluated;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function predicateValue(array $row, array $expression): ?int
    {
        $predicate = $expression['predicate'] ?? null;
        if (!is_array($predicate)) {
            throw new \InvalidArgumentException('SQLite SELECT predicate expression needs a predicate');
        }

        $value = SQLiteSelectPredicate::evaluate($row, $predicate);
        if ($value === null) {
            return null;
        }

        return self::isSqlTrue($value) ? 1 : 0;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function subqueryValue(array $row, array $expression): mixed
    {
        $subquery = $expression['subquery'] ?? null;
        if (!is_callable($subquery)) {
            throw new \InvalidArgumentException('SQLite SELECT scalar subquery expression needs a callable');
        }

        $rows = $subquery($row);
        if (!is_array($rows)) {
            throw new \InvalidArgumentException('SQLite SELECT scalar subquery expression must return rows');
        }
        if ($rows === []) {
            return null;
        }
        $first = $rows[0];
        if (!is_array($first)) {
            throw new \InvalidArgumentException('SQLite SELECT scalar subquery expression rows must be arrays');
        }

        $columns = array_keys($first);
        if (count($columns) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT scalar subquery expression must return one column');
        }

        return $first[$columns[0]];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function caseValue(array $row, array $expression): mixed
    {
        $base = null;
        $hasBase = array_key_exists('base', $expression);
        if ($hasBase) {
            $baseExpression = $expression['base'];
            if (!is_array($baseExpression)) {
                throw new \InvalidArgumentException('SQLite SELECT CASE expression base must be an expression');
            }
            $base = self::evaluate($row, $baseExpression);
        }

        $branches = $expression['branches'] ?? null;
        if (!is_array($branches) || !array_is_list($branches) || $branches === []) {
            throw new \InvalidArgumentException('SQLite SELECT CASE expression needs non-empty branches');
        }

        foreach ($branches as $branch) {
            if (!is_array($branch) || !array_key_exists('when', $branch) || !array_key_exists('then', $branch)) {
                throw new \InvalidArgumentException('SQLite SELECT CASE expression branches need when and then expressions');
            }

            $when = self::caseExpressionValue($row, $branch['when'], 'branch WHEN');
            $matched = $hasBase
                ? self::caseBaseMatches(
                    $row,
                    $base,
                    $when,
                    $expression['base'] ?? null,
                    $branch['when'],
                    self::caseBranchCollation($expression['base'] ?? null, $branch['when'])
                )
                : self::isSqlTrue($when);
            if ($matched) {
                return self::caseExpressionValue($row, $branch['then'], 'branch THEN');
            }
        }

        if (!array_key_exists('else', $expression)) {
            return null;
        }

        return self::caseExpressionValue($row, $expression['else'], 'ELSE');
    }

    private static function caseExpressionValue(array $row, mixed $expression, string $context): mixed
    {
        if (is_array($expression)) {
            if (array_key_exists('type', $expression)) {
                return self::evaluate($row, $expression);
            }

            throw new \InvalidArgumentException("SQLite SELECT CASE expression {$context} must be an expression or scalar value");
        }

        if ($expression === null || $expression instanceof SQLiteBlobValue || is_bool($expression) || is_int($expression) || is_float($expression) || is_string($expression)) {
            return $expression;
        }

        throw new \InvalidArgumentException("SQLite SELECT CASE expression {$context} must be scalar, BLOB, or NULL");
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function caseBaseMatches(array $row, mixed $base, mixed $when, mixed $baseExpression, mixed $whenExpression, ?string $collation): bool
    {
        if ($base === null || $when === null) {
            return false;
        }

        $comparison = SQLiteAffinityComparison::compare(
            $base,
            $when,
            self::expressionAffinity($row, $baseExpression),
            self::expressionAffinity($row, $whenExpression),
            $collation ?? 'BINARY'
        );

        return $comparison === 0;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function expressionAffinity(array $row, mixed $expression): string
    {
        if (!is_array($expression)) {
            return 'NONE';
        }

        if (($expression['type'] ?? null) === 'collate') {
            return self::expressionAffinity($row, $expression['operand'] ?? null);
        }

        if (($expression['type'] ?? null) !== 'column') {
            return 'NONE';
        }

        $name = $expression['name'] ?? null;
        if (!is_string($name) || $name === '') {
            return 'NONE';
        }

        $affinities = $row['__sqlite_column_affinities'] ?? [];
        if (!is_array($affinities)) {
            return 'NONE';
        }

        $candidates = [$name];
        if (str_contains($name, '.')) {
            $parts = explode('.', $name);
            $candidates[] = (string) end($parts);
        }

        foreach ($candidates as $candidate) {
            $affinity = $affinities[$candidate] ?? null;
            if (is_string($affinity) && $affinity !== '') {
                return $affinity;
            }
        }

        return 'NONE';
    }

    private static function caseBranchCollation(mixed $base, mixed $when): ?string
    {
        return self::expressionCollation($base) ?? self::expressionCollation($when);
    }

    private static function expressionCollation(mixed $expression): ?string
    {
        if (!is_array($expression)) {
            return null;
        }
        if (($expression['type'] ?? null) === 'collate') {
            $collation = $expression['collation'] ?? null;
            if (!is_string($collation) || $collation === '') {
                throw new \InvalidArgumentException('SQLite SELECT COLLATE expression needs a collation');
            }

            return strtoupper($collation);
        }

        return null;
    }

    private static function compareCaseValues(mixed $left, mixed $right, ?string $collation): int
    {
        self::assertCaseValue($left);
        self::assertCaseValue($right);
        $leftRank = self::caseSortRank($left);
        $rightRank = self::caseSortRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }
        if ($leftRank === 1) {
            return self::compareNumericCaseValues($left, $right);
        }
        if ($leftRank === 3) {
            return strcmp($left->bytes, $right->bytes);
        }

        return self::compareCaseText((string) $left, (string) $right, $collation ?? 'BINARY');
    }

    private static function assertCaseValue(mixed $value): void
    {
        if ($value instanceof SQLiteBlobValue || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return;
        }

        throw new \InvalidArgumentException('SQLite SELECT CASE expression values must be scalar, BLOB, or NULL');
    }

    private static function caseSortRank(mixed $value): int
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return 1;
        }
        if (is_string($value)) {
            return 2;
        }
        if ($value instanceof SQLiteBlobValue) {
            return 3;
        }

        throw new \InvalidArgumentException('SQLite SELECT CASE expression values must be scalar, BLOB, or NULL');
    }

    private static function compareNumericCaseValues(bool|int|float $left, bool|int|float $right): int
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

    private static function compareCaseText(string $left, string $right, string $collation): int
    {
        return match (strtoupper($collation)) {
            'BINARY' => strcmp($left, $right),
            'NOCASE' => strcmp(self::asciiLower($left), self::asciiLower($right)),
            'RTRIM' => strcmp(rtrim($left, ' '), rtrim($right, ' ')),
            default => throw new \InvalidArgumentException("Unsupported SQLite SELECT CASE collation: {$collation}"),
        };
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    private static function isSqlTrue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if ($value instanceof SQLiteBlobValue) {
            return self::numericPrefix($value->bytes) != 0;
        }
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return (float) $value !== 0.0;
        }
        if (is_string($value)) {
            return self::numericPrefix($value) != 0;
        }

        throw new \InvalidArgumentException('SQLite SELECT CASE expression values must be scalar, BLOB, or NULL');
    }

    private static function valueKey(mixed $value): string
    {
        if ($value === null) {
            return 'null:';
        }
        if ($value instanceof SQLiteBlobValue) {
            return 'blob:' . $value->bytes;
        }
        if (is_bool($value) || is_int($value)) {
            return 'integer:' . (int) $value;
        }
        if (is_float($value)) {
            return 'real:' . sprintf('%.17G', $value);
        }
        if (is_string($value)) {
            return 'text:' . $value;
        }

        throw new \InvalidArgumentException('SQLite SELECT CASE expression values must be scalar, BLOB, or NULL');
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function unaryValue(array $row, array $expression): mixed
    {
        $operator = self::requiredString($expression, 'operator', 'unary expression');
        $operandExpression = $expression['operand'] ?? null;
        if (!is_array($operandExpression)) {
            throw new \InvalidArgumentException("SQLite SELECT expression unary {$operator} needs an expression operand");
        }

        $operand = self::evaluate($row, $operandExpression);
        if ($operand === null) {
            return null;
        }

        return match ($operator) {
            '+' => $operand,
            '-' => -self::numericOperand($operand),
            '~' => ~self::integerOperand($operand),
            'NOT' => self::notValue($operand),
            default => throw new \InvalidArgumentException("SQLite SELECT unary expression operator {$operator} is not supported"),
        };
    }

    private static function notValue(mixed $operand): ?int
    {
        if ($operand === null) {
            return null;
        }

        return self::isSqlTrue($operand) ? 0 : 1;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function functionValue(array $row, array $expression): mixed
    {
        $function = self::requiredString($expression, 'name', 'function expression');
        $arguments = $expression['arguments'] ?? [];
        if (!is_array($arguments) || !array_is_list($arguments)) {
            throw new \InvalidArgumentException("SQLite SELECT expression function {$function} arguments must be a list");
        }

        $evaluated = [];
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                $evaluated[] = self::evaluate($row, $argument);
                continue;
            }

            $evaluated[] = $argument;
        }

        $normalized = strtolower($function);
        if ($normalized === 'json' || $normalized === 'jsonb') {
            return self::jsonExpressionResult($normalized, SQLiteJsonCanonical::jsonSqlFunctionArguments($normalized, $evaluated));
        }
        if ($normalized === 'json_array' || $normalized === 'jsonb_array') {
            return self::jsonExpressionResult($normalized, SQLiteJsonConstructor::jsonArraySqlFunctionArguments($normalized, $evaluated));
        }
        if ($normalized === 'json_object' || $normalized === 'jsonb_object') {
            return self::jsonExpressionResult($normalized, SQLiteJsonConstructor::jsonObjectSqlFunctionArguments($normalized, $evaluated));
        }
        if ($normalized === 'json_quote') {
            return SQLiteJsonQuote::jsonQuoteSqlFunctionArguments($normalized, $evaluated);
        }
        if ($normalized === 'json_valid') {
            $value = $evaluated[0] ?? null;
            if (
                $value !== null
                && !is_string($value)
                && !is_int($value)
                && !is_float($value)
                && !is_bool($value)
                && !$value instanceof SQLiteBlobValue
                && !$value instanceof SQLiteJsonSubtypeValue
            ) {
                throw new \InvalidArgumentException('SQLite SELECT expression json_valid() argument must be a SQL scalar, JSONB, JSON subtype, or NULL');
            }
            $valid = SQLiteJsonValidity::jsonValidSqlFunctionArguments($normalized, $evaluated);

            return $valid === null ? null : ($valid ? 1 : 0);
        }
        if ($normalized === 'json_error_position') {
            return SQLiteJsonErrorPosition::jsonErrorPositionSqlFunctionArguments($normalized, $evaluated);
        }
        if ($normalized === 'json_pretty') {
            return SQLiteJsonPretty::jsonPrettySqlFunctionArguments($normalized, $evaluated);
        }
        if ($normalized === 'json_patch' || $normalized === 'jsonb_patch') {
            foreach ([0, 1] as $jsonArgumentIndex) {
                if (($evaluated[$jsonArgumentIndex] ?? null) instanceof SQLiteJsonSubtypeValue) {
                    $evaluated[$jsonArgumentIndex] = $evaluated[$jsonArgumentIndex]->json;
                }
            }

            return self::jsonExpressionResult($normalized, SQLiteJsonPatch::patchSqlFunctionArguments($normalized, $evaluated));
        }
        if (
            $normalized === 'json_insert'
            || $normalized === 'jsonb_insert'
            || $normalized === 'json_set'
            || $normalized === 'jsonb_set'
            || $normalized === 'json_replace'
            || $normalized === 'jsonb_replace'
        ) {
            if (($evaluated[0] ?? null) instanceof SQLiteJsonSubtypeValue) {
                $evaluated[0] = $evaluated[0]->json;
            }

            return self::jsonExpressionResult($normalized, SQLiteJsonMutation::mutateSqlFunctionArguments($normalized, $evaluated));
        }
        if ($normalized === 'json_remove' || $normalized === 'jsonb_remove') {
            if (($evaluated[0] ?? null) instanceof SQLiteJsonSubtypeValue) {
                $evaluated[0] = $evaluated[0]->json;
            }

            return self::jsonExpressionResult($normalized, SQLiteJsonRemove::removeSqlFunctionArguments($normalized, $evaluated));
        }
        if ($normalized === 'json_array_insert' || $normalized === 'jsonb_array_insert') {
            if (($evaluated[0] ?? null) instanceof SQLiteJsonSubtypeValue) {
                $evaluated[0] = $evaluated[0]->json;
            }

            return self::jsonExpressionResult($normalized, SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments($normalized, $evaluated));
        }
        if ($normalized === 'json_extract' || $normalized === 'jsonb_extract') {
            if ($evaluated === []) {
                throw new \InvalidArgumentException('SQLite SELECT expression json_extract() requires a JSON argument');
            }

            $value = array_shift($evaluated);
            if ($value instanceof SQLiteJsonSubtypeValue) {
                $value = $value->json;
            }
            if ($value !== null && !is_string($value) && !$value instanceof SQLiteBlobValue) {
                throw new \InvalidArgumentException('SQLite SELECT expression json_extract() JSON argument must be text, JSONB, or NULL');
            }

            $paths = [];
            foreach ($evaluated as $path) {
                if ($path === null) {
                    return null;
                }
                $paths[] = self::jsonPathText($path, 'json_extract()');
            }

            return $normalized === 'json_extract'
                ? SQLiteJsonExtract::extractJsonArgumentSqlFunction($normalized, $value, ...$paths)
                : SQLiteJsonExtract::extractSqlFunction($normalized, $value, ...$paths);
        }
        if ($normalized === 'json_type' || $normalized === 'json_array_length') {
            if (count($evaluated) < 1 || count($evaluated) > 2) {
                throw new \InvalidArgumentException('SQLite SELECT expression json_type() and json_array_length() expect one or two arguments');
            }

            $value = $evaluated[0];
            if ($value !== null && !is_string($value) && !$value instanceof SQLiteBlobValue && !$value instanceof SQLiteJsonSubtypeValue) {
                throw new \InvalidArgumentException('SQLite SELECT expression JSON inspection argument must be text, JSONB, JSON subtype, or NULL');
            }

            $path = array_key_exists(1, $evaluated) ? $evaluated[1] : '$';
            if ($path !== null) {
                $path = self::jsonPathText($path, 'JSON inspection');
            }

            return SQLiteJsonInspection::inspectionSqlFunction($normalized, $value, $path);
        }

        return SQLiteCoreScalarFunction::sqlFunctionArguments($function, $evaluated);
    }

    private static function jsonExpressionResult(string $function, string|SQLiteBlobValue|null $value): SQLiteJsonSubtypeValue|SQLiteBlobValue|null
    {
        if ($value === null || $value instanceof SQLiteBlobValue || str_starts_with($function, 'jsonb_') || $function === 'jsonb') {
            return $value;
        }

        return new SQLiteJsonSubtypeValue($value);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function castValue(array $row, array $expression): mixed
    {
        $operandExpression = $expression['operand'] ?? null;
        if (!is_array($operandExpression)) {
            throw new \InvalidArgumentException('SQLite SELECT CAST expression needs an operand');
        }
        $target = self::castAffinity(self::requiredString($expression, 'target', 'CAST expression'));
        $value = self::evaluate($row, $operandExpression);
        if ($value === null) {
            return null;
        }

        return match ($target) {
            'integer' => self::integerOperand($value),
            'real' => (float) self::numericOperand($value),
            'numeric' => self::numericCastOperand($value),
            'text' => self::textValue($value),
            'none' => new SQLiteBlobValue(self::textValue($value)),
            default => throw new \InvalidArgumentException("SQLite SELECT CAST target {$target} is not supported"),
        };
    }

    private static function castAffinity(string $target): string
    {
        $normalized = strtoupper(trim($target));
        if ($normalized === '') {
            throw new \InvalidArgumentException('SQLite SELECT CAST target must not be empty');
        }
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        if (!is_string($normalized)) {
            throw new \InvalidArgumentException('SQLite SELECT CAST target is malformed');
        }

        if (str_contains($normalized, 'INT')) {
            return 'integer';
        }
        if (str_contains($normalized, 'CHAR') || str_contains($normalized, 'CLOB') || str_contains($normalized, 'TEXT')) {
            return 'text';
        }
        if (str_contains($normalized, 'BLOB') || $normalized === 'NONE') {
            return 'none';
        }
        if (str_contains($normalized, 'REAL') || str_contains($normalized, 'FLOA') || str_contains($normalized, 'DOUB')) {
            return 'real';
        }

        return 'numeric';
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function binaryValue(array $row, array $expression): mixed
    {
        $operator = self::requiredString($expression, 'operator', 'binary expression');
        $leftExpression = $expression['left'] ?? null;
        $rightExpression = $expression['right'] ?? null;
        if (!is_array($leftExpression) || !is_array($rightExpression)) {
            throw new \InvalidArgumentException("SQLite SELECT expression {$operator} needs expression operands");
        }

        $left = self::evaluate($row, $leftExpression);
        $right = self::evaluate($row, $rightExpression);
        if ($left === null || $right === null) {
            return null;
        }

        return match ($operator) {
            '||' => self::concatenationTextValue($left, $leftExpression) . self::concatenationTextValue($right, $rightExpression),
            '+', '-', '*', '/', '%' => self::numericValue($left, $right, $operator),
            '&', '|', '<<', '>>' => self::bitwiseValue($left, $right, $operator),
            '->', '->>' => self::jsonOperatorValue($left, $right, $operator),
            default => throw new \InvalidArgumentException("SQLite SELECT expression operator {$operator} is not supported"),
        };
    }

    private static function jsonOperatorValue(mixed $left, mixed $right, string $operator): mixed
    {
        if ($left !== null && !is_string($left) && !$left instanceof SQLiteBlobValue && !$left instanceof SQLiteJsonSubtypeValue) {
            throw new \InvalidArgumentException('SQLite SELECT JSON operator left operand must be text, JSONB, JSON subtype, or NULL');
        }

        $path = self::jsonOperatorPath($right);
        $json = $left instanceof SQLiteJsonSubtypeValue ? $left->json : $left;
        $located = SQLiteJsonInspection::locatePath($json, $path);
        if (!$located['found']) {
            return null;
        }

        if ($operator === '->>') {
            $value = $located['value'];
            if ($value === true) {
                return 1;
            }
            if ($value === false) {
                return 0;
            }
            if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
                return $value;
            }

            return SQLiteJsonCanonical::encodeDecodedJson($value);
        }

        return SQLiteJsonCanonical::encodeDecodedJson($located['value']);
    }

    private static function jsonOperatorPath(mixed $operand): string
    {
        if ($operand instanceof SQLiteJsonSubtypeValue) {
            $operand = $operand->json;
        }
        if ($operand instanceof SQLiteBlobValue || is_bool($operand) || is_float($operand) || is_string($operand)) {
            $path = SQLiteJsonPath::normalizeOperatorPath($operand);
            if ($path === null) {
                throw new \InvalidArgumentException('SQLite SELECT JSON operator path is malformed');
            }

            return $path;
        }
        if (is_int($operand)) {
            return SQLiteJsonPath::normalizeOperatorPath($operand) ?? throw new \InvalidArgumentException('SQLite SELECT JSON operator path is malformed');
        }

        throw new \InvalidArgumentException('SQLite SELECT JSON operator path must have scalar, BLOB, or NULL affinity');
    }

    private static function numericValue(mixed $left, mixed $right, string $operator): int|float|null
    {
        $leftNumeric = self::numericOperand($left);
        $rightNumeric = self::numericOperand($right);
        if ($operator === '/' && $rightNumeric == 0) {
            return null;
        }
        if ($operator === '%' && (int) $rightNumeric === 0) {
            return null;
        }

        return match ($operator) {
            '+' => self::integerLike($leftNumeric, $rightNumeric) ? (int) $leftNumeric + (int) $rightNumeric : $leftNumeric + $rightNumeric,
            '-' => self::integerLike($leftNumeric, $rightNumeric) ? (int) $leftNumeric - (int) $rightNumeric : $leftNumeric - $rightNumeric,
            '*' => self::integerLike($leftNumeric, $rightNumeric) ? (int) $leftNumeric * (int) $rightNumeric : $leftNumeric * $rightNumeric,
            '/' => self::integerLike($leftNumeric, $rightNumeric) ? self::integerDivisionValue((int) $leftNumeric, (int) $rightNumeric) : $leftNumeric / $rightNumeric,
            '%' => self::integerLike($leftNumeric, $rightNumeric)
                ? (int) $leftNumeric % (int) $rightNumeric
                : (float) ((int) $leftNumeric % (int) $rightNumeric),
            default => throw new \InvalidArgumentException("SQLite SELECT numeric operator {$operator} is not supported"),
        };
    }

    private static function integerDivisionValue(int $left, int $right): int|float
    {
        if ($left === PHP_INT_MIN && $right === -1) {
            return (float) $left / -1.0;
        }

        return intdiv($left, $right);
    }

    private static function bitwiseValue(mixed $left, mixed $right, string $operator): int
    {
        $leftInteger = self::integerOperand($left);
        $rightInteger = self::integerOperand($right);

        if (($operator === '<<' || $operator === '>>') && $rightInteger < 0) {
            $operator = $operator === '<<' ? '>>' : ($operator === '>>' ? '<<' : $operator);
            $rightInteger = -$rightInteger;
        }

        return match ($operator) {
            '&' => $leftInteger & $rightInteger,
            '|' => $leftInteger | $rightInteger,
            '<<' => $rightInteger >= 64 ? 0 : $leftInteger << $rightInteger,
            '>>' => $rightInteger >= 64 ? ($leftInteger < 0 ? -1 : 0) : $leftInteger >> $rightInteger,
            default => throw new \InvalidArgumentException("SQLite SELECT bitwise operator {$operator} is not supported"),
        };
    }

    private static function integerOperand(mixed $value): int
    {
        if (is_bool($value) || is_int($value)) {
            return (int) $value;
        }
        if (is_float($value)) {
            return self::clampFloatToInt64($value);
        }
        if ($value instanceof SQLiteBlobValue) {
            return self::integerPrefix($value->bytes);
        }
        if (is_string($value)) {
            return self::integerPrefix($value);
        }

        return (int) self::numericOperand($value);
    }

    private static function numericOperand(mixed $value): int|float
    {
        if (is_bool($value) || is_int($value)) {
            return (int) $value;
        }
        if (is_float($value)) {
            return $value;
        }
        if ($value instanceof SQLiteBlobValue) {
            return self::numericPrefix($value->bytes);
        }
        if (is_string($value)) {
            return self::numericPrefix($value);
        }

        throw new \InvalidArgumentException('SQLite SELECT numeric expression operands must be scalar, BLOB, or NULL');
    }

    private static function numericCastOperand(mixed $value): int|float
    {
        if (is_float($value)) {
            return $value;
        }

        $numeric = self::numericOperand($value);
        if (is_int($numeric)) {
            return $numeric;
        }
        if (is_finite($numeric) && floor($numeric) === $numeric && abs($numeric) <= 2251799813685247.0) {
            return (int) $numeric;
        }

        return $numeric;
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

    private static function integerPrefix(string $value): int
    {
        $trimmed = ltrim($value);
        if (preg_match('/^[+-]?[0-9]+/', $trimmed, $match) !== 1) {
            return 0;
        }

        $integer = $match[0];
        $negative = str_starts_with($integer, '-');
        if ($integer[0] === '-' || $integer[0] === '+') {
            $integer = substr($integer, 1);
        }

        $digits = ltrim($integer, '0');
        if ($digits === '') {
            return 0;
        }

        $limit = $negative ? '9223372036854775808' : '9223372036854775807';
        if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            return $negative ? PHP_INT_MIN : PHP_INT_MAX;
        }
        if ($negative && $digits === '9223372036854775808') {
            return PHP_INT_MIN;
        }

        $parsed = (int) $digits;

        return $negative ? -$parsed : $parsed;
    }

    private static function clampFloatToInt64(float $value): int
    {
        if ($value >= 9223372036854775807.0) {
            return PHP_INT_MAX;
        }
        if ($value <= -9223372036854775808.0) {
            return PHP_INT_MIN;
        }

        return (int) $value;
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

    private static function integerLike(int|float $left, int|float $right): bool
    {
        return is_int($left) && is_int($right);
    }

    private static function textValue(mixed $value): string
    {
        if ($value instanceof SQLiteBlobValue) {
            return $value->bytes;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value)) {
            return self::realTextValue($value);
        }
        if (is_int($value) || is_string($value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException('SQLite SELECT concatenation operands must be scalar, BLOB, or NULL');
    }

    private static function realTextValue(float $value): string
    {
        if (floor($value) === $value && abs($value) < 1.0e16) {
            return sprintf('%.1F', $value);
        }

        $text = sprintf('%.15G', $value);

        return str_contains($text, 'E') ? str_replace('E', 'e', $text) : $text;
    }

    /**
     * @param array<string,mixed> $expression
     */
    private static function concatenationTextValue(mixed $value, array $expression): string
    {
        $literalText = $expression['literalText'] ?? null;
        if (is_float($value) && is_string($literalText) && $literalText !== '') {
            return $literalText;
        }

        return self::textValue($value);
    }

    private static function jsonPathText(mixed $value, string $context): string
    {
        if ($value instanceof SQLiteBlobValue || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return self::textValue($value);
        }

        throw new \InvalidArgumentException("SQLite SELECT expression {$context} path must have scalar, BLOB, or NULL affinity");
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function columnValue(array $row, string $name): mixed
    {
        if (array_key_exists($name, $row)) {
            return $row[$name];
        }

        if (str_contains($name, '.')) {
            $schemaQualifiedSuffix = substr($name, strpos($name, '.') + 1);
            if (array_key_exists($schemaQualifiedSuffix, $row)) {
                return $row[$schemaQualifiedSuffix];
            }

            $suffix = substr($name, strrpos($name, '.') + 1);
            if (array_key_exists($suffix, $row)) {
                return $row[$suffix];
            }
        }

        if (!str_contains($name, '.')) {
            $matches = [];
            $suffix = '.' . $name;
            foreach ($row as $column => $value) {
                if (is_string($column) && str_ends_with($column, $suffix)) {
                    $matches[] = $value;
                }
            }
            if (count($matches) === 1) {
                return $matches[0];
            }
            if (count($matches) > 1) {
                throw new \InvalidArgumentException("SQLite SELECT expression column {$name} is ambiguous");
            }
        }

        throw new \InvalidArgumentException("SQLite SELECT expression row is missing column {$name}");
    }

    /**
     * @param array<string,mixed> $expression
     */
    private static function requiredString(array $expression, string $key, string $context): string
    {
        if (!isset($expression[$key]) || !is_string($expression[$key]) || $expression[$key] === '') {
            throw new \InvalidArgumentException("SQLite SELECT expression {$context} needs {$key}");
        }

        return $expression[$key];
    }
}
