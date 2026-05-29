<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext153
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $currentSchemas
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $nextSchemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array<string,mixed>,next_source:array<string,mixed>,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,mixed>,next_counts:array<string,mixed>,delta:array<string,mixed>,next_state:array{ready:bool,blocking:list<string>},next:array{source_id:string,offset:int}|null,rows:list<array<string,mixed>>}
     */
    public static function page(
        SQLiteAttachedSchemaCatalog $currentCatalog,
        SQLiteAttachedSchemaCatalog $nextCatalog,
        string $indexXinfoSql,
        array $currentSchemas,
        array $nextSchemas,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        int $offset = 0,
        int $limit = 153,
        bool $indexTableValued = false,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo foreign-key current-source next153 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo foreign-key current-source next153 limit must be positive');
        }

        $current = self::snapshot($currentCatalog, $indexXinfoSql, $currentSchemas, $foreignKeySql, $indexTableValued);
        $next = self::snapshot($nextCatalog, $indexXinfoSql, $nextSchemas, $foreignKeySql, $indexTableValued);
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next153',
            'current' => $current['source_id'],
            'next' => $next['source_id'],
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $rows = [
            ...array_map(static fn (array $row): array => ['side' => 'current', ...$row], $current['rows']),
            ...array_map(static fn (array $row): array => ['side' => 'next', ...$row], $next['rows']),
        ];
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $delta = self::delta($current['counts'], $next['counts'], $current['index_signature'], $next['index_signature']);
        $blocking = self::blocking($next['counts'], $current['index_signature'], $next['index_signature']);

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'source_id' => $sourceId,
            'current_source' => $current['source'],
            'next_source' => $next['source'],
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $current['counts'],
            'next_counts' => $next['counts'],
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
     * @return array{source_id:string,source:array<string,mixed>,rows:list<array<string,mixed>>,counts:array<string,mixed>,index_signature:string}
     */
    private static function snapshot(
        SQLiteAttachedSchemaCatalog $catalog,
        string $indexXinfoSql,
        array $schemas,
        string $foreignKeySql,
        bool $indexTableValued,
    ): array {
        $index = $indexTableValued
            ? $catalog->executeTableValuedPragma($indexXinfoSql)
            : $catalog->executeSchemaPragma($indexXinfoSql);
        if (($index['pragma'] ?? null) !== 'index_xinfo') {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo foreign-key current-source next153 requires index_xinfo input');
        }

        $foreignKeys = self::foreignKeyRows($foreignKeySql, $schemas, $catalog);
        $indexRows = self::indexRows($index);
        $fkRows = array_map(
            static fn (array $row): array => [
                'kind' => 'foreign_key_check',
                'phase' => 'foreign_key_check',
                'schema' => $row['schema'],
                'target' => $row['table'],
                'seqno' => null,
                'cid' => null,
                'name' => null,
                'desc' => null,
                'coll' => null,
                'key' => null,
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => $row['parent'],
                'fkid' => $row['fkid'],
                'message' => self::foreignKeyMessage($row),
            ],
            $foreignKeys['rows'],
        );
        $source = [
            'mode' => 'index_xinfo_foreignkey_current_source_next153',
            'catalog_hash' => self::catalogHash($catalog),
            'schemas_hash' => self::stableHash($schemas),
            'index_xinfo_sql' => self::normalizeSql($indexXinfoSql),
            'foreign_key_sql' => self::normalizeSql($foreignKeySql),
            'index_schema' => $index['schema'],
            'index_target' => $index['target'],
            'index_table_valued' => $indexTableValued,
            'foreign_key_table_valued' => $foreignKeys['table_valued'],
        ];
        $rows = [...$indexRows, ...$fkRows];

        return [
            'source_id' => self::stableHash($source),
            'source' => $source,
            'rows' => $rows,
            'counts' => [
                'index_xinfo' => count($indexRows),
                'index_key_columns' => count(array_filter($indexRows, static fn (array $row): bool => $row['key'] === 1)),
                'index_auxiliary_columns' => count(array_filter($indexRows, static fn (array $row): bool => $row['key'] === 0)),
                'index_expression_columns' => count(array_filter($indexRows, static fn (array $row): bool => $row['cid'] === -2)),
                'foreign_key' => count($fkRows),
                'foreign_key_tables' => self::uniqueCount(array_map(static fn (array $row): string => (string) $row['table'], $fkRows)),
            ],
            'index_signature' => self::stableHash($indexRows),
        ];
    }

    /**
     * @param array{schema:string,target:string,rows:list<array<string,int|string|null>>} $index
     * @return list<array<string,mixed>>
     */
    private static function indexRows(array $index): array
    {
        return array_map(
            static fn (array $row): array => [
                'kind' => 'index_xinfo',
                'phase' => 'index_xinfo',
                'schema' => $index['schema'],
                'target' => $index['target'],
                'seqno' => $row['seqno'],
                'cid' => $row['cid'],
                'name' => $row['name'],
                'desc' => $row['desc'],
                'coll' => $row['coll'],
                'key' => $row['key'],
                'table' => null,
                'rowid' => null,
                'parent' => null,
                'fkid' => null,
                'message' => self::indexMessage($index['schema'], $index['target'], $row),
            ],
            $index['rows'],
        );
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{rows:list<array<string,mixed>>,table_valued:bool}
     */
    private static function foreignKeyRows(string $foreignKeySql, array $schemas, SQLiteAttachedSchemaCatalog $catalog): array
    {
        $normalized = self::normalizeSql($foreignKeySql);
        $tableValued = str_starts_with($normalized, 'select ')
            || str_starts_with($normalized, 'pragma_foreign_key_check')
            || str_contains($normalized, '.pragma_foreign_key_check(');
        $result = $tableValued
            ? SQLitePragmaForeignKeyIntegrity::executeTableValued($foreignKeySql, $schemas, $catalog)
            : SQLitePragmaForeignKeyIntegrity::execute($foreignKeySql, $schemas);

        return [
            'rows' => $result['rows'],
            'table_valued' => $tableValued,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function indexMessage(string $schema, string $target, array $row): string
    {
        $name = $row['name'] === null ? '<expr>' : (string) $row['name'];

        return "{$schema}.{$target} index_xinfo seq {$row['seqno']} cid {$row['cid']} name {$name} coll {$row['coll']} key {$row['key']}";
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function foreignKeyMessage(array $row): string
    {
        $rowid = $row['rowid'] === null ? 'NULL' : (string) $row['rowid'];

        return "foreign key mismatch in {$row['schema']}.{$row['table']} rowid {$rowid} references {$row['parent']} fkid {$row['fkid']}";
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return array<string,mixed>
     */
    private static function delta(array $current, array $next, string $currentIndexSignature, string $nextIndexSignature): array
    {
        $keys = ['index_xinfo', 'index_key_columns', 'index_auxiliary_columns', 'index_expression_columns', 'foreign_key', 'foreign_key_tables'];
        $delta = [
            'index_signature_changed' => $currentIndexSignature !== $nextIndexSignature,
        ];
        foreach ($keys as $key) {
            $delta[$key] = (int) ($next[$key] ?? 0) - (int) ($current[$key] ?? 0);
        }
        $delta['foreign_keys_cleared'] = ((int) ($current['foreign_key'] ?? 0)) > 0 && ((int) ($next['foreign_key'] ?? 0)) === 0;

        return $delta;
    }

    /**
     * @param array<string,mixed> $next
     * @return list<string>
     */
    private static function blocking(array $next, string $currentIndexSignature, string $nextIndexSignature): array
    {
        $blocking = [];
        if ($currentIndexSignature !== $nextIndexSignature) {
            $blocking[] = 'index_xinfo_drift';
        }
        if (((int) ($next['foreign_key'] ?? 0)) > 0) {
            $blocking[] = 'foreign_key_check';
        }

        return $blocking;
    }

    /**
     * @param list<string> $values
     */
    private static function uniqueCount(array $values): int
    {
        return count(array_unique($values));
    }

    private static function catalogHash(SQLiteAttachedSchemaCatalog $catalog): string
    {
        $snapshot = [
            'database_list' => $catalog->databaseList(),
            'schema_generation' => $catalog->schemaGeneration(),
            'search_order' => $catalog->searchOrder(),
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

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo foreign-key current-source next153 cursor source changed');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo foreign-key current-source next153 cursor offset changed');
        }
    }

    private static function normalizeSql(string $sql): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', rtrim($sql, " \t\r\n;")) ?? trim($sql)));
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
