<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext241Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext237Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_action_next237'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next237'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next237'] ?? [], 'attempted next source rows');
        $closeToken = self::token((string) ($options['current_source_upsert_close_token_next241'] ?? 'wp.current.source.upsert.close.241'), 'close token');
        $expectedCloseToken = self::token((string) ($options['expected_current_source_upsert_close_token_next241'] ?? $closeToken), 'expected close token');
        $sourceGeneration = self::token((string) ($options['current_source_upsert_generation_next241'] ?? 'main@source-generation-241-current'), 'source generation');
        $expectedSourceGeneration = self::token((string) ($options['expected_current_source_upsert_generation_next241'] ?? $sourceGeneration), 'expected source generation');
        $viewCookie = self::token((string) ($options['current_upsert_close_view_cookie_next241'] ?? ($base['current_upsert_action_view_cookie_next237'] ?? 'main@view-cookie-241-current')), 'view cookie');
        $expectedViewCookie = self::token((string) ($options['expected_current_upsert_close_view_cookie_next241'] ?? $viewCookie), 'expected view cookie');
        $triggerCookie = self::token((string) ($options['current_upsert_close_trigger_cookie_next241'] ?? ($base['current_upsert_action_trigger_cookie_next237'] ?? 'main@trigger-cookie-241-current')), 'trigger cookie');
        $expectedTriggerCookie = self::token((string) ($options['expected_current_upsert_close_trigger_cookie_next241'] ?? $triggerCookie), 'expected trigger cookie');
        $requireOrder = (bool) ($options['require_current_source_upsert_close_order_next241'] ?? true);

        $required = self::closeReceipts($currentRows, $closeToken, $sourceGeneration, $viewCookie, $triggerCookie);
        $acknowledged = self::acknowledgedReceipts($options, $required);
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
        $blockedReasons = self::blockedReasons(
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

        $taggedCurrent = self::tagRows($currentRows, 'current-close', true, $required, $closeToken, $sourceGeneration, $viewCookie, $triggerCookie, []);
        $taggedNext = self::tagRows($nextRows, 'next-source', $nextVisible, [], $closeToken, $sourceGeneration, $viewCookie, $triggerCookie, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next241' => self::status($nextVisible, $baseVisible, $tokenMatches, $sourceMatches, $viewMatches, $triggerMatches, $missing, $unexpected, $requireOrder, $orderMatches),
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
    private static function closeReceipts(array $rows, string $closeToken, string $sourceGeneration, string $viewCookie, string $triggerCookie): array
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
    private static function acknowledgedReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_upsert_closes_next241'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_upsert_close_receipts_next241'] ?? [], 'acknowledged current source upsert close receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
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
    private static function rows(mixed $rows, string $label): array
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
    private static function tagRows(array $rows, string $phase, bool $visible, array $receipts, string $token, string $sourceGeneration, string $viewCookie, string $triggerCookie, array $reasons): array
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
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $tokenMatches, bool $sourceMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
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
    private static function status(bool $nextVisible, bool $baseVisible, bool $tokenMatches, bool $sourceMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
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

    private static function token(string $token, string $label): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]{3,180}$/', $token)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next241 invalid {$label}");
        }

        return $token;
    }
}
