<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext212Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext209Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $yieldToken = self::token((string) ($options['current_source_yield_token_next212'] ?? 'wp.current.source.yield.212'), 'current source yield token');
        $viewCursor = self::token((string) ($options['current_view_yield_cursor_next212'] ?? 'wp.returning.view.yield.cursor.212'), 'current view yield cursor');
        $triggerCursor = self::token((string) ($options['current_trigger_yield_cursor_next212'] ?? 'wp.returning.trigger.yield.cursor.212'), 'current trigger yield cursor');
        $requireOrder = (bool) ($options['require_current_source_yield_order_next212'] ?? true);
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_drain_next209'] ?? false);

        $currentRows = self::rows($base['current_source_rows_next209'] ?? [], 'current source rows');
        $attemptedNextRows = self::rows($base['attempted_next_source_rows_next209'] ?? [], 'attempted next source rows');
        $requiredYields = self::yieldReceipts($currentRows, $yieldToken, $viewCursor, $triggerCursor);
        $acknowledgedYields = self::acknowledgedYields($options, $requiredYields);
        $missingYields = array_values(array_diff($requiredYields, $acknowledgedYields));
        $unexpectedYields = array_values(array_diff($acknowledgedYields, $requiredYields));
        $orderMatches = !$requireOrder || $requiredYields === $acknowledgedYields;
        $yieldComplete = $requiredYields !== []
            && $missingYields === []
            && $unexpectedYields === []
            && $orderMatches;
        $nextVisible = $baseVisible && $yieldComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next209'] ?? [],
            $baseVisible,
            $yieldComplete,
            $missingYields,
            $unexpectedYields,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRows(
            $currentRows,
            'current',
            true,
            $requiredYields,
            $yieldToken,
            $viewCursor,
            $triggerCursor,
            [],
        );
        $nextRows = self::tagRows(
            $attemptedNextRows,
            'next',
            $nextVisible,
            [],
            $yieldToken,
            $viewCursor,
            $triggerCursor,
            $nextVisible ? [] : $blockedReasons,
        );
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_yield_next212'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_yield_next212'],
        ));

        return [
            'status_next212' => self::status($baseVisible, $yieldComplete, $missingYields, $unexpectedYields, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next212' => $baseVisible,
            'current_source_yield_token_next212' => $yieldToken,
            'current_view_yield_cursor_next212' => $viewCursor,
            'current_trigger_yield_cursor_next212' => $triggerCursor,
            'required_current_source_yields_next212' => $requiredYields,
            'acknowledged_current_source_yields_next212' => $acknowledgedYields,
            'missing_current_source_yields_next212' => $missingYields,
            'unexpected_current_source_yields_next212' => $unexpectedYields,
            'require_current_source_yield_order_next212' => $requireOrder,
            'current_source_yield_order_matches_next212' => $orderMatches,
            'current_source_yield_complete_next212' => $yieldComplete,
            'next_source_visible_after_current_source_yield_next212' => $nextVisible,
            'current_source_rows_next212' => $currentRows,
            'attempted_next_source_rows_next212' => $nextRows,
            'visible_returning_rows_next212' => $visibleRows,
            'held_next_source_rows_next212' => $heldRows,
            'visible_returning_payloads_next212' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next212' => array_column($heldRows, 'returning'),
            'current_source_row_count_next212' => count($currentRows),
            'attempted_next_source_row_count_next212' => count($nextRows),
            'visible_row_count_next212' => count($visibleRows),
            'held_next_row_count_next212' => count($heldRows),
            'blocked_reasons_next212' => $blockedReasons,
            'current_source_yield_plan_next212' => [
                'base_next_source_visible' => $baseVisible,
                'required_yields' => $requiredYields,
                'acknowledged_yields' => $acknowledgedYields,
                'missing_yields' => $missingYields,
                'unexpected_yields' => $unexpectedYields,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'yield_complete' => $yieldComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-source-yield'
                    : 'hold-next-source-until-current-source-yield',
            ],
            'yield_boundary_next212' => $nextVisible
                ? 'recursive-view-returning-next212-current-source-yield-then-next'
                : 'recursive-view-returning-next212-current-source-yield-fences-next',
            'dependency_closure_next212' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-yield-receipts',
            'dependencies_next212' => array_values(array_unique(array_merge($base['dependencies_next209'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next212',
                'sqlite-returning-current-source-yield-receipt',
                'wordpress-recursive-view-returning-current-source-next212',
            ]))),
            'non_overlap_next212' => 'adds ordered current-source trigger-yield receipts after next209 drain watermarks; avoids accepted trigger recursive view RETURNING next157-next209 surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function yieldReceipts(array $rows, string $yieldToken, string $viewCursor, string $triggerCursor): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $yieldToken,
                $viewCursor,
                $triggerCursor,
                (string) ($row['current_source_watermark_next209'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
                (string) ($row['returning']['trigger_source_alias'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 34);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedYields(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_yields_next212'] ?? false) === true) {
            return $required;
        }

        return self::yieldList($options['acknowledged_current_source_yields_next212'] ?? [], 'acknowledged current source yields');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function yieldList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next212 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{34}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next212 {$label} contain a malformed yield receipt");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next212 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next212 {$label} contain a malformed row");
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
        string $yieldToken,
        string $viewCursor,
        string $triggerCursor,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_yield_phase_next212' => $phase,
                'current_source_yield_token_next212' => $yieldToken,
                'current_view_yield_cursor_next212' => $viewCursor,
                'current_trigger_yield_cursor_next212' => $triggerCursor,
                'current_source_yield_receipt_next212' => $receipts[$index] ?? null,
                'visible_after_current_source_yield_next212' => $visible,
                'held_by_current_source_yield_reasons_next212' => $visible ? [] : $reasons,
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
        bool $yieldComplete,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next212 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next209-current-source-drain-not-published';
        }
        if (!$yieldComplete) {
            if ($missing !== []) {
                $reasons[] = 'current-source-yield-missing';
            }
            if ($unexpected !== []) {
                $reasons[] = 'current-source-yield-unexpected';
            }
            if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
                $reasons[] = 'current-source-yield-order-mismatch';
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(bool $baseVisible, bool $yieldComplete, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next212-yield-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next212-base-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches && $yieldComplete === false) {
            return 'trigger-recursive-view-returning-current-source-next212-yield-order-held';
        }
        if (!$yieldComplete) {
            return 'trigger-recursive-view-returning-current-source-next212-yield-held';
        }

        return 'trigger-recursive-view-returning-current-source-next212-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next212 {$label} is malformed");
        }

        return $token;
    }
}
