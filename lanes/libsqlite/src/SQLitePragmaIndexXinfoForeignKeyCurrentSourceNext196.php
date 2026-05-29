<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext196
{
    /**
     * @param list<SQLiteSchemaRecord> $currentRecords
     * @param list<SQLiteSchemaRecord> $nextRecords
     * @param array{source_id:string,offset:int}|null $resume
     * @return array{status:string,operation:string,source_id:string,current_source:array{index_xinfo_sql:string,foreign_key_sql:string,schema:string,index:string,table:string,current_catalog_hash:string,next_catalog_hash:string},offset:int,limit:int,count:int,total:int,next:array{source_id:string,offset:int}|null,next_row:array<string,mixed>|null,current:array{index_xinfo:int,foreign_key_list:int,expression_terms:int,auxiliary_terms:int,foreign_key_groups:int},next_counts:array{index_xinfo:int,foreign_key_list:int,expression_terms:int,auxiliary_terms:int,foreign_key_groups:int},delta:array{index_xinfo:int,foreign_key_list:int,expression_terms:int,auxiliary_terms:int,foreign_key_groups:int,total:int},rows:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function page(
        array $currentRecords,
        array $nextRecords,
        string $indexXinfoSql,
        string $foreignKeySql,
        int $offset = 0,
        int $limit = 50,
        ?array $resume = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next196 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next196 limit must be positive');
        }

        $indexParsed = SQLitePragmaSchemaCatalog::parsePragma($indexXinfoSql);
        $foreignKeyParsed = SQLitePragmaSchemaCatalog::parsePragma($foreignKeySql);
        if ($indexParsed['pragma'] !== 'index_xinfo') {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next196 requires PRAGMA index_xinfo');
        }
        if ($foreignKeyParsed['pragma'] !== 'foreign_key_list') {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next196 requires PRAGMA foreign_key_list');
        }

        $schema = $indexParsed['schema'] ?? $foreignKeyParsed['schema'] ?? 'main';
        $indexName = $indexParsed['target'];
        $tableName = $foreignKeyParsed['target'];
        $normalizedIndexSql = self::normalizePragmaSql('index_xinfo', $schema, $indexName);
        $normalizedForeignKeySql = self::normalizePragmaSql('foreign_key_list', $schema, $tableName);
        $sourceId = self::sourceId($currentRecords, $nextRecords, $normalizedIndexSql, $normalizedForeignKeySql);

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next196 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next196 resume cursor offset mismatch');
            }
        }

        $currentCatalog = new SQLitePragmaSchemaCatalog($currentRecords);
        $nextCatalog = new SQLitePragmaSchemaCatalog($nextRecords);
        $currentRows = self::combinedRows($currentCatalog, $indexName, $tableName, 'current');
        $nextRows = self::combinedRows($nextCatalog, $indexName, $tableName, 'next');
        $allRows = array_merge($currentRows, $nextRows);
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);

        return [
            'status' => 'ok',
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next196',
            'source_id' => $sourceId,
            'current_source' => [
                'index_xinfo_sql' => $normalizedIndexSql,
                'foreign_key_sql' => $normalizedForeignKeySql,
                'schema' => $schema,
                'index' => $indexName,
                'table' => $tableName,
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
                'sqlite-pragma-foreign-key-list-current-source',
                'sqlite-wordpress-copied-schema-reparse-preflight',
            ],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function combinedRows(SQLitePragmaSchemaCatalog $catalog, string $indexName, string $tableName, string $phase): array
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
            ];
        }

        foreach ($catalog->foreignKeyList($tableName) as $row) {
            $rows[] = [
                'phase' => $phase,
                'kind' => 'foreign_key_list',
                'source' => 'foreign_key_list',
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
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{index_xinfo:int,foreign_key_list:int,expression_terms:int,auxiliary_terms:int,foreign_key_groups:int}
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
            }
        }
        $counts['foreign_key_groups'] = count($foreignKeyIds);

        return $counts;
    }

    /**
     * @param array{index_xinfo:int,foreign_key_list:int,expression_terms:int,auxiliary_terms:int,foreign_key_groups:int} $current
     * @param array{index_xinfo:int,foreign_key_list:int,expression_terms:int,auxiliary_terms:int,foreign_key_groups:int} $next
     * @return array{index_xinfo:int,foreign_key_list:int,expression_terms:int,auxiliary_terms:int,foreign_key_groups:int,total:int}
     */
    private static function delta(array $current, array $next): array
    {
        $delta = [
            'index_xinfo' => $next['index_xinfo'] - $current['index_xinfo'],
            'foreign_key_list' => $next['foreign_key_list'] - $current['foreign_key_list'],
            'expression_terms' => $next['expression_terms'] - $current['expression_terms'],
            'auxiliary_terms' => $next['auxiliary_terms'] - $current['auxiliary_terms'],
            'foreign_key_groups' => $next['foreign_key_groups'] - $current['foreign_key_groups'],
        ];
        $delta['total'] = array_sum($delta);

        return $delta;
    }

    private static function normalizePragmaSql(string $pragma, string $schema, string $target): string
    {
        return 'pragma ' . strtolower($schema) . '.' . $pragma . '(' . self::quoteIdentifier($target) . ')';
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * @param list<SQLiteSchemaRecord> $currentRecords
     * @param list<SQLiteSchemaRecord> $nextRecords
     */
    private static function sourceId(array $currentRecords, array $nextRecords, string $indexSql, string $foreignKeySql): string
    {
        return hash('sha256', implode("\n", [
            $indexSql,
            $foreignKeySql,
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
