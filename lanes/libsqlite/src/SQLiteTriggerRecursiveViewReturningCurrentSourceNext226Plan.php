<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext226Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext219Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $baseFollowingVisible = (bool) ($base['following_current_source_visible_next219'] ?? false);
        $followingRows = self::rows($base['following_current_rows_next219'] ?? [], 'following current rows');
        $sealToken = self::token((string) ($options['following_current_seal_token_next226'] ?? 'wp.following.current.seal.226'), 'following current seal token');
        $sealCursor = self::token((string) ($options['following_current_seal_cursor_next226'] ?? 'wp.returning.following.current.cursor.226'), 'following current seal cursor');
        $expectedSealCursor = self::token((string) ($options['expected_following_current_seal_cursor_next226'] ?? $sealCursor), 'expected following current seal cursor');
        $subsequentToken = self::token((string) ($options['subsequent_next_source_token_next226'] ?? 'wp.subsequent.next.source.226'), 'subsequent next source token');
        $expectedSubsequentToken = self::token((string) ($options['expected_subsequent_next_source_token_next226'] ?? $subsequentToken), 'expected subsequent next source token');
        $subsequentView = self::view($options['subsequent_next_view_next226'] ?? $nextView);
        $subsequentInput = self::inputRows($options['subsequent_next_input_next226'] ?? [], 'subsequent next input');
        $requiredSeals = self::sealReceipts($followingRows, $sealToken, $sealCursor);
        $acknowledgedSeals = self::acknowledgedSeals($options, $requiredSeals);
        $missingSeals = array_values(array_diff($requiredSeals, $acknowledgedSeals));
        $unexpectedSeals = array_values(array_diff($acknowledgedSeals, $requiredSeals));
        $requireOrder = (bool) ($options['require_following_current_seal_order_next226'] ?? true);
        $orderMatches = !$requireOrder || $requiredSeals === $acknowledgedSeals;
        $sealCursorMatches = hash_equals($sealCursor, $expectedSealCursor);
        $subsequentTokenMatches = hash_equals($subsequentToken, $expectedSubsequentToken);
        $sealComplete = $requiredSeals !== []
            && $missingSeals === []
            && $unexpectedSeals === []
            && $orderMatches;
        $subsequentVisible = $baseFollowingVisible && $sealComplete && $sealCursorMatches && $subsequentTokenMatches;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next219'] ?? [],
            $baseFollowingVisible,
            $missingSeals,
            $unexpectedSeals,
            $requireOrder,
            $orderMatches,
            $sealCursorMatches,
            $subsequentTokenMatches,
        );

        $taggedFollowingRows = self::tagFollowingRows($followingRows, $requiredSeals, $sealToken, $sealCursor, $subsequentVisible ? [] : $blockedReasons);
        $subsequentRows = $subsequentVisible
            ? self::subsequentRows($subsequentInput, $subsequentView, $returning, $subsequentToken, $sealToken, $sealCursor)
            : [];
        $visibleRows = array_values(array_merge(
            self::rows($base['visible_returning_rows_next219'] ?? [], 'base visible rows'),
            $subsequentRows,
        ));

        return [
            'status_next226' => self::status($baseFollowingVisible, $sealComplete, $sealCursorMatches, $subsequentTokenMatches, $subsequentVisible, $missingSeals, $unexpectedSeals, $requireOrder, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_following_current_visible_next226' => $baseFollowingVisible,
            'following_current_seal_token_next226' => $sealToken,
            'following_current_seal_cursor_next226' => $sealCursor,
            'expected_following_current_seal_cursor_next226' => $expectedSealCursor,
            'following_current_seal_cursor_matches_next226' => $sealCursorMatches,
            'subsequent_next_source_token_next226' => $subsequentToken,
            'expected_subsequent_next_source_token_next226' => $expectedSubsequentToken,
            'subsequent_next_source_token_matches_next226' => $subsequentTokenMatches,
            'required_following_current_seal_receipts_next226' => $requiredSeals,
            'acknowledged_following_current_seal_receipts_next226' => $acknowledgedSeals,
            'missing_following_current_seal_receipts_next226' => $missingSeals,
            'unexpected_following_current_seal_receipts_next226' => $unexpectedSeals,
            'require_following_current_seal_order_next226' => $requireOrder,
            'following_current_seal_order_matches_next226' => $orderMatches,
            'following_current_seal_complete_next226' => $sealComplete,
            'subsequent_next_source_visible_next226' => $subsequentVisible,
            'following_current_rows_next226' => $taggedFollowingRows,
            'subsequent_next_rows_next226' => $subsequentRows,
            'visible_returning_rows_next226' => $visibleRows,
            'visible_returning_payloads_next226' => array_column($visibleRows, 'returning'),
            'subsequent_next_payloads_next226' => array_column($subsequentRows, 'returning'),
            'following_current_row_count_next226' => count($taggedFollowingRows),
            'subsequent_next_row_count_next226' => count($subsequentRows),
            'visible_row_count_next226' => count($visibleRows),
            'blocked_reasons_next226' => $blockedReasons,
            'following_current_seal_plan_next226' => [
                'base_following_current_visible' => $baseFollowingVisible,
                'required_seal_receipts' => $requiredSeals,
                'acknowledged_seal_receipts' => $acknowledgedSeals,
                'missing_seal_receipts' => $missingSeals,
                'unexpected_seal_receipts' => $unexpectedSeals,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'seal_cursor_matches' => $sealCursorMatches,
                'subsequent_token_matches' => $subsequentTokenMatches,
                'seal_complete' => $sealComplete,
                'subsequent_next_source_visible' => $subsequentVisible,
                'decision' => $subsequentVisible
                    ? 'admit-subsequent-next-source-after-following-current-seal'
                    : 'hold-subsequent-next-source-until-following-current-seal',
            ],
            'yield_boundary_next226' => $subsequentVisible
                ? 'recursive-view-returning-next226-following-current-sealed-then-subsequent-next'
                : 'recursive-view-returning-next226-following-current-seal-fences-subsequent-next',
            'dependency_closure_next226' => 'no-new-support-component-reuses-native-recursive-view-returning-next219-and-adds-following-current-seal-admission',
            'dependencies_next226' => array_values(array_unique(array_merge($base['dependencies_next219'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next226',
                'sqlite-returning-following-current-seal-subsequent-next-fence',
                'wordpress-recursive-view-returning-current-source-next226',
            ]))),
            'non_overlap_next226' => 'adds following-current RETURNING seal admission before a subsequent next-source view trigger generation; avoids next219 next-source reset, next217 provenance, next212 yield receipts, next190 resume-source validation, row-value RETURNING savepoints, DML RETURNING conflicts, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function sealReceipts(array $rows, string $token, string $cursor): array
    {
        $receipts = [];
        foreach ($rows as $index => $row) {
            $returning = $row['returning'];
            $parts = [
                $token,
                $cursor,
                (string) ($row['current_view_source_next219'] ?? ''),
                (string) ($row['current_trigger_source_next219'] ?? ''),
                (string) ($row['following_current_source_token_next219'] ?? ''),
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
    private static function acknowledgedSeals(array $options, array $required): array
    {
        if (($options['auto_ack_following_current_seal_next226'] ?? false) === true) {
            return $required;
        }

        return self::receiptList($options['acknowledged_following_current_seal_receipts_next226'] ?? [], 'acknowledged following current seal receipts');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function receiptList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next226 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{34}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next226 {$label} contains a malformed seal receipt");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next226 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next226 {$label} contain a malformed row");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next226 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next226 {$label} contain a malformed row");
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
            throw new InvalidArgumentException('SQLite recursive view RETURNING next226 subsequent next view is malformed');
        }

        return $view;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $receipts
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagFollowingRows(array $rows, array $receipts, string $token, string $cursor, array $reasons): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'following_current_seal_token_next226' => $token,
                'following_current_seal_cursor_next226' => $cursor,
                'following_current_seal_receipt_next226' => $receipts[$index] ?? null,
                'following_current_seal_reasons_next226' => $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $input
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     * @return list<array<string,mixed>>
     */
    private static function subsequentRows(array $input, array $view, array $returning, string $subsequentToken, string $sealToken, string $sealCursor): array
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
                'statement_source' => 'subsequent-next-after-following-current-seal',
                'returning_row_ordinal' => count($rows),
                'returning' => self::returningPayload($returning, $new, $view, count($rows)),
                'returning_option_name' => $new['option_name'],
                'subsequent_next_source_token_next226' => $subsequentToken,
                'following_current_seal_token_next226' => $sealToken,
                'following_current_seal_cursor_next226' => $sealCursor,
                'next_view_source_next226' => (string) $view['source'],
                'next_trigger_source_next226' => (string) $view['trigger_source'],
                'visible_after_following_current_seal_next226' => true,
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
                $payload['expr_' . count($payload)] = $term($new, null, $view, 'subsequent-next-after-following-current-seal', 0, $ordinal, (string) ($view['trigger_source'] ?? ''));
                continue;
            }
            $expr = is_array($term) ? (string) ($term['expr'] ?? '') : (string) $term;
            $alias = is_array($term) ? (string) ($term['as'] ?? $expr) : $expr;
            $payload[$alias] = match ($expr) {
                'new.option_name' => $new['option_name'],
                'new.option_value' => $new['option_value'],
                'old.option_value' => null,
                'event' => 'subsequent-next-after-following-current-seal',
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
        bool $baseFollowingVisible,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
        bool $sealCursorMatches,
        bool $subsequentTokenMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next226 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseFollowingVisible && $reasons === []) {
            $reasons[] = 'following-current-next219-not-visible';
        }
        if ($missing !== []) {
            $reasons[] = 'following-current-seal-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'following-current-seal-unexpected';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            $reasons[] = 'following-current-seal-order-mismatch';
        }
        if (!$sealCursorMatches) {
            $reasons[] = 'following-current-seal-cursor-mismatch';
        }
        if (!$subsequentTokenMatches) {
            $reasons[] = 'subsequent-next-source-token-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(
        bool $baseFollowingVisible,
        bool $sealComplete,
        bool $sealCursorMatches,
        bool $subsequentTokenMatches,
        bool $subsequentVisible,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): string {
        if ($subsequentVisible) {
            return 'trigger-recursive-view-returning-current-source-next226-subsequent-next-visible';
        }
        if (!$baseFollowingVisible) {
            return 'trigger-recursive-view-returning-current-source-next226-base-held';
        }
        if (!$sealCursorMatches) {
            return 'trigger-recursive-view-returning-current-source-next226-seal-cursor-held';
        }
        if (!$subsequentTokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next226-subsequent-token-held';
        }
        if ($missing === [] && $unexpected === [] && $requireOrder && !$orderMatches) {
            return 'trigger-recursive-view-returning-current-source-next226-seal-order-held';
        }
        if (!$sealComplete) {
            return 'trigger-recursive-view-returning-current-source-next226-seal-held';
        }

        return 'trigger-recursive-view-returning-current-source-next226-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next226 {$label} is malformed");
        }

        return $token;
    }
}
