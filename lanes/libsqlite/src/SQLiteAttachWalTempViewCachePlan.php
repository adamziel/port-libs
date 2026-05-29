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

    /**
     * @param list<string> $views
     * @param array<string,list<SQLiteSchemaRecord>> $nextSchemaRecords
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>}> $schemaStates
     * @return array<string,mixed>
     */
    public static function viewDependencyCachePlan(
        SQLiteAttachedSchemaCatalog $catalog,
        array $views,
        array $nextSchemaRecords = [],
        array $schemaStates = [],
        string $sourceSchema = 'main',
    ): array {
        if ($views === []) {
            throw new \InvalidArgumentException('SQLite view cache plan requires at least one prepared view');
        }

        $source = self::normalizeName($sourceSchema);
        $beforeViews = [];
        $dependencyTables = [];
        foreach ($views as $view) {
            $viewName = trim($view);
            if ($viewName === '') {
                throw new \InvalidArgumentException('SQLite prepared view name cannot be empty');
            }

            $resolved = self::resolveViewForCache($catalog, $viewName);
            $beforeViews[$viewName] = self::viewEntry($resolved);
            foreach (self::viewDependencyTables($resolved) as $dependency) {
                if (!in_array($dependency, $dependencyTables, true)) {
                    $dependencyTables[] = $dependency;
                }
            }
        }

        $beforeSnapshot = $catalog->schemaCacheResolutionSnapshot(
            array_values(array_unique(array_merge($views, $dependencyTables))),
            [],
            $source,
        );

        foreach ($nextSchemaRecords as $schema => $records) {
            $catalog->replaceSchemaRecords($schema, $records);
        }

        $afterViews = [];
        $viewPlans = [];
        $reprepareViews = [];
        foreach ($views as $view) {
            $resolved = self::resolveViewForCache($catalog, $view);
            $after = self::viewEntry($resolved);
            $afterViews[$view] = $after;

            $beforeDependencies = self::dependencyEntries($beforeSnapshot, self::viewDependencyTablesFromEntry($beforeViews[$view]));
            $afterDependencyNames = self::viewDependencyTables($resolved);
            $afterSnapshot = $catalog->schemaCacheResolutionSnapshot($afterDependencyNames, [], $source);
            $afterDependencies = self::dependencyEntries($afterSnapshot, $afterDependencyNames);
            $viewChanged = $beforeViews[$view] !== $after;
            $dependenciesChanged = $beforeDependencies !== $afterDependencies;
            $requiresReprepare = $viewChanged || $dependenciesChanged;
            if ($requiresReprepare) {
                $reprepareViews[] = $view;
            }

            $viewPlans[$view] = [
                'before' => $beforeViews[$view],
                'after' => $after,
                'view_changed' => $viewChanged,
                'dependency_tables_before' => array_keys($beforeDependencies),
                'dependency_tables_after' => array_keys($afterDependencies),
                'dependencies_before' => $beforeDependencies,
                'dependencies_after' => $afterDependencies,
                'dependencies_changed' => $dependenciesChanged,
                'requires_reprepare' => $requiresReprepare,
            ];
        }

        $invalidation = $catalog->schemaCacheResolutionInvalidation($beforeSnapshot);
        $schemaCookies = self::schemaCookies($schemaStates);

        return [
            'status' => 'planned',
            'operation' => 'attach-temp-main-wal-view-cache-dependency-plan',
            'source_schema' => $source,
            'view_count' => count($views),
            'views' => $viewPlans,
            'reprepare_views' => $reprepareViews,
            'requires_reprepare' => $reprepareViews !== [],
            'schema_record_updates' => array_keys($nextSchemaRecords),
            'changed_schemas' => array_values(array_unique(array_merge($invalidation['invalidated_schemas'], array_keys($nextSchemaRecords), $schemaCookies['changed_schemas']))),
            'schema_cookies_current' => $schemaCookies['current'],
            'schema_cookies_next' => $schemaCookies['next'],
            'wal_schema_cookie_sources' => $schemaCookies['wal_sources'],
            'invalidation' => $invalidation,
            'dependencies' => [
                'sqlite-attach-temp-main-wal-view-cache-dependency-plan',
                'sqlite-view-dependency-cache-reprepare',
                'sqlite-wal-page-one-schema-cookie',
            ],
        ];
    }

    /**
     * @param list<array{name:string, source?:string, active?:bool}> $preparedViews
     * @param array<string,list<SQLiteSchemaRecord>> $nextSchemaRecords
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>}> $schemaStates
     * @return array<string,mixed>
     */
    public static function preparedViewCacheRepreparePlan(
        SQLiteAttachedSchemaCatalog $catalog,
        array $preparedViews,
        array $nextSchemaRecords = [],
        array $schemaStates = [],
    ): array {
        if ($preparedViews === []) {
            throw new \InvalidArgumentException('SQLite attach WAL temp view-cache reprepare requires prepared views');
        }

        $viewNames = [];
        $sources = [];
        $active = [];
        foreach ($preparedViews as $index => $view) {
            if (!isset($view['name']) || trim($view['name']) === '') {
                throw new \InvalidArgumentException("SQLite prepared view {$index} requires a name");
            }
            $name = trim($view['name']);
            $viewNames[] = $name;
            $sources[$name] = self::normalizeName((string) ($view['source'] ?? 'main'));
            $catalog->schemaCacheSnapshot($sources[$name]);
            $active[$name] = (bool) ($view['active'] ?? false);
        }

        $primarySource = $sources[$viewNames[0]];
        $plan = self::viewDependencyCachePlan($catalog, $viewNames, $nextSchemaRecords, $schemaStates, $primarySource);
        $viewPlans = [];
        $currentSnapshotReaders = [];
        $resetSchemaReaders = [];
        $nextStepSchemaReaders = [];
        $stableReaders = [];
        $sourceSchemas = [];

        foreach ($viewNames as $name) {
            $entry = $plan['views'][$name];
            $source = $sources[$name];
            $sourceSchemas[$name] = $source;
            $requiresReprepare = (bool) $entry['requires_reprepare'];
            $isActive = $active[$name];
            if (!$requiresReprepare) {
                $action = 'reuse_prepared_view';
                $sqliteResult = 'SQLITE_OK';
                $stableReaders[] = $name;
            } elseif ($isActive) {
                $action = 'finish_current_source_then_sqlite_schema_on_reset';
                $sqliteResult = 'SQLITE_OK';
                $currentSnapshotReaders[] = $name;
                $resetSchemaReaders[] = $name;
            } else {
                $action = 'sqlite_schema_on_next_step';
                $sqliteResult = 'SQLITE_SCHEMA';
                $nextStepSchemaReaders[] = $name;
            }

            $viewPlans[$name] = $entry + [
                'source_schema' => $source,
                'active' => $isActive,
                'current_step_result' => $sqliteResult,
                'next_step_action' => $action,
                'current_source_kept_until_reset' => $isActive && $requiresReprepare,
            ];
        }

        $changedSchemas = array_values(array_unique(array_merge(
            $plan['changed_schemas'],
            $plan['schema_record_updates'],
        )));
        sort($changedSchemas);

        return [
            'status' => $plan['requires_reprepare'] ? 'view_cache_expired' : 'view_cache_stable',
            'operation' => 'attach-wal-temp-schema-view-cache-reprepare',
            'source_schema' => $primarySource,
            'source_schemas' => $sourceSchemas,
            'view_count' => count($viewNames),
            'active_view_count' => count(array_filter($active)),
            'views' => $viewPlans,
            'reprepare_views' => $plan['reprepare_views'],
            'stable_views' => $stableReaders,
            'active_current_snapshot_views' => $currentSnapshotReaders,
            'reset_schema_views' => $resetSchemaReaders,
            'next_step_schema_views' => $nextStepSchemaReaders,
            'requires_reprepare' => $plan['requires_reprepare'],
            'changed_schemas' => $changedSchemas,
            'schema_record_updates' => $plan['schema_record_updates'],
            'schema_cookies_current' => $plan['schema_cookies_current'],
            'schema_cookies_next' => $plan['schema_cookies_next'],
            'wal_schema_cookie_sources' => $plan['wal_schema_cookie_sources'],
            'invalidation' => $plan['invalidation'],
            'dependencies' => array_values(array_unique(array_merge(
                ['sqlite-attach-wal-temp-schema-view-cache-reprepare'],
                $plan['dependencies'],
                ['sqlite-prepared-view-current-source-reset']
            ))),
        ];
    }

    /**
     * @param list<string> $triggers
     * @param array<string,list<SQLiteSchemaRecord>> $nextSchemaRecords
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>}> $schemaStates
     * @return array<string,mixed>
     */
    public static function triggerProgramCacheRepreparePlan(
        SQLiteAttachedSchemaCatalog $catalog,
        array $triggers,
        array $nextSchemaRecords = [],
        array $schemaStates = [],
        string $sourceSchema = 'main',
    ): array {
        if ($triggers === []) {
            throw new \InvalidArgumentException('SQLite trigger cache plan requires at least one prepared trigger');
        }

        $source = self::normalizeName($sourceSchema);
        $beforeSnapshot = $catalog->schemaCacheResolutionSnapshot([], [], $source);
        $beforeTriggers = [];
        foreach ($triggers as $trigger) {
            $triggerName = trim($trigger);
            if ($triggerName === '') {
                throw new \InvalidArgumentException('SQLite prepared trigger name cannot be empty');
            }

            $beforeTriggers[$triggerName] = self::triggerProgramEntry($catalog, $triggerName);
        }

        foreach ($nextSchemaRecords as $schema => $records) {
            $catalog->replaceSchemaRecords($schema, $records);
        }

        $triggerPlans = [];
        $reprepareTriggers = [];
        $targetChangedTriggers = [];
        $bodyChangedTriggers = [];
        $missingTriggers = [];
        foreach ($triggers as $trigger) {
            $before = $beforeTriggers[$trigger];
            $after = self::triggerProgramEntry($catalog, $trigger);
            $triggerChanged = $before['trigger'] !== $after['trigger'];
            $targetChanged = $before['target'] !== $after['target'];
            $bodyChanged = $before['body_dependencies'] !== $after['body_dependencies'];
            $requiresReprepare = $triggerChanged || $targetChanged || $bodyChanged || $after['trigger']['schema'] === null;
            if ($requiresReprepare) {
                $reprepareTriggers[] = $trigger;
            }
            if ($targetChanged) {
                $targetChangedTriggers[] = $trigger;
            }
            if ($bodyChanged) {
                $bodyChangedTriggers[] = $trigger;
            }
            if ($after['trigger']['schema'] === null) {
                $missingTriggers[] = $trigger;
            }

            $triggerPlans[$trigger] = [
                'before' => $before,
                'after' => $after,
                'trigger_changed' => $triggerChanged,
                'target_changed' => $targetChanged,
                'body_dependencies_changed' => $bodyChanged,
                'current_program_kept' => true,
                'next_requires_reprepare' => $requiresReprepare,
            ];
        }

        $invalidation = $catalog->schemaCacheInvalidation($beforeSnapshot);
        $schemaCookies = self::schemaCookies($schemaStates);
        $changedSchemas = array_values(array_unique(array_merge(
            $invalidation['invalidated_schemas'],
            array_keys($nextSchemaRecords),
            $schemaCookies['changed_schemas'],
        )));
        sort($changedSchemas);

        return [
            'status' => 'planned',
            'operation' => 'attach-temp-wal-trigger-cache-reprepare',
            'source_schema' => $source,
            'trigger_count' => count($triggers),
            'triggers' => $triggerPlans,
            'reprepare_triggers' => $reprepareTriggers,
            'target_changed_triggers' => $targetChangedTriggers,
            'body_changed_triggers' => $bodyChangedTriggers,
            'missing_triggers_next' => $missingTriggers,
            'active_current_programs_kept' => true,
            'requires_reprepare' => $reprepareTriggers !== [],
            'schema_record_updates' => array_keys($nextSchemaRecords),
            'changed_schemas' => $changedSchemas,
            'schema_cookies_current' => $schemaCookies['current'],
            'schema_cookies_next' => $schemaCookies['next'],
            'wal_schema_cookie_sources' => $schemaCookies['wal_sources'],
            'invalidation' => $invalidation,
            'dependencies' => [
                'sqlite-attach-temp-wal-trigger-cache-reprepare',
                'sqlite-trigger-program-cache-reprepare',
                'sqlite-wal-page-one-schema-cookie',
            ],
        ];
    }

    /**
     * @param array{schema: string, record: SQLiteSchemaRecord}|null $resolved
     * @return array{schema:string|null,name:string|null,rootpage:int|null,type:string|null,sql:string|null}
     */
    private static function viewEntry(?array $resolved): array
    {
        if ($resolved === null || $resolved['record']->type !== 'view') {
            return ['schema' => null, 'name' => null, 'rootpage' => null, 'type' => null, 'sql' => null];
        }

        return [
            'schema' => $resolved['schema'],
            'name' => $resolved['record']->name,
            'rootpage' => $resolved['record']->rootPage,
            'type' => $resolved['record']->type,
            'sql' => $resolved['record']->sql,
        ];
    }

    /**
     * @return array{trigger:array{schema:string|null,name:string|null,table:string|null,rootpage:int|null,type:string|null,sql:string|null,rowid:int|null},target:array{schema:string|null,name:string|null,rootpage:int|null,type:string|null},body_dependencies:array<string,array{schema:string|null,name:string|null,rootpage:int|null,type:string|null}>}
     */
    private static function triggerProgramEntry(SQLiteAttachedSchemaCatalog $catalog, string $trigger): array
    {
        try {
            $resolvedTrigger = SQLiteAttachTempViewTriggerResolution::resolveTrigger($catalog, $trigger);
            $resolvedProgram = SQLiteAttachTempViewTriggerResolution::resolve($catalog, $trigger);
        } catch (\InvalidArgumentException $exception) {
            if (str_contains($exception->getMessage(), 'does not exist') || str_contains($exception->getMessage(), 'does not resolve')) {
                return [
                    'trigger' => ['schema' => null, 'name' => null, 'table' => null, 'rootpage' => null, 'type' => null, 'sql' => null, 'rowid' => null],
                    'target' => ['schema' => null, 'name' => null, 'rootpage' => null, 'type' => null],
                    'body_dependencies' => [],
                ];
            }

            throw $exception;
        }

        $record = $resolvedTrigger['record'];
        $target = self::tableEntry($catalog->resolveTable($resolvedProgram['targetSchema'] . '.' . $resolvedProgram['target']));
        $dependencies = [];
        foreach ($resolvedProgram['bodyDependencies'] as $dependency) {
            $key = ($dependency['schema'] ?? '') !== '' ? $dependency['schema'] . '.' . $dependency['name'] : $dependency['name'];
            $dependencies[$key] = self::tableEntry(self::resolveTriggerBodyDependency(
                $catalog,
                $dependency,
                $resolvedTrigger['schema'],
                $resolvedProgram['triggerTemporary'],
            ));
        }
        ksort($dependencies);

        return [
            'trigger' => [
                'schema' => $resolvedTrigger['schema'],
                'name' => $record->name,
                'table' => $record->tableName,
                'rootpage' => $record->rootPage,
                'type' => $record->type,
                'sql' => $record->sql,
                'rowid' => $record->rowId,
            ],
            'target' => $target,
            'body_dependencies' => $dependencies,
        ];
    }

    /**
     * @param array{schema:?string,name:string} $dependency
     * @return array{schema:string,record:SQLiteSchemaRecord}|null
     */
    private static function resolveTriggerBodyDependency(SQLiteAttachedSchemaCatalog $catalog, array $dependency, string $triggerSchema, bool $tempTrigger): ?array
    {
        $schemas = ($dependency['schema'] ?? '') !== ''
            ? [(string) $dependency['schema']]
            : ($tempTrigger ? $catalog->searchOrder() : [$triggerSchema]);

        foreach ($schemas as $schema) {
            foreach ($catalog->schemaRecords($schema) as $record) {
                if (strtolower($record->type) === 'table' && strcasecmp($record->name, $dependency['name']) === 0) {
                    return ['schema' => $schema, 'record' => $record];
                }
            }
        }

        return null;
    }

    /**
     * @param array{schema: string, record: SQLiteSchemaRecord}|null $resolved
     * @return array{schema:string|null,name:string|null,rootpage:int|null,type:string|null}
     */
    private static function tableEntry(?array $resolved): array
    {
        if ($resolved === null) {
            return ['schema' => null, 'name' => null, 'rootpage' => null, 'type' => null];
        }

        return [
            'schema' => $resolved['schema'],
            'name' => $resolved['record']->name,
            'rootpage' => $resolved['record']->rootPage,
            'type' => $resolved['record']->type,
        ];
    }

    /**
     * @return array{schema: string, record: SQLiteSchemaRecord}|null
     */
    private static function resolveViewForCache(SQLiteAttachedSchemaCatalog $catalog, string $view): ?array
    {
        try {
            return $catalog->resolveTable($view);
        } catch (\InvalidArgumentException $exception) {
            if (str_contains($exception->getMessage(), ' is not attached')) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * @param array{schema: string, record: SQLiteSchemaRecord}|null $resolved
     * @return list<string>
     */
    private static function viewDependencyTables(?array $resolved): array
    {
        if ($resolved === null || $resolved['record']->type !== 'view') {
            return [];
        }

        return self::viewDependencyTablesFromEntry(self::viewEntry($resolved));
    }

    /**
     * @param array{schema:string|null,sql:string|null} $entry
     * @return list<string>
     */
    private static function viewDependencyTablesFromEntry(array $entry): array
    {
        $sql = $entry['sql'];
        if ($sql === null || trim($sql) === '') {
            return [];
        }

        $tables = [];
        if (preg_match_all('/\b(?:FROM|JOIN)\s+((?:"[^"]+"|`[^`]+`|\'[^\']+\'|[A-Za-z_][A-Za-z0-9_]*)(?:\s*\.\s*(?:"[^"]+"|`[^`]+`|\'[^\']+\'|[A-Za-z_][A-Za-z0-9_]*))?)/i', $sql, $matches)) {
            foreach ($matches[1] as $match) {
                $table = self::normalizeSqlName($match);
                if (!str_contains($table, '.') && $entry['schema'] !== null && $entry['schema'] !== 'temp') {
                    $table = $entry['schema'] . '.' . $table;
                }
                if (!in_array($table, $tables, true)) {
                    $tables[] = $table;
                }
            }
        }

        return $tables;
    }

    /**
     * @param array<string,mixed> $snapshot
     * @param list<string> $tables
     * @return array<string,array{schema:string|null,name:string|null,rootpage:int|null,type:string|null}>
     */
    private static function dependencyEntries(array $snapshot, array $tables): array
    {
        $entries = [];
        foreach ($tables as $table) {
            $entries[$table] = $snapshot['tables'][$table] ?? ['schema' => null, 'name' => null, 'rootpage' => null, 'type' => null];
        }

        return $entries;
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

    private static function normalizeSqlName(string $name): string
    {
        $parts = preg_split('/\s*\.\s*/', trim($name));
        if ($parts === false || $parts === []) {
            throw new \InvalidArgumentException('SQLite table name cannot be empty');
        }

        return implode('.', array_map([self::class, 'normalizeName'], $parts));
    }
}
