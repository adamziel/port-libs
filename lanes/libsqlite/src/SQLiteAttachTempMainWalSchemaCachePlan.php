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
                'attach-temp-main-wal-schema-cache-current-next',
                'sqlite-wal-page-one-schema-cookie',
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
    private static function resolvePreparedTable(array $schemas, array $order, string $table): array
    {
        $parts = explode('.', $table, 2);
        if (count($parts) === 2) {
            $schema = self::normalizeName($parts[0]);
            $name = self::normalizeName($parts[1]);
            return [
                'schema' => $schema,
                'name' => $name,
                'qualified' => true,
                'found' => self::tableExists($schemas[$schema] ?? null, $name),
            ];
        }

        $name = self::normalizeName($table);
        foreach ($order as $schema) {
            if (self::tableExists($schemas[$schema] ?? null, $name)) {
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
    private static function shadowedSchemas(array $schemas, array $order, string $table, string $winner): array
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
            if ($seenWinner && self::tableExists($schemas[$schema] ?? null, $name)) {
                $shadowed[] = $schema;
            }
        }

        return $shadowed;
    }

    /**
     * @param array{tables:list<string>}|null $schema
     */
    private static function tableExists(?array $schema, string $table): bool
    {
        return $schema !== null && in_array(self::normalizeName($table), $schema['tables'], true);
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
        $trimmed = trim($name, " \t\r\n`'\"");
        if ($trimmed === '') {
            throw new \InvalidArgumentException('SQLite schema or table name cannot be empty');
        }

        return strtolower($trimmed);
    }
}
