<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext202
{
    /**
     * @param list<SQLiteSchemaRecord> $currentRecords
     * @param list<SQLiteSchemaRecord> $nextRecords
     * @param array{source_id:string,offset:int}|null $resume
     * @return array<string,mixed>
     */
    public static function page(
        array $currentRecords,
        array $nextRecords,
        string $indexXinfoSql,
        string $foreignKeySql,
        int $offset = 0,
        int $limit = 50,
        ?array $resume = null,
        bool $tableValuedIndexXinfo = false,
        bool $tableValuedForeignKeyList = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next202 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next202 limit must be positive');
        }

        $indexParsed = $tableValuedIndexXinfo
            ? SQLitePragmaSchemaCatalog::parseTableValuedPragma($indexXinfoSql)
            : SQLitePragmaSchemaCatalog::parsePragma($indexXinfoSql);
        $foreignKeyParsed = $tableValuedForeignKeyList
            ? SQLitePragmaSchemaCatalog::parseTableValuedPragma($foreignKeySql)
            : SQLitePragmaSchemaCatalog::parsePragma($foreignKeySql);

        if ($indexParsed['pragma'] !== 'index_xinfo') {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next202 requires PRAGMA index_xinfo');
        }
        if ($foreignKeyParsed['pragma'] !== 'foreign_key_list') {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next202 requires PRAGMA foreign_key_list');
        }

        $schema = $indexParsed['schema'] ?? $foreignKeyParsed['schema'] ?? 'main';
        $indexName = $indexParsed['target'];
        $tableName = $foreignKeyParsed['target'];
        $normalizedIndexSql = $tableValuedIndexXinfo
            ? self::normalizeTableValuedPragmaSql('index_xinfo', $schema, $indexName)
            : self::normalizePragmaSql('index_xinfo', $schema, $indexName);
        $normalizedForeignKeySql = $tableValuedForeignKeyList
            ? self::normalizeTableValuedPragmaSql('foreign_key_list', $schema, $tableName)
            : self::normalizePragmaSql('foreign_key_list', $schema, $tableName);
        $sourceId = self::sourceId(
            $currentRecords,
            $nextRecords,
            $normalizedIndexSql,
            $normalizedForeignKeySql,
            $tableValuedIndexXinfo,
            $tableValuedForeignKeyList,
        );

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next202 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next202 resume cursor offset mismatch');
            }
        }

        $currentCatalog = new SQLitePragmaSchemaCatalog($currentRecords);
        $nextCatalog = new SQLitePragmaSchemaCatalog($nextRecords);
        $currentRows = self::combinedRows($currentCatalog, $indexName, $tableName, 'current', $tableValuedForeignKeyList);
        $nextRows = self::combinedRows($nextCatalog, $indexName, $tableName, 'next', $tableValuedForeignKeyList);
        $allRows = array_merge($currentRows, $nextRows);
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);

        return [
            'status' => 'ok',
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next202',
            'source_id' => $sourceId,
            'current_source' => [
                'index_xinfo_sql' => $normalizedIndexSql,
                'foreign_key_sql' => $normalizedForeignKeySql,
                'schema' => $schema,
                'index' => $indexName,
                'table' => $tableName,
                'table_valued_index_xinfo' => $tableValuedIndexXinfo,
                'table_valued_foreign_key_list' => $tableValuedForeignKeyList,
                'foreign_key_source' => $tableValuedForeignKeyList ? 'pragma_foreign_key_list' : 'pragma foreign_key_list',
                'current_catalog_hash' => self::catalogHash($currentRecords),
                'next_catalog_hash' => self::catalogHash($nextRecords),
            ],
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current' => self::counts($currentRows),
            'next_counts' => self::counts($nextRows),
            'delta' => self::delta(self::counts($currentRows), self::counts($nextRows)),
            'rows' => $pageRows,
            'dependencies' => [
                'sqlite-pragma-index-xinfo-current-source',
                'sqlite-pragma-foreign-key-list-table-valued-current-source',
                'sqlite-wordpress-copied-schema-reparse-preflight',
            ],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function combinedRows(SQLitePragmaSchemaCatalog $catalog, string $indexName, string $tableName, string $phase, bool $tableValuedForeignKeyList): array
    {
        $rows = [];
        foreach ($catalog->indexXInfo($indexName) as $row) {
            $rows[] = [
                'phase' => $phase,
                'kind' => 'index_xinfo',
                'source' => 'index_xinfo',
                'seqno' => $row['seqno'],
                'cid' => $row['cid'],
                'name' => $row['name'],
                'desc' => $row['desc'],
                'coll' => $row['coll'],
                'key' => $row['key'],
                'is_expression' => $row['cid'] === -2 ? 1 : 0,
                'is_auxiliary' => $row['key'] === 0 ? 1 : 0,
                'foreign_key_id' => null,
                'foreign_key_seq' => null,
                'foreign_table' => null,
                'from' => null,
                'to' => null,
                'on_update' => null,
                'on_delete' => null,
                'match' => null,
                'foreign_key_source' => null,
            ];
        }

        foreach ($catalog->foreignKeyList($tableName) as $row) {
            $rows[] = [
                'phase' => $phase,
                'kind' => 'foreign_key_list',
                'source' => $tableValuedForeignKeyList ? 'pragma_foreign_key_list' : 'foreign_key_list',
                'seqno' => null,
                'cid' => null,
                'name' => null,
                'desc' => null,
                'coll' => null,
                'key' => null,
                'is_expression' => 0,
                'is_auxiliary' => 0,
                'foreign_key_id' => $row['id'],
                'foreign_key_seq' => $row['seq'],
                'foreign_table' => $row['table'],
                'from' => $row['from'],
                'to' => $row['to'],
                'on_update' => $row['on_update'],
                'on_delete' => $row['on_delete'],
                'match' => $row['match'],
                'foreign_key_source' => $tableValuedForeignKeyList ? 'table_valued' : 'pragma',
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{index_xinfo:int,foreign_key_list:int,expression_terms:int,auxiliary_terms:int,foreign_key_groups:int,table_valued_foreign_key_rows:int}
     */
    private static function counts(array $rows): array
    {
        $foreignKeyIds = [];
        $counts = [
            'index_xinfo' => 0,
            'foreign_key_list' => 0,
            'expression_terms' => 0,
            'auxiliary_terms' => 0,
            'foreign_key_groups' => 0,
            'table_valued_foreign_key_rows' => 0,
        ];
        foreach ($rows as $row) {
            if ($row['kind'] === 'index_xinfo') {
                $counts['index_xinfo']++;
                $counts['expression_terms'] += (int) $row['is_expression'];
                $counts['auxiliary_terms'] += (int) $row['is_auxiliary'];
                continue;
            }
            if ($row['kind'] === 'foreign_key_list') {
                $counts['foreign_key_list']++;
                $foreignKeyIds[(string) $row['foreign_key_id']] = true;
                if (($row['foreign_key_source'] ?? null) === 'table_valued') {
                    $counts['table_valued_foreign_key_rows']++;
                }
            }
        }
        $counts['foreign_key_groups'] = count($foreignKeyIds);

        return $counts;
    }

    /**
     * @param array{index_xinfo:int,foreign_key_list:int,expression_terms:int,auxiliary_terms:int,foreign_key_groups:int,table_valued_foreign_key_rows:int} $current
     * @param array{index_xinfo:int,foreign_key_list:int,expression_terms:int,auxiliary_terms:int,foreign_key_groups:int,table_valued_foreign_key_rows:int} $next
     * @return array{index_xinfo:int,foreign_key_list:int,expression_terms:int,auxiliary_terms:int,foreign_key_groups:int,table_valued_foreign_key_rows:int,total:int}
     */
    private static function delta(array $current, array $next): array
    {
        $delta = [
            'index_xinfo' => $next['index_xinfo'] - $current['index_xinfo'],
            'foreign_key_list' => $next['foreign_key_list'] - $current['foreign_key_list'],
            'expression_terms' => $next['expression_terms'] - $current['expression_terms'],
            'auxiliary_terms' => $next['auxiliary_terms'] - $current['auxiliary_terms'],
            'foreign_key_groups' => $next['foreign_key_groups'] - $current['foreign_key_groups'],
            'table_valued_foreign_key_rows' => $next['table_valued_foreign_key_rows'] - $current['table_valued_foreign_key_rows'],
        ];
        $delta['total'] = $delta['index_xinfo'] + $delta['foreign_key_list'];

        return $delta;
    }

    private static function normalizePragmaSql(string $pragma, string $schema, string $target): string
    {
        return 'pragma ' . strtolower($schema) . '.' . $pragma . '(' . self::quoteIdentifier($target) . ')';
    }

    private static function normalizeTableValuedPragmaSql(string $pragma, string $schema, string $target): string
    {
        return 'pragma_' . strtolower($pragma) . '(' . self::quoteLiteral($target) . ',' . self::quoteLiteral(strtolower($schema)) . ')';
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private static function quoteLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * @param list<SQLiteSchemaRecord> $currentRecords
     * @param list<SQLiteSchemaRecord> $nextRecords
     */
    private static function sourceId(array $currentRecords, array $nextRecords, string $indexSql, string $foreignKeySql, bool $tableValuedIndexXinfo, bool $tableValuedForeignKeyList): string
    {
        return hash('sha256', implode("\n", [
            $indexSql,
            $foreignKeySql,
            $tableValuedIndexXinfo ? 'index-table-valued' : 'index-pragma',
            $tableValuedForeignKeyList ? 'fk-table-valued' : 'fk-pragma',
            self::catalogHash($currentRecords),
            self::catalogHash($nextRecords),
        ]));
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function catalogHash(array $records): string
    {
        $parts = [];
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next202 records must be SQLiteSchemaRecord instances');
            }
            $parts[] = implode('|', [
                $record->type,
                $record->name,
                $record->tableName,
                (string) $record->rootPage,
                (string) $record->rowId,
                $record->sql ?? '',
            ]);
        }
        sort($parts);

        return hash('sha256', implode("\n", $parts));
    }
}
