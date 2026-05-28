<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext220
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next220 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next220 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext217::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::parentCollationRows($currentRecords, 'current');
        $nextRows = self::parentCollationRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next220',
            'base' => $base['source_id'],
            'current_parent_collations' => self::rowSummary($currentRows),
            'next_parent_collations' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next220 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next220 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::collationCounts($currentRows);
        $nextCounts = self::collationCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next220',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_parent_collation_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_collation',
                'foreign_key_parent_collations' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_parent_collation_source' => 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_collation',
                'foreign_key_parent_collations' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_parent_collations' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_parent_collations' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_parent_collation_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_parent_collation_mismatches' => $nextCounts['mismatch'] - $currentCounts['mismatch'],
                'foreign_key_parent_collation_repaired' => $currentCounts['mismatch'] > 0 && $nextCounts['mismatch'] === 0 && $nextCounts['blocked'] === 0,
                'foreign_key_parent_collation_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-parent-index-collation',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function parentCollationRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $tableCollations = self::tableCollations($records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase)) as $group) {
            $parent = (string) $group[0]['parent'];
            $parentColumns = array_map(static fn (array $row): string => (string) $row['to'], $group);
            if (in_array('', $parentColumns, true)) {
                continue;
            }

            $candidate = self::matchingUniquePrefix($catalog, $parent, $parentColumns);
            foreach ($group as $row) {
                $seq = (int) $row['seq'];
                $indexRow = $candidate['rows'][$seq] ?? null;
                $declared = $tableCollations[strtolower($parent)][strtolower((string) $row['to'])] ?? 'BINARY';
                $actual = strtoupper((string) ($indexRow['coll'] ?? ($candidate['index'] === 'sqlite_primary_key' ? $declared : '')));
                $matches = $candidate['status'] === 'ok' && $actual === $declared;
                $status = $candidate['status'] !== 'ok' ? $candidate['status'] : ($matches ? 'ok' : 'parent_collation_mismatch');

                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_parent_collation',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => (string) $row['to'],
                    'parent_columns' => $parentColumns,
                    'parent_unique_index' => $candidate['index'],
                    'parent_index_seqno' => $indexRow['seqno'] ?? null,
                    'parent_index_collation' => $actual === '' ? null : $actual,
                    'parent_column_collation' => $declared,
                    'collation_matches' => $matches,
                    'status' => $status,
                    'message' => $status === 'parent_collation_mismatch'
                        ? "foreign key {$row['table']}->{$parent} parent column {$row['to']} uses {$actual} index collation but declares {$declared}"
                        : ($status === 'ok'
                            ? "foreign key {$row['table']}->{$parent} parent UNIQUE index collation matches declared parent column collation"
                            : "foreign key {$row['table']}->{$parent} has no full non-partial UNIQUE parent prefix to compare collations"),
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
     * @param list<string> $parentColumns
     * @return array{status:string,index:string|null,rows:list<array<string,mixed>>}
     */
    private static function matchingUniquePrefix(SQLitePragmaSchemaCatalog $catalog, string $parent, array $parentColumns): array
    {
        $wanted = array_map('strtolower', $parentColumns);
        $primaryKey = self::primaryKeyColumns($catalog, $parent);
        if (array_map('strtolower', $primaryKey) === $wanted) {
            return [
                'status' => 'ok',
                'index' => 'sqlite_primary_key',
                'rows' => array_map(
                    static fn (string $column, int $seqno): array => ['seqno' => $seqno, 'name' => $column, 'coll' => null],
                    $primaryKey,
                    array_keys($primaryKey),
                ),
            ];
        }

        foreach ($catalog->indexList($parent) as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1 || (int) ($index['partial'] ?? 0) !== 0) {
                continue;
            }

            $keyRows = array_values(array_filter(
                $catalog->indexXInfo((string) $index['name']),
                static fn (array $row): bool => (int) ($row['key'] ?? 0) === 1,
            ));
            $prefix = array_slice($keyRows, 0, count($wanted));
            $columns = array_map(static fn (array $row): string => strtolower((string) ($row['name'] ?? '')), $prefix);
            if ($columns === $wanted) {
                return [
                    'status' => 'ok',
                    'index' => (string) $index['name'],
                    'rows' => $prefix,
                ];
            }
        }

        return ['status' => 'missing_parent_unique_index', 'index' => null, 'rows' => []];
    }

    /**
     * @return list<string>
     */
    private static function primaryKeyColumns(SQLitePragmaSchemaCatalog $catalog, string $table): array
    {
        $columns = [];
        foreach ($catalog->tableInfo($table) as $row) {
            $pk = (int) ($row['pk'] ?? 0);
            if ($pk > 0) {
                $columns[$pk] = (string) $row['name'];
            }
        }
        ksort($columns);

        return array_values($columns);
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,array<string,string>>
     */
    private static function tableCollations(array $records): array
    {
        $collations = [];
        foreach ($records as $record) {
            if ($record->type !== 'table' || $record->sql === null) {
                continue;
            }
            $body = self::parenthesizedBody($record->sql);
            if ($body === null) {
                continue;
            }
            foreach (self::splitTopLevel($body) as $definition) {
                $definition = trim($definition);
                if ($definition === '' || preg_match('/^(CONSTRAINT|PRIMARY|UNIQUE|CHECK|FOREIGN)\b/i', $definition) === 1) {
                    continue;
                }
                if (preg_match('/^(?<name>"(?:""|[^"])*"|`[^`]*`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\b(?<rest>.*)$/s', $definition, $matches) !== 1) {
                    continue;
                }
                $collations[strtolower($record->name)][strtolower(self::normalizeIdentifier($matches['name']))] = self::collationFromColumnDefinition($matches['rest']);
            }
        }

        return $collations;
    }

    private static function collationFromColumnDefinition(string $definition): string
    {
        if (preg_match('/\bCOLLATE\s+(?<coll>"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)/i', $definition, $matches) !== 1) {
            return 'BINARY';
        }

        return strtoupper(self::normalizeIdentifier($matches['coll']));
    }

    private static function parenthesizedBody(string $sql): ?string
    {
        $start = strpos($sql, '(');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        for ($i = $start; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if ($quote === "'" && ($sql[$i + 1] ?? '') === "'") {
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
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return substr($sql, $start + 1, $i - $start - 1);
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $sql): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if ($quote === "'" && ($sql[$i + 1] ?? '') === "'") {
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
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                continue;
            }
            if ($char === ',' && $depth === 0) {
                $parts[] = substr($sql, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($sql, $start);

        return $parts;
    }

    private static function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return '';
        }
        $first = $identifier[0];
        $last = $identifier[strlen($identifier) - 1];
        if (($first === '"' && $last === '"') || ($first === '`' && $last === '`') || ($first === '[' && $last === ']')) {
            return str_replace('""', '"', substr($identifier, 1, -1));
        }

        return $identifier;
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
     * @return array{rows:int,ok:int,mismatch:int,blocked:int,binary:int,nocase:int,rtrim:int,missing_parent_unique_index:int}
     */
    private static function collationCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'ok' => 0,
            'mismatch' => 0,
            'blocked' => 0,
            'binary' => 0,
            'nocase' => 0,
            'rtrim' => 0,
            'missing_parent_unique_index' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'ok') {
                $counts['ok']++;
            } elseif ($status === 'parent_collation_mismatch') {
                $counts['mismatch']++;
            } else {
                $counts['blocked']++;
                if (isset($counts[$status])) {
                    $counts[$status]++;
                }
            }

            $collation = strtoupper((string) ($row['parent_column_collation'] ?? 'BINARY'));
            if ($collation === 'BINARY') {
                $counts['binary']++;
            } elseif ($collation === 'NOCASE') {
                $counts['nocase']++;
            } elseif ($collation === 'RTRIM') {
                $counts['rtrim']++;
            }
        }

        return $counts;
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
                'parent=' . implode(',', (array) $row['parent_columns']),
                (string) ($row['parent_unique_index'] ?? 'missing-parent-index'),
                'column=' . (string) ($row['parent_column_collation'] ?? ''),
                'index=' . (string) ($row['parent_index_collation'] ?? ''),
                (string) ($row['status'] ?? ''),
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
                throw new InvalidArgumentException('SQLite PRAGMA current-source next220 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
