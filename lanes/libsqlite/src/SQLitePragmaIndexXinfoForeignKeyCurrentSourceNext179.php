<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext179
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
        int $limit = 179,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        $baseCurrentRecords = self::recordsWithDoubleQuotedConstraintNames($currentRecords);
        $baseNextRecords = self::recordsWithDoubleQuotedConstraintNames($nextRecords);
        $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext171::currentNextPageFromCatalog(
            $baseCurrentRecords,
            $currentTables,
            $baseNextRecords,
            $nextTables,
            $indexXinfoSql,
            $offset,
            $limit,
            null,
            $tableValuedIndexXinfo,
        );

        $currentNames = self::constraintRows($currentRecords);
        $nextNames = self::constraintRows($nextRecords);
        $sourceId = self::stableHash([
            'mode' => 'pragma-index-xinfo-foreignkey-current-source-next179',
            'base' => $page['source_id'],
            'current_constraints' => self::constraintSummary($currentNames),
            'next_constraints' => self::constraintSummary($nextNames),
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $currentMap = self::constraintMap($currentNames);
        $nextMap = self::constraintMap($nextNames);

        return [
            ...$page,
            'source_id' => $sourceId,
            'current_source' => [
                ...$page['current_source'],
                'foreign_key_constraint_source' => 'create_table_constraint_names_single_quoted',
                'foreign_key_constraints' => self::constraintSummary($currentNames),
            ],
            'next_source' => [
                ...$page['next_source'],
                'foreign_key_constraint_source' => 'create_table_constraint_names_single_quoted',
                'foreign_key_constraints' => self::constraintSummary($nextNames),
            ],
            'current' => [
                ...$page['current'],
                'foreign_key_constraints' => self::constraintCounts($currentNames),
            ],
            'next_counts' => [
                ...$page['next_counts'],
                'foreign_key_constraints' => self::constraintCounts($nextNames),
            ],
            'delta' => [
                ...$page['delta'],
                'foreign_key_constraint_changes' => self::constraintChangeCount($currentNames, $nextNames),
                'foreign_key_constraint_changed' => self::constraintSummary($currentNames) !== self::constraintSummary($nextNames),
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
     * @return list<array{table:string,fkid:int,constraint:string|null,origin:string}>
     */
    public static function constraintRows(array $records): array
    {
        self::validateRecords($records);

        $foreignKeys = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext171::foreignKeysFromCatalog($records);
        $clauses = self::constraintClausesByTable($records);
        $rows = [];

        foreach ($foreignKeys as $foreignKey) {
            $table = (string) $foreignKey['table'];
            $id = (int) $foreignKey['id'];
            $clause = $clauses[strtolower($table)][$id] ?? null;
            $rows[] = [
                'table' => $table,
                'fkid' => $id,
                'constraint' => $clause === null ? null : $clause['constraint'],
                'origin' => $clause === null ? 'pragma_foreign_key_list' : $clause['origin'],
            ];
        }

        return $rows;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,list<array{constraint:string|null,origin:string}>>
     */
    private static function constraintClausesByTable(array $records): array
    {
        $clauses = [];
        foreach ($records as $record) {
            if ($record->type !== 'table' || $record->sql === null) {
                continue;
            }

            $body = self::parenthesizedBody($record->sql);
            if ($body === null) {
                continue;
            }

            foreach (self::splitTopLevel($body, ',') as $definition) {
                $definition = trim($definition);
                if (preg_match('/\bREFERENCES\b/i', $definition) !== 1) {
                    continue;
                }
                $clauses[strtolower($record->name)][] = self::constraintFromDefinition($definition);
            }
        }

        return $clauses;
    }

    /**
     * @return array{constraint:string|null,origin:string}
     */
    private static function constraintFromDefinition(string $definition): array
    {
        $identifier = self::identifierPattern();
        if (preg_match('/^CONSTRAINT\s+' . $identifier . '\s+FOREIGN\s+KEY\b/is', $definition, $matches) === 1) {
            return [
                'constraint' => self::matchedIdentifier($matches),
                'origin' => 'table_constraint',
            ];
        }

        if (preg_match('/^(?:"(?:""|[^"])*"|\'(?:\'\'|[^\'])*\'|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)\b.*?\bCONSTRAINT\s+' . $identifier . '\s+REFERENCES\b/is', $definition, $matches) === 1) {
            return [
                'constraint' => self::matchedIdentifier($matches),
                'origin' => 'column_constraint',
            ];
        }

        return [
            'constraint' => null,
            'origin' => preg_match('/^\s*(?:CONSTRAINT\s+\S+\s+)?FOREIGN\s+KEY\b/i', $definition) === 1 ? 'table_constraint' : 'column_constraint',
        ];
    }

    /**
     * @param array<string,string> $matches
     */
    private static function matchedIdentifier(array $matches): string
    {
        if (($matches['dq'] ?? '') !== '') {
            return str_replace('""', '"', $matches['dq']);
        }
        if (($matches['sq'] ?? '') !== '') {
            return str_replace("''", "'", $matches['sq']);
        }
        if (($matches['bt'] ?? '') !== '') {
            return $matches['bt'];
        }
        if (($matches['br'] ?? '') !== '') {
            return $matches['br'];
        }

        return $matches['bare'];
    }

    private static function identifierPattern(): string
    {
        return '(?:"(?<dq>(?:""|[^"])*)"|\'(?<sq>(?:\'\'|[^\'])*)\'|`(?<bt>[^`]*)`|\[(?<br>[^\]]*)\]|(?<bare>[A-Za-z_][A-Za-z0-9_]*))';
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<SQLiteSchemaRecord>
     */
    private static function recordsWithDoubleQuotedConstraintNames(array $records): array
    {
        self::validateRecords($records);

        return array_map(
            static function (SQLiteSchemaRecord $record): SQLiteSchemaRecord {
                if ($record->type !== 'table' || $record->sql === null || !str_contains($record->sql, "CONSTRAINT '")) {
                    return $record;
                }

                $sql = preg_replace_callback(
                    "/\\bCONSTRAINT\\s+'((?:''|[^'])*)'(?=\\s+(?:FOREIGN\\s+KEY\\b|REFERENCES\\b))/i",
                    static fn (array $matches): string => 'CONSTRAINT "' . str_replace('"', '""', str_replace("''", "'", $matches[1])) . '"',
                    $record->sql,
                );

                return new SQLiteSchemaRecord(
                    $record->type,
                    $record->name,
                    $record->tableName,
                    $record->rootPage,
                    $sql,
                    $record->rowId,
                );
            },
            $records,
        );
    }

    /**
     * @param list<array{table:string,fkid:int,constraint:string|null,origin:string}> $rows
     * @return array<string,array{constraint:string|null,origin:string}>
     */
    private static function constraintMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[self::constraintKey($row['table'], $row['fkid'])] = [
                'constraint' => $row['constraint'],
                'origin' => $row['origin'],
            ];
        }

        return $map;
    }

    /**
     * @param array<string,array{constraint:string|null,origin:string}> $constraints
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
            'constraint' => $constraint['constraint'],
            'constraint_origin' => $constraint['origin'],
            'constraint_named' => $constraint['constraint'] !== null,
        ];
    }

    /**
     * @param list<array{table:string,fkid:int,constraint:string|null,origin:string}> $rows
     * @return list<string>
     */
    private static function constraintSummary(array $rows): array
    {
        $summary = array_map(
            static fn (array $row): string => $row['table'] . '#' . $row['fkid'] . ':constraint=' . ($row['constraint'] ?? '<anonymous>') . ',origin=' . $row['origin'],
            $rows,
        );
        sort($summary);

        return $summary;
    }

    /**
     * @param list<array{table:string,fkid:int,constraint:string|null,origin:string}> $rows
     * @return array<string,int>
     */
    private static function constraintCounts(array $rows): array
    {
        $counts = [
            'named' => 0,
            'anonymous' => 0,
            'table_constraint' => 0,
            'column_constraint' => 0,
        ];
        foreach ($rows as $row) {
            $counts[$row['constraint'] === null ? 'anonymous' : 'named']++;
            $counts[$row['origin']] = ($counts[$row['origin']] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param list<array{table:string,fkid:int,constraint:string|null,origin:string}> $current
     * @param list<array{table:string,fkid:int,constraint:string|null,origin:string}> $next
     */
    private static function constraintChangeCount(array $current, array $next): int
    {
        return count(array_diff(self::constraintSummary($next), self::constraintSummary($current)))
            + count(array_diff(self::constraintSummary($current), self::constraintSummary($next)));
    }

    private static function constraintKey(string $table, int $id): string
    {
        return strtolower($table) . '#' . $id;
    }

    /** @param list<mixed> $records */
    private static function validateRecords(array $records): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next179 records must be SQLiteSchemaRecord instances');
            }
        }
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next179 cursor does not match the current source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next179 cursor offset does not match the requested page offset');
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
            if ($depth === 0 && $char === $delimiter) {
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
