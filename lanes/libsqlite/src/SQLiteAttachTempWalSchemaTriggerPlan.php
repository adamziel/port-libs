<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachTempWalSchemaTriggerPlan
{
    /**
     * @param array<string,array{wal:SQLiteWal,database_bytes:string,database_path:string,transactions:list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}>,watch_pages:list<int>,mode?:string,reader_end_frame?:int|null}> $schemaWal
     * @param array<string,array{schema_cookie:int,wal_schema_cookie?:int|null,wal_frames?:list<array{page:int,schema_cookie?:int|null,commit?:bool}>,tables?:list<string>,indexes?:list<string>,file?:string|null,cache?:string|null}> $schemaCache
     * @param list<string> $preparedTables
     * @param array<string,mixed> $newRow
     * @param array<string,mixed>|null $oldRow
     * @return array<string,mixed>
     */
    public static function plan(
        SQLiteAttachedSchemaCatalog $catalog,
        string $triggerName,
        array $schemaWal,
        array $schemaCache,
        array $preparedTables = ['wp_options'],
        array $newRow = [],
        ?array $oldRow = null,
        string $sourceSchema = 'main'
    ): array {
        $trigger = SQLiteAttachTempWalViewTriggerPlan::plan($catalog, $triggerName, $schemaWal, $newRow, $oldRow);
        $schemaWrites = self::schemaWrites($trigger['operations']);
        $nextSchemaCache = self::schemaCacheAfterWrites($schemaCache, $schemaWrites);
        $cache = SQLiteAttachTempMainWalSchemaCachePlan::currentNext($nextSchemaCache, $preparedTables, $sourceSchema);

        return [
            'status' => 'planned',
            'trigger' => $trigger['trigger'],
            'trigger_schema' => $trigger['trigger_schema'],
            'target' => $trigger['target'],
            'target_schema' => $trigger['target_schema'],
            'trigger_plan' => $trigger,
            'schema_write_count' => count($schemaWrites),
            'schema_write_schemas' => array_values(array_unique(array_map(static fn (array $write): string => $write['schema'], $schemaWrites))),
            'schema_writes' => $schemaWrites,
            'schema_cache' => $cache,
            'reprepare_schemas' => $cache['changed_schemas'],
            'requires_reprepare' => $cache['requires_reprepare'],
            'wal_schemas' => $trigger['wal_schemas'],
            'temp_schemas' => $trigger['temp_schemas'],
            'rollback_schemas' => $trigger['rollback_schemas'],
            'dependencies' => self::dependencies($trigger, $schemaWrites),
        ];
    }

    /**
     * @param list<array<string,mixed>> $operations
     * @return list<array{operation_index:int,kind:string,schema:string,table:string,cookie_delta:int,journal:string,source:string}>
     */
    private static function schemaWrites(array $operations): array
    {
        $writes = [];
        foreach ($operations as $index => $operation) {
            if (($operation['kind'] ?? '') === 'select') {
                continue;
            }
            $table = strtolower((string) ($operation['table'] ?? ''));
            if ($table !== 'sqlite_schema' && $table !== 'sqlite_master') {
                continue;
            }
            $schema = (string) ($operation['schema'] ?? '');
            $writes[] = [
                'operation_index' => $index,
                'kind' => (string) ($operation['kind'] ?? ''),
                'schema' => $schema,
                'table' => $table,
                'cookie_delta' => 1,
                'journal' => $schema === 'temp' ? 'temp-rollback' : 'wal',
                'source' => (string) ($operation['source'] ?? ''),
            ];
        }

        return $writes;
    }

    /**
     * @param array<string,array{schema_cookie:int,wal_schema_cookie?:int|null,wal_frames?:list<array{page:int,schema_cookie?:int|null,commit?:bool}>,tables?:list<string>,indexes?:list<string>,file?:string|null,cache?:string|null}> $schemaCache
     * @param list<array{schema:string,cookie_delta:int}> $schemaWrites
     * @return array<string,array<string,mixed>>
     */
    private static function schemaCacheAfterWrites(array $schemaCache, array $schemaWrites): array
    {
        $next = $schemaCache;
        foreach ($schemaWrites as $write) {
            $schema = $write['schema'];
            if (!isset($next[$schema])) {
                throw new \InvalidArgumentException("SQLite schema trigger write targets unattached schema {$schema}");
            }
            if (!isset($next[$schema]['schema_cookie']) || !is_int($next[$schema]['schema_cookie'])) {
                throw new \InvalidArgumentException("SQLite schema {$schema} requires an integer schema cookie");
            }
            $next[$schema]['wal_schema_cookie'] = (int) ($next[$schema]['wal_schema_cookie'] ?? $next[$schema]['schema_cookie']) + $write['cookie_delta'];
            $next[$schema]['wal_frames'][] = [
                'page' => 1,
                'schema_cookie' => $next[$schema]['wal_schema_cookie'],
                'commit' => true,
            ];
        }

        return $next;
    }

    /**
     * @param array<string,mixed> $trigger
     * @param list<array<string,mixed>> $schemaWrites
     * @return list<string>
     */
    private static function dependencies(array $trigger, array $schemaWrites): array
    {
        $dependencies = ['sqlite-attach-temp-wal-schema-trigger-current-next'];
        foreach (($trigger['dependencies'] ?? []) as $dependency) {
            $dependencies[] = (string) $dependency;
        }
        if ($schemaWrites !== []) {
            $dependencies[] = 'sqlite-trigger-schema-cookie-reprepare';
        }

        return array_values(array_unique($dependencies));
    }
}
