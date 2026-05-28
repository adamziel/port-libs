<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext158
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
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        int $offset = 0,
        int $limit = 158,
        string $integritySql = 'PRAGMA integrity_check',
        bool $indexTableValued = false,
        ?array $cursor = null,
        ?SQLiteAttachedSchemaCatalog $currentForeignKeyCatalog = null,
        ?SQLiteAttachedSchemaCatalog $nextForeignKeyCatalog = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next158 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next158 limit must be positive');
        }

        $currentPage = SQLitePragmaIndexIntegrityForeignKeyCurrentSourceYield::page(
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
        $nextPage = SQLitePragmaIndexIntegrityForeignKeyCurrentSourceYield::page(
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
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next158',
            'current' => $currentPage['source_id'],
            'next' => $nextPage['source_id'],
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentRows = self::sideRows('current', $currentPage['rows']);
        $nextRows = self::sideRows('next', $nextPage['rows']);
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
            'current_source' => $currentPage['current_source'],
            'next_source' => $nextPage['current_source'],
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
                'blockers' => $nextCounts['blockers'] - $currentCounts['blockers'],
                'cleared' => $currentCounts['blockers'] > 0 && $nextCounts['blockers'] === 0,
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
     * @return array{index_xinfo:int,integrity_root:int,foreign_key:int,blockers:int,target_schema:string,target_index:string|null,target_table:string|null,foreign_key_tables:list<string>}
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'index_xinfo' => 0,
            'integrity_root' => 0,
            'foreign_key' => 0,
            'blockers' => 0,
            'target_schema' => 'main',
            'target_index' => null,
            'target_table' => null,
            'foreign_key_tables' => [],
        ];

        foreach ($rows as $row) {
            $kind = $row['kind'] ?? null;
            if ($kind === 'index_xinfo') {
                $counts['index_xinfo']++;
                $counts['target_schema'] = (string) ($row['schema'] ?? $counts['target_schema']);
                $counts['target_index'] = (string) ($row['target'] ?? $counts['target_index']);
                continue;
            }
            if ($kind === 'foreign_key_check') {
                $counts['foreign_key']++;
                $counts['blockers']++;
                $counts['target_table'] = (string) ($row['table'] ?? $counts['target_table']);
                $table = (string) ($row['table'] ?? '');
                if ($table !== '' && !in_array($table, $counts['foreign_key_tables'], true)) {
                    $counts['foreign_key_tables'][] = $table;
                }
                continue;
            }
            $counts['integrity_root']++;
            $counts['blockers']++;
        }

        return $counts;
    }

    /**
     * @param array<string,mixed> $counts
     * @return list<string>
     */
    private static function blocking(array $counts): array
    {
        $blocking = [];
        if (($counts['index_xinfo'] ?? 0) === 0) {
            $blocking[] = 'index_xinfo';
        }
        if (($counts['integrity_root'] ?? 0) > 0) {
            $blocking[] = 'index_root_integrity';
        }
        if (($counts['foreign_key'] ?? 0) > 0) {
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
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next158 cursor does not match the current/next source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next158 cursor offset does not match the requested page offset');
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
