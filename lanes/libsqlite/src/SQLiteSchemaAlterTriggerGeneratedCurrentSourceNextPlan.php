<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteSchemaAlterTriggerGeneratedCurrentSourceNextPlan
{
    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<string> $ddl
     * @param list<array{id:string,schema_cookie:int,sql:string,target?:string}> $preparedStatements
     * @param array<string,list<array<string,mixed>>> $currentRowsByTable
     * @return array<string,mixed>
     */
    public static function plan(
        array $records,
        array $ddl,
        int $schemaCookie = 133,
        string $schema = 'main',
        array $preparedStatements = [],
        array $currentRowsByTable = [],
    ): array {
        $beforeTable = self::targetTable($records, $ddl);
        $beforeColumns = self::tableColumns(self::tableSql($records, $beforeTable));
        $beforeTriggers = self::triggerSnapshots($records, $beforeTable, $beforeColumns, $schemaCookie);

        $reparse = SQLiteSchemaDdlReparsePlan::apply(
            $records,
            $ddl,
            $schemaCookie,
            $schema,
            $preparedStatements,
            $currentRowsByTable,
        );

        $afterTable = self::renamedTable($beforeTable, $reparse['operations']);
        $afterColumns = self::tableColumns(self::tableSql($reparse['records'], $afterTable));
        $afterTriggers = self::triggerSnapshots($reparse['records'], $afterTable, $afterColumns, $reparse['after_schema_cookie']);
        $transitions = self::triggerTransitions($beforeTriggers, $afterTriggers);
        $generatedAdded = array_values(array_diff(self::generatedNames($afterColumns), self::generatedNames($beforeColumns)));

        return [
            'operation' => 'schema-alter-trigger-generated-current-source',
            'status' => $transitions === [] ? 'stable' : 'trigger-reparse-required',
            'schema' => $reparse['schema'],
            'table_before' => $beforeTable,
            'table_after' => $afterTable,
            'schema_cookie_before' => $reparse['before_schema_cookie'],
            'schema_cookie_after' => $reparse['after_schema_cookie'],
            'schema_cookie_changed' => $reparse['schema_changed'],
            'ddl_operations' => array_column($reparse['operations'], 'kind'),
            'generated_before' => self::generatedNames($beforeColumns),
            'generated_after' => self::generatedNames($afterColumns),
            'generated_added' => $generatedAdded,
            'trigger_transitions' => $transitions,
            'reparse_triggers' => array_column($transitions, 'name'),
            'invalidated_prepared' => $reparse['invalidated_prepared'],
            'current_source_required' => ($reparse['schema_changed'] && $transitions !== []) || $reparse['invalidated_prepared'] !== [],
            'table_xinfo_after' => $reparse['pragma_samples']['table_xinfo:' . $afterTable]['rows'] ?? [],
            'dependency_closure' => 'no new support component needed; generated-trigger schema reparse composes native schema DDL reparse, generated-column metadata, and trigger current-source dependency analysis',
            'non_overlap' => 'avoids accepted generated-trigger reparse snapshots, standalone ALTER generated view/trigger helper, rename reparse, ADD COLUMN dependent record listing, and generated-index reparse; this slice tracks trigger current-source dependencies across ALTER-generated schema changes',
            'dependencies' => array_values(array_unique(array_merge($reparse['dependencies'], [
                'sqlite-trigger-generated-current-source',
                'sqlite-alter-table-generated-column-current-source',
            ]))),
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<string> $ddl
     */
    private static function targetTable(array $records, array $ddl): string
    {
        foreach ($ddl as $sql) {
            if (preg_match('/^\s*alter\s+table\s+(?<table>"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\b/i', $sql, $matches)) {
                return self::unquoteIdentifier($matches['table']);
            }
        }

        foreach ($records as $record) {
            if ($record->type === 'table') {
                return $record->name;
            }
        }

        throw new InvalidArgumentException('SQLite trigger generated reparse requires a target table');
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function tableSql(array $records, string $table): string
    {
        foreach ($records as $record) {
            if ($record->type === 'table' && strcasecmp($record->name, $table) === 0) {
                if ($record->sql === null || trim($record->sql) === '') {
                    break;
                }

                return $record->sql;
            }
        }

        throw new InvalidArgumentException("SQLite trigger generated reparse cannot find table SQL for {$table}");
    }

    /**
     * @param list<array<string,mixed>> $operations
     */
    private static function renamedTable(string $table, array $operations): string
    {
        $current = $table;
        foreach ($operations as $operation) {
            if (($operation['kind'] ?? null) === 'alter_table_rename' && strcasecmp((string) $operation['old_name'], $current) === 0) {
                $current = (string) $operation['new_name'];
            }
        }

        return $current;
    }

    /**
     * @return list<array{name:string,generated:bool}>
     */
    private static function tableColumns(string $sql): array
    {
        if (!preg_match('/\bcreate\s+(?:temp(?:orary)?\s+)?table\s+(?:if\s+not\s+exists\s+)?(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)(?:\s*\.\s*(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+))?\s*\((?<body>.*)\)/is', $sql, $matches)) {
            throw new InvalidArgumentException('SQLite trigger generated reparse requires CREATE TABLE SQL');
        }

        $columns = [];
        foreach (self::splitTopLevel($matches['body']) as $definition) {
            $definition = trim($definition);
            if ($definition === '' || preg_match('/^(?:constraint|primary|foreign|unique|check)\b/i', $definition)) {
                continue;
            }
            if (!preg_match('/^(?<name>"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)(?<tail>.*)$/s', $definition, $column)) {
                continue;
            }
            $columns[] = [
                'name' => self::unquoteIdentifier($column['name']),
                'generated' => preg_match('/\b(?:generated\s+always\s+)?as\s*\(/i', (string) $column['tail']) === 1,
            ];
        }

        return $columns;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<array{name:string,generated:bool}> $columns
     * @return array<string,array<string,mixed>>
     */
    private static function triggerSnapshots(array $records, string $table, array $columns, int $schemaCookie): array
    {
        $snapshots = [];
        foreach ($records as $record) {
            if ($record->type !== 'trigger' || strcasecmp($record->tableName, $table) !== 0 || $record->sql === null) {
                continue;
            }
            $references = self::triggerReferences($record->sql);
            $generated = self::matchingGenerated($references, $columns);
            $missing = array_values(array_udiff($references, array_column($columns, 'name'), 'strcasecmp'));
            $snapshots[$record->name] = [
                'name' => $record->name,
                'table' => $record->tableName,
                'schema_cookie' => $schemaCookie,
                'event' => self::triggerEvent($record->sql),
                'update_of' => self::updateOfColumns($record->sql),
                'new_references' => self::qualifiedReferences($record->sql, 'new'),
                'old_references' => self::qualifiedReferences($record->sql, 'old'),
                'all_references' => $references,
                'generated_references' => $generated,
                'missing_references' => $missing,
                'status' => $missing === [] ? 'resolved' : 'unresolved',
            ];
        }

        return $snapshots;
    }

    /**
     * @param array<string,array<string,mixed>> $before
     * @param array<string,array<string,mixed>> $after
     * @return list<array<string,mixed>>
     */
    private static function triggerTransitions(array $before, array $after): array
    {
        $transitions = [];
        foreach ($after as $name => $next) {
            $current = $before[$name] ?? null;
            if ($current === null) {
                continue;
            }
            $addedGeneratedRefs = array_values(array_udiff(
                $next['generated_references'],
                $current['generated_references'],
                'strcasecmp',
            ));
            $resolvedMissing = array_values(array_uintersect(
                $current['missing_references'],
                $next['generated_references'],
                'strcasecmp',
            ));
            if ($addedGeneratedRefs === [] && $resolvedMissing === [] && $current['table'] === $next['table']) {
                continue;
            }
            $transitions[] = [
                'name' => $name,
                'event' => $next['event'],
                'table_before' => $current['table'],
                'table_after' => $next['table'],
                'schema_cookie_before' => $current['schema_cookie'],
                'schema_cookie_after' => $next['schema_cookie'],
                'current_status' => $current['status'],
                'next_status' => $next['status'],
                'update_of_before' => $current['update_of'],
                'update_of_after' => $next['update_of'],
                'generated_before' => $current['generated_references'],
                'generated_after' => $next['generated_references'],
                'generated_added_to_trigger' => $addedGeneratedRefs,
                'resolved_missing_generated' => $resolvedMissing,
                'new_references_after' => $next['new_references'],
                'old_references_after' => $next['old_references'],
                'reprepare_reason' => 'schema-cookie-generated-trigger-current-source',
            ];
        }

        return $transitions;
    }

    /**
     * @param list<array{name:string,generated:bool}> $columns
     * @return list<string>
     */
    private static function generatedNames(array $columns): array
    {
        return array_values(array_map(
            static fn (array $column): string => $column['name'],
            array_values(array_filter($columns, static fn (array $column): bool => $column['generated'])),
        ));
    }

    /**
     * @param list<string> $references
     * @param list<array{name:string,generated:bool}> $columns
     * @return list<string>
     */
    private static function matchingGenerated(array $references, array $columns): array
    {
        $generated = self::generatedNames($columns);
        $out = [];
        foreach ($references as $reference) {
            foreach ($generated as $name) {
                if (strcasecmp($reference, $name) === 0) {
                    $out[] = $name;
                    break;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<string>
     */
    private static function triggerReferences(string $sql): array
    {
        return array_values(array_unique(array_merge(
            self::updateOfColumns($sql),
            self::qualifiedReferences($sql, 'new'),
            self::qualifiedReferences($sql, 'old'),
        )));
    }

    /**
     * @return list<string>
     */
    private static function updateOfColumns(string $sql): array
    {
        if (!preg_match('/\bupdate\s+of\s+(?<columns>.*?)\s+on\b/is', $sql, $matches)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $column): string => self::unquoteIdentifier($column),
            self::splitTopLevel($matches['columns']),
        ), static fn (string $column): bool => $column !== ''));
    }

    /**
     * @return list<string>
     */
    private static function qualifiedReferences(string $sql, string $qualifier): array
    {
        preg_match_all('/\b' . preg_quote($qualifier, '/') . '\s*\.\s*("[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)/i', $sql, $matches);
        return array_values(array_unique(array_map(
            static fn (string $token): string => self::unquoteIdentifier($token),
            $matches[1] ?? [],
        )));
    }

    private static function triggerEvent(string $sql): string
    {
        if (preg_match('/\b(insert|update|delete)\b/i', $sql, $matches)) {
            return strtolower($matches[1]);
        }

        return 'unknown';
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $value): array
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
            if ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $parts[] = trim($current);

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
}
