<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachSchemaCookieRepreparePlan
{
    /**
     * @param array<string,array{schema_cookie:int,wal_schema_cookie?:int|null,wal_frames?:list<array{page:int,schema_cookie?:int|null,commit?:bool}>,tables?:list<string>,file?:string|null,temp?:bool}> $schemas
     * @param list<array{name:string,sql:string,active?:bool,read_only?:bool}> $preparedStatements
     * @param list<array{op:string,schema?:string,file?:string|null,schema_cookie?:int,tables?:list<string>,table?:string,object?:string,commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function plan(array $schemas, array $preparedStatements, array $events, string $sourceSchema = 'main'): array
    {
        if ($preparedStatements === []) {
            throw new \InvalidArgumentException('SQLite attach schema-cookie reprepare plan requires prepared statements');
        }

        $source = self::normalizeName($sourceSchema, 'SQLite source schema');
        $current = self::normalizeSchemas($schemas);
        if (!isset($current[$source])) {
            throw new \InvalidArgumentException("SQLite source schema {$source} is not attached");
        }

        $prepareOrder = self::searchOrder($current);
        $prepared = [];
        foreach ($preparedStatements as $index => $statement) {
            $name = self::statementName($statement, $index);
            $tables = self::statementTables((string) ($statement['sql'] ?? ''));
            $resolved = [];
            $cookies = [];
            $schemasRead = [];
            foreach ($tables as $table) {
                $resolution = self::resolveTable($current, $prepareOrder, $table);
                $resolved[$table] = $resolution;
                $cookies[$resolution['schema']] = $current[$resolution['schema']]['schema_cookie'] ?? null;
                if (!in_array($resolution['schema'], $schemasRead, true)) {
                    $schemasRead[] = $resolution['schema'];
                }
            }
            ksort($cookies);

            $prepared[$name] = [
                'index' => $index,
                'name' => $name,
                'sql' => (string) $statement['sql'],
                'tables' => $tables,
                'read_only' => $statement['read_only'] ?? self::statementReadOnly((string) $statement['sql']),
                'active' => (bool) ($statement['active'] ?? false),
                'prepare_schemas' => $schemasRead,
                'prepare_schema_cookies' => $cookies,
                'prepare_resolutions' => $resolved,
            ];
        }

        $next = $current;
        $eventLog = [];
        $attached = [];
        $detached = [];
        $written = [];
        foreach ($events as $index => $event) {
            $op = strtolower(trim((string) ($event['op'] ?? '')));
            if ($op === 'attach') {
                $schema = self::normalizeName((string) ($event['schema'] ?? ''), 'SQLite ATTACH schema');
                if (isset($next[$schema])) {
                    throw new \InvalidArgumentException("SQLite schema {$schema} is already attached");
                }
                $next[$schema] = self::normalizeSchema($schema, [
                    'schema_cookie' => $event['schema_cookie'] ?? 1,
                    'tables' => $event['tables'] ?? [],
                    'file' => $event['file'] ?? null,
                    'temp' => $schema === 'temp',
                ]);
                $attached[] = $schema;
                $eventLog[] = self::eventEntry($index, $op, $schema, $next[$schema]['schema_cookie']);
                continue;
            }

            if ($op === 'detach') {
                $schema = self::normalizeName((string) ($event['schema'] ?? ''), 'SQLite DETACH schema');
                if ($schema === 'main' || $schema === 'temp') {
                    throw new \InvalidArgumentException('SQLite cannot DETACH main or temp schema');
                }
                if (!isset($next[$schema])) {
                    throw new \InvalidArgumentException("SQLite schema {$schema} is not attached");
                }
                unset($next[$schema]);
                $detached[] = $schema;
                $eventLog[] = self::eventEntry($index, $op, $schema, null);
                continue;
            }

            if ($op === 'schema_write') {
                $schema = self::normalizeName((string) ($event['schema'] ?? ''), 'SQLite schema write target');
                if (!isset($next[$schema])) {
                    throw new \InvalidArgumentException("SQLite schema {$schema} is not attached");
                }
                $next[$schema]['schema_cookie'] = (int) ($next[$schema]['schema_cookie'] + 1);
                if (isset($event['table']) || isset($event['object'])) {
                    $table = self::normalizeName((string) ($event['table'] ?? $event['object']), 'SQLite schema object');
                    if (!in_array($table, $next[$schema]['tables'], true)) {
                        $next[$schema]['tables'][] = $table;
                        sort($next[$schema]['tables']);
                    }
                }
                $written[] = $schema;
                $eventLog[] = self::eventEntry($index, $op, $schema, $next[$schema]['schema_cookie']);
                continue;
            }

            if ($op === 'wal_commit') {
                $schema = self::normalizeName((string) ($event['schema'] ?? ''), 'SQLite WAL schema');
                if (!isset($next[$schema])) {
                    throw new \InvalidArgumentException("SQLite schema {$schema} is not attached");
                }
                if (($event['commit'] ?? true) === true) {
                    $next[$schema]['schema_cookie'] = isset($event['schema_cookie'])
                        ? self::integer($event['schema_cookie'], "SQLite WAL schema cookie for {$schema}")
                        : (int) ($next[$schema]['schema_cookie'] + 1);
                    $written[] = $schema;
                }
                $eventLog[] = self::eventEntry($index, $op, $schema, $next[$schema]['schema_cookie']);
                continue;
            }

            throw new \InvalidArgumentException("SQLite attach schema-cookie event {$op} is not supported");
        }

        $nextOrder = self::searchOrder($next);
        $statementPlans = [];
        $expired = [];
        $stable = [];
        $activeCurrent = [];
        $retryableReads = [];
        $writeBlocked = [];
        foreach ($prepared as $name => $statement) {
            $transitions = [];
            $requiresReprepare = false;
            $nextSchemas = [];
            foreach ($statement['tables'] as $table) {
                $before = $statement['prepare_resolutions'][$table];
                $after = self::resolveTable($next, $nextOrder, $table);
                $beforeCookie = $statement['prepare_schema_cookies'][$before['schema']] ?? null;
                $afterCookie = $next[$after['schema']]['schema_cookie'] ?? null;
                $changed = $before['schema'] !== $after['schema']
                    || $before['found'] !== $after['found']
                    || $before['name'] !== $after['name']
                    || $beforeCookie !== $afterCookie;
                $requiresReprepare = $requiresReprepare || $changed;
                if (!in_array($after['schema'], $nextSchemas, true)) {
                    $nextSchemas[] = $after['schema'];
                }
                $transitions[] = [
                    'table' => $table,
                    'prepare_schema' => $before['schema'],
                    'next_schema' => $after['schema'],
                    'prepare_found' => $before['found'],
                    'next_found' => $after['found'],
                    'prepare_schema_cookie' => $beforeCookie,
                    'next_schema_cookie' => $afterCookie,
                    'resolution_changed' => $before['schema'] !== $after['schema'] || $before['found'] !== $after['found'] || $before['name'] !== $after['name'],
                    'requires_reprepare' => $changed,
                ];
            }

            $action = self::nextAction($requiresReprepare, $statement['active'], $statement['read_only']);
            if ($requiresReprepare) {
                $expired[] = $name;
                if ($statement['active']) {
                    $activeCurrent[] = $name;
                }
                if ($statement['read_only']) {
                    $retryableReads[] = $name;
                } else {
                    $writeBlocked[] = $name;
                }
            } else {
                $stable[] = $name;
            }

            $statementPlans[] = [
                'name' => $name,
                'sql' => $statement['sql'],
                'tables' => $statement['tables'],
                'read_only' => $statement['read_only'],
                'active' => $statement['active'],
                'prepare_schemas' => $statement['prepare_schemas'],
                'next_schemas' => $nextSchemas,
                'prepare_schema_cookies' => $statement['prepare_schema_cookies'],
                'transitions' => $transitions,
                'requires_reprepare' => $requiresReprepare,
                'sqlite_result_on_current_step' => $statement['active'] ? 'SQLITE_OK' : ($requiresReprepare ? 'SQLITE_SCHEMA' : 'SQLITE_OK'),
                'next_step_action' => $action,
            ];
        }

        $currentCookies = self::schemaCookies($current);
        $nextCookies = self::schemaCookies($next);
        $changedSchemas = array_values(array_unique(array_merge(
            $attached,
            $detached,
            $written,
            self::cookieChangedSchemas($currentCookies, $nextCookies),
        )));
        sort($changedSchemas);

        return [
            'status' => $expired === [] ? 'schema_cache_stable' : 'schema_cache_expired',
            'operation' => 'attach-schema-cookie-reprepare-current-source-next84',
            'source' => $source,
            'event_count' => count($events),
            'statement_count' => count($statementPlans),
            'search_order_before' => $prepareOrder,
            'search_order_after' => $nextOrder,
            'schema_cookies_current' => $currentCookies,
            'schema_cookies_next' => $nextCookies,
            'changed_schemas' => $changedSchemas,
            'attached_schemas' => array_values(array_unique($attached)),
            'detached_schemas' => array_values(array_unique($detached)),
            'written_schemas' => array_values(array_unique($written)),
            'events' => $eventLog,
            'statements' => $statementPlans,
            'expired_statements' => $expired,
            'stable_statements' => $stable,
            'active_current_snapshot_statements' => $activeCurrent,
            'retryable_read_statements' => $retryableReads,
            'write_statements_blocked_before_retry' => $writeBlocked,
            'requires_reprepare' => $expired !== [],
            'database_list_before' => self::databaseList($current, $prepareOrder),
            'database_list_after' => self::databaseList($next, $nextOrder),
            'dependencies' => [
                'sqlite-attach-schema-cookie-reprepare-current-source-next84',
                'sqlite-schema-cookie-current-source-expiry',
                'sqlite-attach-detach-search-order-reprepare',
                'sqlite-wal-page-one-schema-cookie',
            ],
        ];
    }

    /**
     * @param array<string,array{schema_cookie:int,wal_schema_cookie?:int|null,wal_frames?:list<array{page:int,schema_cookie?:int|null,commit?:bool}>,tables?:list<string>,file?:string|null,temp?:bool}> $schemas
     * @param list<array{name:string,sql:string,active?:bool,read_only?:bool}> $preparedStatements
     * @param list<array{op:string,schema?:string,file?:string|null,schema_cookie?:int,tables?:list<string>,table?:string,object?:string,commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function currentSourceNext100(array $schemas, array $preparedStatements, array $events, string $sourceSchema = 'main'): array
    {
        $plan = self::plan($schemas, $preparedStatements, $events, $sourceSchema);
        $plan['operation'] = 'attach-schema-cookie-reprepare-current-source-next100';
        array_unshift($plan['dependencies'], 'sqlite-attach-schema-cookie-reprepare-current-source-next100');
        $plan['dependencies'] = array_values(array_unique($plan['dependencies']));

        return $plan;
    }

    /**
     * @param array<string,array{schema_cookie:int,wal_schema_cookie?:int|null,wal_frames?:list<array{page:int,schema_cookie?:int|null,commit?:bool}>,tables?:list<string>,file?:string|null,temp?:bool,cache?:string|null}> $schemas
     * @param list<array{name:string,sql:string,active?:bool,read_only?:bool}> $preparedStatements
     * @param list<array{op:string,schema?:string,file?:string|null,schema_cookie?:int,tables?:list<string>,table?:string,object?:string,commit?:bool,cache?:string|null}> $events
     * @param array<string,array{file?:string|null,cache?:string|null,schema_cookie:int,generation?:int}> $schemaCacheEntries
     * @return array<string,mixed>
     */
    public static function currentSourceNext103(
        array $schemas,
        array $preparedStatements,
        array $events,
        array $schemaCacheEntries = [],
        string $sourceSchema = 'main',
    ): array {
        $plan = self::plan($schemas, $preparedStatements, $events, $sourceSchema);
        $plan['operation'] = 'attach-temp-schema-cache-reprepare-current-source-next103';
        array_unshift($plan['dependencies'], 'sqlite-attach-temp-schema-cache-reprepare-current-source-next103');
        $plan['dependencies'] = array_values(array_unique($plan['dependencies']));

        $current = self::normalizeSchemas($schemas);
        $next = self::applyEventsForCache($current, $events);
        $cachePlans = [];
        $reloadSchemas = [];
        $reuseSchemas = [];
        $uncachedSchemas = [];
        foreach ($next as $schema => $entry) {
            $cacheEntry = $schemaCacheEntries[$schema] ?? [];
            $cacheMode = (string) ($cacheEntry['cache'] ?? ($schemas[$schema]['cache'] ?? ''));
            $cacheable = $cacheMode === 'shared' && $schema !== 'temp';
            $cachedCookie = isset($cacheEntry['schema_cookie'])
                ? self::integer($cacheEntry['schema_cookie'], "SQLite schema cache cookie for {$schema}")
                : null;
            $nextCookie = $entry['schema_cookie'];
            $requiresReload = $cacheable && $cachedCookie !== null && $cachedCookie !== $nextCookie;
            $reuse = $cacheable && $cachedCookie === $nextCookie;

            if ($requiresReload) {
                $reloadSchemas[] = $schema;
            } elseif ($reuse) {
                $reuseSchemas[] = $schema;
            } else {
                $uncachedSchemas[] = $schema;
            }

            $cachePlans[$schema] = [
                'schema' => $schema,
                'file' => $entry['file'],
                'cache' => $cacheMode === '' ? null : $cacheMode,
                'cacheable' => $cacheable,
                'cache_generation' => $cacheEntry['generation'] ?? null,
                'cached_schema_cookie' => $cachedCookie,
                'current_schema_cookie' => $current[$schema]['schema_cookie'] ?? null,
                'next_schema_cookie' => $nextCookie,
                'requires_reload' => $requiresReload,
                'reuse_cached_schema' => $reuse,
                'next_source_action' => $requiresReload ? 'reload_shared_schema_cache_before_reprepare' : ($reuse ? 'reuse_shared_schema_cache' : 'load_schema_records_without_shared_cache'),
            ];
        }

        $resetReload = [];
        $nextStepReload = [];
        foreach ($plan['statements'] as $index => $statement) {
            $schemasRead = array_values(array_unique(array_merge($statement['prepare_schemas'], $statement['next_schemas'])));
            $statementReloadSchemas = array_values(array_intersect($schemasRead, $reloadSchemas));
            $plan['statements'][$index]['shared_cache_reload_schemas'] = $statementReloadSchemas;
            $plan['statements'][$index]['next_source_cache_action'] = $statementReloadSchemas === []
                ? 'no_shared_cache_reload'
                : ($statement['active'] ? 'finish_current_source_then_reload_cache_on_reset' : 'reload_cache_then_reprepare_next_step');
            if ($statementReloadSchemas !== []) {
                if ($statement['active']) {
                    $resetReload[] = $statement['name'];
                } else {
                    $nextStepReload[] = $statement['name'];
                }
            }
        }

        $plan['schema_cache_entries'] = $cachePlans;
        $plan['shared_cache_reload_schemas'] = $reloadSchemas;
        $plan['shared_cache_reuse_schemas'] = $reuseSchemas;
        $plan['uncached_schemas'] = $uncachedSchemas;
        $plan['active_reset_shared_cache_reload_statements'] = $resetReload;
        $plan['next_step_shared_cache_reload_statements'] = $nextStepReload;
        $plan['requires_shared_cache_reload'] = $reloadSchemas !== [];

        return $plan;
    }

    /**
     * @param array<string,array{schema_cookie:int,tables:list<string>,file:string|null,temp:bool}> $current
     * @param list<array<string,mixed>> $events
     * @return array<string,array{schema_cookie:int,tables:list<string>,file:string|null,temp:bool}>
     */
    private static function applyEventsForCache(array $current, array $events): array
    {
        $next = $current;
        foreach ($events as $event) {
            $op = strtolower(trim((string) ($event['op'] ?? '')));
            if ($op === 'attach') {
                $schema = self::normalizeName((string) ($event['schema'] ?? ''), 'SQLite ATTACH schema');
                $next[$schema] = self::normalizeSchema($schema, [
                    'schema_cookie' => $event['schema_cookie'] ?? 1,
                    'tables' => $event['tables'] ?? [],
                    'file' => $event['file'] ?? null,
                    'temp' => false,
                ]);
                continue;
            }
            if ($op === 'detach') {
                $schema = self::normalizeName((string) ($event['schema'] ?? ''), 'SQLite DETACH schema');
                unset($next[$schema]);
                continue;
            }
            if ($op === 'schema_write') {
                $schema = self::normalizeName((string) ($event['schema'] ?? ''), 'SQLite schema write target');
                if (isset($next[$schema])) {
                    $next[$schema]['schema_cookie']++;
                    $table = $event['table'] ?? $event['object'] ?? null;
                    if (is_string($table) && trim($table) !== '') {
                        $name = self::normalizeName($table, 'SQLite schema object');
                        if (!in_array($name, $next[$schema]['tables'], true)) {
                            $next[$schema]['tables'][] = $name;
                            sort($next[$schema]['tables']);
                        }
                    }
                }
                continue;
            }
            if ($op === 'wal_commit') {
                $schema = self::normalizeName((string) ($event['schema'] ?? ''), 'SQLite WAL schema');
                if (isset($next[$schema]) && ($event['commit'] ?? true) === true) {
                    $next[$schema]['schema_cookie'] = isset($event['schema_cookie'])
                        ? self::integer($event['schema_cookie'], "SQLite WAL schema cookie for {$schema}")
                        : $next[$schema]['schema_cookie'] + 1;
                }
            }
        }
        ksort($next);

        return $next;
    }

    /**
     * @param array<string,array<string,mixed>> $schemas
     * @return array<string,array{schema_cookie:int,tables:list<string>,file:string|null,temp:bool}>
     */
    private static function normalizeSchemas(array $schemas): array
    {
        $normalized = [];
        foreach ($schemas as $schema => $entry) {
            $name = self::normalizeName((string) $schema, 'SQLite schema');
            $normalized[$name] = self::normalizeSchema($name, $entry);
        }

        foreach (['main', 'temp'] as $schema) {
            $normalized[$schema] ??= [
                'schema_cookie' => 0,
                'tables' => [],
                'file' => $schema === 'temp' ? '' : null,
                'temp' => $schema === 'temp',
            ];
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<string,mixed> $entry
     * @return array{schema_cookie:int,tables:list<string>,file:string|null,temp:bool}
     */
    private static function normalizeSchema(string $schema, array $entry): array
    {
        if (!array_key_exists('schema_cookie', $entry)) {
            throw new \InvalidArgumentException("SQLite schema {$schema} requires an integer schema cookie");
        }
        $cookie = self::integer($entry['schema_cookie'], "SQLite schema {$schema} cookie");
        foreach ($entry['wal_frames'] ?? [] as $frame) {
            if (!isset($frame['page']) || !is_int($frame['page'])) {
                throw new \InvalidArgumentException("SQLite WAL frame for {$schema} requires an integer page");
            }
            if (($frame['commit'] ?? true) === true && $frame['page'] === 1 && isset($frame['schema_cookie']) && is_int($frame['schema_cookie'])) {
                $cookie = $frame['schema_cookie'];
            }
        }
        if (($entry['wal_schema_cookie'] ?? null) !== null) {
            $cookie = self::integer($entry['wal_schema_cookie'], "SQLite WAL schema cookie for {$schema}");
        }

        $tables = [];
        foreach ($entry['tables'] ?? [] as $table) {
            $tables[] = self::normalizeName((string) $table, 'SQLite table');
        }
        $tables = array_values(array_unique($tables));
        sort($tables);

        return [
            'schema_cookie' => $cookie,
            'tables' => $tables,
            'file' => isset($entry['file']) ? (string) $entry['file'] : null,
            'temp' => (bool) ($entry['temp'] ?? $schema === 'temp'),
        ];
    }

    /**
     * @param array<string,array{schema_cookie:int,tables:list<string>,file:string|null,temp:bool}> $schemas
     * @return list<string>
     */
    private static function searchOrder(array $schemas): array
    {
        $order = [];
        foreach (['temp', 'main'] as $schema) {
            if (isset($schemas[$schema])) {
                $order[] = $schema;
            }
        }
        foreach ($schemas as $schema => $_entry) {
            if ($schema !== 'temp' && $schema !== 'main') {
                $order[] = $schema;
            }
        }

        return $order;
    }

    /**
     * @param array<string,array{schema_cookie:int,tables:list<string>,file:string|null,temp:bool}> $schemas
     * @param list<string> $order
     * @return array{schema:string,name:string,qualified:bool,found:bool}
     */
    private static function resolveTable(array $schemas, array $order, string $table): array
    {
        $parts = explode('.', $table, 2);
        if (count($parts) === 2) {
            $schema = self::normalizeName($parts[0], 'SQLite qualified schema');
            $name = self::normalizeName($parts[1], 'SQLite qualified table');

            return [
                'schema' => $schema,
                'name' => $name,
                'qualified' => true,
                'found' => in_array($name, $schemas[$schema]['tables'] ?? [], true),
            ];
        }

        $name = self::normalizeName($table, 'SQLite table');
        foreach ($order as $schema) {
            if (in_array($name, $schemas[$schema]['tables'] ?? [], true)) {
                return [
                    'schema' => $schema,
                    'name' => $name,
                    'qualified' => false,
                    'found' => true,
                ];
            }
        }

        return ['schema' => 'main', 'name' => $name, 'qualified' => false, 'found' => false];
    }

    /**
     * @return list<string>
     */
    private static function statementTables(string $sql): array
    {
        $trimmed = trim($sql);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('SQLite prepared SQL statement cannot be empty');
        }
        $identifier = '(?:"[^"]+"|`[^`]+`|\'[^\']+\'|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)';
        $patterns = [
            '/\b(?:FROM|JOIN|INTO|UPDATE)\s+((' . $identifier . ')(?:\s*\.\s*' . $identifier . ')?)/i',
            '/\bDELETE\s+FROM\s+((' . $identifier . ')(?:\s*\.\s*' . $identifier . ')?)/i',
        ];
        $cteNames = self::cteNames($trimmed);
        $tables = [];
        foreach ($patterns as $pattern) {
            $matchCount = preg_match_all($pattern, $trimmed, $matches);
            if ($matchCount === false || $matchCount === 0) {
                continue;
            }
            foreach ($matches[1] as $match) {
                $table = self::normalizeSqlName($match);
                if (!str_contains($table, '.') && isset($cteNames[$table])) {
                    continue;
                }
                if (!in_array($table, $tables, true)) {
                    $tables[] = $table;
                }
            }
        }
        if ($tables === []) {
            throw new \InvalidArgumentException('SQLite prepared SQL statement has no bounded table reference');
        }

        return $tables;
    }

    /**
     * @return array<string,true>
     */
    private static function cteNames(string $sql): array
    {
        if (preg_match('/^\s*WITH\s+(?:RECURSIVE\s+)?/i', $sql, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [];
        }

        $offset = strlen($match[0][0]);
        $length = strlen($sql);
        $names = [];
        while ($offset < $length) {
            $offset = self::skipWhitespace($sql, $offset);
            [$name, $offset] = self::readSqlIdentifier($sql, $offset, 'SQLite CTE name');
            $names[$name] = true;
            $offset = self::skipWhitespace($sql, $offset);

            if (($sql[$offset] ?? '') === '(') {
                $offset = self::skipBalanced($sql, $offset);
                $offset = self::skipWhitespace($sql, $offset);
            }

            foreach (['MATERIALIZED', 'NOT MATERIALIZED'] as $modifier) {
                $end = $offset + strlen($modifier);
                if (strtoupper(substr($sql, $offset, strlen($modifier))) === $modifier && !preg_match('/[A-Za-z0-9_]/', $sql[$end] ?? '')) {
                    $offset = self::skipWhitespace($sql, $end);
                    break;
                }
            }

            if (strtoupper(substr($sql, $offset, 2)) !== 'AS') {
                return $names;
            }
            $offset = self::skipWhitespace($sql, $offset + 2);
            $offset = self::skipCteMaterialization($sql, $offset);
            if (($sql[$offset] ?? '') !== '(') {
                return $names;
            }
            $offset = self::skipBalanced($sql, $offset);
            $offset = self::skipWhitespace($sql, $offset);
            if (($sql[$offset] ?? '') !== ',') {
                break;
            }
            $offset++;
        }

        return $names;
    }

    private static function skipWhitespace(string $sql, int $offset): int
    {
        $length = strlen($sql);
        while ($offset < $length && ctype_space($sql[$offset])) {
            $offset++;
        }

        return $offset;
    }

    /**
     * @return array{0:string,1:int}
     */
    private static function readSqlIdentifier(string $sql, int $offset, string $label): array
    {
        $char = $sql[$offset] ?? '';
        if ($char === '"' || $char === '\'' || $char === '`' || $char === '[') {
            $endChar = $char === '[' ? ']' : $char;
            $end = strpos($sql, $endChar, $offset + 1);
            if ($end === false) {
                throw new \InvalidArgumentException($label . ' has an unterminated quoted identifier');
            }

            return [self::normalizeName(substr($sql, $offset, $end - $offset + 1), $label), $end + 1];
        }

        if (preg_match('/[A-Za-z_][A-Za-z0-9_]*/A', substr($sql, $offset), $match) !== 1) {
            throw new \InvalidArgumentException($label . ' cannot be empty');
        }

        return [self::normalizeName($match[0], $label), $offset + strlen($match[0])];
    }

    private static function skipBalanced(string $sql, int $offset): int
    {
        $depth = 0;
        $length = strlen($sql);
        for ($index = $offset; $index < $length; $index++) {
            $char = $sql[$index];
            if ($char === '"' || $char === '\'' || $char === '`' || $char === '[') {
                $endChar = $char === '[' ? ']' : $char;
                $end = strpos($sql, $endChar, $index + 1);
                if ($end === false) {
                    throw new \InvalidArgumentException('SQLite SQL has an unterminated quoted identifier');
                }
                $index = $end;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $index + 1;
                }
            }
        }

        throw new \InvalidArgumentException('SQLite SQL has an unterminated parenthesized expression');
    }

    private static function statementReadOnly(string $sql): bool
    {
        $keyword = self::firstStatementKeyword($sql);

        return in_array($keyword, ['SELECT', 'PRAGMA'], true);
    }

    private static function firstStatementKeyword(string $sql): string
    {
        $trimmed = ltrim($sql);
        if (preg_match('/^WITH\s+(?:RECURSIVE\s+)?/i', $trimmed, $match) !== 1) {
            return strtoupper(strtok($trimmed, " \t\r\n(") ?: '');
        }

        $offset = strlen($match[0]);
        $length = strlen($trimmed);
        while ($offset < $length) {
            $offset = self::skipWhitespace($trimmed, $offset);
            [, $offset] = self::readSqlIdentifier($trimmed, $offset, 'SQLite CTE name');
            $offset = self::skipWhitespace($trimmed, $offset);
            if (($trimmed[$offset] ?? '') === '(') {
                $offset = self::skipBalanced($trimmed, $offset);
                $offset = self::skipWhitespace($trimmed, $offset);
            }
            foreach (['MATERIALIZED', 'NOT MATERIALIZED'] as $modifier) {
                $end = $offset + strlen($modifier);
                if (strtoupper(substr($trimmed, $offset, strlen($modifier))) === $modifier && !preg_match('/[A-Za-z0-9_]/', $trimmed[$end] ?? '')) {
                    $offset = self::skipWhitespace($trimmed, $end);
                    break;
                }
            }
            if (strtoupper(substr($trimmed, $offset, 2)) !== 'AS') {
                return strtoupper(strtok(substr($trimmed, $offset), " \t\r\n(") ?: '');
            }
            $offset = self::skipWhitespace($trimmed, $offset + 2);
            $offset = self::skipCteMaterialization($trimmed, $offset);
            if (($trimmed[$offset] ?? '') !== '(') {
                return strtoupper(strtok(substr($trimmed, $offset), " \t\r\n(") ?: '');
            }
            $offset = self::skipBalanced($trimmed, $offset);
            $offset = self::skipWhitespace($trimmed, $offset);
            if (($trimmed[$offset] ?? '') !== ',') {
                return strtoupper(strtok(substr($trimmed, $offset), " \t\r\n(") ?: '');
            }
            $offset++;
        }

        return '';
    }

    private static function skipCteMaterialization(string $sql, int $offset): int
    {
        foreach (['NOT MATERIALIZED', 'MATERIALIZED'] as $modifier) {
            $end = $offset + strlen($modifier);
            if (strtoupper(substr($sql, $offset, strlen($modifier))) === $modifier && !preg_match('/[A-Za-z0-9_]/', $sql[$end] ?? '')) {
                return self::skipWhitespace($sql, $end);
            }
        }

        return $offset;
    }

    /**
     * @param array{name?:string} $statement
     */
    private static function statementName(array $statement, int $index): string
    {
        $name = trim((string) ($statement['name'] ?? 'stmt-' . $index));
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite prepared statement name cannot be empty');
        }

        return $name;
    }

    private static function nextAction(bool $requiresReprepare, bool $active, bool $readOnly): string
    {
        if (!$requiresReprepare) {
            return 'reuse_prepared_statement';
        }
        if ($active) {
            return 'finish_current_source_then_sqlite_schema_on_reset';
        }

        return $readOnly ? 'sqlite_schema_then_reprepare_and_retry' : 'sqlite_schema_before_write_retry';
    }

    /**
     * @param array<string,array{schema_cookie:int}> $schemas
     * @return array<string,int>
     */
    private static function schemaCookies(array $schemas): array
    {
        $cookies = [];
        foreach ($schemas as $schema => $entry) {
            $cookies[$schema] = $entry['schema_cookie'];
        }
        ksort($cookies);

        return $cookies;
    }

    /**
     * @param array<string,int> $current
     * @param array<string,int> $next
     * @return list<string>
     */
    private static function cookieChangedSchemas(array $current, array $next): array
    {
        $schemas = array_values(array_unique(array_merge(array_keys($current), array_keys($next))));
        sort($schemas);
        $changed = [];
        foreach ($schemas as $schema) {
            if (($current[$schema] ?? null) !== ($next[$schema] ?? null)) {
                $changed[] = $schema;
            }
        }

        return $changed;
    }

    /**
     * @param array<string,array{schema_cookie:int,tables:list<string>,file:string|null,temp:bool}> $schemas
     * @param list<string> $order
     * @return list<array{seq:int,name:string,file:string|null,schema_cookie:int,table_count:int}>
     */
    private static function databaseList(array $schemas, array $order): array
    {
        $rows = [];
        foreach ($order as $seq => $schema) {
            $rows[] = [
                'seq' => $seq,
                'name' => $schema,
                'file' => $schemas[$schema]['file'],
                'schema_cookie' => $schemas[$schema]['schema_cookie'],
                'table_count' => count($schemas[$schema]['tables']),
            ];
        }

        return $rows;
    }

    /**
     * @return array{index:int,op:string,schema:string,schema_cookie:int|null}
     */
    private static function eventEntry(int $index, string $op, string $schema, ?int $cookie): array
    {
        return ['index' => $index, 'op' => $op, 'schema' => $schema, 'schema_cookie' => $cookie];
    }

    private static function integer(mixed $value, string $label): int
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException($label . ' must be an integer');
        }

        return $value;
    }

    private static function normalizeSqlName(string $name): string
    {
        $parts = preg_split('/\s*\.\s*/', trim($name));
        if ($parts === false || $parts === []) {
            throw new \InvalidArgumentException('SQLite table name cannot be empty');
        }

        $normalized = array_map(static fn (string $part): string => self::normalizeName($part, 'SQLite table name'), $parts);
        $last = count($normalized) - 1;
        if (($normalized[$last] ?? '') === 'sqlite_master') {
            $normalized[$last] = 'sqlite_schema';
        }

        return implode('.', $normalized);
    }

    private static function normalizeName(string $name, string $label): string
    {
        $trimmed = trim($name, " \t\r\n`'\"[]");
        if ($trimmed === '') {
            throw new \InvalidArgumentException($label . ' cannot be empty');
        }

        return strtolower($trimmed);
    }
}
