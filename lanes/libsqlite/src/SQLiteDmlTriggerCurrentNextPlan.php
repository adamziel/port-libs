<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteDmlTriggerCurrentNextPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $triggers
     * @return array{rows:list<array<string,mixed>>,audit:list<array<string,mixed>>,changes:int,inserted:list<array<string,mixed>>,updated:list<array<string,mixed>>,deleted:list<array<string,mixed>>,visited:list<int|string>}
     */
    public static function insertRows(
        array $rows,
        array $inputRows,
        array $triggers,
        string $rowIdColumn = 'setting_id',
    ): array {
        self::validateIdentifier($rowIdColumn, 'rowid column');
        $rows = array_values($rows);
        $audit = [];
        $inserted = [];
        $visited = [];
        $nextRowId = self::nextRowId($rows, $rowIdColumn);

        foreach ($inputRows as $inputRow) {
            $new = $inputRow;
            if (!array_key_exists($rowIdColumn, $new) || $new[$rowIdColumn] === null) {
                $new[$rowIdColumn] = $nextRowId++;
            } elseif (is_int($new[$rowIdColumn]) && $new[$rowIdColumn] >= $nextRowId) {
                $nextRowId = $new[$rowIdColumn] + 1;
            }

            $visited[] = $new[$rowIdColumn];
            self::fireTriggers('before', 'insert', null, $new, [], $triggers, $audit);
            $rows[] = $new;
            $inserted[] = $new;
            self::fireTriggers('after', 'insert', null, $new, [], $triggers, $audit);
        }

        return self::result($rows, $audit, $inserted, [], [], $visited);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed|callable(array<string,mixed>):mixed> $assignments
     * @param callable(array<string,mixed>):bool $where
     * @param list<array<string,mixed>> $triggers
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array{rows:list<array<string,mixed>>,audit:list<array<string,mixed>>,changes:int,inserted:list<array<string,mixed>>,updated:list<array<string,mixed>>,deleted:list<array<string,mixed>>,visited:list<int|string>}
     */
    public static function updateRows(
        array $rows,
        array $assignments,
        callable $where,
        array $triggers,
        array $orderBy = [],
        ?int $limit = null,
        string $rowIdColumn = 'setting_id',
    ): array {
        self::validateIdentifier($rowIdColumn, 'rowid column');
        self::validateAssignments($assignments);
        $rows = array_values($rows);
        $indexes = self::selectedIndexes($rows, $where, $orderBy, $limit);
        $audit = [];
        $updated = [];
        $visited = [];

        foreach ($indexes as $index) {
            $old = $rows[$index];
            $new = self::updatedRow($old, $assignments);
            $changedColumns = self::changedColumns($old, $new);
            $visited[] = $old[$rowIdColumn] ?? $index;

            self::fireTriggers('before', 'update', $old, $new, $changedColumns, $triggers, $audit);
            $rows[$index] = $new;
            $updated[] = $new;
            self::fireTriggers('after', 'update', $old, $new, $changedColumns, $triggers, $audit);
        }

        return self::result($rows, $audit, [], $updated, [], $visited);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param callable(array<string,mixed>):bool $where
     * @param list<array<string,mixed>> $triggers
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array{rows:list<array<string,mixed>>,audit:list<array<string,mixed>>,changes:int,inserted:list<array<string,mixed>>,updated:list<array<string,mixed>>,deleted:list<array<string,mixed>>,visited:list<int|string>}
     */
    public static function deleteRows(
        array $rows,
        callable $where,
        array $triggers,
        array $orderBy = [],
        ?int $limit = null,
        string $rowIdColumn = 'setting_id',
    ): array {
        self::validateIdentifier($rowIdColumn, 'rowid column');
        $rows = array_values($rows);
        $indexes = self::selectedIndexes($rows, $where, $orderBy, $limit);
        $audit = [];
        $deleted = [];
        $visited = [];

        foreach ($indexes as $index) {
            $old = $rows[$index];
            $visited[] = $old[$rowIdColumn] ?? $index;
            self::fireTriggers('before', 'delete', $old, null, [], $triggers, $audit);
            unset($rows[$index]);
            $deleted[] = $old;
            self::fireTriggers('after', 'delete', $old, null, [], $triggers, $audit);
        }

        return self::result(array_values($rows), $audit, [], [], $deleted, $visited);
    }

    /**
     * @param list<array<string,mixed>> $triggers
     * @param list<array<string,mixed>> $audit
     */
    private static function fireTriggers(
        string $timing,
        string $event,
        ?array $old,
        ?array $new,
        array $changedColumns,
        array $triggers,
        array &$audit,
    ): void {
        foreach ($triggers as $trigger) {
            self::validateTrigger($trigger);
            if (strtolower((string) $trigger['timing']) !== $timing || strtolower((string) $trigger['event']) !== $event) {
                continue;
            }
            if ($event === 'update' && isset($trigger['of']) && !array_intersect((array) $trigger['of'], $changedColumns)) {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? null, $old, $new)) {
                continue;
            }

            $audit[] = self::auditRow($trigger, $old, $new);
        }
    }

    /**
     * @param array<string,mixed> $trigger
     * @return array<string,mixed>
     */
    private static function auditRow(array $trigger, ?array $old, ?array $new): array
    {
        $row = [
            'trigger' => (string) $trigger['name'],
            'timing' => strtolower((string) $trigger['timing']),
            'event' => strtolower((string) $trigger['event']),
        ];

        foreach ((array) ($trigger['values'] ?? []) as $column => $value) {
            self::validateIdentifier((string) $column, 'audit column');
            $row[$column] = self::resolveValue($value, $old, $new);
        }

        return $row;
    }

    private static function resolveValue(mixed $value, ?array $old, ?array $new): mixed
    {
        if (is_string($value) && str_starts_with($value, 'old.')) {
            if ($old === null) {
                throw new \InvalidArgumentException('SQLite trigger OLD row is unavailable for INSERT');
            }
            $column = substr($value, 4);
            if (!array_key_exists($column, $old)) {
                throw new \InvalidArgumentException("SQLite trigger OLD column {$column} is missing");
            }
            return $old[$column];
        }
        if (is_string($value) && str_starts_with($value, 'new.')) {
            if ($new === null) {
                throw new \InvalidArgumentException('SQLite trigger NEW row is unavailable for DELETE');
            }
            $column = substr($value, 4);
            if (!array_key_exists($column, $new)) {
                throw new \InvalidArgumentException("SQLite trigger NEW column {$column} is missing");
            }
            return $new[$column];
        }
        if (is_string($value) && str_starts_with($value, 'coalesce:')) {
            $parts = explode(':', substr($value, 9));
            foreach ($parts as $part) {
                $resolved = self::resolveValue($part, $old, $new);
                if ($resolved !== null) {
                    return $resolved;
                }
            }

            return null;
        }
        if (is_string($value) && str_starts_with($value, 'concat:')) {
            $parts = explode(':', substr($value, 7));
            $resolved = '';
            foreach ($parts as $part) {
                $resolved .= (string) self::resolveValue($part, $old, $new);
            }

            return $resolved;
        }

        return $value;
    }

    private static function whenMatches(mixed $when, ?array $old, ?array $new): bool
    {
        if ($when === null) {
            return true;
        }
        if (!is_array($when)) {
            throw new \InvalidArgumentException('SQLite trigger WHEN clause is malformed');
        }

        $left = self::resolveValue($when['left'] ?? null, $old, $new);
        $right = self::resolveValue($when['right'] ?? null, $old, $new);
        return match (strtolower((string) ($when['operator'] ?? '='))) {
            '=' => $left === $right,
            '!=' => $left !== $right,
            'is' => $left === $right,
            'is not' => $left !== $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            default => throw new \InvalidArgumentException('SQLite trigger WHEN operator is unsupported'),
        };
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return list<int>
     */
    private static function selectedIndexes(array $rows, callable $where, array $orderBy, ?int $limit): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite DML trigger LIMIT cannot be negative');
        }

        $selected = [];
        foreach ($rows as $index => $row) {
            if ($where($row)) {
                $selected[] = $index;
            }
        }

        if ($orderBy !== []) {
            foreach ($orderBy as $term) {
                self::validateIdentifier((string) ($term['column'] ?? ''), 'ORDER BY column');
                $direction = strtolower((string) ($term['direction'] ?? 'asc'));
                if (!in_array($direction, ['asc', 'desc'], true)) {
                    throw new \InvalidArgumentException('SQLite DML trigger ORDER BY direction is unsupported');
                }
            }
            usort($selected, static function (int $left, int $right) use ($rows, $orderBy): int {
                foreach ($orderBy as $term) {
                    $column = (string) $term['column'];
                    $direction = strtolower((string) ($term['direction'] ?? 'asc'));
                    $comparison = ($rows[$left][$column] ?? null) <=> ($rows[$right][$column] ?? null);
                    if ($comparison !== 0) {
                        return $direction === 'desc' ? -$comparison : $comparison;
                    }
                }

                return $left <=> $right;
            });
        }

        return $limit === null ? $selected : array_slice($selected, 0, $limit);
    }

    /**
     * @param array<string,mixed|callable(array<string,mixed>):mixed> $assignments
     * @return array<string,mixed>
     */
    private static function updatedRow(array $old, array $assignments): array
    {
        $new = $old;
        foreach ($assignments as $column => $value) {
            $new[$column] = is_callable($value) ? $value($old) : $value;
        }

        return $new;
    }

    /**
     * @return list<string>
     */
    private static function changedColumns(array $old, array $new): array
    {
        $columns = [];
        foreach ($new as $column => $value) {
            if (!array_key_exists($column, $old) || $old[$column] !== $value) {
                $columns[] = (string) $column;
            }
        }

        return $columns;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function nextRowId(array $rows, string $rowIdColumn): int
    {
        $max = 0;
        foreach ($rows as $row) {
            $value = $row[$rowIdColumn] ?? null;
            if (is_int($value) && $value > $max) {
                $max = $value;
            }
        }

        return $max + 1;
    }

    /**
     * @param array<string,mixed> $trigger
     */
    private static function validateTrigger(array $trigger): void
    {
        if (!isset($trigger['name']) || !is_string($trigger['name']) || $trigger['name'] === '') {
            throw new \InvalidArgumentException('SQLite DML trigger name is required');
        }
        if (!in_array(strtolower((string) ($trigger['timing'] ?? '')), ['before', 'after'], true)) {
            throw new \InvalidArgumentException('SQLite DML trigger timing is unsupported');
        }
        if (!in_array(strtolower((string) ($trigger['event'] ?? '')), ['insert', 'update', 'delete'], true)) {
            throw new \InvalidArgumentException('SQLite DML trigger event is unsupported');
        }
        if (($trigger['table'] ?? null) !== 'app_settings') {
            throw new \InvalidArgumentException('SQLite DML trigger target table is unsupported');
        }
        if (isset($trigger['of'])) {
            foreach ((array) $trigger['of'] as $column) {
                self::validateIdentifier((string) $column, 'UPDATE OF column');
            }
        }
    }

    /**
     * @param array<string,mixed|callable(array<string,mixed>):mixed> $assignments
     */
    private static function validateAssignments(array $assignments): void
    {
        if ($assignments === []) {
            throw new \InvalidArgumentException('SQLite DML trigger UPDATE assignments cannot be empty');
        }
        foreach (array_keys($assignments) as $column) {
            self::validateIdentifier((string) $column, 'assignment column');
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $audit
     * @param list<array<string,mixed>> $inserted
     * @param list<array<string,mixed>> $updated
     * @param list<array<string,mixed>> $deleted
     * @param list<int|string> $visited
     * @return array{rows:list<array<string,mixed>>,audit:list<array<string,mixed>>,changes:int,inserted:list<array<string,mixed>>,updated:list<array<string,mixed>>,deleted:list<array<string,mixed>>,visited:list<int|string>}
     */
    private static function result(array $rows, array $audit, array $inserted, array $updated, array $deleted, array $visited): array
    {
        return [
            'rows' => array_values($rows),
            'audit' => $audit,
            'changes' => count($inserted) + count($updated) + count($deleted),
            'inserted' => $inserted,
            'updated' => $updated,
            'deleted' => $deleted,
            'visited' => $visited,
        ];
    }

    private static function validateIdentifier(string $identifier, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException("SQLite DML trigger {$label} is malformed");
        }
    }
}
