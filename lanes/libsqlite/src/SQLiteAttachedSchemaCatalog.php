<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachedSchemaCatalog
{
    /** @var array<string,array{name: string, file: string|null, records: list<SQLiteSchemaRecord>, sequence: int}> */
    private array $schemas = [];

    /** @var list<string> */
    private array $attachedOrder = [];

    private int $schemaGeneration = 0;

    /** @var array<string,array{generation: int, result: array{schema: string, record: SQLiteSchemaRecord}|null}> */
    private array $lookupCache = [];

    private int $lookupCacheHits = 0;

    private int $lookupCacheMisses = 0;

    /**
     * @param list<SQLiteSchemaRecord> $mainRecords
     * @param list<SQLiteSchemaRecord> $tempRecords
     */
    public function __construct(array $mainRecords = [], array $tempRecords = [])
    {
        $this->schemas['main'] = [
            'name' => 'main',
            'file' => null,
            'records' => $mainRecords,
            'sequence' => 0,
        ];
        $this->schemas['temp'] = [
            'name' => 'temp',
            'file' => '',
            'records' => $tempRecords,
            'sequence' => 1,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    public function attach(string $schemaName, string $fileName, array $records): void
    {
        $name = self::normalizeSchemaName($schemaName);
        if ($name === 'main' || $name === 'temp') {
            throw new \InvalidArgumentException('SQLite ATTACH schema name cannot be main or temp');
        }
        if (isset($this->schemas[$name])) {
            throw new \InvalidArgumentException("SQLite ATTACH schema {$name} is already in use");
        }

        $this->attachedOrder[] = $name;
        $this->schemas[$name] = [
            'name' => $name,
            'file' => $fileName,
            'records' => array_values($records),
            'sequence' => count($this->attachedOrder) + 1,
        ];
        $this->invalidateLookupCache();
    }

    public function detach(string $schemaName): void
    {
        $name = self::normalizeSchemaName($schemaName);
        if ($name === 'main' || $name === 'temp') {
            throw new \InvalidArgumentException('SQLite DETACH cannot detach main or temp');
        }
        if (!isset($this->schemas[$name])) {
            throw new \InvalidArgumentException("SQLite DETACH schema {$name} is not attached");
        }

        unset($this->schemas[$name]);
        $this->attachedOrder = array_values(array_filter(
            $this->attachedOrder,
            static fn (string $attached): bool => $attached !== $name,
        ));

        foreach ($this->attachedOrder as $index => $attached) {
            $this->schemas[$attached]['sequence'] = $index + 2;
        }
        $this->invalidateLookupCache();
    }

    /**
     * Execute bounded ATTACH/DETACH schema statements against this in-memory
     * schema catalog. The optional loader receives the normalized file name and
     * schema name and returns the schema records for that attached database.
     *
     * @param callable(string, string): list<SQLiteSchemaRecord>|null $recordLoader
     * @return array{status: string, operation: string, schema: string, file: string|null, database_list: list<array{seq: int, name: string, file: string|null}>, uri: array<string, mixed>|null, open_plan: array<string, mixed>|null, schema_generation: int, cache_invalidated: bool}
     */
    public function executeAttachDetachSql(string $sql, ?callable $recordLoader = null): array
    {
        $trimmed = trim($sql);
        $trimmed = rtrim($trimmed, " \t\r\n;");

        if (preg_match('/^attach(?:\s+database)?\s+(.+?)\s+as\s+(.+)$/is', $trimmed, $matches) === 1) {
            $attachment = self::parseAttachFileExpression($matches[1]);
            $fileName = $attachment['file'];
            $schemaName = self::normalizeSchemaName($matches[2]);
            $records = $recordLoader !== null ? $recordLoader($fileName, $schemaName) : [];
            $this->attach($schemaName, $fileName, $records);

            return [
                'status' => 'ok',
                'operation' => 'attach',
                'schema' => $schemaName,
                'file' => $fileName,
                'database_list' => $this->databaseList(),
                'uri' => $attachment['uri'],
                'open_plan' => $attachment['open_plan'],
                'schema_generation' => $this->schemaGeneration,
                'cache_invalidated' => true,
            ];
        }

        if (preg_match('/^detach(?:\s+database)?\s+(.+)$/is', $trimmed, $matches) === 1) {
            $schemaName = self::normalizeSchemaName($matches[1]);
            $this->detach($schemaName);

            return [
                'status' => 'ok',
                'operation' => 'detach',
                'schema' => $schemaName,
                'file' => null,
                'database_list' => $this->databaseList(),
                'uri' => null,
                'open_plan' => null,
                'schema_generation' => $this->schemaGeneration,
                'cache_invalidated' => true,
            ];
        }

        throw new \InvalidArgumentException('SQLite schema catalog can only execute ATTACH or DETACH statements');
    }

    /**
     * @return list<array{seq: int, name: string, file: string|null}>
     */
    public function databaseList(): array
    {
        $rows = [
            ['seq' => 0, 'name' => 'main', 'file' => $this->schemas['main']['file']],
            ['seq' => 1, 'name' => 'temp', 'file' => $this->schemas['temp']['file']],
        ];

        foreach ($this->attachedOrder as $attached) {
            $schema = $this->schemas[$attached];
            $rows[] = ['seq' => $schema['sequence'], 'name' => $schema['name'], 'file' => $schema['file']];
        }

        return $rows;
    }

    public function schemaGeneration(): int
    {
        return $this->schemaGeneration;
    }

    /**
     * Capture the connection-level schema-cache state that SQLite uses to
     * decide whether prepared statements must be reprepared after ATTACH or
     * DETACH changes the database array.
     *
     * @return array{generation: int, source: string, database_count: int, search_order: list<string>, database_list: list<array{seq: int, name: string, file: string|null}>, schema_names: list<string>}
     */
    public function schemaCacheSnapshot(string $sourceSchema = 'main'): array
    {
        $source = self::normalizeSchemaName($sourceSchema);
        if (!isset($this->schemas[$source])) {
            throw new \InvalidArgumentException("SQLite schema {$source} is not attached");
        }

        return [
            'generation' => $this->schemaGeneration,
            'source' => $source,
            'database_count' => count($this->schemas),
            'search_order' => $this->searchOrder(),
            'database_list' => $this->databaseList(),
            'schema_names' => array_map(
                static fn (array $schema): string => $schema['name'],
                $this->databaseList(),
            ),
        ];
    }

    /**
     * @param array{generation?: int} $snapshot
     */
    public function schemaCacheIsCurrent(array $snapshot): bool
    {
        return ($snapshot['generation'] ?? -1) === $this->schemaGeneration;
    }

    /**
     * @param array{generation?: int, search_order?: list<string>, database_list?: list<array{seq: int, name: string, file: string|null}>} $snapshot
     * @return array{current: bool, before_generation: int|null, after_generation: int, before_search_order: list<string>, after_search_order: list<string>, before_database_count: int, after_database_count: int, invalidated_schemas: list<string>, added_schemas: list<string>, removed_schemas: list<string>, sequence_changed: bool}
     */
    public function schemaCacheInvalidation(array $snapshot): array
    {
        $beforeOrder = array_values($snapshot['search_order'] ?? []);
        $afterOrder = $this->searchOrder();
        $beforeSchemas = self::schemaNamesFromDatabaseList($snapshot['database_list'] ?? []);
        $afterSchemas = self::schemaNamesFromDatabaseList($this->databaseList());
        $added = array_values(array_diff($afterSchemas, $beforeSchemas));
        $removed = array_values(array_diff($beforeSchemas, $afterSchemas));
        $sequenceChanged = self::sequenceMap($snapshot['database_list'] ?? []) !== self::sequenceMap($this->databaseList());

        return [
            'current' => $this->schemaCacheIsCurrent($snapshot),
            'before_generation' => isset($snapshot['generation']) ? (int) $snapshot['generation'] : null,
            'after_generation' => $this->schemaGeneration,
            'before_search_order' => $beforeOrder,
            'after_search_order' => $afterOrder,
            'before_database_count' => count($beforeSchemas),
            'after_database_count' => count($afterSchemas),
            'invalidated_schemas' => array_values(array_unique(array_merge($added, $removed))),
            'added_schemas' => $added,
            'removed_schemas' => $removed,
            'sequence_changed' => $sequenceChanged,
        ];
    }

    /**
     * Capture the current unqualified/qualified table and index winners for a
     * prepared statement. SQLite invalidates this style of cached lookup after
     * ATTACH/DETACH because TEMP, main, and attached schemas can shadow each
     * other without the SQL text changing.
     *
     * @param list<string> $tables
     * @param list<string> $indexes
     * @return array{generation: int, source: string, search_order: list<string>, database_list: list<array{seq: int, name: string, file: string|null}>, tables: array<string, array{schema: string|null, name: string, rootpage: int|null, type: string|null}>, indexes: array<string, array{schema: string|null, name: string, rootpage: int|null, type: string|null}>}
     */
    public function schemaCacheResolutionSnapshot(array $tables = [], array $indexes = [], string $sourceSchema = 'main'): array
    {
        $source = self::normalizeSchemaName($sourceSchema);
        if (!isset($this->schemas[$source])) {
            throw new \InvalidArgumentException("SQLite schema {$source} is not attached");
        }

        $tableSnapshots = [];
        foreach ($tables as $table) {
            $name = trim($table);
            $tableSnapshots[$name] = $this->resolutionCacheEntry($this->resolveTableForCache($name));
        }

        $indexSnapshots = [];
        foreach ($indexes as $index) {
            $name = trim($index);
            $indexSnapshots[$name] = $this->resolutionCacheEntry($this->resolveIndexForCache($name));
        }

        return [
            'generation' => $this->schemaGeneration,
            'source' => $source,
            'search_order' => $this->searchOrder(),
            'database_list' => $this->databaseList(),
            'tables' => $tableSnapshots,
            'indexes' => $indexSnapshots,
        ];
    }

    /**
     * @param array{generation?: int, search_order?: list<string>, database_list?: list<array{seq: int, name: string, file: string|null}>, tables?: array<string, array{schema?: string|null, name?: string|null, rootpage?: int|null, type?: string|null}>, indexes?: array<string, array{schema?: string|null, name?: string|null, rootpage?: int|null, type?: string|null}>} $snapshot
     * @return array{current: bool, before_generation: int|null, after_generation: int, before_search_order: list<string>, after_search_order: list<string>, before_database_count: int, after_database_count: int, invalidated_schemas: list<string>, added_schemas: list<string>, removed_schemas: list<string>, sequence_changed: bool, table_changes: array<string, array{before: array{schema: string|null, name: string|null, rootpage: int|null, type: string|null}, after: array{schema: string|null, name: string|null, rootpage: int|null, type: string|null}, changed: bool}>, index_changes: array<string, array{before: array{schema: string|null, name: string|null, rootpage: int|null, type: string|null}, after: array{schema: string|null, name: string|null, rootpage: int|null, type: string|null}, changed: bool}>, changed_tables: list<string>, changed_indexes: list<string>, unchanged_tables: list<string>, unchanged_indexes: list<string>, stale: bool}
     */
    public function schemaCacheResolutionInvalidation(array $snapshot): array
    {
        $base = $this->schemaCacheInvalidation($snapshot);
        $tableChanges = [];
        $changedTables = [];
        $unchangedTables = [];
        foreach ($snapshot['tables'] ?? [] as $name => $before) {
            $after = $this->resolutionCacheEntry($this->resolveTableForCache((string) $name));
            $beforeEntry = self::normalizeResolutionCacheEntry($before);
            $changed = $beforeEntry !== $after;
            $tableChanges[(string) $name] = [
                'before' => $beforeEntry,
                'after' => $after,
                'changed' => $changed,
            ];
            if ($changed) {
                $changedTables[] = (string) $name;
            } else {
                $unchangedTables[] = (string) $name;
            }
        }

        $indexChanges = [];
        $changedIndexes = [];
        $unchangedIndexes = [];
        foreach ($snapshot['indexes'] ?? [] as $name => $before) {
            $after = $this->resolutionCacheEntry($this->resolveIndexForCache((string) $name));
            $beforeEntry = self::normalizeResolutionCacheEntry($before);
            $changed = $beforeEntry !== $after;
            $indexChanges[(string) $name] = [
                'before' => $beforeEntry,
                'after' => $after,
                'changed' => $changed,
            ];
            if ($changed) {
                $changedIndexes[] = (string) $name;
            } else {
                $unchangedIndexes[] = (string) $name;
            }
        }

        return $base + [
            'table_changes' => $tableChanges,
            'index_changes' => $indexChanges,
            'changed_tables' => $changedTables,
            'changed_indexes' => $changedIndexes,
            'unchanged_tables' => $unchangedTables,
            'unchanged_indexes' => $unchangedIndexes,
            'stale' => !$base['current'] || $changedTables !== [] || $changedIndexes !== [],
        ];
    }

    /**
     * @return array{schema: string, record: SQLiteSchemaRecord}|null
     */
    public function resolveTable(string $name): ?array
    {
        $schemaTable = $this->resolveSchemaTable($name);
        if ($schemaTable !== null) {
            return $schemaTable;
        }

        return $this->resolveObjectByType($name, 'table');
    }

    /**
     * @return array{schema: string, record: SQLiteSchemaRecord}|null
     */
    public function resolveIndex(string $name): ?array
    {
        return $this->resolveObjectByType($name, 'index');
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    public function replaceSchemaRecords(string $schemaName, array $records): void
    {
        $name = self::normalizeSchemaName($schemaName);
        if (!isset($this->schemas[$name])) {
            throw new \InvalidArgumentException("SQLite schema {$name} is not attached");
        }

        $this->schemas[$name]['records'] = array_values($records);
        $this->invalidateLookupCache();
    }

    /**
     * Apply bounded schema DDL to one attached schema and report the
     * connection-level schema-cache invalidation that current prepared
     * statements would observe on their next step.
     *
     * @param list<string> $ddl
     * @param array{generation?: int, search_order?: list<string>, database_list?: list<array{seq: int, name: string, file: string|null}>, tables?: array<string, array{schema?: string|null, name?: string|null, rootpage?: int|null, type?: string|null}>, indexes?: array<string, array{schema?: string|null, name?: string|null, rootpage?: int|null, type?: string|null}>}|null $resolutionSnapshot
     * @param list<array{id:string,schema_cookie:int,sql:string,target?:string}> $preparedStatements
     * @return array{status: string, operation: string, schema: string, before_generation: int, after_generation: int, cache_invalidated: bool, ddl_plan: array<string,mixed>, invalidation: array<string,mixed>|null, database_list: list<array{seq: int, name: string, file: string|null}>, dependencies: list<string>}
     */
    public function applySchemaDdlCurrentSource(
        string $schemaName,
        array $ddl,
        int $schemaCookie,
        ?array $resolutionSnapshot = null,
        array $preparedStatements = [],
    ): array {
        $name = self::normalizeSchemaName($schemaName);
        if (!isset($this->schemas[$name])) {
            throw new \InvalidArgumentException("SQLite schema {$name} is not attached");
        }

        $beforeGeneration = $this->schemaGeneration;
        $ddlPlan = SQLiteSchemaDdlReparsePlan::apply(
            $this->schemas[$name]['records'],
            $ddl,
            $schemaCookie,
            $name,
            $preparedStatements,
        );

        $changed = (bool) $ddlPlan['schema_changed'];
        if ($changed) {
            $this->schemas[$name]['records'] = $ddlPlan['records'];
            $this->invalidateLookupCache();
        }

        return [
            'status' => $changed ? 'schema_cache_expired' : 'schema_cache_stable',
            'operation' => 'attach-schema-cache-ddl-current-source',
            'schema' => $name,
            'before_generation' => $beforeGeneration,
            'after_generation' => $this->schemaGeneration,
            'cache_invalidated' => $changed,
            'ddl_plan' => $ddlPlan,
            'invalidation' => $resolutionSnapshot === null ? null : $this->schemaCacheResolutionInvalidation($resolutionSnapshot),
            'database_list' => $this->databaseList(),
            'dependencies' => [
                'sqlite-attach-schema-cache-ddl-current-source',
                'schema-sql-reparse',
                'sqlite-schema-cookie',
                'sqlite-attached-current-source-cache-expiry',
            ],
        ];
    }

    /**
     * @return array{generation: int, entries: int, hits: int, misses: int}
     */
    public function lookupCacheStats(): array
    {
        return [
            'generation' => $this->schemaGeneration,
            'entries' => count($this->lookupCache),
            'hits' => $this->lookupCacheHits,
            'misses' => $this->lookupCacheMisses,
        ];
    }

    /**
     * @return list<SQLiteSchemaRecord>
     */
    public function schemaRecords(string $schemaName): array
    {
        $name = self::normalizeSchemaName($schemaName);
        if (!isset($this->schemas[$name])) {
            throw new \InvalidArgumentException("SQLite schema {$name} is not attached");
        }

        return $this->schemas[$name]['records'];
    }

    public function pragmaCatalog(string $schemaName): SQLitePragmaSchemaCatalog
    {
        return new SQLitePragmaSchemaCatalog($this->schemaRecords($schemaName));
    }

    /**
     * Execute schema-introspection PRAGMAs against the schema that owns the
     * current source object. Unqualified table PRAGMAs follow SQLite name
     * resolution order (temp, main, then attached databases); schema-qualified
     * PRAGMAs stay pinned to the requested catalog.
     *
     * @return array{status: string, pragma: string, schema: string, target: string, rows: list<array<string, int|string|null>>}
     */
    public function executeSchemaPragma(string $sql): array
    {
        if (preg_match('/^pragma\s+database_list\s*;?$/i', trim($sql)) === 1) {
            return [
                'status' => 'ok',
                'pragma' => 'database_list',
                'schema' => 'main',
                'target' => '',
                'rows' => $this->databaseList(),
            ];
        }

        $parsed = SQLitePragmaSchemaCatalog::parsePragma($sql);
        $schemaName = $parsed['schema'];

        if ($parsed['pragma'] === 'table_list' && $schemaName === null) {
            return [
                'status' => 'ok',
                'pragma' => 'table_list',
                'schema' => 'main',
                'target' => $parsed['target'],
                'rows' => $this->tableList($parsed['target'] === '' ? null : $parsed['target']),
            ];
        }

        if ($schemaName === null) {
            $resolved = match ($parsed['pragma']) {
                'table_info', 'table_xinfo', 'index_list', 'foreign_key_list' => $this->resolveTable($parsed['target']),
                'index_info', 'index_xinfo' => $this->resolveIndex($parsed['target']),
                'table_list' => ['schema' => 'main'],
                'function_list', 'module_list', 'collation_list' => ['schema' => 'main'],
            };
            $schemaName = $resolved['schema'] ?? 'main';
            if (isset($resolved['record'])) {
                $parsed['target'] = $resolved['record']->name;
            }
        }

        if ($parsed['pragma'] === 'table_list') {
            $pragmaSql = 'PRAGMA ' . $schemaName . '.table_list'
                . ($parsed['target'] === '' ? '' : '(' . self::pragmaArgumentLiteral($parsed['target']) . ')');
        } else {
            $pragmaSql = $parsed['target'] === '' && in_array($parsed['pragma'], ['function_list', 'module_list', 'collation_list'], true)
                ? 'PRAGMA ' . $parsed['pragma']
                : 'PRAGMA ' . $parsed['pragma'] . '(' . self::pragmaArgumentLiteral($parsed['target']) . ')';
        }
        $result = $this->pragmaCatalog($schemaName)->execute($pragmaSql);
        $result['schema'] = $schemaName;

        return $result;
    }

    public function executeSchemaPragmaCursor(string $sql): SQLitePragmaRowCursor
    {
        return new SQLitePragmaRowCursor($this->executeSchemaPragma($sql));
    }

    /**
     * Execute SQLite's table-valued PRAGMA function form against the same
     * current-source catalog resolution used by direct schema PRAGMAs.
     *
     * @return array{status: string, pragma: 'table_info'|'table_xinfo'|'index_list'|'index_info'|'index_xinfo'|'foreign_key_list'|'function_list'|'module_list'|'collation_list', schema: string, target: string, rows: list<array<string, int|string|null>>}
     */
    public function executeTableValuedPragma(string $sql): array
    {
        if (preg_match('/^pragma_database_list\s*\(\s*\)\s*;?$/i', trim($sql)) === 1) {
            return [
                'status' => 'ok',
                'pragma' => 'database_list',
                'schema' => 'main',
                'target' => '',
                'rows' => $this->databaseList(),
            ];
        }

        $parsed = SQLitePragmaSchemaCatalog::parseTableValuedPragma($sql);
        $schemaName = $parsed['schema'];

        if ($parsed['pragma'] === 'table_list' && $schemaName === null) {
            return [
                'status' => 'ok',
                'pragma' => 'table_list',
                'schema' => 'main',
                'target' => $parsed['target'],
                'rows' => $this->tableList($parsed['target'] === '' ? null : $parsed['target']),
            ];
        }

        if ($schemaName === null) {
            $resolved = match ($parsed['pragma']) {
                'table_info', 'table_xinfo', 'index_list', 'foreign_key_list' => $this->resolveTable($parsed['target']),
                'index_info', 'index_xinfo' => $this->resolveIndex($parsed['target']),
                'table_list' => ['schema' => 'main'],
                'function_list', 'module_list', 'collation_list' => ['schema' => 'main'],
            };
            $schemaName = $resolved['schema'] ?? 'main';
            if (isset($resolved['record'])) {
                $parsed['target'] = $resolved['record']->name;
            }
        }

        $pragmaSql = $parsed['target'] === '' && $parsed['pragma'] !== 'table_list'
            ? 'pragma_' . $parsed['pragma'] . '()'
            : 'pragma_' . $parsed['pragma'] . '(' . self::pragmaArgumentLiteral($parsed['target']) . ')';
        if ($parsed['pragma'] === 'table_list') {
            $pragmaSql = 'pragma_table_list(' . self::pragmaArgumentLiteral($parsed['target']) . ', ' . self::pragmaArgumentLiteral($schemaName) . ')';
        }

        $result = $this->pragmaCatalog($schemaName)->executeTableValuedPragma($pragmaSql);
        $result['schema'] = $schemaName;

        return $result;
    }

    public function executeTableValuedPragmaCursor(string $sql): SQLitePragmaRowCursor
    {
        return new SQLitePragmaRowCursor($this->executeTableValuedPragma($sql));
    }

    /**
     * Snapshot a table-valued PRAGMA foreign_key_list cursor, replace the
     * owning schema catalog, and return the next cursor that SQLite would see
     * after schema reparse. This keeps the current cursor rows stable while
     * exposing recursive self-reference edges from the reparsed schema.
     *
     * @param list<SQLiteSchemaRecord> $nextRecords
     * @return array{status: string, operation: string, pragma: string, target: string, current_schema: string, next_schema: string, current_generation: int, next_generation: int, current_rows: list<array<string, int|string|null>>, next_rows: list<array<string, int|string|null>>, current_recursive_rows: list<array<string, int|string|null>>, next_recursive_rows: list<array<string, int|string|null>>, current_cursor: SQLitePragmaRowCursor, next_cursor: SQLitePragmaRowCursor, dependencies: list<string>}
     */
    public function foreignKeyListAfterSchemaReparse(string $sql, array $nextRecords, ?string $schemaName = null): array
    {
        $parsed = SQLitePragmaSchemaCatalog::parseTableValuedPragma($sql);
        if ($parsed['pragma'] !== 'foreign_key_list') {
            throw new \InvalidArgumentException('SQLite schema reparse preview only supports pragma_foreign_key_list');
        }

        $current = $this->executeTableValuedPragma($sql);
        $currentCursor = new SQLitePragmaRowCursor($current);
        $owner = $schemaName !== null ? self::normalizeSchemaName($schemaName) : $current['schema'];
        $currentGeneration = $this->schemaGeneration;
        $this->replaceSchemaRecords($owner, $nextRecords);

        $nextSql = 'pragma_foreign_key_list(' . self::pragmaArgumentLiteral($parsed['target']) . ', ' . self::pragmaArgumentLiteral($owner) . ')';
        $next = $this->executeTableValuedPragma($nextSql);
        $nextCursor = new SQLitePragmaRowCursor($next);

        return [
            'status' => 'ok',
            'operation' => 'pragma-foreign-key-list-after-schema-reparse',
            'pragma' => 'foreign_key_list',
            'target' => $parsed['target'],
            'current_schema' => $current['schema'],
            'next_schema' => $next['schema'],
            'current_generation' => $currentGeneration,
            'next_generation' => $this->schemaGeneration,
            'current_rows' => $current['rows'],
            'next_rows' => $next['rows'],
            'current_recursive_rows' => self::recursiveForeignKeyRows($parsed['target'], $current['rows']),
            'next_recursive_rows' => self::recursiveForeignKeyRows($parsed['target'], $next['rows']),
            'current_cursor' => $currentCursor,
            'next_cursor' => $nextCursor,
            'dependencies' => [
                'sqlite-pragma-foreign-key-list-after-schema-reparse',
                'sqlite-schema-catalog-current-source-cursor',
                'sqlite-foreign-key-recursive-self-reference',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function searchOrder(): array
    {
        return array_merge(['temp', 'main'], $this->attachedOrder);
    }

    /**
     * @return list<array{schema: string, name: string, type: string, ncol: int, wr: int, strict: int}>
     */
    public function tableList(?string $target = null): array
    {
        $rows = [];
        foreach ($this->searchOrder() as $schemaName) {
            $schemaRows = $this->pragmaCatalog($schemaName)->tableList($schemaName, $target);
            array_push($rows, ...$schemaRows);
        }

        return $rows;
    }

    /**
     * @param list<array<string, int|string|null>> $rows
     * @return list<array<string, int|string|null>>
     */
    private static function recursiveForeignKeyRows(string $target, array $rows): array
    {
        $normalized = strtolower($target);

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => strtolower((string) ($row['table'] ?? '')) === $normalized,
        ));
    }

    /**
     * @return array{schema: string, name: string}
     */
    private static function splitQualifiedName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite schema object name cannot be empty');
        }

        $dot = self::firstUnquotedDot($name);
        if ($dot === null) {
            return ['schema' => '', 'name' => self::unquoteIdentifier($name)];
        }

        return [
            'schema' => self::normalizeSchemaName(substr($name, 0, $dot)),
            'name' => self::unquoteIdentifier(substr($name, $dot + 1)),
        ];
    }

    private static function firstUnquotedDot(string $name): ?int
    {
        $length = strlen($name);
        for ($i = 0; $i < $length; $i++) {
            $char = $name[$i];
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                for ($i++; $i < $length; $i++) {
                    if ($name[$i] !== $quote) {
                        continue;
                    }
                    if (isset($name[$i + 1]) && $name[$i + 1] === $quote) {
                        $i++;
                        continue;
                    }
                    break;
                }
                continue;
            }
            if ($char === '[') {
                $end = strpos($name, ']', $i + 1);
                $i = $end === false ? $length : $end;
                continue;
            }
            if ($char === '.') {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return array{schema: string, record: SQLiteSchemaRecord}|null
     */
    private function resolveObjectByType(string $name, string $type): ?array
    {
        $cacheKey = strtolower($type . ':' . $name);
        if (isset($this->lookupCache[$cacheKey]) && $this->lookupCache[$cacheKey]['generation'] === $this->schemaGeneration) {
            $this->lookupCacheHits++;

            return $this->lookupCache[$cacheKey]['result'];
        }

        $this->lookupCacheMisses++;
        $result = $this->resolveObject($name, static function (SQLiteSchemaRecord $record) use ($type): bool {
            if ($type === 'table') {
                return $record->type === 'table' || $record->type === 'view';
            }

            return $record->type === $type;
        });
        $this->lookupCache[$cacheKey] = [
            'generation' => $this->schemaGeneration,
            'result' => $result,
        ];

        return $result;
    }

    private function resolveObject(string $name, callable $accept): ?array
    {
        $qualified = self::splitQualifiedName($name);
        $schemas = $qualified['schema'] !== '' ? [$qualified['schema']] : $this->searchOrder();

        foreach ($schemas as $schemaName) {
            if (!isset($this->schemas[$schemaName])) {
                throw new \InvalidArgumentException("SQLite schema {$schemaName} is not attached");
            }
            foreach ($this->schemas[$schemaName]['records'] as $record) {
                if (strcasecmp($record->name, $qualified['name']) === 0 && $accept($record)) {
                    return ['schema' => $schemaName, 'record' => $record];
                }
            }
        }

        return null;
    }

    private function invalidateLookupCache(): void
    {
        $this->schemaGeneration++;
        $this->lookupCache = [];
    }

    /**
     * Resolve SQLite's built-in schema table aliases. Unlike ordinary
     * unqualified table names, bare sqlite_schema/sqlite_master refer to main,
     * while sqlite_temp_schema/sqlite_temp_master refer to temp.
     *
     * @return array{schema: string, record: SQLiteSchemaRecord}|null
     */
    private function resolveSchemaTable(string $name): ?array
    {
        $qualified = self::splitQualifiedName($name);
        $object = strtolower($qualified['name']);
        $schemaName = $qualified['schema'];

        if ($schemaName === '' && ($object === 'sqlite_temp_schema' || $object === 'sqlite_temp_master')) {
            return ['schema' => 'temp', 'record' => $this->schemaTableRecord('temp')];
        }

        if ($object !== 'sqlite_schema' && $object !== 'sqlite_master') {
            return null;
        }

        if ($schemaName === '') {
            $schemaName = 'main';
        }
        if (!isset($this->schemas[$schemaName])) {
            throw new \InvalidArgumentException("SQLite schema {$schemaName} is not attached");
        }

        return ['schema' => $schemaName, 'record' => $this->schemaTableRecord($schemaName)];
    }

    private function schemaTableRecord(string $schemaName): SQLiteSchemaRecord
    {
        return new SQLiteSchemaRecord(
            'table',
            'sqlite_schema',
            'sqlite_schema',
            1,
            'CREATE TABLE sqlite_schema(type text,name text,tbl_name text,rootpage int,sql text)',
            1,
        );
    }

    /**
     * @param array{schema: string, record: SQLiteSchemaRecord}|null $resolved
     * @return array{schema: string|null, name: string|null, rootpage: int|null, type: string|null}
     */
    private function resolutionCacheEntry(?array $resolved): array
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
    private function resolveTableForCache(string $name): ?array
    {
        try {
            return $this->resolveTable($name);
        } catch (\InvalidArgumentException $exception) {
            if (str_contains($exception->getMessage(), ' is not attached')) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * @return array{schema: string, record: SQLiteSchemaRecord}|null
     */
    private function resolveIndexForCache(string $name): ?array
    {
        try {
            return $this->resolveIndex($name);
        } catch (\InvalidArgumentException $exception) {
            if (str_contains($exception->getMessage(), ' is not attached')) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * @param array{schema?: string|null, name?: string|null, rootpage?: int|null, type?: string|null} $entry
     * @return array{schema: string|null, name: string|null, rootpage: int|null, type: string|null}
     */
    private static function normalizeResolutionCacheEntry(array $entry): array
    {
        return [
            'schema' => array_key_exists('schema', $entry) && $entry['schema'] !== null ? (string) $entry['schema'] : null,
            'name' => array_key_exists('name', $entry) && $entry['name'] !== null ? (string) $entry['name'] : null,
            'rootpage' => array_key_exists('rootpage', $entry) && $entry['rootpage'] !== null ? (int) $entry['rootpage'] : null,
            'type' => array_key_exists('type', $entry) && $entry['type'] !== null ? (string) $entry['type'] : null,
        ];
    }

    private static function normalizeSchemaName(string $name): string
    {
        $normalized = strtolower(self::unquoteIdentifier($name));
        if ($normalized === '') {
            throw new \InvalidArgumentException('SQLite schema name cannot be empty');
        }

        return $normalized;
    }

    /**
     * @param list<array{seq?: int, name?: string, file?: string|null}> $databaseList
     * @return list<string>
     */
    private static function schemaNamesFromDatabaseList(array $databaseList): array
    {
        return array_values(array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $databaseList,
        ));
    }

    /**
     * @param list<array{seq?: int, name?: string, file?: string|null}> $databaseList
     * @return array<string, int>
     */
    private static function sequenceMap(array $databaseList): array
    {
        $map = [];
        foreach ($databaseList as $row) {
            $map[(string) ($row['name'] ?? '')] = (int) ($row['seq'] ?? -1);
        }

        return $map;
    }

    /**
     * @return array{file: string, uri: array<string, mixed>, open_plan: array<string, mixed>}
     */
    private static function parseAttachFileExpression(string $expression): array
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new \InvalidArgumentException('SQLite ATTACH file name cannot be empty');
        }

        $fileName = null;
        $quote = $expression[0];
        if (($quote === "'" || $quote === '"') && substr($expression, -1) === $quote) {
            $body = substr($expression, 1, -1);
            if ($body === '') {
                throw new \InvalidArgumentException('SQLite ATTACH file name cannot be empty');
            }

            $fileName = str_replace($quote . $quote, $quote, $body);
        } elseif (preg_match('/^[A-Za-z0-9_\/.\-:?&=%]+$/', $expression) === 1) {
            $fileName = $expression;
        } else {
            throw new \InvalidArgumentException('SQLite ATTACH file name must be a bounded string literal or path token');
        }

        $uri = SQLiteFileUri::parse($fileName);
        $normalizedFile = (string) $uri['path'];
        if ($normalizedFile === '') {
            throw new \InvalidArgumentException('SQLite ATTACH file name cannot be empty');
        }

        return [
            'file' => $normalizedFile,
            'uri' => $uri,
            'open_plan' => SQLiteOpenPlan::forFilename($fileName, true, true, true),
        ];
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return '';
        }
        $first = $identifier[0];
        $last = $identifier[strlen($identifier) - 1];
        if (($first === '"' && $last === '"') || ($first === '`' && $last === '`') || ($first === "'" && $last === "'")) {
            return str_replace($first . $first, $first, substr($identifier, 1, -1));
        }
        if ($first === '[' && $last === ']') {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }

    private static function pragmaArgumentLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
