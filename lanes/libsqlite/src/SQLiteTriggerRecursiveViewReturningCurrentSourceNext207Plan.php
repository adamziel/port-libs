<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext207Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext206Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rows($base['current_source_rows_next206'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows_next206'] ?? [], 'attempted next source rows');
        $baseVisible = (bool) ($base['next_source_visible_after_yield_watermark_next206'] ?? false);
        $drainToken = self::token((string) ($options['current_returning_drain_token_next207'] ?? 'wp.current.returning.drain.207'), 'current drain token');
        $expectedDrainToken = self::token((string) ($options['expected_current_returning_drain_token_next207'] ?? $drainToken), 'expected current drain token');
        $cursor = self::token((string) ($options['current_returning_cursor_next207'] ?? 'wp.current.returning.cursor.207'), 'current returning cursor');
        $statementToken = self::token((string) ($options['returning_statement_token_next207'] ?? 'wp.recursive.view.returning.statement.207'), 'statement token');
        $drainKeys = self::drainKeys($currentRows, $drainToken, $cursor, $statementToken);
        $acknowledgedDrainKeys = self::acknowledgedDrainKeys($options, $drainKeys);
        $requireOrder = (bool) ($options['require_returning_drain_order_next207'] ?? true);
        $missing = array_values(array_diff($drainKeys, $acknowledgedDrainKeys));
        $unexpected = array_values(array_diff($acknowledgedDrainKeys, $drainKeys));
        $orderMatches = !$requireOrder || $drainKeys === $acknowledgedDrainKeys;
        $drainTokenMatches = hash_equals($drainToken, $expectedDrainToken);
        $expectedCount = self::nonNegativeInt($options['expected_current_returning_drain_count_next207'] ?? count($currentRows), 'expected current drain count');
        $countMatches = count($currentRows) === $expectedCount;
        $drainClear = $drainKeys !== []
            && $missing === []
            && $unexpected === []
            && $orderMatches
            && $drainTokenMatches
            && $countMatches;
        $nextVisible = $baseVisible && $drainClear;
        $blocked = self::blockedReasons(
            $base['blocked_reasons_next206'] ?? [],
            $baseVisible,
            $drainTokenMatches,
            $countMatches,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
        );

        $taggedCurrent = self::tagCurrent($currentRows, $drainKeys, $drainToken, $cursor, $statementToken);
        $taggedNext = self::tagNext($nextRows, $nextVisible, $blocked, $drainToken, $cursor, $statementToken);
        $visibleRows = array_values(array_filter(
            array_merge($taggedCurrent, $taggedNext),
            static fn (array $row): bool => (bool) $row['visible_after_current_drain_next207'],
        ));
        $heldRows = array_values(array_filter(
            $taggedNext,
            static fn (array $row): bool => !(bool) $row['visible_after_current_drain_next207'],
        ));

        return [
            'status_next207' => self::status($nextVisible, $baseVisible, $drainTokenMatches, $countMatches, $missing, $unexpected, $orderMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next207' => $baseVisible,
            'current_returning_drain_token_next207' => $drainToken,
            'expected_current_returning_drain_token_next207' => $expectedDrainToken,
            'current_returning_drain_token_matches_next207' => $drainTokenMatches,
            'current_returning_cursor_next207' => $cursor,
            'returning_statement_token_next207' => $statementToken,
            'current_returning_drain_keys_next207' => $drainKeys,
            'acknowledged_current_returning_drain_keys_next207' => $acknowledgedDrainKeys,
            'missing_current_returning_drain_keys_next207' => $missing,
            'unexpected_current_returning_drain_keys_next207' => $unexpected,
            'require_returning_drain_order_next207' => $requireOrder,
            'current_returning_drain_order_matches_next207' => $orderMatches,
            'current_returning_drain_count_next207' => count($currentRows),
            'expected_current_returning_drain_count_next207' => $expectedCount,
            'current_returning_drain_count_matches_next207' => $countMatches,
            'current_returning_drain_clear_next207' => $drainClear,
            'next_source_visible_after_current_drain_next207' => $nextVisible,
            'current_returning_drain_rows_next207' => $taggedCurrent,
            'attempted_next_returning_drain_rows_next207' => $taggedNext,
            'visible_returning_rows_next207' => $visibleRows,
            'held_next_source_rows_next207' => $heldRows,
            'visible_returning_payloads_next207' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next207' => array_column($heldRows, 'returning'),
            'blocked_reasons_next207' => $blocked,
            'current_drain_plan_next207' => [
                'base_next_source_visible' => $baseVisible,
                'current_rows' => count($taggedCurrent),
                'attempted_next_rows' => count($taggedNext),
                'visible_rows' => count($visibleRows),
                'held_next_rows' => count($heldRows),
                'drain_token_matches' => $drainTokenMatches,
                'drain_count_matches' => $countMatches,
                'missing_drain_keys' => $missing,
                'unexpected_drain_keys' => $unexpected,
                'drain_order_matches' => $orderMatches,
                'drain_clear' => $drainClear,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-returning-drain'
                    : 'hold-next-source-until-current-returning-drain',
            ],
            'yield_boundary_next207' => $nextVisible
                ? 'recursive-view-returning-next207-current-drain-then-next'
                : 'recursive-view-returning-next207-current-drain-fences-next',
            'dependency_closure_next207' => 'no new support component needed; reuses next206 recursive view RETURNING watermark rows and adds current RETURNING drain fencing',
            'dependencies_next207' => array_values(array_unique(array_merge($base['dependencies_next206'], [
                'sqlite-trigger-recursive-view-returning-current-source-next207',
                'sqlite-returning-current-source-drain-fence',
                'wordpress-recursive-view-returning-current-source-next207',
            ]))),
            'non_overlap_next207' => 'adds current RETURNING drain admission after next206 yield watermark; avoids accepted next206 watermark, next205 sequence, next203 generation handoff, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function drainKeys(array $rows, string $drainToken, string $cursor, string $statementToken): array
    {
        $keys = [];
        foreach ($rows as $index => $row) {
            $payload = $row['returning'];
            $parts = [
                $drainToken,
                $cursor,
                $statementToken,
                (string) ($row['yield_watermark_next206'] ?? ''),
                (string) ($row['yield_batch_key_next206'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($payload['name'] ?? $payload['option_name'] ?? $row['returning_option_name'] ?? ''),
            ];
            $keys[] = substr(hash('sha256', implode('|', $parts)), 0, 32);
        }

        return $keys;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedDrainKeys(array $options, array $required): array
    {
        if (($options['auto_ack_current_returning_drain_next207'] ?? false) === true) {
            return $required;
        }

        return self::drainKeyList($options['acknowledged_current_returning_drain_keys_next207'] ?? [], 'acknowledged current drain keys');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function drainKeyList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next207 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{32}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next207 {$label} contains a malformed drain key");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next207 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next207 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $drainKeys
     * @return list<array<string,mixed>>
     */
    private static function tagCurrent(array $rows, array $drainKeys, string $drainToken, string $cursor, string $statementToken): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'drain_phase_next207' => 'current',
                'current_returning_drain_token_next207' => $drainToken,
                'current_returning_cursor_next207' => $cursor,
                'returning_statement_token_next207' => $statementToken,
                'current_returning_drain_key_next207' => $drainKeys[$index] ?? null,
                'visible_after_current_drain_next207' => true,
                'held_by_current_drain_reasons_next207' => [],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $blocked
     * @return list<array<string,mixed>>
     */
    private static function tagNext(array $rows, bool $visible, array $blocked, string $drainToken, string $cursor, string $statementToken): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'drain_phase_next207' => 'next',
                'current_returning_drain_token_next207' => $drainToken,
                'current_returning_cursor_next207' => $cursor,
                'returning_statement_token_next207' => $statementToken,
                'current_returning_drain_key_next207' => null,
                'visible_after_current_drain_next207' => $visible,
                'held_by_current_drain_reasons_next207' => $visible ? [] : $blocked,
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
        bool $drainTokenMatches,
        bool $countMatches,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next207 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'next206-yield-watermark-held';
        }
        if (!$drainTokenMatches) {
            $reasons[] = 'current-returning-drain-token-mismatch';
        }
        if (!$countMatches) {
            $reasons[] = 'current-returning-drain-count-mismatch';
        }
        if ($missing !== []) {
            $reasons[] = 'current-returning-drain-missing';
        }
        if ($unexpected !== []) {
            $reasons[] = 'current-returning-drain-unexpected';
        }
        if ($requireOrder && !$orderMatches) {
            $reasons[] = 'current-returning-drain-order-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    private static function status(
        bool $nextVisible,
        bool $baseVisible,
        bool $drainTokenMatches,
        bool $countMatches,
        array $missing,
        array $unexpected,
        bool $orderMatches,
    ): string {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next207-drain-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next207-base-held';
        }
        if (!$drainTokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next207-token-held';
        }
        if (!$countMatches) {
            return 'trigger-recursive-view-returning-current-source-next207-count-held';
        }
        if ($missing !== [] || $unexpected !== [] || !$orderMatches) {
            return 'trigger-recursive-view-returning-current-source-next207-drain-held';
        }

        return 'trigger-recursive-view-returning-current-source-next207-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next207 {$label} is malformed");
        }

        return $token;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next207 {$label} must be a non-negative integer");
        }

        return $value;
    }
}
