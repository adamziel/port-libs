<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext194Plan
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
        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext190Plan::execute(
            $baseRows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options + [
                'admit_next_source' => true,
                'auto_ack_current' => true,
                'cursor_name' => 'wp_recursive_view_returning_cursor_194',
                'current_generation' => 'wp-current-returning-194',
                'next_generation' => 'wp-next-returning-194',
                'checkpoint_name' => 'wp_recursive_view_checkpoint_194',
                'handoff_token' => 'wp.returning.current.source.handoff.194',
                'savepoint' => 'wp_recursive_view_returning_next194',
                'drain_ticket_prefix' => 'wp.returning.current.source.drain.194',
                'resume_source_prefix' => 'wp.returning.current.source.resume.194',
            ],
        );

        $currentDoneCode = self::resultCode((string) ($options['current_result_code'] ?? 'SQLITE_DONE'), 'current result code');
        $expectedDoneCode = self::resultCode((string) ($options['expected_current_result_code'] ?? 'SQLITE_DONE'), 'expected current result code');
        $currentCookie = self::token((string) ($options['current_source_cookie'] ?? self::sourceCookie($currentView, $returning)), 'current source cookie');
        $expectedCookie = self::token((string) ($options['expected_current_source_cookie'] ?? self::sourceCookie($currentView, $returning)), 'expected current source cookie');
        $stepEpoch = self::token((string) ($options['current_step_epoch'] ?? self::stepEpoch($base)), 'current step epoch');
        $expectedStepEpoch = self::token((string) ($options['expected_current_step_epoch'] ?? self::stepEpoch($base)), 'expected current step epoch');

        $doneMatches = hash_equals($expectedDoneCode, $currentDoneCode) && $currentDoneCode === 'SQLITE_DONE';
        $cookieMatches = hash_equals($expectedCookie, $currentCookie);
        $epochMatches = hash_equals($expectedStepEpoch, $stepEpoch);
        $baseExposed = (bool) ($base['next_source_exposed_after_resume_source'] ?? false);
        $admitNext = $baseExposed && $doneMatches && $cookieMatches && $epochMatches;
        $blockReasons = self::blockReasons($base['block_reasons'] ?? [], $baseExposed, $doneMatches, $cookieMatches, $epochMatches);

        $currentRows = self::rows($base['current_source_rows'] ?? [], 'current source rows');
        $nextRows = self::rows($base['attempted_next_source_rows'] ?? [], 'attempted next source rows');
        $gatedCurrentRows = self::tagRows($currentRows, $currentDoneCode, $currentCookie, $stepEpoch, true, []);
        $gatedNextRows = self::tagRows($nextRows, $currentDoneCode, $currentCookie, $stepEpoch, $admitNext, $blockReasons);
        $visibleRows = array_values(array_filter(array_merge($gatedCurrentRows, $gatedNextRows), static fn (array $row): bool => $row['visible_after_current_done_next194']));
        $heldRows = array_values(array_filter($gatedNextRows, static fn (array $row): bool => !$row['visible_after_current_done_next194']));

        return [
            'status' => self::status($admitNext, $baseExposed, $doneMatches, $cookieMatches, $epochMatches),
            'base' => $base,
            'current_result_code_next194' => $currentDoneCode,
            'expected_current_result_code_next194' => $expectedDoneCode,
            'current_result_code_matches_next194' => $doneMatches,
            'current_source_cookie_next194' => $currentCookie,
            'expected_current_source_cookie_next194' => $expectedCookie,
            'current_source_cookie_matches_next194' => $cookieMatches,
            'current_step_epoch_next194' => $stepEpoch,
            'expected_current_step_epoch_next194' => $expectedStepEpoch,
            'current_step_epoch_matches_next194' => $epochMatches,
            'base_next_exposed_before_current_done_next194' => $baseExposed,
            'next_source_exposed_after_current_done_next194' => $admitNext,
            'current_source_rows' => $gatedCurrentRows,
            'attempted_next_source_rows' => $gatedNextRows,
            'visible_rows' => $visibleRows,
            'held_rows' => $heldRows,
            'visible_returning_rows' => array_column($visibleRows, 'returning'),
            'held_returning_rows' => array_column($heldRows, 'returning'),
            'block_reasons_next194' => $blockReasons,
            'current_done_plan_next194' => [
                'current_rows' => count($gatedCurrentRows),
                'attempted_next_rows' => count($gatedNextRows),
                'visible_rows' => count($visibleRows),
                'held_next_rows' => count($heldRows),
                'current_result_code' => $currentDoneCode,
                'current_source_cookie_matches' => $cookieMatches,
                'current_step_epoch_matches' => $epochMatches,
                'decision' => $admitNext ? 'admit-next-source-after-current-done' : 'hold-next-source-until-current-done',
                'blocked_at_resume_token' => $admitNext || $gatedNextRows === [] ? null : (string) ($gatedNextRows[0]['resume_token'] ?? ''),
            ],
            'counts_next194' => [
                'current_rows' => count($gatedCurrentRows),
                'attempted_next_rows' => count($gatedNextRows),
                'visible_rows' => count($visibleRows),
                'held_rows' => count($heldRows),
                'block_reasons' => count($blockReasons),
            ],
            'yield_boundary_next194' => $admitNext
                ? 'recursive-view-returning-current-source-next194-current-done-next-exposed'
                : 'recursive-view-returning-current-source-next194-current-done-held',
            'dependencies_next194' => array_values(array_unique(array_merge($base['dependencies'] ?? [], [
                'sqlite-trigger-recursive-view-returning-current-source-next194',
                'sqlite-returning-current-source-done-gate',
                'wordpress-recursive-view-returning-current-source-next194',
            ]))),
            'dependency_closure_next194' => 'no new support component needed; reuses recursive view trigger RETURNING resume rows and adds current-source SQLITE_DONE/source-cookie gating',
            'non_overlap_next194' => 'extends accepted next190 resume-source validation with final current-source SQLITE_DONE, source-cookie, and step-epoch gating; avoids accepted next190 resume-token, next187 drain-ticket, next184 checkpoint admission, row-value RETURNING, WAL, pager, B-tree, JSON, PRAGMA, and encoding slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $blockReasons
     * @return list<array<string,mixed>>
     */
    private static function tagRows(array $rows, string $resultCode, string $cookie, string $epoch, bool $visible, array $blockReasons): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row + [
                'current_result_code_next194' => $resultCode,
                'current_source_cookie_next194' => $cookie,
                'current_step_epoch_next194' => $epoch,
                'visible_after_current_done_next194' => $visible,
                'held_by_current_done_reasons_next194' => $visible ? [] : $blockReasons,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next194 {$label} must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['returning'], $row['resume_token'])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next194 {$label} row envelope is malformed");
            }
        }

        return $rows;
    }

    /**
     * @param mixed $baseReasons
     * @return list<string>
     */
    private static function blockReasons(mixed $baseReasons, bool $baseExposed, bool $doneMatches, bool $cookieMatches, bool $epochMatches): array
    {
        if (!is_array($baseReasons) || !array_is_list($baseReasons)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next194 base block reasons must be a list');
        }

        $reasons = array_map(static fn (mixed $reason): string => (string) $reason, $baseReasons);
        if (!$baseExposed && $reasons === []) {
            $reasons[] = 'resume-source-not-exposed';
        }
        if (!$doneMatches) {
            $reasons[] = 'current-source-not-done';
        }
        if (!$cookieMatches) {
            $reasons[] = 'current-source-cookie-mismatch';
        }
        if (!$epochMatches) {
            $reasons[] = 'current-step-epoch-mismatch';
        }

        return array_values(array_unique($reasons));
    }

    private static function status(bool $admitted, bool $baseExposed, bool $doneMatches, bool $cookieMatches, bool $epochMatches): string
    {
        if ($admitted) {
            return 'trigger-recursive-view-returning-current-source-next194-next-exposed';
        }
        if (!$baseExposed) {
            return 'trigger-recursive-view-returning-current-source-next194-resume-source-held';
        }
        if (!$doneMatches) {
            return 'trigger-recursive-view-returning-current-source-next194-current-not-done';
        }
        if (!$cookieMatches) {
            return 'trigger-recursive-view-returning-current-source-next194-source-cookie-held';
        }
        if (!$epochMatches) {
            return 'trigger-recursive-view-returning-current-source-next194-step-epoch-held';
        }

        return 'trigger-recursive-view-returning-current-source-next194-held';
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable> $returning
     */
    private static function sourceCookie(array $view, array $returning): string
    {
        $material = [
            (string) ($view['name'] ?? ''),
            (string) ($view['source'] ?? ''),
            (string) ($view['trigger_source'] ?? ''),
            count($returning),
        ];

        return 'cookie194:' . substr(hash('sha256', implode('|', $material)), 0, 16);
    }

    /**
     * @param array<string,mixed> $base
     */
    private static function stepEpoch(array $base): string
    {
        $resumePlan = is_array($base['resume_plan'] ?? null) ? $base['resume_plan'] : [];
        $material = [
            (string) ($base['last_current_resume_token'] ?? ''),
            (string) ($base['first_next_resume_token'] ?? ''),
            (string) ($resumePlan['visible_row_count'] ?? ''),
            (string) ($resumePlan['decision'] ?? ''),
        ];

        return 'epoch194:' . substr(hash('sha256', implode('|', $material)), 0, 16);
    }

    private static function resultCode(string $value, string $label): string
    {
        if (!in_array($value, ['SQLITE_ROW', 'SQLITE_DONE', 'SQLITE_BUSY', 'SQLITE_SCHEMA', 'SQLITE_ERROR'], true)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next194 {$label} is malformed");
        }

        return $value;
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next194 {$label} is malformed");
        }

        return $value;
    }
}
