<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext154
{
    /**
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array<string,mixed>,next_source:array<string,mixed>,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,mixed>,next_counts:array<string,mixed>,delta:array<string,mixed>,next_state:array{ready:bool,blocking:list<string>},next:array{source_id:string,offset:int}|null,rows:list<array<string,mixed>>}
     */
    public static function currentNextPage(
        SQLiteAttachedSchemaCatalog $currentCatalog,
        SQLiteAttachedSchemaCatalog $nextCatalog,
        string $indexXinfoSql,
        string $foreignKeyListSql,
        int $offset = 0,
        int $limit = 154,
        bool $tableValuedIndexXinfo = false,
        bool $tableValuedForeignKeyList = false,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK-list current-source next154 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK-list current-source next154 limit must be positive');
        }

        $currentSource = self::source($currentCatalog, $indexXinfoSql, $foreignKeyListSql, $tableValuedIndexXinfo, $tableValuedForeignKeyList);
        $nextSource = self::source($nextCatalog, $indexXinfoSql, $foreignKeyListSql, $tableValuedIndexXinfo, $tableValuedForeignKeyList);
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next154',
            'current' => $currentSource['source_id'],
            'next' => $nextSource['source_id'],
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentRows = self::sideRows('current', self::collect($currentCatalog, $indexXinfoSql, $foreignKeyListSql, $tableValuedIndexXinfo, $tableValuedForeignKeyList));
        $nextRows = self::sideRows('next', self::collect($nextCatalog, $indexXinfoSql, $foreignKeyListSql, $tableValuedIndexXinfo, $tableValuedForeignKeyList));
        $rows = [...$currentRows, ...$nextRows];
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $currentCounts = self::counts($currentRows);
        $nextCounts = self::counts($nextRows);
        $delta = self::delta($currentRows, $nextRows, $currentCounts, $nextCounts);
        $blocking = self::blocking($delta, $nextCounts);

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
     * @return list<array<string,mixed>>
     */
    public static function collect(
        SQLiteAttachedSchemaCatalog $catalog,
        string $indexXinfoSql,
        string $foreignKeyListSql,
        bool $tableValuedIndexXinfo = false,
        bool $tableValuedForeignKeyList = false,
    ): array {
        $index = $tableValuedIndexXinfo
            ? $catalog->executeTableValuedPragma($indexXinfoSql)
            : $catalog->executeSchemaPragma($indexXinfoSql);
        if (($index['pragma'] ?? null) !== 'index_xinfo') {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK-list current-source next154 requires index_xinfo rows');
        }
        if (($index['rows'] ?? []) === []) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK-list current-source next154 requires a resolved index_xinfo target');
        }

        $foreignKeys = $tableValuedForeignKeyList
            ? $catalog->executeTableValuedPragma($foreignKeyListSql)
            : $catalog->executeSchemaPragma($foreignKeyListSql);
        if (($foreignKeys['pragma'] ?? null) !== 'foreign_key_list') {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK-list current-source next154 requires foreign_key_list rows');
        }
        if (($foreignKeys['rows'] ?? []) === []) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK-list current-source next154 requires a resolved foreign_key_list target');
        }

        $rows = [];
        foreach ($index['rows'] as $row) {
            $rows[] = [
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
                'message' => self::indexMessage($index['schema'], $index['target'], $row),
            ];
        }
        foreach ($foreignKeys['rows'] as $row) {
            $rows[] = [
                'kind' => 'foreign_key_list',
                'phase' => 'foreign_key_list',
                'schema' => $foreignKeys['schema'],
                'target' => $foreignKeys['target'],
                'id' => $row['id'],
                'seq' => $row['seq'],
                'table' => $row['table'],
                'from' => $row['from'],
                'to' => $row['to'],
                'on_update' => $row['on_update'],
                'on_delete' => $row['on_delete'],
                'match' => $row['match'],
                'message' => self::foreignKeyMessage($foreignKeys['schema'], $foreignKeys['target'], $row),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function sideRows(string $side, array $rows): array
    {
        return array_map(static fn (array $row): array => ['side' => $side, ...$row], $rows);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'index_xinfo' => 0,
            'index_key_columns' => 0,
            'index_aux_columns' => 0,
            'index_expression_columns' => 0,
            'foreign_key_list' => 0,
            'foreign_key_actions' => [],
            'schemas' => [],
            'targets' => [],
            'index_collations' => [],
            'foreign_key_parents' => [],
        ];

        foreach ($rows as $row) {
            $schema = (string) ($row['schema'] ?? '');
            if ($schema !== '' && !in_array($schema, $counts['schemas'], true)) {
                $counts['schemas'][] = $schema;
            }
            $target = (string) ($row['target'] ?? '');
            if ($target !== '' && !in_array($target, $counts['targets'], true)) {
                $counts['targets'][] = $target;
            }

            if (($row['kind'] ?? null) === 'index_xinfo') {
                $counts['index_xinfo']++;
                if ((int) ($row['key'] ?? 0) === 1) {
                    $counts['index_key_columns']++;
                } else {
                    $counts['index_aux_columns']++;
                }
                if ((int) ($row['key'] ?? 0) === 1 && (int) ($row['cid'] ?? 0) < 0 && ($row['name'] ?? null) === null) {
                    $counts['index_expression_columns']++;
                }
                $collation = (string) ($row['coll'] ?? '');
                if ($collation !== '' && !in_array($collation, $counts['index_collations'], true)) {
                    $counts['index_collations'][] = $collation;
                }
                continue;
            }

            if (($row['kind'] ?? null) === 'foreign_key_list') {
                $counts['foreign_key_list']++;
                $action = (string) ($row['on_update'] ?? '') . '/' . (string) ($row['on_delete'] ?? '');
                if ($action !== '/' && !in_array($action, $counts['foreign_key_actions'], true)) {
                    $counts['foreign_key_actions'][] = $action;
                }
                $parent = (string) ($row['table'] ?? '');
                if ($parent !== '' && !in_array($parent, $counts['foreign_key_parents'], true)) {
                    $counts['foreign_key_parents'][] = $parent;
                }
            }
        }

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array<string,mixed> $currentCounts
     * @param array<string,mixed> $nextCounts
     * @return array<string,mixed>
     */
    private static function delta(array $currentRows, array $nextRows, array $currentCounts, array $nextCounts): array
    {
        $currentIndex = self::signatures($currentRows, 'index_xinfo');
        $nextIndex = self::signatures($nextRows, 'index_xinfo');
        $currentFk = self::signatures($currentRows, 'foreign_key_list');
        $nextFk = self::signatures($nextRows, 'foreign_key_list');

        return [
            'index_xinfo' => $nextCounts['index_xinfo'] - $currentCounts['index_xinfo'],
            'foreign_key_list' => $nextCounts['foreign_key_list'] - $currentCounts['foreign_key_list'],
            'index_changed' => $currentIndex !== $nextIndex,
            'foreign_key_changed' => $currentFk !== $nextFk,
            'index_added' => array_values(array_diff($nextIndex, $currentIndex)),
            'index_removed' => array_values(array_diff($currentIndex, $nextIndex)),
            'foreign_key_added' => array_values(array_diff($nextFk, $currentFk)),
            'foreign_key_removed' => array_values(array_diff($currentFk, $nextFk)),
            'stable' => $currentIndex === $nextIndex && $currentFk === $nextFk,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function signatures(array $rows, string $kind): array
    {
        $signatures = [];
        foreach ($rows as $row) {
            if (($row['kind'] ?? null) !== $kind) {
                continue;
            }
            $copy = $row;
            unset($copy['side'], $copy['message'], $copy['phase'], $copy['kind']);
            $signatures[] = self::stableHash($copy);
        }
        sort($signatures);

        return $signatures;
    }

    /**
     * @param array<string,mixed> $delta
     * @param array<string,mixed> $nextCounts
     * @return list<string>
     */
    private static function blocking(array $delta, array $nextCounts): array
    {
        $blocking = [];
        if (($nextCounts['index_xinfo'] ?? 0) === 0) {
            $blocking[] = 'index_xinfo';
        }
        if (($nextCounts['foreign_key_list'] ?? 0) === 0) {
            $blocking[] = 'foreign_key_list';
        }
        if (($delta['index_changed'] ?? false) === true) {
            $blocking[] = 'index_xinfo_drift';
        }
        if (($delta['foreign_key_changed'] ?? false) === true) {
            $blocking[] = 'foreign_key_list_drift';
        }

        return $blocking;
    }

    /**
     * @return array<string,mixed>
     */
    private static function source(
        SQLiteAttachedSchemaCatalog $catalog,
        string $indexXinfoSql,
        string $foreignKeyListSql,
        bool $tableValuedIndexXinfo,
        bool $tableValuedForeignKeyList,
    ): array {
        $source = [
            'catalog' => self::catalogHash($catalog),
            'index_xinfo_sql' => self::normalizeSql($indexXinfoSql),
            'foreign_key_list_sql' => self::normalizeSql($foreignKeyListSql),
            'table_valued_index_xinfo' => $tableValuedIndexXinfo,
            'table_valued_foreign_key_list' => $tableValuedForeignKeyList,
        ];

        return [
            ...$source,
            'source_id' => self::stableHash($source),
        ];
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

    private static function catalogHash(SQLiteAttachedSchemaCatalog $catalog): string
    {
        $payload = [
            'databases' => $catalog->databaseList(),
            'records' => [],
        ];
        foreach ($catalog->databaseList() as $database) {
            $schema = (string) $database['name'];
            $payload['records'][$schema] = array_map(
                static fn (SQLiteSchemaRecord $record): array => [
                    'type' => $record->type,
                    'name' => $record->name,
                    'table' => $record->tableName,
                    'root' => $record->rootPage,
                    'sql' => $record->sql,
                    'rowid' => $record->rowId,
                ],
                $catalog->schemaRecords($schema),
            );
        }

        return self::stableHash($payload);
    }

    private static function normalizeSql(string $sql): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', rtrim(trim($sql), ';')) ?? trim($sql)));
    }

    /**
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null} $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK-list current-source next154 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && (int) $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK-list current-source next154 cursor offset is stale');
        }
    }

    /**
     * @param array<string,mixed> $value
     */
    private static function stableHash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<string,int|string|null> $row
     */
    private static function indexMessage(string $schema, string $index, array $row): string
    {
        $name = $row['name'] === null ? '<expr>' : (string) $row['name'];

        return sprintf(
            'index_xinfo %s.%s seqno %d cid %d %s coll %s key %d',
            $schema,
            $index,
            (int) $row['seqno'],
            (int) $row['cid'],
            $name,
            (string) $row['coll'],
            (int) $row['key'],
        );
    }

    /**
     * @param array<string,int|string|null> $row
     */
    private static function foreignKeyMessage(string $schema, string $table, array $row): string
    {
        return sprintf(
            'foreign_key_list %s.%s id %d seq %d %s references %s(%s) update %s delete %s match %s',
            $schema,
            $table,
            (int) $row['id'],
            (int) $row['seq'],
            (string) $row['from'],
            (string) $row['table'],
            (string) ($row['to'] ?? ''),
            (string) $row['on_update'],
            (string) $row['on_delete'],
            (string) $row['match'],
        );
    }
}
