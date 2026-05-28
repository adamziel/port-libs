<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityAutoindexYield
{
    /**
     * @return list<array<string,mixed>>
     */
    public static function collect(string|SQLiteDatabase $database, string $integritySql = 'PRAGMA integrity_check'): array
    {
        [$pragma] = self::parseIntegritySql($integritySql);
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

        [$tables, $autoindexes] = self::schemaAutoindexInventory($records);
        if (self::usesLegacyRootAudit($tables)) {
            return self::collectLegacyRootRows($database, $records);
        }

        return self::collectExpectedAutoindexRows($database, $tables, $autoindexes, $pragma);
    }

    /**
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,rows:list<array<string,mixed>>}
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

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array{0:array<string,SQLiteSchemaRecord>,1:array<string,array<string,SQLiteSchemaRecord>>}
     */
    private static function schemaAutoindexInventory(array $records): array
    {
        $tables = [];
        $autoindexes = [];
        foreach ($records as $record) {
            if ($record->type === 'table' && $record->sql !== null) {
                $tables[$record->name] = $record;
                continue;
            }
            if ($record->type === 'index' && $record->sql === null && preg_match('/^sqlite_autoindex_(.+)_(\d+)$/', $record->name) === 1) {
                $autoindexes[$record->tableName][$record->name] = $record;
            }
        }

        return [$tables, $autoindexes];
    }

    /**
     * Batch50 accepted a root-page pagination helper over synthetic autoindex
     * schemas. Batch51 adds expected-autoindex diagnostics for richer
     * multi-constraint tables; keep the root-page audit for small two-index
     * fixtures and high-cardinality synthetic cursor fixtures.
     *
     * @param array<string,SQLiteSchemaRecord> $tables
     */
    private static function usesLegacyRootAudit(array $tables): bool
    {
        foreach ($tables as $table) {
            if ($table->sql !== null && count(SQLiteCreateTable::automaticIndexColumnMetadata($table->sql)) > 2) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    private static function collectLegacyRootRows(SQLiteDatabase $database, array $records): array
    {
        $rows = [];
        $autoindexes = array_values(array_filter($records, static fn (SQLiteSchemaRecord $record): bool => self::isAutoindex($record)));
        $last = count($autoindexes) - 1;
        foreach ($autoindexes as $index => $record) {
            [$status, $message, $pageType, $pointerMap] = self::checkAutoindexRoot($database, $record);
            $rows[] = [
                'kind' => 'autoindex',
                'name' => $record->name,
                'table' => $record->tableName,
                'schema_rowid' => $record->rowId,
                'rootpage' => $record->rootPage,
                'status' => $status,
                'message' => $message,
                'previous_rootpage' => $index > 0 ? $autoindexes[$index - 1]->rootPage : null,
                'current_rootpage' => $record->rootPage,
                'next_rootpage' => $index < $last ? $autoindexes[$index + 1]->rootPage : null,
                'rootpage_page_type' => $pageType,
                'rootpage_is_largest_root' => $record->rootPage !== null ? $record->rootPage === $database->header->largestRootBtreePage : null,
                'pointer_map' => $pointerMap?->toArray(),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,SQLiteSchemaRecord> $tables
     * @param array<string,array<string,SQLiteSchemaRecord>> $autoindexes
     * @return list<array<string,mixed>>
     */
    private static function collectExpectedAutoindexRows(SQLiteDatabase $database, array $tables, array $autoindexes, string $pragma): array
    {
        $rows = [];
        foreach ($tables as $tableName => $tableRecord) {
            $expectedConstraints = SQLiteCreateTable::automaticIndexColumnMetadata($tableRecord->sql ?? '');
            $expectedNames = [];
            foreach (array_keys($expectedConstraints) as $index) {
                $expectedNames[] = 'sqlite_autoindex_' . $tableName . '_' . ($index + 1);
            }

            foreach ($expectedNames as $index => $expectedName) {
                $sequence = $index + 1;
                $record = $autoindexes[$tableName][$expectedName] ?? null;
                if ($record === null) {
                    $rows[] = self::row($pragma, 'missing_autoindex', $tableName, $expectedName, $sequence, null, null, "sqlite_schema table {$tableName} missing expected autoindex {$expectedName}");
                    continue;
                }

                if ($record->rootPage === null || $record->rootPage <= 0) {
                    $rows[] = self::row($pragma, 'autoindex_rootpage', $tableName, $expectedName, $sequence, $record->rootPage, null, "sqlite_schema autoindex {$expectedName} rootpage is not a positive btree page");
                    continue;
                }
                if ($record->rootPage > $database->pageCount()) {
                    $rows[] = self::row($pragma, 'autoindex_rootpage', $tableName, $expectedName, $sequence, $record->rootPage, null, "sqlite_schema autoindex {$expectedName} rootpage {$record->rootPage} is beyond the database image");
                    continue;
                }

                $pointerMapPage = self::pointerMapPageNumber($database, $record->rootPage);
                if ($database->isAutoVacuum() && !$database->isPointerMapPage($record->rootPage)) {
                    try {
                        $entry = $database->pointerMapEntryForPage($record->rootPage);
                    } catch (InvalidArgumentException $exception) {
                        $rows[] = self::row($pragma, 'autoindex_pointer_map', $tableName, $expectedName, $sequence, $record->rootPage, $pointerMapPage, $exception->getMessage());
                        continue;
                    }

                    if ($entry->type !== SQLitePointerMapEntry::ROOT_PAGE) {
                        $rows[] = self::row($pragma, 'autoindex_pointer_map', $tableName, $expectedName, $sequence, $record->rootPage, $pointerMapPage, "sqlite_schema autoindex {$expectedName} rootpage {$record->rootPage} pointer-map type {$entry->typeName()} does not match expected root-page");
                    } elseif ($entry->parentPageNumber !== 0) {
                        $rows[] = self::row($pragma, 'autoindex_pointer_map', $tableName, $expectedName, $sequence, $record->rootPage, $pointerMapPage, "sqlite_schema autoindex {$expectedName} rootpage {$record->rootPage} pointer-map parent {$entry->parentPageNumber} does not match expected parent 0");
                    }
                }
            }

            foreach (($autoindexes[$tableName] ?? []) as $name => $record) {
                if (in_array($name, $expectedNames, true)) {
                    continue;
                }

                $rows[] = self::row($pragma, 'unexpected_autoindex', $tableName, $name, self::autoindexSequence($name), $record->rootPage, self::pointerMapPageNumber($database, $record->rootPage), "sqlite_schema autoindex {$name} is not declared by table {$tableName}");
            }
        }

        foreach ($autoindexes as $tableName => $indexes) {
            if (isset($tables[$tableName])) {
                continue;
            }
            foreach ($indexes as $name => $record) {
                $rows[] = self::row($pragma, 'orphan_autoindex', $tableName, $name, self::autoindexSequence($name), $record->rootPage, self::pointerMapPageNumber($database, $record->rootPage), "sqlite_schema autoindex {$name} references missing table {$tableName}");
            }
        }

        return $rows;
    }

    private static function isAutoindex(SQLiteSchemaRecord $record): bool
    {
        return $record->type === 'index'
            && $record->sql === null
            && str_starts_with($record->name, 'sqlite_autoindex_');
    }

    /**
     * @return array{0:string,1:string,2:string|null,3:SQLitePointerMapEntry|null}
     */
    private static function checkAutoindexRoot(SQLiteDatabase $database, SQLiteSchemaRecord $record): array
    {
        if ($record->rootPage === null || $record->rootPage === 0) {
            return ['error', "sqlite_schema autoindex {$record->name} rootpage is empty", null, null];
        }
        if ($record->rootPage < 0) {
            return ['error', "sqlite_schema autoindex {$record->name} rootpage {$record->rootPage} is negative", null, null];
        }
        if ($record->rootPage > $database->pageCount()) {
            return ['error', "sqlite_schema autoindex {$record->name} rootpage {$record->rootPage} is beyond the database image", null, null];
        }
        if ($database->isPointerMapPage($record->rootPage)) {
            return ['error', "sqlite_schema autoindex {$record->name} rootpage {$record->rootPage} points at a pointer-map page", 'pointer-map', null];
        }

        $page = $database->page($record->rootPage);
        $flag = ord($page[0]);
        $pageType = self::pageTypeName($flag);
        if (!in_array($flag, [0x02, 0x0a], true)) {
            return ['error', sprintf('sqlite_schema autoindex %s rootpage %d is not an index b-tree page: 0x%02x', $record->name, $record->rootPage, $flag), $pageType, null];
        }

        $entry = null;
        if ($database->isAutoVacuum() && $record->rootPage !== 1) {
            try {
                $entry = $database->pointerMapEntryForPage($record->rootPage);
            } catch (InvalidArgumentException $exception) {
                return ['error', $exception->getMessage(), $pageType, null];
            }

            if ($entry->type !== SQLitePointerMapEntry::ROOT_PAGE) {
                return ['error', "sqlite_schema autoindex {$record->name} rootpage {$record->rootPage} pointer-map type {$entry->typeName()} does not match expected root-page", $pageType, $entry];
            }
            if ($entry->parentPageNumber !== 0) {
                return ['error', "sqlite_schema autoindex {$record->name} rootpage {$record->rootPage} pointer-map parent {$entry->parentPageNumber} does not match expected parent 0", $pageType, $entry];
            }
        }

        return ['ok', "sqlite_schema autoindex {$record->name} rootpage {$record->rootPage} ok", $pageType, $entry];
    }

    private static function pageTypeName(int $flag): string
    {
        return match ($flag) {
            0x02 => 'index-interior',
            0x05 => 'table-interior',
            0x0a => 'index-leaf',
            0x0d => 'table-leaf',
            default => sprintf('unknown-0x%02x', $flag),
        };
    }

    /**
     * @return array{0:string,1:int}
     */
    private static function parseIntegritySql(string $sql): array
    {
        $parsed = SQLitePragmaIntegrityCheck::execute($sql, self::minimalDatabaseBytes());

        return [$parsed['pragma'], $parsed['limit']];
    }

    private static function minimalDatabaseBytes(): string
    {
        $pageSize = 512;
        $page = str_repeat("\0", $pageSize);
        $page = substr_replace($page, "SQLite format 3\0", 0, 16);
        $page = substr_replace($page, pack('n', $pageSize), 16, 2);
        $page[18] = "\x01";
        $page[19] = "\x01";
        $page = substr_replace($page, pack('N', 1), 28, 4);
        $page = substr_replace($page, pack('N', 1), 56, 4);
        $page[100] = "\x0d";
        $page = substr_replace($page, pack('n', 0), 103, 2);
        $page = substr_replace($page, pack('n', $pageSize), 105, 2);

        return $page;
    }

    private static function pointerMapPageNumber(SQLiteDatabase $database, ?int $pageNumber): ?int
    {
        if ($pageNumber === null || !$database->isAutoVacuum() || $pageNumber < 2 || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapPageFor($pageNumber);
    }

    private static function autoindexSequence(string $name): ?int
    {
        if (preg_match('/_(\d+)$/', $name, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private static function row(string $pragma, string $source, string $table, string $index, ?int $sequence, ?int $rootPage, ?int $pointerMapPage, string $message): array
    {
        return [
            'kind' => $pragma,
            'source' => $source,
            'table' => $table,
            'index' => $index,
            'name' => $index,
            'sequence' => $sequence,
            'schema_rowid' => null,
            'rootpage' => $rootPage,
            'pointer_map_page' => $pointerMapPage,
            'status' => 'error',
            'message' => $message,
        ];
    }
}
