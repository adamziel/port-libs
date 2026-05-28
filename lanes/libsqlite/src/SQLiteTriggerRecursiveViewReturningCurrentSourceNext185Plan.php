<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext185Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string,drained_current_pages?:int,resume_source_signature?:string,savepoint_action?:string,restart_cursor?:string,current_source_epoch?:int,snapshot_token?:string,expected_snapshot_token?:string,current_schema_cookie?:int,expected_current_schema_cookie?:int,current_source_generation?:string,expected_current_source_generation?:string,trigger_source_generation?:string,expected_trigger_source_generation?:string,returning_cursor_generation?:string,nested_epoch?:string,expected_nested_epoch?:string,drained_nested_depths?:list<int>,required_nested_depths?:list<int>,outer_publish_requested?:bool} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $nestedEpoch = self::token((string) ($options['nested_epoch'] ?? 'wp.recursive.view.nested.185'), 'nested epoch');
        $expectedNestedEpoch = self::token((string) ($options['expected_nested_epoch'] ?? $nestedEpoch), 'expected nested epoch');
        $requiredDepths = self::depths($options['required_nested_depths'] ?? [1, 2], 'required nested depths');
        $drainedDepths = self::depths($options['drained_nested_depths'] ?? $requiredDepths, 'drained nested depths');
        $outerPublishRequested = (bool) ($options['outer_publish_requested'] ?? true);

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext182Plan::execute(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + ['savepoint_action' => 'release'],
        );

        $baseVisibleRows = self::rows($base['visible_returning_rows_next182'] ?? [], 'visible rows');
        $baseCurrentRows = self::rows($base['current_source_returning_rows_next182'] ?? [], 'current rows');
        $baseNextRows = self::rows($base['next_source_returning_rows_next182'] ?? [], 'next rows');
        $baseQuarantinedRows = self::rows($base['quarantined_next_source_rows_next182'] ?? [], 'quarantined rows');

        $nestedRows = array_values(array_filter($baseCurrentRows, static fn (array $row): bool => (int) ($row['depth_value'] ?? $row['depth'] ?? 0) > 0));
        $outerRows = array_values(array_filter($baseCurrentRows, static fn (array $row): bool => (int) ($row['depth_value'] ?? $row['depth'] ?? 0) === 0));
        $requiredMissing = array_values(array_diff($requiredDepths, $drainedDepths));
        $nestedEpochMatches = hash_equals($nestedEpoch, $expectedNestedEpoch);
        $nestedDepthsDrained = $requiredMissing === [];
        $basePublishAllowed = (bool) ($base['next_source_publish_allowed_next182'] ?? false);
        $outerPublishAllowed = $basePublishAllowed && $outerPublishRequested && $nestedEpochMatches && $nestedDepthsDrained;

        $visibleRows = self::tagRows($outerPublishAllowed ? $baseVisibleRows : $baseCurrentRows, $nestedEpoch);
        $visibleCurrentRows = array_values(array_filter($visibleRows, static fn (array $row): bool => $row['statement_source'] === 'current'));
        $visibleNextRows = array_values(array_filter($visibleRows, static fn (array $row): bool => $row['statement_source'] === 'next'));
        $heldNextRows = $outerPublishAllowed ? [] : self::tagRows(array_merge($baseNextRows, $baseQuarantinedRows), $nestedEpoch);
        $heldNextRows = self::dedupeRows($heldNextRows);

        $blockedReasons = self::strings($base['blocked_reasons_next182'] ?? [], 'blocked reasons');
        if (!$outerPublishRequested) {
            $blockedReasons[] = 'outer-returning-publish-not-requested';
        }
        if (!$nestedEpochMatches) {
            $blockedReasons[] = 'nested-recursive-returning-epoch-mismatch';
        }
        if (!$nestedDepthsDrained) {
            $blockedReasons[] = 'nested-recursive-returning-depths-not-drained';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        return $base + [
            'status_next185' => match (true) {
                !$nestedEpochMatches => 'trigger-recursive-view-returning-current-source-nested-restart-next185',
                $outerPublishAllowed => 'trigger-recursive-view-returning-current-source-nested-drained-next185',
                default => 'trigger-recursive-view-returning-current-source-nested-held-next185',
            },
            'nested_epoch_next185' => $nestedEpoch,
            'expected_nested_epoch_next185' => $expectedNestedEpoch,
            'nested_epoch_matches_next185' => $nestedEpochMatches,
            'required_nested_depths_next185' => $requiredDepths,
            'drained_nested_depths_next185' => $drainedDepths,
            'missing_nested_depths_next185' => $requiredMissing,
            'nested_depths_drained_next185' => $nestedDepthsDrained,
            'outer_publish_requested_next185' => $outerPublishRequested,
            'outer_publish_allowed_next185' => $outerPublishAllowed,
            'outer_current_returning_rows_next185' => self::tagRows($outerRows, $nestedEpoch),
            'nested_current_returning_rows_next185' => self::tagRows($nestedRows, $nestedEpoch),
            'visible_returning_rows_next185' => $visibleRows,
            'visible_current_returning_rows_next185' => $visibleCurrentRows,
            'visible_next_returning_rows_next185' => $visibleNextRows,
            'held_next_source_rows_next185' => $heldNextRows,
            'outer_current_row_count_next185' => count($outerRows),
            'nested_current_row_count_next185' => count($nestedRows),
            'visible_row_count_next185' => count($visibleRows),
            'visible_current_row_count_next185' => count($visibleCurrentRows),
            'visible_next_row_count_next185' => count($visibleNextRows),
            'held_next_row_count_next185' => count($heldNextRows),
            'returning_source_order_next185' => array_values(array_unique(array_column($visibleRows, 'statement_source'))),
            'nested_depth_drain_plan_next185' => [
                'nested_epoch_matches' => $nestedEpochMatches,
                'required_depths' => $requiredDepths,
                'drained_depths' => $drainedDepths,
                'missing_depths' => $requiredMissing,
                'base_publish_allowed' => $basePublishAllowed,
                'outer_publish_requested' => $outerPublishRequested,
                'outer_publish_allowed' => $outerPublishAllowed,
                'decision' => !$nestedEpochMatches
                    ? 'restart-nested-recursive-returning-epoch'
                    : ($outerPublishAllowed ? 'publish-current-nested-then-next' : 'hold-next-until-nested-depths-drain'),
            ],
            'blocked_reasons_next185' => $blockedReasons,
            'yield_boundary_next185' => $outerPublishAllowed
                ? 'recursive-view-returning-next185-nested-current-source-drained-then-next'
                : 'recursive-view-returning-next185-nested-current-source-fences-next',
            'dependencies_next185' => [
                'sqlite-trigger-recursive-view-returning-current-source-next185',
                'sqlite-returning-nested-recursive-depth-drain-fence',
                'sqlite-returning-nested-recursive-epoch-fence',
                'wordpress-recursive-view-returning-current-source-next185',
            ],
            'dependency_closure_next185' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-generation-and-nested-depth-drain-model',
            'non_overlap_next185' => 'extends next182 generation fencing with nested recursive RETURNING depth-drain epochs; does not repeat next178 snapshot/schema-cookie, next176 page acknowledgements, next181 checkpoints, row-value RETURNING, UPSERT, WAL, VFS, or schema-reparse slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $nestedEpoch): array
    {
        $out = [];
        foreach ($rows as $row) {
            $depth = (int) ($row['depth_value'] ?? $row['depth'] ?? 0);
            $out[] = $row + [
                'returning_nested_epoch' => $nestedEpoch,
                'returning_nested_depth_drained' => $depth > 0,
                'returning_nested_depth' => $depth,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function dedupeRows(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = ($row['statement_source'] ?? '') . "\0" . ($row['returning_row_ordinal'] ?? '') . "\0" . ($row['returning_option_name'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next185 {$label} are malformed");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next185 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param mixed $values
     * @return list<int>
     */
    private static function depths(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next185 {$label} are malformed");
        }

        $out = [];
        foreach ($values as $value) {
            $depth = (int) $value;
            if ($depth < 0 || (string) $depth !== (string) $value) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next185 {$label} contain a malformed depth");
            }
            $out[] = $depth;
        }

        sort($out);

        return array_values(array_unique($out));
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function strings(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next185 {$label} are malformed");
        }

        return array_map(static fn (mixed $value): string => (string) $value, $values);
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next185 {$label} is malformed");
        }

        return $value;
    }
}
