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
        }

        $result = $this->pragmaCatalog($schemaName)->execute($sql);
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
     * @return array{schema: string, name: string}
     */
    private static function splitQualifiedName(string $name): array
    {
        $parts = preg_split('/\s*\.\s*/', trim($name), 2);
        if ($parts === false || $parts === []) {
            throw new \InvalidArgumentException('SQLite schema object name cannot be empty');
        }
        if (count($parts) === 1) {
            return ['schema' => '', 'name' => self::unquoteIdentifier($parts[0])];
        }

        return ['schema' => self::normalizeSchemaName($parts[0]), 'name' => self::unquoteIdentifier($parts[1])];
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
