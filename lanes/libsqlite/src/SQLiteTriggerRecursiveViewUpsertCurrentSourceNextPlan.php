<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan
{

    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentViewRows
     * @param list<array<string,mixed>> $nextViewRows
     * @param array{name:string,source:string,mapping:array<string,string>} $view
     * @param list<string> $uniqueColumns
     * @param list<array{name:string,when:string,target:string,value:string,recursive?:bool}> $triggers
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentUpsertConflictSeal(
        array $rows,
        array $currentViewRows,
        array $nextViewRows,
        array $view,
        array $uniqueColumns,
        array $triggers,
        array $options = [],
    ): array {
        $baseOptions = $options;
        $baseOptions['release_next'] = false;
        $base = SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan::execute(
            $rows,
            $currentViewRows,
            $nextViewRows,
            $view,
            $uniqueColumns,
            $triggers,
            $baseOptions,
        );

        $upsertSource = self::tokenCurrentUpsertConflictSeal((string) ($options['current_upsert_source_next232'] ?? 'wp.current.upsert.source.232'), 'current upsert source');
        $viewSource = self::tokenCurrentUpsertConflictSeal((string) ($options['current_view_source_next232'] ?? (string) ($base['current_source'] ?? 'main@cookie232-current')), 'current view source');
        $expectedViewSource = self::tokenCurrentUpsertConflictSeal((string) ($options['expected_current_view_source_next232'] ?? $viewSource), 'expected current view source');
        $triggerProgram = self::tokenCurrentUpsertConflictSeal((string) ($options['current_trigger_program_next232'] ?? 'wp.current.recursive.trigger.program.232'), 'current trigger program');
        $expectedTriggerProgram = self::tokenCurrentUpsertConflictSeal((string) ($options['expected_current_trigger_program_next232'] ?? $triggerProgram), 'expected current trigger program');
        $requireOrder = (bool) ($options['require_current_upsert_conflict_order_next232'] ?? true);

        $currentYield = self::rowsCurrentUpsertConflictSeal($base['current_yield_stream'] ?? [], 'current yield stream');
        $attemptedNextYield = self::rowsCurrentUpsertConflictSeal($base['attempted_next_yield_stream'] ?? [], 'attempted next yield stream');
        $requiredSeals = self::conflictSealsCurrentUpsertConflictSeal($currentYield, $upsertSource, $viewSource, $triggerProgram);
        $acknowledgedSeals = self::acknowledgedSealsCurrentUpsertConflictSeal($options, $requiredSeals);
        $missingSeals = array_values(array_diff($requiredSeals, $acknowledgedSeals));
        $unexpectedSeals = array_values(array_diff($acknowledgedSeals, $requiredSeals));
        $viewMatches = hash_equals($viewSource, $expectedViewSource);
        $triggerMatches = hash_equals($triggerProgram, $expectedTriggerProgram);
        $orderMatches = !$requireOrder || $requiredSeals === $acknowledgedSeals;
        $conflictComplete = $requiredSeals !== []
            && $viewMatches
            && $triggerMatches
            && $missingSeals === []
            && $unexpectedSeals === []
            && $orderMatches;

        $next = null;
        if ($conflictComplete) {
            $releaseOptions = $options;
            $releaseOptions['release_next'] = true;
            $next = SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan::execute(
                $rows,
                $currentViewRows,
                $nextViewRows,
                $view,
                $uniqueColumns,
                $triggers,
                $releaseOptions,
            );
        }

        $blockedReasons = self::blockedReasonsCurrentUpsertConflictSeal($viewMatches, $triggerMatches, $missingSeals, $unexpectedSeals, $requireOrder, $orderMatches);
        $currentYield = self::tagRowsCurrentUpsertConflictSeal($currentYield, 'current', true, $requiredSeals, $upsertSource, $viewSource, $triggerProgram, []);
        $attemptedNextYield = self::tagRowsCurrentUpsertConflictSeal($attemptedNextYield, 'next', $conflictComplete, [], $upsertSource, $viewSource, $triggerProgram, $conflictComplete ? [] : $blockedReasons);
        $currentReturning = self::tagRowsCurrentUpsertConflictSeal(self::rowsCurrentUpsertConflictSeal($base['current_returning_rows'] ?? [], 'current returning rows'), 'current', true, $requiredSeals, $upsertSource, $viewSource, $triggerProgram, []);
        $attemptedNextReturning = self::tagRowsCurrentUpsertConflictSeal(self::rowsCurrentUpsertConflictSeal($base['attempted_next_returning_rows'] ?? [], 'attempted next returning rows'), 'next', $conflictComplete, [], $upsertSource, $viewSource, $triggerProgram, $conflictComplete ? [] : $blockedReasons);

        return [
            'status_next232' => self::statusCurrentUpsertConflictSeal($conflictComplete, $viewMatches, $triggerMatches, $missingSeals, $unexpectedSeals, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'released_base_next232' => $next,
            'current_upsert_source_next232' => $upsertSource,
            'current_view_source_next232' => $viewSource,
            'expected_current_view_source_next232' => $expectedViewSource,
            'current_view_source_matches_next232' => $viewMatches,
            'current_trigger_program_next232' => $triggerProgram,
            'expected_current_trigger_program_next232' => $expectedTriggerProgram,
            'current_trigger_program_matches_next232' => $triggerMatches,
            'required_current_upsert_conflict_seals_next232' => $requiredSeals,
            'acknowledged_current_upsert_conflict_seals_next232' => $acknowledgedSeals,
            'missing_current_upsert_conflict_seals_next232' => $missingSeals,
            'unexpected_current_upsert_conflict_seals_next232' => $unexpectedSeals,
            'require_current_upsert_conflict_order_next232' => $requireOrder,
            'current_upsert_conflict_order_matches_next232' => $orderMatches,
            'current_upsert_conflict_complete_next232' => $conflictComplete,
            'next_source_visible_after_current_upsert_conflict_next232' => $conflictComplete,
            'current_yield_stream_next232' => $currentYield,
            'attempted_next_yield_stream_next232' => $attemptedNextYield,
            'current_returning_rows_next232' => $currentReturning,
            'attempted_next_returning_rows_next232' => $attemptedNextReturning,
            'visible_yield_stream_next232' => $conflictComplete ? array_merge($currentYield, $attemptedNextYield) : $currentYield,
            'held_next_yield_stream_next232' => $conflictComplete ? [] : $attemptedNextYield,
            'visible_returning_rows_next232' => $conflictComplete ? array_merge($currentReturning, $attemptedNextReturning) : $currentReturning,
            'held_next_returning_rows_next232' => $conflictComplete ? [] : $attemptedNextReturning,
            'current_change_count_next232' => (int) $base['current_changes'],
            'attempted_next_change_count_next232' => count($attemptedNextYield),
            'visible_change_count_next232' => $conflictComplete ? (int) ($next['changes'] ?? 0) : (int) $base['current_changes'],
            'after_savepoint_next232' => $conflictComplete ? ($next['after_savepoint'] ?? []) : $base['after_savepoint'],
            'blocked_reasons_next232' => $blockedReasons,
            'current_upsert_conflict_plan_next232' => [
                'view_source_matches' => $viewMatches,
                'trigger_program_matches' => $triggerMatches,
                'required_seals' => $requiredSeals,
                'acknowledged_seals' => $acknowledgedSeals,
                'missing_seals' => $missingSeals,
                'unexpected_seals' => $unexpectedSeals,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'conflict_complete' => $conflictComplete,
                'decision' => $conflictComplete
                    ? 'publish-next-source-after-current-recursive-upsert-conflicts'
                    : 'hold-next-source-until-current-recursive-upsert-conflicts',
            ],
            'yield_boundary_next232' => $conflictComplete
                ? 'recursive-view-upsert-next232-current-conflict-sealed-then-next'
                : 'recursive-view-upsert-next232-current-conflict-seal-fences-next',
            'dependency_closure_next232' => 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-yield-and-adds-conflict-seal',
            'dependencies_next232' => [
                'sqlite-trigger-recursive-view-upsert-current-source-next232',
                'sqlite-instead-of-view-upsert-recursive-trigger',
                'sqlite-current-upsert-conflict-seal',
                'wordpress-recursive-view-upsert-current-source-next232',
            ],
            'non_overlap_next232' => 'adds UPSERT conflict seals over recursive INSTEAD OF view trigger current-source yields; avoids accepted recursive view RETURNING next157-next229, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private static function conflictSealsCurrentUpsertConflictSeal(array $rows, string $source, string $viewSource, string $triggerProgram): array
    {
        $seals = [];
        foreach ($rows as $index => $row) {
            $returning = is_array($row['returning'] ?? null) ? $row['returning'] : $row;
            $parts = [
                $source,
                $viewSource,
                $triggerProgram,
                (string) ($row['event'] ?? $returning['event'] ?? ''),
                (string) ($row['depth'] ?? $returning['depth'] ?? ''),
                (string) ($row['ordinal'] ?? $returning['ordinal'] ?? $index),
                (string) ($row['trigger'] ?? $returning['trigger'] ?? ''),
                (string) ($returning['option_name'] ?? ''),
                (string) ($returning['old_value'] ?? ''),
                (string) ($returning['option_value'] ?? ''),
            ];
            $seals[] = substr(hash('sha256', implode('|', $parts)), 0, 46);
        }

        return $seals;
    }

    /** @param array<string,mixed> $options @param list<string> $required @return list<string> */
    private static function acknowledgedSealsCurrentUpsertConflictSeal(array $options, array $required): array
    {
        if (($options['auto_ack_current_upsert_conflict_seals_next232'] ?? false) === true) {
            return $required;
        }

        return self::sealListCurrentUpsertConflictSeal($options['acknowledged_current_upsert_conflict_seals_next232'] ?? [], 'acknowledged current upsert conflict seals');
    }

    /** @param mixed $values @return list<string> */
    private static function sealListCurrentUpsertConflictSeal(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next232 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{46}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next232 {$label} contain a malformed conflict seal");
            }
        }

        return array_values(array_unique($values));
    }

    /** @param mixed $rows @return list<array<string,mixed>> */
    private static function rowsCurrentUpsertConflictSeal(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next232 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next232 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /** @param list<array<string,mixed>> $rows @param list<string> $seals @param list<string> $reasons @return list<array<string,mixed>> */
    private static function tagRowsCurrentUpsertConflictSeal(array $rows, string $phase, bool $visible, array $seals, string $source, string $viewSource, string $triggerProgram, array $reasons): array
    {
        $tagged = [];
        foreach ($rows as $index => $row) {
            $tagged[] = $row + [
                'upsert_conflict_phase_next232' => $phase,
                'current_upsert_source_next232' => $source,
                'current_view_source_next232' => $viewSource,
                'current_trigger_program_next232' => $triggerProgram,
                'current_upsert_conflict_seal_next232' => $seals[$index] ?? null,
                'visible_after_current_upsert_conflict_next232' => $visible,
                'held_by_current_upsert_conflict_reasons_next232' => $visible ? [] : $reasons,
            ];
        }

        return $tagged;
    }

    /** @param list<string> $missing @param list<string> $unexpected @return list<string> */
    private static function blockedReasonsCurrentUpsertConflictSeal(bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        $reasons = [];
        if (!$viewMatches) {
            $reasons[] = 'current-upsert-view-source-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-upsert-trigger-program-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-upsert-conflict-seal-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-upsert-conflict-seal-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-upsert-conflict-seal-order-mismatch';
        }

        return $reasons;
    }

    /** @param list<string> $missing @param list<string> $unexpected */
    private static function statusCurrentUpsertConflictSeal(bool $complete, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($complete) {
            return 'trigger-recursive-view-upsert-current-source-next232-conflict-released';
        }
        if (!$viewMatches) {
            return 'trigger-recursive-view-upsert-current-source-next232-view-source-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-upsert-current-source-next232-trigger-program-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next232-conflict-seal-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next232-conflict-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next232-held';
    }

    private static function tokenCurrentUpsertConflictSeal(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next232 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentUpsertConflictTarget(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentReturningGenerationSeal(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rowsCurrentUpsertConflictTarget($base['current_source_rows_next229'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentUpsertConflictTarget($base['attempted_next_source_rows_next229'] ?? [], 'attempted next source rows');
        $baseVisible = (bool) ($base['next_source_visible_after_current_returning_generation_next229'] ?? false);
        $upsertToken = self::tokenCurrentUpsertConflictTarget((string) ($options['current_upsert_source_token_next233'] ?? 'wp.current.upsert.source.233'), 'current upsert source token');
        $expectedUpsertToken = self::tokenCurrentUpsertConflictTarget((string) ($options['expected_current_upsert_source_token_next233'] ?? $upsertToken), 'expected current upsert source token');
        $viewSource = self::tokenCurrentUpsertConflictTarget((string) ($options['current_upsert_view_source_next233'] ?? ($currentView['source'] ?? 'main@view-upsert-233-current')), 'current upsert view source');
        $expectedViewSource = self::tokenCurrentUpsertConflictTarget((string) ($options['expected_current_upsert_view_source_next233'] ?? $viewSource), 'expected current upsert view source');
        $triggerSource = self::tokenCurrentUpsertConflictTarget((string) ($options['current_upsert_trigger_source_next233'] ?? ($currentView['trigger_source'] ?? 'main@trigger-upsert-233-current')), 'current upsert trigger source');
        $expectedTriggerSource = self::tokenCurrentUpsertConflictTarget((string) ($options['expected_current_upsert_trigger_source_next233'] ?? $triggerSource), 'expected current upsert trigger source');
        $conflictTarget = self::identifierListCurrentUpsertConflictTarget($options['current_upsert_conflict_target_next233'] ?? ['option_name'], 'conflict target');
        $updateColumns = self::identifierListCurrentUpsertConflictTarget($options['current_upsert_update_columns_next233'] ?? ['option_value', 'autoload'], 'update columns');
        $requireOrder = (bool) ($options['require_current_upsert_order_next233'] ?? true);

        $requiredSeals = self::upsertSealsCurrentUpsertConflictTarget($currentRows, $upsertToken, $viewSource, $triggerSource, $conflictTarget, $updateColumns);
        $acknowledgedSeals = self::acknowledgedSealsCurrentUpsertConflictTarget($options, $requiredSeals);
        $missingSeals = array_values(array_diff($requiredSeals, $acknowledgedSeals));
        $unexpectedSeals = array_values(array_diff($acknowledgedSeals, $requiredSeals));
        $upsertMatches = hash_equals($upsertToken, $expectedUpsertToken);
        $viewMatches = hash_equals($viewSource, $expectedViewSource);
        $triggerMatches = hash_equals($triggerSource, $expectedTriggerSource);
        $orderMatches = !$requireOrder || $requiredSeals === $acknowledgedSeals;
        $upsertEvents = self::upsertEventsCurrentUpsertConflictTarget($currentRows);
        $hasUpsertRows = $requiredSeals !== [] && ($upsertEvents['insert'] + $upsertEvents['update']) > 0;
        $sealComplete = $requiredSeals !== []
            && $hasUpsertRows
            && $upsertMatches
            && $viewMatches
            && $triggerMatches
            && $missingSeals === []
            && $unexpectedSeals === []
            && $orderMatches;
        $nextVisible = $baseVisible && $sealComplete;
        $blockedReasons = self::blockedReasonsCurrentUpsertConflictTarget(
            $base['blocked_reasons_next229'] ?? [],
            $baseVisible,
            $hasUpsertRows,
            $upsertMatches,
            $viewMatches,
            $triggerMatches,
            $missingSeals,
            $unexpectedSeals,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRowsCurrentUpsertConflictTarget($currentRows, 'current', true, $requiredSeals, $upsertToken, $viewSource, $triggerSource, $conflictTarget, $updateColumns, []);
        $nextRows = self::tagRowsCurrentUpsertConflictTarget($nextRows, 'next', $nextVisible, [], $upsertToken, $viewSource, $triggerSource, $conflictTarget, $updateColumns, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_upsert_source_next233'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_upsert_source_next233'],
        ));

        return [
            'status_next233' => self::statusCurrentUpsertConflictTarget($nextVisible, $baseVisible, $hasUpsertRows, $upsertMatches, $viewMatches, $triggerMatches, $missingSeals, $unexpectedSeals, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next233' => $baseVisible,
            'current_upsert_source_token_next233' => $upsertToken,
            'expected_current_upsert_source_token_next233' => $expectedUpsertToken,
            'current_upsert_source_matches_next233' => $upsertMatches,
            'current_upsert_view_source_next233' => $viewSource,
            'expected_current_upsert_view_source_next233' => $expectedViewSource,
            'current_upsert_view_source_matches_next233' => $viewMatches,
            'current_upsert_trigger_source_next233' => $triggerSource,
            'expected_current_upsert_trigger_source_next233' => $expectedTriggerSource,
            'current_upsert_trigger_source_matches_next233' => $triggerMatches,
            'current_upsert_conflict_target_next233' => $conflictTarget,
            'current_upsert_update_columns_next233' => $updateColumns,
            'current_upsert_events_next233' => $upsertEvents,
            'current_upsert_has_rows_next233' => $hasUpsertRows,
            'required_current_upsert_seals_next233' => $requiredSeals,
            'acknowledged_current_upsert_seals_next233' => $acknowledgedSeals,
            'missing_current_upsert_seals_next233' => $missingSeals,
            'unexpected_current_upsert_seals_next233' => $unexpectedSeals,
            'require_current_upsert_order_next233' => $requireOrder,
            'current_upsert_order_matches_next233' => $orderMatches,
            'current_upsert_source_complete_next233' => $sealComplete,
            'next_source_visible_after_current_upsert_source_next233' => $nextVisible,
            'current_source_rows_next233' => $currentRows,
            'attempted_next_source_rows_next233' => $nextRows,
            'visible_returning_rows_next233' => $visibleRows,
            'held_next_source_rows_next233' => $heldRows,
            'visible_returning_payloads_next233' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next233' => array_column($heldRows, 'returning'),
            'current_source_row_count_next233' => count($currentRows),
            'attempted_next_source_row_count_next233' => count($nextRows),
            'visible_row_count_next233' => count($visibleRows),
            'held_next_row_count_next233' => count($heldRows),
            'blocked_reasons_next233' => $blockedReasons,
            'current_upsert_source_plan_next233' => [
                'base_next_source_visible' => $baseVisible,
                'has_upsert_rows' => $hasUpsertRows,
                'upsert_source_matches' => $upsertMatches,
                'view_source_matches' => $viewMatches,
                'trigger_source_matches' => $triggerMatches,
                'conflict_target' => $conflictTarget,
                'update_columns' => $updateColumns,
                'events' => $upsertEvents,
                'required_seals' => $requiredSeals,
                'acknowledged_seals' => $acknowledgedSeals,
                'missing_seals' => $missingSeals,
                'unexpected_seals' => $unexpectedSeals,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'upsert_complete' => $sealComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-view-upsert-seal'
                    : 'hold-next-source-until-current-view-upsert-seal',
            ],
            'yield_boundary_next233' => $nextVisible
                ? 'recursive-view-upsert-next233-current-source-sealed-then-next'
                : 'recursive-view-upsert-next233-current-source-seal-fences-next',
            'dependency_closure_next233' => 'no-new-support-component-reuses-native-recursive-view-returning-generation-and-adds-current-upsert-source-seal',
            'dependencies_next233' => array_values(array_unique(array_merge($base['dependencies_next229'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next233',
                'sqlite-current-view-upsert-source-seal',
                'wordpress-recursive-view-upsert-current-source-next233',
            ]))),
            'non_overlap_next233' => 'adds current view UPSERT conflict-target/update-column source seals after next229 generation seals; avoids accepted recursive view RETURNING cursor, epoch, source, snapshot, and generation handoffs, row-value RETURNING savepoints, DML RETURNING conflicts, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $conflictTarget
     * @param list<string> $updateColumns
     * @return list<string>
     */
    private static function upsertSealsCurrentUpsertConflictTarget(array $rows, string $upsertToken, string $viewSource, string $triggerSource, array $conflictTarget, array $updateColumns): array
    {
        $seals = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts = [
                $upsertToken,
                $viewSource,
                $triggerSource,
                implode(',', $conflictTarget),
                implode(',', $updateColumns),
                (string) ($row['current_returning_generation_seal_next229'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($returning['name'] ?? ''),
                (string) ($returning['event_name'] ?? ''),
                (string) ($returning['trigger_source_alias'] ?? ''),
            ];
            $seals[] = substr(hash('sha256', implode('|', $parts)), 0, 46);
        }

        return $seals;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{insert:int,update:int,other:int,names:list<string>}
     */
    private static function upsertEventsCurrentUpsertConflictTarget(array $rows): array
    {
        $events = ['insert' => 0, 'update' => 0, 'other' => 0, 'names' => []];
        foreach ($rows as $row) {
            $returning = $row['returning'];
            $event = strtolower((string) ($returning['event_name'] ?? ''));
            if ($event === 'update') {
                ++$events['update'];
            } elseif ($event === '' || $event === 'insert') {
                ++$events['insert'];
            } else {
                ++$events['insert'];
            }
            $events['names'][] = (string) ($returning['name'] ?? '');
        }

        return $events;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedSealsCurrentUpsertConflictTarget(array $options, array $required): array
    {
        if (($options['auto_ack_current_upsert_seals_next233'] ?? false) === true) {
            return $required;
        }

        return self::sealListCurrentUpsertConflictTarget($options['acknowledged_current_upsert_seals_next233'] ?? [], 'acknowledged current upsert seals');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function sealListCurrentUpsertConflictTarget(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next233 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{46}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next233 {$label} contain a malformed upsert seal");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentUpsertConflictTarget(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next233 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next233 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $seals
     * @param list<string> $conflictTarget
     * @param list<string> $updateColumns
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentUpsertConflictTarget(
        array $rows,
        string $phase,
        bool $visible,
        array $seals,
        string $upsertToken,
        string $viewSource,
        string $triggerSource,
        array $conflictTarget,
        array $updateColumns,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_source_phase_next233' => $phase,
                'current_upsert_source_token_next233' => $upsertToken,
                'current_upsert_view_source_next233' => $viewSource,
                'current_upsert_trigger_source_next233' => $triggerSource,
                'current_upsert_conflict_target_next233' => $conflictTarget,
                'current_upsert_update_columns_next233' => $updateColumns,
                'current_upsert_seal_next233' => $seals[$index] ?? null,
                'visible_after_current_upsert_source_next233' => $visible,
                'held_by_current_upsert_source_reasons_next233' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsCurrentUpsertConflictTarget(
        mixed $baseReasons,
        bool $baseVisible,
        bool $hasUpsertRows,
        bool $upsertMatches,
        bool $viewMatches,
        bool $triggerMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        $reasons = is_array($baseReasons) && array_is_list($baseReasons) ? $baseReasons : [];
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'current-returning-generation-base-held';
        }
        if (!$hasUpsertRows) {
            $reasons[] = 'current-upsert-source-empty';
        }
        if (!$upsertMatches) {
            $reasons[] = 'current-upsert-source-token-mismatch';
        }
        if (!$viewMatches) {
            $reasons[] = 'current-upsert-view-source-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-upsert-trigger-source-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-upsert-seal-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-upsert-seal-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-upsert-seal-order-mismatch';
        }

        return array_values(array_unique(array_map('strval', $reasons)));
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function identifierListCurrentUpsertConflictTarget(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values) || $values === []) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next233 {$label} must be a non-empty list");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next233 {$label} contains an invalid identifier");
            }
            $out[] = $value;
        }

        return array_values(array_unique($out));
    }

    private static function tokenCurrentUpsertConflictTarget(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next233 {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusCurrentUpsertConflictTarget(
        bool $nextVisible,
        bool $baseVisible,
        bool $hasUpsertRows,
        bool $upsertMatches,
        bool $viewMatches,
        bool $triggerMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): string {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next233-upsert-sealed';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next233-base-held';
        }
        if (!$hasUpsertRows) {
            return 'trigger-recursive-view-upsert-current-source-next233-empty-held';
        }
        if (!$upsertMatches) {
            return 'trigger-recursive-view-upsert-current-source-next233-upsert-token-held';
        }
        if (!$viewMatches) {
            return 'trigger-recursive-view-upsert-current-source-next233-view-source-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-upsert-current-source-next233-trigger-source-held';
        }
        if ($unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next233-upsert-seal-held';
        }
        if ($missing !== []) {
            return 'trigger-recursive-view-upsert-current-source-next233-upsert-seal-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next233-upsert-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next233-held';
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentSourceUpsertReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceCursorClose(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_close_source_close'] ?? false);
        $currentRows = self::rowsCurrentSourceUpsertReceipt($base['current_source_rows_source_close'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentSourceUpsertReceipt($base['attempted_next_source_rows_source_close'] ?? [], 'attempted next source rows');
        $conflictColumns = self::columnsCurrentSourceUpsertReceipt($options['upsert_conflict_columns_next234'] ?? ['option_name']);
        $upsertToken = self::tokenCurrentSourceUpsertReceipt((string) ($options['current_source_upsert_token_next234'] ?? 'wp.current.source.upsert.234'), 'upsert token');
        $expectedUpsertToken = self::tokenCurrentSourceUpsertReceipt((string) ($options['expected_current_source_upsert_token_next234'] ?? $upsertToken), 'expected upsert token');
        $viewCookie = self::tokenCurrentSourceUpsertReceipt((string) ($options['current_upsert_view_cookie_next234'] ?? (string) ($currentView['source'] ?? 'main@view-cookie-234-current')), 'view cookie');
        $triggerCookie = self::tokenCurrentSourceUpsertReceipt((string) ($options['current_upsert_trigger_cookie_next234'] ?? (string) ($currentView['trigger_source'] ?? 'main@trigger-cookie-234-current')), 'trigger cookie');
        $requireOrder = (bool) ($options['require_current_source_upsert_order_next234'] ?? true);

        $required = self::upsertReceiptsCurrentSourceUpsertReceipt($currentRows, $conflictColumns, $upsertToken, $viewCookie, $triggerCookie);
        $acknowledged = self::acknowledgedReceiptsCurrentSourceUpsertReceipt($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $orderMatches = !$requireOrder || $required === $acknowledged;
        $tokenMatches = hash_equals($upsertToken, $expectedUpsertToken);
        $upsertComplete = $required !== []
            && $tokenMatches
            && $missing === []
            && $unexpected === []
            && $orderMatches;
        $nextVisible = $baseVisible && $upsertComplete;
        $blockedReasons = self::blockedReasonsCurrentSourceUpsertReceipt(
            $base['blocked_reasons_source_close'] ?? [],
            $baseVisible,
            $tokenMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $taggedCurrent = self::tagRowsCurrentSourceUpsertReceipt($currentRows, 'current-upsert', true, $required, $conflictColumns, $upsertToken, $viewCookie, $triggerCookie, []);
        $taggedNext = self::tagRowsCurrentSourceUpsertReceipt($nextRows, 'next-source', $nextVisible, [], $conflictColumns, $upsertToken, $viewCookie, $triggerCookie, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next234' => self::statusCurrentSourceUpsertReceipt($baseVisible, $tokenMatches, $missing, $unexpected, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next234' => $baseVisible,
            'upsert_conflict_columns_next234' => $conflictColumns,
            'current_source_upsert_token_next234' => $upsertToken,
            'expected_current_source_upsert_token_next234' => $expectedUpsertToken,
            'current_source_upsert_token_matches_next234' => $tokenMatches,
            'current_upsert_view_cookie_next234' => $viewCookie,
            'current_upsert_trigger_cookie_next234' => $triggerCookie,
            'required_current_source_upsert_receipts_next234' => $required,
            'acknowledged_current_source_upsert_receipts_next234' => $acknowledged,
            'missing_current_source_upsert_receipts_next234' => $missing,
            'unexpected_current_source_upsert_receipts_next234' => $unexpected,
            'require_current_source_upsert_order_next234' => $requireOrder,
            'current_source_upsert_order_matches_next234' => $orderMatches,
            'current_source_upsert_complete_next234' => $upsertComplete,
            'next_source_visible_after_current_source_upsert_next234' => $nextVisible,
            'current_source_rows_next234' => $taggedCurrent,
            'attempted_next_source_rows_next234' => $taggedNext,
            'visible_returning_rows_next234' => $visibleRows,
            'held_next_source_rows_next234' => $heldRows,
            'visible_returning_payloads_next234' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next234' => array_column($heldRows, 'returning'),
            'current_source_row_count_next234' => count($taggedCurrent),
            'attempted_next_source_row_count_next234' => count($taggedNext),
            'visible_row_count_next234' => count($visibleRows),
            'held_next_row_count_next234' => count($heldRows),
            'blocked_reasons_next234' => $blockedReasons,
            'current_source_upsert_plan_next234' => [
                'base_next_source_visible' => $baseVisible,
                'conflict_columns' => $conflictColumns,
                'required_upsert_receipts' => $required,
                'acknowledged_upsert_receipts' => $acknowledged,
                'missing_upsert_receipts' => $missing,
                'unexpected_upsert_receipts' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'token_matches' => $tokenMatches,
                'upsert_complete' => $upsertComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert'
                    : 'hold-next-source-until-current-recursive-view-upsert',
            ],
            'yield_boundary_next234' => $nextVisible
                ? 'recursive-view-upsert-next234-current-upsert-then-next'
                : 'recursive-view-upsert-next234-current-upsert-fences-next',
            'dependency_closure_next234' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-close-and-adds-upsert-conflict-receipts',
            'dependencies_next234' => array_values(array_unique(array_merge($base['dependencies_source_close'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next234',
                'sqlite-instead-of-view-trigger-current-source-upsert-receipts',
                'wordpress-recursive-view-upsert-current-source-next234',
            ]))),
            'non_overlap_next234' => 'adds recursive INSTEAD OF view UPSERT conflict-key receipt admission after accepted source_close cursor-close handoff; avoids accepted next230-source_close recursive view RETURNING close/epoch surfaces, trigger RETURNING conflicts, row-value savepoints, schema reparse, WAL/VFS, JSON, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<string>
     */
    private static function upsertReceiptsCurrentSourceUpsertReceipt(array $rows, array $columns, string $token, string $viewCookie, string $triggerCookie): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts = [$token, $viewCookie, $triggerCookie, (string) ($row['current_source_close_receipt_source_close'] ?? ''), (string) $index];
            foreach ($columns as $column) {
                $parts[] = $column . '=' . (string) ($returning['name'] ?? $returning[$column] ?? '');
            }
            $parts[] = (string) ($returning['event_name'] ?? $returning['event'] ?? '');
            $parts[] = (string) ($returning['trigger_source_alias'] ?? '');
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 40);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsCurrentSourceUpsertReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_upserts_next234'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentSourceUpsertReceipt($options['acknowledged_current_source_upsert_receipts_next234'] ?? [], 'acknowledged current source upsert receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListCurrentSourceUpsertReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next234 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{40}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next234 {$label} contain a malformed receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentSourceUpsertReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next234 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next234 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param mixed $columns
     * @return list<string>
     */
    private static function columnsCurrentSourceUpsertReceipt(mixed $columns): array
    {
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next234 conflict columns must be a non-empty list');
        }

        return array_map(static function (mixed $column): string {
            $column = (string) $column;
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next234 invalid conflict column: {$column}");
            }

            return $column;
        }, $columns);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $columns
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentSourceUpsertReceipt(array $rows, string $phase, bool $visible, array $receipts, array $columns, string $token, string $viewCookie, string $triggerCookie, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_phase_next234' => $phase,
                'upsert_conflict_columns_next234' => $columns,
                'current_source_upsert_token_next234' => $token,
                'current_upsert_view_cookie_next234' => $viewCookie,
                'current_upsert_trigger_cookie_next234' => $triggerCookie,
                'current_source_upsert_receipt_next234' => $receipts[$index] ?? null,
                'visible_after_current_source_upsert_next234' => $visible,
                'held_by_current_source_upsert_reasons_next234' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsCurrentSourceUpsertReceipt(mixed $baseReasons, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next234 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-source-close-current-source-close-not-published';
        }
        if (!$tokenMatches) {
            $reasons[] = 'current-source-upsert-token-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-upsert-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-upsert-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-upsert-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusCurrentSourceUpsertReceipt(bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next234-upsert-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next234-base-held';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-upsert-current-source-next234-upsert-token-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next234-upsert-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next234-upsert-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next234-upsert-empty-held';
    }

    private static function tokenCurrentSourceUpsertReceipt(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next234 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentViewRows
     * @param list<array<string,mixed>> $nextViewRows
     * @param array{name:string,source:string,mapping:array<string,string>} $view
     * @param list<string> $uniqueColumns
     * @param list<array{name:string,when:string,target:string,value:string,recursive?:bool}> $triggers
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentYieldTicket(
        array $rows,
        array $currentViewRows,
        array $nextViewRows,
        array $view,
        array $uniqueColumns,
        array $triggers,
        array $options = [],
    ): array {
        $baseOptions = $options;
        $baseOptions['auto_ack_current_upsert_conflict_seals_next232'] = true;
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentUpsertConflictSeal(
            $rows,
            $currentViewRows,
            $nextViewRows,
            $view,
            $uniqueColumns,
            $triggers,
            $baseOptions,
        );

        $currentTicketSource = self::tokenCurrentYieldTicket((string) ($options['current_yield_ticket_source_next235'] ?? 'wp.current.yield.ticket.source.235'), 'current yield ticket source');
        $expectedTicketSource = self::tokenCurrentYieldTicket((string) ($options['expected_current_yield_ticket_source_next235'] ?? $currentTicketSource), 'expected current yield ticket source');
        $resumeCursor = self::tokenCurrentYieldTicket((string) ($options['current_yield_resume_cursor_next235'] ?? 'wp.current.yield.cursor.235'), 'current yield resume cursor');
        $expectedResumeCursor = self::tokenCurrentYieldTicket((string) ($options['expected_current_yield_resume_cursor_next235'] ?? $resumeCursor), 'expected current yield resume cursor');
        $requireOrder = (bool) ($options['require_current_yield_ticket_order_next235'] ?? true);

        $currentRows = self::rowsCurrentYieldTicket($base['current_yield_stream_next232'] ?? [], 'current yield stream');
        $attemptedNextRows = self::rowsCurrentYieldTicket($base['attempted_next_yield_stream_next232'] ?? [], 'attempted next yield stream');
        $requiredTickets = self::ticketsCurrentYieldTicket($currentRows, $currentTicketSource, $resumeCursor);
        $acknowledgedTickets = self::acknowledgedTicketsCurrentYieldTicket($options, $requiredTickets);
        $missingTickets = array_values(array_diff($requiredTickets, $acknowledgedTickets));
        $unexpectedTickets = array_values(array_diff($acknowledgedTickets, $requiredTickets));
        $sourceMatches = hash_equals($currentTicketSource, $expectedTicketSource);
        $cursorMatches = hash_equals($resumeCursor, $expectedResumeCursor);
        $orderMatches = !$requireOrder || $requiredTickets === $acknowledgedTickets;
        $baseReleased = (bool) ($base['next_source_visible_after_current_upsert_conflict_next232'] ?? false);
        $ticketsComplete = $requiredTickets !== []
            && $baseReleased
            && $sourceMatches
            && $cursorMatches
            && $missingTickets === []
            && $unexpectedTickets === []
            && $orderMatches;

        $blockedReasons = self::blockedReasonsCurrentYieldTicket($baseReleased, $sourceMatches, $cursorMatches, $missingTickets, $unexpectedTickets, $requireOrder, $orderMatches);
        $currentTagged = self::tagRowsCurrentYieldTicket($currentRows, 'current', true, $requiredTickets, $currentTicketSource, $resumeCursor, []);
        $nextTagged = self::tagRowsCurrentYieldTicket($attemptedNextRows, 'next', $ticketsComplete, [], $currentTicketSource, $resumeCursor, $ticketsComplete ? [] : $blockedReasons);
        $currentReturning = self::tagRowsCurrentYieldTicket(self::rowsCurrentYieldTicket($base['current_returning_rows_next232'] ?? [], 'current returning rows'), 'current', true, $requiredTickets, $currentTicketSource, $resumeCursor, []);
        $nextReturning = self::tagRowsCurrentYieldTicket(self::rowsCurrentYieldTicket($base['attempted_next_returning_rows_next232'] ?? [], 'attempted next returning rows'), 'next', $ticketsComplete, [], $currentTicketSource, $resumeCursor, $ticketsComplete ? [] : $blockedReasons);

        return [
            'status_next235' => self::statusCurrentYieldTicket($ticketsComplete, $baseReleased, $sourceMatches, $cursorMatches, $missingTickets, $unexpectedTickets, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base_next235' => $base,
            'current_yield_ticket_source_next235' => $currentTicketSource,
            'expected_current_yield_ticket_source_next235' => $expectedTicketSource,
            'current_yield_ticket_source_matches_next235' => $sourceMatches,
            'current_yield_resume_cursor_next235' => $resumeCursor,
            'expected_current_yield_resume_cursor_next235' => $expectedResumeCursor,
            'current_yield_resume_cursor_matches_next235' => $cursorMatches,
            'required_current_yield_tickets_next235' => $requiredTickets,
            'acknowledged_current_yield_tickets_next235' => $acknowledgedTickets,
            'missing_current_yield_tickets_next235' => $missingTickets,
            'unexpected_current_yield_tickets_next235' => $unexpectedTickets,
            'require_current_yield_ticket_order_next235' => $requireOrder,
            'current_yield_ticket_order_matches_next235' => $orderMatches,
            'current_yield_ticket_complete_next235' => $ticketsComplete,
            'base_conflict_released_next235' => $baseReleased,
            'next_source_visible_after_current_yield_tickets_next235' => $ticketsComplete,
            'current_yield_stream_next235' => $currentTagged,
            'attempted_next_yield_stream_next235' => $nextTagged,
            'visible_yield_stream_next235' => $ticketsComplete ? array_merge($currentTagged, $nextTagged) : $currentTagged,
            'held_next_yield_stream_next235' => $ticketsComplete ? [] : $nextTagged,
            'current_returning_rows_next235' => $currentReturning,
            'attempted_next_returning_rows_next235' => $nextReturning,
            'visible_returning_rows_next235' => $ticketsComplete ? array_merge($currentReturning, $nextReturning) : $currentReturning,
            'held_next_returning_rows_next235' => $ticketsComplete ? [] : $nextReturning,
            'visible_change_count_next235' => $ticketsComplete ? (int) ($base['visible_change_count_next232'] ?? 0) : (int) ($base['current_change_count_next232'] ?? 0),
            'after_savepoint_next235' => $ticketsComplete ? ($base['after_savepoint_next232'] ?? []) : ($base['base']['after_savepoint'] ?? []),
            'blocked_reasons_next235' => $blockedReasons,
            'current_yield_ticket_plan_next235' => [
                'base_conflict_released' => $baseReleased,
                'ticket_source_matches' => $sourceMatches,
                'resume_cursor_matches' => $cursorMatches,
                'required_tickets' => $requiredTickets,
                'acknowledged_tickets' => $acknowledgedTickets,
                'missing_tickets' => $missingTickets,
                'unexpected_tickets' => $unexpectedTickets,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'ticket_complete' => $ticketsComplete,
                'decision' => $ticketsComplete
                    ? 'publish-next-source-after-current-recursive-view-upsert-yields'
                    : 'hold-next-source-until-current-recursive-view-upsert-yields',
            ],
            'yield_boundary_next235' => $ticketsComplete
                ? 'recursive-view-upsert-next235-current-yield-tickets-then-next'
                : 'recursive-view-upsert-next235-current-yield-tickets-fence-next',
            'dependency_closure_next235' => 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-conflict-seals-and-adds-yield-tickets',
            'dependencies_next235' => [
                'sqlite-trigger-recursive-view-upsert-current-source-next235',
                'sqlite-current-recursive-view-upsert-yield-ticket',
                'sqlite-current-upsert-conflict-seal',
                'wordpress-recursive-view-upsert-yield-ticket-next235',
            ],
            'non_overlap_next235' => 'adds current-source yield-ticket fencing after accepted next232 conflict seals; avoids recursive view RETURNING, trigger/FK, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private static function ticketsCurrentYieldTicket(array $rows, string $source, string $cursor): array
    {
        $tickets = [];
        foreach ($rows as $index => $row) {
            $returning = is_array($row['returning'] ?? null) ? $row['returning'] : $row;
            $parts = [
                $source,
                $cursor,
                (string) ($row['current_view_source_next232'] ?? ''),
                (string) ($row['current_trigger_program_next232'] ?? ''),
                (string) ($row['current_upsert_conflict_seal_next232'] ?? ''),
                (string) ($row['event'] ?? $returning['event'] ?? ''),
                (string) ($row['depth'] ?? $returning['depth'] ?? ''),
                (string) ($row['ordinal'] ?? $returning['ordinal'] ?? $index),
                (string) ($row['trigger'] ?? $returning['trigger'] ?? ''),
                (string) ($returning['option_name'] ?? ''),
                (string) ($returning['option_value'] ?? ''),
            ];
            $tickets[] = substr(hash('sha256', implode('|', $parts)), 0, 50);
        }

        return $tickets;
    }

    /** @param array<string,mixed> $options @param list<string> $required @return list<string> */
    private static function acknowledgedTicketsCurrentYieldTicket(array $options, array $required): array
    {
        if (($options['auto_ack_current_yield_tickets_next235'] ?? false) === true) {
            return $required;
        }

        return self::ticketListCurrentYieldTicket($options['acknowledged_current_yield_tickets_next235'] ?? [], 'acknowledged current yield tickets');
    }

    /** @param mixed $values @return list<string> */
    private static function ticketListCurrentYieldTicket(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next235 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{50}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next235 {$label} contain a malformed yield ticket");
            }
        }

        return array_values(array_unique($values));
    }

    /** @param mixed $rows @return list<array<string,mixed>> */
    private static function rowsCurrentYieldTicket(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next235 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next235 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /** @param list<array<string,mixed>> $rows @param list<string> $tickets @param list<string> $reasons @return list<array<string,mixed>> */
    private static function tagRowsCurrentYieldTicket(array $rows, string $phase, bool $visible, array $tickets, string $source, string $cursor, array $reasons): array
    {
        $tagged = [];
        foreach ($rows as $index => $row) {
            $tagged[] = $row + [
                'yield_ticket_phase_next235' => $phase,
                'current_yield_ticket_source_next235' => $source,
                'current_yield_resume_cursor_next235' => $cursor,
                'current_yield_ticket_next235' => $tickets[$index] ?? null,
                'visible_after_current_yield_ticket_next235' => $visible,
                'held_by_current_yield_ticket_reasons_next235' => $visible ? [] : $reasons,
            ];
        }

        return $tagged;
    }

    /** @param list<string> $missing @param list<string> $unexpected @return list<string> */
    private static function blockedReasonsCurrentYieldTicket(bool $baseReleased, bool $sourceMatches, bool $cursorMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        $reasons = [];
        if (!$baseReleased) {
            $reasons[] = 'base-current-upsert-conflict-not-released';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-yield-ticket-source-mismatch';
        }
        if (!$cursorMatches) {
            $reasons[] = 'current-yield-resume-cursor-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-yield-ticket-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-yield-ticket-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-yield-ticket-order-mismatch';
        }

        return $reasons;
    }

    /** @param list<string> $missing @param list<string> $unexpected */
    private static function statusCurrentYieldTicket(bool $complete, bool $baseReleased, bool $sourceMatches, bool $cursorMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($complete) {
            return 'trigger-recursive-view-upsert-current-source-next235-yield-released';
        }
        if (!$baseReleased) {
            return 'trigger-recursive-view-upsert-current-source-next235-base-conflict-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-upsert-current-source-next235-ticket-source-held';
        }
        if (!$cursorMatches) {
            return 'trigger-recursive-view-upsert-current-source-next235-resume-cursor-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next235-yield-ticket-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next235-yield-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next235-held';
    }

    private static function tokenCurrentYieldTicket(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next235 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentRowImageReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentUpsertConflictTarget(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rowsCurrentRowImageReceipt($base['current_source_rows_next233'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentRowImageReceipt($base['attempted_next_source_rows_next233'] ?? [], 'attempted next source rows');
        $baseVisible = (bool) ($base['next_source_visible_after_current_upsert_source_next233'] ?? false);
        $imageToken = self::tokenCurrentRowImageReceipt((string) ($options['current_upsert_row_image_token_next236'] ?? 'wp.current.upsert.row.image.236'), 'current upsert row-image token');
        $expectedImageToken = self::tokenCurrentRowImageReceipt((string) ($options['expected_current_upsert_row_image_token_next236'] ?? $imageToken), 'expected current upsert row-image token');
        $viewSource = self::tokenCurrentRowImageReceipt((string) ($options['current_upsert_row_image_view_source_next236'] ?? ($base['current_upsert_view_source_next233'] ?? ($currentView['source'] ?? 'main@view-upsert-236-current'))), 'current upsert row-image view source');
        $expectedViewSource = self::tokenCurrentRowImageReceipt((string) ($options['expected_current_upsert_row_image_view_source_next236'] ?? $viewSource), 'expected current upsert row-image view source');
        $triggerSource = self::tokenCurrentRowImageReceipt((string) ($options['current_upsert_row_image_trigger_source_next236'] ?? ($base['current_upsert_trigger_source_next233'] ?? ($currentView['trigger_source'] ?? 'main@trigger-upsert-236-current'))), 'current upsert row-image trigger source');
        $expectedTriggerSource = self::tokenCurrentRowImageReceipt((string) ($options['expected_current_upsert_row_image_trigger_source_next236'] ?? $triggerSource), 'expected current upsert row-image trigger source');
        $requireOrder = (bool) ($options['require_current_upsert_row_image_order_next236'] ?? true);

        $requiredReceipts = self::rowImageReceiptsCurrentRowImageReceipt($currentRows, $imageToken, $viewSource, $triggerSource);
        $acknowledgedReceipts = self::acknowledgedReceiptsCurrentRowImageReceipt($options, $requiredReceipts);
        $missingReceipts = array_values(array_diff($requiredReceipts, $acknowledgedReceipts));
        $unexpectedReceipts = array_values(array_diff($acknowledgedReceipts, $requiredReceipts));
        $imageMatches = hash_equals($imageToken, $expectedImageToken);
        $viewMatches = hash_equals($viewSource, $expectedViewSource);
        $triggerMatches = hash_equals($triggerSource, $expectedTriggerSource);
        $orderMatches = !$requireOrder || $requiredReceipts === $acknowledgedReceipts;
        $currentImages = self::rowImagesCurrentRowImageReceipt($currentRows);
        $hasImages = $requiredReceipts !== [] && $currentImages['total'] > 0;
        $imageComplete = $hasImages
            && $imageMatches
            && $viewMatches
            && $triggerMatches
            && $missingReceipts === []
            && $unexpectedReceipts === []
            && $orderMatches;
        $nextVisible = $baseVisible && $imageComplete;
        $blockedReasons = self::blockedReasonsCurrentRowImageReceipt(
            $base['blocked_reasons_next233'] ?? [],
            $baseVisible,
            $hasImages,
            $imageMatches,
            $viewMatches,
            $triggerMatches,
            $missingReceipts,
            $unexpectedReceipts,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRowsCurrentRowImageReceipt($currentRows, 'current', true, $requiredReceipts, $imageToken, $viewSource, $triggerSource, []);
        $nextRows = self::tagRowsCurrentRowImageReceipt($nextRows, 'next', $nextVisible, [], $imageToken, $viewSource, $triggerSource, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_upsert_row_image_next236'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_upsert_row_image_next236'],
        ));

        return [
            'status_next236' => self::statusCurrentRowImageReceipt($nextVisible, $baseVisible, $hasImages, $imageMatches, $viewMatches, $triggerMatches, $missingReceipts, $unexpectedReceipts, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next236' => $baseVisible,
            'current_upsert_row_image_token_next236' => $imageToken,
            'expected_current_upsert_row_image_token_next236' => $expectedImageToken,
            'current_upsert_row_image_token_matches_next236' => $imageMatches,
            'current_upsert_row_image_view_source_next236' => $viewSource,
            'expected_current_upsert_row_image_view_source_next236' => $expectedViewSource,
            'current_upsert_row_image_view_source_matches_next236' => $viewMatches,
            'current_upsert_row_image_trigger_source_next236' => $triggerSource,
            'expected_current_upsert_row_image_trigger_source_next236' => $expectedTriggerSource,
            'current_upsert_row_image_trigger_source_matches_next236' => $triggerMatches,
            'current_upsert_row_images_next236' => $currentImages,
            'current_upsert_row_image_has_rows_next236' => $hasImages,
            'required_current_upsert_row_image_receipts_next236' => $requiredReceipts,
            'acknowledged_current_upsert_row_image_receipts_next236' => $acknowledgedReceipts,
            'missing_current_upsert_row_image_receipts_next236' => $missingReceipts,
            'unexpected_current_upsert_row_image_receipts_next236' => $unexpectedReceipts,
            'require_current_upsert_row_image_order_next236' => $requireOrder,
            'current_upsert_row_image_order_matches_next236' => $orderMatches,
            'current_upsert_row_image_complete_next236' => $imageComplete,
            'next_source_visible_after_current_upsert_row_image_next236' => $nextVisible,
            'current_source_rows_next236' => $currentRows,
            'attempted_next_source_rows_next236' => $nextRows,
            'visible_returning_rows_next236' => $visibleRows,
            'held_next_source_rows_next236' => $heldRows,
            'visible_returning_payloads_next236' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next236' => array_column($heldRows, 'returning'),
            'current_source_row_count_next236' => count($currentRows),
            'attempted_next_source_row_count_next236' => count($nextRows),
            'visible_row_count_next236' => count($visibleRows),
            'held_next_row_count_next236' => count($heldRows),
            'blocked_reasons_next236' => $blockedReasons,
            'current_upsert_row_image_plan_next236' => [
                'base_next_source_visible' => $baseVisible,
                'has_row_images' => $hasImages,
                'image_token_matches' => $imageMatches,
                'view_source_matches' => $viewMatches,
                'trigger_source_matches' => $triggerMatches,
                'row_images' => $currentImages,
                'required_receipts' => $requiredReceipts,
                'acknowledged_receipts' => $acknowledgedReceipts,
                'missing_receipts' => $missingReceipts,
                'unexpected_receipts' => $unexpectedReceipts,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'row_image_complete' => $imageComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-view-upsert-row-images'
                    : 'hold-next-source-until-current-view-upsert-row-images',
            ],
            'yield_boundary_next236' => $nextVisible
                ? 'recursive-view-upsert-next236-current-row-images-then-next'
                : 'recursive-view-upsert-next236-current-row-image-fences-next',
            'dependency_closure_next236' => 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-and-adds-row-image-receipts',
            'dependencies_next236' => array_values(array_unique(array_merge($base['dependencies_next233'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next236',
                'sqlite-current-view-upsert-row-image-receipts',
                'wordpress-recursive-view-upsert-current-source-next236',
            ]))),
            'non_overlap_next236' => 'adds current recursive view UPSERT row-image receipts after accepted next233 conflict-target/update-column seals; avoids next233 UPSERT source seal, recursive view RETURNING generation/source handoffs, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private static function rowImageReceiptsCurrentRowImageReceipt(array $rows, string $imageToken, string $viewSource, string $triggerSource): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts = [
                $imageToken,
                $viewSource,
                $triggerSource,
                (string) ($row['current_upsert_seal_next233'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($returning['name'] ?? ''),
                (string) ($returning['event_name'] ?? ''),
                (string) ($returning['value'] ?? ''),
                (string) ($returning['depth_value'] ?? ''),
                (string) ($returning['trigger_source_alias'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 48);
        }

        return $receipts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{total:int,insert:int,update:int,recursive:int,names:list<string>,events:list<string>,max_depth:int}
     */
    private static function rowImagesCurrentRowImageReceipt(array $rows): array
    {
        $images = ['total' => 0, 'insert' => 0, 'update' => 0, 'recursive' => 0, 'names' => [], 'events' => [], 'max_depth' => 0];
        foreach ($rows as $row) {
            $returning = $row['returning'];
            $event = strtolower((string) ($returning['event_name'] ?? 'insert'));
            $depth = (int) ($returning['depth_value'] ?? 0);
            ++$images['total'];
            if ($event === 'update') {
                ++$images['update'];
            } else {
                ++$images['insert'];
                $event = 'insert';
            }
            if ($depth > 0) {
                ++$images['recursive'];
            }
            $images['max_depth'] = max($images['max_depth'], $depth);
            $images['names'][] = (string) ($returning['name'] ?? '');
            $images['events'][] = $event;
        }

        return $images;
    }

    /** @param array<string,mixed> $options @param list<string> $required @return list<string> */
    private static function acknowledgedReceiptsCurrentRowImageReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_upsert_row_image_receipts_next236'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentRowImageReceipt($options['acknowledged_current_upsert_row_image_receipts_next236'] ?? [], 'acknowledged current upsert row-image receipts');
    }

    /** @param mixed $values @return list<string> */
    private static function receiptListCurrentRowImageReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next236 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{48}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next236 {$label} contain a malformed row-image receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /** @param mixed $rows @return list<array<string,mixed>> */
    private static function rowsCurrentRowImageReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next236 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next236 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentRowImageReceipt(array $rows, string $phase, bool $visible, array $receipts, string $imageToken, string $viewSource, string $triggerSource, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_row_image_phase_next236' => $phase,
                'current_upsert_row_image_token_next236' => $imageToken,
                'current_upsert_row_image_view_source_next236' => $viewSource,
                'current_upsert_row_image_trigger_source_next236' => $triggerSource,
                'current_upsert_row_image_receipt_next236' => $receipts[$index] ?? null,
                'visible_after_current_upsert_row_image_next236' => $visible,
                'held_by_current_upsert_row_image_reasons_next236' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsCurrentRowImageReceipt(
        mixed $baseReasons,
        bool $baseVisible,
        bool $hasImages,
        bool $imageMatches,
        bool $viewMatches,
        bool $triggerMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        $reasons = is_array($baseReasons) && array_is_list($baseReasons) ? $baseReasons : [];
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'current-upsert-source-base-held';
        }
        if (!$hasImages) {
            $reasons[] = 'current-upsert-row-image-empty';
        }
        if (!$imageMatches) {
            $reasons[] = 'current-upsert-row-image-token-mismatch';
        }
        if (!$viewMatches) {
            $reasons[] = 'current-upsert-row-image-view-source-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-upsert-row-image-trigger-source-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-upsert-row-image-receipt-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-upsert-row-image-receipt-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-upsert-row-image-receipt-order-mismatch';
        }

        return array_values(array_unique(array_map('strval', $reasons)));
    }

    private static function tokenCurrentRowImageReceipt(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next236 {$label} is malformed");
        }

        return $value;
    }

    /** @param list<string> $missing @param list<string> $unexpected */
    private static function statusCurrentRowImageReceipt(
        bool $nextVisible,
        bool $baseVisible,
        bool $hasImages,
        bool $imageMatches,
        bool $viewMatches,
        bool $triggerMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): string {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next236-row-image-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next236-base-held';
        }
        if (!$hasImages) {
            return 'trigger-recursive-view-upsert-current-source-next236-empty-held';
        }
        if (!$imageMatches) {
            return 'trigger-recursive-view-upsert-current-source-next236-row-image-token-held';
        }
        if (!$viewMatches) {
            return 'trigger-recursive-view-upsert-current-source-next236-view-source-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-upsert-current-source-next236-trigger-source-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next236-row-image-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next236-row-image-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next236-held';
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentActionReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentSourceUpsertReceipt(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_next234'] ?? false);
        $currentRows = self::rowsCurrentActionReceipt($base['current_source_rows_next234'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentActionReceipt($base['attempted_next_source_rows_next234'] ?? [], 'attempted next source rows');
        $actionToken = self::tokenCurrentActionReceipt((string) ($options['current_source_upsert_action_token_next237'] ?? 'wp.current.source.upsert.action.237'), 'action token');
        $expectedActionToken = self::tokenCurrentActionReceipt((string) ($options['expected_current_source_upsert_action_token_next237'] ?? $actionToken), 'expected action token');
        $viewCookie = self::tokenCurrentActionReceipt((string) ($options['current_upsert_action_view_cookie_next237'] ?? ($base['current_upsert_view_cookie_next234'] ?? 'main@view-cookie-237-current')), 'view cookie');
        $triggerCookie = self::tokenCurrentActionReceipt((string) ($options['current_upsert_action_trigger_cookie_next237'] ?? ($base['current_upsert_trigger_cookie_next234'] ?? 'main@trigger-cookie-237-current')), 'trigger cookie');
        $requireOrder = (bool) ($options['require_current_source_upsert_action_order_next237'] ?? true);
        $actionOverrides = self::actionOverridesCurrentActionReceipt($options['current_source_upsert_actions_next237'] ?? []);

        $actionRows = self::actionRowsCurrentActionReceipt($currentRows, $baseRows, $actionOverrides);
        $required = self::actionReceiptsCurrentActionReceipt($actionRows, $actionToken, $viewCookie, $triggerCookie);
        $acknowledged = self::acknowledgedReceiptsCurrentActionReceipt($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $orderMatches = !$requireOrder || $required === $acknowledged;
        $tokenMatches = hash_equals($actionToken, $expectedActionToken);
        $actionComplete = $required !== []
            && $tokenMatches
            && $missing === []
            && $unexpected === []
            && $orderMatches;
        $nextVisible = $baseVisible && $actionComplete;
        $blockedReasons = self::blockedReasonsCurrentActionReceipt(
            $base['blocked_reasons_next234'] ?? [],
            $baseVisible,
            $tokenMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $taggedCurrent = self::tagRowsCurrentActionReceipt($currentRows, $actionRows, 'current-action', true, $required, $actionToken, $viewCookie, $triggerCookie, []);
        $taggedNext = self::tagRowsCurrentActionReceipt($nextRows, [], 'next-source', $nextVisible, [], $actionToken, $viewCookie, $triggerCookie, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next237' => self::statusCurrentActionReceipt($baseVisible, $tokenMatches, $missing, $unexpected, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next237' => $baseVisible,
            'current_source_upsert_action_token_next237' => $actionToken,
            'expected_current_source_upsert_action_token_next237' => $expectedActionToken,
            'current_source_upsert_action_token_matches_next237' => $tokenMatches,
            'current_upsert_action_view_cookie_next237' => $viewCookie,
            'current_upsert_action_trigger_cookie_next237' => $triggerCookie,
            'current_source_upsert_actions_next237' => $actionRows,
            'required_current_source_upsert_action_receipts_next237' => $required,
            'acknowledged_current_source_upsert_action_receipts_next237' => $acknowledged,
            'missing_current_source_upsert_action_receipts_next237' => $missing,
            'unexpected_current_source_upsert_action_receipts_next237' => $unexpected,
            'require_current_source_upsert_action_order_next237' => $requireOrder,
            'current_source_upsert_action_order_matches_next237' => $orderMatches,
            'current_source_upsert_action_complete_next237' => $actionComplete,
            'next_source_visible_after_current_source_upsert_action_next237' => $nextVisible,
            'current_source_rows_next237' => $taggedCurrent,
            'attempted_next_source_rows_next237' => $taggedNext,
            'visible_returning_rows_next237' => $visibleRows,
            'held_next_source_rows_next237' => $heldRows,
            'visible_returning_payloads_next237' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next237' => array_column($heldRows, 'returning'),
            'current_source_row_count_next237' => count($taggedCurrent),
            'attempted_next_source_row_count_next237' => count($taggedNext),
            'visible_row_count_next237' => count($visibleRows),
            'held_next_row_count_next237' => count($heldRows),
            'blocked_reasons_next237' => $blockedReasons,
            'current_source_upsert_action_plan_next237' => [
                'base_next_source_visible' => $baseVisible,
                'action_token_matches' => $tokenMatches,
                'required_action_receipts' => $required,
                'acknowledged_action_receipts' => $acknowledged,
                'missing_action_receipts' => $missing,
                'unexpected_action_receipts' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'action_complete' => $actionComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert-actions'
                    : 'hold-next-source-until-current-recursive-view-upsert-actions',
            ],
            'yield_boundary_next237' => $nextVisible
                ? 'recursive-view-upsert-next237-current-actions-then-next'
                : 'recursive-view-upsert-next237-current-actions-fence-next',
            'dependency_closure_next237' => 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-receipts-and-adds-conflict-action-seals',
            'dependencies_next237' => array_values(array_unique(array_merge($base['dependencies_next234'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next237',
                'sqlite-instead-of-view-trigger-current-source-upsert-action-seals',
                'wordpress-recursive-view-upsert-current-source-next237',
            ]))),
            'non_overlap_next237' => 'adds recursive INSTEAD OF view UPSERT conflict-action seals after accepted next234 conflict-key receipt admission; avoids next234 receipt duplication, trigger RETURNING conflicts, row-value savepoints, schema reparse, WAL/VFS, JSON, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $baseRows
     * @param array<string,string> $overrides
     * @return list<array{name:string,action:string,event:string,conflict:int}>
     */
    private static function actionRowsCurrentActionReceipt(array $rows, array $baseRows, array $overrides): array
    {
        $existing = [];
        foreach ($baseRows as $row) {
            if (is_array($row) && isset($row['option_name']) && is_scalar($row['option_name'])) {
                $existing[(string) $row['option_name']] = true;
            }
        }

        $actions = [];
        foreach ($rows as $row) {
            $returning = $row['returning'];
            $name = (string) ($returning['name'] ?? $returning['option_name'] ?? '');
            if ($name === '') {
                throw new InvalidArgumentException('SQLite recursive view UPSERT next237 current rows require a RETURNING name');
            }
            $action = $overrides[$name] ?? self::defaultActionCurrentActionReceipt($name, isset($existing[$name]));
            $actions[] = [
                'name' => $name,
                'action' => $action,
                'event' => (string) ($returning['event_name'] ?? $returning['event'] ?? ''),
                'conflict' => isset($existing[$name]) ? 1 : 0,
            ];
        }

        return $actions;
    }

    private static function defaultActionCurrentActionReceipt(string $name, bool $conflict): string
    {
        if (str_ends_with($name, '_child')) {
            return 'insert-recursive';
        }

        return $conflict ? 'do-update' : 'insert';
    }

    /**
     * @param list<array{name:string,action:string,event:string,conflict:int}> $actions
     * @return list<string>
     */
    private static function actionReceiptsCurrentActionReceipt(array $actions, string $token, string $viewCookie, string $triggerCookie): array
    {
        $receipts = [];
        foreach ($actions as $index => $action) {
            $receipts[] = substr(hash('sha256', implode('|', [
                $token,
                $viewCookie,
                $triggerCookie,
                (string) $index,
                $action['name'],
                $action['action'],
                $action['event'],
                (string) $action['conflict'],
            ])), 0, 46);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsCurrentActionReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_upsert_actions_next237'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentActionReceipt($options['acknowledged_current_source_upsert_action_receipts_next237'] ?? [], 'acknowledged current source upsert action receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListCurrentActionReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next237 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{46}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next237 {$label} contain a malformed action receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $values
     * @return array<string,string>
     */
    private static function actionOverridesCurrentActionReceipt(mixed $values): array
    {
        if (!is_array($values)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next237 action overrides must be an array');
        }
        $out = [];
        foreach ($values as $name => $action) {
            if (!is_string($name) || !preg_match('/^[A-Za-z0-9_:-]+$/', $name)) {
                throw new InvalidArgumentException('SQLite recursive view UPSERT next237 action override names must be stable identifiers');
            }
            if (!is_string($action) || !in_array($action, ['insert', 'insert-recursive', 'do-update', 'do-nothing'], true)) {
                throw new InvalidArgumentException('SQLite recursive view UPSERT next237 action override must be insert, insert-recursive, do-update, or do-nothing');
            }
            $out[$name] = $action;
        }

        return $out;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentActionReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next237 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next237 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{name:string,action:string,event:string,conflict:int}> $actions
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentActionReceipt(array $rows, array $actions, string $phase, bool $visible, array $receipts, string $token, string $viewCookie, string $triggerCookie, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_action_phase_next237' => $phase,
                'current_source_upsert_action_token_next237' => $token,
                'current_upsert_action_view_cookie_next237' => $viewCookie,
                'current_upsert_action_trigger_cookie_next237' => $triggerCookie,
                'current_source_upsert_action_next237' => $actions[$index]['action'] ?? null,
                'current_source_upsert_action_receipt_next237' => $receipts[$index] ?? null,
                'visible_after_current_source_upsert_action_next237' => $visible,
                'held_by_current_source_upsert_action_reasons_next237' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsCurrentActionReceipt(mixed $baseReasons, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next237 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next234-current-source-upsert-not-published';
        }
        if (!$tokenMatches) {
            $reasons[] = 'current-source-upsert-action-token-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-upsert-action-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-upsert-action-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-upsert-action-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusCurrentActionReceipt(bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next237-actions-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next237-base-held';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-upsert-current-source-next237-action-token-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next237-action-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next237-actions-held';
    }

    private static function tokenCurrentActionReceipt(string $token, string $label): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]{3,160}$/', $token)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next237 invalid {$label}");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentViewRows
     * @param list<array<string,mixed>> $nextViewRows
     * @param array{name:string,source:string,mapping:array<string,string>} $view
     * @param list<string> $uniqueColumns
     * @param list<array{name:string,when:string,target:string,value:string,recursive?:bool}> $triggers
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentUpsertReceipt(
        array $rows,
        array $currentViewRows,
        array $nextViewRows,
        array $view,
        array $uniqueColumns,
        array $triggers,
        array $options = [],
    ): array {
        $baseOptions = $options;
        $baseOptions['auto_ack_current_yield_tickets_next235'] = true;
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentYieldTicket(
            $rows,
            $currentViewRows,
            $nextViewRows,
            $view,
            $uniqueColumns,
            $triggers,
            $baseOptions,
        );

        $resumeSource = self::tokenCurrentUpsertReceipt((string) ($options['current_resume_source_next238'] ?? 'wp.current.resume.source.238'), 'current resume source');
        $expectedResumeSource = self::tokenCurrentUpsertReceipt((string) ($options['expected_current_resume_source_next238'] ?? $resumeSource), 'expected current resume source');
        $resumeCursor = self::tokenCurrentUpsertReceipt((string) ($options['current_resume_cursor_next238'] ?? 'wp.current.resume.cursor.238'), 'current resume cursor');
        $expectedResumeCursor = self::tokenCurrentUpsertReceipt((string) ($options['expected_current_resume_cursor_next238'] ?? $resumeCursor), 'expected current resume cursor');
        $resumeEpoch = self::tokenCurrentUpsertReceipt((string) ($options['current_resume_epoch_next238'] ?? 'wp.current.resume.epoch.238'), 'current resume epoch');
        $expectedResumeEpoch = self::tokenCurrentUpsertReceipt((string) ($options['expected_current_resume_epoch_next238'] ?? $resumeEpoch), 'expected current resume epoch');
        $requireOrder = (bool) ($options['require_current_resume_receipt_order_next238'] ?? true);

        $currentRows = self::rowsCurrentUpsertReceipt($base['current_yield_stream_next235'] ?? [], 'current yield stream');
        $attemptedNextRows = self::rowsCurrentUpsertReceipt($base['attempted_next_yield_stream_next235'] ?? [], 'attempted next yield stream');
        $requiredReceipts = self::receiptsCurrentUpsertReceipt($currentRows, $resumeSource, $resumeCursor, $resumeEpoch);
        $acknowledgedReceipts = self::acknowledgedReceiptsCurrentUpsertReceipt($options, $requiredReceipts);
        $missingReceipts = array_values(array_diff($requiredReceipts, $acknowledgedReceipts));
        $unexpectedReceipts = array_values(array_diff($acknowledgedReceipts, $requiredReceipts));
        $sourceMatches = hash_equals($resumeSource, $expectedResumeSource);
        $cursorMatches = hash_equals($resumeCursor, $expectedResumeCursor);
        $epochMatches = hash_equals($resumeEpoch, $expectedResumeEpoch);
        $orderMatches = !$requireOrder || $requiredReceipts === $acknowledgedReceipts;
        $baseReleased = (bool) ($base['next_source_visible_after_current_yield_tickets_next235'] ?? false);
        $resumeComplete = $requiredReceipts !== []
            && $baseReleased
            && $sourceMatches
            && $cursorMatches
            && $epochMatches
            && $missingReceipts === []
            && $unexpectedReceipts === []
            && $orderMatches;

        $blockedReasons = self::blockedReasonsCurrentUpsertReceipt($baseReleased, $sourceMatches, $cursorMatches, $epochMatches, $missingReceipts, $unexpectedReceipts, $requireOrder, $orderMatches);
        $currentTagged = self::tagRowsCurrentUpsertReceipt($currentRows, 'current', true, $requiredReceipts, $resumeSource, $resumeCursor, $resumeEpoch, []);
        $nextTagged = self::tagRowsCurrentUpsertReceipt($attemptedNextRows, 'next', $resumeComplete, [], $resumeSource, $resumeCursor, $resumeEpoch, $resumeComplete ? [] : $blockedReasons);
        $currentReturning = self::tagRowsCurrentUpsertReceipt(self::rowsCurrentUpsertReceipt($base['current_returning_rows_next235'] ?? [], 'current returning rows'), 'current', true, $requiredReceipts, $resumeSource, $resumeCursor, $resumeEpoch, []);
        $nextReturning = self::tagRowsCurrentUpsertReceipt(self::rowsCurrentUpsertReceipt($base['attempted_next_returning_rows_next235'] ?? [], 'attempted next returning rows'), 'next', $resumeComplete, [], $resumeSource, $resumeCursor, $resumeEpoch, $resumeComplete ? [] : $blockedReasons);

        return [
            'status_next238' => self::statusCurrentUpsertReceipt($resumeComplete, $baseReleased, $sourceMatches, $cursorMatches, $epochMatches, $missingReceipts, $unexpectedReceipts, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base_next238' => $base,
            'current_resume_source_next238' => $resumeSource,
            'expected_current_resume_source_next238' => $expectedResumeSource,
            'current_resume_source_matches_next238' => $sourceMatches,
            'current_resume_cursor_next238' => $resumeCursor,
            'expected_current_resume_cursor_next238' => $expectedResumeCursor,
            'current_resume_cursor_matches_next238' => $cursorMatches,
            'current_resume_epoch_next238' => $resumeEpoch,
            'expected_current_resume_epoch_next238' => $expectedResumeEpoch,
            'current_resume_epoch_matches_next238' => $epochMatches,
            'required_current_resume_receipts_next238' => $requiredReceipts,
            'acknowledged_current_resume_receipts_next238' => $acknowledgedReceipts,
            'missing_current_resume_receipts_next238' => $missingReceipts,
            'unexpected_current_resume_receipts_next238' => $unexpectedReceipts,
            'require_current_resume_receipt_order_next238' => $requireOrder,
            'current_resume_receipt_order_matches_next238' => $orderMatches,
            'current_resume_receipt_complete_next238' => $resumeComplete,
            'base_yield_ticket_released_next238' => $baseReleased,
            'next_source_visible_after_current_resume_receipts_next238' => $resumeComplete,
            'current_resume_stream_next238' => $currentTagged,
            'attempted_next_resume_stream_next238' => $nextTagged,
            'visible_resume_stream_next238' => $resumeComplete ? array_merge($currentTagged, $nextTagged) : $currentTagged,
            'held_next_resume_stream_next238' => $resumeComplete ? [] : $nextTagged,
            'current_returning_rows_next238' => $currentReturning,
            'attempted_next_returning_rows_next238' => $nextReturning,
            'visible_returning_rows_next238' => $resumeComplete ? array_merge($currentReturning, $nextReturning) : $currentReturning,
            'held_next_returning_rows_next238' => $resumeComplete ? [] : $nextReturning,
            'visible_change_count_next238' => $resumeComplete ? (int) ($base['visible_change_count_next235'] ?? 0) : (int) ($base['base_next235']['current_change_count_next232'] ?? 0),
            'after_savepoint_next238' => $resumeComplete ? ($base['after_savepoint_next235'] ?? []) : ($base['base_next235']['base']['after_savepoint'] ?? []),
            'blocked_reasons_next238' => $blockedReasons,
            'current_resume_receipt_plan_next238' => [
                'base_yield_ticket_released' => $baseReleased,
                'resume_source_matches' => $sourceMatches,
                'resume_cursor_matches' => $cursorMatches,
                'resume_epoch_matches' => $epochMatches,
                'required_receipts' => $requiredReceipts,
                'acknowledged_receipts' => $acknowledgedReceipts,
                'missing_receipts' => $missingReceipts,
                'unexpected_receipts' => $unexpectedReceipts,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'resume_complete' => $resumeComplete,
                'decision' => $resumeComplete
                    ? 'publish-next-source-after-current-recursive-view-upsert-resume'
                    : 'hold-next-source-until-current-recursive-view-upsert-resume',
            ],
            'yield_boundary_next238' => $resumeComplete
                ? 'recursive-view-upsert-next238-current-resume-receipts-then-next'
                : 'recursive-view-upsert-next238-current-resume-receipts-fence-next',
            'dependency_closure_next238' => 'no-new-support-component-reuses-native-recursive-view-upsert-yield-tickets-and-adds-current-resume-receipts',
            'dependencies_next238' => [
                'sqlite-trigger-recursive-view-upsert-current-source-next238',
                'sqlite-current-recursive-view-upsert-resume-receipt',
                'sqlite-current-recursive-view-upsert-yield-ticket',
                'wordpress-recursive-view-upsert-resume-receipt-next238',
            ],
            'non_overlap_next238' => 'adds current-source resume receipt fencing after next235 yield tickets; avoids accepted next232 conflict seals, next235 yield tickets, recursive view RETURNING, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private static function receiptsCurrentUpsertReceipt(array $rows, string $source, string $cursor, string $epoch): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = is_array($row['returning'] ?? null) ? $row['returning'] : $row;
            $nextRow = is_array($row['next_row'] ?? null) ? $row['next_row'] : [];
            $currentRow = is_array($row['current_row'] ?? null) ? $row['current_row'] : [];
            $parts = [
                $source,
                $cursor,
                $epoch,
                (string) ($row['current_yield_ticket_next235'] ?? ''),
                (string) ($row['current_view_source_next232'] ?? ''),
                (string) ($row['current_trigger_program_next232'] ?? ''),
                (string) ($row['phase'] ?? ''),
                (string) ($row['event'] ?? $returning['event'] ?? ''),
                (string) ($row['depth'] ?? $returning['depth'] ?? ''),
                (string) ($row['ordinal'] ?? $returning['ordinal'] ?? $index),
                (string) ($row['trigger'] ?? $returning['trigger'] ?? ''),
                (string) ($nextRow['option_name'] ?? $returning['option_name'] ?? ''),
                (string) ($currentRow['option_value'] ?? $returning['old_value'] ?? ''),
                (string) ($nextRow['option_value'] ?? $returning['option_value'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 52);
        }

        return $receipts;
    }

    /** @param array<string,mixed> $options @param list<string> $required @return list<string> */
    private static function acknowledgedReceiptsCurrentUpsertReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_resume_receipts_next238'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentUpsertReceipt($options['acknowledged_current_resume_receipts_next238'] ?? [], 'acknowledged current resume receipts');
    }

    /** @param mixed $values @return list<string> */
    private static function receiptListCurrentUpsertReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next238 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{52}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next238 {$label} contain a malformed resume receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /** @param mixed $rows @return list<array<string,mixed>> */
    private static function rowsCurrentUpsertReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next238 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next238 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /** @param list<array<string,mixed>> $rows @param list<string> $receipts @param list<string> $reasons @return list<array<string,mixed>> */
    private static function tagRowsCurrentUpsertReceipt(array $rows, string $phase, bool $visible, array $receipts, string $source, string $cursor, string $epoch, array $reasons): array
    {
        $tagged = [];
        foreach ($rows as $index => $row) {
            $tagged[] = $row + [
                'resume_receipt_phase_next238' => $phase,
                'current_resume_source_next238' => $source,
                'current_resume_cursor_next238' => $cursor,
                'current_resume_epoch_next238' => $epoch,
                'current_resume_receipt_next238' => $receipts[$index] ?? null,
                'visible_after_current_resume_receipt_next238' => $visible,
                'held_by_current_resume_receipt_reasons_next238' => $visible ? [] : $reasons,
            ];
        }

        return $tagged;
    }

    /** @param list<string> $missing @param list<string> $unexpected @return list<string> */
    private static function blockedReasonsCurrentUpsertReceipt(bool $baseReleased, bool $sourceMatches, bool $cursorMatches, bool $epochMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        $reasons = [];
        if (!$baseReleased) {
            $reasons[] = 'base-current-yield-ticket-not-released';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-resume-source-mismatch';
        }
        if (!$cursorMatches) {
            $reasons[] = 'current-resume-cursor-mismatch';
        }
        if (!$epochMatches) {
            $reasons[] = 'current-resume-epoch-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-resume-receipt-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-resume-receipt-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-resume-receipt-order-mismatch';
        }

        return $reasons;
    }

    /** @param list<string> $missing @param list<string> $unexpected */
    private static function statusCurrentUpsertReceipt(bool $complete, bool $baseReleased, bool $sourceMatches, bool $cursorMatches, bool $epochMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($complete) {
            return 'trigger-recursive-view-upsert-current-source-next238-resume-released';
        }
        if (!$baseReleased) {
            return 'trigger-recursive-view-upsert-current-source-next238-base-yield-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-upsert-current-source-next238-resume-source-held';
        }
        if (!$cursorMatches) {
            return 'trigger-recursive-view-upsert-current-source-next238-resume-cursor-held';
        }
        if (!$epochMatches) {
            return 'trigger-recursive-view-upsert-current-source-next238-resume-epoch-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next238-resume-receipt-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next238-resume-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next238-held';
    }

    private static function tokenCurrentUpsertReceipt(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next238 {$label} is malformed");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentTargetReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceCursorClose(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_close_source_close'] ?? false);
        $currentRows = self::rowsCurrentTargetReceipt($base['current_source_rows_source_close'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentTargetReceipt($base['attempted_next_source_rows_source_close'] ?? [], 'attempted next source rows');
        $target = self::tokenCurrentTargetReceipt((string) ($options['current_source_upsert_target_next239'] ?? 'option_name'), 'upsert target');
        $policy = self::tokenCurrentTargetReceipt((string) ($options['current_source_upsert_policy_next239'] ?? 'do-update-returning'), 'upsert policy');
        $cursor = self::tokenCurrentTargetReceipt((string) ($options['current_source_upsert_cursor_next239'] ?? 'wp.returning.upsert.cursor.239'), 'upsert cursor');
        $generation = self::tokenCurrentTargetReceipt((string) ($options['current_source_upsert_generation_next239'] ?? 'wp.current.source.upsert.generation.239'), 'upsert generation');
        $expectedGeneration = self::tokenCurrentTargetReceipt((string) ($options['expected_current_source_upsert_generation_next239'] ?? $generation), 'expected upsert generation');
        $requireOrder = (bool) ($options['require_current_source_upsert_order_next239'] ?? true);
        $generationMatches = hash_equals($generation, $expectedGeneration);
        $requiredTargets = self::targetReceiptsCurrentTargetReceipt($currentRows, $target, $policy, $cursor, $generation);
        $acknowledgedTargets = self::acknowledgedTargetsCurrentTargetReceipt($options, $requiredTargets);
        $missingTargets = array_values(array_diff($requiredTargets, $acknowledgedTargets));
        $unexpectedTargets = array_values(array_diff($acknowledgedTargets, $requiredTargets));
        $orderMatches = !$requireOrder || $requiredTargets === $acknowledgedTargets;
        $upsertComplete = $requiredTargets !== []
            && $generationMatches
            && $missingTargets === []
            && $unexpectedTargets === []
            && $orderMatches;
        $nextVisible = $baseVisible && $upsertComplete;
        $blockedReasons = self::blockedReasonsCurrentTargetReceipt(
            $base['blocked_reasons_source_close'] ?? [],
            $baseVisible,
            $generationMatches,
            $missingTargets,
            $unexpectedTargets,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRowsCurrentTargetReceipt($currentRows, 'current', true, $requiredTargets, $target, $policy, $cursor, $generation, []);
        $nextRows = self::tagRowsCurrentTargetReceipt($nextRows, 'next', $nextVisible, [], $target, $policy, $cursor, $generation, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_upsert_next239'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_upsert_next239'],
        ));

        return [
            'status_next239' => self::statusCurrentTargetReceipt($baseVisible, $generationMatches, $missingTargets, $unexpectedTargets, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next239' => $baseVisible,
            'current_source_upsert_target_next239' => $target,
            'current_source_upsert_policy_next239' => $policy,
            'current_source_upsert_cursor_next239' => $cursor,
            'current_source_upsert_generation_next239' => $generation,
            'expected_current_source_upsert_generation_next239' => $expectedGeneration,
            'current_source_upsert_generation_matches_next239' => $generationMatches,
            'required_current_source_upsert_targets_next239' => $requiredTargets,
            'acknowledged_current_source_upsert_targets_next239' => $acknowledgedTargets,
            'missing_current_source_upsert_targets_next239' => $missingTargets,
            'unexpected_current_source_upsert_targets_next239' => $unexpectedTargets,
            'require_current_source_upsert_order_next239' => $requireOrder,
            'current_source_upsert_order_matches_next239' => $orderMatches,
            'current_source_upsert_complete_next239' => $upsertComplete,
            'next_source_visible_after_current_source_upsert_next239' => $nextVisible,
            'current_source_rows_next239' => $currentRows,
            'attempted_next_source_rows_next239' => $nextRows,
            'visible_returning_rows_next239' => $visibleRows,
            'held_next_source_rows_next239' => $heldRows,
            'visible_returning_payloads_next239' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next239' => array_column($heldRows, 'returning'),
            'current_source_row_count_next239' => count($currentRows),
            'attempted_next_source_row_count_next239' => count($nextRows),
            'visible_row_count_next239' => count($visibleRows),
            'held_next_row_count_next239' => count($heldRows),
            'blocked_reasons_next239' => $blockedReasons,
            'current_source_upsert_plan_next239' => [
                'base_next_source_visible' => $baseVisible,
                'target' => $target,
                'policy' => $policy,
                'generation_matches' => $generationMatches,
                'required_targets' => $requiredTargets,
                'acknowledged_targets' => $acknowledgedTargets,
                'missing_targets' => $missingTargets,
                'unexpected_targets' => $unexpectedTargets,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'upsert_complete' => $upsertComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-upsert-targets'
                    : 'hold-next-source-until-current-upsert-targets',
            ],
            'yield_boundary_next239' => $nextVisible
                ? 'recursive-view-upsert-next239-current-targets-then-next'
                : 'recursive-view-upsert-next239-current-targets-fence-next',
            'dependency_closure_next239' => 'no-new-support-component-reuses-native-recursive-view-returning-source_close-and-adds-current-source-upsert-target-admission',
            'dependencies_next239' => array_values(array_unique(array_merge($base['dependencies_source_close'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next239',
                'sqlite-upsert-current-source-target-receipts',
                'wordpress-recursive-view-upsert-current-source-next239',
            ]))),
            'non_overlap_next239' => 'adds current-source UPSERT target receipt admission after source_close cursor close; avoids accepted recursive view RETURNING next203-source_close surfaces, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function targetReceiptsCurrentTargetReceipt(array $rows, string $target, string $policy, string $cursor, string $generation): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts = [
                $target,
                $policy,
                $cursor,
                $generation,
                (string) ($row['current_source_close_receipt_source_close'] ?? ''),
                (string) ($returning['name'] ?? ''),
                (string) ($returning['value'] ?? ''),
                (string) ($returning['event_name'] ?? ''),
                (string) ($returning['ordinal_value'] ?? $index),
                (string) ($returning['trigger_source_alias'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 40);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedTargetsCurrentTargetReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_upsert_targets_next239'] ?? false) === true) {
            return $required;
        }

        return self::targetListCurrentTargetReceipt($options['acknowledged_current_source_upsert_targets_next239'] ?? [], 'acknowledged current source upsert targets');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function targetListCurrentTargetReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next239 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{40}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next239 {$label} contain a malformed target receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentTargetReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next239 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next239 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentTargetReceipt(
        array $rows,
        string $phase,
        bool $visible,
        array $receipts,
        string $target,
        string $policy,
        string $cursor,
        string $generation,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_target_phase_next239' => $phase,
                'current_source_upsert_target_next239' => $target,
                'current_source_upsert_policy_next239' => $policy,
                'current_source_upsert_cursor_next239' => $cursor,
                'current_source_upsert_generation_next239' => $generation,
                'current_source_upsert_target_receipt_next239' => $receipts[$index] ?? null,
                'visible_after_current_source_upsert_next239' => $visible,
                'held_by_current_source_upsert_reasons_next239' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsCurrentTargetReceipt(
        mixed $baseReasons,
        bool $baseVisible,
        bool $generationMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next239 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-source-close-current-source-close-not-published';
        }
        if (!$generationMatches) {
            $reasons[] = 'current-source-upsert-generation-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-upsert-target-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-upsert-target-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-upsert-target-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusCurrentTargetReceipt(
        bool $baseVisible,
        bool $generationMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
        bool $nextVisible,
    ): string {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next239-targets-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next239-base-held';
        }
        if (!$generationMatches) {
            return 'trigger-recursive-view-upsert-current-source-next239-generation-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next239-targets-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next239-target-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next239-empty-held';
    }

    private static function tokenCurrentTargetReceipt(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next239 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentCompositeKeyReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceCursorClose(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $cursor = self::tokenCurrentCompositeKeyReceipt((string) ($options['current_source_upsert_cursor_next240'] ?? 'wp.upsert.current.cursor.240'), 'current source upsert cursor');
        $viewCookie = self::tokenCurrentCompositeKeyReceipt((string) ($options['current_view_upsert_cookie_next240'] ?? (string) ($currentView['source'] ?? 'main@view-upsert-cookie-240-current')), 'current view upsert cookie');
        $triggerCookie = self::tokenCurrentCompositeKeyReceipt((string) ($options['current_trigger_upsert_cookie_next240'] ?? (string) ($currentView['trigger_source'] ?? 'main@trigger-upsert-cookie-240-current')), 'current trigger upsert cookie');
        $conflictColumns = self::columnsCurrentCompositeKeyReceipt($options['upsert_conflict_columns_next240'] ?? ['name'], 'conflict columns');
        $requireOrder = (bool) ($options['require_current_source_upsert_order_next240'] ?? true);
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_close_source_close'] ?? false);

        $currentRows = self::rowsCurrentCompositeKeyReceipt($base['current_source_rows_source_close'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentCompositeKeyReceipt($base['attempted_next_source_rows_source_close'] ?? [], 'attempted next source rows');
        $currentKeys = self::currentKeysCurrentCompositeKeyReceipt($currentRows, $conflictColumns);
        $nextKeys = self::nextKeysCurrentCompositeKeyReceipt($nextRows, $conflictColumns);
        $conflictingNextKeys = array_values(array_intersect($nextKeys, $currentKeys));
        $requiredReceipts = self::upsertReceiptsCurrentCompositeKeyReceipt($currentRows, $conflictColumns, $cursor, $viewCookie, $triggerCookie);
        $acknowledgedReceipts = self::acknowledgedReceiptsCurrentCompositeKeyReceipt($options, $requiredReceipts);
        $missingReceipts = array_values(array_diff($requiredReceipts, $acknowledgedReceipts));
        $unexpectedReceipts = array_values(array_diff($acknowledgedReceipts, $requiredReceipts));
        $orderMatches = !$requireOrder || $requiredReceipts === $acknowledgedReceipts;
        $conflictSourceComplete = $requiredReceipts !== []
            && $missingReceipts === []
            && $unexpectedReceipts === []
            && $orderMatches;
        $nextVisible = $baseVisible && $conflictSourceComplete;
        $blockedReasons = self::blockedReasonsCurrentCompositeKeyReceipt(
            $base['blocked_reasons_source_close'] ?? [],
            $baseVisible,
            $missingReceipts,
            $unexpectedReceipts,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRowsCurrentCompositeKeyReceipt($currentRows, 'current', true, $requiredReceipts, $cursor, $viewCookie, $triggerCookie, $conflictColumns, []);
        $nextRows = self::tagRowsCurrentCompositeKeyReceipt($nextRows, 'next', $nextVisible, [], $cursor, $viewCookie, $triggerCookie, $conflictColumns, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_upsert_next240'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_upsert_next240'],
        ));

        return [
            'status_next240' => self::statusCurrentCompositeKeyReceipt($baseVisible, $missingReceipts, $unexpectedReceipts, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next240' => $baseVisible,
            'current_source_upsert_cursor_next240' => $cursor,
            'current_view_upsert_cookie_next240' => $viewCookie,
            'current_trigger_upsert_cookie_next240' => $triggerCookie,
            'upsert_conflict_columns_next240' => $conflictColumns,
            'current_upsert_conflict_keys_next240' => $currentKeys,
            'attempted_next_upsert_conflict_keys_next240' => $nextKeys,
            'conflicting_next_upsert_keys_next240' => $conflictingNextKeys,
            'required_current_source_upsert_receipts_next240' => $requiredReceipts,
            'acknowledged_current_source_upsert_receipts_next240' => $acknowledgedReceipts,
            'missing_current_source_upsert_receipts_next240' => $missingReceipts,
            'unexpected_current_source_upsert_receipts_next240' => $unexpectedReceipts,
            'require_current_source_upsert_order_next240' => $requireOrder,
            'current_source_upsert_order_matches_next240' => $orderMatches,
            'current_source_upsert_complete_next240' => $conflictSourceComplete,
            'next_source_visible_after_current_source_upsert_next240' => $nextVisible,
            'current_source_rows_next240' => $currentRows,
            'attempted_next_source_rows_next240' => $nextRows,
            'visible_returning_rows_next240' => $visibleRows,
            'held_next_source_rows_next240' => $heldRows,
            'visible_returning_payloads_next240' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next240' => array_column($heldRows, 'returning'),
            'current_source_row_count_next240' => count($currentRows),
            'attempted_next_source_row_count_next240' => count($nextRows),
            'visible_row_count_next240' => count($visibleRows),
            'held_next_row_count_next240' => count($heldRows),
            'blocked_reasons_next240' => $blockedReasons,
            'current_source_upsert_plan_next240' => [
                'base_next_source_visible' => $baseVisible,
                'conflict_columns' => $conflictColumns,
                'current_keys' => $currentKeys,
                'attempted_next_keys' => $nextKeys,
                'conflicting_next_keys' => $conflictingNextKeys,
                'required_receipts' => $requiredReceipts,
                'acknowledged_receipts' => $acknowledgedReceipts,
                'missing_receipts' => $missingReceipts,
                'unexpected_receipts' => $unexpectedReceipts,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'upsert_complete' => $conflictSourceComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-view-upsert-conflict-source'
                    : 'hold-next-source-until-current-view-upsert-conflict-source',
            ],
            'yield_boundary_next240' => $nextVisible
                ? 'recursive-view-upsert-next240-current-conflict-source-then-next'
                : 'recursive-view-upsert-next240-current-conflict-source-fences-next',
            'dependency_closure_next240' => 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-handoff',
            'dependencies_next240' => array_values(array_unique(array_merge($base['dependencies_source_close'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next240',
                'sqlite-instead-of-view-upsert-conflict-source',
                'wordpress-recursive-view-upsert-current-source-next240',
            ]))),
            'non_overlap_next240' => 'adds current-source UPSERT conflict-key admission after accepted source_close cursor-close handoff; avoids accepted trigger recursive view RETURNING next157-source_close cursor/ticket/close surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param mixed $columns
     * @return list<string>
     */
    private static function columnsCurrentCompositeKeyReceipt(mixed $columns, string $label): array
    {
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next240 {$label} must be a non-empty list");
        }
        $out = [];
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '' || preg_match('/\s/', $column) === 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next240 {$label} contain a malformed column");
            }
            $out[] = $column;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentCompositeKeyReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next240 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next240 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<string>
     */
    private static function currentKeysCurrentCompositeKeyReceipt(array $rows, array $columns): array
    {
        $keys = [];
        foreach ($rows as $row) {
            $keys[] = self::keyForCurrentCompositeKeyReceipt($row['returning'], $columns);
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<string>
     */
    private static function nextKeysCurrentCompositeKeyReceipt(array $rows, array $columns): array
    {
        $keys = [];
        foreach ($rows as $row) {
            $keys[] = self::keyForCurrentCompositeKeyReceipt($row['returning'], $columns);
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param array<string,mixed> $payload
     * @param list<string> $columns
     */
    private static function keyForCurrentCompositeKeyReceipt(array $payload, array $columns): string
    {
        $parts = [];
        foreach ($columns as $column) {
            if (!array_key_exists($column, $payload)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next240 conflict column {$column} is missing");
            }
            $parts[] = $column . '=' . self::scalarKeyCurrentCompositeKeyReceipt($payload[$column]);
        }

        return implode('|', $parts);
    }

    private static function scalarKeyCurrentCompositeKeyReceipt(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? 'BOOL:1' : 'BOOL:0',
            is_int($value) => 'INT:' . $value,
            is_float($value) => 'FLOAT:' . sprintf('%.17g', $value),
            is_string($value) => 'TEXT:' . $value,
            default => throw new InvalidArgumentException('SQLite recursive view UPSERT next240 conflict value must be scalar'),
        };
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<string>
     */
    private static function upsertReceiptsCurrentCompositeKeyReceipt(array $rows, array $columns, string $cursor, string $viewCookie, string $triggerCookie): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $cursor,
                $viewCookie,
                $triggerCookie,
                (string) ($row['current_source_close_receipt_source_close'] ?? ''),
                self::keyForCurrentCompositeKeyReceipt($row['returning'], $columns),
                (string) ($row['returning_row_ordinal'] ?? $index),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 48);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsCurrentCompositeKeyReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_upserts_next240'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentCompositeKeyReceipt($options['acknowledged_current_source_upserts_next240'] ?? [], 'acknowledged current source upserts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListCurrentCompositeKeyReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next240 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{48}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next240 {$label} contain a malformed receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $columns
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentCompositeKeyReceipt(
        array $rows,
        string $phase,
        bool $visible,
        array $receipts,
        string $cursor,
        string $viewCookie,
        string $triggerCookie,
        array $columns,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_source_phase_next240' => $phase,
                'current_source_upsert_cursor_next240' => $cursor,
                'current_view_upsert_cookie_next240' => $viewCookie,
                'current_trigger_upsert_cookie_next240' => $triggerCookie,
                'upsert_conflict_columns_next240' => $columns,
                'upsert_conflict_key_next240' => self::keyForCurrentCompositeKeyReceipt($row['returning'], $columns),
                'current_source_upsert_receipt_next240' => $receipts[$index] ?? null,
                'visible_after_current_source_upsert_next240' => $visible,
                'held_by_current_source_upsert_reasons_next240' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsCurrentCompositeKeyReceipt(
        mixed $baseReasons,
        bool $baseVisible,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next240 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-source-close-current-source-close-not-published';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-upsert-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-upsert-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-upsert-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusCurrentCompositeKeyReceipt(
        bool $baseVisible,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
        bool $nextVisible,
    ): string {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next240-conflict-source-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next240-base-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next240-conflict-source-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next240-conflict-source-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next240-conflict-source-empty-held';
    }

    private static function tokenCurrentCompositeKeyReceipt(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next240 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentCloseReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentActionReceipt(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_action_next237'] ?? false);
        $currentRows = self::rowsCurrentCloseReceipt($base['current_source_rows_next237'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentCloseReceipt($base['attempted_next_source_rows_next237'] ?? [], 'attempted next source rows');
        $closeToken = self::tokenCurrentCloseReceipt((string) ($options['current_source_upsert_close_token_next241'] ?? 'wp.current.source.upsert.close.241'), 'close token');
        $expectedCloseToken = self::tokenCurrentCloseReceipt((string) ($options['expected_current_source_upsert_close_token_next241'] ?? $closeToken), 'expected close token');
        $sourceGeneration = self::tokenCurrentCloseReceipt((string) ($options['current_source_upsert_generation_next241'] ?? 'main@source-generation-241-current'), 'source generation');
        $expectedSourceGeneration = self::tokenCurrentCloseReceipt((string) ($options['expected_current_source_upsert_generation_next241'] ?? $sourceGeneration), 'expected source generation');
        $viewCookie = self::tokenCurrentCloseReceipt((string) ($options['current_upsert_close_view_cookie_next241'] ?? ($base['current_upsert_action_view_cookie_next237'] ?? 'main@view-cookie-241-current')), 'view cookie');
        $expectedViewCookie = self::tokenCurrentCloseReceipt((string) ($options['expected_current_upsert_close_view_cookie_next241'] ?? $viewCookie), 'expected view cookie');
        $triggerCookie = self::tokenCurrentCloseReceipt((string) ($options['current_upsert_close_trigger_cookie_next241'] ?? ($base['current_upsert_action_trigger_cookie_next237'] ?? 'main@trigger-cookie-241-current')), 'trigger cookie');
        $expectedTriggerCookie = self::tokenCurrentCloseReceipt((string) ($options['expected_current_upsert_close_trigger_cookie_next241'] ?? $triggerCookie), 'expected trigger cookie');
        $requireOrder = (bool) ($options['require_current_source_upsert_close_order_next241'] ?? true);

        $required = self::closeReceiptsCurrentCloseReceipt($currentRows, $closeToken, $sourceGeneration, $viewCookie, $triggerCookie);
        $acknowledged = self::acknowledgedReceiptsCurrentCloseReceipt($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $orderMatches = !$requireOrder || $required === $acknowledged;
        $tokenMatches = hash_equals($closeToken, $expectedCloseToken);
        $sourceMatches = hash_equals($sourceGeneration, $expectedSourceGeneration);
        $viewMatches = hash_equals($viewCookie, $expectedViewCookie);
        $triggerMatches = hash_equals($triggerCookie, $expectedTriggerCookie);
        $closeComplete = $required !== []
            && $tokenMatches
            && $sourceMatches
            && $viewMatches
            && $triggerMatches
            && $missing === []
            && $unexpected === []
            && $orderMatches;
        $nextVisible = $baseVisible && $closeComplete;
        $blockedReasons = self::blockedReasonsCurrentCloseReceipt(
            $base['blocked_reasons_next237'] ?? [],
            $baseVisible,
            $tokenMatches,
            $sourceMatches,
            $viewMatches,
            $triggerMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $taggedCurrent = self::tagRowsCurrentCloseReceipt($currentRows, 'current-close', true, $required, $closeToken, $sourceGeneration, $viewCookie, $triggerCookie, []);
        $taggedNext = self::tagRowsCurrentCloseReceipt($nextRows, 'next-source', $nextVisible, [], $closeToken, $sourceGeneration, $viewCookie, $triggerCookie, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next241' => self::statusCurrentCloseReceipt($nextVisible, $baseVisible, $tokenMatches, $sourceMatches, $viewMatches, $triggerMatches, $missing, $unexpected, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next241' => $baseVisible,
            'current_source_upsert_close_token_next241' => $closeToken,
            'expected_current_source_upsert_close_token_next241' => $expectedCloseToken,
            'current_source_upsert_close_token_matches_next241' => $tokenMatches,
            'current_source_upsert_generation_next241' => $sourceGeneration,
            'expected_current_source_upsert_generation_next241' => $expectedSourceGeneration,
            'current_source_upsert_generation_matches_next241' => $sourceMatches,
            'current_upsert_close_view_cookie_next241' => $viewCookie,
            'expected_current_upsert_close_view_cookie_next241' => $expectedViewCookie,
            'current_upsert_close_view_cookie_matches_next241' => $viewMatches,
            'current_upsert_close_trigger_cookie_next241' => $triggerCookie,
            'expected_current_upsert_close_trigger_cookie_next241' => $expectedTriggerCookie,
            'current_upsert_close_trigger_cookie_matches_next241' => $triggerMatches,
            'required_current_source_upsert_close_receipts_next241' => $required,
            'acknowledged_current_source_upsert_close_receipts_next241' => $acknowledged,
            'missing_current_source_upsert_close_receipts_next241' => $missing,
            'unexpected_current_source_upsert_close_receipts_next241' => $unexpected,
            'require_current_source_upsert_close_order_next241' => $requireOrder,
            'current_source_upsert_close_order_matches_next241' => $orderMatches,
            'current_source_upsert_close_complete_next241' => $closeComplete,
            'next_source_visible_after_current_source_upsert_close_next241' => $nextVisible,
            'current_source_rows_next241' => $taggedCurrent,
            'attempted_next_source_rows_next241' => $taggedNext,
            'visible_returning_rows_next241' => $visibleRows,
            'held_next_source_rows_next241' => $heldRows,
            'visible_returning_payloads_next241' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next241' => array_column($heldRows, 'returning'),
            'current_source_row_count_next241' => count($taggedCurrent),
            'attempted_next_source_row_count_next241' => count($taggedNext),
            'visible_row_count_next241' => count($visibleRows),
            'held_next_row_count_next241' => count($heldRows),
            'blocked_reasons_next241' => $blockedReasons,
            'current_source_upsert_close_plan_next241' => [
                'base_next_source_visible' => $baseVisible,
                'close_token_matches' => $tokenMatches,
                'source_generation_matches' => $sourceMatches,
                'view_cookie_matches' => $viewMatches,
                'trigger_cookie_matches' => $triggerMatches,
                'required_close_receipts' => $required,
                'acknowledged_close_receipts' => $acknowledged,
                'missing_close_receipts' => $missing,
                'unexpected_close_receipts' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'close_complete' => $closeComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert-close'
                    : 'hold-next-source-until-current-recursive-view-upsert-close',
            ],
            'yield_boundary_next241' => $nextVisible
                ? 'recursive-view-upsert-next241-current-close-then-next'
                : 'recursive-view-upsert-next241-current-close-fence-next',
            'dependency_closure_next241' => 'no-new-support-component-reuses-native-recursive-view-upsert-action-receipts-and-adds-current-source-close-seals',
            'dependencies_next241' => array_values(array_unique(array_merge($base['dependencies_next237'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next241',
                'sqlite-instead-of-view-trigger-current-source-upsert-close-seals',
                'wordpress-recursive-view-upsert-current-source-next241',
            ]))),
            'non_overlap_next241' => 'adds current-source close seals after accepted next237 recursive view UPSERT action receipts; avoids next237 action receipt duplication, recursive view RETURNING, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function closeReceiptsCurrentCloseReceipt(array $rows, string $closeToken, string $sourceGeneration, string $viewCookie, string $triggerCookie): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $receipts[] = substr(hash('sha256', implode('|', [
                $closeToken,
                $sourceGeneration,
                $viewCookie,
                $triggerCookie,
                (string) ($row['current_source_upsert_action_receipt_next237'] ?? ''),
                (string) $index,
                (string) ($returning['name'] ?? ''),
                (string) ($returning['event_name'] ?? $returning['event'] ?? ''),
                (string) ($returning['depth_value'] ?? ''),
                (string) ($row['current_source_upsert_action_next237'] ?? ''),
            ])), 0, 52);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsCurrentCloseReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_upsert_closes_next241'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentCloseReceipt($options['acknowledged_current_source_upsert_close_receipts_next241'] ?? [], 'acknowledged current source upsert close receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListCurrentCloseReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next241 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{52}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next241 {$label} contain a malformed close receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentCloseReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next241 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next241 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentCloseReceipt(array $rows, string $phase, bool $visible, array $receipts, string $token, string $sourceGeneration, string $viewCookie, string $triggerCookie, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_close_phase_next241' => $phase,
                'current_source_upsert_close_token_next241' => $token,
                'current_source_upsert_generation_next241' => $sourceGeneration,
                'current_upsert_close_view_cookie_next241' => $viewCookie,
                'current_upsert_close_trigger_cookie_next241' => $triggerCookie,
                'current_source_upsert_close_receipt_next241' => $receipts[$index] ?? null,
                'visible_after_current_source_upsert_close_next241' => $visible,
                'held_by_current_source_upsert_close_reasons_next241' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsCurrentCloseReceipt(mixed $baseReasons, bool $baseVisible, bool $tokenMatches, bool $sourceMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next241 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next237-current-source-upsert-actions-not-published';
        }
        if (!$tokenMatches) {
            $reasons[] = 'current-source-upsert-close-token-mismatch';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-source-upsert-generation-mismatch';
        }
        if (!$viewMatches) {
            $reasons[] = 'current-source-upsert-close-view-cookie-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-source-upsert-close-trigger-cookie-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-upsert-close-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-upsert-close-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-upsert-close-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusCurrentCloseReceipt(bool $nextVisible, bool $baseVisible, bool $tokenMatches, bool $sourceMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next241-close-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next241-base-held';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-upsert-current-source-next241-close-token-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-upsert-current-source-next241-source-generation-held';
        }
        if (!$viewMatches) {
            return 'trigger-recursive-view-upsert-current-source-next241-view-cookie-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-upsert-current-source-next241-trigger-cookie-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next241-close-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next241-close-held';
    }

    private static function tokenCurrentCloseReceipt(string $token, string $label): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]{3,180}$/', $token)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next241 invalid {$label}");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentStatementEpochReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentTargetReceipt(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_next239'] ?? false);
        $currentRows = self::rowsCurrentStatementEpochReceipt($base['current_source_rows_next239'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentStatementEpochReceipt($base['attempted_next_source_rows_next239'] ?? [], 'attempted next source rows');
        $statementEpoch = self::tokenCurrentStatementEpochReceipt((string) ($options['current_source_statement_epoch_next242'] ?? 'wp.current.source.statement.epoch.242'), 'statement epoch');
        $expectedStatementEpoch = self::tokenCurrentStatementEpochReceipt((string) ($options['expected_current_source_statement_epoch_next242'] ?? $statementEpoch), 'expected statement epoch');
        $viewProgram = self::tokenCurrentStatementEpochReceipt((string) ($options['current_source_view_program_next242'] ?? ($currentView['source'] ?? 'main@view-cookie-242-current')), 'view program');
        $triggerProgram = self::tokenCurrentStatementEpochReceipt((string) ($options['current_source_trigger_program_next242'] ?? ($currentView['trigger_source'] ?? 'main@trigger-cookie-242-current')), 'trigger program');
        $schemaCookie = self::tokenCurrentStatementEpochReceipt((string) ($options['current_source_schema_cookie_next242'] ?? 'main.schema.cookie.242'), 'schema cookie');
        $sqlHash = self::tokenCurrentStatementEpochReceipt((string) ($options['current_source_upsert_sql_hash_next242'] ?? 'insert-into-recursive-view-upsert-242'), 'UPSERT SQL hash');
        $requireOrder = (bool) ($options['require_current_source_statement_epoch_order_next242'] ?? true);

        $epochMatches = hash_equals($statementEpoch, $expectedStatementEpoch);
        $requiredEpochs = self::statementReceiptsCurrentStatementEpochReceipt($currentRows, $statementEpoch, $viewProgram, $triggerProgram, $schemaCookie, $sqlHash);
        $acknowledgedEpochs = self::acknowledgedReceiptsCurrentStatementEpochReceipt($options, $requiredEpochs);
        $missingEpochs = array_values(array_diff($requiredEpochs, $acknowledgedEpochs));
        $unexpectedEpochs = array_values(array_diff($acknowledgedEpochs, $requiredEpochs));
        $orderMatches = !$requireOrder || $requiredEpochs === $acknowledgedEpochs;
        $statementComplete = $requiredEpochs !== []
            && $epochMatches
            && $missingEpochs === []
            && $unexpectedEpochs === []
            && $orderMatches;
        $nextVisible = $baseVisible && $statementComplete;
        $blockedReasons = self::blockedReasonsCurrentStatementEpochReceipt(
            $base['blocked_reasons_next239'] ?? [],
            $baseVisible,
            $epochMatches,
            $missingEpochs,
            $unexpectedEpochs,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRowsCurrentStatementEpochReceipt($currentRows, 'current-statement', true, $requiredEpochs, $statementEpoch, $viewProgram, $triggerProgram, $schemaCookie, $sqlHash, []);
        $nextRows = self::tagRowsCurrentStatementEpochReceipt($nextRows, 'next-source', $nextVisible, [], $statementEpoch, $viewProgram, $triggerProgram, $schemaCookie, $sqlHash, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($currentRows, $nextRows) : $currentRows;
        $heldRows = $nextVisible ? [] : $nextRows;

        return [
            'status_next242' => self::statusCurrentStatementEpochReceipt($baseVisible, $epochMatches, $missingEpochs, $unexpectedEpochs, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next242' => $baseVisible,
            'current_source_statement_epoch_next242' => $statementEpoch,
            'expected_current_source_statement_epoch_next242' => $expectedStatementEpoch,
            'current_source_statement_epoch_matches_next242' => $epochMatches,
            'current_source_view_program_next242' => $viewProgram,
            'current_source_trigger_program_next242' => $triggerProgram,
            'current_source_schema_cookie_next242' => $schemaCookie,
            'current_source_upsert_sql_hash_next242' => $sqlHash,
            'required_current_source_statement_receipts_next242' => $requiredEpochs,
            'acknowledged_current_source_statement_receipts_next242' => $acknowledgedEpochs,
            'missing_current_source_statement_receipts_next242' => $missingEpochs,
            'unexpected_current_source_statement_receipts_next242' => $unexpectedEpochs,
            'require_current_source_statement_epoch_order_next242' => $requireOrder,
            'current_source_statement_epoch_order_matches_next242' => $orderMatches,
            'current_source_statement_epoch_complete_next242' => $statementComplete,
            'next_source_visible_after_current_source_statement_epoch_next242' => $nextVisible,
            'current_source_rows_next242' => $currentRows,
            'attempted_next_source_rows_next242' => $nextRows,
            'visible_returning_rows_next242' => $visibleRows,
            'held_next_source_rows_next242' => $heldRows,
            'visible_returning_payloads_next242' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next242' => array_column($heldRows, 'returning'),
            'current_source_row_count_next242' => count($currentRows),
            'attempted_next_source_row_count_next242' => count($nextRows),
            'visible_row_count_next242' => count($visibleRows),
            'held_next_row_count_next242' => count($heldRows),
            'blocked_reasons_next242' => $blockedReasons,
            'current_source_statement_epoch_plan_next242' => [
                'base_next_source_visible' => $baseVisible,
                'statement_epoch_matches' => $epochMatches,
                'view_program' => $viewProgram,
                'trigger_program' => $triggerProgram,
                'schema_cookie' => $schemaCookie,
                'sql_hash' => $sqlHash,
                'required_receipts' => $requiredEpochs,
                'acknowledged_receipts' => $acknowledgedEpochs,
                'missing_receipts' => $missingEpochs,
                'unexpected_receipts' => $unexpectedEpochs,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'statement_complete' => $statementComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-upsert-statement-epoch'
                    : 'hold-next-source-until-current-upsert-statement-epoch',
            ],
            'yield_boundary_next242' => $nextVisible
                ? 'recursive-view-upsert-next242-current-statement-epoch-then-next'
                : 'recursive-view-upsert-next242-current-statement-epoch-fence-next',
            'dependency_closure_next242' => 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-target-receipts-and-adds-statement-epoch-fencing',
            'dependencies_next242' => array_values(array_unique(array_merge($base['dependencies_next239'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next242',
                'sqlite-instead-of-view-upsert-current-statement-epoch',
                'wordpress-recursive-view-upsert-current-source-next242',
            ]))),
            'non_overlap_next242' => 'adds current-source statement-epoch fencing after accepted next239 UPSERT target receipts; avoids accepted next238/next239 recursive-view UPSERT yield and target receipt behavior, recursive view RETURNING, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function statementReceiptsCurrentStatementEpochReceipt(array $rows, string $epoch, string $viewProgram, string $triggerProgram, string $schemaCookie, string $sqlHash): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $receipts[] = substr(hash('sha256', implode('|', [
                $epoch,
                $viewProgram,
                $triggerProgram,
                $schemaCookie,
                $sqlHash,
                (string) ($row['current_source_upsert_target_receipt_next239'] ?? ''),
                (string) ($returning['name'] ?? ''),
                (string) ($returning['value'] ?? ''),
                (string) ($returning['event_name'] ?? ''),
                (string) ($returning['ordinal_value'] ?? $index),
                (string) ($returning['trigger_source_alias'] ?? ''),
            ])), 0, 44);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsCurrentStatementEpochReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_statement_epochs_next242'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentStatementEpochReceipt($options['acknowledged_current_source_statement_receipts_next242'] ?? [], 'acknowledged current source statement receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListCurrentStatementEpochReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next242 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{44}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next242 {$label} contain a malformed statement receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentStatementEpochReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next242 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next242 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentStatementEpochReceipt(
        array $rows,
        string $phase,
        bool $visible,
        array $receipts,
        string $epoch,
        string $viewProgram,
        string $triggerProgram,
        string $schemaCookie,
        string $sqlHash,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'statement_epoch_phase_next242' => $phase,
                'current_source_statement_epoch_next242' => $epoch,
                'current_source_view_program_next242' => $viewProgram,
                'current_source_trigger_program_next242' => $triggerProgram,
                'current_source_schema_cookie_next242' => $schemaCookie,
                'current_source_upsert_sql_hash_next242' => $sqlHash,
                'current_source_statement_receipt_next242' => $receipts[$index] ?? null,
                'visible_after_current_source_statement_epoch_next242' => $visible,
                'held_by_current_source_statement_epoch_reasons_next242' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsCurrentStatementEpochReceipt(
        mixed $baseReasons,
        bool $baseVisible,
        bool $epochMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next242 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next239-current-source-upsert-targets-not-published';
        }
        if (!$epochMatches) {
            $reasons[] = 'current-source-statement-epoch-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-statement-receipt-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-statement-receipt-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-statement-receipt-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusCurrentStatementEpochReceipt(
        bool $baseVisible,
        bool $epochMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
        bool $nextVisible,
    ): string {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next242-statement-epoch-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next242-base-held';
        }
        if (!$epochMatches) {
            return 'trigger-recursive-view-upsert-current-source-next242-statement-epoch-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next242-statement-receipts-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next242-statement-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next242-empty-held';
    }

    private static function tokenCurrentStatementEpochReceipt(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next242 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentSourceReady(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentCompositeKeyReceipt(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentCookie = self::tokenCurrentSourceReady((string) ($options['current_source_view_cookie_next243'] ?? ($currentView['source'] ?? 'main@view-cookie-243-current')), 'current source view cookie');
        $expectedCurrentCookie = self::tokenCurrentSourceReady((string) ($options['expected_current_source_view_cookie_next243'] ?? $currentCookie), 'expected current source view cookie');
        $currentTrigger = self::tokenCurrentSourceReady((string) ($options['current_source_trigger_cookie_next243'] ?? ($currentView['trigger_source'] ?? 'main@trigger-cookie-243-current')), 'current source trigger cookie');
        $expectedCurrentTrigger = self::tokenCurrentSourceReady((string) ($options['expected_current_source_trigger_cookie_next243'] ?? $currentTrigger), 'expected current source trigger cookie');
        $nextCookie = self::tokenCurrentSourceReady((string) ($options['next_source_view_cookie_next243'] ?? ($nextView['source'] ?? 'main@view-cookie-243-next')), 'next source view cookie');
        $cursor = self::tokenCurrentSourceReady((string) ($options['upsert_source_cursor_next243'] ?? 'wp.upsert.source.cursor.243'), 'upsert source cursor');
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_next240'] ?? false);
        $viewMatches = hash_equals($currentCookie, $expectedCurrentCookie);
        $triggerMatches = hash_equals($currentTrigger, $expectedCurrentTrigger);
        $sourceCurrent = $viewMatches && $triggerMatches;
        $nextVisible = $baseVisible && $sourceCurrent;
        $reasons = self::blockedReasonsCurrentSourceReady($base['blocked_reasons_next240'] ?? [], $baseVisible, $viewMatches, $triggerMatches);
        $currentRows = self::tagRowsCurrentSourceReady(self::rowsCurrentSourceReady($base['current_source_rows_next240'] ?? [], 'current rows'), 'current', true, $cursor, $currentCookie, $currentTrigger, $nextCookie, []);
        $nextRows = self::tagRowsCurrentSourceReady(self::rowsCurrentSourceReady($base['attempted_next_source_rows_next240'] ?? [], 'next rows'), 'next', $nextVisible, $cursor, $currentCookie, $currentTrigger, $nextCookie, $nextVisible ? [] : $reasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_upsert_current_source_next243'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_upsert_current_source_next243'],
        ));

        return [
            'status_next243' => self::statusCurrentSourceReady($baseVisible, $viewMatches, $triggerMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next243' => $baseVisible,
            'current_source_view_cookie_next243' => $currentCookie,
            'expected_current_source_view_cookie_next243' => $expectedCurrentCookie,
            'current_source_view_cookie_matches_next243' => $viewMatches,
            'current_source_trigger_cookie_next243' => $currentTrigger,
            'expected_current_source_trigger_cookie_next243' => $expectedCurrentTrigger,
            'current_source_trigger_cookie_matches_next243' => $triggerMatches,
            'next_source_view_cookie_next243' => $nextCookie,
            'upsert_source_cursor_next243' => $cursor,
            'current_source_still_current_next243' => $sourceCurrent,
            'next_source_visible_after_upsert_current_source_next243' => $nextVisible,
            'current_source_rows_next243' => $currentRows,
            'attempted_next_source_rows_next243' => $nextRows,
            'visible_returning_rows_next243' => $visibleRows,
            'held_next_source_rows_next243' => $heldRows,
            'visible_returning_payloads_next243' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next243' => array_column($heldRows, 'returning'),
            'current_source_row_count_next243' => count($currentRows),
            'attempted_next_source_row_count_next243' => count($nextRows),
            'visible_row_count_next243' => count($visibleRows),
            'held_next_row_count_next243' => count($heldRows),
            'blocked_reasons_next243' => $reasons,
            'upsert_current_source_plan_next243' => [
                'base_next_source_visible' => $baseVisible,
                'current_view_cookie_matches' => $viewMatches,
                'current_trigger_cookie_matches' => $triggerMatches,
                'current_source_still_current' => $sourceCurrent,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-view-upsert-source-match'
                    : 'hold-next-source-until-current-view-upsert-source-match',
            ],
            'yield_boundary_next243' => $nextVisible
                ? 'recursive-view-upsert-next243-current-source-then-next'
                : 'recursive-view-upsert-next243-current-source-fences-next',
            'dependency_closure_next243' => 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-cookies',
            'dependencies_next243' => array_values(array_unique(array_merge($base['dependencies_next240'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next243',
                'sqlite-instead-of-view-upsert-current-source-cookie-fence',
                'wordpress-recursive-view-upsert-current-source-next243',
            ]))),
            'non_overlap_next243' => 'adds current view/trigger source-cookie fencing after accepted next240 UPSERT conflict receipts; avoids accepted next240 receipt admission, trigger RETURNING, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentSourceReady(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next243 {$label} must be a list");
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentSourceReady(array $rows, string $phase, bool $visible, string $cursor, string $viewCookie, string $triggerCookie, string $nextCookie, array $reasons): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'upsert_source_phase_next243' => $phase,
                'upsert_source_cursor_next243' => $cursor,
                'current_source_view_cookie_next243' => $viewCookie,
                'current_source_trigger_cookie_next243' => $triggerCookie,
                'next_source_view_cookie_next243' => $nextCookie,
                'visible_after_upsert_current_source_next243' => $visible,
                'held_by_upsert_current_source_reasons_next243' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @return list<string>
     */
    private static function blockedReasonsCurrentSourceReady(mixed $baseReasons, bool $baseVisible, bool $viewMatches, bool $triggerMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next243 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next240-current-source-upsert-not-published';
        }
        if (!$viewMatches) {
            $reasons[] = 'current-view-source-cookie-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-trigger-source-cookie-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function statusCurrentSourceReady(bool $baseVisible, bool $viewMatches, bool $triggerMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next243-source-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next243-base-held';
        }
        if (!$viewMatches) {
            return 'trigger-recursive-view-upsert-current-source-next243-view-source-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-upsert-current-source-next243-trigger-source-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next243-source-held';
    }

    private static function tokenCurrentSourceReady(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next243 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentCommitReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentCloseReceipt(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_close_next241'] ?? false);
        $currentRows = self::rowsCurrentCommitReceipt($base['current_source_rows_next241'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentCommitReceipt($base['attempted_next_source_rows_next241'] ?? [], 'attempted next source rows');
        $statementId = self::tokenCurrentCommitReceipt((string) ($options['current_source_upsert_statement_id_next244'] ?? 'wp.current.source.upsert.statement.244'), 'statement id');
        $expectedStatementId = self::tokenCurrentCommitReceipt((string) ($options['expected_current_source_upsert_statement_id_next244'] ?? $statementId), 'expected statement id');
        $watermark = self::tokenCurrentCommitReceipt((string) ($options['current_source_upsert_commit_watermark_next244'] ?? 'wp.current.source.upsert.commit.244'), 'commit watermark');
        $expectedWatermark = self::tokenCurrentCommitReceipt((string) ($options['expected_current_source_upsert_commit_watermark_next244'] ?? $watermark), 'expected commit watermark');
        $viewCookie = self::tokenCurrentCommitReceipt((string) ($options['current_upsert_commit_view_cookie_next244'] ?? ($base['current_upsert_close_view_cookie_next241'] ?? 'main@view-cookie-244-current')), 'view cookie');
        $expectedViewCookie = self::tokenCurrentCommitReceipt((string) ($options['expected_current_upsert_commit_view_cookie_next244'] ?? $viewCookie), 'expected view cookie');
        $triggerCookie = self::tokenCurrentCommitReceipt((string) ($options['current_upsert_commit_trigger_cookie_next244'] ?? ($base['current_upsert_close_trigger_cookie_next241'] ?? 'main@trigger-cookie-244-current')), 'trigger cookie');
        $expectedTriggerCookie = self::tokenCurrentCommitReceipt((string) ($options['expected_current_upsert_commit_trigger_cookie_next244'] ?? $triggerCookie), 'expected trigger cookie');
        $requireOrder = (bool) ($options['require_current_source_upsert_commit_order_next244'] ?? true);

        $required = self::commitReceiptsCurrentCommitReceipt($currentRows, $statementId, $watermark, $viewCookie, $triggerCookie);
        $acknowledged = self::acknowledgedReceiptsCurrentCommitReceipt($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $orderMatches = !$requireOrder || $required === $acknowledged;
        $statementMatches = hash_equals($statementId, $expectedStatementId);
        $watermarkMatches = hash_equals($watermark, $expectedWatermark);
        $viewMatches = hash_equals($viewCookie, $expectedViewCookie);
        $triggerMatches = hash_equals($triggerCookie, $expectedTriggerCookie);
        $commitComplete = $required !== []
            && $statementMatches
            && $watermarkMatches
            && $viewMatches
            && $triggerMatches
            && $missing === []
            && $unexpected === []
            && $orderMatches;
        $nextVisible = $baseVisible && $commitComplete;
        $blockedReasons = self::blockedReasonsCurrentCommitReceipt(
            $base['blocked_reasons_next241'] ?? [],
            $baseVisible,
            $statementMatches,
            $watermarkMatches,
            $viewMatches,
            $triggerMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $taggedCurrent = self::tagRowsCurrentCommitReceipt($currentRows, 'current-commit', true, $required, $statementId, $watermark, $viewCookie, $triggerCookie, []);
        $taggedNext = self::tagRowsCurrentCommitReceipt($nextRows, 'next-source', $nextVisible, [], $statementId, $watermark, $viewCookie, $triggerCookie, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next244' => self::statusCurrentCommitReceipt($nextVisible, $baseVisible, $statementMatches, $watermarkMatches, $viewMatches, $triggerMatches, $missing, $unexpected, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next244' => $baseVisible,
            'current_source_upsert_statement_id_next244' => $statementId,
            'expected_current_source_upsert_statement_id_next244' => $expectedStatementId,
            'current_source_upsert_statement_id_matches_next244' => $statementMatches,
            'current_source_upsert_commit_watermark_next244' => $watermark,
            'expected_current_source_upsert_commit_watermark_next244' => $expectedWatermark,
            'current_source_upsert_commit_watermark_matches_next244' => $watermarkMatches,
            'current_upsert_commit_view_cookie_next244' => $viewCookie,
            'expected_current_upsert_commit_view_cookie_next244' => $expectedViewCookie,
            'current_upsert_commit_view_cookie_matches_next244' => $viewMatches,
            'current_upsert_commit_trigger_cookie_next244' => $triggerCookie,
            'expected_current_upsert_commit_trigger_cookie_next244' => $expectedTriggerCookie,
            'current_upsert_commit_trigger_cookie_matches_next244' => $triggerMatches,
            'required_current_source_upsert_commit_receipts_next244' => $required,
            'acknowledged_current_source_upsert_commit_receipts_next244' => $acknowledged,
            'missing_current_source_upsert_commit_receipts_next244' => $missing,
            'unexpected_current_source_upsert_commit_receipts_next244' => $unexpected,
            'require_current_source_upsert_commit_order_next244' => $requireOrder,
            'current_source_upsert_commit_order_matches_next244' => $orderMatches,
            'current_source_upsert_commit_complete_next244' => $commitComplete,
            'next_source_visible_after_current_source_upsert_commit_next244' => $nextVisible,
            'current_source_rows_next244' => $taggedCurrent,
            'attempted_next_source_rows_next244' => $taggedNext,
            'visible_returning_rows_next244' => $visibleRows,
            'held_next_source_rows_next244' => $heldRows,
            'visible_returning_payloads_next244' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next244' => array_column($heldRows, 'returning'),
            'current_source_row_count_next244' => count($taggedCurrent),
            'attempted_next_source_row_count_next244' => count($taggedNext),
            'visible_row_count_next244' => count($visibleRows),
            'held_next_row_count_next244' => count($heldRows),
            'blocked_reasons_next244' => $blockedReasons,
            'current_source_upsert_commit_plan_next244' => [
                'base_next_source_visible' => $baseVisible,
                'statement_id_matches' => $statementMatches,
                'commit_watermark_matches' => $watermarkMatches,
                'view_cookie_matches' => $viewMatches,
                'trigger_cookie_matches' => $triggerMatches,
                'required_commit_receipts' => $required,
                'acknowledged_commit_receipts' => $acknowledged,
                'missing_commit_receipts' => $missing,
                'unexpected_commit_receipts' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'commit_complete' => $commitComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert-commit-watermark'
                    : 'hold-next-source-until-current-recursive-view-upsert-commit-watermark',
            ],
            'yield_boundary_next244' => $nextVisible
                ? 'recursive-view-upsert-next244-current-commit-then-next'
                : 'recursive-view-upsert-next244-current-commit-fence-next',
            'dependency_closure_next244' => 'no-new-support-component-reuses-native-recursive-view-upsert-close-seals-and-adds-statement-commit-watermarks',
            'dependencies_next244' => array_values(array_unique(array_merge($base['dependencies_next241'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next244',
                'sqlite-instead-of-view-trigger-upsert-statement-commit-watermark',
                'wordpress-recursive-view-upsert-current-source-next244',
            ]))),
            'non_overlap_next244' => 'adds statement-level UPSERT commit watermark admission after accepted next241 current-source close seals; avoids next241 close-seal duplication, recursive view RETURNING cursor/ticket/generation surfaces, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function commitReceiptsCurrentCommitReceipt(array $rows, string $statementId, string $watermark, string $viewCookie, string $triggerCookie): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $receipts[] = substr(hash('sha256', implode('|', [
                $statementId,
                $watermark,
                $viewCookie,
                $triggerCookie,
                (string) ($row['current_source_upsert_close_receipt_next241'] ?? ''),
                (string) $index,
                (string) ($returning['name'] ?? ''),
                (string) ($returning['event_name'] ?? $returning['event'] ?? ''),
                (string) ($returning['depth_value'] ?? ''),
            ])), 0, 56);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsCurrentCommitReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_upsert_commits_next244'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentCommitReceipt($options['acknowledged_current_source_upsert_commit_receipts_next244'] ?? [], 'acknowledged current source upsert commit receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListCurrentCommitReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next244 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{56}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next244 {$label} contain a malformed commit receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentCommitReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next244 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next244 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentCommitReceipt(array $rows, string $phase, bool $visible, array $receipts, string $statementId, string $watermark, string $viewCookie, string $triggerCookie, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_commit_phase_next244' => $phase,
                'current_source_upsert_statement_id_next244' => $statementId,
                'current_source_upsert_commit_watermark_next244' => $watermark,
                'current_upsert_commit_view_cookie_next244' => $viewCookie,
                'current_upsert_commit_trigger_cookie_next244' => $triggerCookie,
                'current_source_upsert_commit_receipt_next244' => $receipts[$index] ?? null,
                'visible_after_current_source_upsert_commit_next244' => $visible,
                'held_by_current_source_upsert_commit_reasons_next244' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsCurrentCommitReceipt(mixed $baseReasons, bool $baseVisible, bool $statementMatches, bool $watermarkMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next244 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next241-current-source-upsert-close-not-published';
        }
        if (!$statementMatches) {
            $reasons[] = 'current-source-upsert-statement-id-mismatch';
        }
        if (!$watermarkMatches) {
            $reasons[] = 'current-source-upsert-commit-watermark-mismatch';
        }
        if (!$viewMatches) {
            $reasons[] = 'current-source-upsert-commit-view-cookie-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-source-upsert-commit-trigger-cookie-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-upsert-commit-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-upsert-commit-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-upsert-commit-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusCurrentCommitReceipt(bool $nextVisible, bool $baseVisible, bool $statementMatches, bool $watermarkMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next244-commit-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next244-base-held';
        }
        if (!$statementMatches) {
            return 'trigger-recursive-view-upsert-current-source-next244-statement-held';
        }
        if (!$watermarkMatches) {
            return 'trigger-recursive-view-upsert-current-source-next244-watermark-held';
        }
        if (!$viewMatches) {
            return 'trigger-recursive-view-upsert-current-source-next244-view-cookie-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-upsert-current-source-next244-trigger-cookie-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next244-commit-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next244-commit-held';
    }

    private static function tokenCurrentCommitReceipt(string $token, string $label): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]{3,180}$/', $token)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next244 invalid {$label}");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentTargetReasonReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentCloseReceipt(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_close_next241'] ?? false);
        $currentRows = self::rowsCurrentTargetReasonReceipt($base['current_source_rows_next241'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentTargetReasonReceipt($base['attempted_next_source_rows_next241'] ?? [], 'attempted next source rows');
        $target = self::identifierListCurrentTargetReasonReceipt($options['current_source_upsert_conflict_target_next245'] ?? ['option_name'], 'conflict target');
        $expectedTarget = self::identifierListCurrentTargetReasonReceipt($options['expected_current_source_upsert_conflict_target_next245'] ?? $target, 'expected conflict target');
        $excludedColumns = self::identifierListCurrentTargetReasonReceipt($options['current_source_upsert_excluded_columns_next245'] ?? ['option_value', 'autoload'], 'excluded columns');
        $expectedExcludedColumns = self::identifierListCurrentTargetReasonReceipt($options['expected_current_source_upsert_excluded_columns_next245'] ?? $excludedColumns, 'expected excluded columns');
        $sourceToken = self::tokenCurrentTargetReasonReceipt((string) ($options['current_source_upsert_target_token_next245'] ?? 'wp.current.source.upsert.target.245'), 'target token');
        $expectedSourceToken = self::tokenCurrentTargetReasonReceipt((string) ($options['expected_current_source_upsert_target_token_next245'] ?? $sourceToken), 'expected target token');
        $viewCookie = self::tokenCurrentTargetReasonReceipt((string) ($options['current_upsert_target_view_cookie_next245'] ?? ($base['current_upsert_close_view_cookie_next241'] ?? 'main@view-cookie-245-current')), 'view cookie');
        $expectedViewCookie = self::tokenCurrentTargetReasonReceipt((string) ($options['expected_current_upsert_target_view_cookie_next245'] ?? $viewCookie), 'expected view cookie');
        $triggerCookie = self::tokenCurrentTargetReasonReceipt((string) ($options['current_upsert_target_trigger_cookie_next245'] ?? ($base['current_upsert_close_trigger_cookie_next241'] ?? 'main@trigger-cookie-245-current')), 'trigger cookie');
        $expectedTriggerCookie = self::tokenCurrentTargetReasonReceipt((string) ($options['expected_current_upsert_target_trigger_cookie_next245'] ?? $triggerCookie), 'expected trigger cookie');
        $requireOrder = (bool) ($options['require_current_source_upsert_target_order_next245'] ?? true);

        $required = self::targetReceiptsCurrentTargetReasonReceipt($currentRows, $sourceToken, $viewCookie, $triggerCookie, $target, $excludedColumns);
        $acknowledged = self::acknowledgedReceiptsCurrentTargetReasonReceipt($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $orderMatches = !$requireOrder || $required === $acknowledged;
        $targetMatches = $target === $expectedTarget;
        $excludedMatches = $excludedColumns === $expectedExcludedColumns;
        $tokenMatches = hash_equals($sourceToken, $expectedSourceToken);
        $viewMatches = hash_equals($viewCookie, $expectedViewCookie);
        $triggerMatches = hash_equals($triggerCookie, $expectedTriggerCookie);
        $targetComplete = $required !== []
            && $targetMatches
            && $excludedMatches
            && $tokenMatches
            && $viewMatches
            && $triggerMatches
            && $missing === []
            && $unexpected === []
            && $orderMatches;
        $nextVisible = $baseVisible && $targetComplete;
        $blockedReasons = self::blockedReasonsCurrentTargetReasonReceipt(
            $base['blocked_reasons_next241'] ?? [],
            $baseVisible,
            $targetMatches,
            $excludedMatches,
            $tokenMatches,
            $viewMatches,
            $triggerMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $taggedCurrent = self::tagRowsCurrentTargetReasonReceipt($currentRows, 'current-upsert-target', true, $required, $sourceToken, $target, $excludedColumns, $viewCookie, $triggerCookie, []);
        $taggedNext = self::tagRowsCurrentTargetReasonReceipt($nextRows, 'next-source', $nextVisible, [], $sourceToken, $target, $excludedColumns, $viewCookie, $triggerCookie, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next245' => self::statusCurrentTargetReasonReceipt($nextVisible, $baseVisible, $targetMatches, $excludedMatches, $tokenMatches, $viewMatches, $triggerMatches, $missing, $unexpected, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next245' => $baseVisible,
            'current_source_upsert_target_token_next245' => $sourceToken,
            'expected_current_source_upsert_target_token_next245' => $expectedSourceToken,
            'current_source_upsert_target_token_matches_next245' => $tokenMatches,
            'current_source_upsert_conflict_target_next245' => $target,
            'expected_current_source_upsert_conflict_target_next245' => $expectedTarget,
            'current_source_upsert_conflict_target_matches_next245' => $targetMatches,
            'current_source_upsert_excluded_columns_next245' => $excludedColumns,
            'expected_current_source_upsert_excluded_columns_next245' => $expectedExcludedColumns,
            'current_source_upsert_excluded_columns_match_next245' => $excludedMatches,
            'current_upsert_target_view_cookie_next245' => $viewCookie,
            'expected_current_upsert_target_view_cookie_next245' => $expectedViewCookie,
            'current_upsert_target_view_cookie_matches_next245' => $viewMatches,
            'current_upsert_target_trigger_cookie_next245' => $triggerCookie,
            'expected_current_upsert_target_trigger_cookie_next245' => $expectedTriggerCookie,
            'current_upsert_target_trigger_cookie_matches_next245' => $triggerMatches,
            'required_current_source_upsert_target_receipts_next245' => $required,
            'acknowledged_current_source_upsert_target_receipts_next245' => $acknowledged,
            'missing_current_source_upsert_target_receipts_next245' => $missing,
            'unexpected_current_source_upsert_target_receipts_next245' => $unexpected,
            'require_current_source_upsert_target_order_next245' => $requireOrder,
            'current_source_upsert_target_order_matches_next245' => $orderMatches,
            'current_source_upsert_target_complete_next245' => $targetComplete,
            'next_source_visible_after_current_source_upsert_target_next245' => $nextVisible,
            'current_source_rows_next245' => $taggedCurrent,
            'attempted_next_source_rows_next245' => $taggedNext,
            'visible_returning_rows_next245' => $visibleRows,
            'held_next_source_rows_next245' => $heldRows,
            'visible_returning_payloads_next245' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next245' => array_column($heldRows, 'returning'),
            'current_source_row_count_next245' => count($taggedCurrent),
            'attempted_next_source_row_count_next245' => count($taggedNext),
            'visible_row_count_next245' => count($visibleRows),
            'held_next_row_count_next245' => count($heldRows),
            'blocked_reasons_next245' => $blockedReasons,
            'current_source_upsert_target_plan_next245' => [
                'base_next_source_visible' => $baseVisible,
                'conflict_target_matches' => $targetMatches,
                'excluded_columns_match' => $excludedMatches,
                'target_token_matches' => $tokenMatches,
                'view_cookie_matches' => $viewMatches,
                'trigger_cookie_matches' => $triggerMatches,
                'required_target_receipts' => $required,
                'acknowledged_target_receipts' => $acknowledged,
                'missing_target_receipts' => $missing,
                'unexpected_target_receipts' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'target_complete' => $targetComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert-target'
                    : 'hold-next-source-until-current-recursive-view-upsert-target',
            ],
            'yield_boundary_next245' => $nextVisible
                ? 'recursive-view-upsert-next245-current-target-then-next'
                : 'recursive-view-upsert-next245-current-target-fence-next',
            'dependency_closure_next245' => 'no-new-support-component-reuses-native-recursive-view-upsert-close-seals-and-adds-conflict-target-receipts',
            'dependencies_next245' => array_values(array_unique(array_merge($base['dependencies_next241'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next245',
                'sqlite-instead-of-view-trigger-current-source-upsert-conflict-target-receipts',
                'wordpress-recursive-view-upsert-current-source-next245',
            ]))),
            'non_overlap_next245' => 'adds current-source UPSERT conflict-target and excluded-row receipt admission after accepted next241 close seals; avoids next241 close-seal duplication, recursive view RETURNING, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $target
     * @param list<string> $excludedColumns
     * @return list<string>
     */
    private static function targetReceiptsCurrentTargetReasonReceipt(array $rows, string $sourceToken, string $viewCookie, string $triggerCookie, array $target, array $excludedColumns): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $receipts[] = substr(hash('sha256', implode('|', [
                $sourceToken,
                $viewCookie,
                $triggerCookie,
                implode(',', $target),
                implode(',', $excludedColumns),
                (string) ($row['current_source_upsert_close_receipt_next241'] ?? ''),
                (string) ($row['current_source_upsert_action_receipt_next237'] ?? ''),
                (string) $index,
                (string) ($returning['name'] ?? ''),
                (string) ($returning['value'] ?? ''),
                (string) ($returning['event_name'] ?? $returning['event'] ?? ''),
                (string) ($returning['depth_value'] ?? ''),
                (string) ($returning['spawn_child'] ?? ''),
            ])), 0, 56);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsCurrentTargetReasonReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_upsert_targets_next245'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentTargetReasonReceipt($options['acknowledged_current_source_upsert_target_receipts_next245'] ?? [], 'acknowledged current source upsert target receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListCurrentTargetReasonReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next245 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{56}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next245 {$label} contain a malformed target receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function identifierListCurrentTargetReasonReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values) || $values === []) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next245 {$label} must be a non-empty list");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next245 {$label} contains an invalid identifier");
            }
            $out[] = $value;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentTargetReasonReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next245 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next245 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $target
     * @param list<string> $excludedColumns
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentTargetReasonReceipt(array $rows, string $phase, bool $visible, array $receipts, string $token, array $target, array $excludedColumns, string $viewCookie, string $triggerCookie, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_target_phase_next245' => $phase,
                'current_source_upsert_target_token_next245' => $token,
                'current_source_upsert_conflict_target_next245' => $target,
                'current_source_upsert_excluded_columns_next245' => $excludedColumns,
                'current_upsert_target_view_cookie_next245' => $viewCookie,
                'current_upsert_target_trigger_cookie_next245' => $triggerCookie,
                'current_source_upsert_target_receipt_next245' => $receipts[$index] ?? null,
                'visible_after_current_source_upsert_target_next245' => $visible,
                'held_by_current_source_upsert_target_reasons_next245' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsCurrentTargetReasonReceipt(
        mixed $baseReasons,
        bool $baseVisible,
        bool $targetMatches,
        bool $excludedMatches,
        bool $tokenMatches,
        bool $viewMatches,
        bool $triggerMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        $reasons = [];
        if (!$baseVisible) {
            $reasons = array_merge($reasons, self::reasonListCurrentTargetReasonReceipt($baseReasons));
        }
        if (!$targetMatches) {
            $reasons[] = 'current-source-upsert-conflict-target-mismatch';
        }
        if (!$excludedMatches) {
            $reasons[] = 'current-source-upsert-excluded-columns-mismatch';
        }
        if (!$tokenMatches) {
            $reasons[] = 'current-source-upsert-target-token-mismatch';
        }
        if (!$viewMatches) {
            $reasons[] = 'current-source-upsert-target-view-cookie-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-source-upsert-target-trigger-cookie-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-upsert-target-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-upsert-target-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-upsert-target-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function reasonListCurrentTargetReasonReceipt(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }
        $out = [];
        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusCurrentTargetReasonReceipt(bool $nextVisible, bool $baseVisible, bool $targetMatches, bool $excludedMatches, bool $tokenMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next245-target-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next245-base-held';
        }
        if (!$targetMatches) {
            return 'trigger-recursive-view-upsert-current-source-next245-conflict-target-held';
        }
        if (!$excludedMatches) {
            return 'trigger-recursive-view-upsert-current-source-next245-excluded-columns-held';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-upsert-current-source-next245-target-token-held';
        }
        if (!$viewMatches) {
            return 'trigger-recursive-view-upsert-current-source-next245-view-cookie-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-upsert-current-source-next245-trigger-cookie-held';
        }
        if ($missing !== []) {
            return 'trigger-recursive-view-upsert-current-source-next245-target-held';
        }
        if ($unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next245-target-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next245-target-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next245-target-held';
    }

    private static function tokenCurrentTargetReasonReceipt(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next245 {$label} cannot be empty");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentConflictImageReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentSourceReady(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_upsert_current_source_next243'] ?? false);
        $currentRows = self::rowsCurrentConflictImageReceipt($base['current_source_rows_next243'] ?? [], 'current rows');
        $nextRows = self::rowsCurrentConflictImageReceipt($base['attempted_next_source_rows_next243'] ?? [], 'next rows');
        $conflictColumns = self::columnsCurrentConflictImageReceipt($options['upsert_conflict_columns_next246'] ?? ['name'], 'conflict columns');
        $excludedColumns = self::columnsCurrentConflictImageReceipt($options['upsert_excluded_columns_next246'] ?? ['value', 'autoload_flag'], 'excluded columns');
        $oldRows = self::oldRowsCurrentConflictImageReceipt($baseRows, (string) ($options['key'] ?? 'option_name'));
        $sourceToken = self::tokenCurrentConflictImageReceipt((string) ($options['current_source_conflict_image_token_next246'] ?? 'wp.current.source.conflict.image.246'), 'conflict image token');
        $expectedSourceToken = self::tokenCurrentConflictImageReceipt((string) ($options['expected_current_source_conflict_image_token_next246'] ?? $sourceToken), 'expected conflict image token');
        $requireOrder = (bool) ($options['require_current_source_conflict_image_order_next246'] ?? true);
        $sourceMatches = hash_equals($sourceToken, $expectedSourceToken);
        $requiredReceipts = self::conflictImageReceiptsCurrentConflictImageReceipt($currentRows, $oldRows, $conflictColumns, $excludedColumns, $sourceToken);
        $acknowledgedReceipts = self::acknowledgedReceiptsCurrentConflictImageReceipt($options, $requiredReceipts);
        $missingReceipts = array_values(array_diff($requiredReceipts, $acknowledgedReceipts));
        $unexpectedReceipts = array_values(array_diff($acknowledgedReceipts, $requiredReceipts));
        $orderMatches = !$requireOrder || $requiredReceipts === $acknowledgedReceipts;
        $conflictImagesComplete = $requiredReceipts !== []
            && $sourceMatches
            && $missingReceipts === []
            && $unexpectedReceipts === []
            && $orderMatches;
        $nextVisible = $baseVisible && $conflictImagesComplete;
        $blockedReasons = self::blockedReasonsCurrentConflictImageReceipt(
            $base['blocked_reasons_next243'] ?? [],
            $baseVisible,
            $sourceMatches,
            $missingReceipts,
            $unexpectedReceipts,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRowsCurrentConflictImageReceipt($currentRows, 'current-conflict-image', true, $requiredReceipts, $sourceToken, $conflictColumns, $excludedColumns, []);
        $nextRows = self::tagRowsCurrentConflictImageReceipt($nextRows, 'next-source', $nextVisible, [], $sourceToken, $conflictColumns, $excludedColumns, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_conflict_image_next246'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_conflict_image_next246'],
        ));

        return [
            'status_next246' => self::statusCurrentConflictImageReceipt($baseVisible, $sourceMatches, $missingReceipts, $unexpectedReceipts, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next246' => $baseVisible,
            'current_source_conflict_image_token_next246' => $sourceToken,
            'expected_current_source_conflict_image_token_next246' => $expectedSourceToken,
            'current_source_conflict_image_token_matches_next246' => $sourceMatches,
            'upsert_conflict_columns_next246' => $conflictColumns,
            'upsert_excluded_columns_next246' => $excludedColumns,
            'required_current_source_conflict_image_receipts_next246' => $requiredReceipts,
            'acknowledged_current_source_conflict_image_receipts_next246' => $acknowledgedReceipts,
            'missing_current_source_conflict_image_receipts_next246' => $missingReceipts,
            'unexpected_current_source_conflict_image_receipts_next246' => $unexpectedReceipts,
            'require_current_source_conflict_image_order_next246' => $requireOrder,
            'current_source_conflict_image_order_matches_next246' => $orderMatches,
            'current_source_conflict_images_complete_next246' => $conflictImagesComplete,
            'next_source_visible_after_current_source_conflict_image_next246' => $nextVisible,
            'current_source_rows_next246' => $currentRows,
            'attempted_next_source_rows_next246' => $nextRows,
            'visible_returning_rows_next246' => $visibleRows,
            'held_next_source_rows_next246' => $heldRows,
            'visible_returning_payloads_next246' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next246' => array_column($heldRows, 'returning'),
            'current_source_row_count_next246' => count($currentRows),
            'attempted_next_source_row_count_next246' => count($nextRows),
            'visible_row_count_next246' => count($visibleRows),
            'held_next_row_count_next246' => count($heldRows),
            'blocked_reasons_next246' => $blockedReasons,
            'current_source_conflict_images_next246' => self::conflictImagesCurrentConflictImageReceipt($currentRows, $oldRows, $conflictColumns, $excludedColumns),
            'current_source_conflict_image_plan_next246' => [
                'base_next_source_visible' => $baseVisible,
                'source_token_matches' => $sourceMatches,
                'conflict_columns' => $conflictColumns,
                'excluded_columns' => $excludedColumns,
                'required_receipts' => $requiredReceipts,
                'acknowledged_receipts' => $acknowledgedReceipts,
                'missing_receipts' => $missingReceipts,
                'unexpected_receipts' => $unexpectedReceipts,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'conflict_images_complete' => $conflictImagesComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-upsert-conflict-images'
                    : 'hold-next-source-until-current-upsert-conflict-images',
            ],
            'yield_boundary_next246' => $nextVisible
                ? 'recursive-view-upsert-next246-current-conflict-images-then-next'
                : 'recursive-view-upsert-next246-current-conflict-images-fence-next',
            'dependency_closure_next246' => 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-cookies-and-adds-conflict-image-receipts',
            'dependencies_next246' => array_values(array_unique(array_merge($base['dependencies_next243'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next246',
                'sqlite-instead-of-view-upsert-conflict-image-receipts',
                'wordpress-recursive-view-upsert-current-source-next246',
            ]))),
            'non_overlap_next246' => 'adds current-source UPSERT old/excluded conflict-image receipt fencing after accepted next243 source-cookie fencing; avoids accepted next240 conflict-key receipts, next242 statement epoch, next243 cookie fence, recursive view RETURNING, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,array<string,mixed>> $oldRows
     * @param list<string> $conflictColumns
     * @param list<string> $excludedColumns
     * @return list<string>
     */
    private static function conflictImageReceiptsCurrentConflictImageReceipt(array $rows, array $oldRows, array $conflictColumns, array $excludedColumns, string $sourceToken): array
    {
        $receipts = [];
        foreach (self::conflictImagesCurrentConflictImageReceipt($rows, $oldRows, $conflictColumns, $excludedColumns) as $image) {
            $receipts[] = substr(hash('sha256', json_encode([$sourceToken, $image], JSON_THROW_ON_ERROR)), 0, 46);
        }

        return $receipts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,array<string,mixed>> $oldRows
     * @param list<string> $conflictColumns
     * @param list<string> $excludedColumns
     * @return list<array<string,mixed>>
     */
    private static function conflictImagesCurrentConflictImageReceipt(array $rows, array $oldRows, array $conflictColumns, array $excludedColumns): array
    {
        $images = [];
        foreach ($rows as $index => $row) {
            $returning = self::returningCurrentConflictImageReceipt($row);
            $key = self::imageKeyCurrentConflictImageReceipt($returning, $conflictColumns);
            $old = $oldRows[$key] ?? null;
            $excluded = [];
            foreach ($excludedColumns as $column) {
                $excluded[$column] = $returning[$column] ?? ($returning[self::inputAliasCurrentConflictImageReceipt($column)] ?? null);
            }
            $images[] = [
                'ordinal' => $index,
                'conflict_key' => $key,
                'conflict_columns' => self::valuesForCurrentConflictImageReceipt($returning, $conflictColumns),
                'old_values' => $old === null ? [] : self::valuesForCurrentConflictImageReceipt($old, array_keys($old)),
                'excluded_values' => $excluded,
                'matched_existing_row' => $old !== null,
                'upsert_action' => $old === null ? 'insert' : 'do-update',
            ];
        }

        return $images;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private static function oldRowsCurrentConflictImageReceipt(array $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                continue;
            }
            $out[self::scalarKeyCurrentConflictImageReceipt($row[$key])] = $row;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $payload
     * @param list<string> $columns
     */
    private static function imageKeyCurrentConflictImageReceipt(array $payload, array $columns): string
    {
        $parts = [];
        foreach ($columns as $column) {
            $value = $payload[$column] ?? ($payload[self::inputAliasCurrentConflictImageReceipt($column)] ?? null);
            $parts[] = self::scalarKeyCurrentConflictImageReceipt($value);
        }

        return implode('|', $parts);
    }

    /**
     * @param array<string,mixed> $payload
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function valuesForCurrentConflictImageReceipt(array $payload, array $columns): array
    {
        $values = [];
        foreach ($columns as $column) {
            $values[$column] = $payload[$column] ?? ($payload[self::inputAliasCurrentConflictImageReceipt($column)] ?? null);
        }

        return $values;
    }

    private static function inputAliasCurrentConflictImageReceipt(string $column): string
    {
        return match ($column) {
            'name' => 'option_name',
            'value' => 'option_value',
            'autoload_flag' => 'autoload',
            default => $column,
        };
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsCurrentConflictImageReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_conflict_images_next246'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentConflictImageReceipt($options['acknowledged_current_source_conflict_image_receipts_next246'] ?? [], 'acknowledged current source conflict image receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListCurrentConflictImageReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next246 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{46}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next246 {$label} contain a malformed conflict image receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentConflictImageReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next246 {$label} must be a list");
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function returningCurrentConflictImageReceipt(array $row): array
    {
        if (!isset($row['returning']) || !is_array($row['returning'])) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next246 row missing RETURNING payload');
        }

        return $row['returning'];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $conflictColumns
     * @param list<string> $excludedColumns
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentConflictImageReceipt(array $rows, string $phase, bool $visible, array $receipts, string $sourceToken, array $conflictColumns, array $excludedColumns, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'conflict_image_phase_next246' => $phase,
                'current_source_conflict_image_token_next246' => $sourceToken,
                'current_source_conflict_image_receipt_next246' => $receipts[$index] ?? null,
                'upsert_conflict_columns_next246' => $conflictColumns,
                'upsert_excluded_columns_next246' => $excludedColumns,
                'visible_after_current_source_conflict_image_next246' => $visible,
                'held_by_current_source_conflict_image_reasons_next246' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $columns
     * @return list<string>
     */
    private static function columnsCurrentConflictImageReceipt(mixed $columns, string $label): array
    {
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next246 {$label} must be a non-empty list");
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '' || preg_match('/\s/', $column) === 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next246 {$label} contain a malformed column");
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missingReceipts
     * @param list<string> $unexpectedReceipts
     * @return list<string>
     */
    private static function blockedReasonsCurrentConflictImageReceipt(mixed $baseReasons, bool $baseVisible, bool $sourceMatches, array $missingReceipts, array $unexpectedReceipts, bool $requireOrder, bool $orderMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next246 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next243-current-source-not-published';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-source-conflict-image-token-mismatch';
        }
        if ($missingReceipts !== []) {
            $reasons[] = 'current-source-conflict-image-receipt-missing';
        }
        if ($unexpectedReceipts !== []) {
            $reasons[] = 'current-source-conflict-image-receipt-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-conflict-image-receipt-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missingReceipts
     * @param list<string> $unexpectedReceipts
     */
    private static function statusCurrentConflictImageReceipt(bool $baseVisible, bool $sourceMatches, array $missingReceipts, array $unexpectedReceipts, bool $requireOrder, bool $orderMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next246-conflict-images-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next246-base-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-upsert-current-source-next246-conflict-image-token-held';
        }
        if ($missingReceipts !== [] || $unexpectedReceipts !== []) {
            return 'trigger-recursive-view-upsert-current-source-next246-conflict-image-receipts-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next246-conflict-image-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next246-conflict-images-held';
    }

    private static function scalarKeyCurrentConflictImageReceipt(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? 'BOOL:1' : 'BOOL:0',
            is_int($value) => 'INT:' . $value,
            is_float($value) => 'FLOAT:' . sprintf('%.17g', $value),
            is_string($value) => 'TEXT:' . $value,
            default => throw new InvalidArgumentException('SQLite recursive view UPSERT next246 conflict value must be scalar'),
        };
    }

    private static function tokenCurrentConflictImageReceipt(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next246 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentSequenceReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentCommitReceipt(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_commit_next244'] ?? false);
        $currentRows = self::rowsCurrentSequenceReceipt($base['current_source_rows_next244'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentSequenceReceipt($base['attempted_next_source_rows_next244'] ?? [], 'attempted next source rows');
        $sequence = self::sequenceCurrentSequenceReceipt($options['current_source_statement_sequence_next247'] ?? 1, 'statement sequence');
        $expectedSequence = self::sequenceCurrentSequenceReceipt($options['expected_current_source_statement_sequence_next247'] ?? $sequence, 'expected statement sequence');
        $nextSequence = self::sequenceCurrentSequenceReceipt($options['next_source_statement_sequence_next247'] ?? ($sequence + 1), 'next source statement sequence');
        $viewCookie = self::tokenCurrentSequenceReceipt((string) ($options['current_source_sequence_view_cookie_next247'] ?? ($base['current_upsert_commit_view_cookie_next244'] ?? ($currentView['source'] ?? 'main@view-cookie-247-current'))), 'view cookie');
        $triggerCookie = self::tokenCurrentSequenceReceipt((string) ($options['current_source_sequence_trigger_cookie_next247'] ?? ($base['current_upsert_commit_trigger_cookie_next244'] ?? ($currentView['trigger_source'] ?? 'main@trigger-cookie-247-current'))), 'trigger cookie');
        $cursor = self::tokenCurrentSequenceReceipt((string) ($options['current_source_sequence_cursor_next247'] ?? 'wp.returning.current.sequence.cursor.247'), 'sequence cursor');
        $requireMonotonic = (bool) ($options['require_monotonic_statement_sequence_next247'] ?? true);

        $sequenceMatches = $sequence === $expectedSequence;
        $nextIsFuture = $nextSequence > $sequence;
        $required = self::sequenceReceiptsCurrentSequenceReceipt($currentRows, $sequence, $viewCookie, $triggerCookie, $cursor);
        $acknowledged = self::acknowledgedReceiptsCurrentSequenceReceipt($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $sequenceComplete = $required !== []
            && $sequenceMatches
            && (!$requireMonotonic || $nextIsFuture)
            && $missing === []
            && $unexpected === [];
        $nextVisible = $baseVisible && $sequenceComplete;
        $blockedReasons = self::blockedReasonsCurrentSequenceReceipt(
            $base['blocked_reasons_next244'] ?? [],
            $baseVisible,
            $sequenceMatches,
            $requireMonotonic,
            $nextIsFuture,
            $missing,
            $unexpected,
        );

        $taggedCurrent = self::tagRowsCurrentSequenceReceipt($currentRows, 'current-sequence', true, $required, $sequence, $nextSequence, $viewCookie, $triggerCookie, $cursor, []);
        $taggedNext = self::tagRowsCurrentSequenceReceipt($nextRows, 'next-source', $nextVisible, [], $sequence, $nextSequence, $viewCookie, $triggerCookie, $cursor, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next247' => self::statusCurrentSequenceReceipt($nextVisible, $baseVisible, $sequenceMatches, $requireMonotonic, $nextIsFuture, $missing, $unexpected),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next247' => $baseVisible,
            'current_source_statement_sequence_next247' => $sequence,
            'expected_current_source_statement_sequence_next247' => $expectedSequence,
            'current_source_statement_sequence_matches_next247' => $sequenceMatches,
            'next_source_statement_sequence_next247' => $nextSequence,
            'require_monotonic_statement_sequence_next247' => $requireMonotonic,
            'next_source_statement_sequence_is_future_next247' => $nextIsFuture,
            'current_source_sequence_view_cookie_next247' => $viewCookie,
            'current_source_sequence_trigger_cookie_next247' => $triggerCookie,
            'current_source_sequence_cursor_next247' => $cursor,
            'required_current_source_sequence_receipts_next247' => $required,
            'acknowledged_current_source_sequence_receipts_next247' => $acknowledged,
            'missing_current_source_sequence_receipts_next247' => $missing,
            'unexpected_current_source_sequence_receipts_next247' => $unexpected,
            'current_source_statement_sequence_complete_next247' => $sequenceComplete,
            'next_source_visible_after_current_source_statement_sequence_next247' => $nextVisible,
            'current_source_rows_next247' => $taggedCurrent,
            'attempted_next_source_rows_next247' => $taggedNext,
            'visible_returning_rows_next247' => $visibleRows,
            'held_next_source_rows_next247' => $heldRows,
            'visible_returning_payloads_next247' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next247' => array_column($heldRows, 'returning'),
            'current_source_row_count_next247' => count($taggedCurrent),
            'attempted_next_source_row_count_next247' => count($taggedNext),
            'visible_row_count_next247' => count($visibleRows),
            'held_next_row_count_next247' => count($heldRows),
            'blocked_reasons_next247' => $blockedReasons,
            'current_source_statement_sequence_plan_next247' => [
                'base_next_source_visible' => $baseVisible,
                'statement_sequence_matches' => $sequenceMatches,
                'next_source_sequence_is_future' => $nextIsFuture,
                'require_monotonic' => $requireMonotonic,
                'required_receipts' => $required,
                'acknowledged_receipts' => $acknowledged,
                'missing_receipts' => $missing,
                'unexpected_receipts' => $unexpected,
                'sequence_complete' => $sequenceComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert-sequence'
                    : 'hold-next-source-until-current-recursive-view-upsert-sequence',
            ],
            'yield_boundary_next247' => $nextVisible
                ? 'recursive-view-upsert-next247-current-sequence-then-next'
                : 'recursive-view-upsert-next247-current-sequence-fence-next',
            'dependency_closure_next247' => 'no-new-support-component-reuses-native-recursive-view-upsert-commit-watermark-and-adds-statement-source-sequence-fencing',
            'dependencies_next247' => array_values(array_unique(array_merge($base['dependencies_next244'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next247',
                'sqlite-instead-of-view-trigger-upsert-statement-source-sequence',
                'wordpress-recursive-view-upsert-current-source-next247',
            ]))),
            'non_overlap_next247' => 'adds statement-source sequence fencing after accepted next244 commit watermark receipts; avoids next244 commit receipt/watermark duplication, next242 statement epoch fencing, next239 target receipts, trigger RETURNING cursor/ticket/generation surfaces, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function sequenceReceiptsCurrentSequenceReceipt(array $rows, int $sequence, string $viewCookie, string $triggerCookie, string $cursor): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $receipts[] = substr(hash('sha256', implode('|', [
                (string) $sequence,
                $viewCookie,
                $triggerCookie,
                $cursor,
                (string) ($row['current_source_upsert_commit_receipt_next244'] ?? ''),
                (string) ($returning['name'] ?? ''),
                (string) ($returning['value'] ?? ''),
                (string) ($returning['event_name'] ?? ''),
                (string) ($returning['ordinal_value'] ?? $index),
            ])), 0, 48);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsCurrentSequenceReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_statement_sequences_next247'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentSequenceReceipt($options['acknowledged_current_source_sequence_receipts_next247'] ?? [], 'acknowledged statement sequence receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListCurrentSequenceReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next247 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{48}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next247 {$label} contain a malformed sequence receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentSequenceReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next247 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next247 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentSequenceReceipt(array $rows, string $phase, bool $visible, array $receipts, int $sequence, int $nextSequence, string $viewCookie, string $triggerCookie, string $cursor, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'statement_sequence_phase_next247' => $phase,
                'current_source_statement_sequence_next247' => $sequence,
                'next_source_statement_sequence_next247' => $nextSequence,
                'current_source_sequence_view_cookie_next247' => $viewCookie,
                'current_source_sequence_trigger_cookie_next247' => $triggerCookie,
                'current_source_sequence_cursor_next247' => $cursor,
                'current_source_sequence_receipt_next247' => $receipts[$index] ?? null,
                'visible_after_current_source_statement_sequence_next247' => $visible,
                'held_by_current_source_statement_sequence_reasons_next247' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsCurrentSequenceReceipt(mixed $baseReasons, bool $baseVisible, bool $sequenceMatches, bool $requireMonotonic, bool $nextIsFuture, array $missing, array $unexpected): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next247 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next244-current-source-upsert-commit-not-published';
        }
        if (!$sequenceMatches) {
            $reasons[] = 'current-source-statement-sequence-mismatch';
        }
        if ($requireMonotonic && !$nextIsFuture) {
            $reasons[] = 'next-source-statement-sequence-not-future';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-statement-sequence-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-statement-sequence-unexpected';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusCurrentSequenceReceipt(bool $nextVisible, bool $baseVisible, bool $sequenceMatches, bool $requireMonotonic, bool $nextIsFuture, array $missing, array $unexpected): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next247-sequence-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next247-base-held';
        }
        if (!$sequenceMatches) {
            return 'trigger-recursive-view-upsert-current-source-next247-sequence-held';
        }
        if ($requireMonotonic && !$nextIsFuture) {
            return 'trigger-recursive-view-upsert-current-source-next247-next-sequence-held';
        }
        if ($missing !== []) {
            return 'trigger-recursive-view-upsert-current-source-next247-sequence-missing-held';
        }
        if ($unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next247-sequence-unexpected-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next247-sequence-held';
    }

    private static function sequenceCurrentSequenceReceipt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next247 {$label} must be a non-negative integer");
        }

        return $value;
    }

    private static function tokenCurrentSequenceReceipt(string $token, string $label): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]{3,180}$/', $token)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next247 invalid {$label}");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentWhereOutcomeReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentTargetReasonReceipt(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_target_next245'] ?? false);
        $currentRows = self::rowsCurrentWhereOutcomeReceipt($base['current_source_rows_next245'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentWhereOutcomeReceipt($base['attempted_next_source_rows_next245'] ?? [], 'attempted next source rows');
        $guardToken = self::tokenCurrentWhereOutcomeReceipt((string) ($options['current_source_upsert_where_token_next248'] ?? 'wp.current.source.upsert.where.248'), 'where token');
        $expectedGuardToken = self::tokenCurrentWhereOutcomeReceipt((string) ($options['expected_current_source_upsert_where_token_next248'] ?? $guardToken), 'expected where token');
        $whereColumns = self::identifierListCurrentWhereOutcomeReceipt($options['current_source_upsert_where_columns_next248'] ?? ['option_value', 'autoload'], 'where columns');
        $expectedWhereColumns = self::identifierListCurrentWhereOutcomeReceipt($options['expected_current_source_upsert_where_columns_next248'] ?? $whereColumns, 'expected where columns');
        $expectedOutcomes = self::boolListCurrentWhereOutcomeReceipt($options['expected_current_source_upsert_where_outcomes_next248'] ?? self::outcomesCurrentWhereOutcomeReceipt($currentRows), 'expected where outcomes');
        $requireOrder = (bool) ($options['require_current_source_upsert_where_order_next248'] ?? true);

        $required = self::whereReceiptsCurrentWhereOutcomeReceipt($currentRows, $guardToken, $whereColumns);
        $acknowledged = self::acknowledgedReceiptsCurrentWhereOutcomeReceipt($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $orderMatches = !$requireOrder || $required === $acknowledged;
        $tokenMatches = hash_equals($guardToken, $expectedGuardToken);
        $columnsMatch = $whereColumns === $expectedWhereColumns;
        $outcomes = self::outcomesCurrentWhereOutcomeReceipt($currentRows);
        $outcomesMatch = $outcomes === $expectedOutcomes;
        $whereComplete = $required !== []
            && $tokenMatches
            && $columnsMatch
            && $outcomesMatch
            && $missing === []
            && $unexpected === []
            && $orderMatches;
        $nextVisible = $baseVisible && $whereComplete;
        $blockedReasons = self::blockedReasonsCurrentWhereOutcomeReceipt(
            $base['blocked_reasons_next245'] ?? [],
            $baseVisible,
            $tokenMatches,
            $columnsMatch,
            $outcomesMatch,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $taggedCurrent = self::tagRowsCurrentWhereOutcomeReceipt($currentRows, 'current-upsert-where', true, $required, $outcomes, $guardToken, $whereColumns, []);
        $taggedNext = self::tagRowsCurrentWhereOutcomeReceipt($nextRows, 'next-source', $nextVisible, [], [], $guardToken, $whereColumns, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next248' => self::statusCurrentWhereOutcomeReceipt($nextVisible, $baseVisible, $tokenMatches, $columnsMatch, $outcomesMatch, $missing, $unexpected, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next248' => $baseVisible,
            'current_source_upsert_where_token_next248' => $guardToken,
            'expected_current_source_upsert_where_token_next248' => $expectedGuardToken,
            'current_source_upsert_where_token_matches_next248' => $tokenMatches,
            'current_source_upsert_where_columns_next248' => $whereColumns,
            'expected_current_source_upsert_where_columns_next248' => $expectedWhereColumns,
            'current_source_upsert_where_columns_match_next248' => $columnsMatch,
            'current_source_upsert_where_outcomes_next248' => $outcomes,
            'expected_current_source_upsert_where_outcomes_next248' => $expectedOutcomes,
            'current_source_upsert_where_outcomes_match_next248' => $outcomesMatch,
            'required_current_source_upsert_where_receipts_next248' => $required,
            'acknowledged_current_source_upsert_where_receipts_next248' => $acknowledged,
            'missing_current_source_upsert_where_receipts_next248' => $missing,
            'unexpected_current_source_upsert_where_receipts_next248' => $unexpected,
            'require_current_source_upsert_where_order_next248' => $requireOrder,
            'current_source_upsert_where_order_matches_next248' => $orderMatches,
            'current_source_upsert_where_complete_next248' => $whereComplete,
            'next_source_visible_after_current_source_upsert_where_next248' => $nextVisible,
            'current_source_rows_next248' => $taggedCurrent,
            'attempted_next_source_rows_next248' => $taggedNext,
            'visible_returning_rows_next248' => $visibleRows,
            'held_next_source_rows_next248' => $heldRows,
            'visible_returning_payloads_next248' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next248' => array_column($heldRows, 'returning'),
            'current_source_row_count_next248' => count($taggedCurrent),
            'attempted_next_source_row_count_next248' => count($taggedNext),
            'visible_row_count_next248' => count($visibleRows),
            'held_next_row_count_next248' => count($heldRows),
            'blocked_reasons_next248' => $blockedReasons,
            'current_source_upsert_where_plan_next248' => [
                'base_next_source_visible' => $baseVisible,
                'where_token_matches' => $tokenMatches,
                'where_columns_match' => $columnsMatch,
                'where_outcomes_match' => $outcomesMatch,
                'required_where_receipts' => $required,
                'acknowledged_where_receipts' => $acknowledged,
                'missing_where_receipts' => $missing,
                'unexpected_where_receipts' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'where_complete' => $whereComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert-where'
                    : 'hold-next-source-until-current-recursive-view-upsert-where',
            ],
            'yield_boundary_next248' => $nextVisible
                ? 'recursive-view-upsert-next248-current-where-then-next'
                : 'recursive-view-upsert-next248-current-where-fence-next',
            'dependency_closure_next248' => 'no-new-support-component-reuses-native-recursive-view-upsert-target-receipts-and-adds-do-update-where-guard-receipts',
            'dependencies_next248' => array_values(array_unique(array_merge($base['dependencies_next245'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next248',
                'sqlite-instead-of-view-trigger-current-source-upsert-where-receipts',
                'wordpress-recursive-view-upsert-current-source-next248',
            ]))),
            'non_overlap_next248' => 'adds current-source UPSERT DO UPDATE WHERE guard receipt admission after accepted next245 conflict-target receipts; avoids next245 target/excluded-column duplication, recursive view RETURNING, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $whereColumns
     * @return list<string>
     */
    private static function whereReceiptsCurrentWhereOutcomeReceipt(array $rows, string $guardToken, array $whereColumns): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $receipts[] = substr(hash('sha256', implode('|', [
                $guardToken,
                implode(',', $whereColumns),
                (string) ($row['current_source_upsert_target_receipt_next245'] ?? ''),
                (string) $index,
                (string) ($returning['name'] ?? ''),
                (string) ($returning['value'] ?? ''),
                (string) ($returning['event_name'] ?? $returning['event'] ?? ''),
                self::boolTextCurrentWhereOutcomeReceipt(self::rowOutcomeCurrentWhereOutcomeReceipt($row)),
            ])), 0, 64);
        }

        return $receipts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<bool>
     */
    private static function outcomesCurrentWhereOutcomeReceipt(array $rows): array
    {
        return array_map(static fn (array $row): bool => self::rowOutcomeCurrentWhereOutcomeReceipt($row), $rows);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowOutcomeCurrentWhereOutcomeReceipt(array $row): bool
    {
        $returning = $row['returning'];

        return (string) ($returning['value'] ?? '') !== ''
            && ($returning['autoload'] ?? $returning['autoload_flag'] ?? 'yes') !== 'skip';
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsCurrentWhereOutcomeReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_upsert_where_next248'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentWhereOutcomeReceipt($options['acknowledged_current_source_upsert_where_receipts_next248'] ?? [], 'acknowledged current source upsert where receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListCurrentWhereOutcomeReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} contain a malformed where receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function identifierListCurrentWhereOutcomeReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values) || $values === []) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} must be a non-empty list");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} contains an invalid identifier");
            }
            $out[] = $value;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param mixed $values
     * @return list<bool>
     */
    private static function boolListCurrentWhereOutcomeReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_bool($value)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} must contain booleans");
            }
        }

        return $values;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentWhereOutcomeReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<bool> $outcomes
     * @param list<string> $whereColumns
     * @param list<string> $blockedReasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentWhereOutcomeReceipt(array $rows, string $phase, bool $visible, array $receipts, array $outcomes, string $guardToken, array $whereColumns, array $blockedReasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_where_phase_next248' => $phase,
                'current_source_upsert_where_token_next248' => $guardToken,
                'current_source_upsert_where_columns_next248' => $whereColumns,
                'current_source_upsert_where_receipt_next248' => $receipts[$index] ?? null,
                'current_source_upsert_where_outcome_next248' => $outcomes[$index] ?? null,
                'visible_after_current_source_upsert_where_next248' => $visible,
                'held_by_current_source_upsert_where_reasons_next248' => $visible ? [] : $blockedReasons,
            ];
        }

        return $out;
    }

    /**
     * @param list<string> $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsCurrentWhereOutcomeReceipt(array $baseReasons, bool $baseVisible, bool $tokenMatches, bool $columnsMatch, bool $outcomesMatch, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        $reasons = [];
        foreach ($baseReasons as $reason) {
            if (is_string($reason) && $reason !== '') {
                $reasons[] = $reason;
            }
        }
        if (!$baseVisible) {
            $reasons[] = 'next245-target-not-visible';
        }
        if (!$tokenMatches) {
            $reasons[] = 'where-token-mismatch';
        }
        if (!$columnsMatch) {
            $reasons[] = 'where-columns-mismatch';
        }
        if (!$outcomesMatch) {
            $reasons[] = 'where-outcomes-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'where-receipts-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'where-receipts-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'where-receipts-out-of-order';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusCurrentWhereOutcomeReceipt(bool $nextVisible, bool $baseVisible, bool $tokenMatches, bool $columnsMatch, bool $outcomesMatch, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next248-where-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next248-base-held';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-upsert-current-source-next248-where-token-held';
        }
        if (!$columnsMatch) {
            return 'trigger-recursive-view-upsert-current-source-next248-where-columns-held';
        }
        if (!$outcomesMatch) {
            return 'trigger-recursive-view-upsert-current-source-next248-where-outcomes-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next248-where-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next248-where-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next248-held';
    }

    private static function tokenCurrentWhereOutcomeReceipt(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next248 {$label} must not be empty");
        }

        return $value;
    }

    private static function boolTextCurrentWhereOutcomeReceipt(bool $value): string
    {
        return $value ? '1' : '0';
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentAssignmentImageReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentConflictImageReceipt(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_conflict_image_next246'] ?? false);
        $currentRows = self::rowsCurrentAssignmentImageReceipt($base['current_source_rows_next246'] ?? [], 'current rows');
        $nextRows = self::rowsCurrentAssignmentImageReceipt($base['attempted_next_source_rows_next246'] ?? [], 'next rows');
        $assignmentColumns = self::columnsCurrentAssignmentImageReceipt($options['upsert_assignment_columns_next249'] ?? ($base['upsert_excluded_columns_next246'] ?? ['value']), 'assignment columns');
        $sourceToken = self::tokenCurrentAssignmentImageReceipt((string) ($options['current_source_assignment_token_next249'] ?? 'wp.current.source.assignment.249'), 'assignment token');
        $expectedSourceToken = self::tokenCurrentAssignmentImageReceipt((string) ($options['expected_current_source_assignment_token_next249'] ?? $sourceToken), 'expected assignment token');
        $sourceMatches = hash_equals($sourceToken, $expectedSourceToken);
        $requireOrder = (bool) ($options['require_current_source_assignment_order_next249'] ?? true);
        $assignments = self::assignmentImagesCurrentAssignmentImageReceipt($base['current_source_conflict_images_next246'] ?? [], $assignmentColumns);
        $requiredReceipts = self::assignmentReceiptsCurrentAssignmentImageReceipt($assignments, $sourceToken);
        $acknowledgedReceipts = self::acknowledgedReceiptsCurrentAssignmentImageReceipt($options, $requiredReceipts);
        $missingReceipts = array_values(array_diff($requiredReceipts, $acknowledgedReceipts));
        $unexpectedReceipts = array_values(array_diff($acknowledgedReceipts, $requiredReceipts));
        $orderMatches = !$requireOrder || $requiredReceipts === $acknowledgedReceipts;
        $assignmentsComplete = $requiredReceipts !== []
            && $sourceMatches
            && $missingReceipts === []
            && $unexpectedReceipts === []
            && $orderMatches;
        $nextVisible = $baseVisible && $assignmentsComplete;
        $blockedReasons = self::blockedReasonsCurrentAssignmentImageReceipt(
            $base['blocked_reasons_next246'] ?? [],
            $baseVisible,
            $sourceMatches,
            $missingReceipts,
            $unexpectedReceipts,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRowsCurrentAssignmentImageReceipt($currentRows, 'current-assignment', true, $requiredReceipts, $sourceToken, $assignmentColumns, []);
        $nextRows = self::tagRowsCurrentAssignmentImageReceipt($nextRows, 'next-source', $nextVisible, [], $sourceToken, $assignmentColumns, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_assignment_next249'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_assignment_next249'],
        ));

        return [
            'status_next249' => self::statusCurrentAssignmentImageReceipt($baseVisible, $sourceMatches, $missingReceipts, $unexpectedReceipts, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next249' => $baseVisible,
            'current_source_assignment_token_next249' => $sourceToken,
            'expected_current_source_assignment_token_next249' => $expectedSourceToken,
            'current_source_assignment_token_matches_next249' => $sourceMatches,
            'upsert_assignment_columns_next249' => $assignmentColumns,
            'current_source_assignment_images_next249' => $assignments,
            'required_current_source_assignment_receipts_next249' => $requiredReceipts,
            'acknowledged_current_source_assignment_receipts_next249' => $acknowledgedReceipts,
            'missing_current_source_assignment_receipts_next249' => $missingReceipts,
            'unexpected_current_source_assignment_receipts_next249' => $unexpectedReceipts,
            'require_current_source_assignment_order_next249' => $requireOrder,
            'current_source_assignment_order_matches_next249' => $orderMatches,
            'current_source_assignments_complete_next249' => $assignmentsComplete,
            'next_source_visible_after_current_source_assignment_next249' => $nextVisible,
            'current_source_rows_next249' => $currentRows,
            'attempted_next_source_rows_next249' => $nextRows,
            'visible_returning_rows_next249' => $visibleRows,
            'held_next_source_rows_next249' => $heldRows,
            'visible_returning_payloads_next249' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next249' => array_column($heldRows, 'returning'),
            'visible_row_count_next249' => count($visibleRows),
            'held_next_row_count_next249' => count($heldRows),
            'blocked_reasons_next249' => $blockedReasons,
            'current_source_assignment_plan_next249' => [
                'base_next_source_visible' => $baseVisible,
                'source_token_matches' => $sourceMatches,
                'assignment_columns' => $assignmentColumns,
                'required_receipts' => $requiredReceipts,
                'acknowledged_receipts' => $acknowledgedReceipts,
                'missing_receipts' => $missingReceipts,
                'unexpected_receipts' => $unexpectedReceipts,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'assignments_complete' => $assignmentsComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-upsert-assignments'
                    : 'hold-next-source-until-current-upsert-assignments',
            ],
            'yield_boundary_next249' => $nextVisible
                ? 'recursive-view-upsert-next249-current-assignments-then-next'
                : 'recursive-view-upsert-next249-current-assignments-fence-next',
            'dependency_closure_next249' => 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-conflict-images-and-adds-do-update-assignment-receipts',
            'dependencies_next249' => array_values(array_unique(array_merge($base['dependencies_next246'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next249',
                'sqlite-instead-of-view-upsert-do-update-assignment-receipts',
                'wordpress-recursive-view-upsert-current-source-next249',
            ]))),
            'non_overlap_next249' => 'adds current-source UPSERT DO UPDATE assignment receipt fencing after accepted next246 conflict-image fencing; avoids accepted next240 conflict-key receipts, next242 statement epoch, next243 cookie fence, next246 old/excluded conflict images, recursive view RETURNING, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param mixed $images
     * @param list<string> $assignmentColumns
     * @return list<array<string,mixed>>
     */
    private static function assignmentImagesCurrentAssignmentImageReceipt(mixed $images, array $assignmentColumns): array
    {
        if (!is_array($images) || !array_is_list($images)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next249 conflict images must be a list');
        }

        $out = [];
        foreach ($images as $index => $image) {
            if (!is_array($image)) {
                throw new InvalidArgumentException('SQLite recursive view UPSERT next249 conflict image is malformed');
            }
            $excluded = isset($image['excluded_values']) && is_array($image['excluded_values']) ? $image['excluded_values'] : [];
            $old = isset($image['old_values']) && is_array($image['old_values']) ? $image['old_values'] : [];
            $assignments = [];
            foreach ($assignmentColumns as $column) {
                $assignments[$column] = [
                    'old' => $old[$column] ?? null,
                    'excluded' => $excluded[$column] ?? null,
                    'final' => $excluded[$column] ?? ($old[$column] ?? null),
                    'source' => array_key_exists($column, $excluded) ? 'excluded' : 'old',
                ];
            }
            $out[] = [
                'ordinal' => (int) ($image['ordinal'] ?? $index),
                'conflict_key' => (string) ($image['conflict_key'] ?? ''),
                'upsert_action' => (string) ($image['upsert_action'] ?? 'insert'),
                'matched_existing_row' => (bool) ($image['matched_existing_row'] ?? false),
                'assignment_columns' => $assignmentColumns,
                'assignments' => $assignments,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $assignments
     * @return list<string>
     */
    private static function assignmentReceiptsCurrentAssignmentImageReceipt(array $assignments, string $sourceToken): array
    {
        $receipts = [];
        foreach ($assignments as $assignment) {
            $receipts[] = substr(hash('sha256', json_encode([$sourceToken, $assignment], JSON_THROW_ON_ERROR)), 0, 46);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsCurrentAssignmentImageReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_assignments_next249'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentAssignmentImageReceipt($options['acknowledged_current_source_assignment_receipts_next249'] ?? [], 'acknowledged current source assignment receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListCurrentAssignmentImageReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next249 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{46}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next249 {$label} contain a malformed assignment receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentAssignmentImageReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next249 {$label} must be a list");
        }

        return $rows;
    }

    /**
     * @param mixed $columns
     * @return list<string>
     */
    private static function columnsCurrentAssignmentImageReceipt(mixed $columns, string $label): array
    {
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next249 {$label} must be a non-empty list");
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '' || preg_match('/\s/', $column) === 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next249 {$label} contain a malformed column");
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $assignmentColumns
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentAssignmentImageReceipt(array $rows, string $phase, bool $visible, array $receipts, string $sourceToken, array $assignmentColumns, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'assignment_phase_next249' => $phase,
                'current_source_assignment_token_next249' => $sourceToken,
                'current_source_assignment_receipt_next249' => $receipts[$index] ?? null,
                'upsert_assignment_columns_next249' => $assignmentColumns,
                'visible_after_current_source_assignment_next249' => $visible,
                'held_by_current_source_assignment_reasons_next249' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missingReceipts
     * @param list<string> $unexpectedReceipts
     * @return list<string>
     */
    private static function blockedReasonsCurrentAssignmentImageReceipt(mixed $baseReasons, bool $baseVisible, bool $sourceMatches, array $missingReceipts, array $unexpectedReceipts, bool $requireOrder, bool $orderMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next249 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next246-current-source-not-published';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-source-assignment-token-mismatch';
        }
        if ($missingReceipts !== []) {
            $reasons[] = 'current-source-assignment-receipt-missing';
        }
        if ($unexpectedReceipts !== []) {
            $reasons[] = 'current-source-assignment-receipt-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-assignment-receipt-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missingReceipts
     * @param list<string> $unexpectedReceipts
     */
    private static function statusCurrentAssignmentImageReceipt(bool $baseVisible, bool $sourceMatches, array $missingReceipts, array $unexpectedReceipts, bool $requireOrder, bool $orderMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next249-assignments-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next249-base-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-upsert-current-source-next249-assignment-token-held';
        }
        if ($missingReceipts !== [] || $unexpectedReceipts !== []) {
            return 'trigger-recursive-view-upsert-current-source-next249-assignment-receipts-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next249-assignment-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next249-assignments-held';
    }

    private static function tokenCurrentAssignmentImageReceipt(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next249 {$label} is malformed");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentRowidProvenanceReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentSequenceReceipt(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_statement_sequence_next247'] ?? false);
        $currentRows = self::rowsCurrentRowidProvenanceReceipt($base['current_source_rows_next247'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentRowidProvenanceReceipt($base['attempted_next_source_rows_next247'] ?? [], 'attempted next source rows');
        $rowidToken = self::tokenCurrentRowidProvenanceReceipt((string) ($options['current_source_rowid_provenance_token_next250'] ?? 'wp.current.source.rowid.provenance.250'), 'rowid provenance token');
        $expectedToken = self::tokenCurrentRowidProvenanceReceipt((string) ($options['expected_current_source_rowid_provenance_token_next250'] ?? $rowidToken), 'expected rowid provenance token');
        $rowidColumn = self::columnCurrentRowidProvenanceReceipt((string) ($options['rowid_column_next250'] ?? 'option_id'), 'rowid column');
        $conflictKey = self::columnCurrentRowidProvenanceReceipt((string) ($options['conflict_key_column_next250'] ?? 'option_name'), 'conflict key column');
        $requireExisting = (bool) ($options['require_existing_rowid_for_update_next250'] ?? false);
        $tokenMatches = hash_equals($rowidToken, $expectedToken);
        $oldRows = self::oldRowsCurrentRowidProvenanceReceipt($baseRows, $conflictKey);
        $provenance = self::rowidProvenanceCurrentRowidProvenanceReceipt($currentRows, $oldRows, $rowidColumn, $conflictKey);
        $required = self::rowidReceiptsCurrentRowidProvenanceReceipt($provenance, $rowidToken);
        $acknowledged = self::acknowledgedReceiptsCurrentRowidProvenanceReceipt($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $missingExisting = $requireExisting
            ? array_values(array_filter($provenance, static fn (array $row): bool => $row['old_rowid'] === null))
            : [];
        $rowidsComplete = $required !== []
            && $tokenMatches
            && $missing === []
            && $unexpected === []
            && $missingExisting === [];
        $nextVisible = $baseVisible && $rowidsComplete;
        $blockedReasons = self::blockedReasonsCurrentRowidProvenanceReceipt(
            $base['blocked_reasons_next247'] ?? [],
            $baseVisible,
            $tokenMatches,
            $missing,
            $unexpected,
            $requireExisting,
            $missingExisting,
        );

        $taggedCurrent = self::tagRowsCurrentRowidProvenanceReceipt($currentRows, 'current-rowid-provenance', true, $required, $rowidToken, $rowidColumn, $conflictKey, []);
        $taggedNext = self::tagRowsCurrentRowidProvenanceReceipt($nextRows, 'next-source', $nextVisible, [], $rowidToken, $rowidColumn, $conflictKey, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next250' => self::statusCurrentRowidProvenanceReceipt($nextVisible, $baseVisible, $tokenMatches, $missing, $unexpected, $requireExisting, $missingExisting),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next250' => $baseVisible,
            'current_source_rowid_provenance_token_next250' => $rowidToken,
            'expected_current_source_rowid_provenance_token_next250' => $expectedToken,
            'current_source_rowid_provenance_token_matches_next250' => $tokenMatches,
            'rowid_column_next250' => $rowidColumn,
            'conflict_key_column_next250' => $conflictKey,
            'require_existing_rowid_for_update_next250' => $requireExisting,
            'current_source_rowid_provenance_next250' => $provenance,
            'required_current_source_rowid_receipts_next250' => $required,
            'acknowledged_current_source_rowid_receipts_next250' => $acknowledged,
            'missing_current_source_rowid_receipts_next250' => $missing,
            'unexpected_current_source_rowid_receipts_next250' => $unexpected,
            'missing_existing_rowid_provenance_next250' => $missingExisting,
            'current_source_rowid_provenance_complete_next250' => $rowidsComplete,
            'next_source_visible_after_current_source_rowid_provenance_next250' => $nextVisible,
            'current_source_rows_next250' => $taggedCurrent,
            'attempted_next_source_rows_next250' => $taggedNext,
            'visible_returning_rows_next250' => $visibleRows,
            'held_next_source_rows_next250' => $heldRows,
            'visible_returning_payloads_next250' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next250' => array_column($heldRows, 'returning'),
            'current_source_row_count_next250' => count($taggedCurrent),
            'attempted_next_source_row_count_next250' => count($taggedNext),
            'visible_row_count_next250' => count($visibleRows),
            'held_next_row_count_next250' => count($heldRows),
            'blocked_reasons_next250' => $blockedReasons,
            'current_source_rowid_provenance_plan_next250' => [
                'base_next_source_visible' => $baseVisible,
                'rowid_token_matches' => $tokenMatches,
                'rowid_column' => $rowidColumn,
                'conflict_key_column' => $conflictKey,
                'require_existing_rowid_for_update' => $requireExisting,
                'required_receipts' => $required,
                'acknowledged_receipts' => $acknowledged,
                'missing_receipts' => $missing,
                'unexpected_receipts' => $unexpected,
                'missing_existing_rowid_provenance' => $missingExisting,
                'rowid_provenance_complete' => $rowidsComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert-rowids'
                    : 'hold-next-source-until-current-recursive-view-upsert-rowids',
            ],
            'yield_boundary_next250' => $nextVisible
                ? 'recursive-view-upsert-next250-current-rowids-then-next'
                : 'recursive-view-upsert-next250-current-rowids-fence-next',
            'dependency_closure_next250' => 'no-new-support-component-reuses-native-recursive-view-upsert-sequence-and-adds-current-rowid-provenance-receipts',
            'dependencies_next250' => array_values(array_unique(array_merge($base['dependencies_next247'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next250',
                'sqlite-instead-of-view-trigger-upsert-rowid-provenance',
                'wordpress-recursive-view-upsert-current-source-next250',
            ]))),
            'non_overlap_next250' => 'adds current-source UPSERT rowid-provenance receipt fencing after accepted next247 statement sequence fencing; avoids next246 conflict images, next247 sequence receipts, commit watermark/source-cookie surfaces, trigger RETURNING, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,array<string,mixed>> $oldRows
     * @return list<array<string,mixed>>
     */
    private static function rowidProvenanceCurrentRowidProvenanceReceipt(array $rows, array $oldRows, string $rowidColumn, string $conflictKey): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $key = self::scalarKeyCurrentRowidProvenanceReceipt($returning['name'] ?? $returning[$conflictKey] ?? null);
            $old = $oldRows[$key] ?? null;
            $out[] = [
                'ordinal' => $index,
                'conflict_key' => $key,
                'old_rowid' => $old[$rowidColumn] ?? null,
                'new_rowid' => $returning[$rowidColumn] ?? $returning['import_id'] ?? null,
                'returning_name' => $returning['name'] ?? null,
                'returning_value' => $returning['value'] ?? null,
                'statement_sequence_receipt' => $row['current_source_sequence_receipt_next247'] ?? null,
                'upsert_rowid_action' => $old === null ? 'insert-rowid' : 'update-existing-rowid',
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $provenance
     * @return list<string>
     */
    private static function rowidReceiptsCurrentRowidProvenanceReceipt(array $provenance, string $token): array
    {
        $receipts = [];
        foreach ($provenance as $row) {
            $receipts[] = substr(hash('sha256', json_encode([$token, $row], JSON_THROW_ON_ERROR)), 0, 50);
        }

        return $receipts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private static function oldRowsCurrentRowidProvenanceReceipt(array $rows, string $keyColumn): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($keyColumn, $row)) {
                continue;
            }
            $out[self::scalarKeyCurrentRowidProvenanceReceipt($row[$keyColumn])] = $row;
        }

        return $out;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentRowidProvenanceReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next250 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next250 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsCurrentRowidProvenanceReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_rowid_provenance_next250'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentRowidProvenanceReceipt($options['acknowledged_current_source_rowid_receipts_next250'] ?? [], 'acknowledged rowid receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListCurrentRowidProvenanceReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next250 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{50}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next250 {$label} contain a malformed rowid receipt");
            }
        }

        return array_values(array_unique($values));
    }

    private static function tokenCurrentRowidProvenanceReceipt(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next250 {$label} is malformed");
        }

        return $value;
    }

    private static function columnCurrentRowidProvenanceReceipt(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next250 {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentRowidProvenanceReceipt(array $rows, string $phase, bool $visible, array $receipts, string $token, string $rowidColumn, string $conflictKey, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'rowid_provenance_phase_next250' => $phase,
                'current_source_rowid_provenance_token_next250' => $token,
                'rowid_column_next250' => $rowidColumn,
                'conflict_key_column_next250' => $conflictKey,
                'current_source_rowid_receipt_next250' => $receipts[$index] ?? null,
                'visible_after_current_source_rowid_provenance_next250' => $visible,
                'held_by_current_source_rowid_provenance_reasons_next250' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @param list<array<string,mixed>> $missingExisting
     * @return list<string>
     */
    private static function blockedReasonsCurrentRowidProvenanceReceipt(mixed $baseReasons, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireExisting, array $missingExisting): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next250 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next247-statement-sequence-not-published';
        }
        if (!$tokenMatches) {
            $reasons[] = 'current-source-rowid-provenance-token-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-rowid-provenance-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-rowid-provenance-unexpected';
        }
        if ($requireExisting && $missingExisting !== []) {
            $reasons[] = 'current-source-rowid-provenance-existing-rowid-missing';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @param list<array<string,mixed>> $missingExisting
     */
    private static function statusCurrentRowidProvenanceReceipt(bool $nextVisible, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireExisting, array $missingExisting): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next250-rowid-provenance-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next250-base-held';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-upsert-current-source-next250-rowid-token-held';
        }
        if ($missing !== []) {
            return 'trigger-recursive-view-upsert-current-source-next250-rowid-missing-held';
        }
        if ($unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next250-rowid-unexpected-held';
        }
        if ($requireExisting && $missingExisting !== []) {
            return 'trigger-recursive-view-upsert-current-source-next250-rowid-existing-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next250-held';
    }

    private static function scalarKeyCurrentRowidProvenanceReceipt(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => 'BOOL:' . ($value ? '1' : '0'),
            is_int($value) => 'INT:' . $value,
            is_float($value) => 'FLOAT:' . $value,
            default => 'TEXT:' . (string) $value,
        };
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentChangeReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentSequenceReceipt(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_statement_sequence_next247'] ?? false);
        $currentRows = self::rowsCurrentChangeReceipt($base['current_source_rows_next247'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentChangeReceipt($base['attempted_next_source_rows_next247'] ?? [], 'attempted next source rows');
        $statementChanges = self::counterCurrentChangeReceipt($options['current_source_changes_next251'] ?? count($currentRows), 'statement changes');
        $expectedChanges = self::counterCurrentChangeReceipt($options['expected_current_source_changes_next251'] ?? count($currentRows), 'expected statement changes');
        $totalChanges = self::counterCurrentChangeReceipt($options['current_source_total_changes_next251'] ?? ($statementChanges + count($baseRows)), 'total changes');
        $minimumTotal = self::counterCurrentChangeReceipt($options['minimum_current_source_total_changes_next251'] ?? $statementChanges, 'minimum total changes');
        $viewCookie = self::tokenCurrentChangeReceipt((string) ($options['current_source_change_view_cookie_next251'] ?? ($base['current_source_sequence_view_cookie_next247'] ?? ($currentView['source'] ?? 'main@view-cookie-251-current'))), 'view cookie');
        $triggerCookie = self::tokenCurrentChangeReceipt((string) ($options['current_source_change_trigger_cookie_next251'] ?? ($base['current_source_sequence_trigger_cookie_next247'] ?? ($currentView['trigger_source'] ?? 'main@trigger-cookie-251-current'))), 'trigger cookie');
        $counterCursor = self::tokenCurrentChangeReceipt((string) ($options['current_source_change_counter_cursor_next251'] ?? 'wp.returning.current.change.counter.251'), 'change counter cursor');
        $requireTotal = (bool) ($options['require_total_changes_monotonic_next251'] ?? true);

        $changesMatch = $statementChanges === $expectedChanges;
        $totalMonotonic = $totalChanges >= $minimumTotal && $totalChanges >= $statementChanges;
        $required = self::changeReceiptsCurrentChangeReceipt($currentRows, $statementChanges, $totalChanges, $viewCookie, $triggerCookie, $counterCursor);
        $acknowledged = self::acknowledgedReceiptsCurrentChangeReceipt($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $changesComplete = $required !== []
            && $changesMatch
            && (!$requireTotal || $totalMonotonic)
            && $missing === []
            && $unexpected === [];
        $nextVisible = $baseVisible && $changesComplete;
        $blockedReasons = self::blockedReasonsCurrentChangeReceipt(
            $base['blocked_reasons_next247'] ?? [],
            $baseVisible,
            $changesMatch,
            $requireTotal,
            $totalMonotonic,
            $missing,
            $unexpected,
        );

        $taggedCurrent = self::tagRowsCurrentChangeReceipt($currentRows, 'current-change-counter', true, $required, $statementChanges, $expectedChanges, $totalChanges, $minimumTotal, $viewCookie, $triggerCookie, $counterCursor, []);
        $taggedNext = self::tagRowsCurrentChangeReceipt($nextRows, 'next-source', $nextVisible, [], $statementChanges, $expectedChanges, $totalChanges, $minimumTotal, $viewCookie, $triggerCookie, $counterCursor, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next251' => self::statusCurrentChangeReceipt($nextVisible, $baseVisible, $changesMatch, $requireTotal, $totalMonotonic, $missing, $unexpected),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next251' => $baseVisible,
            'current_source_changes_next251' => $statementChanges,
            'expected_current_source_changes_next251' => $expectedChanges,
            'current_source_changes_match_next251' => $changesMatch,
            'current_source_total_changes_next251' => $totalChanges,
            'minimum_current_source_total_changes_next251' => $minimumTotal,
            'require_total_changes_monotonic_next251' => $requireTotal,
            'current_source_total_changes_monotonic_next251' => $totalMonotonic,
            'current_source_change_view_cookie_next251' => $viewCookie,
            'current_source_change_trigger_cookie_next251' => $triggerCookie,
            'current_source_change_counter_cursor_next251' => $counterCursor,
            'required_current_source_change_receipts_next251' => $required,
            'acknowledged_current_source_change_receipts_next251' => $acknowledged,
            'missing_current_source_change_receipts_next251' => $missing,
            'unexpected_current_source_change_receipts_next251' => $unexpected,
            'current_source_changes_complete_next251' => $changesComplete,
            'next_source_visible_after_current_source_changes_next251' => $nextVisible,
            'current_source_rows_next251' => $taggedCurrent,
            'attempted_next_source_rows_next251' => $taggedNext,
            'visible_returning_rows_next251' => $visibleRows,
            'held_next_source_rows_next251' => $heldRows,
            'visible_returning_payloads_next251' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next251' => array_column($heldRows, 'returning'),
            'current_source_row_count_next251' => count($taggedCurrent),
            'attempted_next_source_row_count_next251' => count($taggedNext),
            'visible_row_count_next251' => count($visibleRows),
            'held_next_row_count_next251' => count($heldRows),
            'blocked_reasons_next251' => $blockedReasons,
            'current_source_change_counter_plan_next251' => [
                'base_next_source_visible' => $baseVisible,
                'statement_changes' => $statementChanges,
                'expected_statement_changes' => $expectedChanges,
                'changes_match' => $changesMatch,
                'total_changes' => $totalChanges,
                'minimum_total_changes' => $minimumTotal,
                'require_total_changes_monotonic' => $requireTotal,
                'total_changes_monotonic' => $totalMonotonic,
                'required_receipts' => $required,
                'acknowledged_receipts' => $acknowledged,
                'missing_receipts' => $missing,
                'unexpected_receipts' => $unexpected,
                'changes_complete' => $changesComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert-change-counters'
                    : 'hold-next-source-until-current-recursive-view-upsert-change-counters',
            ],
            'yield_boundary_next251' => $nextVisible
                ? 'recursive-view-upsert-next251-current-change-counters-then-next'
                : 'recursive-view-upsert-next251-current-change-counters-fence-next',
            'dependency_closure_next251' => 'no-new-support-component-reuses-native-recursive-view-upsert-statement-sequence-and-adds-change-counter-fencing',
            'dependencies_next251' => array_values(array_unique(array_merge($base['dependencies_next247'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next251',
                'sqlite-instead-of-view-trigger-upsert-change-counter-fence',
                'wordpress-recursive-view-upsert-current-source-next251',
            ]))),
            'non_overlap_next251' => 'adds current-source change-counter receipts after accepted next247 statement sequence fencing; avoids next247 sequence receipts, next244 commit watermarks, next239 target receipts, recursive view RETURNING cursor/ticket/generation surfaces, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function changeReceiptsCurrentChangeReceipt(array $rows, int $changes, int $totalChanges, string $viewCookie, string $triggerCookie, string $cursor): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $receipts[] = substr(hash('sha256', implode('|', [
                (string) $changes,
                (string) $totalChanges,
                $viewCookie,
                $triggerCookie,
                $cursor,
                (string) ($row['current_source_sequence_receipt_next247'] ?? ''),
                (string) ($returning['name'] ?? ''),
                (string) ($returning['value'] ?? ''),
                (string) ($returning['event_name'] ?? ''),
                (string) ($returning['ordinal_value'] ?? $index),
            ])), 0, 56);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsCurrentChangeReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_change_counters_next251'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentChangeReceipt($options['acknowledged_current_source_change_receipts_next251'] ?? [], 'acknowledged change-counter receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListCurrentChangeReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next251 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{56}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next251 {$label} contain a malformed change receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentChangeReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next251 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next251 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentChangeReceipt(array $rows, string $phase, bool $visible, array $receipts, int $changes, int $expectedChanges, int $totalChanges, int $minimumTotal, string $viewCookie, string $triggerCookie, string $cursor, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'change_counter_phase_next251' => $phase,
                'current_source_changes_next251' => $changes,
                'expected_current_source_changes_next251' => $expectedChanges,
                'current_source_total_changes_next251' => $totalChanges,
                'minimum_current_source_total_changes_next251' => $minimumTotal,
                'current_source_change_view_cookie_next251' => $viewCookie,
                'current_source_change_trigger_cookie_next251' => $triggerCookie,
                'current_source_change_counter_cursor_next251' => $cursor,
                'current_source_change_receipt_next251' => $receipts[$index] ?? null,
                'visible_after_current_source_changes_next251' => $visible,
                'held_by_current_source_change_counter_reasons_next251' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsCurrentChangeReceipt(mixed $baseReasons, bool $baseVisible, bool $changesMatch, bool $requireTotal, bool $totalMonotonic, array $missing, array $unexpected): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next251 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next247-current-source-statement-sequence-not-published';
        }
        if (!$changesMatch) {
            $reasons[] = 'current-source-changes-mismatch';
        }
        if ($requireTotal && !$totalMonotonic) {
            $reasons[] = 'current-source-total-changes-not-monotonic';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-change-counter-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-change-counter-unexpected';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusCurrentChangeReceipt(bool $nextVisible, bool $baseVisible, bool $changesMatch, bool $requireTotal, bool $totalMonotonic, array $missing, array $unexpected): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next251-change-counters-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next251-base-held';
        }
        if (!$changesMatch) {
            return 'trigger-recursive-view-upsert-current-source-next251-changes-held';
        }
        if ($requireTotal && !$totalMonotonic) {
            return 'trigger-recursive-view-upsert-current-source-next251-total-changes-held';
        }
        if ($missing !== []) {
            return 'trigger-recursive-view-upsert-current-source-next251-change-counters-missing-held';
        }
        if ($unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next251-change-counters-unexpected-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next251-change-counters-held';
    }

    private static function counterCurrentChangeReceipt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next251 {$label} must be a non-negative integer");
        }

        return $value;
    }

    private static function tokenCurrentChangeReceipt(string $token, string $label): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]{3,180}$/', $token)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next251 invalid {$label}");
        }

        return $token;
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentPredicateDecisionReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentAssignmentImageReceipt(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_assignment_next249'] ?? false);
        $currentRows = self::rowsCurrentPredicateDecisionReceipt($base['current_source_rows_next249'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentPredicateDecisionReceipt($base['attempted_next_source_rows_next249'] ?? [], 'attempted next source rows');
        $assignments = self::assignmentsCurrentPredicateDecisionReceipt($base['current_source_assignment_images_next249'] ?? []);
        $predicateToken = self::tokenCurrentPredicateDecisionReceipt((string) ($options['current_source_upsert_where_token_next252'] ?? 'wp.current.source.upsert.where.252'), 'where token');
        $expectedPredicateToken = self::tokenCurrentPredicateDecisionReceipt((string) ($options['expected_current_source_upsert_where_token_next252'] ?? $predicateToken), 'expected where token');
        $tokenMatches = hash_equals($predicateToken, $expectedPredicateToken);
        $decisions = self::predicateDecisionsCurrentPredicateDecisionReceipt($options['current_source_upsert_where_decisions_next252'] ?? null, $assignments);
        $requireTrue = (bool) ($options['require_current_source_upsert_where_true_next252'] ?? false);
        $requiredReceipts = self::predicateReceiptsCurrentPredicateDecisionReceipt($assignments, $decisions, $predicateToken);
        $acknowledgedReceipts = self::acknowledgedReceiptsCurrentPredicateDecisionReceipt($options, $requiredReceipts);
        $missingReceipts = array_values(array_diff($requiredReceipts, $acknowledgedReceipts));
        $unexpectedReceipts = array_values(array_diff($acknowledgedReceipts, $requiredReceipts));
        $allPredicatesTrue = !in_array(false, $decisions, true);
        $predicateComplete = $requiredReceipts !== []
            && $tokenMatches
            && $missingReceipts === []
            && $unexpectedReceipts === []
            && (!$requireTrue || $allPredicatesTrue);
        $nextVisible = $baseVisible && $predicateComplete;
        $blockedReasons = self::blockedReasonsCurrentPredicateDecisionReceipt(
            $base['blocked_reasons_next249'] ?? [],
            $baseVisible,
            $tokenMatches,
            $missingReceipts,
            $unexpectedReceipts,
            $requireTrue,
            $allPredicatesTrue,
        );

        $currentRows = self::tagRowsCurrentPredicateDecisionReceipt($currentRows, 'current-where', true, $requiredReceipts, $predicateToken, $decisions, []);
        $nextRows = self::tagRowsCurrentPredicateDecisionReceipt($nextRows, 'next-source', $nextVisible, [], $predicateToken, [], $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($currentRows, $nextRows) : $currentRows;
        $heldRows = $nextVisible ? [] : $nextRows;

        return [
            'status_next252' => self::statusCurrentPredicateDecisionReceipt($baseVisible, $tokenMatches, $missingReceipts, $unexpectedReceipts, $requireTrue, $allPredicatesTrue, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next252' => $baseVisible,
            'current_source_upsert_where_token_next252' => $predicateToken,
            'expected_current_source_upsert_where_token_next252' => $expectedPredicateToken,
            'current_source_upsert_where_token_matches_next252' => $tokenMatches,
            'current_source_upsert_where_decisions_next252' => $decisions,
            'current_source_upsert_where_all_true_next252' => $allPredicatesTrue,
            'require_current_source_upsert_where_true_next252' => $requireTrue,
            'required_current_source_upsert_where_receipts_next252' => $requiredReceipts,
            'acknowledged_current_source_upsert_where_receipts_next252' => $acknowledgedReceipts,
            'missing_current_source_upsert_where_receipts_next252' => $missingReceipts,
            'unexpected_current_source_upsert_where_receipts_next252' => $unexpectedReceipts,
            'current_source_upsert_where_complete_next252' => $predicateComplete,
            'next_source_visible_after_current_source_upsert_where_next252' => $nextVisible,
            'current_source_rows_next252' => $currentRows,
            'attempted_next_source_rows_next252' => $nextRows,
            'visible_returning_rows_next252' => $visibleRows,
            'held_next_source_rows_next252' => $heldRows,
            'visible_returning_payloads_next252' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next252' => array_column($heldRows, 'returning'),
            'visible_row_count_next252' => count($visibleRows),
            'held_next_row_count_next252' => count($heldRows),
            'blocked_reasons_next252' => $blockedReasons,
            'current_source_upsert_where_plan_next252' => [
                'base_next_source_visible' => $baseVisible,
                'token_matches' => $tokenMatches,
                'decisions' => $decisions,
                'require_true' => $requireTrue,
                'all_true' => $allPredicatesTrue,
                'required_receipts' => $requiredReceipts,
                'acknowledged_receipts' => $acknowledgedReceipts,
                'missing_receipts' => $missingReceipts,
                'unexpected_receipts' => $unexpectedReceipts,
                'predicate_complete' => $predicateComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-upsert-where'
                    : 'hold-next-source-until-current-upsert-where',
            ],
            'yield_boundary_next252' => $nextVisible
                ? 'recursive-view-upsert-next252-current-where-then-next'
                : 'recursive-view-upsert-next252-current-where-fence-next',
            'dependency_closure_next252' => 'no-new-support-component-reuses-native-recursive-view-upsert-assignment-receipts-and-adds-do-update-where-decision-receipts',
            'dependencies_next252' => array_values(array_unique(array_merge($base['dependencies_next249'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next252',
                'sqlite-instead-of-view-upsert-do-update-where-receipts',
                'wordpress-recursive-view-upsert-current-source-next252',
            ]))),
            'non_overlap_next252' => 'adds current-source UPSERT DO UPDATE WHERE predicate decision receipts after accepted next249 assignment receipts; avoids next249 assignment receipt fencing, next246 conflict images, recursive view RETURNING, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentPredicateDecisionReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next252 {$label} must be a list");
        }

        return $rows;
    }

    /**
     * @param mixed $assignments
     * @return list<array<string,mixed>>
     */
    private static function assignmentsCurrentPredicateDecisionReceipt(mixed $assignments): array
    {
        if (!is_array($assignments) || !array_is_list($assignments) || $assignments === []) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next252 assignment images must be a non-empty list');
        }
        foreach ($assignments as $assignment) {
            if (!is_array($assignment)) {
                throw new InvalidArgumentException('SQLite recursive view UPSERT next252 assignment image is malformed');
            }
        }

        return $assignments;
    }

    /**
     * @param mixed $decisions
     * @param list<array<string,mixed>> $assignments
     * @return list<bool>
     */
    private static function predicateDecisionsCurrentPredicateDecisionReceipt(mixed $decisions, array $assignments): array
    {
        if ($decisions === null) {
            return array_fill(0, count($assignments), true);
        }
        if (!is_array($decisions) || !array_is_list($decisions) || count($decisions) !== count($assignments)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next252 predicate decisions must match assignment count');
        }
        foreach ($decisions as $decision) {
            if (!is_bool($decision)) {
                throw new InvalidArgumentException('SQLite recursive view UPSERT next252 predicate decisions must be booleans');
            }
        }

        return $decisions;
    }

    /**
     * @param list<array<string,mixed>> $assignments
     * @param list<bool> $decisions
     * @return list<string>
     */
    private static function predicateReceiptsCurrentPredicateDecisionReceipt(array $assignments, array $decisions, string $predicateToken): array
    {
        $receipts = [];
        foreach ($assignments as $index => $assignment) {
            $receipts[] = substr(hash('sha256', json_encode([
                $predicateToken,
                $assignment['ordinal'] ?? $index,
                $assignment['conflict_key'] ?? '',
                $assignment['upsert_action'] ?? '',
                $assignment['assignments'] ?? [],
                $decisions[$index],
            ], JSON_THROW_ON_ERROR)), 0, 44);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsCurrentPredicateDecisionReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_upsert_where_next252'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentPredicateDecisionReceipt($options['acknowledged_current_source_upsert_where_receipts_next252'] ?? [], 'acknowledged where receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListCurrentPredicateDecisionReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next252 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{44}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next252 {$label} contain a malformed where receipt");
            }
        }

        return array_values(array_unique($values));
    }

    private static function tokenCurrentPredicateDecisionReceipt(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next252 {$label} is malformed");
        }

        return $token;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<bool> $decisions
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentPredicateDecisionReceipt(array $rows, string $phase, bool $visible, array $receipts, string $predicateToken, array $decisions, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_where_phase_next252' => $phase,
                'current_source_upsert_where_token_next252' => $predicateToken,
                'current_source_upsert_where_decision_next252' => $decisions[$index] ?? null,
                'current_source_upsert_where_receipt_next252' => $receipts[$index] ?? null,
                'visible_after_current_source_upsert_where_next252' => $visible,
                'held_by_current_source_upsert_where_reasons_next252' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsCurrentPredicateDecisionReceipt(mixed $baseReasons, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireTrue, bool $allPredicatesTrue): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next252 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next249-current-source-upsert-assignments-not-published';
        }
        if (!$tokenMatches) {
            $reasons[] = 'current-source-upsert-where-token-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-upsert-where-receipt-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-upsert-where-receipt-unexpected';
        }
        if ($requireTrue && !$allPredicatesTrue) {
            $reasons[] = 'current-source-upsert-where-false';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusCurrentPredicateDecisionReceipt(bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireTrue, bool $allPredicatesTrue, bool $nextVisible): string
    {
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next252-base-held';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-upsert-current-source-next252-where-token-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next252-where-receipts-held';
        }
        if ($requireTrue && !$allPredicatesTrue) {
            return 'trigger-recursive-view-upsert-current-source-next252-where-false-held';
        }

        return $nextVisible
            ? 'trigger-recursive-view-upsert-current-source-next252-where-released'
            : 'trigger-recursive-view-upsert-current-source-next252-held';
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentViewMaterializationReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentRowidProvenanceReceipt(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_rowid_provenance_next250'] ?? false);
        $currentRows = self::rowsCurrentViewMaterializationReceipt($base['current_source_rows_next250'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentViewMaterializationReceipt($base['attempted_next_source_rows_next250'] ?? [], 'attempted next source rows');
        $materializationToken = self::tokenCurrentViewMaterializationReceipt((string) ($options['current_source_view_materialization_token_next253'] ?? 'wp.current.source.view.materialization.253'), 'view materialization token');
        $expectedToken = self::tokenCurrentViewMaterializationReceipt((string) ($options['expected_current_source_view_materialization_token_next253'] ?? $materializationToken), 'expected view materialization token');
        $viewCookie = self::tokenCurrentViewMaterializationReceipt((string) ($options['current_source_view_cookie_next253'] ?? ($base['base']['current_source_sequence_view_cookie_next247'] ?? ($currentView['source'] ?? 'main@view-cookie-253-current'))), 'view cookie');
        $triggerCookie = self::tokenCurrentViewMaterializationReceipt((string) ($options['current_source_trigger_cookie_next253'] ?? ($base['base']['current_source_sequence_trigger_cookie_next247'] ?? ($currentView['trigger_source'] ?? 'main@trigger-cookie-253-current'))), 'trigger cookie');
        $cursor = self::tokenCurrentViewMaterializationReceipt((string) ($options['current_source_materialization_cursor_next253'] ?? 'wp.returning.materialized.cursor.253'), 'materialization cursor');
        $projectionColumns = self::columnsCurrentViewMaterializationReceipt($options['materialized_returning_columns_next253'] ?? ['name', 'value', 'event_name', 'depth_value', 'ordinal_value'], 'materialized returning columns');
        $tokenMatches = hash_equals($materializationToken, $expectedToken);

        $materialized = self::materializedRowsCurrentViewMaterializationReceipt($currentRows, $projectionColumns, $viewCookie, $triggerCookie, $cursor);
        $required = self::materializationReceiptsCurrentViewMaterializationReceipt($materialized, $materializationToken);
        $acknowledged = self::acknowledgedReceiptsCurrentViewMaterializationReceipt($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $requireOrder = (bool) ($options['require_current_source_view_materialization_order_next253'] ?? true);
        $orderMatches = !$requireOrder || $missing !== [] || $unexpected !== [] || $required === $acknowledged;
        $complete = $required !== []
            && $tokenMatches
            && $missing === []
            && $unexpected === []
            && $orderMatches;
        $nextVisible = $baseVisible && $complete;
        $blockedReasons = self::blockedReasonsCurrentViewMaterializationReceipt(
            $base['blocked_reasons_next250'] ?? [],
            $baseVisible,
            $tokenMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $taggedCurrent = self::tagRowsCurrentViewMaterializationReceipt($currentRows, 'current-view-materialized', true, $required, $materializationToken, $viewCookie, $triggerCookie, $cursor, []);
        $taggedNext = self::tagRowsCurrentViewMaterializationReceipt($nextRows, 'next-source', $nextVisible, [], $materializationToken, $viewCookie, $triggerCookie, $cursor, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next253' => self::statusCurrentViewMaterializationReceipt($nextVisible, $baseVisible, $tokenMatches, $missing, $unexpected, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next253' => $baseVisible,
            'current_source_view_materialization_token_next253' => $materializationToken,
            'expected_current_source_view_materialization_token_next253' => $expectedToken,
            'current_source_view_materialization_token_matches_next253' => $tokenMatches,
            'current_source_view_cookie_next253' => $viewCookie,
            'current_source_trigger_cookie_next253' => $triggerCookie,
            'current_source_materialization_cursor_next253' => $cursor,
            'materialized_returning_columns_next253' => $projectionColumns,
            'current_source_view_materialization_rows_next253' => $materialized,
            'required_current_source_view_materialization_receipts_next253' => $required,
            'acknowledged_current_source_view_materialization_receipts_next253' => $acknowledged,
            'missing_current_source_view_materialization_receipts_next253' => $missing,
            'unexpected_current_source_view_materialization_receipts_next253' => $unexpected,
            'require_current_source_view_materialization_order_next253' => $requireOrder,
            'current_source_view_materialization_order_matches_next253' => $orderMatches,
            'current_source_view_materialization_complete_next253' => $complete,
            'next_source_visible_after_current_source_view_materialization_next253' => $nextVisible,
            'current_source_rows_next253' => $taggedCurrent,
            'attempted_next_source_rows_next253' => $taggedNext,
            'visible_returning_rows_next253' => $visibleRows,
            'held_next_source_rows_next253' => $heldRows,
            'visible_returning_payloads_next253' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next253' => array_column($heldRows, 'returning'),
            'visible_row_count_next253' => count($visibleRows),
            'held_next_row_count_next253' => count($heldRows),
            'blocked_reasons_next253' => $blockedReasons,
            'current_source_view_materialization_plan_next253' => [
                'base_next_source_visible' => $baseVisible,
                'token_matches' => $tokenMatches,
                'view_cookie' => $viewCookie,
                'trigger_cookie' => $triggerCookie,
                'cursor' => $cursor,
                'projection_columns' => $projectionColumns,
                'required_receipts' => $required,
                'acknowledged_receipts' => $acknowledged,
                'missing_receipts' => $missing,
                'unexpected_receipts' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'materialization_complete' => $complete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert-materialization'
                    : 'hold-next-source-until-current-recursive-view-upsert-materialization',
            ],
            'yield_boundary_next253' => $nextVisible
                ? 'recursive-view-upsert-next253-current-materialized-then-next'
                : 'recursive-view-upsert-next253-current-materialized-fence-next',
            'dependency_closure_next253' => 'no-new-support-component-reuses-native-recursive-view-upsert-rowid-provenance-and-adds-current-view-materialization-receipts',
            'dependencies_next253' => array_values(array_unique(array_merge($base['dependencies_next250'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next253',
                'sqlite-instead-of-view-trigger-upsert-materialization-receipts',
                'wordpress-recursive-view-upsert-current-source-next253',
            ]))),
            'non_overlap_next253' => 'adds current-source recursive view UPSERT materialized RETURNING receipt fencing after accepted next250 rowid provenance; avoids next247 statement sequence, next246 conflict images, next250 rowid receipts, recursive view RETURNING-only clusters, WAL/VFS, JSON table, planner, encoding, B-tree, and suite evidence clusters',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentViewMaterializationReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next253 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next253 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param mixed $columns
     * @return list<string>
     */
    private static function columnsCurrentViewMaterializationReceipt(mixed $columns, string $label): array
    {
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next253 {$label} must be a non-empty list");
        }
        $out = [];
        foreach ($columns as $column) {
            if (!is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next253 {$label} contain a malformed column");
            }
            $out[] = $column;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private static function materializedRowsCurrentViewMaterializationReceipt(array $rows, array $columns, string $viewCookie, string $triggerCookie, string $cursor): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $payload = $row['returning'];
            $projection = [];
            foreach ($columns as $column) {
                $projection[$column] = $payload[$column] ?? null;
            }
            $out[] = [
                'ordinal' => $index,
                'view_cookie' => $viewCookie,
                'trigger_cookie' => $triggerCookie,
                'cursor' => $cursor,
                'rowid_receipt_next250' => $row['current_source_rowid_receipt_next250'] ?? null,
                'projection' => $projection,
                'projection_hash' => substr(hash('sha256', json_encode([$viewCookie, $triggerCookie, $cursor, $projection], JSON_THROW_ON_ERROR)), 0, 48),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function materializationReceiptsCurrentViewMaterializationReceipt(array $rows, string $token): array
    {
        $receipts = [];
        foreach ($rows as $row) {
            $receipts[] = substr(hash('sha256', json_encode([$token, $row], JSON_THROW_ON_ERROR)), 0, 50);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsCurrentViewMaterializationReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_view_materialization_next253'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentViewMaterializationReceipt($options['acknowledged_current_source_view_materialization_receipts_next253'] ?? [], 'acknowledged view materialization receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptListCurrentViewMaterializationReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next253 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{50}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next253 {$label} contain a malformed receipt");
            }
        }

        return array_values(array_unique($values));
    }

    private static function tokenCurrentViewMaterializationReceipt(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next253 {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentViewMaterializationReceipt(array $rows, string $phase, bool $visible, array $receipts, string $token, string $viewCookie, string $triggerCookie, string $cursor, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'view_materialization_phase_next253' => $phase,
                'current_source_view_materialization_token_next253' => $token,
                'current_source_view_cookie_next253' => $viewCookie,
                'current_source_trigger_cookie_next253' => $triggerCookie,
                'current_source_materialization_cursor_next253' => $cursor,
                'current_source_view_materialization_receipt_next253' => $receipts[$index] ?? null,
                'visible_after_current_source_view_materialization_next253' => $visible,
                'held_by_current_source_view_materialization_reasons_next253' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsCurrentViewMaterializationReceipt(mixed $baseReasons, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next253 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next250-rowid-provenance-not-published';
        }
        if (!$tokenMatches) {
            $reasons[] = 'current-source-view-materialization-token-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-view-materialization-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-view-materialization-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-view-materialization-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusCurrentViewMaterializationReceipt(bool $nextVisible, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next253-view-materialization-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next253-base-held';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-upsert-current-source-next253-materialization-token-held';
        }
        if ($missing !== []) {
            return 'trigger-recursive-view-upsert-current-source-next253-materialization-missing-held';
        }
        if ($unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next253-materialization-unexpected-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next253-materialization-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next253-held';
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentViewMappingReceipt(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentRowidProvenanceReceipt(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_rowid_provenance_next250'] ?? false);
        $currentRows = self::rowsCurrentViewMappingReceipt($base['current_source_rows_next250'] ?? [], 'current source rows');
        $nextRows = self::rowsCurrentViewMappingReceipt($base['attempted_next_source_rows_next250'] ?? [], 'attempted next source rows');
        $mapping = self::mappingCurrentViewMappingReceipt($currentView['mapping'] ?? [], 'current view mapping');
        $expectedMapping = self::mappingCurrentViewMappingReceipt($options['expected_current_view_mapping_next254'] ?? $mapping, 'expected current view mapping');
        $mappingMatches = $mapping === $expectedMapping;
        $sourceToken = self::tokenCurrentViewMappingReceipt((string) ($options['current_view_mapping_source_token_next254'] ?? ($currentView['source'] ?? 'main@view-mapping-current-254')), 'view mapping source token');
        $expectedSourceToken = self::tokenCurrentViewMappingReceipt((string) ($options['expected_current_view_mapping_source_token_next254'] ?? $sourceToken), 'expected view mapping source token');
        $sourceMatches = hash_equals($sourceToken, $expectedSourceToken);
        $triggerToken = self::tokenCurrentViewMappingReceipt((string) ($options['current_view_mapping_trigger_token_next254'] ?? ($currentView['trigger_source'] ?? 'main@trigger-mapping-current-254')), 'view mapping trigger token');
        $expectedTriggerToken = self::tokenCurrentViewMappingReceipt((string) ($options['expected_current_view_mapping_trigger_token_next254'] ?? $triggerToken), 'expected view mapping trigger token');
        $triggerMatches = hash_equals($triggerToken, $expectedTriggerToken);
        $requiredColumns = self::columnListCurrentViewMappingReceipt($options['required_current_view_mapping_columns_next254'] ?? ['import_id', 'name', 'value', 'autoload_flag'], 'required mapping columns');
        $missingColumns = array_values(array_filter($requiredColumns, static fn (string $column): bool => !array_key_exists($column, $mapping)));
        $mappingRows = self::mappingRowsCurrentViewMappingReceipt($currentRows, $mapping, $sourceToken, $triggerToken, $requiredColumns);
        $required = self::mappingReceiptsCurrentViewMappingReceipt($mappingRows, $sourceToken, $triggerToken);
        $acknowledged = self::acknowledgedReceiptsCurrentViewMappingReceipt($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $mappingComplete = $required !== []
            && $mappingMatches
            && $sourceMatches
            && $triggerMatches
            && $missingColumns === []
            && $missing === []
            && $unexpected === [];
        $nextVisible = $baseVisible && $mappingComplete;
        $blockedReasons = self::blockedReasonsCurrentViewMappingReceipt(
            $base['blocked_reasons_next250'] ?? [],
            $baseVisible,
            $mappingMatches,
            $sourceMatches,
            $triggerMatches,
            $missingColumns,
            $missing,
            $unexpected,
        );

        $taggedCurrent = self::tagRowsCurrentViewMappingReceipt($currentRows, 'current-view-mapping', true, $mappingRows, $required, $sourceToken, $triggerToken, []);
        $taggedNext = self::tagRowsCurrentViewMappingReceipt($nextRows, 'next-source', $nextVisible, [], [], $sourceToken, $triggerToken, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next254' => self::statusCurrentViewMappingReceipt($nextVisible, $baseVisible, $mappingMatches, $sourceMatches, $triggerMatches, $missingColumns, $missing, $unexpected),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next254' => $baseVisible,
            'current_view_mapping_next254' => $mapping,
            'expected_current_view_mapping_next254' => $expectedMapping,
            'current_view_mapping_matches_next254' => $mappingMatches,
            'current_view_mapping_source_token_next254' => $sourceToken,
            'expected_current_view_mapping_source_token_next254' => $expectedSourceToken,
            'current_view_mapping_source_token_matches_next254' => $sourceMatches,
            'current_view_mapping_trigger_token_next254' => $triggerToken,
            'expected_current_view_mapping_trigger_token_next254' => $expectedTriggerToken,
            'current_view_mapping_trigger_token_matches_next254' => $triggerMatches,
            'required_current_view_mapping_columns_next254' => $requiredColumns,
            'missing_current_view_mapping_columns_next254' => $missingColumns,
            'current_view_mapping_rows_next254' => $mappingRows,
            'required_current_view_mapping_receipts_next254' => $required,
            'acknowledged_current_view_mapping_receipts_next254' => $acknowledged,
            'missing_current_view_mapping_receipts_next254' => $missing,
            'unexpected_current_view_mapping_receipts_next254' => $unexpected,
            'current_view_mapping_complete_next254' => $mappingComplete,
            'next_source_visible_after_current_view_mapping_next254' => $nextVisible,
            'current_source_rows_next254' => $taggedCurrent,
            'attempted_next_source_rows_next254' => $taggedNext,
            'visible_returning_rows_next254' => $visibleRows,
            'held_next_source_rows_next254' => $heldRows,
            'visible_returning_payloads_next254' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next254' => array_column($heldRows, 'returning'),
            'current_source_row_count_next254' => count($taggedCurrent),
            'attempted_next_source_row_count_next254' => count($taggedNext),
            'visible_row_count_next254' => count($visibleRows),
            'held_next_row_count_next254' => count($heldRows),
            'blocked_reasons_next254' => $blockedReasons,
            'current_view_mapping_plan_next254' => [
                'base_next_source_visible' => $baseVisible,
                'mapping_matches' => $mappingMatches,
                'source_token_matches' => $sourceMatches,
                'trigger_token_matches' => $triggerMatches,
                'required_columns' => $requiredColumns,
                'missing_columns' => $missingColumns,
                'required_receipts' => $required,
                'acknowledged_receipts' => $acknowledged,
                'missing_receipts' => $missing,
                'unexpected_receipts' => $unexpected,
                'mapping_complete' => $mappingComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert-mapping'
                    : 'hold-next-source-until-current-recursive-view-upsert-mapping',
            ],
            'yield_boundary_next254' => $nextVisible
                ? 'recursive-view-upsert-next254-current-view-mapping-then-next'
                : 'recursive-view-upsert-next254-current-view-mapping-fence-next',
            'dependency_closure_next254' => 'no-new-support-component-reuses-native-recursive-view-upsert-rowid-provenance-and-adds-current-view-mapping-receipts',
            'dependencies_next254' => array_values(array_unique(array_merge($base['dependencies_next250'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next254',
                'sqlite-instead-of-view-trigger-current-mapping-receipts',
                'wordpress-recursive-view-upsert-current-source-next254',
            ]))),
            'non_overlap_next254' => 'adds current view-column mapping and source-token receipt fencing after accepted next250 rowid provenance; avoids next250 rowid receipts, next247 sequence receipts, next244 commit watermarks, trigger RETURNING cursor/ticket/generation surfaces, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsCurrentViewMappingReceipt(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @return array<string,string>
     */
    private static function mappingCurrentViewMappingReceipt(mixed $mapping, string $label): array
    {
        if (!is_array($mapping)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} must be an array");
        }
        $out = [];
        foreach ($mapping as $source => $target) {
            if (!is_string($source) || !is_string($target) || !self::isColumnCurrentViewMappingReceipt($source) || !self::isColumnCurrentViewMappingReceipt($target)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} contains malformed columns");
            }
            $out[$source] = $target;
        }
        ksort($out);

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function columnListCurrentViewMappingReceipt(mixed $columns, string $label): array
    {
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} must be a non-empty list");
        }
        $out = [];
        foreach ($columns as $column) {
            if (!is_string($column) || !self::isColumnCurrentViewMappingReceipt($column)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} contains a malformed column");
            }
            $out[] = $column;
        }

        return array_values(array_unique($out));
    }

    private static function tokenCurrentViewMappingReceipt(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} is malformed");
        }

        return $value;
    }

    private static function isColumnCurrentViewMappingReceipt(string $value): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,string> $mapping
     * @param list<string> $requiredColumns
     * @return list<array<string,mixed>>
     */
    private static function mappingRowsCurrentViewMappingReceipt(array $rows, array $mapping, string $sourceToken, string $triggerToken, array $requiredColumns): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $mapped = [];
            foreach ($requiredColumns as $sourceColumn) {
                $targetColumn = $mapping[$sourceColumn] ?? null;
                $mapped[$sourceColumn] = [
                    'target' => $targetColumn,
                    'value' => $targetColumn === null ? null : ($returning[self::returningAliasCurrentViewMappingReceipt($sourceColumn, $targetColumn)] ?? $returning[$targetColumn] ?? null),
                ];
            }
            $out[] = [
                'ordinal' => $index,
                'returning_name' => $returning['name'] ?? null,
                'returning_value' => $returning['value'] ?? null,
                'rowid_receipt' => $row['current_source_rowid_receipt_next250'] ?? null,
                'source_token' => $sourceToken,
                'trigger_token' => $triggerToken,
                'mapping' => $mapped,
            ];
        }

        return $out;
    }

    private static function returningAliasCurrentViewMappingReceipt(string $sourceColumn, string $targetColumn): string
    {
        if ($sourceColumn === 'name' && $targetColumn === 'option_name') {
            return 'name';
        }
        if ($sourceColumn === 'value' && $targetColumn === 'option_value') {
            return 'value';
        }
        if ($sourceColumn === 'import_id' && $targetColumn === 'option_id') {
            return 'import_id';
        }
        if ($sourceColumn === 'autoload_flag' && $targetColumn === 'autoload') {
            return 'autoload_flag';
        }

        return $targetColumn;
    }

    /**
     * @param list<array<string,mixed>> $mappingRows
     * @return list<string>
     */
    private static function mappingReceiptsCurrentViewMappingReceipt(array $mappingRows, string $sourceToken, string $triggerToken): array
    {
        $receipts = [];
        foreach ($mappingRows as $row) {
            $receipts[] = substr(hash('sha256', json_encode([$sourceToken, $triggerToken, $row], JSON_THROW_ON_ERROR)), 0, 52);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedReceiptsCurrentViewMappingReceipt(array $options, array $required): array
    {
        if (($options['auto_ack_current_view_mapping_receipts_next254'] ?? false) === true) {
            return $required;
        }

        return self::receiptListCurrentViewMappingReceipt($options['acknowledged_current_view_mapping_receipts_next254'] ?? [], 'acknowledged view mapping receipts');
    }

    /**
     * @return list<string>
     */
    private static function receiptListCurrentViewMappingReceipt(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{52}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next254 {$label} contain a malformed mapping receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $mappingRows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsCurrentViewMappingReceipt(array $rows, string $phase, bool $visible, array $mappingRows, array $receipts, string $sourceToken, string $triggerToken, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'view_mapping_phase_next254' => $phase,
                'current_view_mapping_source_token_next254' => $sourceToken,
                'current_view_mapping_trigger_token_next254' => $triggerToken,
                'current_view_mapping_row_next254' => $mappingRows[$index] ?? null,
                'current_view_mapping_receipt_next254' => $receipts[$index] ?? null,
                'visible_after_current_view_mapping_next254' => $visible,
                'held_by_current_view_mapping_reasons_next254' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missingColumns
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function blockedReasonsCurrentViewMappingReceipt(mixed $baseReasons, bool $baseVisible, bool $mappingMatches, bool $sourceMatches, bool $triggerMatches, array $missingColumns, array $missing, array $unexpected): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next254 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next250-rowid-provenance-not-published';
        }
        if (!$mappingMatches) {
            $reasons[] = 'current-view-mapping-mismatch';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-view-mapping-source-token-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-view-mapping-trigger-token-mismatch';
        }
        if ($missingColumns !== []) {
            $reasons[] = 'current-view-mapping-required-columns-missing';
        }
        if ($missing !== []) {
            $reasons[] = 'current-view-mapping-receipts-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-view-mapping-receipts-unexpected';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missingColumns
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function statusCurrentViewMappingReceipt(bool $nextVisible, bool $baseVisible, bool $mappingMatches, bool $sourceMatches, bool $triggerMatches, array $missingColumns, array $missing, array $unexpected): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next254-view-mapping-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next254-base-held';
        }
        if (!$mappingMatches) {
            return 'trigger-recursive-view-upsert-current-source-next254-view-mapping-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-upsert-current-source-next254-source-token-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-upsert-current-source-next254-trigger-token-held';
        }
        if ($missingColumns !== []) {
            return 'trigger-recursive-view-upsert-current-source-next254-columns-held';
        }
        if ($missing !== []) {
            return 'trigger-recursive-view-upsert-current-source-next254-receipts-missing-held';
        }
        if ($unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next254-receipts-unexpected-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next254-held';
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentSourceUpsertReturningDrain(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentPredicateDecisionReceipt(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_where_next252'] ?? false);
        $currentRows = self::currentSourceReturningDrainRows($base['current_source_rows_next252'] ?? [], 'current rows');
        $nextRows = self::currentSourceReturningDrainRows($base['attempted_next_source_rows_next252'] ?? [], 'next rows');
        $cursor = self::currentSourceReturningToken((string) ($options['current_source_returning_cursor_next255'] ?? 'wp.returning.current.upsert.cursor.255'), 'returning cursor');
        $expectedCursor = self::currentSourceReturningToken((string) ($options['expected_current_source_returning_cursor_next255'] ?? $cursor), 'expected returning cursor');
        $cursorMatches = hash_equals($cursor, $expectedCursor);
        $payloads = self::currentSourceReturningPayloads($currentRows);
        $aliases = self::currentSourceReturningAliases($options['required_current_source_returning_aliases_next255'] ?? null, $payloads);
        $missingAliases = self::missingCurrentSourceReturningAliases($payloads, $aliases);
        $requiredReceipts = self::currentSourceReturningReceipts($currentRows, $payloads, $aliases, $cursor);
        $acknowledgedReceipts = self::acknowledgedCurrentSourceReturningReceipts($options, $requiredReceipts);
        $missingReceipts = array_values(array_diff($requiredReceipts, $acknowledgedReceipts));
        $unexpectedReceipts = array_values(array_diff($acknowledgedReceipts, $requiredReceipts));
        $requireDrainOrder = (bool) ($options['require_current_source_returning_order_next255'] ?? true);
        $drainOrderMatches = !$requireDrainOrder || $requiredReceipts === $acknowledgedReceipts;
        $returningComplete = $requiredReceipts !== []
            && $cursorMatches
            && $missingAliases === []
            && $missingReceipts === []
            && $unexpectedReceipts === []
            && $drainOrderMatches;
        $nextVisible = $baseVisible && $returningComplete;
        $blockedReasons = self::currentSourceReturningBlockedReasons(
            $base['blocked_reasons_next252'] ?? [],
            $baseVisible,
            $cursorMatches,
            $missingAliases,
            $missingReceipts,
            $unexpectedReceipts,
            $requireDrainOrder,
            $drainOrderMatches,
        );

        $currentRows = self::tagCurrentSourceReturningRows($currentRows, 'current-returning-drain', true, $requiredReceipts, $cursor, $aliases, []);
        $nextRows = self::tagCurrentSourceReturningRows($nextRows, 'next-source', $nextVisible, [], $cursor, $aliases, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($currentRows, $nextRows) : $currentRows;
        $heldRows = $nextVisible ? [] : $nextRows;

        return [
            'status_next255' => self::currentSourceReturningStatus($baseVisible, $cursorMatches, $missingAliases, $missingReceipts, $unexpectedReceipts, $requireDrainOrder, $drainOrderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next255' => $baseVisible,
            'current_source_returning_cursor_next255' => $cursor,
            'expected_current_source_returning_cursor_next255' => $expectedCursor,
            'current_source_returning_cursor_matches_next255' => $cursorMatches,
            'required_current_source_returning_aliases_next255' => $aliases,
            'missing_current_source_returning_aliases_next255' => $missingAliases,
            'current_source_returning_payloads_next255' => $payloads,
            'required_current_source_returning_receipts_next255' => $requiredReceipts,
            'acknowledged_current_source_returning_receipts_next255' => $acknowledgedReceipts,
            'missing_current_source_returning_receipts_next255' => $missingReceipts,
            'unexpected_current_source_returning_receipts_next255' => $unexpectedReceipts,
            'require_current_source_returning_order_next255' => $requireDrainOrder,
            'current_source_returning_order_matches_next255' => $drainOrderMatches,
            'current_source_returning_complete_next255' => $returningComplete,
            'next_source_visible_after_current_source_returning_next255' => $nextVisible,
            'current_source_rows_next255' => $currentRows,
            'attempted_next_source_rows_next255' => $nextRows,
            'visible_returning_rows_next255' => $visibleRows,
            'held_next_source_rows_next255' => $heldRows,
            'visible_returning_payloads_next255' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next255' => array_column($heldRows, 'returning'),
            'visible_row_count_next255' => count($visibleRows),
            'held_next_row_count_next255' => count($heldRows),
            'blocked_reasons_next255' => $blockedReasons,
            'current_source_returning_drain_plan_next255' => [
                'base_next_source_visible' => $baseVisible,
                'cursor_matches' => $cursorMatches,
                'required_aliases' => $aliases,
                'missing_aliases' => $missingAliases,
                'required_receipts' => $requiredReceipts,
                'acknowledged_receipts' => $acknowledgedReceipts,
                'missing_receipts' => $missingReceipts,
                'unexpected_receipts' => $unexpectedReceipts,
                'require_order' => $requireDrainOrder,
                'order_matches' => $drainOrderMatches,
                'returning_complete' => $returningComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-upsert-returning-drain'
                    : 'hold-next-source-until-current-upsert-returning-drain',
            ],
            'yield_boundary_next255' => $nextVisible
                ? 'recursive-view-upsert-next255-current-returning-drain-then-next'
                : 'recursive-view-upsert-next255-current-returning-drain-fence-next',
            'dependency_closure_next255' => 'no-new-support-component-reuses-native-recursive-view-upsert-where-receipts-and-adds-returning-cursor-drain-fencing',
            'dependencies_next255' => array_values(array_unique(array_merge($base['dependencies_next252'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next255',
                'sqlite-instead-of-view-upsert-returning-cursor-drain',
                'wordpress-recursive-view-upsert-current-source-next255',
            ]))),
            'non_overlap_next255' => 'adds current-source UPSERT RETURNING cursor drain receipts after accepted next252 DO UPDATE WHERE decisions; avoids next251 change counters, next252 predicate receipts, recursive view RETURNING ticket/generation surfaces, row-value RETURNING, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function currentSourceReturningDrainRows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next255 {$label} must be a list");
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function currentSourceReturningPayloads(array $rows): array
    {
        $payloads = [];
        foreach ($rows as $row) {
            if (!isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException('SQLite recursive view UPSERT next255 current row is missing a RETURNING payload');
            }
            $payloads[] = $row['returning'];
        }
        if ($payloads === []) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next255 requires current RETURNING payloads');
        }

        return $payloads;
    }

    /**
     * @param mixed $aliases
     * @param list<array<string,mixed>> $payloads
     * @return list<string>
     */
    private static function currentSourceReturningAliases(mixed $aliases, array $payloads): array
    {
        if ($aliases === null) {
            return array_values(array_map('strval', array_keys($payloads[0])));
        }
        if (!is_array($aliases) || !array_is_list($aliases) || $aliases === []) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next255 returning aliases must be a non-empty list');
        }
        foreach ($aliases as $alias) {
            if (!is_string($alias) || $alias === '' || preg_match('/\s/', $alias) === 1) {
                throw new InvalidArgumentException('SQLite recursive view UPSERT next255 returning alias is malformed');
            }
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @param list<array<string,mixed>> $payloads
     * @param list<string> $aliases
     * @return list<string>
     */
    private static function missingCurrentSourceReturningAliases(array $payloads, array $aliases): array
    {
        $missing = [];
        foreach ($payloads as $payload) {
            foreach ($aliases as $alias) {
                if (!array_key_exists($alias, $payload)) {
                    $missing[] = $alias;
                }
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $payloads
     * @param list<string> $aliases
     * @return list<string>
     */
    private static function currentSourceReturningReceipts(array $rows, array $payloads, array $aliases, string $cursor): array
    {
        $receipts = [];
        foreach ($payloads as $index => $payload) {
            $selected = [];
            foreach ($aliases as $alias) {
                $selected[$alias] = $payload[$alias] ?? null;
            }
            $receipts[] = substr(hash('sha256', json_encode([
                $cursor,
                $rows[$index]['ordinal'] ?? $index,
                $rows[$index]['current_source_upsert_where_receipt_next252'] ?? null,
                $selected,
            ], JSON_THROW_ON_ERROR)), 0, 48);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedCurrentSourceReturningReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_returning_next255'] ?? false) === true) {
            return $required;
        }

        return self::currentSourceReturningReceiptList($options['acknowledged_current_source_returning_receipts_next255'] ?? [], 'acknowledged returning receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function currentSourceReturningReceiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next255 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{48}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next255 {$label} contain a malformed returning receipt");
            }
        }

        return array_values(array_unique($values));
    }

    private static function currentSourceReturningToken(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next255 {$label} is malformed");
        }

        return $token;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $aliases
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentSourceReturningRows(array $rows, string $phase, bool $visible, array $receipts, string $cursor, array $aliases, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'returning_drain_phase_next255' => $phase,
                'current_source_returning_cursor_next255' => $cursor,
                'current_source_returning_aliases_next255' => $aliases,
                'current_source_returning_receipt_next255' => $receipts[$index] ?? null,
                'visible_after_current_source_returning_next255' => $visible,
                'held_by_current_source_returning_reasons_next255' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missingAliases
     * @param list<string> $missingReceipts
     * @param list<string> $unexpectedReceipts
     * @return list<string>
     */
    private static function currentSourceReturningBlockedReasons(
        mixed $baseReasons,
        bool $baseVisible,
        bool $cursorMatches,
        array $missingAliases,
        array $missingReceipts,
        array $unexpectedReceipts,
        bool $requireDrainOrder,
        bool $drainOrderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next255 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next252-current-source-upsert-where-not-published';
        }
        if (!$cursorMatches) {
            $reasons[] = 'current-source-returning-cursor-mismatch';
        }
        if ($missingAliases !== []) {
            $reasons[] = 'current-source-returning-alias-missing';
        }
        if ($missingReceipts !== []) {
            $reasons[] = 'current-source-returning-receipt-missing';
        }
        if ($unexpectedReceipts !== []) {
            $reasons[] = 'current-source-returning-receipt-unexpected';
        }
        if ($missingReceipts === [] && $unexpectedReceipts === [] && $requireDrainOrder && !$drainOrderMatches) {
            $reasons[] = 'current-source-returning-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missingAliases
     * @param list<string> $missingReceipts
     * @param list<string> $unexpectedReceipts
     */
    private static function currentSourceReturningStatus(
        bool $baseVisible,
        bool $cursorMatches,
        array $missingAliases,
        array $missingReceipts,
        array $unexpectedReceipts,
        bool $requireDrainOrder,
        bool $drainOrderMatches,
        bool $nextVisible,
    ): string {
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next255-base-held';
        }
        if (!$cursorMatches) {
            return 'trigger-recursive-view-upsert-current-source-next255-returning-cursor-held';
        }
        if ($missingAliases !== []) {
            return 'trigger-recursive-view-upsert-current-source-next255-returning-alias-held';
        }
        if ($missingReceipts !== [] || $unexpectedReceipts !== []) {
            return 'trigger-recursive-view-upsert-current-source-next255-returning-receipts-held';
        }
        if ($requireDrainOrder && !$drainOrderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next255-returning-order-held';
        }

        return $nextVisible
            ? 'trigger-recursive-view-upsert-current-source-next255-returning-released'
            : 'trigger-recursive-view-upsert-current-source-next255-held';
    }


    /* Variant consolidated from SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function executeCurrentSourceViewUpsertHandoff(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentViewMaterializationReceipt(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_view_materialization_next253'] ?? false);
        $materialized = self::rowsForCurrentSourceViewUpsertHandoff($base['current_source_view_materialization_rows_next253'] ?? [], 'materialized rows');
        $currentRows = self::rowsForCurrentSourceViewUpsertHandoff($base['current_source_rows_next253'] ?? [], 'current source rows');
        $nextRows = self::rowsForCurrentSourceViewUpsertHandoff($base['attempted_next_source_rows_next253'] ?? [], 'attempted next source rows');
        $handoffToken = self::currentSourceViewUpsertHandoffToken((string) ($options['current_source_view_upsert_handoff_token_next256'] ?? 'wp.current.source.view.upsert.handoff.256'), 'handoff token');
        $expectedToken = self::currentSourceViewUpsertHandoffToken((string) ($options['expected_current_source_view_upsert_handoff_token_next256'] ?? $handoffToken), 'expected handoff token');
        $batchSize = self::positiveCurrentSourceViewUpsertHandoffInt($options['current_source_view_upsert_handoff_batch_size_next256'] ?? 1, 'handoff batch size');
        $requireOrder = (bool) ($options['require_current_source_view_upsert_handoff_order_next256'] ?? true);
        $batches = self::currentSourceViewUpsertHandoffBatches($materialized, $handoffToken, $batchSize);
        $required = array_column($batches, 'handoff_receipt');
        $acknowledged = self::acknowledgedCurrentSourceViewUpsertHandoffReceipts($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $orderMatches = !$requireOrder || $missing !== [] || $unexpected !== [] || $required === $acknowledged;
        $tokenMatches = hash_equals($handoffToken, $expectedToken);
        $complete = $required !== []
            && $baseVisible
            && $tokenMatches
            && $missing === []
            && $unexpected === []
            && $orderMatches;
        $blockedReasons = self::currentSourceViewUpsertHandoffBlockedReasons(
            $base['blocked_reasons_next253'] ?? [],
            $baseVisible,
            $tokenMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $currentTagged = self::tagRowsWithCurrentSourceViewUpsertHandoff($currentRows, true, $handoffToken, $batches, $blockedReasons);
        $nextTagged = self::tagRowsWithCurrentSourceViewUpsertHandoff($nextRows, $complete, $handoffToken, [], $complete ? [] : $blockedReasons);
        $visibleRows = $complete ? array_merge($currentTagged, $nextTagged) : $currentTagged;
        $heldRows = $complete ? [] : $nextTagged;

        return [
            'status_next256' => self::currentSourceViewUpsertHandoffStatus($complete, $baseVisible, $tokenMatches, $missing, $unexpected, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next256' => $baseVisible,
            'current_source_view_upsert_handoff_token_next256' => $handoffToken,
            'expected_current_source_view_upsert_handoff_token_next256' => $expectedToken,
            'current_source_view_upsert_handoff_token_matches_next256' => $tokenMatches,
            'current_source_view_upsert_handoff_batch_size_next256' => $batchSize,
            'current_source_view_upsert_handoff_batches_next256' => $batches,
            'required_current_source_view_upsert_handoff_receipts_next256' => $required,
            'acknowledged_current_source_view_upsert_handoff_receipts_next256' => $acknowledged,
            'missing_current_source_view_upsert_handoff_receipts_next256' => $missing,
            'unexpected_current_source_view_upsert_handoff_receipts_next256' => $unexpected,
            'require_current_source_view_upsert_handoff_order_next256' => $requireOrder,
            'current_source_view_upsert_handoff_order_matches_next256' => $orderMatches,
            'current_source_view_upsert_handoff_complete_next256' => $complete,
            'next_source_visible_after_current_source_view_upsert_handoff_next256' => $complete,
            'current_source_rows_next256' => $currentTagged,
            'attempted_next_source_rows_next256' => $nextTagged,
            'visible_returning_rows_next256' => $visibleRows,
            'held_next_source_rows_next256' => $heldRows,
            'visible_returning_payloads_next256' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next256' => array_column($heldRows, 'returning'),
            'visible_row_count_next256' => count($visibleRows),
            'held_next_row_count_next256' => count($heldRows),
            'blocked_reasons_next256' => $blockedReasons,
            'current_source_view_upsert_handoff_plan_next256' => [
                'base_next_source_visible' => $baseVisible,
                'token_matches' => $tokenMatches,
                'batch_size' => $batchSize,
                'batch_count' => count($batches),
                'required_receipts' => $required,
                'acknowledged_receipts' => $acknowledged,
                'missing_receipts' => $missing,
                'unexpected_receipts' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'handoff_complete' => $complete,
                'decision' => $complete
                    ? 'publish-next-source-after-current-recursive-view-upsert-handoff'
                    : 'hold-next-source-until-current-recursive-view-upsert-handoff',
            ],
            'yield_boundary_next256' => $complete
                ? 'recursive-view-upsert-next256-current-handoff-then-next'
                : 'recursive-view-upsert-next256-current-handoff-fence-next',
            'dependency_closure_next256' => 'no-new-support-component-reuses-native-recursive-view-upsert-materialization-and-adds-current-source-handoff-batch-receipts',
            'dependencies_next256' => array_values(array_unique(array_merge($base['dependencies_next253'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next256',
                'sqlite-instead-of-view-trigger-upsert-current-source-handoff',
                'wordpress-recursive-view-upsert-current-source-next256',
            ]))),
            'non_overlap_next256' => 'adds ordered batch-level current-source handoff receipts after accepted next253 recursive view UPSERT materialization; avoids next253 materialized projection receipts, next250 rowid provenance, next247 statement sequence, recursive view RETURNING-only, row-value/window RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite evidence clusters',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowsForCurrentSourceViewUpsertHandoff(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next256 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next256 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function currentSourceViewUpsertHandoffBatches(array $rows, string $token, int $batchSize): array
    {
        $batches = [];
        foreach (array_chunk($rows, $batchSize) as $batchIndex => $batchRows) {
            $ordinals = array_map(static fn (array $row): int => (int) ($row['ordinal'] ?? -1), $batchRows);
            $projectionHashes = array_map(static fn (array $row): string => (string) ($row['projection_hash'] ?? ''), $batchRows);
            $rowidReceipts = array_map(static fn (array $row): ?string => isset($row['rowid_receipt_next250']) ? (string) $row['rowid_receipt_next250'] : null, $batchRows);
            foreach ($ordinals as $ordinal) {
                if ($ordinal < 0) {
                    throw new InvalidArgumentException('SQLite recursive view UPSERT next256 handoff ordinal is malformed');
                }
            }
            foreach ($projectionHashes as $hash) {
                if (preg_match('/^[a-f0-9]{48}$/', $hash) !== 1) {
                    throw new InvalidArgumentException('SQLite recursive view UPSERT next256 projection hash is malformed');
                }
            }
            $receipt = substr(hash('sha256', json_encode([$token, $batchIndex, $ordinals, $projectionHashes, $rowidReceipts], JSON_THROW_ON_ERROR)), 0, 52);
            $batches[] = [
                'batch_index' => $batchIndex,
                'first_ordinal' => min($ordinals),
                'last_ordinal' => max($ordinals),
                'row_count' => count($batchRows),
                'projection_hashes' => $projectionHashes,
                'rowid_receipts_next250' => $rowidReceipts,
                'handoff_receipt' => $receipt,
            ];
        }

        return $batches;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedCurrentSourceViewUpsertHandoffReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_view_upsert_handoff_next256'] ?? false) === true) {
            return $required;
        }

        return self::currentSourceViewUpsertHandoffReceiptList($options['acknowledged_current_source_view_upsert_handoff_receipts_next256'] ?? [], 'acknowledged handoff receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function currentSourceViewUpsertHandoffReceiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next256 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{52}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next256 {$label} contain a malformed receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $batches
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRowsWithCurrentSourceViewUpsertHandoff(array $rows, bool $visible, string $token, array $batches, array $reasons): array
    {
        $receiptByOrdinal = [];
        foreach ($batches as $batch) {
            for ($ordinal = (int) $batch['first_ordinal']; $ordinal <= (int) $batch['last_ordinal']; $ordinal++) {
                $receiptByOrdinal[$ordinal] = $batch['handoff_receipt'];
            }
        }

        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'current_source_view_upsert_handoff_token_next256' => $token,
                'current_source_view_upsert_handoff_receipt_next256' => $receiptByOrdinal[$index] ?? null,
                'visible_after_current_source_view_upsert_handoff_next256' => $visible,
                'held_by_current_source_view_upsert_handoff_reasons_next256' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @return list<string>
     */
    private static function currentSourceViewUpsertHandoffBlockedReasons(mixed $baseReasons, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next256 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next253-view-materialization-not-published';
        }
        if (!$tokenMatches) {
            $reasons[] = 'current-source-view-upsert-handoff-token-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-view-upsert-handoff-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-view-upsert-handoff-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-view-upsert-handoff-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function currentSourceViewUpsertHandoffToken(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next256 {$label} is malformed");
        }

        return $value;
    }

    private static function positiveCurrentSourceViewUpsertHandoffInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next256 {$label} must be positive");
        }

        return $value;
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function currentSourceViewUpsertHandoffStatus(bool $complete, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($complete) {
            return 'trigger-recursive-view-upsert-current-source-next256-handoff-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next256-base-held';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-upsert-current-source-next256-handoff-token-held';
        }
        if ($missing !== []) {
            return 'trigger-recursive-view-upsert-current-source-next256-handoff-missing-held';
        }
        if ($unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next256-handoff-unexpected-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next256-handoff-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next256-held';
    }
}
