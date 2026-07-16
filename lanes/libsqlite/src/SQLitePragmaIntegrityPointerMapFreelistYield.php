<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityPointerMapFreelistYield
{
    /**
     * @return list<array{kind:string,source:string,page:int|null,pointer_map_page:int|null,message:string}>
     */
    public static function collect(string|SQLiteDatabase $database, string $integritySql = 'PRAGMA integrity_check'): array
    {
        $integrity = SQLitePragmaIntegrityCheck::execute($integritySql, $database);
        $rows = [];

        foreach ($integrity['errors'] as $message) {
            $kind = self::classify($message);
            if ($kind === null) {
                continue;
            }

            $page = self::messagePageNumber($message);
            $rows[] = [
                'kind' => $integrity['pragma'],
                'source' => $kind,
                'page' => $page,
                'pointer_map_page' => self::pointerMapPageNumber($database, $page),
                'message' => $message,
            ];
        }

        return $rows;
    }

    /**
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,rows:list<array{kind:string,source:string,page:int|null,pointer_map_page:int|null,message:string}>}
     */
    public static function page(string|SQLiteDatabase $database, int $offset = 0, int $limit = 48, string $integritySql = 'PRAGMA integrity_check'): array
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity pointer-map/freelist yield offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity pointer-map/freelist yield limit must be positive');
        }

        $rows = self::collect($database, $integritySql);
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
            'rows' => $pageRows,
        ];
    }

    private static function classify(string $message): ?string
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'pointer-map')) {
            return 'pointer_map';
        }
        if (str_contains($lower, 'freelist')) {
            return 'freelist';
        }

        return null;
    }

    private static function messagePageNumber(string $message): ?int
    {
        foreach ([
            '/for\s+page\s+(\d+)/i',
            '/for\s+[a-z-]+\s+page\s+(\d+)/i',
            '/page\s+(\d+)\s+pointer-map/i',
            '/marks\s+page\s+(\d+)/i',
            '/at\s+page\s+(\d+)/i',
            '/page\s+(\d+)/i',
        ] as $pattern) {
            if (preg_match($pattern, $message, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    private static function pointerMapPageNumber(string|SQLiteDatabase $database, ?int $pageNumber): ?int
    {
        if ($pageNumber === null) {
            return null;
        }
        if (is_string($database)) {
            try {
                $database = SQLiteDatabase::fromBytes($database);
            } catch (InvalidArgumentException) {
                return null;
            }
        }
        if (!$database->isAutoVacuum() || $pageNumber < 2 || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapPageFor($pageNumber);
    }
}
