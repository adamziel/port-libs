<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoIntegrityRootYield
{
    /**
     * @return list<array<string, int|string|null>>
     */
    public static function collect(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValued = false,
    ): array {
        $cursor = $tableValued
            ? $catalog->executeTableValuedPragmaCursor($indexXinfoSql)
            : ($catalog instanceof SQLiteAttachedSchemaCatalog
                ? $catalog->executeSchemaPragmaCursor($indexXinfoSql)
                : $catalog->executeCursor($indexXinfoSql));
        $metadata = $cursor->metadata();
        if (($metadata['pragma'] ?? null) !== 'index_xinfo') {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo integrity yield requires index_xinfo rows');
        }

        $rows = [];
        foreach ($cursor->rows() as $row) {
            $rows[] = [
                'kind' => 'index_xinfo',
                'schema' => $metadata['schema'],
                'target' => $metadata['target'],
                'seqno' => $row['seqno'] ?? null,
                'cid' => $row['cid'] ?? null,
                'name' => $row['name'] ?? null,
                'desc' => $row['desc'] ?? null,
                'coll' => $row['coll'] ?? null,
                'key' => $row['key'] ?? null,
                'message' => self::indexMessage($metadata['schema'], $metadata['target'], $row),
            ];
        }

        $integrity = SQLitePragmaIntegrityCheck::execute($integritySql, $database);
        $seenIntegrity = [];
        foreach ($integrity['errors'] as $message) {
            if (!self::isRootIntegrityMessage($message)) {
                continue;
            }
            if (isset($seenIntegrity[$message])) {
                continue;
            }
            $seenIntegrity[$message] = true;

            $rows[] = [
                'kind' => $integrity['pragma'],
                'schema' => null,
                'target' => null,
                'seqno' => null,
                'cid' => null,
                'name' => null,
                'desc' => null,
                'coll' => null,
                'key' => null,
                'message' => $message,
            ];
        }

        return $rows;
    }

    /**
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,int|string|null>|null,next:array<string,int|string|null>|null,rows:list<array<string,int|string|null>>}
     */
    public static function page(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        int $offset = 0,
        int $limit = 54,
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValued = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo integrity root yield offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo integrity root yield limit must be positive');
        }

        $rows = self::collect($catalog, $indexXinfoSql, $database, $integritySql, $tableValued);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);

        return [
            'status' => 'ok',
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
     * @param array<string, int|string|null> $metadata
     * @param array<string, int|string|null> $row
     */
    private static function indexMessage(string $schema, string $target, array $row): string
    {
        $name = $row['name'] ?? null;
        $label = $name === null || $name === '' ? 'expression/rowid' : (string) $name;

        return sprintf(
            'index_xinfo %s.%s seqno %d cid %d %s coll %s key %d',
            $schema,
            $target,
            (int) ($row['seqno'] ?? 0),
            (int) ($row['cid'] ?? 0),
            $label,
            (string) ($row['coll'] ?? 'BINARY'),
            (int) ($row['key'] ?? 0),
        );
    }

    private static function isRootIntegrityMessage(string $message): bool
    {
        $lower = strtolower($message);

        return str_contains($lower, 'sqlite_schema')
            || str_contains($lower, 'largest root btree page')
            || (str_contains($lower, 'pointer-map type') && str_contains($lower, 'root-page'));
    }
}
