<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext161
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
        int $limit = 161,
        ?array $cursor = null,
        bool $tableValuedIndexXinfo = false,
    ): array {
        $currentForeignKeys = self::foreignKeysFromCatalog($currentRecords);
        $nextForeignKeys = self::foreignKeysFromCatalog($nextRecords);

        $page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext156::currentNextPage(
            $currentRecords,
            $currentForeignKeys,
            $currentTables,
            $nextRecords,
            $nextForeignKeys,
            $nextTables,
            $indexXinfoSql,
            $offset,
            $limit,
            $cursor,
            $tableValuedIndexXinfo,
        );

        return [
            ...$page,
            'current_source' => [
                ...$page['current_source'],
                'foreign_key_source' => 'pragma_foreign_key_list',
                'derived_foreign_keys' => count($currentForeignKeys),
            ],
            'next_source' => [
                ...$page['next_source'],
                'foreign_key_source' => 'pragma_foreign_key_list',
                'derived_foreign_keys' => count($nextForeignKeys),
            ],
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function foreignKeysFromCatalog(array $records): array
    {
        $catalog = new SQLitePragmaSchemaCatalog($records);
        $tables = self::tableRecords($records);
        $foreignKeys = [];

        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next161 records must be SQLiteSchemaRecord instances');
            }
            if ($record->type !== 'table') {
                continue;
            }

            $grouped = [];
            foreach ($catalog->execute('PRAGMA foreign_key_list(' . self::pragmaArgumentLiteral($record->name) . ')')['rows'] as $row) {
                $id = (int) $row['id'];
                $grouped[$id][] = $row;
            }

            ksort($grouped);
            foreach ($grouped as $id => $rows) {
                usort($rows, static fn (array $left, array $right): int => (int) $left['seq'] <=> (int) $right['seq']);
                $parent = (string) $rows[0]['table'];
                $parentRecord = $tables[strtolower($parent)] ?? null;
                $columns = [];
                $implicitParentColumns = self::implicitParentColumns($parentRecord);
                foreach ($rows as $rowIndex => $row) {
                    $parentColumn = (string) ($row['to'] ?? '');
                    if ($parentColumn === '') {
                        $parentColumn = $implicitParentColumns[$rowIndex] ?? '';
                    }
                    if ($parentColumn === '') {
                        throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next161 needs explicit parent columns or a parent primary key');
                    }
                    $columns[] = [
                        'child' => (string) $row['from'],
                        'parent' => $parentColumn,
                        'affinity' => self::declaredColumnAffinity($parentRecord, $parentColumn),
                        'collation' => strtolower(self::declaredColumnCollation($parentRecord, $parentColumn)),
                    ];
                }

                $foreignKeys[] = [
                    'id' => $id,
                    'table' => $record->name,
                    'parent' => $parent,
                    'without_rowid' => self::isWithoutRowid($record),
                    'columns' => $columns,
                ];
            }
        }

        return $foreignKeys;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,SQLiteSchemaRecord>
     */
    private static function tableRecords(array $records): array
    {
        $tables = [];
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next161 records must be SQLiteSchemaRecord instances');
            }
            if ($record->type === 'table') {
                $tables[strtolower($record->name)] = $record;
            }
        }

        return $tables;
    }

    private static function declaredColumnAffinity(?SQLiteSchemaRecord $record, string $column): string
    {
        $definition = self::columnDefinition($record, $column);
        if ($definition === null) {
            return 'none';
        }

        $tail = trim((string) preg_replace('/^(?:"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)\s*/', '', $definition));
        $type = strtoupper(trim((string) preg_replace('/\b(COLLATE|CONSTRAINT|PRIMARY|NOT|NULL|UNIQUE|CHECK|DEFAULT|REFERENCES|GENERATED|AS)\b.*$/i', '', $tail)));

        if (str_contains($type, 'INT')) {
            return 'integer';
        }
        if (str_contains($type, 'CHAR') || str_contains($type, 'CLOB') || str_contains($type, 'TEXT')) {
            return 'text';
        }
        if (str_contains($type, 'BLOB') || $type === '') {
            return 'none';
        }
        if (str_contains($type, 'REAL') || str_contains($type, 'FLOA') || str_contains($type, 'DOUB')) {
            return 'real';
        }

        return 'numeric';
    }

    private static function declaredColumnCollation(?SQLiteSchemaRecord $record, string $column): string
    {
        $definition = self::columnDefinition($record, $column);
        if ($definition === null) {
            return 'BINARY';
        }
        if (preg_match('/\bCOLLATE\s+(?:"(?<dq>(?:""|[^"])*)"|`(?<bt>[^`]*)`|\[(?<br>[^\]]*)\]|(?<bare>[A-Za-z_][A-Za-z0-9_]*))/i', $definition, $matches) !== 1) {
            return 'BINARY';
        }

        return strtoupper(str_replace('""', '"', $matches['dq'] ?: ($matches['bt'] ?: ($matches['br'] ?: $matches['bare']))));
    }

    private static function columnDefinition(?SQLiteSchemaRecord $record, string $column): ?string
    {
        if ($record === null || $record->sql === null) {
            return null;
        }

        $body = self::parenthesizedBody($record->sql);
        if ($body === null) {
            return null;
        }

        foreach (self::splitTopLevel($body, ',') as $definition) {
            $definition = trim($definition);
            if (preg_match('/^(?:"(?<dq>(?:""|[^"])*)"|`(?<bt>[^`]*)`|\[(?<br>[^\]]*)\]|(?<bare>[A-Za-z_][A-Za-z0-9_]*))\b/s', $definition, $matches) !== 1) {
                continue;
            }
            $name = str_replace('""', '"', $matches['dq'] ?: ($matches['bt'] ?: ($matches['br'] ?: $matches['bare'])));
            if (strcasecmp($name, $column) === 0) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function implicitParentColumns(?SQLiteSchemaRecord $record): array
    {
        if ($record === null || $record->sql === null) {
            return [];
        }

        $body = self::parenthesizedBody($record->sql);
        if ($body === null) {
            return [];
        }

        $inline = [];
        foreach (self::splitTopLevel($body, ',') as $definition) {
            $definition = trim($definition);
            if (preg_match('/^PRIMARY\s+KEY\s*\((?<columns>.*)\)/is', $definition, $matches) === 1) {
                return self::columnNameList($matches['columns']);
            }
            if (preg_match('/^(?:CONSTRAINT\s+(?:"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)\s+)?PRIMARY\s+KEY\s*\((?<columns>.*)\)/is', $definition, $matches) === 1) {
                return self::columnNameList($matches['columns']);
            }
            if (preg_match('/^(?:"(?<dq>(?:""|[^"])*)"|`(?<bt>[^`]*)`|\[(?<br>[^\]]*)\]|(?<bare>[A-Za-z_][A-Za-z0-9_]*))\b(?<tail>.*)$/is', $definition, $matches) !== 1) {
                continue;
            }
            if (preg_match('/\bPRIMARY\s+KEY\b/i', $matches['tail']) === 1) {
                $inline[] = str_replace('""', '"', $matches['dq'] ?: ($matches['bt'] ?: ($matches['br'] ?: $matches['bare'])));
            }
        }

        return $inline;
    }

    /**
     * @return list<string>
     */
    private static function columnNameList(string $columns): array
    {
        $names = [];
        foreach (self::splitTopLevel($columns, ',') as $column) {
            $column = trim($column);
            if (preg_match('/^(?:"(?<dq>(?:""|[^"])*)"|`(?<bt>[^`]*)`|\[(?<br>[^\]]*)\]|(?<bare>[A-Za-z_][A-Za-z0-9_]*))/s', $column, $matches) === 1) {
                $names[] = str_replace('""', '"', $matches['dq'] ?: ($matches['bt'] ?: ($matches['br'] ?: $matches['bare'])));
            }
        }

        return $names;
    }

    private static function isWithoutRowid(SQLiteSchemaRecord $record): bool
    {
        return $record->sql !== null && preg_match('/\)\s*WITHOUT\s+ROWID\b/i', $record->sql) === 1;
    }

    private static function pragmaArgumentLiteral(string $identifier): string
    {
        return "'" . str_replace("'", "''", $identifier) . "'";
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
    private static function splitTopLevel(string $sql, string $delimiter): array
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
                continue;
            }
            if ($depth === 0 && $char === $delimiter) {
                $parts[] = substr($sql, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($sql, $start);

        return $parts;
    }
}
