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
     * @param array{recursive_triggers?:bool,max_depth?:int,conflict_action?:string,key_column?:string,value_column?:string,returning?:list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,?string):mixed>} $options
     * @return array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,updated:list<array<string,mixed>>,skipped:list<array<string,mixed>>,yielded:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int,max_depth_seen:int,returning:list<array<string,mixed>>}
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
            'returning' => [],
        ];
        $context = [
            'unique_columns' => self::uniqueColumns($uniqueColumns),
            'assignments' => $assignments,
            'triggers' => $triggers,
            'recursive_triggers' => (bool) ($options['recursive_triggers'] ?? true),
            'max_depth' => self::maxDepth($options['max_depth'] ?? 1000),
            'conflict_action' => self::conflictAction((string) ($options['conflict_action'] ?? 'update')),
            'key_column' => self::identifier((string) ($options['key_column'] ?? 'key_name'), 'key column'),
            'value_column' => self::identifier((string) ($options['value_column'] ?? 'key_value'), 'value column'),
            'returning' => self::returningProjection($options['returning'] ?? null),
        ];

        foreach ($incomingRows as $ordinal => $row) {
            self::upsert($state, $context, $row, 0, (int) $ordinal, 'statement', null);
        }

        $state['rows'] = array_values($state['rows']);

        return $state;
    }

    /**
     * @param array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,updated:list<array<string,mixed>>,skipped:list<array<string,mixed>>,yielded:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int,max_depth_seen:int,returning:list<array<string,mixed>>} $state
     * @param array{unique_columns:list<string>,assignments:array<string,callable(array<string,mixed>,array<string,mixed>):mixed>,triggers:list<array<string,mixed>>,recursive_triggers:bool,max_depth:int,conflict_action:string,key_column:string,value_column:string,returning:?array} $context
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
            $state['yielded'][] = self::yieldRow($ordinal, $source, $triggerName, 'skipped', $event, $old, $incoming, $incoming, $depth, null, $context);
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
        $returning = self::returningRow($context['returning'], $old, $new, $incoming, $event, $depth, $triggerName);
        $state['yielded'][] = self::yieldRow($ordinal, $source, $triggerName, 'changed', $event, $old, $new, $incoming, $depth, $returning, $context);
        $state['returning'][] = $returning;

        self::fireTriggers('after', $event, $state, $context, $old, $new, $depth, $ordinal);
    }

    /**
     * @param array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,updated:list<array<string,mixed>>,skipped:list<array<string,mixed>>,yielded:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int,max_depth_seen:int,returning:list<array<string,mixed>>} $state
     * @param array{unique_columns:list<string>,assignments:array<string,callable(array<string,mixed>,array<string,mixed>):mixed>,triggers:list<array<string,mixed>>,recursive_triggers:bool,max_depth:int,conflict_action:string,key_column:string,value_column:string,returning:?array} $context
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
                $state['trigger_effects'][] = self::effect($trigger, $timing, $event, 'audit', $old, $new, $depth, $context);
                continue;
            }
            if ($action !== 'upsert-parent') {
                throw new \InvalidArgumentException('SQLite recursive UPSERT trigger conflict action is unsupported');
            }

            $row = self::project((array) ($trigger['row'] ?? []), $old, $new);
            $state['trigger_effects'][] = self::effect($trigger, $timing, $event, $context['recursive_triggers'] ? 'recursive-upsert' : 'recursive-suppressed', $old, $new, $depth, $context);
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
    private static function effect(array $trigger, string $timing, string $event, string $result, ?array $old, array $row, int $depth, array $context = []): array
    {
        $keyColumn = (string) ($context['key_column'] ?? 'key_name');
        return [
            'trigger' => (string) ($trigger['name'] ?? ''),
            'timing' => $timing,
            'event' => $event,
            'result' => $result,
            'depth' => $depth,
            'old_key' => $old[$keyColumn] ?? null,
            'new_key' => $row[$keyColumn] ?? null,
            'row' => self::project((array) ($trigger['values'] ?? []), $old, $row),
        ];
    }

    /**
     * @param array<string,mixed> $new
     * @return array<string,mixed>
     */
    private static function yieldRow(int $ordinal, string $source, ?string $triggerName, string $status, string $event, ?array $old, array $new, array $incoming, int $depth, ?array $returning, array $context): array
    {
        $keyColumn = (string) $context['key_column'];
        $valueColumn = (string) $context['value_column'];
        return [
            'ordinal' => $ordinal,
            'source' => $source,
            'trigger' => $triggerName,
            'status' => $status,
            'event' => $event,
            'depth' => $depth,
            'old_key' => $old[$keyColumn] ?? null,
            'new_key' => $new[$keyColumn] ?? null,
            'incoming_key' => $incoming[$keyColumn] ?? null,
            'old_value' => $old[$valueColumn] ?? null,
            'new_value' => $new[$valueColumn] ?? null,
            'returning' => $returning,
        ];
    }

    /**
     * @return list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,?string):mixed>|null
     */
    private static function returningProjection(mixed $projection): ?array
    {
        if ($projection === null) {
            return null;
        }
        if (!is_array($projection) || !array_is_list($projection)) {
            throw new \InvalidArgumentException('SQLite recursive UPSERT trigger RETURNING projection must be a list');
        }

        return $projection;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,?string):mixed>|null $projection
     * @return array<string,mixed>
     */
    private static function returningRow(?array $projection, ?array $old, array $new, array $incoming, string $event, int $depth, ?string $triggerName): array
    {
        if ($projection === null) {
            return $new;
        }

        $row = [];
        foreach ($projection as $index => $entry) {
            if (is_string($entry)) {
                if ($entry === '*') {
                    $row['*'] = $new;
                    continue;
                }
                $alias = str_contains($entry, '.') ? substr($entry, (int) strrpos($entry, '.') + 1) : $entry;
                $row[self::identifier($alias, 'RETURNING alias')] = self::returningValue($entry, $old, $new, $incoming, $event, $depth, $triggerName);
                continue;
            }
            if (is_array($entry)) {
                $expr = (string) ($entry['expr'] ?? '');
                $alias = (string) ($entry['as'] ?? (str_contains($expr, '.') ? substr($expr, (int) strrpos($expr, '.') + 1) : $expr));
                $row[self::identifier($alias, 'RETURNING alias')] = self::returningValue($expr, $old, $new, $incoming, $event, $depth, $triggerName);
                continue;
            }
            if (is_callable($entry)) {
                $row['expr' . $index] = $entry($new, $old, $incoming, $event, $depth, $triggerName);
                continue;
            }

            throw new \InvalidArgumentException('SQLite recursive UPSERT trigger RETURNING projection is malformed');
        }

        return $row;
    }

    private static function returningValue(string $expr, ?array $old, array $new, array $incoming, string $event, int $depth, ?string $triggerName): mixed
    {
        $expr = trim($expr);
        if ($expr === '') {
            throw new \InvalidArgumentException('SQLite recursive UPSERT trigger RETURNING expression is empty');
        }
        if ($expr === 'event') {
            return $event;
        }
        if ($expr === 'depth') {
            return $depth;
        }
        if ($expr === 'trigger') {
            return $triggerName;
        }
        if (str_starts_with($expr, 'new.')) {
            return self::rowValue($new, substr($expr, 4), 'RETURNING NEW row');
        }
        if (str_starts_with($expr, 'excluded.')) {
            return self::rowValue($incoming, substr($expr, 9), 'RETURNING excluded row');
        }
        if (str_starts_with($expr, 'old.')) {
            if ($old === null) {
                throw new \InvalidArgumentException('SQLite recursive UPSERT trigger RETURNING OLD row is unavailable for INSERT');
            }

            return self::rowValue($old, substr($expr, 4), 'RETURNING OLD row');
        }

        return self::rowValue($new, $expr, 'RETURNING row');
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
