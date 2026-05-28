<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext218Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext212Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $epoch = self::token((string) ($options['current_source_epoch_next218'] ?? 'wp.current.source.epoch.218'), 'current source epoch');
        $expectedEpoch = self::token((string) ($options['expected_current_source_epoch_next218'] ?? $epoch), 'expected current source epoch');
        $viewEpoch = self::token((string) ($options['current_view_epoch_next218'] ?? 'wp.returning.view.epoch.218'), 'current view epoch');
        $triggerEpoch = self::token((string) ($options['current_trigger_epoch_next218'] ?? 'wp.returning.trigger.epoch.218'), 'current trigger epoch');
        $requireOrder = (bool) ($options['require_current_source_epoch_order_next218'] ?? true);
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_yield_next212'] ?? false);
        $epochMatches = hash_equals($epoch, $expectedEpoch);

        $currentRows = self::rows($base['current_source_rows_next212'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next212'] ?? [], 'attempted next source rows');
        $requiredEpochs = self::epochReceipts($currentRows, $epoch, $viewEpoch, $triggerEpoch);
        $acknowledgedEpochs = self::acknowledgedEpochs($options, $requiredEpochs);
        $missingEpochs = array_values(array_diff($requiredEpochs, $acknowledgedEpochs));
        $unexpectedEpochs = array_values(array_diff($acknowledgedEpochs, $requiredEpochs));
        $orderMatches = !$requireOrder || $requiredEpochs === $acknowledgedEpochs;
        $epochComplete = $requiredEpochs !== []
            && $epochMatches
            && $missingEpochs === []
            && $unexpectedEpochs === []
            && $orderMatches;
        $nextVisible = $baseVisible && $epochComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next212'] ?? [],
            $baseVisible,
            $epochMatches,
            $epochComplete,
            $missingEpochs,
            $unexpectedEpochs,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRows($currentRows, 'current', true, $requiredEpochs, $epoch, $viewEpoch, $triggerEpoch, []);
        $nextRows = self::tagRows($nextRows, 'next', $nextVisible, [], $epoch, $viewEpoch, $triggerEpoch, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_epoch_next218'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_epoch_next218'],
        ));

        return [
            'status_next218' => self::status($baseVisible, $epochMatches, $epochComplete, $missingEpochs, $unexpectedEpochs, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next218' => $baseVisible,
            'current_source_epoch_next218' => $epoch,
            'expected_current_source_epoch_next218' => $expectedEpoch,
            'current_source_epoch_matches_next218' => $epochMatches,
            'current_view_epoch_next218' => $viewEpoch,
            'current_trigger_epoch_next218' => $triggerEpoch,
            'required_current_source_epochs_next218' => $requiredEpochs,
            'acknowledged_current_source_epochs_next218' => $acknowledgedEpochs,
            'missing_current_source_epochs_next218' => $missingEpochs,
            'unexpected_current_source_epochs_next218' => $unexpectedEpochs,
            'require_current_source_epoch_order_next218' => $requireOrder,
            'current_source_epoch_order_matches_next218' => $orderMatches,
            'current_source_epoch_complete_next218' => $epochComplete,
            'next_source_visible_after_current_source_epoch_next218' => $nextVisible,
            'current_source_rows_next218' => $currentRows,
            'attempted_next_source_rows_next218' => $nextRows,
            'visible_returning_rows_next218' => $visibleRows,
            'held_next_source_rows_next218' => $heldRows,
            'visible_returning_payloads_next218' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next218' => array_column($heldRows, 'returning'),
            'current_source_row_count_next218' => count($currentRows),
            'attempted_next_source_row_count_next218' => count($nextRows),
            'visible_row_count_next218' => count($visibleRows),
            'held_next_row_count_next218' => count($heldRows),
            'blocked_reasons_next218' => $blockedReasons,
            'current_source_epoch_plan_next218' => [
                'base_next_source_visible' => $baseVisible,
                'current_source_epoch_matches' => $epochMatches,
                'required_epochs' => $requiredEpochs,
                'acknowledged_epochs' => $acknowledgedEpochs,
                'missing_epochs' => $missingEpochs,
                'unexpected_epochs' => $unexpectedEpochs,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'epoch_complete' => $epochComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-source-epoch'
                    : 'hold-next-source-until-current-source-epoch',
            ],
            'yield_boundary_next218' => $nextVisible
                ? 'recursive-view-returning-next218-current-source-epoch-then-next'
                : 'recursive-view-returning-next218-current-source-epoch-fences-next',
            'dependency_closure_next218' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-epoch-handoff',
            'dependencies_next218' => array_values(array_unique(array_merge($base['dependencies_next212'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next218',
                'sqlite-returning-current-source-epoch-handoff',
                'wordpress-recursive-view-returning-current-source-next218',
            ]))),
            'non_overlap_next218' => 'adds current-source epoch handoff after next212 yield receipts; avoids accepted trigger recursive view RETURNING next157-next212 surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function epochReceipts(array $rows, string $epoch, string $viewEpoch, string $triggerEpoch): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $epoch,
                $viewEpoch,
                $triggerEpoch,
                (string) ($row['current_source_yield_receipt_next212'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
                (string) ($row['returning']['trigger_source_alias'] ?? ''),
            ];
            $receipts[] = substr(hash('sha256', implode('|', $parts)), 0, 36);
        }

        return $receipts;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedEpochs(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_epochs_next218'] ?? false) === true) {
            return $required;
        }

        return self::epochList($options['acknowledged_current_source_epochs_next218'] ?? [], 'acknowledged current source epochs');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function epochList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next218 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{36}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next218 {$label} contain a malformed epoch receipt");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next218 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next218 {$label} contain a malformed row");
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
        string $epoch,
        string $viewEpoch,
        string $triggerEpoch,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_epoch_phase_next218' => $phase,
                'current_source_epoch_next218' => $epoch,
                'current_view_epoch_next218' => $viewEpoch,
                'current_trigger_epoch_next218' => $triggerEpoch,
                'current_source_epoch_receipt_next218' => $receipts[$index] ?? null,
                'visible_after_current_source_epoch_next218' => $visible,
                'held_by_current_source_epoch_reasons_next218' => $visible ? [] : $reasons,
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
        bool $epochMatches,
        bool $epochComplete,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next218 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next212-current-source-yield-not-published';
        }
        if (!$epochMatches) {
            $reasons[] = 'current-source-epoch-mismatch';
        }
        if (!$epochComplete) {
            if ($missing !== []) {
                $reasons[] = 'current-source-epoch-missing';
            }
            if ($unexpected !== []) {
                $reasons[] = 'current-source-epoch-unexpected';
            }
            if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
                $reasons[] = 'current-source-epoch-order-mismatch';
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(bool $baseVisible, bool $epochMatches, bool $epochComplete, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next218-epoch-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next218-base-held';
        }
        if (!$epochMatches) {
            return 'trigger-recursive-view-returning-current-source-next218-epoch-mismatch-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches && $epochComplete === false) {
            return 'trigger-recursive-view-returning-current-source-next218-epoch-order-held';
        }
        if (!$epochComplete) {
            return 'trigger-recursive-view-returning-current-source-next218-epoch-held';
        }

        return 'trigger-recursive-view-returning-current-source-next218-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next218 {$label} is malformed");
        }

        return $token;
    }
}
