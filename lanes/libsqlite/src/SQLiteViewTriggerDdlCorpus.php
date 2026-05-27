<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteViewTriggerDdlCorpus
{
    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array{name:string, table:string, temporary:bool, columns:list<string>, dependencies:list<string>, sql:string}>
     */
    public static function views(array $records): array
    {
        $views = [];
        foreach ($records as $record) {
            if (strtolower($record->type) !== 'view') {
                continue;
            }
            if ($record->rootPage !== 0 && $record->rootPage !== null) {
                throw new InvalidArgumentException('SQLite view schema records must use rootpage 0 or NULL');
            }
            if ($record->sql === null || trim($record->sql) === '') {
                throw new InvalidArgumentException('SQLite view schema records require CREATE VIEW SQL');
            }

            $views[] = [
                'name' => $record->name,
                'table' => $record->tableName,
                'temporary' => self::isTemporaryObject($record),
                'columns' => self::viewColumns($record->sql),
                'dependencies' => self::fromDependencies($record->sql),
                'sql' => self::normalizeSql($record->sql),
            ];
        }

        return $views;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array{name:string, table:string, event:string, timing:string, temporary:bool, insteadOf:bool, bodyStatements:int, referencedNew:list<string>, referencedOld:list<string>, dependencies:list<string>, sql:string}>
     */
    public static function triggers(array $records): array
    {
        $triggers = [];
        foreach ($records as $record) {
            if (strtolower($record->type) !== 'trigger') {
                continue;
            }
            if ($record->rootPage !== 0 && $record->rootPage !== null) {
                throw new InvalidArgumentException('SQLite trigger schema records must use rootpage 0 or NULL');
            }
            if ($record->sql === null || trim($record->sql) === '') {
                throw new InvalidArgumentException('SQLite trigger schema records require CREATE TRIGGER SQL');
            }

            $parsed = self::parseTrigger($record->sql);
            $triggers[] = [
                'name' => $record->name,
                'table' => $record->tableName,
                'event' => $parsed['event'],
                'timing' => $parsed['timing'],
                'temporary' => self::isTemporaryObject($record),
                'insteadOf' => $parsed['timing'] === 'instead of',
                'bodyStatements' => self::triggerBodyStatementCount($record->sql),
                'referencedNew' => self::pseudoColumns($record->sql, 'new'),
                'referencedOld' => self::pseudoColumns($record->sql, 'old'),
                'dependencies' => self::fromDependencies($record->sql),
                'sql' => self::normalizeSql($record->sql),
            ];
        }

        return $triggers;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array{status:string, ifExists:bool, dropped:list<string>, remaining:list<string>, danglingTriggers:list<string>}
     */
    public static function dropView(array $records, string $name, bool $ifExists = false): array
    {
        return self::dropObject($records, 'view', $name, $ifExists);
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array{status:string, ifExists:bool, dropped:list<string>, remaining:list<string>, danglingTriggers:list<string>}
     */
    public static function dropTrigger(array $records, string $name, bool $ifExists = false): array
    {
        return self::dropObject($records, 'trigger', $name, $ifExists);
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array{views:int, triggers:int, temporary:int, insteadOf:int, triggerEvents:array<string,int>, viewDependencies:list<string>, danglingTriggers:list<string>}
     */
    public static function summary(array $records): array
    {
        $views = self::views($records);
        $triggers = self::triggers($records);
        $events = [];
        foreach ($triggers as $trigger) {
            $events[$trigger['event']] = ($events[$trigger['event']] ?? 0) + 1;
        }
        ksort($events);

        $viewDependencies = [];
        foreach ($views as $view) {
            foreach ($view['dependencies'] as $dependency) {
                $viewDependencies[$dependency] = true;
            }
        }

        return [
            'views' => count($views),
            'triggers' => count($triggers),
            'temporary' => count(array_filter([...$views, ...$triggers], static fn (array $object): bool => $object['temporary'])),
            'insteadOf' => count(array_filter($triggers, static fn (array $trigger): bool => $trigger['insteadOf'])),
            'triggerEvents' => $events,
            'viewDependencies' => array_keys($viewDependencies),
            'danglingTriggers' => self::danglingTriggers($records),
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<string>
     */
    public static function danglingTriggers(array $records): array
    {
        $targets = [];
        foreach ($records as $record) {
            if (in_array(strtolower($record->type), ['table', 'view'], true)) {
                $targets[strtolower($record->name)] = true;
            }
        }

        $dangling = [];
        foreach (self::triggers($records) as $trigger) {
            if (!isset($targets[strtolower($trigger['table'])])) {
                $dangling[] = $trigger['name'];
            }
        }

        return $dangling;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array{status:string, ifExists:bool, dropped:list<string>, remaining:list<string>, danglingTriggers:list<string>}
     */
    private static function dropObject(array $records, string $type, string $name, bool $ifExists): array
    {
        $needle = strtolower($name);
        $dropped = [];
        $remaining = [];
        $found = false;

        foreach ($records as $record) {
            if (strtolower($record->type) === $type && strtolower($record->name) === $needle) {
                $found = true;
                $dropped[] = $record->name;
                continue;
            }
            $remaining[] = $record;
        }

        if (!$found && !$ifExists) {
            throw new InvalidArgumentException("SQLite {$type} does not exist: {$name}");
        }

        return [
            'status' => $found ? 'dropped' : 'missing',
            'ifExists' => $ifExists,
            'dropped' => $dropped,
            'remaining' => array_map(static fn (SQLiteSchemaRecord $record): string => $record->name, $remaining),
            'danglingTriggers' => self::danglingTriggers($remaining),
        ];
    }

    /**
     * @return list<string>
     */
    private static function viewColumns(string $sql): array
    {
        if (!preg_match('/\bcreate\s+(?:temp(?:orary)?\s+)?view\s+(?:if\s+not\s+exists\s+)?(?:["`\[]?[\w]+["`\]]?\s*\.\s*)?["`\[]?[\w]+["`\]]?\s*\((?<columns>[^)]*)\)/i', $sql, $matches)) {
            return [];
        }

        $columns = [];
        foreach (explode(',', $matches['columns']) as $column) {
            $name = self::unquoteIdentifier(trim($column));
            if ($name !== '') {
                $columns[] = $name;
            }
        }

        return $columns;
    }

    /**
     * @return array{timing:string,event:string}
     */
    private static function parseTrigger(string $sql): array
    {
        if (!preg_match('/\bcreate\s+(?:temp(?:orary)?\s+)?trigger\s+(?:if\s+not\s+exists\s+)?(?:["`\[]?[\w]+["`\]]?\s*\.\s*)?["`\[]?[\w]+["`\]]?\s+(?:(before|after|instead\s+of)\s+)?(insert|delete|update)\b/i', $sql, $matches)) {
            throw new InvalidArgumentException('SQLite trigger SQL must include CREATE TRIGGER timing and event');
        }

        return [
            'timing' => isset($matches[1]) && $matches[1] !== '' ? strtolower(preg_replace('/\s+/', ' ', $matches[1])) : 'before',
            'event' => strtolower($matches[2]),
        ];
    }

    private static function triggerBodyStatementCount(string $sql): int
    {
        if (!preg_match('/\bbegin\b(?<body>.*)\bend\b/is', $sql, $matches)) {
            return 0;
        }

        $statements = array_filter(array_map('trim', explode(';', $matches['body'])), static fn (string $part): bool => $part !== '');

        return count($statements);
    }

    /**
     * @return list<string>
     */
    private static function pseudoColumns(string $sql, string $prefix): array
    {
        preg_match_all('/\b' . preg_quote($prefix, '/') . '\s*\.\s*(["`\[]?[\w]+["`\]]?)/i', $sql, $matches);
        $columns = [];
        foreach ($matches[1] ?? [] as $column) {
            $columns[self::unquoteIdentifier($column)] = true;
        }

        return array_keys($columns);
    }

    /**
     * @return list<string>
     */
    private static function fromDependencies(string $sql): array
    {
        preg_match_all('/\b(?:from|join|update|into|delete\s+from)\s+(["`\[]?[\w]+["`\]]?)(?:\s*\.\s*(["`\[]?[\w]+["`\]]?))?/i', $sql, $matches, PREG_SET_ORDER);
        $dependencies = [];
        foreach ($matches as $match) {
            $schemaQualified = $match[2] ?? '';
            $name = $schemaQualified !== '' ? $schemaQualified : $match[1];
            $name = self::unquoteIdentifier($name);
            if ($name !== '' && !in_array(strtolower($name), ['on'], true)) {
                $dependencies[$name] = true;
            }
        }

        return array_keys($dependencies);
    }

    private static function isTemporaryObject(SQLiteSchemaRecord $record): bool
    {
        return (bool) ($record->sql !== null && preg_match('/\bcreate\s+temp(?:orary)?\s+/i', $record->sql));
    }

    private static function normalizeSql(string $sql): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $sql));
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return '';
        }
        $first = $identifier[0];
        $last = substr($identifier, -1);
        if (($first === '"' && $last === '"') || ($first === '`' && $last === '`') || ($first === '[' && $last === ']')) {
            return str_replace($first === '[' ? ']]' : $first . $first, $first === '[' ? ']' : $first, substr($identifier, 1, -1));
        }

        return $identifier;
    }
}
