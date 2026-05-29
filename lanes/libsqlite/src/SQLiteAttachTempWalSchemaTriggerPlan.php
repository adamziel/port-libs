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
     * @param list<array{name:string, source?:string, active?:bool}> $preparedTriggers
     * @param array<string,list<SQLiteSchemaRecord>> $nextSchemaRecords
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>}> $schemaStates
     * @return array<string,mixed>
     */
    public static function triggerCacheRepreparePlan(
        SQLiteAttachedSchemaCatalog $catalog,
        array $preparedTriggers,
        array $nextSchemaRecords = [],
        array $schemaStates = [],
    ): array {
        if ($preparedTriggers === []) {
            throw new \InvalidArgumentException('SQLite attach temp schema trigger-cache reprepare requires prepared triggers');
        }

        $triggerNames = [];
        $sources = [];
        $active = [];
        $before = [];
        foreach ($preparedTriggers as $index => $trigger) {
            if (!isset($trigger['name']) || trim((string) $trigger['name']) === '') {
                throw new \InvalidArgumentException("SQLite prepared trigger {$index} requires a name");
            }

            $name = trim((string) $trigger['name']);
            $source = self::normalizeName((string) ($trigger['source'] ?? 'main'));
            $catalog->schemaCacheSnapshot($source);
            $triggerNames[] = $name;
            $sources[$name] = $source;
            $active[$name] = (bool) ($trigger['active'] ?? false);
            $before[$name] = self::triggerCacheEntry($catalog, $name);
        }

        foreach ($nextSchemaRecords as $schema => $records) {
            $catalog->replaceSchemaRecords((string) $schema, $records);
        }

        $triggerPlans = [];
        $reprepareTriggers = [];
        $stableTriggers = [];
        $currentSnapshotTriggers = [];
        $resetSchemaTriggers = [];
        $nextStepSchemaTriggers = [];
        $sourceSchemas = [];
        $primarySource = $sources[$triggerNames[0]];

        foreach ($triggerNames as $name) {
            $after = self::triggerCacheEntry($catalog, $name);
            $requiresReprepare = $before[$name] !== $after;
            $sourceSchemas[$name] = $sources[$name];

            if (!$requiresReprepare) {
                $action = 'reuse_prepared_trigger';
                $sqliteResult = 'SQLITE_OK';
                $stableTriggers[] = $name;
            } elseif ($active[$name]) {
                $action = 'finish_current_source_then_sqlite_schema_on_reset';
                $sqliteResult = 'SQLITE_OK';
                $currentSnapshotTriggers[] = $name;
                $resetSchemaTriggers[] = $name;
                $reprepareTriggers[] = $name;
            } else {
                $action = 'sqlite_schema_on_next_step';
                $sqliteResult = 'SQLITE_SCHEMA';
                $nextStepSchemaTriggers[] = $name;
                $reprepareTriggers[] = $name;
            }

            $triggerPlans[$name] = [
                'source_schema' => $sources[$name],
                'active' => $active[$name],
                'before' => $before[$name],
                'after' => $after,
                'trigger_changed' => $requiresReprepare,
                'target_changed' => ($before[$name]['target'] ?? null) !== ($after['target'] ?? null),
                'body_dependencies_changed' => ($before[$name]['body_dependencies'] ?? []) !== ($after['body_dependencies'] ?? []),
                'requires_reprepare' => $requiresReprepare,
                'current_step_result' => $sqliteResult,
                'next_step_action' => $action,
                'current_source_kept_until_reset' => $active[$name] && $requiresReprepare,
            ];
        }

        $schemaCookies = self::schemaCookies($schemaStates);
        $changedSchemas = array_values(array_unique(array_merge(
            array_keys($nextSchemaRecords),
            $schemaCookies['changed_schemas'],
        )));
        sort($changedSchemas);

        return [
            'status' => $reprepareTriggers === [] ? 'trigger_cache_stable' : 'trigger_cache_expired',
            'operation' => 'attach-temp-schema-trigger-cache-reprepare',
            'source_schema' => $primarySource,
            'source_schemas' => $sourceSchemas,
            'trigger_count' => count($triggerNames),
            'active_trigger_count' => count(array_filter($active)),
            'triggers' => $triggerPlans,
            'reprepare_triggers' => $reprepareTriggers,
            'stable_triggers' => $stableTriggers,
            'active_current_snapshot_triggers' => $currentSnapshotTriggers,
            'reset_schema_triggers' => $resetSchemaTriggers,
            'next_step_schema_triggers' => $nextStepSchemaTriggers,
            'requires_reprepare' => $reprepareTriggers !== [],
            'schema_record_updates' => array_keys($nextSchemaRecords),
            'changed_schemas' => $changedSchemas,
            'schema_cookies_current' => $schemaCookies['current'],
            'schema_cookies_next' => $schemaCookies['next'],
            'wal_schema_cookie_sources' => $schemaCookies['wal_sources'],
            'dependencies' => [
                'sqlite-attach-temp-schema-trigger-cache-reprepare',
                'sqlite-prepared-trigger-current-source-reset',
                'sqlite-wal-page-one-schema-cookie',
            ],
        ];
    }

    /**
     * @param list<array{name:string, source?:string, active?:bool}> $preparedTriggers
     * @param array<string,list<SQLiteSchemaRecord>> $nextSchemaRecords
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>}> $schemaStates
     * @return array<string,mixed>
     */
    public static function walTriggerCookieCachePlan(
        SQLiteAttachedSchemaCatalog $catalog,
        array $preparedTriggers,
        array $nextSchemaRecords = [],
        array $schemaStates = [],
    ): array {
        if ($preparedTriggers === []) {
            throw new \InvalidArgumentException('SQLite attach WAL temp trigger-cache cookie-cache requires prepared triggers');
        }

        $schemaCookies = self::schemaCookies($schemaStates);
        $cookieChanged = array_fill_keys($schemaCookies['changed_schemas'], true);
        $triggerNames = [];
        $sources = [];
        $active = [];
        $before = [];

        foreach ($preparedTriggers as $index => $trigger) {
            if (!isset($trigger['name']) || trim((string) $trigger['name']) === '') {
                throw new \InvalidArgumentException("SQLite prepared trigger {$index} requires a name");
            }

            $name = trim((string) $trigger['name']);
            $source = self::normalizeName((string) ($trigger['source'] ?? 'main'));
            $catalog->schemaCacheSnapshot($source);
            $triggerNames[] = $name;
            $sources[$name] = $source;
            $active[$name] = (bool) ($trigger['active'] ?? false);
            $before[$name] = self::triggerCacheEntry($catalog, $name);
        }

        foreach ($nextSchemaRecords as $schema => $records) {
            $catalog->replaceSchemaRecords((string) $schema, $records);
        }

        $triggerPlans = [];
        $reprepareTriggers = [];
        $stableTriggers = [];
        $currentSnapshotTriggers = [];
        $resetSchemaTriggers = [];
        $nextStepSchemaTriggers = [];
        $cookieExpiredTriggers = [];
        $recordExpiredTriggers = [];
        $sourceSchemas = [];
        $primarySource = $sources[$triggerNames[0]];

        foreach ($triggerNames as $name) {
            $after = self::triggerCacheEntry($catalog, $name);
            $sourceSchemas[$name] = $sources[$name];
            $recordChanged = $before[$name] !== $after;
            $dependencySchemas = self::triggerDependencySchemas($sources[$name], $before[$name]);
            $cookieSchemas = array_values(array_filter(
                $dependencySchemas,
                static fn (string $schema): bool => isset($cookieChanged[$schema]),
            ));
            $cookieChangedForTrigger = $cookieSchemas !== [];
            $requiresReprepare = $recordChanged || $cookieChangedForTrigger;

            if (!$requiresReprepare) {
                $action = 'reuse_prepared_trigger_current_and_next_source';
                $sqliteResult = 'SQLITE_OK';
                $stableTriggers[] = $name;
            } elseif ($active[$name]) {
                $action = 'finish_current_source_then_sqlite_schema_on_reset';
                $sqliteResult = 'SQLITE_OK';
                $currentSnapshotTriggers[] = $name;
                $resetSchemaTriggers[] = $name;
                $reprepareTriggers[] = $name;
            } else {
                $action = 'sqlite_schema_before_next_trigger_step';
                $sqliteResult = 'SQLITE_SCHEMA';
                $nextStepSchemaTriggers[] = $name;
                $reprepareTriggers[] = $name;
            }

            if ($cookieChangedForTrigger) {
                $cookieExpiredTriggers[] = $name;
            }
            if ($recordChanged) {
                $recordExpiredTriggers[] = $name;
            }

            $triggerPlans[$name] = [
                'source_schema' => $sources[$name],
                'active' => $active[$name],
                'before' => $before[$name],
                'after' => $after,
                'dependency_schemas' => $dependencySchemas,
                'cookie_changed_schemas' => $cookieSchemas,
                'trigger_changed' => ($before[$name]['sql'] ?? null) !== ($after['sql'] ?? null),
                'target_changed' => ($before[$name]['target'] ?? null) !== ($after['target'] ?? null),
                'body_dependencies_changed' => ($before[$name]['body_dependencies'] ?? []) !== ($after['body_dependencies'] ?? []),
                'schema_cookie_changed' => $cookieChangedForTrigger,
                'record_changed' => $recordChanged,
                'requires_reprepare' => $requiresReprepare,
                'current_step_result' => $sqliteResult,
                'next_step_action' => $action,
                'current_source_kept_until_reset' => $active[$name] && $requiresReprepare,
                'next_source_requires_reprepare' => $requiresReprepare,
            ];
        }

        $changedSchemas = array_values(array_unique(array_merge(
            array_keys($nextSchemaRecords),
            $schemaCookies['changed_schemas'],
        )));
        sort($changedSchemas);

        return [
            'status' => $reprepareTriggers === [] ? 'trigger_cache_stable' : 'trigger_cache_expired',
            'operation' => 'attach-wal-temp-trigger-cookie-cache',
            'source_schema' => $primarySource,
            'source_schemas' => $sourceSchemas,
            'trigger_count' => count($triggerNames),
            'active_trigger_count' => count(array_filter($active)),
            'triggers' => $triggerPlans,
            'reprepare_triggers' => $reprepareTriggers,
            'stable_triggers' => $stableTriggers,
            'active_current_snapshot_triggers' => $currentSnapshotTriggers,
            'reset_schema_triggers' => $resetSchemaTriggers,
            'next_step_schema_triggers' => $nextStepSchemaTriggers,
            'cookie_expired_triggers' => $cookieExpiredTriggers,
            'record_expired_triggers' => $recordExpiredTriggers,
            'requires_reprepare' => $reprepareTriggers !== [],
            'schema_record_updates' => array_keys($nextSchemaRecords),
            'changed_schemas' => $changedSchemas,
            'schema_cookies_current' => $schemaCookies['current'],
            'schema_cookies_next' => $schemaCookies['next'],
            'wal_schema_cookie_sources' => $schemaCookies['wal_sources'],
            'dependencies' => [
                'sqlite-attach-wal-temp-trigger-cookie-cache',
                'sqlite-prepared-trigger-current-source-reset',
                'sqlite-wal-page-one-schema-cookie',
                'sqlite-temp-schema-cookie-trigger-expiry',
            ],
        ];
    }

    /**
     * @param list<array{name:string, active?:bool, statement?:string}> $preparedTriggers
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>}> $schemaStates
     * @return array<string,mixed>
     */
    public static function triggerSourceRepreparePlan(
        SQLiteAttachedSchemaCatalog $current,
        SQLiteAttachedSchemaCatalog $next,
        array $preparedTriggers,
        array $schemaStates = [],
    ): array {
        if ($preparedTriggers === []) {
            throw new \InvalidArgumentException('SQLite attach temp WAL schema trigger source-reprepare requires prepared triggers');
        }

        $plans = [];
        $reprepare = [];
        $stable = [];
        $activeCurrent = [];
        $resetSchema = [];
        $nextStepSchema = [];
        $invalidatedSchemas = [];
        $walSchemas = [];
        $tempSchemas = [];
        $attachedSchemas = [];

        foreach ($preparedTriggers as $index => $trigger) {
            if (!isset($trigger['name']) || trim((string) $trigger['name']) === '') {
                throw new \InvalidArgumentException("SQLite prepared trigger {$index} requires a name");
            }

            $name = trim((string) $trigger['name']);
            $active = (bool) ($trigger['active'] ?? false);
            $source = SQLiteAttachTempViewTriggerResolution::currentNextSourcePlan($current, $next, $name);
            $requiresReprepare = (bool) $source['requiresReprepare'];

            if ($requiresReprepare) {
                $reprepare[] = $name;
                $invalidatedSchemas = array_merge($invalidatedSchemas, $source['invalidatedSources']);
                $walSchemas = array_merge($walSchemas, $source['walSchemas']);
                $tempSchemas = array_merge($tempSchemas, $source['tempSchemas']);
                $attachedSchemas = array_merge($attachedSchemas, $source['attachedSchemas']);
                if ($active) {
                    $action = 'finish_current_source_then_sqlite_schema_on_reset';
                    $sqliteResult = 'SQLITE_OK';
                    $activeCurrent[] = $name;
                    $resetSchema[] = $name;
                } else {
                    $action = 'sqlite_schema_on_next_step';
                    $sqliteResult = 'SQLITE_SCHEMA';
                    $nextStepSchema[] = $name;
                }
            } else {
                $stable[] = $name;
                $action = 'reuse_prepared_trigger';
                $sqliteResult = 'SQLITE_OK';
            }

            $plans[$name] = [
                'active' => $active,
                'statement' => (string) ($trigger['statement'] ?? ''),
                'current' => $source['current'],
                'next' => $source['next'],
                'changed' => $source['changed'],
                'changed_fields' => $source['changedFields'],
                'requires_reprepare' => $requiresReprepare,
                'current_step_result' => $sqliteResult,
                'next_step_action' => $action,
                'current_source_kept_until_reset' => $active && $requiresReprepare,
                'invalidated_sources' => $source['invalidatedSources'],
                'wal_schemas' => $source['walSchemas'],
                'temp_schemas' => $source['tempSchemas'],
                'attached_schemas' => $source['attachedSchemas'],
            ];
        }

        $schemaCookies = self::schemaCookies($schemaStates);
        $changedSchemas = self::orderedSchemaNames(array_merge(
            $invalidatedSchemas,
            $schemaCookies['changed_schemas'],
        ));

        return [
            'status' => $reprepare === [] ? 'trigger_current_source_stable' : 'trigger_current_source_expired',
            'operation' => 'attach-temp-wal-schema-trigger-source-reprepare',
            'trigger_count' => count($preparedTriggers),
            'active_trigger_count' => count(array_filter($preparedTriggers, static fn (array $trigger): bool => (bool) ($trigger['active'] ?? false))),
            'triggers' => $plans,
            'reprepare_triggers' => $reprepare,
            'stable_triggers' => $stable,
            'active_current_snapshot_triggers' => $activeCurrent,
            'reset_schema_triggers' => $resetSchema,
            'next_step_schema_triggers' => $nextStepSchema,
            'requires_reprepare' => $reprepare !== [],
            'changed_schemas' => $changedSchemas,
            'wal_schemas' => self::orderedSchemaNames(array_merge($walSchemas, $schemaCookies['wal_sources'])),
            'temp_schemas' => self::orderedSchemaNames($tempSchemas),
            'attached_schemas' => self::orderedSchemaNames($attachedSchemas),
            'schema_cookies_current' => $schemaCookies['current'],
            'schema_cookies_next' => $schemaCookies['next'],
            'wal_schema_cookie_sources' => $schemaCookies['wal_sources'],
            'dependencies' => [
                'sqlite-attach-temp-wal-schema-trigger-source-reprepare',
                'sqlite-prepared-trigger-current-source-reset',
                'sqlite-temp-trigger-shadow-resolution',
                'sqlite-wal-page-one-schema-cookie',
            ],
        ];
    }

    /**
     * @param list<array{name:string, active?:bool, statement?:string}> $preparedTriggers
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>}> $schemaStates
     * @return array<string,mixed>
     */
    public static function triggerDependencyCookiePlan(
        SQLiteAttachedSchemaCatalog $current,
        SQLiteAttachedSchemaCatalog $next,
        array $preparedTriggers,
        array $schemaStates = [],
    ): array {
        $plan = self::triggerSourceRepreparePlan($current, $next, $preparedTriggers, $schemaStates);
        $schemaCookies = self::schemaCookies($schemaStates);
        $cookieChanged = array_fill_keys($schemaCookies['changed_schemas'], true);

        $reprepare = [];
        $stable = [];
        $activeCurrent = [];
        $resetSchema = [];
        $nextStepSchema = [];
        $invalidatedSchemas = [];
        $walSchemas = [];
        $tempSchemas = [];
        $attachedSchemas = [];
        $dependencyMovedTriggers = [];
        $cookieExpiredTriggers = [];

        foreach ($preparedTriggers as $trigger) {
            $name = trim((string) $trigger['name']);
            $active = (bool) ($trigger['active'] ?? false);
            $currentSource = $plan['triggers'][$name]['current'];
            $nextSource = $plan['triggers'][$name]['next'];
            $currentDependencies = array_column(self::resolvedBodyDependencies($current, $currentSource), 'resolved_schema');
            $nextDependencies = array_column(self::resolvedBodyDependencies($next, $nextSource), 'resolved_schema');
            $dependencySchemas = self::orderedSchemaNames(array_merge(
                [(string) $currentSource['triggerSchema'], (string) $currentSource['targetSchema']],
                [(string) $nextSource['triggerSchema'], (string) $nextSource['targetSchema']],
                $currentDependencies,
                $nextDependencies,
            ));
            $dependencyMoved = $currentDependencies !== $nextDependencies;
            $cookieSchemas = array_values(array_filter(
                $dependencySchemas,
                static fn (string $schema): bool => isset($cookieChanged[$schema]),
            ));
            $cookieExpired = $cookieSchemas !== [];
            $requiresReprepare = (bool) $plan['triggers'][$name]['requires_reprepare'] || $dependencyMoved || $cookieExpired;

            if ($requiresReprepare) {
                $reprepare[] = $name;
                $invalidatedSchemas = array_merge($invalidatedSchemas, $dependencySchemas);
                $walSchemas = array_merge($walSchemas, array_values(array_filter($dependencySchemas, static fn (string $schema): bool => $schema !== 'temp')));
                $tempSchemas = array_merge($tempSchemas, array_values(array_filter($dependencySchemas, static fn (string $schema): bool => $schema === 'temp')));
                $attachedSchemas = array_merge($attachedSchemas, array_values(array_filter($dependencySchemas, static fn (string $schema): bool => !in_array($schema, ['main', 'temp'], true))));
                if ($active) {
                    $action = 'finish_current_source_then_sqlite_schema_on_reset';
                    $sqliteResult = 'SQLITE_OK';
                    $activeCurrent[] = $name;
                    $resetSchema[] = $name;
                } else {
                    $action = 'sqlite_schema_on_next_step';
                    $sqliteResult = 'SQLITE_SCHEMA';
                    $nextStepSchema[] = $name;
                }
            } else {
                $stable[] = $name;
                $action = 'reuse_prepared_trigger';
                $sqliteResult = 'SQLITE_OK';
            }

            if ($dependencyMoved) {
                $dependencyMovedTriggers[] = $name;
            }
            if ($cookieExpired) {
                $cookieExpiredTriggers[] = $name;
            }

            $plan['triggers'][$name]['current_body_dependency_schemas'] = $currentDependencies;
            $plan['triggers'][$name]['next_body_dependency_schemas'] = $nextDependencies;
            $plan['triggers'][$name]['dependency_schemas'] = $dependencySchemas;
            $plan['triggers'][$name]['dependency_moved'] = $dependencyMoved;
            $plan['triggers'][$name]['cookie_changed_schemas'] = $cookieSchemas;
            $plan['triggers'][$name]['schema_cookie_changed'] = $cookieExpired;
            $plan['triggers'][$name]['requires_reprepare'] = $requiresReprepare;
            $plan['triggers'][$name]['current_step_result'] = $sqliteResult;
            $plan['triggers'][$name]['next_step_action'] = $action;
            $plan['triggers'][$name]['current_source_kept_until_reset'] = $active && $requiresReprepare;
            $plan['triggers'][$name]['invalidated_sources'] = $requiresReprepare ? $dependencySchemas : [];
            $plan['triggers'][$name]['wal_schemas'] = self::orderedSchemaNames(array_values(array_filter($dependencySchemas, static fn (string $schema): bool => $schema !== 'temp')));
            $plan['triggers'][$name]['temp_schemas'] = self::orderedSchemaNames(array_values(array_filter($dependencySchemas, static fn (string $schema): bool => $schema === 'temp')));
            $plan['triggers'][$name]['attached_schemas'] = self::orderedSchemaNames(array_values(array_filter($dependencySchemas, static fn (string $schema): bool => !in_array($schema, ['main', 'temp'], true))));
        }

        $plan['status'] = $reprepare === [] ? 'trigger_current_source_stable' : 'trigger_current_source_expired';
        $plan['operation'] = 'attach-temp-wal-schema-trigger-dependency-cookie';
        $plan['reprepare_triggers'] = $reprepare;
        $plan['stable_triggers'] = $stable;
        $plan['active_current_snapshot_triggers'] = $activeCurrent;
        $plan['reset_schema_triggers'] = $resetSchema;
        $plan['next_step_schema_triggers'] = $nextStepSchema;
        $plan['requires_reprepare'] = $reprepare !== [];
        $plan['changed_schemas'] = self::orderedSchemaNames(array_merge($invalidatedSchemas, $schemaCookies['changed_schemas']));
        $plan['wal_schemas'] = self::orderedSchemaNames(array_merge($walSchemas, $schemaCookies['wal_sources']));
        $plan['temp_schemas'] = self::orderedSchemaNames($tempSchemas);
        $plan['attached_schemas'] = self::orderedSchemaNames($attachedSchemas);
        $plan['dependency_moved_triggers'] = $dependencyMovedTriggers;
        $plan['cookie_expired_triggers'] = $cookieExpiredTriggers;
        $plan['dependencies'] = [
            'sqlite-attach-temp-wal-schema-trigger-dependency-cookie',
            'sqlite-prepared-trigger-current-source-reset',
            'sqlite-temp-trigger-body-dependency-resolution',
            'sqlite-wal-page-one-schema-cookie',
        ];

        return $plan;
    }

    /**
     * @param list<array{name:string, active?:bool, statement?:string}> $preparedTriggers
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>}> $schemaStates
     * @return array<string,mixed>
     */
    public static function triggerBodyDependencyRepreparePlan(
        SQLiteAttachedSchemaCatalog $current,
        SQLiteAttachedSchemaCatalog $next,
        array $preparedTriggers,
        array $schemaStates = [],
    ): array {
        $base = self::triggerSourceRepreparePlan($current, $next, $preparedTriggers, $schemaStates);
        $dependencyExpired = [];
        $dependencyStable = [];
        $bodyDependencySchemas = [];

        foreach ($preparedTriggers as $trigger) {
            $name = trim((string) $trigger['name']);
            $active = (bool) ($trigger['active'] ?? false);
            $currentPlan = $base['triggers'][$name]['current'];
            $nextPlan = $base['triggers'][$name]['next'];
            $currentDependencies = self::resolvedBodyDependencies($current, $currentPlan);
            $nextDependencies = self::resolvedBodyDependencies($next, $nextPlan);
            $changed = $currentDependencies !== $nextDependencies;

            $schemas = self::orderedSchemaNames(array_merge(
                array_column($currentDependencies, 'resolved_schema'),
                array_column($nextDependencies, 'resolved_schema'),
            ));
            $bodyDependencySchemas[$name] = $schemas;

            $base['triggers'][$name]['body_dependency_resolution'] = [
                'current' => $currentDependencies,
                'next' => $nextDependencies,
                'changed' => $changed,
                'schemas' => $schemas,
            ];

            if (!$changed) {
                $dependencyStable[] = $name;
                continue;
            }

            $dependencyExpired[] = $name;
            foreach ($schemas as $schema) {
                $base['changed_schemas'][] = $schema;
                if ($schema === 'temp') {
                    $base['temp_schemas'][] = $schema;
                } else {
                    $base['wal_schemas'][] = $schema;
                    if ($schema !== 'main') {
                        $base['attached_schemas'][] = $schema;
                    }
                }
            }
            if (!in_array($name, $base['reprepare_triggers'], true)) {
                $base['reprepare_triggers'][] = $name;
            }
            $base['triggers'][$name]['changed'] = true;
            $base['triggers'][$name]['requires_reprepare'] = true;
            $base['triggers'][$name]['next_source_requires_reprepare'] = true;
            if (!in_array('bodyDependenciesResolved', $base['triggers'][$name]['changed_fields'], true)) {
                $base['triggers'][$name]['changed_fields'][] = 'bodyDependenciesResolved';
            }

            if ($active) {
                if (!in_array($name, $base['active_current_snapshot_triggers'], true)) {
                    $base['active_current_snapshot_triggers'][] = $name;
                }
                if (!in_array($name, $base['reset_schema_triggers'], true)) {
                    $base['reset_schema_triggers'][] = $name;
                }
                $base['triggers'][$name]['current_step_result'] = 'SQLITE_OK';
                $base['triggers'][$name]['next_step_action'] = 'finish_current_source_then_sqlite_schema_on_reset';
                $base['triggers'][$name]['current_source_kept_until_reset'] = true;
            } else {
                if (!in_array($name, $base['next_step_schema_triggers'], true)) {
                    $base['next_step_schema_triggers'][] = $name;
                }
                $base['triggers'][$name]['current_step_result'] = 'SQLITE_SCHEMA';
                $base['triggers'][$name]['next_step_action'] = 'sqlite_schema_before_next_trigger_body_step';
            }
        }

        $base['status'] = $base['reprepare_triggers'] === [] ? 'trigger_body_dependency_stable' : 'trigger_body_dependency_expired';
        $base['operation'] = 'attach-temp-wal-schema-trigger-body-dependency-reprepare';
        $base['requires_reprepare'] = $base['reprepare_triggers'] !== [];
        $base['stable_triggers'] = array_values(array_filter(
            $base['stable_triggers'],
            static fn (string $name): bool => !in_array($name, $dependencyExpired, true),
        ));
        foreach ($dependencyStable as $name) {
            if (!in_array($name, $base['stable_triggers'], true) && !in_array($name, $base['reprepare_triggers'], true)) {
                $base['stable_triggers'][] = $name;
            }
        }
        $base['changed_schemas'] = self::orderedSchemaNames($base['changed_schemas']);
        $base['wal_schemas'] = self::orderedSchemaNames($base['wal_schemas']);
        $base['temp_schemas'] = self::orderedSchemaNames($base['temp_schemas']);
        $base['attached_schemas'] = self::orderedSchemaNames($base['attached_schemas']);
        $base['body_dependency_expired_triggers'] = $dependencyExpired;
        $base['body_dependency_stable_triggers'] = $dependencyStable;
        $base['body_dependency_schemas'] = $bodyDependencySchemas;
        array_unshift($base['dependencies'], 'sqlite-attach-temp-wal-schema-trigger-body-dependency-reprepare');
        $base['dependencies'] = array_values(array_unique($base['dependencies']));

        return $base;
    }

    /**
     * @param list<array{name:string, active?:bool, statement?:string}> $preparedTriggers
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>}> $schemaStates
     * @return array<string,mixed>
     */
    public static function triggerViewCacheRepreparePlan(
        SQLiteAttachedSchemaCatalog $current,
        SQLiteAttachedSchemaCatalog $next,
        array $preparedTriggers,
        array $schemaStates = [],
    ): array {
        if ($preparedTriggers === []) {
            throw new \InvalidArgumentException('SQLite attach temp WAL trigger view-cache reprepare requires prepared triggers');
        }

        $base = self::triggerSourceRepreparePlan($current, $next, $preparedTriggers, $schemaStates);
        $viewExpired = [];
        $viewStable = [];
        $viewPlans = [];
        $reprepare = $base['reprepare_triggers'];
        $activeCurrent = $base['active_current_snapshot_triggers'];
        $resetSchema = $base['reset_schema_triggers'];
        $nextStepSchema = $base['next_step_schema_triggers'];
        $changedSchemas = $base['changed_schemas'];
        $walSchemas = $base['wal_schemas'];
        $tempSchemas = $base['temp_schemas'];
        $attachedSchemas = $base['attached_schemas'];

        foreach ($preparedTriggers as $trigger) {
            $name = trim((string) $trigger['name']);
            $active = (bool) ($trigger['active'] ?? false);
            $currentPlan = $base['triggers'][$name]['current'];
            $nextPlan = $base['triggers'][$name]['next'];
            if (($currentPlan['targetType'] ?? '') !== 'view' && ($nextPlan['targetType'] ?? '') !== 'view') {
                continue;
            }

            $currentView = self::viewCacheSnapshot($current, (string) $currentPlan['targetSchema'], (string) $currentPlan['target']);
            $nextView = self::viewCacheSnapshot($next, (string) $nextPlan['targetSchema'], (string) $nextPlan['target']);
            $changedFields = [];
            foreach (['schema', 'name', 'rootpage', 'sql', 'columns', 'dependencies'] as $field) {
                if (($currentView[$field] ?? null) !== ($nextView[$field] ?? null)) {
                    $changedFields[] = $field;
                }
            }

            $requires = $changedFields !== [];
            if ($requires) {
                $viewExpired[] = $name;
                if (!in_array($name, $reprepare, true)) {
                    $reprepare[] = $name;
                }
                if ($active) {
                    if (!in_array($name, $activeCurrent, true)) {
                        $activeCurrent[] = $name;
                    }
                    if (!in_array($name, $resetSchema, true)) {
                        $resetSchema[] = $name;
                    }
                    $base['triggers'][$name]['current_step_result'] = 'SQLITE_OK';
                    $base['triggers'][$name]['next_step_action'] = 'finish_current_view_source_then_sqlite_schema_on_reset';
                    $base['triggers'][$name]['current_source_kept_until_reset'] = true;
                } else {
                    if (!in_array($name, $nextStepSchema, true)) {
                        $nextStepSchema[] = $name;
                    }
                    $base['triggers'][$name]['current_step_result'] = 'SQLITE_SCHEMA';
                    $base['triggers'][$name]['next_step_action'] = 'sqlite_schema_before_next_view_trigger_step';
                }
                $base['triggers'][$name]['changed'] = true;
                $base['triggers'][$name]['requires_reprepare'] = true;
                foreach (array_merge([(string) $currentView['schema'], (string) $nextView['schema']], $currentView['dependency_schemas'], $nextView['dependency_schemas']) as $schema) {
                    $changedSchemas[] = $schema;
                    if ($schema === 'temp') {
                        $tempSchemas[] = $schema;
                    } elseif ($schema !== '') {
                        $walSchemas[] = $schema;
                        if ($schema !== 'main') {
                            $attachedSchemas[] = $schema;
                        }
                    }
                }
            } else {
                $viewStable[] = $name;
            }

            $viewPlans[$name] = [
                'current' => $currentView,
                'next' => $nextView,
                'changed_fields' => $changedFields,
                'requires_reprepare' => $requires,
                'current_source_kept_until_reset' => $active && $requires,
            ];
            $base['triggers'][$name]['view_cache'] = $viewPlans[$name];
        }

        $base['status'] = $reprepare === [] ? 'trigger_view_cache_stable' : 'trigger_view_cache_expired';
        $base['operation'] = 'attach-temp-wal-trigger-view-cache-reprepare';
        $base['reprepare_triggers'] = array_values($reprepare);
        $base['stable_triggers'] = array_values(array_filter(
            $base['stable_triggers'],
            static fn (string $name): bool => !in_array($name, $viewExpired, true),
        ));
        foreach ($viewStable as $name) {
            if (!in_array($name, $base['stable_triggers'], true) && !in_array($name, $reprepare, true)) {
                $base['stable_triggers'][] = $name;
            }
        }
        $base['active_current_snapshot_triggers'] = array_values($activeCurrent);
        $base['reset_schema_triggers'] = array_values($resetSchema);
        $base['next_step_schema_triggers'] = array_values($nextStepSchema);
        $base['requires_reprepare'] = $reprepare !== [];
        $base['changed_schemas'] = self::orderedSchemaNames($changedSchemas);
        $base['wal_schemas'] = self::orderedSchemaNames($walSchemas);
        $base['temp_schemas'] = self::orderedSchemaNames($tempSchemas);
        $base['attached_schemas'] = self::orderedSchemaNames($attachedSchemas);
        $base['view_cache_triggers'] = array_keys($viewPlans);
        $base['view_cache_expired_triggers'] = $viewExpired;
        $base['view_cache_stable_triggers'] = $viewStable;
        $base['view_caches'] = $viewPlans;
        array_unshift($base['dependencies'], 'sqlite-attach-temp-wal-trigger-view-cache-reprepare');
        $base['dependencies'] = array_values(array_unique($base['dependencies']));

        return $base;
    }

    /**
     * @param list<array{name:string, active?:bool, statement?:string}> $preparedTriggers
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>}> $schemaStates
     * @return array<string,mixed>
     */
    public static function triggerViewDependencyInvalidationPlan(
        SQLiteAttachedSchemaCatalog $current,
        SQLiteAttachedSchemaCatalog $next,
        array $preparedTriggers,
        array $schemaStates = [],
    ): array {
        $base = self::triggerViewCacheRepreparePlan($current, $next, $preparedTriggers, $schemaStates);
        $dependencyExpired = [];
        $dependencyStable = [];
        $viewDependencySchemas = [];

        foreach ($preparedTriggers as $trigger) {
            $name = trim((string) $trigger['name']);
            if (!isset($base['view_caches'][$name])) {
                continue;
            }

            $active = (bool) ($trigger['active'] ?? false);
            $currentView = $base['view_caches'][$name]['current'];
            $nextView = $base['view_caches'][$name]['next'];
            $currentDependencies = self::resolvedViewDependencies($current, $currentView);
            $nextDependencies = self::resolvedViewDependencies($next, $nextView);
            $changed = $currentDependencies !== $nextDependencies;
            $schemas = self::orderedSchemaNames(array_merge(
                array_column($currentDependencies, 'resolved_schema'),
                array_column($nextDependencies, 'resolved_schema'),
            ));
            $viewDependencySchemas[$name] = $schemas;
            $base['view_caches'][$name]['dependency_resolution'] = [
                'current' => $currentDependencies,
                'next' => $nextDependencies,
                'changed' => $changed,
                'schemas' => $schemas,
            ];
            $base['triggers'][$name]['view_dependency_resolution'] = $base['view_caches'][$name]['dependency_resolution'];

            if (!$changed) {
                $dependencyStable[] = $name;
                continue;
            }

            $dependencyExpired[] = $name;
            if (!in_array($name, $base['reprepare_triggers'], true)) {
                $base['reprepare_triggers'][] = $name;
            }
            $base['triggers'][$name]['changed'] = true;
            $base['triggers'][$name]['requires_reprepare'] = true;
            $base['view_caches'][$name]['requires_reprepare'] = true;
            if (!in_array('viewDependenciesResolved', $base['triggers'][$name]['changed_fields'], true)) {
                $base['triggers'][$name]['changed_fields'][] = 'viewDependenciesResolved';
            }
            if (!in_array('dependency_resolution', $base['view_caches'][$name]['changed_fields'], true)) {
                $base['view_caches'][$name]['changed_fields'][] = 'dependency_resolution';
            }
            foreach ($schemas as $schema) {
                $base['changed_schemas'][] = $schema;
                if ($schema === 'temp') {
                    $base['temp_schemas'][] = $schema;
                } else {
                    $base['wal_schemas'][] = $schema;
                    if ($schema !== 'main') {
                        $base['attached_schemas'][] = $schema;
                    }
                }
            }
            if ($active) {
                if (!in_array($name, $base['active_current_snapshot_triggers'], true)) {
                    $base['active_current_snapshot_triggers'][] = $name;
                }
                if (!in_array($name, $base['reset_schema_triggers'], true)) {
                    $base['reset_schema_triggers'][] = $name;
                }
                $base['triggers'][$name]['current_step_result'] = 'SQLITE_OK';
                $base['triggers'][$name]['next_step_action'] = 'finish_current_view_dependency_source_then_sqlite_schema_on_reset';
                $base['triggers'][$name]['current_source_kept_until_reset'] = true;
                $base['view_caches'][$name]['current_source_kept_until_reset'] = true;
            } else {
                if (!in_array($name, $base['next_step_schema_triggers'], true)) {
                    $base['next_step_schema_triggers'][] = $name;
                }
                $base['triggers'][$name]['current_step_result'] = 'SQLITE_SCHEMA';
                $base['triggers'][$name]['next_step_action'] = 'sqlite_schema_before_next_view_dependency_step';
            }
        }

        $base['status'] = $base['reprepare_triggers'] === [] ? 'trigger_view_dependency_stable' : 'trigger_view_dependency_expired';
        $base['operation'] = 'attach-temp-trigger-view-dependency-invalidation';
        $base['requires_reprepare'] = $base['reprepare_triggers'] !== [];
        $base['stable_triggers'] = array_values(array_filter(
            $base['stable_triggers'],
            static fn (string $name): bool => !in_array($name, $dependencyExpired, true),
        ));
        foreach ($dependencyStable as $name) {
            if (!in_array($name, $base['stable_triggers'], true) && !in_array($name, $base['reprepare_triggers'], true)) {
                $base['stable_triggers'][] = $name;
            }
        }
        $base['changed_schemas'] = self::orderedSchemaNames($base['changed_schemas']);
        $base['wal_schemas'] = self::orderedSchemaNames($base['wal_schemas']);
        $base['temp_schemas'] = self::orderedSchemaNames($base['temp_schemas']);
        $base['attached_schemas'] = self::orderedSchemaNames($base['attached_schemas']);
        $base['view_dependency_expired_triggers'] = $dependencyExpired;
        $base['view_dependency_stable_triggers'] = $dependencyStable;
        $base['view_dependency_schemas'] = $viewDependencySchemas;
        array_unshift($base['dependencies'], 'sqlite-attach-temp-trigger-view-dependency-invalidation');
        $base['dependencies'] = array_values(array_unique($base['dependencies']));

        return $base;
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
     * @return array{schema:string,name:string,type:string,rootpage:int|null,sql:string|null,columns:list<string>,dependencies:list<array{schema:?string,name:string}>,dependency_schemas:list<string>,status:string}
     */
    private static function viewCacheSnapshot(SQLiteAttachedSchemaCatalog $catalog, string $schema, string $name): array
    {
        $record = null;
        foreach ($catalog->schemaRecords($schema) as $candidate) {
            if (strtolower($candidate->type) === 'view' && strcasecmp($candidate->name, $name) === 0) {
                $record = $candidate;
                break;
            }
        }
        if ($record === null) {
            throw new \InvalidArgumentException("SQLite trigger view-cache target does not resolve: {$schema}.{$name}");
        }

        $dependencies = self::viewDependencies((string) $record->sql);
        $dependencySchemas = [];
        foreach ($dependencies as $dependency) {
            $dependencySchemas[] = $dependency['schema'] ?? $schema;
        }
        $dependencySchemas = self::orderedSchemaNames($dependencySchemas);

        return [
            'schema' => $schema,
            'name' => $record->name,
            'type' => strtolower($record->type),
            'rootpage' => $record->rootPage,
            'sql' => $record->sql,
            'columns' => self::viewColumnsForSql((string) $record->sql),
            'dependencies' => $dependencies,
            'dependency_schemas' => $dependencySchemas,
            'status' => $record->sql === null || trim($record->sql) === '' ? 'unresolved' : 'resolved',
        ];
    }

    /**
     * @return list<string>
     */
    private static function viewColumnsForSql(string $sql): array
    {
        if (preg_match('/\bcreate\s+(?:temp(?:orary)?\s+)?view\s+(?:if\s+not\s+exists\s+)?(?:["`\[]?[\w]+["`\]]?\s*\.\s*)?["`\[]?[\w]+["`\]]?\s*\((?<columns>[^)]*)\)/i', $sql, $matches)) {
            return array_values(array_filter(array_map(static fn (string $column): string => trim($column, " \t\r\n`\"[]"), explode(',', $matches['columns']))));
        }
        if (!preg_match('/\bas\s+select\s+(?<select>.*?)\s+\bfrom\b/is', $sql, $matches)) {
            return [];
        }

        $columns = [];
        foreach (self::splitSqlCommaList($matches['select']) as $expression) {
            $expression = trim($expression);
            if (preg_match('/\bas\s+(["`\[]?[\w ]+["`\]]?)$/i', $expression, $alias)) {
                $columns[] = trim($alias[1], " \t\r\n`\"[]");
                continue;
            }
            if (preg_match('/(?:^|\.)(["`\[]?[\w]+["`\]]?)$/', $expression, $column)) {
                $columns[] = trim($column[1], " \t\r\n`\"[]");
            }
        }

        return $columns;
    }

    /**
     * @return list<array{schema:?string,name:string}>
     */
    private static function viewDependencies(string $sql): array
    {
        preg_match_all('/\b(?:from|join)\s+(?:(["`\[]?[\w]+["`\]]?)\s*\.\s*)?(["`\[]?[\w]+["`\]]?)/i', $sql, $matches, PREG_SET_ORDER);
        $dependencies = [];
        foreach ($matches as $match) {
            $schema = isset($match[1]) && $match[1] !== '' ? strtolower(trim($match[1], " \t\r\n`\"[]")) : null;
            $name = trim($match[2], " \t\r\n`\"[]");
            if ($name === '') {
                continue;
            }
            $key = ($schema ?? '') . '.' . strtolower($name);
            $dependencies[$key] = ['schema' => $schema, 'name' => $name];
        }

        return array_values($dependencies);
    }

    /**
     * @param array{schema:string,dependencies:list<array{schema:?string,name:string}>} $view
     * @return list<array{schema:?string,name:string,resolved_schema:string,resolved_type:?string,resolved_rootpage:int|null,found:bool}>
     */
    private static function resolvedViewDependencies(SQLiteAttachedSchemaCatalog $catalog, array $view): array
    {
        $dependencies = [];
        $viewSchema = self::normalizeName((string) $view['schema']);
        foreach (($view['dependencies'] ?? []) as $dependency) {
            $schema = $dependency['schema'] ?? null;
            $name = self::normalizeName((string) $dependency['name']);
            $schemas = $schema !== null && $schema !== ''
                ? [self::normalizeName((string) $schema)]
                : ($viewSchema === 'temp' ? $catalog->searchOrder() : [$viewSchema]);
            $resolvedSchema = $schema !== null && $schema !== '' ? self::normalizeName((string) $schema) : $viewSchema;
            $resolvedType = null;
            $resolvedRoot = null;
            $found = false;

            foreach ($schemas as $candidateSchema) {
                foreach ($catalog->schemaRecords($candidateSchema) as $record) {
                    if (!in_array(strtolower($record->type), ['table', 'view'], true) || strcasecmp($record->name, $name) !== 0) {
                        continue;
                    }
                    $resolvedSchema = $candidateSchema;
                    $resolvedType = strtolower($record->type);
                    $resolvedRoot = $record->rootPage;
                    $found = true;
                    break 2;
                }
            }

            $dependencies[] = [
                'schema' => $schema === null || $schema === '' ? null : self::normalizeName((string) $schema),
                'name' => $name,
                'resolved_schema' => $resolvedSchema,
                'resolved_type' => $resolvedType,
                'resolved_rootpage' => $resolvedRoot,
                'found' => $found,
            ];
        }

        return $dependencies;
    }

    /**
     * @return list<string>
     */
    private static function splitSqlCommaList(string $value): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $quote = null;
        $length = strlen($value);

        for ($i = 0; $i < $length; ++$i) {
            $char = $value[$i];
            if ($quote !== null) {
                $current .= $char;
                if ($char === $quote) {
                    if ($i + 1 < $length && $value[$i + 1] === $quote) {
                        $current .= $value[++$i];
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === '"' || $char === '\'' || $char === '`') {
                $quote = $char;
                $current .= $char;
                continue;
            }
            if ($char === '(') {
                ++$depth;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $parts[] = $current;

        return $parts;
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

    /**
     * @return array{schema:string,name:string,target:array<string,mixed>,temporary:bool,instead_of:bool,referenced_new:list<string>,referenced_old:list<string>,missing_new:list<string>,missing_old:list<string>,body_dependencies:list<array{schema:?string,name:string}>,status:string,sql:string|null}
     */
    private static function triggerCacheEntry(SQLiteAttachedSchemaCatalog $catalog, string $triggerName): array
    {
        $resolved = SQLiteAttachTempViewTriggerResolution::resolve($catalog, $triggerName);
        $trigger = SQLiteAttachTempViewTriggerResolution::resolveTrigger($catalog, $triggerName);

        return [
            'schema' => $resolved['triggerSchema'],
            'name' => $resolved['trigger'],
            'target' => [
                'schema' => $resolved['targetSchema'],
                'name' => $resolved['target'],
                'type' => $resolved['targetType'],
                'columns' => $resolved['columns'],
            ],
            'temporary' => $resolved['triggerTemporary'],
            'instead_of' => $resolved['insteadOf'],
            'referenced_new' => $resolved['referencedNew'],
            'referenced_old' => $resolved['referencedOld'],
            'missing_new' => $resolved['missingNew'],
            'missing_old' => $resolved['missingOld'],
            'body_dependencies' => $resolved['bodyDependencies'],
            'status' => $resolved['status'],
            'sql' => $trigger['record']->sql,
        ];
    }

    /**
     * @param array{schema:string,target:array{schema:string},body_dependencies:list<array{schema:?string,name:string}>} $entry
     * @return list<string>
     */
    private static function triggerDependencySchemas(string $source, array $entry): array
    {
        $schemas = [$source, (string) $entry['schema'], (string) $entry['target']['schema']];
        foreach ($entry['body_dependencies'] as $dependency) {
            $schema = $dependency['schema'] ?? null;
            $schemas[] = $schema === null || $schema === '' ? $source : (string) $schema;
        }

        $normalized = [];
        foreach ($schemas as $schema) {
            $name = self::normalizeName($schema);
            if (!in_array($name, $normalized, true)) {
                $normalized[] = $name;
            }
        }
        sort($normalized);

        return $normalized;
    }

    /**
     * @param array<string,mixed> $triggerSource
     * @return list<array{schema:?string,name:string,resolved_schema:string,resolved_type:?string,resolved_rootpage:int|null,found:bool}>
     */
    private static function resolvedBodyDependencies(SQLiteAttachedSchemaCatalog $catalog, array $triggerSource): array
    {
        $dependencies = [];
        $triggerSchema = self::normalizeName((string) $triggerSource['triggerSchema']);
        $isTempTrigger = (bool) ($triggerSource['triggerTemporary'] ?? false);
        foreach (($triggerSource['bodyDependencies'] ?? []) as $dependency) {
            $schema = $dependency['schema'] ?? null;
            $name = self::normalizeName((string) $dependency['name']);
            $schemas = $schema !== null && $schema !== ''
                ? [self::normalizeName((string) $schema)]
                : ($isTempTrigger ? $catalog->searchOrder() : [$triggerSchema]);

            $resolvedSchema = $schema !== null && $schema !== '' ? self::normalizeName((string) $schema) : $triggerSchema;
            $resolvedType = null;
            $resolvedRoot = null;
            $found = false;
            foreach ($schemas as $candidateSchema) {
                foreach ($catalog->schemaRecords($candidateSchema) as $record) {
                    if (!in_array(strtolower($record->type), ['table', 'view'], true) || strcasecmp($record->name, $name) !== 0) {
                        continue;
                    }
                    $resolvedSchema = $candidateSchema;
                    $resolvedType = strtolower($record->type);
                    $resolvedRoot = $record->rootPage;
                    $found = true;
                    break 2;
                }
            }

            $dependencies[] = [
                'schema' => $schema === null || $schema === '' ? null : self::normalizeName((string) $schema),
                'name' => $name,
                'resolved_schema' => $resolvedSchema,
                'resolved_type' => $resolvedType,
                'resolved_rootpage' => $resolvedRoot,
                'found' => $found,
            ];
        }

        return $dependencies;
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>}> $schemaStates
     * @return array{current:array<string,int>,next:array<string,int>,changed_schemas:list<string>,wal_sources:list<string>}
     */
    private static function schemaCookies(array $schemaStates): array
    {
        $current = [];
        $next = [];
        $changed = [];
        $walSources = [];
        foreach ($schemaStates as $schema => $state) {
            $name = self::normalizeName((string) $schema);
            if (!isset($state['schema_cookie']) || !is_int($state['schema_cookie'])) {
                throw new \InvalidArgumentException("SQLite schema {$name} requires an integer schema cookie");
            }

            $current[$name] = $state['schema_cookie'];
            $cookie = $state['schema_cookie'];
            foreach ($state['wal_frames'] ?? [] as $frame) {
                if (!isset($frame['page']) || !is_int($frame['page'])) {
                    throw new \InvalidArgumentException("SQLite WAL frame for {$name} requires an integer page");
                }
                if (($frame['commit'] ?? true) === true && $frame['page'] === 1 && isset($frame['schema_cookie']) && is_int($frame['schema_cookie'])) {
                    $cookie = $frame['schema_cookie'];
                }
            }
            if (array_key_exists('wal_schema_cookie', $state) && $state['wal_schema_cookie'] !== null) {
                if (!is_int($state['wal_schema_cookie'])) {
                    throw new \InvalidArgumentException("SQLite WAL schema cookie for {$name} must be an integer");
                }
                $cookie = $state['wal_schema_cookie'];
            }
            if (($state['wal_schema_cookie'] ?? null) !== null || ($state['wal_frames'] ?? []) !== []) {
                $walSources[] = $name;
            }
            $next[$name] = $cookie;
            if ($cookie !== $state['schema_cookie']) {
                $changed[] = $name;
            }
        }

        return ['current' => $current, 'next' => $next, 'changed_schemas' => $changed, 'wal_sources' => $walSources];
    }

    private static function normalizeName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed !== '') {
            $first = $trimmed[0];
            $last = $trimmed[strlen($trimmed) - 1];
            if (($first === '"' && $last === '"') || ($first === '`' && $last === '`') || ($first === "'" && $last === "'")) {
                $trimmed = str_replace($first . $first, $first, substr($trimmed, 1, -1));
            } elseif ($first === '[' && $last === ']') {
                $trimmed = substr($trimmed, 1, -1);
            }
        }
        if ($trimmed === '') {
            throw new \InvalidArgumentException('SQLite schema object name cannot be empty');
        }

        return strtolower($trimmed);
    }

    /**
     * @param list<string> $schemas
     * @return list<string>
     */
    private static function orderedSchemaNames(array $schemas): array
    {
        $unique = [];
        foreach ($schemas as $schema) {
            $name = self::normalizeName((string) $schema);
            $unique[$name] = true;
        }

        $order = ['temp' => 0, 'main' => 1];
        $names = array_keys($unique);
        usort($names, static function (string $a, string $b) use ($order): int {
            $rankA = $order[$a] ?? 2;
            $rankB = $order[$b] ?? 2;
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            return $a <=> $b;
        });

        return $names;
    }
}
