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
        $patterns = [
            '/\b(?:FROM|JOIN|INTO|UPDATE)\s+((?:"[^"]+"|`[^`]+`|\'[^\']+\'|[A-Za-z_][A-Za-z0-9_]*)(?:\s*\.\s*(?:"[^"]+"|`[^`]+`|\'[^\']+\'|[A-Za-z_][A-Za-z0-9_]*))?)/i',
            '/\bDELETE\s+FROM\s+((?:"[^"]+"|`[^`]+`|\'[^\']+\'|[A-Za-z_][A-Za-z0-9_]*)(?:\s*\.\s*(?:"[^"]+"|`[^`]+`|\'[^\']+\'|[A-Za-z_][A-Za-z0-9_]*))?)/i',
        ];
        $tables = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $trimmed, $matches) !== 1) {
                continue;
            }
            foreach ($matches[1] as $match) {
                $table = self::normalizeSqlName($match);
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

    private static function statementReadOnly(string $sql): bool
    {
        return preg_match('/^\s*(?:SELECT|WITH|PRAGMA)\b/i', $sql) === 1;
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

        return implode('.', array_map(static fn (string $part): string => self::normalizeName($part, 'SQLite table name'), $parts));
    }

    private static function normalizeName(string $name, string $label): string
    {
        $trimmed = trim($name, " \t\r\n`'\"");
        if ($trimmed === '') {
            throw new \InvalidArgumentException($label . ' cannot be empty');
        }

        return strtolower($trimmed);
    }
}
