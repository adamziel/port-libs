<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteAlterTableColumnCorpus
{
    /**
     * @return array{status:string, table:string, column:string, column_count:int, sql:string, added:list<string>, dependencies:list<string>}
     */
    public static function addColumn(SQLiteSchemaRecord $table, string $alterSql): array
    {
        self::assertTableRecord($table);
        if (!preg_match('/\A\s*ALTER\s+TABLE\s+(?:IF\s+EXISTS\s+)?(?:(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)\s*\.\s*)?(?:"([^"]+)"|`([^`]+)`|\[([^\]]+)\]|(\w+))\s+ADD\s+(?:COLUMN\s+)?(.+?)\s*;?\s*\z/is', $alterSql, $matches)) {
            throw new InvalidArgumentException('SQLite ALTER TABLE ADD COLUMN SQL is malformed');
        }

        $target = self::firstNonEmpty(array_slice($matches, 1, 4));
        if (!self::sameIdentifier($target, $table->tableName)) {
            throw new InvalidArgumentException("SQLite ALTER TABLE ADD COLUMN target {$target} does not match {$table->tableName}");
        }

        $definition = self::normalizeSql($matches[5]);
        $column = self::columnName($definition);
        $columns = self::tableColumns($table->sql ?? '');
        if (self::containsIdentifier($columns, $column)) {
            throw new InvalidArgumentException("SQLite duplicate column name: {$column}");
        }
        self::assertAddColumnDefinition($definition);

        $sql = rtrim((string) $table->sql);
        $close = strrpos($sql, ')');
        if ($close === false) {
            throw new InvalidArgumentException('SQLite CREATE TABLE SQL is missing column list');
        }

        $rewritten = substr($sql, 0, $close) . ', ' . $definition . substr($sql, $close);

        return [
            'status' => 'added',
            'table' => $table->tableName,
            'column' => $column,
            'column_count' => count($columns) + 1,
            'sql' => self::normalizeSql($rewritten),
            'added' => [$column],
            'dependencies' => [],
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $schemaRecords
     * @return array{status:string, table:string, column:string, column_count:int, sql:string, dropped:list<string>, preserved:list<string>, dependencies:list<string>}
     */
    public static function dropColumn(SQLiteSchemaRecord $table, string $alterSql, array $schemaRecords = []): array
    {
        self::assertTableRecord($table);
        if (!preg_match('/\A\s*ALTER\s+TABLE\s+(?:(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)\s*\.\s*)?(?:"([^"]+)"|`([^`]+)`|\[([^\]]+)\]|(\w+))\s+DROP\s+(?:COLUMN\s+)?(?:"([^"]+)"|`([^`]+)`|\[([^\]]+)\]|(\w+))\s*;?\s*\z/is', $alterSql, $matches)) {
            throw new InvalidArgumentException('SQLite ALTER TABLE DROP COLUMN SQL is malformed');
        }

        $target = self::firstNonEmpty(array_slice($matches, 1, 4));
        if (!self::sameIdentifier($target, $table->tableName)) {
            throw new InvalidArgumentException("SQLite ALTER TABLE DROP COLUMN target {$target} does not match {$table->tableName}");
        }
        $column = self::firstNonEmpty(array_slice($matches, 5, 4));
        $parts = self::tableColumnDefinitions($table->sql ?? '');
        $allParts = self::tableDefinitions($table->sql ?? '');
        $columns = array_map(static fn (string $definition): string => self::columnName($definition), $parts);
        $index = self::identifierIndex($columns, $column);
        if ($index === null) {
            throw new InvalidArgumentException("SQLite no such column: {$column}");
        }

        self::assertDropColumnAllowed($parts[$index], $column, $allParts, $schemaRecords, $table->tableName);
        unset($parts[$index]);
        $parts = array_values($parts);
        $prefix = self::createTablePrefix($table->sql ?? '');
        $suffix = self::createTableSuffix($table->sql ?? '');

        return [
            'status' => 'dropped',
            'table' => $table->tableName,
            'column' => $column,
            'column_count' => count($parts),
            'sql' => self::normalizeSql($prefix . implode(', ', $parts) . ')' . $suffix),
            'dropped' => [$column],
            'preserved' => array_values(array_filter($columns, static fn (string $name): bool => !self::sameIdentifier($name, $column))),
            'dependencies' => self::dependentObjects($schemaRecords, $table->tableName, $column),
        ];
    }

    /**
     * @return list<string>
     */
    public static function tableColumns(string $createTableSql): array
    {
        return array_map(static fn (string $definition): string => self::columnName($definition), self::tableColumnDefinitions($createTableSql));
    }

    private static function assertTableRecord(SQLiteSchemaRecord $record): void
    {
        if (strtolower($record->type) !== 'table') {
            throw new InvalidArgumentException('SQLite ALTER TABLE column corpus requires a table schema record');
        }
        if ($record->sql === null || trim($record->sql) === '') {
            throw new InvalidArgumentException('SQLite ALTER TABLE column corpus requires CREATE TABLE SQL');
        }
    }

    private static function assertAddColumnDefinition(string $definition): void
    {
        $tail = self::definitionTail($definition);
        if (preg_match('/\b(PRIMARY\s+KEY|UNIQUE)\b/i', $tail)) {
            throw new InvalidArgumentException('SQLite cannot add a PRIMARY KEY or UNIQUE column');
        }
        if (preg_match('/\bGENERATED\s+ALWAYS\b.+\bSTORED\b/i', $tail)) {
            throw new InvalidArgumentException('SQLite cannot add a STORED generated column');
        }
        if (preg_match('/\bNOT\s+NULL\b/i', $tail) && !preg_match('/\bDEFAULT\b/i', $tail)) {
            throw new InvalidArgumentException('SQLite cannot add a NOT NULL column with default NULL');
        }
        if (preg_match('/\bDEFAULT\s*\(\s*(CURRENT_TIME|CURRENT_DATE|CURRENT_TIMESTAMP)\s*\)/i', $tail) || preg_match('/\bDEFAULT\s+(CURRENT_TIME|CURRENT_DATE|CURRENT_TIMESTAMP)\b/i', $tail)) {
            throw new InvalidArgumentException('SQLite cannot add a column with a non-constant default');
        }
    }

    /**
     * @param list<string> $parts
     * @param list<SQLiteSchemaRecord> $schemaRecords
     */
    private static function assertDropColumnAllowed(string $definition, string $column, array $parts, array $schemaRecords, string $tableName): void
    {
        $tail = self::definitionTail($definition);
        if (preg_match('/\b(PRIMARY\s+KEY|UNIQUE)\b/i', $tail)) {
            throw new InvalidArgumentException("SQLite cannot drop column {$column} because it is PRIMARY KEY or UNIQUE");
        }

        foreach ($parts as $part) {
            $trimmed = ltrim($part);
            if (preg_match('/\A(?:CONSTRAINT\s+(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)\s+)?(?:PRIMARY\s+KEY|UNIQUE|CHECK|FOREIGN\s+KEY)\b/i', $trimmed) && self::sqlReferencesIdentifier($trimmed, $column)) {
                throw new InvalidArgumentException("SQLite cannot drop column {$column} because table constraints reference it");
            }
        }

        $dependencies = self::dependentObjects($schemaRecords, $tableName, $column);
        if ($dependencies !== []) {
            throw new InvalidArgumentException("SQLite cannot drop column {$column} because schema objects depend on it: " . implode(', ', $dependencies));
        }
    }

    /**
     * @return list<string>
     */
    private static function tableColumnDefinitions(string $createTableSql): array
    {
        $open = strpos($createTableSql, '(');
        $close = strrpos($createTableSql, ')');
        if ($open === false || $close === false || $close <= $open) {
            throw new InvalidArgumentException('SQLite CREATE TABLE SQL is missing column list');
        }

        $definitions = [];
        foreach (self::tableDefinitions($createTableSql) as $part) {
            $trimmed = trim($part);
            if ($trimmed === '' || preg_match('/\A(?:CONSTRAINT\s+(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)\s+)?(?:PRIMARY\s+KEY|UNIQUE|CHECK|FOREIGN\s+KEY)\b/i', $trimmed)) {
                continue;
            }
            $definitions[] = self::normalizeSql($trimmed);
        }

        return $definitions;
    }

    /**
     * @return list<string>
     */
    private static function tableDefinitions(string $createTableSql): array
    {
        $open = strpos($createTableSql, '(');
        $close = strrpos($createTableSql, ')');
        if ($open === false || $close === false || $close <= $open) {
            throw new InvalidArgumentException('SQLite CREATE TABLE SQL is missing column list');
        }

        return array_map(
            static fn (string $part): string => self::normalizeSql($part),
            self::splitTopLevel(substr($createTableSql, $open + 1, $close - $open - 1))
        );
    }

    private static function createTablePrefix(string $createTableSql): string
    {
        $open = strpos($createTableSql, '(');
        if ($open === false) {
            throw new InvalidArgumentException('SQLite CREATE TABLE SQL is missing column list');
        }

        return rtrim(substr($createTableSql, 0, $open + 1));
    }

    private static function createTableSuffix(string $createTableSql): string
    {
        $close = strrpos($createTableSql, ')');
        if ($close === false) {
            throw new InvalidArgumentException('SQLite CREATE TABLE SQL is missing column list');
        }

        return self::normalizeSql(substr($createTableSql, $close + 1));
    }

    private static function columnName(string $definition): string
    {
        if (!preg_match('/\A\s*(?:"([^"]+)"|`([^`]+)`|\[([^\]]+)\]|(\w+))/s', $definition, $matches)) {
            throw new InvalidArgumentException('SQLite column definition is missing a column name');
        }

        return self::firstNonEmpty(array_slice($matches, 1, 4));
    }

    private static function definitionTail(string $definition): string
    {
        if (!preg_match('/\A\s*(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)\s*(.*)\z/s', $definition, $matches)) {
            return '';
        }

        return $matches[1];
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $sql): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === $quote) {
                    if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                        $buffer .= $sql[++$i];
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === '[') {
                $quote = ']';
                $buffer .= $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }

        $parts[] = $buffer;

        return $parts;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<string>
     */
    private static function dependentObjects(array $records, string $tableName, string $column): array
    {
        $dependencies = [];
        foreach ($records as $record) {
            if ($record->sql === null || $record->sql === '' || self::sameIdentifier($record->name, $tableName)) {
                continue;
            }
            if (!self::sameIdentifier($record->tableName, $tableName) && !self::sqlReferencesIdentifier($record->sql, $tableName)) {
                continue;
            }
            if (self::sqlReferencesIdentifier($record->sql, $column)) {
                $dependencies[] = $record->name;
            }
        }

        sort($dependencies, SORT_STRING | SORT_FLAG_CASE);

        return $dependencies;
    }

    /**
     * @param list<string> $values
     */
    private static function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                return str_replace('""', '"', $value);
            }
        }

        throw new InvalidArgumentException('SQLite identifier is missing');
    }

    /**
     * @param list<string> $identifiers
     */
    private static function containsIdentifier(array $identifiers, string $needle): bool
    {
        return self::identifierIndex($identifiers, $needle) !== null;
    }

    /**
     * @param list<string> $identifiers
     */
    private static function identifierIndex(array $identifiers, string $needle): ?int
    {
        foreach ($identifiers as $index => $identifier) {
            if (self::sameIdentifier($identifier, $needle)) {
                return $index;
            }
        }

        return null;
    }

    private static function sameIdentifier(string $left, string $right): bool
    {
        return strcasecmp($left, $right) === 0;
    }

    private static function sqlReferencesIdentifier(string $sql, string $identifier): bool
    {
        return (bool) preg_match('/(?<![A-Za-z0-9_])' . preg_quote($identifier, '/') . '(?![A-Za-z0-9_])/i', $sql);
    }

    private static function normalizeSql(string $sql): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $sql));
    }
}
