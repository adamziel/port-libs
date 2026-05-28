<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext240Plan
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

        $cursor = self::token((string) ($options['current_source_upsert_cursor_next240'] ?? 'wp.upsert.current.cursor.240'), 'current source upsert cursor');
        $viewCookie = self::token((string) ($options['current_view_upsert_cookie_next240'] ?? (string) ($currentView['source'] ?? 'main@view-upsert-cookie-240-current')), 'current view upsert cookie');
        $triggerCookie = self::token((string) ($options['current_trigger_upsert_cookie_next240'] ?? (string) ($currentView['trigger_source'] ?? 'main@trigger-upsert-cookie-240-current')), 'current trigger upsert cookie');
        $conflictColumns = self::columns($options['upsert_conflict_columns_next240'] ?? ['name'], 'conflict columns');
        $requireOrder = (bool) ($options['require_current_source_upsert_order_next240'] ?? true);
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_close_next231'] ?? false);

        $currentRows = self::rows($base['current_source_rows_next231'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next231'] ?? [], 'attempted next source rows');
        $currentKeys = self::currentKeys($currentRows, $conflictColumns);
        $nextKeys = self::nextKeys($nextRows, $conflictColumns);
        $conflictingNextKeys = array_values(array_intersect($nextKeys, $currentKeys));
        $requiredReceipts = self::upsertReceipts($currentRows, $conflictColumns, $cursor, $viewCookie, $triggerCookie);
        $acknowledgedReceipts = self::acknowledgedReceipts($options, $requiredReceipts);
        $missingReceipts = array_values(array_diff($requiredReceipts, $acknowledgedReceipts));
        $unexpectedReceipts = array_values(array_diff($acknowledgedReceipts, $requiredReceipts));
        $orderMatches = !$requireOrder || $requiredReceipts === $acknowledgedReceipts;
        $conflictSourceComplete = $requiredReceipts !== []
            && $missingReceipts === []
            && $unexpectedReceipts === []
            && $orderMatches;
        $nextVisible = $baseVisible && $conflictSourceComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next231'] ?? [],
            $baseVisible,
            $missingReceipts,
            $unexpectedReceipts,
            $requireOrder,
            $orderMatches,
        );

        $currentRows = self::tagRows($currentRows, 'current', true, $requiredReceipts, $cursor, $viewCookie, $triggerCookie, $conflictColumns, []);
        $nextRows = self::tagRows($nextRows, 'next', $nextVisible, [], $cursor, $viewCookie, $triggerCookie, $conflictColumns, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_upsert_next240'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_upsert_next240'],
        ));

        return [
            'status_next240' => self::status($baseVisible, $missingReceipts, $unexpectedReceipts, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next240' => $baseVisible,
            'current_source_upsert_cursor_next240' => $cursor,
            'current_view_upsert_cookie_next240' => $viewCookie,
            'current_trigger_upsert_cookie_next240' => $triggerCookie,
            'upsert_conflict_columns_next240' => $conflictColumns,
            'current_upsert_conflict_keys_next240' => $currentKeys,
            'attempted_next_upsert_conflict_keys_next240' => $nextKeys,
            'conflicting_next_upsert_keys_next240' => $conflictingNextKeys,
            'required_current_source_upsert_receipts_next240' => $requiredReceipts,
            'acknowledged_current_source_upsert_receipts_next240' => $acknowledgedReceipts,
            'missing_current_source_upsert_receipts_next240' => $missingReceipts,
            'unexpected_current_source_upsert_receipts_next240' => $unexpectedReceipts,
            'require_current_source_upsert_order_next240' => $requireOrder,
            'current_source_upsert_order_matches_next240' => $orderMatches,
            'current_source_upsert_complete_next240' => $conflictSourceComplete,
            'next_source_visible_after_current_source_upsert_next240' => $nextVisible,
            'current_source_rows_next240' => $currentRows,
            'attempted_next_source_rows_next240' => $nextRows,
            'visible_returning_rows_next240' => $visibleRows,
            'held_next_source_rows_next240' => $heldRows,
            'visible_returning_payloads_next240' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next240' => array_column($heldRows, 'returning'),
            'current_source_row_count_next240' => count($currentRows),
            'attempted_next_source_row_count_next240' => count($nextRows),
            'visible_row_count_next240' => count($visibleRows),
            'held_next_row_count_next240' => count($heldRows),
            'blocked_reasons_next240' => $blockedReasons,
            'current_source_upsert_plan_next240' => [
                'base_next_source_visible' => $baseVisible,
                'conflict_columns' => $conflictColumns,
                'current_keys' => $currentKeys,
                'attempted_next_keys' => $nextKeys,
                'conflicting_next_keys' => $conflictingNextKeys,
                'required_receipts' => $requiredReceipts,
                'acknowledged_receipts' => $acknowledgedReceipts,
                'missing_receipts' => $missingReceipts,
                'unexpected_receipts' => $unexpectedReceipts,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'upsert_complete' => $conflictSourceComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-view-upsert-conflict-source'
                    : 'hold-next-source-until-current-view-upsert-conflict-source',
            ],
            'yield_boundary_next240' => $nextVisible
                ? 'recursive-view-upsert-next240-current-conflict-source-then-next'
                : 'recursive-view-upsert-next240-current-conflict-source-fences-next',
            'dependency_closure_next240' => 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-handoff',
            'dependencies_next240' => array_values(array_unique(array_merge($base['dependencies_next231'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next240',
                'sqlite-instead-of-view-upsert-conflict-source',
                'wordpress-recursive-view-upsert-current-source-next240',
            ]))),
            'non_overlap_next240' => 'adds current-source UPSERT conflict-key admission after accepted next231 cursor-close handoff; avoids accepted trigger recursive view RETURNING next157-next231 cursor/ticket/close surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param mixed $columns
     * @return list<string>
     */
    private static function columns(mixed $columns, string $label): array
    {
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next240 {$label} must be a non-empty list");
        }
        $out = [];
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '' || preg_match('/\s/', $column) === 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next240 {$label} contain a malformed column");
            }
            $out[] = $column;
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
            throw new InvalidArgumentException("SQLite recursive view UPSERT next240 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next240 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<string>
     */
    private static function currentKeys(array $rows, array $columns): array
    {
        $keys = [];
        foreach ($rows as $row) {
            $keys[] = self::keyFor($row['returning'], $columns);
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<string>
     */
    private static function nextKeys(array $rows, array $columns): array
    {
        $keys = [];
        foreach ($rows as $row) {
            $keys[] = self::keyFor($row['returning'], $columns);
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param array<string,mixed> $payload
     * @param list<string> $columns
     */
    private static function keyFor(array $payload, array $columns): string
    {
        $parts = [];
        foreach ($columns as $column) {
            if (!array_key_exists($column, $payload)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next240 conflict column {$column} is missing");
            }
            $parts[] = $column . '=' . self::scalarKey($payload[$column]);
        }

        return implode('|', $parts);
    }

    private static function scalarKey(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? 'BOOL:1' : 'BOOL:0',
            is_int($value) => 'INT:' . $value,
            is_float($value) => 'FLOAT:' . sprintf('%.17g', $value),
            is_string($value) => 'TEXT:' . $value,
            default => throw new InvalidArgumentException('SQLite recursive view UPSERT next240 conflict value must be scalar'),
        };
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<string>
     */
    private static function upsertReceipts(array $rows, array $columns, string $cursor, string $viewCookie, string $triggerCookie): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $cursor,
                $viewCookie,
                $triggerCookie,
                (string) ($row['current_source_close_receipt_next231'] ?? ''),
                self::keyFor($row['returning'], $columns),
                (string) ($row['returning_row_ordinal'] ?? $index),
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
    private static function acknowledgedReceipts(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_upserts_next240'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_upserts_next240'] ?? [], 'acknowledged current source upserts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next240 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{48}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next240 {$label} contain a malformed receipt");
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $columns
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(
        array $rows,
        string $phase,
        bool $visible,
        array $receipts,
        string $cursor,
        string $viewCookie,
        string $triggerCookie,
        array $columns,
        array $reasons,
    ): array {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'upsert_source_phase_next240' => $phase,
                'current_source_upsert_cursor_next240' => $cursor,
                'current_view_upsert_cookie_next240' => $viewCookie,
                'current_trigger_upsert_cookie_next240' => $triggerCookie,
                'upsert_conflict_columns_next240' => $columns,
                'upsert_conflict_key_next240' => self::keyFor($row['returning'], $columns),
                'current_source_upsert_receipt_next240' => $receipts[$index] ?? null,
                'visible_after_current_source_upsert_next240' => $visible,
                'held_by_current_source_upsert_reasons_next240' => $visible ? [] : $reasons,
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
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next240 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next231-current-source-close-not-published';
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
    private static function status(
        bool $baseVisible,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
        bool $nextVisible,
    ): string {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next240-conflict-source-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next240-base-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next240-conflict-source-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next240-conflict-source-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next240-conflict-source-empty-held';
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next240 {$label} is malformed");
        }

        return $value;
    }
}
