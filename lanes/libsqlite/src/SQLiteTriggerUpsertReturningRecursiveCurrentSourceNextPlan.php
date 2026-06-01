<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $savepointRows
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<array<string,mixed>> $triggers
     * @param array{current_source?:string,next_source?:string,savepoint?:string,rollback_on_returning_key?:list<string>,recursive_triggers?:bool,max_depth?:int,conflict_action?:string,key_column?:string,value_column?:string,returning?:list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,?string):mixed>} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $savepointRows,
        array $currentRows,
        array $nextRows,
        array $uniqueColumns,
        array $assignments,
        array $triggers,
        array $options = [],
    ): array {
        if ($currentRows === [] || !array_is_list($currentRows)) {
            throw new \InvalidArgumentException('SQLite recursive UPSERT RETURNING current-source rows must be a non-empty list');
        }
        if ($nextRows === [] || !array_is_list($nextRows)) {
            throw new \InvalidArgumentException('SQLite recursive UPSERT RETURNING next-source rows must be a non-empty list');
        }

        $savepoint = self::token((string) ($options['savepoint'] ?? 'app_recursive_upsert_returning_145'), 'savepoint');
        $currentSource = self::token((string) ($options['current_source'] ?? 'current-recursive-upsert-returning-145'), 'current source');
        $nextSource = self::token((string) ($options['next_source'] ?? 'next-recursive-upsert-returning-145'), 'next source');
        $keyColumn = self::identifier((string) ($options['key_column'] ?? 'key_name'), 'key column');
        $valueColumn = self::identifier((string) ($options['value_column'] ?? 'key_value'), 'value column');
        $rollbackKeys = self::rollbackKeys($options['rollback_on_returning_key'] ?? []);
        $baseRows = array_values($savepointRows);

        $current = SQLiteRecursiveUpsertConflictYieldPlan::execute(
            $baseRows,
            array_values($currentRows),
            $uniqueColumns,
            $assignments,
            $triggers,
            $options,
        );
        $currentStream = self::stream($currentSource, 'current', $current['yielded'], $keyColumn, $valueColumn);
        $barrier = self::firstBarrier($currentStream, $rollbackKeys);
        $currentRolledBack = $barrier !== null;
        $nextStartRows = $currentRolledBack ? $baseRows : array_values($current['rows']);

        $next = SQLiteRecursiveUpsertConflictYieldPlan::execute(
            $nextStartRows,
            array_values($nextRows),
            $uniqueColumns,
            $assignments,
            $triggers,
            $options,
        );
        $nextStream = self::stream($nextSource, 'next', $next['yielded'], $keyColumn, $valueColumn);

        $attemptedCurrentReturning = self::returningOnly($currentStream);
        $nextReturning = self::returningOnly($nextStream);

        return [
            'status' => $currentRolledBack
                ? 'recursive-upsert-returning-current-source-rolled-back-next145'
                : 'recursive-upsert-returning-current-source-released-next145',
            'savepoint' => $savepoint,
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'savepoint_rows' => $baseRows,
            'current_attempt_rows' => array_values($current['rows']),
            'next_start_rows' => $nextStartRows,
            'next_rows' => array_values($next['rows']),
            'current' => $current,
            'next' => $next,
            'attempted_current_yield_stream' => $currentStream,
            'current_yield_stream' => $currentRolledBack ? [] : $currentStream,
            'next_yield_stream' => $nextStream,
            'yield_stream' => array_merge($currentRolledBack ? [] : $currentStream, $nextStream),
            'attempted_current_returning_rows' => $attemptedCurrentReturning,
            'current_returning_rows' => $currentRolledBack ? [] : $attemptedCurrentReturning,
            'next_returning_rows' => $nextReturning,
            'returning_rows' => array_merge($currentRolledBack ? [] : $attemptedCurrentReturning, $nextReturning),
            'current_rolled_back' => $currentRolledBack,
            'rollback_barrier' => $barrier,
            'next_started_from' => $currentRolledBack ? 'savepoint' : 'current-source',
            'current_changes' => $currentRolledBack ? 0 : (int) $current['changes'],
            'attempted_current_changes' => (int) $current['changes'],
            'next_changes' => (int) $next['changes'],
            'committed_changes' => ($currentRolledBack ? 0 : (int) $current['changes']) + (int) $next['changes'],
            'recursive_summary' => self::summary($currentSource, $nextSource, $baseRows, $current, $next, $currentRolledBack, $barrier, $keyColumn),
            'dependencies' => [
                'sqlite-trigger-upsert-returning-recursive-current-source-next145',
                'sqlite-recursive-upsert-returning-savepoint-barrier',
                'sqlite-next-source-restarts-after-recursive-returning-rollback',
                'sqlite-recursive-upsert-trigger-returning-current-source-next126',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $yielded
     * @return list<array<string,mixed>>
     */
    private static function stream(string $sourceToken, string $phase, array $yielded, string $keyColumn, string $valueColumn): array
    {
        $stream = [];
        foreach ($yielded as $index => $yield) {
            $returning = $yield['returning'] ?? null;
            $stream[] = [
                'source_token' => $sourceToken,
                'phase' => $phase,
                'yield_index' => $index,
                'ordinal' => $yield['ordinal'] ?? null,
                'source' => $yield['source'] ?? null,
                'trigger' => $yield['trigger'] ?? null,
                'status' => $yield['status'] ?? null,
                'event' => $yield['event'] ?? null,
                'depth' => $yield['depth'] ?? null,
                'old_key' => $yield['old_key'] ?? null,
                'incoming_key' => $yield['incoming_key'] ?? null,
                'new_key' => $yield['new_key'] ?? null,
                'returning_visible' => is_array($returning),
                'returning_key' => is_array($returning) ? ($returning[$keyColumn] ?? null) : null,
                'returning_value' => is_array($returning) ? ($returning['value'] ?? $returning[$valueColumn] ?? null) : null,
                'returning' => $returning,
            ];
        }

        return $stream;
    }

    /**
     * @param list<array<string,mixed>> $stream
     * @return list<array<string,mixed>>
     */
    private static function returningOnly(array $stream): array
    {
        return array_values(array_filter($stream, static fn (array $entry): bool => is_array($entry['returning'] ?? null)));
    }

    /**
     * @param list<string> $rollbackKeys
     * @param list<array<string,mixed>> $stream
     * @return array<string,mixed>|null
     */
    private static function firstBarrier(array $stream, array $rollbackKeys): ?array
    {
        if ($rollbackKeys === []) {
            return null;
        }
        foreach ($stream as $entry) {
            $key = $entry['returning_key'] ?? null;
            if (is_string($key) && in_array($key, $rollbackKeys, true)) {
                return [
                    'reason' => 'recursive-returning-savepoint-barrier',
                    'returning_key' => $key,
                    'phase' => $entry['phase'],
                    'yield_index' => $entry['yield_index'],
                    'depth' => $entry['depth'],
                    'trigger' => $entry['trigger'],
                    'source_token' => $entry['source_token'],
                ];
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $baseRows
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return array<string,mixed>
     */
    private static function summary(string $currentSource, string $nextSource, array $baseRows, array $current, array $next, bool $rolledBack, ?array $barrier, string $keyColumn): array
    {
        $currentRows = array_values((array) ($current['rows'] ?? []));
        $nextRows = array_values((array) ($next['rows'] ?? []));

        return [
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'rolled_back' => $rolledBack,
            'rollback_key' => $barrier['returning_key'] ?? null,
            'savepoint_keys' => self::keys($baseRows, $keyColumn),
            'attempted_current_keys' => self::keys($currentRows, $keyColumn),
            'next_keys' => self::keys($nextRows, $keyColumn),
            'attempted_current_new_keys' => array_values(array_diff(self::keys($currentRows, $keyColumn), self::keys($baseRows, $keyColumn))),
            'next_new_keys' => array_values(array_diff(self::keys($nextRows, $keyColumn), self::keys($rolledBack ? $baseRows : $currentRows, $keyColumn))),
            'next_replayed_current_key' => $rolledBack && in_array($barrier['returning_key'] ?? null, self::keys($nextRows, $keyColumn), true),
            'current_returning_attempts' => count((array) ($current['returning'] ?? [])),
            'next_returning_attempts' => count((array) ($next['returning'] ?? [])),
            'current_max_depth' => (int) ($current['max_depth_seen'] ?? 0),
            'next_max_depth' => (int) ($next['max_depth_seen'] ?? 0),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function keys(array $rows, string $keyColumn): array
    {
        $keys = [];
        foreach ($rows as $row) {
            if (array_key_exists($keyColumn, $row)) {
                $keys[] = (string) $row[$keyColumn];
            }
        }

        return $keys;
    }

    /**
     * @param list<string> $keys
     * @return list<string>
     */
    private static function rollbackKeys(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[] = self::token((string) $key, 'rollback key');
        }

        return $out;
    }

    private static function token(string $token, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $token) !== 1) {
            throw new \InvalidArgumentException("SQLite recursive UPSERT RETURNING {$label} is malformed");
        }

        return $token;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite recursive UPSERT RETURNING {$label} is malformed");
        }

        return $value;
    }
}
