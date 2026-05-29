<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachTempMainWalSchemaCachePlan
{
    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, cache?:string|null}> $schemas
     * @param list<string> $preparedTables
     * @return array<string,mixed>
     */
    public static function currentNext(array $schemas, array $preparedTables = ['wp_options'], string $sourceSchema = 'main'): array
    {
        $normalized = self::normalizeSchemas($schemas);
        $source = self::normalizeName($sourceSchema);
        if (!isset($normalized[$source])) {
            throw new \InvalidArgumentException("SQLite source schema {$source} is not attached");
        }

        $order = self::searchOrder($normalized);
        $current = [];
        $next = [];
        $changed = [];
        foreach ($order as $schemaName) {
            $schema = $normalized[$schemaName];
            $currentCookie = $schema['schema_cookie'];
            $nextCookie = self::nextSchemaCookie($schema);
            $current[$schemaName] = $currentCookie;
            $next[$schemaName] = $nextCookie;
            if ($nextCookie !== $currentCookie) {
                $changed[] = $schemaName;
            }
        }

        $resolutions = [];
        $reprepare = false;
        foreach ($preparedTables as $table) {
            $resolution = self::resolvePreparedTable($normalized, $order, $table);
            $resolution['schema_cookie'] = $current[$resolution['schema']] ?? null;
            $resolution['next_schema_cookie'] = $next[$resolution['schema']] ?? null;
            $resolution['requires_reprepare'] = in_array($resolution['schema'], $changed, true);
            $resolution['shadowed_schemas'] = self::shadowedSchemas($normalized, $order, $table, $resolution['schema']);
            $resolutions[$table] = $resolution;
            $reprepare = $reprepare || $resolution['requires_reprepare'];
        }

        $walDependencies = [];
        foreach ($normalized as $schemaName => $schema) {
            if (($schema['wal_schema_cookie'] ?? null) !== null || $schema['wal_frames'] !== []) {
                $walDependencies[] = $schemaName;
            }
        }

        return [
            'status' => 'ok',
            'operation' => 'attach-temp-main-wal-schema-cache',
            'source' => $source,
            'search_order' => $order,
            'schema_cookies_current' => $current,
            'schema_cookies_next' => $next,
            'changed_schemas' => $changed,
            'prepared_tables' => $resolutions,
            'requires_reprepare' => $reprepare,
            'temp_shadows_main' => self::tableExists($normalized['temp'] ?? null, 'wp_options')
                && self::tableExists($normalized['main'] ?? null, 'wp_options'),
            'wal_schema_cookie_sources' => $walDependencies,
            'database_list' => self::databaseList($normalized, $order),
            'dependencies' => [
                'attach-temp-main-wal-schema-cache',
                'sqlite-wal-page-one-schema-cookie',
                'sqlite-temp-main-name-resolution',
            ],
        ];
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, cache?:string|null}> $schemas
     * @param list<string> $statements
     * @return array<string,mixed>
     */
    public static function currentNextSql(array $schemas, array $statements, string $sourceSchema = 'main'): array
    {
        $preparedTables = [];
        $statementPlans = [];
        foreach ($statements as $offset => $sql) {
            if (!is_string($sql) || trim($sql) === '') {
                throw new \InvalidArgumentException('SQLite prepared SQL statement cannot be empty');
            }

            $tables = self::statementTables($sql);
            foreach ($tables as $table) {
                $preparedTables[] = $table;
            }
            $statementPlans[(string) $offset] = [
                'sql' => $sql,
                'tables' => $tables,
                'read_only' => self::statementReadOnly($sql),
            ];
        }

        $plan = self::currentNext($schemas, array_values(array_unique($preparedTables)), $sourceSchema);
        foreach ($statementPlans as $offset => $statementPlan) {
            $tables = [];
            $reprepare = false;
            $schemasRead = [];
            foreach ($statementPlan['tables'] as $table) {
                $resolution = $plan['prepared_tables'][$table];
                $tables[$table] = $resolution;
                $reprepare = $reprepare || $resolution['requires_reprepare'];
                if (!in_array($resolution['schema'], $schemasRead, true)) {
                    $schemasRead[] = $resolution['schema'];
                }
            }

            $statementPlans[$offset]['resolved_tables'] = $tables;
            $statementPlans[$offset]['schemas'] = $schemasRead;
            $statementPlans[$offset]['requires_reprepare'] = $reprepare;
        }

        $plan['operation'] = 'attach-temp-main-wal-schema-cache-sql';
        $plan['statement_count'] = count($statementPlans);
        $plan['statements'] = $statementPlans;
        $plan['dependencies'][] = 'sqlite-attach-temp-wal-schema-cache-sql';

        return $plan;
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, next_tables?:list<string>|null, indexes?:list<string>, next_indexes?:list<string>|null, file?:string|null, cache?:string|null}> $schemas
     * @param list<string> $preparedTables
     * @return array<string,mixed>
     */
    public static function currentNextObjects(array $schemas, array $preparedTables = ['wp_options'], string $sourceSchema = 'main'): array
    {
        $normalized = self::normalizeSchemas($schemas);
        foreach ($schemas as $name => $schema) {
            $schemaName = self::normalizeName((string) $name);
            if (array_key_exists('next_tables', $schema) && $schema['next_tables'] !== null) {
                $normalized[$schemaName]['next_tables'] = array_values(array_map([self::class, 'normalizeName'], $schema['next_tables']));
            }
            if (array_key_exists('next_indexes', $schema) && $schema['next_indexes'] !== null) {
                $normalized[$schemaName]['next_indexes'] = array_values(array_map([self::class, 'normalizeName'], $schema['next_indexes']));
            }
        }

        $source = self::normalizeName($sourceSchema);
        if (!isset($normalized[$source])) {
            throw new \InvalidArgumentException("SQLite source schema {$source} is not attached");
        }

        $order = self::searchOrder($normalized);
        $current = [];
        $next = [];
        $changed = [];
        $objectChanged = [];
        foreach ($order as $schemaName) {
            $schema = $normalized[$schemaName];
            $currentCookie = $schema['schema_cookie'];
            $nextCookie = self::nextSchemaCookie($schema);
            $current[$schemaName] = $currentCookie;
            $next[$schemaName] = $nextCookie;
            if ($nextCookie !== $currentCookie) {
                $changed[] = $schemaName;
            }
            if (self::schemaObjectsChanged($schema)) {
                $objectChanged[] = $schemaName;
            }
        }

        $resolutions = [];
        $reprepare = false;
        foreach ($preparedTables as $table) {
            $currentResolution = self::resolvePreparedTable($normalized, $order, $table);
            $nextResolution = self::resolvePreparedTable($normalized, $order, $table, true);
            $changedResolution = $currentResolution['schema'] !== $nextResolution['schema']
                || $currentResolution['found'] !== $nextResolution['found']
                || $currentResolution['name'] !== $nextResolution['name'];
            $requiresReprepare = $changedResolution
                || in_array($currentResolution['schema'], $changed, true)
                || in_array($nextResolution['schema'], $changed, true)
                || in_array($currentResolution['schema'], $objectChanged, true)
                || in_array($nextResolution['schema'], $objectChanged, true);

            $resolutions[$table] = [
                'current' => $currentResolution + [
                    'schema_cookie' => $current[$currentResolution['schema']] ?? null,
                    'shadowed_schemas' => self::shadowedSchemas($normalized, $order, $table, $currentResolution['schema']),
                ],
                'next' => $nextResolution + [
                    'schema_cookie' => $next[$nextResolution['schema']] ?? null,
                    'shadowed_schemas' => self::shadowedSchemas($normalized, $order, $table, $nextResolution['schema'], true),
                ],
                'resolution_changed' => $changedResolution,
                'requires_reprepare' => $requiresReprepare,
            ];
            $reprepare = $reprepare || $requiresReprepare;
        }

        return [
            'status' => 'ok',
            'operation' => 'attach-temp-main-wal-schema-cache-objects',
            'source' => $source,
            'search_order' => $order,
            'schema_cookies_current' => $current,
            'schema_cookies_next' => $next,
            'changed_schemas' => $changed,
            'object_changed_schemas' => $objectChanged,
            'prepared_tables' => $resolutions,
            'requires_reprepare' => $reprepare,
            'database_list' => self::databaseList($normalized, $order),
            'dependencies' => [
                'sqlite-attach-temp-main-wal-schema-cache',
                'sqlite-wal-ddl-object-resolution',
                'sqlite-temp-main-name-resolution',
            ],
        ];
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, cache?:string|null}> $schemas
     * @return array<string,array{schema_cookie:int, wal_schema_cookie:int|null, wal_frames:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables:list<string>, indexes:list<string>, file:string|null, cache:string|null}>
     */
    private static function normalizeSchemas(array $schemas): array
    {
        $normalized = [];
        foreach ($schemas as $name => $schema) {
            $schemaName = self::normalizeName((string) $name);
            if (!isset($schema['schema_cookie']) || !is_int($schema['schema_cookie'])) {
                throw new \InvalidArgumentException("SQLite schema {$schemaName} requires an integer schema cookie");
            }

            $walFrames = [];
            foreach ($schema['wal_frames'] ?? [] as $frame) {
                if (!isset($frame['page']) || !is_int($frame['page'])) {
                    throw new \InvalidArgumentException("SQLite WAL frame for {$schemaName} requires an integer page");
                }
                $walFrames[] = $frame;
            }

            $normalized[$schemaName] = [
                'schema_cookie' => $schema['schema_cookie'],
                'wal_schema_cookie' => $schema['wal_schema_cookie'] ?? null,
                'wal_frames' => $walFrames,
                'tables' => array_values(array_map([self::class, 'normalizeName'], $schema['tables'] ?? [])),
                'indexes' => array_values(array_map([self::class, 'normalizeName'], $schema['indexes'] ?? [])),
                'file' => $schema['file'] ?? null,
                'cache' => $schema['cache'] ?? null,
            ];
        }

        foreach (['main', 'temp'] as $required) {
            $normalized[$required] ??= [
                'schema_cookie' => 0,
                'wal_schema_cookie' => null,
                'wal_frames' => [],
                'tables' => [],
                'indexes' => [],
                'file' => $required === 'temp' ? '' : null,
                'cache' => null,
            ];
        }

        return $normalized;
    }

    /**
     * @param array{schema_cookie:int, wal_schema_cookie:int|null, wal_frames:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables:list<string>, indexes:list<string>, file:string|null, cache:string|null} $schema
     */
    private static function nextSchemaCookie(array $schema): int
    {
        $cookie = $schema['schema_cookie'];
        foreach ($schema['wal_frames'] as $frame) {
            if (($frame['commit'] ?? true) === false || $frame['page'] !== 1) {
                continue;
            }
            if (isset($frame['schema_cookie']) && is_int($frame['schema_cookie'])) {
                $cookie = $frame['schema_cookie'];
            }
        }

        if ($schema['wal_schema_cookie'] !== null) {
            $cookie = $schema['wal_schema_cookie'];
        }

        return $cookie;
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie:int|null, wal_frames:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables:list<string>, indexes:list<string>, file:string|null, cache:string|null}> $schemas
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
     * @param array<string,array{schema_cookie:int, wal_schema_cookie:int|null, wal_frames:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables:list<string>, indexes:list<string>, file:string|null, cache:string|null}> $schemas
     * @param list<string> $order
     * @return array{schema:string, name:string, qualified:bool, found:bool}
     */
    private static function resolvePreparedTable(array $schemas, array $order, string $table, bool $next = false): array
    {
        $parts = explode('.', $table, 2);
        if (count($parts) === 2) {
            $schema = self::normalizeName($parts[0]);
            $name = self::normalizeName($parts[1]);
            return [
                'schema' => $schema,
                'name' => $name,
                'qualified' => true,
                'found' => self::tableExists($schemas[$schema] ?? null, $name, $next),
            ];
        }

        $name = self::normalizeName($table);
        foreach ($order as $schema) {
            if (self::tableExists($schemas[$schema] ?? null, $name, $next)) {
                return [
                    'schema' => $schema,
                    'name' => $name,
                    'qualified' => false,
                    'found' => true,
                ];
            }
        }

        return [
            'schema' => 'main',
            'name' => $name,
            'qualified' => false,
            'found' => false,
        ];
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie:int|null, wal_frames:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables:list<string>, indexes:list<string>, file:string|null, cache:string|null}> $schemas
     * @param list<string> $order
     * @return list<string>
     */
    private static function shadowedSchemas(array $schemas, array $order, string $table, string $winner, bool $next = false): array
    {
        $parts = explode('.', $table, 2);
        if (count($parts) === 2) {
            return [];
        }

        $name = self::normalizeName($table);
        $shadowed = [];
        $seenWinner = false;
        foreach ($order as $schema) {
            if ($schema === $winner) {
                $seenWinner = true;
                continue;
            }
            if ($seenWinner && self::tableExists($schemas[$schema] ?? null, $name, $next)) {
                $shadowed[] = $schema;
            }
        }

        return $shadowed;
    }

    /**
     * @param array{tables:list<string>, next_tables?:list<string>}|null $schema
     */
    private static function tableExists(?array $schema, string $table, bool $next = false): bool
    {
        if ($schema === null) {
            return false;
        }

        $tables = $next ? ($schema['next_tables'] ?? $schema['tables']) : $schema['tables'];

        return in_array(self::normalizeName($table), $tables, true);
    }

    /**
     * @param array{tables:list<string>, indexes:list<string>, next_tables?:list<string>, next_indexes?:list<string>} $schema
     */
    private static function schemaObjectsChanged(array $schema): bool
    {
        $nextTables = $schema['next_tables'] ?? $schema['tables'];
        $nextIndexes = $schema['next_indexes'] ?? $schema['indexes'];

        return self::sortedNames($schema['tables']) !== self::sortedNames($nextTables)
            || self::sortedNames($schema['indexes']) !== self::sortedNames($nextIndexes);
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    private static function sortedNames(array $names): array
    {
        $normalized = array_values(array_map([self::class, 'normalizeName'], $names));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie:int|null, wal_frames:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables:list<string>, indexes:list<string>, file:string|null, cache:string|null}> $schemas
     * @param list<string> $order
     * @return list<array{seq:int, name:string, file:string|null, schema_cookie:int, next_schema_cookie:int, cache:string|null}>
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
                'next_schema_cookie' => self::nextSchemaCookie($schemas[$schema]),
                'cache' => $schemas[$schema]['cache'],
            ];
        }

        return $rows;
    }

    private static function normalizeName(string $name): string
    {
        $trimmed = trim($name, " \t\r\n`'\"[]");
        if ($trimmed === '') {
            throw new \InvalidArgumentException('SQLite schema or table name cannot be empty');
        }

        return strtolower($trimmed);
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

    private static function normalizeSqlName(string $name): string
    {
        $parts = preg_split('/\s*\.\s*/', trim($name));
        if ($parts === false || $parts === []) {
            throw new \InvalidArgumentException('SQLite table name cannot be empty');
        }

        return implode('.', array_map([self::class, 'normalizeName'], $parts));
    }
}
