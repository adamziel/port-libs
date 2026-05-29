<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteSchemaAlterGeneratedTriggerViewCurrentSourceNextPlan
{
    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<array{name:string,sql:string,columns?:list<string>,schema_version?:int}> $preparedStatements
     * @return array<string,mixed>
     */
    public static function plan(array $records, string $tableName, string $alterSql, array $preparedStatements = [], array $options = []): array
    {
        $schemaVersionBefore = self::nonNegativeInt($options['schema_version_before'] ?? 0, 'schema_version_before');
        $schemaVersionAfter = self::nonNegativeInt($options['schema_version_after'] ?? ($schemaVersionBefore + 1), 'schema_version_after');
        $table = self::findRecord($records, 'table', $tableName);
        if ($table->sql === null || trim($table->sql) === '') {
            throw new InvalidArgumentException('SQLite ALTER generated trigger/view reparse requires table SQL');
        }

        $added = self::parseAddGeneratedColumn($alterSql, $table->name);
        $currentColumns = self::tableColumns($table->sql);
        if (in_array($added['name'], array_column($currentColumns, 'name'), true)) {
            throw new InvalidArgumentException('SQLite ALTER TABLE ADD COLUMN cannot add duplicate column');
        }

        $nextColumns = array_merge($currentColumns, [$added]);
        $views = [];
        $triggers = [];
        foreach ($records as $record) {
            if ($record->sql === null || trim($record->sql) === '') {
                continue;
            }
            if (strtolower($record->type) === 'view') {
                $view = self::viewSnapshot($record, $table->name, $currentColumns, $nextColumns);
                if ($view['dependsOnTarget']) {
                    $views[] = $view;
                }
            } elseif (strtolower($record->type) === 'trigger') {
                $trigger = self::triggerSnapshot($record, $table->name, $currentColumns, $nextColumns);
                if ($trigger['dependsOnTarget']) {
                    $triggers[] = $trigger;
                }
            }
        }

        $invalidatedStatements = self::invalidatedStatements($preparedStatements, $table->name, $added['name'], $schemaVersionBefore);
        $currentGenerated = array_values(array_map(
            static fn (array $column): string => $column['name'],
            array_values(array_filter($currentColumns, static fn (array $column): bool => $column['generated'])),
        ));
        $nextGenerated = array_values(array_map(
            static fn (array $column): string => $column['name'],
            array_values(array_filter($nextColumns, static fn (array $column): bool => $column['generated'])),
        ));
        $resolvedViews = array_values(array_filter($views, static fn (array $view): bool => $view['current']['status'] !== $view['next']['status']));
        $resolvedTriggers = array_values(array_filter($triggers, static fn (array $trigger): bool => $trigger['current']['status'] !== $trigger['next']['status']));

        return [
            'operation' => 'schema-alter-generated-trigger-view-current-source',
            'table' => $table->name,
            'alterSql' => $alterSql,
            'schema_version_before' => $schemaVersionBefore,
            'schema_version_after' => $schemaVersionAfter,
            'schema_cookie_changed' => $schemaVersionBefore !== $schemaVersionAfter,
            'addedGeneratedColumn' => $added,
            'currentGeneratedColumns' => $currentGenerated,
            'nextGeneratedColumns' => $nextGenerated,
            'generatedAdded' => array_values(array_diff($nextGenerated, $currentGenerated)),
            'views' => $views,
            'triggers' => $triggers,
            'resolvedViews' => array_column($resolvedViews, 'name'),
            'resolvedTriggers' => array_column($resolvedTriggers, 'name'),
            'invalidatedStatements' => $invalidatedStatements,
            'requiresReparse' => ($schemaVersionBefore !== $schemaVersionAfter) && ($views !== [] || $triggers !== [] || $invalidatedStatements !== []),
            'status' => ($views === [] && $triggers === []) ? 'no-dependent-schema' : 'reparse-required',
            'dependencies' => [
                'sqlite-alter-table-add-generated-column',
                'sqlite-schema-cookie-reprepare',
                'sqlite-trigger-current-source',
                'sqlite-view-current-source',
            ],
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
     * @return array{name:string,generated:bool,storage:string,expression:string,source:string}
     */
    private static function parseAddGeneratedColumn(string $sql, string $tableName): array
    {
        if (!preg_match('/^\s*alter\s+table\s+(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)\s+add\s+column\s+(?<definition>.+?)\s*;?\s*$/is', $sql, $matches)) {
            throw new InvalidArgumentException('SQLite ALTER TABLE ADD COLUMN SQL is unsupported');
        }
        if (!preg_match('/^\s*alter\s+table\s+(?<table>"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)/is', $sql, $tableMatch) || strcasecmp(self::unquoteIdentifier($tableMatch['table']), $tableName) !== 0) {
            throw new InvalidArgumentException('SQLite ALTER TABLE ADD COLUMN target does not match table');
        }
        $definition = trim($matches['definition']);
        if (!preg_match('/^(?<name>"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)(?<tail>.*)$/s', $definition, $column)) {
            throw new InvalidArgumentException('SQLite ALTER TABLE ADD COLUMN definition is malformed');
        }
        $expression = self::generatedExpression((string) $column['tail']);
        if ($expression === null) {
            throw new InvalidArgumentException('SQLite ALTER TABLE ADD COLUMN slice requires a generated column');
        }

        return [
            'name' => self::unquoteIdentifier($column['name']),
            'generated' => true,
            'storage' => preg_match('/\bstored\b/i', (string) $column['tail']) ? 'STORED' : 'VIRTUAL',
            'expression' => $expression,
            'source' => $definition,
        ];
    }

    /**
     * @return list<array{name:string,generated:bool,storage:?string,expression:?string,source:string}>
     */
    private static function tableColumns(string $sql): array
    {
        if (!preg_match('/\bcreate\s+(?:temp(?:orary)?\s+)?table\s+(?:if\s+not\s+exists\s+)?(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)(?:\s*\.\s*(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+))?\s*\((?<body>.*)\)/is', $sql, $matches)) {
            throw new InvalidArgumentException('SQLite table SQL is unsupported');
        }

        $columns = [];
        foreach (self::splitTopLevel($matches['body'], ',') as $definition) {
            $definition = trim($definition);
            if ($definition === '' || preg_match('/^(?:constraint|primary|foreign|unique|check)\b/i', $definition)) {
                continue;
            }
            if (!preg_match('/^(?<name>"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)(?<tail>.*)$/s', $definition, $column)) {
                continue;
            }
            $expression = self::generatedExpression((string) $column['tail']);
            $columns[] = [
                'name' => self::unquoteIdentifier($column['name']),
                'generated' => $expression !== null,
                'storage' => $expression === null ? null : (preg_match('/\bstored\b/i', (string) $column['tail']) ? 'STORED' : 'VIRTUAL'),
                'expression' => $expression,
                'source' => $definition,
            ];
        }

        return $columns;
    }

    /**
     * @param list<array{name:string,generated:bool,storage:?string,expression:?string,source:string}> $currentColumns
     * @param list<array{name:string,generated:bool,storage:?string,expression:?string,source:string}> $nextColumns
     * @return array<string,mixed>
     */
    private static function viewSnapshot(SQLiteSchemaRecord $record, string $tableName, array $currentColumns, array $nextColumns): array
    {
        $references = self::viewReferences($record->sql ?? '', $tableName);
        $current = self::dependencyStatus($references, $currentColumns);
        $next = self::dependencyStatus($references, $nextColumns);

        return [
            'name' => $record->name,
            'dependsOnTarget' => self::viewDependsOn($record->sql ?? '', $tableName),
            'references' => $references,
            'current' => $current,
            'next' => $next,
        ];
    }

    /**
     * @param list<array{name:string,generated:bool,storage:?string,expression:?string,source:string}> $currentColumns
     * @param list<array{name:string,generated:bool,storage:?string,expression:?string,source:string}> $nextColumns
     * @return array<string,mixed>
     */
    private static function triggerSnapshot(SQLiteSchemaRecord $record, string $tableName, array $currentColumns, array $nextColumns): array
    {
        $references = self::triggerReferences($record->sql ?? '');
        $current = self::dependencyStatus($references, $currentColumns);
        $next = self::dependencyStatus($references, $nextColumns);

        return [
            'name' => $record->name,
            'event' => self::triggerEvent($record->sql ?? ''),
            'dependsOnTarget' => self::triggerDependsOn($record->sql ?? '', $tableName),
            'references' => $references,
            'current' => $current,
            'next' => $next,
        ];
    }

    /**
     * @param list<string> $references
     * @param list<array{name:string,generated:bool,storage:?string,expression:?string,source:string}> $columns
     * @return array{status:string,missing:list<string>,generated:list<string>,ordinary:list<string>}
     */
    private static function dependencyStatus(array $references, array $columns): array
    {
        $names = array_column($columns, 'name');
        $generated = array_values(array_map(
            static fn (array $column): string => $column['name'],
            array_values(array_filter($columns, static fn (array $column): bool => $column['generated'])),
        ));
        $missing = array_values(array_diff($references, $names));
        $generatedRefs = self::intersection($references, $generated);

        return [
            'status' => $missing === [] ? 'resolved' : 'unresolved',
            'missing' => $missing,
            'generated' => $generatedRefs,
            'ordinary' => array_values(array_diff($references, $generatedRefs, $missing)),
        ];
    }

    /**
     * @return list<string>
     */
    private static function viewReferences(string $sql, string $tableName): array
    {
        $references = [];
        preg_match_all('/\b(?:(?:new|old)\s*\.\s*)?("[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)/i', self::withoutStringLiterals(self::selectBody($sql)), $matches);
        foreach ($matches[1] ?? [] as $token) {
            $name = self::unquoteIdentifier($token);
            if (!in_array(strtolower($name), self::keywords(), true) && strcasecmp($name, $tableName) !== 0) {
                $references[$name] = true;
            }
        }

        return array_keys($references);
    }

    private static function withoutStringLiterals(string $sql): string
    {
        return preg_replace("/'(?:''|[^'])*'/", "''", $sql) ?? $sql;
    }

    /**
     * @return list<string>
     */
    private static function triggerReferences(string $sql): array
    {
        preg_match_all('/\b(?:new|old)\s*\.\s*("[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)/i', $sql, $matches);
        $references = [];
        foreach ($matches[1] ?? [] as $token) {
            $references[self::unquoteIdentifier($token)] = true;
        }

        return array_keys($references);
    }

    private static function viewDependsOn(string $sql, string $tableName): bool
    {
        return (bool) preg_match('/\b(?:from|join)\s+(?:"[^"]+"\s*\.\s*|`[^`]+`\s*\.\s*|\[[^\]]+\]\s*\.\s*|\w+\s*\.\s*)?["`\[]?' . preg_quote($tableName, '/') . '["`\]]?\b/i', $sql);
    }

    private static function triggerDependsOn(string $sql, string $tableName): bool
    {
        return (bool) preg_match('/\bon\s+(?:"[^"]+"\s*\.\s*|`[^`]+`\s*\.\s*|\[[^\]]+\]\s*\.\s*|\w+\s*\.\s*)?["`\[]?' . preg_quote($tableName, '/') . '["`\]]?\b/i', $sql);
    }

    private static function triggerEvent(string $sql): string
    {
        if (preg_match('/\b(insert|update|delete)\b/i', $sql, $matches)) {
            return strtolower($matches[1]);
        }

        return 'unknown';
    }

    private static function selectBody(string $sql): string
    {
        return preg_match('/\bas\b(?<body>.*)$/is', $sql, $matches) ? (string) $matches['body'] : $sql;
    }

    /**
     * @param list<array{name:string,sql:string,columns?:list<string>,schema_version?:int}> $statements
     * @return list<array{name:string,reason:string,schema_version:int|null,columns:list<string>}>
     */
    private static function invalidatedStatements(array $statements, string $tableName, string $addedColumn, int $schemaVersionBefore): array
    {
        $invalidated = [];
        foreach ($statements as $statement) {
            $sql = (string) ($statement['sql'] ?? '');
            $columns = array_values(array_map('strval', $statement['columns'] ?? []));
            if (
                stripos($sql, $tableName) === false
                && !in_array($addedColumn, $columns, true)
            ) {
                continue;
            }
            $invalidated[] = [
                'name' => (string) ($statement['name'] ?? ''),
                'reason' => 'schema-cookie-changed',
                'schema_version' => isset($statement['schema_version']) ? (int) $statement['schema_version'] : null,
                'columns' => $columns,
            ];
        }

        return $invalidated;
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
            } elseif ($char === ')') {
                --$depth;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
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
            if ($char === '(') {
                ++$depth;
            } elseif ($char === ')') {
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

    /**
     * @param list<string> $left
     * @param list<string> $right
     * @return list<string>
     */
    private static function intersection(array $left, array $right): array
    {
        $out = [];
        foreach ($left as $value) {
            foreach ($right as $candidate) {
                if (strcasecmp($value, $candidate) === 0) {
                    $out[] = $candidate;
                    break;
                }
            }
        }

        return array_values(array_unique($out));
    }

    private static function nonNegativeInt(mixed $value, string $name): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite {$name} must be a non-negative integer");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function keywords(): array
    {
        return ['select', 'from', 'where', 'join', 'left', 'on', 'as', 'case', 'when', 'then', 'else', 'end', 'and', 'or', 'is', 'not', 'null', 'insert', 'into', 'values', 'update', 'set', 'delete', 'begin'];
    }
}
