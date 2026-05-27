<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteViewTriggerNameResolution
{
    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array{trigger:string,triggerSchema:string,target:string,targetSchema:string,targetType:string,targetTemporary:bool,insteadOf:bool,columns:list<string>,referencedNew:list<string>,referencedOld:list<string>,missingNew:list<string>,missingOld:list<string>,bodyDependencies:list<string>,status:string}
     */
    public static function resolveTrigger(array $records, string $triggerName): array
    {
        $triggerRecord = self::findRecord($records, 'trigger', $triggerName);
        if ($triggerRecord === null) {
            throw new InvalidArgumentException("SQLite trigger does not exist: {$triggerName}");
        }
        if ($triggerRecord->sql === null || trim($triggerRecord->sql) === '') {
            throw new InvalidArgumentException('SQLite trigger name resolution requires CREATE TRIGGER SQL');
        }

        $trigger = self::parseTrigger($triggerRecord->sql);
        $triggerSchema = self::schemaOfRecord($triggerRecord);
        $target = self::resolveTarget($records, $trigger['table'], self::isTemporaryObject($triggerRecord), $trigger['schema']);
        if ($target === null) {
            $qualifiedName = $trigger['schema'] === null ? $trigger['table'] : $trigger['schema'] . '.' . $trigger['table'];
            throw new InvalidArgumentException("SQLite trigger target does not resolve: {$qualifiedName}");
        }

        $columns = self::columnsForRecord($target);
        $new = self::pseudoColumns($triggerRecord->sql, 'new');
        $old = self::pseudoColumns($triggerRecord->sql, 'old');

        return [
            'trigger' => $triggerRecord->name,
            'triggerSchema' => $triggerSchema,
            'target' => $target->name,
            'targetSchema' => self::schemaOfRecord($target),
            'targetType' => strtolower($target->type),
            'targetTemporary' => self::isTemporaryObject($target),
            'insteadOf' => $trigger['timing'] === 'instead of',
            'columns' => $columns,
            'referencedNew' => $new,
            'referencedOld' => $old,
            'missingNew' => self::missingColumns($new, $columns),
            'missingOld' => self::missingColumns($old, $columns),
            'bodyDependencies' => self::bodyDependencies($triggerRecord->sql),
            'status' => self::missingColumns($new, $columns) === [] && self::missingColumns($old, $columns) === [] ? 'resolved' : 'unresolved',
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array{trigger:string,triggerSchema:string,target:string,targetSchema:string,targetType:string,targetTemporary:bool,insteadOf:bool,columns:list<string>,referencedNew:list<string>,referencedOld:list<string>,missingNew:list<string>,missingOld:list<string>,bodyDependencies:list<string>,status:string}>
     */
    public static function resolveTriggers(array $records): array
    {
        $resolved = [];
        foreach ($records as $record) {
            if (strtolower($record->type) === 'trigger') {
                $resolved[] = self::resolveTrigger($records, $record->name);
            }
        }

        return $resolved;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return array{resolved:int,unresolved:int,insteadOf:int,tempTargets:int,missingReferences:array<string,array{new:list<string>,old:list<string>}>}
     */
    public static function summary(array $records): array
    {
        $missing = [];
        $resolvedCount = 0;
        $unresolvedCount = 0;
        $insteadOf = 0;
        $tempTargets = 0;

        foreach (self::resolveTriggers($records) as $trigger) {
            if ($trigger['status'] === 'resolved') {
                ++$resolvedCount;
            } else {
                ++$unresolvedCount;
                $missing[$trigger['trigger']] = [
                    'new' => $trigger['missingNew'],
                    'old' => $trigger['missingOld'],
                ];
            }
            if ($trigger['insteadOf']) {
                ++$insteadOf;
            }
            if ($trigger['targetTemporary']) {
                ++$tempTargets;
            }
        }

        return [
            'resolved' => $resolvedCount,
            'unresolved' => $unresolvedCount,
            'insteadOf' => $insteadOf,
            'tempTargets' => $tempTargets,
            'missingReferences' => $missing,
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function findRecord(array $records, string $type, string $name): ?SQLiteSchemaRecord
    {
        foreach ($records as $record) {
            if (strtolower($record->type) === $type && strcasecmp($record->name, $name) === 0) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function resolveTarget(array $records, string $name, bool $tempTrigger, ?string $schema): ?SQLiteSchemaRecord
    {
        $matches = [];
        foreach ($records as $record) {
            if (!in_array(strtolower($record->type), ['table', 'view'], true) || strcasecmp($record->name, $name) !== 0) {
                continue;
            }
            if ($schema !== null && strcasecmp(self::schemaOfRecord($record), $schema) !== 0) {
                continue;
            }
            $matches[] = $record;
        }
        if ($matches === []) {
            return null;
        }

        usort($matches, static function (SQLiteSchemaRecord $left, SQLiteSchemaRecord $right) use ($tempTrigger): int {
            $leftTemp = self::isTemporaryObject($left);
            $rightTemp = self::isTemporaryObject($right);
            if ($tempTrigger && $leftTemp !== $rightTemp) {
                return $leftTemp ? -1 : 1;
            }
            if (!$tempTrigger && $leftTemp !== $rightTemp) {
                return $leftTemp ? 1 : -1;
            }

            return $left->rowId <=> $right->rowId;
        });

        return $matches[0];
    }

    /**
     * @return array{timing:string,schema:?string,table:string}
     */
    private static function parseTrigger(string $sql): array
    {
        $identifier = '(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)';
        if (!preg_match('/\bcreate\s+(?:temp(?:orary)?\s+)?trigger\s+(?:if\s+not\s+exists\s+)?(?:' . $identifier . '\s*\.\s*)?' . $identifier . '\s+(?:(before|after|instead\s+of)\s+)?(?:insert|delete|update)(?:\s+of\s+[^;]+?)?\s+on\s+(?:(?<schema>' . $identifier . ')\s*\.\s*)?(?<table>' . $identifier . ')/is', $sql, $matches)) {
            throw new InvalidArgumentException('SQLite trigger SQL must include a target table or view');
        }

        return [
            'timing' => isset($matches[1]) && $matches[1] !== '' ? strtolower((string) preg_replace('/\s+/', ' ', $matches[1])) : 'before',
            'schema' => isset($matches['schema']) && $matches['schema'] !== '' ? self::unquoteIdentifier($matches['schema']) : null,
            'table' => self::unquoteIdentifier($matches['table']),
        ];
    }

    /**
     * @return list<string>
     */
    private static function columnsForRecord(SQLiteSchemaRecord $record): array
    {
        if ($record->sql === null) {
            return [];
        }
        if (strtolower($record->type) === 'table') {
            return self::tableColumns($record->sql);
        }

        $explicit = self::viewColumns($record->sql);
        return $explicit !== [] ? $explicit : self::selectColumns($record->sql);
    }

    /**
     * @return list<string>
     */
    private static function tableColumns(string $sql): array
    {
        if (!preg_match('/\bcreate\s+(?:temp(?:orary)?\s+)?table\s+(?:if\s+not\s+exists\s+)?(?:["`\[]?[\w]+["`\]]?\s*\.\s*)?["`\[]?[\w]+["`\]]?\s*\((?<columns>.*)\)/is', $sql, $matches)) {
            return [];
        }

        $columns = [];
        foreach (self::splitCommaList($matches['columns']) as $definition) {
            $trimmed = ltrim($definition);
            if ($trimmed === '' || preg_match('/^(?:constraint|primary|foreign|unique|check)\b/i', $trimmed)) {
                continue;
            }
            if (preg_match('/^("[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)/', $trimmed, $column)) {
                $columns[] = self::unquoteIdentifier($column[1]);
            }
        }

        return $columns;
    }

    /**
     * @return list<string>
     */
    private static function viewColumns(string $sql): array
    {
        if (!preg_match('/\bcreate\s+(?:temp(?:orary)?\s+)?view\s+(?:if\s+not\s+exists\s+)?(?:["`\[]?[\w]+["`\]]?\s*\.\s*)?["`\[]?[\w]+["`\]]?\s*\((?<columns>[^)]*)\)/i', $sql, $matches)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (string $column): string => self::unquoteIdentifier(trim($column)), explode(',', $matches['columns']))));
    }

    /**
     * @return list<string>
     */
    private static function selectColumns(string $sql): array
    {
        if (!preg_match('/\bas\s+select\s+(?<select>.*?)\s+\bfrom\b/is', $sql, $matches)) {
            return [];
        }

        $columns = [];
        foreach (self::splitCommaList($matches['select']) as $expression) {
            $expression = trim($expression);
            if (preg_match('/\bas\s+(["`\[]?[\w ]+["`\]]?)$/i', $expression, $alias)) {
                $columns[] = self::unquoteIdentifier($alias[1]);
                continue;
            }
            if (preg_match('/(?:^|\.)(["`\[]?[\w]+["`\]]?)$/', $expression, $name)) {
                $columns[] = self::unquoteIdentifier($name[1]);
            }
        }

        return $columns;
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
     * @param list<string> $references
     * @param list<string> $columns
     * @return list<string>
     */
    private static function missingColumns(array $references, array $columns): array
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

    /**
     * @return list<string>
     */
    private static function bodyDependencies(string $sql): array
    {
        if (!preg_match('/\bbegin\b(?<body>.*)\bend\b/is', $sql, $matches)) {
            return [];
        }
        preg_match_all('/\b(?:from|join|update|into|delete\s+from)\s+(?:["`\[]?[\w]+["`\]]?\s*\.\s*)?(["`\[]?[\w]+["`\]]?)/i', $matches['body'], $refs);
        $dependencies = [];
        foreach ($refs[1] ?? [] as $name) {
            $dependencies[self::unquoteIdentifier($name)] = true;
        }

        return array_keys($dependencies);
    }

    /**
     * @return list<string>
     */
    private static function splitCommaList(string $text): array
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
                    $quote = null;
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
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($char === ',' && $depth === 0) {
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

    private static function isTemporaryObject(SQLiteSchemaRecord $record): bool
    {
        return (bool) ($record->sql !== null && preg_match('/\bcreate\s+temp(?:orary)?\s+/i', $record->sql));
    }

    private static function schemaOfRecord(SQLiteSchemaRecord $record): string
    {
        if (self::isTemporaryObject($record)) {
            return 'temp';
        }
        if ($record->sql !== null && preg_match('/\bcreate\s+(?:table|view|trigger)\s+(?:if\s+not\s+exists\s+)?(?<schema>"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)\s*\./i', $record->sql, $matches)) {
            return self::unquoteIdentifier($matches['schema']);
        }

        return 'main';
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
