<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaForeignKeyIntegrityRootpageCurrentSourceNextPlan
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $currentSchemas
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $nextSchemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array<string,mixed>,next_source:array<string,mixed>,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,mixed>,next_counts:array<string,mixed>,delta:array<string,mixed>,next_state:array{ready:bool,blocking:list<string>},next:array{source_id:string,offset:int}|null,rows:list<array<string,mixed>>}
     */
    public static function page(
        string|SQLiteDatabase $currentDatabase,
        array $currentSchemas,
        SQLiteAttachedSchemaCatalog $currentCatalog,
        string|SQLiteDatabase $nextDatabase,
        array $nextSchemas,
        SQLiteAttachedSchemaCatalog $nextCatalog,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        int $offset = 0,
        int $limit = 140,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key integrity rootpage current-source next140 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key integrity rootpage current-source next140 limit must be positive');
        }

        $currentPage = SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($currentDatabase, $currentSchemas, $currentCatalog, $foreignKeySql, 0, PHP_INT_MAX);
        $nextPage = SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($nextDatabase, $nextSchemas, $nextCatalog, $foreignKeySql, 0, PHP_INT_MAX);
        $source = [
            'current' => $currentPage['source_id'],
            'next' => $nextPage['source_id'],
            'foreign_key_sql' => $currentPage['current_source']['foreign_key_sql'],
        ];
        $sourceId = self::stableHash($source);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentRows = array_map(static fn (array $row): array => ['side' => 'current', ...$row], $currentPage['rows']);
        $nextRows = array_map(static fn (array $row): array => ['side' => 'next', ...$row], $nextPage['rows']);
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
     * @param list<array<string,mixed>> $rows
     * @return array{foreign_key_violations:int,child_rootpage_errors:int,parent_rootpage_errors:int,missing_catalog_rootpages:int,pointer_map_conflicts:int,schemas:list<string>}
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'foreign_key_violations' => 0,
            'child_rootpage_errors' => 0,
            'parent_rootpage_errors' => 0,
            'missing_catalog_rootpages' => 0,
            'pointer_map_conflicts' => 0,
            'schemas' => [],
        ];
        foreach ($rows as $row) {
            $counts['foreign_key_violations']++;
            if (is_string($row['schema'] ?? null)) {
                $counts['schemas'][] = $row['schema'];
            }
            foreach (['child', 'parent'] as $side) {
                $status = $row[$side . '_rootpage_status'] ?? null;
                if ($status === 'missing_catalog_rootpage' || $status === 'missing_schema_rootpage') {
                    $counts['missing_catalog_rootpages']++;
                } elseif ($status !== 'ok') {
                    $counts[$side . '_rootpage_errors']++;
                }
                if ($status === 'pointer_map') {
                    $counts['pointer_map_conflicts']++;
                }
            }
        }
        $counts['schemas'] = array_values(array_unique($counts['schemas']));

        return $counts;
    }

    /**
     * @param array<string,mixed> $counts
     * @return list<string>
     */
    private static function blocking(array $counts): array
    {
        $blocking = [];
        if (($counts['foreign_key_violations'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_check';
        }
        if (($counts['missing_catalog_rootpages'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_rootpage_catalog';
        }
        if (($counts['pointer_map_conflicts'] ?? 0) > 0) {
            $blocking[] = 'rootpage_pointer_map';
        }
        if (($counts['child_rootpage_errors'] ?? 0) > 0 || ($counts['parent_rootpage_errors'] ?? 0) > 0) {
            $blocking[] = 'rootpage_integrity';
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
        $keys = ['foreign_key_violations', 'child_rootpage_errors', 'parent_rootpage_errors', 'missing_catalog_rootpages', 'pointer_map_conflicts'];
        $delta = [];
        foreach ($keys as $key) {
            $delta[$key] = (int) $next[$key] - (int) $current[$key];
        }
        $delta['total'] = (int) $next['foreign_key_violations'] - (int) $current['foreign_key_violations'];
        $delta['cleared'] = (int) $current['foreign_key_violations'] > 0 && (int) $next['foreign_key_violations'] === 0;

        return $delta;
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key integrity rootpage current-source next140 cursor does not match the current/next source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key integrity rootpage current-source next140 cursor offset does not match the requested page offset');
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
