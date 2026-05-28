<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext230Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext226Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseVisible = (bool) ($base['subsequent_next_source_visible_next226'] ?? false);
        $followingRows = self::rows($base['following_current_rows_next226'] ?? [], 'following current rows');
        $subsequentRows = self::rows($base['subsequent_next_rows_next226'] ?? [], 'subsequent next rows');
        $epoch = self::token((string) ($options['current_source_epoch_next230'] ?? 'wp.current.source.epoch.230'), 'current source epoch');
        $expectedEpoch = self::token((string) ($options['expected_current_source_epoch_next230'] ?? $epoch), 'expected current source epoch');
        $epochCursor = self::token((string) ($options['current_source_epoch_cursor_next230'] ?? 'wp.returning.current.epoch.cursor.230'), 'current source epoch cursor');
        $expectedEpochCursor = self::token((string) ($options['expected_current_source_epoch_cursor_next230'] ?? $epochCursor), 'expected current source epoch cursor');
        $requiredEpochs = self::epochReceipts($followingRows, $epoch, $epochCursor);
        $acknowledgedEpochs = self::acknowledgedEpochs($options, $requiredEpochs);
        $missingEpochs = array_values(array_diff($requiredEpochs, $acknowledgedEpochs));
        $unexpectedEpochs = array_values(array_diff($acknowledgedEpochs, $requiredEpochs));
        $requireOrder = (bool) ($options['require_current_source_epoch_order_next230'] ?? true);
        $orderMatches = !$requireOrder || $requiredEpochs === $acknowledgedEpochs;
        $epochMatches = hash_equals($epoch, $expectedEpoch);
        $cursorMatches = hash_equals($epochCursor, $expectedEpochCursor);
        $epochComplete = $requiredEpochs !== []
            && $missingEpochs === []
            && $unexpectedEpochs === []
            && $orderMatches;
        $nextVisible = $baseVisible && $epochComplete && $epochMatches && $cursorMatches;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next226'] ?? [],
            $baseVisible,
            $missingEpochs,
            $unexpectedEpochs,
            $requireOrder,
            $orderMatches,
            $epochMatches,
            $cursorMatches,
        );

        $taggedFollowing = self::tagRows($followingRows, 'following-current', true, $requiredEpochs, $epoch, $epochCursor, []);
        $taggedSubsequent = self::tagRows($subsequentRows, 'subsequent-next', $nextVisible, [], $epoch, $epochCursor, $nextVisible ? [] : $blockedReasons);
        $baseVisibleRows = self::rows($base['visible_returning_rows_next226'] ?? [], 'base visible rows');
        $visibleRows = $nextVisible
            ? $baseVisibleRows
            : array_slice($baseVisibleRows, 0, max(0, count($baseVisibleRows) - count($subsequentRows)));
        $heldRows = array_values(array_filter(
            $taggedSubsequent,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_epoch_next230'],
        ));

        return [
            'status_next230' => self::status($baseVisible, $epochComplete, $epochMatches, $cursorMatches, $nextVisible, $missingEpochs, $unexpectedEpochs, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_subsequent_next_visible_next230' => $baseVisible,
            'current_source_epoch_next230' => $epoch,
            'expected_current_source_epoch_next230' => $expectedEpoch,
            'current_source_epoch_matches_next230' => $epochMatches,
            'current_source_epoch_cursor_next230' => $epochCursor,
            'expected_current_source_epoch_cursor_next230' => $expectedEpochCursor,
            'current_source_epoch_cursor_matches_next230' => $cursorMatches,
            'required_current_source_epoch_receipts_next230' => $requiredEpochs,
            'acknowledged_current_source_epoch_receipts_next230' => $acknowledgedEpochs,
            'missing_current_source_epoch_receipts_next230' => $missingEpochs,
            'unexpected_current_source_epoch_receipts_next230' => $unexpectedEpochs,
            'require_current_source_epoch_order_next230' => $requireOrder,
            'current_source_epoch_order_matches_next230' => $orderMatches,
            'current_source_epoch_complete_next230' => $epochComplete,
            'subsequent_next_source_visible_after_epoch_next230' => $nextVisible,
            'following_current_rows_next230' => $taggedFollowing,
            'subsequent_next_rows_next230' => $taggedSubsequent,
            'visible_returning_rows_next230' => $visibleRows,
            'visible_returning_payloads_next230' => array_column($visibleRows, 'returning'),
            'held_subsequent_next_rows_next230' => $heldRows,
            'held_subsequent_next_payloads_next230' => array_column($heldRows, 'returning'),
            'following_current_row_count_next230' => count($taggedFollowing),
            'subsequent_next_row_count_next230' => count($taggedSubsequent),
            'visible_row_count_next230' => count($visibleRows),
            'held_subsequent_next_row_count_next230' => count($heldRows),
            'blocked_reasons_next230' => $blockedReasons,
            'current_source_epoch_plan_next230' => [
                'base_subsequent_next_visible' => $baseVisible,
                'required_epoch_receipts' => $requiredEpochs,
                'acknowledged_epoch_receipts' => $acknowledgedEpochs,
                'missing_epoch_receipts' => $missingEpochs,
                'unexpected_epoch_receipts' => $unexpectedEpochs,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'epoch_matches' => $epochMatches,
                'cursor_matches' => $cursorMatches,
                'epoch_complete' => $epochComplete,
                'subsequent_next_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-subsequent-next-source-after-current-epoch'
                    : 'hold-subsequent-next-source-until-current-epoch',
            ],
            'yield_boundary_next230' => $nextVisible
                ? 'recursive-view-returning-next230-current-source-epoch-then-subsequent-next'
                : 'recursive-view-returning-next230-current-source-epoch-fences-subsequent-next',
            'dependency_closure_next230' => 'no-new-support-component-reuses-native-recursive-view-returning-next226-and-adds-current-source-epoch-admission',
            'dependencies_next230' => array_values(array_unique(array_merge($base['dependencies_next226'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next230',
                'sqlite-returning-current-source-epoch-fence',
                'wordpress-recursive-view-returning-current-source-next230',
            ]))),
            'non_overlap_next230' => 'adds current-source epoch receipt admission after next226 following-current seal; avoids next226 seal, next222 source ticket, next219 reset, next212 yield receipts, row-value RETURNING savepoints, DML RETURNING conflicts, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function epochReceipts(array $rows, string $epoch, string $cursor): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts = [
                $epoch,
                $cursor,
                (string) ($row['current_source_yield_token_next212'] ?? ''),
                (string) ($row['following_current_seal_token_next226'] ?? ''),
                (string) ($row['current_view_source_next219'] ?? ''),
                (string) ($row['current_trigger_source_next219'] ?? ''),
                (string) ($returning['trigger_source_alias'] ?? ''),
                (string) ($returning['ordinal_value'] ?? $index),
                (string) ($returning['name'] ?? ''),
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
    private static function acknowledgedEpochs(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_epoch_next230'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_current_source_epoch_receipts_next230'] ?? [], 'acknowledged current source epoch receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next230 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{34}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next230 {$label} contains a malformed epoch receipt");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next230 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next230 {$label} contain a malformed row");
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
    private static function tagRows(array $rows, string $phase, bool $visible, array $receipts, string $epoch, string $cursor, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_epoch_phase_next230' => $phase,
                'current_source_epoch_next230' => $epoch,
                'current_source_epoch_cursor_next230' => $cursor,
                'current_source_epoch_receipt_next230' => $receipts[$index] ?? null,
                'visible_after_current_source_epoch_next230' => $visible,
                'held_by_current_source_epoch_reasons_next230' => $visible ? [] : $reasons,
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
        bool $epochMatches,
        bool $cursorMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next230 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next226-following-current-seal-held';
        }
        if ($missing !== []) {
            $reasons[] = 'current-source-epoch-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-source-epoch-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'current-source-epoch-order-mismatch';
        }
        if (!$epochMatches) {
            $reasons[] = 'current-source-epoch-mismatch';
        }
        if (!$cursorMatches) {
            $reasons[] = 'current-source-epoch-cursor-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(bool $baseVisible, bool $epochComplete, bool $epochMatches, bool $cursorMatches, bool $nextVisible, array $missing, array $unexpected, bool $requireOrder, bool $orderMatches): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next230-subsequent-next-visible';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next230-base-held';
        }
        if (!$epochMatches) {
            return 'trigger-recursive-view-returning-current-source-next230-epoch-held';
        }
        if (!$cursorMatches) {
            return 'trigger-recursive-view-returning-current-source-next230-cursor-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches && !$epochComplete) {
            return 'trigger-recursive-view-returning-current-source-next230-epoch-order-held';
        }

        return 'trigger-recursive-view-returning-current-source-next230-epoch-receipt-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next230 {$label} is malformed");
        }

        return $token;
    }
}
