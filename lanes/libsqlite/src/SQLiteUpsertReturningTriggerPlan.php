<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpsertReturningTriggerPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<array<string,mixed>> $triggers
     * @param callable(array<string,mixed>,array<string,mixed>):bool|null $where
     * @param list<list<string>>|null $uniqueConstraints
     * @return array{before:list<array<string,mixed>>,after:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>,updated_rows:list<array<string,mixed>>,skipped_rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int}
     */
    public static function execute(
        array $rows,
        array $incomingRows,
        array $uniqueColumns,
        array $assignments,
        array $triggers,
        ?callable $where = null,
        ?array $uniqueConstraints = null,
    ): array {
        $plan = SQLiteUpsertDoUpdateWherePlan::execute(
            $rows,
            $incomingRows,
            $uniqueColumns,
            $assignments,
            $where,
            $uniqueConstraints,
        );

        $after = $plan['before'];
        $inserted = [];
        $updated = [];
        $skipped = [];
        $returning = [];
        $effects = [];
        $changes = 0;

        foreach ($incomingRows as $incoming) {
            $conflictIndex = self::findConflictIndex($after, $incoming, $uniqueColumns);
            if ($conflictIndex === null) {
                $candidate = $incoming;
                $effects = array_merge($effects, self::triggerEffects('before', 'insert', null, $candidate, $triggers));
                $after[] = $candidate;
                $inserted[] = $candidate;
                $returning[] = $candidate;
                ++$changes;
                $effects = array_merge($effects, self::triggerEffects('after', 'insert', null, $candidate, $triggers));
                $after[array_key_last($after)] = self::applyAfterRowMutations($candidate, 'insert', $triggers, null);
                continue;
            }

            $old = $after[$conflictIndex];
            if ($where !== null && !$where($old, $incoming)) {
                $skipped[] = $incoming;
                continue;
            }

            $new = $old;
            foreach ($assignments as $column => $assignment) {
                $new[$column] = $assignment($old, $incoming);
            }

            $effects = array_merge($effects, self::triggerEffects('before', 'update', $old, $new, $triggers));
            $after[$conflictIndex] = $new;
            $updated[] = $new;
            $returning[] = $new;
            ++$changes;
            $effects = array_merge($effects, self::triggerEffects('after', 'update', $old, $new, $triggers));
            $after[$conflictIndex] = self::applyAfterRowMutations($new, 'update', $triggers, $old);
        }

        return [
            'before' => $plan['before'],
            'after' => array_values($after),
            'inserted_rows' => $inserted,
            'updated_rows' => $updated,
            'skipped_rows' => $skipped,
            'returning_rows' => $returning,
            'trigger_effects' => $effects,
            'changes' => $changes,
        ];
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
                    throw new \InvalidArgumentException("SQLite UPSERT trigger unique column {$column} is missing");
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
     * @param array<string,mixed>|null $old
     * @param array<string,mixed> $new
     * @param list<array<string,mixed>> $triggers
     * @return list<array<string,mixed>>
     */
    private static function triggerEffects(string $timing, string $event, ?array $old, array $new, array $triggers): array
    {
        $effects = [];
        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== $timing || strtolower((string) ($trigger['event'] ?? '')) !== $event) {
                continue;
            }
            self::validateTrigger($trigger);
            if (!self::whenMatches($trigger['when'] ?? null, $old, $new)) {
                continue;
            }
            if ($event === 'update' && isset($trigger['of']) && !self::changedAny($old ?? [], $new, (array) $trigger['of'])) {
                continue;
            }

            $effects[] = [
                'trigger' => (string) $trigger['name'],
                'timing' => $timing,
                'event' => $event,
                'row' => self::projectValues((array) ($trigger['values'] ?? []), $old, $new),
            ];
        }

        return $effects;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $triggers
     * @return array<string,mixed>
     */
    private static function applyAfterRowMutations(array $row, string $event, array $triggers, ?array $old): array
    {
        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== 'after' || strtolower((string) ($trigger['event'] ?? '')) !== $event) {
                continue;
            }
            if (($trigger['mutate_target'] ?? null) !== true) {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? null, $old, $row)) {
                continue;
            }
            foreach ((array) ($trigger['set'] ?? []) as $column => $value) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite UPSERT trigger mutation column is malformed');
                }
                $row[$column] = self::value($value, null, $row);
            }
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $old
     * @param array<string,mixed> $new
     * @param list<string> $columns
     */
    private static function changedAny(array $old, array $new, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite UPSERT trigger UPDATE OF column is malformed');
            }
            if (($old[$column] ?? null) !== ($new[$column] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $template
     * @param array<string,mixed>|null $old
     * @param array<string,mixed> $new
     * @return array<string,mixed>
     */
    private static function projectValues(array $template, ?array $old, array $new): array
    {
        $row = [];
        foreach ($template as $column => $value) {
            $row[$column] = self::value($value, $old, $new);
        }

        return $row;
    }

    /**
     * @param array<string,mixed>|null $old
     * @param array<string,mixed> $new
     */
    private static function value(mixed $value, ?array $old, array $new): mixed
    {
        if (is_string($value) && str_starts_with($value, 'new.')) {
            $column = substr($value, 4);
            if (!array_key_exists($column, $new)) {
                throw new \InvalidArgumentException("SQLite UPSERT trigger NEW column {$column} is missing");
            }

            return $new[$column];
        }
        if (is_string($value) && str_starts_with($value, 'old.')) {
            $column = substr($value, 4);
            if ($old === null) {
                throw new \InvalidArgumentException('SQLite UPSERT trigger OLD row is unavailable for INSERT');
            }
            if (!array_key_exists($column, $old)) {
                throw new \InvalidArgumentException("SQLite UPSERT trigger OLD column {$column} is missing");
            }

            return $old[$column];
        }

        return $value;
    }

    /**
     * @param array<string,mixed>|null $old
     * @param array<string,mixed> $new
     */
    private static function whenMatches(mixed $when, ?array $old, array $new): bool
    {
        if ($when === null || $when === true) {
            return true;
        }
        if ($when === false) {
            return false;
        }
        if (!is_array($when) || count($when) !== 3) {
            throw new \InvalidArgumentException('SQLite UPSERT trigger WHEN clause is malformed');
        }

        [$left, $operator, $right] = array_values($when);
        $left = self::value($left, $old, $new);
        $right = self::value($right, $old, $new);

        return match (strtoupper((string) $operator)) {
            '=' => $left == $right,
            '!=', '<>' => $left != $right,
            'IS' => $left === $right,
            'IS NOT' => $left !== $right,
            default => throw new \InvalidArgumentException('SQLite UPSERT trigger WHEN operator is unsupported'),
        };
    }

    /**
     * @param array<string,mixed> $trigger
     */
    private static function validateTrigger(array $trigger): void
    {
        if (!isset($trigger['name']) || !is_string($trigger['name']) || $trigger['name'] === '') {
            throw new \InvalidArgumentException('SQLite UPSERT trigger name is required');
        }
        if (($trigger['table'] ?? null) !== 'app_settings') {
            throw new \InvalidArgumentException('SQLite UPSERT trigger target table is unsupported');
        }
        if (!in_array(strtolower((string) ($trigger['timing'] ?? '')), ['before', 'after'], true)) {
            throw new \InvalidArgumentException('SQLite UPSERT trigger timing is unsupported');
        }
        if (!in_array(strtolower((string) ($trigger['event'] ?? '')), ['insert', 'update'], true)) {
            throw new \InvalidArgumentException('SQLite UPSERT trigger event is unsupported');
        }
    }
}
