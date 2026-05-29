<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext155
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array<string,mixed>,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,mixed>,next:array{source_id:string,offset:int}|null,rows:list<array<string,mixed>>}
     */
    public static function page(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        array $schemas,
        string $indexXinfoSql,
        string $foreignKeyListSql,
        string $foreignKeyCheckSql = 'PRAGMA foreign_key_check',
        int $offset = 0,
        int $limit = 155,
        bool $tableValuedIndexXinfo = false,
        bool $tableValuedForeignKeyList = false,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next155 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next155 limit must be positive');
        }

        $source = self::source($catalog, $schemas, $indexXinfoSql, $foreignKeyListSql, $foreignKeyCheckSql, $tableValuedIndexXinfo, $tableValuedForeignKeyList);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $rows = self::collect($catalog, $schemas, $indexXinfoSql, $foreignKeyListSql, $foreignKeyCheckSql, $tableValuedIndexXinfo, $tableValuedForeignKeyList);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);

        return [
            'status' => 'ok',
            'source_id' => $source['source_id'],
            'current_source' => self::publicSource($source),
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => self::counts($rows),
            'next' => $complete ? null : [
                'source_id' => $source['source_id'],
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
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        array $schemas,
        string $indexXinfoSql,
        string $foreignKeyListSql,
        string $foreignKeyCheckSql = 'PRAGMA foreign_key_check',
        bool $tableValuedIndexXinfo = false,
        bool $tableValuedForeignKeyList = false,
    ): array {
        $index = self::executeCatalogPragma($catalog, $indexXinfoSql, $tableValuedIndexXinfo);
        if (($index['pragma'] ?? null) !== 'index_xinfo') {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next155 requires index_xinfo rows');
        }

        $foreignKeyList = self::executeCatalogPragma($catalog, $foreignKeyListSql, $tableValuedForeignKeyList);
        if (($foreignKeyList['pragma'] ?? null) !== 'foreign_key_list') {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next155 requires foreign_key_list rows');
        }

        $foreignKeyCheck = self::foreignKeyCheckRows($schemas, $foreignKeyCheckSql);
        $rows = [];
        foreach ($index['rows'] as $row) {
            $rows[] = [
                'phase' => 'index_xinfo',
                'kind' => 'index_xinfo',
                'schema' => $index['schema'],
                'target' => $index['target'],
                'seqno' => $row['seqno'],
                'cid' => $row['cid'],
                'name' => $row['name'],
                'desc' => $row['desc'],
                'coll' => $row['coll'],
                'key' => $row['key'],
                'table' => null,
                'from' => null,
                'to' => null,
                'rowid' => null,
                'parent' => null,
                'fkid' => null,
            ];
        }
        foreach ($foreignKeyList['rows'] as $row) {
            $rows[] = [
                'phase' => 'foreign_key_list',
                'kind' => 'foreign_key_list',
                'schema' => $foreignKeyList['schema'],
                'target' => $foreignKeyList['target'],
                'seqno' => $row['seq'],
                'cid' => null,
                'name' => null,
                'desc' => null,
                'coll' => null,
                'key' => null,
                'table' => $row['table'],
                'from' => $row['from'],
                'to' => $row['to'],
                'on_update' => $row['on_update'],
                'on_delete' => $row['on_delete'],
                'match' => $row['match'],
                'rowid' => null,
                'parent' => $row['table'],
                'fkid' => $row['id'],
            ];
        }
        foreach ($foreignKeyCheck['rows'] as $row) {
            $rows[] = [
                'phase' => 'foreign_key_check',
                'kind' => 'foreign_key_check',
                'schema' => $foreignKeyCheck['schema'],
                'target' => $foreignKeyCheck['target'],
                'seqno' => null,
                'cid' => null,
                'name' => null,
                'desc' => null,
                'coll' => null,
                'key' => null,
                'table' => $row['table'],
                'from' => null,
                'to' => null,
                'rowid' => $row['rowid'],
                'parent' => $row['parent'],
                'fkid' => $row['fkid'],
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,pragma:string,schema:string,target:string|null,rows:list<array{table:string,rowid:int|string|null,parent:string,fkid:int}>}
     */
    private static function foreignKeyCheckRows(array $schemas, string $sql): array
    {
        $parsed = self::parseForeignKeyCheck($sql);
        $schema = $parsed['schema'] ?? 'main';
        $source = $schemas[$schema] ?? ['tables' => [], 'foreignKeys' => []];

        return [
            'status' => 'ok',
            'pragma' => 'foreign_key_check',
            'schema' => $schema,
            'target' => $parsed['target'],
            'rows' => SQLitePragmaForeignKeyCheck::check($source['tables'] ?? [], $source['foreignKeys'] ?? [], $parsed['target']),
        ];
    }

    /**
     * @return array{schema:string|null,target:string|null}
     */
    private static function parseForeignKeyCheck(string $sql): array
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^pragma\s+(?:(?<schema>[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?foreign_key_check\s*(?:\(\s*(?<target>(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|\'(?:\'\'|[^\'])+\'|[A-Za-z_][A-Za-z0-9_]*))\s*\))?$/i', $trimmed, $matches) === 1) {
            return [
                'schema' => isset($matches['schema']) && $matches['schema'] !== '' ? strtolower($matches['schema']) : null,
                'target' => isset($matches['target']) && $matches['target'] !== '' ? self::unquoteIdentifier($matches['target']) : null,
            ];
        }
        if (preg_match('/^(?:select\s+\*\s+from\s+)?pragma_foreign_key_check\s*\(\s*(?<target>(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|\'(?:\'\'|[^\'])+\'|[A-Za-z_][A-Za-z0-9_]*))?\s*\)$/i', $trimmed, $matches) === 1) {
            $target = isset($matches['target']) && $matches['target'] !== '' ? self::unquoteIdentifier($matches['target']) : null;
            $schema = null;
            if (is_string($target) && str_contains($target, '.')) {
                [$schema, $target] = explode('.', $target, 2);
                $schema = strtolower($schema);
            }

            return ['schema' => $schema, 'target' => $target];
        }

        throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next155 requires foreign_key_check SQL');
    }

    /**
     * @return array{status:string,pragma:string,schema:string,target:string,rows:list<array<string,int|string|null>>}
     */
    private static function executeCatalogPragma(SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog, string $sql, bool $tableValued): array
    {
        if ($catalog instanceof SQLiteAttachedSchemaCatalog) {
            return $tableValued ? $catalog->executeTableValuedPragma($sql) : $catalog->executeSchemaPragma($sql);
        }

        return $tableValued ? $catalog->executeTableValuedPragma($sql) : $catalog->execute($sql);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'index_xinfo' => 0,
            'foreign_key_list' => 0,
            'foreign_key_check' => 0,
            'foreign_key_tables' => [],
            'foreign_key_parents' => [],
            'index_key_columns' => [],
            'index_aux_columns' => [],
        ];
        foreach ($rows as $row) {
            $kind = $row['kind'] ?? null;
            if ($kind === 'index_xinfo') {
                $counts['index_xinfo']++;
                $name = $row['name'] ?? null;
                if (($row['key'] ?? null) === 1 && is_string($name)) {
                    $counts['index_key_columns'][] = $name;
                } elseif (($row['key'] ?? null) === 0 && is_string($name)) {
                    $counts['index_aux_columns'][] = $name;
                }
            } elseif ($kind === 'foreign_key_list') {
                $counts['foreign_key_list']++;
                $table = (string) ($row['target'] ?? '');
                $parent = (string) ($row['parent'] ?? '');
                if ($table !== '' && !in_array($table, $counts['foreign_key_tables'], true)) {
                    $counts['foreign_key_tables'][] = $table;
                }
                if ($parent !== '' && !in_array($parent, $counts['foreign_key_parents'], true)) {
                    $counts['foreign_key_parents'][] = $parent;
                }
            } elseif ($kind === 'foreign_key_check') {
                $counts['foreign_key_check']++;
            }
        }

        return $counts;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array<string,mixed>
     */
    private static function source(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        array $schemas,
        string $indexXinfoSql,
        string $foreignKeyListSql,
        string $foreignKeyCheckSql,
        bool $tableValuedIndexXinfo,
        bool $tableValuedForeignKeyList,
    ): array {
        $source = [
            'catalog' => self::catalogHash($catalog),
            'schemas' => self::stableHash($schemas),
            'index_xinfo_sql' => self::normalizeSql($indexXinfoSql),
            'foreign_key_list_sql' => self::normalizeSql($foreignKeyListSql),
            'foreign_key_check_sql' => self::normalizeSql($foreignKeyCheckSql),
            'table_valued_index_xinfo' => $tableValuedIndexXinfo,
            'table_valued_foreign_key_list' => $tableValuedForeignKeyList,
        ];

        return [
            ...$source,
            'source_id' => self::stableHash($source),
        ];
    }

    private static function catalogHash(SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog): string
    {
        if ($catalog instanceof SQLiteAttachedSchemaCatalog) {
            $snapshot = [
                'database_list' => $catalog->databaseList(),
                'schema_generation' => $catalog->schemaGeneration(),
                'search_order' => $catalog->searchOrder(),
                'schemas' => [],
            ];
            foreach ($catalog->databaseList() as $database) {
                $schema = (string) $database['name'];
                $snapshot['schemas'][$schema] = self::schemaRecordsSnapshot($catalog->schemaRecords($schema));
            }

            return self::stableHash($snapshot);
        }

        return self::stableHash([
            'schema' => 'main',
            'records' => self::schemaRecordsSnapshot($catalog->records()),
        ]);
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array{type:string,name:string,table:string,rootpage:int|null,sql:string|null,rowid:int}>
     */
    private static function schemaRecordsSnapshot(array $records): array
    {
        return array_map(
            static fn (SQLiteSchemaRecord $record): array => [
                'type' => $record->type,
                'name' => $record->name,
                'table' => $record->tableName,
                'rootpage' => $record->rootPage,
                'sql' => $record->sql,
                'rowid' => $record->rowId,
            ],
            $records,
        );
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function publicSource(array $source): array
    {
        unset($source['source_id']);

        return $source;
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
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next155 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next155 cursor offset does not match the requested page offset');
        }
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        $first = $identifier[0] ?? '';
        $last = substr($identifier, -1);
        if ($first === '"' && $last === '"') {
            return str_replace('""', '"', substr($identifier, 1, -1));
        }
        if ($first === "'" && $last === "'") {
            return str_replace("''", "'", substr($identifier, 1, -1));
        }
        if (($first === '`' && $last === '`') || ($first === '[' && $last === ']')) {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
