<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext233
{
    /**
     * @param list<SQLiteSchemaRecord> $currentRecords
     * @param list<SQLiteSchemaRecord> $nextRecords
     * @param array{source_id:string,offset:int}|null $resume
     * @return array<string,mixed>
     */
    public static function page(
        array $currentRecords,
        array $nextRecords,
        string $indexXinfoSql,
        string $foreignKeySql,
        int $offset = 0,
        int $limit = 50,
        ?array $resume = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next233 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next233 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext230::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::childExpressionPrefixRows($currentRecords, 'current');
        $nextRows = self::childExpressionPrefixRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next233',
            'base' => $base['source_id'],
            'current_child_expression_prefix_indexes' => self::rowSummary($currentRows),
            'next_child_expression_prefix_indexes' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next233 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next233 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::expressionPrefixCounts($currentRows);
        $nextCounts = self::expressionPrefixCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next233',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_child_expression_prefix_source' => 'pragma_foreign_key_list_child_columns_plus_pragma_index_xinfo_expression_prefix_terms',
                'foreign_key_child_expression_prefix' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_child_expression_prefix_source' => 'pragma_foreign_key_list_child_columns_plus_pragma_index_xinfo_expression_prefix_terms',
                'foreign_key_child_expression_prefix' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_child_expression_prefix' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_child_expression_prefix' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_child_expression_prefix_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_child_expression_prefix_blockers' => $nextCounts['expression_prefix_child_index'] - $currentCounts['expression_prefix_child_index'],
                'foreign_key_child_expression_prefix_repaired' => $currentCounts['expression_prefix_child_index'] > 0 && $nextCounts['expression_prefix_child_index'] === 0,
                'foreign_key_child_expression_prefix_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-child-index-expression-prefix',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function childExpressionPrefixRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $indexSql = self::indexSqlByName($records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase)) as $group) {
            $table = (string) $group[0]['table'];
            $childColumns = array_map(static fn (array $row): string => (string) $row['from'], $group);
            if (in_array('', $childColumns, true)) {
                continue;
            }

            $candidate = self::expressionPrefixChildIndex($catalog, $indexSql, $table, $childColumns);
            if ($candidate === null) {
                continue;
            }

            foreach ($group as $row) {
                $seq = (int) $row['seq'];
                $actualPosition = $candidate['offset'] + $seq;
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_child_expression_prefix',
                    'table' => $table,
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => (string) $row['parent'],
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'child_columns' => $childColumns,
                    'expression_prefix_index' => $candidate['index'],
                    'index_key_terms' => $candidate['key_terms'],
                    'index_key_collations' => $candidate['collations'],
                    'expression_terms' => $candidate['expression_terms'],
                    'expression_term_count' => $candidate['offset'],
                    'expected_position' => $seq,
                    'actual_position' => $actualPosition,
                    'status' => 'expression_prefix_child_index',
                    'message' => "foreign key {$table} child columns are present in index {$candidate['index']} only after expression key terms",
                ];
            }
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['phase'], $left['table'], $left['foreign_key_id'], $left['seq']]
                <=> [$right['phase'], $right['table'], $right['foreign_key_id'], $right['seq']],
        );

        return $rows;
    }

    /**
     * @param array<string,string> $indexSql
     * @param list<string> $childColumns
     * @return array{index:string,offset:int,key_terms:list<string>,collations:list<string>,expression_terms:list<string>}|null
     */
    private static function expressionPrefixChildIndex(SQLitePragmaSchemaCatalog $catalog, array $indexSql, string $table, array $childColumns): ?array
    {
        $wanted = array_map('strtolower', $childColumns);

        foreach ($catalog->indexList($table) as $index) {
            if ((int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $indexName = (string) $index['name'];
            $keyRows = array_values(array_filter(
                $catalog->indexXInfo($indexName),
                static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1,
            ));
            $columns = array_map(static fn (array $row): string => strtolower((string) ($row['name'] ?? '')), $keyRows);
            if (array_slice($columns, 0, count($wanted)) === $wanted) {
                continue;
            }

            for ($offset = 1; $offset <= count($columns) - count($wanted); $offset++) {
                if (array_slice($columns, $offset, count($wanted)) !== $wanted) {
                    continue;
                }

                $leadingRows = array_slice($keyRows, 0, $offset);
                $allLeadingAreExpressions = $leadingRows !== [] && array_reduce(
                    $leadingRows,
                    static fn (bool $carry, array $row): bool => $carry && (int) ($row['cid'] ?? 0) === -2 && ($row['name'] ?? null) === null,
                    true,
                );
                if (!$allLeadingAreExpressions) {
                    continue;
                }

                $terms = self::indexTerms((string) ($indexSql[strtolower($indexName)] ?? ''));
                $expressionTerms = array_slice($terms, 0, $offset);

                return [
                    'index' => $indexName,
                    'offset' => $offset,
                    'key_terms' => array_map(
                        static fn (array $row, int $position): string => (string) ($row['name'] ?? $terms[$position] ?? ''),
                        $keyRows,
                        array_keys($keyRows),
                    ),
                    'collations' => array_map(static fn (array $row): string => strtoupper((string) ($row['coll'] ?? 'BINARY')), $keyRows),
                    'expression_terms' => $expressionTerms,
                ];
            }
        }

        return null;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,string>
     */
    private static function indexSqlByName(array $records): array
    {
        $sql = [];
        foreach ($records as $record) {
            if ($record->type === 'index' && $record->sql !== null) {
                $sql[strtolower($record->name)] = $record->sql;
            }
        }

        return $sql;
    }

    /**
     * @return list<string>
     */
    private static function indexTerms(string $sql): array
    {
        if (!preg_match('/\bon\s+(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s*\((.*)\)\s*(?:where\b.*)?$/is', trim($sql), $match)) {
            return [];
        }

        return array_map(
            static fn (string $term): string => self::normalizeTerm($term),
            self::splitTopLevel($match[1], ','),
        );
    }

    private static function normalizeTerm(string $term): string
    {
        $term = trim(preg_replace('/\s+/', ' ', $term) ?? $term);
        $term = preg_replace('/\s+collate\s+(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)/i', '', $term) ?? $term;
        $term = preg_replace('/\s+(?:asc|desc)\b/i', '', $term) ?? $term;

        return trim($term);
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $input, string $delimiter): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $length = strlen($input);
        for ($i = 0; $i < $length; $i++) {
            $ch = $input[$i];
            if ($quote !== null) {
                $buffer .= $ch;
                if ($ch === $quote) {
                    if ($i + 1 < $length && $input[$i + 1] === $quote) {
                        $buffer .= $input[++$i];
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buffer .= $ch;
                continue;
            }
            if ($ch === '[') {
                $quote = ']';
                $buffer .= $ch;
                continue;
            }
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth = max(0, $depth - 1);
            }
            if ($ch === $delimiter && $depth === 0) {
                $parts[] = trim($buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $ch;
        }
        if (trim($buffer) !== '') {
            $parts[] = trim($buffer);
        }

        return $parts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<list<array<string,mixed>>>
     */
    private static function groupForeignKeyRows(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[strtolower((string) $row['table']) . '#' . (int) $row['id']][] = $row;
        }
        foreach ($groups as &$group) {
            usort($group, static fn (array $left, array $right): int => (int) $left['seq'] <=> (int) $right['seq']);
        }

        return array_values($groups);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,expression_prefix_child_index:int,foreign_keys:int,expression_terms:int,max_expression_terms:int}
     */
    private static function expressionPrefixCounts(array $rows): array
    {
        $foreignKeys = [];
        $expressionTerms = 0;
        $maxExpressionTerms = 0;
        foreach ($rows as $row) {
            $foreignKeys[(string) $row['table'] . '#' . (int) $row['foreign_key_id']] = true;
            $expressionTerms += (int) ($row['expression_term_count'] ?? 0);
            $maxExpressionTerms = max($maxExpressionTerms, (int) ($row['expression_term_count'] ?? 0));
        }

        return [
            'rows' => count($rows),
            'expression_prefix_child_index' => count($rows),
            'foreign_keys' => count($foreignKeys),
            'expression_terms' => $expressionTerms,
            'max_expression_terms' => $maxExpressionTerms,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function rowSummary(array $rows, bool $includePhase = true): array
    {
        $summary = array_map(
            static fn (array $row): string => implode(':', array_filter([
                $includePhase ? (string) $row['phase'] : null,
                (string) $row['table'] . '#' . (int) $row['foreign_key_id'] . '.' . (int) $row['seq'],
                (string) $row['from'] . '->' . (string) $row['parent'] . '.' . (string) $row['to'],
                'child=' . implode(',', (array) $row['child_columns']),
                (string) $row['expression_prefix_index'],
                'terms=' . implode(',', (array) $row['index_key_terms']),
                'expr=' . implode(',', (array) $row['expression_terms']),
                'expected=' . (int) $row['expected_position'],
                'actual=' . (int) $row['actual_position'],
                (string) $row['status'],
            ], static fn (?string $part): bool => $part !== null)),
            $rows,
        );
        sort($summary);

        return $summary;
    }

    /**
     * @param list<mixed> $records
     */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next233 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
