<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewUpsertCurrentSourceNext243Plan
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
        $base = SQLiteTriggerRecursiveViewUpsertCurrentSourceNext240Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentCookie = self::token((string) ($options['current_source_view_cookie_next243'] ?? ($currentView['source'] ?? 'main@view-cookie-243-current')), 'current source view cookie');
        $expectedCurrentCookie = self::token((string) ($options['expected_current_source_view_cookie_next243'] ?? $currentCookie), 'expected current source view cookie');
        $currentTrigger = self::token((string) ($options['current_source_trigger_cookie_next243'] ?? ($currentView['trigger_source'] ?? 'main@trigger-cookie-243-current')), 'current source trigger cookie');
        $expectedCurrentTrigger = self::token((string) ($options['expected_current_source_trigger_cookie_next243'] ?? $currentTrigger), 'expected current source trigger cookie');
        $nextCookie = self::token((string) ($options['next_source_view_cookie_next243'] ?? ($nextView['source'] ?? 'main@view-cookie-243-next')), 'next source view cookie');
        $cursor = self::token((string) ($options['upsert_source_cursor_next243'] ?? 'wp.upsert.source.cursor.243'), 'upsert source cursor');
        $baseVisible = (bool) ($base['next_source_visible_after_current_source_upsert_next240'] ?? false);
        $viewMatches = hash_equals($currentCookie, $expectedCurrentCookie);
        $triggerMatches = hash_equals($currentTrigger, $expectedCurrentTrigger);
        $sourceCurrent = $viewMatches && $triggerMatches;
        $nextVisible = $baseVisible && $sourceCurrent;
        $reasons = self::blockedReasons($base['blocked_reasons_next240'] ?? [], $baseVisible, $viewMatches, $triggerMatches);
        $currentRows = self::tagRows(self::rows($base['current_source_rows_next240'] ?? [], 'current rows'), 'current', true, $cursor, $currentCookie, $currentTrigger, $nextCookie, []);
        $nextRows = self::tagRows(self::rows($base['attempted_next_source_rows_next240'] ?? [], 'next rows'), 'next', $nextVisible, $cursor, $currentCookie, $currentTrigger, $nextCookie, $nextVisible ? [] : $reasons);
        $visibleRows = array_values(array_filter(
            array_merge($currentRows, $nextRows),
            static fn (array $row): bool => (bool) $row['visible_after_upsert_current_source_next243'],
        ));
        $heldRows = array_values(array_filter(
            $nextRows,
            static fn (array $row): bool => !(bool) $row['visible_after_upsert_current_source_next243'],
        ));

        return [
            'status_next243' => self::status($baseVisible, $viewMatches, $triggerMatches, $nextVisible),
            'savepoint' => $base['savepoint'],
            'base' => $base,
            'base_next_source_visible_next243' => $baseVisible,
            'current_source_view_cookie_next243' => $currentCookie,
            'expected_current_source_view_cookie_next243' => $expectedCurrentCookie,
            'current_source_view_cookie_matches_next243' => $viewMatches,
            'current_source_trigger_cookie_next243' => $currentTrigger,
            'expected_current_source_trigger_cookie_next243' => $expectedCurrentTrigger,
            'current_source_trigger_cookie_matches_next243' => $triggerMatches,
            'next_source_view_cookie_next243' => $nextCookie,
            'upsert_source_cursor_next243' => $cursor,
            'current_source_still_current_next243' => $sourceCurrent,
            'next_source_visible_after_upsert_current_source_next243' => $nextVisible,
            'current_source_rows_next243' => $currentRows,
            'attempted_next_source_rows_next243' => $nextRows,
            'visible_returning_rows_next243' => $visibleRows,
            'held_next_source_rows_next243' => $heldRows,
            'visible_returning_payloads_next243' => array_column($visibleRows, 'returning'),
            'held_next_returning_payloads_next243' => array_column($heldRows, 'returning'),
            'current_source_row_count_next243' => count($currentRows),
            'attempted_next_source_row_count_next243' => count($nextRows),
            'visible_row_count_next243' => count($visibleRows),
            'held_next_row_count_next243' => count($heldRows),
            'blocked_reasons_next243' => $reasons,
            'upsert_current_source_plan_next243' => [
                'base_next_source_visible' => $baseVisible,
                'current_view_cookie_matches' => $viewMatches,
                'current_trigger_cookie_matches' => $triggerMatches,
                'current_source_still_current' => $sourceCurrent,
                'next_source_visible' => $nextVisible,
                'decision' => $nextVisible
                    ? 'publish-next-source-after-current-view-upsert-source-match'
                    : 'hold-next-source-until-current-view-upsert-source-match',
            ],
            'yield_boundary_next243' => $nextVisible
                ? 'recursive-view-upsert-next243-current-source-then-next'
                : 'recursive-view-upsert-next243-current-source-fences-next',
            'dependency_closure_next243' => 'no-new-support-component-reuses-native-recursive-view-upsert-current-source-cookies',
            'dependencies_next243' => array_values(array_unique(array_merge($base['dependencies_next240'] ?? [], [
                'sqlite-trigger-recursive-view-upsert-current-source-next243',
                'sqlite-instead-of-view-upsert-current-source-cookie-fence',
                'wordpress-recursive-view-upsert-current-source-next243',
            ]))),
            'non_overlap_next243' => 'adds current view/trigger source-cookie fencing after accepted next240 UPSERT conflict receipts; avoids accepted next240 receipt admission, trigger RETURNING, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters',
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next243 {$label} must be a list");
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $reasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $phase, bool $visible, string $cursor, string $viewCookie, string $triggerCookie, string $nextCookie, array $reasons): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'upsert_source_phase_next243' => $phase,
                'upsert_source_cursor_next243' => $cursor,
                'current_source_view_cookie_next243' => $viewCookie,
                'current_source_trigger_cookie_next243' => $triggerCookie,
                'next_source_view_cookie_next243' => $nextCookie,
                'visible_after_upsert_current_source_next243' => $visible,
                'held_by_upsert_current_source_reasons_next243' => $visible ? [] : $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $baseReasons
     * @return list<string>
     */
    private static function blockedReasons(mixed $baseReasons, bool $baseVisible, bool $viewMatches, bool $triggerMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT next243 base blocked reasons are malformed');
        }
        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseVisible && $reasons === []) {
            $reasons[] = 'base-next240-current-source-upsert-not-published';
        }
        if (!$viewMatches) {
            $reasons[] = 'current-view-source-cookie-mismatch';
        }
        if (!$triggerMatches) {
            $reasons[] = 'current-trigger-source-cookie-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function status(bool $baseVisible, bool $viewMatches, bool $triggerMatches, bool $nextVisible): string
    {
        if ($nextVisible) {
            return 'trigger-recursive-view-upsert-current-source-next243-source-released';
        }
        if (!$baseVisible) {
            return 'trigger-recursive-view-upsert-current-source-next243-base-held';
        }
        if (!$viewMatches) {
            return 'trigger-recursive-view-upsert-current-source-next243-view-source-held';
        }
        if (!$triggerMatches) {
            return 'trigger-recursive-view-upsert-current-source-next243-trigger-source-held';
        }

        return 'trigger-recursive-view-upsert-current-source-next243-source-held';
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT next243 {$label} is malformed");
        }

        return $value;
    }
}
