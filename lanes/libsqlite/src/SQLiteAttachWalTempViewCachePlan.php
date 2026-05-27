<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachWalTempViewCachePlan
{
    /**
     * @param array<string,array{wal:SQLiteWal,database_bytes:string,database_path:string,transactions:list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}>,watch_pages:list<int>,mode?:string,reader_end_frame?:int|null}> $schemaWal
     * @param array<string,mixed> $newRow
     * @param array<string,mixed>|null $oldRow
     * @param list<string> $tables
     * @param list<string> $indexes
     * @param array<string,list<SQLiteSchemaRecord>> $nextSchemaRecords
     * @return array<string,mixed>
     */
    public static function plan(
        SQLiteAttachedSchemaCatalog $catalog,
        string $triggerName,
        array $schemaWal,
        array $newRow = [],
        ?array $oldRow = null,
        array $tables = [],
        array $indexes = [],
        array $nextSchemaRecords = [],
        string $sourceSchema = 'main',
    ): array {
        $before = $catalog->schemaCacheResolutionSnapshot($tables, $indexes, $sourceSchema);
        $triggerPlan = SQLiteAttachTempWalViewTriggerPlan::plan($catalog, $triggerName, $schemaWal, $newRow, $oldRow);

        foreach ($nextSchemaRecords as $schema => $records) {
            $catalog->replaceSchemaRecords($schema, $records);
        }

        $invalidation = $catalog->schemaCacheResolutionInvalidation($before);
        $walSchemas = $triggerPlan['wal_schemas'];
        $tempSchemas = $triggerPlan['temp_schemas'];
        $rollbackSchemas = $triggerPlan['rollback_schemas'];
        $changedSchemas = array_values(array_unique(array_merge(
            $walSchemas,
            $tempSchemas,
            $rollbackSchemas,
            array_keys($nextSchemaRecords),
        )));
        sort($changedSchemas);

        return [
            'status' => 'planned',
            'trigger' => $triggerPlan['trigger'],
            'trigger_schema' => $triggerPlan['trigger_schema'],
            'target' => $triggerPlan['target'],
            'target_schema' => $triggerPlan['target_schema'],
            'source_schema' => $sourceSchema,
            'operation_count' => $triggerPlan['operation_count'],
            'read_count' => $triggerPlan['read_count'],
            'wal_schema_count' => $triggerPlan['wal_schema_count'],
            'temp_write_count' => $triggerPlan['temp_write_count'],
            'rollback_schema_count' => $triggerPlan['rollback_schema_count'],
            'wal_schemas' => $walSchemas,
            'temp_schemas' => $tempSchemas,
            'rollback_schemas' => $rollbackSchemas,
            'changed_schemas' => $changedSchemas,
            'schema_record_updates' => array_keys($nextSchemaRecords),
            'before' => $before,
            'trigger_plan' => $triggerPlan,
            'invalidation' => $invalidation,
            'stale' => $invalidation['stale'],
            'requires_reprepare' => $invalidation['stale'],
            'changed_tables' => $invalidation['changed_tables'],
            'changed_indexes' => $invalidation['changed_indexes'],
            'unchanged_tables' => $invalidation['unchanged_tables'],
            'unchanged_indexes' => $invalidation['unchanged_indexes'],
            'next_generation' => $invalidation['after_generation'],
            'dependencies' => self::dependencies($triggerPlan['dependencies'], $invalidation['stale']),
        ];
    }

    /**
     * @param list<string> $triggerDependencies
     * @return list<string>
     */
    private static function dependencies(array $triggerDependencies, bool $stale): array
    {
        $dependencies = array_merge(
            ['sqlite-attach-wal-temp-view-cache-current-next'],
            $triggerDependencies,
        );
        if ($stale) {
            $dependencies[] = 'sqlite-schema-cache-reprepare-after-wal-trigger';
        }

        return array_values(array_unique($dependencies));
    }
}
