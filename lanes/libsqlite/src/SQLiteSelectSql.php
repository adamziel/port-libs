<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectSql
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<array<string,mixed>>
     */
    public static function execute(string $sql, array $tables): array
    {
        return SQLiteSelectQuery::execute(self::plan($sql, $tables));
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,mixed>
     */
    public static function plan(string $sql, array $tables): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        if (!preg_match('/^select\s+/i', $sql)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL must start with SELECT');
        }

        $fromOffset = self::keywordOffset($sql, 'FROM');
        if ($fromOffset === null) {
            throw new \InvalidArgumentException('SQLite SELECT SQL needs FROM');
        }

        $selectSql = trim(substr($sql, 6, $fromOffset - 6));
        $tail = trim(substr($sql, $fromOffset + 4));
        if ($selectSql === '' || $tail === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL needs select list and table');
        }

        $clauseOffsets = self::tailClauseOffsets($tail);
        $tableEnd = self::firstOffset($clauseOffsets) ?? strlen($tail);
        $table = trim(substr($tail, 0, $tableEnd));
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL table name must be a simple identifier');
        }
        if (!array_key_exists($table, $tables) || !is_array($tables[$table]) || !array_is_list($tables[$table])) {
            throw new \InvalidArgumentException("SQLite SELECT SQL table {$table} is not available");
        }

        $plan = [
            'from' => $tables[$table],
            'select' => self::selectList($selectSql),
        ];

        if (isset($clauseOffsets['WHERE'])) {
            $plan['where'] = self::predicate(self::clauseText($tail, $clauseOffsets, 'WHERE'));
        }
        if (isset($clauseOffsets['ORDER BY'])) {
            $plan['orderBy'] = self::orderBy(self::clauseText($tail, $clauseOffsets, 'ORDER BY'));
        }
        if (isset($clauseOffsets['LIMIT'])) {
            [$limit, $offset] = self::limitOffset(self::clauseText($tail, $clauseOffsets, 'LIMIT'));
            $plan['limit'] = $limit;
            $plan['offset'] = $offset;
        }

        return $plan;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function selectList(string $sql): array
    {
        $items = self::splitTopLevel($sql, ',');
        if ($items === []) {
            throw new \InvalidArgumentException('SQLite SELECT SQL projection needs at least one expression');
        }

        $expressions = [];
        foreach ($items as $item) {
            [$expression, $alias] = self::expressionAlias($item);
            if ($expression === '*') {
                $term = ['type' => 'wildcard'];
            } elseif (str_ends_with($expression, '.*')) {
                $prefix = substr($expression, 0, -2);
                self::assertIdentifier($prefix, 'SQLite SELECT SQL wildcard prefix');
                $term = ['type' => 'wildcard', 'prefix' => $prefix];
            } else {
                $term = self::valueExpression($expression);
                if ($alias !== null) {
                    $term['alias'] = $alias;
                }
            }
            $expressions[] = $term;
        }

        return $expressions;
    }

    /**
     * @return array{0:string,1:?string}
     */
    private static function expressionAlias(string $item): array
    {
        $item = trim($item);
        if ($item === '') {
            throw new \InvalidArgumentException('SQLite SELECT SQL projection expression cannot be empty');
        }
        $as = self::keywordOffset($item, 'AS');
        if ($as === null) {
            return [$item, null];
        }

        $expression = trim(substr($item, 0, $as));
        $alias = trim(substr($item, $as + 2));
        self::assertIdentifier($alias, 'SQLite SELECT SQL projection alias');

        return [$expression, $alias];
    }

    /**
     * @return array<string,mixed>
     */
    private static function predicate(string $sql): array
    {
        $orTerms = self::splitKeyword($sql, 'OR');
        if (count($orTerms) > 1) {
            return ['operator' => 'OR', 'terms' => array_map(self::predicate(...), $orTerms)];
        }

        $andTerms = self::splitKeyword($sql, 'AND');
        if (count($andTerms) > 1) {
            return ['operator' => 'AND', 'terms' => array_map(self::predicate(...), $andTerms)];
        }

        $sql = trim($sql);
        foreach (['NOT LIKE', 'LIKE', '>=', '<=', '<>', '!=', '=', '>', '<'] as $operator) {
            $offset = self::operatorOffset($sql, $operator);
            if ($offset === null) {
                continue;
            }
            $left = trim(substr($sql, 0, $offset));
            $right = trim(substr($sql, $offset + strlen($operator)));
            if ($left === '' || $right === '') {
                throw new \InvalidArgumentException('SQLite SELECT SQL predicate needs both operands');
            }

            return [
                'operator' => $operator,
                'left' => self::valueExpression($left),
                'right' => self::valueExpression($right),
            ];
        }

        if (preg_match('/^(.+?)\s+(not\s+)?in\s*\((.*)\)$/i', $sql, $match) === 1) {
            return [
                'operator' => isset($match[2]) && trim($match[2]) !== '' ? 'NOT IN' : 'IN',
                'left' => self::valueExpression(trim($match[1])),
                'values' => array_map(self::valueExpression(...), self::splitTopLevel($match[3], ',')),
            ];
        }

        if (preg_match('/^(.+?)\s+is\s+(not\s+)?null$/i', $sql, $match) === 1) {
            return [
                'operator' => isset($match[2]) && trim($match[2]) !== '' ? 'IS NOT NULL' : 'IS NULL',
                'left' => self::valueExpression(trim($match[1])),
            ];
        }

        throw new \InvalidArgumentException('SQLite SELECT SQL predicate is not supported');
    }

    /**
     * @return array<string,mixed>
     */
    private static function valueExpression(string $sql): array
    {
        $sql = trim($sql);
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\((.*)\)$/', $sql, $match) === 1) {
            $arguments = trim($match[2]) === '' ? [] : array_map(self::valueExpression(...), self::splitTopLevel($match[2], ','));

            return ['type' => 'function', 'name' => $match[1], 'arguments' => $arguments];
        }
        if (preg_match('/^[+-]?[0-9]+$/', $sql) === 1) {
            return ['type' => 'literal', 'value' => (int) $sql];
        }
        if (preg_match('/^[+-]?(?:[0-9]+\.[0-9]*|\.[0-9]+)$/', $sql) === 1) {
            return ['type' => 'literal', 'value' => (float) $sql];
        }
        if (strcasecmp($sql, 'NULL') === 0) {
            return ['type' => 'literal', 'value' => null];
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/', $sql) === 1) {
            return ['type' => 'column', 'name' => $sql];
        }
        if (str_starts_with($sql, "'") && str_ends_with($sql, "'")) {
            return ['type' => 'literal', 'value' => str_replace("''", "'", substr($sql, 1, -1))];
        }

        throw new \InvalidArgumentException("SQLite SELECT SQL expression {$sql} is not supported");
    }

    /**
     * @return list<array{column:string,direction?:string}>
     */
    private static function orderBy(string $sql): array
    {
        $terms = [];
        foreach (self::splitTopLevel($sql, ',') as $term) {
            $parts = preg_split('/\s+/', trim($term));
            if ($parts === false || $parts === [] || $parts[0] === '') {
                throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY term cannot be empty');
            }
            self::assertIdentifier($parts[0], 'SQLite SELECT SQL ORDER BY column');
            $order = ['column' => $parts[0]];
            if (isset($parts[1])) {
                $direction = strtoupper($parts[1]);
                if ($direction !== 'ASC' && $direction !== 'DESC') {
                    throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY direction must be ASC or DESC');
                }
                $order['direction'] = $direction;
            }
            if (isset($parts[2])) {
                throw new \InvalidArgumentException('SQLite SELECT SQL ORDER BY supports one direction token');
            }
            $terms[] = $order;
        }

        return $terms;
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function limitOffset(string $sql): array
    {
        if (preg_match('/^([+-]?[0-9]+)(?:\s+offset\s+([+-]?[0-9]+))?$/i', trim($sql), $match) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT SQL LIMIT must be integer with optional OFFSET');
        }

        return [(int) $match[1], isset($match[2]) ? (int) $match[2] : 0];
    }

    /**
     * @return array<string,int>
     */
    private static function tailClauseOffsets(string $sql): array
    {
        $offsets = [];
        foreach (['WHERE', 'ORDER BY', 'LIMIT'] as $keyword) {
            $offset = self::keywordOffset($sql, $keyword);
            if ($offset !== null) {
                $offsets[$keyword] = $offset;
            }
        }
        asort($offsets);

        return $offsets;
    }

    /**
     * @param array<string,int> $offsets
     */
    private static function clauseText(string $tail, array $offsets, string $keyword): string
    {
        $start = $offsets[$keyword] + strlen($keyword);
        $end = strlen($tail);
        foreach ($offsets as $other => $offset) {
            if ($offset > $offsets[$keyword]) {
                $end = $offset;
                break;
            }
        }

        $text = trim(substr($tail, $start, $end - $start));
        if ($text === '') {
            throw new \InvalidArgumentException("SQLite SELECT SQL {$keyword} clause cannot be empty");
        }

        return $text;
    }

    /**
     * @param array<string,int> $offsets
     */
    private static function firstOffset(array $offsets): ?int
    {
        return $offsets === [] ? null : min($offsets);
    }

    /**
     * @return list<string>
     */
    private static function splitKeyword(string $sql, string $keyword): array
    {
        $parts = self::splitTopLevelByKeyword($sql, $keyword);
        return count($parts) === 1 ? [trim($sql)] : $parts;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevelByKeyword(string $sql, string $keyword): array
    {
        $parts = [];
        $start = 0;
        $length = strlen($sql);
        $depth = 0;
        $quote = false;
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                if ($quote && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                    continue;
                }
                $quote = !$quote;
                continue;
            }
            if ($quote) {
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                continue;
            }
            if ($depth === 0 && strncasecmp(substr($sql, $i), $keyword, strlen($keyword)) === 0 && self::keywordBounded($sql, $i, strlen($keyword))) {
                $parts[] = trim(substr($sql, $start, $i - $start));
                $start = $i + strlen($keyword);
                $i = $start - 1;
            }
        }
        $parts[] = trim(substr($sql, $start));

        return $parts;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $sql, string $separator): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = false;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                if ($quote && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                    continue;
                }
                $quote = !$quote;
                continue;
            }
            if ($quote) {
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                continue;
            }
            if ($depth === 0 && $char === $separator) {
                $parts[] = trim(substr($sql, $start, $i - $start));
                $start = $i + 1;
            }
        }
        $parts[] = trim(substr($sql, $start));
        if (in_array('', $parts, true)) {
            throw new \InvalidArgumentException('SQLite SELECT SQL list contains an empty item');
        }

        return $parts;
    }

    private static function keywordOffset(string $sql, string $keyword): ?int
    {
        $length = strlen($sql);
        $depth = 0;
        $quote = false;
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                if ($quote && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                    continue;
                }
                $quote = !$quote;
                continue;
            }
            if ($quote) {
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                continue;
            }
            if ($depth === 0 && strncasecmp(substr($sql, $i), $keyword, strlen($keyword)) === 0 && self::keywordBounded($sql, $i, strlen($keyword))) {
                return $i;
            }
        }

        return null;
    }

    private static function operatorOffset(string $sql, string $operator): ?int
    {
        $length = strlen($sql);
        $depth = 0;
        $quote = false;
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                if ($quote && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                    continue;
                }
                $quote = !$quote;
                continue;
            }
            if ($quote) {
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                continue;
            }
            if ($depth === 0 && strncasecmp(substr($sql, $i), $operator, strlen($operator)) === 0) {
                if (ctype_alpha($operator[0]) && !self::keywordBounded($sql, $i, strlen($operator))) {
                    continue;
                }

                return $i;
            }
        }

        return null;
    }

    private static function keywordBounded(string $sql, int $offset, int $length): bool
    {
        $before = $offset === 0 ? ' ' : $sql[$offset - 1];
        $after = $sql[$offset + $length] ?? ' ';

        return !preg_match('/[A-Za-z0-9_]/', $before) && !preg_match('/[A-Za-z0-9_]/', $after);
    }

    private static function assertIdentifier(string $value, string $context): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/', $value) !== 1) {
            throw new \InvalidArgumentException("{$context} must be a simple identifier");
        }
    }
}
