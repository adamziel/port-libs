<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteAttachTempViewCollationPlan
{
    /**
     * @return array{trigger:string,triggerSchema:string,target:string,targetSchema:string,targetType:string,targetCollations:array<string,string>,body:list<array<string,mixed>>,bodyCollationsBySchema:array<string,array<string,array<string,string>>>,selectCollations:list<array<string,string>>,status:string}
     */
    public static function forTrigger(SQLiteAttachedSchemaCatalog $catalog, string $triggerName): array
    {
        $resolved = SQLiteAttachTempViewTriggerResolution::resolve($catalog, $triggerName);
        $trigger = SQLiteAttachTempViewTriggerResolution::resolveTrigger($catalog, $triggerName);
        $sql = $trigger['record']->sql;
        if ($sql === null || trim($sql) === '') {
            throw new InvalidArgumentException('SQLite attach/temp view collation planning requires CREATE TRIGGER SQL');
        }

        $targetRecord = self::recordInSchema($catalog, $resolved['targetSchema'], $resolved['target']);
        $targetCollations = self::recordCollations($catalog, $targetRecord, $resolved['targetSchema']);
        $body = [];
        $bodyCollationsBySchema = [];
        $selectCollations = [];

        foreach (self::bodyStatements($sql) as $statement) {
            $operation = self::bodyOperation($catalog, $statement, $trigger['schema'], $resolved['triggerTemporary']);
            $body[] = $operation;
            if (isset($operation['schema'], $operation['table'], $operation['collations']) && $operation['kind'] !== 'select') {
                $schema = (string) $operation['schema'];
                $table = (string) $operation['table'];
                /** @var array<string,string> $collations */
                $collations = $operation['collations'];
                $bodyCollationsBySchema[$schema][$table] = $collations;
            }
            if (($operation['kind'] ?? '') === 'select') {
                /** @var list<array<string,string>> $collations */
                $collations = $operation['collations'];
                foreach ($collations as $collation) {
                    $selectCollations[] = $collation;
                }
            }
        }

        foreach ($bodyCollationsBySchema as &$tables) {
            ksort($tables);
        }
        unset($tables);
        ksort($bodyCollationsBySchema);

        return [
            'trigger' => $resolved['trigger'],
            'triggerSchema' => $resolved['triggerSchema'],
            'target' => $resolved['target'],
            'targetSchema' => $resolved['targetSchema'],
            'targetType' => $resolved['targetType'],
            'targetCollations' => $targetCollations,
            'body' => $body,
            'bodyCollationsBySchema' => $bodyCollationsBySchema,
            'selectCollations' => $selectCollations,
            'status' => $resolved['status'] === 'resolved' ? 'planned' : 'unresolved',
        ];
    }

    /**
     * @return array<string,array{triggers:int,targetCollations:array<string,int>,bodyCollations:array<string,int>,selectCollations:array<string,int>,unresolved:int}>
     */
    public static function summary(SQLiteAttachedSchemaCatalog $catalog): array
    {
        $summary = [];
        foreach ($catalog->searchOrder() as $schema) {
            $summary[$schema] = [
                'triggers' => 0,
                'targetCollations' => [],
                'bodyCollations' => [],
                'selectCollations' => [],
                'unresolved' => 0,
            ];
        }

        foreach ($catalog->searchOrder() as $schema) {
            foreach ($catalog->schemaRecords($schema) as $record) {
                if (strtolower($record->type) !== 'trigger') {
                    continue;
                }
                $plan = self::forTrigger($catalog, $schema . '.' . $record->name);
                ++$summary[$plan['triggerSchema']]['triggers'];
                if ($plan['status'] !== 'planned') {
                    ++$summary[$plan['triggerSchema']]['unresolved'];
                }
                self::addCollationCounts($summary[$plan['triggerSchema']]['targetCollations'], array_values($plan['targetCollations']));
                foreach ($plan['bodyCollationsBySchema'] as $tables) {
                    foreach ($tables as $collations) {
                        self::addCollationCounts($summary[$plan['triggerSchema']]['bodyCollations'], array_values($collations));
                    }
                }
                foreach ($plan['selectCollations'] as $select) {
                    self::addCollationCounts($summary[$plan['triggerSchema']]['selectCollations'], [$select['collation']]);
                }
            }
        }

        foreach ($summary as &$row) {
            ksort($row['targetCollations']);
            ksort($row['bodyCollations']);
            ksort($row['selectCollations']);
        }
        unset($row);

        return $summary;
    }

    /**
     * @param array<string,int> $counts
     * @param list<string> $collations
     */
    private static function addCollationCounts(array &$counts, array $collations): void
    {
        foreach ($collations as $collation) {
            $normalized = strtoupper($collation);
            $counts[$normalized] = ($counts[$normalized] ?? 0) + 1;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function bodyOperation(SQLiteAttachedSchemaCatalog $catalog, string $statement, string $triggerSchema, bool $tempTrigger): array
    {
        if (preg_match('/\Ainsert\s+into\s+(?:(?<schema>["`\[]?[\w-]+["`\]]?)\s*\.\s*)?(?<table>["`\[]?[\w-]+["`\]]?)\s*\((?<columns>[^)]*)\)\s*values\s*\((?<values>.*)\)$/is', $statement, $matches)) {
            $target = self::resolveBodyTable($catalog, self::nameParts($matches), $triggerSchema, $tempTrigger);
            $columns = self::identifierList($matches['columns']);

            return [
                'kind' => 'insert',
                'schema' => $target['schema'],
                'table' => $target['record']->name,
                'columns' => $columns,
                'collations' => self::filterCollations(self::recordCollations($catalog, $target['record'], $target['schema']), $columns),
            ];
        }

        if (preg_match('/^update\s+(?:(?<schema>["`\[]?[\w-]+["`\]]?)\s*\.\s*)?(?<table>["`\[]?[\w-]+["`\]]?)\s+set\s+(?<set>.*?)\s+where\s+(?<where>.*)$/is', $statement, $matches)) {
            $target = self::resolveBodyTable($catalog, self::nameParts($matches), $triggerSchema, $tempTrigger);
            $columns = [];
            foreach (self::splitCommaList($matches['set']) as $assignment) {
                if (preg_match('/^(?<column>["`\[]?[\w-]+["`\]]?)\s*=/is', trim($assignment), $set)) {
                    $columns[] = self::unquoteIdentifier($set['column']);
                }
            }

            return [
                'kind' => 'update',
                'schema' => $target['schema'],
                'table' => $target['record']->name,
                'columns' => $columns,
                'collations' => self::filterCollations(self::recordCollations($catalog, $target['record'], $target['schema']), $columns),
                'whereCollations' => self::expressionCollations($matches['where']),
            ];
        }

        if (preg_match('/^delete\s+from\s+(?:(?<schema>["`\[]?[\w-]+["`\]]?)\s*\.\s*)?(?<table>["`\[]?[\w-]+["`\]]?)\s+where\s+(?<where>.*)$/is', $statement, $matches)) {
            $target = self::resolveBodyTable($catalog, self::nameParts($matches), $triggerSchema, $tempTrigger);

            return [
                'kind' => 'delete',
                'schema' => $target['schema'],
                'table' => $target['record']->name,
                'collations' => self::recordCollations($catalog, $target['record'], $target['schema']),
                'whereCollations' => self::expressionCollations($matches['where']),
            ];
        }

        if (preg_match('/^select\s+(?<values>.*)$/is', $statement, $matches)) {
            return [
                'kind' => 'select',
                'schema' => $triggerSchema,
                'collations' => self::selectExpressionCollations($matches['values']),
            ];
        }

        throw new InvalidArgumentException('SQLite attach/temp view collation planning only supports bounded INSERT, UPDATE, DELETE, and SELECT body statements');
    }

    /**
     * @param array<string,string> $collations
     * @param list<string> $columns
     * @return array<string,string>
     */
    private static function filterCollations(array $collations, array $columns): array
    {
        $wanted = array_fill_keys(array_map('strtolower', $columns), true);
        $filtered = [];
        foreach ($collations as $column => $collation) {
            if (isset($wanted[strtolower($column)])) {
                $filtered[$column] = $collation;
            }
        }

        return $filtered;
    }

    /**
     * @return array<string,string>
     */
    private static function recordCollations(SQLiteAttachedSchemaCatalog $catalog, SQLiteSchemaRecord $record, string $schema): array
    {
        if ($record->sql === null) {
            return [];
        }
        if (strtolower($record->type) === 'table') {
            return self::tableCollations($record->sql);
        }
        if (strtolower($record->type) !== 'view') {
            return [];
        }

        $columns = self::viewColumns($record->sql);
        $selectCollations = self::viewSelectCollations($catalog, $record->sql, $schema);
        if ($columns === []) {
            $collations = [];
            foreach ($selectCollations as $entry) {
                $collations[$entry['name']] = $entry['collation'];
            }

            return $collations;
        }

        $collations = [];
        foreach ($columns as $index => $column) {
            $collations[$column] = $selectCollations[$index]['collation'] ?? 'BINARY';
        }

        return $collations;
    }

    /**
     * @return array<string,string>
     */
    private static function tableCollations(string $sql): array
    {
        if (!preg_match('/\bcreate\s+(?:temp(?:orary)?\s+)?table\s+(?:if\s+not\s+exists\s+)?(?:["`\[]?[\w-]+["`\]]?\s*\.\s*)?["`\[]?[\w-]+["`\]]?\s*\((?<columns>.*)\)/is', $sql, $matches)) {
            return [];
        }

        $collations = [];
        foreach (self::splitCommaList($matches['columns']) as $definition) {
            $trimmed = ltrim($definition);
            if ($trimmed === '' || preg_match('/^(?:constraint|primary|foreign|unique|check)\b/i', $trimmed)) {
                continue;
            }
            if (!preg_match('/^(?<column>"[^"]+"|`[^`]+`|\[[^\]]+\]|[\w-]+)/', $trimmed, $column)) {
                continue;
            }
            $collation = 'BINARY';
            if (preg_match('/\bcollate\s+(?<collation>"[^"]+"|`[^`]+`|\[[^\]]+\]|[\w-]+)/i', $trimmed, $match)) {
                $collation = strtoupper(self::unquoteIdentifier($match['collation']));
            }
            $collations[self::unquoteIdentifier($column['column'])] = $collation;
        }

        return $collations;
    }

    /**
     * @return list<string>
     */
    private static function viewColumns(string $sql): array
    {
        if (!preg_match('/\bcreate\s+(?:temp(?:orary)?\s+)?view\s+(?:if\s+not\s+exists\s+)?(?:["`\[]?[\w-]+["`\]]?\s*\.\s*)?["`\[]?[\w-]+["`\]]?\s*\((?<columns>[^)]*)\)/i', $sql, $matches)) {
            return [];
        }

        return self::identifierList($matches['columns']);
    }

    /**
     * @return list<array{name:string,collation:string}>
     */
    private static function viewSelectCollations(SQLiteAttachedSchemaCatalog $catalog, string $sql, string $schema): array
    {
        if (!preg_match('/\bas\s+select\s+(?<select>.*?)\s+\bfrom\s+(?:(?<sourceSchema>["`\[]?[\w-]+["`\]]?)\s*\.\s*)?(?<source>["`\[]?[\w-]+["`\]]?)/is', $sql, $matches)) {
            return [];
        }

        $sourceSchema = isset($matches['sourceSchema']) && $matches['sourceSchema'] !== ''
            ? strtolower(self::unquoteIdentifier($matches['sourceSchema']))
            : $schema;
        $source = self::recordInSchema($catalog, $sourceSchema, self::unquoteIdentifier($matches['source']));
        $sourceCollations = strtolower($source->type) === 'table' ? self::tableCollations((string) $source->sql) : [];

        $entries = [];
        foreach (self::splitCommaList($matches['select']) as $expression) {
            $name = self::selectOutputName($expression);
            $collation = self::selectTermCollation($expression, $sourceCollations);
            $entries[] = ['name' => $name, 'collation' => $collation];
        }

        return $entries;
    }

    /**
     * @return list<array{expression:string,collation:string}>
     */
    private static function selectExpressionCollations(string $select): array
    {
        $collations = [];
        foreach (self::splitCommaList($select) as $expression) {
            $collations[] = [
                'expression' => trim($expression),
                'collation' => self::selectTermCollation($expression, []),
            ];
        }

        return $collations;
    }

    /**
     * @return list<array{expression:string,collation:string}>
     */
    private static function expressionCollations(string $expression): array
    {
        return [[
            'expression' => trim($expression),
            'collation' => self::selectTermCollation($expression, []),
        ]];
    }

    /**
     * @param array<string,string> $sourceCollations
     */
    private static function selectTermCollation(string $expression, array $sourceCollations): string
    {
        if (preg_match_all('/\bcollate\s+(?<collation>"[^"]+"|`[^`]+`|\[[^\]]+\]|[\w-]+)/i', $expression, $matches) && ($matches['collation'] ?? []) !== []) {
            return strtoupper(self::unquoteIdentifier((string) $matches['collation'][0]));
        }
        if (preg_match('/(?:^|\.)(?<column>"[^"]+"|`[^`]+`|\[[^\]]+\]|[\w-]+)(?:\s+as\s+["`\[]?[\w-]+["`\]]?)?$/i', trim($expression), $matches)) {
            $column = self::unquoteIdentifier($matches['column']);
            foreach ($sourceCollations as $sourceColumn => $collation) {
                if (strcasecmp($sourceColumn, $column) === 0) {
                    return $collation;
                }
            }
        }

        return 'BINARY';
    }

    private static function selectOutputName(string $expression): string
    {
        $expression = trim($expression);
        if (preg_match('/\bas\s+(?<alias>"[^"]+"|`[^`]+`|\[[^\]]+\]|[\w-]+)$/i', $expression, $alias)) {
            return self::unquoteIdentifier($alias['alias']);
        }
        if (preg_match('/(?:^|\.)(?<column>"[^"]+"|`[^`]+`|\[[^\]]+\]|[\w-]+)(?:\s+collate\s+["`\[]?[\w-]+["`\]]?)?$/i', $expression, $name)) {
            return self::unquoteIdentifier($name['column']);
        }

        return trim(preg_replace('/\s+/', ' ', $expression) ?? $expression);
    }

    private static function recordInSchema(SQLiteAttachedSchemaCatalog $catalog, string $schema, string $name): SQLiteSchemaRecord
    {
        foreach ($catalog->schemaRecords($schema) as $record) {
            if (strcasecmp($record->name, $name) === 0) {
                return $record;
            }
        }

        throw new InvalidArgumentException("SQLite schema record does not exist: {$schema}.{$name}");
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
     * @return list<string>
     */
    private static function identifierList(string $value): array
    {
        return array_values(array_filter(array_map(static fn (string $part): string => self::unquoteIdentifier(trim($part)), self::splitCommaList($value)), static fn (string $part): bool => $part !== ''));
    }

    /**
     * @return list<string>
     */
    private static function bodyStatements(string $sql): array
    {
        if (!preg_match('/\bbegin\b(?<body>.*)\bend\b/is', $sql, $matches)) {
            throw new InvalidArgumentException('SQLite attach/temp view collation planning requires a BEGIN...END body');
        }

        return array_values(array_filter(array_map('trim', self::splitStatements($matches['body'])), static fn (string $statement): bool => $statement !== ''));
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
