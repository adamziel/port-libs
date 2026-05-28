<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan
{
    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, file?:string|null, temp?:bool}> $schemas
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @param list<array{op:string, schema?:string, schema_cookie?:int, tables?:list<string>, table?:string, object?:string, file?:string|null, commit?:bool}> $events
     * @return array<string,mixed>
     */
    public static function plan(array $schemas, array $statements, array $events, string $sourceSchema = 'main'): array
    {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite attach WAL temp schema-cache current-source next92 requires statements');
        }

        $source = self::name($sourceSchema, 'SQLite source schema');
        $current = self::normalizeSchemas($schemas);
        if (!isset($current[$source])) {
            throw new \InvalidArgumentException("SQLite source schema {$source} is not attached");
        }

        $currentOrder = self::searchOrder($current);
        $prepared = self::prepareStatements($current, $currentOrder, $statements);
        [$next, $eventLog] = self::applyEvents($current, $events);
        $nextOrder = self::searchOrder($next);

        $statementPlans = [];
        $expired = [];
        $stable = [];
        $active = [];
        $retryable = [];
        $writeBlocked = [];
        foreach ($prepared as $statement) {
            $transitions = [];
            $requiresReprepare = false;
            $nextSchemas = [];
            foreach ($statement['tables'] as $table) {
                $before = $statement['resolutions'][$table];
                $after = self::resolve($next, $nextOrder, $table);
                $beforeCookie = $current[$before['schema']]['schema_cookie'] ?? null;
                $afterCookie = $next[$after['schema']]['schema_cookie'] ?? null;
                $resolutionChanged = $before['schema'] !== $after['schema']
                    || $before['found'] !== $after['found']
                    || $before['name'] !== $after['name'];
                $cookieChanged = $beforeCookie !== $afterCookie;
                $changed = $resolutionChanged || $cookieChanged;
                $requiresReprepare = $requiresReprepare || $changed;
                if (!in_array($after['schema'], $nextSchemas, true)) {
                    $nextSchemas[] = $after['schema'];
                }
                $transitions[] = [
                    'table' => $table,
                    'current_schema' => $before['schema'],
                    'next_schema' => $after['schema'],
                    'current_found' => $before['found'],
                    'next_found' => $after['found'],
                    'current_schema_cookie' => $beforeCookie,
                    'next_schema_cookie' => $afterCookie,
                    'resolution_changed' => $resolutionChanged,
                    'schema_cookie_changed' => $cookieChanged,
                    'requires_reprepare' => $changed,
                ];
            }

            $name = $statement['name'];
            if ($requiresReprepare) {
                $expired[] = $name;
                if ($statement['active']) {
                    $active[] = $name;
                }
                if ($statement['read_only']) {
                    $retryable[] = $name;
                } else {
                    $writeBlocked[] = $name;
                }
            } else {
                $stable[] = $name;
            }

            $statementPlans[$name] = [
                'name' => $name,
                'sql' => $statement['sql'],
                'active' => $statement['active'],
                'read_only' => $statement['read_only'],
                'tables' => $statement['tables'],
                'current_schemas' => $statement['schemas'],
                'next_schemas' => $nextSchemas,
                'schema_transitions' => $transitions,
                'requires_reprepare' => $requiresReprepare,
                'sqlite_result_on_current_step' => $statement['active'] ? 'SQLITE_OK' : ($requiresReprepare ? 'SQLITE_SCHEMA' : 'SQLITE_OK'),
                'next_step_action' => self::action($statement['active'], $statement['read_only'], $requiresReprepare),
            ];
        }

        $currentCookies = self::cookies($current);
        $nextCookies = self::cookies($next);
        $changedSchemas = self::changedSchemas($currentCookies, $nextCookies, $current, $next);

        return [
            'status' => $expired === [] ? 'schema_cache_stable' : 'schema_cache_expired',
            'operation' => 'attach-wal-temp-schema-cache-current-source-next92',
            'source' => $source,
            'event_count' => count($events),
            'statement_count' => count($statementPlans),
            'search_order_current' => $currentOrder,
            'search_order_next' => $nextOrder,
            'schema_cookies_current' => $currentCookies,
            'schema_cookies_next' => $nextCookies,
            'changed_schemas' => $changedSchemas,
            'events' => $eventLog,
            'statements' => $statementPlans,
            'expired_statements' => $expired,
            'stable_statements' => $stable,
            'active_current_snapshot_statements' => $active,
            'retryable_read_statements' => $retryable,
            'write_statements_blocked_before_retry' => $writeBlocked,
            'requires_reprepare' => $expired !== [],
            'dependencies' => [
                'sqlite-attach-wal-temp-schema-cache-current-source-next92',
                'sqlite-attach-detach-search-order-cache-expiry',
                'sqlite-wal-page-one-schema-cookie-current-source',
                'sqlite-temp-schema-shadow-cache-expiry',
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
            $name = self::name((string) $schema, 'SQLite schema');
            $tables = [];
            foreach (($entry['tables'] ?? []) as $table) {
                $tables[] = self::name((string) $table, 'SQLite table');
            }
            sort($tables);
            $normalized[$name] = [
                'schema_cookie' => self::currentCookie($entry),
                'tables' => array_values(array_unique($tables)),
                'file' => isset($entry['file']) ? (string) $entry['file'] : null,
                'temp' => (bool) ($entry['temp'] ?? $name === 'temp'),
            ];
        }

        foreach (['main', 'temp'] as $schema) {
            $normalized[$schema] ??= [
                'schema_cookie' => 0,
                'tables' => [],
                'file' => $schema === 'temp' ? '' : null,
                'temp' => $schema === 'temp',
            ];
        }

        uksort($normalized, static function (string $a, string $b): int {
            $rank = static fn (string $schema): int => $schema === 'temp' ? 0 : ($schema === 'main' ? 1 : 2);
            return [$rank($a), $a] <=> [$rank($b), $b];
        });

        return $normalized;
    }

    /**
     * @param array<string,mixed> $entry
     */
    private static function currentCookie(array $entry): int
    {
        if (!isset($entry['schema_cookie']) || !is_int($entry['schema_cookie'])) {
            throw new \InvalidArgumentException('SQLite schema requires an integer schema cookie');
        }
        $cookie = $entry['schema_cookie'];
        if (isset($entry['wal_schema_cookie'])) {
            if (!is_int($entry['wal_schema_cookie'])) {
                throw new \InvalidArgumentException('SQLite WAL schema cookie must be an integer');
            }
            $cookie = $entry['wal_schema_cookie'];
        }
        foreach (($entry['wal_frames'] ?? []) as $frame) {
            if (($frame['page'] ?? null) === 1 && ($frame['commit'] ?? false) === true && isset($frame['schema_cookie']) && is_int($frame['schema_cookie'])) {
                $cookie = $frame['schema_cookie'];
            }
        }

        return $cookie;
    }

    /**
     * @param array<string,array{temp:bool}> $schemas
     * @return list<string>
     */
    private static function searchOrder(array $schemas): array
    {
        $order = [];
        if (isset($schemas['temp'])) {
            $order[] = 'temp';
        }
        if (isset($schemas['main'])) {
            $order[] = 'main';
        }
        foreach ($schemas as $schema => $_entry) {
            if ($schema !== 'temp' && $schema !== 'main') {
                $order[] = $schema;
            }
        }

        return $order;
    }

    /**
     * @param array<string,array<string,mixed>> $schemas
     * @param list<string> $order
     * @param list<array{name?:string, sql:string, active?:bool, read_only?:bool}> $statements
     * @return list<array{name:string,sql:string,active:bool,read_only:bool,tables:list<string>,schemas:list<string>,resolutions:array<string,array{schema:string,name:string,found:bool}>}>
     */
    private static function prepareStatements(array $schemas, array $order, array $statements): array
    {
        $prepared = [];
        foreach ($statements as $index => $statement) {
            $sql = trim((string) ($statement['sql'] ?? ''));
            if ($sql === '') {
                throw new \InvalidArgumentException('SQLite prepared statement SQL cannot be empty');
            }
            $tables = self::tables($sql);
            $resolutions = [];
            $schemasRead = [];
            foreach ($tables as $table) {
                $resolution = self::resolve($schemas, $order, $table);
                $resolutions[$table] = $resolution;
                if (!in_array($resolution['schema'], $schemasRead, true)) {
                    $schemasRead[] = $resolution['schema'];
                }
            }
            $prepared[] = [
                'name' => isset($statement['name']) && trim((string) $statement['name']) !== '' ? (string) $statement['name'] : 'stmt-' . $index,
                'sql' => $sql,
                'active' => (bool) ($statement['active'] ?? false),
                'read_only' => (bool) ($statement['read_only'] ?? self::readOnly($sql)),
                'tables' => $tables,
                'schemas' => $schemasRead,
                'resolutions' => $resolutions,
            ];
        }

        return $prepared;
    }

    /**
     * @return list<string>
     */
    private static function tables(string $sql): array
    {
        $tables = [];
        if (preg_match_all('/\b(?:from|join|update|into|table)\s+((?:"[^"]+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)(?:\s*\.\s*(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*))?)/i', $sql, $matches)) {
            foreach ($matches[1] as $raw) {
                $table = self::compoundName($raw);
                if (!in_array($table, $tables, true)) {
                    $tables[] = $table;
                }
            }
        }

        return $tables;
    }

    private static function compoundName(string $raw): string
    {
        $parts = preg_split('/\s*\.\s*/', trim($raw));
        if ($parts === false || $parts === []) {
            throw new \InvalidArgumentException('SQLite table name cannot be empty');
        }
        $names = array_map(static fn (string $part): string => self::name($part, 'SQLite table name'), $parts);
        if (count($names) > 2) {
            throw new \InvalidArgumentException('SQLite table name has too many qualifiers');
        }

        return implode('.', $names);
    }

    /**
     * @param array<string,array{tables:list<string>}> $schemas
     * @param list<string> $order
     * @return array{schema:string,name:string,found:bool}
     */
    private static function resolve(array $schemas, array $order, string $table): array
    {
        if (str_contains($table, '.')) {
            [$schema, $name] = explode('.', $table, 2);
            return [
                'schema' => isset($schemas[$schema]) ? $schema : '__detached__',
                'name' => $name,
                'found' => isset($schemas[$schema]) && in_array($name, $schemas[$schema]['tables'], true),
            ];
        }

        foreach ($order as $schema) {
            if (isset($schemas[$schema]) && in_array($table, $schemas[$schema]['tables'], true)) {
                return ['schema' => $schema, 'name' => $table, 'found' => true];
            }
        }

        return ['schema' => 'main', 'name' => $table, 'found' => false];
    }

    /**
     * @param array<string,array{schema_cookie:int,tables:list<string>,file:string|null,temp:bool}> $current
     * @param list<array<string,mixed>> $events
     * @return array{0:array<string,array{schema_cookie:int,tables:list<string>,file:string|null,temp:bool}>,1:list<array<string,mixed>>}
     */
    private static function applyEvents(array $current, array $events): array
    {
        $next = $current;
        $log = [];
        foreach ($events as $index => $event) {
            $op = strtolower(trim((string) ($event['op'] ?? '')));
            if ($op === 'attach') {
                $schema = self::name((string) ($event['schema'] ?? ''), 'SQLite ATTACH schema');
                if ($schema === 'main' || $schema === 'temp' || isset($next[$schema])) {
                    throw new \InvalidArgumentException("SQLite schema {$schema} cannot be attached");
                }
                $tables = [];
                foreach (($event['tables'] ?? []) as $table) {
                    $tables[] = self::name((string) $table, 'SQLite attached table');
                }
                sort($tables);
                $next[$schema] = [
                    'schema_cookie' => isset($event['schema_cookie']) ? self::integer($event['schema_cookie'], 'SQLite ATTACH schema cookie') : 1,
                    'tables' => array_values(array_unique($tables)),
                    'file' => isset($event['file']) ? (string) $event['file'] : null,
                    'temp' => false,
                ];
                $log[] = ['index' => $index, 'op' => 'attach', 'schema' => $schema, 'schema_cookie' => $next[$schema]['schema_cookie']];
                continue;
            }

            if ($op === 'detach') {
                $schema = self::name((string) ($event['schema'] ?? ''), 'SQLite DETACH schema');
                if ($schema === 'main' || $schema === 'temp' || !isset($next[$schema])) {
                    throw new \InvalidArgumentException("SQLite schema {$schema} cannot be detached");
                }
                unset($next[$schema]);
                $log[] = ['index' => $index, 'op' => 'detach', 'schema' => $schema, 'schema_cookie' => null];
                continue;
            }

            if ($op === 'schema_write' || $op === 'wal_commit') {
                $schema = self::name((string) ($event['schema'] ?? ''), 'SQLite schema write target');
                if (!isset($next[$schema])) {
                    throw new \InvalidArgumentException("SQLite schema {$schema} is not attached");
                }
                if (($event['commit'] ?? true) === true) {
                    $next[$schema]['schema_cookie'] = isset($event['schema_cookie'])
                        ? self::integer($event['schema_cookie'], 'SQLite schema cookie')
                        : $next[$schema]['schema_cookie'] + 1;
                    $table = $event['table'] ?? $event['object'] ?? null;
                    if (is_string($table) && trim($table) !== '') {
                        $name = self::name($table, 'SQLite schema object');
                        if (!in_array($name, $next[$schema]['tables'], true)) {
                            $next[$schema]['tables'][] = $name;
                            sort($next[$schema]['tables']);
                        }
                    }
                }
                $log[] = ['index' => $index, 'op' => $op, 'schema' => $schema, 'schema_cookie' => $next[$schema]['schema_cookie']];
                continue;
            }

            if ($op === 'drop_table') {
                $schema = self::name((string) ($event['schema'] ?? ''), 'SQLite DROP TABLE schema');
                $table = self::name((string) ($event['table'] ?? $event['object'] ?? ''), 'SQLite DROP TABLE name');
                if (!isset($next[$schema])) {
                    throw new \InvalidArgumentException("SQLite schema {$schema} is not attached");
                }
                $next[$schema]['schema_cookie']++;
                $next[$schema]['tables'] = array_values(array_filter(
                    $next[$schema]['tables'],
                    static fn (string $existing): bool => $existing !== $table,
                ));
                $log[] = ['index' => $index, 'op' => 'drop_table', 'schema' => $schema, 'table' => $table, 'schema_cookie' => $next[$schema]['schema_cookie']];
                continue;
            }

            throw new \InvalidArgumentException("SQLite attach WAL temp schema-cache next92 event {$op} is not supported");
        }

        uksort($next, static function (string $a, string $b): int {
            $rank = static fn (string $schema): int => $schema === 'temp' ? 0 : ($schema === 'main' ? 1 : 2);
            return [$rank($a), $a] <=> [$rank($b), $b];
        });

        return [$next, $log];
    }

    /**
     * @param array<string,array{schema_cookie:int}> $schemas
     * @return array<string,int>
     */
    private static function cookies(array $schemas): array
    {
        $cookies = [];
        foreach ($schemas as $schema => $entry) {
            $cookies[$schema] = $entry['schema_cookie'];
        }

        return $cookies;
    }

    /**
     * @param array<string,int> $currentCookies
     * @param array<string,int> $nextCookies
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<string>
     */
    private static function changedSchemas(array $currentCookies, array $nextCookies, array $current, array $next): array
    {
        $schemas = array_values(array_unique(array_merge(array_keys($currentCookies), array_keys($nextCookies))));
        $changed = [];
        foreach ($schemas as $schema) {
            if (($currentCookies[$schema] ?? null) !== ($nextCookies[$schema] ?? null) || !array_key_exists($schema, $current) || !array_key_exists($schema, $next)) {
                $changed[] = $schema;
            }
        }
        usort($changed, static function (string $a, string $b): int {
            $rank = static fn (string $schema): int => $schema === 'temp' ? 0 : ($schema === 'main' ? 1 : 2);
            return [$rank($a), $a] <=> [$rank($b), $b];
        });

        return $changed;
    }

    private static function action(bool $active, bool $readOnly, bool $requiresReprepare): string
    {
        if (!$requiresReprepare) {
            return 'reuse_prepared_statement_current_source';
        }
        if ($active) {
            return 'finish_current_source_then_sqlite_schema_on_reset';
        }
        if ($readOnly) {
            return 'sqlite_schema_then_reprepare_read_statement';
        }

        return 'sqlite_schema_before_write_retry';
    }

    private static function readOnly(string $sql): bool
    {
        return preg_match('/^\s*(?:select|with|pragma)\b/i', $sql) === 1;
    }

    private static function integer(mixed $value, string $label): int
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException("{$label} must be an integer");
        }

        return $value;
    }

    private static function name(string $name, string $label): string
    {
        $trimmed = trim($name);
        if (preg_match('/^(?:"([^"]+)"|`([^`]+)`|\[([^\]]+)\]|\'([^\']+)\')$/', $trimmed, $match) === 1) {
            $trimmed = $match[1] !== '' ? $match[1] : ($match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : $match[4]));
        }
        $normalized = strtolower(trim($trimmed));
        if ($normalized === '') {
            throw new \InvalidArgumentException("{$label} cannot be empty");
        }

        return $normalized;
    }
}
