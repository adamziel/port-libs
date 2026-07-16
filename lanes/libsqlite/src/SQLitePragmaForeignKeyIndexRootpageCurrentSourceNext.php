<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaForeignKeyIndexRootpageCurrentSourceNext
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
        string|SQLiteDatabase $currentDatabase,
        array $currentSchemas,
        string|SQLiteDatabase $nextDatabase,
        array $nextSchemas,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        int $offset = 0,
        int $limit = 144,
        string $integritySql = 'PRAGMA integrity_check',
        bool $indexTableValued = false,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key index rootpage current-source next144 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key index rootpage current-source next144 limit must be positive');
        }

        $currentPage = SQLitePragmaForeignKeyIndexRootCurrentSourceNext::page(
            $currentCatalog,
            $indexXinfoSql,
            $currentDatabase,
            $currentSchemas,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
            $integritySql,
            $indexTableValued,
        );
        $nextPage = SQLitePragmaForeignKeyIndexRootCurrentSourceNext::page(
            $nextCatalog,
            $indexXinfoSql,
            $nextDatabase,
            $nextSchemas,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
            $integritySql,
            $indexTableValued,
        );
        $sourceId = self::stableHash([
            'current' => $currentPage['source_id'],
            'next' => $nextPage['source_id'],
            'index_xinfo_sql' => $currentPage['current_source']['index_xinfo_sql'],
            'foreign_key_sql' => $currentPage['current_source']['foreign_key_sql'],
            'integrity_sql' => $currentPage['current_source']['integrity_sql'],
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentRows = array_map(static fn (array $row): array => ['side' => 'current', ...$row], $currentPage['rows']);
        $nextRows = array_map(static fn (array $row): array => ['side' => 'next', ...$row], $nextPage['rows']);
        $rows = [...$currentRows, ...$nextRows];
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $currentCounts = $currentPage['current'];
        $nextCounts = $nextPage['current'];
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
            'delta' => self::delta($currentCounts, $nextCounts),
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
     * @param array<string,mixed> $counts
     * @return list<string>
     */
    private static function blocking(array $counts): array
    {
        $blocking = [];
        if (($counts['index_root_integrity'] ?? 0) > 0) {
            $blocking[] = 'index_root_integrity';
        }
        if (($counts['foreign_key_rootpage'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_check';
        }
        if (($counts['missing_catalog_rootpages'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_rootpage_catalog';
        }
        if (($counts['pointer_map_conflicts'] ?? 0) > 0) {
            $blocking[] = 'rootpage_pointer_map';
        }

        return $blocking;
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return array<string,mixed>
     */
    private static function delta(array $current, array $next): array
    {
        $keys = ['index_xinfo', 'index_root_integrity', 'foreign_key_rootpage', 'pointer_map_conflicts', 'missing_catalog_rootpages'];
        $delta = [];
        foreach ($keys as $key) {
            $delta[$key] = (int) ($next[$key] ?? 0) - (int) ($current[$key] ?? 0);
        }
        $delta['cleared'] = ((int) ($current['index_root_integrity'] ?? 0) + (int) ($current['foreign_key_rootpage'] ?? 0) + (int) ($current['pointer_map_conflicts'] ?? 0) + (int) ($current['missing_catalog_rootpages'] ?? 0)) > 0
            && ((int) ($next['index_root_integrity'] ?? 0) + (int) ($next['foreign_key_rootpage'] ?? 0) + (int) ($next['pointer_map_conflicts'] ?? 0) + (int) ($next['missing_catalog_rootpages'] ?? 0)) === 0;

        return $delta;
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key index rootpage current-source next144 cursor does not match the current/next source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key index rootpage current-source next144 cursor offset does not match the requested page offset');
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
