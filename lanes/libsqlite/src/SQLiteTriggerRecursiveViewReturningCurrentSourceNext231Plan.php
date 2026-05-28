<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext231Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext222Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $cursor = self::token((string) ($options['current_source_cursor_next231'] ?? 'wp.returning.current.cursor.231'), 'current source cursor');
        $closeToken = self::token((string) ($options['current_source_close_token_next231'] ?? 'wp.current.source.close.231'), 'current source close token');
        $expectedCloseToken = self::token((string) ($options['expected_current_source_close_token_next231'] ?? $closeToken), 'expected current source close token');
        $viewCookie = self::token((string) ($options['current_view_cookie_next231'] ?? (string) ($currentView['source'] ?? 'main@view-cookie-231-current')), 'current view cookie');
        $triggerCookie = self::token((string) ($options['current_trigger_cookie_next231'] ?? (string) ($currentView['trigger_source'] ?? 'main@trigger-cookie-231-current')), 'current trigger cookie');
        $requireOrder = (bool) ($options['require_current_source_close_order_next231'] ?? true);
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_ticket_next222'] ?? false);
        $closeMatches = hash_equals($closeToken, $expectedCloseToken);

        $currentRows = self::rows($base['current_source_rows_next222'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next222'] ?? [], 'attempted next source rows');
        $requiredClosures = self::closeReceipts($currentRows, $cursor, $closeToken, $viewCookie, $triggerCookie);
        $acknowledgedClosures = self::acknowledgedClosures($options, $requiredClosures);
        $missingClosures = array_values(array_diff($requiredClosures, $acknowledgedClosures));
        $unexpectedClosures = array_values(array_diff($acknowledgedClosures, $requiredClosures));
        $orderMatches = !$requireOrder || $requiredClosures === $acknowledgedClosures;
        $closeComplete = $requiredClosures !== []
            && $closeMatches
            && $missingClosures === []
            && $unexpectedClosures === []
            && $orderMatches;
        $nextVisible = $baseVisible && $closeComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next222'] ?? [],
            $baseVisible,
            $closeMatches,
            $missingClosures,
            $unexpectedClosures,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRows($currentRows, 'current', true, $requiredClosures, $cursor, $closeToken, $viewCookie, $triggerCookie, []);
        $nextRows = self::tagRows($nextRows, 'next', $nextVisible, [], $cursor, $closeToken, $viewCookie, $triggerCookie, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_close_next231'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_close_next231'],
        ));

        return [
            'status_next231' => self::status($baseVisible, $closeMatches, $missingClosures, $unexpectedClosures, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next231' => $baseVisible,
            'current_source_cursor_next231' => $cursor,
            'current_source_close_token_next231' => $closeToken,
            'expected_current_source_close_token_next231' => $expectedCloseToken,
            'current_source_close_matches_next231' => $closeMatches,
            'current_view_cookie_next231' => $viewCookie,
            'current_trigger_cookie_next231' => $triggerCookie,
            'required_current_source_closures_next231' => $requiredClosures,
            'acknowledged_current_source_closures_next231' => $acknowledgedClosures,
            'missing_current_source_closures_next231' => $missingClosures,
            'unexpected_current_source_closures_next231' => $unexpectedClosures,
            'require_current_source_close_order_next231' => $requireOrder,
            'current_source_close_order_matches_next231' => $orderMatches,
            'current_source_close_complete_next231' => $closeComplete,
            'next_source_visible_after_current_source_close_next231' => $nextVisible,
            'current_source_rows_next231' => $currentRows,
            'attempted_next_source_rows_next231' => $nextRows,
            'visible_returning_rows_next231' => $visibleRows,
            'held_next_source_rows_next231' => $heldRows,
            'visible_returning_payloads_next231' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next231' => array_column($heldRows, 'returning'),
            'current_source_row_count_next231' => count($currentRows),
            'attempted_next_source_row_count_next231' => count($nextRows),
            'visible_row_count_next231' => count($visibleRows),
            'held_next_row_count_next231' => count($heldRows),
            'blocked_reasons_next231' => $blockedReasons,
            'current_source_close_plan_next231' => [
                'base_next_source_visible' => $baseVisible,
                'close_token_matches' => $closeMatches,
                'required_closures' => $requiredClosures,
                'acknowledged_closures' => $acknowledgedClosures,
                'missing_closures' => $missingClosures,
                'unexpected_closures' => $unexpectedClosures,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'close_complete' => $closeComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-returning-cursor-close'
                    : 'hold-next-source-until-current-returning-cursor-close',
            ],
            'yield_boundary_next231' => $nextVisible
                ? 'recursive-view-returning-next231-current-cursor-close-then-next'
                : 'recursive-view-returning-next231-current-cursor-close-fences-next',
            'dependency_closure_next231' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-close-handoff',
            'dependencies_next231' => array_values(array_unique(array_merge($base['dependencies_next222'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next231',
                'sqlite-returning-current-source-cursor-close-handoff',
                'wordpress-recursive-view-returning-current-source-next231',
            ]))),
            'non_overlap_next231' => 'adds current RETURNING cursor close admission after accepted next222 source-ticket handoff; avoids accepted trigger recursive view RETURNING next157-next222 surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function closeReceipts(array $rows, string $cursor, string $closeToken, string $viewCookie, string $triggerCookie): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $cursor,
                $closeToken,
                $viewCookie,
                $triggerCookie,
                (string) ($row['current_source_ticket_receipt_next222'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
                (string) ($row['returning']['trigger_source_alias'] ?? ''),
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
    private static function acknowledgedClosures(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_closures_next231'] ?? false) === true) {
            return $required;
        }

        return self::closureList($options['acknowledged_current_source_closures_next231'] ?? [], 'acknowledged current source closures');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function closureList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next231 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{48}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next231 {$label} contain a malformed close receipt");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next231 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next231 {$label} contain a malformed row");
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
    private static function tagRows(
        array $rows,
        string $phase,
        bool $visible,
        array $receipts,
        string $cursor,
        string $closeToken,
        string $viewCookie,
        string $triggerCookie,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_close_phase_next231' => $phase,
                'current_source_cursor_next231' => $cursor,
                'current_source_close_token_next231' => $closeToken,
                'current_view_cookie_next231' => $viewCookie,
                'current_trigger_cookie_next231' => $triggerCookie,
                'current_source_close_receipt_next231' => $receipts[$index] ?? null,
                'visible_after_current_source_close_next231' => $visible,
                'held_by_current_source_close_reasons_next231' => $visible ? [] : $reasons,
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
        bool $closeMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next231 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next222-current-source-ticket-not-published';
        }
        if (!$closeMatches) {
            $reasons[] = 'current-source-close-token-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-close-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-close-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-close-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(
        bool $baseVisible,
        bool $closeMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
        bool $nextVisible,
    ): string {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next231-cursor-close-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next231-base-held';
        }
        if (!$closeMatches) {
            return 'trigger-recursive-view-returning-current-source-next231-close-token-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-returning-current-source-next231-cursor-close-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-returning-current-source-next231-cursor-close-order-held';
        }

        return 'trigger-recursive-view-returning-current-source-next231-cursor-close-empty-held';
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next231 {$label} is malformed");
        }

        return $value;
    }
}
