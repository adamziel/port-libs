<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteSchemaGeneratedTriggerReparseCurrentSourceNextPlan
{
    /**
     * @param list<SQLiteSchemaRecord> $currentRecords
     * @param list<SQLiteSchemaRecord> $nextRecords
     * @return array<string,mixed>
     */
    public static function currentNext(array $currentRecords, array $nextRecords, string $triggerName, array $options = []): array
    {
        $schemaVersionBefore = self::nonNegativeInt($options['schema_version_before'] ?? 0, 'schema_version_before');
        $schemaVersionAfter = self::nonNegativeInt($options['schema_version_after'] ?? ($schemaVersionBefore + 1), 'schema_version_after');

        $current = self::snapshot($currentRecords, $triggerName);
        $next = self::snapshot($nextRecords, $triggerName);
        $changedFields = [];

        foreach (['target', 'event', 'timing', 'columns', 'generatedColumns', 'referencedGeneratedNew', 'referencedGeneratedOld', 'missingNew', 'missingOld', 'bodyDependencies', 'status'] as $field) {
            if (($current[$field] ?? null) !== ($next[$field] ?? null)) {
                $changedFields[] = $field;
            }
        }

        $generatedAdded = array_values(array_diff($next['generatedColumns'], $current['generatedColumns']));
        $generatedRemoved = array_values(array_diff($current['generatedColumns'], $next['generatedColumns']));
        $generatedReferences = array_values(array_unique(array_merge(
            $current['referencedGeneratedNew'],
            $current['referencedGeneratedOld'],
            $next['referencedGeneratedNew'],
            $next['referencedGeneratedOld'],
        )));
        sort($generatedReferences);

        $schemaCookieChanged = $schemaVersionBefore !== $schemaVersionAfter;
        $requiresReparse = $schemaCookieChanged && $changedFields !== [];

        return [
            'operation' => 'schema-ddl-reparse-generated-trigger-current-source',
            'trigger' => $current['trigger'],
            'target' => $next['target'],
            'schema_version_before' => $schemaVersionBefore,
            'schema_version_after' => $schemaVersionAfter,
            'schema_cookie_changed' => $schemaCookieChanged,
            'current' => $current,
            'next' => $next,
            'changed' => $changedFields !== [],
            'changedFields' => $changedFields,
            'generatedAdded' => $generatedAdded,
            'generatedRemoved' => $generatedRemoved,
            'generatedReferences' => $generatedReferences,
            'requiresReparse' => $requiresReparse,
            'status' => $requiresReparse ? 'reparse-required' : 'stable',
            'dependencies' => [
                'sqlite-schema-ddl-reparse',
                'sqlite-generated-column-catalog',
                'sqlite-trigger-current-source',
            ],
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array<string,mixed>
     */
    private static function snapshot(array $records, string $triggerName): array
    {
        $trigger = self::findRecord($records, 'trigger', $triggerName);
        if ($trigger->sql === null || trim($trigger->sql) === '') {
            throw new InvalidArgumentException('SQLite generated trigger reparse requires trigger SQL');
        }
        $parsed = self::parseTrigger($trigger->sql);
        $table = self::findRecord($records, 'table', $parsed['target']);
        if ($table->sql === null || trim($table->sql) === '') {
            throw new InvalidArgumentException('SQLite generated trigger reparse requires target table SQL');
        }

        $columns = self::tableColumns($table->sql);
        $columnNames = array_map(static fn (array $column): string => $column['name'], $columns);
        $generated = array_values(array_map(
            static fn (array $column): string => $column['name'],
            array_values(array_filter($columns, static fn (array $column): bool => $column['generated'])),
        ));
        $new = self::pseudoColumns($trigger->sql, 'new');
        $old = self::pseudoColumns($trigger->sql, 'old');

        return [
            'trigger' => $trigger->name,
            'target' => $table->name,
            'event' => $parsed['event'],
            'timing' => $parsed['timing'],
            'columns' => $columnNames,
            'generatedColumns' => $generated,
            'generatedDetails' => array_values(array_filter($columns, static fn (array $column): bool => $column['generated'])),
            'referencedNew' => $new,
            'referencedOld' => $old,
            'referencedGeneratedNew' => self::intersection($new, $generated),
            'referencedGeneratedOld' => self::intersection($old, $generated),
            'missingNew' => self::missing($new, $columnNames),
            'missingOld' => self::missing($old, $columnNames),
            'bodyDependencies' => self::bodyDependencies($trigger->sql),
            'status' => self::missing($new, $columnNames) === [] && self::missing($old, $columnNames) === [] ? 'resolved' : 'unresolved',
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function findRecord(array $records, string $type, string $name): SQLiteSchemaRecord
    {
        foreach ($records as $record) {
            if (strtolower($record->type) === $type && strcasecmp($record->name, $name) === 0) {
                return $record;
            }
        }

        throw new InvalidArgumentException("SQLite schema record not found: {$type} {$name}");
    }

    /**
     * @return array{event:string,timing:string,target:string}
     */
    private static function parseTrigger(string $sql): array
    {
        if (!preg_match('/\bcreate\s+(?:temp(?:orary)?\s+)?trigger\s+(?:if\s+not\s+exists\s+)?(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)\s+(?:(before|after|instead\s+of)\s+)?(insert|delete|update)(?:\s+of\s+.*?)?\s+on\s+(?:"[^"]+"\s*\.\s*|`[^`]+`\s*\.\s*|\[[^\]]+\]\s*\.\s*|\w+\s*\.\s*)?("[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)/is', $sql, $matches)) {
            throw new InvalidArgumentException('SQLite trigger SQL must include timing, event, and target');
        }

        return [
            'event' => strtolower($matches[2]),
            'timing' => isset($matches[1]) && trim($matches[1]) !== '' ? strtolower((string) preg_replace('/\s+/', ' ', $matches[1])) : 'before',
            'target' => self::unquoteIdentifier($matches[3]),
        ];
    }

    /**
     * @return list<array{name:string,generated:bool,storage:?string,expression:?string}>
     */
    private static function tableColumns(string $sql): array
    {
        if (!preg_match('/\bcreate\s+(?:temp(?:orary)?\s+)?table\s+(?:if\s+not\s+exists\s+)?(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)(?:\s*\.\s*(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+))?\s*\((?<body>.*)\)/is', $sql, $matches)) {
            return [];
        }

        $columns = [];
        foreach (self::splitTopLevel($matches['body'], ',') as $definition) {
            $definition = trim($definition);
            if ($definition === '' || preg_match('/^(?:constraint|primary|foreign|unique|check)\b/i', $definition)) {
                continue;
            }
            if (!preg_match('/^("[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)(?<tail>.*)$/s', $definition, $column)) {
                continue;
            }
            $tail = (string) $column['tail'];
            $expression = self::generatedExpression($tail);
            $columns[] = [
                'name' => self::unquoteIdentifier($column[1]),
                'generated' => $expression !== null,
                'storage' => $expression === null ? null : (preg_match('/\bstored\b/i', $tail) ? 'STORED' : 'VIRTUAL'),
                'expression' => $expression,
            ];
        }

        return $columns;
    }

    private static function generatedExpression(string $tail): ?string
    {
        if (!preg_match('/\b(?:generated\s+always\s+)?as\s*\(/i', $tail, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $open = strpos($tail, '(', (int) $matches[0][1]);
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
    private static function pseudoColumns(string $sql, string $prefix): array
    {
        preg_match_all('/\b' . preg_quote($prefix, '/') . '\s*\.\s*("[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)/i', $sql, $matches);
        $columns = [];
        foreach ($matches[1] ?? [] as $column) {
            $columns[self::unquoteIdentifier($column)] = true;
        }

        return array_keys($columns);
    }

    /**
     * @return list<array{schema:?string,name:string}>
     */
    private static function bodyDependencies(string $sql): array
    {
        if (!preg_match('/\bbegin\b(?<body>.*)\bend\b/is', $sql, $matches)) {
            return [];
        }
        preg_match_all('/\b(?:from|join|update|into|delete\s+from)\s+(?:(["`\[]?[\w]+["`\]]?)\s*\.\s*)?(["`\[]?[\w]+["`\]]?)/i', $matches['body'], $refs, PREG_SET_ORDER);
        $dependencies = [];
        foreach ($refs as $ref) {
            $schema = isset($ref[1]) && $ref[1] !== '' ? strtolower(self::unquoteIdentifier($ref[1])) : null;
            $name = self::unquoteIdentifier($ref[2]);
            if ($name === '' || in_array(strtolower($name), ['new', 'old'], true)) {
                continue;
            }
            $dependencies[($schema ?? '') . '.' . strtolower($name)] = ['schema' => $schema, 'name' => $name];
        }

        return array_values($dependencies);
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $value, string $delimiter): array
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
                    if (($value[$i + 1] ?? '') === $quote) {
                        $current .= $value[++$i];
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
                $end = strpos($value, ']', $i + 1);
                if ($end === false) {
                    throw new InvalidArgumentException('Unterminated bracket quoted identifier');
                }
                $current .= substr($value, $i, $end - $i + 1);
                $i = $end;
                continue;
            }
            if ($char === '(') {
                ++$depth;
            } elseif ($char === ')' && $depth > 0) {
                --$depth;
            }
            if ($char === $delimiter && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $parts[] = $current;

        return $parts;
    }

    private static function matchingParen(string $value, int $open): ?int
    {
        $depth = 0;
        $quote = null;
        $length = strlen($value);
        for ($i = $open; $i < $length; ++$i) {
            $char = $value[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if (($value[$i + 1] ?? '') === $quote) {
                        ++$i;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                ++$depth;
                continue;
            }
            if ($char === ')') {
                --$depth;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     * @return list<string>
     */
    private static function intersection(array $left, array $right): array
    {
        $available = array_fill_keys(array_map('strtolower', $right), true);
        $matches = [];
        foreach ($left as $value) {
            if (isset($available[strtolower($value)])) {
                $matches[] = $value;
            }
        }

        return $matches;
    }

    /**
     * @param list<string> $references
     * @param list<string> $columns
     * @return list<string>
     */
    private static function missing(array $references, array $columns): array
    {
        $available = array_fill_keys(array_map('strtolower', $columns), true);
        $missing = [];
        foreach ($references as $reference) {
            if (!isset($available[strtolower($reference)])) {
                $missing[] = $reference;
            }
        }

        return $missing;
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return '';
        }
        $first = $identifier[0];
        $last = substr($identifier, -1);
        if (($first === '"' && $last === '"') || ($first === '`' && $last === '`')) {
            return str_replace($first . $first, $first, substr($identifier, 1, -1));
        }
        if ($first === '[' && $last === ']') {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }

    private static function nonNegativeInt(mixed $value, string $name): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite {$name} must be a non-negative integer");
        }

        return $value;
    }
}
