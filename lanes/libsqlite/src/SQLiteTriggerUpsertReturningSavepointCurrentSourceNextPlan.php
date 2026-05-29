<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerUpsertReturningSavepointCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $savepointRows
     * @param list<array<string,mixed>> $currentIncoming
     * @param list<array<string,mixed>> $nextIncoming
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{savepoint?:string,wal_frame?:int} $options
     * @return array{savepoint:string,status:string,savepoint_rows:list<array<string,mixed>>,current_attempt_rows:list<array<string,mixed>>,next_rows:list<array<string,mixed>>,current_returning_rows:list<array<string,mixed>>,attempted_current_yields:list<array<string,mixed>>,next_returning_rows:list<array<string,mixed>>,attempted_next_yields:list<array<string,mixed>>,current_trigger_effects:list<array<string,mixed>>,next_trigger_effects:list<array<string,mixed>>,discarded_current_rows:list<array<string,mixed>>,current_changes:int,next_changes:int,total_attempted_changes:int,committed_changes:int,current_wal_frame:int,next_wal_frame:int,current_rolled_back:bool,next_started_from_savepoint:bool,returning_suppressed_after_rollback:bool,rollback_reason:?string,dependencies:list<string>}
     */
    public static function execute(
        array $savepointRows,
        array $currentIncoming,
        array $nextIncoming,
        array $uniqueColumns,
        array $assignments,
        array $triggers,
        array $returning,
        array $options = [],
    ): array {
        $savepoint = trim((string) ($options['savepoint'] ?? 'upsert-returning-current'));
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite trigger UPSERT RETURNING savepoint current-source name cannot be empty');
        }
        self::validateColumns($uniqueColumns, 'conflict target column');
        self::validateColumns(array_keys($assignments), 'assignment column');
        if ($returning === []) {
            throw new \InvalidArgumentException('SQLite trigger UPSERT RETURNING savepoint current-source projection cannot be empty');
        }

        $baseRows = array_values($savepointRows);
        $baseWal = (int) ($options['wal_frame'] ?? 0);
        $current = self::runPhase($baseRows, $currentIncoming, $uniqueColumns, $assignments, $triggers, $returning, 'current');
        if ($current['rolled_back']) {
            $next = self::runPhase($baseRows, $nextIncoming, $uniqueColumns, $assignments, $triggers, $returning, 'next');
            $nextStartedFromSavepoint = self::rowsEqual($baseRows, $next['started_rows']);
            $status = $next['rolled_back'] ? 'current-and-next-rolled-back-to-savepoint' : 'current-rolled-back-next-source-applied';
            $nextWalFrame = $baseWal + $next['changes'];
        } else {
            $next = self::runPhase($current['rows'], $nextIncoming, $uniqueColumns, $assignments, $triggers, $returning, 'next');
            $nextStartedFromSavepoint = false;
            $status = $next['rolled_back'] ? 'current-released-next-rolled-back' : 'current-and-next-released';
            $nextWalFrame = $baseWal + $current['changes'] + $next['changes'];
        }

        return [
            'savepoint' => $savepoint,
            'status' => $status,
            'savepoint_rows' => $baseRows,
            'current_attempt_rows' => $current['attempt_rows'],
            'next_rows' => $next['rolled_back'] ? $next['started_rows'] : $next['rows'],
            'current_returning_rows' => $current['rolled_back'] ? [] : $current['returning_rows'],
            'attempted_current_yields' => $current['yielded'],
            'next_returning_rows' => $next['rolled_back'] ? [] : $next['returning_rows'],
            'attempted_next_yields' => $next['yielded'],
            'current_trigger_effects' => $current['effects'],
            'next_trigger_effects' => $next['effects'],
            'discarded_current_rows' => $current['rolled_back'] ? self::rowDelta($baseRows, $current['attempt_rows']) : [],
            'current_changes' => $current['rolled_back'] ? 0 : $current['changes'],
            'next_changes' => $next['rolled_back'] ? 0 : $next['changes'],
            'total_attempted_changes' => $current['attempted_changes'] + $next['attempted_changes'],
            'committed_changes' => ($current['rolled_back'] ? 0 : $current['changes']) + ($next['rolled_back'] ? 0 : $next['changes']),
            'current_wal_frame' => $baseWal,
            'next_wal_frame' => $nextWalFrame,
            'current_rolled_back' => $current['rolled_back'],
            'next_started_from_savepoint' => $nextStartedFromSavepoint,
            'returning_suppressed_after_rollback' => $current['rolled_back'] && $current['returning_rows'] !== [],
            'rollback_reason' => $current['rollback_reason'],
            'dependencies' => [
                'sqlite-trigger-upsert-returning-savepoint-current-source-next129',
                'sqlite-returning-yield-before-current-savepoint-rollback',
                'sqlite-next-upsert-starts-from-savepoint-source',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $startRows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @return array{started_rows:list<array<string,mixed>>,rows:list<array<string,mixed>>,attempt_rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,yielded:list<array<string,mixed>>,effects:list<array<string,mixed>>,changes:int,attempted_changes:int,rolled_back:bool,rollback_reason:?string}
     */
    private static function runPhase(array $startRows, array $incomingRows, array $uniqueColumns, array $assignments, array $triggers, array $returning, string $phase): array
    {
        $rows = array_values($startRows);
        $returningRows = [];
        $yielded = [];
        $effects = [];
        $changes = 0;
        $attemptedChanges = 0;
        $rolledBack = false;
        $rollbackReason = null;

        foreach (array_values($incomingRows) as $ordinal => $incoming) {
            $rowBefore = $rows;
            try {
                $applied = self::applyUpsert($rows, $incoming, $uniqueColumns, $assignments, $triggers, $returning, $phase, (int) $ordinal, $effects);
            } catch (SQLiteTriggerUpsertReturningSavepointCurrentSourceNext129Signal $signal) {
                $rolledBack = true;
                $rollbackReason = $signal->reason;
                $effects[] = [
                    'trigger' => null,
                    'timing' => 'savepoint',
                    'event' => 'rollback',
                    'action' => 'rollback-current-source',
                    'phase' => $phase,
                    'ordinal' => $signal->ordinal,
                    'reason' => $signal->reason,
                    'discarded_count' => count(self::rowDelta($rowBefore, $rows)),
                ];
                break;
            }

            ++$changes;
            ++$attemptedChanges;
            $returningRows[] = $applied['returning'];
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => (int) $ordinal,
                'status' => 'changed',
                'event' => $applied['event'],
                'option_name' => $applied['new']['option_name'] ?? null,
                'old_option_name' => $applied['old']['option_name'] ?? null,
                'returning' => $applied['returning'],
            ];
        }

        return [
            'started_rows' => array_values($startRows),
            'rows' => array_values($rolledBack ? $startRows : $rows),
            'attempt_rows' => array_values($rows),
            'returning_rows' => $returningRows,
            'yielded' => $yielded,
            'effects' => $effects,
            'changes' => $changes,
            'attempted_changes' => $attemptedChanges,
            'rolled_back' => $rolledBack,
            'rollback_reason' => $rollbackReason,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param list<array<string,mixed>> $effects
     * @return array{event:string,old:?array<string,mixed>,new:array<string,mixed>,returning:array<string,mixed>}
     */
    private static function applyUpsert(array &$rows, array $incoming, array $uniqueColumns, array $assignments, array $triggers, array $returning, string $phase, int $ordinal, array &$effects): array
    {
        $index = self::conflictIndex($rows, $incoming, $uniqueColumns);
        $event = $index === null ? 'insert' : 'update';
        $old = $index === null ? null : $rows[$index];
        $new = $old ?? $incoming;
        if ($old !== null) {
            foreach ($assignments as $column => $assignment) {
                $new[$column] = $assignment($old, $incoming);
            }
        }

        self::fireTriggers($triggers, 'before', $event, $old, $new, $phase, $ordinal, $effects);
        if ($index === null) {
            $rows[] = $new;
        } else {
            $rows[$index] = $new;
        }
        self::fireTriggers($triggers, 'after', $event, $old, $new, $phase, $ordinal, $effects);

        return [
            'event' => $event,
            'old' => $old,
            'new' => $new,
            'returning' => self::returningRow($returning, $new, $old, $incoming, $event, $ordinal),
        ];
    }

    /**
     * @param list<array<string,mixed>> $triggers
     * @param list<array<string,mixed>> $effects
     */
    private static function fireTriggers(array $triggers, string $timing, string $event, ?array $old, array &$new, string $phase, int $ordinal, array &$effects): void
    {
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
            } elseif ($action === 'raise') {
                throw new SQLiteTriggerUpsertReturningSavepointCurrentSourceNext129Signal((string) ($trigger['reason'] ?? 'trigger-rollback'), $ordinal);
            } elseif ($action !== 'audit') {
                throw new \InvalidArgumentException('SQLite trigger UPSERT RETURNING savepoint current-source trigger action is unsupported');
            }

            $effects[] = [
                'trigger' => (string) ($trigger['name'] ?? ''),
                'timing' => $timing,
                'event' => $event,
                'action' => $action,
                'phase' => $phase,
                'ordinal' => $ordinal,
                'row' => self::project((array) ($trigger['values'] ?? []), $old, $new),
            ];
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     */
    private static function conflictIndex(array $rows, array $incoming, array $uniqueColumns): ?int
    {
        foreach ($rows as $index => $row) {
            foreach ($uniqueColumns as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $incoming)) {
                    throw new \InvalidArgumentException("SQLite trigger UPSERT RETURNING savepoint current-source conflict column {$column} is missing");
                }
                if ($row[$column] === null || $incoming[$column] === null || $row[$column] != $incoming[$column]) {
                    continue 2;
                }
            }

            return (int) $index;
        }

        return null;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returningRow(array $returning, array $new, ?array $old, array $incoming, string $event, int $ordinal): array
    {
        $row = [];
        foreach ($returning as $index => $term) {
            if (is_callable($term)) {
                $row['expr' . $index] = $term($new, $old, $incoming, $event, $ordinal);
                continue;
            }
            if (is_array($term)) {
                $expr = (string) ($term['expr'] ?? '');
                $alias = (string) ($term['as'] ?? $expr);
                $row[self::identifier($alias, 'RETURNING alias')] = self::returningValue($expr, $new, $old, $incoming);
                continue;
            }
            if (!is_string($term) || $term === '') {
                throw new \InvalidArgumentException('SQLite trigger UPSERT RETURNING savepoint current-source projection term is malformed');
            }
            $row[self::identifier(str_contains($term, '.') ? substr($term, (int) strrpos($term, '.') + 1) : $term, 'RETURNING alias')] = self::returningValue($term, $new, $old, $incoming);
        }

        return $row;
    }

    private static function returningValue(string $expr, array $new, ?array $old, array $incoming): mixed
    {
        $expr = trim($expr);
        if (str_starts_with($expr, 'new.')) {
            return self::rowValue($new, substr($expr, 4), 'NEW row');
        }
        if (str_starts_with($expr, 'old.')) {
            if ($old === null) {
                throw new \InvalidArgumentException('SQLite trigger UPSERT RETURNING savepoint current-source OLD row is unavailable');
            }

            return self::rowValue($old, substr($expr, 4), 'OLD row');
        }
        if (str_starts_with($expr, 'old_or_null.')) {
            return $old === null ? null : self::rowValue($old, substr($expr, 12), 'OLD row');
        }
        if (str_starts_with($expr, 'excluded.')) {
            return self::rowValue($incoming, substr($expr, 9), 'excluded row');
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
            throw new \InvalidArgumentException('SQLite trigger UPSERT RETURNING savepoint current-source WHEN clause is malformed');
        }
        [$left, $operator, $right] = array_values($when);
        $left = self::value($left, $old, $new);
        $right = self::value($right, $old, $new);

        return match (strtoupper((string) $operator)) {
            '=', '==' => $left == $right,
            '!=', '<>' => $left != $right,
            'IS' => $left === $right,
            'IS NOT' => $left !== $right,
            default => throw new \InvalidArgumentException('SQLite trigger UPSERT RETURNING savepoint current-source WHEN operator is unsupported'),
        };
    }

    /** @param array<string,mixed> $template */
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
        if (is_string($value) && str_starts_with($value, 'concat:')) {
            $rendered = '';
            foreach (explode(':', substr($value, 7)) as $part) {
                $rendered .= $part === '' ? ':' : (string) self::value($part, $old, $new);
            }

            return $rendered;
        }
        if (is_string($value) && str_starts_with($value, 'new.')) {
            return self::rowValue($new, substr($value, 4), 'NEW row');
        }
        if (is_string($value) && str_starts_with($value, 'old.')) {
            if ($old === null) {
                throw new \InvalidArgumentException('SQLite trigger UPSERT RETURNING savepoint current-source OLD row is unavailable');
            }

            return self::rowValue($old, substr($value, 4), 'OLD row');
        }

        return $value;
    }

    /** @param list<array<string,mixed>> $before @param list<array<string,mixed>> $after @return list<array<string,mixed>> */
    private static function rowDelta(array $before, array $after): array
    {
        $encodedBefore = array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $before);
        $delta = [];
        foreach ($after as $row) {
            if (!in_array(json_encode($row, JSON_THROW_ON_ERROR), $encodedBefore, true)) {
                $delta[] = $row;
            }
        }

        return $delta;
    }

    /** @param list<array<string,mixed>> $left @param list<array<string,mixed>> $right */
    private static function rowsEqual(array $left, array $right): bool
    {
        return json_encode(array_values($left), JSON_THROW_ON_ERROR) === json_encode(array_values($right), JSON_THROW_ON_ERROR);
    }

    /** @param iterable<string|int,mixed> $columns */
    private static function validateColumns(iterable $columns, string $label): void
    {
        $seen = false;
        foreach ($columns as $column) {
            $seen = true;
            self::identifier((string) $column, $label);
        }
        if (!$seen && $label === 'conflict target column') {
            throw new \InvalidArgumentException('SQLite trigger UPSERT RETURNING savepoint current-source requires conflict target columns');
        }
    }

    private static function rowValue(array $row, string $column, string $label): mixed
    {
        self::identifier($column, $label . ' column');
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite trigger UPSERT RETURNING savepoint current-source {$label} column {$column} is missing");
        }

        return $row[$column];
    }

    private static function identifier(string $value, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new \InvalidArgumentException("SQLite trigger UPSERT RETURNING savepoint current-source {$label} is malformed");
        }

        return $value;
    }
}

final class SQLiteTriggerUpsertReturningSavepointCurrentSourceNext129Signal extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $ordinal)
    {
        parent::__construct($reason);
    }
}
