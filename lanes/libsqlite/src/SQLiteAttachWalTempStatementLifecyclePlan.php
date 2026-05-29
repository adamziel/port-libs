<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachWalTempStatementLifecyclePlan
{
    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, next_tables?:list<string>|null, indexes?:list<string>, next_indexes?:list<string>|null, file?:string|null, cache?:string|null}> $schemas
     * @param list<array{sql:string, active?:bool, name?:string}> $statements
     * @return array<string,mixed>
     */
    public static function plan(array $schemas, array $statements, string $sourceSchema = 'main'): array
    {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite ATTACH WAL temp statement lifecycle requires at least one statement');
        }

        $sql = [];
        foreach ($statements as $statement) {
            if (!isset($statement['sql']) || trim((string) $statement['sql']) === '') {
                throw new \InvalidArgumentException('SQLite ATTACH WAL temp statement lifecycle SQL cannot be empty');
            }
            $sql[] = (string) $statement['sql'];
        }

        $cache = SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas, $sql, $sourceSchema);
        $objectCache = SQLiteAttachTempMainWalSchemaCachePlan::currentNextObjects(
            $schemas,
            array_values(array_unique(array_merge(...array_map(
                static fn (array $statement): array => $statement['tables'],
                $cache['statements'],
            )))),
            $sourceSchema,
        );

        $statementPlans = [];
        $expired = [];
        $stable = [];
        foreach ($statements as $offset => $statement) {
            $key = (string) $offset;
            $statementCache = $cache['statements'][$key];
            $active = (bool) ($statement['active'] ?? false);
            $readOnly = (bool) $statementCache['read_only'];
            $tables = $statementCache['tables'];
            $schemaTransitions = self::schemaTransitions($objectCache['prepared_tables'], $tables);
            $requiresReprepare = (bool) $statementCache['requires_reprepare'] || self::transitionRequiresReprepare($schemaTransitions);
            $schemasRead = $statementCache['schemas'];
            $nextSchemasRead = array_values(array_unique(array_map(
                static fn (array $transition): string => $transition['next_schema'],
                $schemaTransitions,
            )));

            $action = self::action($active, $readOnly, $requiresReprepare);
            $name = isset($statement['name']) ? (string) $statement['name'] : $key;
            $plan = [
                'name' => $name,
                'sql' => (string) $statement['sql'],
                'active' => $active,
                'read_only' => $readOnly,
                'tables' => $tables,
                'current_schemas' => $schemasRead,
                'next_schemas' => $nextSchemasRead,
                'schema_transitions' => $schemaTransitions,
                'requires_reprepare' => $requiresReprepare,
                'current_step_action' => $active ? 'continue_current_snapshot' : 'not_running',
                'next_step_action' => $action,
                'sqlite_result' => $requiresReprepare && !$active ? 'SQLITE_SCHEMA' : 'SQLITE_OK',
                'retryable_after_reprepare' => $requiresReprepare && $readOnly,
            ];

            $statementPlans[$key] = $plan;
            if ($requiresReprepare) {
                $expired[] = $name;
            } else {
                $stable[] = $name;
            }
        }

        return [
            'status' => $expired === [] ? 'stable' : 'schema_changed',
            'operation' => 'attach-wal-temp-statement-lifecycle',
            'source' => $cache['source'],
            'search_order' => $cache['search_order'],
            'schema_cookies_current' => $cache['schema_cookies_current'],
            'schema_cookies_next' => $cache['schema_cookies_next'],
            'changed_schemas' => $cache['changed_schemas'],
            'object_changed_schemas' => $objectCache['object_changed_schemas'],
            'expired_statements' => $expired,
            'stable_statements' => $stable,
            'statement_count' => count($statementPlans),
            'statements' => $statementPlans,
            'requires_reprepare' => $expired !== [],
            'dependencies' => [
                'sqlite-attach-wal-temp-statement-lifecycle',
                'sqlite-schema-cookie-expire-prepared-statements',
                'sqlite-wal-ddl-statement-lifecycle',
            ],
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $preparedTables
     * @param list<string> $tables
     * @return list<array{table:string,current_schema:string,next_schema:string,current_found:bool,next_found:bool,resolution_changed:bool,requires_reprepare:bool}>
     */
    private static function schemaTransitions(array $preparedTables, array $tables): array
    {
        $transitions = [];
        foreach ($tables as $table) {
            $resolution = $preparedTables[$table];
            $transitions[] = [
                'table' => $table,
                'current_schema' => $resolution['current']['schema'],
                'next_schema' => $resolution['next']['schema'],
                'current_found' => (bool) $resolution['current']['found'],
                'next_found' => (bool) $resolution['next']['found'],
                'resolution_changed' => (bool) $resolution['resolution_changed'],
                'requires_reprepare' => (bool) $resolution['requires_reprepare'],
            ];
        }

        return $transitions;
    }

    private static function action(bool $active, bool $readOnly, bool $requiresReprepare): string
    {
        if (!$requiresReprepare) {
            return 'reuse_prepared_statement';
        }
        if ($active) {
            return 'finish_current_snapshot_then_sqlite_schema_on_reset';
        }
        if ($readOnly) {
            return 'sqlite_schema_then_reprepare_read_statement';
        }

        return 'sqlite_schema_before_write_retry';
    }

    /**
     * @param list<array{requires_reprepare:bool}> $schemaTransitions
     */
    private static function transitionRequiresReprepare(array $schemaTransitions): bool
    {
        foreach ($schemaTransitions as $transition) {
            if ($transition['requires_reprepare']) {
                return true;
            }
        }

        return false;
    }
}
