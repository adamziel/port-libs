<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext255Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext252Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_where_next252'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next252'] ?? [], 'current rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next252'] ?? [], 'next rows');
        $cursor = self::token((string) ($options['current_source_returning_cursor_next255'] ?? 'wp.returning.current.upsert.cursor.255'), 'returning cursor');
        $expectedCursor = self::token((string) ($options['expected_current_source_returning_cursor_next255'] ?? $cursor), 'expected returning cursor');
        $cursorMatches = hash_equals($cursor, $expectedCursor);
        $payloads = self::payloads($currentRows);
        $aliases = self::aliases($options['required_current_source_returning_aliases_next255'] ?? null, $payloads);
        $missingAliases = self::missingAliases($payloads, $aliases);
        $requiredReceipts = self::returningReceipts($currentRows, $payloads, $aliases, $cursor);
        $acknowledgedReceipts = self::acknowledgedReceipts($options, $requiredReceipts);
        $missingReceipts = array_values(array_diff($requiredReceipts, $acknowledgedReceipts));
        $unexpectedReceipts = array_values(array_diff($acknowledgedReceipts, $requiredReceipts));
        $requireDrainOrder = (bool) ($options['require_current_source_returning_order_next255'] ?? true);
        $drainOrderMatches = !$requireDrainOrder || $requiredReceipts === $acknowledgedReceipts;
        $returningComplete = $requiredReceipts !== []
            && $cursorMatches
            && $missingAliases === []
            && $missingReceipts === []
            && $unexpectedReceipts === []
            && $drainOrderMatches;
        $nextVisible = $baseVisible && $returningComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next252'] ?? [],
            $baseVisible,
            $cursorMatches,
            $missingAliases,
            $missingReceipts,
            $unexpectedReceipts,
            $requireDrainOrder,
            $drainOrderMatches,
        );

        $currentRows = self::tagRows($currentRows, 'current-returning-drain', true, $requiredReceipts, $cursor, $aliases, []);
        $nextRows = self::tagRows($nextRows, 'next-source', $nextVisible, [], $cursor, $aliases, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($currentRows, $nextRows) : $currentRows;
        $heldRows = $nextVisible ? [] : $nextRows;

        return [
            'status_next255' => self::status($baseVisible, $cursorMatches, $missingAliases, $missingReceipts, $unexpectedReceipts, $requireDrainOrder, $drainOrderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next255' => $baseVisible,
            'current_source_returning_cursor_next255' => $cursor,
            'expected_current_source_returning_cursor_next255' => $expectedCursor,
            'current_source_returning_cursor_matches_next255' => $cursorMatches,
            'required_current_source_returning_aliases_next255' => $aliases,
            'missing_current_source_returning_aliases_next255' => $missingAliases,
            'current_source_returning_payloads_next255' => $payloads,
            'required_current_source_returning_receipts_next255' => $requiredReceipts,
            'acknowledged_current_source_returning_receipts_next255' => $acknowledgedReceipts,
            'missing_current_source_returning_receipts_next255' => $missingReceipts,
            'unexpected_current_source_returning_receipts_next255' => $unexpectedReceipts,
            'require_current_source_returning_order_next255' => $requireDrainOrder,
            'current_source_returning_order_matches_next255' => $drainOrderMatches,
            'current_source_returning_complete_next255' => $returningComplete,
            'next_source_visible_after_current_source_returning_next255' => $nextVisible,
            'current_source_rows_next255' => $currentRows,
            'attempted_next_source_rows_next255' => $nextRows,
            'visible_returning_rows_next255' => $visibleRows,
            'held_next_source_rows_next255' => $heldRows,
            'visible_returning_payloads_next255' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next255' => array_column($heldRows, 'returning'),
            'visible_row_count_next255' => count($visibleRows),
            'held_next_row_count_next255' => count($heldRows),
            'blocked_reasons_next255' => $blockedReasons,
            'current_source_returning_drain_plan_next255' => [
                'base_next_source_visible' => $baseVisible,
                'cursor_matches' => $cursorMatches,
                'required_aliases' => $aliases,
                'missing_aliases' => $missingAliases,
                'required_receipts' => $requiredReceipts,
                'acknowledged_receipts' => $acknowledgedReceipts,
                'missing_receipts' => $missingReceipts,
                'unexpected_receipts' => $unexpectedReceipts,
                'require_order' => $requireDrainOrder,
                'order_matches' => $drainOrderMatches,
                'returning_complete' => $returningComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-upsert-returning-drain'
                    : 'hold-next-source-until-current-upsert-returning-drain',
            ],
            'yield_boundary_next255' => $nextVisible
                ? 'recursive-view-upsert-next255-current-returning-drain-then-next'
                : 'recursive-view-upsert-next255-current-returning-drain-fence-next',
            'dependency_closure_next255' => 'no-new-support-component-reuses-native-recursive-view-upsert-where-receipts-and-adds-returning-cursor-drain-fencing',
            'dependencies_next255' => array_values(array_unique(array_merge($base['dependencies_next252'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next255',
                'sqlite-instead-of-view-upsert-returning-cursor-drain',
                'wordpress-recursive-view-upsert-current-source-next255',
            ]))),
            'non_overlap_next255' => 'adds current-source UPSERT RETURNING cursor drain receipts after accepted next252 DO UPDATE WHERE decisions; avoids next251 change counters, next252 predicate receipts, recursive view RETURNING ticket/generation surfaces, row-value RETURNING, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next255 {$label} must be a list");
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function payloads(array $rows): array
    {
        $payloads = [];
        foreach ($rows as $row) {
            if (!isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException('SQLite recursive view UPSERT next255 current row is missing a RETURNING payload');
            }
            $payloads[] = $row['returning'];
        }
        if ($payloads === []) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next255 requires current RETURNING payloads');
        }

        return $payloads;
    }

    /**
     * @param mixed $aliases
     * @param list<array<string,mixed>> $payloads
     * @return list<string>
     */
    private static function aliases(mixed $aliases, array $payloads): array
    {
        if ($aliases === null) {
            return array_values(array_map('strval', array_keys($payloads[0])));
        }
        if (!is_array($aliases) || !array_is_list($aliases) || $aliases === []) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next255 returning aliases must be a non-empty list');
        }
        foreach ($aliases as $alias) {
            if (!is_string($alias) || $alias === '' || preg_match('/\s/', $alias) === 1) {
                throw new InvalidArgumentException('SQLite recursive view UPSERT next255 returning alias is malformed');
            }
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @param list<array<string,mixed>> $payloads
     * @param list<string> $aliases
     * @return list<string>
     */
    private static function missingAliases(array $payloads, array $aliases): array
    {
        $missing = [];
        foreach ($payloads as $payload) {
            foreach ($aliases as $alias) {
                if (!array_key_exists($alias, $payload)) {
                    $missing[] = $alias;
                }
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $payloads
     * @param list<string> $aliases
     * @return list<string>
     */
    private static function returningReceipts(array $rows, array $payloads, array $aliases, string $cursor): array
    {
        $receipts = [];
        foreach ($payloads as $index => $payload) {
            $selected = [];
            foreach ($aliases as $alias) {
                $selected[$alias] = $payload[$alias] ?? null;
            }
            $receipts[] = substr(hash('sha256', json_encode([
                $cursor,
                $rows[$index]['ordinal'] ?? $index,
                $rows[$index]['current_source_upsert_where_receipt_next252'] ?? null,
                $selected,
            ], JSON_THROW_ON_ERROR)), 0, 48);
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
        if (($options['auto_ack_current_source_returning_next255'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_returning_receipts_next255'] ?? [], 'acknowledged returning receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next255 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{48}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next255 {$label} contain a malformed returning receipt");
            }
        }

        return array_values(array_unique($values));
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next255 {$label} is malformed");
        }

        return $token;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $aliases
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, bool $visible, array $receipts, string $cursor, array $aliases, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'returning_drain_phase_next255' => $phase,
                'current_source_returning_cursor_next255' => $cursor,
                'current_source_returning_aliases_next255' => $aliases,
                'current_source_returning_receipt_next255' => $receipts[$index] ?? null,
                'visible_after_current_source_returning_next255' => $visible,
                'held_by_current_source_returning_reasons_next255' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missingAliases
     * @param list<string> $missingReceipts
     * @param list<string> $unexpectedReceipts
     * @return list<string>
     */
    private static function blockedReasons(
        mixed $baseReasons,
        bool $baseVisible,
        bool $cursorMatches,
        array $missingAliases,
        array $missingReceipts,
        array $unexpectedReceipts,
        bool $requireDrainOrder,
        bool $drainOrderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next255 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next252-current-source-upsert-where-not-published';
        }
        if (!$cursorMatches) {
            $reasons[] = 'current-source-returning-cursor-mismatch';
        }
        if ($missingAliases !== []) {
            $reasons[] = 'current-source-returning-alias-missing';
        }
        if ($missingReceipts !== []) {
            $reasons[] = 'current-source-returning-receipt-missing';
        }
        if ($unexpectedReceipts !== []) {
            $reasons[] = 'current-source-returning-receipt-unexpected';
        }
        if ($missingReceipts === [] && $unexpectedReceipts === [] && $requireDrainOrder && !$drainOrderMatches) {
            $reasons[] = 'current-source-returning-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missingAliases
     * @param list<string> $missingReceipts
     * @param list<string> $unexpectedReceipts
     */
    private static function status(
        bool $baseVisible,
        bool $cursorMatches,
        array $missingAliases,
        array $missingReceipts,
        array $unexpectedReceipts,
        bool $requireDrainOrder,
        bool $drainOrderMatches,
        bool $nextVisible,
    ): string {
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next255-base-held';
        }
        if (!$cursorMatches) {
            return 'trigger-recursive-view-upsert-current-source-next255-returning-cursor-held';
        }
        if ($missingAliases !== []) {
            return 'trigger-recursive-view-upsert-current-source-next255-returning-alias-held';
        }
        if ($missingReceipts !== [] || $unexpectedReceipts !== []) {
            return 'trigger-recursive-view-upsert-current-source-next255-returning-receipts-held';
        }
        if ($requireDrainOrder && !$drainOrderMatches) {
            return 'trigger-recursive-view-upsert-current-source-next255-returning-order-held';
        }

        return $nextVisible
            ? 'trigger-recursive-view-upsert-current-source-next255-returning-released'
            : 'trigger-recursive-view-upsert-current-source-next255-held';
    }
}
