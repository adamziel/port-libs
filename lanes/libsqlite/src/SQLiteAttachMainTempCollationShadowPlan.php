<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteAttachMainTempCollationShadowPlan
{
    /**
     * @param list<string> $registeredCollations
     * @return array{status:string,target:string,current_schema:string|null,current_table:string,qualified:bool,search_order:list<string>,registered_collations:list<string>,current_indexes:list<array{name:string,schema:string,table:string,collations:list<string>,missing_collations:list<string>,usable:bool}>,shadowed_tables:list<array{schema:string,name:string,type:string,root_page:int|null,indexes:list<string>}>,shadowed_indexes:list<array{name:string,schema:string,table:string,collations:list<string>,missing_collations:list<string>,usable:bool}>,usable_current_indexes:list<string>,blocked_current_indexes:list<string>,blocked_shadowed_usable_indexes:list<string>,missing_collations:list<string>,dependencies:list<string>}
     */
    public static function plan(SQLiteAttachedSchemaCatalog $catalog, string $tableName, array $registeredCollations = []): array
    {
        $target = self::splitQualifiedName($tableName);
        $registered = self::normalizeRegisteredCollations($registeredCollations);
        $resolved = $catalog->resolveTable($tableName);
        $searchOrder = $catalog->searchOrder();

        if ($resolved === null) {
            return [
                'status' => 'missing-table',
                'target' => $tableName,
                'current_schema' => null,
                'current_table' => $target['name'],
                'qualified' => $target['schema'] !== '',
                'search_order' => $searchOrder,
                'registered_collations' => array_keys($registered),
                'current_indexes' => [],
                'shadowed_tables' => [],
                'shadowed_indexes' => [],
                'usable_current_indexes' => [],
                'blocked_current_indexes' => [],
                'blocked_shadowed_usable_indexes' => [],
                'missing_collations' => [],
                'dependencies' => ['sqlite-attach-main-temp-collation-shadow-current'],
            ];
        }

        $currentSchema = $resolved['schema'];
        $currentTable = $resolved['record']->name;
        $currentIndexes = self::indexesFor($catalog, $currentSchema, $currentTable, $registered);
        $usableCurrent = [];
        $blockedCurrent = [];
        $missing = [];
        foreach ($currentIndexes as $index) {
            if ($index['usable']) {
                $usableCurrent[] = $index['name'];
            } else {
                $blockedCurrent[] = $index['name'];
                foreach ($index['missing_collations'] as $collation) {
                    $missing[$collation] = true;
                }
            }
        }

        $shadowedTables = [];
        $shadowedIndexes = [];
        $blockedShadowedUsable = [];
        if ($target['schema'] === '') {
            $currentPosition = array_search($currentSchema, $searchOrder, true);
            foreach ($searchOrder as $position => $schema) {
                if ($position <= $currentPosition) {
                    continue;
                }
                $candidate = self::tableInSchema($catalog, $schema, $currentTable);
                if ($candidate === null) {
                    continue;
                }
                $candidateIndexes = self::indexesFor($catalog, $schema, $candidate->name, $registered);
                $shadowedTables[] = [
                    'schema' => $schema,
                    'name' => $candidate->name,
                    'type' => strtolower($candidate->type),
                    'root_page' => $candidate->rootPage,
                    'indexes' => array_column($candidateIndexes, 'name'),
                ];
                foreach ($candidateIndexes as $index) {
                    $shadowedIndexes[] = $index;
                    if ($index['usable'] && $blockedCurrent !== []) {
                        $blockedShadowedUsable[] = $schema . '.' . $index['name'];
                    }
                }
            }
        }

        $status = 'ok';
        if ($blockedCurrent !== [] && $blockedShadowedUsable !== []) {
            $status = 'current-source-collation-blocked-by-shadow';
        } elseif ($blockedCurrent !== []) {
            $status = 'missing-collation';
        }

        return [
            'status' => $status,
            'target' => $tableName,
            'current_schema' => $currentSchema,
            'current_table' => $currentTable,
            'qualified' => $target['schema'] !== '',
            'search_order' => $searchOrder,
            'registered_collations' => array_keys($registered),
            'current_indexes' => $currentIndexes,
            'shadowed_tables' => $shadowedTables,
            'shadowed_indexes' => $shadowedIndexes,
            'usable_current_indexes' => $usableCurrent,
            'blocked_current_indexes' => $blockedCurrent,
            'blocked_shadowed_usable_indexes' => $blockedShadowedUsable,
            'missing_collations' => array_keys($missing),
            'dependencies' => ['sqlite-attach-main-temp-collation-shadow-current'],
        ];
    }

    /**
     * @param list<array{table:string,collations?:list<string>}> $lookups
     * @param list<string> $registeredCollations
     * @return list<array<string,mixed>>
     */
    public static function batchPlan(SQLiteAttachedSchemaCatalog $catalog, array $lookups, array $registeredCollations = []): array
    {
        $plans = [];
        foreach ($lookups as $lookup) {
            if (!isset($lookup['table']) || !is_string($lookup['table']) || trim($lookup['table']) === '') {
                throw new InvalidArgumentException('SQLite ATTACH shadow lookup needs a table name');
            }
            $collations = $lookup['collations'] ?? $registeredCollations;
            if (!is_array($collations)) {
                throw new InvalidArgumentException('SQLite ATTACH shadow lookup collations must be a list');
            }
            $plans[] = self::plan($catalog, $lookup['table'], $collations);
        }

        return $plans;
    }

    /**
     * @param array{status:string,shadowed_tables:list<array{schema:string,name:string}>,blocked_shadowed_usable_indexes:list<string>,missing_collations:list<string>,current_schema:string|null,current_table:string} $plan
     * @return array{current:string,shadowed_by:list<string>,blocked_fallback_indexes:list<string>,missing_collations:list<string>,requires_reprepare:bool}
     */
    public static function shadowSummary(array $plan): array
    {
        $current = ($plan['current_schema'] ?? '') === ''
            ? ''
            : $plan['current_schema'] . '.' . $plan['current_table'];

        return [
            'current' => $current,
            'shadowed_by' => array_map(
                static fn (array $table): string => $table['schema'] . '.' . $table['name'],
                $plan['shadowed_tables'],
            ),
            'blocked_fallback_indexes' => $plan['blocked_shadowed_usable_indexes'],
            'missing_collations' => $plan['missing_collations'],
            'requires_reprepare' => $plan['status'] === 'current-source-collation-blocked-by-shadow',
        ];
    }

    /**
     * @param array<string,true> $registered
     * @return list<array{name:string,schema:string,table:string,collations:list<string>,missing_collations:list<string>,usable:bool}>
     */
    private static function indexesFor(SQLiteAttachedSchemaCatalog $catalog, string $schemaName, string $tableName, array $registered): array
    {
        $indexes = [];
        foreach ($catalog->schemaRecords($schemaName) as $record) {
            if (strtolower($record->type) !== 'index' || strcasecmp($record->tableName, $tableName) !== 0) {
                continue;
            }
            $collations = self::indexCollations($record);
            $missing = [];
            foreach ($collations as $collation) {
                if (!isset($registered[$collation])) {
                    $missing[] = $collation;
                }
            }
            $indexes[] = [
                'name' => $record->name,
                'schema' => $schemaName,
                'table' => $record->tableName,
                'collations' => $collations,
                'missing_collations' => $missing,
                'usable' => $missing === [],
            ];
        }

        return $indexes;
    }

    private static function tableInSchema(SQLiteAttachedSchemaCatalog $catalog, string $schemaName, string $tableName): ?SQLiteSchemaRecord
    {
        foreach ($catalog->schemaRecords($schemaName) as $record) {
            if (($record->type === 'table' || $record->type === 'view') && strcasecmp($record->name, $tableName) === 0) {
                return $record;
            }
        }

        return null;
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
