<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachWalTempSchemaCookieSourcePlan
{
    /**
     * @param array<string,array{schema_cookie:int,wal_schema_cookie?:int|null,temp_schema_cookie?:int|null,wal_frames?:list<array{page:int,schema_cookie?:int|null,commit?:bool}>,tables?:list<string>,next_tables?:list<string>|null,file?:string|null,temp?:bool}> $schemas
     * @param list<array{name?:string,sql:string,active?:bool,read_only?:bool,source?:string}> $statements
     * @return array<string,mixed>
     */
    public static function plan(array $schemas, array $statements, string $sourceSchema = 'main'): array
    {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite ATTACH WAL temp schema-cookie source plan requires statements');
        }

        $normalized = self::normalizeSchemas($schemas);
        $source = self::normalizeName($sourceSchema, 'SQLite source schema');
        if (!isset($normalized[$source])) {
            throw new \InvalidArgumentException("SQLite source schema {$source} is not attached");
        }

        $order = self::searchOrder($normalized);
        $currentCookies = [];
        $nextCookies = [];
        $cookieSources = [];
        $changedSchemas = [];
        foreach ($order as $schemaName) {
            $current = self::currentCookie($normalized[$schemaName]);
            $next = self::nextCookie($normalized[$schemaName]);
            $currentCookies[$schemaName] = $current['cookie'];
            $nextCookies[$schemaName] = $next['cookie'];
            $cookieSources[$schemaName] = [
                'current_cookie' => $current['cookie'],
                'current_source' => $current['source'],
                'current_frame_index' => $current['frame_index'],
                'next_cookie' => $next['cookie'],
                'next_source' => $next['source'],
                'next_frame_index' => $next['frame_index'],
                'temp_uses_rollback_journal' => (bool) $normalized[$schemaName]['temp'],
                'wal_tail_ignored' => self::hasUncommittedPageOne($normalized[$schemaName]),
            ];
            if ($current['cookie'] !== $next['cookie']) {
                $changedSchemas[] = $schemaName;
            }
        }

        $plans = [];
        $expired = [];
        $stable = [];
        $retryableReads = [];
        $writeBlocked = [];
        $activeCurrent = [];
        foreach ($statements as $index => $statement) {
            $sql = (string) ($statement['sql'] ?? '');
            $name = self::statementName($statement, $index);
            $tables = self::statementTables($sql);
            $readOnly = $statement['read_only'] ?? self::statementReadOnly($sql);
            $active = (bool) ($statement['active'] ?? false);
            $statementSource = self::statementSource($statement, $source, $normalized);
            $transitions = [];
            $requiresReprepare = false;
            $prepareSchemas = [];
            $nextSchemas = [];
            foreach ($tables as $table) {
                $currentResolution = self::resolveTable($normalized, self::sourceSearchOrder($order, $statementSource), $table, false);
                $nextResolution = self::resolveTable($normalized, self::sourceSearchOrder($order, $statementSource), $table, true);
                $prepareCookie = $currentCookies[$currentResolution['schema']] ?? null;
                $nextCookie = $nextCookies[$nextResolution['schema']] ?? null;
                $changed = $currentResolution['schema'] !== $nextResolution['schema']
                    || $currentResolution['found'] !== $nextResolution['found']
                    || $currentResolution['name'] !== $nextResolution['name']
                    || $prepareCookie !== $nextCookie;
                $requiresReprepare = $requiresReprepare || $changed;
                if (!in_array($currentResolution['schema'], $prepareSchemas, true)) {
                    $prepareSchemas[] = $currentResolution['schema'];
                }
                if (!in_array($nextResolution['schema'], $nextSchemas, true)) {
                    $nextSchemas[] = $nextResolution['schema'];
                }
                $transitions[] = [
                    'table' => $table,
                    'prepare_source_schema' => $statementSource,
                    'prepare_schema' => $currentResolution['schema'],
                    'next_schema' => $nextResolution['schema'],
                    'prepare_found' => $currentResolution['found'],
                    'next_found' => $nextResolution['found'],
                    'prepare_schema_cookie' => $prepareCookie,
                    'next_schema_cookie' => $nextCookie,
                    'prepare_cookie_source' => $cookieSources[$currentResolution['schema']]['current_source'] ?? null,
                    'next_cookie_source' => $cookieSources[$nextResolution['schema']]['next_source'] ?? null,
                    'resolution_changed' => $currentResolution['schema'] !== $nextResolution['schema']
                        || $currentResolution['found'] !== $nextResolution['found']
                        || $currentResolution['name'] !== $nextResolution['name'],
                    'requires_reprepare' => $changed,
                ];
            }

            if ($requiresReprepare) {
                $expired[] = $name;
                if ($active) {
                    $activeCurrent[] = $name;
                }
                if ($readOnly) {
                    $retryableReads[] = $name;
                } else {
                    $writeBlocked[] = $name;
                }
            } else {
                $stable[] = $name;
            }

            $plans[] = [
                'name' => $name,
                'sql' => $sql,
                'prepare_source_schema' => $statementSource,
                'tables' => $tables,
                'read_only' => $readOnly,
                'active' => $active,
                'prepare_schemas' => $prepareSchemas,
                'next_schemas' => $nextSchemas,
                'schema_transitions' => $transitions,
                'requires_reprepare' => $requiresReprepare,
                'sqlite_result_on_current_step' => $active ? 'SQLITE_OK' : ($requiresReprepare ? 'SQLITE_SCHEMA' : 'SQLITE_OK'),
                'next_step_action' => self::nextAction($requiresReprepare, $active, $readOnly),
            ];
        }

        return [
            'status' => $expired === [] ? 'schema_cache_stable' : 'schema_cache_expired',
            'operation' => 'attach-wal-temp-schema-cookie-current-source-next87',
            'source' => $source,
            'search_order' => $order,
            'schema_cookies_current' => $currentCookies,
            'schema_cookies_next' => $nextCookies,
            'schema_cookie_sources' => $cookieSources,
            'changed_schemas' => $changedSchemas,
            'statement_count' => count($plans),
            'statements' => $plans,
            'expired_statements' => $expired,
            'stable_statements' => $stable,
            'active_current_snapshot_statements' => $activeCurrent,
            'retryable_read_statements' => $retryableReads,
            'write_statements_blocked_before_retry' => $writeBlocked,
            'requires_reprepare' => $expired !== [],
            'database_list' => self::databaseList($normalized, $order, $currentCookies, $nextCookies),
            'dependencies' => [
                'sqlite-attach-wal-temp-schema-cookie-current-source-next87',
                'sqlite-wal-page-one-schema-cookie',
                'sqlite-temp-schema-cookie-rollback-journal',
            ],
        ];
    }

    /**
     * @param array<string,array{schema_cookie:int,wal_schema_cookie?:int|null,temp_schema_cookie?:int|null,wal_frames?:list<array{page:int,schema_cookie?:int|null,commit?:bool}>,tables?:list<string>,next_tables?:list<string>|null,file?:string|null,temp?:bool}> $schemas
     * @param list<array{name?:string,sql:string,active?:bool,read_only?:bool}> $statements
     * @return array<string,mixed>
     */
    public static function currentSourceNext94(array $schemas, array $statements, string $sourceSchema = 'main'): array
    {
        $plan = self::plan($schemas, $statements, $sourceSchema);
        $plan['operation'] = 'attach-temp-wal-schema-cookie-current-source-next94';
        $plan['dependencies'][0] = 'sqlite-attach-temp-wal-schema-cookie-current-source-next94';
        $plan['dependencies'][] = 'sqlite-bracket-quoted-attach-schema-cookie-source';
        $plan['dependencies'][] = 'sqlite-schema-table-alias-cookie-source';

        return $plan;
    }

    /**
     * @param array<string,array{schema_cookie:int,wal_schema_cookie?:int|null,temp_schema_cookie?:int|null,wal_frames?:list<array{page:int,schema_cookie?:int|null,commit?:bool}>,tables?:list<string>,next_tables?:list<string>|null,file?:string|null,temp?:bool}> $schemas
     * @param list<array{name?:string,sql:string,active?:bool,read_only?:bool}> $statements
     * @return array<string,mixed>
     */
    public static function currentSourceNext99(array $schemas, array $statements, string $sourceSchema = 'main'): array
    {
        $plan = self::plan($schemas, $statements, $sourceSchema);
        $plan['operation'] = 'attach-temp-wal-schema-cookie-current-source-next99';
        $plan['dependencies'][0] = 'sqlite-attach-temp-wal-schema-cookie-current-source-next99';
        $plan['dependencies'][] = 'sqlite-with-cte-schema-cookie-source-filter';
        $plan['dependencies'][] = 'sqlite-recursive-cte-attach-temp-wal-source';

        return $plan;
    }

    /**
     * @param array<string,array<string,mixed>> $schemas
     * @return array<string,array{schema_cookie:int,wal_schema_cookie:int|null,temp_schema_cookie:int|null,wal_frames:list<array{page:int,schema_cookie?:int|null,commit?:bool}>,tables:list<string>,next_tables:list<string>|null,file:string|null,temp:bool}>
     */
    private static function normalizeSchemas(array $schemas): array
    {
        $normalized = [];
        foreach ($schemas as $schema => $entry) {
            $name = self::normalizeName((string) $schema, 'SQLite schema');
            if (!isset($entry['schema_cookie']) || !is_int($entry['schema_cookie'])) {
                throw new \InvalidArgumentException("SQLite schema {$name} requires an integer schema cookie");
            }
            $walFrames = [];
            foreach ($entry['wal_frames'] ?? [] as $frame) {
                if (!isset($frame['page']) || !is_int($frame['page'])) {
                    throw new \InvalidArgumentException("SQLite WAL frame for {$name} requires an integer page");
                }
                $walFrames[] = $frame;
            }
            $normalized[$name] = [
                'schema_cookie' => $entry['schema_cookie'],
                'wal_schema_cookie' => $entry['wal_schema_cookie'] ?? null,
                'temp_schema_cookie' => $entry['temp_schema_cookie'] ?? null,
                'wal_frames' => $walFrames,
                'tables' => array_values(array_map([self::class, 'plainName'], $entry['tables'] ?? [])),
                'next_tables' => array_key_exists('next_tables', $entry) && $entry['next_tables'] !== null
                    ? array_values(array_map([self::class, 'plainName'], $entry['next_tables']))
                    : null,
                'file' => $entry['file'] ?? null,
                'temp' => (bool) ($entry['temp'] ?? $name === 'temp'),
            ];
        }

        foreach (['main', 'temp'] as $schema) {
            $normalized[$schema] ??= [
                'schema_cookie' => 0,
                'wal_schema_cookie' => null,
                'temp_schema_cookie' => null,
                'wal_frames' => [],
                'tables' => [],
                'next_tables' => null,
                'file' => $schema === 'temp' ? '' : null,
                'temp' => $schema === 'temp',
            ];
        }

        return $normalized;
    }

    /**
     * @param array{schema_cookie:int,wal_schema_cookie:int|null,temp_schema_cookie:int|null,wal_frames:list<array{page:int,schema_cookie?:int|null,commit?:bool}>,temp:bool} $schema
     * @return array{cookie:int,source:string,frame_index:int|null}
     */
    private static function currentCookie(array $schema): array
    {
        if ($schema['temp']) {
            return ['cookie' => $schema['schema_cookie'], 'source' => 'temp_schema_cookie', 'frame_index' => null];
        }

        $cookie = $schema['schema_cookie'];
        $source = 'database_header';
        $frameIndex = null;
        foreach ($schema['wal_frames'] as $index => $frame) {
            if (($frame['commit'] ?? true) === false || $frame['page'] !== 1 || !isset($frame['schema_cookie']) || !is_int($frame['schema_cookie'])) {
                continue;
            }
            $cookie = $frame['schema_cookie'];
            $source = 'wal_page1_frame';
            $frameIndex = $index;
        }

        return ['cookie' => $cookie, 'source' => $source, 'frame_index' => $frameIndex];
    }

    /**
     * @param array{schema_cookie:int,wal_schema_cookie:int|null,temp_schema_cookie:int|null,wal_frames:list<array{page:int,schema_cookie?:int|null,commit?:bool}>,temp:bool} $schema
     * @return array{cookie:int,source:string,frame_index:int|null}
     */
    private static function nextCookie(array $schema): array
    {
        if ($schema['temp']) {
            if ($schema['temp_schema_cookie'] !== null) {
                return ['cookie' => $schema['temp_schema_cookie'], 'source' => 'temp_rollback_journal', 'frame_index' => null];
            }

            return self::currentCookie($schema);
        }

        if ($schema['wal_schema_cookie'] !== null) {
            return ['cookie' => $schema['wal_schema_cookie'], 'source' => 'wal_commit_header', 'frame_index' => null];
        }

        return self::currentCookie($schema);
    }

    /**
     * @param array{wal_frames:list<array{page:int,schema_cookie?:int|null,commit?:bool}>} $schema
     */
    private static function hasUncommittedPageOne(array $schema): bool
    {
        foreach ($schema['wal_frames'] as $frame) {
            if (($frame['commit'] ?? true) === false && $frame['page'] === 1 && isset($frame['schema_cookie'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,array<string,mixed>> $schemas
     * @return list<string>
     */
    private static function searchOrder(array $schemas): array
    {
        $order = ['temp', 'main'];
        foreach ($schemas as $name => $_schema) {
            if ($name !== 'temp' && $name !== 'main') {
                $order[] = $name;
            }
        }

        return $order;
    }

    /**
     * @param array<string,array{tables:list<string>,next_tables:list<string>|null}> $schemas
     * @param list<string> $order
     * @return array{schema:string,name:string,qualified:bool,found:bool}
     */
    private static function resolveTable(array $schemas, array $order, string $table, bool $next): array
    {
        $parts = explode('.', $table, 2);
        if (count($parts) === 2) {
            $schema = self::plainName($parts[0]);
            $name = self::plainName($parts[1]);
            return [
                'schema' => $schema,
                'name' => $name,
                'qualified' => true,
                'found' => self::tableExists($schemas[$schema] ?? null, $name, $next),
            ];
        }

        $name = self::plainName($table);
        foreach ($order as $schema) {
            if (self::tableExists($schemas[$schema] ?? null, $name, $next)) {
                return ['schema' => $schema, 'name' => $name, 'qualified' => false, 'found' => true];
            }
        }

        return ['schema' => 'main', 'name' => $name, 'qualified' => false, 'found' => false];
    }

    /**
     * @param list<string> $order
     * @return list<string>
     */
    private static function sourceSearchOrder(array $order, string $source): array
    {
        if ($source === 'main' || $source === 'temp') {
            return $order;
        }

        $sourceOrder = ['temp', $source, 'main'];
        foreach ($order as $schema) {
            if (!in_array($schema, $sourceOrder, true)) {
                $sourceOrder[] = $schema;
            }
        }

        return $sourceOrder;
    }

    /**
     * @param array<string,array<string,mixed>> $schemas
     */
    private static function statementSource(array $statement, string $defaultSource, array $schemas): string
    {
        $source = isset($statement['source'])
            ? self::normalizeName((string) $statement['source'], 'SQLite prepared statement source schema')
            : $defaultSource;
        if (!isset($schemas[$source])) {
            throw new \InvalidArgumentException("SQLite prepared statement source schema {$source} is not attached");
        }

        return $source;
    }

    /**
     * @param array{tables:list<string>,next_tables:list<string>|null}|null $schema
     */
    private static function tableExists(?array $schema, string $table, bool $next): bool
    {
        if ($schema === null) {
            return false;
        }
        $tables = $next ? ($schema['next_tables'] ?? $schema['tables']) : $schema['tables'];

        return in_array(self::plainName($table), $tables, true);
    }

    /**
     * @return list<string>
     */
    private static function statementTables(string $sql): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($sql));
        if ($normalized === null || $normalized === '') {
            throw new \InvalidArgumentException('SQLite prepared SQL statement cannot be empty');
        }

        $cteNames = self::cteNames($normalized);
        $tables = [];
        $patterns = [
            '/\b(?:FROM|JOIN|INTO|UPDATE)\s+((?:"[^"]+"|`[^`]+`|\'[^\']+\'|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)(?:\s*\.\s*(?:"[^"]+"|`[^`]+`|\'[^\']+\'|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*))?)/i',
            '/\bDELETE\s+FROM\s+((?:"[^"]+"|`[^`]+`|\'[^\']+\'|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)(?:\s*\.\s*(?:"[^"]+"|`[^`]+`|\'[^\']+\'|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*))?)/i',
        ];
        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $normalized, $matches)) {
                continue;
            }
            foreach ($matches[1] as $match) {
                $table = self::normalizeSqlName($match);
                if (!str_contains($table, '.') && in_array($table, $cteNames, true)) {
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
     * @return list<string>
     */
    private static function cteNames(string $sql): array
    {
        if (preg_match('/^\s*WITH\s+(?:RECURSIVE\s+)?/i', $sql, $match) !== 1) {
            return [];
        }

        $offset = strlen($match[0]);
        $names = [];
        $length = strlen($sql);
        while ($offset < $length) {
            $remaining = substr($sql, $offset);
            if (preg_match('/^\s*((?:"[^"]+"|`[^`]+`|\'[^\']+\'|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*))/i', $remaining, $nameMatch) !== 1) {
                break;
            }
            $name = self::plainName($nameMatch[1]);
            if (!in_array($name, $names, true)) {
                $names[] = $name;
            }
            $offset += strpos($remaining, $nameMatch[1]) + strlen($nameMatch[1]);
            $afterName = substr($sql, $offset);
            if (preg_match('/^(?:\s*\([^)]*\))?\s+AS\s*\(/i', $afterName, $asMatch) !== 1) {
                break;
            }
            $offset += strlen($asMatch[0]) - 1;
            $depth = 0;
            for (; $offset < $length; ++$offset) {
                $char = $sql[$offset];
                if ($char === '(') {
                    ++$depth;
                } elseif ($char === ')') {
                    --$depth;
                    if ($depth === 0) {
                        ++$offset;
                        break;
                    }
                }
            }
            while ($offset < $length && ctype_space($sql[$offset])) {
                ++$offset;
            }
            if (($sql[$offset] ?? '') !== ',') {
                break;
            }
            ++$offset;
        }

        return $names;
    }

    private static function statementName(array $statement, int $index): string
    {
        $name = trim((string) ($statement['name'] ?? 'statement-' . $index));
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite prepared statement name cannot be empty');
        }

        return $name;
    }

    private static function statementReadOnly(string $sql): bool
    {
        if (preg_match('/^\s*WITH\b.*\b(?:INSERT|UPDATE|DELETE)\b/is', $sql) === 1) {
            return false;
        }

        return preg_match('/^\s*(?:SELECT|WITH|PRAGMA)\b/i', $sql) === 1;
    }

    private static function nextAction(bool $requiresReprepare, bool $active, bool $readOnly): string
    {
        if (!$requiresReprepare) {
            return 'reuse_prepared_statement';
        }
        if ($active) {
            return 'finish_current_snapshot_then_sqlite_schema_on_reset';
        }
        if ($readOnly) {
            return 'sqlite_schema_then_reprepare';
        }

        return 'sqlite_schema_before_write_retry';
    }

    private static function normalizeSqlName(string $name): string
    {
        $parts = preg_split('/\s*\.\s*/', trim($name));
        if ($parts === false || $parts === []) {
            throw new \InvalidArgumentException('SQLite table name cannot be empty');
        }

        return implode('.', array_map([self::class, 'plainName'], $parts));
    }

    private static function plainName(string $name): string
    {
        return self::normalizeName($name, 'SQLite schema or table name');
    }

    private static function normalizeName(string $name, string $label): string
    {
        $trimmed = trim($name, " \t\r\n`'\"[]");
        if ($trimmed === '') {
            throw new \InvalidArgumentException("{$label} cannot be empty");
        }

        $lower = strtolower($trimmed);
        if ($lower === 'sqlite_master') {
            return 'sqlite_schema';
        }

        return $lower;
    }

    /**
     * @param array<string,array{file:string|null}> $schemas
     * @param list<string> $order
     * @param array<string,int> $currentCookies
     * @param array<string,int> $nextCookies
     * @return list<array{seq:int,name:string,file:string|null,schema_cookie:int,next_schema_cookie:int}>
     */
    private static function databaseList(array $schemas, array $order, array $currentCookies, array $nextCookies): array
    {
        $rows = [];
        foreach ($order as $seq => $schema) {
            $rows[] = [
                'seq' => $seq,
                'name' => $schema,
                'file' => $schemas[$schema]['file'],
                'schema_cookie' => $currentCookies[$schema],
                'next_schema_cookie' => $nextCookies[$schema],
            ];
        }

        return $rows;
    }
}
