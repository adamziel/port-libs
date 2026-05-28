<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext232Plan
{
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
    public static function execute(
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
        $base = SQLiteTriggerUpsertRecursiveViewCurrentSourceNext148Plan::execute(
            $rows,
            $currentViewRows,
            $nextViewRows,
            $view,
            $uniqueColumns,
            $triggers,
            $baseOptions,
        );

        $upsertSource = self::token((string) ($options['current_upsert_source_next232'] ?? 'wp.current.upsert.source.232'), 'current upsert source');
        $viewSource = self::token((string) ($options['current_view_source_next232'] ?? (string) ($base['current_source'] ?? 'main@cookie232-current')), 'current view source');
        $expectedViewSource = self::token((string) ($options['expected_current_view_source_next232'] ?? $viewSource), 'expected current view source');
        $triggerProgram = self::token((string) ($options['current_trigger_program_next232'] ?? 'wp.current.recursive.trigger.program.232'), 'current trigger program');
        $expectedTriggerProgram = self::token((string) ($options['expected_current_trigger_program_next232'] ?? $triggerProgram), 'expected current trigger program');
        $requireOrder = (bool) ($options['require_current_upsert_conflict_order_next232'] ?? true);

        $currentYield = self::rows($base['current_yield_stream'] ?? [], 'current yield stream');
        $attemptedNextYield = self::rows($base['attempted_next_yield_stream'] ?? [], 'attempted next yield stream');
        $requiredSeals = self::conflictSeals($currentYield, $upsertSource, $viewSource, $triggerProgram);
        $acknowledgedSeals = self::acknowledgedSeals($options, $requiredSeals);
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
            $next = SQLiteTriggerUpsertRecursiveViewCurrentSourceNext148Plan::execute(
                $rows,
                $currentViewRows,
                $nextViewRows,
                $view,
                $uniqueColumns,
                $triggers,
                $releaseOptions,
            );
        }

        $blockedReasons = self::blockedReasons($viewMatches, $triggerMatches, $missingSeals, $unexpectedSeals, $requireOrder, $orderMatches);
        $currentYield = self::tagRows($currentYield, 'current', true, $requiredSeals, $upsertSource, $viewSource, $triggerProgram, []);
        $attemptedNextYield = self::tagRows($attemptedNextYield, 'next', $conflictComplete, [], $upsertSource, $viewSource, $triggerProgram, $conflictComplete ? [] : $blockedReasons);
        $currentReturning = self::tagRows(self::rows($base['current_returning_rows'] ?? [], 'current returning rows'), 'current', true, $requiredSeals, $upsertSource, $viewSource, $triggerProgram, []);
        $attemptedNextReturning = self::tagRows(self::rows($base['attempted_next_returning_rows'] ?? [], 'attempted next returning rows'), 'next', $conflictComplete, [], $upsertSource, $viewSource, $triggerProgram, $conflictComplete ? [] : $blockedReasons);

        return [
            'status_next232' => self::status($conflictComplete, $viewMatches, $triggerMatches, $missingSeals, $unexpectedSeals, $requireOrder, $orderMatches),
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
    private static function conflictSeals(array $rows, string $source, string $viewSource, string $triggerProgram): array
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
    private static function acknowledgedSeals(array $options, array $required): array
    {
        if (($options['auto_ack_current_upsert_conflict_seals_next232'] ?? false) === true) {
            return $required;
        }

        return self::sealList($options['acknowledged_current_upsert_conflict_seals_next232'] ?? [], 'acknowledged current upsert conflict seals');
    }

    /** @param mixed $values @return list<string> */
    private static function sealList(mixed $values, string $label): array
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
    private static function rows(mixed $rows, string $label): array
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
    private static function tagRows(array $rows, string $phase, bool $visible, array $seals, string $source, string $viewSource, string $triggerProgram, array $reasons): array
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
    private static function blockedReasons(bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
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
    private static function status(bool $complete, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
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

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next232 {$label} is malformed");
        }

        return $token;
    }
}
