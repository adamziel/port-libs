<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityRootpageForeignKeyCurrentSourceYield
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,catalog:string,schemas:string,foreign_key_sql:string,integrity_sql:string},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{foreign_key_violations:int,rootpage_errors:int,missing_rootpages:int,schemas:list<string>},next:array{source_id:string,offset:int}|null,next_state:array{ready:bool,blocking:list<string>},rows:list<array<string,mixed>>}
     */
    public static function page(
        string|SQLiteDatabase $database,
        array $schemas,
        SQLiteAttachedSchemaCatalog $catalog,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        int $offset = 0,
        int $limit = 114,
        string $integritySql = 'PRAGMA integrity_check',
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity rootpage/FK current-source next114 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity rootpage/FK current-source next114 limit must be positive');
        }

        $source = self::source($database, $schemas, $catalog, $foreignKeySql, $integritySql);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $rows = self::collect($database, $schemas, $catalog, $foreignKeySql, $integritySql);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $current = self::counts($rows);
        $blocking = [];
        if ($current['foreign_key_violations'] > 0) {
            $blocking[] = 'foreign_key_check';
        }
        if ($current['rootpage_errors'] > 0) {
            $blocking[] = 'integrity_rootpage';
        }
        if ($current['missing_rootpages'] > 0) {
            $blocking[] = 'foreign_key_rootpage_catalog';
        }

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'source_id' => $source['source_id'],
            'current_source' => [
                'database' => $source['database'],
                'catalog' => $source['catalog'],
                'schemas' => $source['schemas'],
                'foreign_key_sql' => $source['foreign_key_sql'],
                'integrity_sql' => $source['integrity_sql'],
            ],
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $current,
            'next' => $complete ? null : [
                'source_id' => $source['source_id'],
                'offset' => $nextOffset,
            ],
            'next_state' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
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
        SQLiteAttachedSchemaCatalog $catalog,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        string $integritySql = 'PRAGMA integrity_check',
    ): array {
        $rows = [];
        $foreignKeys = self::executeForeignKeySql($foreignKeySql, $schemas, $catalog);
        foreach ($foreignKeys['rows'] as $row) {
            $child = self::schemaRecord($catalog, $row['schema'], $row['table'], 'table');
            $parent = self::schemaRecord($catalog, $row['schema'], $row['parent'], 'table');
            $childRoot = $child?->rootPage;
            $parentRoot = $parent?->rootPage;

            $rows[] = [
                'kind' => 'foreign_key_check',
                'source' => 'foreign_key',
                'schema' => $row['schema'],
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => $row['parent'],
                'fkid' => $row['fkid'],
                'child_rootpage' => $childRoot,
                'parent_rootpage' => $parentRoot,
                'rootpage_status' => $childRoot === null || $parentRoot === null ? 'missing' : 'ok',
                'message' => self::foreignKeyMessage($row, $childRoot, $parentRoot),
            ];
        }

        foreach (SQLitePragmaIntegrityCurrentNextYield::collect($database, [], $integritySql) as $row) {
            if ($row['source'] !== 'schema_root') {
                continue;
            }
            $rows[] = [
                'kind' => $row['kind'],
                'source' => 'schema_root',
                'schema' => null,
                'table' => null,
                'rowid' => null,
                'parent' => null,
                'fkid' => null,
                'child_rootpage' => null,
                'parent_rootpage' => null,
                'rootpage_status' => 'integrity-error',
                'page' => $row['page'],
                'pointer_map_page' => $row['pointer_map_page'],
                'message' => $row['message'],
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,pragma:string,schema:string,target_schema:string,target:string|null,target_source:string,rows:list<array{schema:string,table:string,rowid:int|string|null,parent:string,fkid:int}>}
     */
    private static function executeForeignKeySql(string $sql, array $schemas, SQLiteAttachedSchemaCatalog $catalog): array
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

    private static function schemaRecord(SQLiteAttachedSchemaCatalog $catalog, string $schema, string $name, string $type): ?SQLiteSchemaRecord
    {
        foreach ($catalog->schemaRecords($schema) as $record) {
            if ($record->type === $type && strcasecmp($record->name, $name) === 0) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @param array{schema:string,table:string,rowid:int|string|null,parent:string,fkid:int} $row
     */
    private static function foreignKeyMessage(array $row, ?int $childRoot, ?int $parentRoot): string
    {
        $rowid = $row['rowid'] === null ? 'NULL' : (string) $row['rowid'];
        $child = $childRoot === null ? 'missing child rootpage' : "child rootpage {$childRoot}";
        $parent = $parentRoot === null ? 'missing parent rootpage' : "parent rootpage {$parentRoot}";

        return "foreign key mismatch in {$row['schema']}.{$row['table']} rowid {$rowid} references {$row['parent']} fkid {$row['fkid']} ({$child}, {$parent})";
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{foreign_key_violations:int,rootpage_errors:int,missing_rootpages:int,schemas:list<string>}
     */
    private static function counts(array $rows): array
    {
        $schemas = [];
        $missingRootpages = 0;
        $foreignKeyViolations = 0;
        $rootpageErrors = 0;
        foreach ($rows as $row) {
            if (($row['source'] ?? null) === 'foreign_key') {
                $foreignKeyViolations++;
                if (is_string($row['schema'] ?? null)) {
                    $schemas[] = $row['schema'];
                }
                if (($row['rootpage_status'] ?? null) === 'missing') {
                    $missingRootpages++;
                }
            } elseif (($row['source'] ?? null) === 'schema_root') {
                $rootpageErrors++;
            }
        }

        return [
            'foreign_key_violations' => $foreignKeyViolations,
            'rootpage_errors' => $rootpageErrors,
            'missing_rootpages' => $missingRootpages,
            'schemas' => array_values(array_unique($schemas)),
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{source_id:string,database:string,catalog:string,schemas:string,foreign_key_sql:string,integrity_sql:string}
     */
    private static function source(string|SQLiteDatabase $database, array $schemas, SQLiteAttachedSchemaCatalog $catalog, string $foreignKeySql, string $integritySql): array
    {
        $source = [
            'database' => is_string($database) ? hash('sha256', $database) : self::databaseHash($database),
            'catalog' => self::catalogHash($catalog),
            'schemas' => self::stableHash($schemas),
            'foreign_key_sql' => self::normalizeSql($foreignKeySql),
            'integrity_sql' => self::normalizeSql($integritySql),
        ];

        return [
            ...$source,
            'source_id' => self::stableHash($source),
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

    private static function catalogHash(SQLiteAttachedSchemaCatalog $catalog): string
    {
        $snapshot = [
            'database_list' => $catalog->databaseList(),
            'schemas' => [],
        ];
        foreach ($catalog->databaseList() as $database) {
            $schema = (string) $database['name'];
            $snapshot['schemas'][$schema] = array_map(
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

        return self::stableHash($snapshot);
    }

    private static function normalizeSql(string $sql): string
    {
        return strtolower(preg_replace('/\s+/', ' ', rtrim(trim($sql), ';')) ?? trim($sql));
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity rootpage/FK current-source cursor does not match the current database/schema/catalog source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity rootpage/FK current-source cursor offset does not match the requested page offset');
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

            return '[' . implode(',', array_map(
                static fn (mixed $item, string|int $key): string => self::stableEncode((string) $key) . ':' . self::stableEncode($item),
                $value,
                array_keys($value),
            )) . ']';
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
