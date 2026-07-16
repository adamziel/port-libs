<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonbGeneratedCheckIndexPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{name?:string,sql:string,rootPage?:int,unique?:bool}> $indexes
     * @param list<array{rowid:int|string,mutations:list<array{function:string,path:string,value:mixed}>}> $updates
     * @return array<string,mixed>
     */
    public static function plan(string $createTableSql, array $rows, array $indexes, array $updates, int $pageSize = 512): array
    {
        $before = SQLiteGeneratedJsonPathIndexPlan::btreeYieldPlan($createTableSql, $rows, $indexes, [], $pageSize);
        $workingRows = $before['before'];
        $constraints = self::checkConstraints($createTableSql);
        if ($constraints === []) {
            throw new \InvalidArgumentException('SQLite JSONB generated CHECK index plan requires generated-column CHECK constraints');
        }

        $accepted = [];
        $rejected = [];
        $checkResults = [];
        $indexActions = [];

        foreach ($updates as $update) {
            $rowid = $update['rowid'] ?? null;
            if (!is_int($rowid) && !is_string($rowid)) {
                throw new \InvalidArgumentException('SQLite JSONB generated CHECK index plan UPDATE rowid must be integer or text');
            }

            $candidatePlan = SQLiteGeneratedJsonPathIndexPlan::btreeYieldPlan($createTableSql, $workingRows, $indexes, [$update], $pageSize);
            $changed = $candidatePlan['changed_rows'][0] ?? null;
            if (!is_array($changed)) {
                continue;
            }

            $rowChecks = self::evaluateChecks($changed, $constraints);
            $checkResults[] = [
                'rowid' => $rowid,
                'ok' => self::checksOk($rowChecks),
                'checks' => $rowChecks,
            ];
            if (!self::checksOk($rowChecks)) {
                $rejected[] = [
                    'rowid' => $rowid,
                    'row' => $changed,
                    'checks' => $rowChecks,
                ];
                continue;
            }

            $accepted[] = [
                'rowid' => $rowid,
                'row' => $changed,
                'checks' => $rowChecks,
            ];
            $workingRows = $candidatePlan['after'];
            foreach ($candidatePlan['btree_actions'] as $action) {
                $indexActions[] = $action + ['admitted' => true];
            }
        }

        $after = SQLiteGeneratedJsonPathIndexPlan::btreeYieldPlan($createTableSql, $workingRows, $indexes, [], $pageSize);

        return [
            'table' => $before['table'],
            'generated_columns' => $before['generated_columns'],
            'check_constraints' => $constraints,
            'before' => $before['before'],
            'after' => $after['before'],
            'accepted_updates' => $accepted,
            'rejected_updates' => $rejected,
            'check_results' => $checkResults,
            'before_indexes' => $before['btree_indexes'],
            'after_indexes' => $after['btree_indexes'],
            'index_actions' => $indexActions,
            'index_action_count' => count($indexActions),
            'changes' => count($accepted),
            'pageSize' => $pageSize,
        ];
    }

    /**
     * @return list<array{column:string,operator:string,value:mixed,sql:string}>
     */
    private static function checkConstraints(string $createTableSql): array
    {
        $body = self::parenthesizedBody($createTableSql);
        if ($body === null) {
            throw new \InvalidArgumentException('SQLite JSONB generated CHECK index plan requires CREATE TABLE SQL');
        }

        $constraints = [];
        foreach (self::splitTopLevel($body, ',') as $definition) {
            foreach (self::extractCheckBodies($definition) as $check) {
                $parsed = self::parseCheck($check);
                if ($parsed !== null) {
                    $constraints[] = $parsed + ['sql' => 'CHECK(' . trim($check) . ')'];
                }
            }
        }

        return $constraints;
    }

    /**
     * @return list<string>
     */
    private static function extractCheckBodies(string $definition): array
    {
        $checks = [];
        $offset = 0;
        while (preg_match('/\bCHECK\s*\(/i', $definition, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $open = $matches[0][1] + strlen($matches[0][0]) - 1;
            $close = self::matchingParen($definition, $open);
            if ($close === null) {
                throw new \InvalidArgumentException('SQLite JSONB generated CHECK index plan found malformed CHECK constraint');
            }

            $checks[] = substr($definition, $open + 1, $close - $open - 1);
            $offset = $close + 1;
        }

        return $checks;
    }

    /**
     * @return null|array{column:string,operator:string,value:mixed}
     */
    private static function parseCheck(string $check): ?array
    {
        $check = trim($check);
        if (preg_match('/^"?([A-Za-z_][A-Za-z0-9_]*)"?\s+IS\s+NOT\s+NULL$/i', $check, $matches) === 1) {
            return ['column' => $matches[1], 'operator' => 'IS NOT NULL', 'value' => null];
        }
        if (preg_match('/^"?([A-Za-z_][A-Za-z0-9_]*)"?\s+BETWEEN\s+(.+?)\s+AND\s+(.+)$/i', $check, $matches) === 1) {
            return [
                'column' => $matches[1],
                'operator' => 'BETWEEN',
                'value' => [
                    'lower' => self::literalValue(trim($matches[2])),
                    'upper' => self::literalValue(trim($matches[3])),
                ],
            ];
        }
        if (preg_match('/^"?([A-Za-z_][A-Za-z0-9_]*)"?\s*(>=|<=|<>|!=|=|>|<)\s*(.+)$/i', $check, $matches) !== 1) {
            return null;
        }

        return [
            'column' => $matches[1],
            'operator' => $matches[2] === '!=' ? '<>' : $matches[2],
            'value' => self::literalValue(trim($matches[3])),
        ];
    }

    private static function literalValue(string $literal): mixed
    {
        if (preg_match('/^\'((?:\'\'|[^\'])*)\'$/', $literal, $matches) === 1) {
            return str_replace("''", "'", $matches[1]);
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

        throw new \InvalidArgumentException('SQLite JSONB generated CHECK index plan supports literal CHECK bounds only');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array{column:string,operator:string,value:mixed,sql:string}> $constraints
     * @return list<array<string,mixed>>
     */
    private static function evaluateChecks(array $row, array $constraints): array
    {
        $results = [];
        foreach ($constraints as $constraint) {
            $value = $row[$constraint['column']] ?? null;
            $ok = self::evaluateCheck($value, $constraint['operator'], $constraint['value']);
            $results[] = $constraint + [
                'actual' => $value,
                'ok' => $ok,
            ];
        }

        return $results;
    }

    private static function evaluateCheck(mixed $actual, string $operator, mixed $expected): bool
    {
        if ($operator === 'IS NOT NULL') {
            return $actual !== null;
        }
        if ($actual === null || $expected === null) {
            return false;
        }
        if ($operator === 'BETWEEN') {
            return is_array($expected)
                && array_key_exists('lower', $expected)
                && array_key_exists('upper', $expected)
                && self::evaluateCheck($actual, '>=', $expected['lower'])
                && self::evaluateCheck($actual, '<=', $expected['upper']);
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

    private static function parenthesizedBody(string $sql): ?string
    {
        $open = strpos($sql, '(');
        if ($open === false) {
            return null;
        }
        $close = self::matchingParen($sql, $open);
        if ($close === null) {
            return null;
        }

        return substr($sql, $open + 1, $close - $open - 1);
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $sql, string $separator): array
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
            if ($char === "'" || $char === '"' || $char === '`') {
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
            if ($char === $separator && $depth === 0) {
                $parts[] = substr($sql, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($sql, $start);

        return $parts;
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
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
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
}
