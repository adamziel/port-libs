<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext245Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext241Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_close_next241'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next241'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next241'] ?? [], 'attempted next source rows');
        $target = self::identifierList($options['current_source_upsert_conflict_target_next245'] ?? ['option_name'], 'conflict target');
        $expectedTarget = self::identifierList($options['expected_current_source_upsert_conflict_target_next245'] ?? $target, 'expected conflict target');
        $excludedColumns = self::identifierList($options['current_source_upsert_excluded_columns_next245'] ?? ['option_value', 'autoload'], 'excluded columns');
        $expectedExcludedColumns = self::identifierList($options['expected_current_source_upsert_excluded_columns_next245'] ?? $excludedColumns, 'expected excluded columns');
        $sourceToken = self::token((string) ($options['current_source_upsert_target_token_next245'] ?? 'wp.current.source.upsert.target.245'), 'target token');
        $expectedSourceToken = self::token((string) ($options['expected_current_source_upsert_target_token_next245'] ?? $sourceToken), 'expected target token');
        $viewCookie = self::token((string) ($options['current_upsert_target_view_cookie_next245'] ?? ($base['current_upsert_close_view_cookie_next241'] ?? 'main@view-cookie-245-current')), 'view cookie');
        $expectedViewCookie = self::token((string) ($options['expected_current_upsert_target_view_cookie_next245'] ?? $viewCookie), 'expected view cookie');
        $triggerCookie = self::token((string) ($options['current_upsert_target_trigger_cookie_next245'] ?? ($base['current_upsert_close_trigger_cookie_next241'] ?? 'main@trigger-cookie-245-current')), 'trigger cookie');
        $expectedTriggerCookie = self::token((string) ($options['expected_current_upsert_target_trigger_cookie_next245'] ?? $triggerCookie), 'expected trigger cookie');
        $requireOrder = (bool) ($options['require_current_source_upsert_target_order_next245'] ?? true);

        $required = self::targetReceipts($currentRows, $sourceToken, $viewCookie, $triggerCookie, $target, $excludedColumns);
        $acknowledged = self::acknowledgedReceipts($options, $required);
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
        $blockedReasons = self::blockedReasons(
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

        $taggedCurrent = self::tagRows($currentRows, 'current-upsert-target', true, $required, $sourceToken, $target, $excludedColumns, $viewCookie, $triggerCookie, []);
        $taggedNext = self::tagRows($nextRows, 'next-source', $nextVisible, [], $sourceToken, $target, $excludedColumns, $viewCookie, $triggerCookie, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next245' => self::status($nextVisible, $baseVisible, $targetMatches, $excludedMatches, $tokenMatches, $viewMatches, $triggerMatches, $missing, $unexpected, $requireOrder, $orderMatches),
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
    private static function targetReceipts(array $rows, string $sourceToken, string $viewCookie, string $triggerCookie, array $target, array $excludedColumns): array
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
    private static function acknowledgedReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_upsert_targets_next245'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_upsert_target_receipts_next245'] ?? [], 'acknowledged current source upsert target receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
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
    private static function identifierList(mixed $values, string $label): array
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
    private static function rows(mixed $rows, string $label): array
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
    private static function tagRows(array $rows, string $phase, bool $visible, array $receipts, string $token, array $target, array $excludedColumns, string $viewCookie, string $triggerCookie, array $reasons): array
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
    private static function blockedReasons(
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
            $reasons = array_merge($reasons, self::reasonList($baseReasons));
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
    private static function reasonList(mixed $values): array
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
    private static function status(bool $nextVisible, bool $baseVisible, bool $targetMatches, bool $excludedMatches, bool $tokenMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
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

    private static function token(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next245 {$label} cannot be empty");
        }

        return $value;
    }
}
