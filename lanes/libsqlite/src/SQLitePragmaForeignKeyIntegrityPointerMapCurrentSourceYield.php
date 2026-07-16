<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceYield
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,schemas:string,catalog:string|null,integrity_sql:string,foreign_key_sql:string,table_valued:bool},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{pointer_map:int,foreign_key:int,schemas:list<string>,tables:list<string>,blocked:bool},next:array{source_id:string,offset:int}|null,next_state:array{ready:bool,blocking:list<string>},rows:list<array<string,mixed>>}
     */
    public static function page(
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        int $offset = 0,
        int $limit = 64,
        string $integritySql = 'PRAGMA integrity_check',
        ?array $cursor = null,
        ?SQLiteAttachedSchemaCatalog $catalog = null,
        bool $tableValued = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key/integrity pointer-map current-source offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key/integrity pointer-map current-source limit must be positive');
        }

        $source = self::source($database, $schemas, $foreignKeySql, $integritySql, $catalog, $tableValued);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $rows = $tableValued
            ? SQLitePragmaIntegrityCurrentNextYield::collectForForeignKeyTableValuedPragma($database, $schemas, $foreignKeySql, $integritySql, $catalog)
            : SQLitePragmaIntegrityCurrentNextYield::collectForForeignKeyPragma($database, $schemas, $foreignKeySql, $integritySql, $catalog);
        $rows = self::tagRows($rows, $source['source_id']);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $current = self::current($rows);
        $blocking = self::blocking($current);

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'source_id' => $source['source_id'],
            'current_source' => [
                'database' => $source['database'],
                'schemas' => $source['schemas'],
                'catalog' => $source['catalog'],
                'integrity_sql' => $source['integrity_sql'],
                'foreign_key_sql' => $source['foreign_key_sql'],
                'table_valued' => $tableValued,
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
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $sourceId): array
    {
        $tagged = [];
        foreach ($rows as $ordinal => $row) {
            $tagged[] = [
                ...$row,
                'source_id' => $sourceId,
                'ordinal' => $ordinal,
                'blocking' => in_array($row['source'], ['pointer_map', 'foreign_key'], true),
            ];
        }

        return $tagged;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{pointer_map:int,foreign_key:int,schemas:list<string>,tables:list<string>,blocked:bool}
     */
    private static function current(array $rows): array
    {
        $schemas = [];
        $tables = [];
        $pointerMap = 0;
        $foreignKey = 0;
        foreach ($rows as $row) {
            if ($row['source'] === 'pointer_map') {
                $pointerMap++;
            }
            if ($row['source'] === 'foreign_key') {
                $foreignKey++;
            }
            if (($row['schema'] ?? null) !== null) {
                $schemas[] = (string) $row['schema'];
            }
            if (($row['table'] ?? null) !== null) {
                $tables[] = (string) $row['table'];
            }
        }

        return [
            'pointer_map' => $pointerMap,
            'foreign_key' => $foreignKey,
            'schemas' => array_values(array_unique($schemas)),
            'tables' => array_values(array_unique($tables)),
            'blocked' => $pointerMap > 0 || $foreignKey > 0,
        ];
    }

    /**
     * @param array{pointer_map:int,foreign_key:int} $current
     * @return list<string>
     */
    private static function blocking(array $current): array
    {
        $blocking = [];
        if ($current['pointer_map'] > 0) {
            $blocking[] = 'integrity_pointer_map';
        }
        if ($current['foreign_key'] > 0) {
            $blocking[] = 'foreign_key_check';
        }

        return $blocking;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{source_id:string,database:string,schemas:string,catalog:string|null,integrity_sql:string,foreign_key_sql:string,table_valued:bool}
     */
    private static function source(
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql,
        string $integritySql,
        ?SQLiteAttachedSchemaCatalog $catalog,
        bool $tableValued,
    ): array {
        $source = [
            'database' => is_string($database) ? hash('sha256', $database) : self::databaseHash($database),
            'schemas' => self::stableHash($schemas),
            'catalog' => $catalog === null ? null : self::catalogHash($catalog),
            'integrity_sql' => self::normalizeSql($integritySql),
            'foreign_key_sql' => self::normalizeSql($foreignKeySql),
            'table_valued' => $tableValued,
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
            'schema_generation' => $catalog->schemaGeneration(),
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
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key/integrity pointer-map current-source cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key/integrity pointer-map current-source cursor offset does not match the requested page offset');
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
