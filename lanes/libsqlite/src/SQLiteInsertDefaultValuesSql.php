<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteInsertDefaultValuesSql
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<string,string> $schemas
     * @return array{target:string,conflict_action:string,before:list<array<string,mixed>>,inserted_row:array<string,mixed>,after:list<array<string,mixed>>,columns:list<array<string,mixed>>,changes:int}
     */
    public static function execute(string $sql, array $tables, array $schemas, ?string $currentTimestamp = null): array
    {
        $plan = self::plan($sql, $tables, $schemas, $currentTimestamp);
        $after = $plan['before'];
        $after[] = $plan['inserted_row'];

        return $plan + [
            'after' => $after,
            'changes' => 1,
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<string,string> $schemas
     * @return array{target:string,conflict_action:string,before:list<array<string,mixed>>,inserted_row:array<string,mixed>,columns:list<array<string,mixed>>}
     */
    public static function plan(string $sql, array $tables, array $schemas, ?string $currentTimestamp = null): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        if (preg_match('/\Ainsert\s+(?:or\s+(abort|fail|ignore|rollback|replace)\s+)?into\s+([A-Za-z_][A-Za-z0-9_]*)\s+default\s+values$/i', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite INSERT DEFAULT VALUES SQL is malformed');
        }

        $target = $match[2];
        if (!array_key_exists($target, $tables)) {
            throw new \InvalidArgumentException("SQLite INSERT DEFAULT VALUES target table {$target} is missing");
        }
        if (!array_key_exists($target, $schemas)) {
            throw new \InvalidArgumentException("SQLite INSERT DEFAULT VALUES schema for {$target} is missing");
        }

        $columns = self::columns($schemas[$target]);
        $row = [];
        foreach ($columns as $column) {
            if ($column['generated'] !== null) {
                continue;
            }
            if ($column['rowid_alias']) {
                $row[$column['name']] = self::nextRowid($tables[$target], $column['name']);
                continue;
            }
            if ($column['default'] !== null) {
                $row[$column['name']] = self::applyDeclaredAffinity(
                    self::evaluateExpression($column['default'], $row, $currentTimestamp),
                    $column['type'],
                );
                continue;
            }

            $row[$column['name']] = null;
        }

        foreach ($columns as $column) {
            if ($column['generated'] === null) {
                continue;
            }
            $row[$column['name']] = self::applyDeclaredAffinity(
                self::evaluateExpression($column['generated'], $row, $currentTimestamp),
                $column['type'],
            );
        }

        foreach ($columns as $column) {
            if ($column['not_null'] && ($row[$column['name']] ?? null) === null) {
                throw new \InvalidArgumentException("SQLite INSERT DEFAULT VALUES column {$column['name']} may not be NULL");
            }
        }
        self::assertCheckConstraints($schemas[$target], $row, $columns);

        return [
            'target' => $target,
            'conflict_action' => strtolower(($match[1] ?? '') === '' ? 'abort' : $match[1]),
            'before' => $tables[$target],
            'inserted_row' => $row,
            'columns' => $columns,
        ];
    }

    /**
     * @return list<array{name:string,type:string,default:?string,generated:?string,stored:bool,not_null:bool,rowid_alias:bool}>
     */
    private static function columns(string $schema): array
    {
        $body = self::tableBody($schema);
        $columns = [];
        foreach (self::splitTopLevel($body, ',') as $definition) {
            $definition = trim($definition);
            if ($definition === '' || preg_match('/^(?:constraint\s+\S+\s+)?(?:primary|unique|check|foreign)\b/i', $definition) === 1) {
                continue;
            }

            [$name, $offset] = self::readIdentifier($definition, 0, 'SQLite CREATE TABLE column name');
            $tail = trim(substr($definition, $offset));
            $generated = self::generatedExpression($tail);
            $columns[] = [
                'name' => $name,
                'type' => self::declaredType($tail),
                'default' => $generated === null ? self::defaultExpression($tail) : null,
                'generated' => $generated,
                'stored' => $generated !== null && preg_match('/\bstored\b/i', $tail) === 1,
                'not_null' => self::hasTopLevelNotNull($tail),
                'rowid_alias' => self::isRowidAlias($tail),
            ];
        }

        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite INSERT DEFAULT VALUES schema has no columns');
        }

        return $columns;
    }

    private static function tableBody(string $schema): string
    {
        $open = strpos($schema, '(');
        if ($open === false) {
            throw new \InvalidArgumentException('SQLite CREATE TABLE SQL is missing column list');
        }
        $close = self::matchingParen($schema, $open);
        if ($close === null) {
            throw new \InvalidArgumentException('SQLite CREATE TABLE column list is malformed');
        }

        return substr($schema, $open + 1, $close - $open - 1);
    }

    private static function declaredType(string $tail): string
    {
        $end = self::firstTopLevelKeywordOffset($tail, ['CONSTRAINT', 'PRIMARY', 'NOT', 'NULL', 'UNIQUE', 'CHECK', 'DEFAULT', 'COLLATE', 'REFERENCES', 'GENERATED', 'AS']);
        $type = $end === null ? $tail : substr($tail, 0, $end);

        return trim(preg_replace('/\s+/', ' ', $type) ?? $type);
    }

    private static function defaultExpression(string $tail): ?string
    {
        $offset = self::firstTopLevelKeywordOffset($tail, ['DEFAULT']);
        if ($offset === null) {
            return null;
        }

        $start = self::skipWhitespace($tail, $offset + strlen('DEFAULT'));
        $end = self::firstTopLevelKeywordOffset(substr($tail, $start), ['CONSTRAINT', 'PRIMARY', 'NOT', 'NULL', 'UNIQUE', 'CHECK', 'COLLATE', 'REFERENCES', 'GENERATED']);
        $expression = $end === null ? substr($tail, $start) : substr($tail, $start, $end);

        return trim($expression) === '' ? null : trim($expression);
    }

    private static function generatedExpression(string $tail): ?string
    {
        $as = self::firstTopLevelKeywordOffset($tail, ['AS']);
        if ($as === null) {
            return null;
        }
        $open = strpos($tail, '(', $as + 2);
        if ($open === false) {
            throw new \InvalidArgumentException('SQLite generated column AS expression is malformed');
        }
        $close = self::matchingParen($tail, $open);
        if ($close === null) {
            throw new \InvalidArgumentException('SQLite generated column AS expression is malformed');
        }

        return trim(substr($tail, $open + 1, $close - $open - 1));
    }

    private static function hasTopLevelNotNull(string $tail): bool
    {
        $not = self::firstTopLevelKeywordOffset($tail, ['NOT']);
        if ($not === null) {
            return false;
        }
        $offset = self::skipWhitespace($tail, $not + strlen('NOT'));

        return preg_match('/\Gnull\b/i', $tail, $m, 0, $offset) === 1;
    }

    private static function isRowidAlias(string $tail): bool
    {
        return preg_match('/^\s*integer\b/i', $tail) === 1
            && self::firstTopLevelKeywordOffset($tail, ['PRIMARY']) !== null
            && preg_match('/\bprimary\s+key\s+desc\b/i', $tail) !== 1;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function nextRowid(array $rows, string $column): int
    {
        $max = 0;
        foreach ($rows as $row) {
            $value = $row[$column] ?? null;
            if (is_int($value) && $value > $max) {
                $max = $value;
            }
        }

        return $max + 1;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function evaluateExpression(string $expression, array $row, ?string $currentTimestamp): mixed
    {
        $expression = trim($expression);
        if (str_starts_with($expression, '(') && self::matchingParen($expression, 0) === strlen($expression) - 1) {
            return self::evaluateExpression(substr($expression, 1, -1), $row, $currentTimestamp);
        }

        foreach (self::splitTopLevelOperator($expression, '||') as $parts) {
            if (count($parts) > 1) {
                return implode('', array_map(
                    static fn (string $part): string => self::textValue(self::evaluateExpression($part, $row, $currentTimestamp)),
                    $parts,
                ));
            }
        }
        foreach (['+', '-'] as $operator) {
            $parts = self::splitTopLevelArithmetic($expression, $operator);
            if (count($parts) > 1) {
                $value = self::numericValue(self::evaluateExpression(array_shift($parts), $row, $currentTimestamp));
                foreach ($parts as $part) {
                    $right = self::numericValue(self::evaluateExpression($part, $row, $currentTimestamp));
                    $value = $operator === '+' ? $value + $right : $value - $right;
                }

                return is_float($value) && floor($value) != $value ? $value : (int) $value;
            }
        }

        if (preg_match("/^'(?:''|[^'])*'$/", $expression) === 1) {
            return str_replace("''", "'", substr($expression, 1, -1));
        }
        if (preg_match('/^"(?:""|[^"])*"$/', $expression) === 1) {
            return str_replace('""', '"', substr($expression, 1, -1));
        }
        if (preg_match('/^[+-]?\d+$/', $expression) === 1) {
            return (int) $expression;
        }
        if (preg_match('/^[+-]?(?:\d+\.\d*|\d*\.\d+)$/', $expression) === 1) {
            return (float) $expression;
        }
        if (strcasecmp($expression, 'NULL') === 0) {
            return null;
        }
        if (strcasecmp($expression, 'TRUE') === 0) {
            return 1;
        }
        if (strcasecmp($expression, 'FALSE') === 0) {
            return 0;
        }
        if (preg_match('/^not\s+(.+)$/is', $expression, $match) === 1) {
            $value = self::evaluateExpression($match[1], $row, $currentTimestamp);

            return $value === null ? null : (self::isSqlTrue($value) ? 0 : 1);
        }
        if (strcasecmp($expression, 'CURRENT_TIMESTAMP') === 0) {
            return $currentTimestamp ?? gmdate('Y-m-d H:i:s');
        }
        if (strcasecmp($expression, 'CURRENT_DATE') === 0) {
            return substr($currentTimestamp ?? gmdate('Y-m-d H:i:s'), 0, 10);
        }
        if (strcasecmp($expression, 'CURRENT_TIME') === 0) {
            return substr($currentTimestamp ?? gmdate('Y-m-d H:i:s'), 11, 8);
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\((.*)\)$/s', $expression, $match) === 1) {
            return self::functionValue($match[1], self::splitTopLevel($match[2], ','), $row, $currentTimestamp);
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expression) === 1) {
            return $row[$expression] ?? null;
        }

        throw new \InvalidArgumentException("SQLite INSERT DEFAULT VALUES expression is unsupported: {$expression}");
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array{name:string,type:string,default:?string,generated:?string,stored:bool,not_null:bool,rowid_alias:bool}> $columns
     */
    private static function assertCheckConstraints(string $schema, array $row, array $columns): void
    {
        $checks = self::checkExpressions($schema);
        if ($checks === []) {
            return;
        }

        $affinities = [];
        foreach ($columns as $column) {
            $affinities[$column['name']] = self::declaredAffinity($column['type']);
        }
        $checkRow = $row + ['__sqlite_column_affinities' => $affinities];

        foreach ($checks as $check) {
            $result = SQLiteSelectSql::execute(
                "SELECT ({$check}) AS check_result FROM inserted_row",
                ['inserted_row' => [$checkRow]],
            );
            $value = $result[0]['check_result'] ?? null;
            if ($value !== null && !self::isSqlTrue($value)) {
                throw new \InvalidArgumentException("CHECK constraint failed: {$check}");
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function checkExpressions(string $schema): array
    {
        $checks = [];
        foreach (self::splitTopLevel(self::tableBody($schema), ',') as $definition) {
            $offset = 0;
            while ($offset < strlen($definition)) {
                $relative = self::firstTopLevelKeywordOffset(substr($definition, $offset), ['CHECK']);
                if ($relative === null) {
                    break;
                }

                $keywordOffset = $offset + $relative;
                $open = self::skipWhitespace($definition, $keywordOffset + strlen('CHECK'));
                if (($definition[$open] ?? null) !== '(') {
                    break;
                }
                $close = self::matchingParen($definition, $open);
                if ($close === null) {
                    throw new \InvalidArgumentException('SQLite CHECK constraint expression is malformed');
                }

                $checks[] = trim(substr($definition, $open + 1, $close - $open - 1));
                $offset = $close + 1;
            }
        }

        return $checks;
    }

    private static function applyDeclaredAffinity(mixed $value, string $declaredType): mixed
    {
        $affinity = self::declaredAffinity($declaredType);
        if ($affinity === 'NONE' || $value === null) {
            return $value;
        }

        return SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
            [['value' => $value]],
            ['value' => $affinity],
        )[0]['value'];
    }

    private static function declaredAffinity(string $declaredType): string
    {
        $type = strtoupper($declaredType);
        if ($type === '') {
            return 'BLOB';
        }
        if (str_contains($type, 'INT')) {
            return 'INTEGER';
        }
        if (str_contains($type, 'CHAR') || str_contains($type, 'CLOB') || str_contains($type, 'TEXT')) {
            return 'TEXT';
        }
        if (str_contains($type, 'BLOB')) {
            return 'BLOB';
        }
        if (str_contains($type, 'REAL') || str_contains($type, 'FLOA') || str_contains($type, 'DOUB')) {
            return 'REAL';
        }

        return 'NUMERIC';
    }

    private static function isSqlTrue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        if ($value instanceof SQLiteBlobValue) {
            $value = $value->bytes;
        }

        return self::numericValue((string) $value) != 0;
    }

    /**
     * @param list<string> $arguments
     * @param array<string,mixed> $row
     */
    private static function functionValue(string $function, array $arguments, array $row, ?string $currentTimestamp): mixed
    {
        $values = array_map(
            static fn (string $argument): mixed => self::evaluateExpression($argument, $row, $currentTimestamp),
            $arguments,
        );

        return match (strtolower($function)) {
            'lower' => strtolower(self::textValue($values[0] ?? null)),
            'upper' => strtoupper(self::textValue($values[0] ?? null)),
            'length' => strlen(self::textValue($values[0] ?? null)),
            'coalesce' => self::coalesce($values),
            'json_extract' => SQLiteJsonExtract::extractSqlFunction('json_extract', self::textValue($values[0] ?? null), ...array_map('strval', array_slice($values, 1))),
            default => throw new \InvalidArgumentException("SQLite INSERT DEFAULT VALUES function {$function} is unsupported"),
        };
    }

    /**
     * @param list<mixed> $values
     */
    private static function coalesce(array $values): mixed
    {
        foreach ($values as $value) {
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private static function textValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }

    private static function numericValue(mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return 0;
    }

    /**
     * @return \Generator<int,list<string>>
     */
    private static function splitTopLevelOperator(string $expression, string $operator): \Generator
    {
        yield self::splitTopLevel($expression, $operator);
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevelArithmetic(string $expression, string $operator): array
    {
        $parts = self::splitTopLevel($expression, $operator);
        if ($operator === '-' && preg_match('/^\s*[+-]?\d/', $expression) === 1) {
            return [$expression];
        }

        return $parts;
    }

    /**
     * @param list<string> $keywords
     */
    private static function firstTopLevelKeywordOffset(string $text, array $keywords): ?int
    {
        $depth = 0;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === "'" || $char === '"' || $char === '`') {
                $i = self::skipQuoted($text, $i, $char);
                continue;
            }
            if ($char === '[') {
                $i = self::skipBracketQuoted($text, $i);
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')' && $depth > 0) {
                $depth--;
                continue;
            }
            if ($depth !== 0) {
                continue;
            }
            foreach ($keywords as $keyword) {
                $keywordLength = strlen($keyword);
                if (
                    strncasecmp(substr($text, $i, $keywordLength), $keyword, $keywordLength) === 0
                    && ($i === 0 || !self::isIdentifierChar($text[$i - 1]))
                    && (!isset($text[$i + $keywordLength]) || !self::isIdentifierChar($text[$i + $keywordLength]))
                ) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $text, string $separator): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $length = strlen($text);
        $separatorLength = strlen($separator);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === "'" || $char === '"' || $char === '`') {
                $i = self::skipQuoted($text, $i, $char);
                continue;
            }
            if ($char === '[') {
                $i = self::skipBracketQuoted($text, $i);
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')' && $depth > 0) {
                $depth--;
                continue;
            }
            if ($depth === 0 && substr($text, $i, $separatorLength) === $separator) {
                $parts[] = trim(substr($text, $start, $i - $start));
                $start = $i + $separatorLength;
                $i += $separatorLength - 1;
            }
        }
        $parts[] = trim(substr($text, $start));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * @return array{0:string,1:int}
     */
    private static function readIdentifier(string $text, int $offset, string $label): array
    {
        $offset = self::skipWhitespace($text, $offset);
        if (preg_match('/\G([A-Za-z_][A-Za-z0-9_]*)/A', $text, $match, 0, $offset) !== 1) {
            throw new \InvalidArgumentException("{$label} is malformed");
        }

        return [$match[1], $offset + strlen($match[1])];
    }

    private static function matchingParen(string $text, int $open): ?int
    {
        $depth = 0;
        $length = strlen($text);
        for ($i = $open; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === "'" || $char === '"' || $char === '`') {
                $i = self::skipQuoted($text, $i, $char);
                continue;
            }
            if ($char === '[') {
                $i = self::skipBracketQuoted($text, $i);
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function skipQuoted(string $text, int $offset, string $quote): int
    {
        $length = strlen($text);
        for ($i = $offset + 1; $i < $length; $i++) {
            if ($text[$i] !== $quote) {
                continue;
            }
            if (($text[$i + 1] ?? null) === $quote) {
                $i++;
                continue;
            }

            return $i;
        }

        return $length - 1;
    }

    private static function skipBracketQuoted(string $text, int $offset): int
    {
        $close = strpos($text, ']', $offset + 1);

        return $close === false ? strlen($text) - 1 : $close;
    }

    private static function skipWhitespace(string $text, int $offset): int
    {
        $length = strlen($text);
        while ($offset < $length && ctype_space($text[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private static function isIdentifierChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_';
    }
}
