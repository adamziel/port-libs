<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityAutoindexYield
{
    /**
     * @return list<array{kind:string,name:string|null,table:string|null,schema_rowid:int|null,rootpage:int|null,status:string,message:string}>
     */
    public static function collect(string|SQLiteDatabase $database, string $integritySql = 'PRAGMA integrity_check'): array
    {
        SQLitePragmaIntegrityCheck::execute($integritySql, $database);
        if (is_string($database)) {
            try {
                $database = SQLiteDatabase::fromBytes($database);
            } catch (InvalidArgumentException $exception) {
                return [[
                    'kind' => 'database',
                    'name' => null,
                    'table' => null,
                    'schema_rowid' => null,
                    'rootpage' => null,
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                ]];
            }
        }

        try {
            $records = $database->schemaRecords();
        } catch (InvalidArgumentException $exception) {
            return [[
                'kind' => 'sqlite_schema',
                'name' => null,
                'table' => null,
                'schema_rowid' => null,
                'rootpage' => null,
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]];
        }

        $rows = [];
        foreach ($records as $record) {
            if (!self::isAutoindex($record)) {
                continue;
            }

            [$status, $message] = self::checkAutoindexRoot($database, $record);
            $rows[] = [
                'kind' => 'autoindex',
                'name' => $record->name,
                'table' => $record->tableName,
                'schema_rowid' => $record->rowId,
                'rootpage' => $record->rootPage,
                'status' => $status,
                'message' => $message,
            ];
        }

        return $rows;
    }

    /**
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,rows:list<array{kind:string,name:string|null,table:string|null,schema_rowid:int|null,rootpage:int|null,status:string,message:string}>}
     */
    public static function page(string|SQLiteDatabase $database, int $offset = 0, int $limit = 50, string $integritySql = 'PRAGMA integrity_check'): array
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity autoindex yield offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity autoindex yield limit must be positive');
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

    private static function isAutoindex(SQLiteSchemaRecord $record): bool
    {
        return $record->type === 'index'
            && $record->sql === null
            && str_starts_with($record->name, 'sqlite_autoindex_');
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function checkAutoindexRoot(SQLiteDatabase $database, SQLiteSchemaRecord $record): array
    {
        if ($record->rootPage === null || $record->rootPage === 0) {
            return ['error', "sqlite_schema autoindex {$record->name} rootpage is empty"];
        }
        if ($record->rootPage < 0) {
            return ['error', "sqlite_schema autoindex {$record->name} rootpage {$record->rootPage} is negative"];
        }
        if ($record->rootPage > $database->pageCount()) {
            return ['error', "sqlite_schema autoindex {$record->name} rootpage {$record->rootPage} is beyond the database image"];
        }
        if ($database->isPointerMapPage($record->rootPage)) {
            return ['error', "sqlite_schema autoindex {$record->name} rootpage {$record->rootPage} points at a pointer-map page"];
        }

        $page = $database->page($record->rootPage);
        $flag = ord($page[0]);
        if (!in_array($flag, [0x02, 0x0a], true)) {
            return ['error', sprintf('sqlite_schema autoindex %s rootpage %d is not an index b-tree page: 0x%02x', $record->name, $record->rootPage, $flag)];
        }

        if ($database->isAutoVacuum() && $record->rootPage !== 1) {
            try {
                $entry = $database->pointerMapEntryForPage($record->rootPage);
            } catch (InvalidArgumentException $exception) {
                return ['error', $exception->getMessage()];
            }

            if ($entry->type !== SQLitePointerMapEntry::ROOT_PAGE) {
                return ['error', "sqlite_schema autoindex {$record->name} rootpage {$record->rootPage} pointer-map type {$entry->typeName()} does not match expected root-page"];
            }
            if ($entry->parentPageNumber !== 0) {
                return ['error', "sqlite_schema autoindex {$record->name} rootpage {$record->rootPage} pointer-map parent {$entry->parentPageNumber} does not match expected parent 0"];
            }
        }

        return ['ok', "sqlite_schema autoindex {$record->name} rootpage {$record->rootPage} ok"];
    }
}
