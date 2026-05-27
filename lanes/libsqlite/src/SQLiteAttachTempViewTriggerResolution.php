<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteAttachTempViewTriggerResolution
{
    /**
     * @return array{schema:string,record:SQLiteSchemaRecord}
     */
    public static function resolveTrigger(SQLiteAttachedSchemaCatalog $catalog, string $triggerName): array
    {
        $qualified = self::splitQualifiedName($triggerName);
        $schemas = $qualified['schema'] !== '' ? [$qualified['schema']] : $catalog->searchOrder();

        foreach ($schemas as $schema) {
            foreach ($catalog->schemaRecords($schema) as $record) {
                if (strtolower($record->type) === 'trigger' && strcasecmp($record->name, $qualified['name']) === 0) {
                    return ['schema' => $schema, 'record' => $record];
                }
            }
        }

        throw new InvalidArgumentException("SQLite trigger does not exist: {$triggerName}");
    }

    /**
     * @return array{trigger:string,triggerSchema:string,triggerTemporary:bool,target:string,targetSchema:string,targetType:string,targetTemporary:bool,insteadOf:bool,columns:list<string>,referencedNew:list<string>,referencedOld:list<string>,missingNew:list<string>,missingOld:list<string>,bodyDependencies:list<array{schema:?string,name:string}>,status:string}
     */
    public static function resolve(SQLiteAttachedSchemaCatalog $catalog, string $triggerName): array
    {
        $trigger = self::resolveTrigger($catalog, $triggerName);
        $record = $trigger['record'];
        if ($record->sql === null || trim($record->sql) === '') {
            throw new InvalidArgumentException('SQLite attached trigger resolution requires CREATE TRIGGER SQL');
        }

        $parsed = self::parseTrigger($record->sql);
        $triggerTemporary = $trigger['schema'] === 'temp' || self::isTemporaryObject($record);
        $target = self::resolveTarget($catalog, $parsed['target'], $trigger['schema'], $triggerTemporary);
        $columns = self::columnsForRecord($target['record']);
        $new = self::pseudoColumns($record->sql, 'new');
        $old = self::pseudoColumns($record->sql, 'old');
        $missingNew = self::missingColumns($new, $columns);
        $missingOld = self::missingColumns($old, $columns);

        return [
            'trigger' => $record->name,
            'triggerSchema' => $trigger['schema'],
            'triggerTemporary' => $triggerTemporary,
            'target' => $target['record']->name,
            'targetSchema' => $target['schema'],
            'targetType' => strtolower($target['record']->type),
            'targetTemporary' => $target['schema'] === 'temp' || self::isTemporaryObject($target['record']),
            'insteadOf' => $parsed['timing'] === 'instead of',
            'columns' => $columns,
            'referencedNew' => $new,
            'referencedOld' => $old,
            'missingNew' => $missingNew,
            'missingOld' => $missingOld,
            'bodyDependencies' => self::bodyDependencies($record->sql),
            'status' => $missingNew === [] && $missingOld === [] ? 'resolved' : 'unresolved',
        ];
    }

    /**
     * @return array{resolved:int,unresolved:int,tempTriggers:int,tempTargets:int,attachedTargets:array<string,int>,missingReferences:array<string,array{new:list<string>,old:list<string>}>}
     */
    public static function summary(SQLiteAttachedSchemaCatalog $catalog): array
    {
        $resolved = 0;
        $unresolved = 0;
        $tempTriggers = 0;
        $tempTargets = 0;
        $attachedTargets = [];
        $missing = [];

        foreach ($catalog->searchOrder() as $schema) {
            foreach ($catalog->schemaRecords($schema) as $record) {
                if (strtolower($record->type) !== 'trigger') {
                    continue;
                }
                $trigger = self::resolve($catalog, $schema . '.' . $record->name);
                if ($trigger['status'] === 'resolved') {
                    ++$resolved;
                } else {
                    ++$unresolved;
                    $missing[$trigger['trigger']] = ['new' => $trigger['missingNew'], 'old' => $trigger['missingOld']];
                }
                if ($trigger['triggerTemporary']) {
                    ++$tempTriggers;
                }
                if ($trigger['targetTemporary']) {
                    ++$tempTargets;
                }
                if (!in_array($trigger['targetSchema'], ['main', 'temp'], true)) {
                    $attachedTargets[$trigger['targetSchema']] = ($attachedTargets[$trigger['targetSchema']] ?? 0) + 1;
                }
            }
        }
        ksort($attachedTargets);

        return [
            'resolved' => $resolved,
            'unresolved' => $unresolved,
            'tempTriggers' => $tempTriggers,
            'tempTargets' => $tempTargets,
            'attachedTargets' => $attachedTargets,
            'missingReferences' => $missing,
        ];
    }

    /**
     * @return array{schema:string,name:string}
     */
    private static function splitQualifiedName(string $name): array
    {
        $parts = preg_split('/\s*\.\s*/', trim($name), 2);
        if ($parts === false || $parts === [] || trim($parts[0]) === '') {
            throw new InvalidArgumentException('SQLite schema object name cannot be empty');
        }
        if (count($parts) === 1) {
            return ['schema' => '', 'name' => self::unquoteIdentifier($parts[0])];
        }

        return ['schema' => strtolower(self::unquoteIdentifier($parts[0])), 'name' => self::unquoteIdentifier($parts[1])];
    }

    /**
     * @return array{timing:string,target:array{schema:string,name:string}}
     */
    private static function parseTrigger(string $sql): array
    {
        if (!preg_match('/\bcreate\s+(?:temp(?:orary)?\s+)?trigger\s+(?:if\s+not\s+exists\s+)?(?:["`\[]?[\w]+["`\]]?\s*\.\s*)?["`\[]?[\w]+["`\]]?\s+(?:(before|after|instead\s+of)\s+)?(?:insert|delete|update)(?:\s+of\s+[^;]+?)?\s+on\s+(?:(["`\[]?[\w]+["`\]]?)\s*\.\s*)?(["`\[]?[\w]+["`\]]?)/is', $sql, $matches)) {
            throw new InvalidArgumentException('SQLite trigger SQL must include a target table or view');
        }

        return [
            'timing' => isset($matches[1]) && $matches[1] !== '' ? strtolower((string) preg_replace('/\s+/', ' ', $matches[1])) : 'before',
            'target' => [
                'schema' => isset($matches[2]) && $matches[2] !== '' ? strtolower(self::unquoteIdentifier($matches[2])) : '',
                'name' => self::unquoteIdentifier($matches[3]),
            ],
        ];
    }

    /**
     * @param array{schema:string,name:string} $target
     * @return array{schema:string,record:SQLiteSchemaRecord}
     */
    private static function resolveTarget(SQLiteAttachedSchemaCatalog $catalog, array $target, string $triggerSchema, bool $tempTrigger): array
    {
        $schemas = $target['schema'] !== ''
            ? [$target['schema']]
            : ($tempTrigger ? $catalog->searchOrder() : [$triggerSchema]);

        foreach ($schemas as $schema) {
            foreach ($catalog->schemaRecords($schema) as $record) {
                if (in_array(strtolower($record->type), ['table', 'view'], true) && strcasecmp($record->name, $target['name']) === 0) {
                    return ['schema' => $schema, 'record' => $record];
                }
            }
        }

        throw new InvalidArgumentException("SQLite trigger target does not resolve: {$target['name']}");
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
        preg_match_all('/\b' . preg_quote($prefix, '/') . '\s*\.\s*(["`\[]?[\w ]+["`\]]?)/i', $sql, $matches);
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
            $key = ($schema ?? '') . '.' . strtolower($name);
            $dependencies[$key] = ['schema' => $schema, 'name' => $name];
        }

        return array_values($dependencies);
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
            if ($char === '"' || $char === '\'' || $char === '`') {
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

    private static function isTemporaryObject(SQLiteSchemaRecord $record): bool
    {
        return (bool) ($record->sql !== null && preg_match('/\bcreate\s+temp(?:orary)?\s+/i', $record->sql));
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
