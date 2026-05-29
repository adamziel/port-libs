<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $currentSchemas
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $nextSchemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,foreign_key_sql:string,integrity_sql:string,integrity_scope:string,integrity_target:string|null,schema_hash:string,catalog_hash:string|null},next_source:array{database:string,foreign_key_sql:string,integrity_sql:string,integrity_scope:string,integrity_target:string|null,schema_hash:string,catalog_hash:string|null},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{integrity_root:int,foreign_key:int},next_counts:array{integrity_root:int,foreign_key:int},delta:array{integrity_root:int,foreign_key:int,total:int,cleared:bool},next_state:array{ready:bool,blocking:list<string>},next:array{source_id:string,offset:int}|null,rows:list<array<string,mixed>>}
     */
    public static function currentNextPage(
        string|SQLiteDatabase $currentDatabase,
        array $currentSchemas,
        string|SQLiteDatabase $nextDatabase,
        array $nextSchemas,
        string $foreignKeySql,
        int $offset = 0,
        int $limit = 136,
        ?array $cursor = null,
        ?SQLiteAttachedSchemaCatalog $currentCatalog = null,
        ?SQLiteAttachedSchemaCatalog $nextCatalog = null,
        string $integritySql = 'PRAGMA quick_check',
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key quickcheck root current/next cursor offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key quickcheck root current/next cursor limit must be positive');
        }

        $currentSource = self::source($currentDatabase, $currentSchemas, $foreignKeySql, $currentCatalog, $integritySql);
        $nextSource = self::source($nextDatabase, $nextSchemas, $foreignKeySql, $nextCatalog, $integritySql);
        $source = [
            'current' => $currentSource['source_id'],
            'next' => $nextSource['source_id'],
            'foreign_key_sql' => $currentSource['foreign_key_sql'],
            'integrity_sql' => $currentSource['integrity_sql'],
        ];
        $sourceId = self::stableHash($source);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentRows = array_map(
            static fn (array $row): array => ['side' => 'current', ...$row],
            self::collect($currentDatabase, $currentSchemas, $foreignKeySql, $currentCatalog, $integritySql),
        );
        $nextRows = array_map(
            static fn (array $row): array => ['side' => 'next', ...$row],
            self::collect($nextDatabase, $nextSchemas, $foreignKeySql, $nextCatalog, $integritySql),
        );
        $rows = [...$currentRows, ...$nextRows];
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $currentCounts = self::sourceCounts($currentRows);
        $nextCounts = self::sourceCounts($nextRows);
        $delta = [
            'integrity_root' => $nextCounts['integrity_root'] - $currentCounts['integrity_root'],
            'foreign_key' => $nextCounts['foreign_key'] - $currentCounts['foreign_key'],
            'total' => count($nextRows) - count($currentRows),
            'cleared' => count($currentRows) > 0 && count($nextRows) === 0,
        ];
        $blocking = [];
        if ($nextCounts['integrity_root'] > 0) {
            $blocking[] = 'quick_check_root';
        }
        if ($nextCounts['foreign_key'] > 0) {
            $blocking[] = 'foreign_key_check';
        }

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'source_id' => $sourceId,
            'current_source' => self::publicSource($currentSource),
            'next_source' => self::publicSource($nextSource),
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $currentCounts,
            'next_counts' => $nextCounts,
            'delta' => $delta,
            'next_state' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
            'next' => $complete ? null : [
                'source_id' => $sourceId,
                'offset' => $nextOffset,
            ],
            'rows' => $pageRows,
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return list<array<string,mixed>>
     */
    public static function collect(
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        ?SQLiteAttachedSchemaCatalog $catalog = null,
        string $integritySql = 'PRAGMA integrity_check',
    ): array {
        $rootpage = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext::analyze($database);
        $rows = [];
        foreach (self::integrityRootRows($database, $rootpage['rows'], $integritySql) as $row) {
            if (($row['status'] ?? null) === 'ok' || ($row['status'] ?? null) === 'ignored') {
                continue;
            }
            $rows[] = [
                'kind' => 'integrity_root',
                'source' => $row['kind'],
                'schema' => null,
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => null,
                'fkid' => null,
                'type' => $row['type'],
                'name' => $row['name'],
                'rootpage' => $row['rootpage'],
                'page_status' => $row['page_status'],
                'page_type' => $row['page_type'],
                'pointer_map_page' => $row['pointer_map_page'],
                'message' => $row['message'],
            ];
        }

        $foreignKeys = self::executeForeignKeySql($foreignKeySql, $schemas, $catalog);
        foreach ($foreignKeys['rows'] as $row) {
            $rows[] = [
                'kind' => 'foreign_key_check',
                'source' => 'foreign_key',
                'schema' => $row['schema'],
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => $row['parent'],
                'fkid' => $row['fkid'],
                'type' => null,
                'name' => null,
                'rootpage' => null,
                'page_status' => null,
                'page_type' => null,
                'pointer_map_page' => null,
                'message' => self::foreignKeyMessage($row),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,foreign_key_sql:string,integrity_sql:string,integrity_scope:string,integrity_target:string|null,schema_hash:string,catalog_hash:string|null},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{integrity_root:int,foreign_key:int},next:array{source_id:string,offset:int}|null,rows:list<array<string,mixed>>}
     */
    public static function page(
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        int $offset = 0,
        int $limit = 117,
        ?array $cursor = null,
        ?SQLiteAttachedSchemaCatalog $catalog = null,
        string $integritySql = 'PRAGMA integrity_check',
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key root integrity source cursor offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key root integrity source cursor limit must be positive');
        }

        $source = self::source($database, $schemas, $foreignKeySql, $catalog, $integritySql);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $rows = self::collect($database, $schemas, $foreignKeySql, $catalog, $integritySql);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);

        return [
            'status' => 'ok',
            'source_id' => $source['source_id'],
            'current_source' => [
                'database' => $source['database'],
                'foreign_key_sql' => $source['foreign_key_sql'],
                'integrity_sql' => $source['integrity_sql'],
                'integrity_scope' => $source['integrity_scope'],
                'integrity_target' => $source['integrity_target'],
                'schema_hash' => $source['schema_hash'],
                'catalog_hash' => $source['catalog_hash'],
            ],
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => self::sourceCounts($rows),
            'next' => $complete ? null : [
                'source_id' => $source['source_id'],
                'offset' => $nextOffset,
            ],
            'rows' => $pageRows,
        ];
    }

    /**
     * @param array{schema:string,table:string,rowid:int|string|null,parent:string,fkid:int} $row
     */
    private static function foreignKeyMessage(array $row): string
    {
        $rowid = $row['rowid'] === null ? 'NULL' : (string) $row['rowid'];

        return "foreign key mismatch in {$row['schema']}.{$row['table']} rowid {$rowid} references {$row['parent']} fkid {$row['fkid']}";
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function integrityRootRows(string|SQLiteDatabase $database, array $rows, string $integritySql): array
    {
        $scope = self::integrityScope($integritySql);
        if ($scope['target'] === null) {
            return $rows;
        }
        if (is_string($database)) {
            $database = SQLiteDatabase::fromBytes($database);
        }

        $targetNames = self::targetRootNames($database, $scope['target']);
        if ($targetNames === []) {
            throw new InvalidArgumentException("SQLite PRAGMA foreign-key root integrity target {$scope['target']} was not found");
        }

        return array_values(array_filter(
            $rows,
            static function (array $row) use ($targetNames): bool {
                $name = $row['name'] ?? null;
                $table = $row['table'] ?? null;

                return (is_string($name) && isset($targetNames[$name]))
                    || (is_string($table) && isset($targetNames[$table]));
            },
        ));
    }

    /**
     * @return array<string,true>
     */
    private static function targetRootNames(SQLiteDatabase $database, string $target): array
    {
        $names = [];
        $table = null;
        foreach ($database->schemaRecords() as $record) {
            if ($record->type === 'table' && strcasecmp($record->name, $target) === 0) {
                $table = $record->name;
                $names[$record->name] = true;
                break;
            }
        }
        if ($table === null) {
            return [];
        }
        foreach ($database->schemaRecords() as $record) {
            if ($record->type === 'index' && strcasecmp($record->tableName, $table) === 0) {
                $names[$record->name] = true;
            }
        }

        return $names;
    }

    /**
     * @return array{pragma:string,scope:string,target:string|null}
     */
    private static function integrityScope(string $sql): array
    {
        $trimmed = trim(rtrim(trim($sql), ';'));
        $identifier = '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|\'(?:\'\'|[^\'])+\'|[A-Za-z_][A-Za-z0-9_]*)';
        if (preg_match('/^PRAGMA\s+(?:(?:[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?(?<pragma>integrity_check|quick_check)\s*\(\s*(?<target>' . $identifier . ')\s*\)$/i', $trimmed, $matches) === 1) {
            return [
                'pragma' => strtolower($matches['pragma']),
                'scope' => 'table',
                'target' => self::unquoteIdentifier($matches['target']),
            ];
        }
        if (preg_match('/^PRAGMA\s+(?:(?:[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?(?<pragma>integrity_check|quick_check)(?:\s*(?:\(\s*\d+\s*\)|=\s*\d+))?$/i', $trimmed, $matches) === 1) {
            return [
                'pragma' => strtolower($matches['pragma']),
                'scope' => 'database',
                'target' => null,
            ];
        }

        throw new InvalidArgumentException('SQLite PRAGMA foreign-key root integrity needs PRAGMA integrity_check or quick_check SQL');
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,pragma:string,schema:string,target_schema:string,target:string|null,target_source:string,rows:list<array{schema:string,table:string,rowid:int|string|null,parent:string,fkid:int}>}
     */
    private static function executeForeignKeySql(string $sql, array $schemas, ?SQLiteAttachedSchemaCatalog $catalog): array
    {
        try {
            return SQLitePragmaForeignKeyIntegrity::executeTableValued($sql, $schemas, $catalog);
        } catch (InvalidArgumentException $tableValuedError) {
            try {
                return SQLitePragmaForeignKeyIntegrity::execute($sql, $schemas, $catalog);
            } catch (InvalidArgumentException) {
                throw $tableValuedError;
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{integrity_root:int,foreign_key:int}
     */
    private static function sourceCounts(array $rows): array
    {
        $counts = [
            'integrity_root' => 0,
            'foreign_key' => 0,
        ];
        foreach ($rows as $row) {
            if (($row['kind'] ?? null) === 'foreign_key_check') {
                $counts['foreign_key']++;
            } else {
                $counts['integrity_root']++;
            }
        }

        return $counts;
    }

    /**
     * @param array{source_id:string,database:string,foreign_key_sql:string,integrity_sql:string,integrity_scope:string,integrity_target:string|null,schema_hash:string,catalog_hash:string|null} $source
     * @return array{database:string,foreign_key_sql:string,integrity_sql:string,integrity_scope:string,integrity_target:string|null,schema_hash:string,catalog_hash:string|null}
     */
    private static function publicSource(array $source): array
    {
        return [
            'database' => $source['database'],
            'foreign_key_sql' => $source['foreign_key_sql'],
            'integrity_sql' => $source['integrity_sql'],
            'integrity_scope' => $source['integrity_scope'],
            'integrity_target' => $source['integrity_target'],
            'schema_hash' => $source['schema_hash'],
            'catalog_hash' => $source['catalog_hash'],
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{source_id:string,database:string,foreign_key_sql:string,integrity_sql:string,integrity_scope:string,integrity_target:string|null,schema_hash:string,catalog_hash:string|null}
     */
    private static function source(string|SQLiteDatabase $database, array $schemas, string $foreignKeySql, ?SQLiteAttachedSchemaCatalog $catalog, string $integritySql): array
    {
        $integrityScope = self::integrityScope($integritySql);
        $normalized = [
            'database' => is_string($database) ? hash('sha256', $database) : self::databaseHash($database),
            'foreign_key_sql' => self::normalizeSql($foreignKeySql),
            'integrity_sql' => self::normalizeSql($integritySql),
            'integrity_scope' => $integrityScope['scope'],
            'integrity_target' => $integrityScope['target'],
            'schema_hash' => self::stableHash($schemas),
            'catalog_hash' => $catalog === null ? null : self::stableHash(self::catalogSource($catalog)),
        ];

        return [
            ...$normalized,
            'source_id' => self::stableHash($normalized),
        ];
    }

    /**
     * @return array{generation:int,search_order:list<string>,database_list:list<array{seq:int,name:string,file:string|null}>,schema_records:array<string,list<array{type:string,name:string,table:string,rootpage:int|null,sql:string|null,rowid:int}>>}
     */
    private static function catalogSource(SQLiteAttachedSchemaCatalog $catalog): array
    {
        $schemaRecords = [];
        foreach ($catalog->databaseList() as $database) {
            $schema = $database['name'];
            $schemaRecords[$schema] = array_map(
                static fn (SQLiteSchemaRecord $record): array => [
                    'type' => $record->type,
                    'name' => $record->name,
                    'table' => $record->tableName,
                    'rootpage' => $record->rootPage,
                    'sql' => $record->sql,
                    'rowid' => $record->rowId,
                ],
                $catalog->schemaRecords($schema),
            );
        }

        return [
            'generation' => $catalog->schemaGeneration(),
            'search_order' => $catalog->searchOrder(),
            'database_list' => $catalog->databaseList(),
            'schema_records' => $schemaRecords,
        ];
    }

    private static function databaseHash(SQLiteDatabase $database): string
    {
        $context = hash_init('sha256');
        hash_update($context, (string) $database->header->pageSize);
        hash_update($context, ':');
        hash_update($context, (string) $database->pageCount());
        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
            hash_update($context, $database->page($pageNumber));
        }

        return hash_final($context);
    }

    private static function normalizeSql(string $sql): string
    {
        return strtolower(preg_replace('/\s+/', ' ', rtrim(trim($sql), ';')) ?? trim($sql));
    }

    private static function unquoteIdentifier(string $value): string
    {
        $value = trim($value);
        $first = $value[0] ?? '';
        $last = substr($value, -1);
        if ($first === '"' && $last === '"') {
            return str_replace('""', '"', substr($value, 1, -1));
        }
        if ($first === "'" && $last === "'") {
            return str_replace("''", "'", substr($value, 1, -1));
        }
        if (($first === '`' && $last === '`') || ($first === '[' && $last === ']')) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key root integrity source cursor does not match the current database/schema source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key root integrity source cursor offset does not match the requested page offset');
        }
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', self::stableEncode($value));
    }

    private static function stableEncode(mixed $value): string
    {
        if (is_array($value)) {
            if (!array_is_list($value)) {
                ksort($value);
            }

            return '[' . implode(',', array_map(static fn (mixed $item, string|int $key): string => self::stableEncode((string) $key) . ':' . self::stableEncode($item), $value, array_keys($value))) . ']';
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
