<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return list<array{kind:string,source:string,schema:string|null,target_source:string,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,index:string|null,columns:list<string>,collations:list<string>,status:string,page:int|null,pointer_map_page:int|null,message:string}>
     */
    public static function collect(
        string|SQLiteDatabase $database,
        array $schemas,
        SQLiteAttachedSchemaCatalog $catalog,
        string $integritySql = 'PRAGMA integrity_check',
    ): array {
        $rows = [];

        foreach (self::schemaOrder($schemas, $catalog) as $schema) {
            $schemaRows = SQLitePragmaForeignKeyIndexIntegrityYield::collect(
                $catalog->schemaRecords($schema),
                $schemas[$schema]['foreignKeys'],
                $schemas[$schema]['tables'],
            );

            foreach ($schemaRows as $row) {
                $rows[] = [
                    'kind' => $row['kind'],
                    'source' => $row['kind'] === 'foreign_key_check' ? 'foreign_key' : 'index',
                    'schema' => $schema,
                    'target_source' => 'catalog-current',
                    'table' => $row['table'],
                    'rowid' => $row['rowid'],
                    'parent' => $row['parent'],
                    'fkid' => $row['fkid'],
                    'index' => $row['index'],
                    'columns' => $row['columns'],
                    'collations' => $row['collations'],
                    'status' => $row['status'],
                    'page' => null,
                    'pointer_map_page' => null,
                    'message' => self::schemaMessage($schema, $row['message']),
                ];
            }
        }

        foreach (SQLitePragmaIntegrityCurrentNextYield::collect($database, [], $integritySql) as $row) {
            $rows[] = [
                'kind' => $row['kind'],
                'source' => $row['source'],
                'schema' => $row['schema'],
                'target_source' => 'integrity-check',
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => $row['parent'],
                'fkid' => $row['fkid'],
                'index' => null,
                'columns' => [],
                'collations' => [],
                'status' => 'error',
                'page' => $row['page'],
                'pointer_map_page' => $row['pointer_map_page'],
                'message' => $row['message'],
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{index_admissions:int,index_blockers:int,foreign_key_violations:int,integrity_errors:int,schemas:list<string>},next:array{ready:bool,blocking:list<string>},rows:list<array{kind:string,source:string,schema:string|null,target_source:string,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,index:string|null,columns:list<string>,collations:list<string>,status:string,page:int|null,pointer_map_page:int|null,message:string}>}
     */
    public static function page(
        string|SQLiteDatabase $database,
        array $schemas,
        SQLiteAttachedSchemaCatalog $catalog,
        int $offset = 0,
        int $limit = 88,
        string $integritySql = 'PRAGMA integrity_check',
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity/foreign-key/index current-source next88 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity/foreign-key/index current-source next88 limit must be positive');
        }

        $rows = self::collect($database, $schemas, $catalog, $integritySql);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $indexBlockers = count(array_filter(
            $rows,
            static fn (array $row): bool => $row['kind'] === 'index_admission' && $row['status'] === 'blocked'
        ));
        $foreignKeyViolations = self::countRows($rows, 'foreign_key_check');
        $integrityErrors = count(array_filter(
            $rows,
            static fn (array $row): bool => $row['target_source'] === 'integrity-check'
        ));
        $blocking = [];
        if ($indexBlockers > 0) {
            $blocking[] = 'foreign_key_parent_unique_index';
        }
        if ($foreignKeyViolations > 0) {
            $blocking[] = 'foreign_key_check';
        }
        if ($integrityErrors > 0) {
            $blocking[] = 'integrity_check';
        }

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => [
                'index_admissions' => self::countRows($rows, 'index_admission'),
                'index_blockers' => $indexBlockers,
                'foreign_key_violations' => $foreignKeyViolations,
                'integrity_errors' => $integrityErrors,
                'schemas' => array_values(array_unique(array_filter(
                    array_map(static fn (array $row): ?string => $row['schema'], $rows),
                    static fn (?string $schema): bool => $schema !== null,
                ))),
            ],
            'next' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
            'rows' => $pageRows,
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,catalog:string,schemas:string,integrity_sql:string},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{index_admissions:int,index_blockers:int,foreign_key_violations:int,integrity_errors:int,schemas:list<string>},next:array{source_id:string,offset:int}|null,next_state:array{ready:bool,blocking:list<string>},rows:list<array{kind:string,source:string,schema:string|null,target_source:string,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,index:string|null,columns:list<string>,collations:list<string>,status:string,page:int|null,pointer_map_page:int|null,message:string}>}
     */
    public static function pageWithSourceCursor(
        string|SQLiteDatabase $database,
        array $schemas,
        SQLiteAttachedSchemaCatalog $catalog,
        int $offset = 0,
        int $limit = 99,
        string $integritySql = 'PRAGMA integrity_check',
        ?array $cursor = null,
    ): array {
        $source = self::source($database, $schemas, $catalog, $integritySql);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $page = self::page($database, $schemas, $catalog, $offset, $limit, $integritySql);
        $next = $page['next_offset'] === null ? null : [
            'source_id' => $source['source_id'],
            'offset' => $page['next_offset'],
        ];

        return [
            'status' => $page['status'],
            'source_id' => $source['source_id'],
            'current_source' => [
                'database' => $source['database'],
                'catalog' => $source['catalog'],
                'schemas' => $source['schemas'],
                'integrity_sql' => $source['integrity_sql'],
            ],
            'offset' => $page['offset'],
            'limit' => $page['limit'],
            'count' => $page['count'],
            'total' => $page['total'],
            'next_offset' => $page['next_offset'],
            'complete' => $page['complete'],
            'current' => $page['current'],
            'next' => $next,
            'next_state' => $page['next'],
            'rows' => $page['rows'],
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return list<string>
     */
    private static function schemaOrder(array $schemas, SQLiteAttachedSchemaCatalog $catalog): array
    {
        $ordered = [];
        foreach ($catalog->databaseList() as $database) {
            $schema = (string) $database['name'];
            if (isset($schemas[$schema])) {
                $ordered[] = $schema;
            }
        }

        foreach (array_keys($schemas) as $schema) {
            if (!in_array($schema, $ordered, true)) {
                throw new InvalidArgumentException("SQLite PRAGMA integrity/foreign-key/index current-source next88 schema {$schema} is not attached");
            }
        }

        return $ordered;
    }

    /**
     * @param list<array{kind:string}> $rows
     */
    private static function countRows(array $rows, string $kind): int
    {
        return count(array_filter($rows, static fn (array $row): bool => $row['kind'] === $kind));
    }

    private static function schemaMessage(string $schema, string $message): string
    {
        if (str_starts_with($message, $schema . '.')) {
            return $message;
        }

        return $schema . '.' . $message;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{source_id:string,database:string,catalog:string,schemas:string,integrity_sql:string}
     */
    private static function source(string|SQLiteDatabase $database, array $schemas, SQLiteAttachedSchemaCatalog $catalog, string $integritySql): array
    {
        $source = [
            'database' => is_string($database) ? hash('sha256', $database) : self::databaseHash($database),
            'catalog' => self::catalogHash($catalog),
            'schemas' => self::stableHash($schemas),
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
            throw new InvalidArgumentException('SQLite PRAGMA integrity/foreign-key/index current-source cursor does not match the current database/schema/catalog source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity/foreign-key/index current-source cursor offset does not match the requested page offset');
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
