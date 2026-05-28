<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext233Plan
{
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
    public static function execute(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext229Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rows($base['current_source_rows_next229'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next229'] ?? [], 'attempted next source rows');
        $baseVisible = (bool) ($base['next_source_visible_after_current_returning_generation_next229'] ?? false);
        $upsertToken = self::token((string) ($options['current_upsert_source_token_next233'] ?? 'wp.current.upsert.source.233'), 'current upsert source token');
        $expectedUpsertToken = self::token((string) ($options['expected_current_upsert_source_token_next233'] ?? $upsertToken), 'expected current upsert source token');
        $viewSource = self::token((string) ($options['current_upsert_view_source_next233'] ?? ($currentView['source'] ?? 'main@view-upsert-233-current')), 'current upsert view source');
        $expectedViewSource = self::token((string) ($options['expected_current_upsert_view_source_next233'] ?? $viewSource), 'expected current upsert view source');
        $triggerSource = self::token((string) ($options['current_upsert_trigger_source_next233'] ?? ($currentView['trigger_source'] ?? 'main@trigger-upsert-233-current')), 'current upsert trigger source');
        $expectedTriggerSource = self::token((string) ($options['expected_current_upsert_trigger_source_next233'] ?? $triggerSource), 'expected current upsert trigger source');
        $conflictTarget = self::identifierList($options['current_upsert_conflict_target_next233'] ?? ['option_name'], 'conflict target');
        $updateColumns = self::identifierList($options['current_upsert_update_columns_next233'] ?? ['option_value', 'autoload'], 'update columns');
        $requireOrder = (bool) ($options['require_current_upsert_order_next233'] ?? true);

        $requiredSeals = self::upsertSeals($currentRows, $upsertToken, $viewSource, $triggerSource, $conflictTarget, $updateColumns);
        $acknowledgedSeals = self::acknowledgedSeals($options, $requiredSeals);
        $missingSeals = array_values(array_diff($requiredSeals, $acknowledgedSeals));
        $unexpectedSeals = array_values(array_diff($acknowledgedSeals, $requiredSeals));
        $upsertMatches = hash_equals($upsertToken, $expectedUpsertToken);
        $viewMatches = hash_equals($viewSource, $expectedViewSource);
        $triggerMatches = hash_equals($triggerSource, $expectedTriggerSource);
        $orderMatches = !$requireOrder || $requiredSeals === $acknowledgedSeals;
        $upsertEvents = self::upsertEvents($currentRows);
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
        $blockedReasons = self::blockedReasons(
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

        $currentRows = self::tagRows($currentRows, 'current', true, $requiredSeals, $upsertToken, $viewSource, $triggerSource, $conflictTarget, $updateColumns, []);
        $nextRows = self::tagRows($nextRows, 'next', $nextVisible, [], $upsertToken, $viewSource, $triggerSource, $conflictTarget, $updateColumns, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_upsert_source_next233'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_upsert_source_next233'],
        ));

        return [
            'status_next233' => self::status($nextVisible, $baseVisible, $hasUpsertRows, $upsertMatches, $viewMatches, $triggerMatches, $missingSeals, $unexpectedSeals, $requireOrder, $orderMatches),
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
    private static function upsertSeals(array $rows, string $upsertToken, string $viewSource, string $triggerSource, array $conflictTarget, array $updateColumns): array
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
    private static function upsertEvents(array $rows): array
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
    private static function acknowledgedSeals(array $options, array $required): array
    {
        if (($options['auto_ack_current_upsert_seals_next233'] ?? false) === true) {
            return $required;
        }

        return self::sealList($options['acknowledged_current_upsert_seals_next233'] ?? [], 'acknowledged current upsert seals');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function sealList(mixed $values, string $label): array
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
    private static function rows(mixed $rows, string $label): array
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
    private static function tagRows(
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
    private static function blockedReasons(
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
    private static function identifierList(mixed $values, string $label): array
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

    private static function token(string $value, string $label): string
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
    private static function status(
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
}
