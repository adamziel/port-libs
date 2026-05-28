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
    public static function currentSourceNext85(
        SQLiteAttachedSchemaCatalog $catalog,
        array $preparedTriggers,
        array $nextSchemaRecords = [],
        array $schemaStates = [],
    ): array {
        if ($preparedTriggers === []) {
            throw new \InvalidArgumentException('SQLite attach temp schema trigger-cache current-source-next85 requires prepared triggers');
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
            'operation' => 'attach-temp-schema-trigger-cache-current-source-next85',
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
                'sqlite-attach-temp-schema-trigger-cache-current-source-next85',
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
    public static function currentSourceNext89(
        SQLiteAttachedSchemaCatalog $catalog,
        array $preparedTriggers,
        array $nextSchemaRecords = [],
        array $schemaStates = [],
    ): array {
        if ($preparedTriggers === []) {
            throw new \InvalidArgumentException('SQLite attach WAL temp trigger-cache current-source-next89 requires prepared triggers');
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
            'operation' => 'attach-wal-temp-trigger-cache-current-source-next89',
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
                'sqlite-attach-wal-temp-trigger-cache-current-source-next89',
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
    public static function currentSourceNext90(
        SQLiteAttachedSchemaCatalog $current,
        SQLiteAttachedSchemaCatalog $next,
        array $preparedTriggers,
        array $schemaStates = [],
    ): array {
        if ($preparedTriggers === []) {
            throw new \InvalidArgumentException('SQLite attach temp WAL schema trigger current-source-next90 requires prepared triggers');
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
            'operation' => 'attach-temp-wal-schema-trigger-current-source-next90',
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
                'sqlite-attach-temp-wal-schema-trigger-current-source-next90',
                'sqlite-prepared-trigger-current-source-reset',
                'sqlite-temp-trigger-shadow-resolution',
                'sqlite-wal-page-one-schema-cookie',
            ],
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
        $trimmed = trim($name, " \t\r\n`'\"");
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
