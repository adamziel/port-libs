<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext237Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext234Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_next234'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next234'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next234'] ?? [], 'attempted next source rows');
        $actionToken = self::token((string) ($options['current_source_upsert_action_token_next237'] ?? 'wp.current.source.upsert.action.237'), 'action token');
        $expectedActionToken = self::token((string) ($options['expected_current_source_upsert_action_token_next237'] ?? $actionToken), 'expected action token');
        $viewCookie = self::token((string) ($options['current_upsert_action_view_cookie_next237'] ?? ($base['current_upsert_view_cookie_next234'] ?? 'main@view-cookie-237-current')), 'view cookie');
        $triggerCookie = self::token((string) ($options['current_upsert_action_trigger_cookie_next237'] ?? ($base['current_upsert_trigger_cookie_next234'] ?? 'main@trigger-cookie-237-current')), 'trigger cookie');
        $requireOrder = (bool) ($options['require_current_source_upsert_action_order_next237'] ?? true);
        $actionOverrides = self::actionOverrides($options['current_source_upsert_actions_next237'] ?? []);

        $actionRows = self::actionRows($currentRows, $baseRows, $actionOverrides);
        $required = self::actionReceipts($actionRows, $actionToken, $viewCookie, $triggerCookie);
        $acknowledged = self::acknowledgedReceipts($options, $required);
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
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next234'] ?? [],
            $baseVisible,
            $tokenMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $taggedCurrent = self::tagRows($currentRows, $actionRows, 'current-action', true, $required, $actionToken, $viewCookie, $triggerCookie, []);
        $taggedNext = self::tagRows($nextRows, [], 'next-source', $nextVisible, [], $actionToken, $viewCookie, $triggerCookie, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next237' => self::status($baseVisible, $tokenMatches, $missing, $unexpected, $requireOrder, $orderMatches, $nextVisible),
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
    private static function actionRows(array $rows, array $baseRows, array $overrides): array
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
            $action = $overrides[$name] ?? self::defaultAction($name, isset($existing[$name]));
            $actions[] = [
                'name' => $name,
                'action' => $action,
                'event' => (string) ($returning['event_name'] ?? $returning['event'] ?? ''),
                'conflict' => isset($existing[$name]) ? 1 : 0,
            ];
        }

        return $actions;
    }

    private static function defaultAction(string $name, bool $conflict): string
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
    private static function actionReceipts(array $actions, string $token, string $viewCookie, string $triggerCookie): array
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
    private static function acknowledgedReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_upsert_actions_next237'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_upsert_action_receipts_next237'] ?? [], 'acknowledged current source upsert action receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
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
    private static function actionOverrides(mixed $values): array
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
    private static function rows(mixed $rows, string $label): array
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
    private static function tagRows(array $rows, array $actions, string $phase, bool $visible, array $receipts, string $token, string $viewCookie, string $triggerCookie, array $reasons): array
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
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
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
    private static function status(bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches, bool $nextVisible): string
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

    private static function token(string $token, string $label): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]{3,160}$/', $token)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next237 invalid {$label}");
        }

        return $token;
    }
}
