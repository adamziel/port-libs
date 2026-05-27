<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectFlatteningPlan
{
    /**
     * @return array<string,mixed>
     */
    public static function plan(string $sql): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        if (!preg_match('/^select\s+/i', $sql)) {
            throw new \InvalidArgumentException('SQLite SELECT flattening plan needs SELECT SQL');
        }

        $outer = self::selectShape($sql);
        $fromSql = $outer['from'];
        $baseFromSql = self::baseSourceSql($fromSql);
        $derived = self::derivedSource($baseFromSql);
        if ($derived === null) {
            return [
                'flattenable' => false,
                'reason' => 'no-derived-source',
                'blockers' => ['no-derived-source'],
                'outer' => $outer,
            ];
        }

        $inner = self::selectShape($derived['sql']);
        $blockers = self::blockers($outer, $inner, $fromSql);
        $flattenable = $blockers === [];

        $plan = [
            'flattenable' => $flattenable,
            'reason' => $flattenable ? 'flattenable' : $blockers[0],
            'blockers' => $blockers,
            'alias' => $derived['alias'],
            'outer' => $outer,
            'inner' => $inner,
            'projectionMap' => self::projectionMap($inner['select']),
            'mergedWhere' => self::mergedWhere($inner['where'], $outer['where']),
        ];

        if ($flattenable) {
            $plan['flattenedSql'] = self::flattenedSql($outer, $inner);
        }

        return $plan;
    }

    /**
     * @return array{select:string,from:string,where:?string,groupBy:?string,having:?string,orderBy:?string,limit:?string,distinct:bool,compound:bool,hasJoin:bool,hasAggregate:bool,hasWindow:bool}
     */
    private static function selectShape(string $sql): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        if (self::compoundOperatorOffset($sql) !== null) {
            return [
                'select' => trim(substr($sql, 6)),
                'from' => '',
                'where' => null,
                'groupBy' => null,
                'having' => null,
                'orderBy' => null,
                'limit' => null,
                'distinct' => preg_match('/^select\s+distinct(?:\s+|$)/i', $sql) === 1,
                'compound' => true,
                'hasJoin' => false,
                'hasAggregate' => self::hasAggregate($sql),
                'hasWindow' => self::hasWindow($sql),
            ];
        }

        $fromOffset = self::keywordOffset($sql, 'FROM');
        if ($fromOffset === null) {
            throw new \InvalidArgumentException('SQLite SELECT flattening plan needs a FROM clause');
        }

        $select = trim(substr($sql, 6, $fromOffset - 6));
        $distinct = false;
        if (preg_match('/^distinct(?:\s+|$)/i', $select) === 1) {
            $distinct = true;
            $select = trim(substr($select, 8));
        } elseif (preg_match('/^all(?:\s+|$)/i', $select) === 1) {
            $select = trim(substr($select, 3));
        }

        $tail = trim(substr($sql, $fromOffset + 4));
        $offsets = self::tailClauseOffsets($tail);
        $fromEnd = self::firstOffset($offsets) ?? strlen($tail);
        $from = trim(substr($tail, 0, $fromEnd));

        return [
            'select' => $select,
            'from' => $from,
            'where' => isset($offsets['WHERE']) ? self::clauseText($tail, $offsets, 'WHERE') : null,
            'groupBy' => isset($offsets['GROUP BY']) ? self::clauseText($tail, $offsets, 'GROUP BY') : null,
            'having' => isset($offsets['HAVING']) ? self::clauseText($tail, $offsets, 'HAVING') : null,
            'orderBy' => isset($offsets['ORDER BY']) ? self::clauseText($tail, $offsets, 'ORDER BY') : null,
            'limit' => isset($offsets['LIMIT']) ? self::clauseText($tail, $offsets, 'LIMIT') : null,
            'distinct' => $distinct,
            'compound' => false,
            'hasJoin' => self::joinOffset($from) !== null,
            'hasAggregate' => self::hasAggregate($select),
            'hasWindow' => self::hasWindow($select),
        ];
    }

    /**
     * @return array{sql:string,alias:string}|null
     */
    private static function derivedSource(string $fromSql): ?array
    {
        $fromSql = trim($fromSql);
        if (!str_starts_with($fromSql, '(')) {
            return null;
        }

        [$body, $offset] = self::consumeParenthesized($fromSql, 0);
        $body = trim($body);
        if (preg_match('/^select\s+/i', $body) !== 1) {
            return null;
        }

        $tail = trim(substr($fromSql, $offset));
        $alias = 'subquery';
        if ($tail !== '') {
            if (preg_match('/^(?:AS\s+)?([A-Za-z_][A-Za-z0-9_]*)(?:\s*\([^)]*\))?$/i', $tail, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite SELECT flattening derived alias is malformed');
            }
            $alias = $match[1];
        }

        return ['sql' => $body, 'alias' => $alias];
    }

    private static function baseSourceSql(string $fromSql): string
    {
        $joinOffset = self::joinOffset($fromSql);

        return $joinOffset === null ? trim($fromSql) : trim(substr($fromSql, 0, $joinOffset));
    }

    /**
     * @param array<string,mixed> $outer
     * @param array<string,mixed> $inner
     * @return list<string>
     */
    private static function blockers(array $outer, array $inner, string $fromSql): array
    {
        $blockers = [];
        if (self::joinOffset($fromSql) !== null || $outer['hasJoin']) {
            $blockers[] = 'outer-join-source';
        }
        if ($outer['distinct']) {
            $blockers[] = 'outer-distinct';
        }
        if ($outer['groupBy'] !== null || $outer['having'] !== null || $outer['hasAggregate']) {
            $blockers[] = 'outer-aggregate';
        }
        if ($inner['compound']) {
            $blockers[] = 'inner-compound';
        }
        if ($inner['distinct']) {
            $blockers[] = 'inner-distinct';
        }
        if ($inner['groupBy'] !== null || $inner['having'] !== null || $inner['hasAggregate']) {
            $blockers[] = 'inner-aggregate';
        }
        if ($inner['limit'] !== null) {
            $blockers[] = 'inner-limit';
        }
        if ($inner['orderBy'] !== null && ($outer['orderBy'] !== null || $outer['limit'] !== null)) {
            $blockers[] = 'inner-order-sensitive';
        }
        if ($inner['hasWindow'] || $outer['hasWindow']) {
            $blockers[] = 'window-function';
        }

        return array_values(array_unique($blockers));
    }

    /**
     * @return array<string,string>
     */
    private static function projectionMap(string $select): array
    {
        $map = [];
        foreach (self::splitTopLevel($select, ',') as $term) {
            $term = trim($term);
            if ($term === '') {
                continue;
            }
            $alias = null;
            $expression = $term;
            if (preg_match('/^(.*?)(?:\s+AS\s+|\s+)([A-Za-z_][A-Za-z0-9_]*)$/i', $term, $match) === 1) {
                $candidate = trim($match[2]);
                $prefix = trim($match[1]);
                if (!in_array(strtolower($candidate), ['from', 'where', 'group', 'having', 'order', 'limit'], true)) {
                    $alias = $candidate;
                    $expression = $prefix;
                }
            }
            if ($alias === null && preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/', $term) === 1) {
                $alias = str_contains($term, '.') ? substr($term, strrpos($term, '.') + 1) : $term;
            }
            if ($alias !== null) {
                $map[$alias] = $expression;
            }
        }

        return $map;
    }

    private static function mergedWhere(?string $innerWhere, ?string $outerWhere): ?string
    {
        if ($innerWhere === null || trim($innerWhere) === '') {
            return $outerWhere === null ? null : trim($outerWhere);
        }
        if ($outerWhere === null || trim($outerWhere) === '') {
            return trim($innerWhere);
        }

        return '(' . trim($innerWhere) . ') AND (' . trim($outerWhere) . ')';
    }

    /**
     * @param array<string,mixed> $outer
     * @param array<string,mixed> $inner
     */
    private static function flattenedSql(array $outer, array $inner): string
    {
        $sql = 'SELECT ' . $outer['select'] . ' FROM ' . $inner['from'];
        $where = self::mergedWhere($inner['where'], $outer['where']);
        if ($where !== null) {
            $sql .= ' WHERE ' . $where;
        }
        if ($outer['orderBy'] !== null) {
            $sql .= ' ORDER BY ' . $outer['orderBy'];
        } elseif ($inner['orderBy'] !== null) {
            $sql .= ' ORDER BY ' . $inner['orderBy'];
        }
        if ($outer['limit'] !== null) {
            $sql .= ' LIMIT ' . $outer['limit'];
        }

        return $sql;
    }

    private static function hasAggregate(string $sql): bool
    {
        return preg_match('/\b(?:count|sum|avg|min|max|group_concat|json_group_array|json_group_object|jsonb_group_array|jsonb_group_object)\s*\(/i', $sql) === 1;
    }

    private static function hasWindow(string $sql): bool
    {
        return preg_match('/\bover\s*\(/i', $sql) === 1;
    }

    private static function keywordOffset(string $sql, string $keyword): ?int
    {
        $pattern = strtoupper($keyword);
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
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($depth !== 0) {
                continue;
            }
            if (strtoupper(substr($sql, $i, strlen($pattern))) !== $pattern) {
                continue;
            }
            $before = $i === 0 ? ' ' : $sql[$i - 1];
            $after = $sql[$i + strlen($pattern)] ?? ' ';
            if (!preg_match('/[A-Za-z0-9_]/', $before) && !preg_match('/[A-Za-z0-9_]/', $after)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return array<string,int>
     */
    private static function tailClauseOffsets(string $tail): array
    {
        $offsets = [];
        foreach (['WHERE', 'GROUP BY', 'HAVING', 'ORDER BY', 'LIMIT'] as $keyword) {
            $offset = self::keywordOffset($tail, $keyword);
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
    private static function firstOffset(array $offsets): ?int
    {
        return $offsets === [] ? null : min($offsets);
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
                $end = min($end, $offset);
            }
        }

        return trim(substr($tail, $start, $end - $start));
    }

    private static function compoundOperatorOffset(string $sql): ?int
    {
        foreach (['UNION ALL', 'UNION', 'INTERSECT', 'EXCEPT'] as $operator) {
            $offset = self::keywordOffset($sql, $operator);
            if ($offset !== null) {
                return $offset;
            }
        }

        return null;
    }

    private static function joinOffset(string $sql): ?int
    {
        foreach (['LEFT JOIN', 'INNER JOIN', 'CROSS JOIN', 'JOIN'] as $join) {
            $offset = self::keywordOffset($sql, $join);
            if ($offset !== null) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * @return array{0:string,1:int}
     */
    private static function consumeParenthesized(string $sql, int $offset): array
    {
        $length = strlen($sql);
        if (($sql[$offset] ?? null) !== '(') {
            throw new \InvalidArgumentException('SQLite SELECT flattening expected parenthesized SQL');
        }

        $depth = 0;
        $quote = false;
        for ($i = $offset; $i < $length; $i++) {
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
                if ($depth === 0) {
                    return [substr($sql, $offset + 1, $i - $offset - 1), $i + 1];
                }
            }
        }

        throw new \InvalidArgumentException('SQLite SELECT flattening parenthesized SQL is unterminated');
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $sql, string $delimiter): array
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
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($depth === 0 && substr($sql, $i, strlen($delimiter)) === $delimiter) {
                $parts[] = trim(substr($sql, $start, $i - $start));
                $start = $i + strlen($delimiter);
            }
        }
        $parts[] = trim(substr($sql, $start));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}
