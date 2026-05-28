<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext247Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext244Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_commit_next244'] ?? false);
        $currentRows = self::rows($base['current_source_rows_next244'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next244'] ?? [], 'attempted next source rows');
        $sequence = self::sequence($options['current_source_statement_sequence_next247'] ?? 1, 'statement sequence');
        $expectedSequence = self::sequence($options['expected_current_source_statement_sequence_next247'] ?? $sequence, 'expected statement sequence');
        $nextSequence = self::sequence($options['next_source_statement_sequence_next247'] ?? ($sequence + 1), 'next source statement sequence');
        $viewCookie = self::token((string) ($options['current_source_sequence_view_cookie_next247'] ?? ($base['current_upsert_commit_view_cookie_next244'] ?? ($currentView['source'] ?? 'main@view-cookie-247-current'))), 'view cookie');
        $triggerCookie = self::token((string) ($options['current_source_sequence_trigger_cookie_next247'] ?? ($base['current_upsert_commit_trigger_cookie_next244'] ?? ($currentView['trigger_source'] ?? 'main@trigger-cookie-247-current'))), 'trigger cookie');
        $cursor = self::token((string) ($options['current_source_sequence_cursor_next247'] ?? 'wp.returning.current.sequence.cursor.247'), 'sequence cursor');
        $requireMonotonic = (bool) ($options['require_monotonic_statement_sequence_next247'] ?? true);

        $sequenceMatches = $sequence === $expectedSequence;
        $nextIsFuture = $nextSequence > $sequence;
        $required = self::sequenceReceipts($currentRows, $sequence, $viewCookie, $triggerCookie, $cursor);
        $acknowledged = self::acknowledgedReceipts($options, $required);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        $sequenceComplete = $required !== []
            && $sequenceMatches
            && (!$requireMonotonic || $nextIsFuture)
            && $missing === []
            && $unexpected === [];
        $nextVisible = $baseVisible && $sequenceComplete;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next244'] ?? [],
            $baseVisible,
            $sequenceMatches,
            $requireMonotonic,
            $nextIsFuture,
            $missing,
            $unexpected,
        );

        $taggedCurrent = self::tagRows($currentRows, 'current-sequence', true, $required, $sequence, $nextSequence, $viewCookie, $triggerCookie, $cursor, []);
        $taggedNext = self::tagRows($nextRows, 'next-source', $nextVisible, [], $sequence, $nextSequence, $viewCookie, $triggerCookie, $cursor, $nextVisible ? [] : $blockedReasons);
        $visibleRows = $nextVisible ? array_merge($taggedCurrent, $taggedNext) : $taggedCurrent;
        $heldRows = $nextVisible ? [] : $taggedNext;

        return [
            'status_next247' => self::status($nextVisible, $baseVisible, $sequenceMatches, $requireMonotonic, $nextIsFuture, $missing, $unexpected),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next247' => $baseVisible,
            'current_source_statement_sequence_next247' => $sequence,
            'expected_current_source_statement_sequence_next247' => $expectedSequence,
            'current_source_statement_sequence_matches_next247' => $sequenceMatches,
            'next_source_statement_sequence_next247' => $nextSequence,
            'require_monotonic_statement_sequence_next247' => $requireMonotonic,
            'next_source_statement_sequence_is_future_next247' => $nextIsFuture,
            'current_source_sequence_view_cookie_next247' => $viewCookie,
            'current_source_sequence_trigger_cookie_next247' => $triggerCookie,
            'current_source_sequence_cursor_next247' => $cursor,
            'required_current_source_sequence_receipts_next247' => $required,
            'acknowledged_current_source_sequence_receipts_next247' => $acknowledged,
            'missing_current_source_sequence_receipts_next247' => $missing,
            'unexpected_current_source_sequence_receipts_next247' => $unexpected,
            'current_source_statement_sequence_complete_next247' => $sequenceComplete,
            'next_source_visible_after_current_source_statement_sequence_next247' => $nextVisible,
            'current_source_rows_next247' => $taggedCurrent,
            'attempted_next_source_rows_next247' => $taggedNext,
            'visible_returning_rows_next247' => $visibleRows,
            'held_next_source_rows_next247' => $heldRows,
            'visible_returning_payloads_next247' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next247' => array_column($heldRows, 'returning'),
            'current_source_row_count_next247' => count($taggedCurrent),
            'attempted_next_source_row_count_next247' => count($taggedNext),
            'visible_row_count_next247' => count($visibleRows),
            'held_next_row_count_next247' => count($heldRows),
            'blocked_reasons_next247' => $blockedReasons,
            'current_source_statement_sequence_plan_next247' => [
                'base_next_source_visible' => $baseVisible,
                'statement_sequence_matches' => $sequenceMatches,
                'next_source_sequence_is_future' => $nextIsFuture,
                'require_monotonic' => $requireMonotonic,
                'required_receipts' => $required,
                'acknowledged_receipts' => $acknowledged,
                'missing_receipts' => $missing,
                'unexpected_receipts' => $unexpected,
                'sequence_complete' => $sequenceComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-recursive-view-upsert-sequence'
                    : 'hold-next-source-until-current-recursive-view-upsert-sequence',
            ],
            'yield_boundary_next247' => $nextVisible
                ? 'recursive-view-upsert-next247-current-sequence-then-next'
                : 'recursive-view-upsert-next247-current-sequence-fence-next',
            'dependency_closure_next247' => 'no-new-support-component-reuses-native-recursive-view-upsert-commit-watermark-and-adds-statement-source-sequence-fencing',
            'dependencies_next247' => array_values(array_unique(array_merge($base['dependencies_next244'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next247',
                'sqlite-instead-of-view-trigger-upsert-statement-source-sequence',
                'wordpress-recursive-view-upsert-current-source-next247',
            ]))),
            'non_overlap_next247' => 'adds statement-source sequence fencing after accepted next244 commit watermark receipts; avoids next244 commit receipt/watermark duplication, next242 statement epoch fencing, next239 target receipts, trigger RETURNING cursor/ticket/generation surfaces, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function sequenceReceipts(array $rows, int $sequence, string $viewCookie, string $triggerCookie, string $cursor): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $receipts[] = substr(hash('sha256', implode('|', [
                (string) $sequence,
                $viewCookie,
                $triggerCookie,
                $cursor,
                (string) ($row['current_source_upsert_commit_receipt_next244'] ?? ''),
                (string) ($returning['name'] ?? ''),
                (string) ($returning['value'] ?? ''),
                (string) ($returning['event_name'] ?? ''),
                (string) ($returning['ordinal_value'] ?? $index),
            ])), 0, 48);
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
        if (($options['auto_ack_current_source_statement_sequences_next247'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_sequence_receipts_next247'] ?? [], 'acknowledged statement sequence receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next247 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{48}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next247 {$label} contain a malformed sequence receipt");
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
            throw new InvalidArgumentException("SQLite recursive view UPSERT next247 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT next247 {$label} contain a malformed row");
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
    private static function tagRows(array $rows, string $phase, bool $visible, array $receipts, int $sequence, int $nextSequence, string $viewCookie, string $triggerCookie, string $cursor, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'statement_sequence_phase_next247' => $phase,
                'current_source_statement_sequence_next247' => $sequence,
                'next_source_statement_sequence_next247' => $nextSequence,
                'current_source_sequence_view_cookie_next247' => $viewCookie,
                'current_source_sequence_trigger_cookie_next247' => $triggerCookie,
                'current_source_sequence_cursor_next247' => $cursor,
                'current_source_sequence_receipt_next247' => $receipts[$index] ?? null,
                'visible_after_current_source_statement_sequence_next247' => $visible,
                'held_by_current_source_statement_sequence_reasons_next247' => $visible ? [] : $reasons,
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
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $sequenceMatches, bool $requireMonotonic, bool $nextIsFuture, array $missing, array $unexpected): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next247 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next244-current-source-upsert-commit-not-published';
        }
        if (!$sequenceMatches) {
            $reasons[] = 'current-source-statement-sequence-mismatch';
        }
        if ($requireMonotonic && !$nextIsFuture) {
            $reasons[] = 'next-source-statement-sequence-not-future';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-statement-sequence-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-statement-sequence-unexpected';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(bool $nextVisible, bool $baseVisible, bool $sequenceMatches, bool $requireMonotonic, bool $nextIsFuture, array $missing, array $unexpected): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next247-sequence-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next247-base-held';
        }
        if (!$sequenceMatches) {
            return 'trigger-recursive-view-upsert-current-source-next247-sequence-held';
        }
        if ($requireMonotonic && !$nextIsFuture) {
            return 'trigger-recursive-view-upsert-current-source-next247-next-sequence-held';
        }
        if ($missing !== []) {
            return 'trigger-recursive-view-upsert-current-source-next247-sequence-missing-held';
        }
        if ($unexpected !== []) {
            return 'trigger-recursive-view-upsert-current-source-next247-sequence-unexpected-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next247-sequence-held';
    }

    private static function sequence(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next247 {$label} must be a non-negative integer");
        }

        return $value;
    }

    private static function token(string $token, string $label): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]{3,180}$/', $token)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next247 invalid {$label}");
        }

        return $token;
    }
}
