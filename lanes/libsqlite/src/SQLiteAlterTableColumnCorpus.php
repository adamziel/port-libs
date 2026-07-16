<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteAlterTableColumnCorpus
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @return array{status:string, table:string, column:string, column_count:int, sql:string, added:list<string>, dependencies:list<string>, current_row_count:int, checked_rows:int, generated:bool}
     */
    public static function addColumn(SQLiteSchemaRecord $table, string $alterSql, array $currentRows = []): array
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
        $scan = self::validateAddColumnCurrentRows($definition, $column, $currentRows);

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
            'current_row_count' => count($currentRows),
            'checked_rows' => $scan['checked_rows'],
            'generated' => $scan['generated'],
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
        if (preg_match('/(?:\bGENERATED\s+ALWAYS\b.+\bSTORED\b|\bAS\s*\(.+\)\s*STORED\b)/i', $tail)) {
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
     * @param list<array<string,mixed>> $rows
     * @return array{checked_rows:int, generated:bool}
     */
    private static function validateAddColumnCurrentRows(string $definition, string $column, array $rows): array
    {
        $tail = self::definitionTail($definition);
        $generatedExpression = self::generatedExpression($tail);
        $checkExpressions = self::checkExpressions($tail);
        $notNull = preg_match('/\bNOT\s+NULL\b/i', $tail) === 1;
        if ($rows === [] || ($generatedExpression === null && $checkExpressions === [] && !$notNull)) {
            return ['checked_rows' => 0, 'generated' => $generatedExpression !== null];
        }

        $defaultValue = $generatedExpression === null ? self::defaultValue($tail) : null;
        foreach ($rows as $rowIndex => $row) {
            $rowWithColumn = $row;
            $rowWithColumn[$column] = $generatedExpression === null
                ? $defaultValue
                : self::evaluateAddColumnExpression($generatedExpression, $rowWithColumn);

            if ($notNull && $rowWithColumn[$column] === null) {
                throw new InvalidArgumentException("SQLite ADD COLUMN {$column} NOT NULL constraint failed on existing row " . ($rowIndex + 1));
            }

            foreach ($checkExpressions as $checkExpression) {
                $result = self::evaluateAddColumnExpression($checkExpression, $rowWithColumn);
                if ($result === 0 || $result === false) {
                    throw new InvalidArgumentException("SQLite ADD COLUMN {$column} CHECK constraint failed on existing row " . ($rowIndex + 1));
                }
            }
        }

        return ['checked_rows' => count($rows), 'generated' => $generatedExpression !== null];
    }

    private static function generatedExpression(string $tail): ?string
    {
        if (preg_match('/\bAS\s*\(/i', $tail, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }
        $open = strpos($tail, '(', $matches[0][1]);
        if ($open === false) {
            return null;
        }
        $close = self::matchingParen($tail, $open);
        if ($close === null) {
            return null;
        }

        return trim(substr($tail, $open + 1, $close - $open - 1));
    }

    /**
     * @return list<string>
     */
    private static function checkExpressions(string $tail): array
    {
        $expressions = [];
        if (preg_match_all('/\bCHECK\s*\(/i', $tail, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }
        foreach ($matches[0] as $match) {
            $open = strpos($tail, '(', $match[1]);
            if ($open === false) {
                continue;
            }
            $close = self::matchingParen($tail, $open);
            if ($close === null) {
                continue;
            }
            $expressions[] = trim(substr($tail, $open + 1, $close - $open - 1));
        }

        return $expressions;
    }

    private static function matchingParen(string $sql, int $open): ?int
    {
        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        for ($i = $open; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if ($i + 1 < $length && $sql[$i + 1] === $quote) {
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
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function defaultValue(string $tail): mixed
    {
        if (preg_match('/\bDEFAULT\s+((?:\'(?:\'\'|[^\'])*\')|(?:"(?:""|[^"])*")|(?:[+-]?\d+(?:\.\d+)?)|NULL)(?![A-Za-z0-9_])/i', $tail, $matches) !== 1) {
            return null;
        }
        $value = $matches[1];
        if (strcasecmp($value, 'NULL') === 0) {
            return null;
        }
        if ($value[0] === "'" || $value[0] === '"') {
            return str_replace($value[0] . $value[0], $value[0], substr($value, 1, -1));
        }
        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function evaluateAddColumnExpression(string $expression, array $row): mixed
    {
        $expression = trim($expression);
        $parts = preg_split('/\s+AND\s+/i', $expression);
        if (is_array($parts) && count($parts) > 1) {
            foreach ($parts as $part) {
                $value = self::evaluateAddColumnExpression($part, $row);
                if ($value === 0 || $value === false) {
                    return 0;
                }
            }

            return 1;
        }
        if (preg_match('/^(.+?)\s+IS\s+(NOT\s+)?NULL$/i', $expression, $matches) === 1) {
            $value = self::evaluateAddColumnExpression($matches[1], $row);
            $isNull = $value === null;
            return ($matches[2] ?? '') === '' ? ($isNull ? 1 : 0) : (!$isNull ? 1 : 0);
        }
        if (preg_match('/^(.+?)\s*(<>|!=|>=|<=|=|>|<)\s*(.+)$/s', $expression, $matches) === 1) {
            $left = self::evaluateAddColumnExpression($matches[1], $row);
            $right = self::evaluateAddColumnExpression($matches[3], $row);
            if ($left === null || $right === null) {
                return null;
            }
            return match ($matches[2]) {
                '<>', '!=' => $left != $right ? 1 : 0,
                '=' => $left == $right ? 1 : 0,
                '>' => $left > $right ? 1 : 0,
                '<' => $left < $right ? 1 : 0,
                '>=' => $left >= $right ? 1 : 0,
                '<=' => $left <= $right ? 1 : 0,
            };
        }
        $concat = self::splitOperator($expression, '||');
        if ($concat !== null) {
            return (string) self::evaluateAddColumnExpression($concat[0], $row) . (string) self::evaluateAddColumnExpression($concat[1], $row);
        }
        if (preg_match('/^lower\s*\((.*)\)$/is', $expression, $matches) === 1) {
            return strtolower((string) self::evaluateAddColumnExpression($matches[1], $row));
        }
        if (preg_match('/^length\s*\((.*)\)$/is', $expression, $matches) === 1) {
            $value = self::evaluateAddColumnExpression($matches[1], $row);
            return $value === null ? null : strlen((string) $value);
        }
        if (preg_match('/^\((.*)\)$/s', $expression, $matches) === 1 && self::matchingParen($expression, 0) === strlen($expression) - 1) {
            return self::evaluateAddColumnExpression($matches[1], $row);
        }
        if (preg_match('/^\'((?:\'\'|[^\'])*)\'$/s', $expression, $matches) === 1) {
            return str_replace("''", "'", $matches[1]);
        }
        if (preg_match('/^[+-]?\d+(?:\.\d+)?$/', $expression) === 1) {
            return str_contains($expression, '.') ? (float) $expression : (int) $expression;
        }
        if (strcasecmp($expression, 'NULL') === 0) {
            return null;
        }

        $identifier = self::unquoteIdentifierExpression($expression);
        if ($identifier !== null) {
            foreach ($row as $name => $value) {
                if (strcasecmp((string) $name, $identifier) === 0) {
                    return $value;
                }
            }

            return null;
        }

        throw new InvalidArgumentException("SQLite ADD COLUMN expression is not supported: {$expression}");
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private static function splitOperator(string $expression, string $operator): ?array
    {
        $depth = 0;
        $quote = null;
        $length = strlen($expression);
        for ($i = 0; $i < $length - strlen($operator) + 1; $i++) {
            $char = $expression[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if ($i + 1 < $length && $expression[$i + 1] === $quote) {
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
            if ($depth === 0 && substr($expression, $i, strlen($operator)) === $operator) {
                return [substr($expression, 0, $i), substr($expression, $i + strlen($operator))];
            }
        }

        return null;
    }

    private static function unquoteIdentifierExpression(string $expression): ?string
    {
        $expression = trim($expression);
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expression) === 1) {
            return $expression;
        }
        if (preg_match('/^"((?:""|[^"])*)"$/s', $expression, $matches) === 1) {
            return str_replace('""', '"', $matches[1]);
        }
        if (preg_match('/^`([^`]*)`$/s', $expression, $matches) === 1) {
            return $matches[1];
        }
        if (preg_match('/^\[([^\]]*)\]$/s', $expression, $matches) === 1) {
            return $matches[1];
        }

        return null;
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
