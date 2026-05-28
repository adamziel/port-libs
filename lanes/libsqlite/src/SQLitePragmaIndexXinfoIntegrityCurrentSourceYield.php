<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoIntegrityCurrentSourceYield
{
    /**
     * @return list<array<string, int|string|null>>
     */
    public static function collect(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        string $currentSource,
        string $nextSource,
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValued = false,
    ): array {
        self::assertSourcePair($currentSource, $nextSource);

        $rows = SQLitePragmaIndexXinfoIntegrityRootYield::collect($catalog, $indexXinfoSql, $database, $integritySql, $tableValued);
        $indexTarget = self::indexTarget($rows);

        return array_map(
            static function (array $row) use ($currentSource, $nextSource, $indexTarget): array {
                $row['current_source'] = $currentSource;
                $row['next_source'] = $nextSource;
                $row['status'] = $row['kind'] === 'index_xinfo' ? 'metadata' : 'integrity_error';
                $row['index'] = $row['kind'] === 'index_xinfo' ? $row['target'] : $indexTarget;

                return $row;
            },
            $rows,
        );
    }

    /**
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string, int|string|null>,next:array<string, bool|int|string|list<string>>,rows:list<array<string, int|string|null>>}
     */
    public static function page(
        SQLiteAttachedSchemaCatalog|SQLitePragmaSchemaCatalog $catalog,
        string $indexXinfoSql,
        string|SQLiteDatabase $database,
        string $currentSource,
        string $nextSource,
        int $offset = 0,
        int $limit = 100,
        string $integritySql = 'PRAGMA integrity_check',
        bool $tableValued = false,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo integrity current-source next100 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo integrity current-source next100 limit must be positive');
        }

        $rows = self::collect($catalog, $indexXinfoSql, $database, $currentSource, $nextSource, $integritySql, $tableValued);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $metadataRows = count(array_filter($rows, static fn (array $row): bool => $row['kind'] === 'index_xinfo'));
        $integrityRows = count($rows) - $metadataRows;
        $blocking = $integrityRows > 0 ? ['index_root_integrity'] : [];

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => [
                'source' => $currentSource,
                'metadata_rows' => $metadataRows,
                'integrity_errors' => $integrityRows,
                'index' => self::indexTarget($rows),
            ],
            'next' => [
                'source' => $nextSource,
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
            'rows' => $pageRows,
        ];
    }

    private static function assertSourcePair(string $currentSource, string $nextSource): void
    {
        if (trim($currentSource) === '' || trim($nextSource) === '') {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo integrity current-source next100 requires current and next source identifiers');
        }
    }

    /**
     * @param list<array<string, int|string|null>> $rows
     */
    private static function indexTarget(array $rows): ?string
    {
        foreach ($rows as $row) {
            if (($row['kind'] ?? null) === 'index_xinfo' && isset($row['target']) && is_string($row['target']) && $row['target'] !== '') {
                return $row['target'];
            }
        }

        return null;
    }
}
