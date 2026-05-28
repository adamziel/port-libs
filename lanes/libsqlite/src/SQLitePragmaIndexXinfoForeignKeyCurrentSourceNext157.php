<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext157
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $currentSchemas
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $nextSchemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array<string,mixed>,next_source:array<string,mixed>,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,mixed>,next_counts:array<string,mixed>,delta:array<string,mixed>,next_state:array{ready:bool,blocking:list<string>},next:array{source_id:string,offset:int}|null,rows:list<array<string,mixed>>}
     */
    public static function currentNextPage(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $currentCatalog,
        string|SQLiteDatabase $currentDatabase,
        array $currentSchemas,
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $nextCatalog,
        string|SQLiteDatabase $nextDatabase,
        array $nextSchemas,
        string $indexXinfoSql,
        string $foreignKeySql,
        int $offset = 0,
        int $limit = 157,
        string $integritySql = 'PRAGMA integrity_check',
        bool $indexTableValued = false,
        ?array $cursor = null,
        ?SQLiteAttachedSchemaCatalog $currentForeignKeyCatalog = null,
        ?SQLiteAttachedSchemaCatalog $nextForeignKeyCatalog = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next157 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next157 limit must be positive');
        }

        $current = SQLitePragmaIndexIntegrityForeignKeyCurrentSourceYield::page(
            $currentCatalog,
            $indexXinfoSql,
            $currentDatabase,
            $currentSchemas,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
            $integritySql,
            $indexTableValued,
            null,
            $currentForeignKeyCatalog,
        );
        $next = SQLitePragmaIndexIntegrityForeignKeyCurrentSourceYield::page(
            $nextCatalog,
            $indexXinfoSql,
            $nextDatabase,
            $nextSchemas,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
            $integritySql,
            $indexTableValued,
            null,
            $nextForeignKeyCatalog,
        );

        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next157',
            'current' => $current['source_id'],
            'next' => $next['source_id'],
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentRows = self::sideRows('current', $current['rows']);
        $nextRows = self::sideRows('next', $next['rows']);
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
            'current_source' => $current['current_source'],
            'next_source' => $next['current_source'],
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
                'integrity_root' => $nextCounts['integrity_root'] - $currentCounts['integrity_root'],
                'foreign_key' => $nextCounts['foreign_key'] - $currentCounts['foreign_key'],
                'rootpage_errors' => $nextCounts['rootpage_errors'] - $currentCounts['rootpage_errors'],
                'foreign_key_cleared' => $currentCounts['foreign_key'] > 0 && $nextCounts['foreign_key'] === 0,
                'integrity_cleared' => $currentCounts['rootpage_errors'] > 0 && $nextCounts['rootpage_errors'] === 0,
                'ready' => $blocking === [],
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
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function sideRows(string $side, array $rows): array
    {
        return array_map(static fn (array $row): array => ['side' => $side, ...$row], $rows);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{index_xinfo:int,integrity_root:int,foreign_key:int,rootpage_errors:int,target_schema:string,target_index:string|null,target_table:string|null,foreign_key_tables:list<string>,collations:list<string>,key_columns:int,auxiliary_columns:int,expression_columns:int,rowid_auxiliary:int}
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'index_xinfo' => 0,
            'integrity_root' => 0,
            'foreign_key' => 0,
            'rootpage_errors' => 0,
            'target_schema' => 'main',
            'target_index' => null,
            'target_table' => null,
            'foreign_key_tables' => [],
            'collations' => [],
            'key_columns' => 0,
            'auxiliary_columns' => 0,
            'expression_columns' => 0,
            'rowid_auxiliary' => 0,
        ];

        foreach ($rows as $row) {
            $kind = (string) ($row['kind'] ?? '');
            if ($kind === 'index_xinfo') {
                $counts['index_xinfo']++;
                $counts['target_schema'] = (string) ($row['schema'] ?? $counts['target_schema']);
                $counts['target_index'] = (string) ($row['target'] ?? $counts['target_index']);
                $counts['target_table'] = (string) ($row['table'] ?? $counts['target_table']);
                $collation = (string) ($row['coll'] ?? 'BINARY');
                if ($collation !== '' && !in_array($collation, $counts['collations'], true)) {
                    $counts['collations'][] = $collation;
                }
                if ((int) ($row['key'] ?? 1) === 1) {
                    $counts['key_columns']++;
                } else {
                    $counts['auxiliary_columns']++;
                }
                if ((int) ($row['cid'] ?? 0) === -2) {
                    $counts['expression_columns']++;
                }
                if ((int) ($row['cid'] ?? 0) === -1) {
                    $counts['rowid_auxiliary']++;
                }
                continue;
            }
            if ($kind === 'foreign_key_check') {
                $counts['foreign_key']++;
                $table = (string) ($row['table'] ?? '');
                if ($table !== '' && !in_array($table, $counts['foreign_key_tables'], true)) {
                    $counts['foreign_key_tables'][] = $table;
                }
                continue;
            }
            $counts['integrity_root']++;
            if (($row['page_status'] ?? 'ok') !== 'ok') {
                $counts['rootpage_errors']++;
            }
        }

        return $counts;
    }

    /**
     * @param array{index_xinfo:int,foreign_key:int,rootpage_errors:int} $counts
     * @return list<string>
     */
    private static function blocking(array $counts): array
    {
        $blocking = [];
        if ($counts['index_xinfo'] === 0) {
            $blocking[] = 'index_xinfo';
        }
        if ($counts['rootpage_errors'] > 0) {
            $blocking[] = 'index_rootpage_integrity';
        }
        if ($counts['foreign_key'] > 0) {
            $blocking[] = 'foreign_key_check';
        }

        return $blocking;
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next157 cursor does not match the current/next source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next157 cursor offset does not match the requested page offset');
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
