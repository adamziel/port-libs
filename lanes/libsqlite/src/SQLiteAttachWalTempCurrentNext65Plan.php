<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachWalTempCurrentNext65Plan
{
    /**
     * @param array<string,array{journal_mode?:string,page_count?:int,change_counter?:int,wal_frame_count?:int,temp?:bool,read_only?:bool,file?:string|null,tables?:list<string>}> $schemas
     * @param list<array{schema?:string,table:string,page:int,bytes?:int}> $writes
     * @return array<string,mixed>
     */
    public static function plan(array $schemas, array $writes, string $currentSchema = 'main', bool $tempStoreMemory = false): array
    {
        $normalizedSchemas = self::normalizeSchemas($schemas);
        $currentSchema = self::normalizeSchemaName($currentSchema);
        if (!isset($normalizedSchemas[$currentSchema])) {
            throw new \InvalidArgumentException("SQLite ATTACH WAL temp current-next source schema {$currentSchema} is not attached");
        }

        $searchOrder = self::searchOrder($normalizedSchemas);
        $prepared = [];
        $writesBySchema = [];
        foreach ($writes as $write) {
            $resolved = self::resolveWrite($normalizedSchemas, $searchOrder, $write);
            if ($normalizedSchemas[$resolved['schema']]['read_only']) {
                throw new \InvalidArgumentException("SQLite ATTACH WAL temp current-next cannot write read-only schema {$resolved['schema']}");
            }
            $writesBySchema[$resolved['schema']][] = $resolved;
            $prepared[] = $resolved;
        }

        $nextSchemas = $normalizedSchemas;
        $operations = [];
        $walSchemas = [];
        $rollbackSchemas = [];
        $memorySchemas = [];
        $skippedSchemas = [];
        $frameIndexes = [];

        foreach ($searchOrder as $schemaName) {
            $schemaWrites = $writesBySchema[$schemaName] ?? [];
            $schema = $normalizedSchemas[$schemaName];
            if ($schemaWrites === []) {
                $skippedSchemas[] = $schemaName;
                continue;
            }

            $dirtyPages = self::dirtyPages($schemaWrites);
            $nextSchemas[$schemaName]['page_count'] = max($schema['page_count'], max($dirtyPages));
            $nextSchemas[$schemaName]['change_counter'] = ($schema['change_counter'] + 1) & 0xffffffff;

            if ($schema['journal_mode'] === 'wal') {
                $walSchemas[] = $schemaName;
                $frameIndexes[$schemaName] = $schema['wal_frame_count'];
                foreach ($dirtyPages as $page) {
                    ++$frameIndexes[$schemaName];
                    $operations[] = [
                        'op' => 'append_wal_frame',
                        'schema' => $schemaName,
                        'frame' => $frameIndexes[$schemaName],
                        'page' => $page,
                        'commit' => $page === end($dirtyPages),
                        'reason' => $schemaName === 'temp' ? 'temp_wal_frame' : 'attached_wal_frame',
                    ];
                }
                $nextSchemas[$schemaName]['wal_frame_count'] = $schema['wal_frame_count'] + count($dirtyPages);
                continue;
            }

            if ($schemaName === 'temp' && $tempStoreMemory) {
                $memorySchemas[] = $schemaName;
                $operations[] = [
                    'op' => 'discard_temp_memory_journal_after_commit',
                    'schema' => $schemaName,
                    'pages' => $dirtyPages,
                    'reason' => 'temp_store_memory_commit',
                ];
                continue;
            }

            $rollbackSchemas[] = $schemaName;
            $operations[] = [
                'op' => $schemaName === 'temp' ? 'delete_temp_rollback_journal' : 'finalize_rollback_journal',
                'schema' => $schemaName,
                'pages' => $dirtyPages,
                'reason' => $schemaName === 'temp' ? 'temp_journal_delete_on_commit' : 'rollback_journal_commit',
            ];
        }

        return [
            'status' => $prepared === [] ? 'read_transaction_closed' : 'committed',
            'current_schema' => $currentSchema,
            'search_order' => $searchOrder,
            'current' => [
                'schemas' => $normalizedSchemas,
                'writes' => $prepared,
            ],
            'next' => [
                'schemas' => $nextSchemas,
            ],
            'wal_schemas' => $walSchemas,
            'rollback_schemas' => $rollbackSchemas,
            'memory_schemas' => $memorySchemas,
            'skipped_schemas' => $skippedSchemas,
            'write_count' => count($prepared),
            'operation_count' => count($operations),
            'operations' => $operations,
            'cache_invalidated' => false,
            'dependencies' => [
                'sqlite-attach-wal-temp-current-next65',
                'sqlite-attached-pager-transaction-routing',
            ],
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $schemas
     * @return array<string,array{journal_mode:string,page_count:int,change_counter:int,wal_frame_count:int,temp:bool,read_only:bool,file:string|null,tables:list<string>}>
     */
    private static function normalizeSchemas(array $schemas): array
    {
        if ($schemas === []) {
            throw new \InvalidArgumentException('SQLite ATTACH WAL temp current-next requires at least one schema');
        }

        $normalized = [];
        foreach ($schemas as $name => $schema) {
            $schemaName = self::normalizeSchemaName((string) $name);
            $journalMode = strtolower((string) ($schema['journal_mode'] ?? ($schemaName === 'temp' ? 'delete' : 'wal')));
            if (!in_array($journalMode, ['wal', 'delete', 'truncate', 'persist', 'memory', 'off'], true)) {
                throw new \InvalidArgumentException("SQLite ATTACH WAL temp current-next unsupported journal mode {$journalMode}");
            }
            $pageCount = $schema['page_count'] ?? 1;
            $changeCounter = $schema['change_counter'] ?? 0;
            $walFrameCount = $schema['wal_frame_count'] ?? 0;
            if (!is_int($pageCount) || $pageCount < 1) {
                throw new \InvalidArgumentException('SQLite ATTACH WAL temp current-next page counts must be positive integers');
            }
            if (!is_int($changeCounter) || $changeCounter < 0) {
                throw new \InvalidArgumentException('SQLite ATTACH WAL temp current-next change counters must be non-negative integers');
            }
            if (!is_int($walFrameCount) || $walFrameCount < 0) {
                throw new \InvalidArgumentException('SQLite ATTACH WAL temp current-next WAL frame counts must be non-negative integers');
            }

            $tables = [];
            foreach (($schema['tables'] ?? []) as $table) {
                $tableName = trim((string) $table);
                if ($tableName === '') {
                    throw new \InvalidArgumentException('SQLite ATTACH WAL temp current-next table names must be non-empty');
                }
                $tables[] = strtolower($tableName);
            }

            $normalized[$schemaName] = [
                'journal_mode' => $journalMode,
                'page_count' => $pageCount,
                'change_counter' => $changeCounter,
                'wal_frame_count' => $walFrameCount,
                'temp' => (bool) ($schema['temp'] ?? ($schemaName === 'temp')),
                'read_only' => (bool) ($schema['read_only'] ?? false),
                'file' => isset($schema['file']) ? (string) $schema['file'] : null,
                'tables' => $tables,
            ];
        }

        foreach (['main', 'temp'] as $required) {
            if (!isset($normalized[$required])) {
                throw new \InvalidArgumentException("SQLite ATTACH WAL temp current-next requires {$required} schema");
            }
        }

        return $normalized;
    }

    /**
     * @param array<string,array<string,mixed>> $schemas
     * @return list<string>
     */
    private static function searchOrder(array $schemas): array
    {
        $attached = array_values(array_filter(
            array_keys($schemas),
            static fn (string $schema): bool => $schema !== 'main' && $schema !== 'temp',
        ));
        sort($attached, SORT_STRING);

        return array_merge(['temp', 'main'], $attached);
    }

    /**
     * @param array<string,array{tables:list<string>}> $schemas
     * @param list<string> $searchOrder
     * @param array{schema?:string,table:string,page:int,bytes?:int} $write
     * @return array{schema:string,table:string,page:int,bytes:int}
     */
    private static function resolveWrite(array $schemas, array $searchOrder, array $write): array
    {
        $table = trim((string) ($write['table'] ?? ''));
        if ($table === '') {
            throw new \InvalidArgumentException('SQLite ATTACH WAL temp current-next writes require a table name');
        }
        $page = $write['page'] ?? null;
        if (!is_int($page) || $page < 1) {
            throw new \InvalidArgumentException('SQLite ATTACH WAL temp current-next writes require one-based page numbers');
        }
        $bytes = $write['bytes'] ?? 0;
        if (!is_int($bytes) || $bytes < 0) {
            throw new \InvalidArgumentException('SQLite ATTACH WAL temp current-next write bytes must be non-negative');
        }

        $schemaName = isset($write['schema']) ? self::normalizeSchemaName((string) $write['schema']) : null;
        if ($schemaName !== null) {
            if (!isset($schemas[$schemaName])) {
                throw new \InvalidArgumentException("SQLite ATTACH WAL temp current-next schema {$schemaName} is not attached");
            }

            return ['schema' => $schemaName, 'table' => $table, 'page' => $page, 'bytes' => $bytes];
        }

        $tableKey = strtolower($table);
        foreach ($searchOrder as $candidate) {
            if (in_array($tableKey, $schemas[$candidate]['tables'], true)) {
                return ['schema' => $candidate, 'table' => $table, 'page' => $page, 'bytes' => $bytes];
            }
        }

        return ['schema' => 'main', 'table' => $table, 'page' => $page, 'bytes' => $bytes];
    }

    /**
     * @param list<array{page:int}> $writes
     * @return list<int>
     */
    private static function dirtyPages(array $writes): array
    {
        $pages = [];
        foreach ($writes as $write) {
            $pages[$write['page']] = true;
        }
        $pageNumbers = array_keys($pages);
        sort($pageNumbers, SORT_NUMERIC);

        return $pageNumbers;
    }

    private static function normalizeSchemaName(string $schemaName): string
    {
        $name = strtolower(trim($schemaName, " \t\r\n`\"[]"));
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite ATTACH WAL temp current-next schema name cannot be empty');
        }

        return $name;
    }
}
