<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext208Plan
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
        $currentCursor = self::token((string) ($base['yield_current_cursor_next206'] ?? ''), 'base current cursor');
        $closeCursor = self::token((string) ($options['close_current_cursor_next208'] ?? $currentCursor), 'close current cursor');
        $expectedCloseCursor = self::token((string) ($options['expected_close_current_cursor_next208'] ?? $currentCursor), 'expected close cursor');
        $closeStatement = self::token((string) ($options['close_statement_token_next208'] ?? 'wp.recursive.view.returning.close.208'), 'close statement token');
        $closedWatermark = self::closeWatermark($currentRows, $currentCursor, $closeCursor, $closeStatement, (string) ($base['yield_watermark_next206'] ?? ''));
        $expectedWatermark = self::token((string) ($options['expected_closed_yield_watermark_next208'] ?? $closedWatermark), 'expected closed watermark');
        $cursorMatches = hash_equals($currentCursor, $closeCursor) && hash_equals($currentCursor, $expectedCloseCursor);
        $watermarkMatches = hash_equals($closedWatermark, $expectedWatermark);
        $baseVisible = (bool) ($base['next_source_visible_after_yield_watermark_next206'] ?? false);
        $nextVisible = $baseVisible && $cursorMatches && $watermarkMatches;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next206'] ?? [],
            $baseVisible,
            $cursorMatches,
            $watermarkMatches,
        );

        $taggedCurrent = self::tagRows($currentRows, 'current', true, [], $closeCursor, $closeStatement, $closedWatermark);
        $taggedNext = self::tagRows($nextRows, 'next', $nextVisible, $blockedReasons, $closeCursor, $closeStatement, $closedWatermark);
        $visibleRows = array_values(array_filter(
            array_merge($taggedCurrent, $taggedNext),
            static fn (array $row): bool => (bool) $row['visible_after_current_cursor_close_next208'],
        ));
        $heldRows = array_values(array_filter(
            $taggedNext,
            static fn (array $row): bool => !(bool) $row['visible_after_current_cursor_close_next208'],
        ));

        return [
            'status_next208' => self::status($nextVisible, $baseVisible, $cursorMatches, $watermarkMatches),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next208' => $baseVisible,
            'yield_current_cursor_next208' => $currentCursor,
            'close_current_cursor_next208' => $closeCursor,
            'expected_close_current_cursor_next208' => $expectedCloseCursor,
            'close_statement_token_next208' => $closeStatement,
            'closed_yield_watermark_next208' => $closedWatermark,
            'expected_closed_yield_watermark_next208' => $expectedWatermark,
            'current_cursor_close_matches_next208' => $cursorMatches,
            'closed_yield_watermark_matches_next208' => $watermarkMatches,
            'next_source_visible_after_current_cursor_close_next208' => $nextVisible,
            'current_source_rows_next208' => $taggedCurrent,
            'attempted_next_source_rows_next208' => $taggedNext,
            'visible_returning_rows_next208' => $visibleRows,
            'held_next_source_rows_next208' => $heldRows,
            'visible_returning_payloads_next208' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next208' => array_column($heldRows, 'returning'),
            'blocked_reasons_next208' => $blockedReasons,
            'current_cursor_close_plan_next208' => [
                'current_rows' => count($taggedCurrent),
                'attempted_next_rows' => count($taggedNext),
                'visible_rows' => count($visibleRows),
                'held_next_rows' => count($heldRows),
                'base_next_source_visible' => $baseVisible,
                'cursor_matches' => $cursorMatches,
                'closed_watermark_matches' => $watermarkMatches,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-returning-cursor-close'
                    : 'hold-next-source-until-current-returning-cursor-close',
            ],
            'yield_boundary_next208' => $nextVisible
                ? 'recursive-view-returning-next208-current-cursor-closed-then-next'
                : 'recursive-view-returning-next208-current-cursor-close-fences-next',
            'dependency_closure_next208' => 'no new support component needed; reuses next206 current-source yield watermark and adds current RETURNING cursor close fencing',
            'dependencies_next208' => array_values(array_unique(array_merge($base['dependencies_next206'], [
                'sqlite-trigger-recursive-view-returning-current-source-next208',
                'sqlite-returning-current-source-cursor-close-fence',
                'wordpress-recursive-view-returning-current-source-next208',
            ]))),
            'non_overlap_next208' => 'adds current RETURNING cursor close fencing after next206 yield watermark; avoids accepted next206 watermark, next203 generation handoff, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next208 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next208 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function closeWatermark(array $rows, string $currentCursor, string $closeCursor, string $closeStatement, string $yieldWatermark): string
    {
        if ($rows === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next208 current source rows are empty');
        }

        $parts = [$currentCursor, $closeCursor, $closeStatement, $yieldWatermark, (string) count($rows)];
        foreach ($rows as $row) {
            $parts[] = (string) ($row['yield_batch_key_next206'] ?? '');
        }

        return substr(hash('sha256', implode('|', $parts)), 0, 32);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, bool $visible, array $reasons, string $closeCursor, string $closeStatement, string $closedWatermark): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'cursor_close_phase_next208' => $phase,
                'close_current_cursor_next208' => $closeCursor,
                'close_statement_token_next208' => $closeStatement,
                'closed_yield_watermark_next208' => $closedWatermark,
                'visible_after_current_cursor_close_next208' => $visible,
                'held_by_current_cursor_close_reasons_next208' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @return list<string>
     */
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $cursorMatches, bool $watermarkMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next208 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'next206-yield-watermark-held';
        }
        if (!$cursorMatches) {
            $reasons[] = 'current-returning-cursor-close-mismatch';
        }
        if (!$watermarkMatches) {
            $reasons[] = 'closed-yield-watermark-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function status(bool $nextVisible, bool $baseVisible, bool $cursorMatches, bool $watermarkMatches): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next208-cursor-closed';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next208-base-held';
        }
        if (!$cursorMatches) {
            return 'trigger-recursive-view-returning-current-source-next208-cursor-held';
        }
        if (!$watermarkMatches) {
            return 'trigger-recursive-view-returning-current-source-next208-watermark-held';
        }

        return 'trigger-recursive-view-returning-current-source-next208-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next208 {$label} is malformed");
        }

        return $token;
    }
}
