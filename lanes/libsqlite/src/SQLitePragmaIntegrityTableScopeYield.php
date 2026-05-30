<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityTableScopeYield
{
    /**
     * @return array{pragma:string,schema:string,target:string,quick:bool,limit:int}
     */
    public static function parse(string $sql): array
    {
        $trimmed = trim(rtrim(trim($sql), ';'));
        $identifier = '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|\'(?:\'\'|[^\'])+\'|[A-Za-z_][A-Za-z0-9_]*)';
        if (!preg_match('/^PRAGMA\s+(?:(?<schema>' . $identifier . ')\s*\.\s*)?(?<pragma>integrity_check|quick_check)\s*\(\s*(?<target>' . $identifier . ')\s*\)$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException('SQLite table-scoped integrity PRAGMA needs PRAGMA integrity_check(table) or quick_check(table)');
        }

        $pragma = strtolower($matches['pragma']);
        $schema = isset($matches['schema']) && $matches['schema'] !== '' ? self::unquoteIdentifier($matches['schema']) : 'main';

        return [
            'pragma' => $pragma,
            'schema' => strtolower($schema),
            'target' => self::unquoteIdentifier($matches['target']),
            'quick' => $pragma === 'quick_check',
            'limit' => 100,
        ];
    }

    /**
     * @return array{status:string,pragma:string,schema:string,target:string,quick:bool,target_roots:list<array{type:string,name:string,table:string,rootpage:int|null}>,errors:list<string>,rows:list<array<string,string>>,current:array{target:string,root_count:int,error_count:int},next:array{ready:bool,blocking:list<string>}}
     */
    public static function collect(string|SQLiteDatabase $database, string $sql): array
    {
        $parsed = self::parse($sql);
        if (is_string($database)) {
            $database = SQLiteDatabase::fromBytes($database);
        }

        $roots = self::targetRoots($database, $parsed['target']);
        if ($roots === []) {
            throw new InvalidArgumentException("SQLite table-scoped integrity PRAGMA target {$parsed['target']} was not found");
        }

        $messages = SQLitePragmaIntegrityCheck::check($database, $parsed['quick'], 1000);
        $errors = array_values(array_filter(
            $messages,
            static fn (string $message): bool => self::messageAppliesToTarget($message, $roots, $parsed['target'])
        ));
        $errors = array_slice($errors, 0, $parsed['limit']);
        $rows = $errors === []
            ? [[$parsed['pragma'] => 'ok']]
            : array_map(static fn (string $error): array => [$parsed['pragma'] => $error], $errors);

        return [
            'status' => $errors === [] ? 'ok' : 'blocked',
            'pragma' => $parsed['pragma'],
            'schema' => $parsed['schema'],
            'target' => $parsed['target'],
            'quick' => $parsed['quick'],
            'target_roots' => $roots,
            'errors' => $errors,
            'rows' => $rows,
            'current' => [
                'target' => $parsed['target'],
                'root_count' => count($roots),
                'error_count' => count($errors),
            ],
            'next' => [
                'ready' => $errors === [],
                'blocking' => $errors === [] ? [] : [$parsed['pragma']],
            ],
        ];
    }

    /**
     * @return array{status:string,pragma:string,schema:string,target:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,mixed>|null,next:array<string,mixed>|null,rows:list<array<string,mixed>>}
     */
    public static function page(string|SQLiteDatabase $database, string $sql, int $offset = 0, int $limit = 32): array
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite table-scoped integrity current/next offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite table-scoped integrity current/next limit must be positive');
        }

        $snapshot = self::collect($database, $sql);
        $rows = [];
        foreach ($snapshot['target_roots'] as $root) {
            $rows[] = [
                'kind' => 'target_root',
                'target' => $snapshot['target'],
                'type' => $root['type'],
                'name' => $root['name'],
                'table' => $root['table'],
                'rootpage' => $root['rootpage'],
                'message' => "{$root['type']} {$root['name']} rootpage " . ($root['rootpage'] ?? 'NULL'),
            ];
        }
        foreach ($snapshot['errors'] as $message) {
            $rows[] = [
                'kind' => $snapshot['pragma'],
                'target' => $snapshot['target'],
                'type' => null,
                'name' => null,
                'table' => null,
                'rootpage' => self::messagePageNumber($message),
                'message' => $message,
            ];
        }

        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);

        return [
            'status' => $snapshot['status'],
            'pragma' => $snapshot['pragma'],
            'schema' => $snapshot['schema'],
            'target' => $snapshot['target'],
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $pageRows[0] ?? null,
            'next' => $pageRows[1] ?? null,
            'rows' => $pageRows,
        ];
    }

    /**
     * @return list<array{type:string,name:string,table:string,rootpage:int|null}>
     */
    private static function targetRoots(SQLiteDatabase $database, string $target): array
    {
        $records = $database->schemaRecords();
        $tableRecord = null;
        foreach ($records as $record) {
            if ($record->type === 'table' && strcasecmp($record->name, $target) === 0) {
                $tableRecord = $record;
                break;
            }
        }
        if ($tableRecord === null) {
            return [];
        }

        $rows = [[
            'type' => 'table',
            'name' => $tableRecord->name,
            'table' => $tableRecord->tableName,
            'rootpage' => $tableRecord->rootPage,
        ]];
        foreach ($records as $record) {
            if ($record->type === 'index' && strcasecmp($record->tableName, $tableRecord->name) === 0) {
                $rows[] = [
                    'type' => 'index',
                    'name' => $record->name,
                    'table' => $record->tableName,
                    'rootpage' => $record->rootPage,
                ];
            }
        }

        usort($rows, static fn (array $left, array $right): int => [($left['rootpage'] ?? PHP_INT_MAX), $left['type'], $left['name']] <=> [($right['rootpage'] ?? PHP_INT_MAX), $right['type'], $right['name']]);

        return $rows;
    }

    /**
     * @param list<array{type:string,name:string,table:string,rootpage:int|null}> $roots
     */
    private static function messageAppliesToTarget(string $message, array $roots, string $target): bool
    {
        $lower = strtolower($message);
        if (
            str_contains($lower, 'freelist')
            || str_contains($lower, 'database header')
            || str_contains($lower, 'largest root btree page')
            || str_contains($lower, 'schema read version')
            || str_contains($lower, 'schema write version')
        ) {
            return false;
        }
        if (str_contains($lower, strtolower($target))) {
            return true;
        }

        $page = self::messagePageNumber($message);
        if ($page === null) {
            return false;
        }
        foreach ($roots as $root) {
            if ($root['rootpage'] === $page) {
                return true;
            }
        }

        return false;
    }

    private static function messagePageNumber(string $message): ?int
    {
        foreach ([
            '/rootpage\s+(\d+)/i',
            '/for\s+page\s+(\d+)/i',
            '/page\s+(\d+)\s+does/i',
            '/page\s+(\d+):/i',
            '/page\s+(\d+)/i',
        ] as $pattern) {
            if (preg_match($pattern, $message, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    private static function unquoteIdentifier(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];
        if ($first === '"' && $last === '"') {
            return str_replace('""', '"', substr($value, 1, -1));
        }
        if ($first === '\'' && $last === '\'') {
            return str_replace("''", "'", substr($value, 1, -1));
        }
        if (($first === '`' && $last === '`') || ($first === '[' && $last === ']')) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
