<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteDmlTriggerReturningConflictCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $uniqueColumns
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,int,string):mixed> $returning
     * @param array{recursive_triggers?:bool} $options
     * @return array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,deleted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,yielded:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int,conflict_action:string,recursive_triggers:bool,dependencies:list<string>}
     */
    public static function insertRows(
        array $rows,
        array $incomingRows,
        array $uniqueColumns,
        array $triggers = [],
        string $conflictAction = 'abort',
        array $returning = ['*'],
        array $options = [],
    ): array {
        self::validateUniqueColumns($uniqueColumns);
        $conflictAction = self::conflictAction($conflictAction);
        $recursiveTriggers = (bool) ($options['recursive_triggers'] ?? true);
        $working = array_values($rows);
        $inserted = [];
        $deleted = [];
        $ignored = [];
        $returningRows = [];
        $yielded = [];
        $effects = [];
        $changes = 0;

        foreach (array_values($incomingRows) as $ordinal => $incoming) {
            $candidate = $incoming;
            $before = self::fireTriggers('before', 'insert', null, $candidate, $triggers, $ordinal);
            $candidate = $before['row'];
            $effects = array_merge($effects, $before['effects']);

            $conflictingIndexes = self::conflictingIndexes($working, $candidate, $uniqueColumns);
            if ($conflictingIndexes !== []) {
                if ($conflictAction === 'ignore') {
                    $ignored[] = $candidate;
                    $yielded[] = self::yieldRow($ordinal, 'ignored-conflict', $candidate, null, []);
                    $effects[] = self::effect(null, 'conflict', 'ignore', 'insert', $ordinal, $candidate);
                    continue;
                }
                if ($conflictAction !== 'replace') {
                    throw new \InvalidArgumentException('SQLite DML trigger RETURNING conflict current-source unique constraint failed');
                }

                foreach (array_reverse($conflictingIndexes) as $index) {
                    $old = $working[$index];
                    if ($recursiveTriggers) {
                        $deleteBefore = self::fireTriggers('before', 'delete', $old, $old, $triggers, $ordinal);
                        $effects = array_merge($effects, $deleteBefore['effects']);
                    }
                    unset($working[$index]);
                    $working = array_values($working);
                    $deleted[] = $old;
                    ++$changes;
                    if ($recursiveTriggers) {
                        $deleteAfter = self::fireTriggers('after', 'delete', $old, $old, $triggers, $ordinal);
                        $effects = array_merge($effects, $deleteAfter['effects']);
                    }
                }
            }

            $returningImage = $candidate;
            $working[] = $candidate;
            $inserted[] = $candidate;
            ++$changes;

            $after = self::fireTriggers('after', 'insert', null, $candidate, $triggers, $ordinal);
            $candidate = $after['row'];
            $working[array_key_last($working)] = $candidate;
            $inserted[array_key_last($inserted)] = $candidate;
            $effects = array_merge($effects, $after['effects']);

            $returningRow = self::returningRow($returning, $returningImage, null, $ordinal, 'insert');
            $returningRows[] = $returningRow;
            $yielded[] = self::yieldRow($ordinal, 'inserted', $candidate, $returningRow, $conflictingIndexes);
        }

        return [
            'rows' => array_values($working),
            'inserted' => array_values($inserted),
            'deleted' => array_values($deleted),
            'ignored' => array_values($ignored),
            'returning_rows' => array_values($returningRows),
            'yielded' => array_values($yielded),
            'trigger_effects' => array_values($effects),
            'changes' => $changes,
            'conflict_action' => $conflictAction,
            'recursive_triggers' => $recursiveTriggers,
            'dependencies' => [
                'sqlite-insert-or-replace-returning-current-source-next106',
                'sqlite-before-trigger-returning-image',
                'sqlite-replace-delete-trigger-recursive-gate',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $triggers
     * @return array{row:array<string,mixed>,effects:list<array<string,mixed>>}
     */
    private static function fireTriggers(string $timing, string $event, ?array $old, array $new, array $triggers, int $ordinal): array
    {
        $effects = [];
        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== $timing || strtolower((string) ($trigger['event'] ?? '')) !== $event) {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? true, $old, $new)) {
                continue;
            }

            $action = strtolower((string) ($trigger['action'] ?? 'audit'));
            if ($action === 'set-new') {
                foreach ((array) ($trigger['set'] ?? []) as $column => $value) {
                    self::identifier((string) $column, 'trigger set column');
                    $new[$column] = self::value($value, $old, $new);
                }
            } elseif (!in_array($action, ['audit', 'insert-side', 'delete-side'], true)) {
                throw new \InvalidArgumentException('SQLite DML trigger RETURNING current-source trigger action is unsupported');
            }

            $effects[] = self::effect(
                (string) ($trigger['name'] ?? ''),
                $timing,
                $action,
                $event,
                $ordinal,
                self::project((array) ($trigger['values'] ?? []), $old, $new),
            );
        }

        return ['row' => $new, 'effects' => $effects];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     * @return list<int>
     */
    private static function conflictingIndexes(array $rows, array $candidate, array $uniqueColumns): array
    {
        $indexes = [];
        foreach ($rows as $index => $row) {
            $conflict = true;
            foreach ($uniqueColumns as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $candidate)) {
                    throw new \InvalidArgumentException("SQLite DML trigger RETURNING current-source unique column {$column} is missing");
                }
                if ($row[$column] === null || $candidate[$column] === null || $row[$column] != $candidate[$column]) {
                    $conflict = false;
                    break;
                }
            }
            if ($conflict) {
                $indexes[] = (int) $index;
            }
        }

        return $indexes;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,int,string):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returningRow(array $returning, array $new, ?array $old, int $ordinal, string $event): array
    {
        if ($returning === []) {
            throw new \InvalidArgumentException('SQLite DML trigger RETURNING current-source projection is empty');
        }

        $row = [];
        foreach ($returning as $index => $term) {
            if ($term === '*') {
                $row['*'] = $new;
                continue;
            }
            if (is_callable($term)) {
                $row['expr' . $index] = $term($new, $old, $ordinal, $event);
                continue;
            }
            if (is_array($term)) {
                $expr = (string) ($term['expr'] ?? '');
                $alias = (string) ($term['as'] ?? (str_contains($expr, '.') ? substr($expr, (int) strrpos($expr, '.') + 1) : $expr));
                $row[self::identifier($alias, 'RETURNING alias')] = self::returningValue($expr, $old, $new);
                continue;
            }
            if (!is_string($term) || $term === '') {
                throw new \InvalidArgumentException('SQLite DML trigger RETURNING current-source projection term is malformed');
            }
            $alias = str_contains($term, '.') ? substr($term, (int) strrpos($term, '.') + 1) : $term;
            $row[self::identifier($alias, 'RETURNING alias')] = self::returningValue($term, $old, $new);
        }

        return $row;
    }

    private static function returningValue(string $expr, ?array $old, array $new): mixed
    {
        $expr = trim($expr);
        if (str_starts_with($expr, 'new.')) {
            return self::rowValue($new, substr($expr, 4), 'NEW row');
        }
        if (str_starts_with($expr, 'old.')) {
            if ($old === null) {
                throw new \InvalidArgumentException('SQLite DML trigger RETURNING current-source OLD row is unavailable');
            }

            return self::rowValue($old, substr($expr, 4), 'OLD row');
        }

        return self::rowValue($new, $expr, 'RETURNING row');
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
            throw new \InvalidArgumentException('SQLite DML trigger RETURNING current-source WHEN clause is malformed');
        }
        [$left, $operator, $right] = array_values($when);
        $left = self::value($left, $old, $new);
        $right = self::value($right, $old, $new);

        return match (strtoupper((string) $operator)) {
            '=', '==' => $left == $right,
            '!=', '<>' => $left != $right,
            'IS' => $left === $right,
            'IS NOT' => $left !== $right,
            default => throw new \InvalidArgumentException('SQLite DML trigger RETURNING current-source WHEN operator is unsupported'),
        };
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
            $row[(string) $column] = self::value($value, $old, $new);
        }

        return $row;
    }

    private static function value(mixed $value, ?array $old, array $new): mixed
    {
        if (is_string($value) && str_starts_with($value, 'new.')) {
            return self::rowValue($new, substr($value, 4), 'NEW row');
        }
        if (is_string($value) && str_starts_with($value, 'old.')) {
            if ($old === null) {
                throw new \InvalidArgumentException('SQLite DML trigger RETURNING current-source OLD row is unavailable');
            }

            return self::rowValue($old, substr($value, 4), 'OLD row');
        }
        if (is_string($value) && array_key_exists($value, $new)) {
            return $new[$value];
        }

        return $value;
    }

    /**
     * @param list<string> $columns
     */
    private static function validateUniqueColumns(array $columns): void
    {
        if ($columns === [] || !array_is_list($columns)) {
            throw new \InvalidArgumentException('SQLite DML trigger RETURNING current-source unique columns must be a non-empty list');
        }
        foreach ($columns as $column) {
            self::identifier($column, 'unique column');
        }
    }

    private static function conflictAction(string $action): string
    {
        $action = strtolower($action);
        if (!in_array($action, ['abort', 'fail', 'rollback', 'ignore', 'replace'], true)) {
            throw new \InvalidArgumentException('SQLite DML trigger RETURNING current-source conflict action is unsupported');
        }

        return $action;
    }

    private static function rowValue(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite DML trigger RETURNING current-source {$label} missing column {$column}");
        }

        return $row[$column];
    }

    /**
     * @param array<string,mixed>|null $row
     * @param list<int> $conflictIndexes
     * @return array<string,mixed>
     */
    private static function yieldRow(int $ordinal, string $status, array $row, ?array $returning, array $conflictIndexes): array
    {
        return [
            'ordinal' => $ordinal,
            'status' => $status,
            'row_key' => self::rowKey($row),
            'conflict_indexes' => array_values($conflictIndexes),
            'returning' => $returning,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowKey(array $row): mixed
    {
        if (array_key_exists('key_name', $row)) {
            return $row['key_name'];
        }
        if (array_key_exists('name', $row)) {
            return $row['name'];
        }
        foreach ($row as $column => $value) {
            if (is_string($column) && str_ends_with($column, '_name')) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>
     */
    private static function effect(?string $trigger, string $timing, string $action, string $event, int $ordinal, ?array $row): array
    {
        return [
            'trigger' => $trigger,
            'timing' => $timing,
            'event' => $event,
            'action' => $action,
            'ordinal' => $ordinal,
            'row' => $row,
        ];
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite DML trigger RETURNING current-source {$label} is malformed");
        }

        return $value;
    }
}
