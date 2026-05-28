<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext177
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
        int $limit = 177,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext173::currentNextPageFromCatalog(
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

        $currentConstraints = self::constraintRows($currentRecords);
        $nextConstraints = self::constraintRows($nextRecords);
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next177',
            'base' => $page['source_id'],
            'current_constraints' => self::constraintSummary($currentConstraints),
            'next_constraints' => self::constraintSummary($nextConstraints),
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentMap = self::constraintMap($currentConstraints);
        $nextMap = self::constraintMap($nextConstraints);

        return [
            ...$page,
            'source_id' => $sourceId,
            'current_source' => [
                ...$page['current_source'],
                'foreign_key_constraint_source' => 'create_table_constraint_names_and_origins',
                'foreign_key_constraints' => self::constraintSummary($currentConstraints),
            ],
            'next_source' => [
                ...$page['next_source'],
                'foreign_key_constraint_source' => 'create_table_constraint_names_and_origins',
                'foreign_key_constraints' => self::constraintSummary($nextConstraints),
            ],
            'current' => [
                ...$page['current'],
                'foreign_key_constraints' => self::constraintCounts($currentConstraints),
            ],
            'next_counts' => [
                ...$page['next_counts'],
                'foreign_key_constraints' => self::constraintCounts($nextConstraints),
            ],
            'delta' => [
                ...$page['delta'],
                'foreign_key_constraint_changes' => self::constraintChangeCount($currentConstraints, $nextConstraints),
                'foreign_key_constraint_changed' => self::constraintSummary($currentConstraints) !== self::constraintSummary($nextConstraints),
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
     * @return list<array{table:string,fkid:int,constraint_name:string|null,origin:string,child_columns:list<string>,parent_table:string|null}>
     */
    public static function constraintRows(array $records): array
    {
        $rows = [];
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next177 records must be SQLiteSchemaRecord instances');
            }
            if ($record->type !== 'table' || $record->sql === null) {
                continue;
            }

            foreach (self::foreignKeyClauses($record->sql) as $id => $clause) {
                $rows[] = [
                    'table' => $record->name,
                    'fkid' => $id,
                    'constraint_name' => self::constraintName($clause),
                    'origin' => self::constraintOrigin($clause),
                    'child_columns' => self::childColumns($clause),
                    'parent_table' => self::parentTable($clause),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param list<array{table:string,fkid:int,constraint_name:string|null,origin:string,child_columns:list<string>,parent_table:string|null}> $constraints
     * @return list<string>
     */
    private static function constraintSummary(array $constraints): array
    {
        $summary = array_map(
            static fn (array $row): string => $row['table'] . '#' . $row['fkid']
                . ':name=' . ($row['constraint_name'] ?? '')
                . ',origin=' . $row['origin']
                . ',child=' . implode('|', $row['child_columns'])
                . ',parent=' . ($row['parent_table'] ?? ''),
            $constraints,
        );
        sort($summary);

        return $summary;
    }

    /**
     * @param list<array{table:string,fkid:int,constraint_name:string|null,origin:string,child_columns:list<string>,parent_table:string|null}> $constraints
     * @return array<string,int>
     */
    private static function constraintCounts(array $constraints): array
    {
        $counts = [
            'named' => 0,
            'unnamed' => 0,
            'table_origin' => 0,
            'column_origin' => 0,
            'composite_child_keys' => 0,
        ];
        foreach ($constraints as $row) {
            if ($row['constraint_name'] === null) {
                $counts['unnamed']++;
            } else {
                $counts['named']++;
            }
            if ($row['origin'] === 'table') {
                $counts['table_origin']++;
            } else {
                $counts['column_origin']++;
            }
            if (count($row['child_columns']) > 1) {
                $counts['composite_child_keys']++;
            }
        }

        return $counts;
    }

    /**
     * @param list<array{table:string,fkid:int,constraint_name:string|null,origin:string,child_columns:list<string>,parent_table:string|null}> $constraints
     * @return array<string,array{constraint_name:string|null,origin:string,child_columns:list<string>,parent_table:string|null,constraint_summary:string}>
     */
    private static function constraintMap(array $constraints): array
    {
        $map = [];
        foreach ($constraints as $row) {
            $name = $row['constraint_name'];
            $map[self::constraintKey($row['table'], $row['fkid'])] = [
                'constraint_name' => $name,
                'origin' => $row['origin'],
                'child_columns' => $row['child_columns'],
                'parent_table' => $row['parent_table'],
                'constraint_summary' => ($name ?? '<unnamed>') . '/' . $row['origin'] . '/' . implode(',', $row['child_columns']),
            ];
        }

        return $map;
    }

    /**
     * @param array<string,array{constraint_name:string|null,origin:string,child_columns:list<string>,parent_table:string|null,constraint_summary:string}> $constraints
     * @return array<string,mixed>
     */
    private static function decorateRow(array $row, array $constraints): array
    {
        if (($row['kind'] ?? null) !== 'index_admission' && ($row['kind'] ?? null) !== 'foreign_key_check') {
            return $row;
        }

        $constraint = $constraints[self::constraintKey((string) ($row['table'] ?? ''), (int) ($row['fkid'] ?? -1))] ?? null;
        if ($constraint === null) {
            return $row;
        }

        return [
            ...$row,
            'constraint_name' => $constraint['constraint_name'],
            'constraint_origin' => $constraint['origin'],
            'constraint_child_columns' => $constraint['child_columns'],
            'constraint_parent_table' => $constraint['parent_table'],
            'constraint_summary' => $constraint['constraint_summary'],
        ];
    }

    /**
     * @param list<array{table:string,fkid:int,constraint_name:string|null,origin:string,child_columns:list<string>,parent_table:string|null}> $current
     * @param list<array{table:string,fkid:int,constraint_name:string|null,origin:string,child_columns:list<string>,parent_table:string|null}> $next
     */
    private static function constraintChangeCount(array $current, array $next): int
    {
        return count(array_diff(self::constraintSummary($next), self::constraintSummary($current)))
            + count(array_diff(self::constraintSummary($current), self::constraintSummary($next)));
    }

    /**
     * @return list<string>
     */
    private static function foreignKeyClauses(string $sql): array
    {
        $body = self::parenthesizedBody($sql);
        if ($body === null) {
            return [];
        }

        $clauses = [];
        foreach (self::splitTopLevel($body, ',') as $definition) {
            $definition = trim($definition);
            if (preg_match('/^(?:CONSTRAINT\s+(?:"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)\s+)?FOREIGN\s+KEY\b/is', $definition) === 1) {
                $clauses[] = $definition;
                continue;
            }
            if (preg_match('/^(?:CONSTRAINT\s+(?:"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)\s+)?(?:"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)\b.*\bREFERENCES\b/is', $definition) === 1) {
                $clauses[] = $definition;
            }
        }

        return $clauses;
    }

    private static function constraintName(string $clause): ?string
    {
        $clause = trim($clause);
        if (preg_match('/^CONSTRAINT\s+(?:"(?<dq>(?:""|[^"])*)"|`(?<bt>[^`]*)`|\[(?<br>[^\]]*)\]|(?<bare>[A-Za-z_][A-Za-z0-9_]*))/is', $clause, $matches) === 1) {
            return self::identifierFromMatch($matches);
        }
        if (preg_match('/^(?:"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)\b.*?\bCONSTRAINT\s+(?:"(?<dq>(?:""|[^"])*)"|`(?<bt>[^`]*)`|\[(?<br>[^\]]*)\]|(?<bare>[A-Za-z_][A-Za-z0-9_]*))\s+REFERENCES\b/is', $clause, $matches) === 1) {
            return self::identifierFromMatch($matches);
        }

        return null;
    }

    private static function constraintOrigin(string $clause): string
    {
        $clause = trim((string) preg_replace('/^CONSTRAINT\s+(?:"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)\s+/is', '', trim($clause)));

        return preg_match('/^FOREIGN\s+KEY\b/i', $clause) === 1 ? 'table' : 'column';
    }

    /**
     * @return list<string>
     */
    private static function childColumns(string $clause): array
    {
        $trimmed = trim((string) preg_replace('/^CONSTRAINT\s+(?:"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)\s+/is', '', trim($clause)));
        if (preg_match('/^FOREIGN\s+KEY\s*\((?<cols>.*)\)\s+REFERENCES\b/is', $trimmed, $matches) === 1) {
            return array_map(self::normalizeIdentifier(...), self::splitTopLevel($matches['cols'], ','));
        }
        if (preg_match('/^(?<col>"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)\b/is', $trimmed, $matches) === 1) {
            return [self::normalizeIdentifier($matches['col'])];
        }

        return [];
    }

    private static function parentTable(string $clause): ?string
    {
        if (preg_match('/\bREFERENCES\s+(?<table>"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)/is', $clause, $matches) !== 1) {
            return null;
        }

        return self::normalizeIdentifier($matches['table']);
    }

    private static function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return '';
        }
        $first = $identifier[0];
        $last = $identifier[strlen($identifier) - 1];
        if (($first === '"' && $last === '"') || ($first === '`' && $last === '`')) {
            return str_replace($first . $first, $first, substr($identifier, 1, -1));
        }
        if ($first === '[' && $last === ']') {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }

    /**
     * @param array<string,string> $matches
     */
    private static function identifierFromMatch(array $matches): string
    {
        return str_replace('""', '"', $matches['dq'] ?: ($matches['bt'] ?: ($matches['br'] ?: $matches['bare'])));
    }

    private static function constraintKey(string $table, int $id): string
    {
        return strtolower($table) . '#' . $id;
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next177 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next177 cursor offset does not match the requested page offset');
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

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
