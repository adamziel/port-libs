<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerUpsertDeferredReturningCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentIncoming
     * @param list<array<string,mixed>> $nextIncoming
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param list<array<string,mixed>> $parentRows
     * @param array{child_key:string,parent_key:string,child_table?:string,parent_table?:string,deferred?:bool} $foreignKey
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentIncoming,
        array $nextIncoming,
        array $uniqueColumns,
        array $assignments,
        array $triggers,
        array $returning,
        array $parentRows,
        array $foreignKey,
        array $options = [],
    ): array {
        $savepoint = self::identifier((string) ($options['savepoint'] ?? 'app_import_deferred_upsert'), 'savepoint');
        $currentSource = self::source((string) ($options['current_source'] ?? 'current-trigger-upsert-returning'));
        $nextSource = self::source((string) ($options['next_source'] ?? 'next-trigger-upsert-returning'));
        $rollbackOnDeferred = (bool) ($options['rollback_on_deferred_violation'] ?? true);
        $deferred = (bool) ($foreignKey['deferred'] ?? true);

        $childKey = self::identifier((string) ($foreignKey['child_key'] ?? ''), 'child foreign key');
        $parentKey = self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'parent foreign key');
        $childTable = (string) ($foreignKey['child_table'] ?? 'child');
        $parentTable = (string) ($foreignKey['parent_table'] ?? 'parent');

        $current = SQLiteTriggerUpsertReturningSavepointCurrentSourceNextPlan::execute(
            $rows,
            $currentIncoming,
            [],
            $uniqueColumns,
            $assignments,
            $triggers,
            $returning,
            ['savepoint' => $savepoint, 'wal_frame' => (int) ($options['wal_frame'] ?? 0)],
        );

        if ($current['current_rolled_back'] === true) {
            throw new \InvalidArgumentException('SQLite deferred UPSERT RETURNING next137 requires current source to reach deferred validation');
        }

        $violations = $deferred ? self::violations((array) $current['next_rows'], $parentRows, $childKey, $parentKey, $childTable, $parentTable) : [];
        $blocked = $violations !== [];
        $nextStartRows = $blocked && $rollbackOnDeferred ? array_values($rows) : (array) $current['next_rows'];
        $next = SQLiteTriggerUpsertReturningSavepointCurrentSourceNextPlan::execute(
            $nextStartRows,
            [],
            $nextIncoming,
            $uniqueColumns,
            $assignments,
            $triggers,
            $returning,
            ['savepoint' => $savepoint . '_next', 'wal_frame' => (int) $current['next_wal_frame']],
        );

        $currentStream = self::tagStream((array) $current['current_returning_rows'], 'current', $currentSource, !$blocked);
        $attemptedStream = self::tagStream((array) $current['current_returning_rows'], 'current', $currentSource, false);
        $nextStream = self::tagStream((array) $next['next_returning_rows'], 'next', $nextSource, true);

        return [
            'status' => $blocked
                ? 'trigger-upsert-deferred-returning-current-source-next137-rolled-back'
                : 'trigger-upsert-deferred-returning-current-source-next137-released',
            'savepoint' => $savepoint,
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'deferred' => $deferred,
            'rollback_on_deferred_violation' => $rollbackOnDeferred,
            'before' => array_values($rows),
            'parent_rows' => array_values($parentRows),
            'current_attempt_rows' => $current['current_attempt_rows'],
            'current_statement_rows' => $current['next_rows'],
            'next_start_rows' => $nextStartRows,
            'next_rows' => $next['next_rows'],
            'deferred_violations' => $violations,
            'deferred_violation_count' => count($violations),
            'commit_blocked_after_returning' => $blocked,
            'current_returning_rows' => $blocked ? [] : $current['current_returning_rows'],
            'attempted_current_returning_rows' => $current['current_returning_rows'],
            'next_returning_rows' => $next['next_returning_rows'],
            'returning_rows' => array_merge($blocked ? [] : $current['current_returning_rows'], $next['next_returning_rows']),
            'current_source_stream' => $currentStream,
            'attempted_source_stream' => $blocked ? $attemptedStream : [],
            'next_source_stream' => $nextStream,
            'suppressed_current_source_stream' => $blocked ? $currentStream : [],
            'discarded_current_rows' => $blocked ? self::delta($rows, (array) $current['next_rows']) : [],
            'current_changes' => $blocked ? 0 : (int) $current['current_changes'],
            'attempted_current_changes' => (int) $current['current_changes'],
            'next_changes' => (int) $next['next_changes'],
            'committed_changes' => ($blocked ? 0 : (int) $current['current_changes']) + (int) $next['next_changes'],
            'source_transition' => [
                'current' => $currentSource,
                'next' => $nextSource,
                'barrier' => $blocked ? 'deferred-trigger-violation-rolls-back-current-source' : 'deferred-trigger-check-admits-current-source',
                'next_started_from' => $blocked && $rollbackOnDeferred ? 'savepoint' : 'current-source',
            ],
            'dependencies' => [
                'sqlite-trigger-upsert-deferred-returning-current-source-next137',
                'sqlite-trigger-upsert-returning-savepoint-current-source-next129',
                'sqlite-deferred-trigger-source-barrier-after-returning',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $parentRows
     * @return list<array<string,mixed>>
     */
    private static function violations(array $childRows, array $parentRows, string $childKey, string $parentKey, string $childTable, string $parentTable): array
    {
        $parents = [];
        foreach ($parentRows as $parent) {
            if (!array_key_exists($parentKey, $parent)) {
                throw new \InvalidArgumentException("SQLite deferred trigger UPSERT parent key {$parentKey} is missing");
            }
            if ($parent[$parentKey] !== null) {
                $parents[(string) $parent[$parentKey]] = true;
            }
        }

        $violations = [];
        foreach ($childRows as $ordinal => $child) {
            if (!array_key_exists($childKey, $child)) {
                throw new \InvalidArgumentException("SQLite deferred trigger UPSERT child key {$childKey} is missing");
            }
            $value = $child[$childKey];
            if ($value === null || isset($parents[(string) $value])) {
                continue;
            }
            $violations[] = [
                'child_table' => $childTable,
                'parent_table' => $parentTable,
                'child_key' => $childKey,
                'parent_key' => $parentKey,
                'value' => $value,
                'rowid' => $child['setting_id'] ?? $child['rowid'] ?? $ordinal + 1,
                'deferred_until' => 'source-release',
            ];
        }

        return $violations;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function tagStream(array $rows, string $phase, string $source, bool $admitted): array
    {
        $stream = [];
        foreach ($rows as $ordinal => $row) {
            $stream[] = [
                'phase' => $phase,
                'source' => $source,
                'ordinal' => $ordinal,
                'admitted' => $admitted,
                'returning' => $row,
            ];
        }

        return $stream;
    }

    /**
     * @param list<array<string,mixed>> $before
     * @param list<array<string,mixed>> $after
     * @return list<array<string,mixed>>
     */
    private static function delta(array $before, array $after): array
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

    private static function source(string $source): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]+$/', $source)) {
            throw new \InvalidArgumentException('SQLite deferred trigger UPSERT source is malformed');
        }

        return $source;
    }

    private static function identifier(string $identifier, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("SQLite deferred trigger UPSERT {$label} is malformed");
        }

        return $identifier;
    }
}
