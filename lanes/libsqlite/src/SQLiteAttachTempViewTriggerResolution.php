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
     * @return array{trigger:string,triggerSchema:string,target:string,targetSchema:string,targetForeignKeys:list<array<string,mixed>>,bodyForeignKeys:list<array<string,mixed>>,foreignKeySchemas:list<string>,crossSchemaReferences:list<array<string,mixed>>,status:string}
     */
    public static function foreignKeyContext(SQLiteAttachedSchemaCatalog $catalog, string $triggerName): array
    {
        $resolved = self::resolve($catalog, $triggerName);
        $trigger = self::resolveTrigger($catalog, $triggerName);
        $triggerTemporary = $resolved['triggerTemporary'];
        $targetRecord = self::recordInSchema($catalog, $resolved['targetSchema'], $resolved['target']);
        $targetForeignKeys = self::foreignKeysForRecord($targetRecord, $resolved['targetSchema'], 'target');
        $bodyForeignKeys = [];

        foreach ($resolved['bodyDependencies'] as $dependency) {
            $dependencyRecord = self::resolveDependencyRecord($catalog, $dependency, $trigger['schema'], $triggerTemporary);
            if ($dependencyRecord === null) {
                continue;
            }
            foreach (self::foreignKeysForRecord($dependencyRecord['record'], $dependencyRecord['schema'], 'body') as $foreignKey) {
                $bodyForeignKeys[] = $foreignKey;
            }
        }

        $all = array_merge($targetForeignKeys, $bodyForeignKeys);
        $schemas = [];
        $crossSchema = [];
        foreach ($all as $foreignKey) {
            $schemas[$foreignKey['childSchema']] = true;
            if ($foreignKey['childSchema'] !== $foreignKey['parentSchema']) {
                $crossSchema[] = $foreignKey;
            }
        }
        ksort($schemas);

        return [
            'trigger' => $resolved['trigger'],
            'triggerSchema' => $resolved['triggerSchema'],
            'target' => $resolved['target'],
            'targetSchema' => $resolved['targetSchema'],
            'targetForeignKeys' => $targetForeignKeys,
            'bodyForeignKeys' => $bodyForeignKeys,
            'foreignKeySchemas' => array_keys($schemas),
            'crossSchemaReferences' => $crossSchema,
            'status' => $resolved['status'] === 'resolved' && $crossSchema === [] ? 'resolved' : 'unresolved',
        ];
    }

    /**
     * Compare the current prepared trigger source with the next schema catalog
     * that a connection sees after ATTACH, temp DDL, or WAL-backed schema DDL.
     *
     * @return array{trigger:string,current:array<string,mixed>,next:array<string,mixed>,changed:bool,changedFields:list<string>,requiresReprepare:bool,walSchemas:list<string>,tempSchemas:list<string>,attachedSchemas:list<string>,invalidatedSources:list<string>,sqliteResultOnCurrentStep:string,nextStepAction:string,status:string}
     */
    public static function currentNextSourcePlan(SQLiteAttachedSchemaCatalog $current, SQLiteAttachedSchemaCatalog $next, string $triggerName): array
    {
        $currentSource = self::sourceSnapshot($current, $triggerName);
        $nextSource = self::nextSourceSnapshot($next, $triggerName);
        $changedFields = [];

        foreach (['exists', 'triggerSchema', 'triggerTemporary', 'target', 'targetSchema', 'targetType', 'targetTemporary', 'insteadOf', 'columns', 'bodyDependencies', 'status'] as $field) {
            if (($currentSource[$field] ?? null) !== ($nextSource[$field] ?? null)) {
                $changedFields[] = $field;
            }
        }

        $sourceSchemas = array_values(array_unique(array_merge(
            array_values(array_filter([$currentSource['triggerSchema'], $currentSource['targetSchema'], $nextSource['triggerSchema'] ?? null, $nextSource['targetSchema'] ?? null], static fn (mixed $schema): bool => is_string($schema) && $schema !== '')),
            self::dependencySchemas($currentSource['bodyDependencies']),
            self::dependencySchemas($nextSource['bodyDependencies'] ?? []),
            ($nextSource['missingSchema'] ?? null) === null ? [] : [$nextSource['missingSchema']],
        )));
        sort($sourceSchemas);

        $walSchemas = array_values(array_filter($sourceSchemas, static fn (string $schema): bool => $schema !== 'temp'));
        $tempSchemas = array_values(array_filter($sourceSchemas, static fn (string $schema): bool => $schema === 'temp'));
        $attachedSchemas = array_values(array_filter($sourceSchemas, static fn (string $schema): bool => !in_array($schema, ['main', 'temp'], true)));

        return [
            'trigger' => $currentSource['trigger'],
            'current' => $currentSource,
            'next' => $nextSource,
            'changed' => $changedFields !== [],
            'changedFields' => $changedFields,
            'requiresReprepare' => $changedFields !== [],
            'walSchemas' => $walSchemas,
            'tempSchemas' => $tempSchemas,
            'attachedSchemas' => $attachedSchemas,
            'invalidatedSources' => $changedFields === [] ? [] : $sourceSchemas,
            'sqliteResultOnCurrentStep' => 'SQLITE_OK',
            'nextStepAction' => $changedFields === [] ? 'continue-current-program' : 'abort-reset-and-reprepare',
            'status' => $changedFields === [] ? 'stable' : 'reprepare-required',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function sourceSnapshot(SQLiteAttachedSchemaCatalog $catalog, string $triggerName): array
    {
        $resolved = self::resolve($catalog, $triggerName);

        return [
            'trigger' => $resolved['trigger'],
            'exists' => true,
            'triggerSchema' => $resolved['triggerSchema'],
            'triggerTemporary' => $resolved['triggerTemporary'],
            'target' => $resolved['target'],
            'targetSchema' => $resolved['targetSchema'],
            'targetType' => $resolved['targetType'],
            'targetTemporary' => $resolved['targetTemporary'],
            'insteadOf' => $resolved['insteadOf'],
            'columns' => $resolved['columns'],
            'bodyDependencies' => $resolved['bodyDependencies'],
            'status' => $resolved['status'],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function nextSourceSnapshot(SQLiteAttachedSchemaCatalog $catalog, string $triggerName): array
    {
        try {
            return self::sourceSnapshot($catalog, $triggerName);
        } catch (InvalidArgumentException $exception) {
            $qualified = self::splitQualifiedName($triggerName);
            return [
                'trigger' => $qualified['name'],
                'exists' => false,
                'triggerSchema' => $qualified['schema'] !== '' ? $qualified['schema'] : null,
                'triggerTemporary' => false,
                'target' => null,
                'targetSchema' => null,
                'targetType' => null,
                'targetTemporary' => false,
                'insteadOf' => false,
                'columns' => [],
                'bodyDependencies' => [],
                'status' => 'missing',
                'missingReason' => $exception->getMessage(),
                'missingSchema' => $qualified['schema'] !== '' ? $qualified['schema'] : null,
            ];
        }
    }

    /**
     * @param list<array{schema:?string,name:string}> $dependencies
     * @return list<string>
     */
    private static function dependencySchemas(array $dependencies): array
    {
        $schemas = [];
        foreach ($dependencies as $dependency) {
            $schemas[] = $dependency['schema'] ?? 'unqualified';
        }

        return array_values(array_filter($schemas, static fn (string $schema): bool => $schema !== 'unqualified'));
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
     * @param array{schema:?string,name:string} $dependency
     * @return ?array{schema:string,record:SQLiteSchemaRecord}
     */
    private static function resolveDependencyRecord(SQLiteAttachedSchemaCatalog $catalog, array $dependency, string $triggerSchema, bool $tempTrigger): ?array
    {
        $schemas = $dependency['schema'] !== null
            ? [$dependency['schema']]
            : ($tempTrigger ? $catalog->searchOrder() : [$triggerSchema]);

        foreach ($schemas as $schema) {
            foreach ($catalog->schemaRecords($schema) as $record) {
                if (strtolower($record->type) === 'table' && strcasecmp($record->name, $dependency['name']) === 0) {
                    return ['schema' => $schema, 'record' => $record];
                }
            }
        }

        return null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function foreignKeysForRecord(SQLiteSchemaRecord $record, string $schema, string $source): array
    {
        if ($record->sql === null || strtolower($record->type) !== 'table') {
            return [];
        }
        if (!preg_match('/\bcreate\s+(?:temp(?:orary)?\s+)?table\s+(?:if\s+not\s+exists\s+)?(?:["`\[]?[\w]+["`\]]?\s*\.\s*)?["`\[]?[\w]+["`\]]?\s*\((?<columns>.*)\)/is', $record->sql, $matches)) {
            return [];
        }

        $foreignKeys = [];
        foreach (self::splitCommaList($matches['columns']) as $definition) {
            $trimmed = trim($definition);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^(?:constraint\s+(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)\s+)?foreign\s+key\s*\((?<child>[^)]*)\)\s+references\s+(?:(?<parentSchema>["`\[]?[\w]+["`\]]?)\s*\.\s*)?(?<parent>["`\[]?[\w]+["`\]]?)\s*(?:\((?<parentColumns>[^)]*)\))?(?<tail>.*)$/is', $trimmed, $fk)) {
                $foreignKeys[] = self::foreignKeyRow(
                    $schema,
                    $record->name,
                    self::identifierList($fk['child']),
                    isset($fk['parentSchema']) && $fk['parentSchema'] !== '' ? strtolower(self::unquoteIdentifier($fk['parentSchema'])) : $schema,
                    self::unquoteIdentifier($fk['parent']),
                    isset($fk['parentColumns']) && $fk['parentColumns'] !== '' ? self::identifierList($fk['parentColumns']) : [],
                    (string) ($fk['tail'] ?? ''),
                    $source,
                );
                continue;
            }

            if (preg_match('/^(?<column>"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+).*?\breferences\s+(?:(?<parentSchema>["`\[]?[\w]+["`\]]?)\s*\.\s*)?(?<parent>["`\[]?[\w]+["`\]]?)\s*(?:\((?<parentColumns>[^)]*)\))?(?<tail>.*)$/is', $trimmed, $fk)) {
                $foreignKeys[] = self::foreignKeyRow(
                    $schema,
                    $record->name,
                    [self::unquoteIdentifier($fk['column'])],
                    isset($fk['parentSchema']) && $fk['parentSchema'] !== '' ? strtolower(self::unquoteIdentifier($fk['parentSchema'])) : $schema,
                    self::unquoteIdentifier($fk['parent']),
                    isset($fk['parentColumns']) && $fk['parentColumns'] !== '' ? self::identifierList($fk['parentColumns']) : [],
                    (string) ($fk['tail'] ?? ''),
                    $source,
                );
            }
        }

        return $foreignKeys;
    }

    /**
     * @param list<string> $childColumns
     * @param list<string> $parentColumns
     * @return array<string,mixed>
     */
    private static function foreignKeyRow(string $childSchema, string $childTable, array $childColumns, string $parentSchema, string $parentTable, array $parentColumns, string $tail, string $source): array
    {
        return [
            'source' => $source,
            'childSchema' => $childSchema,
            'childTable' => $childTable,
            'childColumns' => $childColumns,
            'parentSchema' => $parentSchema,
            'parentTable' => $parentTable,
            'parentColumns' => $parentColumns,
            'onUpdate' => self::foreignKeyAction($tail, 'update'),
            'onDelete' => self::foreignKeyAction($tail, 'delete'),
            'deferred' => (bool) preg_match('/\bdeferrable\b/i', $tail) && !preg_match('/\bnot\s+deferrable\b/i', $tail),
        ];
    }

    /**
     * @return list<string>
     */
    private static function identifierList(string $value): array
    {
        return array_values(array_filter(array_map(static fn (string $part): string => self::unquoteIdentifier(trim($part)), self::splitCommaList($value)), static fn (string $part): bool => $part !== ''));
    }

    private static function foreignKeyAction(string $tail, string $event): string
    {
        if (!preg_match('/\bon\s+' . preg_quote($event, '/') . '\s+(set\s+null|set\s+default|cascade|restrict|no\s+action)\b/i', $tail, $matches)) {
            return 'NO ACTION';
        }

        return strtoupper((string) preg_replace('/\s+/', ' ', $matches[1]));
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
