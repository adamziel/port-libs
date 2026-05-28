<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext205Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext203Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentRows = self::rows($base['current_generation_rows_next203'] ?? [], 'current generation rows');
        $nextRows = self::rows($base['attempted_next_generation_rows_next203'] ?? [], 'attempted next generation rows');
        $baseVisible = (bool) ($base['next_source_visible_after_current_generation_next203'] ?? false);
        $sourceToken = self::token((string) ($options['current_source_sequence_token_next205'] ?? 'wp.current.returning.source.sequence.205'), 'current source sequence token');
        $expectedSourceToken = self::token((string) ($options['expected_current_source_sequence_token_next205'] ?? $sourceToken), 'expected current source sequence token');
        $nextSourceToken = self::token((string) ($options['next_source_sequence_token_next205'] ?? 'wp.next.returning.source.sequence.205'), 'next source sequence token');
        $expectedNextSourceToken = self::token((string) ($options['expected_next_source_sequence_token_next205'] ?? $nextSourceToken), 'expected next source sequence token');
        $cursor = self::token((string) ($options['source_sequence_cursor_next205'] ?? 'wp.returning.source.sequence.cursor.205'), 'source sequence cursor');
        $sequence = self::sequence($currentRows, $sourceToken, $cursor);
        $acknowledged = self::acknowledgedSequence($options, $sequence);
        $requireOrder = (bool) ($options['require_source_sequence_order_next205'] ?? true);
        $missing = array_values(array_diff($sequence, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $sequence));
        $orderMatches = !$requireOrder || $sequence === $acknowledged;
        $sourceTokenMatches = hash_equals($sourceToken, $expectedSourceToken);
        $nextSourceTokenMatches = hash_equals($nextSourceToken, $expectedNextSourceToken);
        $sourceFenceClear = $sequence !== []
            && $missing === []
            && $unexpected === []
            && $orderMatches
            && $sourceTokenMatches
            && $nextSourceTokenMatches;
        $nextVisible = $baseVisible && $sourceFenceClear;
        $blocked = self::blockedReasons(
            $base['blocked_reasons_next203'] ?? [],
            $baseVisible,
            $sourceFenceClear,
            $missing,
            $unexpected,
            $requireOrder,
            $orderMatches,
            $sourceTokenMatches,
            $nextSourceTokenMatches,
        );

        $taggedCurrent = self::tagCurrent($currentRows, $sequence, $sourceToken, $nextSourceToken, $cursor);
        $taggedNext = self::tagNext($nextRows, $nextVisible, $sourceToken, $nextSourceToken, $cursor, $blocked);
        $visibleRows = array_values(array_filter(
            array_merge($taggedCurrent, $taggedNext),
            static fn (array $row): bool => (bool) $row['visible_after_source_sequence_next205'],
        ));
        $heldRows = array_values(array_filter(
            $taggedNext,
            static fn (array $row): bool => !(bool) $row['visible_after_source_sequence_next205'],
        ));

        return [
            'status_next205' => self::status($baseVisible, $sourceFenceClear, $sourceTokenMatches, $nextSourceTokenMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next205' => $baseVisible,
            'current_source_sequence_token_next205' => $sourceToken,
            'expected_current_source_sequence_token_next205' => $expectedSourceToken,
            'current_source_sequence_token_matches_next205' => $sourceTokenMatches,
            'next_source_sequence_token_next205' => $nextSourceToken,
            'expected_next_source_sequence_token_next205' => $expectedNextSourceToken,
            'next_source_sequence_token_matches_next205' => $nextSourceTokenMatches,
            'source_sequence_cursor_next205' => $cursor,
            'required_current_source_sequence_next205' => $sequence,
            'acknowledged_current_source_sequence_next205' => $acknowledged,
            'missing_current_source_sequence_next205' => $missing,
            'unexpected_current_source_sequence_next205' => $unexpected,
            'require_source_sequence_order_next205' => $requireOrder,
            'current_source_sequence_order_matches_next205' => $orderMatches,
            'current_source_sequence_fence_clear_next205' => $sourceFenceClear,
            'next_source_visible_after_source_sequence_next205' => $nextVisible,
            'current_source_sequence_rows_next205' => $taggedCurrent,
            'attempted_next_source_sequence_rows_next205' => $taggedNext,
            'visible_returning_rows_next205' => $visibleRows,
            'held_next_source_rows_next205' => $heldRows,
            'visible_returning_payloads_next205' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next205' => array_column($heldRows, 'returning'),
            'current_source_sequence_row_count_next205' => count($taggedCurrent),
            'attempted_next_source_sequence_row_count_next205' => count($taggedNext),
            'visible_row_count_next205' => count($visibleRows),
            'held_next_row_count_next205' => count($heldRows),
            'blocked_reasons_next205' => $blocked,
            'source_sequence_plan_next205' => [
                'base_next_source_visible' => $baseVisible,
                'required_sequence' => $sequence,
                'acknowledged_sequence' => $acknowledged,
                'missing_sequence' => $missing,
                'unexpected_sequence' => $unexpected,
                'require_order' => $requireOrder,
                'order_matches' => $orderMatches,
                'current_source_token_matches' => $sourceTokenMatches,
                'next_source_token_matches' => $nextSourceTokenMatches,
                'source_sequence_fence_clear' => $sourceFenceClear,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-source-sequence'
                    : 'hold-next-source-until-current-source-sequence',
            ],
            'yield_boundary_next205' => $nextVisible
                ? 'recursive-view-returning-next205-current-source-sequence-then-next'
                : 'recursive-view-returning-next205-current-source-sequence-fences-next',
            'dependency_closure_next205' => 'no new support component needed; reuses native recursive view RETURNING current-source sequence fencing',
            'dependencies_next205' => array_values(array_unique(array_merge($base['dependencies_next203'], [
                'sqlite-trigger-recursive-view-returning-current-source-next205',
                'sqlite-returning-current-source-sequence-fence',
                'wordpress-recursive-view-returning-current-source-next205',
            ]))),
            'non_overlap_next205' => 'adds a source-sequence fence after next203 generation receipts; avoids accepted next203 generation handoff, next196 child drain, next195 receipt fence, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function sequence(array $rows, string $sourceToken, string $cursor): array
    {
        $sequence = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $sourceToken,
                $cursor,
                (string) ($row['current_generation_receipt_next203'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning_option_name'] ?? ''),
            ];
            $sequence[] = substr(hash('sha256', implode('|', $parts)), 0, 32);
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
        if (($options['auto_ack_current_source_sequence_next205'] ?? false) === true) {
            return $required;
        }

        return self::sequenceList($options['acknowledged_current_source_sequence_next205'] ?? [], 'acknowledged current source sequence');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function sequenceList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next205 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{32}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next205 {$label} contains a malformed sequence token");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next205 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next205 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $sequence
     * @return list<array<string,mixed>>
     */
    private static function tagCurrent(array $rows, array $sequence, string $sourceToken, string $nextSourceToken, string $cursor): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_sequence_phase_next205' => 'current',
                'current_source_sequence_token_next205' => $sourceToken,
                'next_source_sequence_token_next205' => $nextSourceToken,
                'source_sequence_cursor_next205' => $cursor,
                'current_source_sequence_next205' => $sequence[$index] ?? null,
                'visible_after_source_sequence_next205' => true,
                'held_by_source_sequence_reasons_next205' => [],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagNext(array $rows, bool $visible, string $sourceToken, string $nextSourceToken, string $cursor, array $reasons): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'source_sequence_phase_next205' => 'next',
                'current_source_sequence_token_next205' => $sourceToken,
                'next_source_sequence_token_next205' => $nextSourceToken,
                'source_sequence_cursor_next205' => $cursor,
                'current_source_sequence_next205' => null,
                'visible_after_source_sequence_next205' => $visible,
                'held_by_source_sequence_reasons_next205' => $visible ? [] : $reasons,
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
        bool $sourceFenceClear,
        array $missing,
        array $unexpected,
        bool $requireOrder,
        bool $orderMatches,
        bool $sourceTokenMatches,
        bool $nextSourceTokenMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next205 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next203-current-generation-not-visible';
        }
        if (!$sourceTokenMatches) {
            $reasons[] = 'current-source-sequence-token-mismatch';
        }
        if (!$nextSourceTokenMatches) {
            $reasons[] = 'next-source-sequence-token-mismatch';
        }
        if (!$sourceFenceClear) {
            if ($missing !== []) {
                $reasons[] = 'current-source-sequence-missing';
            }
            if ($unexpected !== []) {
                $reasons[] = 'current-source-sequence-unexpected';
            }
            if ($requireOrder && !$orderMatches) {
                $reasons[] = 'current-source-sequence-order-mismatch';
            }
        }

        return array_values(array_unique($reasons));
    }

    private static function status(bool $baseVisible, bool $sourceFenceClear, bool $sourceTokenMatches, bool $nextSourceTokenMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next205-source-sequence-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next205-base-held';
        }
        if (!$sourceTokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next205-current-source-held';
        }
        if (!$nextSourceTokenMatches) {
            return 'trigger-recursive-view-returning-current-source-next205-next-source-held';
        }
        if (!$sourceFenceClear) {
            return 'trigger-recursive-view-returning-current-source-next205-sequence-held';
        }

        return 'trigger-recursive-view-returning-current-source-next205-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next205 {$label} is malformed");
        }

        return $token;
    }
}
