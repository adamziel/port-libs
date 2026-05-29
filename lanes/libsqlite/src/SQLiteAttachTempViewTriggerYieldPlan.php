<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteAttachTempViewTriggerYieldPlan
{
    /**
     * @param array<string,mixed> $newRow
     * @param array<string,mixed>|null $oldRow
     * @return array{trigger:string,triggerSchema:string,target:string,targetSchema:string,operations:list<array<string,mixed>>,operationCount:int,writesBySchema:array<string,int>,readCount:int,when:?string,whenMatched:bool,status:string}
     */
    public static function yield(SQLiteAttachedSchemaCatalog $catalog, string $triggerName, array $newRow = [], ?array $oldRow = null): array
    {
        $resolved = SQLiteAttachTempViewTriggerResolution::resolve($catalog, $triggerName);
        if ($resolved['status'] !== 'resolved') {
            throw new InvalidArgumentException("SQLite trigger {$triggerName} cannot yield operations with unresolved OLD/NEW references");
        }

        $trigger = SQLiteAttachTempViewTriggerResolution::resolveTrigger($catalog, $triggerName);
        $sql = $trigger['record']->sql;
        if ($sql === null || trim($sql) === '') {
            throw new InvalidArgumentException('SQLite attached trigger yield requires CREATE TRIGGER SQL');
        }

        $when = self::whenExpression($sql);
        $whenMatched = $when === null || self::whenMatches($when, $newRow, $oldRow);
        if (!$whenMatched) {
            return [
                'trigger' => $resolved['trigger'],
                'triggerSchema' => $resolved['triggerSchema'],
                'target' => $resolved['target'],
                'targetSchema' => $resolved['targetSchema'],
                'operations' => [],
                'operationCount' => 0,
                'writesBySchema' => [],
                'readCount' => 0,
                'when' => $when,
                'whenMatched' => false,
                'status' => 'skipped',
            ];
        }

        $operations = [];
        foreach (self::bodyStatements($sql) as $statement) {
            $operations[] = self::operation($catalog, $statement, $trigger['schema'], $resolved['triggerTemporary'], $newRow, $oldRow);
        }

        $writesBySchema = [];
        $readCount = 0;
        foreach ($operations as $operation) {
            if (($operation['kind'] ?? '') === 'select') {
                ++$readCount;
                continue;
            }
            $schema = (string) $operation['schema'];
            $writesBySchema[$schema] = ($writesBySchema[$schema] ?? 0) + 1;
        }
        ksort($writesBySchema);

        return [
            'trigger' => $resolved['trigger'],
            'triggerSchema' => $resolved['triggerSchema'],
            'target' => $resolved['target'],
            'targetSchema' => $resolved['targetSchema'],
            'operations' => $operations,
            'operationCount' => count($operations),
            'writesBySchema' => $writesBySchema,
            'readCount' => $readCount,
            'when' => $when,
            'whenMatched' => true,
            'status' => 'yielded',
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function operations(SQLiteAttachedSchemaCatalog $catalog, string $triggerName, array $newRow = [], ?array $oldRow = null): array
    {
        return self::yield($catalog, $triggerName, $newRow, $oldRow)['operations'];
    }

    /**
     * @return list<string>
     */
    private static function bodyStatements(string $sql): array
    {
        if (!preg_match('/\bbegin\b(?<body>.*)\bend\b/is', $sql, $matches)) {
            throw new InvalidArgumentException('SQLite trigger yield requires a BEGIN...END body');
        }

        return array_values(array_filter(array_map('trim', self::splitStatements($matches['body'])), static fn (string $statement): bool => $statement !== ''));
    }

    private static function whenExpression(string $sql): ?string
    {
        if (!preg_match('/\bwhen\b(?<when>.*?)\bbegin\b/is', $sql, $matches)) {
            return null;
        }

        $when = trim($matches['when']);
        return $when === '' ? null : $when;
    }

    private static function whenMatches(string $when, array $newRow, ?array $oldRow): bool
    {
        $orTerms = preg_split('/\s+or\s+/i', $when);
        if ($orTerms === false || $orTerms === []) {
            throw new InvalidArgumentException('SQLite trigger WHEN clause cannot be empty');
        }

        foreach ($orTerms as $orTerm) {
            $andTerms = preg_split('/\s+and\s+/i', trim($orTerm));
            if ($andTerms === false || $andTerms === []) {
                throw new InvalidArgumentException('SQLite trigger WHEN clause cannot be empty');
            }

            $matched = true;
            foreach ($andTerms as $andTerm) {
                $term = trim($andTerm);
                if ($term === '') {
                    throw new InvalidArgumentException('SQLite trigger WHEN clause cannot be empty');
                }
                if (!self::whenTermMatches($term, $newRow, $oldRow)) {
                    $matched = false;
                    break;
                }
            }

            if ($matched) {
                return true;
            }
        }

        return false;
    }

    private static function whenTermMatches(string $term, array $newRow, ?array $oldRow): bool
    {
        if (preg_match('/^(?<left>.+?)\s+(?<operator>is\s+not|is)\s+(?<right>.+)$/is', $term, $matches)) {
            $left = self::value($matches['left'], $newRow, $oldRow);
            $right = self::value($matches['right'], $newRow, $oldRow);

            return strtolower((string) preg_replace('/\s+/', ' ', $matches['operator'])) === 'is not'
                ? !self::sqliteIs($left, $right)
                : self::sqliteIs($left, $right);
        }

        if (preg_match('/^(?<left>.+?)\s*(?<operator><>|!=|=)\s*(?<right>.+)$/s', $term, $matches)) {
            $left = self::value($matches['left'], $newRow, $oldRow);
            $right = self::value($matches['right'], $newRow, $oldRow);
            if ($left === null || $right === null) {
                return false;
            }
            $equal = self::sqliteEquals($left, $right);

            return $matches['operator'] === '=' ? $equal : !$equal;
        }

        return self::sqliteTruthy(self::value($term, $newRow, $oldRow));
    }

    private static function sqliteIs(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $left === null && $right === null;
        }

        return self::sqliteEquals($left, $right);
    }

    private static function sqliteEquals(mixed $left, mixed $right): bool
    {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left == $right;
        }

        return (string) $left === (string) $right;
    }

    private static function sqliteTruthy(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value != 0.0;
        }

        return $value !== '';
    }

    /**
     * @return list<string>
     */
    private static function splitStatements(string $body): array
    {
        $statements = [];
        $current = '';
        $quote = null;
        $length = strlen($body);
        for ($i = 0; $i < $length; ++$i) {
            $char = $body[$i];
            if ($quote !== null) {
                $current .= $char;
                if ($char === $quote) {
                    if ($i + 1 < $length && $body[$i + 1] === $quote) {
                        $current .= $body[++$i];
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === '\'' || $char === '"' || $char === '`') {
                $quote = $char;
                $current .= $char;
                continue;
            }
            if ($char === ';') {
                $statements[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $statements[] = $current;

        return $statements;
    }

    /**
     * @return array<string,mixed>
     */
    private static function operation(SQLiteAttachedSchemaCatalog $catalog, string $statement, string $triggerSchema, bool $tempTrigger, array $newRow, ?array $oldRow): array
    {
        if (preg_match('/^insert\s+into\s+(?:(?<schema>["`\[]?[\w-]+["`\]]?)\s*\.\s*)?(?<table>["`\[]?[\w-]+["`\]]?)\s*\((?<columns>[^)]*)\)\s*values\s*\((?<values>.*)\)$/is', $statement, $matches)) {
            $target = self::resolveBodyTable($catalog, self::nameParts($matches), $triggerSchema, $tempTrigger);
            $columns = self::identifierList($matches['columns']);
            $values = self::splitCommaList($matches['values']);
            if (count($columns) !== count($values)) {
                throw new InvalidArgumentException('SQLite trigger INSERT yield column/value count mismatch');
            }
            $row = [];
            foreach ($columns as $index => $column) {
                $row[$column] = self::value($values[$index], $newRow, $oldRow);
            }

            return [
                'kind' => 'insert',
                'schema' => $target['schema'],
                'table' => $target['record']->name,
                'columns' => $columns,
                'row' => $row,
                'source' => $statement,
            ];
        }

        if (preg_match('/^update\s+(?:(?<schema>["`\[]?[\w-]+["`\]]?)\s*\.\s*)?(?<table>["`\[]?[\w-]+["`\]]?)\s+set\s+(?<set>.*?)\s+where\s+(?<where>.*)$/is', $statement, $matches)) {
            $target = self::resolveBodyTable($catalog, self::nameParts($matches), $triggerSchema, $tempTrigger);

            return [
                'kind' => 'update',
                'schema' => $target['schema'],
                'table' => $target['record']->name,
                'set' => self::assignments($matches['set'], $newRow, $oldRow),
                'where' => self::predicate($matches['where'], $newRow, $oldRow),
                'source' => $statement,
            ];
        }

        if (preg_match('/^delete\s+from\s+(?:(?<schema>["`\[]?[\w-]+["`\]]?)\s*\.\s*)?(?<table>["`\[]?[\w-]+["`\]]?)\s+where\s+(?<where>.*)$/is', $statement, $matches)) {
            $target = self::resolveBodyTable($catalog, self::nameParts($matches), $triggerSchema, $tempTrigger);

            return [
                'kind' => 'delete',
                'schema' => $target['schema'],
                'table' => $target['record']->name,
                'where' => self::predicate($matches['where'], $newRow, $oldRow),
                'source' => $statement,
            ];
        }

        if (preg_match('/^select\s+(?<values>.*?)\s+from\s+(?:(?<schema>["`\[]?[\w-]+["`\]]?)\s*\.\s*)?(?<table>["`\[]?[\w-]+["`\]]?)(?:\s+where\s+(?<where>.*))?$/is', $statement, $matches)) {
            $target = self::resolveBodyTable($catalog, self::nameParts($matches), $triggerSchema, $tempTrigger);
            $operation = [
                'kind' => 'select',
                'schema' => $target['schema'],
                'table' => $target['record']->name,
                'values' => array_map(static fn (string $value): mixed => self::value($value, $newRow, $oldRow), self::splitCommaList($matches['values'])),
                'source' => $statement,
            ];
            if (isset($matches['where']) && trim($matches['where']) !== '') {
                $operation['where'] = self::predicate($matches['where'], $newRow, $oldRow);
            }

            return $operation;
        }

        if (preg_match('/^select\s+(?<values>.*)$/is', $statement, $matches)) {
            return [
                'kind' => 'select',
                'schema' => $triggerSchema,
                'values' => array_map(static fn (string $value): mixed => self::value($value, $newRow, $oldRow), self::splitCommaList($matches['values'])),
                'source' => $statement,
            ];
        }

        throw new InvalidArgumentException('SQLite trigger yield only supports bounded INSERT, UPDATE, DELETE, and SELECT body statements');
    }

    /**
     * @param array<string,string> $matches
     * @return array{schema:?string,name:string}
     */
    private static function nameParts(array $matches): array
    {
        return [
            'schema' => isset($matches['schema']) && $matches['schema'] !== '' ? strtolower(self::unquoteIdentifier($matches['schema'])) : null,
            'name' => self::unquoteIdentifier($matches['table']),
        ];
    }

    /**
     * @param array{schema:?string,name:string} $name
     * @return array{schema:string,record:SQLiteSchemaRecord}
     */
    private static function resolveBodyTable(SQLiteAttachedSchemaCatalog $catalog, array $name, string $triggerSchema, bool $tempTrigger): array
    {
        $schemaAlias = self::resolveSchemaAliasBodyTable($catalog, $name);
        if ($schemaAlias !== null) {
            return $schemaAlias;
        }

        $schemas = $name['schema'] !== null ? [$name['schema']] : ($tempTrigger ? $catalog->searchOrder() : [$triggerSchema]);
        foreach ($schemas as $schema) {
            foreach ($catalog->schemaRecords($schema) as $record) {
                if (strtolower($record->type) === 'table' && strcasecmp($record->name, $name['name']) === 0) {
                    return ['schema' => $schema, 'record' => $record];
                }
            }
        }

        throw new InvalidArgumentException("SQLite trigger body table does not resolve: {$name['name']}");
    }

    /**
     * @param array{schema:?string,name:string} $name
     * @return array{schema:string,record:SQLiteSchemaRecord}|null
     */
    private static function resolveSchemaAliasBodyTable(SQLiteAttachedSchemaCatalog $catalog, array $name): ?array
    {
        $table = strtolower($name['name']);
        $schema = $name['schema'];

        if ($schema === null && ($table === 'sqlite_temp_schema' || $table === 'sqlite_temp_master')) {
            return $catalog->resolveTable($table);
        }

        if ($schema !== null && ($table === 'sqlite_schema' || $table === 'sqlite_master')) {
            return $catalog->resolveTable($schema . '.' . $table);
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private static function assignments(string $set, array $newRow, ?array $oldRow): array
    {
        $assignments = [];
        foreach (self::splitCommaList($set) as $assignment) {
            if (!preg_match('/^(?<column>["`\[]?[\w-]+["`\]]?)\s*=\s*(?<value>.+)$/is', trim($assignment), $matches)) {
                throw new InvalidArgumentException('SQLite trigger UPDATE yield requires simple assignments');
            }
            $assignments[self::unquoteIdentifier($matches['column'])] = self::value($matches['value'], $newRow, $oldRow);
        }

        return $assignments;
    }

    /**
     * @return array{column:string,operator:string,value:mixed}
     */
    private static function predicate(string $where, array $newRow, ?array $oldRow): array
    {
        if (!preg_match('/^(?<column>["`\[]?[\w-]+["`\]]?)\s*(?<operator>=|is)\s*(?<value>.+)$/is', trim($where), $matches)) {
            throw new InvalidArgumentException('SQLite trigger yield requires a simple equality WHERE predicate');
        }

        return [
            'column' => self::unquoteIdentifier($matches['column']),
            'operator' => strtolower($matches['operator']) === 'is' ? 'IS' : '=',
            'value' => self::value($matches['value'], $newRow, $oldRow),
        ];
    }

    private static function value(string $expression, array $newRow, ?array $oldRow): mixed
    {
        $expression = trim($expression);
        if (preg_match('/^new\s*\.\s*(?<column>["`\[]?[\w-]+["`\]]?)$/i', $expression, $matches)) {
            $column = self::unquoteIdentifier($matches['column']);
            if (!array_key_exists($column, $newRow)) {
                throw new InvalidArgumentException("SQLite trigger NEW column {$column} is missing from yielded row");
            }

            return $newRow[$column];
        }
        if (preg_match('/^old\s*\.\s*(?<column>["`\[]?[\w-]+["`\]]?)$/i', $expression, $matches)) {
            if ($oldRow === null) {
                throw new InvalidArgumentException('SQLite trigger OLD row is unavailable for yielded operation');
            }
            $column = self::unquoteIdentifier($matches['column']);
            if (!array_key_exists($column, $oldRow)) {
                throw new InvalidArgumentException("SQLite trigger OLD column {$column} is missing from yielded row");
            }

            return $oldRow[$column];
        }
        if (preg_match('/^\'((?:\'\'|[^\'])*)\'$/s', $expression, $matches)) {
            return str_replace("''", "'", $matches[1]);
        }
        if (preg_match('/^-?\d+$/', $expression)) {
            return (int) $expression;
        }
        if (strcasecmp($expression, 'null') === 0) {
            return null;
        }

        return self::unquoteIdentifier($expression);
    }

    /**
     * @return list<string>
     */
    private static function identifierList(string $value): array
    {
        return array_values(array_filter(array_map(static fn (string $part): string => self::unquoteIdentifier(trim($part)), self::splitCommaList($value)), static fn (string $part): bool => $part !== ''));
    }

    /**
     * @return list<string>
     */
    private static function splitCommaList(string $value): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $quote = null;
        $length = strlen($value);
        for ($i = 0; $i < $length; ++$i) {
            $char = $value[$i];
            if ($quote !== null) {
                $current .= $char;
                if ($char === $quote) {
                    if ($i + 1 < $length && $value[$i + 1] === $quote) {
                        $current .= $value[++$i];
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === '\'' || $char === '"' || $char === '`') {
                $quote = $char;
                $current .= $char;
                continue;
            }
            if ($char === '(') {
                ++$depth;
                $current .= $char;
                continue;
            }
            if ($char === ')') {
                $depth = max(0, $depth - 1);
                $current .= $char;
                continue;
            }
            if ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $parts[] = $current;

        return $parts;
    }

    private static function unquoteIdentifier(string $identifier): string
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
}
