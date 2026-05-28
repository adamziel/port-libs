<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext234Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext231Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_close_next231'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next231'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next231'] ?? [], 'attempted next source rows');
        $conflictColumns = self::columns($options['upsert_conflict_columns_next234'] ?? ['option_name']);
        $upsertToken = self::token((string) ($options['current_source_upsert_token_next234'] ?? 'wp.current.source.upsert.234'), 'upsert token');
        $expectedUpsertToken = self::token((string) ($options['expected_current_source_upsert_token_next234'] ?? $upsertToken), 'expected upsert token');
        $viewCookie = self::token((string) ($options['current_upsert_view_cookie_next234'] ?? (string) ($currentView['source'] ?? 'main@view-cookie-234-current')), 'view cookie');
        $triggerCookie = self::token((string) ($options['current_upsert_trigger_cookie_next234'] ?? (string) ($currentView['trigger_source'] ?? 'main@trigger-cookie-234-current')), 'trigger cookie');
        $requireOrder = (bool) ($options['require_current_source_upsert_order_next234'] ?? true);

        $required = self::upsertReceipts($currentRows, $conflictColumns, $upsertToken, $viewCookie, $triggerCookie);
        $acknowledged = self::acknowledgedReceipts($options, $required);
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
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next231'] ?? [],
            $baseVisible,
            $tokenMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $taggedCurrent = self::tagRows($currentRows, 'current-upsert', true, $required, $conflictColumns, $upsertToken, $viewCookie, $triggerCookie, []);
        $taggedNext = self::tagRows($nextRows, 'next-source', $nextVisible, [], $conflictColumns, $upsertToken, $viewCookie, $triggerCookie, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next234' => self::status($baseVisible, $tokenMatches, $missing, $unexpected, $requireOrder, $orderMatches, $nextVisible),
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
            'dependencies_next234' => array_values(array_unique(array_merge($base['dependencies_next231'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next234',
                'sqlite-instead-of-view-trigger-current-source-upsert-receipts',
                'wordpress-recursive-view-upsert-current-source-next234',
            ]))),
            'non_overlap_next234' => 'adds recursive INSTEAD OF view UPSERT conflict-key receipt admission after accepted next231 cursor-close handoff; avoids accepted next230-next231 recursive view RETURNING close/epoch surfaces, trigger RETURNING conflicts, row-value savepoints, schema reparse, WAL/VFS, JSON, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<string>
     */
    private static function upsertReceipts(array $rows, array $columns, string $token, string $viewCookie, string $triggerCookie): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts = [$token, $viewCookie, $triggerCookie, (string) ($row['current_source_close_receipt_next231'] ?? ''), (string) $index];
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
    private static function acknowledgedReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_upserts_next234'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_upsert_receipts_next234'] ?? [], 'acknowledged current source upsert receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
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
    private static function rows(mixed $rows, string $label): array
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
    private static function columns(mixed $columns): array
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
    private static function tagRows(array $rows, string $phase, bool $visible, array $receipts, array $columns, string $token, string $viewCookie, string $triggerCookie, array $reasons): array
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
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next234 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next231-current-source-close-not-published';
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
    private static function status(bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches, bool $nextVisible): string
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

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next234 {$label} is malformed");
        }

        return $value;
    }
}
