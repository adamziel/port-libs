<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerUpsertReturningRecursiveViewPlan
{
    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $viewRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param array{parent_key:string,child_key:string,deferred?:bool} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,array<string,mixed>,array<string,mixed>,string):mixed>|null $returning
     * @param array{recursive_triggers?:bool,max_depth?:int,conflict_action?:string,view_name?:string,savepoint?:string,view_trigger?:string} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $parentRows,
        array $childRows,
        array $viewRows,
        array $uniqueColumns,
        array $assignments,
        array $foreignKey,
        array $triggers,
        ?array $returning = null,
        array $options = [],
    ): array {
        $plan = SQLiteRecursiveViewReturningPlan::execute(
            $parentRows,
            $childRows,
            $viewRows,
            $uniqueColumns,
            $assignments,
            $foreignKey,
            $triggers,
            $returning,
            $options,
        );

        $topLevelByOrdinal = [];
        foreach ($plan['top_level_yielded'] as $yielded) {
            $topLevelByOrdinal[(int) $yielded['ordinal']] = $yielded;
        }

        $trace = [];
        foreach ($plan['incoming_view_rows'] as $ordinal => $viewRow) {
            $yielded = $topLevelByOrdinal[$ordinal] ?? null;
            $current = $yielded === null
                ? null
                : self::findRow((array) $plan['current_parent'], (string) $yielded['key_name']);
            $recursive = self::recursiveRowsForOrdinal((array) $plan['yielded'], (int) $ordinal);
            $trace[] = [
                'ordinal' => (int) $ordinal,
                'view' => $plan['view'],
                'view_row' => $viewRow,
                'status' => $yielded === null ? 'skipped' : (string) $yielded['status'],
                'event' => $yielded['event'] ?? null,
                'current_row' => $current,
                'current_setting_id' => $current['setting_id'] ?? null,
                'returning_row' => $plan['returning_rows'][$ordinal] ?? null,
                'next_recursive_rows' => $recursive,
                'next_recursive_names' => array_column($recursive, 'key_name'),
                'next_recursive_source_triggers' => array_values(array_unique(array_filter(array_column($recursive, 'source_trigger')))),
            ];
        }

        $recursiveRows = array_values(array_filter(
            (array) $plan['yielded'],
            static fn (array $row): bool => (int) ($row['depth'] ?? 0) > 0
        ));

        $plan['current_next_trace'] = $trace;
        $plan['recursive_yielded'] = $recursiveRows;
        $plan['recursive_returning_suppressed'] = count($recursiveRows);
        $plan['statement_returning_names'] = array_column((array) $plan['returning_rows'], 'key_name');
        $plan['statement_current_setting_ids'] = array_column($trace, 'current_setting_id');
        $plan['dependencies'] = array_values(array_unique(array_merge(
            (array) ($plan['dependencies'] ?? []),
            [
                'sqlite-trigger-upsert-returning-current-row',
                'sqlite-recursive-view-next-row-source',
            ],
        )));

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>|null
     */
    private static function findRow(array $rows, string $optionName): ?array
    {
        foreach ($rows as $row) {
            if (($row['key_name'] ?? null) === $optionName) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $yielded
     * @return list<array<string,mixed>>
     */
    private static function recursiveRowsForOrdinal(array $yielded, int $ordinal): array
    {
        $rows = [];
        foreach ($yielded as $row) {
            if ((int) ($row['ordinal'] ?? -1) !== $ordinal || (int) ($row['depth'] ?? 0) === 0) {
                continue;
            }
            $rows[] = $row;
        }

        usort($rows, static function (array $left, array $right): int {
            return [(int) ($left['depth'] ?? 0), (string) ($left['key_name'] ?? '')]
                <=> [(int) ($right['depth'] ?? 0), (string) ($right['key_name'] ?? '')];
        });

        return $rows;
    }
}
