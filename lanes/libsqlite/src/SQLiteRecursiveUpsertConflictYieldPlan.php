<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRecursiveUpsertConflictYieldPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<array<string,mixed>> $triggers
     * @param array{recursive_triggers?:bool,max_depth?:int,conflict_action?:string} $options
     * @return array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,updated:list<array<string,mixed>>,skipped:list<array<string,mixed>>,yielded:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int,max_depth_seen:int}
     */
    public static function execute(
        array $rows,
        array $incomingRows,
        array $uniqueColumns,
        array $assignments,
        array $triggers,
        array $options = [],
    ): array {
        $state = [
            'rows' => array_values($rows),
            'inserted' => [],
            'updated' => [],
            'skipped' => [],
            'yielded' => [],
            'trigger_effects' => [],
            'changes' => 0,
            'max_depth_seen' => 0,
        ];
        $context = [
            'unique_columns' => self::uniqueColumns($uniqueColumns),
            'assignments' => $assignments,
            'triggers' => $triggers,
            'recursive_triggers' => (bool) ($options['recursive_triggers'] ?? true),
            'max_depth' => self::maxDepth($options['max_depth'] ?? 1000),
            'conflict_action' => self::conflictAction((string) ($options['conflict_action'] ?? 'update')),
        ];

        foreach ($incomingRows as $ordinal => $row) {
            self::upsert($state, $context, $row, 0, (int) $ordinal, 'statement', null);
        }

        $state['rows'] = array_values($state['rows']);

        return $state;
    }

    /**
     * @param array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,updated:list<array<string,mixed>>,skipped:list<array<string,mixed>>,yielded:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int,max_depth_seen:int} $state
     * @param array{unique_columns:list<string>,assignments:array<string,callable(array<string,mixed>,array<string,mixed>):mixed>,triggers:list<array<string,mixed>>,recursive_triggers:bool,max_depth:int,conflict_action:string} $context
     * @param array<string,mixed> $incoming
     */
    private static function upsert(array &$state, array $context, array $incoming, int $depth, int $ordinal, string $source, ?string $triggerName): void
    {
        if ($depth > $context['max_depth']) {
            throw new \RuntimeException('SQLite recursive UPSERT trigger conflict depth limit exceeded');
        }
        $state['max_depth_seen'] = max($state['max_depth_seen'], $depth);

        $conflictIndex = self::findConflictIndex($state['rows'], $incoming, $context['unique_columns']);
        $old = $conflictIndex === null ? null : $state['rows'][$conflictIndex];
        $event = $old === null ? 'insert' : 'update';
        if ($old !== null && $context['conflict_action'] === 'ignore') {
            $state['skipped'][] = $incoming;
            $state['yielded'][] = self::yieldRow($ordinal, $source, $triggerName, 'skipped', $event, $old, $incoming, $depth);
            return;
        }

        $new = $old ?? $incoming;
        if ($old !== null) {
            foreach ($context['assignments'] as $column => $assignment) {
                self::identifier((string) $column, 'assignment column');
                $new[$column] = $assignment($old, $incoming);
            }
        }

        self::fireTriggers('before', $event, $state, $context, $old, $new, $depth, $ordinal);

        if ($old === null) {
            $state['rows'][] = $new;
            $state['inserted'][] = $new;
        } else {
            $state['rows'][$conflictIndex] = $new;
            $state['updated'][] = $new;
        }
        ++$state['changes'];
        $state['yielded'][] = self::yieldRow($ordinal, $source, $triggerName, 'changed', $event, $old, $new, $depth);

        self::fireTriggers('after', $event, $state, $context, $old, $new, $depth, $ordinal);
    }

    /**
     * @param array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,updated:list<array<string,mixed>>,skipped:list<array<string,mixed>>,yielded:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int,max_depth_seen:int} $state
     * @param array{unique_columns:list<string>,assignments:array<string,callable(array<string,mixed>,array<string,mixed>):mixed>,triggers:list<array<string,mixed>>,recursive_triggers:bool,max_depth:int,conflict_action:string} $context
     * @param array<string,mixed> $new
     */
    private static function fireTriggers(string $timing, string $event, array &$state, array $context, ?array $old, array $new, int $depth, int $ordinal): void
    {
        foreach ($context['triggers'] as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== $timing || strtolower((string) ($trigger['event'] ?? '')) !== $event) {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? true, $old, $new)) {
                continue;
            }
            $action = strtolower((string) ($trigger['action'] ?? 'audit'));
            if ($action === 'audit') {
                $state['trigger_effects'][] = self::effect($trigger, $timing, $event, 'audit', $old, $new, $depth);
                continue;
            }
            if ($action !== 'upsert-parent') {
                throw new \InvalidArgumentException('SQLite recursive UPSERT trigger conflict action is unsupported');
            }

            $row = self::project((array) ($trigger['row'] ?? []), $old, $new);
            $state['trigger_effects'][] = self::effect($trigger, $timing, $event, $context['recursive_triggers'] ? 'recursive-upsert' : 'recursive-suppressed', $old, $new, $depth);
            if (!$context['recursive_triggers']) {
                continue;
            }
            self::upsert($state, $context, $row, $depth + 1, $ordinal, 'trigger', (string) ($trigger['name'] ?? ''));
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     */
    private static function findConflictIndex(array $rows, array $incoming, array $uniqueColumns): ?int
    {
        foreach ($rows as $index => $row) {
            foreach ($uniqueColumns as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $incoming)) {
                    throw new \InvalidArgumentException("SQLite recursive UPSERT trigger conflict unique column {$column} is missing");
                }
                if ($row[$column] === null || $incoming[$column] === null || $row[$column] != $incoming[$column]) {
                    continue 2;
                }
            }

            return $index;
        }

        return null;
    }

    /**
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    private static function project(array $template, ?array $old, array $new): array
    {
        $row = [];
        foreach ($template as $column => $value) {
            self::identifier((string) $column, 'projection column');
            $row[$column] = self::value($value, $old, $new);
        }

        return $row;
    }

    private static function whenMatches(mixed $when, ?array $old, array $new): bool
    {
        if ($when === true || $when === null) {
            return true;
        }
        if ($when === false) {
            return false;
        }
        if (!is_array($when) || count($when) !== 3) {
            throw new \InvalidArgumentException('SQLite recursive UPSERT trigger conflict WHEN clause is malformed');
        }
        [$left, $operator, $right] = array_values($when);
        $left = self::value($left, $old, $new);
        $right = self::value($right, $old, $new);

        return match (strtoupper((string) $operator)) {
            '=', '==' => $left == $right,
            '!=', '<>' => $left != $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            'IS' => $left === $right,
            'IS NOT' => $left !== $right,
            default => throw new \InvalidArgumentException('SQLite recursive UPSERT trigger conflict WHEN operator is unsupported'),
        };
    }

    private static function value(mixed $value, ?array $old, array $new): mixed
    {
        if (is_string($value) && str_starts_with($value, 'new.')) {
            return self::rowValue($new, substr($value, 4), 'NEW row');
        }
        if (is_string($value) && str_starts_with($value, 'old.')) {
            if ($old === null) {
                throw new \InvalidArgumentException('SQLite recursive UPSERT trigger conflict OLD row is unavailable for INSERT');
            }

            return self::rowValue($old, substr($value, 4), 'OLD row');
        }
        if (is_array($value) && isset($value['concat']) && is_array($value['concat'])) {
            $parts = [];
            foreach ($value['concat'] as $part) {
                $parts[] = self::value($part, $old, $new);
            }

            return implode('', array_map(static fn (mixed $part): string => (string) $part, $parts));
        }
        if (is_array($value) && array_key_exists('add', $value) && is_array($value['add']) && count($value['add']) === 2) {
            [$left, $right] = array_values($value['add']);

            return self::value($left, $old, $new) + self::value($right, $old, $new);
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $trigger
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function effect(array $trigger, string $timing, string $event, string $result, ?array $old, array $row, int $depth): array
    {
        return [
            'trigger' => (string) ($trigger['name'] ?? ''),
            'timing' => $timing,
            'event' => $event,
            'result' => $result,
            'depth' => $depth,
            'old_key' => $old['option_name'] ?? null,
            'new_key' => $row['option_name'] ?? null,
            'row' => self::project((array) ($trigger['values'] ?? []), $old, $row),
        ];
    }

    /**
     * @param array<string,mixed> $new
     * @return array<string,mixed>
     */
    private static function yieldRow(int $ordinal, string $source, ?string $triggerName, string $status, string $event, ?array $old, array $new, int $depth): array
    {
        return [
            'ordinal' => $ordinal,
            'source' => $source,
            'trigger' => $triggerName,
            'status' => $status,
            'event' => $event,
            'depth' => $depth,
            'old_key' => $old['option_name'] ?? null,
            'new_key' => $new['option_name'] ?? null,
            'old_value' => $old['option_value'] ?? null,
            'new_value' => $new['option_value'] ?? null,
        ];
    }

    private static function rowValue(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite recursive UPSERT trigger conflict {$label} missing column {$column}");
        }

        return $row[$column];
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private static function uniqueColumns(array $columns): array
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite recursive UPSERT trigger conflict unique columns cannot be empty');
        }
        foreach ($columns as $column) {
            self::identifier($column, 'unique column');
        }

        return array_values($columns);
    }

    private static function maxDepth(mixed $depth): int
    {
        if (!is_int($depth) || $depth < 0) {
            throw new \InvalidArgumentException('SQLite recursive UPSERT trigger conflict max_depth must be a non-negative integer');
        }

        return $depth;
    }

    private static function conflictAction(string $action): string
    {
        $action = strtolower($action);
        if (!in_array($action, ['update', 'ignore'], true)) {
            throw new \InvalidArgumentException('SQLite recursive UPSERT trigger conflict action is unsupported');
        }

        return $action;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite recursive UPSERT trigger conflict {$label} is malformed");
        }

        return $value;
    }
}
