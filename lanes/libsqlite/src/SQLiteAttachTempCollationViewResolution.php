<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteAttachTempCollationViewResolution
{
    /**
     * @param list<string> $availableCollations
     * @return array{view:string,viewSchema:string,temporary:bool,columns:list<string>,sourceReferences:list<array{schema:?string,name:string}>,resolvedSources:list<array{schema:string,name:string,type:string,temporary:bool}>,collations:list<string>,missingCollations:list<string>,crossSchemaReferences:list<array{schema:string,name:string,type:string,temporary:bool}>,status:string}
     */
    public static function resolve(SQLiteAttachedSchemaCatalog $catalog, string $viewName, array $availableCollations = ['BINARY', 'NOCASE', 'RTRIM']): array
    {
        $view = self::resolveView($catalog, $viewName);
        $record = $view['record'];
        if ($record->sql === null || trim($record->sql) === '') {
            throw new InvalidArgumentException('SQLite view resolution requires CREATE VIEW SQL');
        }

        $temporary = $view['schema'] === 'temp' || self::isTemporaryView($record);
        $columns = self::viewColumns($record->sql);
        $sourceReferences = self::sourceReferences($record->sql);
        $resolvedSources = [];
        $crossSchema = [];

        foreach ($sourceReferences as $reference) {
            $resolved = self::resolveSource($catalog, $reference, $view['schema'], $temporary);
            $source = [
                'schema' => $resolved['schema'],
                'name' => $resolved['record']->name,
                'type' => strtolower($resolved['record']->type),
                'temporary' => $resolved['schema'] === 'temp' || self::isTemporaryView($resolved['record']),
            ];
            $resolvedSources[] = $source;
            if (!$temporary && $source['schema'] !== $view['schema']) {
                $crossSchema[] = $source;
            }
        }

        $collations = self::collations($record->sql);
        $available = array_fill_keys(array_map('strtoupper', $availableCollations), true);
        $missingCollations = array_values(array_filter(
            $collations,
            static fn (string $collation): bool => !isset($available[strtoupper($collation)]),
        ));

        return [
            'view' => $record->name,
            'viewSchema' => $view['schema'],
            'temporary' => $temporary,
            'columns' => $columns,
            'sourceReferences' => $sourceReferences,
            'resolvedSources' => $resolvedSources,
            'collations' => $collations,
            'missingCollations' => $missingCollations,
            'crossSchemaReferences' => $crossSchema,
            'status' => $missingCollations === [] && $crossSchema === [] ? 'resolved' : 'unresolved',
        ];
    }

    /**
     * @param list<string> $availableCollations
     * @return array{resolved:int,unresolved:int,tempViews:int,attachedViews:int,collations:array<string,int>,crossSchemaViews:array<string,list<string>>,missingCollationViews:array<string,list<string>>}
     */
    public static function summary(SQLiteAttachedSchemaCatalog $catalog, array $availableCollations = ['BINARY', 'NOCASE', 'RTRIM']): array
    {
        $resolved = 0;
        $unresolved = 0;
        $tempViews = 0;
        $attachedViews = 0;
        $collations = [];
        $crossSchemaViews = [];
        $missingCollationViews = [];

        foreach ($catalog->searchOrder() as $schema) {
            foreach ($catalog->schemaRecords($schema) as $record) {
                if (strtolower($record->type) !== 'view') {
                    continue;
                }
                $view = self::resolve($catalog, $schema . '.' . $record->name, $availableCollations);
                if ($view['status'] === 'resolved') {
                    ++$resolved;
                } else {
                    ++$unresolved;
                }
                if ($view['temporary']) {
                    ++$tempViews;
                }
                if (!in_array($view['viewSchema'], ['main', 'temp'], true)) {
                    ++$attachedViews;
                }
                foreach ($view['collations'] as $collation) {
                    $collations[$collation] = ($collations[$collation] ?? 0) + 1;
                }
                if ($view['crossSchemaReferences'] !== []) {
                    $crossSchemaViews[$view['viewSchema'] . '.' . $view['view']] = array_map(
                        static fn (array $source): string => $source['schema'] . '.' . $source['name'],
                        $view['crossSchemaReferences'],
                    );
                }
                if ($view['missingCollations'] !== []) {
                    $missingCollationViews[$view['viewSchema'] . '.' . $view['view']] = $view['missingCollations'];
                }
            }
        }
        ksort($collations);
        ksort($crossSchemaViews);
        ksort($missingCollationViews);

        return [
            'resolved' => $resolved,
            'unresolved' => $unresolved,
            'tempViews' => $tempViews,
            'attachedViews' => $attachedViews,
            'collations' => $collations,
            'crossSchemaViews' => $crossSchemaViews,
            'missingCollationViews' => $missingCollationViews,
        ];
    }

    /**
     * @return array{schema:string,record:SQLiteSchemaRecord}
     */
    private static function resolveView(SQLiteAttachedSchemaCatalog $catalog, string $viewName): array
    {
        $qualified = self::splitQualifiedName($viewName);
        $schemas = $qualified['schema'] !== '' ? [$qualified['schema']] : $catalog->searchOrder();

        foreach ($schemas as $schema) {
            foreach ($catalog->schemaRecords($schema) as $record) {
                if (strtolower($record->type) === 'view' && strcasecmp($record->name, $qualified['name']) === 0) {
                    return ['schema' => $schema, 'record' => $record];
                }
            }
        }

        throw new InvalidArgumentException("SQLite view does not exist: {$viewName}");
    }

    /**
     * @param array{schema:?string,name:string} $reference
     * @return array{schema:string,record:SQLiteSchemaRecord}
     */
    private static function resolveSource(SQLiteAttachedSchemaCatalog $catalog, array $reference, string $viewSchema, bool $temporary): array
    {
        if ($reference['schema'] !== null) {
            $resolved = $catalog->resolveTable($reference['schema'] . '.' . $reference['name']);
            if ($resolved === null) {
                throw new InvalidArgumentException("SQLite view source does not resolve: {$reference['schema']}.{$reference['name']}");
            }

            return $resolved;
        }

        if ($temporary) {
            $resolved = $catalog->resolveTable($reference['name']);
            if ($resolved === null) {
                throw new InvalidArgumentException("SQLite view source does not resolve: {$reference['name']}");
            }

            return $resolved;
        }

        $resolved = $catalog->resolveTable($viewSchema . '.' . $reference['name']);
        if ($resolved === null) {
            throw new InvalidArgumentException("SQLite view source does not resolve: {$viewSchema}.{$reference['name']}");
        }

        return $resolved;
    }

    /**
     * @return list<string>
     */
    private static function viewColumns(string $sql): array
    {
        if (preg_match('/\bcreate\s+(?:temp(?:orary)?\s+)?view\s+(?:if\s+not\s+exists\s+)?(?:["`\[]?[\w]+["`\]]?\s*\.\s*)?["`\[]?[\w]+["`\]]?\s*\((?<columns>[^)]*)\)/i', $sql, $matches) === 1) {
            return self::identifierList($matches['columns']);
        }
        if (preg_match('/\bas\s+select\s+(?<select>.*?)\s+\bfrom\b/is', $sql, $matches) !== 1) {
            return [];
        }

        $columns = [];
        foreach (self::splitCommaList($matches['select']) as $expression) {
            $expression = trim($expression);
            if (preg_match('/\bas\s+(["`\[]?[\w ]+["`\]]?)$/i', $expression, $alias) === 1) {
                $columns[] = self::unquoteIdentifier($alias[1]);
                continue;
            }
            if (preg_match('/(?:^|\.)(["`\[]?[\w]+["`\]]?)$/', $expression, $name) === 1) {
                $columns[] = self::unquoteIdentifier($name[1]);
            }
        }

        return $columns;
    }

    /**
     * @return list<array{schema:?string,name:string}>
     */
    private static function sourceReferences(string $sql): array
    {
        if (preg_match('/\bas\s+select\b(?<select>.*)$/is', $sql, $matches) !== 1) {
            throw new InvalidArgumentException('SQLite view SQL must include SELECT body');
        }

        preg_match_all('/\b(?:from|join)\s+(?:(["`\[]?[\w]+["`\]]?)\s*\.\s*)?(["`\[]?[\w]+["`\]]?)(?:\s+(?:as\s+)?["`\[]?[\w]+["`\]]?)?/i', $matches['select'], $refs, PREG_SET_ORDER);
        $references = [];
        foreach ($refs as $ref) {
            $schema = isset($ref[1]) && $ref[1] !== '' ? strtolower(self::unquoteIdentifier($ref[1])) : null;
            $name = self::unquoteIdentifier($ref[2]);
            $key = ($schema ?? '') . '.' . strtolower($name);
            $references[$key] = ['schema' => $schema, 'name' => $name];
        }

        return array_values($references);
    }

    /**
     * @return list<string>
     */
    private static function collations(string $sql): array
    {
        preg_match_all('/\bcollate\s+(["`\[]?[\w]+["`\]]?)/i', $sql, $matches);
        $collations = [];
        foreach ($matches[1] ?? [] as $collation) {
            $collations[strtoupper(self::unquoteIdentifier($collation))] = true;
        }

        return array_keys($collations);
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
     * @return list<string>
     */
    private static function identifierList(string $value): array
    {
        return array_values(array_filter(array_map(
            static fn (string $part): string => self::unquoteIdentifier(trim($part)),
            self::splitCommaList($value),
        ), static fn (string $part): bool => $part !== ''));
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

    private static function isTemporaryView(SQLiteSchemaRecord $record): bool
    {
        return strtolower($record->type) === 'view'
            && $record->sql !== null
            && preg_match('/\bcreate\s+temp(?:orary)?\s+view\b/i', $record->sql) === 1;
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
