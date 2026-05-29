<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaQuickcheckIndexForeignKeyCurrentSourceNext
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array<string,mixed>,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,mixed>,next_state:array{ready:bool,blocking:list<string>},next:array{source_id:string,offset:int}|null,current_row:array<string,mixed>|null,next_row:array<string,mixed>|null,rows:list<array<string,mixed>>}
     */
    public static function page(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexListSql,
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        int $offset = 0,
        int $limit = 138,
        string $quickCheckSql = 'PRAGMA quick_check',
        bool $tableValuedIndexList = false,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck/index/FK current-source next138 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck/index/FK current-source next138 limit must be positive');
        }
        if (!preg_match('/^pragma\s+(?:[a-z_][a-z0-9_]*\.)?quick_check(?:\s*\(|$)/i', trim($quickCheckSql))) {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck/index/FK current-source next138 requires PRAGMA quick_check');
        }

        $index = SQLitePragmaIndexIntegrityCursorCurrentSourceNext::page(
            $catalog,
            $indexListSql,
            $database,
            0,
            PHP_INT_MAX,
            $quickCheckSql,
            $tableValuedIndexList,
        );
        $foreignKeys = self::foreignKeyRows($foreignKeySql, $schemas, $catalog);
        $sourceId = self::stableHash([
            'mode' => 'pragma-quickcheck-index-foreignkey-current-source-next138',
            'index' => $index['source_id'],
            'foreign_key_sql' => self::normalizeSql($foreignKeySql),
            'schemas' => self::stableHash($schemas),
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $rows = self::rows($index['rows'], $foreignKeys['rows']);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $current = self::current($index, $foreignKeys, $rows);
        $blocking = self::blocking($current);

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'source_id' => $sourceId,
            'current_source' => [
                'mode' => 'quickcheck_index_foreignkey_current_source_next138',
                'index_source_id' => $index['source_id'],
                'index_list_sql' => $index['current_source']['index_list_sql'],
                'quick_check_sql' => $index['current_source']['integrity_sql'],
                'foreign_key_sql' => self::normalizeSql($foreignKeySql),
                'database' => $index['current_source']['database'],
                'catalog' => $index['current_source']['catalog'],
                'schemas' => self::stableHash($schemas),
                'table_valued_index_list' => $tableValuedIndexList,
                'table_valued_foreign_key' => $foreignKeys['table_valued'],
            ],
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $current,
            'next_state' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
            'next' => $complete ? null : [
                'source_id' => $sourceId,
                'offset' => $nextOffset,
            ],
            'current_row' => $pageRows[0] ?? null,
            'next_row' => $pageRows[1] ?? null,
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<array<string,mixed>> $indexRows
     * @param list<array<string,mixed>> $foreignKeyRows
     * @return list<array<string,mixed>>
     */
    private static function rows(array $indexRows, array $foreignKeyRows): array
    {
        return [
            ...array_map(
                static fn (array $row): array => [
                    ...$row,
                    'phase' => match ($row['source'] ?? $row['kind'] ?? null) {
                        'index_list' => 'index_list',
                        'index_xinfo' => 'index_xinfo',
                        'rootpage_integrity', 'rootpage' => 'quick_check_rootpage',
                        default => 'quick_check',
                    },
                ],
                $indexRows,
            ),
            ...array_map(
                static fn (array $row): array => [
                    ...$row,
                    'phase' => 'foreign_key_check',
                    'source' => 'foreign_key_check',
                    'message' => self::foreignKeyMessage($row),
                ],
                $foreignKeyRows,
            ),
        ];
    }

    /**
     * @param array<string,mixed> $index
     * @param array{rows:list<array<string,mixed>>,table_valued:bool} $foreignKeys
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function current(array $index, array $foreignKeys, array $rows): array
    {
        $fkSchemas = [];
        $fkTables = [];
        foreach ($foreignKeys['rows'] as $row) {
            if (is_string($row['schema'] ?? null)) {
                $fkSchemas[] = $row['schema'];
            }
            if (is_string($row['table'] ?? null)) {
                $fkTables[] = $row['table'];
            }
        }

        return [
            'index_list' => $index['current']['index_list'],
            'index_xinfo' => $index['current']['index_xinfo'],
            'quick_check_rootpages' => $index['current']['rootpage'],
            'quick_check_errors' => $index['current']['rootpage_errors'],
            'foreign_key_violations' => count($foreignKeys['rows']),
            'target_schema' => $index['current']['target_schema'],
            'target_table' => $index['current']['target_table'],
            'indexes' => $index['current']['indexes'],
            'foreign_key_schemas' => array_values(array_unique($fkSchemas)),
            'foreign_key_tables' => array_values(array_unique($fkTables)),
            'row_phases' => self::phaseCounts($rows),
        ];
    }

    /**
     * @param array<string,mixed> $current
     * @return list<string>
     */
    private static function blocking(array $current): array
    {
        $blocking = [];
        if (($current['quick_check_errors'] ?? 0) > 0) {
            $blocking[] = 'quick_check';
        }
        if (($current['foreign_key_violations'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_check';
        }

        return $blocking;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{rows:list<array<string,mixed>>,table_valued:bool}
     */
    private static function foreignKeyRows(string $foreignKeySql, array $schemas, SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog): array
    {
        $normalized = self::normalizeSql($foreignKeySql);
        $tableValued = str_starts_with($normalized, 'select ')
            || str_starts_with($normalized, 'pragma_foreign_key_check')
            || str_contains($normalized, '.pragma_foreign_key_check(');
        $result = $tableValued && $catalog instanceof SQLiteAttachedSchemaCatalog
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
    private static function foreignKeyMessage(array $row): string
    {
        $rowid = $row['rowid'] === null ? 'NULL' : (string) $row['rowid'];

        return "foreign key mismatch in {$row['schema']}.{$row['table']} rowid {$rowid} references {$row['parent']} fkid {$row['fkid']}";
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,int>
     */
    private static function phaseCounts(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $phase = (string) ($row['phase'] ?? 'unknown');
            $counts[$phase] = ($counts[$phase] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck/index/FK current-source next138 cursor source changed');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck/index/FK current-source next138 cursor offset changed');
        }
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function normalizeSql(string $sql): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', rtrim($sql, " \t\r\n;")) ?? trim($sql)));
    }
}
