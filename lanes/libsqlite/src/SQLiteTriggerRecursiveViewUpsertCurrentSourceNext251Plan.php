<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext251Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext247Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_statement_sequence_next247'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next247'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next247'] ?? [], 'attempted next source rows');
        $statementChanges = self::counter($options['current_source_changes_next251'] ?? count($currentRows), 'statement changes');
        $expectedChanges = self::counter($options['expected_current_source_changes_next251'] ?? count($currentRows), 'expected statement changes');
        $totalChanges = self::counter($options['current_source_total_changes_next251'] ?? ($statementChanges + count($baseRows)), 'total changes');
        $minimumTotal = self::counter($options['minimum_current_source_total_changes_next251'] ?? $statementChanges, 'minimum total changes');
        $viewCookie = self::token((string) ($options['current_source_change_view_cookie_next251'] ?? ($base['current_source_sequence_view_cookie_next247'] ?? ($currentView['source'] ?? 'main@view-cookie-251-current'))), 'view cookie');
        $triggerCookie = self::token((string) ($options['current_source_change_trigger_cookie_next251'] ?? ($base['current_source_sequence_trigger_cookie_next247'] ?? ($currentView['trigger_source'] ?? 'main@trigger-cookie-251-current'))), 'trigger cookie');
        $counterCursor = self::token((string) ($options['current_source_change_counter_cursor_next251'] ?? 'wp.returning.current.change.counter.251'), 'change counter cursor');
        $requireTotal = (bool) ($options['require_total_changes_monotonic_next251'] ?? true);

        $changesMatch = $statementChanges === $expectedChanges;
        $totalMonotonic = $totalChanges >= $minimumTotal && $totalChanges >= $statementChanges;
        $required = self::changeReceipts($currentRows, $statementChanges, $totalChanges, $viewCookie, $triggerCookie, $counterCursor);
        $acknowledged = self::acknowledgedReceipts($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $changesComplete = $required !== []
            && $changesMatch
            && (!$requireTotal || $totalMonotonic)
            && $missing === []
            && $unexpected === [];
        $nextVisible = $baseVisible && $changesComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next247'] ?? [],
            $baseVisible,
            $changesMatch,
            $requireTotal,
            $totalMonotonic,
            $missing,
            $unexpected,
        );

        $taggedCurrent = self::tagRows($currentRows, 'current-change-counter', true, $required, $statementChanges, $expectedChanges, $totalChanges, $minimumTotal, $viewCookie, $triggerCookie, $counterCursor, []);
        $taggedNext = self::tagRows($nextRows, 'next-source', $nextVisible, [], $statementChanges, $expectedChanges, $totalChanges, $minimumTotal, $viewCookie, $triggerCookie, $counterCursor, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next251' => self::status($nextVisible, $baseVisible, $changesMatch, $requireTotal, $totalMonotonic, $missing, $unexpected),
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
    private static function changeReceipts(array $rows, int $changes, int $totalChanges, string $viewCookie, string $triggerCookie, string $cursor): array
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
    private static function acknowledgedReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_change_counters_next251'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_change_receipts_next251'] ?? [], 'acknowledged change-counter receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
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
    private static function rows(mixed $rows, string $label): array
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
    private static function tagRows(array $rows, string $phase, bool $visible, array $receipts, int $changes, int $expectedChanges, int $totalChanges, int $minimumTotal, string $viewCookie, string $triggerCookie, string $cursor, array $reasons): array
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
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $changesMatch, bool $requireTotal, bool $totalMonotonic, array $missing, array $unexpected): array
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
    private static function status(bool $nextVisible, bool $baseVisible, bool $changesMatch, bool $requireTotal, bool $totalMonotonic, array $missing, array $unexpected): string
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

    private static function counter(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next251 {$label} must be a non-negative integer");
        }

        return $value;
    }

    private static function token(string $token, string $label): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]{3,180}$/', $token)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next251 invalid {$label}");
        }

        return $token;
    }
}
