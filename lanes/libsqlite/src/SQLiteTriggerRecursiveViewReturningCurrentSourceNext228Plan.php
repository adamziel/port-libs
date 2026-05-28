<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext228Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext224Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rows($base['current_source_rows_next224'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next224'] ?? [], 'attempted next source rows');
        $baseVisible = (bool) ($base['next_source_visible_after_current_returning_source_next224'] ?? false);
        $snapshotToken = self::token((string) ($options['current_returning_snapshot_token_next228'] ?? 'wp.current.returning.snapshot.228'), 'current returning snapshot token');
        $expectedSnapshotToken = self::token((string) ($options['expected_current_returning_snapshot_token_next228'] ?? $snapshotToken), 'expected current returning snapshot token');
        $viewSource = self::token((string) ($options['current_returning_view_source_next228'] ?? ($currentView['source'] ?? 'main@view-cookie-228-current')), 'current returning view source');
        $expectedViewSource = self::token((string) ($options['expected_current_returning_view_source_next228'] ?? $viewSource), 'expected current returning view source');
        $triggerSource = self::token((string) ($options['current_returning_trigger_source_next228'] ?? ($currentView['trigger_source'] ?? 'main@trigger-cookie-228-current')), 'current returning trigger source');
        $expectedTriggerSource = self::token((string) ($options['expected_current_returning_trigger_source_next228'] ?? $triggerSource), 'expected current returning trigger source');
        $requiredAcks = self::snapshotAcks($currentRows, $snapshotToken, $viewSource, $triggerSource);
        $acknowledgedAcks = self::acknowledgedAcks($options, $requiredAcks);
        $missingAcks = array_values(array_diff($requiredAcks, $acknowledgedAcks));
        $unexpectedAcks = array_values(array_diff($acknowledgedAcks, $requiredAcks));
        $sourceMatches = hash_equals($snapshotToken, $expectedSnapshotToken);
        $viewMatches = hash_equals($viewSource, $expectedViewSource);
        $triggerMatches = hash_equals($triggerSource, $expectedTriggerSource);
        $snapshotComplete = $requiredAcks !== []
            && $sourceMatches
            && $viewMatches
            && $triggerMatches
            && $missingAcks === []
            && $unexpectedAcks === [];
        $nextVisible = $baseVisible && $snapshotComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next218'] ?? [],
            $baseVisible,
            $sourceMatches,
            $viewMatches,
            $triggerMatches,
            $missingAcks,
            $unexpectedAcks,
        );

        $currentRows = self::tagRows($currentRows, 'current', true, $requiredAcks, $snapshotToken, $viewSource, $triggerSource, []);
        $nextRows = self::tagRows($nextRows, 'next', $nextVisible, [], $snapshotToken, $viewSource, $triggerSource, $nextVisible ? [] : $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_returning_snapshot_next228'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_returning_snapshot_next228'],
        ));

        return [
            'status_next228' => self::status($nextVisible, $baseVisible, $sourceMatches, $viewMatches, $triggerMatches, $missingAcks, $unexpectedAcks),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next228' => $baseVisible,
            'current_returning_snapshot_token_next228' => $snapshotToken,
            'expected_current_returning_snapshot_token_next228' => $expectedSnapshotToken,
            'current_returning_snapshot_matches_next228' => $sourceMatches,
            'current_returning_view_source_next228' => $viewSource,
            'expected_current_returning_view_source_next228' => $expectedViewSource,
            'current_returning_view_source_matches_next228' => $viewMatches,
            'current_returning_trigger_source_next228' => $triggerSource,
            'expected_current_returning_trigger_source_next228' => $expectedTriggerSource,
            'current_returning_trigger_source_matches_next228' => $triggerMatches,
            'required_current_returning_snapshot_acks_next228' => $requiredAcks,
            'acknowledged_current_returning_snapshot_acks_next228' => $acknowledgedAcks,
            'missing_current_returning_snapshot_acks_next228' => $missingAcks,
            'unexpected_current_returning_snapshot_acks_next228' => $unexpectedAcks,
            'current_returning_snapshot_complete_next228' => $snapshotComplete,
            'next_source_visible_after_current_returning_snapshot_next228' => $nextVisible,
            'current_source_rows_next228' => $currentRows,
            'attempted_next_source_rows_next228' => $nextRows,
            'visible_returning_rows_next228' => $visibleRows,
            'held_next_source_rows_next228' => $heldRows,
            'visible_returning_payloads_next228' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next228' => array_column($heldRows, 'returning'),
            'current_source_row_count_next228' => count($currentRows),
            'attempted_next_source_row_count_next228' => count($nextRows),
            'visible_row_count_next228' => count($visibleRows),
            'held_next_row_count_next228' => count($heldRows),
            'blocked_reasons_next228' => $blockedReasons,
            'current_returning_snapshot_plan_next228' => [
                'base_next_source_visible' => $baseVisible,
                'source_matches' => $sourceMatches,
                'view_source_matches' => $viewMatches,
                'trigger_source_matches' => $triggerMatches,
                'required_acks' => $requiredAcks,
                'acknowledged_acks' => $acknowledgedAcks,
                'missing_acks' => $missingAcks,
                'unexpected_acks' => $unexpectedAcks,
                'source_complete' => $snapshotComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-returning-source-ack'
                    : 'hold-next-source-until-current-returning-source-ack',
            ],
            'yield_boundary_next228' => $nextVisible
                ? 'recursive-view-returning-next228-current-source-acked-then-next'
                : 'recursive-view-returning-next228-current-source-ack-fences-next',
            'dependency_closure_next228' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-epoch-and-adds-source-ack',
            'dependencies_next228' => array_values(array_unique(array_merge($base['dependencies_next224'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next228',
                'sqlite-returning-current-source-snapshot-ack',
                'wordpress-recursive-view-returning-current-source-next228',
            ]))),
            'non_overlap_next228' => 'adds current returning snapshot acknowledgements after accepted next224 source seals; avoids accepted next222 ticket handoff, next224 source seal, next208 cursor close, next212 yield receipts, next218 epoch receipts, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function snapshotAcks(array $rows, string $snapshotToken, string $viewSource, string $triggerSource): array
    {
        $acks = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $snapshotToken,
                $viewSource,
                $triggerSource,
                (string) ($row['current_source_epoch_receipt_next218'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
                (string) ($row['returning']['trigger_source_alias'] ?? ''),
            ];
            $acks[] = substr(hash('sha256', implode('|', $parts)), 0, 40);
        }

        return $acks;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedAcks(array $options, array $required): array
    {
        if (($options['auto_ack_current_returning_snapshot_acks_next228'] ?? false) === true) {
            return $required;
        }

        return self::ackList($options['acknowledged_current_returning_snapshot_acks_next228'] ?? [], 'acknowledged current returning snapshot acks');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function ackList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next228 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{40}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next228 {$label} contain a malformed snapshot ack");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next228 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next228 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $acks
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, bool $visible, array $acks, string $snapshotToken, string $viewSource, string $triggerSource, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'returning_snapshot_phase_next228' => $phase,
                'current_returning_snapshot_token_next228' => $snapshotToken,
                'current_returning_view_source_next228' => $viewSource,
                'current_returning_trigger_source_next228' => $triggerSource,
                'current_returning_snapshot_ack_next228' => $acks[$index] ?? null,
                'visible_after_current_returning_snapshot_next228' => $visible,
                'held_by_current_returning_snapshot_reasons_next228' => $visible ? [] : $reasons,
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
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $sourceMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next228 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next218-current-source-epoch-not-published';
        }
        if (!$sourceMatches) {
            $reasons[] = 'current-returning-source-token-mismatch';
        }
        if (!$viewMatches) {
            $reasons[] = 'current-returning-view-source-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-returning-trigger-source-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-returning-source-ack-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-returning-source-ack-unexpected';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(bool $nextVisible, bool $baseVisible, bool $sourceMatches, bool $viewMatches, bool $triggerMatches, array $missing, array $unexpected): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next228-source-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next228-base-held';
        }
        if (!$sourceMatches) {
            return 'trigger-recursive-view-returning-current-source-next228-source-held';
        }
        if (!$viewMatches) {
            return 'trigger-recursive-view-returning-current-source-next228-view-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-returning-current-source-next228-trigger-held';
        }
        if ($missing !== [] || $unexpected !== []) {
            return 'trigger-recursive-view-returning-current-source-next228-ack-held';
        }

        return 'trigger-recursive-view-returning-current-source-next228-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next228 {$label} is malformed");
        }

        return $token;
    }
}
