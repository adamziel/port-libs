<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext240
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
            throw new InvalidArgumentException('SQLite PRAGMA current-source next240 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA current-source next240 limit must be positive');
        }

        $base = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext237::page(
            $currentRecords,
            $nextRecords,
            $indexXinfoSql,
            $foreignKeySql,
            0,
            PHP_INT_MAX,
        );

        $currentRows = self::implicitParentPrimaryKeyRows($currentRecords, 'current');
        $nextRows = self::implicitParentPrimaryKeyRows($nextRecords, 'next');
        $sourceId = hash('sha256', json_encode([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next240',
            'base' => $base['source_id'],
            'current_implicit_parent_pk' => self::rowSummary($currentRows),
            'next_implicit_parent_pk' => self::rowSummary($nextRows),
        ], JSON_THROW_ON_ERROR));

        if ($resume !== null) {
            if (($resume['source_id'] ?? null) !== $sourceId) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next240 resume cursor does not match current source');
            }
            if (($resume['offset'] ?? null) !== $offset) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next240 resume cursor offset mismatch');
            }
        }

        $allRows = array_values(array_merge($base['rows'], $currentRows, $nextRows));
        $pageRows = array_slice($allRows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $currentCounts = self::implicitCounts($currentRows);
        $nextCounts = self::implicitCounts($nextRows);

        return [
            ...$base,
            'operation' => 'pragma-index-xinfo-foreignkey-current-source-next240',
            'source_id' => $sourceId,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($allRows),
            'next' => $nextOffset < count($allRows) ? ['source_id' => $sourceId, 'offset' => $nextOffset] : null,
            'next_row' => $allRows[$nextOffset] ?? null,
            'current_source' => [
                ...$base['current_source'],
                'foreign_key_implicit_parent_primary_key_source' => 'pragma_foreign_key_list_empty_to_plus_parent_table_info_primary_key',
                'foreign_key_implicit_parent_primary_key' => self::rowSummary($currentRows),
            ],
            'next_source' => [
                ...($base['next_source'] ?? []),
                'foreign_key_implicit_parent_primary_key_source' => 'pragma_foreign_key_list_empty_to_plus_parent_table_info_primary_key',
                'foreign_key_implicit_parent_primary_key' => self::rowSummary($nextRows),
            ],
            'current' => [
                ...$base['current'],
                'foreign_key_implicit_parent_primary_key' => $currentCounts,
            ],
            'next_counts' => [
                ...$base['next_counts'],
                'foreign_key_implicit_parent_primary_key' => $nextCounts,
            ],
            'delta' => [
                ...$base['delta'],
                'foreign_key_implicit_parent_primary_key_rows' => $nextCounts['rows'] - $currentCounts['rows'],
                'foreign_key_implicit_parent_primary_key_blockers' => $nextCounts['blocked'] - $currentCounts['blocked'],
                'foreign_key_implicit_parent_primary_key_repaired' => $currentCounts['blocked'] > 0 && $nextCounts['blocked'] === 0,
                'foreign_key_implicit_parent_primary_key_changed' => self::rowSummary($currentRows, false) !== self::rowSummary($nextRows, false),
            ],
            'dependencies' => array_values(array_unique([
                ...$base['dependencies'],
                'sqlite-pragma-foreign-key-implicit-parent-primary-key',
            ])),
            'rows' => $pageRows,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function implicitParentPrimaryKeyRows(array $records, string $phase = 'current'): array
    {
        self::validateRecords($records);

        $catalog = new SQLitePragmaSchemaCatalog($records);
        $omitted = self::omittedParentColumnForeignKeys($records);
        $rows = [];
        foreach (self::groupForeignKeyRows(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175::foreignKeyListRows($records, $phase)) as $group) {
            $groupKey = strtolower((string) $group[0]['table']) . '#' . (int) $group[0]['id'];
            if (!isset($omitted[$groupKey])) {
                continue;
            }

            $parent = (string) $group[0]['parent'];
            $parentPrimaryKey = self::primaryKeyColumns($catalog, $parent);
            $status = $parentPrimaryKey === []
                ? 'missing_parent_primary_key'
                : (count($parentPrimaryKey) === count($group) ? 'ok' : 'parent_primary_key_arity_mismatch');

            foreach ($group as $row) {
                $seq = (int) $row['seq'];
                $parentColumn = $parentPrimaryKey[$seq] ?? null;
                $rows[] = [
                    'phase' => $phase,
                    'kind' => 'foreign_key_implicit_parent_primary_key',
                    'table' => (string) $row['table'],
                    'foreign_key_id' => (int) $row['id'],
                    'seq' => $seq,
                    'parent' => $parent,
                    'from' => (string) $row['from'],
                    'to' => '',
                    'resolved_to' => (string) $row['to'],
                    'implicit_parent_column' => $parentColumn,
                    'parent_primary_key_columns' => $parentPrimaryKey,
                    'child_key_arity' => count($group),
                    'parent_key_arity' => count($parentPrimaryKey),
                    'status' => $status,
                    'message' => match ($status) {
                        'ok' => "foreign key {$row['table']}->{$parent} maps omitted parent column to primary key column {$parentColumn}",
                        'parent_primary_key_arity_mismatch' => "foreign key {$row['table']}->{$parent} omits parent columns but child key arity does not match parent primary key arity",
                        default => "foreign key {$row['table']}->{$parent} omits parent columns but parent table has no explicit primary key",
                    },
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
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,true>
     */
    private static function omittedParentColumnForeignKeys(array $records): array
    {
        $omitted = [];
        foreach ($records as $record) {
            if ($record->type !== 'table' || $record->sql === null) {
                continue;
            }

            $id = 0;
            $body = self::parenthesizedBody($record->sql);
            if ($body === null) {
                continue;
            }

            foreach (self::splitTopLevel($body) as $definition) {
                $definition = trim($definition);
                if ($definition === '') {
                    continue;
                }

                if (preg_match('/^(?:CONSTRAINT\s+(?:"(?:""|[^"])*"|`[^`]*`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s+)?FOREIGN\s+KEY\s*\((?<from>[^)]*)\)\s+REFERENCES\s+(?<parent>"(?:""|[^"])*"|`[^`]*`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)(?<tail>.*)$/is', $definition, $matches) === 1) {
                    if (!self::referenceTailHasColumnList($matches['tail'])) {
                        $omitted[strtolower($record->name) . '#' . $id] = true;
                    }
                    $id++;
                    continue;
                }

                if (preg_match('/^(?:"(?:""|[^"])*"|`[^`]*`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\b(?<tail>.*)$/s', $definition, $matches) !== 1) {
                    continue;
                }
                if (preg_match('/\bREFERENCES\s+(?<parent>"(?:""|[^"])*"|`[^`]*`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)(?<tail>.*)$/is', $matches['tail'], $reference) !== 1) {
                    continue;
                }
                if (!self::referenceTailHasColumnList($reference['tail'])) {
                    $omitted[strtolower($record->name) . '#' . $id] = true;
                }
                $id++;
            }
        }

        return $omitted;
    }

    private static function referenceTailHasColumnList(string $tail): bool
    {
        $tail = ltrim($tail);

        return $tail !== '' && $tail[0] === '(';
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
     * @param list<array<string,mixed>> $rows
     * @return array{rows:int,ok:int,blocked:int,missing_parent_primary_key:int,parent_primary_key_arity_mismatch:int,implicit_columns:int,composite:int}
     */
    private static function implicitCounts(array $rows): array
    {
        $counts = [
            'rows' => count($rows),
            'ok' => 0,
            'blocked' => 0,
            'missing_parent_primary_key' => 0,
            'parent_primary_key_arity_mismatch' => 0,
            'implicit_columns' => 0,
            'composite' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'ok') {
                $counts['ok']++;
                $counts['implicit_columns']++;
            } else {
                $counts['blocked']++;
                if (isset($counts[$status])) {
                    $counts[$status]++;
                }
            }
            if ((int) ($row['child_key_arity'] ?? 0) > 1) {
                $counts['composite']++;
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
                (string) $row['from'] . '->' . (string) $row['parent'] . '.<implicit-pk>',
                'parent_pk=' . implode(',', (array) $row['parent_primary_key_columns']),
                'implicit=' . (string) ($row['implicit_parent_column'] ?? ''),
                'arity=' . (int) $row['child_key_arity'] . '/' . (int) $row['parent_key_arity'],
                (string) ($row['status'] ?? ''),
            ], static fn (?string $part): bool => $part !== null)),
            $rows,
        );
        sort($summary);

        return $summary;
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

    /**
     * @param list<mixed> $records
     */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA current-source next240 records must be SQLiteSchemaRecord instances');
            }
        }
    }
}
