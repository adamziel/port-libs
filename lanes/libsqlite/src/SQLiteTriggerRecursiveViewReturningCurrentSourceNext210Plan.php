<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext210Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext209Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $sequenceToken = self::token((string) ($options['current_source_sequence_token_next210'] ?? 'wp.current.source.sequence.210'), 'current source sequence token');
        $handoffCursor = self::token((string) ($options['sequence_handoff_cursor_next210'] ?? 'wp.returning.sequence.cursor.210'), 'sequence handoff cursor');
        $expectedHandoffCursor = self::token((string) ($options['expected_sequence_handoff_cursor_next210'] ?? $handoffCursor), 'expected sequence handoff cursor');
        $viewCookie = self::token((string) ($base['current_view_cookie_next209'] ?? ''), 'base view cookie');
        $triggerCookie = self::token((string) ($base['current_trigger_cookie_next209'] ?? ''), 'base trigger cookie');
        $expectedSourceSignature = self::token((string) ($options['expected_current_source_signature_next210'] ?? self::sourceSignature($viewCookie, $triggerCookie, $sequenceToken)), 'expected current source signature');
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_drain_next209'] ?? false);

        $currentRows = self::rows($base['current_source_rows_next209'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next209'] ?? [], 'attempted next source rows');
        $requiredSequence = self::sequence($currentRows, $sequenceToken, $handoffCursor, $viewCookie, $triggerCookie);
        $acknowledgedSequence = self::acknowledgedSequence($options, $requiredSequence);
        $missingSequence = array_values(array_diff($requiredSequence, $acknowledgedSequence));
        $unexpectedSequence = array_values(array_diff($acknowledgedSequence, $requiredSequence));
        $requireOrder = (bool) ($options['require_current_source_sequence_order_next210'] ?? true);
        $orderMatches = !$requireOrder || $requiredSequence === $acknowledgedSequence;
        $cursorMatches = hash_equals($handoffCursor, $expectedHandoffCursor);
        $sourceSignature = self::sourceSignature($viewCookie, $triggerCookie, $sequenceToken);
        $sourceSignatureMatches = hash_equals($sourceSignature, $expectedSourceSignature);
        $sequenceComplete = $requiredSequence !== []
            && $missingSequence === []
            && $unexpectedSequence === []
            && $orderMatches;
        $nextVisible = $baseVisible && $sequenceComplete && $cursorMatches && $sourceSignatureMatches;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next209'] ?? [],
            $baseVisible,
            $sequenceComplete,
            $missingSequence,
            $unexpectedSequence,
            $requireOrder,
            $orderMatches,
            $cursorMatches,
            $sourceSignatureMatches,
        );

        $taggedCurrent = self::tagCurrentRows($currentRows, $requiredSequence, $sequenceToken, $handoffCursor, $sourceSignature);
        $taggedNext = self::tagNextRows($nextRows, $nextVisible, $sequenceToken, $handoffCursor, $sourceSignature, $blockedReasons);
        $visibleRows = array_values(array_filter(
            array_merge($taggedCurrent, $taggedNext),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_sequence_next210'],
        ));
        $heldRows = array_values(array_filter(
            $taggedNext,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_sequence_next210'],
        ));

        return [
            'status_next210' => self::status($baseVisible, $sequenceComplete, $cursorMatches, $sourceSignatureMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next210' => $baseVisible,
            'current_source_sequence_token_next210' => $sequenceToken,
            'sequence_handoff_cursor_next210' => $handoffCursor,
            'expected_sequence_handoff_cursor_next210' => $expectedHandoffCursor,
            'sequence_handoff_cursor_matches_next210' => $cursorMatches,
            'current_source_signature_next210' => $sourceSignature,
            'expected_current_source_signature_next210' => $expectedSourceSignature,
            'current_source_signature_matches_next210' => $sourceSignatureMatches,
            'required_current_source_sequence_next210' => $requiredSequence,
            'acknowledged_current_source_sequence_next210' => $acknowledgedSequence,
            'missing_current_source_sequence_next210' => $missingSequence,
            'unexpected_current_source_sequence_next210' => $unexpectedSequence,
            'require_current_source_sequence_order_next210' => $requireOrder,
            'current_source_sequence_order_matches_next210' => $orderMatches,
            'current_source_sequence_complete_next210' => $sequenceComplete,
            'next_source_visible_after_current_source_sequence_next210' => $nextVisible,
            'current_source_rows_next210' => $taggedCurrent,
            'attempted_next_source_rows_next210' => $taggedNext,
            'visible_returning_rows_next210' => $visibleRows,
            'held_next_source_rows_next210' => $heldRows,
            'visible_returning_payloads_next210' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next210' => array_column($heldRows, 'returning'),
            'current_source_row_count_next210' => count($taggedCurrent),
            'attempted_next_source_row_count_next210' => count($taggedNext),
            'visible_row_count_next210' => count($visibleRows),
            'held_next_row_count_next210' => count($heldRows),
            'blocked_reasons_next210' => $blockedReasons,
            'current_source_sequence_plan_next210' => [
                'base_next_source_visible' => $baseVisible,
                'source_signature' => $sourceSignature,
                'source_signature_matches' => $sourceSignatureMatches,
                'required_sequence' => $requiredSequence,
                'acknowledged_sequence' => $acknowledgedSequence,
                'missing_sequence' => $missingSequence,
                'unexpected_sequence' => $unexpectedSequence,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'sequence_complete' => $sequenceComplete,
                'handoff_cursor_matches' => $cursorMatches,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-source-sequence'
                    : 'hold-next-source-until-current-source-sequence',
            ],
            'yield_boundary_next210' => $nextVisible
                ? 'recursive-view-returning-next210-current-source-sequence-then-next'
                : 'recursive-view-returning-next210-current-source-sequence-fences-next',
            'dependency_closure_next210' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-drain-and-adds-ordered-source-sequence-fence',
            'dependencies_next210' => array_values(array_unique(array_merge($base['dependencies_next209'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next210',
                'sqlite-returning-current-source-ordered-sequence-fence',
                'wordpress-recursive-view-returning-current-source-next210',
            ]))),
            'non_overlap_next210' => 'adds ordered current-source sequence fencing after next209 drain watermarks; avoids accepted next209 drain, next208 cursor close, next203 generation handoff, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function sequence(array $rows, string $sequenceToken, string $handoffCursor, string $viewCookie, string $triggerCookie): array
    {
        $sequence = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $sequenceToken,
                $handoffCursor,
                $viewCookie,
                $triggerCookie,
                (string) ($row['current_source_watermark_next209'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
            ];
            $sequence[] = substr(hash('sha256', implode('|', $parts)), 0, 34);
        }

        return $sequence;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedSequence(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_sequence_next210'] ?? false) === true) {
            return $required;
        }

        return self::sequenceList($options['acknowledged_current_source_sequence_next210'] ?? [], 'acknowledged current source sequence');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function sequenceList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next210 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{34}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next210 {$label} contains a malformed sequence token");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next210 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next210 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $sequence
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentRows(array $rows, array $sequence, string $sequenceToken, string $handoffCursor, string $sourceSignature): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_sequence_phase_next210' => 'current',
                'current_source_sequence_token_next210' => $sequenceToken,
                'sequence_handoff_cursor_next210' => $handoffCursor,
                'current_source_signature_next210' => $sourceSignature,
                'current_source_sequence_next210' => $sequence[$index] ?? null,
                'visible_after_current_source_sequence_next210' => true,
                'held_by_current_source_sequence_reasons_next210' => [],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagNextRows(array $rows, bool $visible, string $sequenceToken, string $handoffCursor, string $sourceSignature, array $reasons): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'source_sequence_phase_next210' => 'next',
                'current_source_sequence_token_next210' => $sequenceToken,
                'sequence_handoff_cursor_next210' => $handoffCursor,
                'current_source_signature_next210' => $sourceSignature,
                'current_source_sequence_next210' => null,
                'visible_after_current_source_sequence_next210' => $visible,
                'held_by_current_source_sequence_reasons_next210' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @param list<string> $missingSequence
     * @param list<string> $unexpectedSequence
     * @return list<string>
     */
    private static function blockedReasons(
        mixed $baseReasons,
        bool $baseVisible,
        bool $sequenceComplete,
        array $missingSequence,
        array $unexpectedSequence,
        bool $requireOrder,
        bool $orderMatches,
        bool $cursorMatches,
        bool $sourceSignatureMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next210 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'next209-current-source-drain-held';
        }
        if (!$sequenceComplete && $missingSequence !== []) {
            $reasons[] = 'current-source-sequence-missing';
        }
        if (!$sequenceComplete && $unexpectedSequence !== []) {
            $reasons[] = 'current-source-sequence-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-sequence-order-mismatch';
        }
        if (!$cursorMatches) {
            $reasons[] = 'current-source-sequence-cursor-mismatch';
        }
        if (!$sourceSignatureMatches) {
            $reasons[] = 'current-source-signature-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function sourceSignature(string $viewCookie, string $triggerCookie, string $sequenceToken): string
    {
        return substr(hash('sha256', $viewCookie . '|' . $triggerCookie . '|' . $sequenceToken), 0, 34);
    }

    private static function status(bool $baseVisible, bool $sequenceComplete, bool $cursorMatches, bool $sourceSignatureMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next210-sequence-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next210-base-held';
        }
        if (!$sequenceComplete) {
            return 'trigger-recursive-view-returning-current-source-next210-sequence-held';
        }
        if (!$cursorMatches) {
            return 'trigger-recursive-view-returning-current-source-next210-cursor-held';
        }
        if (!$sourceSignatureMatches) {
            return 'trigger-recursive-view-returning-current-source-next210-source-held';
        }

        return 'trigger-recursive-view-returning-current-source-next210-held';
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next210 {$label} is malformed");
        }

        return $value;
    }
}
