<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext172
{
    /**
     * @param list<SQLiteSchemaRecord> $currentRecords
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param list<SQLiteSchemaRecord> $nextRecords
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array<string,mixed>
     */
    public static function currentNextPageFromCatalog(
        array $currentRecords,
        array $currentTables,
        array $nextRecords,
        array $nextTables,
        string $indexXinfoSql,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        int $offset = 0,
        int $limit = 172,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next172 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next172 limit must be positive');
        }

        $currentForeignKeys = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext166::foreignKeysFromCatalog($currentRecords);
        $nextForeignKeys = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext166::foreignKeysFromCatalog($nextRecords);
        $target = self::foreignKeyTarget($foreignKeySql);
        $currentCatalog = new SQLitePragmaSchemaCatalog($currentRecords);
        $nextCatalog = new SQLitePragmaSchemaCatalog($nextRecords);

        $currentSource = self::source($currentRecords, $currentForeignKeys, $currentTables, $indexXinfoSql, $foreignKeySql, $target, $tableValuedIndexXinfo);
        $nextSource = self::source($nextRecords, $nextForeignKeys, $nextTables, $indexXinfoSql, $foreignKeySql, $target, $tableValuedIndexXinfo);
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next172',
            'current' => $currentSource['source_id'],
            'next' => $nextSource['source_id'],
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentRows = self::sideRows('current', self::collect($currentCatalog, $indexXinfoSql, $currentRecords, $currentForeignKeys, $currentTables, $target, $tableValuedIndexXinfo));
        $nextRows = self::sideRows('next', self::collect($nextCatalog, $indexXinfoSql, $nextRecords, $nextForeignKeys, $nextTables, $target, $tableValuedIndexXinfo));
        $rows = [...$currentRows, ...$nextRows];
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $currentCounts = self::counts($currentRows);
        $nextCounts = self::counts($nextRows);
        $blocking = self::blocking($nextCounts);

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
            'delta' => [
                'index_xinfo' => $nextCounts['index_xinfo'] - $currentCounts['index_xinfo'],
                'index_admissions' => $nextCounts['index_admissions'] - $currentCounts['index_admissions'],
                'index_blockers' => $nextCounts['index_blockers'] - $currentCounts['index_blockers'],
                'foreign_key_violations' => $nextCounts['foreign_key_violations'] - $currentCounts['foreign_key_violations'],
                'total_blockers' => $nextCounts['total_blockers'] - $currentCounts['total_blockers'],
                'cleared' => $currentCounts['total_blockers'] > 0 && $nextCounts['total_blockers'] === 0,
            ],
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
     * @param list<SQLiteSchemaRecord> $records
     * @param list<array<string,mixed>> $foreignKeys
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<array<string,mixed>>
     */
    private static function collect(
        SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        array $records,
        array $foreignKeys,
        array $tables,
        ?string $target,
        bool $tableValuedIndexXinfo,
    ): array {
        $indexRows = array_map(
            static fn (array $row): array => [
                ...$row,
                'phase' => 'index_xinfo',
                'source' => 'index_xinfo',
            ],
            SQLitePragmaIndexXinfoIntegrityRootYield::collect($catalog, $indexXinfoSql, '', 'PRAGMA integrity_check', $tableValuedIndexXinfo),
        );
        $indexRows = array_values(array_filter($indexRows, static fn (array $row): bool => ($row['kind'] ?? null) === 'index_xinfo'));

        $integrityRows = [];
        foreach (SQLitePragmaForeignKeyIndexIntegrityYield::collect($records, $foreignKeys, $tables) as $row) {
            if ($target !== null && strcasecmp((string) ($row['table'] ?? ''), $target) !== 0) {
                continue;
            }
            if (($row['kind'] ?? null) === 'foreign_key_check') {
                continue;
            }
            $integrityRows[] = [
                ...$row,
                'phase' => 'foreign_key_parent_index',
                'source' => 'foreign_key_parent_index',
            ];
        }

        $violations = array_map(
            static fn (array $row): array => [
                'kind' => 'foreign_key_check',
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => $row['parent'],
                'fkid' => $row['fkid'],
                'index' => null,
                'columns' => [],
                'collations' => [],
                'status' => 'violation',
                'message' => "foreign key mismatch in {$row['table']} rowid {$row['rowid']} references {$row['parent']} fkid {$row['fkid']}",
                'phase' => 'foreign_key_check',
                'source' => 'foreign_key_check',
            ],
            SQLitePragmaForeignKeyCheck::check($tables, $foreignKeys, $target),
        );

        return [...$indexRows, ...$integrityRows, ...$violations];
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
            'index_admissions' => 0,
            'index_blockers' => 0,
            'foreign_key_violations' => 0,
            'total_blockers' => 0,
            'target_schema' => 'main',
            'target_index' => '',
            'foreign_key_tables' => [],
            'parent_indexes' => [],
        ];

        foreach ($rows as $row) {
            $kind = $row['kind'] ?? null;
            if ($kind === 'index_xinfo') {
                $counts['index_xinfo']++;
                $counts['target_schema'] = (string) ($row['schema'] ?? $counts['target_schema']);
                $counts['target_index'] = (string) ($row['target'] ?? $counts['target_index']);
                continue;
            }
            if ($kind === 'index_admission') {
                $counts['index_admissions']++;
                self::appendUnique($counts['foreign_key_tables'], (string) ($row['table'] ?? ''));
                self::appendUnique($counts['parent_indexes'], (string) ($row['index'] ?? ''));
                if (($row['status'] ?? 'ok') !== 'ok') {
                    $counts['index_blockers']++;
                    $counts['total_blockers']++;
                }
                continue;
            }
            if ($kind === 'foreign_key_check') {
                $counts['foreign_key_violations']++;
                $counts['total_blockers']++;
                self::appendUnique($counts['foreign_key_tables'], (string) ($row['table'] ?? ''));
            }
        }

        return $counts;
    }

    /**
     * @param list<string> $values
     */
    private static function appendUnique(array &$values, string $value): void
    {
        if ($value !== '' && !in_array($value, $values, true)) {
            $values[] = $value;
        }
    }

    /**
     * @param array<string,mixed> $counts
     * @return list<string>
     */
    private static function blocking(array $counts): array
    {
        $blocking = [];
        if (($counts['index_blockers'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_parent_unique_index';
        }
        if (($counts['foreign_key_violations'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_check';
        }

        return $blocking;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<array<string,mixed>> $foreignKeys
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,mixed>
     */
    private static function source(array $records, array $foreignKeys, array $tables, string $indexXinfoSql, string $foreignKeySql, ?string $target, bool $tableValuedIndexXinfo): array
    {
        $source = [
            'records' => self::stableHash(self::schemaRecordsSnapshot($records)),
            'foreign_keys' => self::stableHash($foreignKeys),
            'tables' => self::stableHash($tables),
            'index_xinfo_sql' => self::normalizeSql($indexXinfoSql),
            'foreign_key_sql' => self::normalizeSql($foreignKeySql),
            'foreign_key_target' => $target,
            'table_valued_index_xinfo' => $tableValuedIndexXinfo,
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
        return [
            'records' => $source['records'],
            'foreign_keys' => $source['foreign_keys'],
            'tables' => $source['tables'],
            'index_xinfo_sql' => $source['index_xinfo_sql'],
            'foreign_key_sql' => $source['foreign_key_sql'],
            'foreign_key_target' => $source['foreign_key_target'],
            'table_valued_index_xinfo' => $source['table_valued_index_xinfo'],
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array{type:string,name:string,table:string,rootpage:int|null,sql:string|null,rowid:int}>
     */
    private static function schemaRecordsSnapshot(array $records): array
    {
        return array_map(
            static function (SQLiteSchemaRecord $record): array {
                if (!$record instanceof SQLiteSchemaRecord) {
                    throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next172 records must be SQLiteSchemaRecord instances');
                }

                return [
                    'type' => $record->type,
                    'name' => $record->name,
                    'table' => $record->tableName,
                    'rootpage' => $record->rootPage,
                    'sql' => $record->sql,
                    'rowid' => $record->rowId,
                ];
            },
            $records,
        );
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next172 cursor does not match the current/next source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next172 cursor offset does not match the requested page offset');
        }
    }

    private static function foreignKeyTarget(string $foreignKeySql): ?string
    {
        $emptyTables = [];
        $emptyKeys = [];
        return SQLitePragmaForeignKeyCheck::execute($foreignKeySql, $emptyTables, $emptyKeys)['target'];
    }

    private static function normalizeSql(string $sql): string
    {
        return strtolower(preg_replace('/\s+/', ' ', rtrim(trim($sql), ';')) ?? trim($sql));
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
