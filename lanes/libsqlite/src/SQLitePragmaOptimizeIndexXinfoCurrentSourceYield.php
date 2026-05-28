<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaOptimizeIndexXinfoCurrentSourceYield
{
    /**
     * @param list<string> $indexXinfoSql
     * @param list<array<string,mixed>> $tables
     * @param array{source_id?: string, next_offset?: int}|null $resume
     * @return array{status:string,source_id:string,next_offset:int,row_count:int,optimize:array<string,mixed>,rows:list<array<string,mixed>>}
     */
    public static function page(
        SQLiteAttachedSchemaCatalog $catalog,
        array $indexXinfoSql,
        SQLitePragmaOptimizePlan $optimize,
        string $optimizeSql,
        array $tables,
        int $offset,
        int $limit,
        ?array $resume = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA optimize index_xinfo current-source offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA optimize index_xinfo current-source limit must be positive');
        }

        $optimizeResult = $optimize->execute($optimizeSql, $tables);
        if (($optimizeResult['pragma'] ?? null) !== 'optimize') {
            throw new InvalidArgumentException('SQLite PRAGMA optimize index_xinfo current-source requires PRAGMA optimize');
        }

        $rows = [];
        foreach ($indexXinfoSql as $sql) {
            if (!is_string($sql) || trim($sql) === '') {
                throw new InvalidArgumentException('SQLite PRAGMA optimize index_xinfo current-source requires non-empty index_xinfo SQL');
            }
            $result = str_starts_with(strtolower(ltrim($sql)), 'pragma_')
                ? $catalog->executeTableValuedPragma($sql)
                : $catalog->executeSchemaPragma($sql);
            if (($result['pragma'] ?? null) !== 'index_xinfo') {
                throw new InvalidArgumentException('SQLite PRAGMA optimize index_xinfo current-source only supports index_xinfo rowsets');
            }

            $owner = self::indexOwner($catalog, $result['schema'], $result['target']);
            $decision = self::optimizeDecision($optimizeResult, $owner['schema'], $owner['table']);
            $indexRows = $result['rows'];
            $keyRows = array_values(array_filter($indexRows, static fn (array $row): bool => (int) ($row['key'] ?? 1) === 1));

            $rows[] = [
                'kind' => 'optimize_index_xinfo',
                'sql' => self::normalizeSql($sql),
                'schema' => $result['schema'],
                'index' => $result['target'],
                'table' => $owner['table'],
                'rootpage' => $owner['rootpage'],
                'row_count' => count($indexRows),
                'key_columns' => count($keyRows),
                'auxiliary_columns' => count($indexRows) - count($keyRows),
                'expression_columns' => count(array_filter($indexRows, static fn (array $row): bool => (int) ($row['cid'] ?? 0) === -2)),
                'rowid_auxiliary' => count(array_filter($indexRows, static fn (array $row): bool => (int) ($row['cid'] ?? 0) === -1)),
                'descending_columns' => count(array_filter($indexRows, static fn (array $row): bool => (int) ($row['desc'] ?? 0) === 1)),
                'collations' => array_values(array_unique(array_map(static fn (array $row): string => (string) ($row['coll'] ?? 'BINARY'), $indexRows))),
                'key_names' => array_values(array_map(static fn (array $row): int|string|null => $row['name'] ?? null, $keyRows)),
                'row_signature' => substr(hash('sha256', json_encode($indexRows, JSON_THROW_ON_ERROR)), 0, 16),
                'optimize_action' => $decision['action'],
                'optimize_reason' => $decision['reason'],
                'optimize_sql' => $decision['sql'],
                'current_source' => $decision['current_source'],
            ];
        }

        $sourceId = self::sourceId($rows, $optimizeResult);
        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA optimize index_xinfo current-source cursor does not match the current source');
            }
            if (array_key_exists('next_offset', $resume) && $resume['next_offset'] !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA optimize index_xinfo current-source cursor offset is stale');
            }
        }

        return [
            'status' => 'ok',
            'source_id' => $sourceId,
            'next_offset' => min(count($rows), $offset + $limit),
            'row_count' => count($rows),
            'optimize' => [
                'schema' => $optimizeResult['schema'],
                'mask' => $optimizeResult['mask'],
                'stable' => $optimizeResult['currentSource']['stable'],
                'analyze_count' => count($optimizeResult['analyze']),
                'skipped_count' => count($optimizeResult['skipped']),
            ],
            'rows' => array_slice($rows, $offset, $limit),
        ];
    }

    /**
     * @return array{schema:string,table:string,rootpage:int|null}
     */
    private static function indexOwner(SQLiteAttachedSchemaCatalog $catalog, string $schema, string $index): array
    {
        foreach ($catalog->schemaRecords($schema) as $record) {
            if ($record->type === 'index' && strcasecmp($record->name, $index) === 0) {
                return [
                    'schema' => $schema,
                    'table' => $record->tableName,
                    'rootpage' => $record->rootPage,
                ];
            }
        }

        throw new InvalidArgumentException('SQLite PRAGMA optimize index_xinfo current-source could not resolve index owner');
    }

    /**
     * @param array<string,mixed> $optimize
     * @return array{action:string,reason:string|null,sql:string|null,current_source:string|null}
     */
    private static function optimizeDecision(array $optimize, string $schema, string $table): array
    {
        foreach ($optimize['analyze'] as $row) {
            if (($row['schema'] ?? null) === $schema && ($row['table'] ?? null) === $table) {
                return [
                    'action' => 'analyze',
                    'reason' => $row['reason'] ?? null,
                    'sql' => $row['sql'] ?? null,
                    'current_source' => $row['currentSource'] ?? null,
                ];
            }
        }

        foreach ($optimize['skipped'] as $row) {
            if (($row['table'] ?? null) === $table) {
                $source = $optimize['currentSource']['tables'][$table]['token'] ?? null;

                return [
                    'action' => 'skip',
                    'reason' => $row['reason'] ?? null,
                    'sql' => null,
                    'current_source' => is_string($source) ? $source : null,
                ];
            }
        }

        return [
            'action' => 'unseen',
            'reason' => null,
            'sql' => null,
            'current_source' => null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $optimize
     */
    private static function sourceId(array $rows, array $optimize): string
    {
        return substr(hash('sha256', json_encode([
            'rows' => $rows,
            'optimize' => [
                'schema' => $optimize['schema'],
                'mask' => $optimize['mask'],
                'currentSource' => $optimize['currentSource'],
                'analyze' => $optimize['analyze'],
                'skipped' => $optimize['skipped'],
            ],
        ], JSON_THROW_ON_ERROR)), 0, 24);
    }

    private static function normalizeSql(string $sql): string
    {
        return strtolower(preg_replace('/\s+/', ' ', rtrim(trim($sql), ';')) ?? trim($sql));
    }
}
