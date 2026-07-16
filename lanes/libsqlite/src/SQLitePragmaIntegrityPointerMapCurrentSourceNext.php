<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityPointerMapCurrentSourceNext
{
    /**
     * @return array{status:string,reason:string,integrity_sql:string,current_source:string,next_source:string,current:array{source:string,database_hash:string,total:int,pointer_map:int,freelist:int},next:array{source:string,database_hash:string,total:int,pointer_map:int,freelist:int,ready:bool,blocking:list<string>},resolved:list<array<string,mixed>>,persisting:list<array<string,mixed>>,introduced:list<array<string,mixed>>,resolved_count:int,persisting_count:int,introduced_count:int,must_block_commit:bool,dependencies:list<string>}
     */
    public static function compare(
        string|SQLiteDatabase $currentDatabase,
        string|SQLiteDatabase $nextDatabase,
        string $currentSource,
        string $nextSource,
        string $integritySql = 'PRAGMA integrity_check',
    ): array {
        self::assertSourcePair($currentSource, $nextSource);

        $currentRows = self::snapshotRows($currentDatabase, $currentSource, $nextSource, $integritySql, 'current');
        $nextRows = self::snapshotRows($nextDatabase, $currentSource, $nextSource, $integritySql, 'next');
        $currentByKey = self::rowsByKey($currentRows);
        $nextByKey = self::rowsByKey($nextRows);

        $resolved = [];
        foreach ($currentByKey as $key => $row) {
            if (!isset($nextByKey[$key])) {
                $resolved[] = $row;
            }
        }

        $persisting = [];
        foreach ($currentByKey as $key => $row) {
            if (isset($nextByKey[$key])) {
                $persisting[] = $row;
            }
        }

        $introduced = [];
        foreach ($nextByKey as $key => $row) {
            if (!isset($currentByKey[$key])) {
                $introduced[] = $row;
            }
        }

        $blocking = [];
        if ($persisting !== []) {
            $blocking[] = 'persisting_pointer_map_integrity';
        }
        if ($introduced !== []) {
            $blocking[] = 'introduced_pointer_map_integrity';
        }

        $status = match (true) {
            $introduced !== [] => 'next_introduced_pointer_map_integrity_findings',
            $persisting !== [] && $resolved !== [] => 'next_partially_resolved_pointer_map_integrity_findings',
            $persisting !== [] => 'next_preserved_pointer_map_integrity_findings',
            $resolved !== [] => 'next_resolved_pointer_map_integrity_findings',
            default => 'pointer_map_integrity_clean',
        };

        return [
            'status' => $status,
            'reason' => 'pragma_integrity_check_pointermap_current_source_next119',
            'integrity_sql' => self::normalizeSql($integritySql),
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'current' => self::summary($currentRows, $currentDatabase, $currentSource),
            'next' => self::summary($nextRows, $nextDatabase, $nextSource) + [
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
            'resolved' => array_values($resolved),
            'persisting' => array_values($persisting),
            'introduced' => array_values($introduced),
            'resolved_count' => count($resolved),
            'persisting_count' => count($persisting),
            'introduced_count' => count($introduced),
            'must_block_commit' => $blocking !== [],
            'dependencies' => [
                'sqlite-pragma-integrity-check',
                'sqlite-pointer-map-freelist-yield',
                'sqlite-current-source-next119-admission',
            ],
        ];
    }

    /**
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{source:string,database_hash:string,total:int,pointer_map:int,freelist:int},next:array{source:string,database_hash:string,total:int,pointer_map:int,freelist:int,ready:bool,blocking:list<string>},rows:list<array<string,mixed>>}
     */
    public static function page(
        string|SQLiteDatabase $currentDatabase,
        string|SQLiteDatabase $nextDatabase,
        string $currentSource,
        string $nextSource,
        int $offset = 0,
        int $limit = 119,
        string $integritySql = 'PRAGMA integrity_check',
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity pointer-map current-source next119 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity pointer-map current-source next119 limit must be positive');
        }

        $comparison = self::compare($currentDatabase, $nextDatabase, $currentSource, $nextSource, $integritySql);
        $rows = [];
        foreach (['resolved', 'persisting', 'introduced'] as $transition) {
            foreach ($comparison[$transition] as $row) {
                $rows[] = $row + ['transition' => $transition];
            }
        }

        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);

        return [
            'status' => $comparison['status'],
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $comparison['current'],
            'next' => $comparison['next'],
            'rows' => $pageRows,
        ];
    }

    private static function assertSourcePair(string $currentSource, string $nextSource): void
    {
        if (trim($currentSource) === '' || trim($nextSource) === '') {
            throw new InvalidArgumentException('SQLite PRAGMA integrity pointer-map current-source next119 requires current and next source identifiers');
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function snapshotRows(
        string|SQLiteDatabase $database,
        string $currentSource,
        string $nextSource,
        string $integritySql,
        string $snapshot,
    ): array {
        $rows = [];
        $databaseObject = is_string($database) ? self::databaseOrNull($database) : $database;
        foreach (SQLitePragmaIntegrityPointerMapFreelistYield::collect($database, $integritySql) as $row) {
            $page = $row['page'];
            $entry = $databaseObject !== null && $page !== null ? self::entryOrNull($databaseObject, $page) : null;
            $rows[] = $row + [
                'snapshot' => $snapshot,
                'current_source' => $currentSource,
                'next_source' => $nextSource,
                'pointer_map_type' => $entry?->typeName(),
                'pointer_map_parent' => $entry?->parentPageNumber,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{source:string,database_hash:string,total:int,pointer_map:int,freelist:int}
     */
    private static function summary(array $rows, string|SQLiteDatabase $database, string $source): array
    {
        return [
            'source' => $source,
            'database_hash' => self::databaseHash($database),
            'total' => count($rows),
            'pointer_map' => count(array_filter($rows, static fn (array $row): bool => ($row['source'] ?? null) === 'pointer_map')),
            'freelist' => count(array_filter($rows, static fn (array $row): bool => ($row['source'] ?? null) === 'freelist')),
        ];
    }

    private static function databaseHash(string|SQLiteDatabase $database): string
    {
        return hash('sha256', is_string($database) ? $database : $database->bytes());
    }

    private static function normalizeSql(string $sql): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $sql) ?? $sql));
    }

    private static function databaseOrNull(string $database): ?SQLiteDatabase
    {
        try {
            return SQLiteDatabase::fromBytes($database);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private static function entryOrNull(SQLiteDatabase $database, int $pageNumber): ?SQLitePointerMapEntry
    {
        try {
            if (!$database->isAutoVacuum() || $pageNumber < 2 || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
                return null;
            }

            return $database->pointerMapEntryForPage($pageNumber);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private static function rowsByKey(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $key = implode('|', [
                (string) ($row['kind'] ?? ''),
                (string) ($row['source'] ?? ''),
                (string) ($row['page'] ?? ''),
                (string) ($row['pointer_map_page'] ?? ''),
                (string) ($row['message'] ?? ''),
            ]);
            if (isset($indexed[$key])) {
                throw new InvalidArgumentException('SQLite PRAGMA integrity pointer-map current-source next119 received duplicate diagnostics');
            }
            $indexed[$key] = $row;
        }

        return $indexed;
    }
}
