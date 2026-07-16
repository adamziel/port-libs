<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield
{
    /**
     * @return list<array<string,mixed>>
     */
    public static function collect(
        string|SQLiteDatabase $database,
        string $currentSource,
        string $nextSource,
        string $integritySql = 'PRAGMA integrity_check',
    ): array {
        self::assertSourcePair($currentSource, $nextSource);

        $databaseObject = is_string($database) ? self::databaseOrNull($database) : $database;
        $rows = [];

        foreach (SQLitePragmaIntegrityAutoindexYield::collect($database, $integritySql) as $row) {
            $rootPage = is_int($row['rootpage'] ?? null) ? $row['rootpage'] : null;
            $entry = $databaseObject !== null && $rootPage !== null ? self::entryOrNull($databaseObject, $rootPage) : null;
            $source = is_string($row['source'] ?? null) ? $row['source'] : (is_string($row['kind'] ?? null) ? $row['kind'] : 'database');

            $rows[] = $row + [
                'current_source' => $currentSource,
                'next_source' => $nextSource,
                'rootpage_pointer_map_type' => $entry?->typeName(),
                'rootpage_pointer_map_parent' => $entry?->parentPageNumber,
                'rootpage_pointer_map_entry_page' => $entry?->pointerMapPageNumber,
                'next_blocker' => self::blockerForSource($source),
            ];
        }

        return $rows;
    }

    /**
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{source:string,autoindex_errors:int,pointer_map_errors:int,missing_autoindexes:int,unexpected_autoindexes:int,orphan_autoindexes:int,rootpage_errors:int},next:array{source:string,ready:bool,blocking:list<string>},rows:list<array<string,mixed>>}
     */
    public static function page(
        string|SQLiteDatabase $database,
        string $currentSource,
        string $nextSource,
        int $offset = 0,
        int $limit = 89,
        string $integritySql = 'PRAGMA integrity_check',
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity autoindex pointer-map current-source next89 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity autoindex pointer-map current-source next89 limit must be positive');
        }

        $rows = self::collect($database, $currentSource, $nextSource, $integritySql);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $counts = self::counts($rows);
        $blocking = self::blocking($rows);

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
                'autoindex_errors' => count($rows),
                'pointer_map_errors' => $counts['autoindex_pointer_map'],
                'missing_autoindexes' => $counts['missing_autoindex'],
                'unexpected_autoindexes' => $counts['unexpected_autoindex'],
                'orphan_autoindexes' => $counts['orphan_autoindex'],
                'rootpage_errors' => $counts['autoindex_rootpage'],
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
            throw new InvalidArgumentException('SQLite PRAGMA integrity autoindex pointer-map current-source next89 requires current and next source identifiers');
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
     * @param list<array<string,mixed>> $rows
     * @return array<string,int>
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'autoindex_pointer_map' => 0,
            'missing_autoindex' => 0,
            'unexpected_autoindex' => 0,
            'orphan_autoindex' => 0,
            'autoindex_rootpage' => 0,
        ];
        foreach ($rows as $row) {
            $source = is_string($row['source'] ?? null) ? $row['source'] : '';
            if (array_key_exists($source, $counts)) {
                ++$counts[$source];
            }
        }

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function blocking(array $rows): array
    {
        $blocking = [];
        foreach ($rows as $row) {
            $blocker = is_string($row['next_blocker'] ?? null) ? $row['next_blocker'] : null;
            if ($blocker !== null && !in_array($blocker, $blocking, true)) {
                $blocking[] = $blocker;
            }
        }

        return $blocking;
    }

    private static function blockerForSource(string $source): string
    {
        return match ($source) {
            'autoindex_pointer_map' => 'autoindex_pointer_map_integrity',
            'autoindex_rootpage' => 'autoindex_rootpage_integrity',
            'missing_autoindex' => 'missing_autoindex_schema',
            'unexpected_autoindex' => 'unexpected_autoindex_schema',
            'orphan_autoindex' => 'orphan_autoindex_schema',
            default => 'autoindex_integrity',
        };
    }
}
