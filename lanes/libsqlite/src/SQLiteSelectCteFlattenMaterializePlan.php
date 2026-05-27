<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectCteFlattenMaterializePlan
{
    /**
     * @return array<string,mixed>
     */
    public static function plan(string $sql): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        if (preg_match('/^with\s+(recursive\s+)?/i', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT CTE flatten materialize plan needs WITH SELECT SQL');
        }

        $recursive = isset($match[1]) && trim($match[1]) !== '';
        $offset = strlen($match[0]);
        $entries = [];
        while (true) {
            [$entry, $offset] = self::consumeEntry($sql, $offset);
            $entries[] = $entry;
            $offset = self::skipWhitespace($sql, $offset);
            if (($sql[$offset] ?? null) === ',') {
                $offset++;
                continue;
            }
            break;
        }

        $mainSql = trim(substr($sql, $offset));
        if (preg_match('/^select\s+/i', $mainSql) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT CTE flatten materialize plan needs trailing SELECT');
        }

        $plans = [];
        foreach ($entries as $entry) {
            $references = self::sourceReferenceCount($entry['name'], $mainSql);
            foreach ($entries as $other) {
                if ($other['name'] === $entry['name']) {
                    continue;
                }
                $references += self::sourceReferenceCount($entry['name'], $other['sql']);
            }

            $blockers = self::blockers($entry, $references, $recursive);
            $decision = $blockers === [] ? 'flatten' : 'materialize';
            if ($entry['hint'] === 'MATERIALIZED') {
                $decision = 'materialize';
            } elseif ($entry['hint'] === 'NOT MATERIALIZED' && $blockers === []) {
                $decision = 'flatten';
            }

            $plans[] = [
                'name' => $entry['name'],
                'hint' => $entry['hint'],
                'columns' => $entry['columns'],
                'references' => $references,
                'decision' => $decision,
                'reason' => $decision === 'flatten' ? 'flattenable' : ($blockers[0] ?? 'materialized-hint'),
                'blockers' => $blockers,
                'sql' => $entry['sql'],
            ];
        }

        return [
            'recursive' => $recursive,
            'cteCount' => count($plans),
            'ctes' => $plans,
            'mainSql' => $mainSql,
            'materialized' => array_values(array_map(
                static fn (array $plan): string => $plan['name'],
                array_filter($plans, static fn (array $plan): bool => $plan['decision'] === 'materialize'),
            )),
            'flattened' => array_values(array_map(
                static fn (array $plan): string => $plan['name'],
                array_filter($plans, static fn (array $plan): bool => $plan['decision'] === 'flatten'),
            )),
        ];
    }

    /**
     * @return array{0:array{name:string,columns:list<string>,hint:?string,sql:string},1:int}
     */
    private static function consumeEntry(string $sql, int $offset): array
    {
        $offset = self::skipWhitespace($sql, $offset);
        if (preg_match('/\G([A-Za-z_][A-Za-z0-9_]*)/A', $sql, $match, 0, $offset) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT CTE flatten materialize name is malformed');
        }
        $name = $match[1];
        $offset += strlen($name);
        $offset = self::skipWhitespace($sql, $offset);

        $columns = [];
        if (($sql[$offset] ?? null) === '(') {
            [$columnSql, $offset] = self::consumeParenthesized($sql, $offset);
            foreach (self::splitTopLevel($columnSql, ',') as $column) {
                $column = trim($column);
                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
                    throw new \InvalidArgumentException('SQLite SELECT CTE flatten materialize column is malformed');
                }
                $columns[] = $column;
            }
            if ($columns === []) {
                throw new \InvalidArgumentException("SQLite SELECT CTE {$name} column list cannot be empty");
            }
            $offset = self::skipWhitespace($sql, $offset);
        }

        if (!self::keywordAt($sql, $offset, 'AS')) {
            throw new \InvalidArgumentException("SQLite SELECT CTE {$name} needs AS");
        }
        $offset += 2;
        $offset = self::skipWhitespace($sql, $offset);

        $hint = null;
        foreach (['MATERIALIZED', 'NOT MATERIALIZED'] as $candidate) {
            if (self::keywordAt($sql, $offset, $candidate)) {
                $hint = $candidate;
                $offset += strlen($candidate);
                $offset = self::skipWhitespace($sql, $offset);
                break;
            }
        }

        if (($sql[$offset] ?? null) !== '(') {
            throw new \InvalidArgumentException("SQLite SELECT CTE {$name} needs parenthesized body");
        }
        [$body, $offset] = self::consumeParenthesized($sql, $offset);
        $body = trim($body);
        if (preg_match('/^(select|values)\s+/i', $body) !== 1) {
            throw new \InvalidArgumentException("SQLite SELECT CTE {$name} body must be SELECT or VALUES");
        }

        return [['name' => $name, 'columns' => $columns, 'hint' => $hint, 'sql' => $body], $offset];
    }

    /**
     * @param array{name:string,columns:list<string>,hint:?string,sql:string} $entry
     * @return list<string>
     */
    private static function blockers(array $entry, int $references, bool $recursive): array
    {
        $sql = $entry['sql'];
        $blockers = [];
        if ($recursive && self::sourceReferenceCount($entry['name'], $sql) > 0) {
            $blockers[] = 'recursive';
        }
        if ($entry['hint'] === 'MATERIALIZED') {
            $blockers[] = 'materialized-hint';
        }
        if ($references !== 1) {
            $blockers[] = $references === 0 ? 'unused' : 'multiple-references';
        }
        if (preg_match('/^values\s+/i', $sql) === 1) {
            $blockers[] = 'values-body';
        }
        foreach (['DISTINCT', 'GROUP BY', 'HAVING', 'WINDOW', 'LIMIT', 'UNION', 'INTERSECT', 'EXCEPT'] as $keyword) {
            if (self::keywordOffset($sql, $keyword) !== null) {
                $blockers[] = strtolower(str_replace(' ', '-', $keyword));
            }
        }
        if (preg_match('/\bover\s*\(/i', $sql) === 1) {
            $blockers[] = 'window-function';
        }
        if (preg_match('/\b(?:count|sum|avg|min|max|group_concat|json_group_array|json_group_object|jsonb_group_array|jsonb_group_object)\s*\(/i', $sql) === 1) {
            $blockers[] = 'aggregate';
        }

        return array_values(array_unique($blockers));
    }

    private static function referenceCount(string $name, string $sql): int
    {
        preg_match_all('/(?<![A-Za-z0-9_])' . preg_quote($name, '/') . '(?![A-Za-z0-9_])/', $sql, $matches);

        return count($matches[0]);
    }

    private static function sourceReferenceCount(string $name, string $sql): int
    {
        preg_match_all('/\b(?:FROM|JOIN)\s+' . preg_quote($name, '/') . '(?![A-Za-z0-9_])/i', $sql, $matches);

        return count($matches[0]);
    }

    private static function keywordAt(string $sql, int $offset, string $keyword): bool
    {
        if (strtoupper(substr($sql, $offset, strlen($keyword))) !== strtoupper($keyword)) {
            return false;
        }
        $before = $offset === 0 ? ' ' : $sql[$offset - 1];
        $after = $sql[$offset + strlen($keyword)] ?? ' ';

        return !preg_match('/[A-Za-z0-9_]/', $before) && !preg_match('/[A-Za-z0-9_]/', $after);
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
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($depth === 0 && self::keywordAt($sql, $i, $keyword)) {
                return $i;
            }
        }

        return null;
    }

    private static function skipWhitespace(string $sql, int $offset): int
    {
        $length = strlen($sql);
        while ($offset < $length && ctype_space($sql[$offset])) {
            $offset++;
        }

        return $offset;
    }

    /**
     * @return array{0:string,1:int}
     */
    private static function consumeParenthesized(string $sql, int $offset): array
    {
        $depth = 0;
        $quote = false;
        $length = strlen($sql);
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

        throw new \InvalidArgumentException('SQLite SELECT CTE flatten materialize parenthesized SQL is unterminated');
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
