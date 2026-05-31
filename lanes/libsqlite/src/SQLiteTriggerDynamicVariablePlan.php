<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerDynamicVariablePlan
{
    /**
     * @param list<array{name:string,sql:string,temp?:bool}> $definitions
     * @return array{accepted:list<array{name:string,temp:bool>>,rejected:list<array{name:string,temp:bool,reason:string,position:int}>}
     */
    public static function createDefinitions(array $definitions): array
    {
        $accepted = [];
        $rejected = [];

        foreach ($definitions as $definition) {
            $name = self::identifier((string) ($definition['name'] ?? ''), 'trigger name');
            $sql = (string) ($definition['sql'] ?? '');
            $temp = (bool) ($definition['temp'] ?? false);
            $position = self::firstVariablePosition($sql);

            if ($position !== null) {
                $rejected[] = [
                    'name' => $name,
                    'temp' => $temp,
                    'reason' => 'trigger cannot use variables',
                    'position' => $position,
                ];
                continue;
            }

            $accepted[] = ['name' => $name, 'temp' => $temp];
        }

        return ['accepted' => $accepted, 'rejected' => $rejected];
    }

    /**
     * @param list<array{name:string,table:string,sql:string}> $storedTriggers
     * @param list<array<string,mixed>> $events
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{tables:array<string,list<array<string,mixed>>>,events:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function replayStoredSchema(array $storedTriggers, array $events, array $tables): array
    {
        $triggerEffects = [];

        foreach ($events as $event) {
            $targetTable = self::identifier((string) ($event['table'] ?? ''), 'event table');
            $eventRow = self::row($event['row'] ?? [], 'event row');

            foreach ($storedTriggers as $trigger) {
                if (self::identifier((string) ($trigger['table'] ?? ''), 'trigger table') !== $targetTable) {
                    continue;
                }

                $name = self::identifier((string) ($trigger['name'] ?? ''), 'trigger name');
                $sql = (string) ($trigger['sql'] ?? '');
                $normalized = self::variablesAsNull($sql);

                if (!self::whenAllows($normalized, $tables)) {
                    $triggerEffects[] = ['trigger' => $name, 'action' => 'skipped-null-when'];
                    continue;
                }

                $effect = self::applyTriggerBody($normalized, $eventRow, $tables);
                $tables = $effect['tables'];
                $triggerEffects[] = [
                    'trigger' => $name,
                    'action' => $effect['action'],
                    'variables_as_null' => $normalized !== $sql,
                ];
            }
        }

        return [
            'tables' => $tables,
            'events' => $events,
            'trigger_effects' => $triggerEffects,
            'dependencies' => [
                'sqlite-triggerE-variable-rejection',
                'sqlite-triggerE-stored-schema-variables-as-null',
                'sqlite-trigger-dynamic-current-source',
            ],
        ];
    }

    private static function firstVariablePosition(string $sql): ?int
    {
        if (preg_match('/(?:\?[0-9]*|[$:@](?:[A-Za-z_][A-Za-z0-9_]*|[0-9]+))/', $sql, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return (int) $match[0][1];
    }

    private static function variablesAsNull(string $sql): string
    {
        return preg_replace('/(?:\?[0-9]*|[$:@](?:[A-Za-z_][A-Za-z0-9_]*|[0-9]+))/', 'NULL', $sql) ?? $sql;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     */
    private static function whenAllows(string $sql, array $tables): bool
    {
        if (preg_match('/\bWHEN\s+NULL\s+IS\s+NULL\b/i', $sql) === 1) {
            return true;
        }

        if (preg_match('/\bWHEN\s+NULL\s+IS\s+NOT\s+NULL\b/i', $sql) === 1) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $eventRow
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{tables:array<string,list<array<string,mixed>>>,action:string}
     */
    private static function applyTriggerBody(string $sql, array $eventRow, array $tables): array
    {
        if (preg_match('/INSERT\s+INTO\s+([A-Za-z_][A-Za-z0-9_]*)\s+VALUES\s*\(([^)]*)\)/i', $sql, $match) === 1) {
            $table = self::identifier($match[1], 'insert table');
            $tables[$table] ??= [];
            $row = [];
            foreach (self::splitCsv($match[2]) as $index => $term) {
                $row['c' . ($index + 1)] = self::termValue($term, $eventRow);
            }
            $tables[$table][] = $row;

            return ['tables' => $tables, 'action' => 'insert'];
        }

        if (preg_match('/UPDATE\s+([A-Za-z_][A-Za-z0-9_]*)\s+SET\s+([A-Za-z_][A-Za-z0-9_]*)\s*=\s*([A-Za-z_][A-Za-z0-9_]*)\s+WHERE\s+([A-Za-z_][A-Za-z0-9_]*)\s+IS\s+NULL/i', $sql, $match) === 1) {
            $table = self::identifier($match[1], 'update table');
            $setColumn = self::identifier($match[2], 'set column');
            $sourceColumn = self::identifier($match[3], 'source column');
            $whereColumn = self::identifier($match[4], 'where column');
            $rows = $tables[$table] ?? [];

            foreach ($rows as &$row) {
                if (($row[$whereColumn] ?? null) !== null) {
                    continue;
                }
                $row[$setColumn] = $row[$sourceColumn] ?? null;
            }
            unset($row);
            $tables[$table] = $rows;

            return ['tables' => $tables, 'action' => 'update-null-match'];
        }

        return ['tables' => $tables, 'action' => 'select-noop'];
    }

    /**
     * @return list<string>
     */
    private static function splitCsv(string $csv): array
    {
        return array_map('trim', explode(',', $csv));
    }

    /**
     * @param array<string,mixed> $eventRow
     */
    private static function termValue(string $term, array $eventRow): mixed
    {
        $term = trim($term);
        if (strcasecmp($term, 'NULL') === 0) {
            return null;
        }
        if (preg_match('/^new\.([A-Za-z_][A-Za-z0-9_]*)$/i', $term, $match) === 1) {
            return $eventRow[$match[1]] ?? null;
        }
        if (preg_match('/^\'(.*)\'$/', $term, $match) === 1) {
            return str_replace("''", "'", $match[1]);
        }
        if (preg_match('/^-?[0-9]+$/', $term) === 1) {
            return (int) $term;
        }

        return $term;
    }

    /**
     * @param mixed $value
     * @return array<string,mixed>
     */
    private static function row(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite trigger dynamic variable {$label} is malformed");
        }

        return $value;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite trigger dynamic variable {$label} is malformed");
        }

        return $value;
    }
}
