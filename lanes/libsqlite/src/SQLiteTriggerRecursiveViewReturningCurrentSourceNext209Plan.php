<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext209Plan
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

        $drainSource = self::token((string) ($options['current_source_drain_token_next209'] ?? 'wp.current.source.drain.209'), 'current source drain token');
        $viewCookie = self::token((string) ($options['current_view_cookie_next209'] ?? (string) ($currentView['source'] ?? 'current-view-cookie-209')), 'current view cookie');
        $triggerCookie = self::token((string) ($options['current_trigger_cookie_next209'] ?? (string) ($currentView['trigger_source'] ?? 'current-trigger-cookie-209')), 'current trigger cookie');
        $expectedViewCookie = self::token((string) ($options['expected_current_view_cookie_next209'] ?? $viewCookie), 'expected current view cookie');
        $expectedTriggerCookie = self::token((string) ($options['expected_current_trigger_cookie_next209'] ?? $triggerCookie), 'expected current trigger cookie');
        $baseVisible = (bool) ($base['next_source_visible_after_current_generation_next203'] ?? false);

        $requiredWatermarks = self::watermarks(
            self::rows($base['current_generation_rows_next203'] ?? [], 'current generation rows'),
            $drainSource,
            $viewCookie,
            $triggerCookie,
        );
        $acknowledgedWatermarks = self::acknowledgedWatermarks($options, $requiredWatermarks);
        $missingWatermarks = array_values(array_diff($requiredWatermarks, $acknowledgedWatermarks));
        $unexpectedWatermarks = array_values(array_diff($acknowledgedWatermarks, $requiredWatermarks));
        $viewCookieMatches = hash_equals($viewCookie, $expectedViewCookie);
        $triggerCookieMatches = hash_equals($triggerCookie, $expectedTriggerCookie);
        $drainComplete = $requiredWatermarks !== []
            && $missingWatermarks === []
            && $unexpectedWatermarks === [];
        $nextVisible = $baseVisible && $drainComplete && $viewCookieMatches && $triggerCookieMatches;
        $blockedReasons = self::blockedReasons(
            $base['blocked_reasons_next203'] ?? [],
            $baseVisible,
            $drainComplete,
            $missingWatermarks,
            $unexpectedWatermarks,
            $viewCookieMatches,
            $triggerCookieMatches,
        );

        $currentRows = self::tagCurrentRows(
            self::rows($base['current_generation_rows_next203'] ?? [], 'current generation rows'),
            $requiredWatermarks,
            $drainSource,
            $viewCookie,
            $triggerCookie,
        );
        $nextRows = self::tagNextRows(
            self::rows($base['attempted_next_generation_rows_next203'] ?? [], 'attempted next generation rows'),
            $nextVisible,
            $drainSource,
            $viewCookie,
            $triggerCookie,
            $blockedReasons,
        );
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_current_source_drain_next209'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_current_source_drain_next209'],
        ));

        return [
            'status_next209' => self::status($baseVisible, $drainComplete, $viewCookieMatches, $triggerCookieMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next209' => $baseVisible,
            'current_source_drain_token_next209' => $drainSource,
            'current_view_cookie_next209' => $viewCookie,
            'expected_current_view_cookie_next209' => $expectedViewCookie,
            'current_view_cookie_matches_next209' => $viewCookieMatches,
            'current_trigger_cookie_next209' => $triggerCookie,
            'expected_current_trigger_cookie_next209' => $expectedTriggerCookie,
            'current_trigger_cookie_matches_next209' => $triggerCookieMatches,
            'required_current_source_watermarks_next209' => $requiredWatermarks,
            'acknowledged_current_source_watermarks_next209' => $acknowledgedWatermarks,
            'missing_current_source_watermarks_next209' => $missingWatermarks,
            'unexpected_current_source_watermarks_next209' => $unexpectedWatermarks,
            'current_source_drain_complete_next209' => $drainComplete,
            'next_source_visible_after_current_source_drain_next209' => $nextVisible,
            'current_source_rows_next209' => $currentRows,
            'attempted_next_source_rows_next209' => $nextRows,
            'visible_returning_rows_next209' => $visibleRows,
            'held_next_source_rows_next209' => $heldRows,
            'visible_returning_payloads_next209' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next209' => array_column($heldRows, 'returning'),
            'current_source_row_count_next209' => count($currentRows),
            'attempted_next_source_row_count_next209' => count($nextRows),
            'visible_row_count_next209' => count($visibleRows),
            'held_next_row_count_next209' => count($heldRows),
            'blocked_reasons_next209' => $blockedReasons,
            'current_source_drain_plan_next209' => [
                'base_next_source_visible' => $baseVisible,
                'required_watermarks' => $requiredWatermarks,
                'acknowledged_watermarks' => $acknowledgedWatermarks,
                'missing_watermarks' => $missingWatermarks,
                'unexpected_watermarks' => $unexpectedWatermarks,
                'view_cookie_matches' => $viewCookieMatches,
                'trigger_cookie_matches' => $triggerCookieMatches,
                'drain_complete' => $drainComplete,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-source-drain'
                    : 'hold-next-source-until-current-source-drain',
            ],
            'yield_boundary_next209' => $nextVisible
                ? 'recursive-view-returning-next209-current-source-drain-then-next'
                : 'recursive-view-returning-next209-current-source-drain-fences-next',
            'dependency_closure_next209' => 'no-new-support-component-reuses-native-recursive-view-returning-current-source-drain-watermarks',
            'dependencies_next209' => array_values(array_unique(array_merge($base['dependencies_next203'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next209',
                'sqlite-returning-current-source-drain-watermark',
                'wordpress-recursive-view-returning-current-source-next209',
            ]))),
            'non_overlap_next209' => 'adds current-source drain watermarks after next203 generation handoff; avoids accepted trigger recursive view RETURNING next172-next203 surfaces, row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function watermarks(array $rows, string $drainSource, string $viewCookie, string $triggerCookie): array
    {
        $watermarks = [];
        foreach ($rows as $index => $row) {
            $parts = [
                $drainSource,
                $viewCookie,
                $triggerCookie,
                (string) ($row['current_generation_receipt_next203'] ?? ''),
                (string) ($row['returning_row_ordinal'] ?? $index),
                (string) ($row['returning']['name'] ?? ''),
            ];
            $watermarks[] = substr(hash('sha256', implode('|', $parts)), 0, 32);
        }

        return $watermarks;
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string> $required
     * @return list<string>
     */
    private static function acknowledgedWatermarks(array $options, array $required): array
    {
        if (($options['auto_ack_current_source_watermarks_next209'] ?? false) === true) {
            return $required;
        }

        return self::watermarkList($options['acknowledged_current_source_watermarks_next209'] ?? [], 'acknowledged current source watermarks');
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function watermarkList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next209 {$label} must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[a-f0-9]{32}$/', $value) !== 1) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next209 {$label} contain a malformed watermark");
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
            throw new InvalidArgumentException("SQLite recursive view RETURNING next209 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning']) || !is_array($row['returning'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next209 {$label} contain a malformed row");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $watermarks
     * @return list<array<string,mixed>>
     */
    private static function tagCurrentRows(array $rows, array $watermarks, string $drainSource, string $viewCookie, string $triggerCookie): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = $row + [
                'source_drain_phase_next209' => 'current',
                'current_source_drain_token_next209' => $drainSource,
                'current_view_cookie_next209' => $viewCookie,
                'current_trigger_cookie_next209' => $triggerCookie,
                'current_source_watermark_next209' => $watermarks[$index] ?? null,
                'visible_after_current_source_drain_next209' => true,
                'held_by_current_source_drain_reasons_next209' => [],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagNextRows(array $rows, bool $visible, string $drainSource, string $viewCookie, string $triggerCookie, array $reasons): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'source_drain_phase_next209' => 'next',
                'current_source_drain_token_next209' => $drainSource,
                'current_view_cookie_next209' => $viewCookie,
                'current_trigger_cookie_next209' => $triggerCookie,
                'current_source_watermark_next209' => null,
                'visible_after_current_source_drain_next209' => $visible,
                'held_by_current_source_drain_reasons_next209' => $visible ? [] : $reasons,
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
        bool $drainComplete,
        array $missing,
        array $unexpected,
        bool $viewCookieMatches,
        bool $triggerCookieMatches,
    ): array {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next209 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next203-current-generation-not-published';
        }
        if (!$drainComplete) {
            if ($missing !== []) {
                $reasons[] = 'current-source-watermark-missing';
            }
            if ($unexpected !== []) {
                $reasons[] = 'current-source-watermark-unexpected';
            }
        }
        if (!$viewCookieMatches) {
            $reasons[] = 'current-view-cookie-mismatch';
        }
        if (!$triggerCookieMatches) {
            $reasons[] = 'current-trigger-cookie-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function status(bool $baseVisible, bool $drainComplete, bool $viewCookieMatches, bool $triggerCookieMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-returning-current-source-next209-drain-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-returning-current-source-next209-base-held';
        }
        if (!$drainComplete) {
            return 'trigger-recursive-view-returning-current-source-next209-drain-held';
        }
        if (!$viewCookieMatches) {
            return 'trigger-recursive-view-returning-current-source-next209-view-cookie-held';
        }
        if (!$triggerCookieMatches) {
            return 'trigger-recursive-view-returning-current-source-next209-trigger-cookie-held';
        }

        return 'trigger-recursive-view-returning-current-source-next209-held';
    }

    private static function token(string $token, string $label): string
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next209 {$label} is malformed");
        }

        return $token;
    }
}
