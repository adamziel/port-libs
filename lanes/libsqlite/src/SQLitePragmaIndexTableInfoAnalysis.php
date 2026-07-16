<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexTableInfoAnalysis
{
    /**
     * @param list<string> $sqlStatements
     * @param array{source_id?: string, next_offset?: int}|null $resume
     * @return array{status: string, source_id: string, next_offset: int, row_count: int, analyses: list<array<string, mixed>>}
     */
    public static function currentSourcePage(SQLiteAttachedSchemaCatalog $catalog, array $sqlStatements, int $offset, int $limit, ?array $resume = null): array
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA analysis offset must be non-negative');
        }
        if ($limit <= 0) {
            throw new InvalidArgumentException('SQLite PRAGMA analysis limit must be positive');
        }

        $analyses = [];
        foreach ($sqlStatements as $sql) {
            if (!is_string($sql) || trim($sql) === '') {
                throw new InvalidArgumentException('SQLite PRAGMA analysis requires non-empty SQL statements');
            }
            $result = str_starts_with(strtolower(ltrim($sql)), 'pragma_')
                ? $catalog->executeTableValuedPragma($sql)
                : $catalog->executeSchemaPragma($sql);
            if (!in_array($result['pragma'], ['table_info', 'table_xinfo', 'index_info', 'index_xinfo'], true)) {
                throw new InvalidArgumentException('SQLite PRAGMA analysis only supports table_info, table_xinfo, index_info, and index_xinfo');
            }
            $analyses[] = self::analyzeResult($sql, $result);
        }

        $sourceId = self::sourceId($analyses);
        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA analysis current-source cursor does not match the current source');
            }
            if (array_key_exists('next_offset', $resume) && $resume['next_offset'] !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA analysis current-source cursor offset is stale');
            }
        }

        return [
            'status' => 'ok',
            'source_id' => $sourceId,
            'next_offset' => min(count($analyses), $offset + $limit),
            'row_count' => count($analyses),
            'analyses' => array_slice($analyses, $offset, $limit),
        ];
    }

    /**
     * @param array{status: string, pragma: string, schema: string, target: string, rows: list<array<string, int|string|null>>} $result
     * @return array<string, mixed>
     */
    private static function analyzeResult(string $sql, array $result): array
    {
        $rows = $result['rows'];
        $analysis = [
            'sql' => self::normalizeSql($sql),
            'pragma' => $result['pragma'],
            'schema' => $result['schema'],
            'target' => $result['target'],
            'row_count' => count($rows),
            'row_names' => array_values(array_map(static fn (array $row): int|string|null => $row['name'] ?? null, $rows)),
            'row_signature' => substr(hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)), 0, 16),
        ];

        if ($result['pragma'] === 'table_info' || $result['pragma'] === 'table_xinfo') {
            $hiddenCodes = array_map(static fn (array $row): int => (int) ($row['hidden'] ?? 0), $rows);
            $analysis += [
                'visible_columns' => count(array_filter($hiddenCodes, static fn (int $hidden): bool => $hidden === 0)),
                'generated_columns' => count(array_filter($hiddenCodes, static fn (int $hidden): bool => $hidden === 2 || $hidden === 3)),
                'primary_key_columns' => count(array_filter($rows, static fn (array $row): bool => (int) ($row['pk'] ?? 0) > 0)),
                'notnull_columns' => count(array_filter($rows, static fn (array $row): bool => (int) ($row['notnull'] ?? 0) === 1)),
                'default_columns' => count(array_filter($rows, static fn (array $row): bool => array_key_exists('dflt_value', $row) && $row['dflt_value'] !== null)),
                'hidden_codes' => $hiddenCodes,
            ];
        } else {
            $analysis += [
                'key_columns' => count(array_filter($rows, static fn (array $row): bool => (int) ($row['key'] ?? 1) === 1)),
                'auxiliary_columns' => count(array_filter($rows, static fn (array $row): bool => (int) ($row['key'] ?? 1) === 0)),
                'expression_columns' => count(array_filter($rows, static fn (array $row): bool => (int) ($row['cid'] ?? 0) === -2)),
                'rowid_auxiliary' => count(array_filter($rows, static fn (array $row): bool => (int) ($row['cid'] ?? 0) === -1)),
                'collations' => array_values(array_unique(array_map(static fn (array $row): string => (string) ($row['coll'] ?? 'BINARY'), $rows))),
                'descending_columns' => count(array_filter($rows, static fn (array $row): bool => (int) ($row['desc'] ?? 0) === 1)),
            ];
        }

        return $analysis;
    }

    /**
     * @param list<array<string, mixed>> $analyses
     */
    private static function sourceId(array $analyses): string
    {
        return substr(hash('sha256', json_encode($analyses, JSON_THROW_ON_ERROR)), 0, 24);
    }

    private static function normalizeSql(string $sql): string
    {
        return strtolower(preg_replace('/\s+/', ' ', rtrim(trim($sql), ';')) ?? trim($sql));
    }
}
