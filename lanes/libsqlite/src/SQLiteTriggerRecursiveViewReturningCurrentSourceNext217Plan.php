<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext217Plan
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

        $provenanceToken = self::token((string) ($options['current_source_provenance_token_next217'] ?? 'wp.current.source.provenance.217'), 'current source provenance token');
        $viewSource = self::token((string) ($currentView['source'] ?? ''), 'current view source');
        $triggerSource = self::token((string) ($currentView['trigger_source'] ?? ''), 'current trigger source');
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_yield_next212'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next212'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next212'] ?? [], 'attempted next source rows');
        $currentRows = self::tamperCurrentRows($currentRows, $options['tamper_current_returning_payloads_next217'] ?? []);

        $required = self::provenanceReceipts($currentRows, $provenanceToken, $viewSource, $triggerSource);
        $expected = self::receiptList($options['expected_current_source_provenance_next217'] ?? $required, 'expected current source provenance');
        $acknowledged = self::acknowledged($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $expectedMissing = array_values(array_diff($required, $expected));
        $expectedUnexpected = array_values(array_diff($expected, $required));
        $requireOrder = (bool) ($options['require_current_source_provenance_order_next217'] ?? true);
        $orderMatches = !$requireOrder || $required === $acknowledged;
        $expectedMatches = $expectedMissing === [] && $expectedUnexpected === [];
        $provenanceComplete = $required !== []
            && $missing === []
            && $unexpected === []
            && $expectedMatches
            && $orderMatches;
        $nextVisible = $baseVisible && $provenanceComplete;
        $reasons = self::blockedReasons(
            $base['blocked_reasons_next212'] ?? [],
            $baseVisible,
            $missing,
            $unexpected,
            $expectedMissing,
            $expectedUnexpected,
            $requireOrder,
            $orderMatches,
        );

        $currentTagged = self::tagRows($currentRows, 'current', true, [], $required, $provenanceToken, $viewSource, $triggerSource);
        $nextTagged = self::tagRows($nextRows, 'next', $nextVisible, $reasons, [], $provenanceToken, $viewSource, $triggerSource);
        $visibleRows = array_values(array_filter(
            array_merge($currentTagged, $nextTagged),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_provenance_next217'],
        ));
        $heldRows = array_values(array_filter(
            $nextTagged,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_provenance_next217'],
        ));

        return [
            'status_next217' => self::status($baseVisible, $provenanceComplete, $expectedMatches, $missing, $unexpected, $requireOrder, $orderMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next217' => $baseVisible,
            'current_source_provenance_token_next217' => $provenanceToken,
            'current_view_source_next217' => $viewSource,
            'current_trigger_source_next217' => $triggerSource,
            'required_current_source_provenance_next217' => $required,
            'expected_current_source_provenance_next217' => $expected,
            'acknowledged_current_source_provenance_next217' => $acknowledged,
            'missing_current_source_provenance_next217' => $missing,
            'unexpected_current_source_provenance_next217' => $unexpected,
            'expected_missing_current_source_provenance_next217' => $expectedMissing,
            'expected_unexpected_current_source_provenance_next217' => $expectedUnexpected,
            'current_source_provenance_expected_matches_next217' => $expectedMatches,
            'require_current_source_provenance_order_next217' => $requireOrder,
            'current_source_provenance_order_matches_next217' => $orderMatches,
            'current_source_provenance_complete_next217' => $provenanceComplete,
            'next_source_visible_after_current_source_provenance_next217' => $nextVisible,
            'current_source_rows_next217' => $currentTagged,
            'attempted_next_source_rows_next217' => $nextTagged,
            'visible_returning_rows_next217' => $visibleRows,
            'held_next_source_rows_next217' => $heldRows,
            'visible_returning_payloads_next217' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next217' => array_column($heldRows, 'returning'),
            'current_source_row_count_next217' => count($currentTagged),
            'attempted_next_source_row_count_next217' => count($nextTagged),
            'visible_row_count_next217' => count($visibleRows),
            'held_next_row_count_next217' => count($heldRows),
            'blocked_reasons_next217' => $reasons,
            'current_source_provenance_plan_next217' => [
                'base_next_source_visible' => $baseVisible,
                'view_source' => $viewSource,
                'trigger_source' => $triggerSource,
                'required_provenance' => $required,
                'expected_provenance' => $expected,
                'acknowledged_provenance' => $acknowledged,
                'expected_matches' => $expectedMatches,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'provenance_complete' => $provenanceComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-returning-provenance'
                    : 'hold-next-source-until-current-returning-provenance',
            ],
            'yield_boundary_next217' => $nextVisible
                ? 'recursive-view-returning-next217-current-source-provenance-then-next'
                : 'recursive-view-returning-next217-current-source-provenance-fences-next',
            'dependency_closure_next217' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-yield-and-adds-returning-payload-provenance-fence',
            'dependencies_next217' => array_values(array_unique(array_merge($base['dependencies_next212'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next217',
                'sqlite-returning-current-source-provenance-fence',
                'wordpress-recursive-view-returning-current-source-next217',
            ]))),
            'non_overlap_next217' => 'adds current-source RETURNING payload provenance after next212 yield receipts; avoids accepted next210 sequence, next211 source seal, next212 yield receipts, row-value RETURNING savepoints, DML RETURNING conflicts, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function provenanceReceipts(array $rows, string $token, string $viewSource, string $triggerSource): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts = [
                $token,
                $viewSource,
                $triggerSource,
                (string) ($row['current_source_yield_receipt_next212'] ?? ''),
                (string) ($returning['trigger_source_alias'] ?? ''),
                (string) ($returning['event_name'] ?? ''),
                (string) ($returning['depth_value'] ?? ''),
                (string) ($returning['ordinal_value'] ?? $index),
                (string) ($returning['name'] ?? ''),
                (string) ($returning['value'] ?? ''),
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
    private static function acknowledged(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_provenance_next217'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_provenance_next217'] ?? [], 'acknowledged current source provenance');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next217 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{34}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next217 {$label} contains a malformed provenance receipt");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next217 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next217 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param mixed $overrides
     * @return list<array<string,mixed>>
     */
    private static function tamperCurrentRows(array $rows, mixed $overrides): array
    {
        if ($overrides === []) {
            return $rows;
        }
        if (!is_array($overrides)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next217 payload overrides must be an array');
        }
        foreach ($overrides as $index => $payload) {
            if (!is_int($index) || !isset($rows[$index]) || !is_array($payload)) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next217 payload override is malformed');
            }
            foreach ($payload as $key => $value) {
                if (!is_string($key) || $key === '' || is_array($value) || is_object($value)) {
                    throw new InvalidArgumentException('SQLite recursive view RETURNING next217 payload override field is malformed');
                }
                $rows[$index]['returning'][$key] = $value;
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
    private static function tagRows(array $rows, string $phase, bool $visible, array $reasons, array $receipts, string $token, string $viewSource, string $triggerSource): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_provenance_phase_next217' => $phase,
                'current_source_provenance_token_next217' => $token,
                'current_view_source_next217' => $viewSource,
                'current_trigger_source_next217' => $triggerSource,
                'current_source_provenance_receipt_next217' => $receipts[$index] ?? null,
                'visible_after_current_source_provenance_next217' => $visible,
                'held_by_current_source_provenance_reasons_next217' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missing
     * @param list<string> $unexpected
     * @param list<string> $expectedMissing
     * @param list<string> $expectedUnexpected
     * @return list<string>
     */
    private static function blockedReasons(
        mixed $baseReasons,
        bool $baseVisible,
        array $missing,
        array $unexpected,
        array $expectedMissing,
        array $expectedUnexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next217 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next212-current-source-yield-not-published';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-provenance-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-provenance-unexpected';
        }
        if ($expectedMissing !== [] || $expectedUnexpected !== []) {
            $reasons[] = 'current-source-provenance-expected-mismatch';
        }
        if ($missing === [] && $unexpected === [] && $expectedMissing === [] && $expectedUnexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-provenance-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(bool $baseVisible, bool $complete, bool $expectedMatches, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next217-provenance-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next217-base-held';
        }
        if (!$expectedMatches) {
            return 'trigger-recursive-view-returning-current-source-next217-provenance-mismatch-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches && $expectedMatches && !$complete) {
            return 'trigger-recursive-view-returning-current-source-next217-provenance-order-held';
        }
        if (!$complete) {
            return 'trigger-recursive-view-returning-current-source-next217-provenance-held';
        }

        return 'trigger-recursive-view-returning-current-source-next217-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next217 {$label} is malformed");
        }

        return $token;
    }
}
