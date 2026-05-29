<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaForeignKeyIndexIntegrityCurrentSourceNext
{
    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<array<string,mixed>> $foreignKeys
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{current:string,next:string,records_hash:string,foreign_key_hash:string,table_hash:string,integrity_sql:string},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{index_admissions:int,index_blockers:int,foreign_key_violations:int},next:array{ready:bool,blocking:list<string>,source_id?:string,offset?:int}|null,rows:list<array{kind:string,table:string,rowid:int|string|null,parent:string,fkid:int|null,index:string|null,columns:list<string>,collations:list<string>,status:string,message:string}>}
     */
    public static function page(
        array $records,
        array $foreignKeys,
        array $tables,
        string $currentSource,
        string $nextSource,
        int $offset = 0,
        int $limit = 131,
        string $integritySql = 'PRAGMA foreign_key_check',
        ?array $cursor = null,
    ): array {
        if ($currentSource === '' || $nextSource === '') {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key/index current-source yield requires current and next source identifiers');
        }
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key/index current-source yield offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key/index current-source yield limit must be positive');
        }

        $source = self::source($records, $foreignKeys, $tables, $currentSource, $nextSource, $integritySql);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $page = SQLitePragmaForeignKeyIndexIntegrityYield::page($records, $foreignKeys, $tables, $offset, $limit);
        $complete = $page['complete'];
        $next = $complete ? null : [
            ...$page['next'],
            'source_id' => $source['source_id'],
            'offset' => $page['next_offset'],
        ];

        return [
            'status' => $page['status'],
            'source_id' => $source['source_id'],
            'current_source' => [
                'current' => $source['current'],
                'next' => $source['next'],
                'records_hash' => $source['records_hash'],
                'foreign_key_hash' => $source['foreign_key_hash'],
                'table_hash' => $source['table_hash'],
                'integrity_sql' => $source['integrity_sql'],
            ],
            'offset' => $page['offset'],
            'limit' => $page['limit'],
            'count' => $page['count'],
            'total' => $page['total'],
            'next_offset' => $page['next_offset'],
            'complete' => $complete,
            'current' => $page['current'],
            'next' => $next,
            'rows' => $page['rows'],
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<array<string,mixed>> $foreignKeys
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{source_id:string,current:string,next:string,records_hash:string,foreign_key_hash:string,table_hash:string,integrity_sql:string}
     */
    private static function source(
        array $records,
        array $foreignKeys,
        array $tables,
        string $currentSource,
        string $nextSource,
        string $integritySql,
    ): array {
        $source = [
            'current' => $currentSource,
            'next' => $nextSource,
            'records_hash' => self::stableHash(array_map(
                static fn (SQLiteSchemaRecord $record): array => [
                    'type' => $record->type,
                    'name' => $record->name,
                    'table' => $record->tableName,
                    'rootpage' => $record->rootPage,
                    'sql' => $record->sql,
                    'rowid' => $record->rowId,
                ],
                $records,
            )),
            'foreign_key_hash' => self::stableHash($foreignKeys),
            'table_hash' => self::stableHash($tables),
            'integrity_sql' => self::normalizeSql($integritySql),
        ];

        return [
            ...$source,
            'source_id' => self::stableHash($source),
        ];
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key/index current-source cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key/index current-source cursor offset does not match the requested page offset');
        }
    }

    private static function normalizeSql(string $sql): string
    {
        $normalized = strtolower(preg_replace('/\s+/', ' ', rtrim(trim($sql), ';')) ?? trim($sql));
        if (!in_array($normalized, ['pragma foreign_key_check', 'pragma integrity_check', 'pragma quick_check'], true)) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key/index current-source yield only accepts foreign_key_check, integrity_check, or quick_check source SQL');
        }

        return $normalized;
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
