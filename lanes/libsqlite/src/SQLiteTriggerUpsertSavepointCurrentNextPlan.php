<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerUpsertSavepointCurrentNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<array<string,mixed>> $triggers
     * @param array{savepoint?:string,conflict_action?:string,wal_frame?:int} $options
     * @return array{savepoint:string,rows:list<array<string,mixed>>,attempted_rows:list<array<string,mixed>>,row_results:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,yielded:list<array<string,mixed>>,rolled_back_rows:list<array<string,mixed>>,changes:int,current_wal_frame:int,next_wal_frame:int,savepoint_preserved:bool,dependencies:list<string>}
     */
    public static function execute(
        array $currentRows,
        array $incomingRows,
        array $uniqueColumns,
        array $assignments,
        array $triggers,
        array $options = [],
    ): array {
        $savepoint = trim((string) ($options['savepoint'] ?? 'upsert-statement'));
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite trigger UPSERT savepoint name cannot be empty');
        }
        if ($uniqueColumns === []) {
            throw new \InvalidArgumentException('SQLite trigger UPSERT requires at least one conflict target column');
        }
        foreach ($uniqueColumns as $column) {
            self::identifier($column, 'conflict target column');
        }
        foreach (array_keys($assignments) as $column) {
            self::identifier((string) $column, 'assignment column');
        }

        $state = [
            'rows' => array_values($currentRows),
            'row_results' => [],
            'trigger_effects' => [],
            'yielded' => [],
            'rolled_back_rows' => [],
            'changes' => 0,
        ];
        $initialRows = $state['rows'];
        $baseWalFrame = (int) ($options['wal_frame'] ?? 0);
        $nextWalFrame = $baseWalFrame;
        $conflictAction = self::conflictAction((string) ($options['conflict_action'] ?? 'abort-row'));

        foreach ($incomingRows as $ordinal => $incoming) {
            $rowSavepoint = $state['rows'];
            $rowChanges = $state['changes'];
            $rowWalFrame = $nextWalFrame;
            try {
                self::applyUpsert($state, $incoming, $uniqueColumns, $assignments, $triggers, 0, (int) $ordinal, 'statement', null);
                ++$nextWalFrame;
                $state['row_results'][] = [
                    'ordinal' => (int) $ordinal,
                    'status' => 'changed',
                    'savepoint_action' => 'release-row',
                    'wal_frame' => $nextWalFrame,
                    'key_name' => $incoming['key_name'] ?? null,
                ];
            } catch (SQLiteTriggerUpsertSavepointCurrentNextSignal $signal) {
                if ($signal->action === 'ignore') {
                    $state['row_results'][] = [
                        'ordinal' => $signal->ordinal,
                        'status' => 'skipped',
                        'savepoint_action' => 'release-row',
                        'wal_frame' => $nextWalFrame,
                        'reason' => $signal->reason,
                        'key_name' => $signal->row['key_name'] ?? null,
                    ];
                    $state['yielded'][] = self::yieldRow($signal->ordinal, 'skipped', $signal->event, $signal->old, $signal->row, $signal->depth, $signal->sourceTrigger, $signal->reason);
                    continue;
                }

                $rolledBackRows = self::rowDelta($rowSavepoint, $state['rows']);
                $state['rows'] = $rowSavepoint;
                $state['changes'] = $rowChanges;
                $nextWalFrame = $rowWalFrame;
                $state['rolled_back_rows'] = array_merge($state['rolled_back_rows'], $rolledBackRows);
                $state['row_results'][] = [
                    'ordinal' => $signal->ordinal,
                    'status' => $conflictAction === 'fail-statement' ? 'aborted' : 'rolled-back',
                    'savepoint_action' => 'rollback-row',
                    'wal_frame' => $nextWalFrame,
                    'reason' => $signal->reason,
                    'key_name' => $signal->row['key_name'] ?? null,
                    'rolled_back_count' => count($rolledBackRows),
                ];
                $state['trigger_effects'][] = [
                    'trigger' => null,
                    'timing' => 'savepoint',
                    'event' => 'rollback',
                    'action' => 'rollback-current-row',
                    'ordinal' => $signal->ordinal,
                    'depth' => $signal->depth,
                    'reason' => $signal->reason,
                    'savepoint' => $savepoint,
                    'rolled_back_count' => count($rolledBackRows),
                ];
                if ($conflictAction === 'fail-statement') {
                    break;
                }
            }
        }

        return [
            'savepoint' => $savepoint,
            'rows' => array_values($state['rows']),
            'attempted_rows' => array_values($state['rows']),
            'row_results' => array_values($state['row_results']),
            'trigger_effects' => array_values($state['trigger_effects']),
            'yielded' => array_values($state['yielded']),
            'rolled_back_rows' => array_values($state['rolled_back_rows']),
            'changes' => $state['changes'],
            'current_wal_frame' => $baseWalFrame,
            'next_wal_frame' => $nextWalFrame,
            'savepoint_preserved' => self::rowsEqual($initialRows, $state['rows']),
            'dependencies' => [
                'sqlite-trigger-upsert-savepoint-current-next73',
                'sqlite-row-savepoint-upsert-trigger-yield',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<array<string,mixed>> $triggers
     */
    private static function applyUpsert(array &$state, array $incoming, array $uniqueColumns, array $assignments, array $triggers, int $depth, int $ordinal, string $source, ?string $sourceTrigger): void
    {
        $conflictIndex = self::findConflictIndex($state['rows'], $incoming, $uniqueColumns);
        $event = $conflictIndex === null ? 'insert' : 'update';
        $old = $conflictIndex === null ? null : $state['rows'][$conflictIndex];
        $new = $old ?? $incoming;
        if ($old !== null) {
            foreach ($assignments as $column => $assignment) {
                $new[$column] = $assignment($old, $incoming);
            }
        }

        self::fireTriggers($state, $triggers, 'before', $event, $old, $new, $uniqueColumns, $assignments, $depth, $ordinal);
        if ($old === null) {
            $state['rows'][] = $new;
        } else {
            $state['rows'][$conflictIndex] = $new;
        }
        ++$state['changes'];
        self::fireTriggers($state, $triggers, 'after', $event, $old, $new, $uniqueColumns, $assignments, $depth, $ordinal);
        $state['yielded'][] = self::yieldRow($ordinal, 'changed', $event, $old, $new, $depth, $sourceTrigger ?? $source, null);
    }

    /**
     * @param array<string,mixed> $state
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     */
    private static function fireTriggers(array &$state, array $triggers, string $timing, string $event, ?array $old, array &$new, array $uniqueColumns, array $assignments, int $depth, int $ordinal): void
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
            } elseif ($action === 'upsert-row') {
                self::applyUpsert($state, self::project((array) ($trigger['row'] ?? []), $old, $new), $uniqueColumns, $assignments, $triggers, $depth + 1, $ordinal, 'trigger', (string) ($trigger['name'] ?? ''));
            } elseif ($action === 'raise') {
                throw new SQLiteTriggerUpsertSavepointCurrentNextSignal(
                    self::raiseAction((string) ($trigger['raise'] ?? 'abort')),
                    (string) ($trigger['reason'] ?? 'trigger-raise'),
                    $new,
                    $ordinal,
                    $event,
                    $old,
                    $depth,
                    (string) ($trigger['name'] ?? ''),
                );
            } elseif ($action !== 'audit') {
                throw new \InvalidArgumentException('SQLite trigger UPSERT savepoint action is unsupported');
            }

            $state['trigger_effects'][] = [
                'trigger' => (string) ($trigger['name'] ?? ''),
                'timing' => $timing,
                'event' => $event,
                'action' => $action,
                'depth' => $depth,
                'ordinal' => $ordinal,
                'row' => self::project((array) ($trigger['values'] ?? []), $old, $new),
            ];
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
                if (!array_key_exists($column, $incoming) || !array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite trigger UPSERT conflict column {$column} is missing");
                }
                if ($incoming[$column] === null || $row[$column] === null || $incoming[$column] != $row[$column]) {
                    continue 2;
                }
            }

            return (int) $index;
        }

        return null;
    }

    private static function yieldRow(int $ordinal, string $status, string $event, ?array $old, array $new, int $depth, ?string $sourceTrigger, ?string $reason): array
    {
        return [
            'ordinal' => $ordinal,
            'status' => $status,
            'event' => $event,
            'depth' => $depth,
            'source_trigger' => $sourceTrigger,
            'old_key' => $old['key_name'] ?? null,
            'key_name' => $new['key_name'] ?? null,
            'key_value' => $new['key_value'] ?? null,
            'reason' => $reason,
        ];
    }

    /**
     * @param list<array<string,mixed>> $before
     * @param list<array<string,mixed>> $after
     * @return list<array<string,mixed>>
     */
    private static function rowDelta(array $before, array $after): array
    {
        $beforeJson = [];
        foreach ($before as $row) {
            $beforeJson[] = json_encode($row, JSON_THROW_ON_ERROR);
        }
        $delta = [];
        foreach ($after as $row) {
            if (!in_array(json_encode($row, JSON_THROW_ON_ERROR), $beforeJson, true)) {
                $delta[] = $row;
            }
        }

        return $delta;
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

    private static function value(mixed $value, ?array $old, array $new): mixed
    {
        if (is_string($value) && str_starts_with($value, 'concat:')) {
            $parts = explode(':', substr($value, 7));
            $rendered = '';
            foreach ($parts as $part) {
                $rendered .= $part === '' ? ':' : (string) self::value($part, $old, $new);
            }

            return $rendered;
        }
        if (is_string($value) && str_starts_with($value, 'new_plus.')) {
            $current = self::rowValue($new, substr($value, 9), 'NEW row');
            return is_int($current) ? $current + 1 : $current;
        }
        if (is_string($value) && str_starts_with($value, 'new.')) {
            return self::rowValue($new, substr($value, 4), 'NEW row');
        }
        if (is_string($value) && str_starts_with($value, 'old.')) {
            if ($old === null) {
                throw new \InvalidArgumentException('SQLite trigger UPSERT OLD row is unavailable for INSERT');
            }

            return self::rowValue($old, substr($value, 4), 'OLD row');
        }

        return $value;
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
            throw new \InvalidArgumentException('SQLite trigger UPSERT WHEN clause is malformed');
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
            default => throw new \InvalidArgumentException('SQLite trigger UPSERT WHEN operator is unsupported'),
        };
    }

    private static function rowValue(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite trigger UPSERT {$label} missing column {$column}");
        }

        return $row[$column];
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite trigger UPSERT {$label} is malformed");
        }

        return $value;
    }

    private static function conflictAction(string $action): string
    {
        $action = strtolower($action);
        if (!in_array($action, ['abort-row', 'fail-statement'], true)) {
            throw new \InvalidArgumentException('SQLite trigger UPSERT savepoint conflict action is unsupported');
        }

        return $action;
    }

    private static function raiseAction(string $action): string
    {
        $action = strtolower($action);
        if (!in_array($action, ['abort', 'rollback', 'ignore'], true)) {
            throw new \InvalidArgumentException('SQLite trigger UPSERT raise action is unsupported');
        }

        return $action === 'ignore' ? 'ignore' : 'abort';
    }

    /**
     * @param list<array<string,mixed>> $left
     * @param list<array<string,mixed>> $right
     */
    private static function rowsEqual(array $left, array $right): bool
    {
        return json_encode(array_values($left), JSON_THROW_ON_ERROR) === json_encode(array_values($right), JSON_THROW_ON_ERROR);
    }
}

final class SQLiteTriggerUpsertSavepointCurrentNextSignal extends \RuntimeException
{
    public function __construct(
        public readonly string $action,
        public readonly string $reason,
        public readonly array $row,
        public readonly int $ordinal,
        public readonly string $event,
        public readonly ?array $old,
        public readonly int $depth,
        public readonly ?string $sourceTrigger,
    ) {
        parent::__construct($reason);
    }
}
