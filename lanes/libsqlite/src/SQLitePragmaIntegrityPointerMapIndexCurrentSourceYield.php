<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityPointerMapIndexCurrentSourceYield
{
    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array{kind:string,source:string,current_source:string,next_source:string,page:int|null,pointer_map_page:int|null,pointer_map_type:string|null,pointer_map_parent:int|null,index:string|null,table:string|null,root_page:int|null,root_kind:string|null,message:string}>
     */
    public static function collect(
        string|SQLiteDatabase $database,
        array $records,
        string $currentSource,
        string $nextSource,
        string $integritySql = 'PRAGMA integrity_check',
    ): array {
        self::assertSourcePair($currentSource, $nextSource);

        $databaseObject = is_string($database) ? self::databaseOrNull($database) : $database;
        $rootByPage = self::rootRecordsByPage($records);
        $rows = [];

        foreach (SQLitePragmaIntegrityPointerMapFreelistYield::collect($database, $integritySql) as $row) {
            $page = $row['page'];
            $entry = $databaseObject !== null && $page !== null ? self::entryOrNull($databaseObject, $page) : null;
            $root = $page !== null ? self::recordForDiagnostic($rootByPage, $page, $entry) : null;

            $rows[] = [
                'kind' => $row['kind'],
                'source' => $row['source'],
                'current_source' => $currentSource,
                'next_source' => $nextSource,
                'page' => $page,
                'pointer_map_page' => $row['pointer_map_page'],
                'pointer_map_type' => $entry?->typeName(),
                'pointer_map_parent' => $entry?->parentPageNumber,
                'index' => $root !== null && $root->type === 'index' ? $root->name : null,
                'table' => $root?->tableName,
                'root_page' => $root?->rootPage,
                'root_kind' => $root?->type,
                'message' => $row['message'],
            ];
        }

        return $rows;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{source:string,pointer_map_errors:int,freelist_errors:int,index_pointer_map_errors:int,index_roots:int},next:array{source:string,ready:bool,blocking:list<string>},rows:list<array{kind:string,source:string,current_source:string,next_source:string,page:int|null,pointer_map_page:int|null,pointer_map_type:string|null,pointer_map_parent:int|null,index:string|null,table:string|null,root_page:int|null,root_kind:string|null,message:string}>}
     */
    public static function page(
        string|SQLiteDatabase $database,
        array $records,
        string $currentSource,
        string $nextSource,
        int $offset = 0,
        int $limit = 85,
        string $integritySql = 'PRAGMA integrity_check',
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity pointer-map index current-source next85 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity pointer-map index current-source next85 limit must be positive');
        }

        $rows = self::collect($database, $records, $currentSource, $nextSource, $integritySql);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $pointerMapErrors = count(array_filter($rows, static fn (array $row): bool => $row['source'] === 'pointer_map'));
        $freelistErrors = count(array_filter($rows, static fn (array $row): bool => $row['source'] === 'freelist'));
        $indexPointerMapErrors = count(array_filter($rows, static fn (array $row): bool => $row['index'] !== null));
        $indexRoots = count(array_filter($records, static fn (SQLiteSchemaRecord $record): bool => $record->type === 'index' && $record->rootPage !== null && $record->rootPage > 0));

        $blocking = [];
        if ($indexPointerMapErrors > 0) {
            $blocking[] = 'index_pointer_map_integrity';
        }
        if ($freelistErrors > 0) {
            $blocking[] = 'freelist_integrity';
        }
        if ($pointerMapErrors > $indexPointerMapErrors) {
            $blocking[] = 'pointer_map_integrity';
        }

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => [
                'source' => $currentSource,
                'pointer_map_errors' => $pointerMapErrors,
                'freelist_errors' => $freelistErrors,
                'index_pointer_map_errors' => $indexPointerMapErrors,
                'index_roots' => $indexRoots,
            ],
            'next' => [
                'source' => $nextSource,
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
            'rows' => $pageRows,
        ];
    }

    private static function assertSourcePair(string $currentSource, string $nextSource): void
    {
        if (trim($currentSource) === '' || trim($nextSource) === '') {
            throw new InvalidArgumentException('SQLite PRAGMA integrity pointer-map index current-source next85 requires current and next source identifiers');
        }
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
     * @param list<SQLiteSchemaRecord> $records
     * @return array<int, SQLiteSchemaRecord>
     */
    private static function rootRecordsByPage(array $records): array
    {
        $roots = [];
        foreach ($records as $record) {
            if (($record->type === 'table' || $record->type === 'index') && $record->rootPage !== null && $record->rootPage > 0) {
                $roots[$record->rootPage] = $record;
            }
        }

        return $roots;
    }

    /**
     * @param array<int, SQLiteSchemaRecord> $roots
     */
    private static function recordForDiagnostic(array $roots, int $page, ?SQLitePointerMapEntry $entry): ?SQLiteSchemaRecord
    {
        if (isset($roots[$page])) {
            return $roots[$page];
        }
        if ($entry !== null && $entry->parentPageNumber > 0 && isset($roots[$entry->parentPageNumber])) {
            return $roots[$entry->parentPageNumber];
        }

        return null;
    }
}
