<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext180
{
    /**
     * @param list<SQLiteSchemaRecord> $currentRecords
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param list<SQLiteSchemaRecord> $nextRecords
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array<string,mixed>
     */
    public static function currentNextPageFromCatalog(
        array $currentRecords,
        array $currentTables,
        array $nextRecords,
        array $nextTables,
        string $indexXinfoSql,
        int $offset = 0,
        int $limit = 180,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext177::currentNextPageFromCatalog(
            $currentRecords,
            $currentTables,
            $nextRecords,
            $nextTables,
            $indexXinfoSql,
            $offset,
            $limit,
            null,
            $tableValuedIndexXinfo,
        );

        $currentDiagnostics = self::parentIndexDiagnostics($currentRecords);
        $nextDiagnostics = self::parentIndexDiagnostics($nextRecords);
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next180',
            'base' => $page['source_id'],
            'current_parent_index_diagnostics' => self::diagnosticSummary($currentDiagnostics),
            'next_parent_index_diagnostics' => self::diagnosticSummary($nextDiagnostics),
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentMap = self::diagnosticMap($currentDiagnostics);
        $nextMap = self::diagnosticMap($nextDiagnostics);

        return [
            ...$page,
            'source_id' => $sourceId,
            'current_source' => [
                ...$page['current_source'],
                'foreign_key_parent_index_source' => 'pragma_index_list_index_xinfo_candidate_diagnostics',
                'foreign_key_parent_indexes' => self::diagnosticSummary($currentDiagnostics),
            ],
            'next_source' => [
                ...$page['next_source'],
                'foreign_key_parent_index_source' => 'pragma_index_list_index_xinfo_candidate_diagnostics',
                'foreign_key_parent_indexes' => self::diagnosticSummary($nextDiagnostics),
            ],
            'current' => [
                ...$page['current'],
                'foreign_key_parent_indexes' => self::diagnosticCounts($currentDiagnostics),
            ],
            'next_counts' => [
                ...$page['next_counts'],
                'foreign_key_parent_indexes' => self::diagnosticCounts($nextDiagnostics),
            ],
            'delta' => [
                ...$page['delta'],
                'foreign_key_parent_index_changes' => self::diagnosticChangeCount($currentDiagnostics, $nextDiagnostics),
                'foreign_key_parent_index_changed' => self::diagnosticSummary($currentDiagnostics) !== self::diagnosticSummary($nextDiagnostics),
            ],
            'next' => $page['next'] === null ? null : [
                'source_id' => $sourceId,
                'offset' => $page['next']['offset'],
            ],
            'rows' => array_map(
                static fn (array $row): array => self::decorateRow($row, ($row['side'] ?? 'current') === 'next' ? $nextMap : $currentMap),
                $page['rows'],
            ),
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function parentIndexDiagnostics(array $records): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $tableRecords = self::tableRecords($records);
        $foreignKeys = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext166::foreignKeysFromCatalog($records);
        $diagnostics = [];

        foreach ($foreignKeys as $foreignKey) {
            $parent = (string) ($foreignKey['parent'] ?? '');
            $parentColumns = array_map(static fn (array $column): string => (string) $column['parent'], $foreignKey['columns']);
            $parentCollations = array_map(static fn (array $column): string => strtoupper((string) $column['collation']), $foreignKey['columns']);
            $candidates = self::candidateRows($catalog, $parent, $parentColumns, $parentCollations);
            $rowidMatch = self::rowidPrimaryKey($tableRecords[strtolower($parent)] ?? null, $parentColumns);
            $accepted = array_values(array_filter($candidates, static fn (array $row): bool => $row['reason'] === 'accepted_unique_index'));
            if ($rowidMatch) {
                array_unshift($accepted, [
                    'index' => 'rowid-primary-key',
                    'reason' => 'accepted_rowid_primary_key',
                    'columns' => $parentColumns,
                    'collations' => ['BINARY'],
                ]);
            }

            $diagnostics[] = [
                'table' => (string) ($foreignKey['table'] ?? ''),
                'fkid' => (int) ($foreignKey['id'] ?? -1),
                'parent' => $parent,
                'parent_columns' => $parentColumns,
                'parent_collations' => $parentCollations,
                'accepted' => $accepted !== [],
                'accepted_index' => $accepted[0]['index'] ?? null,
                'accepted_reason' => $accepted[0]['reason'] ?? 'missing_matching_unique_index',
                'candidate_count' => count($candidates) + ($rowidMatch ? 1 : 0),
                'rejected' => self::rejectionCounts($candidates),
                'candidate_summary' => self::candidateSummary($candidates, $rowidMatch, $parentColumns),
            ];
        }

        return $diagnostics;
    }

    /**
     * @param list<array<string,mixed>> $diagnostics
     * @return list<string>
     */
    private static function diagnosticSummary(array $diagnostics): array
    {
        $summary = array_map(
            static fn (array $row): string => $row['table'] . '#' . $row['fkid']
                . ':parent=' . $row['parent']
                . ',columns=' . implode('|', $row['parent_columns'])
                . ',accepted=' . ($row['accepted_index'] ?? '<none>')
                . ',reason=' . $row['accepted_reason']
                . ',candidates=' . implode('|', $row['candidate_summary']),
            $diagnostics,
        );
        sort($summary);

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $diagnostics
     * @return array<string,int>
     */
    private static function diagnosticCounts(array $diagnostics): array
    {
        $counts = [
            'accepted' => 0,
            'blocked' => 0,
            'rowid_primary_key' => 0,
            'unique_index' => 0,
            'partial_unique_rejected' => 0,
            'non_unique_rejected' => 0,
            'column_order_rejected' => 0,
            'collation_rejected' => 0,
        ];

        foreach ($diagnostics as $row) {
            $counts[$row['accepted'] ? 'accepted' : 'blocked']++;
            if ($row['accepted_reason'] === 'accepted_rowid_primary_key') {
                $counts['rowid_primary_key']++;
            }
            if ($row['accepted_reason'] === 'accepted_unique_index') {
                $counts['unique_index']++;
            }
            foreach ($row['rejected'] as $reason => $count) {
                $counts[$reason . '_rejected'] = ($counts[$reason . '_rejected'] ?? 0) + $count;
            }
        }

        return $counts;
    }

    /**
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $next
     */
    private static function diagnosticChangeCount(array $current, array $next): int
    {
        return count(array_diff(self::diagnosticSummary($next), self::diagnosticSummary($current)))
            + count(array_diff(self::diagnosticSummary($current), self::diagnosticSummary($next)));
    }

    /**
     * @param list<array<string,mixed>> $diagnostics
     * @return array<string,array<string,mixed>>
     */
    private static function diagnosticMap(array $diagnostics): array
    {
        $map = [];
        foreach ($diagnostics as $row) {
            $map[self::diagnosticKey((string) $row['table'], (int) $row['fkid'])] = $row;
        }

        return $map;
    }

    /**
     * @param array<string,array<string,mixed>> $diagnostics
     * @return array<string,mixed>
     */
    private static function decorateRow(array $row, array $diagnostics): array
    {
        if (($row['kind'] ?? null) !== 'index_admission' && ($row['kind'] ?? null) !== 'foreign_key_check') {
            return $row;
        }

        $diagnostic = $diagnostics[self::diagnosticKey((string) ($row['table'] ?? ''), (int) ($row['fkid'] ?? -1))] ?? null;
        if ($diagnostic === null) {
            return $row;
        }

        return [
            ...$row,
            'parent_index_accepted' => $diagnostic['accepted'],
            'parent_index' => $diagnostic['accepted_index'],
            'parent_index_reason' => $diagnostic['accepted_reason'],
            'parent_index_candidates' => $diagnostic['candidate_count'],
            'parent_index_rejections' => $diagnostic['rejected'],
        ];
    }

    /**
     * @param list<string> $parentColumns
     * @param list<string> $parentCollations
     * @return list<array{index:string,reason:string,columns:list<string>,collations:list<string>}>
     */
    private static function candidateRows(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns, array $parentCollations): array
    {
        $rows = [];
        foreach ($catalog->execute('PRAGMA index_list(' . self::pragmaArgumentLiteral($parent) . ')')['rows'] as $index) {
            $indexName = (string) $index['name'];
            $xinfo = array_values(array_filter(
                $catalog->execute('PRAGMA index_xinfo(' . self::pragmaArgumentLiteral($indexName) . ')')['rows'],
                static fn (array $row): bool => (int) $row['key'] === 1
            ));
            $columns = array_map(static fn (array $row): string => (string) $row['name'], $xinfo);
            $collations = array_map(static fn (array $row): string => strtoupper((string) $row['coll']), $xinfo);
            $reason = self::candidateReason((int) $index['unique'], (int) $index['partial'], $columns, $collations, $parentColumns, $parentCollations);
            $rows[] = [
                'index' => $indexName,
                'reason' => $reason,
                'columns' => $columns,
                'collations' => $collations,
            ];
        }

        return $rows;
    }

    /**
     * @param list<string> $columns
     * @param list<string> $collations
     * @param list<string> $parentColumns
     * @param list<string> $parentCollations
     */
    private static function candidateReason(int $unique, int $partial, array $columns, array $collations, array $parentColumns, array $parentCollations): string
    {
        if ($unique !== 1) {
            return 'non_unique';
        }
        if ($partial !== 0) {
            return 'partial_unique';
        }
        if (array_map('strtolower', $columns) !== array_map('strtolower', $parentColumns)) {
            return 'column_order';
        }
        if ($collations !== array_map('strtoupper', $parentCollations)) {
            return 'collation';
        }

        return 'accepted_unique_index';
    }

    /**
     * @param list<array{index:string,reason:string,columns:list<string>,collations:list<string>}> $candidates
     * @return array<string,int>
     */
    private static function rejectionCounts(array $candidates): array
    {
        $counts = [
            'partial_unique' => 0,
            'non_unique' => 0,
            'column_order' => 0,
            'collation' => 0,
        ];
        foreach ($candidates as $candidate) {
            if ($candidate['reason'] !== 'accepted_unique_index') {
                $counts[$candidate['reason']]++;
            }
        }

        return $counts;
    }

    /**
     * @param list<array{index:string,reason:string,columns:list<string>,collations:list<string>}> $candidates
     * @param list<string> $parentColumns
     * @return list<string>
     */
    private static function candidateSummary(array $candidates, bool $rowidMatch, array $parentColumns): array
    {
        $summary = [];
        if ($rowidMatch) {
            $summary[] = 'rowid-primary-key:accepted_rowid_primary_key:' . implode('|', $parentColumns) . ':BINARY';
        }
        foreach ($candidates as $candidate) {
            $summary[] = $candidate['index'] . ':' . $candidate['reason'] . ':' . implode('|', $candidate['columns']) . ':' . implode('|', $candidate['collations']);
        }
        sort($summary);

        return $summary;
    }

    /**
     * @param list<string> $parentColumns
     */
    private static function rowidPrimaryKey(?SQLiteSchemaRecord $record, array $parentColumns): bool
    {
        if ($record === null || $record->sql === null || count($parentColumns) !== 1) {
            return false;
        }

        $body = self::parenthesizedBody($record->sql);
        if ($body === null) {
            return false;
        }
        foreach (self::splitTopLevel($body, ',') as $definition) {
            $definition = trim($definition);
            if (preg_match('/^(?:"(?<dq>(?:""|[^"])*)"|`(?<bt>[^`]*)`|\[(?<br>[^\]]*)\]|(?<bare>[A-Za-z_][A-Za-z0-9_]*))\b(?<tail>.*)$/is', $definition, $matches) !== 1) {
                continue;
            }
            $name = str_replace('""', '"', $matches['dq'] ?: ($matches['bt'] ?: ($matches['br'] ?: $matches['bare'])));
            if (strcasecmp($name, $parentColumns[0]) === 0 && preg_match('/\bINTEGER\s+PRIMARY\s+KEY\b/i', $matches['tail']) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,SQLiteSchemaRecord>
     */
    private static function tableRecords(array $records): array
    {
        $tables = [];
        foreach ($records as $record) {
            if ($record->type === 'table') {
                $tables[strtolower($record->name)] = $record;
            }
        }

        return $tables;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next180 records must be SQLiteSchemaRecord instances');
            }
        }
    }

    private static function diagnosticKey(string $table, int $id): string
    {
        return strtolower($table) . '#' . $id;
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next180 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next180 cursor offset does not match the requested page offset');
        }
    }

    private static function parenthesizedBody(string $sql): ?string
    {
        $open = strpos($sql, '(');
        if ($open === false) {
            return null;
        }
        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        for ($i = $open; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if (($quote === "'" || $quote === '"') && ($sql[$i + 1] ?? '') === $quote) {
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                continue;
            }
            if ($char === '[') {
                $quote = ']';
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return substr($sql, $open + 1, $i - $open - 1);
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $value, string $delimiter): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if (($quote === "'" || $quote === '"') && ($value[$i + 1] ?? '') === $quote) {
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                continue;
            }
            if ($char === '[') {
                $quote = ']';
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                continue;
            }
            if ($char === $delimiter && $depth === 0) {
                $parts[] = substr($value, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($value, $start);

        return $parts;
    }

    private static function pragmaArgumentLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
