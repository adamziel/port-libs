<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaResultColumnPlan
{
    private const NO_RESULT_PRAGMAS = [
        'case_sensitive_like' => true,
        'shrink_memory' => true,
    ];

    /**
     * @return array{status: string, pragma: string, schema: string, target: string, has_rhs: bool, result_columns: int, source: string}
     */
    public static function plan(string $sql): array
    {
        $parsed = self::parse($sql);
        $pragma = $parsed['pragma'];
        $hasResult = !$parsed['has_rhs'] && !isset(self::NO_RESULT_PRAGMAS[$pragma]);

        return [
            'status' => 'ok',
            'pragma' => $pragma,
            'schema' => $parsed['schema'],
            'target' => $parsed['target'],
            'has_rhs' => $parsed['has_rhs'],
            'result_columns' => $hasResult ? 1 : 0,
            'source' => 'sqlite-upstream-pragma4-result-column-arity',
        ];
    }

    /**
     * @return array{pragma: string, schema: string, target: string, has_rhs: bool}
     */
    private static function parse(string $sql): array
    {
        $trimmed = rtrim(trim($sql), " \t\r\n;");
        if (!preg_match('/^pragma\s+(?<body>.+)$/is', $trimmed, $matches)) {
            throw new InvalidArgumentException('SQLite PRAGMA result-column planner expects a PRAGMA statement');
        }

        $body = trim($matches['body']);
        $hasRhs = false;
        $target = '';
        if (preg_match('/^(?<lhs>[^=]+?)\s*=\s*(?<rhs>.*)$/s', $body, $assignment) === 1) {
            $body = trim($assignment['lhs']);
            $target = trim($assignment['rhs']);
            $hasRhs = true;
        } elseif (preg_match('/^(?<lhs>[A-Za-z_][A-Za-z0-9_]*(?:\s*\.\s*[A-Za-z_][A-Za-z0-9_]*)?)\s*\((?<arg>.*)\)$/s', $body, $call) === 1) {
            $body = trim($call['lhs']);
            $target = trim($call['arg']);
            $hasRhs = $target !== '';
        }

        $parts = preg_split('/\s*\.\s*/', $body);
        if ($parts === false || $parts === []) {
            throw new InvalidArgumentException('SQLite PRAGMA result-column planner could not parse pragma name');
        }

        $schema = 'main';
        $pragma = strtolower(trim((string) end($parts)));
        if (count($parts) === 2) {
            $schema = strtolower(trim((string) $parts[0]));
        } elseif (count($parts) > 2) {
            throw new InvalidArgumentException('SQLite PRAGMA result-column planner supports at most one schema qualifier');
        }

        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $pragma)) {
            throw new InvalidArgumentException('SQLite PRAGMA result-column planner found an invalid pragma name');
        }

        return [
            'pragma' => $pragma,
            'schema' => $schema,
            'target' => $target,
            'has_rhs' => $hasRhs,
        ];
    }
}
