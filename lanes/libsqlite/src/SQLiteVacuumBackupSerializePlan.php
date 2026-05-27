<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVacuumBackupSerializePlan
{
    /**
     * @return array{status:string,schema:string,bytes:string,page_size:int,page_count:int,database_size_pages:int,dependencies:list<string>}
     */
    public static function serialize(SQLiteDatabase $database, string $schema = 'main'): array
    {
        self::assertSchemaName($schema);

        return [
            'status' => 'ok',
            'schema' => $schema,
            'bytes' => self::databaseBytes($database),
            'page_size' => $database->header->pageSize,
            'page_count' => $database->pageCount(),
            'database_size_pages' => $database->header->databaseSizePages,
            'dependencies' => ['sqlite3-serialize', 'sqlite-database-image'],
        ];
    }

    /**
     * @return array{status:string,schema:string,database:SQLiteDatabase,bytes:string,page_size:int,page_count:int,readonly:bool,dependencies:list<string>}
     */
    public static function deserialize(string $bytes, string $schema = 'main', bool $readOnly = false): array
    {
        self::assertSchemaName($schema);
        $database = SQLiteDatabase::fromBytes($bytes);
        $expectedBytes = $database->header->pageSize * max(1, $database->header->databaseSizePages);
        if (strlen($bytes) < $expectedBytes) {
            throw new \InvalidArgumentException('SQLite deserialize requires a complete database image for the header page count');
        }

        return [
            'status' => 'ok',
            'schema' => $schema,
            'database' => $database,
            'bytes' => self::databaseBytes($database),
            'page_size' => $database->header->pageSize,
            'page_count' => $database->pageCount(),
            'readonly' => $readOnly,
            'dependencies' => ['sqlite3-deserialize', 'sqlite-database-image'],
        ];
    }

    /**
     * @return array{status:string,source_schema:string,target_schema:string,page_size:int,page_count:int,pages_per_step:int,steps:int,remaining:int,done:bool,pages:list<array{page:int,offset:int,bytes:int,data:string}>,bytes:string,dependencies:list<string>}
     */
    public static function backup(SQLiteDatabase $source, string $targetSchema = 'main', string $sourceSchema = 'main', int $pagesPerStep = -1): array
    {
        self::assertSchemaName($sourceSchema);
        self::assertSchemaName($targetSchema);
        if ($pagesPerStep === 0 || $pagesPerStep < -1) {
            throw new \InvalidArgumentException('SQLite backup pages-per-step must be positive or -1 for all remaining pages');
        }

        $pageCount = $source->pageCount();
        $limit = $pagesPerStep === -1 ? $pageCount : min($pagesPerStep, $pageCount);
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $limit; $pageNumber++) {
            $pages[] = [
                'page' => $pageNumber,
                'offset' => ($pageNumber - 1) * $source->header->pageSize,
                'bytes' => $source->header->pageSize,
                'data' => $source->page($pageNumber),
            ];
        }

        return [
            'status' => $limit === $pageCount ? 'done' : 'in_progress',
            'source_schema' => $sourceSchema,
            'target_schema' => $targetSchema,
            'page_size' => $source->header->pageSize,
            'page_count' => $pageCount,
            'pages_per_step' => $pagesPerStep,
            'steps' => $limit,
            'remaining' => $pageCount - $limit,
            'done' => $limit === $pageCount,
            'pages' => $pages,
            'bytes' => implode('', array_column($pages, 'data')),
            'dependencies' => ['sqlite3-backup-api', 'sqlite-database-page-copy'],
        ];
    }

    /**
     * @return array{status:string,target_path:string,page_size:int,page_count:int,source_freelist_pages:int,bytes:string,operations:list<array<string,mixed>>,dependencies:list<string>,source_auto_vacuum:string,target_auto_vacuum:string,incremental_vacuum:int,largest_root_page:int,pointer_map_page_numbers:list<int>,pointer_map_entry_page_numbers:list<int>,vacuum_rewrite_operations:list<array<string,mixed>>}
     */
    public static function vacuumInto(
        SQLiteDatabase $source,
        string $targetPath,
        bool $overwrite = false,
        int|string|null $pageSize = null,
        int|string|null $autoVacuum = null,
    ): array
    {
        if ($targetPath === '') {
            throw new \InvalidArgumentException('SQLite VACUUM INTO requires a target path');
        }
        if (!$overwrite && is_file($targetPath)) {
            throw new \InvalidArgumentException('SQLite VACUUM INTO target already exists');
        }

        $rewrite = SQLiteVacuumPageSizeAutoVacuumPlan::plan($source, $pageSize, $autoVacuum);
        $bytes = $rewrite['bytes'];
        $target = SQLiteDatabase::fromBytes($bytes);
        $pointerMapPageNumbers = self::pointerMapPageNumbers($target);
        $pointerMapEntryPageNumbers = [];
        if ($target->isAutoVacuum()) {
            for ($pageNumber = 2; $pageNumber <= $target->pageCount(); $pageNumber++) {
                if (!$target->isPointerMapPage($pageNumber)) {
                    $pointerMapEntryPageNumbers[] = $pageNumber;
                }
            }
        }

        return [
            'status' => 'ready',
            'target_path' => $targetPath,
            'page_size' => $rewrite['target_page_size'],
            'page_count' => $rewrite['target_page_count'],
            'source_freelist_pages' => $source->header->freelistPageCount,
            'bytes' => $bytes,
            'operations' => [
                [
                    'op' => 'write',
                    'path' => $targetPath,
                    'offset' => 0,
                    'bytes' => strlen($bytes),
                    'reason' => 'write_vacuum_into_database_image',
                ],
                [
                    'op' => 'sync',
                    'path' => $targetPath,
                    'durable' => true,
                    'reason' => 'sync_vacuum_into_database_image',
                ],
                [
                    'op' => 'sync_directory',
                    'path' => dirname($targetPath),
                    'durable' => true,
                    'reason' => 'persist_vacuum_into_target',
                ],
                [
                    'op' => 'vacuum_rewrite',
                    'source_page_size' => $rewrite['source_page_size'],
                    'target_page_size' => $rewrite['target_page_size'],
                    'source_auto_vacuum' => $rewrite['source_auto_vacuum'],
                    'target_auto_vacuum' => $rewrite['target_auto_vacuum'],
                    'pointer_map_pages' => $pointerMapPageNumbers,
                    'pointer_map_entry_pages' => $pointerMapEntryPageNumbers,
                    'reason' => 'materialize_vacuum_destination_header_and_pointer_map_layout',
                ],
            ],
            'dependencies' => ['sqlite-vacuum-into', 'sqlite-database-image', 'sqlite-auto-vacuum-pointer-map-layout'],
            'source_auto_vacuum' => $rewrite['source_auto_vacuum'],
            'target_auto_vacuum' => $rewrite['target_auto_vacuum'],
            'incremental_vacuum' => $rewrite['incremental_vacuum'],
            'largest_root_page' => $rewrite['largest_root_page'],
            'pointer_map_page_numbers' => $pointerMapPageNumbers,
            'pointer_map_entry_page_numbers' => $pointerMapEntryPageNumbers,
            'vacuum_rewrite_operations' => $rewrite['operations'],
        ];
    }

    private static function databaseBytes(SQLiteDatabase $database): string
    {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
            $pages[] = $database->page($pageNumber);
        }

        return implode('', $pages);
    }

    /**
     * @return list<int>
     */
    private static function pointerMapPageNumbers(SQLiteDatabase $database): array
    {
        if (!$database->isAutoVacuum()) {
            return [];
        }

        $pages = [];
        for ($pageNumber = 2; $pageNumber <= $database->pageCount(); $pageNumber++) {
            if ($database->isPointerMapPage($pageNumber)) {
                $pages[] = $pageNumber;
            }
        }

        return $pages;
    }

    private static function assertSchemaName(string $schema): void
    {
        if ($schema === '') {
            throw new \InvalidArgumentException('SQLite image operation requires a schema name');
        }
        if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $schema)) {
            throw new \InvalidArgumentException('SQLite image operation schema name is malformed');
        }
    }
}
