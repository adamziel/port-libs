<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonbCheckCurrentNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array{op:string,rowid?:int|string,row?:array<string,mixed>,set?:array<string,mixed>,mutations?:list<array{column?:string,function:string,path:string,value:mixed}>}> $changes
     * @param array{jsonColumn?:string} $options
     * @return array<string,mixed>
     */
    public static function plan(string $createTableSql, array $currentRows, array $changes, array $options = []): array
    {
        $checks = self::checkConstraints($createTableSql);
        if ($checks === []) {
            throw new \InvalidArgumentException('SQLite JSONB CHECK current/next plan requires CHECK constraints');
        }
        $jsonColumn = self::identifier((string) ($options['jsonColumn'] ?? 'key_value'), 'JSON column');

        $rows = self::rowsByRowid($currentRows);
        $current = [];
        foreach ($currentRows as $row) {
            $rowid = self::rowid($row);
            $current[] = [
                'rowid' => $rowid,
                'ok' => self::checksOk(self::evaluateChecks($row, $checks)),
                'checks' => self::evaluateChecks($row, $checks),
            ];
        }

        $accepted = [];
        $rejected = [];
        $next = [];
        foreach ($changes as $index => $change) {
            $candidate = self::candidateRow($rows, $change, $index, $jsonColumn);
            $rowid = self::rowid($candidate);
            $evaluated = self::evaluateChecks($candidate, $checks);
            $record = [
                'op' => strtoupper($change['op']),
                'rowid' => $rowid,
                'row' => $candidate,
                'ok' => self::checksOk($evaluated),
                'checks' => $evaluated,
            ];
            $next[] = $record;
            if ($record['ok']) {
                $rows[$rowid] = $candidate;
                $accepted[] = $record;
                continue;
            }

            $rejected[] = $record;
        }

        return [
            'table' => self::tableName($createTableSql),
            'checks' => $checks,
            'current' => $current,
            'next' => $next,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'after' => array_values($rows),
            'currentFailures' => count(array_filter($current, static fn (array $row): bool => $row['ok'] !== true)),
            'changes' => count($accepted),
            'rejectedChanges' => count($rejected),
        ];
    }

    /**
     * @return list<array{sql:string,terms:list<array<string,mixed>>}>
     */
    public static function checkConstraints(string $createTableSql): array
    {
        $body = self::parenthesizedBody($createTableSql);
        if ($body === null) {
            throw new \InvalidArgumentException('SQLite JSONB CHECK current/next plan requires CREATE TABLE SQL');
        }

        $checks = [];
        foreach (self::splitTopLevel($body, ',') as $definition) {
            $offset = 0;
            while (preg_match('/\bCHECK\s*\(/i', $definition, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
                $open = $match[0][1] + strlen($match[0][0]) - 1;
                $close = self::matchingParen($definition, $open);
                if ($close === null) {
                    throw new \InvalidArgumentException('SQLite JSONB CHECK current/next plan found malformed CHECK constraint');
                }
                $sql = trim(substr($definition, $open + 1, $close - $open - 1));
                $checks[] = ['sql' => 'CHECK(' . $sql . ')', 'terms' => self::parseTerms($sql)];
                $offset = $close + 1;
            }
        }

        return $checks;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function parseTerms(string $sql): array
    {
        $terms = [];
        foreach (self::splitAnd($sql) as $term) {
            $terms[] = self::parseTerm($term);
        }

        return $terms;
    }

    /**
     * @return array<string,mixed>
     */
    private static function parseTerm(string $term): array
    {
        $term = trim(self::stripOuterParens($term));
        if (preg_match('/^NOT\s+(.+)$/i', $term, $m) === 1) {
            return ['operator' => 'NOT', 'terms' => [self::parseTerm($m[1])]];
        }

        $andTerms = self::splitLogical($term, 'AND');
        if (count($andTerms) > 1) {
            return ['operator' => 'AND', 'terms' => array_map(self::parseTerm(...), $andTerms)];
        }

        $orTerms = self::splitLogical($term, 'OR');
        if (count($orTerms) > 1) {
            return ['operator' => 'OR', 'terms' => array_map(self::parseTerm(...), $orTerms)];
        }

        if (preg_match('/^(.+?)\s+IS\s+(NOT\s+)?NULL$/i', $term, $m) === 1) {
            return ['expr' => self::parseExpr($m[1]), 'operator' => isset($m[2]) && trim($m[2]) !== '' ? 'IS NOT NULL' : 'IS NULL', 'value' => null];
        }
        if (preg_match('/^(.+?)\s+NOT\s+BETWEEN\s+(.+?)\s+AND\s+(.+)$/i', $term, $m) === 1) {
            return ['expr' => self::parseExpr($m[1]), 'operator' => 'NOT BETWEEN', 'value' => ['lower' => self::literalValue($m[2]), 'upper' => self::literalValue($m[3])]];
        }
        if (preg_match('/^(.+?)\s+BETWEEN\s+(.+?)\s+AND\s+(.+)$/i', $term, $m) === 1) {
            return ['expr' => self::parseExpr($m[1]), 'operator' => 'BETWEEN', 'value' => ['lower' => self::literalValue($m[2]), 'upper' => self::literalValue($m[3])]];
        }
        if (preg_match('/^(.+?)\s+NOT\s+IN\s*\((.+)\)$/i', $term, $m) === 1) {
            return ['expr' => self::parseExpr($m[1]), 'operator' => 'NOT IN', 'value' => array_map(self::literalValue(...), self::splitTopLevel($m[2], ','))];
        }
        if (preg_match('/^(.+?)\s+IN\s*\((.+)\)$/i', $term, $m) === 1) {
            return ['expr' => self::parseExpr($m[1]), 'operator' => 'IN', 'value' => array_map(self::literalValue(...), self::splitTopLevel($m[2], ','))];
        }
        if (preg_match('/^(.+?)\s*(>=|<=|<>|!=|=|>|<)\s*(.+)$/i', $term, $m) === 1) {
            return ['expr' => self::parseExpr($m[1]), 'operator' => $m[2] === '!=' ? '<>' : $m[2], 'value' => self::literalValue($m[3])];
        }

        return ['expr' => self::parseExpr($term), 'operator' => 'truthy', 'value' => 1];
    }

    /**
     * @return array<string,mixed>
     */
    private static function parseExpr(string $expr): array
    {
        $expr = trim(self::stripOuterParens($expr));
        if (preg_match('/^(json_valid|json_type|json_extract|jsonb_extract|json_array_length)\s*\((.*)\)$/i', $expr, $m) === 1) {
            return ['kind' => 'function', 'name' => strtolower($m[1]), 'args' => array_map(self::parseExpr(...), self::splitTopLevel($m[2], ','))];
        }
        if (preg_match('/^"?([A-Za-z_][A-Za-z0-9_]*)"?$/', $expr, $m) === 1) {
            return ['kind' => 'column', 'name' => $m[1]];
        }

        return ['kind' => 'literal', 'value' => self::literalValue($expr)];
    }

    /**
     * @param list<array{sql:string,terms:list<array<string,mixed>>}> $checks
     * @return list<array<string,mixed>>
     */
    private static function evaluateChecks(array $row, array $checks): array
    {
        $results = [];
        foreach ($checks as $check) {
            $terms = [];
            foreach ($check['terms'] as $term) {
                $terms[] = self::evaluateTerm($term, $row);
            }
            $results[] = ['sql' => $check['sql'], 'ok' => self::termsOk($terms), 'terms' => $terms];
        }

        return $results;
    }

    /**
     * @param array<string,mixed> $term
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function evaluateTerm(array $term, array $row): array
    {
        $operator = (string) ($term['operator'] ?? '');
        if ($operator === 'AND') {
            $children = array_map(static fn (array $child): array => self::evaluateTerm($child, $row), $term['terms']);
            $hasNull = false;
            foreach ($children as $child) {
                if (($child['result'] ?? null) === false) {
                    return array_replace($term, ['actual' => array_column($children, 'actual'), 'result' => false, 'ok' => false, 'terms' => $children]);
                }
                $hasNull = $hasNull || (($child['result'] ?? null) === null);
            }
            $result = $hasNull ? null : true;

            return array_replace($term, ['actual' => array_column($children, 'actual'), 'result' => $result, 'ok' => $result !== false, 'terms' => $children]);
        }
        if ($operator === 'OR') {
            $children = array_map(static fn (array $child): array => self::evaluateTerm($child, $row), $term['terms']);
            $hasNull = false;
            foreach ($children as $child) {
                if (($child['result'] ?? null) === true) {
                    return array_replace($term, ['actual' => array_column($children, 'actual'), 'result' => true, 'ok' => true, 'terms' => $children]);
                }
                $hasNull = $hasNull || (($child['result'] ?? null) === null);
            }
            $result = $hasNull ? null : false;

            return array_replace($term, ['actual' => array_column($children, 'actual'), 'result' => $result, 'ok' => $result !== false, 'terms' => $children]);
        }
        if ($operator === 'NOT') {
            $children = array_map(static fn (array $child): array => self::evaluateTerm($child, $row), $term['terms']);
            $childResult = $children[0]['result'] ?? null;
            $result = $childResult === null ? null : !$childResult;

            return array_replace($term, ['actual' => $children[0]['actual'] ?? null, 'result' => $result, 'ok' => $result !== false, 'terms' => $children]);
        }

        $actual = self::evalExpr($term['expr'], $row);
        $result = self::compare($actual, $operator, $term['value']);

        return $term + ['actual' => $actual, 'result' => $result, 'ok' => $result !== false];
    }

    /**
     * @param array<string,mixed> $expr
     */
    private static function evalExpr(array $expr, array $row): mixed
    {
        return match ($expr['kind']) {
            'column' => $row[$expr['name']] ?? null,
            'literal' => $expr['value'],
            'function' => self::evalFunction($expr['name'], array_map(static fn (array $arg): mixed => self::evalExpr($arg, $row), $expr['args'])),
            default => null,
        };
    }

    /**
     * @param list<mixed> $args
     */
    private static function evalFunction(string $name, array $args): mixed
    {
        if (!in_array($name, ['json_valid', 'json_type', 'json_array_length', 'json_extract', 'jsonb_extract'], true)) {
            throw new \InvalidArgumentException('SQLite JSONB CHECK current/next plan supports JSON CHECK functions only');
        }

        try {
            return match ($name) {
                'json_valid' => SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', $args) ? 1 : 0,
                'json_type' => SQLiteJsonInspection::inspectionSqlFunctionArguments('json_type', $args),
                'json_array_length' => SQLiteJsonInspection::inspectionSqlFunctionArguments('json_array_length', $args),
                'json_extract', 'jsonb_extract' => SQLiteJsonExtract::extractSqlFunction($name, $args[0] ?? null, ...array_map(static fn (mixed $arg): string => (string) $arg, array_slice($args, 1))),
            };
        } catch (\InvalidArgumentException) {
            if ($name === 'json_valid') {
                return 0;
            }

            return null;
        }
    }

    private static function compare(mixed $actual, string $operator, mixed $expected): ?bool
    {
        if ($operator === 'truthy') {
            return $actual === null ? null : ($actual !== 0 && $actual !== false && $actual !== '');
        }
        if ($operator === 'IS NULL') {
            return $actual === null;
        }
        if ($operator === 'IS NOT NULL') {
            return $actual !== null;
        }
        if ($operator === 'BETWEEN') {
            return is_array($expected) && self::compare($actual, '>=', $expected['lower']) && self::compare($actual, '<=', $expected['upper']);
        }
        if ($operator === 'NOT BETWEEN') {
            return is_array($expected) && !self::compare($actual, 'BETWEEN', $expected);
        }
        if ($operator === 'IN') {
            $hasNull = false;
            foreach ((array) $expected as $value) {
                if ($value === null) {
                    $hasNull = true;
                    continue;
                }
                if (self::compare($actual, '=', $value)) {
                    return true;
                }
            }

            return $actual === null || $hasNull ? null : false;
        }
        if ($actual === null || $expected === null) {
            return null;
        }
        if ($operator === 'NOT IN') {
            return !self::compare($actual, 'IN', $expected);
        }

        $comparison = (is_int($actual) || is_float($actual) || is_int($expected) || is_float($expected))
            ? ((float) $actual <=> (float) $expected)
            : ((string) $actual <=> (string) $expected);

        return match ($operator) {
            '=' => $comparison === 0,
            '<>' => $comparison !== 0,
            '>' => $comparison > 0,
            '>=' => $comparison >= 0,
            '<' => $comparison < 0,
            '<=' => $comparison <= 0,
            default => false,
        };
    }

    private static function candidateRow(array $rows, array $change, int $index, string $jsonColumn): array
    {
        $op = strtoupper($change['op'] ?? '');
        if ($op === 'INSERT') {
            if (!isset($change['row']) || !is_array($change['row'])) {
                throw new \InvalidArgumentException('SQLite JSONB CHECK INSERT requires a row');
            }

            return $change['row'];
        }
        if ($op !== 'UPDATE') {
            throw new \InvalidArgumentException('SQLite JSONB CHECK change op must be INSERT or UPDATE');
        }
        $rowid = $change['rowid'] ?? null;
        if (!is_int($rowid) && !is_string($rowid)) {
            throw new \InvalidArgumentException('SQLite JSONB CHECK UPDATE requires rowid');
        }
        if (!isset($rows[$rowid])) {
            throw new \InvalidArgumentException('SQLite JSONB CHECK UPDATE rowid is missing from current rows');
        }
        $row = $rows[$rowid];
        foreach (($change['set'] ?? []) as $column => $value) {
            $row[$column] = $value;
        }
        foreach (($change['mutations'] ?? []) as $mutation) {
            $column = $mutation['column'] ?? $jsonColumn;
            $row[$column] = SQLiteJsonMutation::mutateSqlFunction($mutation['function'], $row[$column] ?? null, $mutation['path'], $mutation['value']);
        }

        return $row;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite JSONB CHECK {$label} must be an identifier");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int|string,array<string,mixed>>
     */
    private static function rowsByRowid(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[self::rowid($row)] = $row;
        }

        return $result;
    }

    private static function rowid(array $row): int|string
    {
        foreach (['rowid', 'setting_id', 'id'] as $column) {
            if (isset($row[$column]) && (is_int($row[$column]) || is_string($row[$column]))) {
                return $row[$column];
            }
        }

        throw new \InvalidArgumentException('SQLite JSONB CHECK row requires rowid, setting_id, or id');
    }

    /**
     * @param list<array<string,mixed>> $checks
     */
    private static function checksOk(array $checks): bool
    {
        foreach ($checks as $check) {
            if (($check['ok'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $terms
     */
    private static function termsOk(array $terms): bool
    {
        foreach ($terms as $term) {
            if (($term['ok'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $terms
     */
    private static function anyTermOk(array $terms): bool
    {
        foreach ($terms as $term) {
            if (($term['ok'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    private static function literalValue(string $literal): mixed
    {
        $literal = trim($literal);
        if (preg_match('/^\'((?:\'\'|[^\'])*)\'$/', $literal, $m) === 1) {
            return str_replace("''", "'", $m[1]);
        }
        if (strcasecmp($literal, 'NULL') === 0) {
            return null;
        }
        if (preg_match('/^-?\d+$/', $literal) === 1) {
            return (int) $literal;
        }
        if (is_numeric($literal)) {
            return (float) $literal;
        }

        throw new \InvalidArgumentException('SQLite JSONB CHECK current/next plan supports literal values only');
    }

    private static function tableName(string $sql): string
    {
        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:"([^"]+)"|`([^`]+)`|\[([^\]]+)\]|([A-Za-z_][A-Za-z0-9_]*))/i', $sql, $m) !== 1) {
            return 'unknown';
        }

        return $m[1] ?: ($m[2] ?: ($m[3] ?: $m[4]));
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

    private static function matchingParen(string $sql, int $open): ?int
    {
        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        for ($i = $open; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if ($quote === "'" && ($sql[$i + 1] ?? null) === "'") {
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"') {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $sql, string $delimiter): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if ($quote === "'" && ($sql[$i + 1] ?? null) === "'") {
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"') {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($depth === 0 && $char === $delimiter) {
                $parts[] = trim(substr($sql, $start, $i - $start));
                $start = $i + 1;
            }
        }
        $parts[] = trim(substr($sql, $start));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * @return list<string>
     */
    private static function splitOr(string $sql): array
    {
        return self::splitLogical($sql, 'OR');
    }

    /**
     * @return list<string>
     */
    private static function splitAnd(string $sql): array
    {
        return self::splitLogical($sql, 'AND');
    }

    /**
     * @return list<string>
     */
    private static function splitLogical(string $sql, string $keyword): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $pattern = '/\G\s+' . preg_quote($keyword, '/') . '\s+/i';
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"') {
                $quote = $char;
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
            if ($depth === 0 && preg_match($pattern, $sql, $m, 0, $i) === 1) {
                if (strcasecmp($keyword, 'AND') === 0 && self::andBelongsToBetween(substr($sql, $start, $i - $start))) {
                    $i += strlen($m[0]) - 1;
                    continue;
                }
                $parts[] = trim(substr($sql, $start, $i - $start));
                $i += strlen($m[0]) - 1;
                $start = $i + 1;
            }
        }
        $parts[] = trim(substr($sql, $start));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    private static function andBelongsToBetween(string $leftSql): bool
    {
        return preg_match('/\b(?:NOT\s+)?BETWEEN\b/i', $leftSql) === 1
            && preg_match('/\bAND\b/i', $leftSql) !== 1;
    }

    private static function stripOuterParens(string $sql): string
    {
        $sql = trim($sql);
        while (str_starts_with($sql, '(')) {
            $close = self::matchingParen($sql, 0);
            if ($close !== strlen($sql) - 1) {
                break;
            }
            $sql = trim(substr($sql, 1, -1));
        }

        return $sql;
    }
}
