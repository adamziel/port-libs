<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachTempWalViewTriggerPlan
{
    /**
     * @param array<string,array{wal:SQLiteWal,database_bytes:string,database_path:string,transactions:list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}>,watch_pages:list<int>,mode?:string,reader_end_frame?:int|null}> $schemaWal
     * @param array<string,mixed> $newRow
     * @param array<string,mixed>|null $oldRow
     * @return array{status:string,trigger:string,trigger_schema:string,target:string,target_schema:string,operation_count:int,read_count:int,wal_schema_count:int,temp_write_count:int,rollback_schema_count:int,wal_schemas:list<string>,temp_schemas:list<string>,rollback_schemas:list<string>,writes_by_schema:array<string,int>,operations:list<array<string,mixed>>,wal_plans:array<string,array<string,mixed>>,temp_operations:list<array<string,mixed>>,rollback_operations:list<array<string,mixed>>,current_reader_sources:array<string,list<string>>,next_reader_sources:array<string,list<string>>,next_reader_frame_indexes:array<string,list<int|null>>,dependencies:list<string>}
     */
    public static function plan(
        SQLiteAttachedSchemaCatalog $catalog,
        string $triggerName,
        array $schemaWal,
        array $newRow = [],
        ?array $oldRow = null
    ): array {
        $yield = SQLiteAttachTempViewTriggerYieldPlan::yield($catalog, $triggerName, $newRow, $oldRow);
        $operations = $yield['operations'];
        $writesBySchema = $yield['writesBySchema'];

        $walPlans = [];
        $tempOperations = [];
        $rollbackOperations = [];
        foreach ($operations as $operation) {
            if (($operation['kind'] ?? '') === 'select') {
                continue;
            }

            $schema = (string) ($operation['schema'] ?? '');
            if ($schema === 'temp') {
                $tempOperations[] = $operation + ['journal' => 'temp-rollback'];
                continue;
            }

            if (!array_key_exists($schema, $schemaWal)) {
                $rollbackOperations[] = $operation + ['journal' => 'rollback'];
            }
        }

        foreach (array_keys($writesBySchema) as $schema) {
            if ($schema === 'temp' || !array_key_exists($schema, $schemaWal)) {
                continue;
            }

            $entry = $schemaWal[$schema];
            self::validateWalEntry($schema, $entry);
            $walPlans[$schema] = SQLiteWalAppendPlan::checkpointAppendCurrentNext(
                $entry['wal'],
                $entry['database_bytes'],
                $entry['database_path'],
                $entry['transactions'],
                $entry['watch_pages'],
                $entry['mode'] ?? 'restart',
                $entry['reader_end_frame'] ?? null,
            );
        }
        ksort($walPlans);

        return [
            'status' => 'planned',
            'trigger' => $yield['trigger'],
            'trigger_schema' => $yield['triggerSchema'],
            'target' => $yield['target'],
            'target_schema' => $yield['targetSchema'],
            'operation_count' => $yield['operationCount'],
            'read_count' => $yield['readCount'],
            'wal_schema_count' => count($walPlans),
            'temp_write_count' => count($tempOperations),
            'rollback_schema_count' => count(array_unique(array_map(static fn (array $operation): string => (string) $operation['schema'], $rollbackOperations))),
            'wal_schemas' => array_keys($walPlans),
            'temp_schemas' => array_values(array_unique(array_map(static fn (array $operation): string => (string) $operation['schema'], $tempOperations))),
            'rollback_schemas' => array_values(array_unique(array_map(static fn (array $operation): string => (string) $operation['schema'], $rollbackOperations))),
            'writes_by_schema' => $writesBySchema,
            'operations' => $operations,
            'wal_plans' => $walPlans,
            'temp_operations' => $tempOperations,
            'rollback_operations' => $rollbackOperations,
            'current_reader_sources' => self::walColumn($walPlans, 'current_reader_sources'),
            'next_reader_sources' => self::walColumn($walPlans, 'next_reader_sources'),
            'next_reader_frame_indexes' => self::walColumn($walPlans, 'next_reader_frame_indexes'),
            'dependencies' => self::dependencies($walPlans, $tempOperations, $rollbackOperations),
        ];
    }

    /**
     * @param array<string,mixed> $entry
     */
    private static function validateWalEntry(string $schema, array $entry): void
    {
        if (!($entry['wal'] ?? null) instanceof SQLiteWal) {
            throw new \InvalidArgumentException("SQLite attach/temp WAL trigger plan requires a WAL for schema {$schema}");
        }
        if (!is_string($entry['database_bytes'] ?? null)) {
            throw new \InvalidArgumentException("SQLite attach/temp WAL trigger plan requires database bytes for schema {$schema}");
        }
        if (!is_string($entry['database_path'] ?? null) || $entry['database_path'] === '') {
            throw new \InvalidArgumentException("SQLite attach/temp WAL trigger plan requires a database path for schema {$schema}");
        }
        if (!is_array($entry['transactions'] ?? null) || $entry['transactions'] === []) {
            throw new \InvalidArgumentException("SQLite attach/temp WAL trigger plan requires transactions for schema {$schema}");
        }
        if (!is_array($entry['watch_pages'] ?? null) || $entry['watch_pages'] === []) {
            throw new \InvalidArgumentException("SQLite attach/temp WAL trigger plan requires watch pages for schema {$schema}");
        }
    }

    /**
     * @param array<string,array<string,mixed>> $walPlans
     * @return array<string,mixed>
     */
    private static function walColumn(array $walPlans, string $column): array
    {
        $values = [];
        foreach ($walPlans as $schema => $plan) {
            $values[$schema] = $plan[$column] ?? [];
        }

        return $values;
    }

    /**
     * @param array<string,array<string,mixed>> $walPlans
     * @param list<array<string,mixed>> $tempOperations
     * @param list<array<string,mixed>> $rollbackOperations
     * @return list<string>
     */
    private static function dependencies(array $walPlans, array $tempOperations, array $rollbackOperations): array
    {
        $dependencies = ['sqlite-attach-temp-wal-view-trigger-current-next'];
        foreach ($walPlans as $plan) {
            foreach (($plan['dependencies'] ?? []) as $dependency) {
                $dependencies[] = (string) $dependency;
            }
        }
        if ($tempOperations !== []) {
            $dependencies[] = 'sqlite-temp-trigger-rollback-journal-routing';
        }
        if ($rollbackOperations !== []) {
            $dependencies[] = 'sqlite-attached-trigger-rollback-journal-routing';
        }

        return array_values(array_unique($dependencies));
    }
}
