<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaResultShape
{
    private const QUERY_OR_ASSIGNMENT = [
        'application_id',
        'automatic_index',
        'auto_vacuum',
        'cache_size',
        'cache_spill',
        'cell_size_check',
        'checkpoint_fullfsync',
        'count_changes',
        'default_cache_size',
        'defer_foreign_keys',
        'empty_result_callbacks',
        'encoding',
        'foreign_keys',
        'full_column_names',
        'fullfsync',
        'ignore_check_constraints',
        'page_size',
        'query_only',
        'read_uncommitted',
        'recursive_triggers',
        'reverse_unordered_selects',
        'schema_version',
        'short_column_names',
        'synchronous',
        'temp_store',
        'user_version',
        'writable_schema',
    ];

    private const NEVER_RETURNS_ROWS = [
        'case_sensitive_like',
        'shrink_memory',
    ];

    /**
     * @return array{pragma:string, mode:'query'|'assignment'|'no-result', column_count:int, row_count:int, source:string}
     */
    public static function describe(string $sql): array
    {
        $parsed = self::parse($sql);
        $name = $parsed['name'];
        if (in_array($name, self::NEVER_RETURNS_ROWS, true)) {
            return self::row($name, 'no-result', 0, 0);
        }

        if (!in_array($name, self::QUERY_OR_ASSIGNMENT, true)) {
            throw new InvalidArgumentException("SQLite PRAGMA result shape does not support {$name}");
        }

        if ($parsed['has_rhs']) {
            return self::row($name, 'assignment', 0, 0);
        }

        return self::row($name, 'query', 1, 1);
    }

    /**
     * @return array{name:string, has_rhs:bool}
     */
    private static function parse(string $sql): array
    {
        $trimmed = rtrim(trim($sql), " \t\r\n;");
        $identifier = '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)';
        if (preg_match('/^pragma\s+(?:(?:' . $identifier . ')\s*\.\s*)?(?<name>[A-Za-z_][A-Za-z0-9_]*)(?<tail>.*)$/is', $trimmed, $matches) !== 1) {
            throw new InvalidArgumentException('SQLite PRAGMA result shape needs a PRAGMA statement');
        }

        $tail = trim($matches['tail']);
        $hasRhs = $tail !== '' && (
            str_starts_with($tail, '=')
            || (str_starts_with($tail, '(') && str_ends_with($tail, ')') && trim(substr($tail, 1, -1)) !== '')
        );

        return [
            'name' => strtolower($matches['name']),
            'has_rhs' => $hasRhs,
        ];
    }

    /**
     * @return array{pragma:string, mode:'query'|'assignment'|'no-result', column_count:int, row_count:int, source:string}
     */
    private static function row(string $name, string $mode, int $columnCount, int $rowCount): array
    {
        return [
            'pragma' => $name,
            'mode' => $mode,
            'column_count' => $columnCount,
            'row_count' => $rowCount,
            'source' => 'SQLite test/pragma4.test pragma4-1 result-column-count semantics',
        ];
    }
}
