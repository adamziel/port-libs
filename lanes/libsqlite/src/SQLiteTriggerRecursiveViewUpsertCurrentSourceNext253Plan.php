<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext253Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext250Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_rowid_provenance_next250'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next250'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next250'] ?? [], 'attempted next source rows');
        $materializationToken = self::token((string) ($options['current_source_view_materialization_token_next253'] ?? 'wp.current.source.view.materialization.253'), 'view materialization token');
        $expectedToken = self::token((string) ($options['expected_current_source_view_materialization_token_next253'] ?? $materializationToken), 'expected view materialization token');
        $viewCookie = self::token((string) ($options['current_source_view_cookie_next253'] ?? ($base['base']['current_source_sequence_view_cookie_next247'] ?? ($currentView['source'] ?? 'main@view-cookie-253-current'))), 'view cookie');
        $triggerCookie = self::token((string) ($options['current_source_trigger_cookie_next253'] ?? ($base['base']['current_source_sequence_trigger_cookie_next247'] ?? ($currentView['trigger_source'] ?? 'main@trigger-cookie-253-current'))), 'trigger cookie');
        $cursor = self::token((string) ($options['current_source_materialization_cursor_next253'] ?? 'wp.returning.materialized.cursor.253'), 'materialization cursor');
        $projectionColumns = self::columns($options['materialized_returning_columns_next253'] ?? ['name', 'value', 'event_name', 'depth_value', 'ordinal_value'], 'materialized returning columns');
        $tokenMatches = hash_equals($materializationToken, $expectedToken);

        $materialized = self::materializedRows($currentRows, $projectionColumns, $viewCookie, $triggerCookie, $cursor);
        $required = self::materializationReceipts($materialized, $materializationToken);
        $acknowledged = self::acknowledgedReceipts($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $requireOrder = (bool) ($options['require_current_source_view_materialization_order_next253'] ?? true);
        $orderMatches = !$requireOrder || $missing !== [] || $unexpected !== [] || $required === $acknowledged;
        $complete = $required !== []
            && $tokenMatches
            && $missing === []
            && $unexpected === []
            && $orderMatches;
        $nextVisible = $baseVisible && $complete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next250'] ?? [],
            $baseVisible,
            $tokenMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $taggedCurrent = self::tagRows($currentRows, 'current-view-materialized', true, $required, $materializationToken, $viewCookie, $triggerCookie, $cursor, []);
        $taggedNext = self::tagRows($nextRows, 'next-source', $nextVisible, [], $materializationToken, $viewCookie, $triggerCookie, $cursor, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next253' => self::status($nextVisible, $baseVisible, $tokenMatches, $missing, $unexpected, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next253' => $baseVisible,
            'current_source_view_materialization_token_next253' => $materializationToken,
            'expected_current_source_view_materialization_token_next253' => $expectedToken,
            'current_source_view_materialization_token_matches_next253' => $tokenMatches,
            'current_source_view_cookie_next253' => $viewCookie,
            'current_source_trigger_cookie_next253' => $triggerCookie,
            'current_source_materialization_cursor_next253' => $cursor,
            'materialized_returning_columns_next253' => $projectionColumns,
            'current_source_view_materialization_rows_next253' => $materialized,
            'required_current_source_view_materialization_receipts_next253' => $required,
            'acknowledged_current_source_view_materialization_receipts_next253' => $acknowledged,
            'missing_current_source_view_materialization_receipts_next253' => $missing,
            'unexpected_current_source_view_materialization_receipts_next253' => $unexpected,
            'require_current_source_view_materialization_order_next253' => $requireOrder,
            'current_source_view_materialization_order_matches_next253' => $orderMatches,
            'current_source_view_materialization_complete_next253' => $complete,
            'next_source_visible_after_current_source_view_materialization_next253' => $nextVisible,
            'current_source_rows_next253' => $taggedCurrent,
            'attempted_next_source_rows_next253' => $taggedNext,
            'visible_returning_rows_next253' => $visibleRows,
            'held_next_source_rows_next253' => $heldRows,
            'visible_returning_payloads_next253' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next253' => array_column($heldRows, 'returning'),
            'visible_row_count_next253' => count($visibleRows),
            'held_next_row_count_next253' => count($heldRows),
            'blocked_reasons_next253' => $blockedReasons,
            'current_source_view_materialization_plan_next253' => [
                'base_next_source_visible' => $baseVisible,
                'token_matches' => $tokenMatches,
                'view_cookie' => $viewCookie,
                'trigger_cookie' => $triggerCookie,
                'cursor' => $cursor,
                'projection_columns' => $projectionColumns,
                'required_receipts' => $required,
                'acknowledged_receipts' => $acknowledged,
                'missing_receipts' => $missing,
                'unexpected_receipts' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'materialization_complete' => $complete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert-materialization'
                    : 'hold-next-source-until-current-recursive-view-upsert-materialization',
            ],
            'yield_boundary_next253' => $nextVisible
                ? 'recursive-view-upsert-next253-current-materialized-then-next'
                : 'recursive-view-upsert-next253-current-materialized-fence-next',
            'dependency_closure_next253' => 'no-new-support-component-reuses-native-recursive-view-upsert-rowid-provenance-and-adds-current-view-materialization-receipts',
            'dependencies_next253' => array_values(array_unique(array_merge($base['dependencies_next250'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next253',
                'sqlite-instead-of-view-trigger-upsert-materialization-receipts',
                'wordpress-recursive-view-upsert-current-source-next253',
            ]))),
            'non_overlap_next253' => 'adds current-source recursive view UPSERT materialized RETURNING receipt fencing after accepted next250 rowid provenance; avoids next247 statement sequence, next246 conflict images, next250 rowid receipts, recursive view RETURNING-only clusters, WAL/VFS, JSON table, planner, encoding, B-tree, and suite evidence clusters',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next253 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next253 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param mixed $columns
     * @return list<string>
     */
    private static function columns(mixed $columns, string $label): array
    {
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next253 {$label} must be a non-empty list");
        }
        $out = [];
        foreach ($columns as $column) {
            if (!is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next253 {$label} contain a malformed column");
            }
            $out[] = $column;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private static function materializedRows(array $rows, array $columns, string $viewCookie, string $triggerCookie, string $cursor): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $payload = $row['returning'];
            $projection = [];
            foreach ($columns as $column) {
                $projection[$column] = $payload[$column] ?? null;
            }
            $out[] = [
                'ordinal' => $index,
                'view_cookie' => $viewCookie,
                'trigger_cookie' => $triggerCookie,
                'cursor' => $cursor,
                'rowid_receipt_next250' => $row['current_source_rowid_receipt_next250'] ?? null,
                'projection' => $projection,
                'projection_hash' => substr(hash('sha256', json_encode([$viewCookie, $triggerCookie, $cursor, $projection], JSON_THROW_ON_ERROR)), 0, 48),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function materializationReceipts(array $rows, string $token): array
    {
        $receipts = [];
        foreach ($rows as $row) {
            $receipts[] = substr(hash('sha256', json_encode([$token, $row], JSON_THROW_ON_ERROR)), 0, 50);
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
        if (($options['auto_ack_current_source_view_materialization_next253'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_view_materialization_receipts_next253'] ?? [], 'acknowledged view materialization receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next253 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{50}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next253 {$label} contain a malformed receipt");
            }
        }

        return array_values(array_unique($values));
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next253 {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, bool $visible, array $receipts, string $token, string $viewCookie, string $triggerCookie, string $cursor, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'view_materialization_phase_next253' => $phase,
                'current_source_view_materialization_token_next253' => $token,
                'current_source_view_cookie_next253' => $viewCookie,
                'current_source_trigger_cookie_next253' => $triggerCookie,
                'current_source_materialization_cursor_next253' => $cursor,
                'current_source_view_materialization_receipt_next253' => $receipts[$index] ?? null,
                'visible_after_current_source_view_materialization_next253' => $visible,
                'held_by_current_source_view_materialization_reasons_next253' => $visible ? [] : $reasons,
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
            throw new InvalidArgumentException('SQLite recursive view UPSERT next253 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next250-rowid-provenance-not-published';
        }
        if (!$tokenMatches) {
            $reasons[] = 'current-source-view-materialization-token-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-view-materialization-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-view-materialization-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-view-materialization-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(bool $nextVisible, bool $baseVisible, bool $tokenMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next253-view-materialization-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next253-base-held';
        }
        if (!$tokenMatches) {
            return 'trigger-recursive-view-upsert-current-source-next253-materialization-token-held';
        }
        if ($missing !== []) {
            return 'trigger-recursive-view-upsert-current-source-next253-materialization-missing-held';
        }
        if ($unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next253-materialization-unexpected-held';
        }
        if ($requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next253-materialization-order-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next253-held';
    }
}
