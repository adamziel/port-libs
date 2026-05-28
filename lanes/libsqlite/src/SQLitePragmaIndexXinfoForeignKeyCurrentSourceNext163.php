<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext163
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
        int $limit = 163,
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
                'implicit_parent_keys' => self::implicitParentKeyCount($currentForeignKeys),
            ],
            'next_source' => [
                ...$page['next_source'],
                'foreign_key_source' => 'pragma_foreign_key_list',
                'derived_foreign_keys' => count($nextForeignKeys),
                'implicit_parent_keys' => self::implicitParentKeyCount($nextForeignKeys),
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
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next163 records must be SQLiteSchemaRecord instances');
            }
            if ($record->type !== 'table') {
                continue;
            }

            $grouped = [];
            foreach ($catalog->execute('PRAGMA foreign_key_list(' . self::pragmaArgumentLiteral($record->name) . ')')['rows'] as $row) {
                $grouped[(int) $row['id']][] = $row;
            }

            ksort($grouped);
            foreach ($grouped as $id => $rows) {
                usort($rows, static fn (array $left, array $right): int => (int) $left['seq'] <=> (int) $right['seq']);
                $parent = (string) $rows[0]['table'];
                $parentRecord = $tables[strtolower($parent)] ?? null;
                $implicitParentColumns = self::primaryKeyColumns($parentRecord);
                $columns = [];
                foreach ($rows as $row) {
                    $seq = (int) $row['seq'];
                    $parentColumn = (string) ($row['to'] ?? '');
                    $implicit = false;
                    if ($parentColumn === '') {
                        $parentColumn = $implicitParentColumns[$seq] ?? '';
                        $implicit = true;
                    }
                    if ($parentColumn === '') {
                        throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next163 cannot resolve implicit parent primary-key column');
                    }
                    $columns[] = [
                        'child' => (string) $row['from'],
                        'parent' => $parentColumn,
                        'implicit_parent' => $implicit,
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
     * @param list<array<string,mixed>> $foreignKeys
     */
    private static function implicitParentKeyCount(array $foreignKeys): int
    {
        $count = 0;
        foreach ($foreignKeys as $foreignKey) {
            foreach (($foreignKey['columns'] ?? []) as $column) {
                if (($column['implicit_parent'] ?? false) === true) {
                    $count++;
                }
            }
        }

        return $count;
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
                throw new InvalidArgumentException('SQLite PRAGMA index_xinfo/FK current-source next163 records must be SQLiteSchemaRecord instances');
            }
            if ($record->type === 'table') {
                $tables[strtolower($record->name)] = $record;
            }
        }

        return $tables;
    }

    /**
     * @return list<string>
     */
    private static function primaryKeyColumns(?SQLiteSchemaRecord $record): array
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
            if (preg_match('/^(?:CONSTRAINT\s+(?:"(?:""|[^"])*"|`[^`]*`|\[[^\]]*\]|[A-Za-z_][A-Za-z0-9_]*)\s+)?PRIMARY\s+KEY\s*\((?<cols>.*)\)/is', $definition, $matches) === 1) {
                return self::columnList($matches['cols']);
            }
            $name = self::definitionName($definition);
            if ($name !== null && preg_match('/\bPRIMARY\s+KEY\b/i', $definition) === 1) {
                $inline[] = $name;
            }
        }

        return $inline;
    }

    /**
     * @return list<string>
     */
    private static function columnList(string $sql): array
    {
        $columns = [];
        foreach (self::splitTopLevel($sql, ',') as $part) {
            $part = trim($part);
            $name = self::definitionName($part);
            if ($name !== null) {
                $columns[] = $name;
            }
        }

        return $columns;
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
            $name = self::definitionName($definition);
            if ($name !== null && strcasecmp($name, $column) === 0) {
                return $definition;
            }
        }

        return null;
    }

    private static function definitionName(string $definition): ?string
    {
        if (preg_match('/^(?:"(?<dq>(?:""|[^"])*)"|`(?<bt>[^`]*)`|\[(?<br>[^\]]*)\]|(?<bare>[A-Za-z_][A-Za-z0-9_]*))\b/s', $definition, $matches) !== 1) {
            return null;
        }

        return str_replace('""', '"', $matches['dq'] ?: ($matches['bt'] ?: ($matches['br'] ?: $matches['bare'])));
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
