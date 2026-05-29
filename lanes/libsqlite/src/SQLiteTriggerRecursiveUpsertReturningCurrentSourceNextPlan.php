<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerRecursiveUpsertReturningCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<array<string,mixed>> $triggers
     * @param array{recursive_triggers?:bool,max_depth?:int,conflict_action?:string,current_source?:string,next_source?:string,returning?:list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,?string):mixed>} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentRows,
        array $nextRows,
        array $uniqueColumns,
        array $assignments,
        array $triggers,
        array $options = [],
    ): array {
        if ($currentRows === [] || !array_is_list($currentRows)) {
            throw new \InvalidArgumentException('SQLite trigger recursive UPSERT RETURNING current-source rows must be a non-empty list');
        }
        if ($nextRows === [] || !array_is_list($nextRows)) {
            throw new \InvalidArgumentException('SQLite trigger recursive UPSERT RETURNING next-source rows must be a non-empty list');
        }

        $currentSource = self::source((string) ($options['current_source'] ?? 'current'));
        $nextSource = self::source((string) ($options['next_source'] ?? 'next'));
        $baseRows = array_values($rows);

        $current = SQLiteRecursiveUpsertConflictYieldPlan::execute(
            $baseRows,
            array_values($currentRows),
            $uniqueColumns,
            $assignments,
            $triggers,
            $options,
        );
        $nextSourceRows = array_values($current['rows']);

        $next = SQLiteRecursiveUpsertConflictYieldPlan::execute(
            $nextSourceRows,
            array_values($nextRows),
            $uniqueColumns,
            $assignments,
            $triggers,
            $options,
        );

        $currentYield = self::stream($currentSource, 'current', $current['yielded']);
        $nextYield = self::stream($nextSource, 'next', $next['yielded']);
        $currentReturning = self::returningOnly($currentYield);
        $nextReturning = self::returningOnly($nextYield);

        return [
            'status' => 'current-source-returning-drained-before-next-source',
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'base_rows' => $baseRows,
            'current_source_rows' => $baseRows,
            'next_source_rows' => $nextSourceRows,
            'rows' => array_values($next['rows']),
            'current' => $current,
            'next' => $next,
            'yield_stream' => array_merge($currentYield, $nextYield),
            'current_yield_stream' => $currentYield,
            'next_yield_stream' => $nextYield,
            'current_returning_rows' => $currentReturning,
            'next_returning_rows' => $nextReturning,
            'statement_returning_rows' => self::depthRows($currentReturning, 0),
            'recursive_returning_rows' => self::recursiveRows($currentReturning),
            'next_statement_returning_rows' => self::depthRows($nextReturning, 0),
            'next_recursive_returning_rows' => self::recursiveRows($nextReturning),
            'handoff' => self::handoff($currentSource, $nextSource, $baseRows, $nextSourceRows, $currentYield),
            'changes' => (int) $current['changes'] + (int) $next['changes'],
            'dependencies' => [
                'sqlite-trigger-recursive-upsert-returning-current-source-next126',
                'sqlite-returning-current-source-drain-before-next-source',
                'sqlite-recursive-upsert-trigger-returning-current-source-next118',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $yielded
     * @return list<array<string,mixed>>
     */
    private static function stream(string $sourceToken, string $phase, array $yielded): array
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
                'returning_key' => is_array($returning) ? ($returning['option_name'] ?? null) : null,
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
        $rows = [];
        foreach ($stream as $entry) {
            if (is_array($entry['returning'] ?? null)) {
                $rows[] = $entry;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function depthRows(array $rows, int $depth): array
    {
        return array_values(array_filter($rows, static fn (array $row): bool => (int) ($row['depth'] ?? -1) === $depth));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function recursiveRows(array $rows): array
    {
        return array_values(array_filter($rows, static fn (array $row): bool => (int) ($row['depth'] ?? 0) > 0));
    }

    /**
     * @param list<array<string,mixed>> $before
     * @param list<array<string,mixed>> $after
     * @param list<array<string,mixed>> $currentYield
     * @return array<string,mixed>
     */
    private static function handoff(string $currentSource, string $nextSource, array $before, array $after, array $currentYield): array
    {
        return [
            'from' => $currentSource,
            'to' => $nextSource,
            'before_count' => count($before),
            'after_count' => count($after),
            'returning_rows_drained' => count(self::returningOnly($currentYield)),
            'yield_rows_drained' => count($currentYield),
            'before_keys' => self::keys($before),
            'after_keys' => self::keys($after),
            'inserted_keys' => array_values(array_diff(self::keys($after), self::keys($before))),
            'returning_keys' => array_values(array_filter(array_column(self::returningOnly($currentYield), 'returning_key'), static fn (mixed $key): bool => $key !== null)),
            'next_source_contains_all_returning_keys' => self::containsKeys($after, array_column(self::returningOnly($currentYield), 'returning_key')),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function keys(array $rows): array
    {
        return array_values(array_map(static fn (array $row): mixed => $row['option_name'] ?? null, $rows));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<mixed> $keys
     */
    private static function containsKeys(array $rows, array $keys): bool
    {
        $available = self::keys($rows);
        foreach ($keys as $key) {
            if ($key !== null && !in_array($key, $available, true)) {
                return false;
            }
        }

        return true;
    }

    private static function source(string $source): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $source) !== 1) {
            throw new \InvalidArgumentException('SQLite trigger recursive UPSERT RETURNING current-source token is malformed');
        }

        return $source;
    }
}
