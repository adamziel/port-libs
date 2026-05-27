<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteGeneratedColumnDependencyPlan
{
    /**
     * @return array{status: 'ok'|'error', table: string|null, columns: list<array{name: string, generated: bool, storage: string|null, expression: string|null, dependencies: list<string>}>, order: list<string>, cycle: list<string>, message: string|null}
     */
    public static function analyze(string $sql): array
    {
        $table = self::tableName($sql);
        $columns = self::columns($sql);
        $known = [];
        foreach ($columns as $column) {
            $known[strtolower($column['name'])] = $column['name'];
        }

        foreach ($columns as &$column) {
            if ($column['expression'] === null) {
                continue;
            }

            $column['dependencies'] = self::expressionDependencies($column['expression'], $known);
        }
        unset($column);

        $generatedColumns = [];
        $generatedNames = [];
        foreach ($columns as $column) {
            if ($column['generated']) {
                $generatedColumns[strtolower($column['name'])] = true;
                $generatedNames[strtolower($column['name'])] = $column['name'];
            }
        }

        $graph = [];
        foreach ($columns as $column) {
            if (!$column['generated']) {
                continue;
            }

            $graph[strtolower($column['name'])] = array_values(array_filter(
                $column['dependencies'],
                static fn (string $dependency): bool => isset($generatedColumns[strtolower($dependency)]),
            ));
        }

        $cycle = self::firstCycle($graph);
        if ($cycle !== []) {
            $cycle = array_map(
                static fn (string $column): string => $generatedNames[strtolower($column)] ?? $column,
                $cycle,
            );
            return [
                'status' => 'error',
                'table' => $table,
                'columns' => $columns,
                'order' => [],
                'cycle' => $cycle,
                'message' => 'generated column loop on "' . $cycle[count($cycle) - 1] . '"',
            ];
        }

        return [
            'status' => 'ok',
            'table' => $table,
            'columns' => $columns,
            'order' => self::evaluationOrder($graph),
            'cycle' => [],
            'message' => null,
        ];
    }

    private static function tableName(string $sql): ?string
    {
        if (preg_match('/^\s*CREATE\s+(?:TEMP(?:ORARY)?\s+)?TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:"(?<dq>(?:""|[^"])+)"|`(?<bt>[^`]+)`|\[(?<br>[^\]]+)\]|(?<bare>[A-Za-z_][A-Za-z0-9_]*))/i', $sql, $matches) !== 1) {
            return null;
        }

        foreach (['dq', 'bt', 'br', 'bare'] as $key) {
            if (isset($matches[$key]) && $matches[$key] !== '') {
                return self::unquoteIdentifier($matches[$key]);
            }
        }

        return null;
    }

    /**
     * @return list<array{name: string, generated: bool, storage: string|null, expression: string|null, dependencies: list<string>}>
     */
    private static function columns(string $sql): array
    {
        $body = self::parenthesizedBody($sql);
        if ($body === null) {
            return [];
        }

        $columns = [];
        foreach (self::splitTopLevel($body, ',') as $definition) {
            $definition = trim($definition);
            if ($definition === '') {
                continue;
            }

            $constraint = self::stripLeadingConstraint($definition);
            if (
                self::startsWithKeyword($constraint, 'PRIMARY')
                || self::startsWithKeyword($constraint, 'UNIQUE')
                || self::startsWithKeyword($constraint, 'CHECK')
                || self::startsWithKeyword($constraint, 'FOREIGN')
            ) {
                continue;
            }

            $identifier = self::readIdentifier($definition, 0);
            if ($identifier === null) {
                continue;
            }

            $tail = substr($definition, $identifier['end']);
            $expression = self::generatedExpression($tail);
            $columns[] = [
                'name' => $identifier['identifier'],
                'generated' => $expression !== null,
                'storage' => $expression === null ? null : self::generatedStorage($tail),
                'expression' => $expression,
                'dependencies' => [],
            ];
        }

        return $columns;
    }

    private static function generatedExpression(string $tail): ?string
    {
        $as = self::findTopLevelKeyword($tail, 'AS');
        if ($as === null) {
            return null;
        }

        $offset = $as + strlen('AS');
        while (isset($tail[$offset]) && ctype_space($tail[$offset])) {
            $offset++;
        }
        if (!isset($tail[$offset]) || $tail[$offset] !== '(') {
            return null;
        }

        $close = self::matchingParen($tail, $offset);
        if ($close === null) {
            return null;
        }

        return trim(substr($tail, $offset + 1, $close - $offset - 1));
    }

    private static function generatedStorage(string $tail): string
    {
        if (self::containsTopLevelKeyword($tail, 'STORED')) {
            return 'STORED';
        }
        if (self::containsTopLevelKeyword($tail, 'VIRTUAL')) {
            return 'VIRTUAL';
        }

        return 'VIRTUAL';
    }

    /**
     * @param array<string, string> $knownColumns
     * @return list<string>
     */
    private static function expressionDependencies(string $expression, array $knownColumns): array
    {
        $tokens = self::identifierTokens($expression);
        $dependencies = [];
        foreach ($tokens as $index => $token) {
            $value = is_array($token) ? $token['value'] : $token;
            $quoted = is_array($token) && $token['quoted'];
            $lower = strtolower($value);
            if (!isset($knownColumns[$lower])) {
                continue;
            }
            if (!$quoted && self::isExpressionKeyword($value)) {
                continue;
            }
            if (self::tokenValue($tokens[$index - 1] ?? null) === '.') {
                continue;
            }
            if (self::tokenValue($tokens[$index + 1] ?? null) === '(') {
                continue;
            }
            if (!in_array($knownColumns[$lower], $dependencies, true)) {
                $dependencies[] = $knownColumns[$lower];
            }
        }

        return $dependencies;
    }

    /**
     * @return list<string>
     */
    private static function identifierTokens(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];
            if ($char === "'" || $char === '"' || $char === '`') {
                if ($char !== "'") {
                    $tokens[] = [
                        'value' => self::unquoteIdentifier(substr($expression, $i, self::skipQuoted($expression, $i, $char) - $i + 1)),
                        'quoted' => true,
                    ];
                }
                $i = self::skipQuoted($expression, $i, $char);
                continue;
            }
            if ($char === '[') {
                $end = self::skipBracketQuoted($expression, $i);
                $tokens[] = [
                    'value' => self::unquoteIdentifier(substr($expression, $i, $end - $i + 1)),
                    'quoted' => true,
                ];
                $i = $end;
                continue;
            }
            if ($char === '(' || $char === '.' || $char === ')') {
                $tokens[] = $char;
                continue;
            }
            if (preg_match('/[A-Za-z_]/', $char) === 1 && preg_match('/\G([A-Za-z_][A-Za-z0-9_]*)/A', $expression, $matches, 0, $i) === 1) {
                $tokens[] = ['value' => $matches[1], 'quoted' => false];
                $i += strlen($matches[1]) - 1;
            }
        }

        return $tokens;
    }

    /**
     * @param array{value: string, quoted: bool}|string|null $token
     */
    private static function tokenValue(array|string|null $token): ?string
    {
        if ($token === null) {
            return null;
        }

        return is_array($token) ? $token['value'] : $token;
    }

    private static function isExpressionKeyword(string $token): bool
    {
        static $keywords = [
            'case' => true,
            'when' => true,
            'then' => true,
            'else' => true,
            'end' => true,
            'null' => true,
            'is' => true,
            'not' => true,
            'and' => true,
            'or' => true,
            'like' => true,
            'glob' => true,
            'between' => true,
            'in' => true,
            'as' => true,
            'collate' => true,
        ];

        return isset($keywords[strtolower($token)]);
    }

    /**
     * @param array<string, list<string>> $graph
     * @return list<string>
     */
    private static function firstCycle(array $graph): array
    {
        $visiting = [];
        $visited = [];
        $stack = [];

        $visit = function (string $node) use (&$visit, &$visiting, &$visited, &$stack, $graph): array {
            if (isset($visited[$node])) {
                return [];
            }
            if (isset($visiting[$node])) {
                $offset = array_search($node, $stack, true);
                return $offset === false ? [$node] : array_slice($stack, $offset);
            }

            $visiting[$node] = true;
            $stack[] = $node;
            foreach ($graph[$node] ?? [] as $dependency) {
                $dependency = strtolower($dependency);
                if (!isset($graph[$dependency])) {
                    continue;
                }

                $cycle = $visit($dependency);
                if ($cycle !== []) {
                    return $cycle;
                }
            }
            array_pop($stack);
            unset($visiting[$node]);
            $visited[$node] = true;

            return [];
        };

        foreach (array_keys($graph) as $node) {
            $cycle = $visit($node);
            if ($cycle !== []) {
                return $cycle;
            }
        }

        return [];
    }

    /**
     * @param array<string, list<string>> $graph
     * @return list<string>
     */
    private static function evaluationOrder(array $graph): array
    {
        $visited = [];
        $order = [];
        $visit = function (string $node) use (&$visit, &$visited, &$order, $graph): void {
            if (isset($visited[$node])) {
                return;
            }
            $visited[$node] = true;
            foreach ($graph[$node] ?? [] as $dependency) {
                $dependency = strtolower($dependency);
                if (isset($graph[$dependency])) {
                    $visit($dependency);
                }
            }
            $order[] = $node;
        };

        foreach (array_keys($graph) as $node) {
            $visit($node);
        }

        return $order;
    }

    private static function parenthesizedBody(string $sql): ?string
    {
        $open = strpos($sql, '(');
        if ($open === false) {
            return null;
        }
        $close = self::matchingParen($sql, $open);

        return $close === null ? null : substr($sql, $open + 1, $close - $open - 1);
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $text, string $delimiter): array
    {
        $parts = [];
        $start = 0;
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
            if ($char === $delimiter && $depth === 0) {
                $parts[] = substr($text, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($text, $start);

        return $parts;
    }

    /**
     * @return array{identifier: string, end: int}|null
     */
    private static function readIdentifier(string $text, int $offset): ?array
    {
        while (isset($text[$offset]) && ctype_space($text[$offset])) {
            $offset++;
        }
        if (!isset($text[$offset])) {
            return null;
        }
        if ($text[$offset] === '"' || $text[$offset] === '`') {
            $end = self::skipQuoted($text, $offset, $text[$offset]);
            return ['identifier' => self::unquoteIdentifier(substr($text, $offset, $end - $offset + 1)), 'end' => $end + 1];
        }
        if ($text[$offset] === '[') {
            $end = self::skipBracketQuoted($text, $offset);
            return ['identifier' => self::unquoteIdentifier(substr($text, $offset, $end - $offset + 1)), 'end' => $end + 1];
        }
        if (preg_match('/\G([A-Za-z_][A-Za-z0-9_]*)/A', $text, $matches, 0, $offset) !== 1) {
            return null;
        }

        return ['identifier' => $matches[1], 'end' => $offset + strlen($matches[1])];
    }

    private static function stripLeadingConstraint(string $definition): string
    {
        $trimmed = ltrim($definition);
        if (!self::startsWithKeyword($trimmed, 'CONSTRAINT')) {
            return $trimmed;
        }
        $identifier = self::readIdentifier($trimmed, strlen('CONSTRAINT'));

        return $identifier === null ? $trimmed : ltrim(substr($trimmed, $identifier['end']));
    }

    private static function startsWithKeyword(string $text, string $keyword): bool
    {
        $text = ltrim($text);
        $length = strlen($keyword);
        if (strncasecmp($text, $keyword, $length) !== 0) {
            return false;
        }

        return strlen($text) === $length || !self::isIdentifierChar($text[$length]);
    }

    private static function containsTopLevelKeyword(string $text, string $keyword): bool
    {
        return self::findTopLevelKeyword($text, $keyword) !== null;
    }

    private static function findTopLevelKeyword(string $text, string $keyword): ?int
    {
        $depth = 0;
        $length = strlen($text);
        $keywordLength = strlen($keyword);
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
            if ($depth === 0 && strncasecmp(substr($text, $i, $keywordLength), $keyword, $keywordLength) === 0) {
                $before = $i === 0 ? '' : $text[$i - 1];
                $after = $text[$i + $keywordLength] ?? '';
                if (($before === '' || !self::isIdentifierChar($before)) && ($after === '' || !self::isIdentifierChar($after))) {
                    return $i;
                }
            }
        }

        return null;
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
            if (isset($text[$i + 1]) && $text[$i + 1] === $quote) {
                $i++;
                continue;
            }

            return $i;
        }

        return $length - 1;
    }

    private static function skipBracketQuoted(string $text, int $offset): int
    {
        $end = strpos($text, ']', $offset + 1);

        return $end === false ? strlen($text) - 1 : $end;
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return $identifier;
        }
        $first = $identifier[0];
        if ($first === '"' || $first === '`') {
            return str_replace($first . $first, $first, substr($identifier, 1, -1));
        }
        if ($first === '[') {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }

    private static function isIdentifierChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_';
    }
}
