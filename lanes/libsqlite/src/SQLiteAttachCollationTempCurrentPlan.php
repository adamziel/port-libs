<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteAttachCollationTempCurrentPlan
{
    /**
     * @param list<string> $registeredCollations
     * @return array{status:string,target:string,current_schema:string|null,current_table:string,search_order:list<string>,registered_collations:list<string>,indexes:list<array{name:string,schema:string,table:string,collations:list<string>,available:bool,missing_collations:list<string>,usable:bool>>,usable_indexes:list<string>,blocked_indexes:list<string>,missing_collations:list<string>,dependencies:list<string>}
     */
    public static function plan(SQLiteAttachedSchemaCatalog $catalog, string $tableName, array $registeredCollations = []): array
    {
        $registered = self::normalizeRegisteredCollations($registeredCollations);
        $resolved = $catalog->resolveTable($tableName);
        $target = self::splitQualifiedName($tableName);

        if ($resolved === null) {
            return [
                'status' => 'missing-table',
                'target' => $tableName,
                'current_schema' => null,
                'current_table' => $target['name'],
                'search_order' => $catalog->searchOrder(),
                'registered_collations' => array_keys($registered),
                'indexes' => [],
                'usable_indexes' => [],
                'blocked_indexes' => [],
                'missing_collations' => [],
                'dependencies' => ['sqlite-attach-collation-temp-current'],
            ];
        }

        $schemaName = $resolved['schema'];
        $currentTable = $resolved['record']->name;
        $indexes = [];
        $usable = [];
        $blocked = [];
        $missingAll = [];

        foreach ($catalog->schemaRecords($schemaName) as $record) {
            if ($record->type !== 'index' || strcasecmp($record->tableName, $currentTable) !== 0) {
                continue;
            }

            $collations = self::indexCollations($record);
            $missing = [];
            foreach ($collations as $collation) {
                if (!isset($registered[$collation])) {
                    $missing[] = $collation;
                    $missingAll[$collation] = true;
                }
            }

            $available = $missing === [];
            $row = [
                'name' => $record->name,
                'schema' => $schemaName,
                'table' => $record->tableName,
                'collations' => $collations,
                'available' => $available,
                'missing_collations' => $missing,
                'usable' => $available,
            ];
            $indexes[] = $row;
            if ($available) {
                $usable[] = $record->name;
            } else {
                $blocked[] = $record->name;
            }
        }

        return [
            'status' => $blocked === [] ? 'ok' : 'missing-collation',
            'target' => $tableName,
            'current_schema' => $schemaName,
            'current_table' => $currentTable,
            'search_order' => $catalog->searchOrder(),
            'registered_collations' => array_keys($registered),
            'indexes' => $indexes,
            'usable_indexes' => $usable,
            'blocked_indexes' => $blocked,
            'missing_collations' => array_keys($missingAll),
            'dependencies' => ['sqlite-attach-collation-temp-current'],
        ];
    }

    /**
     * @param list<array{table:string,collations?:list<string>,schema?:string}> $lookups
     * @param list<string> $registeredCollations
     * @return list<array<string,mixed>>
     */
    public static function batchPlan(SQLiteAttachedSchemaCatalog $catalog, array $lookups, array $registeredCollations = []): array
    {
        $plans = [];
        foreach ($lookups as $lookup) {
            if (!isset($lookup['table']) || !is_string($lookup['table']) || trim($lookup['table']) === '') {
                throw new InvalidArgumentException('SQLite ATTACH collation lookup needs a table name');
            }
            $registered = $lookup['collations'] ?? $registeredCollations;
            if (!is_array($registered)) {
                throw new InvalidArgumentException('SQLite ATTACH collation lookup collations must be a list');
            }
            $plans[] = self::plan($catalog, $lookup['table'], $registered);
        }

        return $plans;
    }

    /**
     * @param list<string> $registeredCollations
     * @return array<string,true>
     */
    private static function normalizeRegisteredCollations(array $registeredCollations): array
    {
        $registered = ['BINARY' => true, 'NOCASE' => true, 'RTRIM' => true];
        foreach ($registeredCollations as $collation) {
            if (!is_string($collation) || trim($collation) === '') {
                throw new InvalidArgumentException('SQLite registered collation names must be non-empty strings');
            }
            $registered[strtoupper(trim($collation))] = true;
        }

        return $registered;
    }

    /**
     * @return array{schema:string,name:string}
     */
    private static function splitQualifiedName(string $name): array
    {
        $parts = preg_split('/\s*\.\s*/', trim($name), 2);
        if ($parts === false || $parts === [] || trim($parts[0]) === '') {
            throw new InvalidArgumentException('SQLite table name cannot be empty');
        }

        return count($parts) === 1
            ? ['schema' => '', 'name' => self::unquoteIdentifier($parts[0])]
            : ['schema' => strtolower(self::unquoteIdentifier($parts[0])), 'name' => self::unquoteIdentifier($parts[1])];
    }

    /**
     * @return list<string>
     */
    private static function indexCollations(SQLiteSchemaRecord $record): array
    {
        if ($record->sql === null || trim($record->sql) === '') {
            return ['BINARY'];
        }

        if (!preg_match('/\bon\s+(?:(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s*\.)?(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s*\((?<terms>.*)\)\s*(?:where\b.*)?$/is', $record->sql, $matches)) {
            return ['BINARY'];
        }

        $collations = [];
        foreach (self::splitTopLevel($matches['terms'], ',') as $term) {
            if (preg_match('/\bcollate\s+(?<name>"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)/i', $term, $collationMatch) === 1) {
                $collations[] = strtoupper(self::unquoteIdentifier($collationMatch['name']));
                continue;
            }
            $collations[] = 'BINARY';
        }

        return $collations === [] ? ['BINARY'] : $collations;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $text, string $separator): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $quote = null;
        $length = strlen($text);
        for ($i = 0; $i < $length; ++$i) {
            $char = $text[$i];
            if ($quote !== null) {
                $current .= $char;
                if ($char === $quote) {
                    if ($i + 1 < $length && $text[$i + 1] === $quote) {
                        $current .= $text[++$i];
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                $current .= $char;
                continue;
            }
            if ($char === '[') {
                $quote = ']';
                $current .= $char;
                continue;
            }
            if ($char === '(') {
                ++$depth;
            } elseif ($char === ')' && $depth > 0) {
                --$depth;
            }
            if ($char === $separator && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
                continue;
            }
            $current .= $char;
        }
        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

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
        if (($first === '"' && $last === '"') || ($first === '`' && $last === '`') || ($first === "'" && $last === "'")) {
            return str_replace($first . $first, $first, substr($identifier, 1, -1));
        }
        if ($first === '[' && $last === ']') {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }
}
