<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext219Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext217Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $resetToken = self::token((string) ($options['next_source_reset_token_next219'] ?? 'wp.next.source.reset.219'), 'next source reset token');
        $resetCursor = self::token((string) ($options['next_source_reset_cursor_next219'] ?? 'wp.returning.next.reset.cursor.219'), 'next source reset cursor');
        $expectedResetCursor = self::token((string) ($options['expected_next_source_reset_cursor_next219'] ?? $resetCursor), 'expected next source reset cursor');
        $followingToken = self::token((string) ($options['following_current_source_token_next219'] ?? 'wp.current.source.following.219'), 'following current source token');
        $expectedFollowingToken = self::token((string) ($options['expected_following_current_source_token_next219'] ?? $followingToken), 'expected following current source token');
        $followingView = self::view($options['following_current_view_next219'] ?? $currentView);
        $followingInput = self::inputRows($options['following_current_input_next219'] ?? [], 'following current input');
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_provenance_next217'] ?? false);
        $nextRows = self::rows($base['attempted_next_source_rows_next217'] ?? [], 'attempted next source rows');
        $requiredReset = self::resetReceipts($nextRows, $resetToken, $resetCursor);
        $acknowledgedReset = self::acknowledgedReset($options, $requiredReset);
        $missingReset = array_values(array_diff($requiredReset, $acknowledgedReset));
        $unexpectedReset = array_values(array_diff($acknowledgedReset, $requiredReset));
        $requireOrder = (bool) ($options['require_next_source_reset_order_next219'] ?? true);
        $orderMatches = !$requireOrder || $requiredReset === $acknowledgedReset;
        $resetCursorMatches = hash_equals($resetCursor, $expectedResetCursor);
        $followingTokenMatches = hash_equals($followingToken, $expectedFollowingToken);
        $resetComplete = $requiredReset !== []
            && $missingReset === []
            && $unexpectedReset === []
            && $orderMatches;
        $followingVisible = $baseVisible && $resetComplete && $resetCursorMatches && $followingTokenMatches;
        $reasons = self::blockedReasons(
            $base['blocked_reasons_next217'] ?? [],
            $baseVisible,
            $missingReset,
            $unexpectedReset,
            $requireOrder,
            $orderMatches,
            $resetCursorMatches,
            $followingTokenMatches,
        );

        $taggedNext = self::tagNextRows($nextRows, $requiredReset, $resetToken, $resetCursor, $followingVisible ? [] : $reasons);
        $followingRows = $followingVisible
            ? self::followingRows($followingInput, $followingView, $returning, $followingToken, $resetToken, $resetCursor)
            : [];
        $visibleRows = array_values(array_merge(
            self::rows($base['visible_returning_rows_next217'] ?? [], 'base visible rows'),
            $followingRows,
        ));

        return [
            'status_next219' => self::status($baseVisible, $resetComplete, $resetCursorMatches, $followingTokenMatches, $followingVisible, $missingReset, $unexpectedReset, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next219' => $baseVisible,
            'next_source_reset_token_next219' => $resetToken,
            'next_source_reset_cursor_next219' => $resetCursor,
            'expected_next_source_reset_cursor_next219' => $expectedResetCursor,
            'next_source_reset_cursor_matches_next219' => $resetCursorMatches,
            'following_current_source_token_next219' => $followingToken,
            'expected_following_current_source_token_next219' => $expectedFollowingToken,
            'following_current_source_token_matches_next219' => $followingTokenMatches,
            'required_next_source_reset_receipts_next219' => $requiredReset,
            'acknowledged_next_source_reset_receipts_next219' => $acknowledgedReset,
            'missing_next_source_reset_receipts_next219' => $missingReset,
            'unexpected_next_source_reset_receipts_next219' => $unexpectedReset,
            'require_next_source_reset_order_next219' => $requireOrder,
            'next_source_reset_order_matches_next219' => $orderMatches,
            'next_source_reset_complete_next219' => $resetComplete,
            'following_current_source_visible_next219' => $followingVisible,
            'attempted_next_source_rows_next219' => $taggedNext,
            'following_current_rows_next219' => $followingRows,
            'visible_returning_rows_next219' => $visibleRows,
            'visible_returning_payloads_next219' => array_column($visibleRows, 'returning'),
            'following_current_payloads_next219' => array_column($followingRows, 'returning'),
            'attempted_next_source_row_count_next219' => count($taggedNext),
            'following_current_row_count_next219' => count($followingRows),
            'visible_row_count_next219' => count($visibleRows),
            'blocked_reasons_next219' => $reasons,
            'next_source_reset_plan_next219' => [
                'base_next_source_visible' => $baseVisible,
                'required_reset_receipts' => $requiredReset,
                'acknowledged_reset_receipts' => $acknowledgedReset,
                'missing_reset_receipts' => $missingReset,
                'unexpected_reset_receipts' => $unexpectedReset,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'reset_cursor_matches' => $resetCursorMatches,
                'following_token_matches' => $followingTokenMatches,
                'reset_complete' => $resetComplete,
                'following_current_source_visible' => $followingVisible,
                'decision' => $followingVisible
                    ? 'admit-following-current-source-after-next-returning-reset'
                    : 'hold-following-current-source-until-next-returning-reset',
            ],
            'yield_boundary_next219' => $followingVisible
                ? 'recursive-view-returning-next219-next-source-reset-then-following-current'
                : 'recursive-view-returning-next219-next-source-reset-fences-following-current',
            'dependency_closure_next219' => 'no-new-support-component-reuses-native-recursive-view-returning-provenance-and-adds-next-source-reset-admission-fence',
            'dependencies_next219' => array_values(array_unique(array_merge($base['dependencies_next217'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next219',
                'sqlite-returning-next-source-reset-following-current-fence',
                'wordpress-recursive-view-returning-current-source-next219',
            ]))),
            'non_overlap_next219' => 'adds next-source RETURNING reset admission before a following current-source view trigger generation; avoids next217 provenance, next212 yield receipts, next210 sequence, next211 source seal, row-value RETURNING savepoints, DML RETURNING conflicts, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function resetReceipts(array $rows, string $token, string $cursor): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts = [
                $token,
                $cursor,
                (string) ($row['current_view_source_next217'] ?? ''),
                (string) ($row['current_trigger_source_next217'] ?? ''),
                (string) ($row['source_provenance_phase_next217'] ?? ''),
                (string) ($returning['trigger_source_alias'] ?? ''),
                (string) ($returning['event_name'] ?? ''),
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
    private static function acknowledgedReset(array $options, array $required): array
    {
        if (($options['auto_ack_next_source_reset_next219'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_next_source_reset_receipts_next219'] ?? [], 'acknowledged next source reset receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next219 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{34}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next219 {$label} contains a malformed reset receipt");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next219 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next219 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function inputRows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next219 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next219 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    private static function view(mixed $view): array
    {
        if (!is_array($view) || !isset($view['source'], $view['trigger_source']) || !is_string($view['source']) || !is_string($view['trigger_source'])) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next219 following current view is malformed');
        }

        return $view;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagNextRows(array $rows, array $receipts, string $token, string $cursor, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'next_source_reset_token_next219' => $token,
                'next_source_reset_cursor_next219' => $cursor,
                'next_source_reset_receipt_next219' => $receipts[$index] ?? null,
                'next_source_reset_reasons_next219' => $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $input
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return list<array<string,mixed>>
     */
    private static function followingRows(array $input, array $view, array $returning, string $followingToken, string $resetToken, string $resetCursor): array
    {
        $rows = [];
        foreach ($input as $row) {
            $new = [
                'option_name' => (string) ($row['name'] ?? $row['option_name'] ?? ''),
                'option_value' => (string) ($row['value'] ?? $row['option_value'] ?? ''),
                'autoload' => (string) ($row['autoload_flag'] ?? $row['autoload'] ?? 'yes'),
                'spawn_child' => (bool) ($row['spawn_child'] ?? false),
            ];
            $rows[] = [
                'statement_source' => 'following-current-after-next-reset',
                'returning_row_ordinal' => count($rows),
                'returning' => self::returningPayload($returning, $new, $view, count($rows)),
                'returning_option_name' => $new['option_name'],
                'following_current_source_token_next219' => $followingToken,
                'next_source_reset_token_next219' => $resetToken,
                'next_source_reset_cursor_next219' => $resetCursor,
                'current_view_source_next219' => (string) $view['source'],
                'current_trigger_source_next219' => (string) $view['trigger_source'],
                'visible_after_next_source_reset_next219' => true,
            ];
        }

        return $rows;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return array<string,mixed>
     */
    private static function returningPayload(array $returning, array $new, array $view, int $ordinal): array
    {
        $payload = [];
        foreach ($returning as $term) {
            if (is_callable($term)) {
                $payload['expr_' . count($payload)] = $term($new, null, $view, 'following-current-after-next-reset', 0, $ordinal, (string) ($view['trigger_source'] ?? ''));
                continue;
            }
            $expr = is_array($term) ? (string) ($term['expr'] ?? '') : (string) $term;
            $alias = is_array($term) ? (string) ($term['as'] ?? $expr) : $expr;
            $payload[$alias] = match ($expr) {
                'new.option_name' => $new['option_name'],
                'new.option_value' => $new['option_value'],
                'old.option_value' => null,
                'event' => 'following-current-after-next-reset',
                'depth' => 0,
                'ordinal' => $ordinal,
                'trigger_source' => (string) ($view['trigger_source'] ?? ''),
                'spawn_child' => $new['spawn_child'],
                'view.name' => (string) ($view['name'] ?? ''),
                default => $new[$expr] ?? null,
            };
        }

        return $payload;
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
        bool $resetCursorMatches,
        bool $followingTokenMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next219 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next217-provenance-not-published';
        }
        if ($missing !== []) {
            $reasons[] = 'next-source-reset-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'next-source-reset-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'next-source-reset-order-mismatch';
        }
        if (!$resetCursorMatches) {
            $reasons[] = 'next-source-reset-cursor-mismatch';
        }
        if (!$followingTokenMatches) {
            $reasons[] = 'following-current-source-token-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(
        bool $baseVisible,
        bool $resetComplete,
        bool $resetCursorMatches,
        bool $followingTokenMatches,
        bool $followingVisible,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): string {
        if ($followingVisible) {
            return 'trigger-recursive-view-returning-current-source-next219-following-current-visible';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next219-base-held';
        }
        if (!$resetCursorMatches) {
            return 'trigger-recursive-view-returning-current-source-next219-reset-cursor-held';
        }
        if (!$followingTokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next219-following-token-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-returning-current-source-next219-reset-order-held';
        }
        if (!$resetComplete) {
            return 'trigger-recursive-view-returning-current-source-next219-reset-held';
        }

        return 'trigger-recursive-view-returning-current-source-next219-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next219 {$label} is malformed");
        }

        return $token;
    }
}
