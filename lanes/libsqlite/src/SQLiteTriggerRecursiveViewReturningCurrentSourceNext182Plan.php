<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext182Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string,drained_current_pages?:int,resume_source_signature?:string,savepoint_action?:string,restart_cursor?:string,current_source_epoch?:int,snapshot_token?:string,expected_snapshot_token?:string,current_schema_cookie?:int,expected_current_schema_cookie?:int,current_source_generation?:string,expected_current_source_generation?:string,trigger_source_generation?:string,expected_trigger_source_generation?:string,returning_cursor_generation?:string} $options
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
        $currentGeneration = self::token((string) ($options['current_source_generation'] ?? 'wp.recursive.view.current.182'), 'current source generation');
        $expectedCurrentGeneration = self::token((string) ($options['expected_current_source_generation'] ?? $currentGeneration), 'expected current source generation');
        $triggerGeneration = self::token((string) ($options['trigger_source_generation'] ?? 'wp.recursive.trigger.current.182'), 'trigger source generation');
        $expectedTriggerGeneration = self::token((string) ($options['expected_trigger_source_generation'] ?? $triggerGeneration), 'expected trigger source generation');
        $cursorGeneration = self::token((string) ($options['returning_cursor_generation'] ?? 'wp.recursive.returning.cursor.182'), 'returning cursor generation');

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext178Plan::execute(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + ['savepoint_action' => 'release'],
        );

        $currentMatches = hash_equals($currentGeneration, $expectedCurrentGeneration);
        $triggerMatches = hash_equals($triggerGeneration, $expectedTriggerGeneration);
        $snapshotStable = (bool) ($base['current_source_snapshot_stable_next178'] ?? false);
        $releaseAllowed = (bool) ($base['savepoint_release_allowed_next175'] ?? false);
        $generationStable = $snapshotStable && $currentMatches && $triggerMatches;
        $publishNext = $generationStable && $releaseAllowed;

        $baseVisibleRows = self::rows($base['visible_returning_rows_next178'] ?? [], 'visible rows');
        $baseCurrentRows = self::rows($base['current_source_returning_rows_next178'] ?? [], 'current rows');
        $baseNextRows = self::rows($base['next_source_returning_rows_next178'] ?? [], 'next rows');
        $baseQueuedNextRows = self::rows($base['queued_next_source_rows_next178'] ?? [], 'queued next rows');

        $visibleRows = self::tagRows($publishNext ? $baseVisibleRows : $baseCurrentRows, $currentGeneration, $triggerGeneration, $cursorGeneration);
        $currentRows = array_values(array_filter($visibleRows, static fn (array $row): bool => $row['statement_source'] === 'current'));
        $nextRows = array_values(array_filter($visibleRows, static fn (array $row): bool => $row['statement_source'] === 'next'));

        $quarantinedNext = $publishNext ? [] : self::tagRows(array_merge($baseNextRows, $baseQueuedNextRows), $currentGeneration, $triggerGeneration, $cursorGeneration);
        $quarantinedNext = self::dedupeRows($quarantinedNext);

        $blockedReasons = self::strings($base['blocked_reasons_next178'] ?? [], 'blocked reasons');
        if (!$currentMatches) {
            $blockedReasons[] = 'current-view-source-generation-mismatch';
        }
        if (!$triggerMatches) {
            $blockedReasons[] = 'current-trigger-source-generation-mismatch';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        return $base + [
            'status_next182' => match (true) {
                !$generationStable => 'trigger-recursive-view-returning-current-source-generation-restart-next182',
                $publishNext => 'trigger-recursive-view-returning-current-source-generation-released-next182',
                default => 'trigger-recursive-view-returning-current-source-generation-held-next182',
            },
            'current_source_generation_next182' => $currentGeneration,
            'expected_current_source_generation_next182' => $expectedCurrentGeneration,
            'trigger_source_generation_next182' => $triggerGeneration,
            'expected_trigger_source_generation_next182' => $expectedTriggerGeneration,
            'returning_cursor_generation_next182' => $cursorGeneration,
            'current_source_generation_matches_next182' => $currentMatches,
            'trigger_source_generation_matches_next182' => $triggerMatches,
            'current_source_generation_stable_next182' => $generationStable,
            'next_source_publish_allowed_next182' => $publishNext,
            'visible_returning_rows_next182' => $visibleRows,
            'current_source_returning_rows_next182' => $currentRows,
            'next_source_returning_rows_next182' => $nextRows,
            'quarantined_next_source_rows_next182' => $quarantinedNext,
            'statement_returning_row_count_next182' => count($visibleRows),
            'current_returning_row_count_next182' => count($currentRows),
            'next_returning_row_count_next182' => count($nextRows),
            'quarantined_next_row_count_next182' => count($quarantinedNext),
            'returning_source_order_next182' => array_values(array_unique(array_column($visibleRows, 'statement_source'))),
            'returning_generation_plan_next182' => [
                'snapshot_stable' => $snapshotStable,
                'savepoint_release_allowed' => $releaseAllowed,
                'current_source_generation_matches' => $currentMatches,
                'trigger_source_generation_matches' => $triggerMatches,
                'visible_rows' => count($visibleRows),
                'current_rows' => count($currentRows),
                'next_rows' => count($nextRows),
                'quarantined_next_rows' => count($quarantinedNext),
                'restart_required' => !$generationStable,
                'decision' => !$generationStable ? 'restart-current-source-generation' : ($publishNext ? 'publish-current-then-next-generation' : 'hold-next-source-generation'),
            ],
            'blocked_reasons_next182' => $blockedReasons,
            'yield_boundary_next182' => $publishNext
                ? 'recursive-view-returning-next182-current-generation-stable-then-next'
                : 'recursive-view-returning-next182-current-generation-fences-next',
            'dependencies_next182' => [
                'sqlite-trigger-recursive-view-returning-current-source-next182',
                'sqlite-returning-current-view-source-generation-fence',
                'sqlite-returning-current-trigger-source-generation-fence',
                'wordpress-recursive-view-returning-current-source-next182',
            ],
            'dependency_closure_next182' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-generation-and-trigger-cookie-model',
            'non_overlap_next182' => 'extends next178 snapshot/schema-cookie fencing with current view-source and trigger-source generation quarantine; does not repeat duplicate-key watermarking, savepoint release/rollback, schema reparse, deferred FK, UPSERT, WAL, VFS, or row-value RETURNING slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $currentGeneration, string $triggerGeneration, string $cursorGeneration): array
    {
        $out = [];
        foreach ($rows as $ordinal => $row) {
            $out[] = $row + [
                'returning_current_source_generation' => $currentGeneration,
                'returning_trigger_source_generation' => $triggerGeneration,
                'returning_cursor_generation' => $cursorGeneration,
                'returning_generation_ordinal' => $ordinal,
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
            $key = ($row['statement_source'] ?? '') . "\0" . ($row['returning_page'] ?? '') . "\0" . ($row['returning_row_ordinal'] ?? '') . "\0" . ($row['returning_option_name'] ?? '');
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next182 {$label} are malformed");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next182 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function strings(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next182 {$label} are malformed");
        }

        return array_map(static fn (mixed $value): string => (string) $value, $values);
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next182 {$label} is malformed");
        }

        return $value;
    }
}
