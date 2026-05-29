<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaQuickcheckForeignKeyRootpageCurrentSourceNext
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array<string,mixed>,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,mixed>,next_state:array{ready:bool,blocking:list<string>},next:array{source_id:string,offset:int}|null,next_row:array<string,mixed>|null,rows:list<array<string,mixed>>}
     */
    public static function page(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        array $schemas,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        int $offset = 0,
        int $limit = 132,
        string $quickCheckSql = 'PRAGMA quick_check',
        bool $tableValuedIndex = false,
        ?array $cursor = null,
    ): array {
        if (!$catalog instanceof SQLiteAttachedSchemaCatalog) {
            $catalog = new SQLiteAttachedSchemaCatalog($catalog->records);
        }
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck foreign-key rootpage current-source next132 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck foreign-key rootpage current-source next132 limit must be positive');
        }

        $quick = SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext::page(
            $catalog,
            $indexXinfoSql,
            $database,
            0,
            PHP_INT_MAX,
            $quickCheckSql,
            $tableValuedIndex,
        );
        $foreignKeys = SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page(
            $database,
            $schemas,
            $catalog,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );
        $sourceId = self::stableHash([
            'quick' => $quick['source_id'],
            'foreign_keys' => $foreignKeys['source_id'],
            'mode' => 'pragma-quickcheck-foreignkey-rootpage-current-source-next132',
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $rows = self::rows($quick['rows'], $foreignKeys['rows']);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $current = self::current($quick, $foreignKeys, $rows, $catalog);
        $blocking = self::blocking($current);

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'source_id' => $sourceId,
            'current_source' => [
                'mode' => 'quickcheck_foreignkey_rootpage_current_source_next132',
                'quickcheck_source_id' => $quick['source_id'],
                'foreign_key_source_id' => $foreignKeys['source_id'],
                'index_xinfo_sql' => $quick['current_source']['index_xinfo_sql'],
                'quick_check_sql' => $quick['current_source']['quick_check_sql'],
                'foreign_key_sql' => $foreignKeys['current_source']['foreign_key_sql'],
                'database' => $quick['current_source']['database'],
                'catalog' => $quick['current_source']['catalog'],
                'schemas' => $foreignKeys['current_source']['schemas'],
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
            'next_row' => $pageRows[1] ?? null,
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<array<string,mixed>> $quickRows
     * @param list<array<string,mixed>> $foreignKeyRows
     * @return list<array<string,mixed>>
     */
    private static function rows(array $quickRows, array $foreignKeyRows): array
    {
        return [
            ...array_map(
                static fn (array $row): array => [
                    ...$row,
                    'phase' => ($row['kind'] ?? null) === 'index_xinfo' ? 'index_xinfo' : 'quick_check',
                    'source' => ($row['kind'] ?? null) === 'index_xinfo' ? 'index_xinfo' : 'quick_check',
                ],
                $quickRows,
            ),
            ...array_map(
                static fn (array $row): array => [
                    ...$row,
                    'phase' => 'foreign_key_rootpage',
                    'source' => 'foreign_key_rootpage',
                ],
                $foreignKeyRows,
            ),
        ];
    }

    /**
     * @param array<string,mixed> $quick
     * @param array<string,mixed> $foreignKeys
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function current(array $quick, array $foreignKeys, array $rows, SQLiteAttachedSchemaCatalog $catalog): array
    {
        $targetTables = [];
        foreach ($rows as $row) {
            if (is_string($row['table'] ?? null) && $row['table'] !== '') {
                $targetTables[] = $row['table'];
            }
        }
        $targetTable = null;
        $targetIndex = $quick['current']['target_index'];
        if (is_string($targetIndex)) {
            foreach ($catalog->schemaRecords((string) $quick['current']['target_schema']) as $record) {
                if ($record->type === 'index' && strcasecmp($record->name, $targetIndex) === 0) {
                    $targetTable = $record->tableName;
                    break;
                }
            }
        }

        return [
            'index_xinfo' => $quick['current']['index_xinfo'],
            'quick_check' => $quick['current']['quick_check'],
            'quick_check_errors' => $quick['current']['quick_check_errors'],
            'foreign_key_violations' => $foreignKeys['current']['foreign_key_violations'],
            'child_rootpage_errors' => $foreignKeys['current']['child_rootpage_errors'],
            'parent_rootpage_errors' => $foreignKeys['current']['parent_rootpage_errors'],
            'missing_catalog_rootpages' => $foreignKeys['current']['missing_catalog_rootpages'],
            'pointer_map_conflicts' => $foreignKeys['current']['pointer_map_conflicts'],
            'target_schema' => $quick['current']['target_schema'],
            'target_index' => $quick['current']['target_index'],
            'target_table' => $targetTable ?? $quick['current']['target_table'],
            'foreign_key_schemas' => $foreignKeys['current']['schemas'],
            'tables' => array_values(array_unique($targetTables)),
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
        if (($current['missing_catalog_rootpages'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_rootpage_catalog';
        }
        if (($current['pointer_map_conflicts'] ?? 0) > 0) {
            $blocking[] = 'rootpage_pointer_map';
        }
        if (($current['child_rootpage_errors'] ?? 0) > 0 || ($current['parent_rootpage_errors'] ?? 0) > 0) {
            $blocking[] = 'rootpage_integrity';
        }

        return $blocking;
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
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck foreign-key rootpage current-source next132 cursor does not match the current database/catalog/schema source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck foreign-key rootpage current-source next132 cursor offset does not match the requested page offset');
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

            return '[' . implode(',', array_map(static fn (mixed $item, string|int $key): string => self::stableEncode((string) $key) . ':' . self::stableEncode($item), $value, array_keys($value))) . ']';
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
