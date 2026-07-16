<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteAttachTempCollationCachePlan
{
    /**
     * @param list<string> $columns
     * @return array{status:string,generation:int,table:string,lookup:string,schema:string,record:string,root_page:int|null,columns:list<string>,collations:array<string,string>,cache_key:string,search_order:list<string>}
     */
    public static function prepare(SQLiteAttachedSchemaCatalog $catalog, string $tableName, array $columns): array
    {
        $resolved = $catalog->resolveTable($tableName);
        if ($resolved === null) {
            throw new InvalidArgumentException("SQLite collation cache cannot resolve table {$tableName}");
        }

        $record = $resolved['record'];
        $wanted = self::normalizeColumnList($columns);
        $collations = self::tableCollations($record->sql ?? '');
        $selected = [];
        foreach ($wanted as $column) {
            $selected[$column] = $collations[strtolower($column)] ?? 'BINARY';
        }
        ksort($selected);

        return [
            'status' => 'prepared',
            'generation' => $catalog->schemaGeneration(),
            'table' => self::unqualifiedName($tableName),
            'lookup' => trim($tableName),
            'schema' => $resolved['schema'],
            'record' => $record->name,
            'root_page' => $record->rootPage,
            'columns' => array_keys($selected),
            'collations' => $selected,
            'cache_key' => self::cacheKey($resolved['schema'], $record->name, $record->rootPage, $selected),
            'search_order' => $catalog->searchOrder(),
        ];
    }

    /**
     * @param array{generation?:int,table?:string,lookup?:string,schema?:string,record?:string,root_page?:int|null,columns?:list<string>,collations?:array<string,string>,cache_key?:string,search_order?:list<string>} $prepared
     * @return array{status:string,current:bool,reprepare_required:bool,reason:string,generation_changed:bool,before_generation:int|null,after_generation:int,before_schema:string|null,after_schema:string|null,before_record:string|null,after_record:string|null,before_root_page:int|null,after_root_page:int|null,before_collations:array<string,string>,after_collations:array<string,string>,changed_columns:list<string>,added_columns:list<string>,removed_columns:list<string>,before_search_order:list<string>,after_search_order:list<string>,before_cache_key:string|null,after_cache_key:string|null}
     */
    public static function currentNext(SQLiteAttachedSchemaCatalog $catalog, array $prepared): array
    {
        $table = (string) ($prepared['lookup'] ?? $prepared['table'] ?? '');
        if ($table === '') {
            throw new InvalidArgumentException('SQLite collation cache validation requires a prepared table name');
        }

        $next = self::prepare($catalog, $table, array_values($prepared['columns'] ?? []));
        $beforeCollations = self::normalizeCollationMap($prepared['collations'] ?? []);
        $afterCollations = self::normalizeCollationMap($next['collations']);
        $changedColumns = [];
        foreach (array_unique(array_merge(array_keys($beforeCollations), array_keys($afterCollations))) as $column) {
            if (($beforeCollations[$column] ?? null) !== ($afterCollations[$column] ?? null)) {
                $changedColumns[] = $column;
            }
        }
        sort($changedColumns);

        $beforeGeneration = isset($prepared['generation']) ? (int) $prepared['generation'] : null;
        $generationChanged = $beforeGeneration !== $next['generation'];
        $schemaChanged = ($prepared['schema'] ?? null) !== $next['schema'];
        $recordChanged = ($prepared['record'] ?? null) !== $next['record'];
        $rootChanged = ($prepared['root_page'] ?? null) !== $next['root_page'];
        $cacheKeyChanged = ($prepared['cache_key'] ?? null) !== $next['cache_key'];
        $current = !$generationChanged && !$schemaChanged && !$recordChanged && !$rootChanged && $changedColumns === [] && !$cacheKeyChanged;

        return [
            'status' => $current ? 'current' : 'stale',
            'current' => $current,
            'reprepare_required' => !$current,
            'reason' => self::reason($generationChanged, $schemaChanged, $recordChanged, $rootChanged, $changedColumns, $cacheKeyChanged),
            'generation_changed' => $generationChanged,
            'before_generation' => $beforeGeneration,
            'after_generation' => $next['generation'],
            'before_schema' => isset($prepared['schema']) ? (string) $prepared['schema'] : null,
            'after_schema' => $next['schema'],
            'before_record' => isset($prepared['record']) ? (string) $prepared['record'] : null,
            'after_record' => $next['record'],
            'before_root_page' => isset($prepared['root_page']) ? $prepared['root_page'] : null,
            'after_root_page' => $next['root_page'],
            'before_collations' => $beforeCollations,
            'after_collations' => $afterCollations,
            'changed_columns' => $changedColumns,
            'added_columns' => array_values(array_diff(array_keys($afterCollations), array_keys($beforeCollations))),
            'removed_columns' => array_values(array_diff(array_keys($beforeCollations), array_keys($afterCollations))),
            'before_search_order' => array_values($prepared['search_order'] ?? []),
            'after_search_order' => $next['search_order'],
            'before_cache_key' => isset($prepared['cache_key']) ? (string) $prepared['cache_key'] : null,
            'after_cache_key' => $next['cache_key'],
        ];
    }

    /**
     * @param list<array{label:string,catalog:SQLiteAttachedSchemaCatalog}> $nextCatalogs
     * @return list<array<string,mixed>>
     */
    public static function yieldCurrentNext(SQLiteAttachedSchemaCatalog $catalog, string $tableName, array $columns, array $nextCatalogs): array
    {
        $prepared = self::prepare($catalog, $tableName, $columns);
        $rows = [[
            'label' => 'current',
            'prepared' => $prepared,
            'validation' => self::currentNext($catalog, $prepared),
        ]];

        foreach ($nextCatalogs as $entry) {
            $rows[] = [
                'label' => $entry['label'],
                'prepared' => $prepared,
                'validation' => self::currentNext($entry['catalog'], $prepared),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,string> $collations
     */
    private static function cacheKey(string $schema, string $record, ?int $rootPage, array $collations): string
    {
        return hash('sha256', json_encode([$schema, $record, $rootPage, $collations], JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private static function normalizeColumnList(array $columns): array
    {
        $normalized = [];
        foreach ($columns as $column) {
            $name = strtolower(self::unquoteIdentifier((string) $column));
            if ($name !== '') {
                $normalized[$name] = true;
            }
        }
        if ($normalized === []) {
            throw new InvalidArgumentException('SQLite collation cache requires at least one column');
        }

        return array_keys($normalized);
    }

    /**
     * @param array<string,string> $collations
     * @return array<string,string>
     */
    private static function normalizeCollationMap(array $collations): array
    {
        $normalized = [];
        foreach ($collations as $column => $collation) {
            $normalized[strtolower((string) $column)] = strtoupper((string) $collation);
        }
        ksort($normalized);

        return $normalized;
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
            if ($trimmed === '' || preg_match('/^(?:constraint|primary|foreign|unique|check)\b/i', $trimmed) === 1) {
                continue;
            }
            if (preg_match('/^(?<column>"[^"]+"|`[^`]+`|\[[^\]]+\]|[\w-]+)/', $trimmed, $column) !== 1) {
                continue;
            }
            $collation = 'BINARY';
            if (preg_match('/\bcollate\s+(?<collation>"[^"]+"|`[^`]+`|\[[^\]]+\]|[\w-]+)/i', $trimmed, $match) === 1) {
                $collation = strtoupper(self::unquoteIdentifier($match['collation']));
            }
            $collations[strtolower(self::unquoteIdentifier($column['column']))] = $collation;
        }

        return $collations;
    }

    /**
     * @return list<string>
     */
    private static function splitCommaList(string $value): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $length = strlen($value);
        for ($i = 0; $i < $length; ++$i) {
            $char = $value[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === '(') {
                ++$depth;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = trim($buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        if (trim($buffer) !== '') {
            $parts[] = trim($buffer);
        }

        return $parts;
    }

    private static function unqualifiedName(string $name): string
    {
        $parts = preg_split('/\s*\.\s*/', trim($name), 2);
        $raw = $parts === false ? $name : (count($parts) === 2 ? $parts[1] : $parts[0]);

        return self::unquoteIdentifier($raw);
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

    /**
     * @param list<string> $changedColumns
     */
    private static function reason(bool $generationChanged, bool $schemaChanged, bool $recordChanged, bool $rootChanged, array $changedColumns, bool $cacheKeyChanged): string
    {
        if ($generationChanged) {
            return 'schema generation changed';
        }
        if ($schemaChanged) {
            return 'resolved schema changed';
        }
        if ($recordChanged) {
            return 'resolved record changed';
        }
        if ($rootChanged) {
            return 'root page changed';
        }
        if ($changedColumns !== []) {
            return 'collation dependencies changed';
        }
        if ($cacheKeyChanged) {
            return 'cache key changed';
        }

        return 'cache entry is current';
    }
}
