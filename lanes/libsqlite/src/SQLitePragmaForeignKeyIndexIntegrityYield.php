<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaForeignKeyIndexIntegrityYield
{
    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<array<string,mixed>> $foreignKeys
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<array{kind:string,table:string,rowid:int|string|null,parent:string,fkid:int|null,index:string|null,columns:list<string>,collations:list<string>,status:string,message:string}>
     */
    public static function collect(array $records, array $foreignKeys, array $tables): array
    {
        $tableRecords = self::tableRecords($records);
        $catalog = new SQLitePragmaSchemaCatalog($records);
        $rows = [];

        foreach ($foreignKeys as $ordinal => $foreignKey) {
            $normalized = self::normalizeForeignKey($foreignKey, $ordinal);
            $parent = $normalized['parent'];
            $parentColumns = $normalized['parent_columns'];
            $parentCollations = self::parentColumnCollations($tableRecords[strtolower($parent)] ?? null, $parentColumns);
            $candidate = self::matchingUniqueIndex($catalog, $parent, $parentColumns, $parentCollations);
            $rowidAlias = self::parentIsRowidAlias($tableRecords[strtolower($parent)] ?? null, $parentColumns);
            $status = ($candidate !== null || $rowidAlias) ? 'ok' : 'blocked';
            $indexName = $candidate['name'] ?? ($rowidAlias ? 'rowid-primary-key' : null);
            $collations = $candidate['collations'] ?? ($rowidAlias ? ['BINARY'] : $parentCollations);

            $rows[] = [
                'kind' => 'index_admission',
                'table' => $normalized['table'],
                'rowid' => null,
                'parent' => $parent,
                'fkid' => $normalized['id'],
                'index' => $indexName,
                'columns' => $parentColumns,
                'collations' => $collations,
                'status' => $status,
                'message' => $status === 'ok'
                    ? "foreign key {$normalized['table']}->{$parent} parent key covered by {$indexName}"
                    : "foreign key {$normalized['table']}->{$parent} parent key has no matching UNIQUE index",
            ];
        }

        foreach (SQLitePragmaForeignKeyCheck::check($tables, $foreignKeys) as $violation) {
            $rows[] = [
                'kind' => 'foreign_key_check',
                'table' => $violation['table'],
                'rowid' => $violation['rowid'],
                'parent' => $violation['parent'],
                'fkid' => $violation['fkid'],
                'index' => null,
                'columns' => [],
                'collations' => [],
                'status' => 'violation',
                'message' => self::foreignKeyMessage($violation),
            ];
        }

        return $rows;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<array<string,mixed>> $foreignKeys
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{index_admissions:int,index_blockers:int,foreign_key_violations:int},next:array{ready:bool,blocking:list<string>},rows:list<array{kind:string,table:string,rowid:int|string|null,parent:string,fkid:int|null,index:string|null,columns:list<string>,collations:list<string>,status:string,message:string}>}
     */
    public static function page(array $records, array $foreignKeys, array $tables, int $offset = 0, int $limit = 71): array
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key/index integrity yield offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign-key/index integrity yield limit must be positive');
        }

        $rows = self::collect($records, $foreignKeys, $tables);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $indexBlockers = count(array_filter($rows, static fn (array $row): bool => $row['kind'] === 'index_admission' && $row['status'] === 'blocked'));
        $violations = count(array_filter($rows, static fn (array $row): bool => $row['kind'] === 'foreign_key_check'));
        $blocking = [];
        if ($indexBlockers > 0) {
            $blocking[] = 'foreign_key_parent_unique_index';
        }
        if ($violations > 0) {
            $blocking[] = 'foreign_key_check';
        }

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => [
                'index_admissions' => count(array_filter($rows, static fn (array $row): bool => $row['kind'] === 'index_admission')),
                'index_blockers' => $indexBlockers,
                'foreign_key_violations' => $violations,
            ],
            'next' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
            'rows' => $pageRows,
        ];
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
                throw new InvalidArgumentException('SQLite foreign-key/index integrity records must be SQLiteSchemaRecord instances');
            }
            if ($record->type === 'table') {
                $tables[strtolower($record->name)] = $record;
            }
        }

        return $tables;
    }

    /**
     * @return array{id:int,table:string,parent:string,columns:list<string>,parent_columns:list<string>}
     */
    private static function normalizeForeignKey(array $foreignKey, int $ordinal): array
    {
        $columns = $foreignKey['columns'] ?? null;
        if (!is_array($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite foreign-key/index integrity foreign key {$ordinal} needs columns");
        }

        $childColumns = [];
        $parentColumns = [];
        foreach ($columns as $child => $parentColumn) {
            if (is_int($child) && is_array($parentColumn)) {
                $childColumns[] = self::identifier($parentColumn['child'] ?? null, 'child column');
                $parentColumns[] = self::identifier($parentColumn['parent'] ?? null, 'parent column');
                continue;
            }
            $childColumns[] = self::identifier($child, 'child column');
            $parentColumns[] = self::identifier($parentColumn, 'parent column');
        }

        return [
            'id' => (int) ($foreignKey['id'] ?? $ordinal),
            'table' => self::identifier($foreignKey['table'] ?? null, 'child table'),
            'parent' => self::identifier($foreignKey['parent'] ?? null, 'parent table'),
            'columns' => $childColumns,
            'parent_columns' => $parentColumns,
        ];
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private static function parentColumnCollations(?SQLiteSchemaRecord $record, array $columns): array
    {
        $collations = [];
        $sql = $record?->sql ?? '';
        foreach ($columns as $column) {
            $collations[] = self::declaredColumnCollation($sql, $column);
        }

        return $collations;
    }

    /**
     * @param list<string> $columns
     * @param list<string> $collations
     * @return array{name:string,collations:list<string>}|null
     */
    private static function matchingUniqueIndex(SQLitePragmaSchemaCatalog $catalog, string $table, array $columns, array $collations): ?array
    {
        foreach ($catalog->execute("PRAGMA index_list({$table})")['rows'] as $index) {
            if ((int) $index['unique'] !== 1 || (int) $index['partial'] !== 0) {
                continue;
            }
            $indexName = (string) $index['name'];
            $xinfo = array_values(array_filter(
                $catalog->execute('PRAGMA index_xinfo(' . self::pragmaArgumentLiteral($indexName) . ')')['rows'],
                static fn (array $row): bool => (int) $row['key'] === 1
            ));
            $indexColumns = array_map(static fn (array $row): string => (string) $row['name'], $xinfo);
            $indexCollations = array_map(static fn (array $row): string => strtoupper((string) $row['coll']), $xinfo);
            if (self::sameIdentifierList($indexColumns, $columns) && $indexCollations === array_map('strtoupper', $collations)) {
                return [
                    'name' => $indexName,
                    'collations' => $indexCollations,
                ];
            }
        }

        return null;
    }

    /**
     * @param list<string> $parentColumns
     */
    private static function parentIsRowidAlias(?SQLiteSchemaRecord $table, array $parentColumns): bool
    {
        if ($table === null || $table->sql === null || count($parentColumns) !== 1) {
            return false;
        }

        return preg_match(
            '/\b' . preg_quote($parentColumns[0], '/') . '\b\s+INTEGER\s+PRIMARY\s+KEY\b/i',
            $table->sql
        ) === 1;
    }

    private static function declaredColumnCollation(string $sql, string $column): string
    {
        $body = self::parenthesizedBody($sql);
        if ($body === null) {
            return 'BINARY';
        }

        foreach (self::splitTopLevel($body, ',') as $definition) {
            $definition = trim($definition);
            if (preg_match('/^(?:"(?<dq>(?:""|[^"])*)"|`(?<bt>[^`]*)`|\[(?<br>[^\]]*)\]|(?<bare>[A-Za-z_][A-Za-z0-9_]*))\b(?<tail>.*)$/s', $definition, $matches) !== 1) {
                continue;
            }
            $name = str_replace('""', '"', $matches['dq'] ?: ($matches['bt'] ?: ($matches['br'] ?: $matches['bare'])));
            if (strcasecmp($name, $column) !== 0) {
                continue;
            }
            if (preg_match('/\bCOLLATE\s+(?:"(?<cdq>(?:""|[^"])*)"|`(?<cbt>[^`]*)`|\[(?<cbr>[^\]]*)\]|(?<cbare>[A-Za-z_][A-Za-z0-9_]*))/i', $matches['tail'], $collation) === 1) {
                return strtoupper(str_replace('""', '"', $collation['cdq'] ?: ($collation['cbt'] ?: ($collation['cbr'] ?: $collation['cbare']))));
            }

            return 'BINARY';
        }

        return 'BINARY';
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
                    if ($quote === "'" && ($sql[$i + 1] ?? '') === "'") {
                        $i++;
                        continue;
                    }
                    if ($quote === '"' && ($sql[$i + 1] ?? '') === '"') {
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
                $depth = max(0, $depth - 1);
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

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private static function sameIdentifierList(array $left, array $right): bool
    {
        return array_map('strtolower', $left) === array_map('strtolower', $right);
    }

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite foreign-key/index integrity {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param array{table:string,rowid:int|string|null,parent:string,fkid:int} $row
     */
    private static function foreignKeyMessage(array $row): string
    {
        $rowid = $row['rowid'] === null ? 'NULL' : (string) $row['rowid'];

        return "foreign key mismatch in {$row['table']} rowid {$rowid} references {$row['parent']} fkid {$row['fkid']}";
    }

    private static function pragmaArgumentLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
